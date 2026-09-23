'use strict';

const assert = require('node:assert/strict');
const {chromium} = require('playwright');

const base = process.env.CITE_BASE_URL || 'http://localhost/ITEventManagement';
const metadata = {
  event_types: [{id: 2, label: 'Campus activity'}],
  academic_periods: [{id: 3, label: 'SY 2026-2027 · First Semester', school_year_id: 1}],
  default_academic_period_id: 3,
  locations: [{id: 4, name: 'Main Hall', type: 'general', parent_location_id: null}],
  audience_year_levels: [], audience_teams: [], assignable_users: [],
  attendance_modes: [{id: 1, code: 'none', name: 'No attendance scanning'}, {id: 2, code: 'whole_day', name: 'Whole day'}],
  active_students_count: 1,
};
const event = {
  id: 27, title: 'Practice sayaw²', event_type_id: 2, academic_period_id: 3,
  location_id: 4, location_ids: [4], audience_type: 'specific_students',
  audience_year_level_ids: [], audience_team_ids: [], participant_ids: [9],
  assigned_users: [], attendance_schedules: [], description: 'Rehearsal',
};

(async () => {
  const browser = await chromium.launch({headless: true, executablePath: process.env.CHROME_PATH || 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe', args: ['--no-sandbox']});
  try {
    const page = await browser.newPage({viewport: {width: 390, height: 844}});
    const errors = [];
    page.on('pageerror', error => errors.push(error.message));
    await page.route('**/api/**', route => {
      const url = new URL(route.request().url());
      let payload = {success: true, data: {}};
      if (url.pathname.endsWith('/auth.php')) {
        payload = {success: true, authenticated: true, csrf_token: 'test', user: {
          id: 1, username: 'adviser', full_name: 'SBO Adviser', email: 'adviser@example.test', role: 'SBO Adviser',
        }};
      } else if (url.pathname.endsWith('/adviser-events.php') && url.searchParams.get('action') === 'student_candidates') {
        payload = {success: true, data: {students: [{id: 9, full_name: 'Student Nine', id_number: 'S-9'}], pagination: {
          current_page: 1, last_page: 1, from: 1, to: 1, total: 1,
        }}};
      } else if (url.pathname.endsWith('/adviser-events.php')) {
        payload = {success: true, data: {event, metadata}};
      }
      return route.fulfill({json: payload});
    });

    await page.goto(`${base}/pages/adviser/event-edit.html?id=27`, {waitUntil: 'domcontentloaded'});
    await page.locator('[data-event-form] [name="title"]').waitFor();
    await page.waitForFunction(() => document.querySelector('[data-event-form] [name="title"]')?.value === 'Practice sayaw²');
    assert.equal(await page.locator('[data-event-form] [name="event_type_id"]').inputValue(), '2');
    assert.equal(await page.locator('[data-event-form] [name="academic_period_id"]').inputValue(), '3');
    assert.equal(await page.locator('[data-event-form] [name="description"]').inputValue(), 'Rehearsal');
    assert.equal(await page.locator('[data-selected-student-count]').textContent(), '1 selected');
    assert.equal(await page.locator('[data-student-search]').count(), 1);
    assert.equal(await page.locator('[data-student-results]').count(), 1);
    assert.deepEqual(errors, []);
    console.log('PASS: adviser event edit populates the saved event and student picker on mobile');
  } finally {
    await browser.close();
  }
})().catch(error => { console.error(error); process.exitCode = 1; });
