'use strict';

const assert = require('node:assert/strict');
const {chromium} = require('playwright');
const base = process.env.CITE_BASE_URL || 'http://localhost/ITEventManagement';
const png = Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+tmZkAAAAASUVORK5CYII=', 'base64');

(async () => {
  const browser = await chromium.launch({headless: true, executablePath: process.env.CHROME_PATH || 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe', args: ['--no-sandbox']});
  try {
    const page = await browser.newPage({viewport: {width: 390, height: 844}});
    const errors = [];
    let submitted = '';
    page.on('pageerror', error => errors.push(error.message));
    await page.route('**/api/**', route => {
      const url = new URL(route.request().url());
      let payload = {success: true, data: {}};
      if (url.pathname.endsWith('/auth.php')) payload = {success: true, authenticated: true, user: {id: 1, role: 'SBO Adviser', username: 'adviser', full_name: 'SBO Adviser'}, csrf_token: 'test'};
      if (url.pathname.endsWith('/teams.php')) {
        if (route.request().method() === 'POST') {
          submitted = route.request().postData() || '';
          payload = {success: true, id: 5, message: 'Tribe created successfully.'};
        } else payload = {success: true, data: {teams: [], assignment_teams: [], school_years: [{id: 1, label: '2026-2027'}],
          summary: {total: 0, active: 0, students: 0, assigned: 0, unassigned: 0, school_year_label: null},
          pagination: {current_page: 1, last_page: 1, total: 0, from: null, to: null}}};
      }
      return route.fulfill({json: payload});
    });
    await page.goto(`${base}/pages/adviser/teams.html`, {waitUntil: 'domcontentloaded'});
    await page.locator('[data-dialog-open]').click();
    await page.locator('[data-team-form] [name="name"]').fill('Blue Sharks');
    await page.locator('[data-team-form] [name="image"]').setInputFiles({name: 'tribe.png', mimeType: 'image/png', buffer: png});
    assert.equal(await page.locator('[data-preview-image]').isVisible(), true, 'Selected tribe image has a preview');
    await page.evaluate(() => document.querySelector('[data-team-form]').requestSubmit());
    await page.waitForFunction(() => !document.querySelector('#team-dialog').open);
    assert.ok(submitted.includes('filename="tribe.png"'), 'The image is sent in the team save request');
    assert.ok(submitted.includes('name="member_ids"'), 'The selected member list is sent with the image');
    assert.deepEqual(errors, []);
    console.log('PASS: adviser tribe image preview and multipart save request');
  } finally { await browser.close(); }
})().catch(error => { console.error(error); process.exitCode = 1; });
