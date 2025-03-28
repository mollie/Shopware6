import { defineConfig } from 'vite';
import path from 'path';
import svgr from 'vite-plugin-svgr';
import { fileURLToPath } from 'node:url';

export default defineConfig({
    plugins: [
        svgr(),
    ],
    resolve: {
        alias: {
            '@creditcard_logos': fileURLToPath(new URL('../src/assets/creditcard_logos', import.meta.url)),
        },
    },
    build: {
        rollupOptions: {
            output: {
                assetFileNames: (assetInfo) => {
                    if (assetInfo.name?.endsWith('.svg')) {
                        return 'assets/[name]-[hash][extname]';
                    }
                    return 'assets/[name]-[hash][extname]';
                },
            },
        },
    },
    esbuild: {
        loader: 'tsx',
        include: [
            fileURLToPath(new URL('../src/assets/creditcard_logos', import.meta.url))
        ],
    },

});
