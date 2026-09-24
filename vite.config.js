import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';
import vue from '@vitejs/plugin-vue';

export default defineConfig({
    plugins: [
        vue(),
        laravel({
            input: ['resources/css/app.css', 'resources/css/sv-shell.css', 'resources/js/app.js'],
            refresh: true,
            hotFile: 'storage/framework/vite.hot',
        }),
        tailwindcss(),
    ],
    server: {
        host: '0.0.0.0',
        port: 5173,
        strictPort: true,
        origin: 'http://127.0.0.1:5173',
        cors: {
            origin: [
                'http://127.0.0.1:8000',
                'http://localhost:8000',
                'http://127.0.0.1:5173',
                'http://localhost:5173',
            ],
        },
        hmr: {
            host: '127.0.0.1',
        },
        watch: {
            usePolling: true,
        },
    },
});
