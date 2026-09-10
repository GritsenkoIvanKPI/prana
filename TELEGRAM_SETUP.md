# Contact form → Telegram (Webuzo hosting)

The enquiry form on the site (`#contact`) posts to `send.php`, sitting right
next to `index.html`. It validates the submission and forwards it to a
Telegram chat. The bot token lives only on the server — it never reaches
the browser.

```
browser  →  send.php  →  api.telegram.org
                ↑
        telegram-config.php   (token — created on the server, never in git)
```

> There's also `api/lead.js`, the equivalent for Vercel/Node hosting. Since
> you're moving to Webuzo (a PHP host), `send.php` is the one that matters —
> ignore the `api/` folder, it isn't used here.

---

## Step 1. Build the deploy package

From the project folder, run:

```
sh make-deploy.sh
```

This creates `prana-deploy.zip` containing **exactly** what belongs on the
live site: `index.html`, `send.php`, `favicon.ico`, `site.webmanifest`,
`robots.txt`, `sitemap.xml`, `images/`. Nothing else.

**Don't upload anything else from this folder.** Webuzo serves whatever sits
in `public_html` directly by URL — a stray `CLAUDE.md` or `CLIENT_BRIEF.md`
in there would be world-readable at `pranagardens.com/CLAUDE.md`. `send.php`
itself is safe to expose (it's designed to be requested directly), but the
internal docs, `package.json`, `serve.mjs`, `screenshot.mjs`, and the `api/`
folder should stay out of `public_html` entirely.

## Step 2. Upload to Webuzo

1. Webuzo → **File Manager** (or SFTP) → your site's folder, usually
   `/home/<user>/public_html`
2. Upload `prana-deploy.zip` there, then use Webuzo's **Extract** option —
   `index.html` should end up directly inside `public_html`, not inside a
   `prana-deploy` subfolder
3. Check the PHP version: Webuzo → **PHP** / **Select PHP Version** → pick
   **7.4 or newer** (8.1+ preferred)
4. Make sure the **curl** extension is enabled: Webuzo → **PHP → Extensions
   → curl**
5. Turn on **SSL**: Webuzo → **SSL** → Let's Encrypt. Without HTTPS, form
   submissions travel in plain text

## Step 3. Create the bot

1. In Telegram, open [@BotFather](https://t.me/BotFather)
2. Send `/newbot`, give it a name and a username (must end in `bot`, e.g.
   `pranagardens_bot`)
3. BotFather replies with a **token** like `123456789:AAH...` — copy it.
   Don't paste it anywhere public (chat, email) — treat it like a password

## Step 4. Get the chat ID

1. Create a Telegram group for enquiries to land in
2. Add your new bot to that group
3. Send any message in the group
4. Visit this URL in a browser, replacing `<TOKEN>`:
   `https://api.telegram.org/bot<TOKEN>/getUpdates`
5. Find `"chat":{"id":-100XXXXXXXXXX, ...}` in the response — that negative
   number is your chat ID

> If `getUpdates` shows an empty `result`, send another message in the group
> and reload — the bot only sees messages sent after it was added.

## Step 5. Create `telegram-config.php` on the server

In Webuzo's File Manager, create a new file **directly in `public_html`**
(right next to `send.php`) named `telegram-config.php`, with this content:

```php
<?php
return array(
    'token'   => 'paste your bot token here',
    'chat_id' => '-100XXXXXXXXXX',
);
```

Create this file **on the server, not in the zip** — that's what keeps the
real token out of the Git repository and off your computer's copy.
(`telegram-config.example.php` in the repo shows the same structure with
placeholder values, for reference.)

If your Webuzo plan lets you set environment variables per-site, you can use
`TELEGRAM_BOT_TOKEN` / `TELEGRAM_CHAT_ID` instead — `send.php` checks those
first and only falls back to `telegram-config.php` if they're not set.

## Step 6. Check the setup without sending anything

Open this directly in a browser:

```
https://pranagardens.com/send.php?selftest=1
```

Expected result:

| Field | Should be |
|---|---|
| `php_ok` | `true` |
| `config_source` | `telegram-config.php` (or `environment variables`) |
| `token_present` | `true` |
| `chat_id` | your chat ID |
| `curl_available` | `true` |
| `can_reach_telegram` | `true` |

The token itself is never shown — only whether it's present and how long it
is. This check calls Telegram's harmless `getMe` endpoint, so it does **not**
post anything into your group — reload it as many times as you like while
troubleshooting.

## Step 7. Send a real test enquiry

Open the live site, fill in the contact form, submit.

| What you see | What it means |
|---|---|
| "Thank you — we'll be in touch within one business day." | it worked, the message is already in your Telegram group |
| "Something went wrong — please call or WhatsApp us directly..." | it didn't send — see below |

Success is **never** shown unless the message actually reached Telegram, so
a failed submission is never silently lost.

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
Page: https://pranagardens.com/
```

## Protections already built in

- **Token never reaches the browser** — the page only calls its own
  `send.php`; the token lives solely in `telegram-config.php` on the server
  (or environment variables), never in HTML or JS.
- **Honeypot field** — a hidden input real visitors never see or fill in;
  bots that auto-fill every field get silently discarded.
- **Rate limiting** — max 5 submissions per IP per 10 minutes.
- **Validation & escaping** — empty required fields are rejected, and all
  text is HTML-escaped before being sent, so it can't inject formatting or
  links into the Telegram message.

## Troubleshooting

`send.php` never reveals the failure reason in the browser response (it
could leak details about the setup) — the real reason always goes to the
PHP error log: Webuzo → **Logs** → error log for the site.

| What the visitor sees / API returns | Likely cause |
|---|---|
| `not_configured` | `telegram-config.php` missing, or the values weren't filled in |
| `chat not found` | the bot was removed from the group, or the chat ID is missing its leading `-` |
| `telegram_rejected` / `403 Forbidden` | bot blocked or removed from the group |
| `too_many_requests` | rate limit hit; wait 10 minutes |
| `telegram_unreachable` | server can't reach api.telegram.org — check the curl extension is enabled |
| Form does nothing / network error in browser console | `send.php` isn't next to `index.html`, or a 404/500 — check the exact upload path |

## If the token leaks

In [@BotFather](https://t.me/BotFather): `/revoke` → pick the bot → it
issues a new token immediately, and the old one stops working. Update
`telegram-config.php` (or the environment variable) with the new value —
no redeploy needed, PHP picks it up on the next request.
