'use strict';

const assert = require('node:assert/strict');
const {chromium} = require('playwright');
const base = process.env.CITE_BASE_URL || 'http://localhost/ITEventManagement';
const adviser = {id: 1, role: 'SBO Adviser', username: 'adviser', full_name: 'SBO Adviser', must_change_password: false};
const activities = [
  {id: 11, name: 'Quiz Bee', description: '', status: 'active', schedule_date: '2026-09-23'},
  {id: 12, name: 'Dance', description: '', status: 'active', schedule_date: '2026-09-24'},
];

(async () => {
  const browser = await chromium.launch({headless: true, executablePath: process.env.CHROME_PATH || 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe', args: ['--no-sandbox']});
  try {
    const page = await browser.newPage({viewport: {width: 390, height: 844}});
    const errors = [];
    const rules = new Map();
    page.on('pageerror', error => errors.push(error.message));
    await page.route('**/api/**', route => {
      const url = new URL(route.request().url());
      let payload = {success: true, data: {}};
      if (url.pathname.endsWith('/auth.php')) payload = {success: true, authenticated: true, user: adviser, csrf_token: 'test'};
      if (url.pathname.endsWith('/activity-scoring.php')) {
        if (route.request().method() === 'POST') {
          const input = route.request().postDataJSON();
          if (url.searchParams.get('action') === 'placement-rules-save') rules.set(input.activity_id, input.placement_rules);
          payload = {success: true, message: 'Placement points saved.'};
        } else {
          const selected = activities.find(item => item.id === Number(url.searchParams.get('activity_id'))) || activities[0];
          const placement = rules.get(selected.id) || {};
          payload = {success: true, data: {event: {id: 15, title: 'IT Days'}, activities, selected_activity: selected,
            teams: [{id: 1, name: 'Green Falcons', color: '#397565'}], raw_scores: {},
            placement_rules: Object.entries(placement).map(([place, points]) => ({placement: Number(place), points: Number(points)})),
            results: [], finalized: false}};
        }
      }
      return route.fulfill({json: payload});
    });

    await page.goto(`${base}/pages/adviser/score-configuration.html?event_id=15`, {waitUntil: 'domcontentloaded'});
    await page.getByRole('heading', {name: 'Quiz Bee'}).waitFor();
    assert.equal(await page.locator('[data-activity-schedule] option').count(), 3);
    await page.locator('[data-activity-schedule]').selectOption('2026-09-24');
    assert.equal(await page.locator('[data-activity-results] button').count(), 1);
    await page.locator('[data-activity-schedule]').selectOption('');

    for (const [id, points] of [[11, '10'], [12, '20']]) {
      if (id === 12) await page.locator('[data-activity-id="12"]').click();
      await page.locator('[data-configure-awards]').click();
      await page.locator('[data-rule-points]').fill(points);
      await page.getByRole('button', {name: 'Save awards'}).click();
      await page.locator('[data-awards-dialog]').waitFor({state: 'hidden'});
      assert.equal(await page.getByRole('button', {name: 'Save awards', includeHidden: true}).isEnabled(), true, 'Saving one activity leaves the awards button reusable');
      assert.equal(await page.locator('[data-configuration] h2').textContent(), id === 11 ? 'Quiz Bee' : 'Dance');
      assert.equal(new URL(page.url()).searchParams.get('activity_id'), String(id));
    }
    assert.deepEqual([...rules.entries()].map(([id, points]) => [id, points['1']]), [[11, '10'], [12, '20']]);
    assert.ok(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1), 'Score page fits a phone viewport');
    assert.deepEqual(errors, []);
    await page.close();

    const roster = await browser.newPage({viewport: {width: 390, height: 844}});
    const rosterErrors = [];
    roster.on('pageerror', error => rosterErrors.push(error.message));
    await roster.route('**/api/**', route => {
      const url = new URL(route.request().url());
      let payload = {success: true, data: {}};
      if (url.pathname.endsWith('/auth.php')) payload = {success: true, authenticated: true, user: adviser, csrf_token: 'test'};
      if (url.pathname.endsWith('/attendance.php')) payload = {success: true, data: {
        event: {id: 15, title: 'IT Days', location: 'Campus', start_at: '2026-09-23 08:00:00', end_at: '2026-09-23 17:00:00'},
        event_options: [{id: 15, title: 'IT Days', start_at: '2026-09-23 08:00:00'}], attendance_dates: ['2026-09-23'], attendance_date: '2026-09-23',
        participants: [{id: 9, id_number: '21-001', first_name: 'Hannah', last_name: 'Cubillan', full_name: 'Hannah Cubillan',
          is_expected: true, attendance_status: 'present', evidence_status: 'present', manual_status: null, has_scan_evidence: true,
          time_in_at: null, time_out_at: '2026-09-23 17:10:00', checked_in_at: null, team_names: 'Green Falcons', year_level_label: 'Fourth Year'}],
        participant_total: 1, counts: {present: 1, absent: 0}, summary: {expected: 1, recorded: 1, unrecorded: 0},
        pagination: {current_page: 1, last_page: 1, total: 1, from: 1, to: 1},
      }};
      return route.fulfill({json: payload});
    });
    await roster.goto(`${base}/pages/adviser/attendance-roster.html?event_id=15`, {waitUntil: 'domcontentloaded'});
    await roster.locator('[data-roster-rows] tr').waitFor();
    assert.equal(await roster.locator('th', {hasText: 'Time in'}).count(), 1);
    assert.equal(await roster.locator('th', {hasText: 'Time out'}).count(), 1);
    const cells = await roster.locator('[data-roster-rows] tr td').allTextContents();
    assert.ok(cells[3].includes('—'), 'A missing time-in remains blank');
    assert.ok(cells[4].includes('5:10'), 'Time-out scan is shown even without a time-in');
    assert.ok(await roster.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1), 'Roster table scrolls inside the phone viewport');
    assert.deepEqual(rosterErrors, []);
    console.log('PASS: schedule filter, scoring two activities without refresh, and independent time-out column');
  } finally { await browser.close(); }
})().catch(error => { console.error(error); process.exitCode = 1; });
