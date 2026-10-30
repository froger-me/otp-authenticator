import type { APIRequestContext } from '@playwright/test';

export async function ensureOtpFixtures(request: APIRequestContext): Promise<void> {
  const response = await request.post(
    '/wp-content/plugins/otp-authenticator/tests/e2e/fixtures.php',
  );
  const body = await response.json() as { success: boolean };

  if (!response.ok() || body.success !== true) {
    throw new Error('OTP Authenticator browser fixtures could not be prepared.');
  }
}
