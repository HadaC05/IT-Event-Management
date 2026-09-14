(() => {
  "use strict";

  const $ = (selector, root = document) => root.querySelector(selector);
  const $$ = (selector, root = document) => [...root.querySelectorAll(selector)];
  const queue = $("[data-post-queue]");
  const preview = $("[data-post-preview]");
  const filterForm = $("[data-filter-form]");
  const pagination = $("[data-pagination]");
  const state = {
    csrf: "",
    status: "pending",
    posts: [],
    counts: { pending: 0, approved: 0, rejected: 0 },
    selectedId: null,
    page: 1,
    pagination: null,
    categories: [],
  };

  const escapeHtml = (value) =>
    String(value ?? "").replace(
      /[&<>'"]/g,
      (character) =>
        ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", "'": "&#39;", '"': "&quot;" })[
          character
        ],
    );

  const titleCase = (value) =>
    String(value || "")
      .replaceAll("-", " ")
      .replace(/\b\w/g, (letter) => letter.toUpperCase());

  function assetPath(path) {
    if (!path) return "";
    if (/^(?:https?:)?\/\//i.test(path) || path.startsWith("assets/")) return path;
    return `assets/uploads/${String(path).replace(/^\/+/, "")}`;
  }

  function parsedDate(value) {
    return new Date(String(value || "").replace(" ", "T"));
  }

  function dateTime(value) {
    if (!value) return "—";
    return new Intl.DateTimeFormat("en-PH", {
      month: "short",
      day: "numeric",
      year: "numeric",
      hour: "numeric",
      minute: "2-digit",
    }).format(parsedDate(value));
  }

  function timeAgo(value) {
    if (!value) return "just now";
    const seconds = Math.max(0, Math.floor((Date.now() - parsedDate(value)) / 1000));
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

  function isNew(post) {
    return post.status === "pending" && Date.now() - parsedDate(post.created_at).getTime() < 5 * 60 * 1000;
  }

  function initializeShell() {
    const sidebar = $("#sidebar");
    const scrim = $("[data-sidebar-scrim]");
    $$("[data-sidebar-toggle]").forEach((button) => {
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
    state.csrf = response.data.csrf_token;
    $("meta[name='csrf-token']").content = state.csrf;
    applyAccount(response.data.user);
    $("form[action='api/auth.php?action=logout']").onsubmit = async (event) => {
      event.preventDefault();
      try {
        await axios.post("api/auth.php?action=logout", {}, { headers: { "X-CSRF-Token": state.csrf } });
      } finally {
        location.replace("./");
      }
    };
  }

  function avatar(post, size = "h-11 w-11") {
    if (post.author_photo) {
      return `<img class="${size} shrink-0 rounded-full object-cover ring-2 ring-[#397565]/10" src="${escapeHtml(assetPath(post.author_photo))}" alt="${escapeHtml(post.author_name)} profile picture">`;
    }
    return `<span class="grid ${size} shrink-0 place-items-center rounded-full bg-[#C6F24E] text-xs font-black text-[#121017] ring-2 ring-[#397565]/10" aria-label="${escapeHtml(post.author_name)} profile picture">${escapeHtml(post.author_initials)}</span>`;
  }

  function mediaIcon(post) {
    if (post.image_path) {
      return '<span class="grid h-7 w-7 place-items-center rounded-lg bg-[#2F3AE0]/8 text-[#2F3AE0]" title="Contains an image"><svg class="h-3.5 w-3.5 fill-none stroke-current stroke-2" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="9" cy="10" r="2"/><path d="m21 15-5-5L5 20"/></svg></span>';
    }
    if (post.video_path) {
      return '<span class="grid h-7 w-7 place-items-center rounded-lg bg-[#2F3AE0]/8 text-[#2F3AE0]" title="Contains a video"><svg class="h-3.5 w-3.5 fill-none stroke-current stroke-2" viewBox="0 0 24 24"><rect x="3" y="5" width="15" height="14" rx="2"/><path d="m18 10 4-2v8l-4-2"/></svg></span>';
    }
    return "";
  }

  function statusTone(status) {
    if (status === "pending") return "bg-[#C6F24E]/30 text-[#397565]";
    if (status === "approved") return "bg-[#397565]/10 text-[#397565]";
    return "bg-[#FF6B2C]/12 text-[#c84510]";
  }

  function queueItem(post) {
    const selected = post.id === state.selectedId;
    const event = post.event_title || titleCase(post.category);
    const excerpt = String(post.content || "").replace(/\s+/g, " ");
    return `
      <button class="group mb-2 flex w-full gap-3 rounded-2xl border p-4 text-left transition duration-200 ${selected ? "border-[#397565]/35 bg-[#397565]/8 shadow-[0_8px_25px_rgba(57,117,101,.08)]" : "border-transparent bg-white hover:border-[#397565]/15 hover:bg-[#397565]/5"}" type="button" data-select-post="${post.id}" aria-pressed="${selected}">
        ${avatar(post, "h-10 w-10")}
        <span class="min-w-0 flex-1">
          <span class="flex items-center gap-2"><strong class="truncate text-xs">${escapeHtml(post.author_name)}</strong>${isNew(post) ? '<span class="rounded-full bg-[#C6F24E] px-2 py-0.5 text-[8px] font-black uppercase text-[#121017]">New</span>' : ""}</span>
          <span class="mt-1 block truncate text-sm font-bold text-[#121017]/75">“${escapeHtml(excerpt || "Untitled post")}”</span>
          <span class="mt-2 flex items-center gap-2 text-[9px] font-bold text-[#121017]/38"><span class="truncate">${escapeHtml(event)}</span><span>•</span><span class="shrink-0">${timeAgo(post.created_at)}</span></span>
        </span>
        ${mediaIcon(post)}
      </button>`;
  }

  function emptyQueue() {
    const statusCopy = {
      pending: ["All caught up", "There are no student posts waiting for review."],
      approved: ["No approved posts", "Approved posts will appear here."],
      rejected: ["No rejected posts", "Rejected posts will appear here."],
    }[state.status];
    return `<div class="grid min-h-72 place-items-center px-6 text-center"><div><span class="mx-auto grid h-12 w-12 place-items-center rounded-full bg-[#397565]/8 text-[#397565]"><svg class="h-5 w-5 fill-none stroke-current stroke-2" viewBox="0 0 24 24"><path d="m5 12 4 4L19 6"/></svg></span><h3 class="mt-4 text-sm font-black">${statusCopy[0]}</h3><p class="mt-1 text-xs leading-5 text-[#121017]/40">${statusCopy[1]}</p></div></div>`;
  }

  function mediaPreview(post) {
    if (post.image_path) {
      return `<figure class="mt-7 overflow-hidden rounded-2xl border border-[#121017]/8 bg-[#121017]"><img class="max-h-[520px] w-full object-contain" src="${escapeHtml(assetPath(post.image_path))}" alt="Image attached to ${escapeHtml(post.author_name)}'s post"></figure>`;
    }
    if (post.video_path) {
      return `<figure class="mt-7 overflow-hidden rounded-2xl border border-[#121017]/8 bg-[#121017]"><video class="max-h-[520px] w-full object-contain" src="${escapeHtml(assetPath(post.video_path))}" controls preload="metadata" playsinline></video></figure>`;
    }
    return "";
  }

  function moderationActions(post) {
    if (post.status !== "pending") {
      const reason = post.rejection_reason
        ? `<div class="mt-4 rounded-xl border border-[#FF6B2C]/15 bg-[#FF6B2C]/7 p-4"><span class="text-[9px] font-black uppercase tracking-wider text-[#c84510]">Reason provided</span><p class="mt-1 text-sm leading-6">${escapeHtml(post.rejection_reason)}</p></div>`
        : "";
      return `${reason}<footer class="mt-8 flex flex-wrap items-center justify-between gap-3 border-t border-[#121017]/8 pt-5"><span class="rounded-full px-3 py-1.5 text-[9px] font-black uppercase ${statusTone(post.status)}">${escapeHtml(post.status)}</span><p class="text-[10px] text-[#121017]/40">Reviewed ${timeAgo(post.reviewed_at)} by ${escapeHtml(post.reviewer_name || "an adviser")}</p></footer>`;
    }
    return `
      <div class="mt-6" data-review-actions>
        <div class="flex flex-col gap-3 sm:flex-row">
          <button class="inline-flex min-h-12 min-w-36 items-center justify-center gap-2 rounded-xl bg-[#397565] px-5 text-sm font-black text-white shadow-[0_10px_25px_rgba(57,117,101,.2)] hover:-translate-y-0.5 hover:bg-[#2e6355]" type="button" data-approve-post="${post.id}"><svg class="h-4 w-4 fill-none stroke-current stroke-[2.5]" viewBox="0 0 24 24"><path d="m5 12 4 4L19 6"/></svg>Approve</button>
          <button class="min-h-12 min-w-36 rounded-xl border border-[#FF6B2C]/25 px-5 text-sm font-black text-[#c84510] hover:bg-[#FF6B2C]/7" type="button" data-reject-open>Reject</button>
        </div>
        <form class="mt-4 hidden rounded-2xl border border-[#FF6B2C]/25 bg-[#FF6B2C]/5 p-4" data-reject-form data-id="${post.id}">
          <div class="flex items-start justify-between gap-3"><div><h3 class="text-sm font-black">Why are you rejecting this post?</h3><p class="mt-1 text-[10px] text-[#121017]/40">The student will see this reason.</p></div><button class="grid h-8 w-8 place-items-center rounded-lg text-lg text-[#121017]/35 hover:bg-white" type="button" data-reject-cancel aria-label="Close rejection form">×</button></div>
          <div class="mt-4 flex flex-wrap gap-2">${["Spam", "Inappropriate content", "Unrelated to event"].map((reason) => `<button class="rounded-full border border-[#121017]/10 bg-white px-3 py-2 text-[10px] font-black text-[#121017]/55 hover:border-[#FF6B2C]/30 hover:text-[#c84510]" type="button" data-reason-choice="${escapeHtml(reason)}">${escapeHtml(reason)}</button>`).join("")}</div>
          <label class="mt-3 block"><span class="sr-only">Rejection reason</span><textarea class="min-h-24 w-full rounded-xl border border-[#121017]/10 bg-white p-3 text-xs outline-none focus:border-[#FF6B2C] focus:ring-4 focus:ring-[#FF6B2C]/8" name="rejection_reason" placeholder="Add a reason or choose one above…" required maxlength="1000"></textarea></label>
          <p class="mt-1 hidden text-[10px] font-bold text-[#c84510]" data-reason-error></p>
          <div class="mt-3 flex justify-end gap-2"><button class="min-h-10 px-4 text-xs font-black text-[#121017]/45" type="button" data-reject-cancel>Cancel</button><button class="min-h-10 rounded-xl bg-[#FF6B2C] px-5 text-xs font-black text-white" type="submit" data-loading-text="Rejecting…">Reject post</button></div>
        </form>
      </div>`;
  }

  function renderPreview() {
    const post = state.posts.find((item) => item.id === state.selectedId);
    if (!post) {
      preview.innerHTML = `<div class="grid min-h-72 place-items-center px-8 text-center"><div><span class="mx-auto grid h-14 w-14 place-items-center rounded-full bg-[#397565]/8 text-[#397565]"><svg class="h-6 w-6 fill-none stroke-current stroke-2" viewBox="0 0 24 24"><path d="M4 5h16v12H8l-4 4V5Zm4 4h8m-8 4h5"/></svg></span><h2 class="mt-4 text-lg font-black">${state.posts.length ? "Select a post" : "Nothing to display"}</h2><p class="mt-1 text-sm text-[#121017]/40">${state.posts.length ? "Choose an item from the queue." : "Try another status or adjust your filters."}</p></div></div>`;
      return;
    }
    const context = [titleCase(post.category), post.event_title].filter(Boolean).join(" • ");
    preview.innerHTML = `
      <article class="p-5 sm:p-8 lg:p-10" data-preview-article>
        <header class="flex items-start gap-4">
          ${avatar(post, "h-12 w-12")}
          <div class="min-w-0 flex-1"><div class="flex flex-wrap items-center gap-2"><h2 class="text-lg font-black tracking-[-.02em]">${escapeHtml(post.author_name)}</h2>${isNew(post) ? '<span class="rounded-full bg-[#C6F24E] px-2 py-1 text-[8px] font-black uppercase">New</span>' : ""}</div><p class="mt-1 text-[10px] font-bold text-[#121017]/40">${escapeHtml(context)} • ${dateTime(post.created_at)}</p></div>
          <span class="rounded-full px-3 py-1.5 text-[9px] font-black uppercase ${statusTone(post.status)}">${escapeHtml(post.status)}</span>
        </header>
        <div class="mt-7"><p class="whitespace-pre-line text-base leading-7 text-[#121017]/85">${escapeHtml(post.content)}</p>${mediaPreview(post)}</div>
        ${moderationActions(post)}
      </article>`;
  }

  function renderTabs() {
    $$("[data-status-tab]").forEach((tab) => {
      const selected = tab.dataset.statusTab === state.status;
      tab.setAttribute("aria-selected", String(selected));
      tab.classList.toggle("bg-[#397565]", selected);
      tab.classList.toggle("text-white", selected);
      tab.classList.toggle("text-[#121017]/45", !selected);
      tab.classList.toggle("hover:bg-[#121017]/5", !selected);
    });
    for (const status of ["pending", "approved", "rejected"]) {
      $( `[data-status-count="${status}"]` ).textContent = Number(state.counts[status] || 0).toLocaleString();
    }
    $("[data-pending-count]").textContent = `${Number(state.counts.pending || 0).toLocaleString()} waiting`;
    $("[data-queue-eyebrow]").textContent = {
      pending: "Waiting for review",
      approved: "Approved posts",
      rejected: "Rejected posts",
    }[state.status];
    $("[data-queue-dot]").classList.toggle("hidden", state.status !== "pending");
  }

  function renderPagination() {
    const data = state.pagination;
    pagination.classList.toggle("hidden", !data || data.last_page <= 1);
    pagination.classList.toggle("flex", Boolean(data && data.last_page > 1));
    if (!data || data.last_page <= 1) return;
    pagination.innerHTML = `<span class="font-bold text-[#121017]/40">${data.page} of ${data.last_page}</span><span class="flex gap-1"><button class="grid h-8 w-8 place-items-center rounded-lg border border-[#121017]/10 bg-white font-black disabled:opacity-30" type="button" data-page="${data.page - 1}" ${data.page === 1 ? "disabled" : ""} aria-label="Previous page">←</button><button class="grid h-8 w-8 place-items-center rounded-lg border border-[#121017]/10 bg-white font-black disabled:opacity-30" type="button" data-page="${data.page + 1}" ${data.page === data.last_page ? "disabled" : ""} aria-label="Next page">→</button></span>`;
  }

  function render() {
    renderTabs();
    $("[data-result-summary]").textContent = `${state.pagination?.total || 0} ${state.pagination?.total === 1 ? "post" : "posts"}`;
    queue.innerHTML = state.posts.length ? state.posts.map(queueItem).join("") : emptyQueue();
    renderPagination();
    renderPreview();
  }

  function requestParams() {
    const filters = Object.fromEntries(new FormData(filterForm));
    return {
      status: state.status,
      page: state.page,
      search: filters.search?.trim() || "",
      category: filters.category || "",
      period: filters.period || "",
      sort: filters.sort || "oldest",
    };
  }

  function fillCategories(categories) {
    const select = filterForm.elements.category;
    const selected = select.value;
    select.replaceChildren(new Option("All categories", ""));
    categories.forEach((category) => select.add(new Option(titleCase(category), category)));
    select.value = categories.includes(selected) ? selected : "";
  }

  async function load({ preserveSelection = true } = {}) {
    queue.innerHTML = '<div class="grid min-h-48 place-items-center text-xs font-bold text-[#121017]/35">Loading queue…</div>';
    try {
      const response = await axios.get("api/posts.php", { params: requestParams() });
      const data = response.data.data;
      const previous = preserveSelection ? state.selectedId : null;
      state.posts = data.posts;
      state.counts = data.counts;
      state.pagination = data.pagination;
      state.categories = data.categories;
      state.selectedId = state.posts.some((post) => post.id === previous) ? previous : state.posts[0]?.id || null;
      fillCategories(data.categories);
      render();
    } catch (error) {
      const message = error.response?.data?.message || "Post review queue could not be loaded.";
      queue.innerHTML = `<div class="grid min-h-72 place-items-center px-6 text-center text-xs font-bold text-[#c84510]">${escapeHtml(message)}</div>`;
      preview.innerHTML = "";
      Notifications.error(message);
    }
  }

  async function submitReview(postId, status, reason = "", button = null) {
    if (button) Notifications.setLoading(button, true);
    try {
      const response = await axios.post(
        "api/posts.php",
        { action: "review", id: Number(postId), status, rejection_reason: reason },
        { headers: { "X-CSRF-Token": state.csrf } },
      );
      Notifications.success(status === "approved" ? "Post approved. Opening the next item." : response.data.message);
      state.selectedId = null;
      await load({ preserveSelection: false });
    } catch (error) {
      const errors = error.response?.data?.errors || {};
      const reasonError = $("[data-reason-error]", preview);
      if (reasonError && errors.rejection_reason) {
        reasonError.textContent = errors.rejection_reason[0];
        reasonError.classList.remove("hidden");
      }
      Notifications.error(error.response?.data?.message || "Post could not be reviewed.");
      if (button) Notifications.setLoading(button, false);
    }
  }

  $$("[data-status-tab]").forEach((tab) =>
    tab.addEventListener("click", () => {
      if (state.status === tab.dataset.statusTab) return;
      state.status = tab.dataset.statusTab;
      state.page = 1;
      state.selectedId = null;
      load({ preserveSelection: false });
    }),
  );

  queue.addEventListener("click", (event) => {
    const item = event.target.closest("[data-select-post]");
    if (!item) return;
    state.selectedId = Number(item.dataset.selectPost);
    render();
    if (matchMedia("(max-width: 1279px)").matches) preview.scrollIntoView({ behavior: "smooth", block: "start" });
  });

  preview.addEventListener("click", (event) => {
    const approve = event.target.closest("[data-approve-post]");
    if (approve) {
      submitReview(approve.dataset.approvePost, "approved", "", approve);
      return;
    }
    const rejectOpen = event.target.closest("[data-reject-open]");
    if (rejectOpen) {
      const form = $("[data-reject-form]", preview);
      form.classList.remove("hidden");
      rejectOpen.parentElement.classList.add("hidden");
      form.querySelector("textarea").focus();
      return;
    }
    if (event.target.closest("[data-reject-cancel]")) {
      $("[data-reject-form]", preview).classList.add("hidden");
      $("[data-reject-open]", preview).parentElement.classList.remove("hidden");
      return;
    }
    const choice = event.target.closest("[data-reason-choice]");
    if (choice) {
      const textarea = $("[data-reject-form] textarea", preview);
      textarea.value = choice.dataset.reasonChoice;
      textarea.focus();
    }
  });

  preview.addEventListener("submit", (event) => {
    const form = event.target.closest("[data-reject-form]");
    if (!form) return;
    event.preventDefault();
    const reason = form.rejection_reason.value.trim();
    const error = $("[data-reason-error]", form);
    error.classList.add("hidden");
    if (!reason) {
      error.textContent = "Choose or enter a reason for the student.";
      error.classList.remove("hidden");
      form.rejection_reason.focus();
      return;
    }
    submitReview(form.dataset.id, "rejected", reason, event.submitter);
  });

  function setControlVisibility() {
    const filterOpen = $("[data-filter-toggle]").getAttribute("aria-expanded") === "true";
    const searchOpen = $("[data-search-toggle]").getAttribute("aria-expanded") === "true";
    filterForm.classList.toggle("hidden", !filterOpen && !searchOpen);
    $("[data-search-field]").classList.toggle("hidden", !searchOpen);
    $$("[data-filter-field]").forEach((field) => field.classList.toggle("hidden", !filterOpen));
  }

  $("[data-search-toggle]").addEventListener("click", (event) => {
    const open = event.currentTarget.getAttribute("aria-expanded") !== "true";
    event.currentTarget.setAttribute("aria-expanded", String(open));
    setControlVisibility();
    if (open) filterForm.elements.search.focus();
  });

  $("[data-filter-toggle]").addEventListener("click", (event) => {
    const open = event.currentTarget.getAttribute("aria-expanded") !== "true";
    event.currentTarget.setAttribute("aria-expanded", String(open));
    setControlVisibility();
  });

  filterForm.addEventListener("submit", (event) => {
    event.preventDefault();
    state.page = 1;
    state.selectedId = null;
    const filters = requestParams();
    $("[data-filter-active]").classList.toggle("hidden", !filters.category && !filters.period && filters.sort === "oldest");
    load({ preserveSelection: false });
  });

  $("[data-clear-filters]").addEventListener("click", () => {
    filterForm.reset();
    filterForm.elements.sort.value = "oldest";
    $("[data-filter-active]").classList.add("hidden");
    state.page = 1;
    state.selectedId = null;
    load({ preserveSelection: false });
  });

  pagination.addEventListener("click", (event) => {
    const button = event.target.closest("[data-page]");
    if (!button || button.disabled) return;
    state.page = Number(button.dataset.page);
    state.selectedId = null;
    load({ preserveSelection: false });
  });

  initializeShell();
  authenticate().then(() => load({ preserveSelection: false })).catch((error) => {
    if (error.message !== "Unauthorized") console.error(error);
  });
})();
