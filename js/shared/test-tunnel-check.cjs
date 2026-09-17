'use strict';
const assert = require('node:assert/strict');
const http = require('node:http');
const https = require('node:https');
const base = new URL(process.argv[2] || 'http://127.0.0.1:8787');
if (!((base.hostname === '127.0.0.1' && base.port === '8787') || /^[a-z0-9-]+\.trycloudflare\.com$/.test(base.hostname))) throw new Error('Unexpected test origin');
let passed = 0;
function request(route, options = {}) {
    return new Promise((resolve, reject) => {
        const client = base.protocol === 'https:' ? https : http;
        const req = client.request({ hostname: base.hostname, port: base.port || undefined, path: route, method: options.method || 'GET', headers: options.headers || {} }, res => {
            const chunks = [];
            res.on('data', chunk => chunks.push(chunk));
            res.on('end', () => resolve({ status: res.statusCode, headers: res.headers, text: Buffer.concat(chunks).toString() }));
        });
        req.on('error', reject);
        req.setTimeout(20000, () => req.destroy(new Error('Test request timed out')));
        req.end(options.body);
    });
}
function check(condition, message) { assert.ok(condition, message); passed++; console.log('PASS: ' + message); }
(async () => {
    for (const route of ['/ITEventManagement/', '/ITEventManagement/pages/sbo/attendance.html', '/ITEventManagement/css/tailwind.css', '/ITEventManagement/js/sbo/attendance.js', '/ITEventManagement/js/vendor/qr-scanner/qr-scanner.min.js', '/ITEventManagement/js/vendor/qr-scanner/qr-scanner-worker.min.js', '/ITEventManagement/js/vendor/qrcode-generator/qrcode.mjs']) {
        const response = await request(route);
        check(response.status === 200, 'Page/asset reachable: ' + route);
    }
    const locationRoute = '/ITEventManagement/pages/adviser/locations.html';
    const location = await request(locationRoute);
    const baseMatch = location.text.match(/<base\s+href="([^"]+)"/i);
    check(location.status === 200 && baseMatch, 'Locations page supplies an application base path');
    const resolvedBase = new URL(baseMatch[1], new URL(locationRoute, base));
    check(resolvedBase.pathname === '/ITEventManagement/', 'Locations base resolves to this deployed project, not a hardcoded teammate folder');
    for (const resource of ['css/tailwind.css', 'css/adviser.css', 'assets/images/cite-logo.png', 'js/adviser/locations.js', 'js/shared/interactions.js']) {
        check((await request(new URL(resource, resolvedBase).pathname)).status === 200, 'Locations resource reachable: ' + resource);
    }
    check((await request(new URL('api/adviser-locations.php', resolvedBase).pathname)).status === 401, 'Locations API resolves correctly and requires authentication');
    for (const route of ['/phpmyadmin/', '/dashboard/', '/ITEventManagement/database/event_db.sql', '/ITEventManagement/.git/config', '/ITEventManagement/api/db_connect.php', '/ITEventManagement/api/test-phase-qr-windows.php', '/ITEventManagement/api/migrate_sqlite_to_mysql.php', '/ITEventManagement/.test-tunnel/state.json', '/ITEventManagement/js/shared/test-tunnel.cjs', '/ITEventManagement/assets/../../phpmyadmin/', '/ITEventManagement/assets/%2e%2e/%2e%2e/phpmyadmin/', '/ITEventManagement/assets/%252e%252e/file.png', '/ITEventManagement/assets/test.php']) {
        check((await request(route)).status === 404, 'Sensitive/path-traversal request blocked: ' + route);
    }
    check((await request('/ITEventManagement/api/users.php')).status === 401, 'User directory requires authentication');
    check((await request('/ITEventManagement/api/auth.php?action=session', { method: 'DELETE' })).status === 405, 'Unsupported methods blocked');
    const session = await request('/ITEventManagement/api/auth.php?action=session');
    const data = JSON.parse(session.text);
    check(session.status === 200 && data.authenticated === false && typeof data.csrf_token === 'string', 'Anonymous session and CSRF token issued');
    const cookies = session.headers['set-cookie'] || [];
    check(cookies.some(cookie => /;\s*Secure(?:;|$)/i.test(cookie) && /;\s*HttpOnly(?:;|$)/i.test(cookie)), 'Session cookie is Secure and HttpOnly');
    const cookie = cookies.map(value => value.split(';')[0]).join('; ');
    const body = JSON.stringify({ login: '__invalid_tunnel_test_account__', password: 'invalid' });
    const headers = { Cookie: cookie, 'Content-Type': 'application/json', 'Content-Length': Buffer.byteLength(body) };
    const rejected = await request('/ITEventManagement/api/auth.php?action=login', { method: 'POST', headers, body });
    check(rejected.status === 403, 'Login rejects missing CSRF token');
    const login = await request('/ITEventManagement/api/auth.php?action=login', { method: 'POST', headers: { ...headers, 'X-CSRF-Token': data.csrf_token }, body });
    check(login.status === 422 && /incorrect/i.test(JSON.parse(login.text).message), 'Valid session/CSRF reaches credential validation without session-expired error');
    const again = await request('/ITEventManagement/api/auth.php?action=session', { headers: { Cookie: cookie } });
    check(JSON.parse(again.text).csrf_token === data.csrf_token, 'Session survives consecutive public requests');
    console.log(passed + ' gateway checks passed. Real camera/GPS/mobile-data scanning still requires a phone test.');
})().catch(error => { console.error(error.message); process.exitCode = 1; });
