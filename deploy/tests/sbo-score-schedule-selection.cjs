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
      <input data-assignment-search><select data-assignment-schedule></select><div data-assignment-results></div>
      <select data-assignment-mobile></select><strong data-score-hero-state></strong><small data-score-hero-detail></small>
      <h2 data-score-sheet-title></h2><span data-score-sheet-status></span>
      <form data-score-form><div data-score-grid></div><footer><button data-save-score type="submit">Save</button></footer></form>
    `);
    await page.evaluate(() => {
      const assignments = [
        {id: 11, activity_id: 3, selection_id: '11:3', event_name: 'CITE Days', activity_name: 'Quiz Bee', schedule_date: '2026-09-24', assignment_state: 'current'},
        {id: 12, activity_id: 4, selection_id: '12:4', event_name: 'CITE Days', activity_name: 'Dance', schedule_date: '2026-09-25', assignment_state: 'current'},
        {id: 13, activity_id: 5, selection_id: '13:5', event_name: 'Past Event', activity_name: 'Chess', schedule_date: '2026-09-20', assignment_state: 'ended'},
      ];
      window.requests = [];
      window.posts = [];
      window.SboPortal = {escapeHtml: value => String(value), initialize: async () => ({csrfToken: 'test'})};
      window.Notifications = {success() {}, error(message) {throw Error(message)}};
      window.axios = {
        get: async (_url, options) => {
          window.requests.push(options?.params || {});
          const selected = assignments.find(item => item.id === options?.params?.assignment_id && item.activity_id === options?.params?.activity_id) || assignments[0];
          return {data: {data: {assignments, selected, teams: [{id: 7, name: 'Green Falcons'}], raw_scores: {}, finalized: false}}};
        },
        post: async (_url, payload) => {window.posts.push(payload); return {data: {message: 'Saved'}}},
      };
    });
    await page.addScriptTag({path: 'js/sbo/scores.js'});
    await page.waitForFunction(() => document.querySelectorAll('[data-assignment-mobile] option').length === 3);
    assert.equal(await page.locator('[data-assignment-schedule] option').count(), 4);

    await page.locator('[data-assignment-mobile]').selectOption('12:4');
    await page.waitForFunction(() => document.querySelector('[data-score-sheet-title]').textContent === 'Dance raw scores');
    assert.deepEqual(await page.evaluate(() => window.requests.at(-1)), {assignment_id: 12, activity_id: 4});
    await page.locator('[data-raw-score]').fill('85');
    await page.locator('[data-save-score]').click();
    await page.waitForFunction(() => window.posts.length === 1);
    assert.deepEqual(await page.evaluate(() => window.posts[0]), {assignment_id: 12, activity_id: 4, scores: {'7': '85'}});

    await page.locator('[data-assignment-mobile]').selectOption('13:5');
    await page.waitForFunction(() => document.querySelector('[data-score-hero-state]').textContent === 'Ended');
    assert.equal(await page.locator('[data-score-form] footer').evaluate(footer => footer.classList.contains('hidden')), true);
    assert.equal(await page.locator('[data-raw-score]').isDisabled(), true);
    assert.deepEqual(errors, []);
    console.log('PASS: score dates, activity-specific save, and ended score read-only state');
  } finally {
    await browser.close();
  }
})().catch(error => {console.error(error); process.exitCode = 1});
