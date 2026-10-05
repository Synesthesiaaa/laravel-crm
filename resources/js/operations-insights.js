import Alpine from 'alpinejs';

window.Alpine = Alpine;
window.ApexChartsLoader = () => import('apexcharts').then((module) => module.default);

const chartGroups = new Map();

window.crmCharts = {
    register(group, key, chart) {
        if (!chartGroups.has(group)) {
            chartGroups.set(group, new Map());
        }

        const charts = chartGroups.get(group);
        const previous = charts.get(key);
        if (previous && previous !== chart) {
            try {
                previous.destroy();
            } catch (_) {
                // The chart may already have been removed during a DOM refresh.
            }
        }

        charts.set(key, chart);
    },

    destroyGroup(group) {
        const charts = chartGroups.get(group);
        if (!charts) {
            return;
        }

        charts.forEach((chart) => {
            try {
                chart.destroy();
            } catch (_) {
                // Ignore stale chart instances while the operations view refreshes.
            }
        });
        chartGroups.delete(group);
    },
};

Alpine.start();
