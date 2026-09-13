(() => {
  "use strict";

  const $ = (selector) => document.querySelector(selector);
  const filters = $("[data-score-filters]");
  const eventList = $("[data-event-list]");
  const pagination = $("[data-pagination]");
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
  const query = () => Object.fromEntries(new URLSearchParams(location.search));
  const formatDate = (value) =>
    new Intl.DateTimeFormat("en-PH", {
      month: "short",
      day: "numeric",
      year: "numeric",
      hour: "numeric",
      minute: "2-digit",
    }).format(new Date(String(value).replace(" ", "T")));

  function pageUrl(page) {
    const url = new URL(location.href);
    url.search = "";
    new FormData(filters).forEach((value, key) => {
      if (value) url.searchParams.set(key, value);
    });
    if (page > 1) url.searchParams.set("page", page);
    return url.href;
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

  function renderSummary(summary) {
    $("[data-summary-events]").textContent = summary.events.toLocaleString();
    $("[data-summary-configured]").textContent =
      summary.configured.toLocaleString();
    $("[data-summary-results]").textContent = summary.results.toLocaleString();
    const points = $("[data-summary-points]");
    points.textContent = summary.points ? formatPoints(summary.points) : "—";
    points.className = `mt-1 text-xl font-black ${summary.points ? "text-[#397565]" : "text-[#121017]/30"}`;
    $("[data-manage-events]").classList.toggle("hidden", !summary.events);
    $("[data-manage-events]").classList.toggle(
      "inline-flex",
      Boolean(summary.events),
    );
    filters.classList.toggle("hidden", !summary.events);
    filters.classList.toggle("grid", Boolean(summary.events));
  }

  function renderEvents(data) {
    eventList.replaceChildren();
    $("[data-result-count]").textContent =
      `${data.pagination.total} ${data.pagination.total === 1 ? "event" : "events"} available`;
    $("[data-clear-filters]").classList.toggle(
      "hidden",
      !filters.search.value && !filters.timing.value,
    );
    if (!data.events.length) {
      const filtered = filters.search.value || filters.timing.value;
      eventList.innerHTML = `<div class="px-6 py-10 text-center"><strong class="block text-sm font-black">${filtered ? "No events match these filters" : "There are no events to score yet"}</strong><p class="mt-1 text-xs text-[#121017]/42">${filtered ? "Change or clear the current filters." : "Create an event before configuring its scoring rules."}</p><a class="mt-5 inline-flex min-h-10 items-center rounded-xl bg-[#2F3AE0] px-4 text-xs font-black text-white" href="pages/adviser/events.html?create=1">Create Event</a></div>`;
    }
    const badges = {
      ongoing: ["bg-[#C6F24E]/40 text-[#397565]", "bg-[#C6F24E]"],
      upcoming: ["bg-[#2F3AE0]/8 text-[#2F3AE0]", "bg-[#2F3AE0]"],
      completed: ["bg-[#121017]/6 text-[#121017]/45", "bg-[#121017]/25"],
    };
    data.events.forEach((event) => {
      const configured = event.score_categories_count > 0;
      const state = badges[event.schedule_state];
      const article = document.createElement("article");
      article.className =
        "group grid gap-5 px-5 py-5 transition hover:bg-[#397565]/[.035] sm:px-6 lg:grid-cols-[minmax(250px,1fr)_160px_190px_auto] lg:items-center";
      article.innerHTML = `<div class="min-w-0"><div class="flex flex-wrap items-center gap-2"><h3 class="truncate text-base font-black">${escapeHtml(event.title)}</h3><span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-[9px] font-black uppercase ${state[0]}"><span class="h-1.5 w-1.5 rounded-full ${state[1]}"></span>${event.schedule_state}</span></div><p class="mt-2 text-[10px] font-semibold text-[#121017]/40">${formatDate(event.start_at)} · ${escapeHtml(event.location || "Venue not specified")}</p></div><div><span class="text-[9px] font-black uppercase tracking-[.14em] text-[#121017]/30">Criteria</span><p class="mt-1.5 text-xs font-black ${configured ? "" : "text-[#FF6B2C]"}">${configured ? `${event.score_categories_count} ${event.score_categories_count === 1 ? "criterion" : "criteria"}` : "Setup needed"}</p></div><div><span class="text-[9px] font-black uppercase tracking-[.14em] text-[#121017]/30">Results</span><p class="mt-1.5 text-xs font-black">${event.scored_teams_count ? `${event.scored_teams_count} ${event.scored_teams_count === 1 ? "tribe" : "tribes"}` : "No results yet"}</p>${event.scores_sum_points ? `<small class="mt-1 block text-[9px] font-bold text-[#397565]">${formatPoints(event.scores_sum_points)} points awarded</small>` : ""}</div><a class="inline-flex min-h-11 items-center justify-center gap-2 rounded-xl px-5 text-xs font-black text-white ${configured ? "bg-[#2F3AE0]" : "bg-[#397565]"}" href="pages/adviser/scoreboard.html?event_id=${event.id}">${configured ? "Open Scoreboard" : "Set Up Scoring"} →</a>`;
      eventList.append(article);
    });
    renderPagination(data.pagination);
  }

  function renderPagination(meta) {
    pagination.replaceChildren();
    pagination.classList.toggle("hidden", meta.last_page <= 1);
    if (meta.last_page <= 1) return;
    pagination.classList.add("flex");
    const label = document.createElement("small");
    label.textContent = `Showing ${meta.from}–${meta.to} of ${meta.total}`;
    const links = document.createElement("div");
    links.className = "flex gap-2";
    [
      ["Previous", meta.current_page - 1, meta.current_page === 1],
      ["Next", meta.current_page + 1, meta.current_page === meta.last_page],
    ].forEach(([text, page, disabled]) => {
      const link = document.createElement("a");
      link.textContent = text;
      link.href = disabled ? "#" : pageUrl(page);
      link.className = `rounded-lg border px-3 py-2 font-black ${disabled ? "pointer-events-none opacity-30" : "text-[#397565]"}`;
      links.append(link);
    });
    pagination.append(label, links);
  }

  async function load() {
    try {
      const response = await axios.get("api/scores.php", { params: query() });
      renderSummary(response.data.data.summary);
      renderEvents(response.data.data);
    } catch (error) {
      window.Notifications?.error?.(
        error.response?.data?.message || "Scoreboards could not be loaded.",
      );
      eventList.innerHTML =
        '<p class="p-10 text-center text-sm text-red-600">Scoreboards could not be loaded.</p>';
    }
  }

  const values = query();
  filters.search.value = values.search || "";
  filters.timing.value = values.timing || "";
  filters.onsubmit = (event) => {
    event.preventDefault();
    location.assign(pageUrl(1));
  };
  $("[data-clear-filters]").onclick = () =>
    location.assign("pages/adviser/scores.html");
  initializeShell();
  authenticate()
    .then(load)
    .catch((error) => {
      if (error.message !== "Unauthorized") console.error(error);
    });
})();
