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
      await page.clock.install();
      const errors = [];
      let holdRefresh = false, heldRefresh = null, holdScan = false, heldScan = null, scanPosts = 0;
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
          if (route.request().method() === 'GET' && holdRefresh) {
            holdRefresh = false;
            heldRefresh = route;
            return;
          }
          if (route.request().method() === 'POST') {
            scanPosts++;
            if (holdScan) {
              holdScan = false;
              heldScan = route;
              return;
            }
          }
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

      // A stationary phone may never fire another watchPosition callback.
      // Only explicit getCurrentPosition calls can keep this mock fresh.
      await page.evaluate(() => {
        window.gpsTest = {requests: 0, mode: 'fresh'};
        const result = () => ({coords: {latitude: 8.4699237, longitude: 124.6342058, accuracy: 10}, timestamp: Date.now()});
        Object.defineProperty(navigator, 'geolocation', {configurable: true, value: {
          watchPosition(success) { queueMicrotask(() => success(result())); return 1; },
          clearWatch() {},
          getCurrentPosition(success, error) {
            window.gpsTest.requests++;
            queueMicrotask(() => window.gpsTest.mode === 'fresh' ? success(result()) : error({code: window.gpsTest.mode === 'denied' ? 1 : 3}));
          },
        }});
      });
      await page.locator('[data-gps-toggle]').click();
      await page.waitForFunction(() => !document.querySelector('[data-scanner-open]').disabled);
      await page.clock.runFor(95000);
      assert.equal(await page.locator('[data-scanner-open]').isDisabled(), false, `${role} keeps a stationary phone ready beyond the 30-second GPS expiry`);
      assert.ok(await page.evaluate(() => window.gpsTest.requests >= 4), `${role} actively refreshes GPS even without watch updates`);
      console.log(`PASS: ${role} refreshes stationary-phone GPS for 95 seconds`);

      await page.evaluate(() => { window.gpsTest.mode = 'timeout'; });
      await page.clock.runFor(40000);
      assert.equal(await page.locator('[data-scanner-open]').isDisabled(), true, `${role} blocks scanning when fresh GPS cannot be acquired`);
      assert.equal(await page.locator('[data-manual-submit]').isDisabled(), true, `${role} also protects manual attendance from stale GPS`);
      await page.evaluate(() => { window.gpsTest.mode = 'fresh'; });
      await page.clock.runFor(15000);
      assert.equal(await page.locator('[data-scanner-open]').isDisabled(), false, `${role} recovers automatically when GPS becomes available again`);
      console.log(`PASS: ${role} blocks stale GPS and recovers after a temporary outage`);

      await page.evaluate(() => {
        window.gpsTest.hidden = true;
        Object.defineProperty(document, 'hidden', {configurable: true, get: () => window.gpsTest.hidden});
        document.dispatchEvent(new Event('visibilitychange'));
      });
      const requestsWhenHidden = await page.evaluate(() => window.gpsTest.requests);
      await page.clock.runFor(40000);
      assert.equal(await page.evaluate(() => window.gpsTest.requests), requestsWhenHidden, `${role} pauses GPS refresh while hidden`);
      await page.evaluate(() => {
        window.gpsTest.hidden = false;
        document.dispatchEvent(new Event('visibilitychange'));
      });
      await page.waitForFunction(() => !document.querySelector('[data-scanner-open]').disabled);
      assert.ok(await page.evaluate(() => window.gpsTest.requests) > requestsWhenHidden, `${role} refreshes immediately on returning to the page`);
      console.log(`PASS: ${role} pauses location requests in the background and refreshes on return`);

      await page.evaluate(() => { window.gpsTest.mode = 'denied'; });
      await page.clock.runFor(40000);
      assert.equal(await page.locator('[data-scanner-open]').isDisabled(), true);
      const requestsAfterDenial = await page.evaluate(() => window.gpsTest.requests);
      await page.clock.runFor(40000);
      assert.equal(await page.evaluate(() => window.gpsTest.requests), requestsAfterDenial, `${role} does not repeatedly request denied permission`);
      await page.evaluate(() => { window.gpsTest.mode = 'fresh'; });
      if (await page.locator('[data-gps-policy-dialog]').isVisible()) await page.locator('[data-gps-policy-confirm]').click();
      await page.locator('[data-location-refresh]').click();
      await page.waitForFunction(() => !document.querySelector('[data-scanner-open]').disabled);
      console.log(`PASS: ${role} respects denied permission and supports an explicit retry`);

      holdRefresh = true;
      await page.clock.runFor(30000);
      assert.ok(heldRefresh, `${role} starts a periodic attendance refresh`);
      holdScan = true;
      await page.locator('#manual-student-id').fill('25-001');
      await page.locator('[data-manual-submit]').click();
      await page.waitForFunction(() => document.querySelector('[data-scan-result]').textContent.includes('Recording scan'));
      assert.ok(heldScan, `${role} sends one attendance request`);
      assert.equal(await page.locator('[data-manual-submit]').isDisabled(), true, `${role} blocks another scan while saving`);
      assert.equal(await page.locator('[data-assignment-selector]').isDisabled(), true, `${role} keeps the assignment stable while saving`);
      assert.match(await page.locator('[data-scan-result]').textContent(), /Recording scan/, `${role} ignores a stale refresh during a scan`);
      await heldScan.fulfill({json: {success: true, data: {
        student: {full_name: 'Sample Student', id_number: '25-001', team_name: 'Hero Academia'},
        session_name: 'Whole day', checkpoint: 'in', time_in_missing: false,
        scanned_at: '2026-09-23 10:00:00', location: {status: 'inside', venue_name: 'Main Hall', distance_m: 2, venue_match: 'matched'},
        recent_scans: [], counts: {total: 2, checked_out: 0, remaining: 4},
      }}});
      await page.waitForFunction(() => document.querySelector('[data-scan-result]').textContent.includes('Sample Student'));
      assert.match(await page.locator('[data-scan-counts]').textContent(), /2 timed in/, `${role} shows the saved scan's counts`);
      assert.equal(scanPosts, 1, `${role} submits only one scan while the request is pending`);
      await page.clock.runFor(1000);
      assert.equal(await page.locator('[data-manual-submit]').isDisabled(), false, `${role} becomes ready for the next student`);
      await heldRefresh.fulfill({json: {success: true, data: {
        assignments: [assignment], selected_assignment: assignment,
        recent_scans: [], counts: {total: 1, checked_out: 0, remaining: 5}, next_session: null,
      }}});
      await page.clock.runFor(1);
      assert.match(await page.locator('[data-scan-result]').textContent(), /Sample Student/, `${role} keeps the scan result after a late refresh`);
      assert.match(await page.locator('[data-scan-counts]').textContent(), /2 timed in/, `${role} does not replace saved counts with a stale refresh`);
      heldScan = null;
      holdScan = true;
      await page.locator('#manual-student-id').fill('25-002');
      await page.locator('[data-manual-submit]').click();
      assert.ok(heldScan, `${role} accepts the next student after the first scan completes`);
      assert.equal(scanPosts, 2, `${role} submits the second student only once`);
      await heldScan.fulfill({json: {success: true, data: {
        student: {full_name: 'Next Student', id_number: '25-002', team_name: 'Hero Academia'},
        session_name: 'Whole day', checkpoint: 'in', time_in_missing: false,
        scanned_at: '2026-09-23 10:00:02', location: {status: 'inside', venue_name: 'Main Hall', distance_m: 2, venue_match: 'matched'},
        recent_scans: [], counts: {total: 3, checked_out: 0, remaining: 3},
      }}});
      await page.waitForFunction(() => document.querySelector('[data-scan-result]').textContent.includes('Next Student'));
      assert.match(await page.locator('[data-scan-counts]').textContent(), /3 timed in/, `${role} shows the second student's saved count`);
      console.log(`PASS: ${role} keeps scan feedback stable when a refresh overlaps a save`);

      await page.locator('[data-gps-toggle]').click();
      const requestsWhenOff = await page.evaluate(() => window.gpsTest.requests);
      await page.clock.runFor(40000);
      assert.equal(await page.evaluate(() => window.gpsTest.requests), requestsWhenOff, `${role} stops requesting location when GPS is turned off`);
      assert.equal(await page.locator('[data-scanner-open]').isDisabled(), true);
      assert.deepEqual(errors, [], `${role} GPS recovery has no uncaught errors`);
      await context.close();
    }
  } finally {
    await browser.close();
  }
})().catch(error => { console.error(error); process.exitCode = 1; });
