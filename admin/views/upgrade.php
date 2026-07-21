<?php
/**
 * Upgrade-to-Pro screen. Registered only while the Pro add-on is inactive.
 *
 * Benefit-led but honest: it sells what Pro actually does today, labels
 * roadmap items as roadmap, and never implies the free plugin is limited.
 *
 * @package SimpleEmailNewsletters
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$semnews_features = array(
	array(
		'title' => __( 'Greet every subscriber automatically', 'quintessential-newsletters' ),
		'desc'  => __( 'The welcome series sends up to three emails to each new subscriber — a hello on day one, your best content on day three, an offer on day seven. The most-opened emails you will ever send, written once and working forever.', 'quintessential-newsletters' ),
	),
	array(
		'title' => __( 'Hit the inbox at the perfect moment', 'quintessential-newsletters' ),
		'desc'  => __( 'Scheduled sending: write the newsletter on Friday afternoon, land it Tuesday at 9am when your readers actually open email. Queue a whole month of sends in one sitting, then get on with your week.', 'quintessential-newsletters' ),
	),
	array(
		'title' => __( 'Send to exactly the right people', 'quintessential-newsletters' ),
		'desc'  => __( 'Lists & tags segmentation: a security digest to customers, product news to prospects, VIP offers to your best readers. Relevant email gets opened — irrelevant email gets unsubscribed. Every form can feed its own list automatically.', 'quintessential-newsletters' ),
	),
	array(
		'title' => __( 'Grow your list on autopilot', 'quintessential-newsletters' ),
		'desc'  => __( 'Popup, slide-in and sticky-bar signup placements turn the readers you already have into subscribers — the single most effective thing you can add to a signup flow. Honest frequency caps built in, and never shown to people who already joined.', 'quintessential-newsletters' ),
	),
	array(
		'title' => __( 'Run as many digests as you publish', 'quintessential-newsletters' ),
		'desc'  => __( 'Multiple automated digests, each with its own newsletter, schedule and categories — a weekly news round-up, a monthly blog digest and a product-updates mailer, all running side by side on autopilot.', 'quintessential-newsletters' ),
	),
	array(
		'title' => __( 'A template for every kind of send', 'quintessential-newsletters' ),
		'desc'  => __( 'The template gallery: image-led Cards, editorial Magazine, deliverability-first Plain text, the big-launch Announcement, the link-round-up Compact digest, and the write-your-own Custom HTML template — every design topped with your own brand logo, and one-click previews built from your real posts so you choose with your eyes, not the labels.', 'quintessential-newsletters' ),
	),
	array(
		'title' => __( 'Know who’s reading — honestly', 'quintessential-newsletters' ),
		'desc'  => __( 'Engagement insights without the creepy part: click-based (no hidden open pixels), consent-aware, first-party and auto-expiring after 90 days. Then send only to engaged readers — smaller sends, better inboxing, honest list hygiene.', 'quintessential-newsletters' ),
	),
	array(
		'title' => __( 'A list that stays clean by itself', 'quintessential-newsletters' ),
		'desc'  => __( 'Signup protection, finished: the free plugin already blocks bots and dead domains — Pro adds a curated blocklist of disposable-email providers, your own blocked-domains list, and one polite automatic reminder to genuine signups who never clicked confirm. Fewer fake addresses means better deliverability and stats you can trust.', 'quintessential-newsletters' ),
	),
	array(
		'title' => __( 'Look like a team, not a no-reply', 'quintessential-newsletters' ),
		'desc'  => __( 'Multiple sender identities: send the digest as “The Editor”, support notes as “Sarah from Support”, offers as your brand. Named senders build recognition — and recognition is what keeps you out of the spam folder.', 'quintessential-newsletters' ),
	),
	array(
		'title' => __( 'Turn buyers into readers', 'quintessential-newsletters' ),
		'desc'  => __( 'WooCommerce checkout opt-in: a clean, never pre-ticked newsletter checkbox at checkout. Buyers who say yes are tagged “Customer” automatically, so your best audience — people who already paid you — is one segment away.', 'quintessential-newsletters' ),
	),
	array(
		'title' => __( 'Let your back catalogue sell for you', 'quintessential-newsletters' ),
		'desc'  => __( 'The public newsletter archive shows visitors exactly what they will get before they subscribe — real issues, on your site, indexed by search engines. Proof beats promises on any signup form.', 'quintessential-newsletters' ),
	),
	array(
		'title' => __( 'A real person when it matters', 'quintessential-newsletters' ),
		'desc'  => __( 'Priority support from the people who wrote the code, plus every Pro update the moment it ships. When a send matters, you are not waiting in a forum queue.', 'quintessential-newsletters' ),
	),
);
?>
<div class="wrap semnews-wrap">
	<h1><?php esc_html_e( 'Upgrade to Pro', 'quintessential-newsletters' ); ?></h1>

	<p style="max-width:48em;font-size:14px;">
		<?php esc_html_e( 'The free plugin sends honest email, unlimited and uncapped — that never changes. Pro is for the moment your list starts to matter: when you want the right message reaching the right segment at the right time, and a list that grows while you sleep.', 'quintessential-newsletters' ); ?>
	</p>

	<p style="margin:0 0 20px;">
		<a class="button button-primary button-hero" href="<?php echo esc_url( 'https://quintessentialsoftware.co.uk/' ); ?>" target="_blank" rel="noopener">
			<?php esc_html_e( 'Buy Pro — $5/month', 'quintessential-newsletters' ); ?>
		</a>
		<span class="description" style="margin-left:10px;"><?php esc_html_e( 'Billed annually ($60 per year, per site). Full details below.', 'quintessential-newsletters' ); ?></span>
	</p>

	<div class="semnews-columns">
		<?php foreach ( $semnews_features as $semnews_feature ) : ?>
			<div class="semnews-panel">
				<h2><?php echo esc_html( $semnews_feature['title'] ); ?></h2>
				<p><?php echo esc_html( $semnews_feature['desc'] ); ?></p>
			</div>
		<?php endforeach; ?>
	</div>

	<div class="semnews-panel" style="max-width:48em;">
		<h2><?php esc_html_e( 'One license, everything we ship next', 'quintessential-newsletters' ); ?></h2>
		<p>
			<?php esc_html_e( 'Pro keeps growing, and your license includes every new feature at no extra cost — the welcome series, multiple digests, the WooCommerce opt-in, two new gallery templates, brand logos with one-click previews, honest engagement insights, disposable-email blocking and confirmation reminders all landed in the last two releases alone.', 'quintessential-newsletters' ); ?>
		</p>
	</div>

	<div class="semnews-panel" style="max-width:48em;">
		<h2><?php esc_html_e( 'Get Simple Email Newsletters Pro', 'quintessential-newsletters' ); ?></h2>
		<p class="semnews-price">
			<span class="semnews-price-amount"><?php esc_html_e( '$5', 'quintessential-newsletters' ); ?></span>
			<span class="semnews-price-period"><?php esc_html_e( '/ month', 'quintessential-newsletters' ); ?></span>
		</p>
		<p class="semnews-price-note"><?php esc_html_e( 'Billed annually ($60 per year, per site) — less than a single coffee a month, including every update, every roadmap feature and priority support.', 'quintessential-newsletters' ); ?></p>
		<p><?php esc_html_e( 'Install it alongside this plugin and the greyed-out controls become real, with your existing subscribers, newsletters and settings picked up as-is. One extra sale, sponsor or client from a better-targeted send and it has paid for the year.', 'quintessential-newsletters' ); ?></p>
		<p>
			<a class="button button-primary button-hero" href="<?php echo esc_url( 'https://quintessentialsoftware.co.uk/' ); ?>" target="_blank" rel="noopener">
				<?php esc_html_e( 'Buy Pro', 'quintessential-newsletters' ); ?>
			</a>
		</p>
	</div>
</div>
