'use strict';

const assert = require('node:assert/strict');
const {chromium} = require('playwright');

const base = process.env.CITE_BASE_URL || 'http://localhost/ITEventManagement';
const target = {id: 42, id_number: '02-2324-01365', first_name: 'Test', middle_name: '', last_name: 'Student',
  full_name: 'Test Student', username: '02-2324-01365', email: 'student@example.test', role_id: 5, role: 'Student',
  year_level: 1, status: 'active', assigned_events: [], responsibility_events_count: 0, officer_assignment_id: null};

(async () => {
  const browser = await chromium.launch({headless: true, executablePath: process.env.CHROME_PATH || 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe', args: ['--no-sandbox']});
  try {
    const page = await browser.newPage({viewport: {width: 390, height: 844}});
    const errors = [];
    const posts = [];
    page.on('pageerror', error => errors.push(error.message));
    await page.route('**/api/**', route => {
      const url = new URL(route.request().url());
      if (url.pathname.endsWith('/auth.php')) return route.fulfill({json: {success: true, authenticated: true,
        user: {id: 1, username: 'adviser', role: 'SBO Adviser', first_name: 'Test', last_name: 'Adviser', must_change_password: false}, csrf_token: 'test'}});
      if (url.pathname.endsWith('/users.php')) {
        if (route.request().method() === 'POST') {
          posts.push(route.request().postDataJSON());
          return route.fulfill({json: {success: true, message: 'Password reset.'}});
        }
        return route.fulfill({json: {success: true, data: {users: [target], summary: {total: 1, active: 1, sbo: 0},
          roles: [{id: 5, name: 'Student'}], filter_roles: [{id: 5, name: 'Student'}], filter_statuses: [{id: 1, label: 'active'}],
          year_levels: [{id: 1, label: 'Fourth Year'}], teams: [], events: [],
          pagination: {current_page: 1, last_page: 1, total: 1, from: 1, to: 1}}}});
      }
      return route.fulfill({json: {success: true, data: {unread_notifications: 0, notifications: []}}});
    });

    await page.goto(`${base}/pages/adviser/users.html`, {waitUntil: 'domcontentloaded'});
    await page.locator('[data-user-menu-trigger]:visible').first().click();
    await page.getByRole('button', {name: 'Reset password'}).click();
    const reset = page.locator('dialog', {has: page.getByRole('heading', {name: 'Reset Password'})});
    await reset.waitFor({state: 'visible'});
    await reset.locator('[name="password"]').fill('TempPass123!');
    await reset.locator('[name="password_confirmation"]').fill('TempPass123!');
    await reset.getByRole('button', {name: 'Reset password'}).click();
    await page.waitForFunction(() => document.querySelector('dialog:has(h2)')?.open !== true);
    assert.deepEqual(posts, [{action: 'reset_password', id: 42, password: 'TempPass123!', password_confirmation: 'TempPass123!'}]);

    await page.locator('[data-user-menu-trigger]:visible').first().click();
    await page.getByRole('button', {name: 'Edit account'}).click();
    const edit = page.locator('#add-user-dialog');
    await edit.waitFor({state: 'visible'});
    assert.equal(await edit.locator('[name="password"]').isDisabled(), true);
    assert.equal(await edit.locator('[name="password_confirmation"]').isDisabled(), true);
    assert.deepEqual(errors, []);
    console.log('PASS: student password reset uses isolated action and profile edit excludes credentials');
  } finally {
    await browser.close();
  }
})().catch(error => {console.error(error); process.exitCode = 1});
