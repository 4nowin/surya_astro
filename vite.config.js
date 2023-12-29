import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.scss',
                'resources/css/mobile_app.scss',
                'resources/js/app.js',
                'resources/js/admin_app.js',
                'resources/css/admin_app.scss',
                'resources/js/mobile_app.js',
            ],
            refresh: true,
        }),
    ],
});
