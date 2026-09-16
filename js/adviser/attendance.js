window.SharedNavigation.ready.then(() => {
  "use strict";

  const $ = (selector, root = document) => root.querySelector(selector);
  const filters = $("[data-attendance-filters]");
  const eventList = $("[data-event-list]");
  const pagination = $("[data-pagination]");
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

  function eventLifecycle(value) {
    return { upcoming: "Upcoming event", ongoing: "Event in progress", completed: "Event ended" }[value] || "Event schedule";
  }

  function attendancePresentation(event) {
    const expected = event.expected_count;
    const recorded = event.attendances_count;
    if (!expected) return {
      label: "No roster",
      tone: "bg-[#FF6B2C]/10 text-[#D64A12]",
      title: "Participant roster needed",
      detail: "Define the event audience before recording attendance.",
    };
    if (!recorded) return {
      label: "Not started",
      tone: "bg-[#FF6B2C]/10 text-[#D64A12]",
      title: "Attendance not recorded",
      detail: "Open the roster to begin marking participant attendance.",
    };
    if (recorded < expected) return {
      label: "In progress",
      tone: "bg-[#397565] text-white",
      title: "Attendance in progress",
      detail: `${expected - recorded} ${expected - recorded === 1 ? "student still needs" : "students still need"} a status.`,
    };
    return {
      label: "Complete",
      tone: "bg-[#C6F24E]/40 text-[#397565]",
      title: "Attendance complete",
      detail: "Every expected participant has a recorded status.",
    };
  }

  function statusMetric(label, value, color) {
    return `<span class="inline-flex items-center gap-2 text-xs font-bold text-[#121017]/55"><i class="h-2.5 w-2.5 rounded-full ${color}" aria-hidden="true"></i><strong class="text-[#121017]">${value.toLocaleString()}</strong> ${label}</span>`;
  }

  function renderEvent(event) {
    const expected = event.expected_count;
    const recorded = event.attendances_count;
    const coverage = event.coverage_rate ?? 0;
    const attendance = attendancePresentation(event);
    return `<article class="rounded-3xl border border-[#121017]/9 bg-white p-5 shadow-[0_16px_45px_rgba(18,16,23,.045)] sm:p-7">
      <header class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div class="min-w-0">
          <div class="flex flex-wrap items-center gap-2">
            <h3 class="text-xl font-black tracking-[-.035em] sm:text-2xl">${escapeHtml(event.title)}</h3>
            <span class="rounded-full bg-[#121017]/5 px-2.5 py-1 text-[9px] font-black uppercase tracking-wider text-[#121017]/40">${eventLifecycle(event.schedule_state)}</span>
          </div>
          <p class="mt-2 text-xs font-bold text-[#121017]/45">${eventSchedule(event)}</p>
          <p class="mt-1 text-xs font-semibold text-[#121017]/38">${escapeHtml(event.location || "Venue not specified")}</p>
        </div>
        <span class="w-fit rounded-full px-3 py-1.5 text-[9px] font-black uppercase tracking-wider ${attendance.tone}">${attendance.label}</span>
      </header>
      <div class="mt-6 border-t border-[#121017]/8 pt-5">
        <div class="flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
          <div class="min-w-0 flex-1">
            <p class="text-[9px] font-black uppercase tracking-[.15em] text-[#397565]">Attendance progress</p>
            <p class="mt-2 text-2xl font-black tracking-[-.035em]">${recorded.toLocaleString()} <span class="text-base text-[#121017]/35">/ ${expected.toLocaleString()} students recorded</span></p>
            <div class="mt-4 flex flex-wrap gap-x-6 gap-y-2">
              ${statusMetric("Present", event.present_count, "bg-[#397565]")}
              ${statusMetric("Late", event.late_count, "bg-[#FF6B2C]")}
              ${statusMetric("Absent", event.absent_count, "bg-[#121017]/30")}
            </div>
            <div class="mt-5 flex items-center gap-3">
              <div class="h-2 flex-1 overflow-hidden rounded-full bg-[#121017]/7"><i class="block h-full rounded-full ${recorded >= expected && expected ? "bg-[#397565]" : "bg-[#FF6B2C]"}" style="width:${coverage}%"></i></div>
              <strong class="w-10 text-right text-xs text-[#121017]/48">${event.coverage_rate === null ? "—" : `${event.coverage_rate}%`}</strong>
            </div>
            <h4 class="mt-5 text-sm font-black">${attendance.title}</h4>
            <p class="mt-1 text-xs leading-5 text-[#121017]/42">${attendance.detail}</p>
          </div>
          <a class="inline-flex min-h-11 shrink-0 items-center justify-center rounded-xl bg-[#397565] px-5 text-xs font-black text-white transition hover:bg-[#2f6255]" href="pages/adviser/attendance-roster.html?event_id=${event.id}">Open roster →</a>
        </div>
      </div>
    </article>`;
  }

  function renderEvents(data) {
    $("[data-result-count]").textContent = `${data.pagination.total.toLocaleString()} ${data.pagination.total === 1 ? "event" : "events"} available`;
    $("[data-clear-filters]").classList.toggle("hidden", !state.search && !state.timing);
    if (!data.events.length) {
      const filtered = state.search || state.timing;
      eventList.innerHTML = `<div class="rounded-3xl border border-[#121017]/9 bg-white px-6 py-16 text-center"><span class="text-4xl" aria-hidden="true">✓</span><h3 class="mt-5 text-lg font-black">${filtered ? "No events match these filters" : "There are no attendance rosters yet"}</h3><p class="mt-2 text-sm text-[#121017]/45">${filtered ? "Change or clear the current filters." : "Create an event and define its participants to begin."}</p><${filtered ? "button" : "a"} class="mt-5 inline-flex min-h-11 items-center rounded-xl bg-[#397565] px-5 text-xs font-black text-white" ${filtered ? 'type="button" data-empty-clear' : 'href="pages/adviser/events.html?create=1"'}>${filtered ? "Clear filters" : "Create event"}</${filtered ? "button" : "a"}></div>`;
    } else {
      eventList.innerHTML = data.events.map(renderEvent).join("");
    }
    renderPagination(data.pagination);
  }

  function renderPagination(meta) {
    pagination.classList.toggle("hidden", meta.last_page <= 1);
    pagination.classList.toggle("flex", meta.last_page > 1);
    if (meta.last_page <= 1) return;
    pagination.innerHTML = `<small>Showing ${meta.from}–${meta.to} of ${meta.total}</small><div class="flex gap-2"><button class="rounded-lg border px-3 py-2 font-black text-[#397565] disabled:opacity-30" type="button" data-page="${meta.current_page - 1}" ${meta.current_page === 1 ? "disabled" : ""}>Previous</button><button class="rounded-lg border px-3 py-2 font-black text-[#397565] disabled:opacity-30" type="button" data-page="${meta.current_page + 1}" ${meta.current_page === meta.last_page ? "disabled" : ""}>Next</button></div>`;
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
      eventList.innerHTML = `<p class="rounded-2xl bg-white p-10 text-center text-sm text-[#D64A12]">${escapeHtml(message)}</p>`;
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
  });
  pagination.addEventListener("click", (event) => {
    const button = event.target.closest("[data-page]");
    if (!button || button.disabled) return;
    state.page = Number(button.dataset.page);
    load();
    $("[data-result-count]").scrollIntoView({ behavior: "smooth", block: "center" });
  });

  initializeShell();
  authenticate().then(load).catch((error) => {
    if (error.message !== "Unauthorized") console.error(error);
  });
});
