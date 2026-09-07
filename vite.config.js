import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import { local } from 'laravel-vite-plugin/fonts';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
            fonts: [
                local('Instrument Sans', {
                    variants: [
                        { src: 'resources/fonts/instrument-sans/instrument-sans-400-normal.woff2', weight: 400 },
                        { src: 'resources/fonts/instrument-sans/instrument-sans-500-normal.woff2', weight: 500 },
                        { src: 'resources/fonts/instrument-sans/instrument-sans-600-normal.woff2', weight: 600 },
                    ],
                    optimizedFallbacks: false,
                }),
            ],
        }),
        tailwindcss(),
    ],
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
