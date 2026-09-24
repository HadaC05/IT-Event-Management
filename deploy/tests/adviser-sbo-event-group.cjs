'use strict';

const assert = require('node:assert/strict');
const {chromium} = require('playwright');

(async () => {
  const browser = await chromium.launch({headless: true, executablePath: process.env.CHROME_PATH || 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe', args: ['--no-sandbox']});
  try {
    const page = await browser.newPage();
    const errors = [];
    page.on('pageerror', error => errors.push(error.message));
    await page.setContent(`
      <button data-officer-tab="assignments"></button><div data-officer-tab-panel="assignments"></div>
      <form data-assignment-filters><input name="search"><select name="event"></select><select name="event_day"></select><select name="scanner_mode"><option value=""></option></select></form>
      <span data-task-result-count></span><div data-assignment-pagination></div><div id="officer-assignments-panel"></div>
      <table><tbody data-task-list></tbody></table>
      <button data-dialog-open="event-responsibilities-dialog"></button><button data-task-create-officer></button>
      <dialog id="event-responsibilities-dialog"><h2 data-task-dialog-title></h2><form data-task-form>
        <select data-task-officer></select><div data-task-events></div>
        <select name="scanner_mode"><option value="specific">Specific</option><option value="general">General</option></select>
        <button data-task-submit type="submit">Assign event access</button>
        <button data-task-cancel-edit type="button"></button>
      </form><p data-task-officer-empty></p><p data-task-scanner-general-note></p></dialog>
    `);
    await page.evaluate(() => {
      window.sent = [];
      window.Notifications = {success() {}, error() {}, confirm: async () => true};
      const schedules = [
        {id: 10, event_schedule_id: 101, schedule_date: '2026-09-24', scanner_mode: 'specific', team_name: 'A'},
        {id: 11, event_schedule_id: 102, schedule_date: '2026-09-25', scanner_mode: 'specific', team_name: 'A'},
      ];
      window.axios = {
        get: async url => url.includes('auth.php') ? {data: {csrf_token: 'test'}} : {data: {data: {
          officers: [{id: 7, full_name: 'Test Officer'}],
          events: [{id: 5, schedule_id: 101, title: 'CITE Days', schedule_date: '2026-09-24'}, {id: 5, schedule_id: 102, title: 'CITE Days', schedule_date: '2026-09-25'}, {id: 6, schedule_id: 201, title: 'Other Event', schedule_date: '2026-09-26'}],
          assignment_groups: [{id: 10, key: '7:5', officer_assignment_id: 7, event_id: 5, officer_name: 'Test Officer', event_name: 'CITE Days', schedules, scanner_mode: 'specific', general_count: 0, specific_count: 2}],
        }}},
        post: async (_url, data) => { window.sent.push(data); return {data: {message: 'Saved'}}; },
      };
    });
    await page.addScriptTag({path: 'js/adviser/sbo-assignments.js'});
    await page.evaluate(() => window.SboOfficerAssignments.activate());
    await page.locator('#event-responsibilities-dialog').evaluate(dialog => dialog.close());
    await page.locator('[data-task-edit="7:5"]').click();
    assert.equal(await page.locator('[data-task-events] input:checked').count(), 2);
    assert.equal(await page.locator('[data-task-events] input[value="201"]').isDisabled(), true);
    await page.locator('[data-task-events] input[value="102"]').uncheck();
    await page.locator('[data-task-submit]').click();
    await page.waitForFunction(() => window.sent.length === 1);
    assert.deepEqual(await page.evaluate(() => window.sent[0]), {
      action: 'sync_event', officer_assignment_id: '7', event_schedule_ids: [101], scanner_mode: 'specific', event_id: 5,
    });
    assert.deepEqual(errors, []);
    console.log('Grouped event editing submits exactly the selected days.');
  } finally {
    await browser.close();
  }
})().catch(error => { console.error(error); process.exitCode = 1; });
