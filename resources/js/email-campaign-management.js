let campaignController;

function initializeEmailCampaign() {
    campaignController?.abort();
    campaignController = new AbortController();
    const { signal } = campaignController;
    const root = document.querySelector('[data-email-campaign]');
    if (!root) return;
    let timer;
    let failures = 0;
    let busy = false;
    const notice = root.querySelector('[data-email-poll-state]');
    const schedule = () => { clearTimeout(timer); if (!signal.aborted) timer = setTimeout(poll, Math.min(60000, 5000 * 2 ** failures)); };
    async function poll() {
        if (signal.aborted) return;
        if (document.hidden || busy) { schedule(); return; }
        busy = true;
        try {
            const response = await fetch(root.dataset.statusUrl, { headers: { Accept: 'application/json' }, credentials: 'same-origin', signal });
            if (!response.ok) throw new Error('Status unavailable');
            const data = await response.json();
            failures = 0;
            root.querySelector('[data-email-status]').textContent = data.status.replaceAll('_', ' ');
            root.querySelector('[data-email-refresh-notice]').hidden = data.status === root.dataset.initialStatus && data.approval_state === root.dataset.initialApproval;
            root.querySelectorAll('[data-email-metric]').forEach(element => {
                const key = element.dataset.emailMetric;
                const value = data.metrics[key];
                element.textContent = value == null ? '—' : `${Number(value).toLocaleString()}${key.endsWith('_rate') ? '%' : ''}`;
            });
            root.querySelector('[data-email-progress]').value = data.metrics.progress_percent;
            notice.textContent = `Updated ${new Date(data.updated_at).toLocaleTimeString()}. Refresh the history table for recipient details.`;
        } catch (error) {
            if (error.name !== 'AbortError') { failures = Math.min(failures + 1, 4); notice.textContent = 'Live progress is temporarily unavailable. Retrying; refresh to check manually.'; }
        } finally { busy = false; schedule(); }
    }
    signal.addEventListener('abort', () => clearTimeout(timer), { once: true });
    document.addEventListener('visibilitychange', () => { if (!document.hidden && !busy) { clearTimeout(timer); poll(); } }, { signal });
    schedule();

    const chartNode = root.querySelector('[data-email-outcomes-chart]');
    if (chartNode && window.ApexChartsLoader) {
        window.ApexChartsLoader().then(Chart => {
            if (signal.aborted) return;
            const style = getComputedStyle(document.documentElement);
            const chart = new Chart(chartNode, {
                chart: { type: 'bar', height: 210, toolbar: { show: false }, animations: { enabled: false }, foreColor: style.getPropertyValue('--color-on-surface').trim() },
                series: [{ name: 'Recipients', data: JSON.parse(chartNode.dataset.values) }],
                xaxis: { categories: ['Accepted', 'Blocked', 'Uncertain', 'Skipped'] },
                colors: [style.getPropertyValue('--color-primary').trim() || '#e91e8c'],
                plotOptions: { bar: { horizontal: true, borderRadius: 2 } }, dataLabels: { enabled: false },
                grid: { borderColor: style.getPropertyValue('--color-border').trim() }, tooltip: { theme: document.documentElement.classList.contains('dark') ? 'dark' : 'light' },
            });
            chart.render();
            signal.addEventListener('abort', () => chart.destroy(), { once: true });
        }).catch(() => { chartNode.textContent = 'Chart unavailable. Recipient outcome counts are listed above.'; });
    }
}

document.addEventListener('DOMContentLoaded', initializeEmailCampaign);
document.addEventListener('crm:navigated', initializeEmailCampaign);
window.addEventListener('soft-navigate:before', () => campaignController?.abort());
window.addEventListener('soft-navigate:after', initializeEmailCampaign);
if (document.readyState !== 'loading') initializeEmailCampaign();
