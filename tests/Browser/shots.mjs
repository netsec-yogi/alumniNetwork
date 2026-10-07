// Dev helper: screenshots of pages for visual review.
// node tests/Browser/shots.mjs <outDir> <guest|alumnus|admin> <width> <path...>
// ALUMNUS=<email> picks the member account; admin needs the demo TOTP secret (JBSWY3DPEHPK3PXP) on alumni.office.
// COLLAPSED=1 collapsed sidebar, DRAWER=1 open mobile drawer, VIEWPORT=1 viewport-only shot, CLICK_TEXT=<label> click a button first, THEME=dark|light|system.
import { createHmac } from 'node:crypto';
import { existsSync, mkdirSync } from 'node:fs';
import puppeteer from 'puppeteer-core';

const [outDir, who, width, ...paths] = process.argv.slice(2);
const BASE = process.env.APP_URL ?? 'http://localhost:8090';
const users = { alumnus: process.env.ALUMNUS ?? 'student@iiitm.ac.in', admin: 'alumni.office@iiitm.ac.in' };
const executablePath = [process.env.CHROME_PATH, '/usr/bin/chromium-browser', '/usr/bin/chromium', '/snap/bin/chromium', '/usr/bin/google-chrome'].find((p) => p && existsSync(p));

function totp(secret) {
    const a = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    let bits = '';
    for (const c of secret) bits += a.indexOf(c).toString(2).padStart(5, '0');
    const key = Buffer.from(bits.match(/.{8}/g).map((b) => parseInt(b, 2)));
    const ctr = Buffer.alloc(8);
    ctr.writeBigUInt64BE(BigInt(Math.floor(Date.now() / 30000)));
    const h = createHmac('sha1', key).update(ctr).digest();
    const o = h[h.length - 1] & 0xf;
    return String((h.readUInt32BE(o) & 0x7fffffff) % 1e6).padStart(6, '0');
}

mkdirSync(outDir, { recursive: true });
const browser = await puppeteer.launch({ executablePath, headless: true, args: ['--no-sandbox'] });
const page = await browser.newPage();
await page.setViewport({ width: Number(width), height: 900, deviceScaleFactor: 1 });
if (process.env.COLLAPSED) await page.evaluateOnNewDocument(() => localStorage.setItem('ui.sidebar', 'collapsed'));
if (process.env.THEME) await page.evaluateOnNewDocument((t) => localStorage.setItem('ui.theme', t), process.env.THEME);
const errors = [];
page.on('console', (m) => m.type() === 'error' && errors.push(m.text()));
page.on('pageerror', (e) => errors.push(String(e)));

if (who !== 'guest') {
    await page.goto(`${BASE}/login`, { waitUntil: 'networkidle0' });
    await page.type('input[type=email]', users[who]);
    await page.type('input[type=password]', 'Demo-Password-2026');
    await Promise.all([page.waitForNavigation({ waitUntil: 'networkidle0' }), page.click('button[type=submit]')]);
    if (page.url().includes('two-factor-challenge')) {
        await page.type('input[autocomplete=one-time-code]', totp('JBSWY3DPEHPK3PXP'));
        await Promise.all([page.waitForNavigation({ waitUntil: 'networkidle0' }), page.click('button[type=submit]')]);
    }
}
for (const p of paths) {
    await page.goto(BASE + p, { waitUntil: 'networkidle0' });
    if (process.env.DRAWER) { await page.click('button[aria-controls=sidebar]'); await new Promise((r) => setTimeout(r, 400)); }
    if (process.env.HOVER) await page.hover(process.env.HOVER);
    if (process.env.CLICK_TEXT) {
        await page.evaluate((t) => [...document.querySelectorAll('button, a')].find((el) => el.textContent.trim() === t)?.click(), process.env.CLICK_TEXT);
        await new Promise((r) => setTimeout(r, 400));
    }
    const name = `${who}-${width}-${p.replace(/[^a-z0-9]+/gi, '_').replace(/^_|_$/g, '') || 'home'}.png`;
    await page.screenshot({ path: `${outDir}/${name}`, fullPage: !process.env.VIEWPORT });
    console.log(name + (errors.length ? `  <-- ${errors.length} console error(s): ${errors[0].slice(0, 120)}` : ''));
    errors.length = 0;
}
await browser.close();
