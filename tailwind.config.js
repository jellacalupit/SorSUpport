import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Source Sans 3', 'Figtree', ...defaultTheme.fontFamily.sans],
                display: ['Bitter', ...defaultTheme.fontFamily.serif],
            },
            colors: {
                background: 'oklch(var(--background) / <alpha-value>)',
                foreground: 'oklch(var(--foreground) / <alpha-value>)',
                card: 'oklch(var(--card) / <alpha-value>)',
                'card-foreground': 'oklch(var(--card-foreground) / <alpha-value>)',
                popover: 'oklch(var(--popover) / <alpha-value>)',
                'popover-foreground': 'oklch(var(--popover-foreground) / <alpha-value>)',
                primary: 'oklch(var(--primary) / <alpha-value>)',
                'primary-foreground': 'oklch(var(--primary-foreground) / <alpha-value>)',
                'primary-dark': 'oklch(var(--primary-dark) / <alpha-value>)',
                'primary-soft': 'oklch(var(--primary-soft) / <alpha-value>)',
                secondary: 'oklch(var(--secondary) / <alpha-value>)',
                'secondary-foreground': 'oklch(var(--secondary-foreground) / <alpha-value>)',
                muted: 'oklch(var(--muted) / <alpha-value>)',
                'muted-foreground': 'oklch(var(--muted-foreground) / <alpha-value>)',
                accent: 'oklch(var(--accent) / <alpha-value>)',
                'accent-foreground': 'oklch(var(--accent-foreground) / <alpha-value>)',
                destructive: 'oklch(var(--destructive) / <alpha-value>)',
                'destructive-foreground': 'oklch(var(--destructive-foreground) / <alpha-value>)',
                warning: 'oklch(var(--warning) / <alpha-value>)',
                'warning-foreground': 'oklch(var(--warning-foreground) / <alpha-value>)',
                success: 'oklch(var(--success) / <alpha-value>)',
                'success-foreground': 'oklch(var(--success-foreground) / <alpha-value>)',
                border: 'oklch(var(--border) / <alpha-value>)',
                input: 'oklch(var(--input) / <alpha-value>)',
                ring: 'oklch(var(--ring) / <alpha-value>)',
                sidebar: 'oklch(var(--sidebar) / <alpha-value>)',
                'sidebar-foreground': 'oklch(var(--sidebar-foreground) / <alpha-value>)',
                'sidebar-primary': 'oklch(var(--sidebar-primary) / <alpha-value>)',
                'sidebar-primary-foreground': 'oklch(var(--sidebar-primary-foreground) / <alpha-value>)',
                'sidebar-accent': 'oklch(var(--sidebar-accent) / <alpha-value>)',
                'sidebar-accent-foreground': 'oklch(var(--sidebar-accent-foreground) / <alpha-value>)',
                'sidebar-border': 'oklch(var(--sidebar-border) / <alpha-value>)',
            },
            borderRadius: {
                sm: 'var(--radius-sm)',
                md: 'var(--radius-md)',
                lg: 'var(--radius-lg)',
                xl: 'var(--radius-xl)',
                '2xl': 'var(--radius-2xl)',
            },
            boxShadow: {
                card: 'var(--shadow-card)',
                panel: 'var(--shadow-panel)',
            },
        },
    },

    plugins: [forms],
};
