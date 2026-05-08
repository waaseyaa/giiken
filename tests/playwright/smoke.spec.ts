import { expect, test } from '@playwright/test';

test.describe('Giiken boot-to-browser smoke', () => {
  test('GET / returns Inertia Discover', async ({ page }) => {
    const response = await page.goto('/');
    expect(response?.ok()).toBeTruthy();
    const html = await page.content();
    expect(html).toContain('"component":"Discover"');
    expect(html).toMatch(/data-page="app"/);
  });

  test('GET /test-community returns Discovery index after seed', async ({ page }) => {
    const response = await page.goto('/test-community');
    expect(response?.ok()).toBeTruthy();
    const html = await page.content();
    expect(html).toContain('data-page="app"');
    expect(html).toMatch(/"slug":"test-community"/);
    expect(html).toContain('Welcome to Giiken');
  });
});
