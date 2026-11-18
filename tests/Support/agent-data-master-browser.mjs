// Run against the isolated PHP fixture. Pass an installed playwright/index.mjs as argv[2].
import assert from 'node:assert/strict';
import { mkdir, writeFile } from 'node:fs/promises';
import { pathToFileURL } from 'node:url';
import path from 'node:path';
import { execFileSync } from 'node:child_process';

const { chromium } = await import(pathToFileURL(path.resolve(process.argv[2])).href);
const browser = await chromium.launch({ headless: true, channel: process.env.PLAYWRIGHT_CHANNEL || 'chrome' });
const origin = 'http://127.0.0.1:8881';
const output = path.resolve('.impeccable/review/agent-data-master');
await mkdir(output, { recursive: true });
const report = { checks: [], viewports: [], pageErrors: [], consoleErrors: [], failedRequests: [] };
const check = (name, actual, expected) => {
    assert.deepEqual(actual, expected, name);
    report.checks.push({ name, actual });
    console.log('PASS '+name);
};

async function actor(username) {
    const context = await browser.newContext({ viewport: { width: 1440, height: 1000 }, deviceScaleFactor: 1 });
    const page = await context.newPage();
    page.setDefaultTimeout(15000);
    page.setDefaultNavigationTimeout(15000);
    page.on('pageerror', error => report.pageErrors.push(error.message));
    page.on('console', message => { if (message.type() === 'error') report.consoleErrors.push(message.text()); });
    page.on('response', response => { if (response.status() >= 400) report.failedRequests.push({ url: response.url(), status: response.status() }); });
    await page.goto(`${origin}/login`);
    await page.getByLabel('Username', { exact: true }).fill(username);
    await page.getByLabel('Password', { exact: true }).fill('Synthetic-DM-2026!');
    await page.locator('select[name=campaign]').selectOption('dm_alpha');
    await page.getByRole('button', { name: 'Sign in', exact: true }).click();
    if (new URL(page.url()).pathname === '/login/pending') {
        await page.locator('form[action$="/login/pending"] button[type=submit]').click();
    }
    await page.waitForURL('**/dashboard');
    return page;
}

async function save(admin) {
    const response = admin.waitForResponse(response => response.request().method() === 'POST' && response.url().includes('/configuration/data-master/'));
    await admin.getByRole('button', { name: 'Save Data Master permissions', exact: true }).click();
    check('permission save redirect', (await response).status(), 302);
    await admin.getByLabel('Enable agent Data Master', { exact: true }).waitFor();
}

async function capture(page, name) {
    for (const width of [375, 768, 1024, 1440]) {
        await page.setViewportSize({ width, height: 1000 });
        await page.waitForTimeout(300); // Let the existing responsive shell transition finish.
        await page.evaluate(() => scrollTo(0, 0));
        const metrics = await page.evaluate(() => ({
            inner: innerWidth, dpr: devicePixelRatio, scale: visualViewport.scale,
            scroll: document.documentElement.scrollWidth,
            header: document.querySelector('header').getBoundingClientRect().height,
            sidebar: document.querySelector('#sidebar').getBoundingClientRect().width,
            xl: matchMedia('(min-width: 1280px)').matches,
        }));
        await page.screenshot({ path: path.join(output, `${name}-${width}.png`), fullPage: true, scale: 'css' });
        if (metrics.scroll > width) {
            const overflowing = await page.evaluate(() => [...document.querySelectorAll('body *')].filter(el => {
                const r = el.getBoundingClientRect();
                return !el.closest('.table-scroll-wrap') && r.width > 0 && r.right > innerWidth + 1 && getComputedStyle(el).visibility !== 'hidden';
            }).slice(0, 45).map(el => ({ tag: el.tagName, id: el.id, class: el.className?.baseVal ?? el.className, right: el.getBoundingClientRect().right, position: getComputedStyle(el).position, text: el.textContent.trim().slice(0, 80) })));
            console.log(JSON.stringify({ overflowing }, null, 2));
        }
        check(`${name} ${width} CSS viewport`, metrics.inner, width);
        check(`${name} ${width} DPR`, metrics.dpr, 1);
        check(`${name} ${width} scale`, metrics.scale, 1);
        assert.ok(metrics.scroll <= width, `${name} ${width} document overflow: ${JSON.stringify(metrics)}`);
        check(`${name} ${width} header`, metrics.header, 64);
        check(`${name} ${width} sidebar`, metrics.sidebar, 248);
        check(`${name} ${width} xl breakpoint`, metrics.xl, width >= 1280);
        report.viewports.push({ name, width, ...metrics });
    }
}

try {
    const agent = await actor('dm-agent');
    await agent.getByRole('link', { name: 'Data Master', exact: true }).click();
    await agent.waitForURL('**/data-master');
    check('own scope', await agent.getByText('Your submissions only', { exact: true }).count(), 1);
    check('first page', await agent.locator('table tbody tr').count(), 20);
    check('other agents hidden', await agent.getByText('Synthetic Other Client', { exact: false }).count(), 0);
    await agent.locator('a[rel=next]:visible').click();
    await agent.waitForURL('**page=2');
    check('second page', await agent.locator('table tbody tr').count(), 4);
    await agent.getByRole('link', { name: 'Clear filters', exact: true }).click();
    await agent.waitForURL('**type=sales');
    await agent.getByLabel('Search records', { exact: true }).fill('Synthetic Sales');
    await agent.getByLabel('Submitted from', { exact: true }).fill('2026-11-12');
    await agent.getByLabel('Submitted through', { exact: true }).fill('2026-11-12');
    await agent.getByText('Column filters', { exact: true }).click();
    await agent.getByLabel('Result', { exact: true }).fill('Confirmed');
    await agent.getByRole('button', { name: 'Apply filters', exact: true }).click();
    await agent.waitForURL('**search=Synthetic*');
    check('combined filter results', await agent.locator('table tbody tr').count(), 11);
    check('combined filter excludes pending', (await agent.locator('table tbody').innerText()).includes('Pending'), false);
    await agent.getByRole('combobox', { name: 'Form', exact: true }).selectOption('feedback');
    await agent.getByRole('button', { name: 'Load form', exact: true }).click();
    await agent.waitForURL('**type=feedback');
    check('switch form resets filters', await agent.getByLabel('Search records', { exact: true }).inputValue(), '');
    check('feedback rows', await agent.locator('table tbody tr').count(), 2);
    await agent.getByLabel('Search records', { exact: true }).fill('No synthetic matching row');
    await agent.getByRole('button', { name: 'Apply filters', exact: true }).click();
    await agent.getByText('No submissions match this view.', { exact: false }).waitFor();
    check('empty state', await agent.getByText('No submissions match this view.', { exact: false }).count(), 1);
    await agent.goto(`${origin}/data-master?type=sales`);
    await capture(agent, 'agent');
    await agent.setViewportSize({ width: 375, height: 1000 });
    await agent.waitForTimeout(300);
    await agent.getByText('Column filters', { exact: true }).focus();
    await agent.keyboard.press('Enter');
    check('keyboard opens column filters', await agent.locator('details[open]').count(), 1);
    check('expanded mobile has no document overflow', await agent.evaluate(() => document.documentElement.scrollWidth), 375);
    await agent.evaluate(() => scrollTo(0, 0));
    await agent.screenshot({ path: path.join(output, 'agent-375-columns.png'), fullPage: true, scale: 'css' });

    const admin = await actor('dm-super');
    await admin.goto(`${origin}/admin/configuration?tab=data-master&data_master_campaign=dm_alpha`);
    await admin.getByLabel('Own submissions only', { exact: true }).uncheck();
    await admin.getByLabel('Customer feedback', { exact: true }).uncheck();
    await save(admin);
    await agent.goto(`${origin}/data-master`);
    check('all campaign scope', await agent.getByText('All campaign submissions', { exact: true }).count(), 1);
    check('other agents now visible', await agent.getByText('Synthetic Other Client 27', { exact: true }).count(), 1);
    check('allowed form selection', await agent.locator('select[name=type] option').allTextContents(), ['Sales submissions']);
    check('unpermitted form rejected', (await agent.request.get(`${origin}/data-master?type=feedback`, { headers: { Accept: 'application/json' } })).status(), 422);
    await admin.getByLabel('Enable agent Data Master', { exact: true }).uncheck();
    await save(admin);
    check('immediate revocation', (await agent.goto(`${origin}/data-master`)).status(), 403);
    await agent.goto(`${origin}/attendance`);
    check('revoked navigation hidden', await agent.getByRole('link', { name: 'Data Master', exact: true }).count(), 0);
    await admin.getByLabel('Enable agent Data Master', { exact: true }).check();
    await admin.getByLabel('Own submissions only', { exact: true }).check();
    await admin.getByLabel('Customer feedback', { exact: true }).check();
    await save(admin);
    check('restored access', (await agent.goto(`${origin}/data-master`)).status(), 200);
    execFileSync('php', ['tests/Support/agent-data-master-browser.php', 'storage-unavailable']);
    try {
        check('missing storage status', (await agent.goto(`${origin}/data-master`)).status(), 503);
        await agent.getByRole('combobox', { name: 'Form', exact: true }).selectOption('feedback');
        await agent.getByRole('button', { name: 'Load form', exact: true }).click();
        await agent.getByText('Synthetic Feedback Client 1', { exact: true }).waitFor();
        check('missing first form recovers to healthy form', await agent.locator('table tbody tr').count(), 2);
    } finally {
        execFileSync('php', ['tests/Support/agent-data-master-browser.php', 'storage-restore']);
    }
    await admin.reload();
    await capture(admin, 'admin');
    await admin.getByRole('combobox', { name: 'Campaign', exact: true }).selectOption('dm_beta');
    await admin.getByRole('button', { name: 'Load campaign', exact: true }).click();
    await admin.waitForURL('**data_master_campaign=dm_beta');
    check('new campaign access disabled', await admin.getByLabel('Enable agent Data Master', { exact: true }).isChecked(), false);
    check('new campaign own-only default', await admin.getByLabel('Own submissions only', { exact: true }).isChecked(), true);
    check('no JavaScript exceptions', report.pageErrors, []);
    const unexpected = report.failedRequests.filter(({ url, status }) => !url.includes('/api/sip/credentials') && !url.includes('/api/vicidial/session/local-status') && !(url.includes('/data-master') && [403, 422, 503].includes(status)));
    check('no unexpected HTTP failures', unexpected, []);
    await writeFile(path.join(output, 'browser-validation.json'), JSON.stringify(report, null, 2));
    console.log(JSON.stringify({ checks: report.checks.length, viewports: report.viewports, pageErrors: report.pageErrors, knownIntegrationErrors: [...new Set(report.consoleErrors)] }, null, 2));
} finally {
    await browser.close();
}
