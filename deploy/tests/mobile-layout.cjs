// Browser smoke test: run with Playwright installed locally or on NODE_PATH.
const { chromium } = require('playwright');

const base = process.env.CITE_BASE_URL || 'http://localhost/ITEventManagement';
const role = process.env.CITE_ROLE || 'student';
const pages = process.env.CITE_PAGES?.split(',') || ['team', 'events', 'attendance', 'leaderboard', 'profile', 'home'];
const widths = process.env.CITE_WIDTHS?.split(',').map(Number) || [320, 360, 390, 430, 768];
const mockUser = {
  id: 1,
  role: {student: 'Student', sbo: 'SBO Officer', faculty: 'Faculty', adviser: 'SBO Adviser', admin: 'Admin'}[role],
  username: 'test-student',
  first_name: 'Michaella',
  last_name: 'Calunsag',
  full_name: 'Michaella Jane Henobla Calunsag',
  must_change_password: false,
};

const team = {
  name: 'Titan Slayers',
  color: '#397565',
  school_year: '2026 - 2027',
  members_count: 372,
  current_member: {full_name: mockUser.full_name, initials: 'MC', year_level: 'Fourth Year'},
  scores: [],
  leaders: [],
  activities: [],
  rank: null,
  total_score: 0,
};
const event = {
  title: 'IT Days 2026 Opening and Team Activities',
  start_at: '2026-09-24 08:00:00',
  end_at: '2026-09-24 17:00:00',
  location: 'Campus Gymnasium',
  description: 'A full day of student activities and attendance.',
  schedule_state: 'upcoming',
  type: 'School Event',
  schedules: [],
};

(async () => {
  const browser = await chromium.launch({
    headless: true,
    executablePath: process.env.CHROME_PATH || 'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe',
    args: ['--no-sandbox'],
  });
  let failures = 0;
  try {
    for (const width of widths) {
      const context = await browser.newContext({viewport: {width, height: 740}, deviceScaleFactor: 1, isMobile: true, hasTouch: true});
      const page = await context.newPage();
      if (process.env.CITE_DEBUG) {
        page.on('pageerror', error => console.error('PAGE ERROR', error.message));
        page.on('console', message => {if (message.type() === 'error') console.error('CONSOLE ERROR', message.text());});
      }
      await page.route('**/api/**', route => {
        const url = new URL(route.request().url());
        let response = {success: true, data: {unread_notifications: 0, notifications: []}};
        if (url.pathname.endsWith('/auth.php')) response = {success: true, authenticated: true, user: mockUser, csrf_token: 'test'};
        if (url.pathname.endsWith('/student-portal.php')) {
          const type = url.searchParams.get('page');
          if (type === 'team') response = {success: true, data: {team}};
          if (type === 'team_members') response = {success: true, data: {members: [{full_name: 'Another Student', initials: 'AS', year_level: 'Fourth Year'}], pagination: {current_page: 1, last_page: 1, total: 1}}};
          if (type === 'events') response = {success: true, data: {active: [event], past: [event]}};
          if (type === 'attendance') response = {success: true, data: {summary: {present: 1, late: 0, absent: 0, excused: 0, total: 1, rate: 100}, current_event: event, records: [{...event, event_title: event.title, attendance_date: '2026-09-24', attendance_mode: 'whole_day', status: 'present', morning_in_at: '08:00:00', morning_out_at: '17:00:00'}]}};
          if (type === 'leaderboard') response = {success: true, data: {event, categories: [{id: 1, name: 'Sports'}], teams: [{...team, category_scores: {1: 0}}, {...team, name: 'Spy X Family', category_scores: {1: 0}}]}};
        }
        if (url.pathname.endsWith('/student-attendance-qr.php')) response = {success: true, data: {sessions: []}};
        if (url.pathname.endsWith('/student-profile.php')) response = {success: true, data: {profile: {full_name: mockUser.full_name, initials: 'MC', id_number: '2026-0001', year_level_label: 'Fourth Year', team_name: team.name, team_color: team.color, created_at: '2026-01-01'}, post_counts: {approved: 0, pending: 0}, own_posts: [], posts: [], post_events: [], viewer: mockUser, permissions: {}}};
        if (url.pathname.endsWith('/media.php')) response = {success: true, data: {permissions: {moderate: false, manage_carousel: false}, moderation_counts: {pending: 0}, featured_events: [], active_events: [], post_events: [], event_program: [], posts: [], own_posts: [], viewer: mockUser}};
        if (url.pathname.endsWith('/users.php')) response = {success: true, data: {roles: [], year_levels: [], teams: [], events: [], filter_roles: [], filter_statuses: [], summary: {total: 0, active: 0, sbo: 0}, pagination: {current_page: 1, last_page: 1, total: 0, from: 0, to: 0}, users: []}};
        if (url.pathname.endsWith('/officers.php')) response = {success: true, data: {assignments: [], year_levels: [], pagination: {current_page: 1, last_page: 1, total: 0, from: 0, to: 0}}};
        return route.fulfill({status: 200, contentType: 'application/json', body: JSON.stringify(response)});
      });
      for (const name of pages) {
        await page.goto(`${base}/pages/${role}/${name}.html`, {waitUntil: 'domcontentloaded'});
        await page.waitForTimeout(role === 'student' ? 750 : 1500);
        const result = await page.evaluate(({name, role}) => ({
          viewport: innerWidth,
          scrollWidth: document.documentElement.scrollWidth,
          clientWidth: document.documentElement.clientWidth,
          bodyWidth: document.body.getBoundingClientRect().width,
          navItems: document.querySelectorAll('[data-shared-mobile-links] a').length,
          clippedNavLabels: [...document.querySelectorAll('[data-shared-mobile-links] a span')].filter(node => node.scrollWidth > node.clientWidth + 1).map(node => node.textContent),
          navReady: Boolean(window.SharedNavigation),
          navHosts: document.querySelectorAll('[data-shared-navigation-host]').length,
          teamSearchTop: name === 'team' ? document.querySelector('[data-member-search]')?.getBoundingClientRect().top ?? null : null,
          mobileNavTop: document.querySelector('[data-shared-mobile-links]')?.getBoundingClientRect().top ?? null,
          loaded: Boolean(document.querySelector(role === 'student' ? ({
            team: '[data-team-page] h1',
            events: '[data-active-events] article',
            attendance: '[data-attendance-records] article',
            leaderboard: '[data-leaderboard] [class*="grid-cols-"]',
            profile: '.student-profile-summary',
            home: '.cite-media-shell',
          })[name] : '[data-shared-header]')),
          overflows: [...document.querySelectorAll('body *')].filter(node => {
            const rect = node.getBoundingClientRect();
            const style = getComputedStyle(node);
            return style.position !== 'fixed' && rect.width && rect.right > document.documentElement.clientWidth + 1;
          }).slice(0, 12).map(node => ({tag: node.tagName.toLowerCase(), cls: String(node.className).slice(0, 80), text: node.textContent.trim().slice(0, 40), bounds: [Math.round(node.getBoundingClientRect().left), Math.round(node.getBoundingClientRect().right)]})),
        }), {name, role});
        const expectedNavItems = role === 'student' ? 5 : role === 'sbo' ? 4 : 0;
        const teamSearchHiddenOnPhone = role === 'student' && name === 'team' && width <= 430 && (result.teamSearchTop === null || result.mobileNavTop === null || result.teamSearchTop >= result.mobileNavTop);
        const bad = result.scrollWidth > width + 1 || result.navItems !== expectedNavItems || result.clippedNavLabels.length > 0 || !result.loaded || teamSearchHiddenOnPhone;
        if (bad) failures++;
        console.log(JSON.stringify({page: name, width, bad, ...result}));
        if (process.env.CITE_SCREENSHOT && name === (process.env.CITE_SCREENSHOT_PAGE || 'team') && width === Number(process.env.CITE_SCREENSHOT_WIDTH || 360)) {
          await page.screenshot({path: process.env.CITE_SCREENSHOT, fullPage: process.env.CITE_SCREENSHOT_FULLPAGE === '1'});
        }
      }
      await context.close();
    }
  } finally {
    await browser.close();
  }
  process.exitCode = failures ? 1 : 0;
})().catch(error => {console.error(error); process.exitCode = 1;});
