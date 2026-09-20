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
                'resources/images/faviconnew.png',
                'resources/images/finallogo.png',
                'resources/images/slideshow/background.png',
                'resources/images/whitebackground.png',
            ],
            refresh: true,
            fonts: [
                bunny('Public Sans', {
                    weights: [400, 500, 600, 700, 800],
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
