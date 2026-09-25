// Browser smoke test for the simplified student attendance view and QR states.
const {chromium} = require('playwright');
const assert = require('node:assert/strict');

const base = process.env.CITE_BASE_URL || 'http://localhost/ITEventManagement';

(async () => {
  const browser = await chromium.launch({headless: true, executablePath: process.env.CHROME_PATH || 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe', args: ['--no-sandbox']});
  try {
    for (const width of [390, 1280]) {
      const page = await browser.newPage({viewport: {width, height: 800}});
      const errors = [];
      page.on('pageerror', error => errors.push(error.message));
      await page.route('**/api/**', route => {
        const url = new URL(route.request().url());
        let payload = {success: true, data: {unread_notifications: 0, notifications: []}};
        if (url.pathname.endsWith('/auth.php')) payload = {success: true, authenticated: true, user: {id: 2, role: 'Student', first_name: 'Test', last_name: 'Student', full_name: 'Test Student', must_change_password: false}, csrf_token: 'test'};
        if (url.pathname.endsWith('/student-portal.php')) payload = {success: true, data: {summary: {total: 2, present: 1, absent: 0, pending: 1, rate: 100}, current_event: null, records: [{event_title: 'CITE Day', attendance_date: '2026-09-24', status: 'pending', time_in_at: null, time_out_at: null}]}};
        if (url.pathname.endsWith('/student-attendance-qr.php')) payload = {success: true, data: {server_now: '2026-09-24 12:00:00', sessions: [{event_name: 'CITE Day', session: 'whole_day', phase: 'out', state: 'out_open_without_in', token: 'test-qr-code', in_at: null, out_at: null, in_opens_at: null, out_opens_at: null}]}};
        return route.fulfill({status: 200, contentType: 'application/json', body: JSON.stringify(payload)});
      });
      await page.goto(`${base}/pages/student/attendance.html`, {waitUntil: 'domcontentloaded'});
      await page.locator('[data-qr-phase="out"] svg').waitFor();
      assert.match(await page.locator('[data-attendance-action]').textContent(), /Time Out is open/);
      assert.match(await page.locator('[data-attendance-action]').textContent(), /Time In will remain unrecorded/);
      assert.match(await page.locator('#student-attendance-rule-heading').textContent(), /Both scans are required/);
      assert.match(await page.locator('[data-attendance-records]').textContent(), /CITE Day/);
      assert.deepEqual(errors, []);
      console.log(`PASS: attendance QR and scan guidance at ${width}px`);
      await page.close();
    }
  } finally { await browser.close(); }
})().catch(error => { console.error(error); process.exitCode = 1; });
