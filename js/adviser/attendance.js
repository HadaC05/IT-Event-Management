(() => {
  "use strict";
  const $ = (selector) => document.querySelector(selector),
    filters = $("[data-attendance-filters]"),
    list = $("[data-event-list]"),
    pagination = $("[data-pagination]");
  const escapeHtml = (value) =>
    String(value ?? "").replace(
      /[&<>'"]/g,
      (char) =>
        ({
          "&": "&amp;",
          "<": "&lt;",
          ">": "&gt;",
          "'": "&#39;",
          '"': "&quot;",
        })[char],
    );
  const asDate = (value) => new Date(String(value).replace(" ", "T"));
  const schedule = (event) => {
    const start = asDate(event.start_at),
      end = asDate(event.end_at),
      same = start.toDateString() === end.toDateString(),
      day = (value) =>
        new Intl.DateTimeFormat("en-PH", {
          month: "short",
          day: "numeric",
          year: same ? "numeric" : undefined,
          hour: "numeric",
          minute: "2-digit",
        }).format(value);
    return same
      ? `${day(start)}–${new Intl.DateTimeFormat("en-PH", { hour: "numeric", minute: "2-digit" }).format(end)}`
      : `${day(start)} → ${day(end)}`;
  };
  const query = () => Object.fromEntries(new URLSearchParams(location.search));
  const urlFor = (page) => {
    const url = new URL(location.href);
    url.search = "";
    new FormData(filters).forEach((value, key) => {
      if (value) url.searchParams.set(key, value);
    });
    if (page > 1) url.searchParams.set("page", page);
    return url.href;
  };
  function initializeShell() {
    const sidebar = $("#sidebar"),
      scrim = $("[data-sidebar-scrim]"),
      trigger = $('[aria-controls="sidebar"]');
    document.querySelectorAll("[data-sidebar-toggle]").forEach(
      (button) =>
        (button.onclick = () => {
          const opening = sidebar.classList.contains("-translate-x-full");
          sidebar.classList.toggle("-translate-x-full", !opening);
          sidebar.classList.toggle("translate-x-0", opening);
          scrim.classList.toggle("hidden", !opening);
          trigger?.setAttribute("aria-expanded", String(opening));
        }),
    );
    const menu = $("[data-account-menu]");
    document.addEventListener("click", (event) => {
      if (menu?.open && !menu.contains(event.target))
        menu.removeAttribute("open");
    });
  }
  function applyAccount(user) {
    const account = $("[data-account-menu]"),
      name = user.full_name || user.username,
      avatar = account.querySelector("summary > span:first-child");
    avatar.childNodes[0].textContent =
      `${user.first_name?.[0] || ""}${user.last_name?.[0] || ""}`.toUpperCase();
    account.querySelector("summary strong").textContent = name;
    account.querySelector("summary small").textContent = user.role;
    account.querySelector("div > div strong").textContent = name;
    account.querySelector("div > div span").textContent =
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
    const logout = $('form[action="api/auth.php?action=logout"]');
    logout.onsubmit = async (event) => {
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
    return response.data;
  }
  function renderSummary(summary) {
    $("[data-summary-records]").textContent = summary.records.toLocaleString();
    $("[data-summary-tracked]").innerHTML =
      `${summary.tracked.toLocaleString()} <span class="text-xs text-[#121017]/30">/ ${summary.events.toLocaleString()}</span>`;
    const rate = $("[data-summary-rate]");
    rate.textContent =
      summary.attendance_rate === null ? "—" : `${summary.attendance_rate}%`;
    rate.className = `mt-1 text-xl font-black ${summary.attendance_rate === null ? "text-[#121017]/30" : "text-[#397565]"}`;
    $("[data-summary-attended]").textContent = summary.records
      ? `${summary.attended} attended records`
      : "Waiting for records";
    const untracked = $("[data-summary-untracked]");
    untracked.textContent = summary.untracked.toLocaleString();
    untracked.className = `mt-1 text-xl font-black ${summary.untracked ? "text-[#FF6B2C]" : "text-[#397565]"}`;
    const manage = $("[data-manage-events]");
    manage.classList.toggle("hidden", summary.events === 0);
    manage.classList.toggle("inline-flex", summary.events > 0);
    filters.classList.toggle("hidden", summary.events === 0);
    filters.classList.toggle("grid", summary.events > 0);
  }
  function renderEvents(data) {
    list.replaceChildren();
    $("[data-result-count]").textContent =
      `${data.pagination.total} ${data.pagination.total === 1 ? "event" : "events"} available`;
    $("[data-clear-filters]").classList.toggle(
      "hidden",
      !filters.search.value && !filters.timing.value,
    );
    if (!data.events.length) {
      const filtered = filters.search.value || filters.timing.value,
        empty = document.createElement("div");
      empty.className =
        "rounded-2xl border border-[#121017]/10 bg-white/70 px-6 py-12 text-center xl:col-span-2";
      empty.innerHTML = `<strong class="block text-sm font-black text-[#121017]">${filtered ? "No events match these filters" : "There are no attendance rosters yet"}</strong><p class="mt-1 text-xs text-[#121017]/42">${filtered ? "Change or clear the current filters." : "Create an event and define its participants to begin."}</p><a class="mt-5 inline-flex min-h-10 items-center rounded-xl ${filtered ? "border border-[#FF6B2C]/25 text-[#FF6B2C]" : "bg-[#2F3AE0] text-white"} px-4 text-xs font-black" href="${filtered ? "pages/adviser/attendance.html" : "pages/adviser/events.html?create=1"}">${filtered ? "Reset Filters" : "Create Event"}</a>`;
      list.append(empty);
    }
    const badge = {
      upcoming: ["bg-[#2F3AE0]/8 text-[#2F3AE0]", "bg-[#2F3AE0]"],
      ongoing: ["bg-[#C6F24E]/40 text-[#397565]", "bg-[#C6F24E]"],
      completed: ["bg-[#121017]/6 text-[#121017]/45", "bg-[#121017]/25"],
    };
    data.events.forEach((event) => {
      const expected = event.expected_count,
        recorded = event.attendances_count,
        coverage = event.coverage_rate ?? 0,
        remaining = Math.max(0, expected - recorded),
        attention = !expected
          ? "No participant roster"
          : !remaining
            ? "Roster complete"
            : !recorded
              ? "Attendance not started"
              : `${remaining} ${remaining === 1 ? "status" : "statuses"} needed`,
        attentionColor =
          remaining || !expected ? "text-[#FF6B2C]" : "text-[#397565]",
        dot = remaining || !expected ? "bg-[#FF6B2C]" : "bg-[#C6F24E]",
        state = badge[event.schedule_state];
      const card = document.createElement("article");
      card.className =
        "group flex min-h-64 flex-col rounded-2xl border border-[#121017]/10 bg-white/75 p-5 shadow-[0_16px_45px_rgba(18,16,23,.04)] transition hover:-translate-y-0.5 hover:border-[#397565]/25 hover:shadow-[0_20px_55px_rgba(18,16,23,.07)] sm:p-6";
      card.innerHTML = `<div class="flex items-start justify-between gap-4"><div class="min-w-0"><div class="flex flex-wrap items-center gap-2"><h3 class="truncate text-lg font-black tracking-[-.025em] text-[#121017]">${escapeHtml(event.title)}</h3><span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-[9px] font-black uppercase tracking-wide ${state[0]}"><span class="h-1.5 w-1.5 rounded-full ${state[1]}"></span>${event.schedule_state}</span></div><p class="mt-2 text-[10px] font-semibold text-[#121017]/42">${schedule(event)}</p><p class="mt-1 text-[10px] font-semibold text-[#121017]/42">${escapeHtml(event.location || "Venue not specified")}</p></div><span class="mt-1 h-2.5 w-2.5 shrink-0 rounded-full ${dot}"></span></div><div class="mt-6 grid grid-cols-2 gap-4 border-y border-[#121017]/8 py-4"><div><span class="text-[9px] font-black uppercase tracking-[.14em] text-[#121017]/30">Roster completion</span><p class="mt-1.5 text-sm font-black text-[#121017]">${recorded} of ${expected} recorded</p></div><div><span class="text-[9px] font-black uppercase tracking-[.14em] text-[#121017]/30">Present or late</span><p class="mt-1.5 text-sm font-black ${event.attendance_rate === null ? "text-[#121017]/30" : "text-[#397565]"}">${event.attendance_rate === null ? "—" : `${event.attendance_rate}%`}</p><small class="mt-1 block text-[9px] font-semibold text-[#121017]/35">Among recorded statuses</small></div></div><div class="mt-4"><div class="flex items-center justify-between gap-3 text-[9px] font-black"><span class="${attentionColor}">${attention}</span><span class="text-[#121017]/40">${event.coverage_rate === null ? "—" : `${event.coverage_rate}%`}</span></div><div class="mt-2 h-1.5 overflow-hidden rounded-full bg-[#121017]/6"><div class="h-full rounded-full ${!remaining && expected ? "bg-[#397565]" : "bg-[#FF6B2C]"}" style="width:${coverage}%"></div></div></div><div class="mt-auto flex items-end justify-between gap-4 pt-5"><p class="text-[9px] font-semibold text-[#121017]/38"><span class="text-[#397565]">${event.present_count} present</span> · ${event.late_count} late · ${event.absent_count} absent</p><a class="inline-flex min-h-11 shrink-0 items-center justify-center gap-2 rounded-xl bg-[#2F3AE0] px-5 text-xs font-black text-white shadow-[0_8px_20px_rgba(47,58,224,.16)] transition hover:-translate-y-0.5 hover:bg-[#252fc4]" href="pages/adviser/attendance-roster.html?event_id=${event.id}">Open Roster <span>→</span></a></div>`;
      list.append(card);
    });
    renderPagination(data.pagination);
  }
  function renderPagination(meta) {
    pagination.replaceChildren();
    pagination.classList.toggle("hidden", meta.last_page <= 1);
    if (meta.last_page <= 1) return;
    pagination.classList.add("flex");
    const info = document.createElement("span");
    info.textContent = `Showing ${meta.from}–${meta.to} of ${meta.total}`;
    const links = document.createElement("div");
    links.className = "flex gap-2";
    [
      ["← Previous", meta.current_page - 1, meta.current_page === 1],
      ["Next →", meta.current_page + 1, meta.current_page === meta.last_page],
    ].forEach(([label, page, disabled]) => {
      const a = document.createElement("a");
      a.textContent = label;
      a.className = `rounded-lg border px-3 py-2 font-bold ${disabled ? "pointer-events-none opacity-40" : "hover:bg-slate-50"}`;
      a.href = disabled ? "#" : urlFor(page);
      links.append(a);
    });
    pagination.append(info, links);
  }
  async function load() {
    try {
      const response = await axios.get("api/attendance.php", {
        params: query(),
      });
      renderSummary(response.data.data.summary);
      renderEvents(response.data.data);
    } catch (error) {
      window.Notifications?.error?.(
        error.response?.data?.message ||
          "Attendance events could not be loaded.",
      );
      list.innerHTML =
        '<p class="col-span-full p-10 text-center text-sm text-rose-600">Attendance events could not be loaded.</p>';
    }
  }
  const values = query();
  filters.search.value = values.search || "";
  filters.timing.value = values.timing || "";
  filters.onsubmit = (event) => {
    event.preventDefault();
    location.assign(urlFor(1));
  };
  $("[data-clear-filters]").onclick = () =>
    location.assign("pages/adviser/attendance.html");
  initializeShell();
  authenticate()
    .then(load)
    .catch((error) => {
      if (error.message !== "Unauthorized")
        console.error("Unable to load attendance:", error);
    });
})();
