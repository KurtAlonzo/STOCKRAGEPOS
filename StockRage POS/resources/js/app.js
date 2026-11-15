import './bootstrap';

import Alpine from 'alpinejs';
import Chart from 'chart.js/auto';
import pos from './pos';

window.Alpine = Alpine;
window.Chart = Chart;

// Accent colour of the current theme, for charts: brandColor() solid, brandColor(.18) see-through.
window.brandColor = (alpha = 1, shade = 500) => {
    const raw = getComputedStyle(document.documentElement).getPropertyValue(`--brand-${shade}`).trim() || '238 43 51';
    return `rgba(${raw.split(/\s+/).join(', ')}, ${alpha})`;
};

// Dark-theme defaults for every chart
const ink = (shade) => `rgb(${getComputedStyle(document.documentElement).getPropertyValue('--ink-' + shade).trim().split(/\s+/).join(',')})`;
window.inkColor = ink;
Chart.defaults.color = ink(400);
Chart.defaults.borderColor = ink(700);
Chart.defaults.font.family = '"Inter Variable", ui-sans-serif, system-ui, sans-serif';
Chart.defaults.plugins.legend.display = false;
Chart.defaults.plugins.tooltip.backgroundColor = ink(800);
Chart.defaults.plugins.tooltip.borderColor = ink(600);
Chart.defaults.plugins.tooltip.borderWidth = 1;
Chart.defaults.plugins.tooltip.titleColor = ink(100);
Chart.defaults.plugins.tooltip.bodyColor = ink(200);
Chart.defaults.plugins.tooltip.padding = 10;
Chart.defaults.maintainAspectRatio = false;

// Peso formatting used by Alpine and the charts
window.peso = (n) =>
    '₱' + Number(n || 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

// Register the POS cart component:  x-data="pos(...)"
Alpine.data('pos', pos);

Alpine.start();
