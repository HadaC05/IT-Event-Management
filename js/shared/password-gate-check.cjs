'use strict';

const {chromium} = require('playwright');
const fs = require('node:fs');
const path = require('node:path');
const source = fs.readFileSync(path.join(__dirname, 'password-gate.js'), 'utf8');
const styles = fs.readFileSync(path.join(__dirname, '../../css/tailwind.css'), 'utf8');

const assert = (condition, message) => {
  if (!condition) throw new Error(`FAIL: ${message}`);
};

(async () => {
  const browser = await chromium.launch({channel: 'chrome', headless: true});
  try {
    for (const viewport of [{width: 1366, height: 768}, {width: 390, height: 844}]) {
      const page = await browser.newPage({viewport});
      await page.setContent(`<!doctype html><html><head><style>${styles}</style></head><body><main>Protected portal</main></body></html>`);
      await page.addScriptTag({content: `window.axios={post:async()=>{throw {response:{data:{message:'Test server message'}}}}};${source}`});

      const ordinary = await page.evaluate(() => Promise.race([
        RequiredPasswordGate.open({must_change_password: false}, 'csrf').then(() => 'resolved'),
        new Promise(resolve => setTimeout(() => resolve('waiting'), 50)),
      ]));
      assert(ordinary === 'resolved', `${viewport.width}px unchanged account proceeds`);

      await page.evaluate(() => { RequiredPasswordGate.open({
        id: 9001,
        first_name: 'Test',
        last_name: 'Student',
        role: 'Student',
        must_change_password: true,
      }, 'csrf'); });
      const dialog = page.locator('[data-required-password-gate]');
      await dialog.waitFor({state: 'visible'});
      assert(await dialog.evaluate(node => node.matches(':modal')), `${viewport.width}px gate is modal`);
      await page.keyboard.press('Escape');
      assert(await dialog.isVisible(), `${viewport.width}px Escape cannot bypass gate`);
      const box = await dialog.boundingBox();
      assert(box && box.x >= 0 && box.y >= 0 && box.x + box.width <= viewport.width + 1 && box.y + box.height <= viewport.height + 1, `${viewport.width}px gate fits viewport`);

      await dialog.locator('[name="password"]').fill('temporary-new');
      await dialog.locator('[name="password_confirmation"]').fill('temporary-new');
      await dialog.locator('button[type="submit"]').click();
      await page.locator('[data-password-gate-error]').waitFor({state: 'visible'});
      assert((await page.locator('[data-password-gate-error]').textContent()) === 'Test server message', `${viewport.width}px API error is visible`);
      await page.close();
    }
  } finally {
    await browser.close();
  }
  console.log('PASS: first-login gate works at desktop and mobile widths; no API or database writes.');
})().catch(error => { console.error(error); process.exitCode = 1; });
