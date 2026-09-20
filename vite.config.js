import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';
import vue from '@vitejs/plugin-vue';
import vuetify from 'vite-plugin-vuetify';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
        }),
        tailwindcss(),
        vue(),
        vuetify({
            autoImport: true,
        }),
    ],
    server: {
        host: '0.0.0.0',
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});