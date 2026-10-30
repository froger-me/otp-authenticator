import type { APIRequestContext } from '@playwright/test';

type MailpitList = {
  messages?: MailpitSummary[];
  items?: MailpitSummary[];
};

type MailpitSummary = {
  ID?: string;
  id?: string;
  To?: unknown;
  to?: unknown;
};

function mailpitUrl(path: string): string {
  const baseUrl = process.env.ANYAPE_WP_TEST_TOOLS_E2E_BASE_URL;
  if (!baseUrl) {
    throw new Error('The browser-test base address is unavailable.');
  }

  const url = new URL(baseUrl);
  url.port = '8026';
  url.pathname = path;
  url.search = '';
  return url.toString();
}

export async function clearMailpit(request: APIRequestContext): Promise<void> {
  const response = await request.delete(mailpitUrl('/api/v1/messages'));
  if (!response.ok()) {
    throw new Error(`Mailpit cleanup failed with HTTP ${response.status()}.`);
  }
}

export async function waitForOtpCode(
  request: APIRequestContext,
  recipient: string,
): Promise<string> {
  const deadline = Date.now() + 15_000;

  while (Date.now() < deadline) {
    const listResponse = await request.get(mailpitUrl('/api/v1/messages'));
    if (!listResponse.ok()) {
      throw new Error(`Mailpit message lookup failed with HTTP ${listResponse.status()}.`);
    }

    const list = await listResponse.json() as MailpitList;
    const messages = list.messages ?? list.items ?? [];
    const summary = messages.find(message =>
      JSON.stringify(message.To ?? message.to ?? '').includes(recipient),
    );
    const messageId = summary?.ID ?? summary?.id;

    if (messageId) {
      const messageResponse = await request.get(
        mailpitUrl(`/api/v1/message/${encodeURIComponent(messageId)}`),
      );
      if (!messageResponse.ok()) {
        throw new Error(`Mailpit message read failed with HTTP ${messageResponse.status()}.`);
      }

      const message = await messageResponse.json() as Record<string, unknown>;
      const match = JSON.stringify(message).match(/OTP test code:\s*([A-Z0-9]{6})/i);
      if (match) {
        return match[1].toUpperCase();
      }
    }

    await new Promise(resolve => setTimeout(resolve, 250));
  }

  throw new Error(`No OTP email arrived for ${recipient}.`);
}
