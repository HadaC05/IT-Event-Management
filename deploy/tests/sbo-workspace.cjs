'use strict';

const {chromium} = require('playwright');
const assert = require('node:assert/strict');
const os = require('node:os');
const path = require('node:path');

const base = process.env.CITE_BASE_URL || 'http://localhost/ITEventManagement';
const assignment = {
  id: 11, event_id: 1, event_name: 'CITE Week', activity_name: 'Design Challenge',
  team_id: 7, team_name: 'Titan Slayers', scanner_team_name: 'Titan Slayers', scanner_mode: 'specific',
  day_number: 1, schedule_date: '2099-09-24', session_name: 'Morning Session',
  session_start: '08:00:00', session_end: '12:00:00', assignment_state: 'upcoming',
  is_session_active: false, in_window_open: false, out_window_open: false,
  location_policy: 'warning', venues: [], venue_name: 'CITE Hall',
};
const scoring = {...assignment, activity_id: 3, activity_name: 'Design Challenge', selection_id: '11:3', assignment_state: 'current'};
const students = [
  {id: 1, id_number: '2026-001', full_name: 'Michaella Jane Calunsag', year_level: 'Fourth Year', team_name: 'Titan Slayers', attendance_status: 'present', email: 'michaella@example.test'},
  {id: 2, id_number: '2026-002', full_name: 'Brian D. Ragasi', year_level: 'Fourth Year', team_name: 'Titan Slayers', attendance_status: 'not_recorded', email: 'pending.2@pending.invalid'},
];
const media = {viewer: {id: 50, role: 'SBO Officer', full_name: 'Test Officer', initials: 'TO'},
  permissions: {moderate: false, manage_carousel: true, hide: false}, active_events: [], post_events: [],
  carousel_events: [], featured_events: [], event_program: [], own_posts: [], posts: [], next_cursor: null};

async function respond(route, empty) {
  const url = new URL(route.request().url());
  let payload = {success: true, data: {unread_notifications: 0, notifications: []}};
  if (url.pathname.endsWith('/auth.php')) payload = {success: true, authenticated: true, csrf_token: 'test', user: {
    id: 50, role: 'SBO Officer', first_name: 'Test', last_name: 'Officer', full_name: 'Test Officer', must_change_password: false,
  }};
  if (url.pathname.endsWith('/sbo-students.php')) payload = {success: true, data: {
    assignments: empty ? [] : [assignment], selected: empty ? null : assignment, students: empty ? [] : students,
    pagination: {current_page: 1, last_page: 1, from: empty ? null : 1, to: empty ? null : 2, total: empty ? 0 : 2},
  }};
  if (url.pathname.endsWith('/sbo-scores.php')) payload = {success: true, data: {
    assignments: empty ? [] : [scoring], selected: empty ? null : scoring,
    categories: empty ? [] : [{id: 3, name: 'Creativity', min_points: 0, max_points: 50}, {id: 4, name: 'Execution', min_points: 0, max_points: 50}],
    teams: empty ? [] : [{id: 7, name: 'Titan Slayers'}], raw_scores: empty ? {} : {7: 55}, finalized: false,
  }};
  if (url.pathname.endsWith('/sbo-attendance.php')) payload = {success: true, data: {
    assignments: empty ? [] : [assignment], selected_assignment: empty ? null : assignment,
    next_session: null, recent_scans: empty ? [] : [{full_name: 'Michaella Jane Calunsag', id_number: '2026-001', team_name: 'Titan Slayers', venue_name_snapshot: 'CITE Hall', phase: 'in', scanned_at: '2026-09-23 08:00:00', location_status: 'inside'}],
    counts: {total: empty ? 0 : 12, checked_out: empty ? 0 : 3, remaining: empty ? 0 : 20},
  }};
  if (url.pathname.endsWith('/media.php')) payload = {success: true, data: media};
  await route.fulfill({json: payload});
}

(async () => {
  const browser = await chromium.launch({channel: 'chrome', headless: true});
  try {
    for (const width of [320, 390, 768, 1280]) {
      for (const pageName of ['students', 'scores', 'attendance', 'media']) {
        const context = await browser.newContext({viewport: {width, height: 840}, permissions: []});
        const page = await context.newPage();
        const errors = [];
        page.on('pageerror', error => errors.push(error.message));
        await page.route('**/api/**', route => respond(route, false));
        await page.goto(`${base}/pages/sbo/${pageName}.html`, {waitUntil: 'domcontentloaded'});
        if (pageName === 'students') {
          await page.locator('[data-student-total]').getByText('2').waitFor();
          assert.equal(await page.locator('.sbo-student-card').count(), width < 1024 ? 2 : 0);
          assert.equal(await page.locator('.sbo-student-row').count(), width >= 1024 ? 2 : 0);
          assert.equal(await page.getByText('pending.2@pending.invalid').count(), 0);
        }
        if (pageName === 'scores') {
          await page.locator('[data-score-hero-state]').getByText('Saved').waitFor();
          assert.equal(await page.locator('[data-score-grid] input').inputValue(), '55');
          assert.equal(await page.locator('[data-score-grid] input').count(), 1);
          if (width <= 760) assert.equal(await page.locator('[data-score-form] footer').evaluate(element => getComputedStyle(element).position), 'static');
        }
        if (pageName === 'attendance') {
          await page.locator('[data-attendance-hero-state]').getByText('Upcoming').waitFor();
          assert.equal(await page.locator('[data-scan-progress]').getAttribute('aria-valuenow'), '38');
          assert.equal(await page.locator('.sbo-scan-item').count(), 1);
          assert.equal(await page.locator('[data-gps-policy-dialog]').evaluate(dialog => dialog.open), false, 'Upcoming assignments do not demand GPS early');
          assert.equal(await page.locator('[data-gps-toggle]').isVisible(), false, 'Upcoming assignments do not show a premature GPS action');
        }
        if (pageName === 'media') {
          await page.locator('.cite-media-shell > header').waitFor();
          assert.equal(await page.locator('[data-public-feed]').count(), 1);
        }
        const dimensions = await page.evaluate(() => ({scroll: document.documentElement.scrollWidth, visible: document.documentElement.clientWidth,
          offenders: [...document.querySelectorAll('body *')].filter(element => element.getBoundingClientRect().right > document.documentElement.clientWidth + 1).slice(0, 8).map(element => `${element.tagName}.${String(element.className).slice(0, 65)}`)}));
        assert.ok(dimensions.scroll <= dimensions.visible + 1, `${pageName} overflows at ${width}px: ${dimensions.scroll} > ${dimensions.visible}; ${dimensions.offenders.join(', ')}`);
        assert.deepEqual(errors, [], `${pageName} has browser errors at ${width}px`);
        if (process.env.CITE_SCREENSHOTS === '1' && [390, 1280].includes(width)) {
          await page.screenshot({path: path.join(os.tmpdir(), `cite-sbo-${pageName}-${width}.png`), fullPage: true});
        }
        await context.close();
      }
      console.log(`PASS: all four SBO workspaces render at ${width}px without overflow or browser errors`);
    }
    for (const pageName of ['students', 'scores', 'attendance']) {
      const context = await browser.newContext({viewport: {width: 320, height: 840}, permissions: []});
      const page = await context.newPage();
      const errors = [];
      page.on('pageerror', error => errors.push(error.message));
      await page.route('**/api/**', route => respond(route, true));
      await page.goto(`${base}/pages/sbo/${pageName}.html`, {waitUntil: 'domcontentloaded'});
      if (pageName === 'students') await page.getByText('No students to show').waitFor();
      if (pageName === 'scores') await page.getByText('No score sheet assigned').waitFor();
      if (pageName === 'attendance') await page.locator('[data-no-assignment]:visible').waitFor();
      assert.deepEqual(errors, [], `${pageName} empty state has browser errors`);
      assert.ok(await page.evaluate(() => document.documentElement.scrollWidth <= document.documentElement.clientWidth + 1), `${pageName} empty state overflows`);
      await context.close();
    }
    console.log('PASS: SBO empty states render at 320px');
  } finally { await browser.close(); }
})().catch(error => { console.error(error); process.exitCode = 1; });
