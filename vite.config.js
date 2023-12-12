import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/css/bootstrap.css',
                'resources/css/owl.carousel.css',
                'resources/css/Frontend/style.css',


                'resources/js/app.js',
                'resources/js/jquery.js',
                'resources/js/owl.carousel.js',
                'resources/js/Frontend/custom.js',
            ],
            refresh: true,
        }),
    ],
});
