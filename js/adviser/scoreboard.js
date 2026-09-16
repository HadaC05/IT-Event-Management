window.SharedNavigation.ready.then(() => {
  "use strict";

  const $ = (selector, root = document) => root.querySelector(selector);
  const $$ = (selector, root = document) => [
    ...root.querySelectorAll(selector),
  ];
  const eventId = Number(new URLSearchParams(location.search).get("event_id"));
  const criteriaSection = $("[data-criteria-section]");
  const matrixSection = $("[data-matrix-section]");
  let csrfToken = "";
  let state = null;
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
  const formatScore = (value) =>
    Number(value)
      .toFixed(2)
      .replace(/\.00$/, "")
      .replace(/(\.\d)0$/, "$1");
  const dateValue = (value) => new Date(String(value).replace(" ", "T"));

  function initializeShell() {
    const sidebar = $("#sidebar");
    const scrim = $("[data-sidebar-scrim]");
    $$("[data-sidebar-toggle]").forEach((button) => {
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
    $$("[data-dialog-close]").forEach((button) => {
      button.onclick = () => button.closest("dialog").close();
    });
  }

  function applyAccount(user) {
    const account = $("[data-account-menu]");
    const name = user.full_name || user.username;
    account.querySelector("summary > span:first-child").textContent =
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
    csrfToken = response.data.csrf_token;
    applyAccount(response.data.user);
    $("form[action='api/auth.php?action=logout']").onsubmit = async (event) => {
      event.preventDefault();
      try {
        await axios.post(
          "api/auth.php?action=logout",
          {},
          { headers: { "X-CSRF-Token": csrfToken } },
        );
      } finally {
        location.replace("./");
      }
    };
  }

  async function post(action, payload) {
    return axios.post(
      `api/scores.php?action=${action}`,
      { event_id: eventId, ...payload },
      { headers: { "X-CSRF-Token": csrfToken } },
    );
  }

  function setFieldError(form, field, message = "") {
    const input = form.elements[field];
    const error = $(`[data-error="${field}"]`, form);
    input?.classList.toggle("border-red-500", Boolean(message));
    if (error) {
      error.textContent = message;
      error.classList.toggle("hidden", !message);
    }
  }

  function validateCategory(form) {
    setFieldError(form, "name");
    setFieldError(form, "max_points");
    const name = form.elements.name.value.trim();
    const rawMaximum = form.elements.max_points.value;
    const maximum = Number(rawMaximum);
    let valid = true;
    if (!name) {
      setFieldError(form, "name", "Criterion name is required.");
      valid = false;
    } else if (name.length > 80) {
      setFieldError(
        form,
        "name",
        "Criterion name may not exceed 80 characters.",
      );
      valid = false;
    }
    if (!rawMaximum || !Number.isFinite(maximum) || maximum <= 0) {
      setFieldError(form, "max_points", "Enter a maximum greater than zero.");
      valid = false;
    } else if (maximum > 1000000 || !/^\d+(?:\.\d{1,2})?$/.test(rawMaximum)) {
      setFieldError(
        form,
        "max_points",
        "Use at most two decimal places and no more than 1,000,000.",
      );
      valid = false;
    }
    return valid;
  }

  function renderHeader() {
    const event = state.event;
    document.title = `${event.title} Scores | CITE Events`;
    $("[data-event-title]").textContent = event.title;
    $("[data-event-state]").textContent =
      event.schedule_state[0].toUpperCase() + event.schedule_state.slice(1);
    $("[data-event-date]").textContent = new Intl.DateTimeFormat("en-PH", {
      weekday: "short",
      month: "short",
      day: "numeric",
      year: "numeric",
      hour: "numeric",
      minute: "2-digit",
    }).format(dateValue(event.start_at));
    $("[data-event-location]").textContent =
      event.location || "Venue not specified";
    const switcher = $("[data-event-switch]");
    switcher.innerHTML = state.event_options
      .map(
        (option) =>
          `<option value="${option.id}" ${option.id === event.id ? "selected" : ""}>${escapeHtml(option.title)} · ${new Intl.DateTimeFormat("en-PH", { month: "short", day: "numeric" }).format(dateValue(option.start_at))}</option>`,
      )
      .join("");
    switcher.onchange = () =>
      location.assign(
        `pages/adviser/scoreboard.html?event_id=${switcher.value}`,
      );
    $("[data-summary-categories]").textContent = state.summary.categories;
    $("[data-summary-teams]").textContent = state.summary.teams;
    $("[data-completion]").textContent =
      state.summary.completion === null
        ? "Not configured"
        : `${state.summary.completion}%`;
    $("[data-completion-bar]").style.width =
      `${state.summary.completion || 0}%`;
    $("[data-entry-count]").textContent = state.summary.entries;
    $("[data-grand-total]").textContent = formatScore(
      state.summary.total_points,
    );
    if (state.categories.length) {
      $("[data-step-one]").textContent = "✓";
      $("[data-step-two]").className =
        "grid h-7 w-7 place-items-center rounded-full bg-[#397565] text-[10px] font-black text-white";
    }
    if (state.summary.entries)
      $("[data-step-three]").className =
        "grid h-7 w-7 place-items-center rounded-full bg-[#2F3AE0] text-[10px] font-black text-white";
  }

  function categoryFields(prefix = "") {
    return `<label class="grid gap-2"><span class="text-xs font-extrabold">Criterion name</span><input class="h-12 rounded-xl border border-[#121017]/12 bg-white px-4 text-sm outline-none focus:border-[#397565]" name="name" maxlength="80" placeholder="e.g. Creativity" required /><small class="hidden text-[10px] text-red-600" data-error="name"></small></label><label class="grid gap-2"><span class="text-xs font-extrabold">Maximum points</span><input class="h-12 rounded-xl border border-[#121017]/12 bg-white px-4 text-sm font-bold outline-none focus:border-[#397565]" type="number" name="max_points" value="100" min="0.01" max="1000000" step="0.01" required /><small class="hidden text-[10px] text-red-600" data-error="max_points"></small></label>`;
  }

  function bindCategoryForm(form, action) {
    form.onsubmit = async (event) => {
      event.preventDefault();
      if (!validateCategory(form)) return;
      const submit = $("button[type='submit']", form);
      submit.disabled = true;
      try {
        const payload = Object.fromEntries(new FormData(form));
        const response = await post(action, payload);
        window.Notifications?.success?.(response.data.message);
        $("[data-category-dialog]")?.close();
        await load();
      } catch (error) {
        const message =
          error.response?.data?.message ||
          "The scoring criterion could not be saved.";
        const target = /maximum|points/i.test(message)
          ? "max_points"
          : /criterion|name|already/i.test(message)
            ? "name"
            : null;
        if (target) setFieldError(form, target, message);
        else window.Notifications?.error?.(message);
      } finally {
        submit.disabled = false;
      }
    };
  }

  function renderCriteria() {
    if (!state.categories.length) {
      criteriaSection.innerHTML = `<div class="grid overflow-hidden rounded-2xl border border-[#121017]/10 bg-white/70 lg:grid-cols-[minmax(0,.8fr)_minmax(420px,1.2fr)]"><div class="border-b border-[#121017]/8 p-6 sm:p-8 lg:border-b-0 lg:border-r"><p class="text-[10px] font-black uppercase tracking-[.16em] text-[#FF6B2C]">Start here</p><h2 class="mt-3 text-2xl font-black">Define how this event is judged.</h2><p class="mt-3 text-sm leading-6 text-[#121017]/55">Add the first criterion and its highest possible score. You can add more criteria before entering tribe results.</p><p class="mt-5 text-[10px] font-bold text-[#397565]">Examples: Performance, Creativity, Sportsmanship</p></div><form class="p-6 sm:p-8" data-create-category><div class="grid gap-4 sm:grid-cols-[minmax(0,1fr)_150px]">${categoryFields()}</div><button class="mt-5 min-h-12 w-full rounded-xl bg-[#397565] px-5 text-sm font-black text-white" type="submit">Add First Criterion →</button></form></div>`;
    } else {
      const rows = state.categories
        .map(
          (category, index) =>
            `<tr><td class="px-6 py-3.5"><div class="flex items-center gap-3"><span class="grid h-7 w-7 place-items-center rounded-lg bg-[#397565]/10 text-[10px] font-black text-[#397565]">${index + 1}</span><strong class="text-sm">${escapeHtml(category.name)}</strong></div></td><td class="px-4 py-3.5 text-xs font-black">${formatScore(category.max_points)} <span class="font-semibold text-[#121017]/35">pts</span></td><td class="px-4 py-3.5 text-[10px] font-bold text-[#121017]/50">● ${category.scores_count} ${category.scores_count === 1 ? "entry" : "entries"}</td><td class="px-6 py-3.5"><div class="flex justify-end gap-2"><button class="min-h-10 rounded-xl border border-[#2F3AE0]/25 bg-[#2F3AE0]/8 px-4 text-xs font-black text-[#2F3AE0]" type="button" data-edit-category="${category.id}">Edit</button><button class="min-h-10 rounded-xl border border-[#FF6B2C]/25 bg-[#FF6B2C]/10 px-4 text-xs font-black text-[#d9470a]" type="button" data-delete-category="${category.id}">Remove</button></div></td></tr>`,
        )
        .join("");
      criteriaSection.innerHTML = `<div class="overflow-hidden rounded-2xl border border-[#121017]/10 bg-white/70"><header class="flex items-end justify-between border-b px-6 py-5"><div><p class="text-[10px] font-black uppercase tracking-[.16em] text-[#397565]">Scoring rules</p><h2 class="mt-1 text-xl font-black">${state.categories.length} configured ${state.categories.length === 1 ? "criterion" : "criteria"}</h2></div><p class="text-[10px] font-bold text-[#121017]/40">${formatScore(state.summary.maximum)} maximum points per tribe</p></header><div class="overflow-x-auto"><table class="w-full min-w-[620px]"><thead class="bg-[#121017]/[.025] text-left text-[9px] font-black uppercase text-[#121017]/40"><tr><th class="px-6 py-3">Criterion</th><th class="px-4 py-3">Maximum</th><th class="px-4 py-3">Recorded</th><th class="px-6 py-3 text-right">Actions</th></tr></thead><tbody class="divide-y">${rows}</tbody></table></div><form class="grid gap-3 border-t bg-[#121017]/[.025] p-4 sm:grid-cols-[minmax(220px,1fr)_150px_auto]" data-create-category>${categoryFields()}<button class="min-h-11 rounded-xl bg-[#397565] px-5 text-xs font-black text-white" type="submit">+ Add Criterion</button></form></div>`;
    }
    const form = $("[data-create-category]");
    if (form) bindCategoryForm(form, "category-create");
    $$("[data-edit-category]").forEach((button) => {
      button.onclick = () => openEdit(Number(button.dataset.editCategory));
    });
    $$("[data-delete-category]").forEach((button) => {
      button.onclick = () =>
        removeCategory(Number(button.dataset.deleteCategory));
    });
  }

  function openEdit(categoryId) {
    const category = state.categories.find((item) => item.id === categoryId);
    const dialog = $("[data-category-dialog]");
    const form = $("[data-category-form]");
    form.elements.category_id.value = category.id;
    form.elements.name.value = category.name;
    form.elements.max_points.value = formatScore(category.max_points);
    $("[data-dialog-title]").textContent = category.name;
    bindCategoryForm(form, "category-update");
    dialog.showModal();
  }

  async function removeCategory(categoryId) {
    const category = state.categories.find((item) => item.id === categoryId);
    const accepted = window.Notifications?.confirm
      ? await window.Notifications.confirm({
          title: "Remove criterion?",
          message: `${category.name} and its ${category.scores_count} score entries will be permanently removed.`,
          action: "Remove criterion",
        })
      : confirm(`Remove ${category.name}? Its ${category.scores_count} score entries will be permanently removed.`);
    if (!accepted) return;
    try {
      const response = await post("category-delete", {
        category_id: categoryId,
      });
      window.Notifications?.success?.(response.data.message);
      await load();
    } catch (error) {
      window.Notifications?.error?.(
        error.response?.data?.message || "The criterion could not be removed.",
      );
    }
  }

  function renderMatrix() {
    if (!state.categories.length) {
      matrixSection.innerHTML =
        '<div class="flex items-center gap-4 border-y border-[#121017]/10 py-5 text-[#121017]/45"><span class="grid h-9 w-9 place-items-center rounded-full bg-[#121017]/6 text-xs font-black">2</span><div><h2 class="text-sm font-black">Score entry unlocks after criteria are configured</h2><p class="text-[10px]">Use the form above to add the first judging criterion.</p></div></div>';
      return;
    }
    if (!state.teams.length) {
      matrixSection.innerHTML =
        '<div class="rounded-2xl border border-[#FF6B2C]/20 bg-[#FF6B2C]/[.06] p-6"><h2 class="text-sm font-black">No active tribes are available</h2><p class="mt-1 text-xs text-[#121017]/50">Create or activate a tribe before recording event results.</p><a class="mt-4 inline-flex min-h-10 items-center rounded-xl bg-[#2F3AE0] px-4 text-xs font-black text-white" href="pages/adviser/teams.html">Manage Tribes</a></div>';
      return;
    }
    const heads = state.categories
      .map(
        (category) =>
          `<th class="min-w-36 px-3 py-3.5"><span class="block">${escapeHtml(category.name)}</span><small class="text-[#FF6B2C]">0–${formatScore(category.max_points)} pts</small></th>`,
      )
      .join("");
    const rows = state.teams
      .map((team) => {
        const inputs = state.categories
          .map((category) => {
            const value = state.scores[`${category.id}-${team.id}`] ?? "";
            return `<td class="px-3 py-3"><input class="h-10 w-full rounded-lg border border-[#121017]/10 px-3 text-right text-sm font-black invalid:border-[#FF6B2C]" type="number" min="0" max="${category.max_points}" step="0.01" placeholder="—" value="${value}" data-score-input data-category="${category.id}" data-team="${team.id}" data-original-score="${value}" aria-label="${escapeHtml(category.name)} score for ${escapeHtml(team.name)}" /><small class="hidden text-[9px] text-red-600" data-score-error></small></td>`;
          })
          .join("");
        return `<tr class="group" data-team-row data-team-name="${escapeHtml(team.name.toLowerCase())}"><td class="sticky left-0 z-10 bg-white px-6 py-3.5"><div class="flex items-center gap-3"><span class="grid h-8 w-8 place-items-center rounded-full bg-[#121017]/6 text-[10px] font-black" data-rank>${state.summary.entries ? team.rank : "—"}</span><span class="h-8 w-1 rounded-full" style="background:${escapeHtml(team.color)}"></span><span><strong class="block text-sm">${escapeHtml(team.name)}</strong><small class="text-[9px] text-[#121017]/38">${escapeHtml(team.school_year_label || "School year not set")}${team.is_active ? "" : " · Inactive"}</small></span></div></td>${inputs}<td class="px-6 text-right"><strong class="text-lg font-black" data-team-total>${formatScore(team.event_score_total)}</strong> <small>pts</small></td></tr>`;
      })
      .join("");
    matrixSection.innerHTML = `<div class="overflow-hidden rounded-2xl border border-[#121017]/10 bg-white/80 shadow-[0_18px_55px_rgba(18,16,23,.06)]"><header class="border-b px-5 py-5 sm:px-6"><div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between"><div><p class="text-[10px] font-black uppercase tracking-[.16em] text-[#397565]">Main workspace</p><h2 class="mt-1 text-xl font-black">Tribe Score Matrix</h2><p class="mt-1 text-xs text-[#121017]/45">Enter awarded points below. Blank fields remain unjudged.</p></div><div class="flex gap-2"><p class="inline-flex min-h-10 items-center rounded-xl bg-[#C6F24E]/25 px-3 text-[10px] font-black text-[#397565]" data-save-state>● All changes saved</p><input class="h-10 rounded-xl border bg-[#F3F0E9]/55 px-3 text-xs" type="search" placeholder="Find a tribe…" data-team-search /></div></div></header><form data-score-form><div class="max-h-[62vh] overflow-auto"><table class="w-full border-collapse" style="min-width:${470 + state.categories.length * 154}px"><thead class="sticky top-0 z-20 bg-[#F3F0E9] text-left text-[9px] font-black uppercase text-[#121017]/40"><tr><th class="sticky left-0 z-30 min-w-60 bg-[#F3F0E9] px-6 py-3.5">Rank / Tribe</th>${heads}<th class="min-w-28 px-6 text-right">Total</th></tr></thead><tbody class="divide-y" data-score-body>${rows}<tr class="hidden" data-no-team-results><td class="px-6 py-10 text-center" colspan="${state.categories.length + 2}">No tribes match your search.</td></tr></tbody></table></div><footer class="sticky bottom-0 flex items-center justify-between border-t bg-white/95 px-6 py-4"><div><p class="text-xs font-bold text-[#121017]/45"><strong data-change-count>0</strong> unsaved changes</p><p class="text-[9px] text-[#121017]/35">Maximum ${formatScore(state.summary.maximum)} points per tribe</p></div><div class="flex gap-2"><button class="min-h-11 rounded-xl border px-4 text-xs font-black" type="button" data-reset>Reset</button><button class="min-h-11 rounded-xl bg-[#2F3AE0] px-6 text-xs font-black text-white disabled:bg-[#121017]/15" type="submit" data-save-scores disabled>Save Scores</button></div></footer></form></div>`;
    bindMatrix();
  }

  function bindMatrix() {
    const inputs = $$("[data-score-input]"),
      rows = $$("[data-team-row]"),
      save = $("[data-save-scores]"),
      saveState = $("[data-save-state]");
    const normalize = (value) => (value === "" ? "" : String(Number(value)));
    const refresh = () => {
      let entries = 0,
        total = 0;
      const totals = rows.map((row) => {
        let rowTotal = 0;
        $$("[data-score-input]", row).forEach((input) => {
          const error = $("[data-score-error]", input.parentElement);
          const value = Number(input.value);
          const invalid =
            input.value !== "" &&
            (!Number.isFinite(value) ||
              value < 0 ||
              value > Number(input.max) ||
              !/^\d+(?:\.\d{1,2})?$/.test(input.value));
          error.textContent = invalid
            ? `Use 0–${formatScore(input.max)} with up to 2 decimals.`
            : "";
          error.classList.toggle("hidden", !invalid);
          if (input.value !== "") entries++;
          if (!invalid) rowTotal += value || 0;
        });
        $("[data-team-total]", row).textContent = formatScore(rowTotal);
        total += rowTotal;
        return rowTotal;
      });
      const ordered = [...totals].sort((a, b) => b - a);
      rows.forEach((row, index) => {
        $("[data-rank]", row).textContent = ordered.indexOf(totals[index]) + 1;
      });
      const changed = inputs.filter(
        (input) =>
          normalize(input.value) !== normalize(input.dataset.originalScore),
      ).length;
      $("[data-change-count]").textContent = changed;
      save.disabled = !changed || inputs.some((input) => !input.validity.valid);
      saveState.textContent = changed
        ? `● ${changed} unsaved ${changed === 1 ? "change" : "changes"}`
        : "● All changes saved";
      $("[data-entry-count]").textContent = entries;
      $("[data-grand-total]").textContent = formatScore(total);
      const completion = Math.round((entries / inputs.length) * 1000) / 10;
      $("[data-completion]").textContent = `${completion}%`;
      $("[data-completion-bar]").style.width = `${completion}%`;
    };
    inputs.forEach((input) => input.addEventListener("input", refresh));
    $("[data-team-search]").oninput = (event) => {
      let visible = 0;
      rows.forEach((row) => {
        const match = row.dataset.teamName.includes(
          event.target.value.trim().toLowerCase(),
        );
        row.classList.toggle("hidden", !match);
        if (match) visible++;
      });
      $("[data-no-team-results]").classList.toggle("hidden", Boolean(visible));
    };
    $("[data-reset]").onclick = () => {
      inputs.forEach((input) => (input.value = input.dataset.originalScore));
      refresh();
    };
    $("[data-score-form]").onsubmit = async (event) => {
      event.preventDefault();
      if (!event.currentTarget.reportValidity()) return;
      const scores = {};
      inputs.forEach((input) => {
        scores[input.dataset.category] ??= {};
        scores[input.dataset.category][input.dataset.team] = input.value;
      });
      save.disabled = true;
      try {
        const response = await post("save", { scores });
        window.Notifications?.success?.(response.data.message);
        await load();
      } catch (error) {
        window.Notifications?.error?.(
          error.response?.data?.message || "Scores could not be saved.",
        );
        refresh();
      }
    };
  }

  async function load() {
    const response = await axios.get("api/scores.php", {
      params: { event_id: eventId },
    });
    state = response.data.data;
    renderHeader();
    renderCriteria();
    renderMatrix();
  }

  if (!eventId) {
    location.replace("pages/adviser/scores.html");
    return;
  }
  initializeShell();
  authenticate()
    .then(load)
    .catch((error) => {
      if (error.message !== "Unauthorized") {
        window.Notifications?.error?.(
          error.response?.data?.message ||
            "The event scoreboard could not be loaded.",
        );
        criteriaSection.innerHTML =
          '<p class="p-10 text-center text-red-600">The event scoreboard could not be loaded.</p>';
      }
    });
});
