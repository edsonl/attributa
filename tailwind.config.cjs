// tailwind.config.js
module.exports = {
    prefix: 'tw-',
    content: [
        './resources/frontend/**/*.{vue,js,ts,jsx,tsx}',
        './resources/views/**/*.blade.php',
        './resources/assets/css/**/*.{css,scss,sass}',
    ],
    theme: {
        extend: {
            colors: {
                brand: {
                    primary  : 'rgb(var(--color-primary) / <alpha-value>)',
                    secondary: 'rgb(var(--color-secondary) / <alpha-value>)',
                    accent   : 'rgb(var(--color-accent) / <alpha-value>)',
                    dark     : 'rgb(var(--color-dark) / <alpha-value>)',
                    darkpage : 'rgb(var(--color-dark-page) / <alpha-value>)',
                    positive : 'rgb(var(--color-positive) / <alpha-value>)',
                    negative : 'rgb(var(--color-negative) / <alpha-value>)',
                    info     : 'rgb(var(--color-info) / <alpha-value>)',
                    warning  : 'rgb(var(--color-warning) / <alpha-value>)',
                },
            },
        },
        fontFamily: {
            sans: ['Roboto', 'ui-sans-serif', 'system-ui', '-apple-system', 'Segoe UI', 'Helvetica Neue', 'Arial', 'Noto Sans', 'Apple Color Emoji', 'Segoe UI Emoji', 'sans-serif'],
        },
    },
    plugins: [],
    // OPCIONAL: se notar que o preflight atrapalha estilos do Quasar, desative:
    // corePlugins: { preflight: false },
}
