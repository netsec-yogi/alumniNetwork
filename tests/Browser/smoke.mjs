/**
 * Browser smoke test.
 *
 * A blank Inertia page still returns 200, and a CSP violation only shows up
 * in the browser console -- so this drives a real Chromium over the main
 * journeys and fails on any console error, page error or CSP violation.
 *
 * It also walks the complete mandatory-2FA flow for the seeded super
 * administrator: forced enrolment, password confirmation, QR/secret, TOTP
 * confirmation, one-time recovery codes, and the TOTP challenge on the next
 * sign-in. Run it against freshly seeded data (`make fresh demo`), since it
 * enrols that administrator in 2FA.
 *
 *   node tests/Browser/smoke.mjs [baseUrl]
 */
import { createHmac } from 'node:crypto';
import { existsSync } from 'node:fs';
import puppeteer from 'puppeteer-core';

const BASE = (process.argv[2] ?? process.env.APP_URL ?? 'http://localhost:8090').replace(/\/$/, '');
const ADMIN = { email: process.env.SEED_ADMIN_EMAIL ?? 'admin@iiitm.ac.in', password: process.env.SEED_ADMIN_PASSWORD ?? 'ChangeMe-Alumni-2026' };
const DEMO_PASSWORD = 'Demo-Password-2026';

const executablePath = [process.env.CHROME_PATH, '/usr/bin/chromium-browser', '/usr/bin/chromium', '/snap/bin/chromium', '/usr/bin/google-chrome'].find(
    (p) => p && existsSync(p),
);
if (!executablePath) {
    console.error('No Chrome/Chromium found. Set CHROME_PATH.');
    process.exit(2);
}

/** RFC 6238 TOTP, as an authenticator app would compute it. */
function totp(base32Secret, offsetSteps = 0) {
    const alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    let bits = '';
    for (const c of base32Secret.replace(/[\s=]/g, '').toUpperCase()) bits += alphabet.indexOf(c).toString(2).padStart(5, '0');
    const key = Buffer.from(bits.match(/.{8}/g).map((b) => parseInt(b, 2)));
    const counter = Buffer.alloc(8);
    counter.writeBigUInt64BE(BigInt(Math.floor(Date.now() / 1000 / 30) + offsetSteps));
    const hmac = createHmac('sha1', key).update(counter).digest();
    const offset = hmac[hmac.length - 1] & 0xf;
    return String((hmac.readUInt32BE(offset) & 0x7fffffff) % 1_000_000).padStart(6, '0');
}

const failures = [];
// Set while deliberately visiting a forbidden page: in local mode Laravel's
// stock error page (inline styles) renders, which the CSP rightly blocks.
let expectingErrorPage = false;
const browser = await puppeteer.launch({ executablePath, headless: true, args: ['--no-sandbox'] });

async function newPage() {
    const context = await browser.createBrowserContext();
    const page = await context.newPage();
    page.setDefaultTimeout(15000);
    page.on('console', (msg) => {
        if (msg.type() === 'error' && !expectingErrorPage) failures.push(`[console] ${page.url()}: ${msg.text()}`);
    });
    page.on('pageerror', (err) => failures.push(`[pageerror] ${page.url()}: ${err.message}`));
    // Surface CSP violations explicitly; they are the likeliest regression.
    await page.evaluateOnNewDocument(() => {
        document.addEventListener('securitypolicyviolation', (e) => console.error(`CSP violation: ${e.violatedDirective} ${e.blockedURI}`));
    });
    return page;
}

async function expectText(page, text, label) {
    try {
        await page.waitForFunction((t) => document.body.innerText.includes(t), {}, text);
        console.log(`  ✓ ${label}`);
    } catch {
        failures.push(`${label}: "${text}" not found on ${page.url()}`);
        console.log(`  ✗ ${label}`);
    }
}

async function visit(page, path, text) {
    await page.goto(BASE + path, { waitUntil: 'networkidle0' });
    await expectText(page, text, `${path} renders`);
}

async function signIn(page, email, password) {
    await page.goto(`${BASE}/login`, { waitUntil: 'networkidle0' });
    await page.type('input[type=email]', email);
    await page.type('input[type=password]', password);
    await Promise.all([page.waitForNavigation({ waitUntil: 'networkidle0' }).catch(() => {}), page.click('button[type=submit]')]);
    await page.waitForNetworkIdle();
}

async function clickButton(page, text) {
    const handle = await page.waitForFunction(
        (t) => [...document.querySelectorAll('button')].find((b) => b.innerText.trim() === t && b.offsetParent !== null),
        {},
        text,
    );
    await handle.asElement().click();
}

try {
    console.log('Public pages');
    {
        const page = await newPage();
        await visit(page, '/', 'A lifelong network');
        await visit(page, '/login', 'Sign in');
        await visit(page, '/register', 'Join Alumni Connect');
        await visit(page, '/forgot-password', 'Reset your password');
        await visit(page, '/stories', 'Alumni stories');
        const story = await page.$eval('a[href*="/stories/"]', (a) => a.getAttribute('href'));
        await visit(page, new URL(story, BASE).pathname, 'All stories');
        await visit(page, '/achievements', 'Alumni achievements');
        await visit(page, '/distinguished-alumni', 'Distinguished alumni');
        await visit(page, '/give', 'Give back to IIITM');
        await visit(page, '/campaigns', 'Fundraising campaigns');
        await page.close();
    }

    console.log('Student (demo account)');
    {
        const page = await newPage();
        await signIn(page, 'student@iiitm.ac.in', DEMO_PASSWORD);
        await expectText(page, ', Student', 'student lands on dashboard');
        await visit(page, '/directory', 'Alumni directory');
        const firstProfile = await page.$eval('a[href*="/alumni/"]', (a) => a.getAttribute('href'));
        await visit(page, new URL(firstProfile, BASE).pathname, 'At IIITM');
        await visit(page, '/profile/security', 'Two-factor authentication');
        await visit(page, '/profile/sessions', 'This device');
        await visit(page, '/feed', 'People I follow');
        await visit(page, '/connections', 'Connections');
        await visit(page, '/communities', 'Communities & chapters');
        const group = await page.$eval('a[href*="/communities/"]', (a) => a.getAttribute('href'));
        await visit(page, new URL(group, BASE).pathname, 'members');
        await visit(page, '/events', 'Alumni meets, reunions');
        const event = await page.$eval('a[href*="/events/"]', (a) => a.getAttribute('href'));
        await visit(page, new URL(event, BASE).pathname, 'Add to calendar');
        await visit(page, '/jobs', 'Jobs & internships');
        const job = await page.$eval('a[href*="/jobs/"][href$="0"], a[href*="/jobs/"]:not([href*="referrals"]):not([href*="create"])', (a) => a.getAttribute('href'));
        await visit(page, new URL(job, BASE).pathname, 'Posted by');
        await visit(page, '/mentoring', 'Mentoring');
        await visit(page, '/mentoring/find?category=career', 'Find a mentor');
        await visit(page, '/notifications', 'Notifications');
        await visit(page, '/messages', 'Private conversations');
        await visit(page, '/startups', 'Alumni startups');
        await visit(page, '/research', 'Research & collaboration');
        await visit(page, '/speakers', 'Speaker network');
        await visit(page, '/volunteering', 'Give time to students');
        await visit(page, '/my-donations', 'My donations');
        await visit(page, '/surveys', 'Surveys');
        expectingErrorPage = true;
        const adminStatus = await page.goto(`${BASE}/admin`, { waitUntil: 'networkidle0' }).then((r) => r.status());
        expectingErrorPage = false;
        if (adminStatus !== 403) failures.push(`student reached /admin (status ${adminStatus})`);
        else console.log('  ✓ student is refused /admin (403)');

        // Passkey round trip with Chrome's virtual authenticator (a platform
        // authenticator that performs user verification).
        const cdp = await page.createCDPSession();
        await cdp.send('WebAuthn.enable');
        await cdp.send('WebAuthn.addVirtualAuthenticator', {
            options: { protocol: 'ctap2', transport: 'internal', hasResidentKey: true, hasUserVerification: true, isUserVerified: true, automaticPresenceSimulation: true },
        });
        await visit(page, '/profile/security', 'Passkeys');
        await clickButton(page, 'Add a passkey');
        await page.waitForSelector('dialog[open] input[type=password]');
        await page.type('dialog[open] input[type=password]', DEMO_PASSWORD);
        await clickButton(page, 'Confirm');
        await page.waitForSelector('#passkey-form input');
        await clickButton(page, 'Continue');
        await expectText(page, 'Passkey added', 'passkey registered with the virtual authenticator');
        await expectText(page, 'not used yet', 'new passkey is listed');

        await page.click('button[aria-haspopup=menu]');
        await clickButton(page, 'Sign out');
        await page.waitForNetworkIdle();
        // The login page offers saved passkeys via autofill; the virtual
        // authenticator accepts that at once, so it may sign in before any
        // click. Otherwise use the explicit button.
        await page.goto(`${BASE}/login`, { waitUntil: 'networkidle0' });
        await new Promise((r) => setTimeout(r, 1500));
        if (page.url().includes('/login')) {
            await Promise.all([page.waitForNavigation({ waitUntil: 'networkidle0' }).catch(() => {}), clickButton(page, 'Sign in with a passkey')]);
        }
        await expectText(page, ', Student', 'signed back in with the passkey, no password');
        await page.close();
    }

    console.log('Super administrator: mandatory 2FA enrolment');
    {
        const page = await newPage();
        await signIn(page, ADMIN.email, ADMIN.password);

        if (page.url().includes('/two-factor-challenge')) {
            failures.push('Admin already has 2FA enrolled; run against fresh seed data (make fresh demo).');
        } else {
            await page.goto(`${BASE}/admin`, { waitUntil: 'networkidle0' });
            await expectText(page, 'Two-factor authentication is required for your role', 'admin is forced to enrol before /admin');

            await clickButton(page, 'Set up two-factor authentication');
            await page.waitForSelector('dialog[open] input[type=password]');
            await page.type('dialog[open] input[type=password]', ADMIN.password);
            await clickButton(page, 'Confirm');
            await page.waitForSelector('code.select-all');
            await expectText(page, 'Enter this key manually', 'QR code and setup key shown');
            if (!(await page.$('svg'))) failures.push('QR code SVG missing');

            const secret = await page.$eval('code.select-all', (el) => el.textContent.trim());
            await page.type('input[autocomplete=one-time-code]', totp(secret));
            await clickButton(page, 'Confirm');
            await expectText(page, "won't be shown again", 'recovery codes shown once after confirmation');

            await page.reload({ waitUntil: 'networkidle0' });
            const stillShown = await page.evaluate(() => document.body.innerText.includes("won't be shown again"));
            if (stillShown) failures.push('Recovery codes still visible after reload');
            else console.log('  ✓ recovery codes gone after reload');

            await visit(page, '/admin', 'Alumni network at a glance');
            await visit(page, '/admin/verification', 'Alumni verification');
            await visit(page, '/admin/users', 'Accounts, roles and access');
            await visit(page, '/admin/audit-logs', 'two_factor.confirmed');
            await visit(page, '/admin/alumni', 'records match');
            const alumnus = await page.$$eval('a[href*="/admin/alumni/"]', (as) => as.map((a) => a.getAttribute('href')).find((h) => /\/admin\/alumni\/\d+$/.test(h)));
            await visit(page, new URL(alumnus, BASE).pathname, 'Engagement score');
            await visit(page, '/admin/alumni/import', 'File format');
            await visit(page, '/admin/events', 'Create events');
            const adminEvent = await page.$$eval('a[href*="/admin/events/"]', (as) => as.map((a) => a.getAttribute('href')).find((h) => !h.endsWith('/create')));
            await visit(page, new URL(adminEvent, BASE).pathname, 'Confirmed');
            await visit(page, new URL(adminEvent, BASE).pathname + '/check-in', 'Check-in desk');
            await visit(page, '/admin/events/create', 'Saved as a draft');
            await visit(page, '/admin/jobs', 'Job moderation');
            await visit(page, '/admin/communities', 'Official groups');
            await visit(page, '/admin/moderation', 'Moderation');
            await visit(page, '/admin/reports', 'CASE engagement rate');
            await visit(page, '/admin/programmes', 'Programmes & departments');
            await visit(page, '/admin/achievements', 'Review submissions');
            await visit(page, '/admin/stories', 'New story');
            await visit(page, '/admin/stories/create', 'Markdown');
            await visit(page, '/admin/distinguished-alumni', 'Add honouree');
            await visit(page, '/admin/communications', 'Email and in-app messages');
            await visit(page, '/admin/communications/create', 'people match');
            await visit(page, '/admin/donations', 'Financial records');
            await visit(page, '/admin/fundraising', 'Fundraising campaigns');
            await visit(page, '/admin/surveys', 'Surveys');
            await visit(page, '/admin/analytics', 'Analytics');

            // Sign out, then sign back in through the TOTP challenge. The code
            // used for enrolment cannot be replayed, so wait for the next step.
            await page.click('button[aria-haspopup=menu]'); // account menu
            await clickButton(page, 'Sign out');
            await page.waitForNetworkIdle();
            const wait = 31 - (Math.floor(Date.now() / 1000) % 30);
            console.log(`  … waiting ${wait}s for a fresh TOTP window`);
            await new Promise((r) => setTimeout(r, wait * 1000));

            await signIn(page, ADMIN.email, ADMIN.password);
            await expectText(page, 'Enter the 6-digit code', 'sign-in asks for the TOTP code');
            await page.type('input[autocomplete=one-time-code]', totp(secret));
            await Promise.all([page.waitForNavigation({ waitUntil: 'networkidle0' }).catch(() => {}), page.click('button[type=submit]')]);
            await page.goto(`${BASE}/admin`, { waitUntil: 'networkidle0' });
            await expectText(page, 'Alumni network at a glance', 'admin reaches /admin after TOTP sign-in');
        }
        await page.close();
    }
} catch (e) {
    failures.push(`Unexpected: ${e.stack ?? e}`);
} finally {
    await browser.close();
}

if (failures.length) {
    console.error(`\n${failures.length} problem(s):\n - ${failures.join('\n - ')}`);
    process.exit(1);
}
console.log('\nSmoke test passed.');
