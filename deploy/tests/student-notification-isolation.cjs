// The student notification bell must keep working if the media feed fails.
const {chromium} = require('playwright');
const assert = require('node:assert/strict');

(async () => {
  const browser = await chromium.launch({headless: true, executablePath: process.env.CHROME_PATH || 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe', args: ['--no-sandbox']});
  try {
    const page = await browser.newPage({viewport: {width: 390, height: 780}});
    const notificationRequests = [];
    let viewed = false;
    let markRequests = 0;
    await page.route('**/api/**', route => {
      const url = new URL(route.request().url());
      let status = 200;
      let payload = {success: true, data: {}};
      if (url.pathname.endsWith('/auth.php')) payload = {success: true, authenticated: true, user: {id: 1, role: 'Student', first_name: 'Test', last_name: 'Student', full_name: 'Test Student', must_change_password: false}, csrf_token: 'test'};
      if (url.pathname.endsWith('/media.php')) {
        if (route.request().method() === 'POST' && route.request().postDataJSON().action === 'mark_notifications_read') {
          markRequests++;
          viewed = true;
          payload = {success: true, message: 'Notifications marked as read.'};
        } else if (url.searchParams.get('action') === 'notifications') {
          notificationRequests.push('notifications');
          payload = {success: true, data: {unread_notifications: viewed ? 0 : 1, notifications: [{id: 'notice-1', title: 'Event reminder', detail: 'Opening ceremony', is_read: viewed, created_at: '2026-09-23 08:00:00'}]}};
        } else {
          status = 500;
          payload = {success: false, message: 'The media request failed.'};
        }
      }
      return route.fulfill({status, contentType: 'application/json', body: JSON.stringify(payload)});
    });
    const base = process.env.CITE_BASE_URL || 'http://localhost/ITEventManagement';
    await page.goto(`${base}/pages/student/home.html`, {waitUntil: 'domcontentloaded'});
    await page.waitForFunction(() => document.querySelector('[data-notification-count]')?.textContent === '1');
    await page.getByText('The media request failed.', {exact: true}).waitFor();
    assert.ok(notificationRequests.includes('notifications'));
    assert.equal(await page.locator('[data-notification-count]').isVisible(), true);
    await page.locator('[data-notification-menu] summary').click();
    await page.waitForFunction(() => document.querySelector('[data-notification-count]')?.hidden === true);
    assert.equal(markRequests, 1, 'Opening notifications marks the unread items viewed once');
    assert.match(await page.locator('[data-notification-list]').textContent(), /Opening ceremony/, 'The notification preview remains available');
    console.log('PASS: student notification bell remains available when media feed fails');
  } finally { await browser.close(); }
})().catch(error => { console.error(error); process.exitCode = 1; });
