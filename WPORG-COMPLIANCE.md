# WordPress.org Detailed Plugin Guidelines — compliance audit

Audited against the [Detailed Plugin Guidelines](https://developer.wordpress.org/plugins/wordpress-org/detailed-plugin-guidelines/)
(all 18 guidelines, source text from the official
[wporg-plugin-guidelines](https://github.com/WordPress/wporg-plugin-guidelines) repository), July 2026.

**Scope note:** these guidelines govern plugins **hosted in the WordPress.org
directory**. Distributing the plugin from your own site (the current model,
with the Stripe/Lemon Squeezy licensing and self-hosted updates) is not bound
by them. The audit below covers both readings: what is compliant everywhere,
and what would block a WordPress.org directory listing specifically.

## Compliant

| # | Guideline | Status |
|---|-----------|--------|
| 1 | GPL-compatible | ✅ GPL-2.0-or-later throughout; no third-party libraries, images or assets bundled — every file is original code under the same license. |
| 2 | Developer responsible for all files | ✅ All files authored in-repo; no unverifiable third-party code. |
| 4 | Human-readable code | ✅ No minification/obfuscation anywhere; JS and CSS ship as source. The free core's source + tests + build tools are public via the core mirror repository (linked from the readme "Development" section); the commercial add-on is separate private code that never enters the directory zip. The `.pot` generator ships in `bin/makepot.php`. |
| 7 | No tracking without consent | ✅ The free plugin has zero tracking, analytics, or phone-home. (The separate Pro add-on offers optional click measurement — off by default, owner-attested lawful basis, subscriber opt-in/opt-out in the preference centre, click-based only, first-party, 90-day expiry, included in export/erasure — none of which ships in the directory zip.) All three possible outbound connections are owner-initiated opt-ins (license key entry, `SEMNEWS_UPDATE_URL` constant, SES webhook setup) and are now documented in the readme "External services" section. Subscriber IPs are logged only to the site's own database as GDPR Art. 7 consent proof. |
| 9 | Nothing illegal/dishonest | ✅ No fake reviews/SEO/traffic promises. Readme "compliant/lawfully" phrasing softened and an explicit "not legal advice" note added (the dashboard checklist already carried one) so nothing implies the plugin *guarantees* legal compliance. |
| 10 | No public credits without opt-in | ✅ No "Powered by", no links, no branding anywhere on the front end or in sent emails. |
| 11 | Don't hijack wp-admin | ✅ All notices contextual to the plugin's own screens (the at-capacity notice was site-wide; scoped in 1.8.4). Warnings self-dismiss when resolved and say how to resolve. Upsell surfaces are confined to the plugin's own screens: the Upgrade page, a dashboard panel, and greyed-out previews of the add-on's controls in the exact spots the add-on renders them — all removed automatically once Pro is active, never site-wide, never blocking. |
| 12 | No readme spam | ✅ 5 tags, no competitor terms, no affiliate links, human-written readme. |
| 13 | Use WordPress' bundled libraries | ✅ Mail via `wp_mail()`/PHPMailer, no bundled jQuery/PHPMailer/etc. (The needless jQuery dependency on the admin script was dropped in 1.8.4 — it is vanilla JS.) |
| 14 | No frequent SVN churn | ✅ n/a (git); releases are versioned and batched. |
| 15 | Version incremented every release | ✅ Header, `SEMNEWS_VERSION`, readme stable tag and changelog move together. |
| 16 | Complete plugin at submission | ✅ Fully functional zip built by `git archive` (dev/vendor files export-ignored). |
| 17 | Respect trademarks | ✅ Original generic name; slug does not begin with any brand. SendGrid/Mailgun/Postmark/SES/Stripe/Lemon Squeezy are referenced only descriptively for interoperability. |
| 3, 18 | Directory operations | n/a until hosted on WordPress.org (3: keep the directory copy current; 18: their moderation rights). |

## Former blockers — RESOLVED in 2.0.0 (Option C, the structure wp.org recommends)

The 2.0.0 restructure split the product into an uncapped free core and a
separate paid Pro add-on (`addons/quintessential-newsletters-pro/`, excluded from the core zip):

1. **Guideline 5 (trialware): resolved.** The 50-subscriber quota is gone
   entirely — the free core has no cap, trial, or locked functionality of any
   kind. Pro features are genuinely separate **code** in the add-on, not
   unlocks of shipped code. This includes the template gallery (2.2.0): the
   gallery renderers ship only in the add-on and an unregistered template id
   degrades gracefully to the free Simple layout. The Custom HTML template is
   registered by the add-on; its underlying {posts} tag engine stays in core
   because free features (template previews and the composer's "Build from
   posts") run on it (shared functionality, not locked code). Automated
   sending itself — the digest engine, its hourly cron tick and the Automated
   screen — ships only in the add-on as of 2.5.0: the free plugin sends only
   when the site owner presses send.
2. **Guideline 6 (serviceware): resolved.** All licensing code
   (`SEMNEWS_License`, `SEMNEWS_LemonSqueezy`) moved to the Pro add-on, which is sold
   and distributed off-directory. The core makes no license-validation calls
   — its only possible outbound request is the documented, owner-configured
   Amazon SNS webhook handshake.
3. **Guideline 8 (external updates): resolved.** The self-hosted update
   checker (`SEMNEWS_Updates`) moved to the Pro add-on and updates only the
   add-on itself (`quintessential-newsletters-pro`); the core carries no update checker and would
   update through wordpress.org once listed.

Also relevant: guideline 9's "implying users must pay to unlock included
features" — the core now truthfully includes everything it ships, the Pro
license itself gates only updates/support entitlement (never features), and
the Pro upsell surface in core is limited to a readme section, the plugin's
own Upgrade page/dashboard panel, and clearly-labelled greyed-out previews on
its own screens (rendered through the same hooks the add-on uses, and hidden
when it is active). The previews never claim shipped functionality is locked —
the add-on is separate code.

**Submission readiness:** build the directory zip with `bin/build-zips.sh`
(`dist/quintessential-newsletters.zip`); `addons/`, `license-server/`, `tests/`,
`bin/` and the dev docs are export-ignored. Remaining pre-submission steps are
non-code: screenshots/banner assets for the listing, and re-checking
"Tested up to" against the current WordPress release.
