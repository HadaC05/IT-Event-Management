window.SharedNavigation.ready.then(() => {
  "use strict";
  let csrf = "",
    data = null,
    dirty = false,
    submitting = false,
    rosterRequest = 0;
  const $ = (s) => document.querySelector(s),
    form = $("[data-attendance-form]"),
    filters = $("[data-roster-filters]"),
    rows = $("[data-roster-rows]"),
    paginations = [...document.querySelectorAll("[data-pagination]")];
  form.dataset.axiosForm = "";
  const escapeHtml = (v) =>
    String(v ?? "").replace(
      /[&<>'"]/g,
      (c) =>
        ({
          "&": "&amp;",
          "<": "&lt;",
          ">": "&gt;",
          "'": "&#39;",
          '"': "&quot;",
        })[c],
    );
  const eventId = Number(new URLSearchParams(location.search).get("event_id")),
    params = () => Object.fromEntries(new URLSearchParams(location.search));
  const pageUrl = (changes = {}) => {
    const url = new URL(
        "pages/adviser/attendance-roster.html",
        document.baseURI,
      ),
      current = params();
    url.searchParams.set("event_id", eventId);
    ["date", "search", "status"].forEach((k) => {
      const v = Object.hasOwn(changes, k) ? changes[k] : current[k];
      if (v) url.searchParams.set(k, v);
    });
    const page = changes.page ?? current.page;
    if (Number(page) > 1) url.searchParams.set("page", page);
    return url.href;
  };
  const confirmLeave = async () => {
    if (!dirty) return true;
    return window.Notifications?.confirm
      ? window.Notifications.confirm({
          title: "Discard unsaved changes?",
          message: "Attendance changes on this page have not been saved and will be lost.",
          action: "Discard changes",
        })
      : false;
  };
  const localDate = (v) => new Date(String(v).replace(" ", "T"));
  const shortDate = (v) =>
    new Intl.DateTimeFormat("en-PH", { month: "short", day: "numeric" }).format(
      localDate(v),
    );
  const longDate = (v) =>
    new Intl.DateTimeFormat("en-PH", {
      weekday: "short",
      month: "short",
      day: "numeric",
      year: "numeric",
      hour: "numeric",
      minute: "2-digit",
    }).format(localDate(v));
  const checkedAt = (v) =>
    v
      ? new Intl.DateTimeFormat("en-PH", {
          month: "short",
          day: "numeric",
          year: "numeric",
          hour: "numeric",
          minute: "2-digit",
        }).format(localDate(v))
      : "—";
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
    csrf = response.data.csrf_token;
    const logout = $('form[action="api/auth.php?action=logout"]');
    logout.onsubmit = async (event) => {
      event.preventDefault();
      try {
        await axios.post(
          "api/auth.php?action=logout",
          {},
          { headers: { "X-CSRF-Token": csrf } },
        );
      } finally {
        location.replace("./");
      }
    };
  }

  function renderHeader() {
    const e = data.event,
      now = new Date(),
      start = localDate(e.start_at),
      end = localDate(e.end_at),
      state = start > now ? "upcoming" : end < now ? "completed" : "ongoing",
      tones = {
        upcoming: "bg-[#2F3AE0]/8 text-[#2F3AE0]",
        ongoing: "bg-[#C6F24E]/40 text-[#397565]",
        completed: "bg-[#121017]/6 text-[#121017]/45",
      };
    $("[data-event-title]").textContent = e.title;
    document.title = `${e.title} Attendance | CITE Events`;
    $("[data-event-details]").innerHTML =
      `<span class="inline-flex items-center gap-1.5"><svg class="h-4 w-4 fill-none stroke-[#397565] stroke-2" viewBox="0 0 24 24"><path d="M6 2v4m12-4v4M3 10h18M5 4h14a2 2 0 0 1 2 2v14H3V6a2 2 0 0 1 2-2Z"/></svg>${longDate(e.start_at)}–${new Intl.DateTimeFormat("en-PH", { hour: "numeric", minute: "2-digit" }).format(end)}</span><span class="inline-flex items-center gap-1.5"><svg class="h-4 w-4 fill-none stroke-[#FF6B2C] stroke-2" viewBox="0 0 24 24"><path d="M20 10c0 5-8 12-8 12S4 15 4 10a8 8 0 1 1 16 0Z"/><circle cx="12" cy="10" r="2"/></svg>${escapeHtml(e.location || "Venue not specified")}</span>`;
    const badge = $("[data-schedule-state]");
    badge.textContent = state[0].toUpperCase() + state.slice(1);
    badge.className = `rounded-full px-2.5 py-1 text-[9px] font-black uppercase tracking-wide ${tones[state]}`;
    const eventSwitch = $("[data-event-switch]");
    eventSwitch.replaceChildren();
    data.event_options.forEach((o) =>
      eventSwitch.add(
        new Option(
          `${o.title} · ${shortDate(o.start_at)}`,
          o.id,
          false,
          o.id === e.id,
        ),
      ),
    );
    eventSwitch.onchange = async () => {
      if (await confirmLeave())
        location.assign(
          `pages/adviser/attendance-roster.html?event_id=${eventSwitch.value}`,
        );
      else eventSwitch.value = String(e.id);
    };
    const nav = $("[data-date-navigation]");
    nav.replaceChildren();
    nav.classList.toggle("hidden", data.attendance_dates.length <= 1);
    if (data.attendance_dates.length > 1) {
      nav.classList.add("flex");
      const label = document.createElement("span");
      label.className =
        "px-2 text-[9px] font-black uppercase tracking-[.14em] text-[#121017]/35";
      label.textContent = "Attendance day";
      nav.append(label);
      data.attendance_dates.forEach((date, index) => {
        const a = document.createElement("a");
        a.href = pageUrl({ date, page: 1, search: "", status: "" });
        a.textContent = `Day ${index + 1} · ${shortDate(`${date}T00:00:00`)}`;
        a.className = `rounded-xl px-4 py-2.5 text-xs font-black ${date === data.attendance_date ? "bg-[#397565] text-white" : "bg-[#F3F0E9]/60 text-[#121017]/55 hover:text-[#397565]"}`;
        a.onclick = async (e) => {
          if (!dirty) return;
          e.preventDefault();
          if (await confirmLeave()) location.assign(a.href);
        };
        nav.append(a);
      });
    }
  }

  function renderSummary(counts = data.counts) {
    const recorded = Object.values(counts).reduce((n, v) => n + Number(v), 0),
      expected = data.summary.expected;
    $("[data-recorded]").textContent = recorded;
    $("[data-expected]").textContent = expected;
    $("[data-unrecorded]").textContent =
      Math.max(0, expected - recorded);
    Object.entries(counts).forEach(
      ([status, count]) => ($(`[data-count="${status}"]`).textContent = count),
    );
  }
  const tone = (select) => {
    const all = [
        "border-[#397565]/25",
        "bg-[#397565]/8",
        "text-[#397565]",
        "border-[#FF6B2C]/25",
        "bg-[#FF6B2C]/8",
        "text-[#b94312]",
        "border-red-300/60",
        "bg-red-50",
        "text-red-700",
        "border-[#2F3AE0]/20",
        "bg-[#2F3AE0]/5",
        "text-[#2F3AE0]",
        "border-[#121017]/10",
        "bg-white",
        "text-[#121017]/55",
      ],
      styles = {
        present: ["border-[#397565]/25", "bg-[#397565]/8", "text-[#397565]"],
        absent: ["border-red-300/60", "bg-red-50", "text-red-700"],
        "": ["border-[#121017]/10", "bg-white", "text-[#121017]/55"],
      };
    select.classList.remove(...all);
    select.classList.add(...styles[select.value]);
  };

  function renderRows() {
    rows.replaceChildren();
    $("[data-roster-count]").textContent =
      `${data.participant_total} total in this roster · showing ${data.pagination.total} matching ${data.pagination.total === 1 ? "student" : "students"}`;
    const empty = $("[data-roster-empty]"),
      showTable = data.participant_total > 0 && data.participants.length > 0;
    empty.classList.toggle("hidden", showTable);
    form.classList.toggle("hidden", !showTable);
    filters.classList.toggle("hidden", data.participant_total === 0);
    if (!data.participant_total)
      empty.innerHTML =
        '<strong class="block text-sm font-black text-[#121017]">No students are expected yet</strong><p class="mt-1 text-xs text-[#121017]/42">Update this event’s participant settings or add active student accounts.</p>';
    else if (!data.participants.length)
      empty.innerHTML = `<strong class="block text-sm font-black text-[#121017]">No students match these filters</strong><p class="mt-1 text-xs text-[#121017]/42">Try another name, student ID, or attendance status.</p><a class="mt-5 inline-flex min-h-10 items-center rounded-xl border border-[#FF6B2C]/25 px-4 text-xs font-black text-[#FF6B2C]" href="${pageUrl({ search: "", status: "", page: 1 })}">Reset Filters</a>`;
    data.participants.forEach((person) => {
      const status = person.attendance_status || "",
        tr = document.createElement("tr");
      tr.className =
        "grid grid-cols-2 gap-x-4 gap-y-4 px-5 py-5 transition hover:bg-[#397565]/[.035] sm:table-row sm:px-0 sm:py-0";
      tr.innerHTML = `<td class="col-span-2 block p-0 sm:table-cell sm:px-6 sm:py-3.5"><div class="flex items-center gap-3"><span class="grid h-9 w-9 shrink-0 place-items-center rounded-full bg-[#121017] text-[9px] font-black text-[#F3F0E9]">${escapeHtml(person.first_name[0] + person.last_name[0])}</span><span class="grid min-w-0"><strong class="truncate text-xs font-black text-[#121017]">${escapeHtml(person.full_name)}</strong><small class="mt-0.5 text-[9px] font-semibold text-[#121017]/38">${escapeHtml(person.id_number || "No student ID")}${person.is_expected ? "" : " · Historical record"}</small></span></div></td><td class="block p-0 sm:table-cell sm:px-4 sm:py-3.5"><span class="mb-1.5 block text-[8px] font-black uppercase tracking-[.13em] text-[#121017]/30 sm:hidden">Tribe / Year</span><span class="block max-w-52 truncate text-[10px] font-bold text-[#121017]/65">${escapeHtml(person.team_names || "No tribe")}</span><small class="mt-0.5 block text-[9px] font-semibold text-[#121017]/35">${escapeHtml(person.year_level_label || "Year level not set")}</small></td><td class="block p-0 sm:table-cell sm:px-4 sm:py-3.5"><span class="mb-1.5 block text-[8px] font-black uppercase tracking-[.13em] text-[#121017]/30 sm:hidden">Status</span><label class="relative block w-full sm:max-w-48"><span class="pointer-events-none absolute left-3 top-1/2 h-1.5 w-1.5 -translate-y-1/2 rounded-full bg-current"></span><select class="h-10 w-full appearance-none rounded-xl border py-0 pl-7 pr-8 text-[10px] font-black outline-none transition focus:ring-4 focus:ring-[#397565]/10" data-status data-user-id="${person.id}" data-original="${status}" data-checked-in="${escapeHtml(person.checked_in_at || "")}"><option value="">${person.scan_state === "incomplete" ? "Incomplete scan" : "Not recorded"}</option><option value="present">Present</option><option value="absent">Absent</option></select><svg class="pointer-events-none absolute right-3 top-1/2 h-3.5 w-3.5 -translate-y-1/2 fill-none stroke-current stroke-2" viewBox="0 0 24 24"><path d="m7 10 5 5 5-5"/></svg></label></td><td class="col-span-2 block border-t border-[#121017]/6 pt-3 text-[10px] font-semibold text-[#121017]/38 sm:table-cell sm:border-0 sm:px-6 sm:py-3.5"><span class="mr-2 text-[8px] font-black uppercase tracking-[.13em] text-[#121017]/30 sm:hidden">Time in</span><span data-check-in>${checkedAt(person.checked_in_at)}</span></td>`;
      tr.querySelector("[data-check-in]").textContent = checkedAt(person.time_in_at);
      tr.querySelector("[data-check-in]").previousElementSibling.textContent = "Time in";
      tr.insertAdjacentHTML("beforeend", `<td class="col-span-2 block border-t border-[#121017]/6 pt-3 text-[10px] font-semibold text-[#121017]/55 sm:table-cell sm:border-0 sm:px-4 sm:py-3.5"><span class="mr-2 text-[8px] font-black uppercase tracking-[.13em] text-[#121017]/30 sm:hidden">Time out</span>${checkedAt(person.time_out_at)}</td>`);
      const select = tr.querySelector("[data-status]");
      select.value = status;
      tone(select);
      select.onchange = refresh;
      rows.append(tr);
    });
    renderPagination();
    refresh();
  }

  function renderPagination() {
    const meta = data.pagination;
    paginations.forEach(pagination => {
      pagination.replaceChildren();
      pagination.classList.toggle("hidden", meta.last_page <= 1);
      if (meta.last_page <= 1) return;
      pagination.classList.add("flex");
      const info = document.createElement("small");
      info.className = "font-semibold text-[#121017]/50";
      info.textContent = `Page ${meta.current_page} of ${meta.last_page} · ${meta.from}–${meta.to} of ${meta.total}`;
      const buttons = document.createElement("div");
      buttons.className = "flex gap-2";
      [
        ["Previous", meta.current_page - 1, meta.current_page === 1],
        ["Next", meta.current_page + 1, meta.current_page === meta.last_page],
      ].forEach(([label, page, disabled]) => {
        const button = document.createElement("button");
        button.type = "button";
        button.textContent = label;
        button.disabled = disabled;
        button.className = "min-h-10 rounded-lg border border-[#121017]/10 px-3 py-2 font-black text-[#397565] disabled:opacity-30";
        button.onclick = async () => {
          if (!(await confirmLeave())) return;
          const previousUrl = location.href;
          history.replaceState(null, "", pageUrl({ page }));
          if (!(await load())) { history.replaceState(null, "", previousUrl); return; }
          $("#student-roster-heading").scrollIntoView({ behavior: "smooth", block: "start" });
        };
        buttons.append(button);
      });
      pagination.append(info, buttons);
    });
  }
  function refresh() {
    const counts = { ...data.counts },
      selects = [...document.querySelectorAll("[data-status]")];
    let changed = 0;
    selects.forEach((select) => {
      const original = select.dataset.original,
        current = select.value;
      tone(select);
      if (original !== current) {
        changed++;
        if (original) counts[original] = Math.max(0, counts[original] - 1);
        if (current) counts[current]++;
      }
    });
    dirty = changed > 0;
    $("[data-changed-count]").textContent = changed;
    $("[data-save]").disabled = !dirty;
    renderSummary(counts);
  }
  async function load() {
    if (!eventId) {
      location.replace("pages/adviser/attendance.html");
      return;
    }
    const requestId = ++rosterRequest;
    try {
      const response = await axios.get("api/attendance.php", {
        params: { ...params(), event_id: eventId },
      });
      if (requestId !== rosterRequest) return;
      data = response.data.data;
      filters.search.value = params().search || "";
      filters.status.value = params().status || "";
      $("[data-roster-filter-details]").open = Boolean(params().search || params().status);
      $("[data-attendance-date]").value = data.attendance_date;
      renderHeader();
      renderSummary();
      renderRows();
      return true;
    } catch (error) {
      if (requestId !== rosterRequest) return;
      window.Notifications?.error?.(
        error.response?.data?.message ||
          "Attendance roster could not be loaded.",
      );
      if (error.response?.status === 422)
        setTimeout(
          () => location.replace("pages/adviser/attendance.html"),
          700,
        );
      return false;
    }
  }
  filters.onsubmit = async (e) => {
    e.preventDefault();
    if (await confirmLeave())
      location.assign(
        pageUrl({
          search: filters.search.value.trim(),
          status: filters.status.value,
          page: 1,
        }),
      );
  };
  $("[data-mark-present]").onclick = async () => {
    const selects = [...document.querySelectorAll("[data-status]")];
    const accepted = selects.length && (window.Notifications?.confirm
      ? await window.Notifications.confirm({
          title: "Mark everyone present?",
          message: `All ${selects.length} students shown on this page will be marked present. These changes are not recorded until you save.`,
          action: "Mark present",
        })
      : false);
    if (accepted) {
      selects.forEach((select) => (select.value = "present"));
      refresh();
    }
  };
  $("[data-discard]").onclick = () => {
    document
      .querySelectorAll("[data-status]")
      .forEach((select) => (select.value = select.dataset.original));
    refresh();
  };
  form.onsubmit = async (e) => {
    e.preventDefault();
    if (!dirty) return;
    const button = $("[data-save]"),
      records = {};
    document
      .querySelectorAll("[data-status]")
      .forEach(
        (select) =>
          (records[select.dataset.userId] = { status: select.value || null }),
      );
    submitting = true;
    button.disabled = true;
    button.textContent = "Saving…";
    try {
      const response = await axios.post(
        "api/attendance.php",
        { event_id: eventId, attendance_date: data.attendance_date, records },
        { headers: { "X-CSRF-Token": csrf } },
      );
      dirty = false;
      window.Notifications?.flashNext?.(response.data.message || "Attendance saved successfully.");
      setTimeout(() => location.reload(), 450);
    } catch (error) {
      submitting = false;
      button.disabled = false;
      button.textContent = "Save Attendance";
      window.Notifications?.error?.(
        error.response?.data?.message || "Attendance could not be saved.",
      );
    }
  };
  addEventListener("beforeunload", (e) => {
    if (dirty && !submitting) {
      e.preventDefault();
      e.returnValue = "";
    }
  });
  initializeShell();
  authenticate()
    .then(load)
    .catch((error) => {
      if (error.message !== "Unauthorized")
        console.error("Unable to load attendance roster:", error);
    });
});
