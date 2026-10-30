import { test, expect } from '../../../../../.anyape-wp-test-tools/e2e/test';
import { ensureOtpFixtures } from './helpers/fixtures';

const gatewayLabels = [
  'WordPress Email',
  'Twilio',
  'Alibaba Cloud SMS',
];

test('administrator can open and save OTP Authenticator settings without PHP errors', async ({ page, request }) => {
  await ensureOtpFixtures(request);
  const pageErrors: string[] = [];
  page.on('pageerror', error => pageErrors.push(error.message));

  const selectGateway = async (label: string): Promise<void> => {
    await page.goto('/wp-admin/options-general.php?page=otpa&tab=settings');
    await page.locator('#otpa_gateway_id').selectOption({ label });
    await page.locator('#submit').click();
    await page.waitForURL(/settings-updated=true/);
    await expect(page.locator('#setting-error-settings_updated')).toContainText('Settings saved');
  };

  try {
    await page.goto('/wp-admin/options-general.php?page=otpa&tab=settings');
    await expect(page.getByRole('heading', { name: 'OTP Authenticator' })).toBeVisible();

    const gatewaySelect = page.locator('#otpa_gateway_id');
    for (const label of gatewayLabels) {
      await expect(gatewaySelect.locator('option', { hasText: label })).toHaveCount(1);
    }

    for (const tab of ['style-settings', 'logs-settings']) {
      const response = await page.goto(`/wp-admin/options-general.php?page=otpa&tab=${tab}`);
      expect(response?.status()).toBeLessThan(400);
      await expect(page.getByRole('heading', { name: 'OTP Authenticator' })).toBeVisible();
    }

    for (const label of gatewayLabels) {
      await selectGateway(label);
      const response = await page.goto('/wp-admin/options-general.php?page=otpa&tab=gateway-settings');
      expect(response?.status()).toBeLessThan(400);
      await expect(page.locator('body')).toContainText(`${label} Gateway Settings`);
    }

    const content = await page.locator('body').innerText();
    expect(content).not.toMatch(/PHP (Deprecated|Warning|Fatal error)/i);
    expect(pageErrors).toEqual([]);
  } finally {
    if (!page.isClosed()) {
      await selectGateway('WordPress Email');
    }
  }
});
