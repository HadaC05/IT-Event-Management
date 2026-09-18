window.SharedNavigation.ready.then((context) => {
  "use strict";
  const id = Number(new URLSearchParams(location.search).get("id")),
    toast = (type, message) =>
      window.Notifications?.[type]?.(message) ||
      (type === "error" && alert(message));
  let csrf = context.csrfToken,
    data;
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
  const statusClass = (label) =>
    ({
      upcoming: "bg-[#2F3AE0]/8 text-[#2F3AE0]",
      ongoing: "bg-[#C6F24E]/35 text-[#397565]",
      completed: "bg-[#121017]/6 text-[#121017]/55",
      archived: "bg-[#FF6B2C]/10 text-[#FF6B2C]",
    })[label] || "bg-slate-100 text-slate-500";
  document
    .querySelectorAll(
      "[data-status-form],[data-feature-form],[data-assign-form]",
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
    $("[data-edit-link-visible]").href = `pages/adviser/event-edit.html?id=${event.id}`;
    const scoreLink = $("[data-event-scores]");
    const attendanceLink = $("[data-event-attendance]");
    if (scoreLink)
      scoreLink.href = `pages/adviser/scoreboard.html?event_id=${event.id}`;
    if (attendanceLink)
      attendanceLink.href = `pages/adviser/attendance-roster.html?event_id=${event.id}`;
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
    $("[data-stat-cards]").innerHTML = `${[
      ["Starts", date(event.start_at), time(event.start_at)],
      ["Ends", date(event.end_at), time(event.end_at)],
      ["Location", EventForm.escapeHtml(event.location), "Event venue"],
    ]
      .map(
        (item) =>
          `<article class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm"><span class="text-[10px] font-extrabold uppercase text-slate-400">${item[0]}</span><strong class="mt-2 block truncate text-sm">${item[1]}</strong><span class="mt-1 block text-xs text-slate-500">${item[2]}</span></article>`,
      )
      .join(
        "",
      )}<article class="rounded-2xl border border-emerald-100 bg-emerald-50/60 p-5"><span class="text-[10px] font-extrabold uppercase text-emerald-700">Expected attendees</span><strong class="mt-2 block text-2xl font-black text-emerald-900">${event.expected_participants}</strong><span class="mt-1 block text-xs text-emerald-800/70">${audienceLabel}</span></article>`;
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
    const status = $("[data-status-form] select"),
      statusButton = $("[data-status-form] button");
    status.replaceChildren();
    metadata.statuses.forEach((item) =>
      status.add(
        new Option(
          item.label[0].toUpperCase() + item.label.slice(1),
          item.id,
          false,
          Number(item.id) === Number(event.event_status_id),
        ),
      ),
    );
    statusButton.disabled = true;
    status.onchange = () =>
      (statusButton.disabled =
        Number(status.value) === Number(event.event_status_id));
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
    $("[data-activity-form-title]").textContent = "Add activity";
    $("[data-activity-save]").textContent = "Add activity";
    $("[data-activity-error]").classList.add("hidden");
  }
  function openActivityDialog(activity = null) {
    resetActivityForm();
    if (activity) {
      activityForm.elements.activity_id.value = activity.id;
      activityForm.elements.name.value = activity.name;
      activityForm.elements.description.value = activity.description || "";
      $("[data-activity-form-title]").textContent = "Edit activity";
      $("[data-activity-save]").textContent = "Save changes";
    }
    $("[data-activity-dialog]").showModal();
    activityForm.elements.name.focus();
  }
  function renderActivities() {
    const host = $("[data-activity-list]");
    host.replaceChildren();
    const activities = data.event.activities || [];
    $("[data-activity-count]").textContent = `(${activities.length})`;
    if (!activities.length) {
      host.innerHTML = '<div class="py-9 text-center"><strong class="text-sm text-[#121017]">No activities yet</strong><p class="mt-1 text-xs text-slate-500">Use Add activity to start this event’s program.</p></div>';
      return;
    }
    activities.forEach((activity) => {
      const item = document.createElement("article");
      item.className = "flex flex-col gap-3 py-4 sm:flex-row sm:items-center sm:justify-between";
      item.innerHTML = `<div class="min-w-0"><div class="flex flex-wrap items-center gap-2"><h3 class="font-bold text-[#121017]">${EventForm.escapeHtml(activity.name)}</h3><span class="rounded px-2 py-0.5 text-[10px] font-bold ${activity.status === "active" ? "bg-[#C6F24E]/35 text-[#397565]" : "bg-slate-100 text-slate-500"}">${activity.status === "active" ? "Active" : "Inactive"}</span></div>${activity.description ? `<p class="mt-1 whitespace-pre-wrap text-xs text-slate-500">${EventForm.escapeHtml(activity.description)}</p>` : ""}</div><div class="flex shrink-0 gap-2"><button class="min-h-9 rounded-lg border border-slate-300 px-3 text-xs font-bold text-slate-700" type="button" data-edit>Edit</button><button class="min-h-9 rounded-lg border border-slate-300 px-3 text-xs font-bold text-slate-700" type="button" data-toggle>${activity.status === "active" ? "Deactivate" : "Activate"}</button></div>`;
      item.querySelector("[data-edit]").onclick = () => openActivityDialog(activity);
      item.querySelector("[data-toggle]").onclick = async (event) => {
        event.currentTarget.disabled = true;
        try {
          await post({ action: "activity_status", id, activity_id: activity.id, status: activity.status === "active" ? "inactive" : "active" });
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
      await post({ action: activityId ? "activity_update" : "activity_create", id, activity_id: activityId, name: form.elements.name.value.trim(), description: form.elements.description.value.trim() });
      toast("success", activityId ? "Activity updated." : "Activity added.");
      activityDialog.close();
      await load();
    } catch (error) {
      errorHost.textContent = error.response?.data?.errors?.name?.[0] || error.response?.data?.errors?.description?.[0] || error.response?.data?.message || "Unable to save activity.";
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
  $("[data-status-form]").onsubmit = async (eventObject) => {
    eventObject.preventDefault();
    try {
      await post({
        action: "status",
        id,
        event_status_id: eventObject.target.event_status_id.value,
      });
      toast("success", "Event status updated successfully.");
      await load();
    } catch (error) {
      toast(
        "error",
        error.response?.data?.message || "Unable to update status.",
      );
    }
  };
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
        : confirm("Archive this event? Its details and assignments will be preserved.");
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
