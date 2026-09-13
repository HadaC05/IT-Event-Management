(() => {
  "use strict";

  const $ = (selector, root = document) => root.querySelector(selector);
  const $$ = (selector, root = document) => [...root.querySelectorAll(selector)];
  const composer = $("[data-announcement-form]");
  const filters = $("[data-announcement-filters]");
  const list = $("[data-announcement-list]");
  const query = Object.fromEntries(new URLSearchParams(location.search));
  let csrfToken = "";
  let events = [];

  const escapeHtml = (value) =>
    String(value ?? "").replace(
      /[&<>'"]/g,
      (character) =>
        ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", "'": "&#39;", '"': "&quot;" })[
          character
        ],
    );

  const formatDate = (value) =>
    new Intl.DateTimeFormat("en-PH", { month: "short", day: "numeric" }).format(
      new Date(String(value).replace(" ", "T")),
    );

  function timeAgo(value) {
    if (!value) return "just now";
    const seconds = Math.max(
      0,
      Math.floor((Date.now() - new Date(String(value).replace(" ", "T"))) / 1000),
    );
    for (const [size, label] of [
      [31536000, "year"],
      [2592000, "month"],
      [86400, "day"],
      [3600, "hour"],
      [60, "minute"],
    ]) {
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
    const menu = $("[data-account-menu]");
    document.addEventListener("click", (event) => {
      if (menu?.open && !menu.contains(event.target)) menu.removeAttribute("open");
    });
  }

  function applyAccount(user) {
    const account = $("[data-account-menu]");
    const name = user.full_name || user.username;
    account.querySelector("summary > span:first-child").childNodes[0].textContent =
      `${user.first_name?.[0] || ""}${user.last_name?.[0] || ""}`.toUpperCase();
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
    return events
      .map(
        (event) =>
          `<option value="${event.id}" ${Number(selected) === event.id ? "selected" : ""}>${escapeHtml(event.title)}${includeDate ? ` · ${formatDate(event.start_at)}` : ""}</option>`,
      )
      .join("");
  }

  function fillFilters(data) {
    events = data.events;
    composer.event_id.innerHTML =
      '<option value="">General student feed</option>' + eventOptions("", true);
    filters.event_id.innerHTML = '<option value="">All feeds</option>' + eventOptions(query.event_id);
    filters.search.value = query.search || "";
    filters.status.value = query.status || "all";
    filters.event_id.value = query.event_id || "";
  }

  function renderSummary(data) {
    Object.entries(data.summary).forEach(([key, value]) => {
      const element = $(`[data-summary="${key}"]`);
      if (element) element.textContent = Number(value).toLocaleString();
    });
    $("[data-pending-posts]").textContent = Number(data.pending_posts).toLocaleString();
    $("[data-result-count]").textContent = `${data.pagination.total.toLocaleString()} ${data.pagination.total === 1 ? "announcement" : "announcements"}`;
  }

  function editForm(announcement) {
    const remove = announcement.image_path
      ? '<label class="flex items-center gap-2 text-sm text-[#121017]/60"><input type="checkbox" name="remove_image" value="1"> Remove current image</label>'
      : "";
    return `<details class="group"><summary class="inline-flex min-h-11 cursor-pointer list-none items-center rounded-xl border border-[#2F3AE0]/20 px-4 text-sm font-black text-[#2F3AE0] hover:bg-[#2F3AE0]/5">Edit</summary><form class="mt-3 grid w-full gap-4 rounded-xl border border-[#121017]/9 bg-[#F3F0E9]/55 p-4 lg:w-[520px]" enctype="multipart/form-data" data-edit-form data-id="${announcement.id}" novalidate><label class="grid gap-2"><span class="text-sm font-bold">Message</span><textarea class="min-h-32 rounded-xl border border-[#121017]/10 bg-white p-4 text-sm leading-6 outline-none focus:border-[#397565]" name="content" maxlength="3000" required>${escapeHtml(announcement.content)}</textarea><span class="hidden text-xs font-bold text-[#c84510]" data-error="content"></span></label><label class="grid gap-2"><span class="text-sm font-bold">Event feed</span><select class="h-11 rounded-xl border border-[#121017]/10 bg-white px-3 text-sm" name="event_id"><option value="">General student feed</option>${eventOptions(announcement.event_id)}</select><span class="hidden text-xs font-bold text-[#c84510]" data-error="event_id"></span></label><label class="grid gap-2"><span class="text-sm font-bold">Replace image</span><input class="rounded-xl border border-[#121017]/10 bg-white p-3 text-sm" type="file" name="image" accept="image/jpeg,image/png,image/webp"><span class="hidden text-xs font-bold text-[#c84510]" data-error="image"></span></label>${remove}<div class="flex flex-wrap justify-end gap-2"><button class="min-h-11 rounded-xl border border-[#121017]/10 bg-white px-4 text-sm font-black" type="submit" name="intent" value="draft" data-loading-text="Saving…">Save as draft</button><button class="min-h-11 rounded-xl bg-[#2F3AE0] px-4 text-sm font-black text-white" type="submit" name="intent" value="publish" data-loading-text="Publishing…">Save and publish</button></div></form></details>`;
  }

  function renderAnnouncement(announcement) {
    const published = announcement.status === "approved" && !announcement.is_archived;
    const label = announcement.is_archived ? "Archived" : published ? "Published" : "Draft";
    const tone = announcement.is_archived
      ? "bg-[#121017]/7 text-[#121017]/48"
      : published
        ? "bg-[#C6F24E]/35 text-[#397565]"
        : "bg-[#FF6B2C]/10 text-[#c84510]";
    const image = announcement.image_path
      ? `<div class="mt-4 flex items-center gap-3 rounded-xl bg-[#F3F0E9]/60 p-3"><img class="h-16 w-16 rounded-lg object-cover" src="${escapeHtml(announcement.image_path)}" alt="Announcement attachment"><span><strong class="block text-sm">Image attached</strong><small class="text-xs text-[#121017]/42">Shown below the announcement in the student feed</small></span></div>`
      : "";
    const actions = announcement.is_archived
      ? `<button class="min-h-11 rounded-xl bg-[#397565] px-4 text-sm font-black text-white" type="button" data-action="restore" data-id="${announcement.id}" data-loading-text="Restoring…">Restore as draft</button>`
      : `<button class="min-h-11 rounded-xl px-4 text-sm font-black ${published ? "border border-[#121017]/10 text-[#121017]/60 hover:bg-[#121017]/5" : "bg-[#C6F24E] text-[#121017] hover:bg-[#d2f970]"}" type="button" data-action="status" data-status="${published ? "draft" : "approved"}" data-id="${announcement.id}" data-loading-text="${published ? "Unpublishing…" : "Publishing…"}">${published ? "Unpublish" : "Publish"}</button>${editForm(announcement)}<button class="min-h-11 rounded-xl px-3 text-sm font-black text-[#c84510] hover:bg-[#FF6B2C]/8" type="button" data-action="archive" data-id="${announcement.id}" data-loading-text="Archiving…">Archive</button>`;
    return `<article class="p-5 sm:p-6"><div class="grid gap-5 lg:grid-cols-[minmax(0,1fr)_auto]"><div class="min-w-0"><div class="flex flex-wrap items-center gap-2"><span class="rounded-full px-3 py-1.5 text-xs font-black ${tone}">${label}</span><span class="text-xs font-bold text-[#121017]/38">${escapeHtml(announcement.event_title || "General student feed")}</span><span class="text-xs text-[#121017]/30">· Updated ${timeAgo(announcement.updated_at)}</span></div><p class="mt-4 whitespace-pre-line text-base leading-7 text-[#121017]/75">${escapeHtml(announcement.content)}</p>${image}</div><div class="flex flex-wrap items-start gap-2 lg:max-w-64 lg:justify-end">${actions}</div></div></article>`;
  }

  function renderList(data) {
    if (!data.announcements.length) {
      list.innerHTML = '<div class="px-6 py-16 text-center"><span class="mx-auto grid h-14 w-14 place-items-center rounded-full bg-[#397565]/8 text-[#397565]"><svg class="h-6 w-6 fill-none stroke-current stroke-2" viewBox="0 0 24 24"><path d="M4 13V9l11-5v14L4 13Zm0 0v5h4v-3m7-7h3a3 3 0 0 1 0 6h-3"/></svg></span><h3 class="mt-5 text-lg font-black">No announcements found</h3><p class="mt-2 text-sm text-[#121017]/45">Create the first official update above or clear the current filters.</p></div>';
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
    return axios.post("api/announcements.php", formData, {
      headers: { "X-CSRF-Token": csrfToken },
    });
  }

  async function load() {
    try {
      const response = await axios.get("api/announcements.php", { params: query });
      fillFilters(response.data.data);
      renderSummary(response.data.data);
      renderList(response.data.data);
    } catch (error) {
      const message = error.response?.data?.message || "Announcements could not be loaded.";
      list.innerHTML = `<p class="px-6 py-16 text-center text-sm text-red-600">${escapeHtml(message)}</p>`;
      Notifications.error(message);
    }
  }

  composer.addEventListener("submit", async (event) => {
    event.preventDefault();
    clearErrors(composer);
    if (!validateForm(composer)) return;
    const button = event.submitter;
    const data = new FormData(composer);
    data.set("action", "create");
    data.set("intent", button?.value || "draft");
    Notifications.setLoading(button, true);
    try {
      const response = await send(data);
      composer.reset();
      $("[data-announcement-file]").textContent = "Choose JPG, PNG or WebP";
      updateCount();
      Notifications.success(response.data.message);
      await load();
      scrollTo({ top: 0, behavior: "smooth" });
    } catch (error) {
      showErrors(composer, error.response?.data?.errors);
      Notifications.error(error.response?.data?.message || "Announcement could not be saved.");
    } finally {
      Notifications.setLoading(button, false);
    }
  });

  filters.addEventListener("submit", (event) => {
    event.preventDefault();
    const url = new URL(location.href);
    url.search = "";
    new FormData(filters).forEach((value, key) => {
      if (value && !(key === "status" && value === "all")) url.searchParams.set(key, value);
    });
    location.assign(url.href);
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
      Notifications.success(response.data.message);
      if (action === "restore") {
        location.assign("pages/adviser/announcements.html?status=draft");
        return;
      }
      await load();
    } catch (error) {
      Notifications.error(error.response?.data?.message || "Announcement action failed.");
      Notifications.setLoading(button, false);
    }
  });

  $("[data-pagination]").addEventListener("click", (event) => {
    const button = event.target.closest("[data-page]");
    if (!button || button.disabled) return;
    const url = new URL(location.href);
    url.searchParams.set("page", button.dataset.page);
    location.assign(url.href);
  });

  function updateCount() {
    $("[data-announcement-count]").textContent = `${composer.content.value.length.toLocaleString()} / 3,000`;
  }
  composer.content.addEventListener("input", updateCount);
  composer.image.addEventListener("change", () => {
    $("[data-announcement-file]").textContent = composer.image.files?.[0]?.name || "Choose JPG, PNG or WebP";
  });

  initializeShell();
  updateCount();
  authenticate().then(load).catch((error) => {
    if (error.message !== "Unauthorized") console.error(error);
  });
})();
