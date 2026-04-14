import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';
import vue from '@vitejs/plugin-vue';
import { fileURLToPath, URL } from 'node:url';

export default defineConfig(({ mode }) => ({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.js',
                'resources/js/app-inertia.js',
                'resources/js/login-page.js',
            ],
            refresh: true,
        }),
        tailwindcss(),
        vue({
            template: {
                transformAssetUrls: {
                    base: null,
                    includeAbsolute: false,
                },
            },
        }),
    ],
    esbuild: {
        legalComments: 'none',
        ...(mode === 'production' ? { drop: ['debugger'] } : {}),
    },
    build: {
        rollupOptions: {
            output: {
                // Parallel downloads: Vue core vs Inertia runtime vs charts (Dashboard).
                manualChunks: {
                    apexcharts: ['apexcharts'],
                    vue: ['vue'],
                    inertia: ['@inertiajs/vue3', '@inertiajs/core'],
                },
            },
        },
        sourcemap: mode !== 'production',
    },
    optimizeDeps: {
        include: ['vue', '@inertiajs/vue3', '@inertiajs/core'],
    },
    resolve: {
        alias: {
            '@': fileURLToPath(new URL('./resources/js', import.meta.url)),
        },
    },
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
}));
