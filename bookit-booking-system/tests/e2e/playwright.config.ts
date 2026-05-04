import { defineConfig, devices } from '@playwright/test';
import dotenv from 'dotenv';
import path from 'path';

const mode = process.env.MODE || 'smoke';
const isFullMode = mode === 'full';

dotenv.config({
  path: path.resolve(__dirname, isFullMode ? '.env.test.local' : '.env.test.live'),
});

export default defineConfig({
  testDir: './tests',
  timeout: 90_000,
  retries: isFullMode ? 0 : 1,
  workers: 1,
  reporter: [['html', { open: 'never' }], ['list']],

  use: {
    baseURL: process.env.BASE_URL,
    headless: !isFullMode,
    screenshot: 'only-on-failure',
    video: 'retain-on-failure',
    trace: 'retain-on-failure',
  },

  projects: [
    {
      name: 'chromium',
      use: { ...devices['Desktop Chrome'] },
    },
  ],
});
