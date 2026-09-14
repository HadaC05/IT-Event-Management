(() => {
  "use strict";

  const $ = (selector, root = document) => root.querySelector(selector);
  const $$ = (selector, root = document) => [...root.querySelectorAll(selector)];
  const composer = $("[data-announcement-form]");
  const filters = $("[data-announcement-filters]");
  const list = $("[data-announcement-list]");
  const initialQuery = Object.fromEntries(new URLSearchParams(location.search));
  const state = {
    values: {
      search: initialQuery.search || "",
      status: ["all", "draft", "published", "archived"].includes(initialQuery.status) ? initialQuery.status : "all",
      event_id: initialQuery.event_id || "",
      scope: initialQuery.scope === "event" ? "event" : "",
    },
    page: Math.max(1, Number(initialQuery.page) || 1),
    requestId: 0,
    debounce: 0,
  };
  let csrfToken = "";
  let events = [];
  let previewUrl = "";

  const escapeHtml = (value) => String(value ?? "").replace(
    /[&<>'"]/g,
    (character) => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", "'": "&#39;", '"': "&quot;" })[character],
  );

  function formatDate(value) {
    const date = new Date(String(value).replace(" ", "T"));
    return Number.isNaN(date.getTime()) ? "Date not set" : new Intl.DateTimeFormat("en-PH", { month: "short", day: "numeric", year: "numeric" }).format(date);
  }

  function timeAgo(value) {
    if (!value) return "just now";
    const seconds = Math.max(0, Math.floor((Date.now() - new Date(String(value).replace(" ", "T"))) / 1000));
    for (const [size, label] of [[31536000, "year"], [2592000, "month"], [86400, "day"], [3600, "hour"], [60, "minute"]]) {
      if (seconds >= size) {
        const count = Math.floor(seconds / size);
        return `${count} ${label}${count === 1 ? "" : "s"} ago`;
      }
    }
    return "just now";
  }

  function initializeShell() {
    const sidebar = $("#sidebar");
    const scrim = $("[data-sidebar-scrim]");
    $$('[data-sidebar-toggle]').forEach((button) => {
      button.onclick = () => {
        const opening = sidebar.classList.contains("-translate-x-full");
        sidebar.classList.toggle("-translate-x-full", !opening);
        sidebar.classList.toggle("translate-x-0", opening);
        scrim.classList.toggle("hidden", !opening);
      };
    });
    const accountMenu = $("[data-account-menu]");
    const tips = $("[data-publishing-tips]");
    document.addEventListener("click", (event) => {
      if (accountMenu?.open && !accountMenu.contains(event.target)) accountMenu.removeAttribute("open");
      if (tips?.open && !tips.contains(event.target)) tips.removeAttribute("open");
    });
  }

  function applyAccount(user) {
    const account = $("[data-account-menu]");
    const name = user.full_name || user.username;
    account.querySelector("summary > span:first-child").childNodes[0].textContent = `${user.first_name?.[0] || ""}${user.last_name?.[0] || ""}`.toUpperCase();
    account.querySelector("summary strong").textContent = name;
    account.querySelector("summary small").textContent = user.role;
    account.querySelector(":scope > div > div strong").textContent = name;
    account.querySelector(":scope > div > div span").textContent = user.email || user.username;
  }

  async function authenticate() {
    const response = await axios.get("api/auth.php?action=session");
    if (!response.data.authenticated || response.data.user?.role !== "SBO Adviser") {
      location.replace("./");
      throw new Error("Unauthorized");
    }
    csrfToken = response.data.csrf_token;
    $("meta[name='csrf-token']").content = csrfToken;
    applyAccount(response.data.user);
    $("form[action='api/auth.php?action=logout']").onsubmit = async (event) => {
      event.preventDefault();
      try {
        await axios.post("api/auth.php?action=logout", {}, { headers: { "X-CSRF-Token": csrfToken } });
      } finally {
        location.replace("./");
      }
    };
  }

  function eventOptions(selected = "", includeDate = false) {
    return events.map((event) => `<option value="${event.id}" ${Number(selected) === event.id ? "selected" : ""}>${escapeHtml(event.title)}${includeDate ? ` · ${formatDate(event.start_at)}` : ""}</option>`).join("");
  }

  function fillControls(data) {
    const composerEvent = composer.event_id.value;
    events = data.events;
    composer.event_id.innerHTML = '<option value="">General student feed</option>' + eventOptions(composerEvent, true);
    composer.event_id.value = events.some((event) => String(event.id) === String(composerEvent)) ? composerEvent : "";
    filters.event_id.innerHTML = '<option value="">All events and feeds</option>' + eventOptions(state.values.event_id);
    filters.search.value = state.values.search;
    filters.status.value = state.values.status;
    filters.event_id.value = state.values.event_id;
    updateAudienceHelp();
  }

  function syncUrl() {
    const url = new URL(location.href);
    url.search = "";
    for (const [name, value] of Object.entries(state.values)) {
      if (value && !(name === "status" && value === "all")) url.searchParams.set(name, value);
    }
    if (state.page > 1) url.searchParams.set("page", state.page);
    history.replaceState(null, "", url);
  }

  function renderSummary(data) {
    Object.entries(data.summary).forEach(([key, value]) => {
      const element = $(`[data-summary="${key}"]`);
      if (element) element.textContent = Number(value).toLocaleString();
    });
    $("[data-pending-posts]").textContent = Number(data.pending_posts).toLocaleString();
    $("[data-result-count]").textContent = `${data.pagination.total.toLocaleString()} ${data.pagination.total === 1 ? "announcement" : "announcements"}`;
    $$('[data-summary-filter]').forEach((button) => {
      const key = button.dataset.summaryFilter;
      const active = key === "event"
        ? state.values.scope === "event"
        : state.values.scope !== "event" && state.values.status === key;
      button.style.boxShadow = active ? "inset 0 -3px 0 #397565" : "";
      button.setAttribute("aria-pressed", String(active));
    });
  }

  function editForm(announcement) {
    const remove = announcement.image_path
      ? '<label class="flex items-center gap-2 text-xs font-bold text-[#121017]/55"><input type="checkbox" name="remove_image" value="1"> Remove current image</label>'
      : "";
    return `<details class="group"><summary class="inline-flex min-h-10 cursor-pointer list-none items-center rounded-xl border border-[#2F3AE0]/20 px-4 text-xs font-black text-[#2F3AE0] hover:bg-[#2F3AE0]/5">Edit</summary><form class="mt-3 grid w-full gap-4 rounded-2xl border border-[#121017]/9 bg-[#F3F0E9]/45 p-4 lg:w-[520px]" enctype="multipart/form-data" data-edit-form data-id="${announcement.id}" novalidate><label class="grid gap-2"><span class="text-xs font-black">Message</span><textarea class="min-h-32 rounded-xl border border-[#121017]/10 bg-white p-4 text-sm leading-6 outline-none focus:border-[#397565]" name="content" maxlength="3000" required>${escapeHtml(announcement.content)}</textarea><span class="hidden text-xs font-bold text-[#D64A12]" data-error="content"></span></label><label class="grid gap-2"><span class="text-xs font-black">Audience</span><select class="h-11 rounded-xl border border-[#121017]/10 bg-white px-3 text-xs font-bold" name="event_id"><option value="">General student feed</option>${eventOptions(announcement.event_id)}</select><span class="hidden text-xs font-bold text-[#D64A12]" data-error="event_id"></span></label><label class="grid gap-2"><span class="text-xs font-black">Replace image</span><input class="rounded-xl border border-[#121017]/10 bg-white p-3 text-xs" type="file" name="image" accept="image/jpeg,image/png,image/webp"><span class="hidden text-xs font-bold text-[#D64A12]" data-error="image"></span></label>${remove}<div class="flex flex-wrap justify-end gap-2"><button class="min-h-10 rounded-xl border border-[#121017]/10 bg-white px-4 text-xs font-black" type="submit" name="intent" value="draft" data-loading-text="Saving…">Save as draft</button><button class="min-h-10 rounded-xl bg-[#397565] px-4 text-xs font-black text-white" type="submit" name="intent" value="publish" data-loading-text="Publishing…">Save and publish</button></div></form></details>`;
  }

  function renderAnnouncement(announcement) {
    const published = announcement.status === "approved" && !announcement.is_archived;
    const label = announcement.is_archived ? "Archived" : published ? "Published" : "Draft";
    const tone = announcement.is_archived
      ? "bg-[#121017]/7 text-[#121017]/48"
      : published ? "bg-[#397565]/10 text-[#397565]" : "bg-[#121017]/5 text-[#121017]/45";
    const image = announcement.image_path
      ? `<div class="mt-4 flex items-center gap-3"><img class="h-16 w-20 rounded-xl object-cover" src="${escapeHtml(announcement.image_path)}" alt="Announcement attachment"><span><strong class="block text-xs">Image attached</strong><small class="text-[10px] text-[#121017]/40">Shown with this announcement</small></span></div>`
      : "";
    const actions = announcement.is_archived
      ? `<button class="min-h-10 rounded-xl bg-[#397565] px-4 text-xs font-black text-white" type="button" data-action="restore" data-id="${announcement.id}" data-loading-text="Restoring…">Restore as draft</button>`
      : `<button class="min-h-10 rounded-xl px-4 text-xs font-black ${published ? "border border-[#121017]/10 text-[#121017]/55 hover:bg-[#121017]/5" : "bg-[#397565] text-white hover:bg-[#2f6255]"}" type="button" data-action="status" data-status="${published ? "draft" : "approved"}" data-id="${announcement.id}" data-loading-text="${published ? "Unpublishing…" : "Publishing…"}">${published ? "Unpublish" : "Publish"}</button>${editForm(announcement)}<button class="min-h-10 rounded-xl px-3 text-xs font-black text-[#D64A12] hover:bg-[#FF6B2C]/8" type="button" data-action="archive" data-id="${announcement.id}" data-loading-text="Archiving…">Archive</button>`;
    return `<article class="p-5 transition hover:bg-[#397565]/[.018] sm:p-6"><div class="grid gap-5 lg:grid-cols-[minmax(0,1fr)_auto]"><div class="min-w-0"><div class="flex flex-wrap items-center gap-2"><span class="rounded-full px-3 py-1 text-[9px] font-black uppercase tracking-wider ${tone}">${label}</span><span class="text-xs font-bold text-[#121017]/42">${escapeHtml(announcement.event_title || "General student feed")}</span><span class="text-xs text-[#121017]/30">· Updated ${timeAgo(announcement.updated_at)}</span></div><p class="mt-3 whitespace-pre-line text-sm leading-7 text-[#121017]/72">${escapeHtml(announcement.content)}</p>${image}</div><div class="flex flex-wrap items-start gap-2 lg:max-w-72 lg:justify-end">${actions}</div></div></article>`;
  }

  function hasActiveFilters() {
    return Boolean(state.values.search || state.values.event_id || state.values.scope || state.values.status !== "all");
  }

  function renderList(data) {
    if (!data.announcements.length) {
      const filtered = hasActiveFilters() && Object.values(data.summary).some((value) => Number(value) > 0);
      list.innerHTML = `<div class="px-6 py-16 text-center"><span class="mx-auto grid h-14 w-14 place-items-center rounded-full bg-[#397565]/8 text-[#397565]"><svg class="h-6 w-6 fill-none stroke-current stroke-2" viewBox="0 0 24 24"><path d="M4 13V9l11-5v14L4 13Zm0 0v5h4v-3m7-7h3a3 3 0 0 1 0 6h-3"/></svg></span><h3 class="mt-5 text-lg font-black">${filtered ? "No announcements match" : "No announcements yet"}</h3><p class="mx-auto mt-2 max-w-md text-sm leading-6 text-[#121017]/45">${filtered ? "Change or clear the current filters to see more announcements." : "Published updates and saved drafts will appear here."}</p>${filtered ? '<button class="mt-5 min-h-10 rounded-xl border border-[#397565]/25 px-4 text-xs font-black text-[#397565]" type="button" data-clear-library-filters>Clear filters</button>' : ""}</div>`;
    } else {
      list.innerHTML = data.announcements.map(renderAnnouncement).join("");
    }
    renderPagination(data.pagination);
  }

  function renderPagination(pagination) {
    const nav = $("[data-pagination]");
    nav.classList.toggle("hidden", pagination.last_page <= 1);
    nav.classList.toggle("flex", pagination.last_page > 1);
    if (pagination.last_page <= 1) return;
    nav.innerHTML = `<span class="font-bold text-[#121017]/40">Page ${pagination.page} of ${pagination.last_page}</span><span class="flex gap-2"><button class="min-h-10 rounded-xl border border-[#121017]/10 px-4 font-black disabled:opacity-30" data-page="${pagination.page - 1}" ${pagination.page === 1 ? "disabled" : ""}>Previous</button><button class="min-h-10 rounded-xl border border-[#121017]/10 px-4 font-black disabled:opacity-30" data-page="${pagination.page + 1}" ${pagination.page === pagination.last_page ? "disabled" : ""}>Next</button></span>`;
  }

  function clearErrors(form) {
    $$('[data-error]', form).forEach((element) => {
      element.textContent = "";
      element.classList.add("hidden");
    });
  }

  function showErrors(form, errors = {}) {
    Object.entries(errors).forEach(([field, messages]) => {
      const element = $(`[data-error="${field}"]`, form);
      if (!element) return;
      element.textContent = Array.isArray(messages) ? messages[0] : messages;
      element.classList.remove("hidden");
    });
  }

  function validateForm(form) {
    const errors = {};
    const content = form.content.value.trim();
    if (!content) errors.content = ["The announcement message is required."];
    else if (content.length > 3000) errors.content = ["The announcement message may not exceed 3,000 characters."];
    const image = form.image?.files?.[0];
    if (image && image.size > 5 * 1024 * 1024) errors.image = ["The image may not exceed 5 MB."];
    if (image && !["image/jpeg", "image/png", "image/webp"].includes(image.type)) errors.image = ["Use a JPG, PNG, or WebP image."];
    showErrors(form, errors);
    return !Object.keys(errors).length;
  }

  async function send(formData) {
    return axios.post("api/announcements.php", formData, { headers: { "X-CSRF-Token": csrfToken } });
  }

  async function load() {
    const requestId = ++state.requestId;
    syncUrl();
    list.style.opacity = "0.55";
    try {
      const response = await axios.get("api/announcements.php", { params: { ...state.values, page: state.page } });
      if (requestId !== state.requestId) return;
      const data = response.data.data;
      state.page = data.pagination.page;
      fillControls(data);
      renderSummary(data);
      renderList(data);
    } catch (error) {
      if (requestId !== state.requestId) return;
      const message = error.response?.data?.message || "Announcements could not be loaded.";
      list.innerHTML = `<p class="px-6 py-16 text-center text-sm text-[#D64A12]">${escapeHtml(message)}</p>`;
      Notifications.error(message);
    } finally {
      if (requestId === state.requestId) list.style.opacity = "";
    }
  }

  function updateAudienceHelp() {
    const selected = composer.event_id.options[composer.event_id.selectedIndex];
    $("[data-audience-help]").textContent = composer.event_id.value
      ? `Visible only in ${selected?.textContent?.split(" · ")[0] || "the selected event"}.`
      : "Visible to students in the general feed.";
  }

  function syncComposerControls() {
    const empty = composer.content.value.trim() === "";
    $$('button[type="submit"]', composer).forEach((button) => {
      if (button.dataset.loadingActive !== "true") {
        button.disabled = empty;
        button.style.opacity = empty ? "0.4" : "";
        button.style.cursor = empty ? "not-allowed" : "";
      }
    });
    $("[data-announcement-count]").textContent = `${composer.content.value.length.toLocaleString()} / 3,000`;
  }

  function clearImage() {
    if (previewUrl) URL.revokeObjectURL(previewUrl);
    previewUrl = "";
    composer.image.value = "";
    $("[data-image-preview]").src = "";
    $("[data-image-preview]").classList.add("hidden");
    $("[data-image-remove]").classList.add("hidden");
    $("[data-announcement-file]").textContent = "No image selected";
    $("[data-image-browse-label]").textContent = "Browse image";
    $("[data-image-toggle-label]").textContent = "Add image";
  }

  function updateImagePreview() {
    const file = composer.image.files?.[0];
    if (!file) {
      clearImage();
      return;
    }
    if (previewUrl) URL.revokeObjectURL(previewUrl);
    previewUrl = URL.createObjectURL(file);
    const preview = $("[data-image-preview]");
    preview.src = previewUrl;
    preview.classList.remove("hidden");
    $("[data-image-remove]").classList.remove("hidden");
    $("[data-announcement-file]").textContent = file.name;
    $("[data-image-browse-label]").textContent = "Replace image";
    $("[data-image-toggle-label]").textContent = "Change image";
    $("[data-image-panel]").classList.remove("hidden");
  }

  function resetComposer() {
    composer.reset();
    clearImage();
    $("[data-image-panel]").classList.add("hidden");
    clearErrors(composer);
    updateAudienceHelp();
    syncComposerControls();
  }

  function clearLibraryFilters() {
    state.values = { search: "", status: "all", event_id: "", scope: "" };
    state.page = 1;
    load();
  }

  async function returnToDraft(id) {
    const data = new FormData();
    data.set("action", "status");
    data.set("id", id);
    data.set("status", "draft");
    try {
      await send(data);
      await load();
      return true;
    } catch (error) {
      throw new Error(error.response?.data?.message || "The announcement could not be returned to drafts.");
    }
  }

  composer.addEventListener("submit", async (event) => {
    event.preventDefault();
    clearErrors(composer);
    if (!validateForm(composer)) return;
    const button = event.submitter;
    const intent = button?.value || "draft";
    const audience = composer.event_id.value
      ? composer.event_id.options[composer.event_id.selectedIndex]?.textContent?.split(" · ")[0]
      : "General student feed";
    const data = new FormData(composer);
    data.set("action", "create");
    data.set("intent", intent);
    Notifications.setLoading(button, true);
    try {
      const response = await send(data);
      resetComposer();
      await load();
      if (intent === "publish") {
        Notifications.undo(`Announcement published · Visible in ${audience}`, () => returnToDraft(response.data.id));
      } else {
        Notifications.success(response.data.message);
      }
    } catch (error) {
      showErrors(composer, error.response?.data?.errors);
      Notifications.error(error.response?.data?.message || "Announcement could not be saved.");
    } finally {
      Notifications.setLoading(button, false);
      syncComposerControls();
    }
  });

  filters.addEventListener("submit", (event) => {
    event.preventDefault();
    clearTimeout(state.debounce);
    state.values.search = filters.search.value.trim();
    state.page = 1;
    load();
  });
  filters.search.addEventListener("input", () => {
    clearTimeout(state.debounce);
    state.debounce = setTimeout(() => {
      state.values.search = filters.search.value.trim();
      state.page = 1;
      load();
    }, 320);
  });
  filters.status.addEventListener("change", () => {
    clearTimeout(state.debounce);
    state.values.search = filters.search.value.trim();
    state.values.status = filters.status.value;
    state.values.scope = "";
    state.page = 1;
    load();
  });
  filters.event_id.addEventListener("change", () => {
    clearTimeout(state.debounce);
    state.values.search = filters.search.value.trim();
    state.values.event_id = filters.event_id.value;
    state.values.scope = "";
    state.page = 1;
    load();
  });

  $("[aria-label='Announcement summary']").addEventListener("click", (event) => {
    const button = event.target.closest("[data-summary-filter]");
    if (!button) return;
    clearTimeout(state.debounce);
    state.values.search = filters.search.value.trim();
    const selected = button.dataset.summaryFilter;
    const alreadyActive = selected === "event"
      ? state.values.scope === "event"
      : state.values.scope !== "event" && state.values.status === selected;
    state.values.status = alreadyActive ? "all" : selected === "event" ? "all" : selected;
    state.values.scope = alreadyActive ? "" : selected === "event" ? "event" : "";
    state.values.event_id = "";
    state.page = 1;
    load();
    $("#announcement-library-title").scrollIntoView({ behavior: "smooth", block: "start" });
  });

  list.addEventListener("submit", async (event) => {
    const form = event.target.closest("[data-edit-form]");
    if (!form) return;
    event.preventDefault();
    clearErrors(form);
    if (!validateForm(form)) return;
    const button = event.submitter;
    const data = new FormData(form);
    data.set("action", "update");
    data.set("id", form.dataset.id);
    data.set("intent", button?.value || "draft");
    Notifications.setLoading(button, true);
    try {
      const response = await send(data);
      Notifications.success(response.data.message);
      await load();
    } catch (error) {
      showErrors(form, error.response?.data?.errors);
      Notifications.error(error.response?.data?.message || "Announcement could not be updated.");
    } finally {
      Notifications.setLoading(button, false);
    }
  });

  list.addEventListener("click", async (event) => {
    if (event.target.closest("[data-clear-library-filters]")) {
      clearLibraryFilters();
      return;
    }
    const button = event.target.closest("[data-action]");
    if (!button) return;
    const action = button.dataset.action;
    if (action === "archive") {
      const accepted = await Notifications.confirm({ title: "Archive announcement?", message: "It will disappear from the student feed.", action: "Archive" });
      if (!accepted) return;
    }
    const data = new FormData();
    data.set("action", action);
    data.set("id", button.dataset.id);
    if (action === "status") data.set("status", button.dataset.status);
    Notifications.setLoading(button, true);
    try {
      const response = await send(data);
      await load();
      if (action === "status" && button.dataset.status === "approved") {
        Notifications.undo("Announcement published", () => returnToDraft(button.dataset.id));
      } else {
        Notifications.success(response.data.message);
      }
    } catch (error) {
      Notifications.error(error.response?.data?.message || "Announcement action failed.");
      Notifications.setLoading(button, false);
    }
  });

  $("[data-pagination]").addEventListener("click", (event) => {
    const button = event.target.closest("[data-page]");
    if (!button || button.disabled) return;
    state.page = Number(button.dataset.page);
    load();
    $("#announcement-library-title").scrollIntoView({ behavior: "smooth", block: "start" });
  });

  composer.content.addEventListener("input", syncComposerControls);
  composer.event_id.addEventListener("change", updateAudienceHelp);
  composer.image.addEventListener("change", updateImagePreview);
  $("[data-image-toggle]").addEventListener("click", () => {
    const panel = $("[data-image-panel]");
    panel.classList.toggle("hidden");
    if (!panel.classList.contains("hidden") && !composer.image.files?.length) window.setTimeout(() => composer.image.click(), 80);
  });
  $("[data-image-remove]").addEventListener("click", clearImage);

  initializeShell();
  syncComposerControls();
  authenticate().then(load).catch((error) => {
    if (error.message !== "Unauthorized") console.error(error);
  });
})();
