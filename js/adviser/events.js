window.SharedNavigation.ready.then((context) => {
  "use strict";
  let csrf = context.csrfToken,
    page = 1,
    state = null,
    formController = null,
    initialStatus = "",
    initialEventType = "",
    currentUserId = Number(context.user.id),
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

  const typesDialog = $("[data-event-types-dialog]");
  const typeForm = $("[data-event-type-form]");
  typeForm.dataset.axiosForm = "";
  function resetTypeForm() {
    typeForm.reset();
    typeForm.elements.type_id.value = "";
    $("[data-event-type-form-title]").textContent = "Add event type";
    $("[data-event-type-save]").textContent = "Add type";
    $("[data-event-type-cancel]").classList.add("hidden");
    $("[data-event-type-error]").classList.add("hidden");
  }
  function renderEventTypes() {
    const host = $("[data-event-types-list]");
    host.replaceChildren();
    const types = state?.metadata.event_types || [];
    if (!types.length) {
      host.innerHTML = '<p class="py-6 text-center text-xs text-slate-500">No event types yet. Add one below.</p>';
      return;
    }
    types.forEach((type) => {
      const row = document.createElement("div");
      row.className = "flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 py-3 last:border-b-0";
      row.innerHTML = `<div class="min-w-0"><strong class="block truncate text-sm">${EventForm.escapeHtml(type.label)}</strong><span class="text-[10px] text-slate-500">${type.events_count} ${type.events_count === 1 ? "event" : "events"}</span></div><div class="flex gap-1"><button class="min-h-9 rounded-lg px-3 text-xs font-bold text-[#397565] hover:bg-[#397565]/10" type="button" data-type-edit>Edit</button><button class="min-h-9 rounded-lg px-3 text-xs font-bold text-red-600 hover:bg-red-50 disabled:cursor-not-allowed disabled:text-slate-300" type="button" data-type-delete ${type.events_count ? 'disabled title="Used by an event"' : ''}>Delete</button></div>`;
      row.querySelector("[data-type-edit]").onclick = () => {
        typeForm.elements.type_id.value = type.id;
        typeForm.elements.label.value = type.label;
        $("[data-event-type-form-title]").textContent = "Rename event type";
        $("[data-event-type-save]").textContent = "Save changes";
        $("[data-event-type-cancel]").classList.remove("hidden");
        $("[data-event-type-error]").classList.add("hidden");
        typeForm.elements.label.focus();
      };
      row.querySelector("[data-type-delete]").onclick = async () => {
        if (!(await confirmAction({ title: "Delete event type?", message: `Delete ${type.label}? This type is not used by any event.`, action: "Delete" }))) return;
        try {
          await post({ action: "event_type_delete", type_id: type.id });
          await refreshEventTypes();
          toast("success", "Event type deleted.");
        } catch (error) {
          toast("error", error.response?.data?.message || "Unable to delete event type.");
        }
      };
      host.append(row);
    });
  }
  async function refreshEventTypes(preferredId = "") {
    const response = await axios.get("api/adviser-events.php", { params: { action: "event_types" } });
    const currentFormType = $("[data-event-form]").elements.event_type_id.value;
    const currentFilterType = filters.event_type.value;
    state.metadata.event_types = response.data.data;
    const select = $("[data-event-form]").elements.event_type_id;
    select.replaceChildren(new Option("Select event type", ""));
    state.metadata.event_types.forEach((type) => select.add(new Option(type.label, type.id)));
    const desired = String(preferredId || currentFormType);
    select.value = state.metadata.event_types.some((type) => String(type.id) === desired) ? desired : "";
    fillFilters(filters.status.value, currentFilterType);
    renderEventTypes();
  }
  $("[data-manage-event-types]").onclick = () => {
    resetTypeForm();
    renderEventTypes();
    typesDialog.showModal();
  };
  $("[data-close-event-types]").onclick = () => typesDialog.close();
  $("[data-event-type-cancel]").onclick = resetTypeForm;
  typesDialog.addEventListener("close", resetTypeForm);
  typeForm.onsubmit = async (event) => {
    event.preventDefault();
    const typeId = Number(typeForm.elements.type_id.value);
    const save = $("[data-event-type-save]");
    const errorHost = $("[data-event-type-error]");
    errorHost.classList.add("hidden");
    save.disabled = true;
    try {
      const result = await post({ action: typeId ? "event_type_update" : "event_type_create", type_id: typeId, label: typeForm.elements.label.value.trim() });
      await refreshEventTypes(result.data.id);
      toast("success", result.data.message);
      if (!typeId) typesDialog.close();
      else resetTypeForm();
    } catch (error) {
      errorHost.textContent = error.response?.data?.errors?.label?.[0] || error.response?.data?.message || "Unable to save event type.";
      errorHost.classList.remove("hidden");
    } finally {
      save.disabled = false;
    }
  };

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
      : `<a class="${itemClass}" href="pages/adviser/event-details.html?id=${event.id}" role="menuitem">View details</a><a class="${itemClass}" href="pages/adviser/event-details.html?id=${event.id}#event-activities" role="menuitem">Manage activities</a><a class="${itemClass}" href="pages/adviser/scoreboard.html?event_id=${event.id}" role="menuitem">Score activities</a><a class="${itemClass}" href="pages/adviser/event-edit.html?id=${event.id}" role="menuitem">Edit event</a><div class="my-1 border-t border-[#121017]/8"></div><button class="${destructiveClass}" type="button" data-menu-action="archive" role="menuitem">Archive event</button>`;
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
      const venueCount = Math.max(1, event.event_locations?.length || 0);
      const venueSummary = venueCount > 1
        ? `${EventForm.escapeHtml(event.location || "Not specified")} <small class="mt-0.5 block text-[10px] font-bold text-[#397565]">+${venueCount - 1} additional ${venueCount === 2 ? "venue" : "venues"}</small>`
        : EventForm.escapeHtml(event.location || "Not specified");
      const row = document.createElement("tr");
      row.className = `group cursor-pointer transition hover:bg-emerald-50/40 ${event.is_archived ? "bg-slate-50/70 opacity-75" : ""}`;
      row.tabIndex = 0;
      row.setAttribute("role", "link");
      row.setAttribute("aria-label", `View ${event.title}`);
      row.innerHTML = `<td class="px-5 py-4" data-event-cell="event"><div class="grid min-w-48 gap-1"><strong class="text-sm text-[#121017] transition group-hover:text-[#397565]">${EventForm.escapeHtml(event.title)}</strong><small class="max-w-sm truncate text-[10px] text-[#121017]/35">${EventForm.escapeHtml(event.description || "No description")}</small></div></td><td class="px-4 py-4" data-event-cell="schedule" data-label="Schedule"><span class="grid gap-0.5 text-xs font-semibold text-[#121017]/60">${formatDate(event.start_at)}<small class="font-medium text-[#121017]/35">${formatTime(event.start_at)}</small></span></td><td class="max-w-52 px-4 py-4 text-xs text-[#121017]/48" data-event-cell="location" data-label="Location"><span class="block">${venueSummary}</span></td><td class="px-4 py-4" data-event-cell="status" data-label="Status"><span class="rounded-full px-2.5 py-1 text-[10px] font-bold ${statusClass(event.status_label)}">${event.status_label[0].toUpperCase() + event.status_label.slice(1)}</span></td><td class="px-4 py-4" data-event-cell="in-charge" data-label="Event-in-Charge">${inChargeMarkup(event)}</td><td class="px-5 py-4 text-right" data-event-cell="actions"><div class="inline-flex" data-actions></div></td>`;
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
  initializeQuery();
  load()
    .catch((error) => {
      console.error("Event Management failed to initialize.", error);
      toast(
        "error",
        error.response?.data?.message ||
          "Event Management could not finish loading. Please refresh the page.",
      );
    })
    .finally(() => window.SharedNavigation.finishPageLoad());
});
