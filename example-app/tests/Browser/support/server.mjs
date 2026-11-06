import { mkdirSync } from 'node:fs';
import { resolve } from 'node:path';
import { spawn, spawnSync } from 'node:child_process';
const dir = resolve('storage/framework/testing/task5-browser');
for (const sub of [
    '',
    'framework/cache/data',
    'framework/sessions',
    'framework/views',
    'logs',
])
    mkdirSync(resolve(dir, sub), { recursive: true });
const env = {
    ...process.env,
    APP_ENV: 'testing',
    APP_DEBUG: 'true',
    APP_KEY: 'base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=',
    APP_URL: 'http://127.0.0.1:8765',
    DB_CONNECTION: 'sqlite',
    DB_DATABASE: resolve(dir, 'test.sqlite'),
    DB_URL: '',
    CACHE_STORE: 'file',
    SESSION_DRIVER: 'file',
    QUEUE_CONNECTION: 'sync',
    MAIL_MAILER: 'array',
    BCRYPT_ROUNDS: '4',
    TASK5_STORAGE: dir,
    APP_CONFIG_CACHE: resolve(dir, 'config.php'),
    APP_ROUTES_CACHE: resolve(dir, 'routes.php'),
};
const seeded = spawnSync(
    'php',
    ['tests/Support/pos-browser-fixture.php', 'reset'],
    { env, stdio: 'inherit' },
);
if (seeded.status !== 0) process.exit(seeded.status ?? 1);
const server = spawn(
    'php',
    [
        '-S',
        '127.0.0.1:8765',
        '-t',
        'public',
        'tests/Support/browser-router.php',
    ],
    { env, stdio: 'inherit' },
);
server.on('exit', (code) => process.exit(code ?? 0));
process.on('SIGTERM', () => server.kill());
process.on('SIGINT', () => server.kill());
