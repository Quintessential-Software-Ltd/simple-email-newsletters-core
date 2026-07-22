=== Quintessential Newsletters ===
Contributors: quintessentialsoftware
Tags: newsletter, email, subscribers, gdpr, double opt-in
Requires at least: 5.8
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 2.5.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Honest, GDPR-friendly newsletters for WordPress. Double opt-in and consent logging as standard. Unlimited subscribers, free.

== Description ==

Quintessential Newsletters is a deliberately simple newsletter plugin built around **honest email**. Unlimited subscribers, unlimited sending, and every feature you need to collect real, informed consent and treat subscribers with respect — free. We never paywall trust, and we never cap your list.

*A note on the law: no plugin can make your newsletter legally compliant by itself. This plugin gives you strong defaults, consent records and plain-language guidance — it is not legal advice.*

* **Double opt-in by default** — people confirm by email before they are added.
* **Consent logging** — the exact wording, time, IP and source of every signup is recorded as proof (GDPR Art. 7).
* **One-click unsubscribe** — no login, no friction, plus RFC 8058 `List-Unsubscribe` headers for Gmail/Yahoo.
* **Template previews** — one click renders any template with your own latest posts, wrapped in the real email layout, before you commit to it
* **Clean built-in template** — a tidy, deliverability-friendly Simple layout for your newsletters, with `{site_name}`, `{date}` and a repeatable `{posts}…{/posts}` loop. (The Pro add-on adds a gallery of designed templates and a write-your-own Custom HTML option.)
* **Deliverability health panel** — live SPF/DKIM/DMARC checks with copy-paste DNS fixes and a one-click "send myself a test".
* **Honest subject-line linter** — friendly, never-blocking warnings about fake Re:/Fwd:, ALL-CAPS, image-only emails and missing preheaders.
* **Setup wizard** — three short steps to a sending-ready newsletter with honest defaults.
* **Bounce & complaint suppression** — an authenticated webhook feeds bounces and spam complaints straight into your suppression list.
* **PECR soft opt-in** — add existing customers under the lawful soft opt-in basis with a built-in attestation, recorded for your records.
* **Your own data, transparently** — every subscriber can view and download their data and consent history from a link in any email.
* **WP-CLI + translation-ready** — power-user commands and a full .pot with RTL support.
* **GDPR tools** — works with WordPress' built-in Export and Erase Personal Data tools, plus a suppression list so erased or unsubscribed people are never silently re-added.
* **Honest by default** — the consent box is never pre-ticked, tracking is off (the Pro add-on offers optional, disclosed, click-based insights — never hidden pixels), and every email carries your real sender identity and postal address.

= Free vs Pro =

**This plugin is complete and free: unlimited subscribers, unlimited newsletters, and every trust feature — double opt-in, consent log, suppression list, preference centre, deliverability checks, bounce/complaint auto-suppression, GDPR export/erasure, a clean built-in template, WP-CLI and translations.** Nothing in it is locked, capped or time-limited.

A separate, paid **Pro add-on** (sold from [quintessentialsoftware.co.uk](https://quintessentialsoftware.co.uk/)) adds power features on top for business senders, plus priority support:

* **Lists & tags segmentation** — organise subscribers into lists (joinable via a form's `list="…"` attribute) and tags, then send a newsletter to just those segments
* **Scheduled sending** — write now, send at a chosen date and time (free sends immediately)
* **Welcome series** — up to three emails sent automatically to every new subscriber after they confirm
* **Automated digests** — send a newsletter of your choice daily, weekly or monthly, its `{posts}…{/posts}` block refilled with the posts published since the last issue; run one or several side by side, each with its own newsletter, schedule and categories
* **Template gallery** — Cards, Magazine, Plain text, Announcement and Compact digest designs with your own brand logo on every one, plus a write-your-own Custom HTML template with merge tags
* **WooCommerce checkout opt-in** — a never pre-ticked newsletter checkbox at checkout; buyers who say yes are tagged "Customer"
* **Signup protection extras** — a curated blocklist of disposable-email providers, your own blocked-domains list, and one automatic reminder to signups still unconfirmed after 3 days (on top of the free plugin's honeypot, time-trap, rate limiting and mail-domain checks)
* **Engagement insights** — honest, click-based measurement (no hidden open pixels): first-party redirects on your own site, consent or documented legitimate-interests mode with a subscriber opt-out in the preference centre, data auto-expiring after 90 days, and a "send only to engaged subscribers" switch on every newsletter
* **Popup, slide-in and sticky-bar** signup placements (free includes the block, shortcode, widget and in/after-post placements)
* **Multiple sender identities** — named From profiles, pick one per newsletter
* **Public newsletter archive** — let visitors read past issues via `[semnews_archive]`

Trust and compliance features will never move to Pro.

= External services =

The plugin never contacts an external service on its own and contains no tracking or analytics of any kind. The only outbound connections it can make are ones the site owner explicitly sets up:

* **Amazon SNS / Amazon SES (optional).** Only if you choose to connect Amazon SES bounce/complaint notifications to this plugin's webhook: when Amazon SNS sends its one-time subscription-confirmation message, the plugin makes a single GET request to the `sns.*.amazonaws.com` `SubscribeURL` that Amazon includes, to complete the handshake. No data of yours is sent in that request (it only visits Amazon's confirmation URL), and it never contacts AWS at any other time. This is entirely optional and only happens if you set up SES notifications yourself.
    * Amazon Web Services — terms: https://aws.amazon.com/service-terms/ , privacy: https://aws.amazon.com/privacy/

That is the complete list — this plugin itself never phones home. (The separate Pro add-on validates its license key and checks for its updates against the vendor's server; that is documented in the add-on, sends only the license key and site URL, and never any subscriber data.)

Everything else stays on your server: subscribers live in your WordPress database and emails are sent by your own site through `wp_mail()`.

= Development =

The free plugin is developed in the open on [GitHub](https://github.com/Quintessential-Software-Ltd/quintessential-newsletters-core) — full source code, the test suite and build tools. Bug reports and pull requests are welcome. (The commercial Pro add-on is developed privately and sold from quintessentialsoftware.co.uk; its code is GPL like everything else.)

== Installation ==

1. Upload the `quintessential-newsletters` folder to `/wp-content/plugins/`, or install the zip from Plugins → Add New.
2. Activate the plugin.
3. Go to **Newsletters → Settings** and set your From name, From email and physical postal address.
4. Add the signup form to a page using the `[semnews_newsletter]` shortcode, the **Newsletter Signup** block, or the widget.
5. Write and send your first newsletter from **Newsletters → Write new**.

== Frequently Asked Questions ==

= Does it really do double opt-in? =
Yes, and it is on by default. New signups are stored as "pending" and never emailed campaigns until they click the confirmation link. The confirmation time and IP are recorded.

= Is there a subscriber limit? =
No. Free means unlimited subscribers and unlimited newsletters. There is no cap, trial or quota anywhere in this plugin.

= How does it send large lists without timing out? =
Sending runs in throttled background batches via WP-Cron, one queue row per recipient, so it resumes safely and never double-sends.

= Will it work with my SMTP plugin? =
Yes. It sends through `wp_mail()` and cooperates with any SMTP/mailer plugin you already use.

= Is my subscriber data ever sent anywhere? =
No. Your list stays in your WordPress database. The plugin never transmits subscriber data to us or anyone else.

= What stops fake or bot signups? =
Several layers, all in the free plugin: a honeypot field and a time-trap catch bots (they are told "success" and discarded), per-IP rate limiting stops floods, at most one confirmation email is sent per address per 15 minutes, the email's domain must actually be able to receive mail, and pending signups that never confirm are purged automatically after your retention window. Nothing gets onto your list without clicking the confirmation link. The Pro add-on additionally rejects disposable-email providers and can send one reminder to genuine signups who missed the confirmation email.

= Can it send automated digests of my latest posts? =
Automated sending is part of the separate Pro add-on — this plugin only sends when you press send. With the add-on installed, you write a newsletter in the normal editor, then on its Automated screen pick that newsletter, a frequency (daily, weekly or monthly), a send time, how many posts and which categories; on each run the newsletter's {posts}…{/posts} block is refilled with the posts published since the last issue, and empty issues are skipped by default. You can run several digests side by side.

= Can I design my own newsletter? =
Yes. Pick one of the built-in templates, or choose **Custom HTML** and write your own with `{site_name}`, `{date}` and a repeatable `{posts}…{/posts}` block containing `{post_title}`, `{post_url}`, `{post_excerpt}`, `{post_image}`, `{post_date}` and `{post_author}`. The sender-identity footer and unsubscribe link are always added for you. For one-off newsletters you can also write free-form HTML in the composer, or use "Build from posts".

= What does the Pro add-on change on this plugin? =
Nothing is taken away and nothing is unlocked — this plugin is already complete. The separate Pro add-on plugs extra features in (lists & tags segmentation, scheduled sending, automated digests, overlay signup placements, named sender profiles, a public archive) through this plugin's public hooks. If Pro is ever deactivated, anything it scheduled can still be cancelled here.

== Screenshots ==

1. Dashboard with subscriber stats and compliance status.
2. Subscriber management with status filtering and bulk actions.
3. The newsletter composer with templates, "build from posts", recipient count and test send.
4. The GDPR-friendly signup form on the front end.

== Changelog ==

= 2.5.2 =
* Security hardening on the Subscribers screen: the search and status filter inputs are now accepted only with a valid nonce from the screen's own forms and are checked against a fixed whitelist, and the screen re-verifies the user's permission directly.

= 2.5.1 =
* Renamed the plugin to **Quintessential Newsletters** and corrected the translation text domain to `quintessential-newsletters` so it matches the WordPress.org plugin slug — community translations now load out of the box.
* Standardised every code identifier on the `semnews` prefix (functions, classes, options, database tables, hooks, the `[semnews_newsletter]` shortcode and the `wp semnews` WP-CLI commands). Existing subscribers, campaigns, settings and secrets are migrated in place automatically on upgrade — nothing is lost. If you hooked the plugin's actions/filters or used the shortcode or CLI, update those names to the new prefix.

= 2.5.0 =
* Automated sending has moved entirely to the separate Pro add-on: the free plugin no longer contains the digest engine, its hourly cron tick, the Automated screen or the `wp semnews digest run` command, and it sends only when you press send. An existing digest configuration is kept and picks up exactly where it left off once the add-on is active.
* The standalone confirm/unsubscribe/preferences pages now load their stylesheet through the WordPress enqueue API instead of an inline style block.
* All plugin-generated HTML (signup forms, opt-in pages, email bodies and previews) is now escaped at output time with context-appropriate `wp_kses` allowlists.

= 2.4.0 =
* Refined the plugin's display name for a more distinctive listing (the plugin, its settings and your subscribers are unchanged — only the name).
* Scheduled sending is now provided entirely by the separate Pro add-on: the free plugin sends immediately and no longer carries any scheduling engine. The queue tick exposes a new `semnews_process_queue` action the add-on hooks.
* The in-admin pointers to Pro features are now plain, non-interactive notes with a link, rather than disabled example controls.
* Documented the optional Amazon SNS/SES bounce-webhook handshake (with service links) in the External services section.
* Removed a redundant, too-generic signup shortcode; use `[semnews_newsletter]`.

= 2.3.0 =
* Signup protection hardened: the plugin already refused bot submissions (honeypot, time-trap, per-IP rate limiting) and purged stale pending signups daily; it now also checks that a signup's email domain can actually receive mail (MX lookup with RFC 5321 fallback, cached per domain, failing open if DNS is unavailable) and sends at most one confirmation email per address per 15 minutes, so the form cannot be used to bomb an inbox. New semnews_signup_domain_allowed filter lets add-ons veto domains — the Pro add-on uses it to reject disposable-email providers, and adds a single confirmation reminder for genuine signups still pending after 3 days.
* The confirmation and welcome emails have a polished new look: a branded header (your company name as a wordmark, or your logo via the new semnews_email_logo_url filter — the Pro add-on feeds its brand-logo setting through it), a proper heading, a stronger call-to-action button, and a "this request was made on your-site" trust note in the confirmation email.
* Deliverability: the mailer now aligns the envelope sender (Return-Path) with your From address when the host has not set one, so SPF is evaluated against your domain instead of the web host's default. Override or disable via the new semnews_envelope_sender filter.
* Template previews now work without JavaScript: the Preview button links directly to the selected template's preview, and the script only re-targets it when the selection changes.
* Pro previews in the admin are redesigned: framed with a clear purple accent, clearly labelled, with readable descriptions — only the example controls are dimmed. Every Pro feature now has one, in the spot the real control appears: the template gallery in the newsletter editor, the brand logo in Settings → Sender, and signup-protection extras, the public archive and the WooCommerce checkout opt-in (when WooCommerce is active) in Settings → Privacy & data.

= 2.2.0 =
* Automated digests now send a newsletter you pick from the ordinary Newsletters list instead of content authored on the automation screen. The selected newsletter's {posts}…{/posts} block is refilled with the newest posts on every send, so one place — the newsletter editor — styles everything.
* Existing automated digests migrate automatically: your subject, intro, template and custom HTML become a real draft newsletter and the schedule points at it. Nothing changes in what your subscribers receive.
* A "Create a starter digest newsletter" button scaffolds the {posts} loop for new setups, and the automation screen now matches the newsletter editor's layout.
* New Upgrade screen plus greyed-out previews of the Pro add-on's controls (scheduling, sender identities, lists & tags, overlay placements) in the spots they would appear. Shown only while the add-on is not installed; the free plugin remains complete and unlimited.
* Templates: the free plugin now ships the Simple list template; the designed gallery (Cards, Magazine, Plain text, the new Announcement and Compact digest, and the Custom HTML template) moved to the Pro add-on. Anything saved with a gallery template renders with the Simple layout until Pro is active — nothing breaks and no content is lost, and the {site_name}/{date}/{posts} merge tags in newsletter content remain a free core feature.
* New hooks for add-on automations: semnews_automation_after (extra panels on the Automated screen) and the existing hourly tick now also drives the Pro add-on's extra digests.
* Template previews: a Preview button beside the template picker renders the selected template with your latest real posts in the full email layout (new semnews_preview_template endpoint). The Pro gallery templates gain a brand logo (Settings → Sender → Brand logo, media-library picker) shown at the top of every design.
* The public development repository is now the core-only mirror at github.com/Quintessential-Software-Ltd/quintessential-newsletters-core (the Plugin URI follows). Same GPL license, same open development for the free plugin; the commercial add-on moved to a private repository.
* New hooks for consent-aware engagement measurement (Pro): semnews_campaign_body_for_subscriber (per-subscriber body filter, applied before the footer so unsubscribe links can never be rewritten), semnews_preferences_fields / semnews_preferences_saved (preference-centre fields), and semnews_subscriber_export_data (add-on data joins the self-service GDPR export). The free plugin itself still tracks nothing.


= 2.1.3 =
* Vendor details updated: author and product pages now point to quintessentialsoftware.co.uk and the development repository moved to github.com/Quintessential-Software-Ltd/quintessential-newsletters. Metadata only.

= 2.1.2 =
* Plugin author is now Quintessential Software Ltd (metadata only; no functional changes).

= 2.1.1 =
* Clicking an unsubscribe/preferences link in a [TEST] email or browser preview now shows a friendly explanation (test emails have no real recipient, so those links are placeholders) instead of an "invalid link" error, and the Send-a-test panel says so up front.

= 2.1.0 =
* New public hooks so add-ons can segment and extend safely: semnews_campaign_recipients_sql and semnews_campaign_recipient_count (narrow who receives a campaign), semnews_campaign_saved / semnews_campaign_deleted / semnews_subscriber_deleted (persist and clean add-on data), semnews_form_atts_defaults / semnews_form_hidden_fields (extra signup-form attributes and fields) and semnews_subscriber_view_panels (extra panels on the subscriber profile). No behaviour changes without an add-on installed — segments can only ever narrow the confirmed-subscriber audience, never bypass unsubscribes or suppression.

= 2.0.0 =
* Free is now unlimited: the 50-confirmed-subscriber cap is gone, along with all licensing and update-checker code — the plugin no longer has any locked or quota-limited functionality, making it fully compliant with the WordPress.org directory guidelines.
* New Pro add-on (separate plugin, sold from quintessentialsoftware.co.uk): scheduled sending, popup/slide-in/sticky-bar placements, multiple sender identities and the public newsletter archive move there, wired through new public hooks (semnews_admin_menu, semnews_campaign_editor_fields, semnews_campaign_send_panel, semnews_campaign_sender, semnews_display_defaults/sanitize/sections, semnews_settings_privacy_rows, semnews_sanitize_settings, semnews_default_settings, semnews_dashboard_panels, semnews_admin_notices).
* Kept in core deliberately: the scheduling engine (anything already scheduled still fires and can always be cancelled, even without the add-on), the in/after-post form placements, one automated digest, and every trust/compliance feature.
* Existing sites: nothing is lost on upgrade. Campaign sender choices, display settings and the archive toggle are preserved and picked up by the Pro add-on when it is installed.

= 1.8.4 =
* Guideline pass against the WordPress.org detailed plugin guidelines: the free-limit notice is now shown only on the plugin's own screens instead of site-wide (guideline 11), a new "External services" readme section documents every possible outbound connection — license validation, optional self-hosted updates, Amazon SNS handshake — and confirms there is no tracking of any kind (guidelines 6–7), a "Development" section links the public source and build tools (guideline 4), wording that could be read as promising legal compliance was softened with an explicit not-legal-advice note (guideline 9), and the admin script no longer declares an unused jQuery dependency.

= 1.8.3 =
* Composer: unsaved-changes protection. Send now, Schedule, Send test and Duplicate all act on the last saved draft and reload the page — they now warn first if you have unsaved edits (which would otherwise be sent stale and silently lost). Navigating away with unsaved edits triggers the browser's native warning, and "Preview in browser" explains it shows the saved version.

= 1.8.2 =
* Preheader: hardened the hidden preview text so inboxes reliably use it — full industry-standard hiding styles (including mso-hide for Outlook) plus whitespace padding so mail clients don't append body text to the snippet. The padding never leaks into the plain-text part.
* Preheader: merge tags like {{first_name}} now work in the preheader, and the composer explains that the preheader appears next to the subject in the inbox list (it is deliberately invisible inside the opened email).
* Automated digests now send with an honest preheader built from the post titles inside the issue, instead of letting the inbox show a random snippet.

= 1.8.1 =
* Compliance panel: every check now names the law it comes from (GDPR/PECR, CAN-SPAM, CASL, ePrivacy, Australia's Spam Act), two new always-satisfied rows highlight the consent records and never-expiring unsubscribe links the plugin already provides, and a clear disclaimer explains that the checklist is the strictest common denominator across regimes — these laws follow where your subscribers live, and it is guidance, not legal advice.

= 1.8.0 =
* Reliability: sending is now crash- and concurrency-safe — atomic per-campaign locks, queue rows are claimed before mailing (a PHP death mid-batch resumes with the unsent remainder instead of re-mailing), failed sends retry with backoff, and each batch respects a time budget. Campaigns can no longer be edited or re-sent mid-send.
* Scheduling: schedule a newsletter for a future date/time, pause and resume mid-send, and the automated digest now catches up when WP-Cron misses its hour instead of silently skipping a day/week/month.
* Scale: CSV exports stream with constant memory (no more silent 100,000-row truncation), the send loop lost its per-recipient query, old queue rows are purged, and hot queries gained indexes.
* Admin: subscriber detail view with full consent history, an Art. 30 consent-register export, duplicate campaign, browser preview, CSV file-upload import, campaigns pagination, WP-Cron/mail-error health warnings, and editable confirmation/welcome email text.
* New: opt-in public newsletter archive ([semnews_archive], default off) and a self-hosted update checker (SEMNEWS_UPDATE_URL) served by the License Server.
* Honesty: removed the open/click-tracking checkbox that had no implementation behind it — the plugin truthfully contains no tracking at all.
* Multisite: network activation/uninstall now handles every site; new subsites are provisioned automatically.
* Developer: bundled dependency-free test suite (86 checks) and a GitHub Actions CI workflow.

= 1.7.3 =
* New: the Upgrade screen now shows the Pro price as a monthly figure billed annually (default $5/mo, $60/year). Filterable via semnews_pro_currency / semnews_pro_price_monthly / semnews_pro_price_annual so it matches your Lemon Squeezy or Stripe product.

= 1.7.2 =
* Pricing clarity: Free now includes every plugin feature — the only difference in Pro is removing the 50 confirmed-subscriber cap (unlimited subscribers). Upgrade screen, composer copy and docs updated to match. No features were removed or newly gated; only the 50-subscriber limit was ever enforced.

= 1.7.1 =
* Security: hardening from an OWASP 2025 code review. Subscriber CSV exports now neutralise spreadsheet formula injection (= + - @). Confirm, unsubscribe and the personal-data view act only on POST, so link-prefetchers/email scanners can't auto-trigger them (mailbox one-click unsubscribe still works). Opt-in pages send Referrer-Policy: no-referrer and the data view is no longer linked directly from emails. The bounce webhook now prefers an X-SEMNEWS-Secret header / HTTP Basic Auth over a URL secret, and failed webhook auth + signup rate-limit trips fire a semnews_security_event action.

= 1.7.0 =
* New: the bounce/complaint webhook now auto-detects SendGrid, Amazon SES (via SNS, with auto-confirm), Mailgun and Postmark and maps their events automatically — hard bounces and spam complaints flow onto your suppression list with no custom code. The Settings screen gives a ready-to-paste URL (secret included) and per-provider setup notes, plus guidance on using a free SMTP plugin for sending.

= 1.6.0 =
* New: multiple senders. Create several “From” identities (label, From name/email, reply-to) under Newsletters → Senders and choose one per newsletter. Your Settings From stays the site default for system emails. Warns when a sender's domain isn't aligned with your site (deliverability).

= 1.5.0 =
* New: sell Pro with Stripe via the bundled SEN License Server plugin (annual subscriptions). Stripe webhooks issue domain-locked, RSA-signed license keys; the plugin verifies them locally. Configure with SEMNEWS_LICENSE_PROVIDER / SEMNEWS_LICENSE_API_URL / SEMNEWS_LICENSE_PUBLIC_KEY — see STRIPE-SETUP.md.
* The license layer is now provider-agnostic (Lemon Squeezy, your own Stripe-backed server, or a custom signed-token server) with clearer activation error messages and a server-aware "Deactivate this site".

= 1.4.0 =
* New: Pro licensing via Lemon Squeezy (a Merchant of Record that handles checkout and global sales tax/VAT). Customers activate with their license key; activations are domain-locked and capped, re-validated daily, and freed by a new "Deactivate this site" button.
* Vendors can lock licenses to their own store/product with SEMNEWS_LS_STORE_ID / SEMNEWS_LS_PRODUCT_IDS. A self-hosted signed-token provider remains available via the semnews_license_provider filter.

= 1.3.0 =
* New: form placement options under Newsletters → Display — show the signup form automatically after/within post content, as a dismissible popup (time/scroll/exit-intent triggers), a slide-in box, or a sticky top/bottom bar.
* All placements are honest by default: dismissible and frequency-capped (remembered per visitor), never shown to people who already subscribed, accessible (ESC + focus trap on the popup), and they reuse the same double opt-in. Everything ships OFF.

= 1.2.1 =
* Fix: the "Build from posts" panel now remembers the chosen template, post count and categories after saving a draft (it previously reset to defaults on reload).
* Fix: the plugin header version now matches the release (was still showing 1.0.0).

= 1.2.0 =
* New: Deliverability health panel — live SPF/DKIM/DMARC checks, copy-paste DNS records, and a "send myself a deliverability test".
* New: Honest subject-line linter in the composer — advisory warnings (fake Re:/Fwd:, ALL-CAPS, image-only, missing preheader). Never blocks sending.
* New: 3-step setup wizard shown on activation.
* New: Self-service transparency — subscribers can view and download their own data and consent history via their token link (also linked in every email footer).
* New: Bounce & spam-complaint auto-suppression via an authenticated REST webhook, plus a "mark as bounced" bulk action and wp_mail failure capture.
* New: PECR/ePrivacy soft opt-in lawful basis for existing-customer lists, with an attestation and consent-log record.
* New: WP-CLI commands (semnews subscriber export/list/add, semnews queue run, semnews digest run, semnews bounce, semnews complaint, semnews license, semnews deliverability).
* New: Full translation template (.pot) and RTL stylesheet/email support.

= 1.1.0 =
* New: automated post digests — daily, weekly or monthly, by post count and category, on a schedule you choose, with an "empty digests are not sent" default.
* New: multiple newsletter templates (Simple, Cards, Magazine, Plain text) plus a Custom HTML template with a {posts} merge-tag loop.
* New: "Build from posts" in the newsletter composer.
* Security: Pro licensing is now a cryptographically signed, domain-locked token (RSA-verified locally) with daily re-validation — it can't be enabled by editing options or shared across sites.
* Hardening: directory-listing "silence" files added throughout; documented security model in SECURITY.md.

= 1.0.0 =
* Initial release: double opt-in, consent logging, one-click unsubscribe, suppression list, GDPR export/erasure, campaign queue sender, free 50-subscriber tier with Pro gating.

== Upgrade Notice ==

= 2.1.0 =
Adds the extension hooks used by Pro 1.1.0's lists & tags segmentation. No behaviour changes on their own.

= 2.0.0 =
Free is now unlimited — the subscriber cap and licensing code are gone. Scheduled sending, overlays, sender profiles and the archive now live in the Pro add-on. Install it before upgrading if you use those.

= 1.8.4 =
Documentation and admin-notice polish for WordPress.org guideline compliance; no functional changes to sending.

= 1.8.3 =
The newsletter composer now warns before unsaved edits are lost or a test/send goes out with stale content.

= 1.8.2 =
Preheader (inbox preview text) now renders reliably across mail clients, and automated digests get an honest preheader from their post titles.

= 1.8.1 =
Compliance checklist now explains which country's law each check comes from, with an honest guidance-not-legal-advice disclaimer.

= 1.8.0 =
Major reliability and admin release: crash-safe sending, scheduling and pause/resume, digest catch-up, streamed exports, subscriber detail view and more. Database changes are additive and automatic.

= 1.7.3 =
Adds a Pro price (monthly, billed annually) to the Upgrade screen; filterable to match your store.

= 1.7.2 =
Free now includes every feature; Pro only lifts the 50-subscriber cap.

= 1.7.1 =
Security hardening from an OWASP 2025 review (CSV-injection-safe exports, POST-only state changes, safer webhook auth). Recommended for all users.

= 1.7.0 =
The bounce/complaint webhook now works out of the box with SendGrid, Amazon SES, Mailgun and Postmark.

= 1.6.0 =
Adds multiple sender identities you can choose per newsletter (Newsletters → Senders).

= 1.5.0 =
Adds the option to sell Pro through Stripe (annual subscriptions) using the bundled License Server plugin, alongside the existing Lemon Squeezy support.

= 1.4.0 =
Adds Pro licensing through Lemon Squeezy (handles tax/VAT), with domain-locked activations, daily re-validation and per-site deactivation.

= 1.3.0 =
Adds signup-form placements: after-post content, popup, slide-in and sticky bar — all dismissible, frequency-capped and off by default.

= 1.2.0 =
Adds a deliverability panel, subject-line linter, setup wizard, bounce/complaint suppression, PECR soft opt-in, subscriber self-service data view, WP-CLI commands and full translation/RTL support.

= 1.1.0 =
Adds automated post digests, multiple templates with custom HTML, and a hardened signed/domain-locked Pro license.

= 1.0.0 =
First release.
