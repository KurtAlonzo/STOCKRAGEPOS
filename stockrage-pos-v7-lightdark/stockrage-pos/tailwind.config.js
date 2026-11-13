import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';
import plugin from 'tailwindcss/plugin';
import colors from 'tailwindcss/colors';

// ---- Theme colours -------------------------------------------------------------
// `brand` is the accent colour used for buttons, highlights and charts. Each theme
// below is a full palette; the one in use is chosen by <html data-theme="...">
// (Settings > Theme color). Keys here must match App\Support\Theme::all().
const shades = [50, 100, 200, 300, 400, 500, 600, 700, 800, 900];
const themes = {
    // Stockrage red, taken from the logo (the default)
    red: { 50: '#fff1f1', 100: '#ffdfdf', 200: '#ffc5c6', 300: '#ff9c9e', 400: '#ff5f63', 500: '#ee2b33', 600: '#d6151e', 700: '#b30f18', 800: '#93131a', 900: '#78161c' },
    orange: colors.orange,
    green: colors.green,
    teal: colors.teal,
    blue: colors.blue,
    violet: colors.violet,
    pink: colors.pink,
};
const rgb = (hex) => { const n = parseInt(hex.slice(1), 16); return `${(n >> 16) & 255} ${(n >> 8) & 255} ${n & 255}`; };
const themeVariables = plugin(({ addBase }) => {
    const base = {};
    for (const [name, scale] of Object.entries(themes)) {
        const selector = name === 'red' ? ':root, html[data-theme="red"]' : `html[data-theme="${name}"]`;
        base[selector] = Object.fromEntries(shades.map((shade) => [`--brand-${shade}`, rgb(scale[shade])]));
    }
    addBase(base);
});
const inkShades = [100, 200, 300, 400, 500, 600, 700, 800, 850, 900, 950];
const inkDark = ['#ececf0', '#d6d6dc', '#b4b4be', '#8b8b96', '#5b5b66', '#383840', '#27272d', '#1c1c21', '#16161a', '#111114', '#0b0b0d'];
const inkLight = ['#16161a', '#2c2c33', '#4b4b55', '#6b6b76', '#8b8b96', '#c4c4cc', '#dcdce2', '#e9e9ee', '#f1f1f4', '#ffffff', '#f4f4f7'];
const modeVariables = plugin(({ addBase }) => {
    const vars = (list, fg) => ({ ...Object.fromEntries(inkShades.map((shade, i) => [`--ink-${shade}`, rgb(list[i])])), '--fg': rgb(fg) });
    addBase({
        ':root': vars(inkLight, '#0b0b0d'),
        'html.dark': vars(inkDark, '#ffffff'),
        // Light mode: pale accent text would be unreadable on white, so use the darker shades
        'html:not(.dark)': { '--brand-300': 'var(--brand-700)', '--brand-400': 'var(--brand-600)' },
    });
});
const brand = Object.fromEntries(shades.map((shade) => [shade, `rgb(var(--brand-${shade}) / <alpha-value>)`]));

/** @type {import('tailwindcss').Config} */
export default {
    darkMode: 'class',

    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.js',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['"Inter Variable"', ...defaultTheme.fontFamily.sans],
            },
            colors: {
                // Accent colour: follows the chosen theme (see top of this file)
                brand,
                // Neutrals. Values come from CSS variables so one set of classes works in light and
                // dark mode (palettes below; the `dark` class on <html> picks one).
                ink: Object.fromEntries(inkShades.map((shade) => [shade, `rgb(var(--ink-${shade}) / <alpha-value>)`])),
                // Strong foreground: white in dark mode, near-black in light mode
                fg: 'rgb(var(--fg) / <alpha-value>)',
            },
        },
    },

    plugins: [forms, themeVariables, modeVariables],
};
