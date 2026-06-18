import { defineConfig } from 'vite';

export default defineConfig({
    build: {
        rollupOptions: {
            output: {
                assetFileNames: () => {
                    return 'assets/[name]-[hash][extname]';
                },
            },
        },
    },
});
