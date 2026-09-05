# Contact form → Telegram

The enquiry form on the site (`#contact`) posts to `/api/lead`, a serverless
function that forwards it to a Telegram chat. The bot token lives only on
Vercel as an environment variable — it never reaches the browser.

```
Your site  →  Vercel (/api/lead.js, token lives here)  →  your Telegram chat
 the form         free, runs automatically                 enquiry arrives
```

Since the site is already deployed on Vercel, no extra hosting or plumbing is
needed — just create the bot and add two environment variables.

---

## Step 1. Create the bot

1. In Telegram, open [@BotFather](https://t.me/BotFather)
2. Send `/newbot`, give it a name and a username (must end in `bot`, e.g. `prana_leads_bot`)
3. BotFather replies with a **token** that looks like `123456789:AAH...` — copy it

## Step 2. Get the chat ID

1. Create a Telegram group (or use an existing one) for enquiries to land in
2. Add your new bot to that group
3. Send any message in the group
4. Visit this URL in a browser, replacing `<TOKEN>` with your bot's token:
   `https://api.telegram.org/bot<TOKEN>/getUpdates`
5. Find `"chat":{"id":-100XXXXXXXXXX, ...}` in the response — that negative
   number is your `TELEGRAM_CHAT_ID`

## Step 3. Add the environment variables on Vercel

1. Open the project on [vercel.com](https://vercel.com) → **Settings** → **Environment Variables**
2. Add:

   | Name | Value |
   |---|---|
   | `TELEGRAM_BOT_TOKEN` | the token from Step 1 |
   | `TELEGRAM_CHAT_ID` | the id from Step 2 (including the `-`) |

3. **Redeploy** the project (Deployments tab → ⋯ → Redeploy) — variables only
   take effect on a fresh deploy

## Step 4. Test it

Open the live site, fill in the contact form, submit.

| What you see | What it means |
|---|---|
| "Thank you — we'll be in touch..." | it worked, the message is already in your Telegram chat |
| "Something went wrong — please call or WhatsApp us directly..." | it didn't send — see below |

Success is **never** shown unless the message actually reached Telegram, so a
failed submission is never silently lost.

---

## What arrives in the chat

```
🌿 New enquiry — PRANA

Name: Jane Smith
Phone / WhatsApp: +44 7700 900123
Email: jane@example.com
What they need: Full garden design & build
Message: 600m² plot in Benahavís, looking to start in spring.

Source: PRANA website contact form
When: 06/09/2026, 14:32:10
Page: https://prana-gray.vercel.app/
```

## Protections already built in

- **Token never reaches the browser** — the page only calls its own
  `/api/lead` endpoint; the token lives solely in Vercel's environment.
- **Honeypot field** — a hidden input real visitors never see or fill in;
  bots that auto-fill every field get silently discarded.
- **Rate limiting** — max 5 submissions per IP per 10 minutes.
- **Validation & escaping** — empty required fields are rejected, and all
  text is HTML-escaped before being sent, so it can't inject formatting or
  links into the Telegram message.

## Troubleshooting

- `not_configured` (in Vercel logs) — the environment variables aren't set,
  or you forgot to Redeploy after adding them
- `chat not found` — the bot was removed from the group, or the chat ID is
  missing its leading `-`
- `403 Forbidden` / `telegram_rejected` — the bot was blocked or removed
  from the group
- `too_many_requests` — the rate limit kicked in; wait 10 minutes
- Check **Vercel → your project → Logs** for the exact error

## If the token leaks

In [@BotFather](https://t.me/BotFather): `/revoke` → pick the bot → it issues
a new token immediately, and the old one stops working. Update
`TELEGRAM_BOT_TOKEN` on Vercel with the new value and Redeploy.
