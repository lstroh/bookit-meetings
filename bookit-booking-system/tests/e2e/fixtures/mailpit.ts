const MAILPIT_URL = process.env.MAILPIT_URL || 'http://localhost:8025';

export interface MailpitMessage {
  ID: string;
  Subject: string;
  To: Array<{ Address: string; Name: string }>;
  From: { Address: string; Name: string };
  Text: string;
  HTML: string;
}

export async function getLatestEmail(
  toAddress: string,
  page: import('@playwright/test').Page,
  timeoutMs = 60_000
): Promise<MailpitMessage> {
  const baseUrl = process.env.BASE_URL || 'http://plugin-test-1.local';
  const deadline = Date.now() + timeoutMs;
  let attempt = 0;

  while (Date.now() < deadline) {
    // Every 2 attempts, visit wp-admin to trigger Action Scheduler
    if (attempt % 2 === 0) {
      await page.goto(`${baseUrl}/wp-admin/`, {
        waitUntil: 'commit',
        timeout: 10_000,
      }).catch(() => {/* best effort */});
    }

    const res = await fetch(`${MAILPIT_URL}/api/v1/messages`);
    if (!res.ok) throw new Error(`Mailpit API error: ${res.status}. Is Mailpit running?`);
    const data = await res.json();
    const messages: MailpitMessage[] = data.messages || [];
    const match = messages.find((m) =>
      m.To.some((t) => t.Address.toLowerCase() === toAddress.toLowerCase())
    );
    if (match) {
      const full = await fetch(`${MAILPIT_URL}/api/v1/message/${match.ID}`);
      return (await full.json()) as MailpitMessage;
    }

    await page.waitForTimeout(1_500);
    attempt++;
  }

  throw new Error(`No email found for ${toAddress} within ${timeoutMs}ms. Is Mailpit running?`);
}

export async function clearMailpit(): Promise<void> {
  await fetch(`${MAILPIT_URL}/api/v1/messages`, { method: 'DELETE' });
}

// Extracts href from <a>linkText</a> in email HTML
// Used to pull cancel/reschedule magic link URLs
export function extractLinkFromEmail(html: string, linkText: string): string {
  const escaped = linkText.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
  const regex = new RegExp(`<a[^>]+href="([^"]+)"[^>]*>\\s*${escaped}\\s*<\\/a>`, 'i');
  const match = html.match(regex);
  if (!match) throw new Error(`Link "${linkText}" not found in email HTML`);
  // Decode HTML entities in the URL (e.g. &#038; → &, &amp; → &)
  return match[1]
    .replace(/&#038;/g, '&')
    .replace(/&amp;/g, '&');
}
