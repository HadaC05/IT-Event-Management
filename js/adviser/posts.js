(() => {
  "use strict";

  const $ = (selector, root = document) => root.querySelector(selector);
  const $$ = (selector, root = document) => [...root.querySelectorAll(selector)];
  const list = $("[data-post-list]");
  const query = Object.fromEntries(new URLSearchParams(location.search));
  let csrfToken = "";

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

  function dateTime(value) {
    if (!value) return "—";
    return new Intl.DateTimeFormat("en-PH", {
      month: "short",
      day: "numeric",
      year: "numeric",
      hour: "numeric",
      minute: "2-digit",
    }).format(new Date(String(value).replace(" ", "T")));
  }

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

  function avatar(post) {
    if (post.author_photo) {
      return `<img class="h-11 w-11 shrink-0 rounded-full object-cover ring-2 ring-[#397565]/10" src="${escapeHtml(assetPath(post.author_photo))}" alt="${escapeHtml(post.author_name)} profile picture">`;
    }
    return `<span class="grid h-11 w-11 shrink-0 place-items-center rounded-full bg-[#C6F24E] text-xs font-black text-[#121017] ring-2 ring-[#397565]/10" aria-label="${escapeHtml(post.author_name)} profile picture">${escapeHtml(post.author_initials)}</span>`;
  }

  function statusTone(status) {
    if (status === "pending") return "bg-[#C6F24E]/30 text-[#397565]";
    if (status === "approved") return "bg-[#397565]/10 text-[#397565]";
    return "bg-[#FF6B2C]/12 text-[#c84510]";
  }

  function reviewControls(post) {
    if (post.status !== "pending") {
      return `<div class="border-t border-[#121017]/7 px-5 py-3 text-[10px] text-[#121017]/40">Reviewed ${timeAgo(post.reviewed_at)} by ${escapeHtml(post.reviewer_name || "an adviser")}</div>`;
    }
    return `<div class="grid gap-3 border-t border-[#121017]/7 p-5 sm:grid-cols-2"><form data-review-form data-id="${post.id}"><input type="hidden" name="status" value="approved"><button class="min-h-11 w-full rounded-xl bg-[#397565] text-xs font-black text-white" type="submit" data-loading-text="Approving…">Approve post</button></form><form data-review-form data-id="${post.id}" novalidate><input type="hidden" name="status" value="rejected"><label class="sr-only" for="reason-${post.id}">Rejection reason</label><textarea class="min-h-20 w-full rounded-xl border border-[#121017]/10 p-3 text-xs outline-none focus:border-[#FF6B2C] focus:ring-4 focus:ring-[#FF6B2C]/8" id="reason-${post.id}" name="rejection_reason" placeholder="Required reason for rejection" required maxlength="1000"></textarea><p class="mt-1 hidden text-[10px] font-bold text-[#c84510]" data-reason-error></p><button class="mt-2 min-h-11 w-full rounded-xl border border-[#FF6B2C]/25 bg-[#FF6B2C]/7 text-xs font-black text-[#c84510]" type="submit" data-loading-text="Rejecting…">Reject with reason</button></form></div>`;
  }

  function renderPost(post) {
    const event = post.event_title ? ` · ${escapeHtml(post.event_title)}` : "";
    const reason = post.rejection_reason
      ? `<p class="mt-3 rounded-xl bg-[#FF6B2C]/7 p-3 text-xs"><strong>Rejection reason:</strong> ${escapeHtml(post.rejection_reason)}</p>`
      : "";
    let media = "";
    if (post.image_path) {
      media = `
        <div class="aspect-[4/5] max-h-[590px] w-full overflow-hidden border-y border-[#121017]/7 bg-[#121017]">
          <img class="h-full w-full object-contain" src="${escapeHtml(assetPath(post.image_path))}" alt="${escapeHtml(post.author_name)} post image" loading="lazy">
        </div>`;
    } else if (post.video_path) {
      media = `
        <div class="aspect-[4/5] max-h-[590px] w-full overflow-hidden border-y border-[#121017]/7 bg-[#121017]">
          <video class="h-full w-full object-contain" src="${escapeHtml(assetPath(post.video_path))}" controls preload="metadata" playsinline></video>
        </div>`;
    }

    return `
      <article class="overflow-hidden rounded-3xl border border-[#121017]/8 bg-white">
        <header class="flex items-center gap-3 p-5">
          ${avatar(post)}
          <div class="min-w-0 flex-1">
            <strong class="block truncate text-sm">${escapeHtml(post.author_name)}</strong>
            <span class="text-[10px] text-[#121017]/40">${dateTime(post.created_at)}${event}</span>
          </div>
          <span class="rounded-full px-2.5 py-1 text-[9px] font-black uppercase ${statusTone(post.status)}">${escapeHtml(post.status)}</span>
        </header>
        <div class="px-5 pb-5">
          <span class="text-[9px] font-black uppercase tracking-wider text-[#397565]">${escapeHtml(titleCase(post.category))}</span>
          <p class="mt-2 whitespace-pre-line text-sm leading-6">${escapeHtml(post.content)}</p>
          ${reason}
        </div>
        ${media}
        ${reviewControls(post)}
      </article>`;
  }

  function renderPagination(pagination) {
    const nav = $("[data-pagination]");
    nav.classList.toggle("hidden", pagination.last_page <= 1);
    nav.classList.toggle("flex", pagination.last_page > 1);
    if (pagination.last_page <= 1) return;
    nav.innerHTML = `<span class="font-bold text-[#121017]/40">Page ${pagination.page} of ${pagination.last_page}</span><span class="flex gap-2"><button class="min-h-10 rounded-xl border border-[#121017]/10 bg-white px-4 font-black disabled:opacity-30" data-page="${pagination.page - 1}" ${pagination.page === 1 ? "disabled" : ""}>Previous</button><button class="min-h-10 rounded-xl border border-[#121017]/10 bg-white px-4 font-black disabled:opacity-30" data-page="${pagination.page + 1}" ${pagination.page === pagination.last_page ? "disabled" : ""}>Next</button></span>`;
  }

  function render(data) {
    $("[data-pending-count]").textContent = `${data.pending_count.toLocaleString()} pending`;
    list.innerHTML = data.posts.length
      ? data.posts.map(renderPost).join("")
      : '<div class="col-span-full rounded-3xl border border-dashed border-[#397565]/25 bg-white py-16 text-center"><h2 class="font-black">No posts to review</h2></div>';
    renderPagination(data.pagination);
  }

  async function load() {
    try {
      const response = await axios.get("api/posts.php", { params: query });
      render(response.data.data);
      const flash = sessionStorage.getItem("postReviewFlash");
      if (flash) {
        sessionStorage.removeItem("postReviewFlash");
        Notifications.success(flash);
      }
    } catch (error) {
      const message = error.response?.data?.message || "Post review queue could not be loaded.";
      list.innerHTML = `<div class="col-span-full rounded-3xl border border-red-200 bg-white py-16 text-center text-sm text-red-600">${escapeHtml(message)}</div>`;
      Notifications.error(message);
    }
  }

  list.addEventListener("submit", async (event) => {
    const form = event.target.closest("[data-review-form]");
    if (!form) return;
    event.preventDefault();
    const status = form.status.value;
    const reason = form.rejection_reason?.value.trim() || "";
    const error = $("[data-reason-error]", form);
    if (error) {
      error.textContent = "";
      error.classList.add("hidden");
    }
    if (status === "rejected" && !reason) {
      error.textContent = "A rejection reason is required.";
      error.classList.remove("hidden");
      form.rejection_reason.focus();
      return;
    }
    if (reason.length > 1000) {
      error.textContent = "The rejection reason may not exceed 1,000 characters.";
      error.classList.remove("hidden");
      return;
    }
    const button = event.submitter;
    Notifications.setLoading(button, true);
    try {
      const response = await axios.post(
        "api/posts.php",
        { action: "review", id: Number(form.dataset.id), status, rejection_reason: reason },
        { headers: { "X-CSRF-Token": csrfToken } },
      );
      sessionStorage.setItem("postReviewFlash", response.data.message);
      location.reload();
    } catch (requestError) {
      const errors = requestError.response?.data?.errors || {};
      if (error && errors.rejection_reason) {
        error.textContent = errors.rejection_reason[0];
        error.classList.remove("hidden");
      }
      Notifications.error(requestError.response?.data?.message || "Post could not be reviewed.");
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

  initializeShell();
  authenticate().then(load).catch((error) => {
    if (error.message !== "Unauthorized") console.error(error);
  });
})();
