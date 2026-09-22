'use strict';

const {chromium} = require('playwright');

const assert = (condition, message) => {
  if (!condition) throw new Error(`FAIL: ${message}`);
};

(async () => {
  const browser = await chromium.launch({channel: 'chrome', headless: true});
  try {
    for (const viewport of [{width: 1366, height: 768}, {width: 390, height: 844}]) {
      const page = await browser.newPage({viewport});
      let passwordChanged = false;
      await page.route('**/api/auth.php?action=*', async route => {
        const action = new URL(route.request().url()).searchParams.get('action');
        if (action === 'session') {
          await route.fulfill({json: {
            success: true,
            csrf_token: 'test-token',
            user: passwordChanged ? null : {
              id: 1, first_name: 'Test', last_name: 'Faculty', role: 'Faculty',
              must_change_password: true,
            },
          }});
        } else if (action === 'change_password') {
          passwordChanged = true;
          await route.fulfill({json: {
            success: true,
            message: 'Your password was changed. Sign in again using your new password.',
            redirect_url: './?login=password-changed',
          }});
        } else {
          await route.abort();
        }
      });
      await page.route('**/api/events.php', route => route.fulfill({json: {
        success: true, data: {upcoming: [], featured: []},
      }}));

      await page.goto('http://localhost/ITEventManagement/');
      const gate = page.locator('[data-required-password-gate]');
      await gate.waitFor({state: 'visible'});
      await gate.locator('[name="password"]').fill('new-password-123');
      await gate.locator('[name="password_confirmation"]').fill('new-password-123');
      await gate.locator('button[type="submit"]').click();

      const confirmation = page.locator('[data-password-change-success]');
      await confirmation.waitFor({state: 'visible'});
      assert(passwordChanged, `${viewport.width}px password-change request succeeded`);
      assert(await page.locator('#login-modal').evaluate(dialog => dialog.open), `${viewport.width}px sign-in dialog opens after password change`);
      assert((await confirmation.textContent()).includes('Sign in with your new password'), `${viewport.width}px confirmation explains the next step`);
      assert(!new URL(page.url()).searchParams.has('login'), `${viewport.width}px one-time login flag is removed`);

      await page.reload();
      assert(await confirmation.isHidden(), `${viewport.width}px confirmation does not repeat on a later visit`);
      await page.close();
    }
  } finally {
    await browser.close();
  }
  console.log('PASS: password-change confirmation appears at desktop and mobile widths; no API or database writes.');
})().catch(error => { console.error(error); process.exitCode = 1; });
