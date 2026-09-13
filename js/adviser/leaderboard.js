(() => {
  "use strict";

  const $ = (selector, root = document) => root.querySelector(selector);
  const filters = $("[data-leaderboard-filters]");
  const standings = $("[data-standings]");
  const values = Object.fromEntries(new URLSearchParams(location.search));

  const escapeHtml = (value) =>
    String(value ?? "").replace(
      /[&<>'"]/g,
      (character) =>
        ({
          "&": "&amp;",
          "<": "&lt;",
          ">": "&gt;",
          "'": "&#39;",
          '"': "&quot;",
        })[character],
    );

  const formatPoints = (value) =>
    Number(value)
      .toFixed(2)
      .replace(/\.00$/, "")
      .replace(/(\.\d)0$/, "$1");

  const formatDate = (value) =>
    new Intl.DateTimeFormat("en-PH", {
      month: "short",
      day: "numeric",
      year: "numeric",
    }).format(new Date(String(value).replace(" ", "T")));

  function timeAgo(value) {
    if (!value) return "Not scored";
    const seconds = Math.max(
      0,
      Math.floor(
        (Date.now() - new Date(String(value).replace(" ", "T"))) / 1000,
      ),
    );
    const units = [
      [31536000, "year"],
      [2592000, "month"],
      [86400, "day"],
      [3600, "hour"],
      [60, "minute"],
    ];
    for (const [size, label] of units) {
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
    document.querySelectorAll("[data-sidebar-toggle]").forEach((button) => {
      button.onclick = () => {
        const opening = sidebar.classList.contains("-translate-x-full");
        sidebar.classList.toggle("-translate-x-full", !opening);
        sidebar.classList.toggle("translate-x-0", opening);
        scrim.classList.toggle("hidden", !opening);
      };
    });
    const menu = $("[data-account-menu]");
    document.addEventListener("click", (event) => {
      if (menu?.open && !menu.contains(event.target))
        menu.removeAttribute("open");
    });
  }

  function applyAccount(user) {
    const account = $("[data-account-menu]");
    const name = user.full_name || user.username;
    account.querySelector(
      "summary > span:first-child",
    ).childNodes[0].textContent =
      `${user.first_name?.[0] || ""}${user.last_name?.[0] || ""}`.toUpperCase();
    account.querySelector("summary strong").textContent = name;
    account.querySelector("summary small").textContent = user.role;
    account.querySelector(":scope > div > div strong").textContent = name;
    account.querySelector(":scope > div > div span").textContent =
      user.email || user.username;
  }

  async function authenticate() {
    const response = await axios.get("api/auth.php?action=session");
    if (
      !response.data.authenticated ||
      response.data.user?.role !== "SBO Adviser"
    ) {
      location.replace("./");
      throw new Error("Unauthorized");
    }
    applyAccount(response.data.user);
    $("form[action='api/auth.php?action=logout']").onsubmit = async (event) => {
      event.preventDefault();
      try {
        await axios.post(
          "api/auth.php?action=logout",
          {},
          { headers: { "X-CSRF-Token": response.data.csrf_token } },
        );
      } finally {
        location.replace("./");
      }
    };
  }

  function fillOptions(data) {
    filters.event_id.innerHTML =
      '<option value="">All scored events</option>' +
      data.events
        .map(
          (event) =>
            `<option value="${event.id}">${escapeHtml(event.title)} · ${formatDate(event.start_at)}</option>`,
        )
        .join("");
    filters.school_year_id.innerHTML =
      '<option value="">All school years</option>' +
      data.school_years
        .map(
          (year) =>
            `<option value="${year.id}">${escapeHtml(year.label)}</option>`,
        )
        .join("");
    filters.category_id.innerHTML =
      '<option value="">Overall event score</option>' +
      data.categories
        .map(
          (category) =>
            `<option value="${category.id}">${escapeHtml(category.name)}</option>`,
        )
        .join("");
    filters.event_id.value = values.event_id || "";
    filters.school_year_id.value = values.school_year_id || "";
    filters.category_id.value = values.category_id || "";
    filters.search.value = values.search || "";
    filters.category_id.disabled = !data.selected_event;
    $("[data-clear-filters]").classList.toggle(
      "hidden",
      !Object.values(values).some(Boolean),
    );
  }

  function renderSummary(summary) {
    $("[data-ranked]").textContent = summary.ranked_teams.toLocaleString();
    $("[data-eligible]").textContent = summary.eligible_teams.toLocaleString();
    $("[data-points]").textContent = formatPoints(summary.points);
    $("[data-event-count]").textContent =
      `${summary.events} ${summary.events === 1 ? "event" : "events"}`;
    $("[data-leader]").textContent = summary.leader?.name || "Not available";
    $("[data-leader-detail]").textContent = summary.leader
      ? `${formatPoints(summary.leader.total_score)} points`
      : "Record scores to establish a leader";
    $("[data-lead]").textContent =
      summary.lead === null ? "—" : formatPoints(summary.lead);
    $("[data-lead-detail]").textContent =
      summary.lead === null
        ? "A second ranked tribe is needed"
        : summary.lead === 0
          ? "The lead is currently tied"
          : "points ahead of second place";
  }

  function context(data) {
    const title =
      data.selected_category?.name ||
      data.selected_event?.title ||
      "All events";
    const parts = [
      data.selected_event
        ? formatDate(data.selected_event.start_at)
        : "Cumulative results",
      data.selected_school_year?.label,
      data.selected_category ? "Category ranking" : null,
    ].filter(Boolean);
    return { title, subtitle: parts.join(" · ") };
  }

  function rankTone(rank) {
    if (rank === 1) return "bg-[#C6F24E] text-[#121017]";
    if (rank === 2) return "bg-[#121017]/8 text-[#121017]/65";
    if (rank === 3) return "bg-[#FF6B2C]/12 text-[#d34e16]";
    return "bg-[#121017]/5 text-[#121017]/45";
  }

  function renderTop(data) {
    const section = $("[data-top-performers]");
    section.classList.toggle("hidden", !data.summary.leader);
    section.classList.toggle("grid", Boolean(data.summary.leader));
    if (!data.summary.leader) return;
    const leader = data.summary.leader;
    const scope = context(data);
    $("[data-leader-card]").innerHTML =
      `<div class="absolute -right-12 -top-16 h-52 w-52 rounded-full border-[42px] border-[#C6F24E]/10"></div><div class="relative flex h-full flex-col justify-between gap-8"><div class="flex items-start justify-between gap-4"><div><p class="text-[10px] font-black uppercase tracking-[.17em] text-[#C6F24E]">Leading this view</p><p class="mt-2 text-xs text-white/42">${escapeHtml(scope.title)} · ${escapeHtml(scope.subtitle)}</p></div><span class="grid h-11 w-11 place-items-center rounded-full bg-[#C6F24E] text-sm font-black text-[#121017]">#1</span></div><div class="flex items-end gap-5"><span class="grid h-16 w-16 place-items-center rounded-2xl text-xl font-black text-white ring-1 ring-white/15" style="background:${escapeHtml(leader.color)}">${escapeHtml(leader.name.slice(0, 2).toUpperCase())}</span><div class="min-w-0"><h2 class="truncate text-3xl font-black tracking-[-.04em]">${escapeHtml(leader.name)}</h2><p class="mt-1 text-sm text-white/48">${escapeHtml(leader.school_year_label || "School year not set")} · ${leader.members_count} ${leader.members_count === 1 ? "member" : "members"}</p></div></div><div><strong class="text-5xl font-black tracking-[-.055em]">${formatPoints(leader.total_score)}</strong><span class="ml-2 text-sm font-bold text-white/40">points</span></div></div>`;
    $("[data-podium]").innerHTML = data.podium
      .map(
        (team) =>
          `<div class="flex items-center gap-3 px-5 py-4"><span class="grid h-9 w-9 place-items-center rounded-full text-xs font-black ${rankTone(team.rank)}">${team.rank}</span><i class="h-3 w-3 rounded-full" style="background:${escapeHtml(team.color)}"></i><span class="min-w-0 flex-1"><strong class="block truncate text-sm">${escapeHtml(team.name)}</strong><small class="text-[10px] text-[#121017]/38">${team.score_entries_count} scored ${team.score_entries_count === 1 ? "entry" : "entries"}</small></span><strong class="text-sm text-[#397565]">${formatPoints(team.total_score)}</strong></div>`,
      )
      .join("");
  }

  function renderStandings(data) {
    const scope = context(data);
    $("[data-context-title]").textContent = scope.title;
    $("[data-context-subtitle]").textContent = scope.subtitle;
    $("[data-visible-count]").textContent =
      `${data.rankings.length} ${data.rankings.length === 1 ? "tribe" : "tribes"}`;
    if (!data.rankings.length) {
      standings.innerHTML =
        '<div class="px-6 py-16 text-center"><span class="mx-auto grid h-14 w-14 place-items-center rounded-full bg-[#397565]/8 text-[#397565]"><svg class="h-6 w-6 fill-none stroke-current stroke-2" viewBox="0 0 24 24" aria-hidden="true"><path d="M4 20V10h4v10H4Zm6 0V4h4v16h-4Zm6 0v-7h4v7h-4Z"></path></svg></span><h3 class="mt-5 text-lg font-black">No tribes match this view</h3><p class="mx-auto mt-2 max-w-md text-sm leading-6 text-[#121017]/45">Try clearing the filters, or configure scoring and record tribe results for an event.</p><a class="mt-5 inline-flex min-h-11 items-center rounded-xl bg-[#397565] px-5 text-xs font-black text-white" href="pages/adviser/scores.html">Open score management</a></div>';
      return;
    }
    const rows = data.rankings
      .map(
        (team) =>
          `<tr class="transition hover:bg-[#397565]/[.025]"><td class="px-6 py-4">${team.has_score ? `<span class="grid h-9 w-9 place-items-center rounded-full text-xs font-black ${rankTone(team.rank)}">${team.rank}</span>` : '<span class="text-xs font-black text-[#121017]/25">—</span>'}</td><td class="px-3 py-4"><div class="flex items-center gap-3"><span class="grid h-10 w-10 place-items-center rounded-xl text-[10px] font-black text-white" style="background:${escapeHtml(team.color)}">${escapeHtml(team.name.slice(0, 2).toUpperCase())}</span><span class="min-w-0"><strong class="block truncate text-sm">${escapeHtml(team.name)}</strong><small class="text-[10px] text-[#121017]/38">${team.members_count} ${team.members_count === 1 ? "member" : "members"}</small></span></div></td><td class="px-3 py-4 text-xs font-bold text-[#121017]/55">${escapeHtml(team.school_year_label || "—")}</td><td class="px-3 py-4"><strong class="block text-xs">${team.score_entries_count} ${team.score_entries_count === 1 ? "entry" : "entries"}</strong><small class="text-[10px] text-[#121017]/38">${team.scored_events_count} scored ${team.scored_events_count === 1 ? "event" : "events"}</small></td><td class="px-3 py-4 text-xs text-[#121017]/45">${timeAgo(team.last_scored_at)}</td><td class="px-6 py-4 text-right">${team.has_score ? `<strong class="text-lg font-black text-[#397565]">${formatPoints(team.total_score)}</strong><small class="ml-1 text-[10px] font-bold text-[#121017]/30">pts</small>` : '<span class="rounded-full bg-[#121017]/5 px-3 py-1.5 text-[9px] font-black uppercase tracking-wider text-[#121017]/35">Not scored</span>'}</td></tr>`,
      )
      .join("");
    standings.innerHTML = `<div class="overflow-x-auto"><table class="w-full min-w-[820px] border-collapse text-left"><thead class="bg-[#F3F0E9]/55 text-[9px] font-black uppercase tracking-[.14em] text-[#121017]/38"><tr><th class="w-24 px-6 py-3.5">Rank</th><th class="px-3 py-3.5">Tribe</th><th class="px-3 py-3.5">School year</th><th class="px-3 py-3.5">Coverage</th><th class="px-3 py-3.5">Last update</th><th class="px-6 py-3.5 text-right">Points</th></tr></thead><tbody class="divide-y divide-[#121017]/7">${rows}</tbody></table></div>`;
  }

  async function load() {
    try {
      const response = await axios.get("api/leaderboard.php", {
        params: values,
      });
      const data = response.data.data;
      fillOptions(data);
      renderSummary(data.summary);
      renderTop(data);
      renderStandings(data);
    } catch (error) {
      const message =
        error.response?.data?.message || "The leaderboard could not be loaded.";
      $("[data-filter-error]").textContent = message;
      $("[data-filter-error]").classList.remove("hidden");
      standings.innerHTML = `<p class="px-6 py-16 text-center text-sm text-red-600">${escapeHtml(message)}</p>`;
    }
  }

  filters.onsubmit = (event) => {
    event.preventDefault();
    const url = new URL(location.href);
    url.search = "";
    new FormData(filters).forEach((value, key) => {
      if (value) url.searchParams.set(key, value);
    });
    location.assign(url.href);
  };
  filters.event_id.onchange = () => {
    filters.category_id.value = "";
  };
  $("[data-clear-filters]").onclick = () =>
    location.assign("pages/adviser/leaderboard.html");

  initializeShell();
  authenticate()
    .then(load)
    .catch((error) => {
      if (error.message !== "Unauthorized") console.error(error);
    });
})();
