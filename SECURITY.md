# Security & licensing model

This document is deliberately honest about what the plugin's protections do and
don't do. The plugin's brand is *honest newsletters*, and that extends to being
straight about security.

## Reporting a vulnerability

Please email **hello@quintessentialsoftware.co.uk** with details and steps to
reproduce. Do not open a public issue for a security report.

## Application security (how the plugin protects sites and subscribers)

These are the protections that matter most for a newsletter plugin, and they are
implemented throughout the codebase:

- **No direct file execution.** Every PHP file begins with
  `if ( ! defined( 'ABSPATH' ) ) { exit; }`, and every directory carries an
  `index.php` "silence" file, so plugin files cannot be run directly or listed
  by a misconfigured web server.
- **Capability checks.** Every admin action checks
  `current_user_can( SEMNEWS_Admin::capability() )` (default `manage_options`).
- **Nonces / CSRF.** Every state-changing request is protected with
  `wp_nonce_field()` + `check_admin_referer()` / `check_ajax_referer()`.
- **SQL injection.** All database access goes through `$wpdb->prepare()` (or
  `$wpdb` helper methods with format specifiers). Table names are never taken
  from user input.
- **Output escaping.** Output is escaped at the point of use
  (`esc_html`, `esc_attr`, `esc_url`). Email HTML is sanitised with `wp_kses`.
- **Email header injection.** Newline characters are stripped from any value
  that becomes a mail header (From/Reply-To/Subject).
- **Unguessable tokens.** Confirm / unsubscribe / preference links use 32-char
  tokens from `wp_generate_password()` and are compared with `hash_equals()`
  (constant time).
- **No state change on GET.** Confirm, unsubscribe and the personal-data view
  only act on a `POST`; a bare `GET` (link-prefetchers, email scanners, a URL
  leaked to a log/`Referer`) shows a one-button page and changes nothing. The
  mailbox one-click unsubscribe (RFC 8058 `List-Unsubscribe-Post`) still works.
  Opt-in pages send `Referrer-Policy: no-referrer`, and the raw data view is not
  linked directly from emails (only via the preference centre).
- **Token lifetime.** The per-subscriber token is intentionally *not* rotated on
  use: a newsletter's unsubscribe links must keep working in every email already
  sent, so rotating would break compliance. The token is high-entropy and
  `hash_equals`-compared; the personal-data (PII) view is additionally gated
  behind a deliberate POST so a leaked URL alone cannot dump data.
- **CSV-safe exports.** Subscriber exports neutralise spreadsheet formula leads
  (`= + - @`, tab, CR) so attacker-supplied names can't execute on open (CWE-1236).
- **Security events.** Failed webhook authentication and signup rate-limit trips
  fire `do_action( 'semnews_security_event', $type, $context )` for SIEM/alerting.
- **Spam resistance.** Public signup uses a honeypot field and per-IP rate
  limiting, and never reveals whether an address is already subscribed.
- **Data minimisation.** Unconfirmed signups are auto-purged; a one-way hashed
  suppression list keeps unsubscribed/erased people out without storing their
  address in clear.

## "Can people steal the plugin code?"

The plugin is **GPL-2.0-or-later**, and WordPress plugins are plain PHP that runs
on the site owner's own server. We therefore do **not** ship obfuscated or
encrypted code:

- Obfuscation is forbidden by the WordPress.org plugin guidelines and provides
  no real protection (it is trivially reversed).
- PHP source on a server is always readable by whoever controls that server.

What we *do* prevent is the realistic exposure risk: source being served or
listed over HTTP. Direct execution is blocked (`ABSPATH` guard) and directory
listing is blocked (`index.php` in every folder). Site **visitors** never receive
any PHP — only rendered HTML/CSS/JS.

Since 2.0.0 the commercial features live in exactly that shape: a **separate
Pro add-on plugin** (`addons/quintessential-newsletters-pro/`) that hooks the core's documented
actions and filters. The free core contains no licensing code at all.

## "Can people steal a Pro subscription?"

The Pro add-on's license is a **domain-locked, provider-verified check** — never
a simple boolean a database edit could flip. Honesty first: the license gates
**updates and support entitlement**, not features — the add-on's code is GPL
and keeps working if a license lapses; what stops is new versions. The add-on
supports two providers (chosen via the `semnews_license_provider` filter):

### Default: Lemon Squeezy (`SEMNEWS_LemonSqueezy`)

[Lemon Squeezy](https://www.lemonsqueezy.com) is a Merchant of Record — it runs
checkout, handles global sales tax/VAT, and issues license keys. Its license API
is authenticated by the key itself, so **no vendor secret ships in the plugin**.

1. On activation the plugin calls Lemon Squeezy's `/licenses/activate` with the
   customer's key and this site's URL as the instance name.
2. Lemon Squeezy enforces the per-license **activation limit** and returns the
   license status, expiry, and store/product metadata.
3. The add-on stores the activation and **binds it to this site's URL**; a daily
   `semnewsp_license_recheck` cron calls `/licenses/validate` so refunds, expiries
   and manual disables end the update entitlement (with an offline grace period
   until expiry).
4. `SEMNEWS_License::is_pro()` only returns true when the license is `active`, not
   past its expiry, **and bound to this exact site** — so copying the database to
   another domain does not carry the entitlement with it.

Vendors lock activations to their own store/product with
`define( 'SEMNEWS_LS_STORE_ID', '…' )` (and optionally `SEMNEWS_LS_PRODUCT_IDS`) so a
license from a different Lemon Squeezy store can't validate.

### Alternative: self-signed token (`semnews_license_provider` = `token`)

For vendors who run their own licensing server, the plugin can instead verify a
JSON payload — `{ key, site, plan, exp, iat }` — **signed with the vendor's RSA
private key** and checked locally with `openssl_verify()` against the **public**
key in `SEMNEWS_License::public_key()`. Same guarantees: signature + domain + expiry.
Generate your own keypair; ship only the public key.

Both providers defeat the two things that actually happen in practice:

- **Option flipping** — setting a status option does nothing; a valid license
  requires a live provider check (or a signature only the vendor's private key
  can produce).
- **Key sharing** — a license activated for `site-a.com` will not validate on
  `site-b.com`: activations are domain-bound (and capped by the store).

### Honest limitation

Because PHP executes on the customer's own server, anyone who fully controls that
server can edit the plugin to bypass any check (this is true of every
self-hosted PHP licensing scheme — it is not DRM). The signed, domain-locked
design stops casual theft, database tampering and key sharing; it does not, and
cannot, stop a developer editing their own files. We think that's the right,
honest trade-off for a GPL plugin.

### Developer / self-host escape hatches (intentional)

- `define( 'SEMNEWS_PRO', true )` in `wp-config.php` marks the license valid for
  self-hosted or GPL rebuilds of the add-on.
- The `semnews_is_pro` filter is the final override.
- `semnews_license_public_key`, `semnews_license_api_url` and
  `semnews_license_remote_response` let you point the verification at your own key
  pair / server.

Using any of these requires file or server access, which the plugin UI never
grants — so they are not a path a license thief can take through the product.
