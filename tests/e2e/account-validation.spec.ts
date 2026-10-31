import { test, expect } from '../../../../../.anyape-wp-test-tools/e2e/test';
import { ensureOtpFixtures } from './helpers/fixtures';
import { clearMailpit, waitForOtpCode } from './helpers/mailpit';

const login = 'otpa_validation_e2e';
const password = 'OTPA-Test-Password-2026!';
const email = 'otpa-validation@example.test';
const displayName = 'OTP Validation Test User';
const combinedLogin = 'otpa_both_e2e';
const combinedEmail = 'otpa-both@example.test';
const combinedName = 'OTP Combined Security Test User';

function baseUrl(): string {
  const value = process.env.ANYAPE_WP_TEST_TOOLS_E2E_BASE_URL;
  if (!value) throw new Error('The browser-test base address is unavailable.');
  return value;
}

async function fillAndEnable(locator: import('@playwright/test').Locator, value: string): Promise<void> {
  await locator.fill(value);
  await locator.dispatchEvent('keyup');
  await locator.dispatchEvent('change');
}

async function closeContext(context: import('@playwright/test').BrowserContext): Promise<void> {
  if (context.pages().some(page => !page.isClosed())) {
    await context.close();
  }
}

test('account validation accepts the emailed code once and restores the requested page', async ({ browser, request }) => {
  await ensureOtpFixtures(request);
  await clearMailpit(request);
  const context = await browser.newContext({
    baseURL: baseUrl(),
    ignoreHTTPSErrors: true,
    storageState: { cookies: [], origins: [] },
  });
  const page = await context.newPage();

  try {
    await page.goto('/wp-login.php?redirect_to=%2Fmy-account%2F');
    await page.locator('#user_login').fill(login);
    await page.locator('#user_pass').fill(password);
    await page.locator('#wp-submit').click();
    await expect(page.locator('#otpa_otp_form[data-otp_form_type="account_validation"]')).toBeVisible();

    const blockedStatuses = await page.evaluate(async () => {
      const requests = [
        fetch('/wp-json/wp/v2/users/me'),
        fetch('/wp-json/wp/v2/users/me', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ description: 'must not be saved' }),
        }),
        fetch('/wp-admin/admin-ajax.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
          body: 'action=heartbeat',
        }),
        fetch('/my-account/', {
          method: 'POST',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
          body: 'otpa_security_probe=1',
        }),
        fetch('/my-account/edit-account/', {
          method: 'POST',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
          body: 'account_display_name=must-not-be-saved&save_account_details=Save',
        }),
        fetch('/wp-admin/profile.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
          body: 'first_name=must-not-be-saved',
        }),
      ];
      return Promise.all(requests.map(async pendingRequest => (await pendingRequest).status));
    });
    expect(blockedStatuses).toEqual([403, 403, 403, 403, 403, 403]);

    await fillAndEnable(page.locator('#otpa_id_widget'), email);
    await page.locator('#otpa_send_code').click();
    const code = await waitForOtpCode(request, email);
    await fillAndEnable(page.locator('#otpa_code_widget'), code);
    await page.locator('#otpa_submit').click();

    await page.waitForURL(/\/my-account\/?$/);
    await expect(page.locator('body')).toContainText(displayName);
    await page.reload();
    await expect(page).toHaveURL(/\/my-account\/?$/);
    await expect(page.locator('#otpa_otp_form[data-otp_form_type="account_validation"]')).toHaveCount(0);
  } finally {
    await closeContext(context);
  }
});
test('two-factor authentication and account validation require separate codes', async ({ browser, request }) => {
  await ensureOtpFixtures(request);
  await clearMailpit(request);
  const context = await browser.newContext({
    baseURL: baseUrl(),
    ignoreHTTPSErrors: true,
    storageState: { cookies: [], origins: [] },
  });
  const page = await context.newPage();

  try {
    await page.goto('/wp-login.php?redirect_to=%2Fmy-account%2F');
    await page.locator('#user_login').fill(combinedLogin);
    await page.locator('#user_pass').fill(password);
    await page.locator('#wp-submit').click();
    await expect(page.locator('#otpa_otp_form[data-otp_form_type="2fa"]')).toBeVisible();

    await fillAndEnable(page.locator('#otpa_id_widget'), combinedEmail);
    await page.locator('#otpa_send_code').click();
    const twoFactorCode = await waitForOtpCode(request, combinedEmail);
    await fillAndEnable(page.locator('#otpa_code_widget'), twoFactorCode);
    await page.locator('#otpa_submit').click();
    await expect(page.locator('#otpa_otp_form[data-otp_form_type="account_validation"]')).toBeVisible();
    await expect(page.locator('body')).not.toContainText(combinedName);

    await clearMailpit(request);
    await fillAndEnable(page.locator('#otpa_id_widget'), combinedEmail);
    await page.locator('#otpa_send_code').click();
    const validationCode = await waitForOtpCode(request, combinedEmail);
    await fillAndEnable(page.locator('#otpa_code_widget'), validationCode);
    await page.locator('#otpa_submit').click();

    await page.waitForURL(/\/my-account\/?$/);
    await expect(page.locator('body')).toContainText(combinedName);
  } finally {
    await closeContext(context);
  }
});
