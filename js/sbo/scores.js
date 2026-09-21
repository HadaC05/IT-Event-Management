(() => {
  'use strict';

  const API = 'api/sbo-scores.php';
  const select = document.querySelector('[data-assignment]');
  const grid = document.querySelector('[data-score-grid]');
  const form = document.querySelector('[data-score-form]');
  let csrf = '', state = null, finalize = false, saving = false;
  const esc = value => SboPortal.escapeHtml(value);

  const total = team => state.categories.reduce((sum, category) =>
    sum + Number(form.elements[`score_${team.id}_${category.id}`]?.value || 0), 0);

  const syncTotals = () => state?.teams.forEach(team => {
    const cell = grid.querySelector(`[data-total="${team.id}"]`);
    if (cell) cell.textContent = total(team).toFixed(2);
  });

  function render() {
    select.innerHTML = state.assignments.length
      ? state.assignments.map(assignment => `<option value="${assignment.id}">${assignment.assignment_state === 'upcoming' ? 'Upcoming assignment · ' : ''}${esc(assignment.event_name)} · ${esc(assignment.session_name)} · ${esc(assignment.team_name)} · ${esc(assignment.activity_name)}</option>`).join('')
      : '<option value="">No scoring assignment</option>';
    if (state.selected) select.value = state.selected.id;

    const upcoming = state.selected?.assignment_state === 'upcoming';
    const locked = state.sheet?.status === 'finalized' || upcoming;
    form.querySelector('footer').classList.toggle('hidden', !state.selected || locked);
    if (!state.selected) {
      grid.innerHTML = '<p class="p-12 text-center text-sm text-[#121017]/45">You currently have no scoring assignment.</p>';
      return;
    }
    if (!state.categories.length) {
      grid.innerHTML = '<p class="p-12 text-center text-sm text-[#121017]/45">The adviser has not created criteria for this activity.</p>';
      return;
    }

    const heading = state.categories.map(category => `<th class="p-4">${esc(category.name)}<small class="block font-normal opacity-70">${category.min_points}–${category.max_points}</small></th>`).join('');
    const rows = state.teams.map(team => `<tr class="border-b border-[#121017]/8">
      <th class="p-4" scope="row">${esc(team.name)}</th>
      ${state.categories.map(category => `<td class="p-3" data-label="${esc(category.name)}">
        <input class="h-11 w-28 rounded-xl border border-[#121017]/12 px-3" type="number" step="0.01"
          min="${category.min_points}" max="${category.max_points}"
          name="score_${team.id}_${category.id}" data-team="${team.id}"
          aria-label="${esc(category.name)} score for ${esc(team.name)}"
          value="${esc(state.scores[team.id]?.[category.id] ?? '')}" ${locked ? 'disabled' : 'required'}>
      </td>`).join('')}
      <td class="p-4 font-black text-[#397565]" data-label="Total" data-total="${team.id}">0.00</td>
    </tr>`).join('');

    grid.innerHTML = `${upcoming ? '<p class="bg-[#C6F24E]/25 p-3 text-center text-xs font-black text-[#397565]">Upcoming assignment — scoring will be available when the event begins.</p>' : locked ? '<p class="bg-[#C6F24E]/25 p-3 text-center text-xs font-black text-[#397565]">Finalized — only the adviser can reopen this score sheet.</p>' : ''}
      <table class="w-full min-w-[760px] text-left"><thead class="bg-[#397565] text-xs text-white"><tr><th class="p-4">Team</th>${heading}<th class="p-4">Total</th></tr></thead><tbody>${rows}</tbody></table>`;
    syncTotals();
  }

  async function load(assignmentId) {
    state = (await axios.get(API, {params: assignmentId ? {assignment_id: assignmentId} : {}})).data.data;
    render();
  }

  select.addEventListener('change', () => load(Number(select.value)));
  grid.addEventListener('input', syncTotals);
  form.querySelectorAll('[data-finalize]').forEach(button => button.addEventListener('click', () => {
    finalize = button.dataset.finalize === 'true';
  }));
  form.addEventListener('submit', async event => {
    event.preventDefault();
    if (saving) return;
    if (finalize && !(await Notifications.confirm({
      title: 'Finalize scores?',
      message: 'You cannot edit these scores again unless the adviser reopens the sheet.',
      action: 'Finalize',
    }))) return;

    const scores = {};
    state.teams.forEach(team => {
      scores[team.id] = {};
      state.categories.forEach(category => {
        scores[team.id][category.id] = form.elements[`score_${team.id}_${category.id}`].value;
      });
    });
    saving = true;
    form.querySelectorAll('button[type="submit"]').forEach(button => { button.disabled = true; });
    try {
      const response = await axios.post(API, {assignment_id: state.selected.id, scores, finalize}, {
        headers: {'X-CSRF-Token': csrf},
      });
      Notifications.success(response.data.message);
      await load(state.selected.id);
    } catch (error) {
      Notifications.error(error.response?.data?.message || 'Unable to save scores.');
    } finally {
      saving = false;
      form.querySelectorAll('button[type="submit"]').forEach(button => { button.disabled = false; });
    }
  });

  SboPortal.initialize('scores').then(context => {
    csrf = context.csrfToken;
    return load();
  });
})();
