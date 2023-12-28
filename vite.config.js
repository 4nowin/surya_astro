import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.js',
                'resources/js/admin_app.js',
                'resources/css/admin_app.scss',
            ],
            refresh: true,
        }),
    ],
});
