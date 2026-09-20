import { defineConfig } from 'vite';
import os from 'node:os';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';
import vue from '@vitejs/plugin-vue';
import vuetify from 'vite-plugin-vuetify';

function getLocalIp() {
    const interfaces = os.networkInterfaces();

    for (const addresses of Object.values(interfaces)) {
        for (const address of addresses ?? []) {
            if (address.family === 'IPv4' && !address.internal) {
                return address.address;
            }
        }
    }

    return '127.0.0.1';
}

const localIp = getLocalIp();

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
        host: true,
        cors: true,
        hmr: {
            host: localIp,
        },
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});