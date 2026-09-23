window.SharedNavigation.ready.then((context) => {
  "use strict";
  let csrf = context.csrfToken,
    page = 1,
    data = null,
    editing = null,
    timer,
    previewImageUrl = null;
  const $ = (s) => document.querySelector(s),
    list = $("[data-team-list]"),
    filters = $("[data-tribe-filters]"),
    dialog = $("#team-dialog"),
    unassignedDialog = $("#unassigned-students-dialog"),
    form = $("[data-team-form]");
  let unassignedSelection = new Set(),
    unassignedData = { students: [], pagination: { total: 0, current_page: 1, last_page: 1 } },
    unassignedPage = 1,
    unassignedSearchTimer,
    unassignedRequest = 0,
    memberSelection = new Set(),
    memberPage = 1,
    memberSearchTimer,
    memberRequest = 0;
  const initialUrl = new URL(location.href);
  let requestedSchoolYear = initialUrl.searchParams.get("school_year") || "";
  filters.search.value = initialUrl.searchParams.get("search") || "";
  filters.status.value = initialUrl.searchParams.get("status") || "";
  page = Math.max(1, Number(initialUrl.searchParams.get("page")) || 1);
  const toast = (type, msg) =>
    window.Notifications?.[type]?.(msg);
  const initials = (name) => {
    const w = name.trim().split(/\s+/).filter(Boolean);
    return (
      w.length > 1
        ? w
            .slice(0, 2)
            .map((x) => x[0])
            .join("")
        : (w[0] || "TR").slice(0, 2)
    ).toUpperCase();
  };
  const personInitials = name => {
    const words = String(name || '').trim().split(/\s+/).filter(Boolean);
    return (words.length > 1 ? `${words[0][0]}${words[words.length - 1][0]}` : (words[0] || 'ST').slice(0, 2)).toUpperCase();
  };
  const schoolYearLabel = (label) =>
    /^SY\b/i.test(String(label || "")) ? String(label) : `SY ${label}`;
  const api = async (payload) =>
    axios.post("api/teams.php", payload, { headers: { "X-CSRF-Token": csrf } });
  const confirmAction = (o) =>
    window.Notifications?.confirm?.(o) ?? Promise.resolve(false);
  const button = (label, cls, fn) => {
    const b = document.createElement("button");
    b.type = "button";
    b.className = cls;
    b.textContent = label;
    b.onclick = fn;
    return b;
  };
  const syncUrl = () => {
    const u = new URL(location.href);
    u.search = "";
    new FormData(filters).forEach((v, k) => {
      if (v) u.searchParams.set(k, v);
    });
    if (page > 1) u.searchParams.set("page", page);
    history.replaceState(null, "", u);
  };

  const selectedReviewYear = () =>
    String(
      $("[data-unassigned-year]")?.value ||
        filters.school_year.value ||
        data?.school_years?.[0]?.id ||
        "",
    );

  function activeTeamsForYear(yearId) {
    return (data.assignment_teams || data.teams).filter(
      (team) => String(team.school_year_id) === String(yearId),
    );
  }

  function fillTeamSelect(select, teams, placeholder = "Choose a tribe…") {
    select.replaceChildren(new Option(placeholder, ""));
    teams.forEach((team) =>
      select.add(new Option(`${team.name} · ${team.members_count} members`, team.id)),
    );
  }

  function updateUnassignedSelection() {
    const visibleChecks = [
      ...$("[data-unassigned-list]").querySelectorAll(
        '[data-unassigned-check]:not([disabled])',
      ),
    ].filter((check) => !check.closest("[data-unassigned-row]").classList.contains("hidden"));
    const selected = visibleChecks.filter((check) => check.checked).length;
    $("[data-unassigned-selected]").textContent = `${unassignedSelection.size.toLocaleString()} selected`;
    $("[data-assign-selected]").disabled =
      !unassignedSelection.size || !$("[data-unassigned-bulk-team]").value;
    const all = $("[data-unassigned-select-all]");
    all.checked = !!visibleChecks.length && selected === visibleChecks.length;
    all.indeterminate = selected > 0 && selected < visibleChecks.length;
  }

  function renderUnassignedReview() {
    if (!data) return;
    const yearId = selectedReviewYear();
    const year = data.school_years.find((item) => String(item.id) === yearId);
    const students = unassignedData.students;
    const pagination = unassignedData.pagination;
    const teams = activeTeamsForYear(yearId);
    const host = $("[data-unassigned-list]");
    const randomizeButton = $("[data-randomize-unassigned]");
    randomizeButton.disabled = !pagination.total || teams.length < 2;
    randomizeButton.classList.toggle("opacity-40", randomizeButton.disabled);
    randomizeButton.title = teams.length < 2
      ? "Create at least two active tribes before randomizing students."
      : "Distribute unassigned students without changing existing assignments.";
    $("[data-unassigned-context]").textContent =
      `${pagination.total.toLocaleString()} students have no tribe assignment for ${year ? schoolYearLabel(year.label) : "the selected school year"}.`;
    const bulkTeamSelect = $("[data-unassigned-bulk-team]"),
      selectedBulkTeam = bulkTeamSelect.value;
    fillTeamSelect(bulkTeamSelect, teams);
    if ([...bulkTeamSelect.options].some((option) => option.value === selectedBulkTeam))
      bulkTeamSelect.value = selectedBulkTeam;
    host.replaceChildren();
    if (!pagination.total) {
      const empty = document.createElement("div");
      empty.className = "px-6 py-16 text-center";
      empty.innerHTML = '<span class="mx-auto grid h-14 w-14 place-items-center rounded-full bg-[#C6F24E]/35 text-2xl text-[#397565]">✓</span><strong class="mt-4 block text-base text-[#121017]">Everyone is assigned</strong><p class="mt-1 text-sm text-slate-500">There are no unassigned active students for this school year.</p>';
      host.append(empty);
      $("[data-unassigned-bulk-bar]").classList.add("hidden");
      $("[data-unassigned-visible-count]").textContent = "0 unassigned students";
      renderUnassignedPagination();
      updateUnassignedSelection();
      return;
    }
    $("[data-unassigned-bulk-bar]").classList.remove("hidden");
    students.forEach((student) => {
      const row = document.createElement("article");
      const details = [student.program, student.year_level_label, student.section_name]
        .filter(Boolean)
        .join(" · ");
      const reason = student.import_missing_tribe
        ? "Roster imported without a confirmed tribe"
        : `No tribe in ${year ? schoolYearLabel(year.label) : "the selected school year"}`;
      row.dataset.unassignedRow = "";
      row.dataset.search = `${student.full_name} ${student.id_number || ""} ${details}`.toLowerCase();
      row.className = "unassigned-review-row grid gap-4 border-b border-slate-100 px-5 py-4 last:border-0 sm:px-7";
      row.innerHTML = `<div class="flex min-w-0 items-start gap-3"><input class="mt-3 h-4 w-4 shrink-0 rounded border-slate-300 accent-[#397565]" type="checkbox" data-unassigned-check><span class="grid h-10 w-10 shrink-0 place-items-center rounded-full bg-[#121017] text-[10px] font-black text-white">${personInitials(student.full_name)}</span><span class="min-w-0"><strong class="block truncate text-sm text-[#121017]">${escapeHtml(student.full_name)}</strong><span class="mt-0.5 block truncate text-xs text-slate-500">${escapeHtml(student.id_number || "No student ID")}</span><span class="mt-0.5 block text-xs text-slate-400">${escapeHtml(details || "Program or year information unavailable")}</span></span></div><div class="min-w-0"><span class="block text-[10px] font-black uppercase tracking-[.12em] text-slate-400">Reason</span><span class="mt-1 block text-xs font-semibold text-[#c94b18]">${escapeHtml(reason)}</span></div><div class="min-w-0 gap-2"><select class="h-10 w-full min-w-0 rounded-xl border border-slate-200 bg-white px-3 text-xs font-bold text-slate-700 outline-none" data-row-team></select><button class="min-h-10 shrink-0 rounded-xl border border-[#397565]/25 px-4 text-xs font-extrabold text-[#397565] transition hover:bg-[#397565]/5 disabled:cursor-not-allowed disabled:text-slate-300" type="button" data-row-assign disabled>Assign</button></div>`;
      const check = row.querySelector("[data-unassigned-check]");
      check.value = student.id;
      check.checked = unassignedSelection.has(student.id);
      check.onchange = () => {
        check.checked
          ? unassignedSelection.add(student.id)
          : unassignedSelection.delete(student.id);
        updateUnassignedSelection();
      };
      const teamSelect = row.querySelector("[data-row-team]");
      const assignButton = row.querySelector("[data-row-assign]");
      fillTeamSelect(teamSelect, teams, "Assign to…");
      teamSelect.onchange = () => (assignButton.disabled = !teamSelect.value);
      assignButton.onclick = () => assignUnassigned([student.id], teamSelect.value);
      host.append(row);
    });
    $("[data-unassigned-visible-count]").textContent =
      `Showing ${pagination.from.toLocaleString()}–${pagination.to.toLocaleString()} of ${pagination.total.toLocaleString()} unassigned students`;
    renderUnassignedPagination();
    updateUnassignedSelection();
  }

  function renderUnassignedPagination() {
    const pagination = unassignedData.pagination,
      nav = $("[data-unassigned-pagination]");
    const visible = pagination.last_page > 1;
    nav.classList.toggle("hidden", !visible);
    nav.classList.toggle("flex", visible);
    if (!visible) {
      nav.replaceChildren();
      return;
    }
    nav.innerHTML = `<button class="min-h-9 rounded-lg border border-slate-200 px-3 font-bold disabled:opacity-30" type="button" data-unassigned-page="${pagination.current_page - 1}" ${pagination.current_page === 1 ? "disabled" : ""}>Previous</button><b>${pagination.current_page} / ${pagination.last_page}</b><button class="min-h-9 rounded-lg border border-slate-200 px-3 font-bold disabled:opacity-30" type="button" data-unassigned-page="${pagination.current_page + 1}" ${pagination.current_page === pagination.last_page ? "disabled" : ""}>Next</button>`;
  }

  async function loadUnassignedStudents(targetPage = unassignedPage) {
    const request = ++unassignedRequest;
    $("[data-unassigned-visible-count]").textContent = "Loading students…";
    try {
      const response = await axios.get("api/teams.php", {
        params: {
          action: "unassigned_students",
          school_year_id: selectedReviewYear(),
          search: $("[data-unassigned-search]").value.trim(),
          page: targetPage,
        },
      });
      if (request !== unassignedRequest) return;
      unassignedData = response.data.data;
      unassignedPage = unassignedData.pagination.current_page;
      renderUnassignedReview();
    } catch (error) {
      if (request !== unassignedRequest) return;
      toast("error", error.response?.data?.message || "Unable to load unassigned students.");
    }
  }

  async function openUnassignedReview() {
    const yearSelect = $("[data-unassigned-year]");
    const yearId = filters.school_year.value || data.school_years[0]?.id || "";
    yearSelect.replaceChildren();
    data.school_years.forEach((year) =>
      yearSelect.add(new Option(schoolYearLabel(year.label), year.id, false, String(year.id) === String(yearId))),
    );
    unassignedSelection.clear();
    $("[data-unassigned-search]").value = "";
    unassignedDialog.showModal();
    await loadUnassignedStudents(1);
  }

  async function assignUnassigned(studentIds, teamId) {
    if (!studentIds.length || !teamId) return;
    try {
      const response = await api({
        action: "assign_unassigned",
        school_year_id: selectedReviewYear(),
        team_id: teamId,
        student_ids: studentIds,
      });
      toast("success", response.data.message);
      studentIds.forEach((id) => unassignedSelection.delete(Number(id)));
      await Promise.all([load(), loadUnassignedStudents(unassignedPage)]);
    } catch (error) {
      toast("error", error.response?.data?.message || "Unable to assign the selected students.");
    }
  }

  function renderSummary() {
    const s = data.summary;
    const summaryYear =
      s.school_year_label ||
      (data.school_years.length === 1 ? data.school_years[0].label : null);
    $("[data-summary-year]").textContent = summaryYear
      ? schoolYearLabel(summaryYear)
      : "All school years";
    const assignment = s.students
      ? s.unassigned
        ? `${s.assigned.toLocaleString()} assigned`
        : "All students assigned"
      : "No active students";
    $("[data-team-context]").textContent =
      `${s.total.toLocaleString()} ${s.total === 1 ? "tribe" : "tribes"} · ${s.students.toLocaleString()} active ${s.students === 1 ? "student" : "students"} · ${assignment}`;
    const un = $("[data-unassigned-summary]");
    un.textContent = s.unassigned
      ? `⚠ ${s.unassigned.toLocaleString()} ${s.unassigned === 1 ? "student is" : "students are"} unassigned — review students →`
      : "";
    un.classList.toggle("hidden", !s.unassigned);
    un.classList.toggle("inline-flex", !!s.unassigned);
    const rf = $("[data-randomize-form]");
    rf.classList.toggle("hidden", !(s.total && s.students));
    rf.classList.toggle("flex", !!(s.total && s.students));
  }

  function fillOptions() {
    const selectedYear = filters.school_year.value || requestedSchoolYear;
    filters.school_year.replaceChildren(new Option("All school years", ""));
    data.school_years.forEach((y) =>
      filters.school_year.add(
        new Option(schoolYearLabel(y.label), y.id, false, String(y.id) === selectedYear),
      ),
    );
    filters.school_year.value = selectedYear;
    requestedSchoolYear = "";
    const ry = $("[data-randomize-year]");
    const selected = filters.school_year.value || ry.value;
    ry.replaceChildren();
    data.school_years.forEach((y) => {
      const o = new Option(
        schoolYearLabel(y.label),
        y.id,
        false,
        String(y.id) === selected,
      );
      o.dataset.randomizedAt = y.teams_randomized_at || "";
      ry.add(o);
    });
    updateRandomize();
  }
  function updateRandomize() {
    const select = $("[data-randomize-year]"),
      b = $("[data-randomize-button]"),
      status = $("[data-randomize-status]"),
      locked = !!select.selectedOptions[0]?.dataset.randomizedAt;
    b.style.display = locked ? "none" : "";
    status.style.display = locked ? "inline-flex" : "none";
  }
  function renderTeams() {
    list.replaceChildren();
    $("[data-result-count]").textContent =
      `${data.pagination.total} ${data.pagination.total === 1 ? "tribe" : "tribes"} found`;
    filters.classList.remove("hidden");
    filters.classList.add("grid");
    $("[data-clear-filters]").classList.toggle(
      "hidden",
      ![...new FormData(filters).values()].some(Boolean),
    );
    if (!data.teams.length) {
      const e = document.createElement("div");
      e.className = "col-span-full px-6 py-10 text-center sm:py-12";
      const filtered = [...new FormData(filters).values()].some(Boolean);
      e.innerHTML = `<span class="mx-auto grid h-14 w-14 place-items-center rounded-2xl bg-emerald-50 text-emerald-700"><svg class="h-7 w-7 fill-none stroke-current" viewBox="0 0 24 24"><path d="M8 11a3 3 0 1 0 0-6 3 3 0 0 0 0 6Zm8 0a3 3 0 1 0 0-6 3 3 0 0 0 0 6ZM2 20v-2a4 4 0 0 1 4-4h4a4 4 0 0 1 4 4v2m0-5.5a4 4 0 0 1 3-1.5h1a4 4 0 0 1 4 4v3"/></svg></span><strong class="mt-4 block text-sm text-slate-600">${filtered ? "No tribes match these filters" : "No tribes created yet"}</strong><p class="mt-1 text-xs text-slate-400">${filtered ? "Try adjusting or clearing the filters." : "Create the tribes first, then randomize students when the roster is ready."}</p>${filtered ? "" : '<button class="mt-5 inline-flex min-h-10 items-center rounded-xl bg-[#397565] px-4 text-xs font-extrabold text-white" type="button" data-empty-create>Create Tribe</button>'}`;
      e.querySelector("[data-empty-create]")?.addEventListener("click", () =>
        openTeam(),
      );
      list.append(e);
    }
    data.teams.forEach((t) => {
      const card = document.createElement("article");
      card.className =
        "group overflow-hidden rounded-2xl border border-slate-200 bg-white transition hover:-translate-y-0.5 hover:shadow-lg";
      card.innerHTML = `<div class="h-1.5" style="background:${t.color}"></div>${t.image_path ? `<img class="w-full object-cover" style="aspect-ratio:16/7" src="${escapeHtml(t.image_path)}" alt="${escapeHtml(t.name)} tribe image">` : ""}<div class="p-5"><header class="flex items-start justify-between gap-4"><div class="flex min-w-0 items-center gap-3"><span class="grid h-11 w-11 place-items-center rounded-xl text-sm font-black text-white" style="background:${t.color}">${initials(t.name)}</span><div class="min-w-0"><h3 class="truncate text-base font-extrabold">${escapeHtml(t.name)}</h3><p class="mt-0.5 text-xs text-slate-400">${escapeHtml(schoolYearLabel(t.school_year_label))} · ${t.members_count} ${t.members_count === 1 ? "student" : "students"}</p></div></div><span class="rounded-full px-2.5 py-1 text-[10px] font-extrabold ${t.is_active ? "bg-[#C6F24E]/35 text-[#397565]" : "bg-[#FF6B2C]/10 text-[#FF6B2C]"}">${t.is_active ? "Active" : "Inactive"}</span></header><div class="mt-5 min-h-12" data-members></div><footer class="mt-5 flex items-center justify-between border-t border-slate-100 pt-4" data-actions></footer></div>`;
      const mh = card.querySelector("[data-members]");
      if (t.members.length) {
        const members = document.createElement("div");
        members.className = "grid gap-3";
        t.members.slice(0, 3).forEach((m) => {
          const row = document.createElement("div");
          row.className = "flex min-w-0 items-center gap-3";
          row.innerHTML = `<span class="grid h-8 w-8 shrink-0 place-items-center rounded-full text-[9px] font-black text-white" style="background:${t.color}">${personInitials(m.full_name)}</span><span class="min-w-0"><strong class="block truncate text-xs text-[#121017]">${escapeHtml(m.full_name)}</strong><small class="block truncate text-[10px] text-slate-400">${escapeHtml(m.id_number || m.year_level_label || "Student")}</small></span>`;
          members.append(row);
        });
        if (t.members_count > 3) {
          const more = document.createElement("span");
          more.className = "pl-11 text-[11px] font-extrabold text-[#397565]";
          more.textContent = `+${t.members_count - 3} more`;
          members.append(more);
        }
        mh.append(members);
      } else
        mh.innerHTML =
          '<div class="rounded-xl border border-dashed px-3 py-2.5 text-center text-xs text-slate-400">No students assigned yet</div>';
      const acts = card.querySelector("[data-actions]");
      acts.append(
        button(
          "View members →",
          "min-h-10 text-xs font-extrabold text-[#397565]",
          () => openTeam(t, true),
        ),
        button(
          "⋯",
          "grid h-10 w-10 place-items-center rounded-xl border border-slate-200 text-lg font-black text-slate-500 transition hover:border-[#397565]/30 hover:bg-slate-50",
          (event) => openTeamMenu(event.currentTarget, t),
        ),
      );
      list.append(card);
    });
    renderPagination();
  }
  function escapeHtml(v) {
    const d = document.createElement("div");
    d.textContent = v ?? "";
    return d.innerHTML;
  }
  function closeTeamMenu() {
    document.querySelector("[data-team-actions-menu]")?.remove();
  }
  function openTeamMenu(anchor, team) {
    closeTeamMenu();
    const menu = document.createElement("div");
    menu.dataset.teamActionsMenu = "";
    menu.className =
      "fixed z-[80] w-52 rounded-xl border border-slate-200 bg-white p-1.5 shadow-2xl";
    const item = (label, action, danger = false) => {
      const control = button(
        label,
        `block w-full rounded-lg px-3 py-2.5 text-left text-xs font-bold transition hover:bg-slate-50 ${danger ? "text-[#d9470a]" : "text-[#121017]"}`,
        () => {
          closeTeamMenu();
          action();
        },
      );
      menu.append(control);
    };
    item("View members", () => openTeam(team, true));
    item("Edit tribe", () => openTeam(team));
    item("Change members", () => openTeam(team, true));
    const rule = document.createElement("div");
    rule.className = "my-1 border-t border-slate-100";
    menu.append(rule);
    item(team.is_active ? "Deactivate tribe" : "Activate tribe", () => toggle(team), team.is_active);
    document.body.append(menu);
    const rect = anchor.getBoundingClientRect();
    const menuRect = menu.getBoundingClientRect();
    menu.style.left = `${Math.max(12, Math.min(innerWidth - menuRect.width - 12, rect.right - menuRect.width))}px`;
    menu.style.top = `${Math.min(innerHeight - menuRect.height - 12, rect.bottom + 6)}px`;
  }
  function renderPagination() {
    const p = data.pagination,
      n = $("[data-pagination]");
    n.replaceChildren();
    n.classList.toggle("hidden", p.last_page <= 1);
    n.classList.toggle("flex", p.last_page > 1);
    if (p.last_page <= 1) return;
    const info = document.createElement("small");
    info.textContent = `Showing ${p.from}–${p.to} of ${p.total}`;
    const ctr = document.createElement("div");
    ctr.className = "flex gap-2";
    const go = (l, target, disabled) =>
      disabled
        ? Object.assign(document.createElement("span"), {
            className: "rounded-lg border bg-slate-50 px-3 py-2 text-slate-300",
            textContent: l,
          })
        : button(l, "rounded-lg border px-3 py-2 font-bold", async () => {
            page = target;
            await load();
            syncUrl();
            scrollTo({ top: 0, behavior: "smooth" });
          });
    ctr.append(
      go("Previous", p.current_page - 1, p.current_page === 1),
      Object.assign(document.createElement("span"), {
        className: "px-2 py-2 text-slate-400",
        textContent: `${p.current_page} / ${p.last_page}`,
      }),
      go("Next", p.current_page + 1, p.current_page === p.last_page),
    );
    n.append(info, ctr);
  }
  function renderMembers(result) {
    const host = $("[data-member-list]");
    host.replaceChildren();
    result.students.forEach((s) => {
      const l = document.createElement("label");
      l.className =
        "flex cursor-pointer items-center gap-3 rounded-xl border border-slate-200 px-3.5 py-3 has-[:checked]:border-emerald-300 has-[:checked]:bg-emerald-50/60";
      const c = document.createElement("input");
      c.type = "checkbox";
      c.value = s.id;
      c.checked = memberSelection.has(Number(s.id));
      c.dataset.memberCandidate = "";
      c.onchange = () => {
        c.checked
          ? memberSelection.add(Number(s.id))
          : memberSelection.delete(Number(s.id));
        updateMemberCount();
      };
      const av = document.createElement("span");
      av.className =
        "grid h-9 w-9 place-items-center rounded-full bg-[#121017] text-[9px] font-extrabold text-white";
      av.textContent = personInitials(s.full_name);
      const tx = document.createElement("span");
      tx.className = "grid min-w-0";
      tx.innerHTML = `<strong class="truncate text-sm">${escapeHtml(s.full_name)}</strong><small class="text-slate-400">${escapeHtml(s.id_number || "No student ID")} · ${escapeHtml(s.year_level_label || "No year")}</small>`;
      l.append(c, av, tx);
      host.append(l);
    });
    $("[data-no-member-results]").classList.toggle("hidden", !!result.students.length);
    const pagination = result.pagination;
    $("[data-member-result-count]").textContent = pagination.total
      ? `Showing ${pagination.from.toLocaleString()}–${pagination.to.toLocaleString()} of ${pagination.total.toLocaleString()} eligible students`
      : "No eligible students match this search.";
    memberPage = pagination.current_page;
    renderMemberPagination(pagination);
    updateMemberCount();
  }
  function renderMemberPagination(pagination) {
    const nav = $("[data-member-pagination]"),
      visible = pagination.last_page > 1;
    nav.classList.toggle("hidden", !visible);
    nav.classList.toggle("flex", visible);
    if (!visible) {
      nav.replaceChildren();
      return;
    }
    nav.innerHTML = `<span>Page ${pagination.current_page} of ${pagination.last_page}</span><span class="flex gap-2"><button class="min-h-9 rounded-lg border border-slate-200 px-3 font-bold disabled:opacity-30" type="button" data-member-page="${pagination.current_page - 1}" ${pagination.current_page === 1 ? "disabled" : ""}>Previous</button><button class="min-h-9 rounded-lg border border-slate-200 px-3 font-bold disabled:opacity-30" type="button" data-member-page="${pagination.current_page + 1}" ${pagination.current_page === pagination.last_page ? "disabled" : ""}>Next</button></span>`;
  }
  async function loadMemberCandidates(targetPage = memberPage) {
    if (!editing) return;
    const request = ++memberRequest;
    $("[data-member-result-count]").textContent = "Loading eligible students…";
    try {
      const response = await axios.get("api/teams.php", {
        params: {
          action: "student_candidates",
          team_id: editing.id,
          school_year_id: form.elements.school_year_id.value,
          search: $("[data-member-search]").value.trim(),
          page: targetPage,
        },
      });
      if (request !== memberRequest) return;
      renderMembers(response.data.data);
    } catch (error) {
      if (request !== memberRequest) return;
      $("[data-member-list]").replaceChildren();
      $("[data-member-result-count]").textContent =
        error.response?.data?.message || "Eligible students could not be loaded.";
    }
  }
  function updateMemberCount() {
    const count = memberSelection.size;
    $("[data-selected-count]").textContent = count;
    $("[data-preview-members]").textContent = count;
    $("[data-preview-member-label]").textContent =
      count === 1 ? "member" : "members";
    clearFieldError("member_ids");
  }
  async function openTeam(team = null, focusMembers = false) {
    editing = team;
    memberSelection = new Set();
    memberPage = 1;
    memberRequest++;
    form.reset();
    if (previewImageUrl) URL.revokeObjectURL(previewImageUrl);
    previewImageUrl = null;
    const removeImageLabel = $("[data-remove-image-label]");
    removeImageLabel.classList.toggle("hidden", !team?.image_path);
    removeImageLabel.classList.toggle("flex", !!team?.image_path);
    updateImagePreview(team?.image_path || "");
    clearFormErrors();
    form.dataset.id = team?.id || "";
    $("[data-team-mode]").textContent = team ? "Edit Team" : "New Team";
    dialog.querySelector("header h2").textContent = team
      ? "Edit Tribe"
      : "Create a Tribe";
    $("[data-save-team]").textContent = team ? "Save Changes" : "Create Tribe";
    $("[data-details-step]").classList.toggle("hidden", !team);
    $("[data-details-step]").classList.toggle("grid", !!team);
    $("[data-team-help]").textContent = team
      ? "A student may belong to one tribe per school year, but can join a different tribe in another year."
      : "Student assignments are handled separately with Randomize Students after the tribes are created.";
    const sy = form.elements.school_year_id;
    sy.replaceChildren(new Option("Select school year", ""));
    data.school_years.forEach((y) => sy.add(new Option(schoolYearLabel(y.label), y.id)));
    form.elements.name.value = team?.name || "";
    sy.value = team?.school_year_id || data.school_years[0]?.id || "";
    applyColor(team?.color || "#397565");
    $("[data-members-section]").classList.toggle("hidden", !team);
    $("[data-preview-member-row]").classList.toggle("hidden", !team);
    $("[data-preview-member-row]").classList.toggle("flex", !!team);
    $("[data-current-year-note]").classList.toggle(
      "hidden",
      data.school_years.length !== 1,
    );
    $("[data-member-list]").replaceChildren();
    $("[data-member-pagination]").classList.add("hidden");
    $("[data-member-search]").value = "";
    $("[data-member-result-count]").textContent = team
      ? "Loading tribe members…"
      : "Members can be assigned after the tribe is created.";
    updateMemberCount();
    updatePreview();
    dialog.showModal();
    if (team) {
      try {
        const response = await axios.get("api/teams.php", {
          params: {
            action: "team_editor",
            id: team.id,
            school_year_id: form.elements.school_year_id.value,
            page: 1,
          },
        });
        if (editing?.id !== team.id) return;
        memberSelection = new Set(response.data.data.member_ids.map(Number));
        renderMembers(response.data.data);
      } catch (error) {
        toast("error", error.response?.data?.message || "Unable to load tribe members.");
      }
    }
    if (focusMembers)
      requestAnimationFrame(() =>
        $("[data-members-section]")?.scrollIntoView({ block: "start" }),
      );
  }
  function updateImagePreview(path = "") {
    const preview = $("[data-preview-image]");
    preview.src = path || "";
    preview.classList.toggle("hidden", !path);
  }
  function applyColor(color) {
    const normalized = color.toUpperCase();
    form.elements.color.value = normalized;
    $("[data-preview-accent]").style.background = color;
    $("[data-preview-badge]").style.background = color;
    const match = [...form.querySelectorAll("[data-color-option]")].find(
      (option) => option.value.toUpperCase() === normalized,
    );
    if (match) match.checked = true;
    else {
      const customOption = form.querySelector("[data-custom-option]"),
        customColor = form.querySelector("[data-custom-color]");
      customOption.checked = true;
      customColor.value = normalized;
    }
    clearFieldError("color");
    updatePreview();
  }
  function updatePreview() {
    const name = form.elements.name.value.trim(),
      year = form.elements.school_year_id;
    $("[data-preview-name]").textContent = name || "Your tribe";
    $("[data-preview-badge]").textContent = initials(name);
    $("[data-preview-year]").textContent = year.selectedOptions[0]?.value
      ? `School Year ${year.selectedOptions[0].textContent.replace(/^SY /, "")}`
      : "Choose a school year";
    $("[data-save-team]").disabled =
      !name || !year.value || !form.elements.color.value;
  }
  function clearFieldError(field) {
    const error = form.querySelector(`[data-error-for="${field}"]`);
    if (error) {
      error.textContent = "";
      error.classList.add("hidden");
    }
    const control = form.elements[field];
    if (control instanceof HTMLElement) {
      control.classList.remove("border-rose-400", "bg-rose-50/40");
      control.removeAttribute("aria-invalid");
    }
  }
  function clearFormErrors() {
    form.querySelectorAll("[data-error-for]").forEach((error) => {
      error.textContent = "";
      error.classList.add("hidden");
    });
    form.querySelectorAll('[aria-invalid="true"]').forEach((control) => {
      control.classList.remove("border-rose-400", "bg-rose-50/40");
      control.removeAttribute("aria-invalid");
    });
    const general = $("[data-form-error]");
    general.textContent = "";
    general.classList.add("hidden");
  }
  function showFieldError(field, message) {
    const error = form.querySelector(`[data-error-for="${field}"]`);
    if (!error) return false;
    error.textContent = Array.isArray(message) ? message[0] : message;
    error.classList.remove("hidden");
    const control = form.elements[field];
    if (control && control instanceof HTMLElement) {
      control.classList.add("border-rose-400", "bg-rose-50/40");
      control.setAttribute("aria-invalid", "true");
    }
    return true;
  }
  function showErrors(errors, message) {
    let shown = false,
      first = null;
    Object.entries(errors || {}).forEach(([field, error]) => {
      if (showFieldError(field, error)) {
        shown = true;
        first ??= form.elements[field];
      }
    });
    if (!shown) {
      const general = $("[data-form-error]");
      general.textContent = message || "Please check the form and try again.";
      general.classList.remove("hidden");
      first = general;
    }
    first?.scrollIntoView?.({ behavior: "smooth", block: "center" });
    first?.focus?.();
  }
  function clientErrors() {
    const errors = {},
      name = form.elements.name.value.trim();
    if (!name) errors.name = ["The tribe name is required."];
    else if (name.length > 100)
      errors.name = ["The tribe name may not exceed 100 characters."];
    if (!form.elements.school_year_id.value)
      errors.school_year_id = ["Please select a school year."];
    if (!/^#[0-9A-F]{6}$/i.test(form.elements.color.value))
      errors.color = ["Please choose a valid tribe color."];
    return errors;
  }
  async function toggle(t) {
    if (
      t.is_active &&
      !(await confirmAction({
        title: "Deactivate tribe?",
        message: `${t.name} will be marked inactive. Its members and scores will be preserved.`,
        action: "Deactivate",
      }))
    )
      return;
    try {
      const response = await api({ action: "toggle", id: t.id, active: !t.is_active });
      toast("success", response.data.message || (t.is_active ? "Tribe deactivated." : "Tribe activated."));
      await load().catch(() => toast("warning", "Saved, but the tribe list could not refresh. Reload the page."));
    } catch (e) {
      toast("error", e.response?.data?.message || "Unable to update tribe.");
    }
  }
  async function load() {
    const params = Object.fromEntries(new FormData(filters));
    if (!params.school_year && requestedSchoolYear)
      params.school_year = requestedSchoolYear;
    params.page = page;
    const r = await axios.get("api/teams.php", { params });
    data = r.data.data;
    page = data.pagination.current_page;
    fillOptions();
    renderSummary();
    renderTeams();
  }
  const palette = [
    ["Green", "#397565"],
    ["Cobalt", "#2F3AE0"],
    ["Lime", "#C6F24E"],
    ["Tangerine", "#FF6B2C"],
    ["Ink", "#121017"],
  ];
  palette.forEach(([name, color], index) => {
    const l = document.createElement("label");
    l.className = "cursor-pointer text-center";
    l.innerHTML = `<input class="peer sr-only" type="radio" name="color_palette" value="${color}" ${index === 0 ? "checked" : ""} data-color-option><span class="grid h-11 w-11 place-items-center rounded-full border-4 border-white shadow-sm ring-1 ring-slate-200 transition peer-checked:scale-105 peer-checked:ring-2 peer-checked:ring-[#121017] peer-checked:[&>svg]:opacity-100"><svg class="h-4 w-4 stroke-white opacity-0 transition" viewBox="0 0 24 24" fill="none"><path d="m5 12 4 4L19 6"/></svg></span><span class="mt-1.5 block text-[10px] font-bold text-slate-500">${name}</span>`;
    l.querySelector("span").style.background = color;
    l.querySelector("input").onchange = () => applyColor(color);
    $("[data-palette]").append(l);
  });
  const custom = document.createElement("label");
  custom.className = "cursor-pointer text-center";
  custom.innerHTML =
    '<input class="peer sr-only" type="radio" name="color_palette" value="custom" data-custom-option><span class="relative grid h-11 w-11 place-items-center overflow-hidden rounded-full border-4 border-white shadow-sm ring-1 ring-slate-200 transition peer-checked:scale-105 peer-checked:ring-2 peer-checked:ring-[#121017]"><input class="absolute inset-[-8px] h-16 w-16 cursor-pointer border-0 p-0" type="color" value="#397565" aria-label="Custom tribe color" data-custom-color></span><span class="mt-1.5 block text-[10px] font-bold text-slate-500">Custom</span>';
  $("[data-palette]").append(custom);
  custom.querySelector("[data-custom-option]").onchange = () =>
    applyColor(custom.querySelector("[data-custom-color]").value);
  custom.querySelector("[data-custom-color]").oninput = (e) => {
    custom.querySelector("[data-custom-option]").checked = true;
    applyColor(e.target.value);
  };
  $("[data-dialog-open]").onclick = () => openTeam();
  dialog
    .querySelectorAll("[data-dialog-close]")
    .forEach((b) => (b.onclick = () => dialog.close()));
  $("[data-unassigned-summary]").onclick = openUnassignedReview;
  unassignedDialog
    .querySelectorAll("[data-unassigned-close]")
    .forEach((button) => (button.onclick = () => unassignedDialog.close()));
  $("[data-unassigned-search]").oninput = () => {
    clearTimeout(unassignedSearchTimer);
    unassignedSearchTimer = setTimeout(() => loadUnassignedStudents(1), 300);
  };
  $("[data-unassigned-year]").onchange = async (event) => {
    unassignedPage = 1;
    unassignedSelection.clear();
    await loadUnassignedStudents(1);
  };
  $("[data-unassigned-select-all]").onchange = (event) => {
    $("[data-unassigned-list]")
      .querySelectorAll("[data-unassigned-row]:not(.hidden) [data-unassigned-check]")
      .forEach((check) => {
        check.checked = event.target.checked;
        event.target.checked
          ? unassignedSelection.add(Number(check.value))
          : unassignedSelection.delete(Number(check.value));
      });
    updateUnassignedSelection();
  };
  $("[data-unassigned-bulk-team]").onchange = () => {
    updateUnassignedSelection();
  };
  $("[data-assign-selected]").onclick = () =>
    assignUnassigned(
      [...unassignedSelection],
      $("[data-unassigned-bulk-team]").value,
    );
  $("[data-randomize-unassigned]").onclick = async () => {
    const selected = [...unassignedSelection];
    const available = Number(unassignedData.pagination.total || 0);
    if (!available) return;
    const count = selected.length || available;
    if (
      !(await confirmAction({
        title: selected.length
          ? `Randomize ${count} selected students?`
          : `Randomize all ${count} unassigned students?`,
        message:
          "They will be distributed as evenly as possible among active tribes. Existing tribe assignments will not be changed.",
        action: "Randomize students",
      }))
    )
      return;
    try {
      const response = await api({
        action: "randomize_unassigned",
        school_year_id: selectedReviewYear(),
        student_ids: selected,
      });
      toast("success", response.data.message);
      unassignedSelection.clear();
      await Promise.all([load(), loadUnassignedStudents(unassignedPage)]);
    } catch (error) {
      toast("error", error.response?.data?.message || "Unable to randomize unassigned students.");
    }
  };
  form.elements.name.oninput = () => {
    clearFieldError("name");
    updatePreview();
  };
  form.elements.image.onchange = () => {
    if (previewImageUrl) URL.revokeObjectURL(previewImageUrl);
    previewImageUrl = null;
    const file = form.elements.image.files[0];
    if (file) {
      previewImageUrl = URL.createObjectURL(file);
      form.elements.remove_image.checked = false;
    }
    updateImagePreview(previewImageUrl || (form.elements.remove_image.checked ? "" : editing?.image_path || ""));
    clearFieldError("image");
  };
  form.elements.remove_image.onchange = () => {
    if (form.elements.remove_image.checked) {
      form.elements.image.value = "";
      if (previewImageUrl) URL.revokeObjectURL(previewImageUrl);
      previewImageUrl = null;
    }
    updateImagePreview(form.elements.remove_image.checked ? "" : editing?.image_path || "");
  };
  form.elements.name.onblur = () => {
    const name = form.elements.name.value.trim();
    if (!name) showFieldError("name", "The tribe name is required.");
    else if (name.length > 100)
      showFieldError("name", "The tribe name may not exceed 100 characters.");
  };
  form.elements.school_year_id.onchange = () => {
    clearFieldError("school_year_id");
    updatePreview();
    if (editing) loadMemberCandidates(1);
  };
  $("[data-member-search]").oninput = () => {
    clearTimeout(memberSearchTimer);
    memberSearchTimer = setTimeout(() => loadMemberCandidates(1), 300);
  };
  $("[data-member-pagination]").onclick = (event) => {
    const control = event.target.closest("[data-member-page]");
    if (!control || control.disabled) return;
    loadMemberCandidates(Number(control.dataset.memberPage));
  };
  $("[data-unassigned-pagination]").onclick = (event) => {
    const control = event.target.closest("[data-unassigned-page]");
    if (!control || control.disabled) return;
    loadUnassignedStudents(Number(control.dataset.unassignedPage));
  };
  form.onsubmit = async (e) => {
    e.preventDefault();
    const submit = e.submitter || form.querySelector('button[type="submit"]');
    clearFormErrors();
    const validation = clientErrors();
    if (Object.keys(validation).length) {
      showErrors(validation, "Please check the highlighted fields.");
      return;
    }
    if (!form.reportValidity()) return;
    const image = form.elements.image.files[0];
    if (image && (image.size > 5 * 1024 * 1024 || !["image/jpeg", "image/png", "image/webp"].includes(image.type))) {
      showErrors({image: ["Use a JPG, PNG, or WebP image under 5 MB."]}, "Please check the tribe image.");
      return;
    }
    const payload = new FormData(form);
    payload.set("member_ids", JSON.stringify([...memberSelection]));
    payload.set("action", editing ? "update" : "create");
    if (editing) payload.set("id", editing.id);
    if (submit) submit.disabled = true;
    try {
      const r = await axios.post("api/teams.php", payload, {headers: {"X-CSRF-Token": csrf}});
      dialog.close();
      toast("success", r.data.message);
      page = 1;
      await load();
    } catch (err) {
      const response = err.response?.data;
      showErrors(
        response?.errors,
        response?.message || "Unable to save tribe.",
      );
      if (!response?.errors || !Object.keys(response.errors).length)
        toast("error", response?.message || "Unable to save tribe.");
    } finally {
      if (submit) submit.disabled = false;
    }
  };
  filters.onchange = async () => {
    page = 1;
    await load();
    syncUrl();
  };
  filters.search.oninput = () => {
    clearTimeout(timer);
    timer = setTimeout(async () => {
      page = 1;
      await load();
      syncUrl();
    }, 320);
  };
  $("[data-clear-filters]").onclick = async (e) => {
    e.preventDefault();
    filters.reset();
    page = 1;
    await load();
    syncUrl();
  };
  $("[data-randomize-year]").onchange = updateRandomize;
  $("[data-randomize-form]").onsubmit = async (e) => {
    e.preventDefault();
    const submit = e.submitter || e.target.querySelector('button[type="submit"]');
    if (
      !(await confirmAction({
        title: "Randomize tribe members?",
        message:
          "All active students will be shuffled and evenly redistributed among the active tribes in this school year. This can only be done once.",
        action: "Randomize students",
      }))
    )
      return;
    if (submit) submit.disabled = true;
    try {
      const r = await api({
        action: "randomize",
        school_year_id: e.target.school_year_id.value,
      });
      toast("success", r.data.message);
      await load();
    } catch (err) {
      toast(
        "error",
        err.response?.data?.message || "Unable to randomize students.",
      );
    } finally {
      if (submit) submit.disabled = false;
    }
  };
  document.addEventListener("click", (event) => {
    if (
      !event.target.closest("[data-team-actions-menu]") &&
      !event.target.closest("[data-actions]")
    )
      closeTeamMenu();
  });
  addEventListener("resize", closeTeamMenu);
  addEventListener("scroll", closeTeamMenu, true);
  load().catch((error) => {
    console.error("Unable to load teams.", error);
    toast("error", error.response?.data?.message || "Unable to load teams.");
  });
});
