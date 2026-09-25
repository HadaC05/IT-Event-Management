// Browser smoke test for the adviser profile page and save flow.
const {chromium} = require('playwright');
const assert = require('node:assert/strict');

const base = process.env.CITE_BASE_URL || 'http://localhost/ITEventManagement';

(async () => {
  const browser = await chromium.launch({headless: true, executablePath: process.env.CHROME_PATH || 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe', args: ['--no-sandbox']});
  try {
    for (const width of [390, 1280]) {
      const page = await browser.newPage({viewport: {width, height: 800}});
      const errors = [];
      let saved = false;
      page.on('pageerror', error => errors.push(error.message));
      await page.route('**/api/**', route => {
        const url = new URL(route.request().url());
        let payload = {success: true, data: {unread_notifications: 0, notifications: []}};
        if (url.pathname.endsWith('/auth.php')) payload = {success: true, authenticated: true, user: {id: 2, role: 'SBO Adviser', first_name: 'SBO', last_name: 'Adviser', full_name: 'SBO Adviser', must_change_password: false}, csrf_token: 'test'};
        if (url.pathname.endsWith('/adviser-profile.php')) {
          if (route.request().method() === 'POST') {
            assert.match(route.request().postData() || '', /Updated/);
            saved = true;
          }
          payload = {success: true, data: {first_name: saved ? 'Updated' : 'SBO', middle_name: '', last_name: 'Adviser', username: 'sbo.adviser', email: 'adviser@example.test', bio: '', profile_photo_path: null}};
        }
        return route.fulfill({status: 200, contentType: 'application/json', body: JSON.stringify(payload)});
      });
      await page.goto(`${base}/pages/adviser/profile.html`, {waitUntil: 'domcontentloaded'});
      await page.locator('[data-profile-form] input[name="first_name"]').waitFor();
      await page.evaluate(async () => {
        const canvas = document.createElement('canvas');
        canvas.width = 64; canvas.height = 64;
        canvas.getContext('2d').fillRect(0, 0, 64, 64);
        const blob = await new Promise(resolve => canvas.toBlob(resolve, 'image/png'));
        window.__cropPromise = window.ProfilePhotoCrop.open(new File([blob], 'photo.png', {type: 'image/png'}));
      });
      await page.locator('[data-crop-save]').click();
      const cropped = await page.evaluate(async () => {
        const file = await window.__cropPromise;
        return {type: file?.type, size: file?.size};
      });
      assert.equal(cropped.type, 'image/jpeg');
      assert.ok(cropped.size > 0);
      await page.locator('[data-profile-form] input[name="first_name"]').fill('Updated');
      await page.locator('[data-save-profile]').click();
      await page.waitForFunction(() => document.querySelector('[data-adviser-profile-root] h2')?.textContent === 'Updated Adviser');
      assert.equal(saved, true);
      assert.equal(await page.locator('[data-navigation-role="adviser"] [data-shared-profile-link]').count(), 1);
      assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= document.documentElement.clientWidth + 1), true);
      assert.deepEqual(errors, []);
      console.log(`PASS: adviser profile loads, crops, saves, and fits ${width}px`);
      await page.close();
    }
  } finally { await browser.close(); }
})().catch(error => { console.error(error); process.exitCode = 1; });
