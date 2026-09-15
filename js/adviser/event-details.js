(() => {
  "use strict";
  const id = Number(new URLSearchParams(location.search).get("id")),
    toast = (type, message) =>
      window.Notifications?.[type]?.(message) ||
      (type === "error" && alert(message));
  let csrf = "",
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
    avatar.childNodes[0].textContent = EventForm.initials(name);
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
    csrf = response.data.csrf_token;
    applyAccount(response.data.user);
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
    return response.data;
  }
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
      row("Location", EventForm.escapeHtml(event.location)) +
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
    } catch (error) {
      toast("error", error.response?.data?.message || "Event not found.");
      setTimeout(() => location.replace("pages/adviser/events.html"), 800);
    }
  }
  initializeShell();
  authenticate()
    .then(load)
    .catch((error) => {
      if (error.message !== "Unauthorized")
        toast("error", "Unable to verify your session.");
    });
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
        toast("success", "Event archived successfully.");
        location.replace("pages/adviser/events.html");
      } catch (error) {
        toast(
          "error",
          error.response?.data?.message || "Unable to archive event.",
        );
      }
    };
})();
