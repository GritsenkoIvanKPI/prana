/* ============================================================================
   PRANA — receive the contact form and forward it to Telegram (Vercel/Node)
   ============================================================================
   ENVIRONMENT VARIABLES TO SET (never in code):
     TELEGRAM_BOT_TOKEN   — token from @BotFather, e.g. 1234567890:AAH...
     TELEGRAM_CHAT_ID     — destination group/chat id, e.g. -1001234567890

   On Vercel: Settings → Environment Variables → add both → Redeploy.

   The token never reaches the browser: the page only calls this endpoint,
   and this endpoint is the only thing that talks to Telegram.
   ============================================================================ */

const FIELDS = {
  name: 'Name',
  phone: 'Phone / WhatsApp',
  email: 'Email',
  service: 'What they need',
  message: 'Message',
};

const escapeHtml = (v) => String(v == null ? '' : v)
  .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');

// trim whatever the browser sent, cap length against both accidents and abuse
const clean = (v, max = 2000) => String(v == null ? '' : v).trim().slice(0, max);

/* Basic per-IP throttle. Lives in the function instance's memory, so it's
   the best available without a database, not a strict guarantee — but it
   stops a burst of hundreds of submissions in a minute. */
const HITS = new Map();
function rateLimited(ip, max = 5, windowMs = 10 * 60 * 1000) {
  const now = Date.now();
  const recent = (HITS.get(ip) || []).filter((t) => now - t < windowMs);
  if (recent.length >= max) { HITS.set(ip, recent); return true; }
  recent.push(now);
  HITS.set(ip, recent);
  if (HITS.size > 5000) HITS.clear(); // guard against unbounded memory growth
  return false;
}

module.exports = async (req, res) => {
  res.setHeader('X-Content-Type-Options', 'nosniff');

  if (req.method !== 'POST') {
    res.status(405).json({ ok: false, error: 'method_not_allowed' });
    return;
  }

  const ip = (req.headers['x-forwarded-for'] || '').split(',')[0].trim()
             || req.socket?.remoteAddress || 'unknown';
  if (rateLimited(ip)) {
    res.status(429).json({ ok: false, error: 'too_many_requests' });
    return;
  }

  const token = process.env.TELEGRAM_BOT_TOKEN;
  const chatId = process.env.TELEGRAM_CHAT_ID;
  if (!token || !chatId) {
    console.error('TELEGRAM_BOT_TOKEN / TELEGRAM_CHAT_ID are not set');
    res.status(500).json({ ok: false, error: 'not_configured' });
    return;
  }

  let body = req.body;
  if (typeof body === 'string') { try { body = JSON.parse(body); } catch { body = {}; } }
  if (!body || typeof body !== 'object') body = {};

  // Honeypot: hidden from real visitors, so only a spam bot fills it in.
  // Reply "ok" anyway so the bot doesn't notice and keep retrying.
  if (clean(body.website)) { res.status(200).json({ ok: true }); return; }

  const required = ['name', 'phone', 'email'];
  const missing = required.filter((k) => !clean(body[k]));
  if (missing.length) {
    res.status(400).json({ ok: false, error: 'missing_fields', missing });
    return;
  }

  const rows = ['name', 'phone', 'email', 'service', 'message']
    .filter((key) => clean(body[key]))
    .map((key) => `<b>${FIELDS[key]}:</b> ${escapeHtml(clean(body[key]))}`);

  const source = clean(body.source, 80) || 'PRANA website';
  const page = clean(body.page, 300);
  const when = clean(body.submittedAt, 60);

  const text = [
    '🌿 <b>New enquiry — PRANA</b>',
    '',
    ...rows,
    '',
    `<i>Source:</i> ${escapeHtml(source)}`,
    when ? `<i>When:</i> ${escapeHtml(when)}` : null,
    page ? `<i>Page:</i> ${escapeHtml(page)}` : null,
  ].filter((line) => line !== null).join('\n');

  try {
    const tg = await fetch(`https://api.telegram.org/bot${token}/sendMessage`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        chat_id: chatId,
        text,
        parse_mode: 'HTML',
        disable_web_page_preview: true,
      }),
    });
    const result = await tg.json();
    if (!result.ok) {
      // Telegram's own description is handy here: "chat not found", "bot was blocked", etc.
      console.error('Telegram rejected the message:', result.description);
      res.status(502).json({ ok: false, error: 'telegram_rejected' });
      return;
    }
    res.status(200).json({ ok: true });
  } catch (err) {
    console.error('Could not reach Telegram:', err);
    res.status(502).json({ ok: false, error: 'telegram_unreachable' });
  }
};
