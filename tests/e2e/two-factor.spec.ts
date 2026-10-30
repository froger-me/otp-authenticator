import { test, expect } from '../../../../../.anyape-wp-test-tools/e2e/test';
import { ensureOtpFixtures } from './helpers/fixtures';
import { clearMailpit, waitForOtpCode } from './helpers/mailpit';

const login = 'otpa_two_factor_e2e';
const password = 'OTPA-Test-Password-2026!';
const email = 'otpa-two-factor@example.test';
const displayName = 'OTP Two Factor Test User';

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

async function signIn(page: import('@playwright/test').Page): Promise<void> {
  await page.goto('/wp-login.php?redirect_to=%2Fmy-account%2F');
  await page.locator('#user_login').fill(login);
  await page.locator('#user_pass').fill(password);
  await page.locator('#wp-submit').click();
  await expect(page.locator('#otpa_otp_form[data-otp_form_type="2fa"]')).toBeVisible();
}

test('two-factor login accepts the emailed code and restores the requested page', async ({ browser, request }) => {
  await ensureOtpFixtures(request);
  await clearMailpit(request);
  const context = await browser.newContext({
    baseURL: baseUrl(),
    ignoreHTTPSErrors: true,
    storageState: { cookies: [], origins: [] },
  });
  const page = await context.newPage();

  try {
    await signIn(page);
    await fillAndEnable(page.locator('#otpa_id_widget'), email);
    await page.locator('#otpa_send_code').click();
    const code = await waitForOtpCode(request, email);
    await fillAndEnable(page.locator('#otpa_code_widget'), code);
    await page.locator('#otpa_submit').click();

    await page.waitForURL(/\/my-account\/?$/);
    await expect(page.locator('body')).toContainText(displayName);
  } finally {
    await closeContext(context);
  }
});

test('two-factor login keeps protected pages blocked after an incorrect code', async ({ browser, request }) => {
  await ensureOtpFixtures(request);
  await clearMailpit(request);
  const context = await browser.newContext({
    baseURL: baseUrl(),
    ignoreHTTPSErrors: true,
    storageState: { cookies: [], origins: [] },
  });
  const page = await context.newPage();

  try {
    await signIn(page);
    await fillAndEnable(page.locator('#otpa_id_widget'), email);
    await page.locator('#otpa_send_code').click();
    await waitForOtpCode(request, email);
    await fillAndEnable(page.locator('#otpa_code_widget'), 'WRONG1');

    const responsePromise = page.waitForResponse(response =>
      response.url().includes('/otpa-api/otp')
        && response.request().postData()?.includes('handler=verify_otp_code') === true,
    );
    await page.locator('#otpa_submit').click();
    const body = await (await responsePromise).json() as { success: boolean };

    expect(body.success).toBe(false);
    await page.goto('/my-account/');
    await expect(page.locator('#otpa_otp_form[data-otp_form_type="2fa"]')).toBeVisible();
    await expect(page.locator('body')).not.toContainText(displayName);
  } finally {
    await closeContext(context);
  }
});
