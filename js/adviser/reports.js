(() => {
  "use strict";

  const $ = (selector, root = document) => root.querySelector(selector);
  const $$ = (selector, root = document) => [...root.querySelectorAll(selector)];
  const filters = $("[data-report-filters]");
  const results = $("[data-report-results]");
  const query = Object.fromEntries(new URLSearchParams(location.search));
  const type = ["attendance", "participation", "scores", "rankings"].includes(query.type)
    ? query.type
    : "attendance";

  const definitions = {
    attendance: {
      eyebrow: "Student records",
      title: "Attendance report",
      description: "Review every recorded attendance status and check-in across events.",
      summary: [
        ["Records", "records", "Included in this report"],
        ["Attended", "attended", "Present or late"],
        ["Absent", "absent", "Marked absent"],
        ["Attendance rate", "rate", "Across recorded statuses", "%"],
      ],
    },
    participation: {
      eyebrow: "Event coverage",
      title: "Event participation",
      description: "Compare expected audiences with recorded and attended students for each event.",
      summary: [
        ["Events", "events", "Included in this report"],
        ["Expected", "expected", "Student opportunities"],
        ["Attended", "attended", "Present or late"],
        ["Participation", "rate", "Across included events", "%"],
      ],
    },
    scores: {
      eyebrow: "Competition results",
      title: "Score report",
      description: "Audit recorded tribe points by event, school year, and scoring criterion.",
      summary: [
        ["Entries", "entries", "Recorded score values"],
        ["Tribes", "teams", "With a result"],
        ["Points", "points", "Total awarded", "points"],
        ["Average", "average", "Points per entry", "points"],
      ],
    },
    rankings: {
      eyebrow: "Tribe standings",
      title: "Ranking report",
      description: "Review current tribe positions, total points, and scoring coverage.",
      summary: [
        ["Ranked tribes", "ranked", "With recorded scores"],
        ["Eligible tribes", "eligible", "Included in this scope"],
        ["Points", "points", "Total awarded", "points"],
        ["Leader", "leader", "Current first place", "leader"],
      ],
    },
  };

  const escapeHtml = (value) =>
    String(value ?? "").replace(
      /[&<>'"]/g,
      (character) =>
        ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", "'": "&#39;", '"': "&quot;" })[
          character
        ],
    );

  const formatNumber = (value) => Number(value).toLocaleString("en-PH");
  const formatPoints = (value) =>
    Number(value).toFixed(2).replace(/\.00$/, "").replace(/(\.\d)0$/, "$1");
  const asDate = (value) => new Date(String(value).replace(" ", "T"));
  const formatDate = (value) =>
    value
      ? new Intl.DateTimeFormat("en-PH", { month: "short", day: "numeric", year: "numeric" }).format(asDate(value))
      : "—";
  const formatTime = (value) =>
    value
      ? new Intl.DateTimeFormat("en-PH", { hour: "numeric", minute: "2-digit" }).format(asDate(value))
      : "Not checked in";
  const formatDateTime = (value) =>
    value
      ? `${formatDate(value)} · ${formatTime(value)}`
      : "—";

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

  function initializeType() {
    const definition = definitions[type];
    $("[data-report-eyebrow]").textContent = definition.eyebrow;
    $("[data-report-title]").textContent = definition.title;
    $("[data-report-description]").textContent = definition.description;
    filters.type.value = type;
    $$('[data-report-tabs] a').forEach((tab) => {
      const active = tab.dataset.type === type;
      tab.classList.toggle("bg-[#397565]", active);
      tab.classList.toggle("text-white", active);
      tab.classList.toggle("shadow-sm", active);
      tab.classList.toggle("text-[#121017]/50", !active);
      tab.classList.toggle("hover:bg-[#397565]/7", !active);
      tab.classList.toggle("hover:text-[#397565]", !active);
      tab.setAttribute("aria-current", active ? "page" : "false");
    });
    $("[data-category-field]").hidden = !["scores", "rankings"].includes(type);
    $("[data-status-field]").hidden = type !== "attendance";
    $$('[data-date-field]').forEach((field) => (field.hidden = type === "rankings"));
    const hasFilters = ["event_id", "school_year_id", "category_id", "status", "date_from", "date_to", "search"].some((key) => query[key]);
    const clear = $("[data-clear-filters]");
    clear.classList.toggle("hidden", !hasFilters);
    clear.href = `pages/adviser/reports.html?type=${type}`;
  }

  function option(value, label, selected) {
    return `<option value="${value}" ${String(selected) === String(value) ? "selected" : ""}>${escapeHtml(label)}</option>`;
  }

  function fillFilters(data) {
    filters.event_id.innerHTML = '<option value="">All events</option>' + data.events.map((event) => option(event.id, `${event.title} · ${formatDate(event.start_at)}`, query.event_id)).join("");
    filters.school_year_id.innerHTML = '<option value="">All school years</option>' + data.school_years.map((year) => option(year.id, year.label, query.school_year_id)).join("");
    filters.category_id.innerHTML = '<option value="">All criteria</option>' + data.categories.map((category) => option(category.id, category.name, query.category_id)).join("");
    filters.category_id.disabled = !query.event_id;
    ["status", "date_from", "date_to", "search"].forEach((name) => {
      filters[name].value = query[name] || "";
    });
  }

  function displaySummaryValue(summary, key, mode) {
    const value = summary[key];
    if (value === null || value === undefined || value === "") return "—";
    if (mode === "%") return `${formatPoints(value)}%`;
    if (mode === "points") return formatPoints(value);
    return typeof value === "number" ? formatNumber(value) : value;
  }

  function renderSummary(summary) {
    const cards = definitions[type].summary;
    $("[data-summary]").innerHTML = cards
      .map(([label, key, originalHint, mode], index) => {
        const hint = mode === "leader" && !summary[key] ? "No results yet" : originalHint;
        const border = index < 2
          ? "border-b border-[#121017]/7 sm:border-r"
          : index === 2
            ? "border-b border-[#121017]/7 xl:border-b-0 xl:border-r"
            : "";
        const second = index === 1 ? "sm:border-r-0" : "";
        return `<div class="px-5 py-5 sm:px-7 ${border} ${second}"><span class="text-[9px] font-black uppercase tracking-[.15em] text-[#121017]/35">${label}</span><strong class="mt-2 block truncate text-2xl font-black ${index === 3 ? "text-[#397565]" : ""}">${escapeHtml(displaySummaryValue(summary, key, mode))}</strong><small class="mt-1 block text-xs text-[#121017]/40">${hint}</small></div>`;
      })
      .join("");
  }

  function statusBadge(status) {
    const tone = ["present", "late"].includes(status)
      ? "bg-[#C6F24E]/35 text-[#397565]"
      : status === "absent"
        ? "bg-[#FF6B2C]/10 text-[#FF6B2C]"
        : "bg-[#2F3AE0]/8 text-[#2F3AE0]";
    return `<span class="rounded-full px-2.5 py-1 text-[9px] font-black uppercase tracking-wider ${tone}">${escapeHtml(status)}</span>`;
  }

  function attendanceTable(rows) {
    const body = rows.map((row) => `<tr class="transition hover:bg-[#397565]/[.025]"><td class="px-6 py-4"><strong class="block text-xs">${escapeHtml(row.student_name || "Deleted user")}</strong><small class="mt-0.5 block text-[10px] text-[#121017]/38">${escapeHtml(row.id_number || "No student ID")}</small></td><td class="px-3 py-4 text-xs font-bold">${escapeHtml(row.event_title || "Deleted event")}</td><td class="px-3 py-4 text-xs text-[#121017]/55">${formatDate(row.attendance_date)}</td><td class="px-3 py-4"><span class="block text-xs font-bold">${escapeHtml(row.team_name || "No tribe")}</span><small class="text-[10px] text-[#121017]/38">${escapeHtml(row.year_level || "No year level")}</small></td><td class="px-3 py-4">${statusBadge(row.status)}</td><td class="px-6 py-4 text-xs text-[#121017]/48">${formatTime(row.checked_in_at)}</td></tr>`).join("");
    return `<div class="overflow-x-auto"><table class="w-full min-w-[980px] text-left"><thead><tr class="bg-[#121017]/[.025] text-[9px] font-black uppercase tracking-[.14em] text-[#121017]/38"><th class="px-6 py-3.5">Student</th><th class="px-3 py-3.5">Event</th><th class="px-3 py-3.5">Date</th><th class="px-3 py-3.5">Tribe / Year</th><th class="px-3 py-3.5">Status</th><th class="px-6 py-3.5">Check-in</th></tr></thead><tbody class="divide-y divide-[#121017]/7">${body}</tbody></table></div>`;
  }

  function participationTable(rows) {
    const body = rows.map((row) => `<tr class="transition hover:bg-[#397565]/[.025]"><td class="px-6 py-4"><strong class="block text-xs">${escapeHtml(row.title)}</strong><small class="mt-0.5 block text-[10px] text-[#121017]/38">${escapeHtml(row.location || "Venue not set")}</small></td><td class="px-3 py-4"><span class="block text-xs font-bold">${formatDate(row.start_at)}</span><small class="text-[10px] text-[#121017]/38">${formatTime(row.start_at)}</small></td><td class="px-3 py-4 text-xs font-black">${formatNumber(row.expected_count)}</td><td class="px-3 py-4 text-xs font-black">${formatNumber(row.recorded_count)}</td><td class="px-3 py-4 text-xs font-black text-[#397565]">${formatNumber(row.attended_count)}</td><td class="px-6 py-4 text-right"><strong class="text-sm font-black">${row.participation_rate === null ? "—" : `${formatPoints(row.participation_rate)}%`}</strong><div class="ml-auto mt-2 h-1.5 w-24 overflow-hidden rounded-full bg-[#121017]/7"><i class="block h-full rounded-full bg-[#397565]" style="width:${Number(row.participation_rate || 0)}%"></i></div></td></tr>`).join("");
    return `<div class="overflow-x-auto"><table class="w-full min-w-[900px] text-left"><thead><tr class="bg-[#121017]/[.025] text-[9px] font-black uppercase tracking-[.14em] text-[#121017]/38"><th class="px-6 py-3.5">Event</th><th class="px-3 py-3.5">Schedule</th><th class="px-3 py-3.5">Expected</th><th class="px-3 py-3.5">Recorded</th><th class="px-3 py-3.5">Attended</th><th class="px-6 py-3.5 text-right">Participation</th></tr></thead><tbody class="divide-y divide-[#121017]/7">${body}</tbody></table></div>`;
  }

  function scoresTable(rows) {
    const body = rows.map((row) => `<tr class="transition hover:bg-[#397565]/[.025]"><td class="px-6 py-4"><strong class="block text-xs">${escapeHtml(row.event_title || "Deleted event")}</strong><small class="text-[10px] text-[#121017]/38">${formatDate(row.event_start_at)}</small></td><td class="px-3 py-4"><span class="inline-flex items-center gap-2 text-xs font-black"><i class="h-2.5 w-2.5 rounded-full" style="background:${escapeHtml(row.color || "#397565")}"></i>${escapeHtml(row.team_name || "Deleted tribe")}</span><small class="mt-0.5 block text-[10px] text-[#121017]/38">${escapeHtml(row.school_year || "")}</small></td><td class="px-3 py-4 text-xs font-bold">${escapeHtml(row.category_name || "Removed criterion")}</td><td class="px-3 py-4"><strong class="text-base font-black text-[#397565]">${formatPoints(row.points)}</strong><small class="ml-1 text-[10px] text-[#121017]/35">/ ${formatPoints(row.max_points)}</small></td><td class="px-3 py-4 text-xs text-[#121017]/52">${escapeHtml(row.recorder_name || "System")}</td><td class="px-6 py-4 text-xs text-[#121017]/45">${formatDateTime(row.updated_at)}</td></tr>`).join("");
    return `<div class="overflow-x-auto"><table class="w-full min-w-[980px] text-left"><thead><tr class="bg-[#121017]/[.025] text-[9px] font-black uppercase tracking-[.14em] text-[#121017]/38"><th class="px-6 py-3.5">Event</th><th class="px-3 py-3.5">Tribe</th><th class="px-3 py-3.5">Criterion</th><th class="px-3 py-3.5">Points</th><th class="px-3 py-3.5">Recorded by</th><th class="px-6 py-3.5">Updated</th></tr></thead><tbody class="divide-y divide-[#121017]/7">${body}</tbody></table></div>`;
  }

  function rankingsTable(rows) {
    const body = rows.map((row) => `<tr class="transition hover:bg-[#397565]/[.025]"><td class="px-6 py-4"><span class="grid h-9 w-9 place-items-center rounded-full text-xs font-black ${row.rank === 1 ? "bg-[#C6F24E] text-[#121017]" : "bg-[#397565]/10 text-[#397565]"}">${row.rank ?? "—"}</span></td><td class="px-3 py-4"><span class="inline-flex items-center gap-2 text-sm font-black"><i class="h-3 w-3 rounded-full" style="background:${escapeHtml(row.color)}"></i>${escapeHtml(row.name)}</span><small class="mt-0.5 block text-[10px] text-[#121017]/38">${formatNumber(row.members_count)} ${row.members_count === 1 ? "member" : "members"}</small></td><td class="px-3 py-4 text-xs font-bold text-[#121017]/55">${escapeHtml(row.school_year_label || "—")}</td><td class="px-3 py-4"><strong class="block text-xs">${formatNumber(row.score_entries_count)} ${row.score_entries_count === 1 ? "entry" : "entries"}</strong><small class="text-[10px] text-[#121017]/38">${formatNumber(row.scored_events_count)} scored ${row.scored_events_count === 1 ? "event" : "events"}</small></td><td class="px-6 py-4 text-right">${row.has_score ? `<strong class="text-lg font-black text-[#397565]">${formatPoints(row.total_score)}</strong><small class="ml-1 text-[10px] text-[#121017]/30">pts</small>` : '<span class="rounded-full bg-[#121017]/5 px-3 py-1.5 text-[9px] font-black uppercase tracking-wider text-[#121017]/35">Not scored</span>'}</td></tr>`).join("");
    return `<div class="overflow-x-auto"><table class="w-full min-w-[900px] text-left"><thead><tr class="bg-[#121017]/[.025] text-[9px] font-black uppercase tracking-[.14em] text-[#121017]/38"><th class="px-6 py-3.5">Rank</th><th class="px-3 py-3.5">Tribe</th><th class="px-3 py-3.5">School year</th><th class="px-3 py-3.5">Coverage</th><th class="px-6 py-3.5 text-right">Total points</th></tr></thead><tbody class="divide-y divide-[#121017]/7">${body}</tbody></table></div>`;
  }

  function renderResults(data) {
    $("[data-result-count]").textContent = `${formatNumber(data.pagination.total)} ${data.pagination.total === 1 ? "result" : "results"}`;
    if (!data.rows.length) {
      results.innerHTML = '<div class="px-6 py-16 text-center"><span class="mx-auto grid h-14 w-14 place-items-center rounded-full bg-[#397565]/8 text-[#397565]"><svg class="h-6 w-6 fill-none stroke-current stroke-2" viewBox="0 0 24 24"><path d="M5 3h10l4 4v14H5V3Zm10 0v5h4M8 12h8M8 16h6"/></svg></span><h3 class="mt-5 text-lg font-black">No report data found</h3><p class="mx-auto mt-2 max-w-md text-sm leading-6 text-[#121017]/45">Try clearing the filters or record activity in the related management screen first.</p></div>';
    } else {
      results.innerHTML = { attendance: attendanceTable, participation: participationTable, scores: scoresTable, rankings: rankingsTable }[type](data.rows);
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

  async function load() {
    try {
      const response = await axios.get("api/reports.php", { params: { ...query, type } });
      const data = response.data.data;
      fillFilters(data);
      renderSummary(data.summary);
      renderResults(data);
      $("[data-generated]").textContent = `Generated ${formatDateTime(data.generated_at)}`;
      const exportUrl = new URL("api/reports.php", document.baseURI);
      Object.entries({ ...query, type, action: "export" }).forEach(([key, value]) => {
        if (key !== "page" && value) exportUrl.searchParams.set(key, value);
      });
      $("[data-export]").href = exportUrl.href;
    } catch (error) {
      const message = error.response?.data?.errors?.category_id?.[0] || error.response?.data?.message || "Report could not be loaded.";
      $("[data-filter-error]").textContent = message;
      $("[data-filter-error]").classList.remove("hidden");
      results.innerHTML = `<p class="px-6 py-16 text-center text-sm text-red-600">${escapeHtml(message)}</p>`;
    }
  }

  filters.addEventListener("submit", (event) => {
    event.preventDefault();
    if (filters.date_from.value && filters.date_to.value && filters.date_to.value < filters.date_from.value) {
      $("[data-filter-error]").textContent = "The to date must be on or after the from date.";
      $("[data-filter-error]").classList.remove("hidden");
      return;
    }
    const url = new URL(location.href);
    url.search = "";
    new FormData(filters).forEach((value, key) => {
      if (value) url.searchParams.set(key, value);
    });
    location.assign(url.href);
  });

  filters.event_id.addEventListener("change", () => {
    filters.category_id.value = "";
  });
  $("[data-pagination]").addEventListener("click", (event) => {
    const button = event.target.closest("[data-page]");
    if (!button || button.disabled) return;
    const url = new URL(location.href);
    url.searchParams.set("page", button.dataset.page);
    location.assign(url.href);
  });

  initializeShell();
  initializeType();
  authenticate().then(load).catch((error) => {
    if (error.message !== "Unauthorized") console.error(error);
  });
})();
