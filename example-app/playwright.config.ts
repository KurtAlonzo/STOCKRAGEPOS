import { defineConfig } from '@playwright/test';
export default defineConfig({
    testDir: 'tests/Browser',
    workers: 1,
    fullyParallel: false,
    timeout: 45000,
    use: {
        baseURL: 'http://127.0.0.1:8765',
        viewport: { width: 1440, height: 900 },
        trace: 'retain-on-failure',
    },
    webServer: [
        {
            command: 'node tests/Browser/support/server.mjs',
            url: 'http://127.0.0.1:8765/login',
            reuseExistingServer: false,
            timeout: 120000,
        },
        {
            command:
                'npx vp dev --config tests/Browser/support/vite.config.ts --host 127.0.0.1 --port 5179 --strictPort',
            url: 'http://127.0.0.1:5179/resources/js/lib/pos/draft-storage.ts',
            reuseExistingServer: false,
            timeout: 60000,
        },
    ],
});
