import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.js',
                'resources/js/attendance-face.js',
                'resources/js/attendance-scan.js',
            ],
            refresh: true,
            fonts: [
                bunny('Source Sans 3', {
                    weights: [400, 500, 600, 700],
                }),
                bunny('Fraunces', {
                    weights: [500, 600, 700],
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
