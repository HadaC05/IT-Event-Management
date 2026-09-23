'use strict';

const {chromium} = require('playwright');
const assert = require('node:assert/strict');
const path = require('node:path');

const base = process.env.CITE_BASE_URL || 'http://localhost/ITEventManagement';
const viewer = {id: 7, role: 'Student', first_name: 'Micah', last_name: 'Lago', full_name: 'Micah Dusil Lago', must_change_password: false};
const team = {id: 47, name: 'Hero Academia', color: '#FACC15', school_year: '2026-2027', members_count: 369,
  current_member: {id: 7, full_name: viewer.full_name, initials: 'ML', year_level: 'Fourth Year'},
  scores: [], leaders: [], activities: [], rank: null, total_score: 0};
const featured = [
  {id: 11, title: 'Campus Welcome', description: 'Meet the CITE community.', start_at: '2026-09-24 08:00:00', location: 'Main Hall', poster_path: null, is_featured: 1},
  {id: 12, title: 'Team Games', description: 'Play alongside your team.', start_at: '2026-09-25 09:00:00', location: 'Gymnasium', poster_path: 'assets/images/cite_favicon.png', is_featured: 1},
  {id: 14, title: 'CITE Fair', description: 'See what is next.', start_at: '2026-09-27 09:00:00', location: 'Campus', poster_path: 'assets/images/cite_favicon.png', is_featured: 1},
  {id: 13, title: 'Ordinary Event', description: 'Not chosen for the carousel.', start_at: '2026-09-26 09:00:00', location: 'Library', poster_path: null, is_featured: 0},
];

(async () => {
  const browser = await chromium.launch({headless: true, executablePath: process.env.CHROME_PATH || 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe', args: ['--no-sandbox']});
  try {
    for (const width of [320, 390, 1280]) {
      const context = await browser.newContext({viewport: {width, height: 800}, reducedMotion: 'reduce'});
      const page = await context.newPage();
      const errors = [];
      page.on('pageerror', error => errors.push(error.message));
      await page.route('**/api/**', route => {
        const url = new URL(route.request().url());
        let payload = {success: true, data: {unread_notifications: 0, notifications: []}};
        if (url.pathname.endsWith('/auth.php')) payload = {success: true, authenticated: true, user: viewer, csrf_token: 'test'};
        if (url.pathname.endsWith('/student-portal.php')) {
          payload = url.searchParams.get('page') === 'team_members'
            ? {success: true, data: {members: [], pagination: {current_page: 1, last_page: 1, total: 0}}}
            : {success: true, data: {team}};
        }
        if (url.pathname.endsWith('/media.php')) payload = {success: true, data: {
          viewer, permissions: {moderate: false, manage_carousel: false, hide: false},
          featured_events: featured, active_events: [], post_events: [], carousel_events: [],
          event_program: [], own_posts: [], posts: [], next_cursor: null,
        }};
        return route.fulfill({json: payload});
      });

      await page.goto(`${base}/pages/student/home.html`, {waitUntil: 'domcontentloaded'});
      await page.locator('[data-home-slide].is-active .student-home-event__poster').waitFor();
      assert.equal(await page.locator('.student-home-event').count(), 2, 'Only event photos become carousel slides');
      assert.equal(await page.getByRole('heading', {name: 'Ordinary Event'}).count(), 0, 'Ordinary events stay out of the featured hero');
      assert.equal(await page.locator('[data-event-carousel]').count(), 0, 'Student homepage does not repeat the event carousel below the hero');
      assert.equal(await page.locator('[data-home-intro]').isVisible(), false, 'Text fallback stays hidden when event photos exist');
      assert.equal(await page.locator('[data-home-slide].is-active h2').textContent(), 'Team Games');
      assert.equal(await page.getByRole('link', {name: 'Attendance here'}).isVisible(), true, 'Attendance QR link is visible without waiting for a carousel slide');
      await page.locator('[data-home-carousel-next]').click();
      assert.equal(await page.locator('[data-home-slide].is-active h2').textContent(), 'CITE Fair');
      if (process.env.CITE_SCREENSHOT_DIR && width === 390) await page.screenshot({path: path.join(process.env.CITE_SCREENSHOT_DIR, 'student-home-event.png')});
      assert.equal(await page.locator('[data-home-carousel-dots] button[aria-current="true"]').count(), 1);
      await page.locator('[data-home-carousel-next]').click();
      assert.equal(await page.locator('[data-home-slide].is-active h2').textContent(), 'Team Games');
      assert.equal(await page.locator('[data-home-slide].is-active .student-home-event__poster').evaluate(node => getComputedStyle(node).zIndex), '0', 'Event poster is layered above the card background');
      await page.locator('[data-home-carousel-prev]').click();
      assert.equal(await page.locator('[data-home-slide].is-active h2').textContent(), 'CITE Fair');
      await page.evaluate(() => StudentHomeCarousel.render([{title: 'Older event', is_featured: 0}]));
      assert.equal(await page.locator('.student-home-event').count(), 0, 'Events without photos are not shown as photo slides');
      assert.equal(await page.locator('[data-home-intro]').isVisible(), true, 'A useful fallback appears when no event photos exist');
      assert.equal(await page.locator('[data-home-carousel-controls]').isVisible(), false, 'One slide has no carousel controls');
      const homeWidth = await page.evaluate(() => document.documentElement.scrollWidth);
      assert.ok(homeWidth <= width + 1, `Home has no horizontal overflow at ${width}px`);
      await page.getByRole('link', {name: 'Attendance here'}).click();
      await page.waitForURL('**/pages/student/attendance.html#attendance-qr');
      assert.equal(await page.locator('#attendance-qr').count(), 1, 'Quick access lands at the QR section');

      await page.goto(`${base}/pages/student/team.html`, {waitUntil: 'domcontentloaded'});
      await page.getByRole('heading', {name: 'Hero Academia', exact: true}).waitFor();
      assert.equal(await page.locator('[data-team-page]').evaluate(node => node.style.getPropertyValue('--team-accent')), '#FACC15');
      assert.equal(await page.locator('.student-team-hero').evaluate(node => getComputedStyle(node).borderTopColor), 'rgb(250, 204, 21)');
      assert.equal(await page.locator('.student-team-hero__color span').evaluate(node => getComputedStyle(node).backgroundColor), 'rgb(250, 204, 21)');
      if (process.env.CITE_SCREENSHOT_DIR && width === 390) await page.screenshot({path: path.join(process.env.CITE_SCREENSHOT_DIR, 'student-team-yellow.png')});
      const teamWidth = await page.evaluate(() => document.documentElement.scrollWidth);
      assert.ok(teamWidth <= width + 1, `Team has no horizontal overflow at ${width}px`);
      assert.deepEqual(errors, []);
      console.log(`PASS: homepage event carousel and Hero Academia yellow team accents at ${width}px`);
      await context.close();
    }
  } finally { await browser.close(); }
})().catch(error => { console.error(error); process.exitCode = 1; });
