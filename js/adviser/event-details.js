window.SharedNavigation.ready.then((context) => {
  "use strict";
  const id = Number(new URLSearchParams(location.search).get("id")),
    toast = (type, message) =>
      window.Notifications?.[type]?.(message) ||
      undefined;
  let csrf = context.csrfToken,
    data;
  const activityFilterState = { search: "", schedule: "", status: "" };
  const $ = (selector) => document.querySelector(selector),
    format = (value, options) =>
      new Intl.DateTimeFormat("en-US", options).format(
        new Date(value.replace(" ", "T")),
      ),
    clock = (value) =>
      format(`2000-01-01 ${value}`, { hour: "numeric", minute: "2-digit" }),
    post = (payload) =>
      axios.post("api/adviser-events.php", payload, {
        headers: { "X-CSRF-Token": csrf },
      });
  const tabButtons = document.querySelectorAll("[data-event-tab]"),
    tabPanels = document.querySelectorAll("[data-event-tab-panel]");
  function activateTab(name, updateUrl = false) {
    const tab = ["activities", "attendance", "scores"].includes(name) ? name : "details";
    tabButtons.forEach((button) => {
      const active = button.dataset.eventTab === tab;
      button.setAttribute("aria-selected", String(active));
      button.tabIndex = active ? 0 : -1;
      button.className = active
        ? "inline-flex min-h-10 flex-1 items-center justify-center rounded-lg bg-[#397565] px-4 text-sm font-bold text-white shadow-sm"
        : "inline-flex min-h-10 flex-1 items-center justify-center rounded-lg px-4 text-sm font-bold text-slate-500 transition hover:text-[#397565]";
    });
    tabPanels.forEach((panel) => {
      panel.classList.toggle("hidden", panel.dataset.eventTabPanel !== tab);
    });
    if (updateUrl) {
      const url = new URL(location.href);
      url.hash = tab === "activities" ? "event-activities" : tab === "attendance" ? "event-attendance" : tab === "scores" ? "event-scores" : "";
      history.pushState(null, "", url);
    }
  }
  tabButtons.forEach((button) => {
    button.addEventListener("click", () => activateTab(button.dataset.eventTab, true));
  });
  window.addEventListener("hashchange", () =>
    activateTab(location.hash === "#event-activities" ? "activities" : location.hash === "#event-attendance" ? "attendance" : location.hash === "#event-scores" ? "scores" : "details"),
  );
  window.addEventListener("popstate", () =>
    activateTab(location.hash === "#event-activities" ? "activities" : location.hash === "#event-attendance" ? "attendance" : location.hash === "#event-scores" ? "scores" : "details"),
  );
  activateTab(location.hash === "#event-activities" ? "activities" : location.hash === "#event-attendance" ? "attendance" : location.hash === "#event-scores" ? "scores" : "details");
  const statusClass = (label) =>
    ({
      upcoming: "bg-[#2F3AE0]/8 text-[#2F3AE0]",
      ongoing: "bg-[#C6F24E]/35 text-[#397565]",
      completed: "bg-[#121017]/6 text-[#121017]/55",
      archived: "bg-[#FF6B2C]/10 text-[#FF6B2C]",
    })[label] || "bg-slate-100 text-slate-500";
  document
    .querySelectorAll(
      "[data-feature-form],[data-assign-form]",
    )
    .forEach((form) => (form.dataset.axiosForm = ""));
  function row(label, content) {
    return `<div class="grid grid-cols-[90px_1fr] gap-3 border-b border-slate-100 py-5"><dt class="text-[10px] font-extrabold uppercase tracking-wider text-slate-400">${label}</dt><dd class="text-xs font-bold text-slate-700">${content}</dd></div>`;
  }
  function render() {
    const event = data.event,
      metadata = data.metadata;
    document.title = `${event.title} | CITE Events`;
    $("[data-event-title]").textContent = event.title;
    $("[data-event-description]").textContent =
      event.description || "No event description has been added.";
    $("[data-status-badge]").innerHTML =
      `<i class="h-1.5 w-1.5 rounded-full bg-current"></i>${EventForm.escapeHtml(event.status_label[0].toUpperCase() + event.status_label.slice(1))}`;
    $("[data-status-badge]").className =
      `inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-[10px] font-bold ${statusClass(event.status_label)}`;
    $("[data-type-badge]").textContent = event.type_label || "Event";
    if (event.poster_path) {
      const poster = $("[data-event-poster]");
      poster.style.backgroundImage = `linear-gradient(rgba(20,30,70,.15),rgba(20,30,70,.42)),url('${event.poster_path}')`;
      poster.replaceChildren();
    }
    $("[data-edit-link]").href = `pages/adviser/event-edit.html?id=${event.id}`;
    const scoreLink = $("[data-event-scores]");
    if (scoreLink)
      scoreLink.href = `pages/adviser/score-configuration.html?event_id=${event.id}`;
    if ($("[data-attendance-roster-link]"))
      $("[data-attendance-roster-link]").href = `pages/adviser/attendance-roster.html?event_id=${event.id}`;
    if ($("[data-scores-workspace-link]"))
      $("[data-scores-workspace-link]").href = `pages/adviser/score-configuration.html?event_id=${event.id}`;
    const date = (value) =>
        format(value, { month: "short", day: "numeric", year: "numeric" }),
      time = (value) => format(value, { hour: "numeric", minute: "2-digit" }),
      audienceLabel =
        {
          selected_tribes: "Selected tribes",
          selected_year_levels: "Selected year levels",
          specific_students: "Specific students",
          all_students: "All active students",
        }[event.audience_type] || "All active students";
    const eventLocations = event.event_locations?.length
      ? event.event_locations
      : [{ name: event.location, is_primary: true }];
    const locationList = eventLocations
      .map((location) => `${EventForm.escapeHtml(location.name)}${location.is_primary ? ' <small class="font-bold text-[#397565]">(Primary)</small>' : ""}`)
      .join('<span class="text-slate-300"> · </span>');
    const heroIcon = (path) =>
      `<svg aria-hidden="true" viewBox="0 0 24 24"><path d="${path}" /></svg>`;
    $("[data-event-hero-meta]").innerHTML = [
      [
        heroIcon("M7 2v3m10-3v3M4 9h16M5 4h14a1 1 0 0 1 1 1v14H4V5a1 1 0 0 1 1-1Z"),
        "Date",
        `${date(event.start_at)} – ${date(event.end_at)}`,
      ],
      [
        heroIcon("M12 6v6l4 2m5-2a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"),
        "Time",
        `${time(event.start_at)} – ${time(event.end_at)}`,
      ],
      [
        heroIcon("M12 21s7-4.4 7-11a7 7 0 1 0-14 0c0 6.6 7 11 7 11Zm0-8.5h.01"),
        "Location",
        EventForm.escapeHtml(event.location || "Location to be announced"),
      ],
      [
        heroIcon("M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2m18-6a4 4 0 0 0-3-3.9M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8Z"),
        "Attendees",
        `${event.expected_participants} expected · ${EventForm.escapeHtml(audienceLabel)}`,
      ],
    ]
      .map(
        ([icon, label, value]) =>
          `<div class="event-detail-hero__meta-item">${icon}<span><b>${label}</b><small>${value}</small></span></div>`,
      )
      .join("");
    const schedules = event.attendance_schedules
      .map((day, index) => {
        const slots = [];
        if (day.mode_code === "whole_day") {
          slots.push(
            ["Time in", day.whole_day_in_time],
            ["Time out", day.whole_day_out_time],
          );
        }
        if (day.mode_code === "two_sessions") {
          slots.push(
            ["Morning in", day.morning_in_time],
            ["Morning out", day.morning_out_time],
            ["Afternoon in", day.afternoon_in_time],
            ["Afternoon out", day.afternoon_out_time],
          );
        }
        return `<section class="rounded-lg border bg-[#397565]/7 px-3 py-2.5"><strong class="text-[10px] font-black text-[#397565]">Day ${index + 1} · ${format(day.schedule_date + " 12:00:00", { weekday: "short", month: "short", day: "numeric" })}</strong>${slots.length ? `<div class="mt-2 grid grid-cols-2 gap-1 text-[9px]">${slots.map((slot) => `<span>${slot[0]}: <b>${clock(slot[1])}</b></span>`).join("")}</div>` : '<p class="mt-1 text-[9px] text-slate-400">Attendance scans not configured</p>'}</section>`;
      })
      .join("");
    $("[data-event-details]").innerHTML =
      row(
        "Starts",
        `${format(event.start_at, { weekday: "long", month: "long", day: "numeric", year: "numeric" })}<span class="block font-medium text-slate-400">${time(event.start_at)}</span>`,
      ) +
      row(
        "Ends",
        `${format(event.end_at, { weekday: "long", month: "long", day: "numeric", year: "numeric" })}<span class="block font-medium text-slate-400">${time(event.end_at)}</span>`,
      ) +
      row(
        "Period",
        `${EventForm.escapeHtml(event.school_year_label || "Academic year")}<span class="block font-medium text-slate-400">${EventForm.escapeHtml(event.academic_term_name || "Term")}</span>`,
      ) +
      row("Daily scans", `<div class="grid gap-2">${schedules}</div>`) +
      row("Locations", locationList) +
      row(
        "Participants",
        `${event.expected_participants} expected<span class="block font-medium text-slate-400">${audienceLabel}</span>`,
      ) +
      row(
        "Created by",
        `${EventForm.escapeHtml(event.creator_name)}<span class="block font-medium text-slate-400">${date(event.created_at)}</span>`,
      );
    renderAssigned();
    renderAttendance();
    renderScores();
    const feature = $("[data-feature-form]"),
      canFeature =
        ["upcoming", "ongoing"].includes(event.status_label) &&
        new Date(event.end_at.replace(" ", "T")) > new Date();
    $("[data-feature-available]").classList.toggle("hidden", !canFeature);
    $("[data-feature-unavailable]").classList.toggle("hidden", canFeature);
    $("[data-featured-badge]").classList.toggle("hidden", !event.is_featured);
    $("[data-remove-feature]").classList.toggle("hidden", !event.is_featured);
    $("[data-feature-submit]").textContent = event.is_featured
      ? "Update feature"
      : "Feature event";
    feature.elements.featured_order.value = event.featured_order || "";
    feature.elements.featured_until.value = (event.featured_until || "")
      .slice(0, 16)
      .replace(" ", "T");
    feature.elements.featured_until.max = event.end_at
      .slice(0, 16)
      .replace(" ", "T");
  }
  function renderAttendance() {
    const overview = data.event.attendance_overview || { expected: 0, recorded: 0, present: 0, absent: 0, unrecorded: 0, days: [] };
    const number = (value) => Number(value || 0).toLocaleString("en-PH");
    $("[data-attendance-summary]").innerHTML = [
      ["Expected records", overview.expected, "bg-slate-50 text-slate-700"], ["Recorded", overview.recorded, "bg-blue-50 text-[#2F3AE0]"],
      ["Present / late", overview.present, "bg-emerald-50 text-[#397565]"], ["Not recorded", overview.unrecorded, "bg-amber-50 text-amber-700"],
    ].map(([label, value, classes]) => `<article class="rounded-2xl p-4 ${classes}"><p class="text-[10px] font-extrabold uppercase tracking-wider">${label}</p><strong class="mt-2 block text-2xl font-black">${number(value)}</strong></article>`).join("");
    const days = overview.days || [];
    $("[data-attendance-days]").innerHTML = days.length ? days.map((day) => `<div class="grid gap-2 px-5 py-4 text-xs sm:grid-cols-[150px_repeat(4,1fr)] sm:px-6"><strong>${format(`${day.date} 12:00:00`, { weekday: "short", month: "short", day: "numeric", year: "numeric" })}</strong><span><b>${number(day.recorded)}</b> recorded</span><span class="text-[#397565]"><b>${number(day.present)}</b> present</span><span class="text-red-600"><b>${number(day.absent)}</b> absent</span><span class="text-slate-500"><b>${number(day.unrecorded)}</b> awaiting</span></div>`).join("") : '<p class="px-5 py-8 text-center text-xs text-slate-500">No attendance schedule has been configured for this event.</p>';
    const assignments = data.event.attendance_assignments || [];
    $("[data-attendance-officers]").innerHTML = assignments.length ? assignments.map((assignment) => `<tr><td class="px-5 py-3 sm:px-6"><strong>${EventForm.escapeHtml(assignment.officer_name)}</strong><small class="mt-0.5 block text-slate-400">${EventForm.escapeHtml(assignment.username || "SBO Officer")}</small></td><td class="px-4 py-3">${format(`${assignment.schedule_date} 12:00:00`, { month: "short", day: "numeric", year: "numeric" })}<small class="mt-0.5 block text-slate-400">${EventForm.escapeHtml(assignment.session_code.replace("_", " "))}</small></td><td class="px-4 py-3">${EventForm.escapeHtml(assignment.scanner_mode === "general" ? "All eligible attendees" : assignment.scanner_team_name || assignment.team_name || "Assigned team")}</td><td class="px-4 py-3">${EventForm.escapeHtml(assignment.activity_name || "Attendance")}</td><td class="px-5 py-3 sm:px-6"><span class="rounded-full px-2 py-1 text-[10px] font-bold ${assignment.status === "active" ? "bg-emerald-50 text-[#397565]" : "bg-slate-100 text-slate-500"}">${EventForm.escapeHtml(assignment.status)}</span></td></tr>`).join("") : '<tr><td class="px-5 py-8 text-center text-slate-500 sm:px-6" colspan="5">No SBO officers are assigned to attendance for this event yet.</td></tr>';
  }
  function renderScores() {
    const overview = data.event.score_overview || {};
    const number = (value) => Number(value || 0).toLocaleString("en-PH");
    $("[data-score-summary]").innerHTML = [
      ["Competitions", overview.activities_count, "bg-slate-50 text-slate-700"],
      ["Eligible teams", overview.eligible_teams_count, "bg-blue-50 text-[#2F3AE0]"],
      ["Draft raw scores", overview.raw_score_count, "bg-amber-50 text-amber-700"],
      ["Finalized results", overview.finalized_activities_count, "bg-emerald-50 text-[#397565]"],
    ].map(([label, value, classes]) => `<article class="rounded-2xl p-4 ${classes}"><p class="text-[10px] font-extrabold uppercase tracking-wider">${label}</p><strong class="mt-2 block text-2xl font-black">${number(value)}</strong></article>`).join("");
    const activities = overview.activities || [];
    const activityHost = $("[data-score-activities]");
    activityHost.innerHTML = activities.length ? activities.map((activity) => {
      const finalized = activity.result_count > 0;
      const scoreProgress = overview.eligible_teams_count ? Math.min(100, activity.scored_teams_count / overview.eligible_teams_count * 100) : 0;
      const status = finalized ? "Finalized" : activity.status === "ongoing" ? "Ongoing" : activity.status === "upcoming" ? "Upcoming" : activity.status === "inactive" ? "Inactive" : activity.raw_score_count ? "Draft scoring" : "Ready to score";
      const tone = finalized ? "bg-emerald-50 text-[#397565]" : activity.status === "ongoing" ? "bg-amber-50 text-amber-700" : activity.status === "upcoming" ? "bg-sky-50 text-sky-700" : activity.status === "inactive" ? "bg-slate-100 text-slate-500" : "bg-[#C6F24E]/35 text-[#397565]";
      const detail = finalized ? `${number(activity.result_count)} team results finalized and published.` : activity.raw_score_count ? `${number(activity.scored_teams_count)} of ${number(overview.eligible_teams_count)} teams have raw scores.` : activity.placement_rules_count ? "Points are set. Start entering raw scores." : "Set points for each placement before entering scores.";
      const schedule = activity.schedule_date ? format(`${activity.schedule_date} 12:00:00`, { weekday: "short", month: "short", day: "numeric", year: "numeric" }) : "Schedule not set";
      return `<article class="px-5 py-5 sm:px-6"><div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between"><div class="min-w-0 flex-1"><div class="flex flex-wrap items-center gap-2"><h3 class="text-sm font-bold">${EventForm.escapeHtml(activity.name)}</h3><span class="rounded-full px-2 py-0.5 text-[10px] font-bold ${tone}">${status}</span></div><p class="mt-1 text-xs text-slate-500">${schedule} · ${detail}</p>${!finalized && overview.eligible_teams_count ? `<div class="mt-3 h-1.5 max-w-md overflow-hidden rounded-full bg-slate-100"><i class="block h-full rounded-full bg-[#397565]" style="width:${scoreProgress}%"></i></div>` : ""}</div><div class="flex shrink-0 flex-wrap gap-2">${finalized ? `<a class="inline-flex min-h-10 items-center justify-center rounded-lg border border-[#397565]/25 px-3 text-xs font-bold text-[#397565] hover:bg-emerald-50" href="pages/adviser/score-configuration.html?event_id=${id}&activity_id=${activity.id}">View results</a>` : `<button class="min-h-10 rounded-lg border border-[#397565]/25 px-3 text-xs font-bold text-[#397565] hover:bg-emerald-50" type="button" data-set-points="${activity.id}">Set points</button><a class="inline-flex min-h-10 items-center justify-center rounded-lg bg-[#397565] px-3 text-xs font-bold text-white" href="pages/adviser/score-configuration.html?event_id=${id}&activity_id=${activity.id}">Open scoring</a>`}</div></div></article>`;
    }).join("") : '<p class="px-5 py-8 text-center text-xs text-slate-500">No activities have been added to this event yet.</p>';
    activityHost.onclick = (event) => {
      const button = event.target.closest("[data-set-points]");
      if (!button) return;
      const activity = activities.find((item) => item.id === Number(button.dataset.setPoints));
      if (activity) openPointsDialog(activity);
    };
  }
  const pointsRow = (placement = "", points = "") => `<div class="grid gap-3" style="grid-template-columns:minmax(0,1fr) minmax(0,1fr) 88px" data-points-row><input class="h-10 rounded-lg border border-slate-300 px-3 text-sm font-bold" type="number" min="1" max="255" step="1" inputmode="numeric" data-points-placement value="${placement}" placeholder="Place" required><input class="h-10 rounded-lg border border-slate-300 px-3 text-sm font-bold" type="number" min="0" max="255" step="1" inputmode="numeric" data-points-value value="${points}" placeholder="Points" required><button class="min-h-10 rounded-lg border border-red-200 text-xs font-bold text-red-600" type="button" data-points-remove>Remove</button></div>`;
  function openPointsDialog(activity) {
    const dialog = $("[data-points-dialog]"), form = $("[data-points-form]");
    form.reset();
    form.elements.activity_id.value = activity.id;
    $("[data-points-activity-name]").textContent = activity.name;
    $("[data-points-rule-list]").innerHTML = activity.placement_rules?.length ? activity.placement_rules.map((rule) => pointsRow(rule.placement, rule.points)).join("") : pointsRow(1, "");
    $("[data-points-error]").classList.add("hidden");
    dialog.showModal();
  }
  function renderAssigned() {
    const event = data.event,
      host = $("[data-assigned-users]");
    host.replaceChildren();
    if (!event.assigned_users.length)
      host.innerHTML =
        '<div class="py-9 text-center"><strong class="text-sm text-slate-600">No one assigned yet</strong><p class="mt-1 text-xs text-slate-400">Assign an active SBO or Faculty member below.</p></div>';
    event.assigned_users.forEach((user) => {
      const item = document.createElement("div");
      item.className = "flex min-h-16 items-center gap-3 border-b py-3";
      item.innerHTML = `<span class="grid h-9 w-9 place-items-center rounded-full bg-emerald-100 text-xs font-extrabold text-emerald-800">${EventForm.initials(user.full_name)}</span><span class="grid flex-1"><strong class="text-xs">${EventForm.escapeHtml(user.full_name)}</strong><small class="text-[10px] text-slate-400">${EventForm.escapeHtml(user.role)}</small></span><button class="grid h-8 w-8 place-items-center rounded-lg bg-slate-100 text-lg text-slate-500">×</button>`;
      item.querySelector("button").onclick = async () => {
        try {
          await post({ action: "unassign", id: event.id, user_id: user.id });
          toast("success", "Assignment removed successfully.");
          await load();
        } catch (error) {
          toast(
            "error",
            error.response?.data?.message || "Unable to remove assignment.",
          );
        }
      };
      host.append(item);
    });
    const select = $("[data-assign-form] select"),
      current = select.value;
    select.replaceChildren(new Option("Select SBO or Faculty", ""));
    data.metadata.available_users.forEach((user) =>
      select.add(
        new Option(
          `${user.full_name} · ${user.role}`,
          user.id,
          false,
          String(user.id) === current,
        ),
      ),
    );
    $("[data-assign-form]").classList.toggle(
      "hidden",
      event.status_label === "archived" ||
        !data.metadata.available_users.length,
    );
  }
  function resetActivityForm() {
    const form = $("[data-activity-form]");
    form.reset();
    form.elements.activity_id.value = "";
    populateActivityOptions();
    populateActivitySchedules();
    $("[data-activity-form-title]").textContent = "Add activity";
    $("[data-activity-save]").textContent = "Add activity";
    $("[data-activity-error]").classList.add("hidden");
  }
  function populateActivityOptions(selectedId = "") {
    const select = activityForm.elements.catalog_activity_id;
    const options = data.event.activity_options || [];
    select.replaceChildren(new Option(options.length ? "Select an activity" : "No activities available for this event type", ""));
    options.forEach((activity) =>
      select.add(new Option(activity.label, activity.id, false, String(activity.id) === String(selectedId))),
    );
    select.disabled = !options.length;
    $("[data-activity-options-note]").textContent = options.length
      ? `Showing activities for ${data.event.type_label || "this event type"}.`
      : "Create an activity in the Activity Catalog for this event type first.";
  }
  function populateActivitySchedules(selectedId = "") {
    const select = activityForm.elements.event_schedule_id;
    const schedules = data.event.attendance_schedules || [];
    select.replaceChildren(new Option(schedules.length ? "Select an event day" : "No event days have been scheduled", ""));
    schedules.forEach((schedule) => {
      const date = new Date(`${schedule.schedule_date}T00:00:00`);
      const label = Number.isNaN(date.getTime()) ? schedule.schedule_date : date.toLocaleDateString("en-PH", { weekday: "long", month: "long", day: "numeric", year: "numeric" });
      select.add(new Option(label, schedule.id, false, String(schedule.id) === String(selectedId)));
    });
    select.disabled = !schedules.length;
    $("[data-activity-schedule-note]").textContent = schedules.length
      ? "Choose one of the days configured for this event."
      : "Add an event day before scheduling a competition.";
  }
  function openActivityDialog(activity = null) {
    resetActivityForm();
    if (activity) {
      activityForm.elements.activity_id.value = activity.id;
      populateActivityOptions(activity.catalog_activity_id);
      populateActivitySchedules(activity.event_schedule_id);
      activityForm.elements.name.value = activity.name;
      $("[data-activity-form-title]").textContent = "Edit activity";
      $("[data-activity-save]").textContent = "Save changes";
    }
    $("[data-activity-dialog]").showModal();
    activityForm.elements.name.focus();
  }
  function renderActivities() {
    const host = $("[data-activity-list]");
    host.replaceChildren();
    const allActivities = data.event.activities || [];
    const filterForm = $("[data-activity-filters]");
    const scheduleSelect = filterForm.elements.schedule;
    const scheduleOptions = [...new Set(allActivities.map((activity) => activity.schedule_date).filter(Boolean))].sort();
    const currentSchedule = activityFilterState.schedule;
    scheduleSelect.replaceChildren(new Option("All schedules", ""));
    scheduleOptions.forEach((schedule) => scheduleSelect.add(new Option(new Date(`${schedule}T00:00:00`).toLocaleDateString("en-PH", { month: "short", day: "numeric", year: "numeric" }), schedule, false, schedule === currentSchedule)));
    const search = activityFilterState.search.toLocaleLowerCase();
    const activities = allActivities.filter((activity) => (!search || activity.name.toLocaleLowerCase().includes(search)) && (!activityFilterState.schedule || activity.schedule_date === activityFilterState.schedule) && (!activityFilterState.status || activity.status === activityFilterState.status)).sort((a, b) => {
      const scheduleA = a.schedule_date || "9999-12-31";
      const scheduleB = b.schedule_date || "9999-12-31";
      return scheduleA.localeCompare(scheduleB) || a.name.localeCompare(b.name, undefined, { sensitivity: "base" });
    });
    $("[data-activity-count]").textContent = `(${activities.length}${activities.length !== allActivities.length ? ` of ${allActivities.length}` : ""})`;
    if (!activities.length) {
      host.innerHTML = allActivities.length ? '<tr><td class="px-5 py-9 text-center text-xs text-slate-500 sm:px-6" colspan="4">No activities match the current filters.</td></tr>' : '<tr><td class="px-5 py-9 text-center sm:px-6" colspan="4"><strong class="text-sm text-[#121017]">No activities yet</strong><p class="mt-1 text-xs text-slate-500">Use Add activity to start this event’s program.</p></td></tr>';
      return;
    }
    activities.forEach((activity) => {
      const item = document.createElement("tr");
      item.className = "align-middle";
      const statusStyle = { active: "bg-[#C6F24E]/35 text-[#397565]", upcoming: "bg-sky-100 text-sky-700", ongoing: "bg-amber-100 text-amber-800", completed: "bg-emerald-100 text-emerald-800", inactive: "bg-slate-100 text-slate-500" }[activity.status] || "bg-slate-100 text-slate-500";
      const statusLabel = activity.status ? activity.status.charAt(0).toUpperCase() + activity.status.slice(1) : "Active";
      const scheduledFor = activity.schedule_date ? new Date(`${activity.schedule_date}T00:00:00`).toLocaleDateString("en-PH", { month: "short", day: "numeric", year: "numeric" }) : "Schedule not set";
      item.innerHTML = `<td class="px-5 py-4 sm:px-6"><strong class="text-sm text-[#121017]">${EventForm.escapeHtml(activity.name)}</strong></td><td class="px-4 py-4 text-xs font-bold text-slate-700">${scheduledFor}</td><td class="px-4 py-4"><span class="rounded px-2 py-0.5 text-[10px] font-bold ${statusStyle}">${statusLabel}</span></td><td class="px-5 py-4 sm:px-6"><div class="flex justify-end gap-2"><button class="min-h-9 rounded-lg border border-slate-300 px-3 text-xs font-bold text-slate-700" type="button" data-edit>Edit</button><button class="min-h-9 rounded-lg border border-slate-300 px-3 text-xs font-bold text-slate-700" type="button" data-toggle>${activity.status === "inactive" ? "Include" : "Remove"}</button></div></td>`;
      item.querySelector("[data-edit]").onclick = () => openActivityDialog(activity);
      item.querySelector("[data-toggle]").onclick = async (event) => {
        event.currentTarget.disabled = true;
        try {
          await post({ action: "activity_status", id, activity_id: activity.id, status: activity.status === "inactive" ? "active" : "inactive" });
          toast("success", "Activity status updated.");
          await load();
        } catch (error) {
          event.currentTarget.disabled = false;
          toast("error", error.response?.data?.message || "Unable to update activity.");
        }
      };
      host.append(item);
    });
  }
  const activityForm = $("[data-activity-form]");
  const activityFilters = $("[data-activity-filters]");
  activityFilters.elements.search.addEventListener("input", () => {
    activityFilterState.search = activityFilters.elements.search.value.trim();
    renderActivities();
  });
  activityFilters.elements.schedule.addEventListener("change", () => {
    activityFilterState.schedule = activityFilters.elements.schedule.value;
    renderActivities();
  });
  activityFilters.elements.status.addEventListener("change", () => {
    activityFilterState.status = activityFilters.elements.status.value;
    renderActivities();
  });
  const pointsDialog = $("[data-points-dialog]"), pointsForm = $("[data-points-form]");
  document.querySelectorAll("[data-points-cancel]").forEach((button) => button.onclick = () => pointsDialog.close());
  pointsForm.onclick = (event) => {
    if (event.target.closest("[data-points-add]")) {
      const placements = [...pointsForm.querySelectorAll("[data-points-placement]")].map((input) => Number(input.value) || 0);
      $("[data-points-rule-list]").insertAdjacentHTML("beforeend", pointsRow(Math.max(0, ...placements) + 1, ""));
    }
    const remove = event.target.closest("[data-points-remove]");
    if (remove && pointsForm.querySelectorAll("[data-points-row]").length > 1) remove.closest("[data-points-row]").remove();
  };
  pointsForm.onsubmit = async (event) => {
    event.preventDefault();
    if (!pointsForm.reportValidity()) return;
    const placementRules = {};
    let duplicate = false;
    pointsForm.querySelectorAll("[data-points-row]").forEach((row) => {
      const placement = row.querySelector("[data-points-placement]").value;
      if (placementRules[placement] !== undefined) duplicate = true;
      placementRules[placement] = row.querySelector("[data-points-value]").value;
    });
    const errorHost = $("[data-points-error]"), saveButton = $("[data-points-save]");
    if (duplicate) { errorHost.textContent = "Each placement can be configured only once."; errorHost.classList.remove("hidden"); return; }
    errorHost.classList.add("hidden"); saveButton.disabled = true;
    try {
      const response = await axios.post("api/activity-scoring.php?action=placement-rules-save", { event_id: id, activity_id: Number(pointsForm.elements.activity_id.value), placement_rules: placementRules }, { headers: { "X-CSRF-Token": csrf } });
      pointsDialog.close(); toast("success", response.data.message || "Points saved."); await load();
    } catch (error) {
      errorHost.textContent = error.response?.data?.message || "Points could not be saved."; errorHost.classList.remove("hidden");
    } finally { saveButton.disabled = false; }
  };
  activityForm.dataset.axiosForm = "";
  const activityDialog = $("[data-activity-dialog]");
  $("[data-activity-add]").onclick = () => openActivityDialog();
  document.querySelectorAll("[data-activity-cancel]").forEach((button) => {
    button.onclick = () => activityDialog.close();
  });
  activityDialog.addEventListener("close", resetActivityForm);
  activityForm.onsubmit = async (event) => {
    event.preventDefault();
    const form = event.currentTarget;
    const activityId = Number(form.elements.activity_id.value);
    const errorHost = $("[data-activity-error]");
    const saveButton = $("[data-activity-save]");
    errorHost.classList.add("hidden");
    saveButton.disabled = true;
    try {
      await post({ action: activityId ? "activity_update" : "activity_create", id, activity_id: activityId, catalog_activity_id: Number(form.elements.catalog_activity_id.value), event_schedule_id: Number(form.elements.event_schedule_id.value), name: form.elements.name.value.trim() });
      toast("success", activityId ? "Activity updated." : "Activity added.");
      activityDialog.close();
      location.reload();
    } catch (error) {
      errorHost.textContent = error.response?.data?.errors?.catalog_activity_id?.[0] || error.response?.data?.errors?.event_schedule_id?.[0] || error.response?.data?.errors?.name?.[0] || error.response?.data?.message || "Unable to save activity.";
      errorHost.classList.remove("hidden");
    } finally {
      saveButton.disabled = false;
    }
  };
  async function load() {
    if (!id) {
      location.replace("pages/adviser/events.html");
      return;
    }
    try {
      const response = await axios.get("api/adviser-events.php", {
        params: { id },
      });
      data = response.data.data;
      render();
      renderActivities();
    } catch (error) {
      toast("error", error.response?.data?.message || "Event not found.");
      setTimeout(() => location.replace("pages/adviser/events.html"), 800);
    }
  }
  load();
  $("[data-feature-form]").onsubmit = async (eventObject) => {
    eventObject.preventDefault();
    const body = Object.fromEntries(new FormData(eventObject.target));
    body.action = "feature";
    body.id = id;
    body.is_featured = 1;
    const message = $("[data-feature-error]");
    message.classList.add("hidden");
    try {
      await post(body);
      toast("success", "Featured setting updated.");
      await load();
    } catch (error) {
      message.textContent =
        error.response?.data?.errors?.featured_until?.[0] ||
        error.response?.data?.errors?.featured_order?.[0] ||
        error.response?.data?.message ||
        "Unable to update featured setting.";
      message.classList.remove("hidden");
    }
  };
  $("[data-remove-feature]").onclick = async () => {
    try {
      await post({ action: "feature", id, is_featured: 0 });
      toast("success", "Event removed from the featured carousel.");
      await load();
    } catch (error) {
      toast(
        "error",
        error.response?.data?.message || "Unable to remove featured event.",
      );
    }
  };
  $("[data-assign-form]").onsubmit = async (eventObject) => {
    eventObject.preventDefault();
    const error = $("[data-assignment-error]");
    error.classList.add("hidden");
    try {
      await post({
        action: "assign",
        id,
        user_id: eventObject.target.user_id.value,
      });
      toast("success", "Person assigned successfully.");
      await load();
    } catch (requestError) {
      error.textContent =
        requestError.response?.data?.errors?.user_id?.[0] ||
        requestError.response?.data?.message ||
        "Unable to assign person.";
      error.classList.remove("hidden");
    }
  };
  const archiveButton = $("[data-archive-event]");
  if (archiveButton)
    archiveButton.onclick = async () => {
      const accepted = window.Notifications?.confirm
        ? await window.Notifications.confirm({
            title: "Archive event?",
            message: "Its details and assignments will be preserved, but it will leave the active event list.",
            action: "Archive",
          })
        : false;
      if (!accepted) return;
      try {
        await post({ action: "archive", id });
        window.Notifications?.flashNext?.("Event archived successfully.");
        location.replace("pages/adviser/events.html");
      } catch (error) {
        toast(
          "error",
          error.response?.data?.message || "Unable to archive event.",
        );
      }
    };
});
