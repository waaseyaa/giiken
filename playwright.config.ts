import { defineConfig, devices } from '@playwright/test';

/** Ephemeral SQLite so smoke does not touch `storage/waaseyaa.sqlite`. */
const smokeDb = '/tmp/giiken-playwright-smoke.sqlite';

export default defineConfig({
  testDir: 'tests/playwright',
  fullyParallel: false,
  forbidOnly: Boolean(process.env.CI),
  retries: process.env.CI ? 1 : 0,
  workers: 1,
  reporter: [['list']],
  use: {
    baseURL: 'http://127.0.0.1:9323',
    trace: 'on-first-retry',
  },
  projects: [{ name: 'chromium', use: { ...devices['Desktop Chrome'] } }],
  webServer: {
    command: [
      `rm -f "${smokeDb}"`,
      `WAASEYAA_DB="${smokeDb}" ./vendor/bin/waaseyaa migrate`,
      `WAASEYAA_DB="${smokeDb}" ./vendor/bin/waaseyaa giiken:seed:test-community`,
      `WAASEYAA_DB="${smokeDb}" php -S 127.0.0.1:9323 -t public public/index.php`,
    ].join(' && '),
    url: 'http://127.0.0.1:9323/',
    reuseExistingServer: !process.env.CI,
    timeout: 120_000,
  },
});
