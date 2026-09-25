window.SharedNavigation.ready.then(() => {
  "use strict";

  const $ = (selector, root = document) => root.querySelector(selector);
  const $$ = (selector, root = document) => [...root.querySelectorAll(selector)];
  const filters = $("[data-report-filters]");
  const results = $("[data-report-results]");
  const initial = Object.fromEntries(new URLSearchParams(location.search));
  const validTypes = ["attendance", "participation", "scores", "rankings"];
  const filterNames = ["event_id", "academic_period_id", "category_id", "status", "date_from", "date_to", "search"];
  const stateNames = [...filterNames, "time_sort"];
  const state = {
    type: validTypes.includes(initial.type) ? initial.type : "attendance",
    page: Math.max(1, Number(initial.page) || 1),
    values: Object.fromEntries(stateNames.map((name) => [name, name === "time_sort" ? (initial[name] === "earliest" ? "earliest" : "latest") : (initial[name] || "")])),
    requestId: 0,
    debounce: null,
  };

  const definitions = {
    attendance: {
      eyebrow: "Attendance records",
      title: "Attendance Records",
      summary: [["Records", "records"], ["Present", "attended"], ["Absent", "absent"], ["Attendance rate", "rate", "%"]],
      emptyTitle: "No attendance records yet",
      emptyText: "Records will appear here when students check in.",
    },
    participation: {
      eyebrow: "Event participation",
      title: "Participation Records",
      summary: [["Events", "events"], ["Expected", "expected"], ["Attended", "attended"], ["Participation rate", "rate", "%"]],
      emptyTitle: "No participation records yet",
      emptyText: "Participation appears after events have students and attendance activity.",
    },
    scores: {
      eyebrow: "Competition scores",
      title: "Score Records",
      summary: [["Entries", "entries"], ["Scored tribes", "teams"], ["Points awarded", "points", "points"], ["Average score", "average", "points"]],
      emptyTitle: "No scores recorded yet",
      emptyText: "Scores will appear here after judging begins.",
    },
    rankings: {
      eyebrow: "Tribe standings",
      title: "Ranking Records",
      summary: [["Ranked tribes", "ranked"], ["Eligible tribes", "eligible"], ["Points awarded", "points", "points"], ["Current leader", "leader", "text"]],
      emptyTitle: "No rankings available yet",
      emptyText: "Rankings will appear after tribes receive scores.",
    },
  };

  const escapeHtml = (value) =>
    String(value ?? "").replace(
      /[&<>'"]/g,
      (character) => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", "'": "&#39;", '"': "&quot;" })[character],
    );
  const formatNumber = (value) => Number(value || 0).toLocaleString("en-PH");
  const formatPoints = (value) => Number(value).toFixed(2).replace(/\.00$/, "").replace(/(\.\d)0$/, "$1");
  const asDate = (value) => new Date(String(value).replace(" ", "T"));
  const formatDate = (value) => value ? new Intl.DateTimeFormat("en-PH", { month: "short", day: "numeric", year: "numeric" }).format(asDate(value)) : "—";
  const formatTime = (value) => value ? new Intl.DateTimeFormat("en-PH", { hour: "numeric", minute: "2-digit" }).format(asDate(value)) : "Not checked in";
  const formatDateTime = (value) => value ? `${formatDate(value)} · ${formatTime(value)}` : "—";

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
      const dateArea = $("[data-date-field]");
      if (!dateArea?.contains(event.target)) closeDatePopover();
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

  function displayValue(summary, key, mode) {
    const value = summary[key];
    if (value === null || value === undefined || value === "") return "—";
    if (mode === "%") return `${formatPoints(value)}%`;
    if (mode === "points") return `${formatPoints(value)} pts`;
    return typeof value === "number" ? formatNumber(value) : value;
  }

  function renderSummary(summary) {
    $("[data-summary]").innerHTML = definitions[state.type].summary.map(([label, key, mode], index) => `
      <div class="flex items-baseline gap-2 border-b border-[#121017]/7 px-5 py-5 sm:px-6 ${index % 2 === 0 ? "sm:border-r" : ""} ${index >= 2 ? "sm:border-b-0" : ""} ${index < 3 ? "xl:border-r" : ""} xl:border-b-0">
        <strong class="truncate text-2xl font-black ${index === 3 ? "text-[#397565]" : ""}">${escapeHtml(displayValue(summary, key, mode))}</strong>
        <span class="text-[10px] font-bold text-[#121017]/38">${escapeHtml(label)}</span>
      </div>`).join("");
  }

  function statusBadge(status) {
    const tone = status === "present"
      ? "bg-[#C6F24E]/35 text-[#397565]"
      : status === "incomplete"
        ? "bg-amber-50 text-amber-700"
        : "bg-[#FF6B2C]/10 text-[#FF6B2C]";
    return `<span class="rounded-full px-2.5 py-1 text-[9px] font-black uppercase tracking-wider ${tone}">${escapeHtml(status === "incomplete" ? "Incomplete scan" : status)}</span>`;
  }

  function attendanceTable(rows) {
    const body = rows.map((row) => {
      const detail = [row.id_number, row.team_name, row.year_level].filter(Boolean).join(" · ");
      return `<tr class="transition hover:bg-[#397565]/[.025]"><td class="px-6 py-4"><strong class="block text-xs">${escapeHtml(row.student_name || "Deleted user")}</strong><small class="mt-0.5 block text-[10px] text-[#121017]/38">${escapeHtml(detail || "No student details")}</small></td><td class="px-3 py-4 text-xs font-bold">${escapeHtml(row.event_title || "Deleted event")}<small class="mt-0.5 block text-[10px] font-medium text-[#121017]/38">${escapeHtml(row.academic_period_label || "Period not set")}</small></td><td class="px-3 py-4 text-xs text-[#121017]/55">${formatDate(row.attendance_date)}</td><td class="px-3 py-4">${statusBadge(row.status)}</td><td class="px-3 py-4 text-xs text-[#121017]/48">${row.time_in_at ? formatTime(row.time_in_at) : "Not scanned"}</td><td class="px-6 py-4 text-xs text-[#121017]/48">${row.time_out_at ? formatTime(row.time_out_at) : "Not scanned"}</td></tr>`;
    }).join("");
    return `<div class="overflow-x-auto"><table class="w-full min-w-[960px] text-left"><thead><tr class="bg-[#121017]/[.025] text-[9px] font-black uppercase tracking-[.14em] text-[#121017]/38"><th class="px-6 py-3.5">Student</th><th class="px-3 py-3.5">Event</th><th class="px-3 py-3.5">Date</th><th class="px-3 py-3.5">Status</th><th class="px-3 py-3.5">Time In</th><th class="px-6 py-3.5">Time Out</th></tr></thead><tbody class="divide-y divide-[#121017]/7">${body}</tbody></table></div>`;
  }

  function participationTable(rows) {
    const body = rows.map((row) => `<tr class="transition hover:bg-[#397565]/[.025]"><td class="px-6 py-4"><strong class="block text-xs">${escapeHtml(row.title)}</strong><small class="mt-0.5 block text-[10px] text-[#121017]/38">${escapeHtml(row.location || "Venue not set")} · ${escapeHtml(row.academic_period_label || "Period not set")}</small></td><td class="px-3 py-4"><span class="block text-xs font-bold">${formatDate(row.start_at)}</span><small class="text-[10px] text-[#121017]/38">${formatTime(row.start_at)}</small></td><td class="px-3 py-4 text-xs font-black">${formatNumber(row.expected_count)}</td><td class="px-3 py-4 text-xs font-black">${formatNumber(row.recorded_count)}</td><td class="px-3 py-4 text-xs font-black text-[#397565]">${formatNumber(row.attended_count)}</td><td class="px-6 py-4 text-right"><strong class="text-sm font-black">${row.participation_rate === null ? "—" : `${formatPoints(row.participation_rate)}%`}</strong><div class="ml-auto mt-2 h-1.5 w-24 overflow-hidden rounded-full bg-[#121017]/7"><i class="block h-full rounded-full bg-[#397565]" style="width:${Number(row.participation_rate || 0)}%"></i></div></td></tr>`).join("");
    return `<div class="overflow-x-auto"><table class="w-full min-w-[900px] text-left"><thead><tr class="bg-[#121017]/[.025] text-[9px] font-black uppercase tracking-[.14em] text-[#121017]/38"><th class="px-6 py-3.5">Event</th><th class="px-3 py-3.5">Schedule</th><th class="px-3 py-3.5">Expected</th><th class="px-3 py-3.5">Recorded</th><th class="px-3 py-3.5">Attended</th><th class="px-6 py-3.5 text-right">Participation</th></tr></thead><tbody class="divide-y divide-[#121017]/7">${body}</tbody></table></div>`;
  }

  function scoresTable(rows) {
    const body = rows.map((row) => `<tr class="transition hover:bg-[#397565]/[.025]"><td class="px-6 py-4"><strong class="block text-xs">${escapeHtml(row.event_title || "Deleted event")}</strong><small class="text-[10px] text-[#121017]/38">${formatDate(row.event_start_at)} · ${escapeHtml(row.academic_period_label || "Period not set")}</small></td><td class="px-3 py-4"><span class="inline-flex items-center gap-2 text-xs font-black"><i class="h-2.5 w-2.5 rounded-full" style="background:${escapeHtml(row.color || "#397565")}"></i>${escapeHtml(row.team_name || "Deleted tribe")}</span></td><td class="px-3 py-4 text-xs font-bold">${escapeHtml(row.category_name || "Removed criterion")}</td><td class="px-3 py-4"><strong class="text-base font-black text-[#397565]">${formatPoints(row.points)}</strong><small class="ml-1 text-[10px] text-[#121017]/35">/ ${formatPoints(row.max_points)}</small></td><td class="px-3 py-4 text-xs text-[#121017]/52">${escapeHtml(row.recorder_name || "System")}</td><td class="px-6 py-4 text-xs text-[#121017]/45">${formatDateTime(row.updated_at)}</td></tr>`).join("");
    return `<div class="overflow-x-auto"><table class="w-full min-w-[980px] text-left"><thead><tr class="bg-[#121017]/[.025] text-[9px] font-black uppercase tracking-[.14em] text-[#121017]/38"><th class="px-6 py-3.5">Event</th><th class="px-3 py-3.5">Tribe</th><th class="px-3 py-3.5">Criterion</th><th class="px-3 py-3.5">Points</th><th class="px-3 py-3.5">Recorded by</th><th class="px-6 py-3.5">Updated</th></tr></thead><tbody class="divide-y divide-[#121017]/7">${body}</tbody></table></div>`;
  }

  function rankingsTable(rows) {
    const body = rows.map((row) => `<tr class="transition hover:bg-[#397565]/[.025]"><td class="px-6 py-4"><span class="grid h-9 w-9 place-items-center rounded-full text-xs font-black ${row.rank === 1 ? "bg-[#C6F24E] text-[#121017]" : "bg-[#397565]/10 text-[#397565]"}">${row.rank ?? "—"}</span></td><td class="px-3 py-4"><span class="inline-flex items-center gap-2 text-sm font-black"><i class="h-3 w-3 rounded-full" style="background:${escapeHtml(row.color)}"></i>${escapeHtml(row.name)}</span><small class="mt-0.5 block text-[10px] text-[#121017]/38">${formatNumber(row.members_count)} ${row.members_count === 1 ? "member" : "members"}</small></td><td class="px-3 py-4 text-xs font-bold text-[#121017]/55">${escapeHtml(row.school_year_label || "—")}</td><td class="px-3 py-4"><strong class="block text-xs">${formatNumber(row.score_entries_count)} ${row.score_entries_count === 1 ? "entry" : "entries"}</strong><small class="text-[10px] text-[#121017]/38">${formatNumber(row.scored_events_count)} scored ${row.scored_events_count === 1 ? "event" : "events"}</small></td><td class="px-6 py-4 text-right">${row.has_score ? `<strong class="text-lg font-black text-[#397565]">${formatPoints(row.total_score)}</strong><small class="ml-1 text-[10px] text-[#121017]/30">pts</small>` : '<span class="rounded-full bg-[#121017]/5 px-3 py-1.5 text-[9px] font-black uppercase tracking-wider text-[#121017]/35">Not scored</span>'}</td></tr>`).join("");
    return `<div class="overflow-x-auto"><table class="w-full min-w-[900px] text-left"><thead><tr class="bg-[#121017]/[.025] text-[9px] font-black uppercase tracking-[.14em] text-[#121017]/38"><th class="px-6 py-3.5">Rank</th><th class="px-3 py-3.5">Tribe</th><th class="px-3 py-3.5">School year</th><th class="px-3 py-3.5">Coverage</th><th class="px-6 py-3.5 text-right">Total points</th></tr></thead><tbody class="divide-y divide-[#121017]/7">${body}</tbody></table></div>`;
  }

  function hasActiveFilters() {
    return filterNames.some((name) => state.values[name]);
  }

  function emptyState(data) {
    const filtered = data.has_any_data && hasActiveFilters();
    const definition = definitions[state.type];
    return `<div class="px-6 py-16 text-center"><span class="mx-auto grid h-14 w-14 place-items-center rounded-full bg-[#397565]/8 text-[#397565]"><svg class="h-6 w-6 fill-none stroke-current stroke-2" viewBox="0 0 24 24"><path d="M5 3h10l4 4v14H5V3Zm10 0v5h4M8 12h8M8 16h6"/></svg></span><h3 class="mt-5 text-lg font-black">${filtered ? "No records match your filters" : definition.emptyTitle}</h3><p class="mx-auto mt-2 max-w-md text-sm leading-6 text-[#121017]/45">${filtered ? "Change or clear the current filters to see more results." : definition.emptyText}</p>${filtered ? '<button class="mt-5 min-h-10 rounded-xl border border-[#397565]/25 px-4 text-xs font-black text-[#397565]" type="button" data-empty-clear>Clear filters</button>' : ""}</div>`;
  }

  function renderResults(data) {
    $("[data-result-count]").textContent = `${formatNumber(data.pagination.total)} ${data.pagination.total === 1 ? "record" : "records"}`;
    results.innerHTML = data.rows.length
      ? { attendance: attendanceTable, participation: participationTable, scores: scoresTable, rankings: rankingsTable }[state.type](data.rows)
      : emptyState(data);
    renderPagination(data.pagination);
  }

  function renderPagination(pagination) {
    const nav = $("[data-pagination]");
    nav.classList.toggle("hidden", pagination.last_page <= 1);
    nav.classList.toggle("flex", pagination.last_page > 1);
    if (pagination.last_page <= 1) return;
    nav.innerHTML = `<span class="font-bold text-[#121017]/40">Page ${pagination.page} of ${pagination.last_page}</span><span class="flex gap-2"><button class="min-h-10 rounded-xl border border-[#121017]/10 px-4 font-black disabled:opacity-30" data-page="${pagination.page - 1}" ${pagination.page === 1 ? "disabled" : ""}>Previous</button><button class="min-h-10 rounded-xl border border-[#121017]/10 px-4 font-black disabled:opacity-30" data-page="${pagination.page + 1}" ${pagination.page === pagination.last_page ? "disabled" : ""}>Next</button></span>`;
  }

  function option(value, label, selected) {
    return `<option value="${value}" ${String(selected) === String(value) ? "selected" : ""}>${escapeHtml(label)}</option>`;
  }

  function fillFilters(data) {
    filters.event_id.innerHTML = '<option value="">All events</option>' + data.events.map((event) => option(event.id, `${event.title} · ${formatDate(event.start_at)}`, state.values.event_id)).join("");
    filters.academic_period_id.innerHTML = '<option value="">All academic periods</option>' + data.academic_periods.map((period) => option(period.id, period.label, state.values.academic_period_id)).join("");
    filters.category_id.innerHTML = '<option value="">All criteria</option>' + data.categories.map((category) => option(category.id, category.name, state.values.category_id)).join("");
    if (!data.categories.some((category) => String(category.id) === String(state.values.category_id))) state.values.category_id = "";
    filters.category_id.disabled = !state.values.event_id;
    for (const name of ["status", "date_from", "date_to", "search", "time_sort"]) filters[name].value = state.values[name];
  }

  function renderType() {
    const definition = definitions[state.type];
    filters.type.value = state.type;
    $("[data-report-eyebrow]").textContent = definition.eyebrow;
    $("[data-report-title]").textContent = definition.title;
    $$("[data-report-tabs] [data-type]").forEach((tab) => {
      const active = tab.dataset.type === state.type;
      tab.classList.toggle("text-[#397565]", active);
      tab.classList.toggle("text-[#121017]/45", !active);
      tab.classList.toggle("hover:bg-[#397565]/7", !active);
      tab.style.boxShadow = active ? "inset 0 -2px 0 #397565" : "";
      tab.setAttribute("aria-pressed", String(active));
    });
    $("[data-status-field]").hidden = state.type !== "attendance";
    $("[data-time-sort-field]").hidden = state.type !== "attendance";
    $("[data-date-field]").hidden = state.type === "rankings";
    $("[data-category-field]").hidden = !["scores", "rankings"].includes(state.type);
    const moreFilters = $("[data-more-filters]");
    const canShowMoreFilters = ["scores", "rankings"].includes(state.type);
    moreFilters.style.display = canShowMoreFilters ? "" : "none";
    if (!canShowMoreFilters) {
      $("[data-extra-filters]").classList.add("hidden");
      $("[data-extra-filters]").classList.remove("flex");
      moreFilters.setAttribute("aria-expanded", "false");
    }
    updateDateLabel();
    updateFilterBadge();
  }

  function requestParams() {
    return { type: state.type, page: state.page, ...state.values };
  }

  function syncUrl() {
    const url = new URL(location.href);
    url.search = "";
    url.searchParams.set("type", state.type);
    for (const [name, value] of Object.entries(state.values)) if (value) url.searchParams.set(name, value);
    if (state.page > 1) url.searchParams.set("page", state.page);
    history.replaceState(null, "", url);
  }

  function updateExport(total) {
    const exportLink = $("[data-export]");
    const url = new URL("api/reports.php", document.baseURI);
    const params = requestParams();
    for (const [name, value] of Object.entries(params)) if (name !== "page" && value) url.searchParams.set(name, value);
    url.searchParams.set("action", "export");
    exportLink.href = url.href;
    exportLink.classList.toggle("pointer-events-none", total === 0);
    exportLink.style.opacity = total === 0 ? "0.4" : "";
    exportLink.tabIndex = total === 0 ? -1 : 0;
    exportLink.setAttribute("aria-disabled", String(total === 0));
  }

  function showLoading() {
    results.classList.add("opacity-50");
    $("[data-generated]").textContent = "Updating…";
  }

  async function load() {
    const requestId = ++state.requestId;
    $("[data-filter-error]").classList.add("hidden");
    showLoading();
    syncUrl();
    try {
      const response = await axios.get("api/reports.php", { params: requestParams() });
      if (requestId !== state.requestId) return;
      const data = response.data.data;
      state.page = data.pagination.page;
      fillFilters(data);
      renderType();
      renderSummary(data.summary);
      renderResults(data);
      updateExport(data.pagination.total);
      $("[data-generated]").textContent = "Updated just now";
    } catch (error) {
      if (requestId !== state.requestId) return;
      const message = error.response?.data?.errors?.date_to?.[0] || error.response?.data?.errors?.category_id?.[0] || error.response?.data?.message || "Report could not be loaded.";
      $("[data-filter-error]").textContent = message;
      $("[data-filter-error]").classList.remove("hidden");
      results.innerHTML = `<p class="px-6 py-16 text-center text-sm text-[#FF6B2C]">${escapeHtml(message)}</p>`;
    } finally {
      if (requestId === state.requestId) results.classList.remove("opacity-50");
    }
  }

  function updateValue(name, value) {
    state.values[name] = value;
    state.page = 1;
  }

  function updateDateLabel() {
    const from = state.values.date_from;
    const to = state.values.date_to;
    $("[data-date-label]").textContent = from && to ? `${formatDate(from)} – ${formatDate(to)}` : from ? `From ${formatDate(from)}` : to ? `Until ${formatDate(to)}` : "Any date";
  }

  function updateFilterBadge() {
    const count = filterNames.filter((name) => state.values[name]).length;
    const badge = $("[data-filter-count]");
    badge.textContent = count;
    badge.classList.toggle("hidden", count === 0);
  }

  function closeDatePopover() {
    $("[data-date-popover]").classList.add("hidden");
    $("[data-date-toggle]").setAttribute("aria-expanded", "false");
  }

  function clearFilters() {
    filterNames.forEach((name) => state.values[name] = "");
    state.values.time_sort = "latest";
    state.page = 1;
    filters.reset();
    closeDatePopover();
    $("[data-extra-filters]").classList.add("hidden");
    $("[data-more-filters]").setAttribute("aria-expanded", "false");
    load();
  }

  $$("[data-report-tabs] [data-type]").forEach((tab) => tab.addEventListener("click", () => {
    if (state.type === tab.dataset.type) return;
    state.type = tab.dataset.type;
    state.page = 1;
    state.values.category_id = "";
    state.values.status = "";
    if (state.type === "rankings") {
      state.values.date_from = "";
      state.values.date_to = "";
    }
    renderType();
    load();
  }));

  ["event_id", "academic_period_id", "status", "category_id", "time_sort"].forEach((name) => filters[name].addEventListener("change", () => {
    updateValue(name, filters[name].value);
    if (name === "event_id") {
      state.values.category_id = "";
      filters.category_id.value = "";
    }
    load();
  }));

  ["date_from", "date_to"].forEach((name) => filters[name].addEventListener("change", () => {
    updateValue(name, filters[name].value);
    updateDateLabel();
    if (state.values.date_from && state.values.date_to && state.values.date_to < state.values.date_from) {
      $("[data-filter-error]").textContent = "The end date must be on or after the start date.";
      $("[data-filter-error]").classList.remove("hidden");
      return;
    }
    load();
  }));

  filters.search.addEventListener("input", () => {
    clearTimeout(state.debounce);
    state.debounce = setTimeout(() => {
      updateValue("search", filters.search.value.trim());
      load();
    }, 320);
  });

  filters.addEventListener("submit", (event) => {
    event.preventDefault();
    clearTimeout(state.debounce);
    updateValue("search", filters.search.value.trim());
    load();
  });

  $("[data-date-toggle]").addEventListener("click", (event) => {
    event.stopPropagation();
    const opening = $("[data-date-popover]").classList.contains("hidden");
    $("[data-date-popover]").classList.toggle("hidden", !opening);
    event.currentTarget.setAttribute("aria-expanded", String(opening));
  });
  $("[data-date-popover]").addEventListener("click", (event) => event.stopPropagation());
  $("[data-clear-dates]").addEventListener("click", () => {
    state.values.date_from = "";
    state.values.date_to = "";
    filters.date_from.value = "";
    filters.date_to.value = "";
    updateDateLabel();
    closeDatePopover();
    state.page = 1;
    load();
  });

  $("[data-more-filters]").addEventListener("click", (event) => {
    const panel = $("[data-extra-filters]");
    const opening = panel.classList.contains("hidden");
    panel.classList.toggle("hidden", !opening);
    panel.classList.toggle("flex", opening);
    event.currentTarget.setAttribute("aria-expanded", String(opening));
  });
  $("[data-clear-filters]").addEventListener("click", clearFilters);
  results.addEventListener("click", (event) => {
    if (event.target.closest("[data-empty-clear]")) clearFilters();
  });
  $("[data-pagination]").addEventListener("click", (event) => {
    const button = event.target.closest("[data-page]");
    if (!button || button.disabled) return;
    state.page = Number(button.dataset.page);
    load();
  });
  $("[data-export]").addEventListener("click", (event) => {
    if (event.currentTarget.getAttribute("aria-disabled") === "true") event.preventDefault();
  });

  initializeShell();
  renderType();
  authenticate().then(load).catch((error) => {
    if (error.message !== "Unauthorized") console.error(error);
  });
});
