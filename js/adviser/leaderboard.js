window.SharedNavigation.ready.then(() => {
  "use strict";

  const $ = (selector, root = document) => root.querySelector(selector);
  const $$ = (selector, root = document) => [...root.querySelectorAll(selector)];
  const filters = $("[data-leaderboard-filters]");
  const standings = $("[data-standings]");
  const competitionStandings = $("[data-competition-standings]");
  const competitionStandingList = $("[data-competition-standing-list]");
  const publicationToggle = $("[data-publication-toggle]");
  let csrfToken = "";
  const PAGE_SIZE = 10;
  const initial = Object.fromEntries(new URLSearchParams(location.search));
  const state = {
    values: {
      event_id: initial.event_id || "",
      activity_id: initial.activity_id || "",
      search: initial.search || "",
    },
    debounce: 0,
    requestId: 0,
    page: 1,
    data: null,
    studentVisible: null,
    publicationSaving: false,
  };

  const escapeHtml = (value) => String(value ?? "").replace(
    /[&<>'"]/g,
    (character) => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", "'": "&#39;", '"': "&quot;" })[character],
  );
  const formatPoints = (value) => Number(value).toFixed(2).replace(/\.00$/, "").replace(/(\.\d)0$/, "$1");

  function formatDate(value) {
    if (!value) return "—";
    const date = new Date(String(value).replace(" ", "T"));
    return Number.isNaN(date.getTime()) ? "—" : new Intl.DateTimeFormat("en-PH", { month: "short", day: "numeric", year: "numeric" }).format(date);
  }

  function renderPublication(visible) {
    state.studentVisible = visible;
    publicationToggle.disabled = state.publicationSaving;
    publicationToggle.setAttribute("aria-checked", String(visible));
    publicationToggle.setAttribute("aria-label", visible ? "Hide leaderboard from students" : "Show leaderboard to students");
    $("[data-publication-label]").textContent = visible ? "Visible to students" : "Show to students";
    $("[data-publication-status]").textContent = visible
      ? "Students can see the leaderboard and their team standings now."
      : "Hidden from students. Adviser and Faculty views remain available.";
    $("[data-publication-track]").style.background = visible ? "#397565" : "rgba(18,16,23,.2)";
    $("[data-publication-knob]").style.transform = visible ? "translateX(20px)" : "translateX(0)";
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
    csrfToken = response.data.csrf_token;
    $("form[action='api/auth.php?action=logout']").onsubmit = async (event) => {
      event.preventDefault();
      try {
        await axios.post("api/auth.php?action=logout", {}, { headers: { "X-CSRF-Token": response.data.csrf_token } });
      } finally {
        location.replace("./");
      }
    };
  }

  function option(value, label, selected) {
    return `<option value="${value}" ${String(value) === String(selected) ? "selected" : ""}>${escapeHtml(label)}</option>`;
  }

  function fillOptions(data) {
    filters.event_id.innerHTML = '<option value="">All events</option>' + data.events.map((event) => option(event.id, event.title, state.values.event_id)).join("");
    filters.activity_id.innerHTML = '<option value="">Overall score</option>' + data.activities.map((activity) => option(activity.id, activity.name, state.values.activity_id)).join("");
    if (!data.activities.some((activity) => String(activity.id) === String(state.values.activity_id))) state.values.activity_id = "";
    filters.event_id.value = state.values.event_id;
    filters.activity_id.value = state.values.activity_id;
    filters.search.value = state.values.search;
    filters.activity_id.disabled = !data.selected_event;
    $("[data-clear-filters]").classList.toggle("hidden", !Object.values(state.values).some(Boolean));
  }

  function syncUrl() {
    const url = new URL(location.href);
    url.search = "";
    for (const [name, value] of Object.entries(state.values)) if (value) url.searchParams.set(name, value);
    history.replaceState(null, "", url);
  }

  function context(data) {
    const title = data.selected_activity?.name || data.selected_event?.title || "All events";
    const parts = [
      data.selected_event ? formatDate(data.selected_event.start_at) : "Cumulative results",
      data.selected_school_year?.label,
      data.selected_activity ? "Competition ranking" : null,
    ].filter(Boolean);
    return { title, subtitle: parts.join(" · ") };
  }

  function renderCompactSummary(summary) {
    const tribes = `${summary.ranked_teams.toLocaleString()} ${summary.ranked_teams === 1 ? "ranked tribe" : "ranked tribes"}`;
    const events = `${summary.events.toLocaleString()} ${summary.events === 1 ? "scored event" : "scored events"}`;
    $("[data-compact-summary]").textContent = `${tribes} · ${events}`;
  }

  function podiumCard(team, place) {
    const medal = ["🥇", "🥈", "🥉"][place - 1];
    const emphasis = {
      1: "min-height: 14.5rem; padding: 2.75rem 1.5rem 2.5rem; box-shadow: 0 22px 48px rgba(18,16,23,.28)",
      2: "min-height: 12.25rem; padding: 2rem 1.25rem; box-shadow: 0 14px 30px rgba(18,16,23,.18)",
      3: "min-height: 10.5rem; padding: 1.5rem 1.25rem; box-shadow: 0 10px 22px rgba(18,16,23,.14)",
    }[place];
    const titleSize = place === 1 ? "text-2xl" : place === 2 ? "text-xl" : "text-lg";
    const scoreSize = place === 1 ? "text-3xl" : place === 2 ? "text-2xl" : "text-xl";
    return `<article class="relative overflow-hidden rounded-3xl border border-white/25 text-center text-white" style="background:${escapeHtml(team.color)}; ${emphasis}"><span class="absolute inset-x-0 top-0 h-px bg-white/45"></span><span class="relative ${place === 1 ? "text-4xl" : place === 2 ? "text-3xl" : "text-2xl"}" aria-label="${place === 1 ? "First" : place === 2 ? "Second" : "Third"} place">${medal}</span><span class="mx-auto mt-4 block h-3 w-3 rounded-full border border-white/50 bg-white/20 ring-4 ring-white/15"></span><h2 class="mt-3 truncate ${titleSize} font-black tracking-[-.03em]">${escapeHtml(team.name)}</h2><strong class="mt-2 block ${scoreSize} font-black">${formatPoints(team.total_score)} <small class="text-xs font-bold text-white/65">pts</small></strong><p class="mt-2 text-[10px] font-bold text-white/70">${team.scored_events_count} ${team.scored_events_count === 1 ? "scored event" : "scored events"}</p></article>`;
  }

  function renderTop(data) {
    const section = $("[data-top-performers]");
    section.classList.toggle("hidden", !data.podium.length);
    if (!data.podium.length) return;
    const mobile = data.podium.map((team, index) => podiumCard(team, index + 1)).join("");
    const placeholder = '<span class="hidden sm:block" aria-hidden="true"></span>';
    const desktop = `${data.podium[1] ? podiumCard(data.podium[1], 2) : placeholder}${podiumCard(data.podium[0], 1)}${data.podium[2] ? podiumCard(data.podium[2], 3) : placeholder}`;
    $("[data-podium]").innerHTML = `<div class="grid gap-4 sm:hidden">${mobile}</div><div class="hidden items-end gap-4 sm:grid sm:grid-cols-3">${desktop}</div>`;
  }

  function emptyState(data) {
    if (data.summary.ranked_teams === 0) {
      return `<div class="px-6 py-16 text-center"><span class="text-4xl" aria-hidden="true">🏆</span><h3 class="mt-5 text-lg font-black">No scores yet</h3><p class="mx-auto mt-2 max-w-md text-sm leading-6 text-[#121017]/45">Rankings will appear after an event receives its first score.</p><a class="mt-5 inline-flex min-h-11 items-center rounded-xl bg-[#397565] px-5 text-xs font-black text-white" href="pages/adviser/scores.html">Manage scores</a><p class="mt-4 text-[10px] font-bold text-[#121017]/32">${data.summary.eligible_teams.toLocaleString()} ${data.summary.eligible_teams === 1 ? "tribe is" : "tribes are"} ready to compete.</p></div>`;
    }
    return `<div class="px-6 py-16 text-center"><span class="mx-auto grid h-14 w-14 place-items-center rounded-full bg-[#397565]/8 text-[#397565]"><svg class="h-6 w-6 fill-none stroke-current stroke-2" viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/></svg></span><h3 class="mt-5 text-lg font-black">No ranked tribes match</h3><p class="mx-auto mt-2 max-w-md text-sm leading-6 text-[#121017]/45">Change or clear the current filters to see more standings.</p><button class="mt-5 min-h-10 rounded-xl border border-[#397565]/25 px-4 text-xs font-black text-[#397565]" type="button" data-empty-clear>Clear filters</button></div>`;
  }

  function renderStandings(data) {
    const scope = context(data);
    $("[data-context-title]").textContent = scope.title;
    $("[data-context-subtitle]").textContent = scope.subtitle;
    $("[data-visible-count]").textContent = `${data.rankings.length} ${data.rankings.length === 1 ? "tribe" : "tribes"}`;
    if (!data.rankings.length) {
      standings.innerHTML = emptyState(data);
      return;
    }
    const lastPage = Math.ceil(data.rankings.length / PAGE_SIZE);
    state.page = Math.min(Math.max(1, state.page), lastPage);
    const start = (state.page - 1) * PAGE_SIZE;
    const rows = data.rankings.slice(start, start + PAGE_SIZE).map((team) => `<tr class="transition hover:bg-[#397565]/[.025]"><td class="px-6 py-4"><span class="text-base font-black ${team.rank <= 3 ? "text-[#397565]" : "text-[#121017]/45"}">${team.rank}</span></td><td class="px-3 py-4"><div class="flex items-center gap-3"><i class="h-3 w-3 rounded-full ring-4 ring-[#121017]/5" style="background:${escapeHtml(team.color)}"></i><span><strong class="block text-sm">${escapeHtml(team.name)}</strong><small class="text-[10px] text-[#121017]/38">${escapeHtml(team.school_year_label || "School year not set")}</small></span></div></td><td class="px-3 py-4 text-xs font-bold text-[#121017]/55">${team.scored_events_count} ${team.scored_events_count === 1 ? "event" : "events"}</td><td class="px-3 py-4 text-xs text-[#121017]/45">${formatDate(team.last_scored_at)}</td><td class="px-6 py-4 text-right"><strong class="text-lg font-black text-[#397565]">${formatPoints(team.total_score)}</strong><small class="ml-1 text-[10px] font-bold text-[#121017]/30">pts</small></td></tr>`).join("");
    const pager = lastPage > 1 ? `<nav class="flex flex-wrap items-center justify-between gap-3 border-t border-[#121017]/8 px-4 py-3 text-xs" aria-label="Standings pages"><span>Showing ${start + 1}–${Math.min(start + PAGE_SIZE, data.rankings.length)} of ${data.rankings.length}</span><span class="flex items-center gap-2"><button class="min-h-10 rounded-lg border px-3 font-bold disabled:opacity-30" type="button" data-standings-page="${state.page - 1}" ${state.page === 1 ? "disabled" : ""}>Previous</button><b>${state.page} / ${lastPage}</b><button class="min-h-10 rounded-lg border px-3 font-bold disabled:opacity-30" type="button" data-standings-page="${state.page + 1}" ${state.page === lastPage ? "disabled" : ""}>Next</button></span></nav>` : "";
    standings.innerHTML = `<div class="overflow-x-auto"><table class="w-full min-w-[700px] border-collapse text-left"><thead class="bg-[#F3F0E9]/55 text-[9px] font-black uppercase tracking-[.14em] text-[#121017]/38"><tr><th class="w-20 px-6 py-3.5">#</th><th class="px-3 py-3.5">Tribe</th><th class="px-3 py-3.5">Events</th><th class="px-3 py-3.5">Last scored</th><th class="px-6 py-3.5 text-right">Points</th></tr></thead><tbody class="divide-y divide-[#121017]/7">${rows}</tbody></table></div>${pager}`;
  }

  function renderCompetitionStandings(data) {
    const groups = data.competition_standings || [];
    competitionStandings.classList.toggle("hidden", !groups.length);
    if (!groups.length) return;
    competitionStandingList.innerHTML = groups.map((group, index) => {
      const rows = group.rankings.map((team, teamIndex) => `<li class="flex items-center justify-between gap-3 border-b border-[#121017]/7 py-3 last:border-0 ${teamIndex >= 5 ? "hidden" : ""}" ${teamIndex >= 5 ? "data-competition-extra" : ""}><div class="flex min-w-0 items-center gap-3"><strong class="w-5 text-sm text-[#397565]">${team.rank}</strong><i class="h-2.5 w-2.5 shrink-0 rounded-full" style="background:${escapeHtml(team.color)}"></i><span class="min-w-0"><strong class="block truncate text-sm">${escapeHtml(team.name)}</strong><small class="text-[10px] text-[#121017]/42">Raw score: ${formatPoints(team.raw_score)}</small></span></div></li>`).join("");
      const empty = '<p class="px-5 py-8 text-center text-xs text-[#121017]/45">No scores have been entered yet.</p>';
      const toggle = group.rankings.length > 5 ? `<button class="w-full border-t border-[#121017]/7 px-5 py-3 text-left text-xs font-black text-[#397565] hover:bg-[#397565]/[.04]" type="button" data-show-more="${index}">See more (${group.rankings.length - 5})</button>` : "";
      return `<article class="overflow-hidden rounded-2xl border border-[#121017]/9 bg-white" data-competition-card="${index}"><header class="border-b border-[#121017]/7 px-5 py-4"><h3 class="truncate text-base font-black">${escapeHtml(group.activity_name)}</h3></header>${group.rankings.length ? `<ol class="px-5">${rows}</ol>${toggle}` : empty}</article>`;
    }).join("");
    competitionStandingList.onclick = (event) => { const button = event.target.closest("[data-show-more]"); if (!button) return; const card = button.closest("[data-competition-card]"); const expanded = button.dataset.expanded === "true"; card.querySelectorAll("[data-competition-extra]").forEach(row => row.classList.toggle("hidden", expanded)); button.dataset.expanded = String(!expanded); const hiddenCount = card.querySelectorAll("[data-competition-extra]").length; button.textContent = expanded ? `See more (${hiddenCount})` : "See less"; if (expanded) card.scrollIntoView({behavior: "smooth", block: "start"}); };
  }

  async function load() {
    const requestId = ++state.requestId;
    syncUrl();
    standings.style.opacity = "0.55";
    $("[data-filter-error]").classList.add("hidden");
    try {
      const response = await axios.get("api/leaderboard.php", { params: state.values });
      if (requestId !== state.requestId) return;
      const data = response.data.data;
      state.data = data;
      renderPublication(Boolean(data.student_visible));
      fillOptions(data);
      renderCompactSummary(data.summary);
      renderTop(data);
      renderStandings(data);
      renderCompetitionStandings(data);
    } catch (error) {
      if (requestId !== state.requestId) return;
      const message = error.response?.data?.message || "The leaderboard could not be loaded.";
      $("[data-filter-error]").textContent = message;
      $("[data-filter-error]").classList.remove("hidden");
      standings.innerHTML = `<p class="px-6 py-16 text-center text-sm text-[#D64A12]">${escapeHtml(message)}</p>`;
    } finally {
      if (requestId === state.requestId) standings.style.opacity = "";
    }
  }

  filters.addEventListener("submit", (event) => {
    event.preventDefault();
    clearTimeout(state.debounce);
    state.values.search = filters.search.value.trim();
    state.page = 1;
    load();
  });

  publicationToggle.addEventListener("click", async () => {
    if (publicationToggle.disabled || state.studentVisible === null) return;
    const nextVisible = !state.studentVisible;
    if (nextVisible) {
      const accepted = await Notifications.confirm({
        title: "Reveal leaderboard to students?",
        message: "Students will immediately see standings and finalized scores on their Leaderboard and Team pages.",
        action: "Reveal leaderboard",
      });
      if (!accepted) return;
    }
    state.publicationSaving = true;
    publicationToggle.disabled = true;
    try {
      const response = await axios.post("api/leaderboard.php", { student_visible: nextVisible }, { headers: { "X-CSRF-Token": csrfToken } });
      renderPublication(Boolean(response.data.data.student_visible));
      Notifications.success(response.data.message);
    } catch (error) {
      Notifications.error(error.response?.data?.message || "Leaderboard visibility could not be changed.");
    } finally {
      state.publicationSaving = false;
      publicationToggle.disabled = false;
    }
  });
  ["event_id", "activity_id"].forEach((name) => filters[name].addEventListener("change", () => {
    clearTimeout(state.debounce);
    state.values.search = filters.search.value.trim();
    state.values[name] = filters[name].value;
    state.page = 1;
    if (name === "event_id") state.values.activity_id = "";
    load();
  }));
  filters.search.addEventListener("input", () => {
    clearTimeout(state.debounce);
    state.debounce = setTimeout(() => {
      state.values.search = filters.search.value.trim();
      state.page = 1;
      load();
    }, 320);
  });
  $("[data-clear-filters]").addEventListener("click", () => {
    state.values = { event_id: "", activity_id: "", search: "" };
    state.page = 1;
    load();
  });
  standings.addEventListener("click", (event) => {
    const pageButton = event.target.closest("[data-standings-page]");
    if (pageButton && state.data) {
      state.page = Number(pageButton.dataset.standingsPage);
      renderStandings(state.data);
      return;
    }
    if (event.target.closest("[data-empty-clear]")) {
      state.values = { event_id: "", activity_id: "", search: "" };
      state.page = 1;
      load();
    }
  });

  initializeShell();
  authenticate().then(load).catch((error) => {
    if (error.message !== "Unauthorized") console.error(error);
  });
});
