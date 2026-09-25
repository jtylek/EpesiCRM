// Screenshots of the running app, signed in with the cookie session.php wrote.
//
//   node shoot.cjs <session.json> <base url> <out dir> <theme: dark|light> <page>...
//
// A page is a path under the base URL, optionally with a tab to click after
// loading: "meetings/13@History". Each is saved as <out dir>/<page, slugged>.png.

const fs = require('fs');
const os = require('os');
const path = require('path');

// A playwright-core whose Chromium is already downloaded: npx leaves several
// versions in its cache, each wanting its own browser build, and Google Chrome
// itself may not be installed. Falls back to Edge, which Windows always has.
function findPlaywright() {
    const home = os.homedir();
    const browsersDir = process.env.PLAYWRIGHT_BROWSERS_PATH
        || (process.platform === 'win32' ? path.join(home, 'AppData', 'Local', 'ms-playwright')
            : process.platform === 'darwin' ? path.join(home, 'Library', 'Caches', 'ms-playwright')
                : path.join(home, '.cache', 'ms-playwright'));
    const npxCache = process.platform === 'win32'
        ? path.join(home, 'AppData', 'Local', 'npm-cache', '_npx')
        : path.join(home, '.npm', '_npx');

    const candidates = [];
    for (const dir of fs.existsSync(npxCache) ? fs.readdirSync(npxCache) : []) {
        candidates.push(path.join(npxCache, dir, 'node_modules', 'playwright-core'));
    }
    try {
        const globalRoot = require('child_process').execSync('npm root -g', { encoding: 'utf8' }).trim();
        candidates.push(path.join(globalRoot, '@playwright', 'mcp', 'node_modules', 'playwright-core'));
        candidates.push(path.join(globalRoot, 'playwright-core'));
    } catch (e) { /* no npm on PATH */ }

    let fallback = null;
    for (const candidate of candidates) {
        const browsers = path.join(candidate, 'browsers.json');
        if (!fs.existsSync(browsers)) continue;
        fallback ??= candidate;
        const chromium = JSON.parse(fs.readFileSync(browsers, 'utf8')).browsers.find(b => b.name === 'chromium-headless-shell' || b.name === 'chromium');
        if (chromium && fs.existsSync(path.join(browsersDir, `chromium_headless_shell-${chromium.revision}`))) {
            return { module: candidate, channel: undefined };
        }
    }
    if (!fallback) throw new Error('No playwright-core found. Run: npx -y playwright@latest install chromium');
    return { module: fallback, channel: process.platform === 'win32' ? 'msedge' : 'chrome' };
}

(async () => {
    const [sessionFile, base, out, theme, ...pages] = process.argv.slice(2);
    if (!pages.length) throw new Error('usage: node shoot.cjs <session.json> <base url> <out dir> <dark|light> <page>...');

    const session = JSON.parse(fs.readFileSync(sessionFile, 'utf8'));
    const { module, channel } = findPlaywright();
    const { chromium } = require(module);
    fs.mkdirSync(out, { recursive: true });

    const browser = await chromium.launch({ channel });
    console.log(`browser: ${channel ?? 'playwright chromium'} ${browser.version()} (${module})`);
    const context = await browser.newContext({ viewport: { width: 1600, height: 1000 }, colorScheme: theme });
    await context.addCookies([{ name: session.name, value: session.value, url: base }]);
    // Filament keeps the chosen theme in localStorage, not in the colour scheme.
    await context.addInitScript(t => localStorage.setItem('theme', t), theme);
    const page = await context.newPage();

    for (const spec of pages) {
        const [route, tab] = spec.split('@');
        await page.goto(base.replace(/\/$/, '') + '/' + route.replace(/^\//, ''), { waitUntil: 'networkidle' });
        if (page.url().includes('/login')) throw new Error(`Sent to the login page for ${route}: the session was not accepted.`);
        if (tab) {
            await page.getByRole('tab', { name: tab }).first().click();
            await page.waitForLoadState('networkidle');
        }
        await page.waitForTimeout(500);
        const file = path.join(out, (spec.replace(/[^a-z0-9]+/gi, '-').replace(/^-|-$/g, '') || 'home') + '.png');
        await page.screenshot({ path: file });
        console.log(`${spec} -> ${file} (${await page.title()})`);
    }

    await browser.close();
})().catch(e => { console.error(e.message); process.exit(1); });
