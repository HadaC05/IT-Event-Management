(() => {
  "use strict";
  let csrf = "",
    page = 1,
    state = null,
    formController = null,
    initialStatus = "",
    initialEventType = "",
    currentUserId = 0,
    filterTimer = 0,
    loadRequest = 0,
    shouldOpenCreate = new URLSearchParams(location.search).get("create") === "1";
  const $ = (selector) => document.querySelector(selector),
    filters = $("[data-event-filters]"),
    rows = $("[data-event-rows]"),
    dialog = $("#create-event-dialog");
  const actionMenu = document.createElement("div");
  actionMenu.className =
    "fixed z-[80] hidden w-52 overflow-hidden rounded-xl border border-[#121017]/10 bg-white p-1.5 shadow-2xl";
  actionMenu.setAttribute("role", "menu");
  document.body.append(actionMenu);
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

  function closeActionMenu() {
    actionMenu.classList.add("hidden");
    actionMenu.replaceChildren();
  }

  function openActionMenu(trigger, event) {
    const itemClass =
      "flex min-h-10 w-full items-center rounded-lg px-3 text-left text-xs font-bold transition hover:bg-[#397565]/8 hover:text-[#397565]";
    const destructiveClass =
      "flex min-h-10 w-full items-center rounded-lg px-3 text-left text-xs font-bold text-[#D64A12] transition hover:bg-[#FF6B2C]/8";
    actionMenu.innerHTML = event.is_archived
      ? `<a class="${itemClass}" href="pages/adviser/event-details.html?id=${event.id}" role="menuitem">View details</a><button class="${itemClass}" type="button" data-menu-action="restore" role="menuitem">Restore event</button><div class="my-1 border-t border-[#121017]/8"></div><button class="${destructiveClass}" type="button" data-menu-action="delete" role="menuitem">Delete permanently</button>`
      : `<a class="${itemClass}" href="pages/adviser/event-details.html?id=${event.id}" role="menuitem">View details</a><a class="${itemClass}" href="pages/adviser/event-edit.html?id=${event.id}" role="menuitem">Edit event</a><button class="${itemClass}" type="button" data-menu-action="duplicate" role="menuitem">Duplicate event</button><div class="my-1 border-t border-[#121017]/8"></div><button class="${destructiveClass}" type="button" data-menu-action="archive" role="menuitem">Archive event</button>`;
    actionMenu.dataset.eventId = String(event.id);
    actionMenu.classList.remove("hidden");
    actionMenu.style.visibility = "hidden";
    const triggerRect = trigger.getBoundingClientRect();
    const menuRect = actionMenu.getBoundingClientRect();
    const left = Math.max(12, Math.min(innerWidth - menuRect.width - 12, triggerRect.right - menuRect.width));
    const top = triggerRect.bottom + menuRect.height + 12 > innerHeight
      ? Math.max(12, triggerRect.top - menuRect.height - 6)
      : triggerRect.bottom + 6;
    actionMenu.style.left = `${left}px`;
    actionMenu.style.top = `${top}px`;
    actionMenu.style.visibility = "";
    actionMenu.querySelector("a,button")?.focus();
  }

  function inChargeMarkup(event) {
    const assigned = event.assigned_users;
    if (!assigned.length) return '<span class="text-[10px] font-bold text-[#121017]/35">Unassigned</span>';
    const lead = assigned[0];
    const names = assigned.map((user) => user.full_name).join(", ");
    const more = assigned.length > 1 ? ` +${assigned.length - 1}` : "";
    return `<div class="flex items-center gap-2" title="${EventForm.escapeHtml(names)}"><span class="grid h-8 w-8 shrink-0 place-items-center rounded-full border-2 border-white bg-[#C6F24E]/40 text-[8px] font-black text-[#397565] ring-1 ring-[#397565]/12">${EventForm.initials(lead.full_name)}</span><span class="max-w-36 truncate text-[10px] font-bold text-[#121017]/48">${EventForm.escapeHtml(lead.full_name)}${more}</span></div>`;
  }
  function render() {
    closeActionMenu();
    rows.replaceChildren();
    if (!state.events.length) {
      const row = document.createElement("tr");
      row.innerHTML = `<td class="px-5 py-16 text-center" colspan="6"><strong class="text-sm text-slate-600">No events found</strong><p class="mt-1 text-xs text-slate-400">${[...new FormData(filters).values()].some(Boolean) ? "Try changing your filters." : "Create your first event to get started."}</p></td>`;
      rows.append(row);
    }
    state.events.forEach((event) => {
      const row = document.createElement("tr");
      row.className = `group cursor-pointer transition hover:bg-emerald-50/40 ${event.is_archived ? "bg-slate-50/70 opacity-75" : ""}`;
      row.tabIndex = 0;
      row.setAttribute("role", "link");
      row.setAttribute("aria-label", `View ${event.title}`);
      row.innerHTML = `<td class="px-5 py-4"><div class="grid min-w-48 gap-1"><strong class="text-sm text-[#121017] transition group-hover:text-[#397565]">${EventForm.escapeHtml(event.title)}</strong><small class="max-w-sm truncate text-[10px] text-[#121017]/35">${EventForm.escapeHtml(event.description || "No description")}</small></div></td><td class="px-4 py-4"><span class="grid gap-0.5 text-xs font-semibold text-[#121017]/60">${formatDate(event.start_at)}<small class="font-medium text-[#121017]/35">${formatTime(event.start_at)}</small></span></td><td class="max-w-52 px-4 py-4 text-xs text-[#121017]/48"><span class="block truncate">${EventForm.escapeHtml(event.location || "Not specified")}</span></td><td class="px-4 py-4"><span class="rounded-full px-2.5 py-1 text-[10px] font-bold ${statusClass(event.status_label)}">${event.status_label[0].toUpperCase() + event.status_label.slice(1)}</span></td><td class="px-4 py-4">${inChargeMarkup(event)}</td><td class="px-5 py-4 text-right"><div class="inline-flex" data-actions></div></td>`;
      const actions = row.querySelector("[data-actions]");
      const menu = makeButton(
        "",
        "grid h-10 w-10 place-items-center rounded-xl text-lg font-black tracking-[.12em] text-[#121017]/35 transition hover:bg-[#121017]/6 hover:text-[#397565]",
        (click) => {
          click.stopPropagation();
          if (!actionMenu.classList.contains("hidden") && actionMenu.dataset.eventId === String(event.id)) closeActionMenu();
          else openActionMenu(menu, event);
        },
      );
      menu.innerHTML = '<span aria-hidden="true">•••</span><span class="sr-only">Event actions</span>';
      menu.setAttribute("aria-haspopup", "menu");
      actions.append(menu);
      const openDetails = () => location.assign(`pages/adviser/event-details.html?id=${event.id}`);
      row.onclick = (click) => {
        if (!click.target.closest("a,button")) openDetails();
      };
      row.onkeydown = (key) => {
        if ((key.key === "Enter" || key.key === " ") && !key.target.closest("a,button")) {
          key.preventDefault();
          openDetails();
        }
      };
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
  async function duplicateEvent(event) {
    try {
      const response = await post({ action: "duplicate", id: event.id });
      toast("success", "Event duplicated. Review the copied details before saving changes.");
      location.assign(`pages/adviser/event-edit.html?id=${response.data.id}`);
    } catch (error) {
      toast(
        "error",
        error.response?.data?.message || "Unable to duplicate event.",
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
        "←",
        "grid h-9 w-9 place-items-center rounded-lg border border-slate-200 text-slate-600 disabled:opacity-40",
        () => go(page - 1),
      ),
      next = makeButton(
        "→",
        "grid h-9 w-9 place-items-center rounded-lg border border-slate-200 text-slate-600 disabled:opacity-40",
        () => go(page + 1),
      );
    previous.disabled = page === 1;
    next.disabled = page === p.last_page;
    const info = document.createElement("small");
    info.className = "font-semibold text-slate-400";
    info.textContent = `Showing ${p.from}–${p.to} of ${p.total}`;
    const controls = document.createElement("div");
    controls.className = "flex items-center gap-2";
    const current = document.createElement("span");
    current.className = "grid h-9 w-9 place-items-center rounded-lg bg-[#397565] font-black text-white";
    current.textContent = page;
    controls.append(previous, current, next);
    nav.append(info, controls);
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
    const requestId = ++loadRequest;
    const params = Object.fromEntries(new FormData(filters)),
      selectedStatus = initialStatus || filters.status.value,
      selectedEventType = initialEventType || filters.event_type.value;
    if (selectedStatus) params.status = selectedStatus;
    if (selectedEventType) params.event_type = selectedEventType;
    params.page = page;
    rows.style.opacity = "0.5";
    try {
      const response = await axios.get("api/adviser-events.php", { params });
      if (requestId !== loadRequest) return;
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
      syncUrl();
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
        if (shouldOpenCreate) {
          shouldOpenCreate = false;
          dialog.showModal();
        }
      }
    } finally {
      if (requestId === loadRequest) rows.style.opacity = "";
    }
  }
  function initializeQuery() {
    const query = new URLSearchParams(location.search);
    filters.search.value = query.get("search") || "";
    initialStatus = query.get("status") || "";
    initialEventType = query.get("event_type") || "";
    page = Math.max(1, Number(query.get("page")) || 1);
  }
  actionMenu.addEventListener("click", async (click) => {
    const action = click.target.closest("[data-menu-action]")?.dataset.menuAction;
    if (!action) return;
    click.stopPropagation();
    const event = state?.events.find((item) => String(item.id) === actionMenu.dataset.eventId);
    closeActionMenu();
    if (!event) return;
    if (action === "duplicate") await duplicateEvent(event);
    if (action === "archive") await archiveEvent(event);
    if (action === "restore") await restoreEvent(event);
    if (action === "delete") await deleteEvent(event);
  });
  document.addEventListener("click", (click) => {
    if (!actionMenu.contains(click.target)) closeActionMenu();
  });
  document.addEventListener("keydown", (key) => {
    if (key.key === "Escape") closeActionMenu();
  });
  addEventListener("resize", closeActionMenu);
  addEventListener("scroll", closeActionMenu, true);
  $("[data-create-event]").onclick = () => dialog.showModal();
  dialog
    .querySelectorAll("[data-dialog-close]")
    .forEach((button) => (button.onclick = () => dialog.close()));
  async function applyFilters() {
    page = 1;
    try {
      await load();
    } catch (error) {
      toast(
        "error",
        error.response?.data?.message || "Unable to filter events.",
      );
    }
  }
  filters.onsubmit = (event) => {
    event.preventDefault();
    clearTimeout(filterTimer);
    applyFilters();
  };
  filters.search.addEventListener("input", () => {
    clearTimeout(filterTimer);
    filterTimer = setTimeout(applyFilters, 320);
  });
  [filters.status, filters.event_type].forEach((select) => {
    select.addEventListener("change", () => {
      clearTimeout(filterTimer);
      applyFilters();
    });
  });
  $("[data-clear-filters]").onclick = async () => {
    clearTimeout(filterTimer);
    filters.search.value = "";
    filters.status.value = "";
    filters.event_type.value = "";
    await applyFilters();
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
    .catch((error) => {
      if ([401, 403].includes(Number(error.response?.status))) {
        location.replace("./");
        return;
      }
      console.error("Event Management failed to initialize.", error);
      toast(
        "error",
        error.response?.data?.message ||
          "Event Management could not finish loading. Please refresh the page.",
      );
    });
})();
