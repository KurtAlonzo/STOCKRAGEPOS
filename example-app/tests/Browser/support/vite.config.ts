import { defineConfig } from 'vite-plus';
import { resolve } from 'node:path';
export default defineConfig({
    resolve: { alias: { '@': resolve('resources/js') } },
    server: { cors: { origin: 'http://127.0.0.1:8765' } },
});
