import {defineConfig} from 'vite';
import vue from '@vitejs/plugin-vue';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
        }),
        vue(),
        tailwindcss(),
    ],
    build: {
        rollupOptions: {
            output: {
                // 3D 仍只在場景可見時載入；核心與 WebGL 分包以利獨立快取。
                manualChunks(id) {
                    if (id.includes('/node_modules/three/build/three.core.js')) return 'three-core';
                    if (id.includes('/node_modules/three/')) return 'three-renderer';
                    if (/\/node_modules\/(vue|vue-router|@vue|axios|dayjs)\//.test(id)) return 'vendor';
                }
            }
        }
    }
});
