import inertia from '@inertiajs/vite';
import { wayfinder } from '@laravel/vite-plugin-wayfinder';
import tailwindcss from '@tailwindcss/vite';
import vue from '@vitejs/plugin-vue';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import { defineConfig } from 'vite';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.ts'],
            refresh: true,
            fonts: [
                // Manual de identidad: Manrope (UI/texto), Montserrat
                // (encabezados), Syncopate Bold (momentos de marca).
                bunny('Manrope', {
                    weights: [300, 400, 500, 600, 700],
                }),
                bunny('Montserrat', {
                    weights: [400, 500, 600, 700],
                }),
                bunny('Syncopate', {
                    weights: [700],
                }),
            ],
        }),
        inertia(),
        tailwindcss(),
        vue({
            template: {
                transformAssetUrls: {
                    base: null,
                    includeAbsolute: false,
                },
            },
        }),
        wayfinder({
            formVariants: true,
        }),
    ],
});
