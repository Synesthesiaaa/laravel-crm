import fs from 'node:fs';
import path from 'node:path';
import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

const viteHotFile = path.resolve('storage/vite.hot');

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.js',
                'resources/js/agent-capture-webform-entry.js',
            ],
            hotFile: 'storage/vite.hot',
            refresh: true,
        }),
        tailwindcss(),
        {
            name: 'remove-vite-hot-file-after-build',
            apply: 'build',
            closeBundle() {
                fs.rmSync(viteHotFile, { force: true });
            },
        },
    ],
    build: {
        rollupOptions: {
            output: {
                manualChunks: {
                    apexcharts: ['apexcharts'],
                },
            },
        },
        sourcemap: process.env.NODE_ENV !== 'production',
    },
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
