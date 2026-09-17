'use strict';

// Development-only gateway. Never point a public tunnel at XAMPP's whole root.
const http = require('node:http');
const { spawn } = require('node:child_process');
const fs = require('node:fs');
const path = require('node:path');

const project = path.resolve(__dirname, '../..');
const runtime = path.join(project, '.test-tunnel');
const prefix = '/ITEventManagement/';
const port = 8787;
const endpoints = new Set([
    'auth.php', 'login.php', 'logout.php', 'session.php', 'users.php', 'officers.php',
    'teams.php', 'events.php', 'adviser-events.php', 'adviser-locations.php',
    'adviser-dashboard.php', 'attendance.php', 'adviser-attendance-scans.php',
    'scores.php', 'leaderboard.php', 'announcements.php', 'reports.php', 'posts.php',
    'sbo-assignments.php', 'sbo-attendance.php', 'sbo-media.php', 'sbo-scores.php',
    'sbo-students.php', 'student-attendance-qr.php', 'student-home.php', 'student-portal.php',
]);
const staticFile = /^(?:pages\/[A-Za-z0-9_/-]+\.html|(?:css|js|assets)\/[A-Za-z0-9_./%-]+\.(?:css|js|mjs|png|jpe?g|gif|webp|svg|ico|woff2?|ttf|otf|mp4|webm|pdf))$/i;
const rates = new Map();
let child;
let closing = false;

function route(raw) {
    const pathname = raw.split('?')[0];
    let decoded;
    try { decoded = decodeURIComponent(pathname); } catch { return null; }
    if (decoded.includes('\\') || decoded.includes('%') || /[\x00-\x1f\x7f]/.test(decoded)) return null;
    if (decoded.split('/').some(part => part.startsWith('.') || part === '..')) return null;
    if (decoded === '/' || decoded === '/ITEventManagement') return 'redirect';
    if (!decoded.startsWith(prefix)) return null;
    const relative = decoded.slice(prefix.length);
    if (!relative || (relative.startsWith('api/') && endpoints.has(relative.slice(4))) || staticFile.test(relative)) return raw;
    return null;
}

function deny(res, status, message) {
    res.writeHead(status, { 'Content-Type': 'text/plain; charset=utf-8', 'Cache-Control': 'no-store' });
    res.end(message);
}

const server = http.createServer((req, res) => {
    const destination = route(req.url || '');
    if (!destination) return deny(res, 404, 'Not found');
    if (!['GET', 'HEAD', 'POST'].includes(req.method)) return deny(res, 405, 'Method not allowed');
    if (destination === 'redirect') {
        res.writeHead(302, { Location: prefix });
        return res.end();
    }
    const ip = req.headers['cf-connecting-ip'] || req.socket.remoteAddress;
    const now = Date.now();
    const entry = rates.get(ip);
    const current = entry && now - entry.start < 60000 ? entry : { start: now, count: 0 };
    current.count++;
    rates.set(ip, current);
    if (current.count > 600) return deny(res, 429, 'Too many requests. Try again shortly.');
    if (Number(req.headers['content-length'] || 0) > 25 * 1024 * 1024) return deny(res, 413, 'Request too large');

    const headers = { ...req.headers, host: 'localhost', 'x-forwarded-proto': 'https' };
    for (const header of ['connection', 'proxy-connection', 'proxy-authorization', 'upgrade', 'forwarded', 'x-forwarded-host']) delete headers[header];
    const upstream = http.request({ hostname: '127.0.0.1', port: 80, path: destination, method: req.method, headers }, response => {
        const outgoing = { ...response.headers, 'x-content-type-options': 'nosniff', 'referrer-policy': 'same-origin' };
        delete outgoing.connection;
        delete outgoing.server;
        delete outgoing['x-powered-by'];
        if (outgoing.location) {
            outgoing.location = outgoing.location.replace(/^https?:\/\/(?:localhost|127\.0\.0\.1)(?::\d+)?(?=\/)/i, '');
            if (/^https?:\/\//i.test(outgoing.location)) {
                response.resume();
                return deny(res, 502, 'Unexpected upstream redirect');
            }
        }
        if (outgoing['set-cookie']) {
            outgoing['set-cookie'] = outgoing['set-cookie'].map(cookie => /;\s*secure(?:;|$)/i.test(cookie) ? cookie : cookie + '; Secure');
        }
        res.writeHead(response.statusCode || 502, outgoing);
        response.pipe(res);
    });
    let bytes = 0;
    req.on('data', chunk => {
        bytes += chunk.length;
        if (bytes > 25 * 1024 * 1024) {
            upstream.destroy();
            if (!res.headersSent) deny(res, 413, 'Request too large');
        }
    });
    upstream.setTimeout(30000, () => upstream.destroy());
    upstream.on('error', () => {
        if (!res.headersSent) deny(res, 502, 'Local Apache is unavailable');
        else res.destroy();
    });
    req.on('aborted', () => upstream.destroy());
    res.on('close', () => { if (!res.writableEnded) upstream.destroy(); });
    req.pipe(upstream);
});

function shutdown() {
    if (closing) return;
    closing = true;
    if (child && child.exitCode === null) child.kill();
    server.close();
    setTimeout(() => process.exit(0), 1000).unref();
}

server.on('error', error => { console.error(error.message); shutdown(); process.exitCode = 1; });
server.listen(port, '127.0.0.1', () => {
    console.log(`Restricted gateway listening on 127.0.0.1:${port}`);
    if (process.argv.includes('--proxy-only')) return;
    const executable = path.join(runtime, 'cloudflared.exe');
    if (!fs.existsSync(executable)) { console.error('Run start-test-tunnel.ps1 first.'); shutdown(); return; }
    child = spawn(executable, ['tunnel', '--url', `http://127.0.0.1:${port}`, '--protocol', 'http2', '--no-autoupdate'], { windowsHide: true, stdio: ['ignore', 'pipe', 'pipe'] });
    const state = { gatewayPid: process.pid, tunnelPid: child.pid, startedAt: new Date().toISOString(), expiresAt: new Date(Date.now() + 2 * 3600000).toISOString() };
    fs.writeFileSync(path.join(runtime, 'state.json'), JSON.stringify(state, null, 2));
    child.stdout.pipe(process.stdout);
    child.stderr.pipe(process.stderr);
    child.on('error', error => { console.error(error.message); shutdown(); });
    child.on('exit', shutdown);
});
setInterval(() => { for (const [ip, entry] of rates) if (Date.now() - entry.start > 60000) rates.delete(ip); }, 60000).unref();
setTimeout(shutdown, 2 * 3600000).unref();
process.on('SIGINT', shutdown);
process.on('SIGTERM', shutdown);
