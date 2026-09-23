(() => {
  'use strict';

  const API = 'api/sbo-scores.php';
  const assignmentSearch = document.querySelector('[data-assignment-search]');
  const assignmentSchedule = document.querySelector('[data-assignment-schedule]');
  const assignmentResults = document.querySelector('[data-assignment-results]');
  const mobileAssignment = document.querySelector('[data-assignment-mobile]');
  const grid = document.querySelector('[data-score-grid]');
  const form = document.querySelector('[data-score-form]');
  const saveButton = form.querySelector('[data-save-score]');
  const esc = value => SboPortal.escapeHtml(value);
  let csrf = '', state = null, saving = false;

  function assignmentLabel(assignment) {
    return `${assignment.event_name} - ${assignment.activity_name}`;
  }

  function scheduleLabel(value) {
    if (!value) return 'Unscheduled';
    const date = new Date(`${value}T12:00:00`);
    return Number.isNaN(date.getTime()) ? value : new Intl.DateTimeFormat('en-PH', {month: 'short', day: 'numeric', year: 'numeric'}).format(date);
  }

  function renderAssignmentResults() {
    const search = assignmentSearch.value.trim().toLocaleLowerCase();
    const matches = state.assignments.filter(assignment => assignmentLabel(assignment).toLocaleLowerCase().includes(search) && (!assignmentSchedule.value || (assignment.schedule_date || 'unscheduled') === assignmentSchedule.value));
    assignmentResults.innerHTML = matches.length
      ? matches.map(assignment => `<button class="min-h-11 rounded-xl border px-4 py-3 text-left text-xs font-black transition ${assignment.id === state.selected?.id ? 'border-[#397565] bg-[#397565] text-white' : 'border-[#121017]/10 bg-white text-[#121017] hover:border-[#397565]/35'}" type="button" data-assignment-id="${assignment.id}">${esc(assignment.activity_name)}</button>`).join('')
      : '<p class="py-3 text-xs text-[#121017]/45">No assignments match these filters.</p>';
  }

  function render() {
    mobileAssignment.innerHTML = state.assignments.length
      ? state.assignments.map(assignment => `<option value="${assignment.id}">${esc(assignment.activity_name)}</option>`).join('')
      : '<option value="">No scoring assignment</option>';
    if (state.selected) mobileAssignment.value = state.selected.id;
    const selectedSchedule = assignmentSchedule.value;
    const schedules = [...new Set(state.assignments.map(assignment => assignment.schedule_date || 'unscheduled'))].sort((left, right) => left === 'unscheduled' ? 1 : right === 'unscheduled' ? -1 : left.localeCompare(right));
    assignmentSchedule.replaceChildren(new Option('All scheduled dates', ''), ...schedules.map(value => new Option(scheduleLabel(value), value)));
    assignmentSchedule.value = schedules.includes(selectedSchedule) ? selectedSchedule : '';
    renderAssignmentResults();

    const upcoming = state.selected?.assignment_state === 'upcoming';
    const locked = Boolean(state.finalized) || upcoming;
    const savedTeams = Object.keys(state.raw_scores || {}).length;
    const stage = !state.selected ? 'Unassigned' : upcoming ? 'Upcoming' : state.finalized ? 'Finalized' : savedTeams === state.teams.length ? 'Saved' : 'Ready';
    document.querySelector('[data-score-hero-state]').textContent = stage;
    document.querySelector('[data-score-hero-detail]').textContent = state.selected ? `${state.selected.event_name} - ${state.selected.activity_name}` : 'Ask the adviser for a scoring assignment';
    document.querySelector('[data-score-sheet-title]').textContent = state.selected ? `${state.selected.activity_name} raw scores` : 'Raw score entry';
    document.querySelector('[data-score-sheet-status]').textContent = state.selected ? `${state.teams.length} tribes - ${stage}` : 'No assignment';
    saveButton.closest('footer').classList.toggle('hidden', !state.selected || locked);

    if (!state.selected) {
      grid.innerHTML = '<div class="sbo-workspace-empty"><span aria-hidden="true">◇</span><strong>No score sheet assigned</strong><p>Ask the SBO Adviser to assign an activity before entering a raw score.</p></div>';
      return;
    }

    const note = upcoming
      ? 'Upcoming assignment - raw-score entry opens when the event begins.'
      : state.finalized
        ? 'Finalized - placement points have been locked by the adviser.'
        : 'Enter a whole-number raw score for every tribe. The adviser will rank all tribes and award placement points.';
    const rows = state.teams.map(team => `<tr class="border-b border-[#121017]/8 last:border-0"><th class="p-4 text-left" scope="row"><span class="mr-3 inline-block h-3 w-3 rounded-full" style="background:${esc(team.color || '#397565')}"></span>${esc(team.name)}</th><td class="p-3 text-right"><input class="h-11 w-32 rounded-xl border border-[#121017]/12 px-3 text-right font-black outline-none focus:border-[#397565]" type="text" inputmode="numeric" maxlength="3" autocomplete="off" data-raw-score data-team="${team.id}" value="${state.raw_scores[team.id] ?? ''}" placeholder="0" aria-label="Raw score for ${esc(team.name)}" ${locked ? 'disabled' : 'required'}></td></tr>`).join('');
    grid.innerHTML = `<div class="overflow-x-auto"><table class="w-full min-w-[460px] text-sm"><thead class="bg-[#F3F0E9]/55 text-left text-[10px] font-black uppercase tracking-wide text-[#121017]/45"><tr><th class="p-4">Tribe</th><th class="p-4 text-right">Raw score (0-200)</th></tr></thead><tbody>${rows}</tbody></table></div><p class="px-5 py-4 text-sm text-[#121017]/55">${note}</p>`;
    grid.querySelectorAll('[data-raw-score]').forEach(input => {
      input.addEventListener('beforeinput', event => { if (event.data && !/^\d+$/.test(event.data)) event.preventDefault(); });
      input.addEventListener('input', () => {
        input.value = input.value.replace(/\D/g, '');
        input.setCustomValidity(input.value !== '' && Number(input.value) <= 200 ? '' : 'Enter a whole number from 0 to 200.');
      });
    });
  }

  async function load(assignmentId) {
    state = (await axios.get(API, {params: assignmentId ? {assignment_id: assignmentId} : {}})).data.data;
    render();
  }

  assignmentSearch.addEventListener('input', renderAssignmentResults);
  assignmentSchedule.addEventListener('change', renderAssignmentResults);
  assignmentResults.addEventListener('click', event => {
    const button = event.target.closest('[data-assignment-id]');
    if (button) load(Number(button.dataset.assignmentId));
  });
  mobileAssignment.addEventListener('change', () => load(Number(mobileAssignment.value)));
  form.addEventListener('submit', async event => {
    event.preventDefault();
    if (saving || !state?.selected || !form.reportValidity()) return;
    const scores = {};
    grid.querySelectorAll('[data-raw-score]').forEach(input => { scores[input.dataset.team] = input.value; });
    saving = true;
    saveButton.disabled = true;
    try {
      const response = await axios.post(API, {assignment_id: state.selected.id, scores}, {headers: {'X-CSRF-Token': csrf}});
      Notifications.success(response.data.message);
      await load(state.selected.id);
    } catch (error) {
      Notifications.error(error.response?.data?.message || 'Unable to save the raw score.');
    } finally {
      saving = false;
      saveButton.disabled = false;
    }
  });

  SboPortal.initialize('scores').then(context => {
    csrf = context.csrfToken;
    return load();
  });
})();
