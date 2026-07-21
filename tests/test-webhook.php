<?php
/**
 * Bounce/complaint webhook: provider auto-detection + authentication.
 *
 * @package QuintessentialNewsletters\Tests
 */

// phpcs:ignoreFile

require_once SEMNEWS_PLUGIN_DIR . 'includes/class-semnews-webhook.php';

semnews_test_reset();

class SEMNEWS_Test_Request {
	private $body, $headers, $params;
	public function __construct( $body = '', $headers = array(), $params = array() ) {
		$this->body    = $body;
		$this->headers = array_change_key_case( $headers, CASE_LOWER );
		$this->params  = $params;
	}
	public function get_body() { return $this->body; }
	public function get_header( $k ) { $k = strtolower( $k ); return isset( $this->headers[ $k ] ) ? $this->headers[ $k ] : null; }
	public function get_param( $k ) { return isset( $this->params[ $k ] ) ? $this->params[ $k ] : null; }
	public function get_params() { return $this->params; }
	public function get_json_params() { $d = json_decode( $this->body, true ); return is_array( $d ) ? $d : null; }
}

$w   = new SEMNEWS_Webhook();
$ref = new ReflectionMethod( 'SEMNEWS_Webhook', 'parse_request' );
$ref->setAccessible( true );
$ev = function ( $r ) {
	return array_map(
		function ( $e ) {
			return $e['event'] . ':' . $e['email'] . ( isset( $e['type'] ) ? ':' . $e['type'] : '' );
		},
		$r
	);
};

// --- SendGrid ---
$sg = json_encode( array(
	array( 'email' => 'a@x.com', 'event' => 'bounce', 'type' => 'bounce' ),
	array( 'email' => 'b@x.com', 'event' => 'spamreport' ),
	array( 'email' => 'c@x.com', 'event' => 'delivered' ),
	array( 'email' => 'd@x.com', 'event' => 'bounce', 'type' => 'blocked' ),
) );
t( 'webhook: sendgrid maps', $ev( $ref->invoke( $w, new SEMNEWS_Test_Request( $sg ) ) ) === array( 'bounce:a@x.com:hard', 'complaint:b@x.com', 'bounce:d@x.com:soft' ) );

// --- Mailgun (modern + legacy) ---
t( 'webhook: mailgun permanent->hard', $ev( $ref->invoke( $w, new SEMNEWS_Test_Request( json_encode( array( 'event-data' => array( 'event' => 'failed', 'severity' => 'permanent', 'recipient' => 'm@x.com' ) ) ) ) ) ) === array( 'bounce:m@x.com:hard' ) );
t( 'webhook: mailgun temporary->soft', $ev( $ref->invoke( $w, new SEMNEWS_Test_Request( json_encode( array( 'event-data' => array( 'event' => 'failed', 'severity' => 'temporary', 'recipient' => 'm2@x.com' ) ) ) ) ) ) === array( 'bounce:m2@x.com:soft' ) );
t( 'webhook: mailgun complained', $ev( $ref->invoke( $w, new SEMNEWS_Test_Request( json_encode( array( 'event-data' => array( 'event' => 'complained', 'recipient' => 'mc@x.com' ) ) ) ) ) ) === array( 'complaint:mc@x.com' ) );
t( 'webhook: mailgun legacy form', $ev( $ref->invoke( $w, new SEMNEWS_Test_Request( '', array(), array( 'event' => 'failed', 'severity' => 'permanent', 'recipient' => 'ml@x.com' ) ) ) ) === array( 'bounce:ml@x.com:hard' ) );

// --- Postmark ---
t( 'webhook: postmark hard bounce', $ev( $ref->invoke( $w, new SEMNEWS_Test_Request( json_encode( array( 'RecordType' => 'Bounce', 'Type' => 'HardBounce', 'Email' => 'p@x.com' ) ) ) ) ) === array( 'bounce:p@x.com:hard' ) );
t( 'webhook: postmark soft bounce', $ev( $ref->invoke( $w, new SEMNEWS_Test_Request( json_encode( array( 'RecordType' => 'Bounce', 'Type' => 'SoftBounce', 'Email' => 'p2@x.com' ) ) ) ) ) === array( 'bounce:p2@x.com:soft' ) );
t( 'webhook: postmark spam complaint', $ev( $ref->invoke( $w, new SEMNEWS_Test_Request( json_encode( array( 'RecordType' => 'SpamComplaint', 'Email' => 'ps@x.com' ) ) ) ) ) === array( 'complaint:ps@x.com' ) );

// --- Amazon SES via SNS ---
$msg = json_encode( array( 'notificationType' => 'Bounce', 'bounce' => array( 'bounceType' => 'Permanent', 'bouncedRecipients' => array( array( 'emailAddress' => 's1@x.com' ), array( 'emailAddress' => 's2@x.com' ) ) ) ) );
$sns = json_encode( array( 'Type' => 'Notification', 'TopicArn' => 'arn', 'Message' => $msg ) );
t( 'webhook: ses multi-recipient bounce', $ev( $ref->invoke( $w, new SEMNEWS_Test_Request( $sns ) ) ) === array( 'bounce:s1@x.com:hard', 'bounce:s2@x.com:hard' ) );

$msgc = json_encode( array( 'notificationType' => 'Complaint', 'complaint' => array( 'complainedRecipients' => array( array( 'emailAddress' => 'sc@x.com' ) ) ) ) );
t( 'webhook: ses complaint', $ev( $ref->invoke( $w, new SEMNEWS_Test_Request( json_encode( array( 'Type' => 'Notification', 'TopicArn' => 'arn', 'Message' => $msgc ) ) ) ) ) === array( 'complaint:sc@x.com' ) );

$msgt = json_encode( array( 'notificationType' => 'Bounce', 'bounce' => array( 'bounceType' => 'Transient', 'bouncedRecipients' => array( array( 'emailAddress' => 'st@x.com' ) ) ) ) );
t( 'webhook: ses transient->soft', $ev( $ref->invoke( $w, new SEMNEWS_Test_Request( json_encode( array( 'Type' => 'Notification', 'TopicArn' => 'arn', 'Message' => $msgt ) ) ) ) ) === array( 'bounce:st@x.com:soft' ) );

$conf = json_encode( array( 'Type' => 'SubscriptionConfirmation', 'TopicArn' => 'arn', 'SubscribeURL' => 'https://sns.eu-west-1.amazonaws.com/?Action=Confirm' ) );
t( 'webhook: sns subscription confirmed', $ref->invoke( $w, new SEMNEWS_Test_Request( $conf ) ) === 'subscription_confirmed' );
t( 'webhook: sns confirm fetched AWS url', 1 === count( $GLOBALS['semnews_test']['http_gets'] ) );

$GLOBALS['semnews_test']['http_gets'] = array();
$bad = json_encode( array( 'Type' => 'SubscriptionConfirmation', 'TopicArn' => 'arn', 'SubscribeURL' => 'https://evil.example.com/x' ) );
$ref->invoke( $w, new SEMNEWS_Test_Request( $bad ) );
t( 'webhook: SSRF guard rejects non-AWS confirm url', 0 === count( $GLOBALS['semnews_test']['http_gets'] ) );

// --- Generic format ---
t( 'webhook: generic format', $ev( $ref->invoke( $w, new SEMNEWS_Test_Request( json_encode( array( 'event' => 'bounce', 'email' => 'g@x.com' ) ) ) ) ) === array( 'bounce:g@x.com:hard' ) );

// --- Authentication ---
semnews_test_reset();
$GLOBALS['semnews_test']['options']['semnews_webhook_secret'] = 'THESECRET40chars';
$events_fired = function () {
	foreach ( $GLOBALS['semnews_test']['actions'] as $a ) {
		if ( 'semnews_security_event' === $a['name'] ) {
			return true;
		}
	}
	return false;
};

t( 'auth: X-SEMNEWS-Secret header ok', $w->authorize( new SEMNEWS_Test_Request( '', array( 'x-semnews-secret' => 'THESECRET40chars' ) ) ) === true );
t( 'auth: basic user:secret ok', $w->authorize( new SEMNEWS_Test_Request( '', array( 'authorization' => 'Basic ' . base64_encode( 'user:THESECRET40chars' ) ) ) ) === true );
t( 'auth: basic secret-only ok', $w->authorize( new SEMNEWS_Test_Request( '', array( 'authorization' => 'Basic ' . base64_encode( 'THESECRET40chars' ) ) ) ) === true );
t( 'auth: ?secret ok by default', $w->authorize( new SEMNEWS_Test_Request( '', array(), array( 'secret' => 'THESECRET40chars' ) ) ) === true );

$GLOBALS['semnews_test']['filters']['semnews_webhook_allow_url_secret'] = false;
$GLOBALS['semnews_test']['actions'] = array();
t( 'auth: ?secret rejected when filter off (+event)', $w->authorize( new SEMNEWS_Test_Request( '', array(), array( 'secret' => 'THESECRET40chars' ) ) ) === false && $events_fired() );
unset( $GLOBALS['semnews_test']['filters']['semnews_webhook_allow_url_secret'] );

$GLOBALS['semnews_test']['actions'] = array();
t( 'auth: wrong secret rejected (+event)', $w->authorize( new SEMNEWS_Test_Request( '', array( 'x-semnews-secret' => 'nope' ) ) ) === false && $events_fired() );
t( 'auth: no secret rejected', $w->authorize( new SEMNEWS_Test_Request() ) === false );
