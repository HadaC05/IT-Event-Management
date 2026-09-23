'use strict';

const assert = require('node:assert/strict');
const {chromium} = require('playwright');

const base = process.env.CITE_BASE_URL || 'http://localhost/ITEventManagement';
const origin = new URL(base).origin;
const assignment = {
  id: 12, event_id: 42, event_name: 'Campus Day', team_name: 'Hero Academia',
  scanner_team_name: 'Hero Academia', scanner_mode: 'specific',
  assignment_state: 'active', is_session_active: true, in_window_open: true,
  out_window_open: false, day_number: 1, schedule_date: '2026-09-23',
  session_name: 'Whole day', session_start: '08:00:00', session_end: '18:00:00',
  location_policy: 'strict', venue_name: 'Main Hall',
  venues: [{id: 5, name: 'Main Hall', latitude: 8.4699237, longitude: 124.6342058, radius: 100}],
};

(async () => {
  const browser = await chromium.launch({headless: true, executablePath: process.env.CHROME_PATH || 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe', args: ['--no-sandbox']});
  try {
    for (const role of ['sbo', 'faculty']) {
      const context = await browser.newContext({viewport: {width: 390, height: 844}, geolocation: {latitude: 8.4699237, longitude: 124.6342058}});
      await context.grantPermissions(['geolocation'], {origin});
      const page = await context.newPage();
      const errors = [];
      page.on('pageerror', error => errors.push(error.message));
      await page.route('**/api/**', route => {
        const url = new URL(route.request().url());
        let payload = {success: true, data: {}};
        if (url.pathname.endsWith('/auth.php')) {
          payload = {success: true, authenticated: true, csrf_token: 'test', user: {
            id: 1, username: role, first_name: 'Test', last_name: 'Officer',
            full_name: 'Test Officer', role: role === 'sbo' ? 'SBO Officer' : 'Faculty',
          }};
        } else if (url.pathname.endsWith('/sbo-attendance.php') || url.pathname.endsWith('/faculty.php')) {
          payload = {success: true, data: {
            assignments: [assignment], selected_assignment: assignment,
            recent_scans: [], counts: {total: 1, checked_out: 0, remaining: 5}, next_session: null,
          }};
        }
        return route.fulfill({json: payload});
      });

      await page.goto(`${base}/pages/${role}/attendance.html`, {waitUntil: 'domcontentloaded'});
      await page.waitForFunction(() => document.querySelector('[data-assignment-summary]')?.textContent.includes('Campus Day'));
      await page.waitForFunction(() => document.querySelector('[data-scanner-open]')?.disabled === false);
      assert.equal(await page.locator('[data-manual-submit]').isDisabled(), false, `${role} can record by ID when the session and GPS are valid`);
      assert.equal(await page.locator('[data-session-state]').textContent(), 'Time In scanning is open.');
      assert.deepEqual(errors, [], `${role} attendance page initializes without JavaScript errors`);
      console.log(`PASS: ${role} attendance assignment and scanner controls initialize at mobile width`);

      if (role === 'sbo') {
        const decoded = await page.evaluate(async () => {
          const [{default: QrScanner}, {default: makeQr}] = await Promise.all([
            import(new URL('js/vendor/qr-scanner/qr-scanner.min.js', document.baseURI)),
            import(new URL('js/vendor/qrcode-generator/qrcode.mjs', document.baseURI)),
          ]);
          QrScanner._disableBarcodeDetector = true;
          const token = 'aB1_2345678901234567890123456789';
          const qr = makeQr(0, 'M');
          qr.addData(token);
          qr.make();
          const url = URL.createObjectURL(new Blob([qr.createSvgTag({cellSize: 10, margin: 20})], {type: 'image/svg+xml'}));
          try {
            const result = await QrScanner.scanImage(url, {returnDetailedScanResult: true});
            return result.data;
          } finally {
            URL.revokeObjectURL(url);
          }
        });
        assert.equal(decoded, 'aB1_2345678901234567890123456789', 'The fallback worker decodes a real attendance-style QR under the site CSP');
        console.log('PASS: QR fallback worker decodes a student-style token under the site CSP');
      }
      await page.locator('[data-gps-toggle]').click();
      assert.equal(await page.locator('[data-scanner-open]').isDisabled(), true);
      assert.equal(await page.locator('[data-manual-submit]').isDisabled(), true);
      assert.match(await page.locator('[data-session-state]').textContent(), /Turn On GPS/);
      console.log(`PASS: ${role} explains why scanning is disabled when GPS is off`);
      await context.close();
    }
  } finally {
    await browser.close();
  }
})().catch(error => { console.error(error); process.exitCode = 1; });
