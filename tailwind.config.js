import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.vue',
    ],

    theme: {
        extend: {
            colors: {
                brand: {
                    50: '#f7f3ec',
                    100: '#efe8dc',
                    200: '#ddd2c1',
                    300: '#c4b6a1',
                    400: '#a59580',
                    500: '#867663',
                    600: '#6b5d4e',
                    700: '#53483d',
                    800: '#2e2924',
                    900: '#151312',
                },
                ink: '#121110',
            },
            fontFamily: {
                sans: ['Inter', ...defaultTheme.fontFamily.sans],
                display: ['"Playfair Display"', ...defaultTheme.fontFamily.serif],
            },
        },
    },

    plugins: [forms],
};
