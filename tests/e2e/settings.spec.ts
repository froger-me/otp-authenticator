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

test('passwordless roles keep one role selected while allowing Subscriber to be replaced', async ({ page, request }) => {
  await ensureOtpFixtures(request);
  await page.goto('/wp-admin/options-general.php?page=otpa&tab=settings');

  const twoFactor = page.locator('#otpa_enable_2fa');
  const passwordless = page.locator('#otpa_enable_passwordless');
  const rolesField = page.locator('.otpa-passwordless_roles-field');
  const subscriber = page.locator('#otpa_passwordless_roles-subscriber');
  const administrator = page.locator('#otpa_passwordless_roles-administrator');

  if (await twoFactor.isChecked()) await twoFactor.uncheck();
  if (!(await passwordless.isChecked())) await passwordless.check();

  await expect(rolesField).toBeVisible();
  await expect(rolesField).toContainText('Roles to include for Passwordless Authentication');
  await expect(rolesField).toContainText('Subscriber is selected automatically when no other role is selected.');
  await expect(subscriber).toBeChecked();
  await expect(subscriber).toBeDisabled();

  await administrator.check();
  await expect(subscriber).toBeEnabled();
  await subscriber.uncheck();
  await expect(subscriber).not.toBeChecked();

  await administrator.uncheck();
  await expect(subscriber).toBeChecked();
  await expect(subscriber).toBeDisabled();
});

test('style settings update the form preview without saving', async ({ page, request }) => {
  await ensureOtpFixtures(request);
  await page.goto('/wp-admin/options-general.php?page=otpa&tab=style-settings');

  const previewLogo = 'data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///ywAAAAAAQABAAACAUwAOw==';
  const preview = page.frameLocator('.otpa-form-settings-preview iframe');
  const backgroundColor = page.locator('#otpa_submit_button_background_color');
  const buttonTextColor = page.locator('#otpa_submit_button_text_color');
  const linkTextColor = page.locator('#otpa_link_text_color');
  const logoUrl = page.locator('#otpa_logo_url');

  await expect(preview.locator('#otpa_otp_form')).toBeVisible();

  await backgroundColor.evaluate(element => {
    (element as HTMLInputElement).value = '#123456';
    element.dispatchEvent(new Event('input', { bubbles: true }));
  });
  await buttonTextColor.evaluate(element => {
    (element as HTMLInputElement).value = '#abcdef';
    element.dispatchEvent(new Event('input', { bubbles: true }));
  });
  await linkTextColor.evaluate(element => {
    (element as HTMLInputElement).value = '#654321';
    element.dispatchEvent(new Event('input', { bubbles: true }));
  });
  await logoUrl.evaluate((element, value) => {
    (element as HTMLInputElement).value = value;
    element.dispatchEvent(new Event('change', { bubbles: true }));
  }, previewLogo);

  const liveStyles = preview.locator('#otpa-live-style-preview');
  await expect(liveStyles).toBeAttached();

  const liveStyleText = await liveStyles.evaluate(element => element.textContent ?? '');

  expect(liveStyleText).toContain('background-color:#123456');
  expect(liveStyleText).toContain('color:#abcdef');
  expect(liveStyleText).toContain('color:#654321');
  await expect(preview.locator('#otpa_logo')).toHaveCSS(
    'background-image',
    `url("${previewLogo}")`,
  );
});
