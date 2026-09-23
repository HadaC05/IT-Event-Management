window.SharedNavigation.ready.then(() => {
  "use strict";

  const $ = (selector, root = document) => root.querySelector(selector);
  const filters = $("[data-score-filters]");
  const eventList = $("[data-event-list]");
  const pagination = $("[data-pagination]");
  const initial = Object.fromEntries(new URLSearchParams(location.search));
  const state = { search: initial.search || "", timing: initial.timing || "", page: Math.max(1, Number(initial.page) || 1), debounce: 0, requestId: 0 };
  const escapeHtml = (value) => String(value ?? "").replace(/[&<>'"]/g, (character) => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", "'": "&#39;", '"': "&quot;" })[character]);

  async function authenticate() {
    const response = await axios.get("api/auth.php?action=session");
    if (!response.data.authenticated || response.data.user?.role !== "SBO Adviser") {
      location.replace("./");
      throw new Error("Unauthorized");
    }
  }

  function syncUrl() {
    const url = new URL(location.href);
    url.search = "";
    if (state.search) url.searchParams.set("search", state.search);
    if (state.timing) url.searchParams.set("timing", state.timing);
    if (state.page > 1) url.searchParams.set("page", state.page);
    history.replaceState(null, "", url);
  }

  function renderEvent(event) {
    const stats = [[event.activity_count, "Total activities"], [event.ongoing_activity_count, "Ongoing"], [event.completed_activity_count, "Completed"]];
    const poster = event.poster_path ? `<img class="absolute inset-y-0 left-0 hidden h-full w-3/4 object-cover sm:block" style="opacity:.50;filter:blur(1px);transform:scale(1.05);mix-blend-mode:multiply" src="${escapeHtml(event.poster_path)}" alt="" aria-hidden="true">` : "";
    return `<article class="relative overflow-hidden rounded-3xl border border-[#121017]/9 bg-[#F3F0E9] shadow-[0_16px_45px_rgba(18,16,23,.5)]"><div class="absolute inset-0" style="background:rgba(243,240,233,.82)"></div>${poster}<div class="absolute inset-0" style="background:linear-gradient(90deg,rgba(243,240,233,.35) 0%,rgba(243,240,233,.58) 42%,rgba(243,240,233,.88) 65%,rgb(243,240,233) 82%)"></div><div class="relative flex flex-col gap-5 p-5 sm:flex-row sm:items-center sm:justify-between sm:p-7"><div class="min-w-0"><h2 class="text-xl font-black tracking-[-.035em] sm:text-2xl">${escapeHtml(event.title)}</h2><div class="mt-5 flex items-start gap-5 sm:gap-8">${stats.map(([value, label]) => `<div class="min-w-0"><strong class="block text-xl font-black text-[#397565]">${Number(value).toLocaleString()}</strong><span class="mt-1 block whitespace-nowrap text-[10px] font-bold uppercase tracking-wide text-[#121017]/42">${label}</span></div>`).join("")}</div></div><a class="inline-flex min-h-11 shrink-0 items-center justify-center rounded-xl bg-[#397565] px-5 text-xs font-black text-white transition hover:bg-[#2f6255]" href="pages/adviser/score-configuration.html?event_id=${event.id}">Manage scores</a></div></article>`;
  }

  function renderPagination(meta) {
    pagination.classList.toggle("hidden", meta.last_page <= 1);
    pagination.classList.toggle("flex", meta.last_page > 1);
    if (meta.last_page <= 1) return;
    pagination.innerHTML = `<small>Showing ${meta.from}–${meta.to} of ${meta.total}</small><div class="flex gap-2"><button class="rounded-lg border px-3 py-2 font-black text-[#397565] disabled:opacity-30" type="button" data-page="${meta.current_page - 1}" ${meta.current_page === 1 ? "disabled" : ""}>Previous</button><button class="rounded-lg border px-3 py-2 font-black text-[#397565] disabled:opacity-30" type="button" data-page="${meta.current_page + 1}" ${meta.current_page === meta.last_page ? "disabled" : ""}>Next</button></div>`;
  }

  function renderEvents(data) {
    $("[data-clear-filters]").classList.toggle("hidden", !state.search && !state.timing);
    if (!data.events.length) {
      const filtered = state.search || state.timing;
      eventList.innerHTML = `<div class="rounded-3xl border border-[#121017]/9 bg-white px-6 py-16 text-center"><h2 class="text-lg font-black">${filtered ? "No events match these filters" : "There are no events to score yet"}</h2><p class="mt-2 text-sm text-[#121017]/45">${filtered ? "Change or clear the current filters." : "Create an event before configuring its scoring."}</p></div>`;
    } else eventList.innerHTML = data.events.map(renderEvent).join("");
    renderPagination(data.pagination);
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
      renderEvents(data);
    } catch (error) {
      if (requestId !== state.requestId) return;
      const message = error.response?.data?.message || "Scoreboards could not be loaded.";
      window.Notifications?.error?.(message);
      eventList.innerHTML = `<p class="rounded-2xl bg-white p-10 text-center text-sm text-[#D64A12]">${escapeHtml(message)}</p>`;
    } finally { if (requestId === state.requestId) eventList.style.opacity = ""; }
  }

  filters.search.value = state.search;
  filters.timing.value = state.timing;
  filters.addEventListener("submit", (event) => { event.preventDefault(); state.search = filters.search.value.trim(); state.page = 1; load(); });
  filters.search.addEventListener("input", () => { clearTimeout(state.debounce); state.debounce = setTimeout(() => { state.search = filters.search.value.trim(); state.page = 1; load(); }, 320); });
  filters.timing.addEventListener("change", () => { state.timing = filters.timing.value; state.page = 1; load(); });
  $("[data-clear-filters]").addEventListener("click", () => { state.search = ""; state.timing = ""; state.page = 1; filters.search.value = ""; filters.timing.value = ""; load(); });
  pagination.addEventListener("click", (event) => { const button = event.target.closest("[data-page]"); if (!button || button.disabled) return; state.page = Number(button.dataset.page); load(); filters.scrollIntoView({ behavior: "smooth", block: "start" }); });

  authenticate().then(load).catch((error) => { if (error.message !== "Unauthorized") console.error(error); }).finally(() => window.SharedNavigation.finishPageLoad());
});
