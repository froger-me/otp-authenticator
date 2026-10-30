import { test, expect } from '../../../../../.anyape-wp-test-tools/e2e/test';
import { ensureOtpFixtures } from './helpers/fixtures';
import { clearMailpit, waitForOtpCode } from './helpers/mailpit';

const requestedEmail = 'otpa-passwordless@example.test';
const requestedName = 'OTP Passwordless Test User';
const differentEmail = 'otpa-two-factor@example.test';
const differentName = 'OTP Two Factor Test User';

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

test('passwordless request hides the code and binds login to the requested account', async ({ browser, request }) => {
  await ensureOtpFixtures(request);
  await clearMailpit(request);
  const context = await browser.newContext({
    baseURL: baseUrl(),
    ignoreHTTPSErrors: true,
    storageState: { cookies: [], origins: [] },
  });
  const page = await context.newPage();

  try {
    await page.goto('/otpa/passwordless-login');
    const nonceSessionCookie = (await context.cookies()).find(
      cookie => cookie.name === 'otpa_passwordless_nonce_session',
    );
    expect(nonceSessionCookie?.httpOnly).toBe(true);
    expect(nonceSessionCookie?.sameSite).toBe('Lax');
    expect(nonceSessionCookie?.secure).toBe(true);
    await fillAndEnable(page.locator('#otpa_id_widget'), requestedEmail);

    const requestResponsePromise = page.waitForResponse(response =>
      response.url().includes('/otpa-api/otp')
        && response.request().postData()?.includes('handler=request_otp_code') === true,
    );
    await page.locator('#otpa_send_code').click();
    const requestResponse = await requestResponsePromise;
    const requestBody = await requestResponse.json() as { success: boolean; data: Record<string, unknown> };
    const code = await waitForOtpCode(request, requestedEmail);
    const requestCookie = (await context.cookies()).find(
      cookie => cookie.name === 'otpa_passwordless_request',
    );

    expect(requestBody.success).toBe(true);
    expect(requestBody.data).not.toHaveProperty('otp_code');
    expect(JSON.stringify(requestBody)).not.toContain(code);
    expect(requestCookie?.httpOnly).toBe(true);
    expect(requestCookie?.sameSite).toBe('Lax');
    expect(requestCookie?.path).toBe('/');
    expect(requestCookie?.secure).toBe(true);

    await fillAndEnable(page.locator('#otpa_code_widget'), 'WRONG1');
    const wrongResponsePromise = page.waitForResponse(response =>
      response.url().includes('/otpa-api/otp')
        && response.request().postData()?.includes('handler=verify_otp_code') === true,
    );
    await page.locator('#otpa_submit').click();
    const wrongBody = await (await wrongResponsePromise).json() as { success: boolean };
    expect(wrongBody.success).toBe(false);
    expect((await context.cookies()).some(cookie => cookie.name === 'otpa_passwordless_request')).toBe(true);

    await fillAndEnable(page.locator('#otpa_code_widget'), code);
    await page.locator('#otpa_id').evaluate(
      (element, email) => {
        (element as HTMLInputElement).value = email as string;
      },
      differentEmail,
    );

    let verifyBody: { success: boolean } | undefined;
    await page.route(/\/otpa-api\/otp/, async route => {
      if (route.request().postData()?.includes('handler=verify_otp_code') === true) {
        const response = await route.fetch();
        verifyBody = await response.json() as { success: boolean };
        await route.fulfill({ response });
        return;
      }
      await route.continue();
    });
    await page.locator('#otpa_submit').click();

    await page.waitForURL(url => !url.pathname.includes('/otpa/passwordless-login'));
    expect(verifyBody?.success).toBe(true);
    await page.goto('/my-account/');
    await expect(page).toHaveURL(/\/my-account\/?$/);
    await expect(page.locator('body')).toContainText(requestedName);
    await expect(page.locator('body')).not.toContainText(differentName);

    if (!requestCookie) throw new Error('The passwordless request cookie was not created.');
    const replayContext = await browser.newContext({
      baseURL: baseUrl(),
      ignoreHTTPSErrors: true,
      storageState: { cookies: [], origins: [] },
    });
    await replayContext.addCookies([requestCookie]);
    const replayPage = await replayContext.newPage();
    try {
      await replayPage.goto('/otpa/passwordless-login');
      await fillAndEnable(replayPage.locator('#otpa_code_widget'), code);
      const replayResponsePromise = replayPage.waitForResponse(response =>
        response.url().includes('/otpa-api/otp')
          && response.request().postData()?.includes('handler=verify_otp_code') === true,
      );
      await replayPage.locator('#otpa_submit').click();
      const replayBody = await (await replayResponsePromise).json() as { success: boolean };
      expect(replayBody.success).toBe(false);
      expect((await replayContext.cookies()).some(cookie => cookie.name.startsWith('wordpress_logged_in'))).toBe(false);
    } finally {
      await closeContext(replayContext);
    }
  } finally {
    await closeContext(context);
  }
});

test('passwordless verification without the matching request cookie is rejected', async ({ browser, request }) => {
  await ensureOtpFixtures(request);
  await clearMailpit(request);
  const requestingContext = await browser.newContext({
    baseURL: baseUrl(),
    ignoreHTTPSErrors: true,
    storageState: { cookies: [], origins: [] },
  });
  const requestingPage = await requestingContext.newPage();

  try {
    await requestingPage.goto('/otpa/passwordless-login');
    const requestingNonceCookie = (await requestingContext.cookies()).find(
      cookie => cookie.name === 'otpa_passwordless_nonce_session',
    );
    await fillAndEnable(requestingPage.locator('#otpa_id_widget'), requestedEmail);
    await requestingPage.locator('#otpa_send_code').click();
    const code = await waitForOtpCode(request, requestedEmail);

    const context = await browser.newContext({
      baseURL: baseUrl(),
      ignoreHTTPSErrors: true,
      storageState: { cookies: [], origins: [] },
    });
    const page = await context.newPage();
    try {
      await page.goto('/otpa/passwordless-login');
      const separateNonceCookie = (await context.cookies()).find(
        cookie => cookie.name === 'otpa_passwordless_nonce_session',
      );
      expect(requestingNonceCookie?.value).toBeTruthy();
      expect(separateNonceCookie?.value).toBeTruthy();
      expect(separateNonceCookie?.value).not.toBe(requestingNonceCookie?.value);
      await fillAndEnable(page.locator('#otpa_code_widget'), code);
      const responsePromise = page.waitForResponse(response =>
        response.url().includes('/otpa-api/otp')
          && response.request().postData()?.includes('handler=verify_otp_code') === true,
      );
      await page.locator('#otpa_submit').click();
      const body = await (await responsePromise).json() as { success: boolean };

      expect(body.success).toBe(false);
      expect((await context.cookies()).some(cookie => cookie.name.startsWith('wordpress_logged_in'))).toBe(false);
    } finally {
      await closeContext(context);
    }
  } finally {
    await closeContext(requestingContext);
  }
});
