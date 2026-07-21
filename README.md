> **This is the public mirror of the free Simple Email Newsletters plugin.**
> The free core is developed in the open — full source, test suite and build tools.
> The commercial Pro add-on and the licensing server live in a private repository;
> Pro is sold from [quintessentialsoftware.co.uk](https://quintessentialsoftware.co.uk/).
> Issues and pull requests are welcome here. The mirror is synced as a single
> commit per release, so long-running forks should rebase onto the latest sync.

# Simple Email Newsletters



A deliberately **simple**, **honest**, **GDPR-friendly** newsletter plugin for WordPress.

**Free and unlimited** — no subscriber cap, no locked features. Every trust and compliance feature — double opt-in, consent logging, one-click unsubscribe, data export/erasure — is **free, forever**. A separate paid **Pro add-on** (`addons/quintessential-newsletters-pro/`) adds power features: scheduled sending, overlay placements, sender profiles and a public archive.

---

## Why another newsletter plugin?

Most newsletter tools bury the ethical defaults behind upsells: pre-ticked boxes, silent tracking, single opt-in, hard-to-find unsubscribe links. This plugin inverts that. The honest choice is the default, and the paid add-on only ever sells **convenience and power features** — never trust.

## Features (free)

| Area | What you get |
|------|--------------|
| **Consent** | Double opt-in on by default; never a pre-ticked box; the exact consent text, time, IP and source are logged as proof (GDPR Art. 7). |
| **Unsubscribe** | One-click, login-free unsubscribe link in every email + RFC 8058 `List-Unsubscribe` / `List-Unsubscribe-Post` headers for Gmail/Yahoo. |
| **GDPR** | Integrates with WordPress' built-in *Export* and *Erase Personal Data* tools; suggested privacy-policy text; daily auto-purge of stale unconfirmed signups (data minimisation). |
| **Suppression list** | Unsubscribed or erased addresses are stored as one-way hashes so they can never be silently re-imported or re-mailed. |
| **Forms** | Signup as a Gutenberg block, the `[semnews_newsletter]` shortcode, or a widget — with honeypot + timing + rate-limit spam protection (no CAPTCHA). |
| **Placements** | Show the form automatically **after/within post content** (Pro adds popup, slide-in and sticky bar) — hidden for existing subscribers and off by default. |
| **Templates + custom HTML** | Four built-in layouts (Simple, Cards, Magazine, Plain text) plus a **Custom HTML** template with a `{posts}…{/posts}` merge-tag loop. "Build from posts" in the composer too. |
| **Deliverability panel** | Live **SPF / DKIM / DMARC** DNS checks with copy-paste records and a "send myself a deliverability test". |
| **Subject-line linter** | Advisory, never-blocking checks: fake `Re:`/`Fwd:`, ALL-CAPS, image-only emails, missing preheader. |
| **Setup wizard** | Three steps (sender + domain alignment, postal address, consent) shown on activation. |
| **Bounce / complaint suppression** | Authenticated REST webhook (`/wp-json/semnews/v1/webhook`) that **auto-detects SendGrid, Amazon SES (SNS), Mailgun & Postmark** and feeds hard bounces + spam complaints into the suppression list; "mark bounced" bulk action. |
| **PECR soft opt-in** | Add existing customers under the lawful soft opt-in basis with an attestation, recorded in the consent log. |
| **Subscriber self-service** | Each person can view & download their own data + consent history from a token link in every email. |
| **WP-CLI + i18n** | `wp semnews …` commands; full `.pot` and RTL stylesheet/email support. |
| **Sending** | Batched, resumable queue via WP-Cron; auto plain-text alternative; honest merge tags (`{{name}}`, `{{first_name}}`, `{{email}}`). |
| **Compliance dashboard** | A "trust mirror" showing double opt-in, postal address, privacy link, tracking-off and retention status at a glance. |
| **Your data** | CSV export of your subscribers any time. Nothing is ever sent to us. |

## Free vs Pro

**Free (this plugin) is complete and unlimited** — it contains no cap, licensing or update-phone-home code at all, which also makes it eligible for the WordPress.org plugin directory.

**Pro** is a separate add-on plugin (developed in `addons/quintessential-newsletters-pro/`, sold and updated from quintessentialsoftware.co.uk) that plugs in through public hooks:

* Lists & tags segmentation — target sends at the union of chosen lists/tags; forms join a list via `list="…"`
* Scheduled sending — write now, send at a chosen date and time (anything already scheduled can still be cancelled from the free plugin)
* Automated digests — daily / weekly / monthly, refilled with your latest posts; run one or several side by side — and a welcome series (up to three emails after confirmation)
* The template gallery (Cards, Magazine, Plain text, Announcement, Compact digest, Custom HTML) with a brand logo on every design, and a WooCommerce checkout opt-in
* Engagement insights: click-based (no open pixels), consent or attested legitimate-interests mode, subscriber opt-out, 90-day auto-expiry, engaged-only sends
* Popup, slide-in and sticky-bar signup placements
* Multiple named sender identities (per-newsletter From)
* Public newsletter archive (`[semnews_archive]`)
* Priority support; the license entitles a site to updates — it never disables features

The Pro add-on carries the licensing stack (Lemon Squeezy or the bundled Stripe License Server, signed domain-locked tokens) and the self-hosted update checker.

Pro is unlocked by a **cryptographically signed, domain-locked license token** — not a database flag — so it can't be enabled by editing options or shared across sites. See [SECURITY.md](SECURITY.md) for the full, honest threat model.

## Usage

```text
[semnews_newsletter title="Join the list" show_name="true" button="Subscribe"]
```

Or insert the **Newsletter Signup** block, or drop the **Newsletter Signup** widget into a sidebar.

Confirm / unsubscribe / preference links are handled at `?semnews_action=confirm|unsubscribe|preferences&semnews_id=…&semnews_token=…` — no rewrite rules to flush.

## Architecture

```
quintessential-newsletters.php   Bootstrap, constants, requires
uninstall.php                  Honours "keep data" by default
includes/
  class-semnews-install.php        Tables (dbDelta), cron, lifecycle
  class-semnews-templates.php      Post→email renderers + Custom HTML loop
  class-semnews-linter.php         Honest subject-line / content linter
  class-semnews-deliverability.php SPF/DKIM/DMARC checks + self-test
  class-semnews-webhook.php        Authenticated bounce/complaint REST webhook
  class-semnews-cli.php            WP-CLI commands
  class-semnews-subscribers.php    Subscriber lifecycle + double opt-in
  class-semnews-consent-log.php    Append-only proof-of-consent trail
  class-semnews-suppression.php    One-way hash suppression list
  class-semnews-campaigns.php      Newsletter CRUD
  class-semnews-queue.php          Per-recipient send queue
  class-semnews-mailer.php         Email building, headers, plain-text
  class-semnews-sender.php         Batched WP-Cron sender
  class-semnews-optin.php          Public confirm/unsubscribe/preferences
  class-semnews-forms.php          Shortcode + AJAX/POST submission
  class-semnews-gdpr.php           WP privacy exporter/eraser + retention
  class-semnews-settings.php       Settings API
  class-semnews-block.php          Server-rendered Gutenberg block
  class-semnews-widget.php         Classic widget
  class-semnews-plugin.php         Orchestrator
admin/                         Menus, list table, views
templates/emails/              Confirmation, welcome, layout
assets/                        CSS / JS
```

### Extensibility (hooks)

* `semnews_is_pro` (filter, Pro add-on) — final Pro override (for the official Pro add-on; requires server access).
* `semnews_license_public_key`, `semnews_license_api_url`, `semnews_license_remote_response` (filters, Pro add-on) — point license verification at your own key pair / server.
* `semnews_templates`, `semnews_posts_query_args`, `semnews_rendered_template` (filters) — register templates and shape the post query / output.
* `semnews_automation_sent` (action) — fires after the Pro add-on queues an automated digest.
* `semnews_subscriber_created`, `semnews_subscriber_confirmed`, `semnews_subscriber_unsubscribed`, `semnews_confirmation_blocked`, `semnews_campaign_sent`, `semnews_subscriber_suppressed` (actions).
* `semnews_mail_headers`, `semnews_merge_tags`, `semnews_confirmation_subject`, `semnews_welcome_subject` (filters).
* `semnews_email_logo_url` (filter) — logo shown atop the confirmation/welcome emails (the Pro add-on feeds its brand-logo setting through this).
* `semnews_envelope_sender` (filter) — the envelope sender (Return-Path) aligned with the From address; return `''` to leave the host default alone.
* `semnews_pro_currency`, `semnews_pro_price_monthly`, `semnews_pro_price_annual`, `semnews_upgrade_url` (filters) — the price shown on the Upgrade screen (monthly, billed annually) and the buy link.

## Shipped in 1.2.0 (former roadmap)

All of the v1 roadmap landed in 1.2.0:

- ✅ **Deliverability health panel** — live SPF/DKIM/DMARC checks, copy-paste DNS fixes, "send myself a deliverability test".
- ✅ **Honest subject-line linter** — advisory warnings (fake `Re:`/`Fwd:`, ALL-CAPS, image-only, missing preheader). Never blocks.
- ✅ **Setup wizard** — sender + domain alignment, postal address, consent.
- ✅ **Self-service transparency view** — subscribers view/download their data + consent history via their token link.
- ✅ **Hard-bounce & complaint auto-suppression** — authenticated webhook into the suppression list.
- ✅ **PECR/ePrivacy soft opt-in** modelling for existing-customer lists, with attestation.
- ✅ **WP-CLI commands** — `wp semnews subscriber export`, `wp semnews queue run`, and more.
- ✅ **Multilingual/RTL** — full `.pot` + RTL stylesheet/email support.

### Still ahead (natural Pro features)

- Segmentation & tags, A/B subject testing, a visual drag-and-drop builder, and an opt-in analytics dashboard.

## Requirements

- WordPress 5.8+
- PHP 7.4+

## License

GPL-2.0-or-later.
