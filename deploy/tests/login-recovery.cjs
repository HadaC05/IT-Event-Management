'use strict';

const {chromium} = require('playwright');

const assert = (condition, message) => {
  if (!condition) throw new Error(`FAIL: ${message}`);
};

const base = process.env.CITE_BASE_URL || 'http://localhost/ITEventManagement/';

(async () => {
  const browser = await chromium.launch({channel: 'chrome', headless: true});
  try {
    for (const width of [320, 390, 1280]) {
      const page = await browser.newPage({viewport: {width, height: width === 320 ? 568 : 844}});
      const remembered = [];
      let requests = 0;
      await page.route('**/api/auth.php?action=*', async route => {
        const action = new URL(route.request().url()).searchParams.get('action');
        if (action === 'session') {
          await route.fulfill({json: {success: true, csrf_token: 'test-token', user: null}});
          return;
        }
        if (action === 'login') {
          requests += 1;
          remembered.push(route.request().postDataJSON().remember);
          if (requests < 3) {
            await new Promise(resolve => setTimeout(resolve, 450));
            await route.fulfill({status: 401, json: {message: 'Delayed response'}}).catch(() => {});
          } else if (requests === 3) {
            await route.fulfill({status: 401, json: {message: 'Wrong credentials'}});
          } else {
            await route.fulfill({json: {
              success: true,
              user: {id: 1, role: 'Student', must_change_password: false},
              csrf_token: 'next-token',
              redirect_url: './?test-success=1',
            }});
          }
          return;
        }
        await route.abort();
      });
      await page.route('**/api/events.php', route => route.fulfill({json: {
        success: true, data: {upcoming: [], featured: []},
      }}));
      await page.goto(`${base}?login=sign-in`);
      await page.evaluate(() => {
        const post = axios.post.bind(axios);
        axios.post = (url, data, config) => {
          if (url.includes('action=login')) window.loginTimeout = config.timeout;
          return post(url, data, config);
        };
      });

      await page.locator('#login-modal').waitFor({state: 'visible'});
      await page.locator('[name="login"]').fill('test-student');
      await page.locator('#modal-password').fill('test-password');
      const remember = page.locator('[name="remember"]');
      assert(!(await remember.isChecked()), `${width}px remember choice defaults off`);
      await remember.check();
      await page.locator('[data-login-submit]').click();
      await page.locator('#sign-in-wait').waitFor({state: 'visible'});
      assert(await page.evaluate(() => window.loginTimeout) === 20000, `${width}px login has a 20-second timeout`);
      await page.locator('[data-sign-in-cancel]').click();
      await page.locator('#login-modal').waitFor({state: 'visible'});
      assert(await page.locator('[data-login-submit]').isEnabled(), `${width}px cancel restores submit`);
      await page.waitForTimeout(600);
      assert(await page.locator('#login-modal').isVisible(), `${width}px late response cannot dismiss login`);

      await page.locator('[data-login-submit]').click();
      await page.locator('#sign-in-wait').waitFor({state: 'visible'});
      await page.keyboard.press('Escape');
      await page.locator('#login-modal').waitFor({state: 'visible'});
      assert(await page.locator('[data-login-submit]').isEnabled(), `${width}px Escape restores submit`);
      await page.waitForTimeout(600);

      await remember.uncheck();
      await page.locator('[data-login-submit]').click();
      await page.locator('[data-login-error]').waitFor({state: 'visible'});
      assert((await page.locator('[data-login-error]').textContent()).includes('Wrong credentials'), `${width}px later failed login shows its own error`);
      assert(remembered.length === 3 && remembered[0] === true && remembered[1] === true && remembered[2] === false,
        `${width}px remember choice reaches the API`);
      await page.locator('[data-login-submit]').click();
      await page.waitForURL('**/?test-success=1');
      assert(remembered.length === 4 && remembered[3] === false, `${width}px successful retry redirects`);
      await page.close();
    }
  } finally {
    await browser.close();
  }
  console.log('PASS: login remember choice, cancel button, Escape, timeout config, and retry at mobile and desktop widths.');
})().catch(error => { console.error(error); process.exitCode = 1; });
