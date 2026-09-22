'use strict';

const {chromium} = require('playwright');
const assert = require('node:assert/strict');
const path = require('node:path');

const base = process.env.CITE_BASE_URL || 'http://localhost/ITEventManagement';
const team = {
  id: 1, name: 'Titan Slayers', color: '#397565', school_year: '2026–2027',
  members_count: 32, rank: 2, total_score: 48, attendance_total: 63,
  attendance: [{status: 'present', total: 61}, {status: 'late', total: 2}],
  member_preview: [{id_number: '2026-001', name: 'Michaella Jane Calunsag', year_level: 'Fourth Year'}],
  scores: [{event_title: 'CITE Week', category: 'Innovation', points: 48}],
  activities: [{title: 'CITE Week', start_at: '2099-09-24 08:00:00', location: 'Gymnasium'}],
  announcements: [{content: 'Team assembly at the gymnasium.', created_at: '2026-09-23 09:00:00'}],
};
const student = {id: 10, id_number: '2026-001', name: 'Michaella Jane Calunsag', year_level: 'Fourth Year', team: team.name, attendance_count: 3};
const events = [{id: 1, title: 'CITE Week', end_at: '2099-09-25 17:00:00'}, {id: 2, title: 'Last Year Games', end_at: '2020-09-25 17:00:00'}];
const rankings = [
  {id: 2, name: 'Spy X Family', rank: 1, color: '#c46938', total_score: 72, members_count: 30},
  {id: 1, name: team.name, rank: 2, color: team.color, total_score: 48, members_count: 32},
];
const assignment = {
  id: 11, event_id: 1, event_name: 'CITE Week', scanner_team_name: team.name,
  team_name: team.name, scanner_mode: 'specific', day_number: 1,
  schedule_date: '2099-09-24', session_name: 'Morning Session',
  session_start: '08:00:00', session_end: '12:00:00',
  assignment_state: 'upcoming', is_session_active: false,
  in_window_open: false, out_window_open: false,
  location_policy: 'none', venues: [],
};

const respond = async (route, empty = false) => {
  const url = new URL(route.request().url());
  const page = url.searchParams.get('page');
  let payload = {success: true, data: {unread_notifications: 0, notifications: []}};
  if (url.pathname.endsWith('/auth.php')) payload = {success: true, authenticated: true, csrf_token: 'test', user: {
    id: 1, role: 'Faculty', first_name: 'Test', last_name: 'Faculty', full_name: 'Test Faculty', must_change_password: false,
  }};
  if (url.pathname.endsWith('/faculty.php')) {
    if (page === 'students') payload = {success: true, data: {
      team, events: {1: 'CITE Week'}, students: [student],
      pagination: {current_page: 1, last_page: 1, from: 1, to: 1, total: 1},
    }};
    if (page === 'student_history') payload = {success: true, data: {
      records: [{event_title: 'CITE Week', date: '2026-09-23', status: 'present', morning_in_at: '08:00:00'}],
      pagination: {current_page: 1, last_page: 1},
    }};
    if (page === 'team') payload = {success: true, data: {team}};
    if (page === 'leaderboard') payload = {success: true, data: {
      events, categories: [{id: 4, name: 'Innovation'}], rankings,
      team_id: team.id, summary: {ranked_teams: 2, points: 120},
    }};
    if (page === 'profile') payload = {success: true, data: {
      first_name: 'Test', middle_name: '', last_name: 'Faculty', username: 'test-faculty',
      email: 'faculty@example.test', bio: 'Helping CITE students grow.', profile_photo_path: null,
    }};
    if (page === 'attendance') payload = {success: true, data: {
      assignments: [assignment], selected_assignment: assignment, next_session: null,
      recent_scans: [], counts: {total: 0, checked_out: 0, remaining: 32},
    }};
    if (empty && (page === 'students' || page === 'team')) payload = {success: true, data: {team: null}};
    if (empty && page === 'leaderboard') payload = {success: true, data: {
      events: [], categories: [], rankings: [], team_id: null,
      summary: {ranked_teams: 0, points: 0},
    }};
    if (empty && page === 'attendance') payload = {success: true, data: {
      assignments: [], selected_assignment: null, next_session: null,
      recent_scans: [], counts: {total: 0, checked_out: 0, remaining: 0},
    }};
  }
  await route.fulfill({json: payload});
};

(async () => {
  const browser = await chromium.launch({channel: 'chrome', headless: true});
  try {
    for (const width of [320, 390, 768, 1280]) {
      const context = await browser.newContext({viewport: {width, height: 800}});
      const tab = await context.newPage();
      const errors = [];
      tab.on('pageerror', error => errors.push(error.message));
      await tab.route('**/api/**', route => respond(route));
      for (const name of ['students', 'leaderboard', 'team', 'attendance', 'profile']) {
        await tab.goto(`${base}/pages/faculty/${name}.html`);
        if (name === 'attendance') await tab.locator('[data-attendance-workspace]').waitFor({state: 'visible'});
        else await tab.locator('[data-faculty-content] .faculty-panel, [data-faculty-content] > section').first().waitFor({state: 'visible'});
        const layout = await tab.evaluate(() => ({
          scrollWidth: document.documentElement.scrollWidth,
          screenWidth: document.documentElement.clientWidth,
          stage: document.querySelector('.faculty-stage')?.getBoundingClientRect().toJSON(),
        }));
        assert.ok(layout.scrollWidth <= layout.screenWidth + 1, `${name} overflows at ${width}px`);
        assert.ok(!layout.stage || layout.stage.left >= -1 && layout.stage.right <= width + 1, `${name} hero fits at ${width}px`);
        if (name === 'attendance' && width === 1280) assert.ok(layout.stage.width > 1000, 'attendance hero uses the same content width as the scanner workspace');
        assert.deepEqual(errors, [], `${name} has no browser errors at ${width}px`);
        if (name === 'students') {
          await tab.locator('[data-student-id] summary').click();
          await tab.locator('.faculty-history-record').waitFor();
          assert.match(await tab.locator('.faculty-history-record').textContent(), /CITE Week/);
        }
        if (name === 'leaderboard') {
          assert.equal(await tab.locator('.faculty-ranking-list li').count(), 2);
          await tab.locator('[data-past]').click();
          await tab.locator('[data-event="2"]').click();
          assert.match(await tab.locator('.faculty-board-filter-row').textContent(), /Last Year Games/);
        }
        if (name === 'profile') assert.equal(await tab.locator('[data-profile] input[name="email"]').inputValue(), 'faculty@example.test');
        if (process.env.CITE_FACULTY_SCREENSHOT_DIR && width === Number(process.env.CITE_FACULTY_SCREENSHOT_WIDTH || 390)) {
          await tab.screenshot({path: path.join(process.env.CITE_FACULTY_SCREENSHOT_DIR, `faculty-${name}.png`), fullPage: true});
        }
      }
      await context.close();
      console.log(`PASS: five faculty pages fit ${width}px; roster, standings, and profile interactions work`);
    }
    const emptyContext = await browser.newContext({viewport: {width: 320, height: 740}});
    const emptyTab = await emptyContext.newPage();
    const emptyErrors = [];
    emptyTab.on('pageerror', error => emptyErrors.push(error.message));
    await emptyTab.route('**/api/**', route => respond(route, true));
    for (const name of ['students', 'leaderboard', 'team', 'attendance']) {
      await emptyTab.goto(`${base}/pages/faculty/${name}.html`);
      if (name === 'attendance') await emptyTab.locator('[data-no-assignment]').waitFor({state: 'visible'});
      else await emptyTab.locator('[data-faculty-content]').getByText(name === 'leaderboard' ? 'The field is open' : 'No team is assigned', {exact: false}).waitFor();
      assert.ok(await emptyTab.evaluate(() => document.documentElement.scrollWidth <= document.documentElement.clientWidth + 1), `${name} empty state fits 320px`);
      assert.deepEqual(emptyErrors, [], `${name} empty state has no browser errors`);
    }
    await emptyContext.close();
    console.log('PASS: unassigned and no-score states fit 320px without browser errors');
  } finally {
    await browser.close();
  }
})().catch(error => {console.error(error); process.exitCode = 1;});
