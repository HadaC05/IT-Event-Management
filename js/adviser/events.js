(() => {
  "use strict";
  let csrf = "",
    page = 1,
    state = null,
    formController = null,
    initialStatus = "",
    initialEventType = "",
    currentUserId = 0;
  const $ = (selector) => document.querySelector(selector),
    filters = $("[data-event-filters]"),
    rows = $("[data-event-rows]"),
    dialog = $("#create-event-dialog");
  const toast = (type, message) =>
    window.Notifications?.[type]?.(message) ||
    (type === "error" && alert(message));
  filters.dataset.axiosForm = "";
  const confirmAction = (options) =>
    window.Notifications?.confirm
      ? window.Notifications.confirm(options)
      : Promise.resolve(confirm(options.message));
  const formatDate = (value) =>
    new Intl.DateTimeFormat("en-US", {
      month: "short",
      day: "numeric",
      year: "numeric",
    }).format(new Date(value.replace(" ", "T")));
  const formatTime = (value) =>
    new Intl.DateTimeFormat("en-US", {
      hour: "numeric",
      minute: "2-digit",
    }).format(new Date(value.replace(" ", "T")));
  const statusClass = (label) =>
    ({
      upcoming: "bg-[#2F3AE0]/8 text-[#2F3AE0]",
      ongoing: "bg-[#C6F24E]/35 text-[#397565]",
      completed: "bg-[#121017]/6 text-[#121017]/55",
      archived: "bg-[#FF6B2C]/10 text-[#FF6B2C]",
    })[label] || "bg-[#FF6B2C]/10 text-[#FF6B2C]";
  const post = (payload) =>
    axios.post("api/adviser-events.php", payload, {
      headers: { "X-CSRF-Token": csrf },
    });
  function fillFilters(
    selectedStatus = filters.status.value,
    selectedType = filters.event_type.value,
  ) {
    filters.status.replaceChildren(new Option("All statuses", ""));
    state.metadata.statuses.forEach((item) =>
      filters.status.add(
        new Option(
          item.label[0].toUpperCase() + item.label.slice(1),
          item.id,
          false,
          String(item.id) === String(selectedStatus),
        ),
      ),
    );
    filters.event_type.replaceChildren(new Option("All event types", ""));
    state.metadata.event_types.forEach((item) =>
      filters.event_type.add(
        new Option(
          item.label,
          item.id,
          false,
          String(item.id) === String(selectedType),
        ),
      ),
    );
  }
  function render() {
    rows.replaceChildren();
    if (!state.events.length) {
      const row = document.createElement("tr");
      row.innerHTML = `<td class="px-5 py-16 text-center" colspan="6"><strong class="text-sm text-slate-600">No events found</strong><p class="mt-1 text-xs text-slate-400">${[...new FormData(filters).values()].some(Boolean) ? "Try changing your filters." : "Create your first event to get started."}</p></td>`;
      rows.append(row);
    }
    state.events.forEach((event) => {
      const row = document.createElement("tr");
      row.className = `group transition ${event.is_archived ? "bg-slate-50/70 opacity-75" : "cursor-pointer hover:bg-emerald-50/40"}`;
      row.innerHTML = `<td class="px-5 py-4"><div class="grid min-w-48 gap-1"><strong class="text-sm group-hover:text-emerald-700">${EventForm.escapeHtml(event.title)}</strong><small class="max-w-sm truncate text-[10px] text-slate-400">${EventForm.escapeHtml(event.description || "No description")}</small></div></td><td class="px-4 py-4"><span class="grid gap-0.5 text-xs font-semibold text-slate-600">${formatDate(event.start_at)}<small class="font-medium text-slate-400">${formatTime(event.start_at)}</small></span></td><td class="max-w-52 px-4 py-4 text-xs text-slate-500"><span class="block truncate">${EventForm.escapeHtml(event.location || "Not specified")}</span></td><td class="px-4 py-4"><span class="rounded-full px-2.5 py-1 text-[10px] font-bold ${statusClass(event.status_label)}">${event.status_label[0].toUpperCase() + event.status_label.slice(1)}</span></td><td class="px-4 py-4"><div class="flex items-center gap-2"><div class="flex pl-1">${event.assigned_users
        .slice(0, 3)
        .map(
          (user) =>
            `<span class="-ml-1 grid h-7 w-7 place-items-center rounded-full border-2 border-white bg-emerald-100 text-[8px] font-extrabold text-emerald-800" title="${EventForm.escapeHtml(user.full_name)}">${EventForm.initials(user.full_name)}</span>`,
        )
        .join(
          "",
        )}</div><span class="text-[10px] text-slate-400">${event.assigned_users.length || "None"}</span></div></td><td class="px-5 py-4 text-right"><div class="inline-flex gap-1.5" data-actions></div></td>`;
      const actions = row.querySelector("[data-actions]");
      if (event.is_archived) {
        actions.append(
          makeButton(
            "Restore",
            "h-9 rounded-lg border px-3 text-xs font-bold",
            () => restoreEvent(event),
          ),
          makeButton("Delete", "h-9 px-2 text-xs font-bold text-red-600", () =>
            deleteEvent(event),
          ),
        );
      } else {
        const edit = document.createElement("a");
        edit.href = `pages/adviser/event-edit.html?id=${event.id}`;
        edit.className =
          "grid h-11 w-11 place-items-center rounded-xl border border-[#397565]/25 bg-[#397565]/8 text-[#397565]";
        edit.innerHTML =
          '<svg class="h-[18px] w-[18px] fill-none stroke-current stroke-2" viewBox="0 0 24 24"><path d="M12 20h9M16.5 3.5a2.12 2.12 0 0 1 3 3L8 18l-4 1 1-4Z"/></svg>';
        edit.title = "Edit event";
        const archive = makeButton(
          "",
          "grid h-11 w-11 place-items-center rounded-xl border border-[#FF6B2C]/25 bg-[#FF6B2C]/9 text-[#d9470a]",
          () => archiveEvent(event),
        );
        archive.innerHTML =
          '<svg class="h-[18px] w-[18px] fill-none stroke-current stroke-2" viewBox="0 0 24 24"><path d="M4 7h16v13H4V7Zm-1-4h18v4H3V3Zm6 8h6"/></svg>';
        archive.title = "Archive event";
        actions.append(edit, archive);
        row.onclick = (click) => {
          if (!click.target.closest("a,button"))
            location.href = `pages/adviser/event-details.html?id=${event.id}`;
        };
      }
      rows.append(row);
    });
    renderPagination();
  }
  function makeButton(label, className, handler) {
    const button = document.createElement("button");
    button.type = "button";
    button.className = className;
    button.textContent = label;
    button.onclick = handler;
    return button;
  }
  async function archiveEvent(event) {
    if (
      !(await confirmAction({
        title: "Archive event?",
        message: `${event.title} will be hidden, but its details and assignments will be preserved.`,
        action: "Archive",
      }))
    )
      return;
    try {
      await post({ action: "archive", id: event.id });
      toast("success", "Event archived successfully.");
      await load();
    } catch (error) {
      toast(
        "error",
        error.response?.data?.message || "Unable to archive event.",
      );
    }
  }
  async function restoreEvent(event) {
    try {
      await post({ action: "restore", id: event.id });
      toast("success", "Event restored successfully.");
      await load();
    } catch (error) {
      toast(
        "error",
        error.response?.data?.message || "Unable to restore event.",
      );
    }
  }
  async function deleteEvent(event) {
    if (
      !(await confirmAction({
        title: "Delete event permanently?",
        message: `${event.title} and all assignments will be permanently removed. This cannot be undone.`,
        action: "Delete permanently",
      }))
    )
      return;
    try {
      await post({ action: "force_delete", id: event.id });
      toast("success", "Event permanently deleted.");
      await load();
    } catch (error) {
      toast(
        "error",
        error.response?.data?.message || "Unable to delete event.",
      );
    }
  }
  function renderPagination() {
    const nav = $("[data-pagination]"),
      p = state.pagination;
    nav.replaceChildren();
    nav.classList.toggle("hidden", p.last_page <= 1);
    nav.classList.toggle("flex", p.last_page > 1);
    if (p.last_page <= 1) return;
    const previous = makeButton(
        "Previous",
        "rounded-lg border border-slate-200 px-3 py-2 text-slate-600",
        () => go(page - 1),
      ),
      next = makeButton(
        "Next",
        "rounded-lg border border-slate-200 px-3 py-2 text-slate-600",
        () => go(page + 1),
      );
    previous.disabled = page === 1;
    next.disabled = page === p.last_page;
    const info = document.createElement("small");
    info.className = "text-slate-400";
    info.textContent = `Page ${page} of ${p.last_page}`;
    nav.append(previous, info, next);
  }
  async function go(target) {
    page = target;
    await load();
    syncUrl();
    scrollTo({ top: 0, behavior: "smooth" });
  }
  function syncUrl() {
    const url = new URL(location.href);
    url.search = "";
    new FormData(filters).forEach((value, key) => {
      if (value) url.searchParams.set(key, value);
    });
    if (page > 1) url.searchParams.set("page", page);
    history.replaceState(null, "", url);
  }
  async function load() {
    const params = Object.fromEntries(new FormData(filters)),
      selectedStatus = initialStatus || filters.status.value,
      selectedEventType = initialEventType || filters.event_type.value;
    if (selectedStatus) params.status = selectedStatus;
    if (selectedEventType) params.event_type = selectedEventType;
    params.page = page;
    const response = await axios.get("api/adviser-events.php", { params });
    state = response.data.data;
    page = state.pagination.current_page;
    fillFilters(selectedStatus, selectedEventType);
    initialStatus = "";
    initialEventType = "";
    render();
    $("[data-clear-filters]").classList.toggle(
      "hidden",
      ![...new FormData(filters).values()].some(Boolean),
    );
    if (!formController) {
      formController = EventForm.mount($("[data-event-form]"), state.metadata, {
        csrf,
        currentUserId,
        onSaved: (result) => {
          dialog.close();
          toast("success", result.message);
          page = 1;
          load();
        },
      });
      if (new URLSearchParams(location.search).get("create") === "1")
        dialog.showModal();
    }
  }
  function initializeQuery() {
    const query = new URLSearchParams(location.search);
    filters.search.value = query.get("search") || "";
    initialStatus = query.get("status") || "";
    initialEventType = query.get("event_type") || "";
    page = Math.max(1, Number(query.get("page")) || 1);
  }
  $("[data-create-event]").onclick = () => dialog.showModal();
  dialog
    .querySelectorAll("[data-dialog-close]")
    .forEach((button) => (button.onclick = () => dialog.close()));
  filters.onsubmit = async (event) => {
    event.preventDefault();
    const submit =
      event.submitter || filters.querySelector('button[type="submit"]');
    window.Notifications?.setLoading(submit, true, "Loading…");
    page = 1;
    try {
      await load();
      syncUrl();
    } catch (error) {
      toast(
        "error",
        error.response?.data?.message || "Unable to filter events.",
      );
    } finally {
      window.Notifications?.setLoading(submit, false);
    }
  };
  $("[data-clear-filters]").onclick = async () => {
    filters.reset();
    page = 1;
    try {
      await load();
      syncUrl();
    } catch (error) {
      toast(
        "error",
        error.response?.data?.message || "Unable to clear event filters.",
      );
    }
  };
  const sidebar = $("#sidebar"),
    scrim = $("[data-sidebar-scrim]");
  document.querySelectorAll("[data-sidebar-toggle]").forEach(
    (button) =>
      (button.onclick = () => {
        const open = sidebar.classList.contains("-translate-x-full");
        sidebar.classList.toggle("-translate-x-full", !open);
        sidebar.classList.toggle("translate-x-0", open);
        scrim.classList.toggle("hidden", !open);
      }),
  );
  $('form[action="api/auth.php?action=logout"]').onsubmit = async (event) => {
    event.preventDefault();
    try {
      await axios.post(
        "api/auth.php?action=logout",
        {},
        { headers: { "X-CSRF-Token": csrf } },
      );
    } finally {
      location.replace("./");
    }
  };
  initializeQuery();
  axios
    .get("api/auth.php?action=session")
    .then((response) => {
      const user = response.data.user;
      if (user?.role !== "SBO Adviser") {
        location.replace("./");
        return;
      }
      csrf = response.data.csrf_token;
      currentUserId = Number(user.id);
      const account = $("[data-account-menu]");
      account.querySelector("[data-account-initials]").textContent =
        EventForm.initials(user.full_name);
      account.querySelector("summary strong").textContent = user.full_name;
      account.querySelector("summary small").textContent = user.role;
      account.querySelector("div>div strong").textContent = user.full_name;
      account.querySelector("div>div span").textContent = user.email;
      return load();
    })
    .catch(() => location.replace("./"));
})();
