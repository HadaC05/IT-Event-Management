(() => {
  "use strict";
  const escapeHtml = (value) => {
    const div = document.createElement("div");
    div.textContent = value ?? "";
    return div.innerHTML;
  };
  const initials = (name) => {
    const words = (name || "").trim().split(/\s+/).filter(Boolean);
    return (
      words.length > 1
        ? `${words[0][0]}${words[words.length - 1][0]}`
        : (words[0] || "NA").slice(0, 2)
    ).toUpperCase();
  };
  const option = (label, value) => new Option(label, String(value));
  const values = (form) => Object.fromEntries(new FormData(form));
  function mount(
    form,
    metadata,
    { csrf, event = null, currentUserId = 0, onSaved } = {},
  ) {
    form.dataset.axiosForm = "";
    const type = form.elements.event_type_id,
      academicPeriod = form.elements.academic_period_id,
      generalLocation = form.elements.general_location_id,
      specificLocation = form.elements.specific_location_id,
      locationHost = form.querySelector("[data-event-locations]"),
      locationSection = form.querySelector("[data-event-locations-section]"),
      locationCount = form.querySelector("[data-location-count]"),
      audience = form.elements.audience_type,
      save = form.querySelector("[data-save-event]");
    let conflictTimer;
    metadata.event_types.forEach((item) =>
      type.add(option(item.label, item.id)),
    );
    (metadata.academic_periods || []).forEach((item) =>
      academicPeriod.add(option(item.label, item.id)),
    );
    academicPeriod.value = String(
      event?.academic_period_id || metadata.default_academic_period_id || "",
    );
    const locations = metadata.locations || [];
    const locationNames = new Map(locations.map((item) => [Number(item.id), item.name]));
    locations
      .filter((item) => item.type === "general")
      .forEach((item) => generalLocation.add(option(item.name, item.id)));
    const fillSpecificLocations = (selected = "") => {
      const matching = locations.filter(
        (item) =>
          item.type === "specific" &&
          Number(item.parent_location_id) === Number(generalLocation.value),
      );
      specificLocation.replaceChildren(
        option(
          matching.length
            ? "Use the general location"
            : generalLocation.value
              ? "No specific locations available"
              : "Select a general location first",
          "",
        ),
      );
      matching.forEach((item) =>
        specificLocation.add(option(item.name, item.id)),
      );
      specificLocation.disabled = !generalLocation.value || !matching.length;
      specificLocation.value = matching.some(
        (item) => Number(item.id) === Number(selected),
      )
        ? String(selected)
        : "";
    };
    fillSpecificLocations();
    let previousPrimaryLocationId = 0;
    const selectedLocationIds = new Set(
      (event?.location_ids || (event?.location_id ? [event.location_id] : [])).map(Number),
    );
    const rememberAdditionalLocations = () => {
      locationHost.querySelectorAll("[data-event-location]").forEach((input) => {
        const id = Number(input.value);
        if (input.checked) selectedLocationIds.add(id);
        else selectedLocationIds.delete(id);
      });
    };
    const renderEventLocations = (primaryId = Number(specificLocation.value || generalLocation.value || 0)) => {
      locationHost.replaceChildren();
      locationSection.classList.toggle("hidden", !primaryId);
      if (!primaryId) {
        locationCount.textContent = "0 additional selected";
        return;
      }
      const additionalLocations = locations.filter((item) => Number(item.id) !== primaryId);
      additionalLocations.forEach((item) => {
        const row = document.createElement("label");
        row.className = "flex min-h-14 cursor-pointer items-center gap-3 rounded-lg border border-slate-200 bg-slate-50 p-3 text-xs transition hover:border-emerald-300 has-[:checked]:border-emerald-400 has-[:checked]:bg-emerald-50";
        const input = document.createElement("input");
        input.type = "checkbox";
        input.name = "location_ids[]";
        input.value = item.id;
        input.checked = selectedLocationIds.has(Number(item.id));
        input.className = "h-4 w-4 shrink-0 accent-emerald-600";
        input.dataset.eventLocation = "";
        const parentName = item.parent_location_id ? locationNames.get(Number(item.parent_location_id)) : "";
        const text = document.createElement("span");
        text.className = "min-w-0 flex-1";
        text.innerHTML = `<strong class="block text-slate-700">${escapeHtml(item.name)}</strong><small class="text-slate-400">${item.type === "specific" ? `Specific venue${parentName ? ` · ${escapeHtml(parentName)}` : ""}` : "General location"}</small>`;
        row.append(input, text);
        locationHost.append(row);
      });
      if (!additionalLocations.length) {
        const empty = document.createElement("p");
        empty.className = "sm:col-span-2 rounded-lg bg-slate-50 p-4 text-center text-xs text-slate-500";
        empty.textContent = "No other saved locations are available.";
        locationHost.append(empty);
      }
    };
    const updateLocationCount = () => {
      const count = locationHost.querySelectorAll('[data-event-location]:checked').length;
      locationCount.textContent = `${count} additional selected`;
    };
    const syncPrimaryLocation = () => {
      rememberAdditionalLocations();
      const primaryId = Number(specificLocation.value || generalLocation.value || 0);
      if (previousPrimaryLocationId && previousPrimaryLocationId !== primaryId) {
        selectedLocationIds.delete(previousPrimaryLocationId);
      }
      if (primaryId) selectedLocationIds.add(primaryId);
      previousPrimaryLocationId = primaryId;
      renderEventLocations(primaryId);
      updateLocationCount();
    };
    locationHost.addEventListener("change", () => {
      rememberAdditionalLocations();
      clearError("location_ids");
      updateLocationCount();
      checkConflicts();
    });
    const fillChecks = (host, items, name, label, selected = []) => {
      host.replaceChildren();
      items.forEach((item) => {
        const row = document.createElement("label");
        row.className =
          "flex min-h-12 cursor-pointer items-center gap-3 rounded-lg border border-slate-200 bg-slate-100 p-3 text-xs transition hover:border-emerald-300 has-[:checked]:border-emerald-400 has-[:checked]:bg-emerald-200";
        const input = document.createElement("input");
        input.type = "checkbox";
        input.name = name + "[]";
        input.value = item.id;
        input.checked = selected.includes(Number(item.id));
        input.className = "h-4 w-4 accent-emerald-600";
        const text = document.createElement("span");
        text.className = "min-w-0 flex-1";
        text.innerHTML = label(item);
        row.append(input, text);
        host.append(row);
      });
    };
    fillChecks(
      form.querySelector('[data-audience-panel="selected_year_levels"]'),
      metadata.audience_year_levels,
      "year_level_ids",
      (item) =>
        `<strong>${escapeHtml(item.label)}</strong><small class="float-right text-slate-400">${item.students_count} students</small>`,
      event?.audience_year_level_ids || [],
    );
    const renderPeriodTeams = () => {
      const period = (metadata.academic_periods || []).find(
        (item) => String(item.id) === String(academicPeriod.value),
      );
      const selected = [
        ...form.querySelectorAll('[name="tribe_ids[]"]:checked'),
      ].map((input) => Number(input.value));
      fillChecks(
        form.querySelector('[data-audience-panel="selected_tribes"]'),
        metadata.audience_teams.filter(
          (item) =>
            !period ||
            Number(item.school_year_id) === Number(period.school_year_id),
        ),
        "tribe_ids",
        (item) =>
          `<strong>${escapeHtml(item.name)}</strong><small class="float-right text-slate-400">${item.students_count} students</small>`,
        selected.length ? selected : event?.audience_team_ids || [],
      );
    };
    renderPeriodTeams();
    fillChecks(
      form.querySelector("[data-assignable-users]"),
      metadata.assignable_users,
      "assigned_user_ids",
      (item) =>
        `<strong class="block text-slate-700">${escapeHtml(item.full_name)}</strong><small class="text-slate-500">${escapeHtml(item.role)}</small>`,
      event?.assigned_users?.map((user) => Number(user.id)) || [],
    );
    const audienceCounts = {
      selected_year_levels: new Map(
        metadata.audience_year_levels.map((item) => [
          String(item.id),
          Number(item.students_count),
        ]),
      ),
      selected_tribes: new Map(
        metadata.audience_teams.map((item) => [
          String(item.id),
          Number(item.students_count),
        ]),
      ),
    },
      selectedParticipantIds = new Set(
        (event?.participant_ids || []).map(Number),
      ),
      studentPanel = form.querySelector(
        '[data-audience-panel="specific_students"]',
      ),
      studentSearch = studentPanel.querySelector("[data-student-search]"),
      studentResults = studentPanel.querySelector("[data-student-results]"),
      studentPagination = studentPanel.querySelector(
        "[data-student-pagination]",
      ),
      clearStudents = studentPanel.querySelector("[data-clear-students]");
    let studentPage = 1,
      studentSearchTimer,
      studentRequest = 0,
      studentsLoaded = false;

    const updateSelectedStudentCount = () => {
      const count = selectedParticipantIds.size;
      studentPanel.querySelector("[data-selected-student-count]").textContent =
        `${count.toLocaleString()} selected`;
      clearStudents.disabled = count === 0;
    };
    const renderStudentPagination = (pagination) => {
      const visible = pagination.last_page > 1;
      studentPagination.classList.toggle("hidden", !visible);
      studentPagination.classList.toggle("flex", visible);
      if (!visible) {
        studentPagination.replaceChildren();
        return;
      }
      studentPagination.innerHTML = `<span>Showing ${pagination.from.toLocaleString()}–${pagination.to.toLocaleString()} of ${pagination.total.toLocaleString()}</span><span class="flex items-center gap-2"><button class="min-h-9 rounded-lg border border-slate-200 bg-white px-3 font-bold disabled:opacity-30" type="button" data-student-page="${pagination.current_page - 1}" ${pagination.current_page === 1 ? "disabled" : ""}>Previous</button><b>${pagination.current_page} / ${pagination.last_page}</b><button class="min-h-9 rounded-lg border border-slate-200 bg-white px-3 font-bold disabled:opacity-30" type="button" data-student-page="${pagination.current_page + 1}" ${pagination.current_page === pagination.last_page ? "disabled" : ""}>Next</button></span>`;
    };
    const renderStudentCandidates = (data) => {
      studentResults.replaceChildren();
      data.students.forEach((student) => {
        const row = document.createElement("label");
        row.className =
          "flex min-h-14 cursor-pointer items-center gap-3 rounded-lg border border-slate-200 bg-white p-3 text-xs transition hover:border-emerald-300 has-[:checked]:border-emerald-400 has-[:checked]:bg-emerald-50";
        const input = document.createElement("input");
        input.type = "checkbox";
        input.value = student.id;
        input.checked = selectedParticipantIds.has(Number(student.id));
        input.disabled = audience.value !== "specific_students";
        input.className = "h-4 w-4 shrink-0 accent-emerald-600";
        input.dataset.studentCandidate = "";
        const text = document.createElement("span");
        text.className = "min-w-0 flex-1";
        text.innerHTML = `<strong class="block truncate text-slate-700">${escapeHtml(student.full_name)}</strong><small class="block truncate text-slate-400">${escapeHtml(student.id_number || "No student ID")}${student.year_level_label ? ` · ${escapeHtml(student.year_level_label)}` : ""}</small>`;
        row.append(input, text);
        studentResults.append(row);
      });
      if (!data.students.length) {
        const empty = document.createElement("p");
        empty.className =
          "sm:col-span-2 rounded-lg border border-dashed border-slate-200 bg-white px-4 py-8 text-center text-xs text-slate-500";
        empty.textContent = "No active students match this search.";
        studentResults.append(empty);
      }
      const pagination = data.pagination;
      studentPanel.querySelector("[data-student-result-count]").textContent =
        `${pagination.total.toLocaleString()} matching ${pagination.total === 1 ? "student" : "students"}`;
      studentPage = pagination.current_page;
      renderStudentPagination(pagination);
      updateSelectedStudentCount();
    };
    const loadStudents = async (page = studentPage) => {
      const request = ++studentRequest;
      studentPanel.querySelector("[data-student-result-count]").textContent =
        "Loading students…";
      try {
        const response = await axios.get("api/adviser-events.php", {
          params: {
            action: "student_candidates",
            search: studentSearch.value.trim(),
            page,
          },
        });
        if (request !== studentRequest) return;
        studentsLoaded = true;
        renderStudentCandidates(response.data.data);
      } catch (error) {
        if (request !== studentRequest) return;
        studentResults.replaceChildren();
        studentPagination.classList.add("hidden");
        studentPanel.querySelector("[data-student-result-count]").textContent =
          error.response?.data?.message || "Students could not be loaded.";
      }
    };
    const updateAudience = () => {
      form.querySelectorAll("[data-audience-panel]").forEach((panel) => {
        const active = panel.dataset.audiencePanel === audience.value;
        panel.classList.toggle("hidden", !active);
        panel
          .querySelectorAll("input")
          .forEach((input) => (input.disabled = !active));
      });
      let count = Number(metadata.active_students_count || 0);
      if (audience.value === "specific_students")
        count = selectedParticipantIds.size;
      else if (audienceCounts[audience.value]) {
        count = 0;
        form
          .querySelectorAll(
            `[data-audience-panel="${audience.value}"] input:checked`,
          )
          .forEach(
            (input) =>
              (count += audienceCounts[audience.value].get(input.value) || 0),
          );
      }
      form.querySelector("[data-expected-count]").textContent =
        count.toLocaleString();
      updateSelectedStudentCount();
      readiness();
    };
    audience.addEventListener("change", () => {
      const panel = form.querySelector(
        `[data-audience-panel="${audience.value}"]`,
      );
      if (
        audience.value === "selected_year_levels" &&
        !panel.querySelector("input:checked")
      )
        panel
          .querySelectorAll("input")
          .forEach((input) => (input.checked = true));
      updateAudience();
      if (audience.value === "specific_students" && !studentsLoaded)
        loadStudents(1);
    });
    studentSearch.addEventListener("input", () => {
      clearTimeout(studentSearchTimer);
      studentSearchTimer = setTimeout(() => loadStudents(1), 300);
    });
    studentResults.addEventListener("change", (eventObject) => {
      const input = eventObject.target.closest("[data-student-candidate]");
      if (!input) return;
      const id = Number(input.value);
      if (input.checked) selectedParticipantIds.add(id);
      else selectedParticipantIds.delete(id);
      clearError("audience_type");
      updateAudience();
    });
    studentPagination.addEventListener("click", (eventObject) => {
      const button = eventObject.target.closest("[data-student-page]");
      if (!button || button.disabled) return;
      loadStudents(Number(button.dataset.studentPage));
    });
    clearStudents.addEventListener("click", () => {
      selectedParticipantIds.clear();
      studentResults
        .querySelectorAll("[data-student-candidate]")
        .forEach((input) => (input.checked = false));
      updateAudience();
    });
    form
      .querySelector("[data-audience-selector]")
      .addEventListener("change", updateAudience);
    const scheduleSection = form.querySelector("[data-attendance-schedule]"),
      scheduleList = scheduleSection.querySelector("[data-schedules]"),
      modes = metadata.attendance_modes,
      modeDefault = modes.find((mode) => mode.code === "whole_day");
    const rows = () => [
      ...scheduleList.querySelectorAll("[data-schedule-row]"),
    ];
    const rowValues = (row) => ({
      date: row.querySelector("[data-schedule-date]").value,
      attendance_session_mode_id: row.querySelector("[data-session-mode]")
        .value,
      morning_in: row.querySelector('[data-checkpoint="morning_in"]').value,
      morning_in_close: row.querySelector('[data-checkpoint="morning_in_close"]').value,
      morning_out_open: row.querySelector('[data-checkpoint="morning_out_open"]').value,
      morning_out: row.querySelector('[data-checkpoint="morning_out"]').value,
    });
    const syncSchedules = () => {
      rows().forEach((row, index) => {
        row.querySelector("[data-schedule-number]").textContent =
          `Schedule ${index + 1}`;
        row.querySelector("[data-remove-schedule]").hidden =
          rows().length === 1;
        row
          .querySelectorAll("[name]")
          .forEach(
            (input) =>
              (input.name = input.name.replace(
                /attendance_days\[\d+\]/,
                `attendance_days[${index}]`,
              )),
          );
      });
      const days = rows()
          .map(rowValues)
          .filter((day) => day.date)
          .sort((a, b) => a.date.localeCompare(b.date)),
        first = days[0],
        last = days.at(-1);
      form.elements.start_date.value = first?.date || "";
      form.elements.start_time.value = first?.morning_in || "";
      form.elements.end_date.value = last?.date || "";
      form.elements.end_time.value = last?.morning_out || "";
      readiness();
      checkConflicts();
    };
    const applyDefaults = (row, defaults = false) => {
      if (defaults) {
        row.querySelector('[data-checkpoint="morning_in"]').value = "08:00";
        row.querySelector('[data-checkpoint="morning_in_close"]').value = "10:00";
        row.querySelector('[data-checkpoint="morning_out_open"]').value = "17:00";
        row.querySelector('[data-checkpoint="morning_out"]').value = "18:00";
      }
      syncSchedules();
    };
    const addSchedule = (day = {}, defaults = false) => {
      const index = rows().length,
        row = document.createElement("div"),
        today = new Date().toLocaleDateString("en-CA");
      row.dataset.scheduleRow = "";
      row.className = "rounded-xl border border-slate-200 bg-white p-4";
      row.innerHTML = `<input type="hidden" name="attendance_days[${index}][id]" value="${day.id || ""}"><input type="hidden" name="attendance_days[${index}][attendance_session_mode_id]" value="${modeDefault?.id || ""}" data-session-mode><div class="mb-3 flex items-center justify-between"><strong class="text-xs font-extrabold text-slate-600" data-schedule-number>Schedule ${index + 1}</strong><button class="text-xs font-bold text-rose-600" type="button" data-remove-schedule>Remove</button></div><div class="event-schedule-fields grid gap-4 sm:grid-cols-2"><label class="grid gap-2"><span class="text-xs font-bold">Date *</span><input class="h-11 min-w-0 rounded-xl border border-slate-200 px-3 text-sm" type="date" min="${day.date && day.date < today ? day.date : today}" name="attendance_days[${index}][date]" value="${day.date || day.schedule_date || ""}" required data-schedule-date></label>${[
        ["morning_in", "Time In opens", "data-primary"],
        ["morning_in_close", "Time In closes", "data-primary"],
        ["morning_out_open", "Time Out opens", "data-primary"],
        ["morning_out", "Time Out closes", "data-primary"],
      ]
        .map(
          ([key, label, kind]) =>
            `<label class="grid gap-2" ${kind}><span class="text-xs font-bold">${label} *</span><input class="h-11 min-w-0 rounded-xl border border-slate-200 px-3 text-sm" type="time" name="attendance_days[${index}][${key}]" value="${day[key] || ""}" data-checkpoint="${key}"></label>`,
        )
        .join(
          "",
        )}</div><p class="mt-2 hidden text-xs font-semibold text-red-600" data-row-error></p>`;
      scheduleList.append(row);
      applyDefaults(row, defaults);
    };
    scheduleSection.querySelector("[data-add-schedule]").onclick = () => {
      const prior = rows().at(-1),
        day = prior ? rowValues(prior) : {},
        date = day.date ? new Date(day.date + "T12:00:00") : new Date();
      date.setDate(date.getDate() + (day.date ? 1 : 0));
      addSchedule(
        { ...day, date: date.toLocaleDateString("en-CA") },
        !day.attendance_session_mode_id,
      );
    };
    scheduleSection.addEventListener("input", syncSchedules);
    scheduleSection.addEventListener("change", syncSchedules);
    scheduleSection.addEventListener("click", (event) => {
      const remove = event.target.closest("[data-remove-schedule]");
      if (remove) {
        remove.closest("[data-schedule-row]").remove();
        syncSchedules();
      }
    });
    const saved = (event?.attendance_schedules || []).map((day) => ({
      id: day.id,
      date: day.schedule_date,
      attendance_session_mode_id: modeDefault?.id || "",
      morning_in: (day.whole_day_in_time || day.morning_in_time || day.afternoon_in_time || "").slice(
        0,
        5,
      ),
      morning_in_close: (day.whole_day_in_close_time || day.morning_in_close_time || day.afternoon_in_close_time || "").slice(0, 5),
      morning_out_open: (day.whole_day_out_open_time || day.afternoon_out_open_time || day.morning_out_open_time || "").slice(0, 5),
      morning_out: (day.whole_day_out_time || day.afternoon_out_time || day.morning_out_time || "").slice(
        0,
        5,
      ),
      afternoon_in: "",
      afternoon_in_close: "",
      afternoon_out_open: "",
      afternoon_out: "",
    }));
    (saved.length ? saved : [{}]).forEach((day) =>
      addSchedule(day, !day.attendance_session_mode_id),
    );
    if (event) {
      type.value = event.event_type_id || "";
      academicPeriod.value = event.academic_period_id || "";
      renderPeriodTeams();
      form.elements.title.value = event.title || "";
      const savedLocation =
        locations.find(
          (item) => Number(item.id) === Number(event.location_id),
        ) || locations.find((item) => item.name === event.location);
      if (savedLocation?.type === "specific") {
        generalLocation.value = savedLocation.parent_location_id || "";
        fillSpecificLocations(savedLocation.id);
      } else {
        generalLocation.value = savedLocation?.id || "";
        fillSpecificLocations();
      }
      syncPrimaryLocation();
      audience.value = event.audience_type || "all_students";
      form.elements.description.value = event.description || "";
      form.elements.attendance_location_policy.value = event.attendance_location_policy === "strict" ? "strict" : "warning";
      const current = form.querySelector("[data-current-poster]");
      if (current && event.poster_path) {
        current.src = event.poster_path;
        current.classList.remove("hidden");
      }
    } else {
      const me =
        metadata.assignable_users.find(
          (user) => Number(user.id) === Number(currentUserId),
        ) ||
        metadata.assignable_users.find((user) => user.role === "SBO Adviser");
      if (me)
        form
          .querySelector(`[name="assigned_user_ids[]"][value="${me.id}"]`)
          ?.click();
      syncPrimaryLocation();
    }
    const poster = form.elements.poster;
    poster?.addEventListener("change", () => {
      const file = poster.files?.[0],
        error = validatePoster(file);
      clearError("poster");
      if (error) showError("poster", error);
      if (file && !error) {
        form.querySelector("[data-poster-name]").textContent = file.name;
        const preview = form.querySelector("[data-poster-preview]");
        if (preview) {
          preview.src = URL.createObjectURL(file);
          preview.classList.remove("hidden");
        }
      }
    });
    type.addEventListener("change", () => {
      if (
        !event &&
        type.selectedOptions[0]?.value &&
        !form.elements.title.value.trim()
      ) {
        form.elements.title.value = `${type.selectedOptions[0].textContent} ${new Date().getFullYear()}`;
        form.elements.title.dispatchEvent(new Event("input"));
      }
    });
    function readiness() {
      const audienceReady =
          audience.value === "all_students" ||
          (audience.value === "specific_students"
            ? selectedParticipantIds.size > 0
            : !!form.querySelector(
                `[data-audience-panel="${audience.value}"] input:checked`,
              )),
        scheduleReady =
          rows().length &&
          rows().every((row) =>
            [...row.querySelectorAll("[required]:not(:disabled)")].every(
              (field) => field.value,
            ),
          );
      const ready = !(
        !type.value ||
        !form.elements.title.value.trim() ||
        !generalLocation.value ||
        !audienceReady ||
        !scheduleReady
      );
      // Keep the action clickable so validation can explain what is missing.
      save.disabled = false;
      save.setAttribute("aria-disabled", String(!ready));
      save.title = ready ? "" : "Click to review the required event information.";
      return { ready, audienceReady, scheduleReady };
    }
    function clearError(field) {
      const error = form.querySelector(
        `[data-error-for="${CSS.escape(field)}"]`,
      );
      if (error) {
        error.textContent = "";
        error.classList.add("hidden");
      }
      const control = form.elements[field];
      if (control instanceof HTMLElement) {
        control.classList.remove("border-red-400", "bg-red-50/40");
        control.removeAttribute("aria-invalid");
      }
    }
    function clearErrors() {
      form
        .querySelectorAll("[data-error-for],[data-row-error]")
        .forEach((error) => {
          error.textContent = "";
          error.classList.add("hidden");
        });
      form.querySelectorAll("[aria-invalid]").forEach((control) => {
        control.removeAttribute("aria-invalid");
        control.classList.remove("border-red-400", "bg-red-50/40");
      });
      form.querySelector("[data-form-error]")?.classList.add("hidden");
    }
    function showError(field, message) {
      const audienceFields = ["tribe_ids", "year_level_ids", "participant_ids"],
        root = field.startsWith("attendance_days.")
          ? "attendance_days"
          : audienceFields.includes(field)
            ? "audience_type"
            : field,
        error = form.querySelector(`[data-error-for="${CSS.escape(root)}"]`);
      if (!error) return false;
      error.textContent = Array.isArray(message) ? message[0] : message;
      error.classList.remove("hidden");
      const control = form.elements[root];
      if (control instanceof HTMLElement) {
        control.setAttribute("aria-invalid", "true");
        control.classList.add("border-red-400", "bg-red-50/40");
      }
      return true;
    }
    function showErrors(errors, message) {
      let shown = false;
      Object.entries(errors || {}).forEach(
        ([field, error]) => (shown = showError(field, error) || shown),
      );
      if (!shown) {
        const general = form.querySelector("[data-form-error]");
        general.textContent = message || "Please check the event form.";
        general.classList.remove("hidden");
      }
      form
        .querySelector(
          "[data-error-for]:not(.hidden),[data-form-error]:not(.hidden)",
        )
        ?.scrollIntoView({ behavior: "smooth", block: "center" });
    }
    function validatePoster(file) {
      if (!file) return "";
      if (file.size > 5 * 1024 * 1024) return "The poster may not exceed 5 MB.";
      if (!["image/jpeg", "image/png", "image/webp"].includes(file.type))
        return "Use a JPG, PNG, or WebP image.";
      return "";
    }
    async function checkConflicts() {
      clearTimeout(conflictTimer);
      const start =
          form.elements.start_date.value + "T" + form.elements.start_time.value,
        end = form.elements.end_date.value + "T" + form.elements.end_time.value;
      if (!form.elements.start_date.value || new Date(end) <= new Date(start))
        return;
      conflictTimer = setTimeout(async () => {
        const payload = new FormData();
        payload.append("action", "conflicts");
        [
          "start_date",
          "start_time",
          "end_date",
          "end_time",
          "general_location_id",
          "specific_location_id",
        ].forEach((name) =>
          payload.append(name, form.elements[name].value || ""),
        );
        locationHost
          .querySelectorAll('[data-event-location]:checked')
          .forEach((input) => payload.append("location_ids[]", input.value));
        if (event) payload.append("event_id", event.id);
        form
          .querySelectorAll('[name="assigned_user_ids[]"]:checked')
          .forEach((input) =>
            payload.append("assigned_user_ids[]", input.value),
          );
        try {
          const response = await axios.post("api/adviser-events.php", payload, {
              headers: { "X-CSRF-Token": csrf },
            }),
            result = response.data,
            warning = form.querySelector("[data-conflict-warning]"),
            list = warning.querySelector("[data-conflict-list]");
          warning.classList.toggle("hidden", !result.has_conflicts);
          if (!result.has_conflicts) {
            form.elements.acknowledge_conflicts.checked = false;
            list.replaceChildren();
            return;
          }
          const messages = [
            ...result.conflicts.location.map(
              (item) =>
                `${item.location} is already used by ${item.title} · ${item.schedule}`,
            ),
            ...result.conflicts.people.map(
              (item) =>
                `${item.people.join(", ")} is assigned to ${item.title} · ${item.schedule}`,
            ),
          ];
          list.innerHTML = messages
            .map((message) => `<p>${escapeHtml(message)}</p>`)
            .join("");
        } catch {}
      }, 450);
    }
    form.elements.title.addEventListener("input", () => {
      clearError("title");
      readiness();
    });
    academicPeriod.addEventListener("change", () => {
      renderPeriodTeams();
      clearError("academic_period_id");
      updateAudience();
      readiness();
    });
    generalLocation.addEventListener("change", () => {
      fillSpecificLocations();
      syncPrimaryLocation();
      clearError("general_location_id");
      clearError("specific_location_id");
      readiness();
      checkConflicts();
    });
    specificLocation.addEventListener("change", () => {
      syncPrimaryLocation();
      clearError("specific_location_id");
      checkConflicts();
    });
    form
      .querySelector("[data-assignable-users]")
      .addEventListener("change", checkConflicts);
    updateAudience();
    if (audience.value === "specific_students") loadStudents(1);
    syncSchedules();
    readiness();
    form.addEventListener("submit", async (eventObject) => {
      eventObject.preventDefault();
      clearErrors();
      const posterError = validatePoster(poster?.files?.[0]);
      if (posterError) {
        showError("poster", posterError);
        return;
      }
      const readinessState = readiness();
      let customInvalid = false;
      if (!readinessState.audienceReady) {
        showError("audience_type", "Select at least one participant group or student.");
        customInvalid = true;
      }
      if (!readinessState.scheduleReady) {
        showError("attendance_days", "Complete the schedule date and all required attendance times.");
        customInvalid = true;
      }
      const nativeValid = form.reportValidity();
      if (!nativeValid || customInvalid) {
        form
          .querySelector("[data-error-for]:not(.hidden)")
          ?.scrollIntoView({ behavior: "smooth", block: "center" });
        return;
      }
      save.disabled = true;
      const body = new FormData(form);
      if (audience.value === "specific_students") {
        selectedParticipantIds.forEach((id) =>
          body.append("participant_ids[]", String(id)),
        );
      }
      body.append("action", event ? "update" : "create");
      if (event) body.append("id", event.id);
      try {
        const response = await axios.post("api/adviser-events.php", body, {
          headers: { "X-CSRF-Token": csrf },
        });
        onSaved?.(response.data);
      } catch (error) {
        const response = error.response?.data;
        showErrors(response?.errors, response?.message);
      } finally {
        readiness();
      }
    });
    return { readiness, clearErrors };
  }
  window.EventForm = { mount, escapeHtml, initials };
})();
