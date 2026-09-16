window.SharedNavigation.ready.then(() => {
  "use strict";

  const $ = (selector, root = document) => root.querySelector(selector);
  const $$ = (selector, root = document) => [...root.querySelectorAll(selector)];
  const filters = $("[data-leaderboard-filters]");
  const standings = $("[data-standings]");
  const initial = Object.fromEntries(new URLSearchParams(location.search));
  const state = {
    values: {
      event_id: initial.event_id || "",
      school_year_id: initial.school_year_id || "",
      category_id: initial.category_id || "",
      search: initial.search || "",
    },
    debounce: 0,
    requestId: 0,
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
    filters.school_year_id.innerHTML = '<option value="">All school years</option>' + data.school_years.map((year) => option(year.id, year.label, state.values.school_year_id)).join("");
    filters.category_id.innerHTML = '<option value="">Overall score</option>' + data.categories.map((category) => option(category.id, category.name, state.values.category_id)).join("");
    if (!data.categories.some((category) => String(category.id) === String(state.values.category_id))) state.values.category_id = "";
    filters.event_id.value = state.values.event_id;
    filters.school_year_id.value = state.values.school_year_id;
    filters.category_id.value = state.values.category_id;
    filters.search.value = state.values.search;
    filters.category_id.disabled = !data.selected_event;
    $("[data-clear-filters]").classList.toggle("hidden", !Object.values(state.values).some(Boolean));
  }

  function syncUrl() {
    const url = new URL(location.href);
    url.search = "";
    for (const [name, value] of Object.entries(state.values)) if (value) url.searchParams.set(name, value);
    history.replaceState(null, "", url);
  }

  function context(data) {
    const title = data.selected_category?.name || data.selected_event?.title || "All events";
    const parts = [
      data.selected_event ? formatDate(data.selected_event.start_at) : "Cumulative results",
      data.selected_school_year?.label,
      data.selected_category ? "Criterion ranking" : null,
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
    const primary = place === 1;
    return `<article class="relative overflow-hidden rounded-3xl border ${primary ? "border-[#397565]/20 bg-[#397565] pb-8 pt-9 text-white shadow-[0_20px_50px_rgba(57,117,101,.2)]" : "border-[#121017]/9 bg-white px-5 py-7"} text-center"><span class="text-3xl" aria-label="${place === 1 ? "First" : place === 2 ? "Second" : "Third"} place">${medal}</span><span class="mx-auto mt-4 block h-3 w-3 rounded-full ring-4 ${primary ? "ring-white/15" : "ring-[#121017]/5"}" style="background:${escapeHtml(team.color)}"></span><h2 class="mt-3 truncate text-xl font-black tracking-[-.03em]">${escapeHtml(team.name)}</h2><strong class="mt-2 block text-2xl font-black ${primary ? "text-[#C6F24E]" : "text-[#397565]"}">${formatPoints(team.total_score)} <small class="text-xs font-bold ${primary ? "text-white/45" : "text-[#121017]/35"}">pts</small></strong><p class="mt-2 text-[10px] font-bold ${primary ? "text-white/50" : "text-[#121017]/38"}">${team.scored_events_count} ${team.scored_events_count === 1 ? "scored event" : "scored events"}</p></article>`;
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
    const rows = data.rankings.map((team) => `<tr class="transition hover:bg-[#397565]/[.025]"><td class="px-6 py-4"><span class="text-base font-black ${team.rank <= 3 ? "text-[#397565]" : "text-[#121017]/45"}">${team.rank}</span></td><td class="px-3 py-4"><div class="flex items-center gap-3"><i class="h-3 w-3 rounded-full ring-4 ring-[#121017]/5" style="background:${escapeHtml(team.color)}"></i><span><strong class="block text-sm">${escapeHtml(team.name)}</strong><small class="text-[10px] text-[#121017]/38">${escapeHtml(team.school_year_label || "School year not set")}</small></span></div></td><td class="px-3 py-4 text-xs font-bold text-[#121017]/55">${team.scored_events_count} ${team.scored_events_count === 1 ? "event" : "events"}</td><td class="px-3 py-4 text-xs text-[#121017]/45">${formatDate(team.last_scored_at)}</td><td class="px-6 py-4 text-right"><strong class="text-lg font-black text-[#397565]">${formatPoints(team.total_score)}</strong><small class="ml-1 text-[10px] font-bold text-[#121017]/30">pts</small></td></tr>`).join("");
    standings.innerHTML = `<div class="overflow-x-auto"><table class="w-full min-w-[700px] border-collapse text-left"><thead class="bg-[#F3F0E9]/55 text-[9px] font-black uppercase tracking-[.14em] text-[#121017]/38"><tr><th class="w-20 px-6 py-3.5">#</th><th class="px-3 py-3.5">Tribe</th><th class="px-3 py-3.5">Events</th><th class="px-3 py-3.5">Last scored</th><th class="px-6 py-3.5 text-right">Points</th></tr></thead><tbody class="divide-y divide-[#121017]/7">${rows}</tbody></table></div>`;
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
      fillOptions(data);
      renderCompactSummary(data.summary);
      renderTop(data);
      renderStandings(data);
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
    load();
  });
  ["event_id", "school_year_id", "category_id"].forEach((name) => filters[name].addEventListener("change", () => {
    clearTimeout(state.debounce);
    state.values.search = filters.search.value.trim();
    state.values[name] = filters[name].value;
    if (name === "event_id") state.values.category_id = "";
    load();
  }));
  filters.search.addEventListener("input", () => {
    clearTimeout(state.debounce);
    state.debounce = setTimeout(() => {
      state.values.search = filters.search.value.trim();
      load();
    }, 320);
  });
  $("[data-clear-filters]").addEventListener("click", () => {
    state.values = { event_id: "", school_year_id: "", category_id: "", search: "" };
    load();
  });
  standings.addEventListener("click", (event) => {
    if (event.target.closest("[data-empty-clear]")) {
      state.values = { event_id: "", school_year_id: "", category_id: "", search: "" };
      load();
    }
  });

  initializeShell();
  authenticate().then(load).catch((error) => {
    if (error.message !== "Unauthorized") console.error(error);
  });
});
