window.SharedNavigation.ready.then(() => {
  "use strict";

  const $ = (selector, root = document) => root.querySelector(selector);
  const filters = $("[data-attendance-filters]");
  const eventList = $("[data-event-list]");
  const paginations = [...document.querySelectorAll("[data-pagination]")];
  const eventDialog = $("[data-event-attendance-dialog]");
  let visibleEvents = new Map();
  const initial = Object.fromEntries(new URLSearchParams(location.search));
  const state = {
    search: initial.search || "",
    timing: ["", "upcoming", "ongoing", "completed"].includes(initial.timing) ? initial.timing : "",
    page: Math.max(1, Number(initial.page) || 1),
    debounce: 0,
    requestId: 0,
  };

  const escapeHtml = (value) => String(value ?? "").replace(
    /[&<>'"]/g,
    (character) => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", "'": "&#39;", '"': "&quot;" })[character],
  );

  function eventSchedule(event) {
    const start = new Date(String(event.start_at).replace(" ", "T"));
    const end = new Date(String(event.end_at).replace(" ", "T"));
    if (Number.isNaN(start.getTime()) || Number.isNaN(end.getTime())) return "Schedule not set";
    const sameDay = start.toDateString() === end.toDateString();
    const date = new Intl.DateTimeFormat("en-PH", { month: "short", day: "numeric", year: "numeric" }).format(start);
    const time = (value) => new Intl.DateTimeFormat("en-PH", { hour: "numeric", minute: "2-digit" }).format(value);
    if (sameDay) return `${date} · ${time(start)}–${time(end)}`;
    const endDate = new Intl.DateTimeFormat("en-PH", { month: "short", day: "numeric", year: "numeric" }).format(end);
    return `${date}, ${time(start)} → ${endDate}, ${time(end)}`;
  }

  function attendanceDay(event) {
    const date = new Date(`${event.attendance_date}T00:00:00`);
    const formatted = Number.isNaN(date.getTime())
      ? event.attendance_date
      : new Intl.DateTimeFormat("en-PH", { month: "short", day: "numeric", year: "numeric" }).format(date);
    return `Day ${event.attendance_day_number} of ${event.attendance_day_count} · ${formatted}`;
  }

  function initializeShell() {
    const sidebar = $("#sidebar");
    const scrim = $("[data-sidebar-scrim]");
    const trigger = $('[aria-controls="sidebar"]');
    document.querySelectorAll("[data-sidebar-toggle]").forEach((button) => {
      button.onclick = () => {
        const opening = sidebar.classList.contains("-translate-x-full");
        sidebar.classList.toggle("-translate-x-full", !opening);
        sidebar.classList.toggle("translate-x-0", opening);
        scrim.classList.toggle("hidden", !opening);
        trigger?.setAttribute("aria-expanded", String(opening));
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
    applyAccount(response.data.user);
    $("form[action='api/auth.php?action=logout']").onsubmit = async (event) => {
      event.preventDefault();
      try {
        await axios.post("api/auth.php?action=logout", {}, { headers: { "X-CSRF-Token": response.data.csrf_token } });
      } finally {
        location.replace("./");
      }
    };
  }

  function syncUrl() {
    const url = new URL(location.href);
    url.search = "";
    if (state.search) url.searchParams.set("search", state.search);
    if (state.timing) url.searchParams.set("timing", state.timing);
    if (state.page > 1) url.searchParams.set("page", state.page);
    history.replaceState(null, "", url);
  }

  function renderSummary(summary) {
    const eventLabel = `${summary.events.toLocaleString()} ${summary.events === 1 ? "event" : "events"}`;
    const studentLabel = `${summary.students.toLocaleString()} ${summary.students === 1 ? "student" : "students"}`;
    const recordLabel = `${summary.records.toLocaleString()} attendance ${summary.records === 1 ? "record" : "records"}`;
    $("[data-attendance-summary]").textContent = `${eventLabel} · ${studentLabel} · ${recordLabel}`;
    $("[data-manage-events]").classList.toggle("hidden", !summary.events);
    $("[data-manage-events]").classList.toggle("inline-flex", Boolean(summary.events));
    filters.classList.toggle("hidden", !summary.events);
    filters.classList.toggle("grid", Boolean(summary.events));
  }

  function attendancePresentation(event) {
    const expected = event.expected_count;
    const recorded = event.attendances_count;
    const scheduleState = event.attendance_day_state;
    if (!expected) return {
      label: "No roster",
      tone: "bg-[#FF6B2C]/10 text-[#D64A12]",
      title: "Participant roster needed",
      detail: "Define the event audience before recording attendance.",
    };
    if (recorded >= expected) return {
      label: "Complete",
      tone: "bg-[#C6F24E]/40 text-[#397565]",
      title: "Attendance complete",
      detail: "Every expected participant has a recorded status.",
    };
    if (scheduleState === "completed") return {
      label: "Incomplete",
      tone: "bg-[#FF6B2C]/10 text-[#D64A12]",
      title: "Attendance incomplete",
      detail: `${expected - recorded} ${expected - recorded === 1 ? "student is" : "students are"} still missing an attendance status after the event ended.`,
    };
    if (scheduleState === "upcoming") return {
      label: "Not started",
      tone: "bg-[#FF6B2C]/10 text-[#D64A12]",
      title: "Event has not started",
      detail: recorded
        ? `${recorded} attendance ${recorded === 1 ? "record exists" : "records exist"}, but the event schedule has not started.`
        : "Attendance will begin when the event schedule starts.",
    };
    if (!recorded) return {
      label: "Waiting",
      tone: "bg-[#397565]/10 text-[#397565]",
      title: "Waiting for attendance",
      detail: "The event is ongoing, but no attendance has been recorded yet.",
    };
    if (recorded < expected) return {
      label: "In progress",
      tone: "bg-[#397565] text-white",
      title: "Attendance in progress",
      detail: `${expected - recorded} ${expected - recorded === 1 ? "student still needs" : "students still need"} a status.`,
    };
    return {
      label: "Not started",
      tone: "bg-[#FF6B2C]/10 text-[#D64A12]",
      title: "Attendance not started",
      detail: "Attendance has not started.",
    };
  }

  function renderEvent(event) {
    const expected = event.expected_count;
    const recorded = event.attendances_count;
    const attendance = attendancePresentation(event);
    return `<button class="group flex min-h-36 w-full flex-col justify-between rounded-2xl border border-[#121017]/10 bg-white p-5 text-left shadow-sm transition hover:border-[#397565]/40 hover:shadow-md focus-visible:outline focus-visible:outline-2 focus-visible:outline-[#397565]" type="button" data-event-id="${event.id}" aria-label="View ${escapeHtml(event.title)} attendance">
      <span class="flex w-full items-start justify-between gap-3"><strong class="text-lg font-black leading-tight">${escapeHtml(event.title)}</strong><span class="shrink-0 rounded-full px-2.5 py-1 text-[9px] font-black uppercase tracking-wide ${attendance.tone}">${attendance.label}</span></span>
      <span class="mt-3 block text-xs text-[#121017]/50">${escapeHtml(attendanceDay(event))}<br>${escapeHtml(event.location || "Venue not specified")}</span>
      <span class="mt-4 flex w-full items-center justify-between border-t border-[#121017]/8 pt-3 text-xs font-bold text-[#397565]"><span>${recorded.toLocaleString()} of ${expected.toLocaleString()} recorded</span><span class="group-hover:translate-x-1 transition-transform">View details →</span></span>
    </button>`;
  }

  function openEvent(event) {
    const attendance = attendancePresentation(event);
    $("[data-event-dialog-title]").textContent = event.title;
    $("[data-event-dialog-schedule]").textContent = `${attendanceDay(event)} · ${event.location || "Venue not specified"}`;
    $("[data-event-dialog-status]").textContent = attendance.detail;
    $("[data-event-dialog-counts]").innerHTML = `
      <div class="rounded-2xl bg-[#397565]/8 p-4"><span class="text-xs font-bold text-[#397565]">Present</span><strong class="mt-1 block text-3xl font-black text-[#397565]">${Number(event.present_count).toLocaleString()}</strong></div>
      <div class="rounded-2xl bg-red-50 p-4"><span class="text-xs font-bold text-red-700">Absent</span><strong class="mt-1 block text-3xl font-black text-red-700">${Number(event.absent_count).toLocaleString()}</strong></div>
      <div class="rounded-2xl bg-[#121017]/5 p-4"><span class="text-xs font-bold text-[#121017]/55">Awaiting</span><strong class="mt-1 block text-3xl font-black text-[#121017]">${Number(event.awaiting_count).toLocaleString()}</strong></div>`;
    $("[data-event-dialog-recorded]").textContent = `${event.attendances_count} of ${event.expected_count} students finalized`;
    $("[data-event-dialog-roster]").href = `pages/adviser/attendance-roster.html?event_id=${encodeURIComponent(event.id)}&date=${encodeURIComponent(event.attendance_date)}`;
    eventDialog.showModal();
  }

  function renderEvents(data) {
    visibleEvents = new Map(data.events.map(event => [String(event.id), event]));
    $("[data-result-count]").textContent = `${data.pagination.total.toLocaleString()} ${data.pagination.total === 1 ? "event" : "events"} available`;
    $("[data-clear-filters]").classList.toggle("hidden", !state.search && !state.timing);
    if (!data.events.length) {
      const filtered = state.search || state.timing;
      eventList.innerHTML = `<div class="rounded-3xl border border-[#121017]/9 bg-white px-6 py-16 text-center md:col-span-2 xl:col-span-3"><span class="text-4xl" aria-hidden="true">✓</span><h3 class="mt-5 text-lg font-black">${filtered ? "No events match these filters" : "There are no attendance rosters yet"}</h3><p class="mt-2 text-sm text-[#121017]/45">${filtered ? "Change or clear the current filters." : "Create an event and define its participants to begin."}</p><${filtered ? "button" : "a"} class="mt-5 inline-flex min-h-11 items-center rounded-xl bg-[#397565] px-5 text-xs font-black text-white" ${filtered ? 'type="button" data-empty-clear' : 'href="pages/adviser/events.html?create=1"'}>${filtered ? "Clear filters" : "Create event"}</${filtered ? "button" : "a"}></div>`;
    } else {
      eventList.innerHTML = data.events.map(renderEvent).join("");
    }
    renderPagination(data.pagination);
  }

  function renderPagination(meta) {
    paginations.forEach(pagination => {
      pagination.classList.toggle("hidden", meta.last_page <= 1);
      pagination.classList.toggle("flex", meta.last_page > 1);
      if (meta.last_page <= 1) return;
      pagination.innerHTML = `<small>Page ${meta.current_page} of ${meta.last_page} · ${meta.from}–${meta.to} of ${meta.total}</small><div class="flex gap-2"><button class="min-h-10 rounded-lg border px-3 py-2 font-black text-[#397565] disabled:opacity-30" type="button" data-page="${meta.current_page - 1}" ${meta.current_page === 1 ? "disabled" : ""}>Previous</button><button class="min-h-10 rounded-lg border px-3 py-2 font-black text-[#397565] disabled:opacity-30" type="button" data-page="${meta.current_page + 1}" ${meta.current_page === meta.last_page ? "disabled" : ""}>Next</button></div>`;
    });
  }

  async function load() {
    const requestId = ++state.requestId;
    syncUrl();
    eventList.style.opacity = "0.55";
    try {
      const response = await axios.get("api/attendance.php", { params: { search: state.search, timing: state.timing, page: state.page } });
      if (requestId !== state.requestId) return;
      const data = response.data.data;
      state.page = data.pagination.current_page;
      renderSummary(data.summary);
      renderEvents(data);
    } catch (error) {
      if (requestId !== state.requestId) return;
      const message = error.response?.data?.message || "Attendance events could not be loaded.";
      window.Notifications?.error?.(message);
      eventList.innerHTML = `<p class="rounded-2xl bg-white p-10 text-center text-sm text-[#D64A12] md:col-span-2 xl:col-span-3">${escapeHtml(message)}</p>`;
    } finally {
      if (requestId === state.requestId) eventList.style.opacity = "";
    }
  }

  filters.search.value = state.search;
  filters.timing.value = state.timing;
  filters.addEventListener("submit", (event) => {
    event.preventDefault();
    clearTimeout(state.debounce);
    state.search = filters.search.value.trim();
    state.page = 1;
    load();
  });
  filters.search.addEventListener("input", () => {
    clearTimeout(state.debounce);
    state.debounce = setTimeout(() => {
      state.search = filters.search.value.trim();
      state.page = 1;
      load();
    }, 320);
  });
  filters.timing.addEventListener("change", () => {
    clearTimeout(state.debounce);
    state.search = filters.search.value.trim();
    state.timing = filters.timing.value;
    state.page = 1;
    load();
  });
  $("[data-clear-filters]").addEventListener("click", () => {
    state.search = "";
    state.timing = "";
    state.page = 1;
    filters.search.value = "";
    filters.timing.value = "";
    load();
  });
  eventList.addEventListener("click", (event) => {
    if (event.target.closest("[data-empty-clear]")) $("[data-clear-filters]").click();
    const card = event.target.closest("[data-event-id]");
    if (card) { const selected = visibleEvents.get(card.dataset.eventId); if (selected) openEvent(selected); }
  });
  eventDialog.querySelector("[data-event-dialog-close]").onclick = () => eventDialog.close();
  eventDialog.addEventListener("click", event => { if (event.target === eventDialog) eventDialog.close(); });
  const changePage = async (event) => {
    const button = event.target.closest("[data-page]");
    if (!button || button.disabled) return;
    state.page = Number(button.dataset.page);
    await load();
    $("[data-result-count]").scrollIntoView({ behavior: "smooth", block: "start" });
  };
  paginations.forEach(pagination => pagination.addEventListener("click", changePage));

  initializeShell();
  authenticate().then(load).catch((error) => {
    if (error.message !== "Unauthorized") console.error(error);
  });
});
