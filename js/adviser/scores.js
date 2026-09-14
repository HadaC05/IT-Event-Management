(() => {
  "use strict";

  const $ = (selector, root = document) => root.querySelector(selector);
  const filters = $("[data-score-filters]");
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
  const formatPoints = (value) => Number(value).toFixed(2).replace(/\.00$/, "").replace(/(\.\d)0$/, "$1");

  function formatDate(value) {
    const date = new Date(String(value).replace(" ", "T"));
    return Number.isNaN(date.getTime()) ? "Date not set" : new Intl.DateTimeFormat("en-PH", { month: "short", day: "numeric", year: "numeric" }).format(date);
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
    const readyLabel = `${summary.ready.toLocaleString()} ready for scoring`;
    const finalizedLabel = `${summary.finalized.toLocaleString()} finalized`;
    $("[data-score-summary]").textContent = `${eventLabel} · ${readyLabel} · ${finalizedLabel}`;
    $("[data-manage-events]").classList.toggle("hidden", !summary.events);
    $("[data-manage-events]").classList.toggle("inline-flex", Boolean(summary.events));
    filters.classList.toggle("hidden", !summary.events);
    filters.classList.toggle("grid", Boolean(summary.events));
  }

  function eventLifecycle(stateName) {
    return { upcoming: "Upcoming event", ongoing: "Event in progress", completed: "Event ended" }[stateName] || "Event schedule";
  }

  function statePresentation(event) {
    return {
      not_setup: {
        label: "Not set up",
        tone: "bg-[#FF6B2C]/10 text-[#D64A12]",
        title: "Scoring not set up",
        detail: "Set the judging criteria before scores can be entered.",
        action: "Set up scoring",
      },
      ready: {
        label: "Ready to score",
        tone: "bg-[#397565]/10 text-[#397565]",
        title: "Scoring is ready",
        detail: "Criteria are configured. Results can now be recorded.",
        action: "Enter scores",
      },
      scoring: {
        label: "Scoring",
        tone: "bg-[#397565] text-white",
        title: "Scoring in progress",
        detail: `${event.completed_teams_count} of ${event.eligible_teams_count} tribes have complete scores.`,
        action: "Continue scoring",
      },
      complete: {
        label: "Scores complete",
        tone: "bg-[#397565]/10 text-[#397565]",
        title: "All tribe scores are entered",
        detail: "Review the completed results before they are finalized.",
        action: "Review scores",
      },
      finalized: {
        label: "Results finalized",
        tone: "bg-[#C6F24E]/40 text-[#397565]",
        title: "Results are finalized",
        detail: "The submitted score sheets for this event are locked.",
        action: "Review results",
      },
    }[event.scoring_state];
  }

  function criteriaList(event) {
    if (!event.criteria.length) return "";
    return `<div class="mt-5 grid gap-2 sm:grid-cols-2 lg:grid-cols-3">${event.criteria.map((criterion) => `<div class="flex items-center justify-between gap-3 rounded-xl bg-[#F3F0E9]/55 px-3 py-2.5"><span class="truncate text-xs font-bold">${escapeHtml(criterion.name)}</span><strong class="shrink-0 text-xs text-[#397565]">${formatPoints(criterion.max_points)} pts</strong></div>`).join("")}</div>`;
  }

  function teamProgress(event) {
    if (!event.team_progress.length || !["scoring", "complete", "finalized"].includes(event.scoring_state)) return "";
    const teams = event.team_progress.slice(0, 6).map((team) => `<div class="flex items-center gap-2 text-xs"><i class="h-2.5 w-2.5 rounded-full" style="background:${escapeHtml(team.color || "#397565")}"></i><span class="min-w-0 flex-1 truncate font-bold">${escapeHtml(team.name)}</span><span class="font-black ${team.complete ? "text-[#397565]" : "text-[#121017]/35"}">${team.complete ? "✓" : "Waiting"}</span></div>`).join("");
    const more = event.team_progress.length > 6 ? `<p class="mt-2 text-[10px] font-bold text-[#121017]/35">+${event.team_progress.length - 6} more tribes</p>` : "";
    return `<div class="mt-5 grid gap-2 rounded-2xl border border-[#121017]/7 bg-[#F3F0E9]/30 p-4 sm:grid-cols-2">${teams}</div>${more}`;
  }

  function renderEvent(event) {
    const presentation = statePresentation(event);
    const progress = event.eligible_teams_count ? Math.min(100, event.completed_teams_count / event.eligible_teams_count * 100) : 0;
    return `<article class="rounded-3xl border border-[#121017]/9 bg-white p-5 shadow-[0_16px_45px_rgba(18,16,23,.045)] sm:p-7"><header class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between"><div class="min-w-0"><div class="flex flex-wrap items-center gap-2"><h3 class="text-xl font-black tracking-[-.035em] sm:text-2xl">${escapeHtml(event.title)}</h3><span class="rounded-full bg-[#121017]/5 px-2.5 py-1 text-[9px] font-black uppercase tracking-wider text-[#121017]/40">${eventLifecycle(event.schedule_state)}</span></div><p class="mt-2 text-xs font-bold text-[#121017]/42">${formatDate(event.start_at)} · ${escapeHtml(event.location || "Venue not specified")}</p></div><span class="w-fit rounded-full px-3 py-1.5 text-[9px] font-black uppercase tracking-wider ${presentation.tone}">${presentation.label}</span></header><div class="mt-6 border-t border-[#121017]/8 pt-5"><div class="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between"><div class="min-w-0 flex-1"><h4 class="text-base font-black">${presentation.title}</h4><p class="mt-1 text-sm leading-6 text-[#121017]/45">${presentation.detail}</p>${criteriaList(event)}${teamProgress(event)}${event.scoring_state === "scoring" ? `<div class="mt-4 h-1.5 overflow-hidden rounded-full bg-[#121017]/7"><i class="block h-full rounded-full bg-[#397565]" style="width:${progress}%"></i></div>` : ""}</div><a class="inline-flex min-h-11 shrink-0 items-center justify-center rounded-xl bg-[#397565] px-5 text-xs font-black text-white transition hover:bg-[#2f6255]" href="pages/adviser/scoreboard.html?event_id=${event.id}">${presentation.action} →</a></div></div></article>`;
  }

  function renderEvents(data) {
    $("[data-result-count]").textContent = `${data.pagination.total.toLocaleString()} ${data.pagination.total === 1 ? "event" : "events"} available`;
    $("[data-clear-filters]").classList.toggle("hidden", !state.search && !state.timing);
    if (!data.events.length) {
      const filtered = state.search || state.timing;
      eventList.innerHTML = `<div class="rounded-3xl border border-[#121017]/9 bg-white px-6 py-16 text-center"><span class="text-4xl" aria-hidden="true">🏆</span><h3 class="mt-5 text-lg font-black">${filtered ? "No events match these filters" : "There are no events to score yet"}</h3><p class="mt-2 text-sm text-[#121017]/45">${filtered ? "Change or clear the current filters." : "Create an event before configuring its scoring rules."}</p><${filtered ? "button" : "a"} class="mt-5 inline-flex min-h-11 items-center rounded-xl bg-[#397565] px-5 text-xs font-black text-white" ${filtered ? 'type="button" data-empty-clear' : 'href="pages/adviser/events.html?create=1"'}>${filtered ? "Clear filters" : "Create event"}</${filtered ? "button" : "a"}></div>`;
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
      const response = await axios.get("api/scores.php", { params: { search: state.search, timing: state.timing, page: state.page } });
      if (requestId !== state.requestId) return;
      const data = response.data.data;
      state.page = data.pagination.current_page;
      renderSummary(data.summary);
      renderEvents(data);
    } catch (error) {
      if (requestId !== state.requestId) return;
      const message = error.response?.data?.message || "Scoreboards could not be loaded.";
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
})();
