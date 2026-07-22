<?php
/**
 * Subscriber data access and double opt-in lifecycle.
 *
 * @package QuintessentialNewsletters
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery, PluginCheck.Security.DirectDB.UnescapedDBParameter -- This class reads and writes the plugin's own custom tables. Every
// interpolated table name is built from $wpdb->prefix plus a fixed literal
// (never user input), all values go through $wpdb->prepare(), and the WP
// post/meta APIs and object cache do not apply to these tables: queue,
// consent and subscriber state must always read current.

/**
 * Subscriber repository.
 */
class SEMNEWS_Subscribers {

	const STATUS_PENDING      = 'pending';
	const STATUS_SUBSCRIBED   = 'subscribed';
	const STATUS_UNSUBSCRIBED = 'unsubscribed';
	const STATUS_BOUNCED      = 'bounced';

	/**
	 * Lawful basis for mailing someone.
	 *
	 * CONSENT     — GDPR Art. 6(1)(a): a freely given, specific opt-in.
	 * SOFT_OPTIN  — PECR reg. 22(3) / ePrivacy "soft opt-in": an existing customer
	 *               who bought a similar product, was offered a simple opt-out at
	 *               the point of sale, and is offered it in every message.
	 */
	const BASIS_CONSENT    = 'consent';
	const BASIS_SOFT_OPTIN = 'soft_opt_in';

	/**
	 * Valid lawful bases.
	 *
	 * @return array
	 */
	public static function bases() {
		return array(
			self::BASIS_CONSENT    => __( 'Consent (opt-in)', 'quintessential-newsletters' ),
			self::BASIS_SOFT_OPTIN => __( 'Soft opt-in (existing customers — PECR)', 'quintessential-newsletters' ),
		);
	}

	/**
	 * Valid statuses.
	 *
	 * @return array
	 */
	public static function statuses() {
		return array(
			self::STATUS_PENDING      => __( 'Pending', 'quintessential-newsletters' ),
			self::STATUS_SUBSCRIBED   => __( 'Subscribed', 'quintessential-newsletters' ),
			self::STATUS_UNSUBSCRIBED => __( 'Unsubscribed', 'quintessential-newsletters' ),
			self::STATUS_BOUNCED      => __( 'Bounced', 'quintessential-newsletters' ),
		);
	}

	/**
	 * Fetch a subscriber row by ID.
	 *
	 * @param int $id Subscriber ID.
	 * @return object|null
	 */
	public static function get( $id ) {
		global $wpdb;
		$table = SEMNEWS_Install::table( 'subscribers' );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", absint( $id ) ) );
	}

	/**
	 * Fetch a subscriber by email.
	 *
	 * @param string $email Email address.
	 * @return object|null
	 */
	public static function get_by_email( $email ) {
		global $wpdb;
		$table = SEMNEWS_Install::table( 'subscribers' );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE email = %s", sanitize_email( $email ) ) );
	}

	/**
	 * Fetch a subscriber by ID and verify the token in constant time.
	 *
	 * @param int    $id    Subscriber ID.
	 * @param string $token Token from the link.
	 * @return object|null Null if not found or token mismatch.
	 */
	public static function get_verified( $id, $token ) {
		$subscriber = self::get( $id );

		if ( ! $subscriber || '' === (string) $subscriber->token ) {
			return null;
		}

		if ( ! hash_equals( (string) $subscriber->token, (string) $token ) ) {
			return null;
		}

		return $subscriber;
	}

	/**
	 * Count subscribers in a given status.
	 *
	 * @param string $status Status key.
	 * @return int
	 */
	public static function count_by_status( $status ) {
		global $wpdb;
		$table = SEMNEWS_Install::table( 'subscribers' );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE status = %s", $status ) );
	}

	/**
	 * Counts for every status in one query.
	 *
	 * @return array status => count, plus 'total'.
	 */
	public static function count_all() {
		global $wpdb;
		$table = SEMNEWS_Install::table( 'subscribers' );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results( "SELECT status, COUNT(*) AS c FROM {$table} GROUP BY status", OBJECT_K );

		$counts = array(
			self::STATUS_PENDING      => 0,
			self::STATUS_SUBSCRIBED   => 0,
			self::STATUS_UNSUBSCRIBED => 0,
			self::STATUS_BOUNCED      => 0,
		);
		$total = 0;
		foreach ( $rows as $status => $row ) {
			$counts[ $status ] = (int) $row->c;
			$total            += (int) $row->c;
		}
		$counts['total'] = $total;

		return $counts;
	}

	/**
	 * High-level subscribe entry point used by forms.
	 *
	 * Handles the full double opt-in decision tree and the free-tier capacity
	 * check, and writes the consent log. It NEVER reveals whether an address
	 * was already subscribed (anti-enumeration): the caller shows the same
	 * "check your inbox" message regardless.
	 *
	 * @param array $args {
	 *     @type string $email        Required.
	 *     @type string $name         Optional.
	 *     @type string $consent_text The exact consent statement shown.
	 *     @type string $source       Where the signup came from.
	 *     @type bool   $double_optin Whether to require confirmation (defaults to setting).
	 * }
	 * @return array {
	 *     @type bool   $success
	 *     @type string $status   pending|subscribed
	 *     @type string $code     ok|invalid_email|at_capacity|error
	 *     @type int    $id
	 * }
	 */
	public static function subscribe( $args ) {
		$email = isset( $args['email'] ) ? sanitize_email( $args['email'] ) : '';

		if ( ! $email || ! is_email( $email ) ) {
			return array(
				'success' => false,
				'code'    => 'invalid_email',
				'status'  => '',
				'id'      => 0,
			);
		}

		/**
		 * Whether this signup's email domain is acceptable. Core checks that
		 * the domain can actually receive mail (MX, with the RFC 5321 A/AAAA
		 * fallback); add-ons can veto further — the Pro add-on rejects
		 * disposable-email providers here.
		 *
		 * @param bool   $allowed Whether the domain passes.
		 * @param string $email   The submitted address.
		 */
		if ( ! apply_filters( 'semnews_signup_domain_allowed', self::domain_accepts_mail( $email ), $email ) ) {
			return array(
				'success' => false,
				'code'    => 'invalid_email',
				'status'  => '',
				'id'      => 0,
			);
		}

		$name         = isset( $args['name'] ) ? sanitize_text_field( $args['name'] ) : '';
		$consent_text = isset( $args['consent_text'] ) ? wp_kses_post( $args['consent_text'] ) : '';
		$source       = isset( $args['source'] ) ? substr( sanitize_text_field( $args['source'] ), 0, 120 ) : '';
		$double_optin = isset( $args['double_optin'] ) ? (bool) $args['double_optin'] : (bool) semnews_get_option( 'double_optin', 1 );

		$ip = semnews_get_ip();
		$ua = semnews_get_user_agent();

		$existing = self::get_by_email( $email );

		// Already a confirmed subscriber: do nothing, report success silently.
		if ( $existing && self::STATUS_SUBSCRIBED === $existing->status ) {
			return array(
				'success' => true,
				'code'    => 'already_subscribed',
				'status'  => self::STATUS_SUBSCRIBED,
				'id'      => (int) $existing->id,
			);
		}

		$now             = current_time( 'mysql', true );
		$token           = semnews_generate_token();
		$status          = $double_optin ? self::STATUS_PENDING : self::STATUS_SUBSCRIBED;
		$consent_version = (string) semnews_get_option( 'consent_version', '' );

		if ( $existing ) {
			$id   = (int) $existing->id;
			$data = array(
				'name'            => $name ? $name : $existing->name,
				'status'          => $status,
				'token'           => $token,
				'consent_text'    => $consent_text,
				'consent_version' => $consent_version,
				'consent_basis'   => self::BASIS_CONSENT,
				'source'          => $source,
				'ip_signup'       => $ip,
				// Clear any prior unsubscribe so a re-subscriber's row is consistent.
				'unsubscribed_at' => null,
				'updated_at'      => $now,
			);
			if ( ! $double_optin ) {
				$data['confirmed_at'] = $now;
				$data['ip_confirmed'] = $ip;
			}
			self::update( $id, $data );
		} else {
			$id = self::insert(
				array(
					'email'           => $email,
					'name'            => $name,
					'status'          => $status,
					'token'           => $token,
					'consent_text'    => $consent_text,
					'consent_version' => $consent_version,
					'consent_basis'   => self::BASIS_CONSENT,
					'source'          => $source,
					'ip_signup'       => $ip,
					'created_at'      => $now,
					'updated_at'      => $now,
					'confirmed_at'    => $double_optin ? null : $now,
					'ip_confirmed'    => $double_optin ? null : $ip,
				)
			);
		}

		if ( ! $id ) {
			return array(
				'success' => false,
				'code'    => 'error',
				'status'  => '',
				'id'      => 0,
			);
		}

		SEMNEWS_Consent_Log::add(
			$id,
			$double_optin ? 'subscribe_request' : 'subscribe',
			array(
				'email'        => $email,
				'consent_text' => $consent_text,
				'source'       => $source,
				'ip'           => $ip,
				'user_agent'   => $ua,
			)
		);

		$subscriber = self::get( $id );

		if ( $double_optin ) {
			// One confirmation email per address per 15 minutes, however often
			// the form is resubmitted — combined with the per-IP form limit,
			// the signup endpoint cannot be used to bomb someone's inbox.
			$conf_key = 'semnews_conf_' . md5( strtolower( $email ) );
			if ( ! get_transient( $conf_key ) ) {
				set_transient( $conf_key, 1, 15 * MINUTE_IN_SECONDS );
				SEMNEWS_Mailer::send_confirmation( $subscriber );
			}
		} else {
			// Immediate (single opt-in) consent clears any prior suppression.
			SEMNEWS_Suppression::remove( $email );
			SEMNEWS_Mailer::send_welcome( $subscriber );
			do_action( 'semnews_subscriber_confirmed', $subscriber );
		}

		do_action( 'semnews_subscriber_created', $subscriber, $double_optin );

		return array(
			'success' => true,
			'code'    => 'ok',
			'status'  => $status,
			'id'      => $id,
		);
	}

	/**
	 * Whether an email's domain can actually receive mail: MX record, with
	 * the RFC 5321 fallback to A/AAAA. Verdicts are cached for a day per
	 * domain, and lookup failure fails OPEN — an unavailable resolver must
	 * never lock genuine subscribers out.
	 *
	 * @param string $email Email address.
	 * @return bool
	 */
	public static function domain_accepts_mail( $email ) {
		$at = strrchr( (string) $email, '@' );
		if ( false === $at || strlen( $at ) < 2 ) {
			return false;
		}
		$domain = strtolower( substr( $at, 1 ) );

		if ( ! function_exists( 'checkdnsrr' ) ) {
			return true;
		}

		$cache_key = 'semnews_mx_' . md5( $domain );
		$cached    = get_transient( $cache_key );
		if ( false !== $cached ) {
			return '1' === $cached;
		}

		$ok = checkdnsrr( $domain . '.', 'MX' ) || checkdnsrr( $domain . '.', 'A' ) || checkdnsrr( $domain . '.', 'AAAA' );

		set_transient( $cache_key, $ok ? '1' : '0', DAY_IN_SECONDS );

		return $ok;
	}

	/**
	 * Confirm a pending subscriber (the double opt-in click).
	 *
	 * @param object $subscriber Subscriber row (already token-verified).
	 * @return array { @type bool $success, @type string $code }
	 */
	public static function confirm( $subscriber ) {
		if ( self::STATUS_SUBSCRIBED === $subscriber->status ) {
			return array(
				'success' => true,
				'code'    => 'already_confirmed',
			);
		}

		$now = current_time( 'mysql', true );
		self::update(
			(int) $subscriber->id,
			array(
				'status'          => self::STATUS_SUBSCRIBED,
				'confirmed_at'    => $now,
				'ip_confirmed'    => semnews_get_ip(),
				'unsubscribed_at' => null,
				'updated_at'      => $now,
			)
		);

		// A genuine, freshly-consented opt-in clears any prior suppression.
		SEMNEWS_Suppression::remove( $subscriber->email );

		SEMNEWS_Consent_Log::add(
			(int) $subscriber->id,
			'confirm',
			array(
				'email'        => $subscriber->email,
				'consent_text' => $subscriber->consent_text,
				'source'       => $subscriber->source,
				'ip'           => semnews_get_ip(),
				'user_agent'   => semnews_get_user_agent(),
			)
		);

		$fresh = self::get( (int) $subscriber->id );

		if ( semnews_get_option( 'send_welcome', 1 ) ) {
			SEMNEWS_Mailer::send_welcome( $fresh );
		}

		do_action( 'semnews_subscriber_confirmed', $fresh );

		return array(
			'success' => true,
			'code'    => 'confirmed',
		);
	}

	/**
	 * Unsubscribe a subscriber.
	 *
	 * @param object $subscriber Subscriber row.
	 * @return void
	 */
	public static function unsubscribe( $subscriber ) {
		if ( self::STATUS_UNSUBSCRIBED === $subscriber->status ) {
			return;
		}

		$now = current_time( 'mysql', true );
		self::update(
			(int) $subscriber->id,
			array(
				'status'          => self::STATUS_UNSUBSCRIBED,
				'unsubscribed_at' => $now,
				'updated_at'      => $now,
			)
		);

		// Suppress so a later CSV re-import can't silently re-add them.
		SEMNEWS_Suppression::add( $subscriber->email, SEMNEWS_Suppression::REASON_UNSUBSCRIBE );

		SEMNEWS_Consent_Log::add(
			(int) $subscriber->id,
			'unsubscribe',
			array(
				'email'      => $subscriber->email,
				'source'     => 'link',
				'ip'         => semnews_get_ip(),
				'user_agent' => semnews_get_user_agent(),
			)
		);

		do_action( 'semnews_subscriber_unsubscribed', $subscriber );
	}

	/**
	 * Record a bounce against an address and stop mailing it.
	 *
	 * Hard bounces (or soft bounces past the threshold) flip the subscriber to
	 * "bounced" and add a one-way suppression hash so the address is never mailed
	 * or re-imported again. Soft bounces below the threshold only increment a
	 * counter. The address does not need to be a known subscriber — suppressing an
	 * unknown bounced address is still correct.
	 *
	 * @param string $email Email address.
	 * @param string $type  hard|soft.
	 * @return string Outcome: suppressed|counted|ignored.
	 */
	public static function record_bounce( $email, $type = 'hard' ) {
		$email = sanitize_email( $email );
		if ( ! $email || ! is_email( $email ) ) {
			return 'ignored';
		}

		$type       = ( 'soft' === $type ) ? 'soft' : 'hard';
		$subscriber = self::get_by_email( $email );
		$limit      = (int) apply_filters( 'semnews_soft_bounce_limit', 3 );
		$now        = current_time( 'mysql', true );

		// Soft bounce under the limit: just count it, keep mailing for now.
		if ( 'soft' === $type && $subscriber ) {
			$count = (int) $subscriber->bounce_count + 1;
			if ( $count < $limit ) {
				self::update( (int) $subscriber->id, array( 'bounce_count' => $count, 'updated_at' => $now ) );
				SEMNEWS_Consent_Log::add( (int) $subscriber->id, 'soft_bounce', array( 'email' => $email, 'source' => 'bounce' ) );
				return 'counted';
			}
		}

		// Hard bounce, or a soft bounce that has reached the limit: suppress.
		if ( $subscriber ) {
			self::update(
				(int) $subscriber->id,
				array(
					'status'       => self::STATUS_BOUNCED,
					'bounce_count' => (int) $subscriber->bounce_count + 1,
					'updated_at'   => $now,
				)
			);
			SEMNEWS_Consent_Log::add( (int) $subscriber->id, 'bounce', array( 'email' => $email, 'source' => $type . '_bounce' ) );
			do_action( 'semnews_subscriber_bounced', $subscriber, $type );
		}

		SEMNEWS_Suppression::add( $email, SEMNEWS_Suppression::REASON_BOUNCE );

		return 'suppressed';
	}

	/**
	 * Record a spam complaint (feedback loop / FBL) and suppress the address.
	 *
	 * A complaint is the strongest possible "stop" signal: we treat the person as
	 * unsubscribed and suppress them so they are never mailed again.
	 *
	 * @param string $email Email address.
	 * @return string Outcome: suppressed|ignored.
	 */
	public static function record_complaint( $email ) {
		$email = sanitize_email( $email );
		if ( ! $email || ! is_email( $email ) ) {
			return 'ignored';
		}

		$subscriber = self::get_by_email( $email );
		$now        = current_time( 'mysql', true );

		if ( $subscriber ) {
			self::update(
				(int) $subscriber->id,
				array(
					'status'          => self::STATUS_UNSUBSCRIBED,
					'unsubscribed_at' => $now,
					'updated_at'      => $now,
				)
			);
			SEMNEWS_Consent_Log::add( (int) $subscriber->id, 'complaint', array( 'email' => $email, 'source' => 'fbl' ) );
			do_action( 'semnews_subscriber_complained', $subscriber );
		}

		SEMNEWS_Suppression::add( $email, SEMNEWS_Suppression::REASON_COMPLAINT );

		return 'suppressed';
	}

	/**
	 * Insert a raw subscriber row.
	 *
	 * @param array $data Column => value.
	 * @return int|false Insert ID.
	 */
	public static function insert( $data ) {
		global $wpdb;
		$result = $wpdb->insert( SEMNEWS_Install::table( 'subscribers' ), $data ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return $result ? (int) $wpdb->insert_id : false;
	}

	/**
	 * Update a subscriber row.
	 *
	 * @param int   $id   Subscriber ID.
	 * @param array $data Column => value.
	 * @return bool
	 */
	public static function update( $id, $data ) {
		global $wpdb;
		return false !== $wpdb->update( SEMNEWS_Install::table( 'subscribers' ), $data, array( 'id' => absint( $id ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}

	/**
	 * Permanently delete a subscriber and their consent log.
	 *
	 * @param int    $id              Subscriber ID.
	 * @param string $suppress_reason If set (e.g. erasure), record a one-way
	 *                                suppression hash before deleting the PII so
	 *                                the address can't be silently re-imported.
	 * @return void
	 */
	public static function delete( $id, $suppress_reason = '' ) {
		global $wpdb;
		$id = absint( $id );

		if ( $suppress_reason ) {
			$subscriber = self::get( $id );
			if ( $subscriber && $subscriber->email ) {
				SEMNEWS_Suppression::add( $subscriber->email, $suppress_reason );
			}
		}

		$wpdb->delete( SEMNEWS_Install::table( 'subscribers' ), array( 'id' => $id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->delete( SEMNEWS_Install::table( 'queue' ), array( 'subscriber_id' => $id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		SEMNEWS_Consent_Log::delete_for_subscriber( $id );

		/**
		 * Fires after a subscriber row (and their queue/consent rows) was
		 * deleted, so add-ons can clean their own relations (e.g. Pro
		 * list/tag memberships).
		 *
		 * @param int $id Deleted subscriber id.
		 */
		do_action( 'semnews_subscriber_deleted', $id );
	}

	/**
	 * Admin-side add (single opt-in, with capacity check). Used by the
	 * "Add subscriber" admin screen and CSV import.
	 *
	 * @param string $email  Email.
	 * @param string $name   Name.
	 * @param string $status Desired status (default subscribed).
	 * @param string $source Source label.
	 * @param string $basis  Lawful basis (consent|soft_opt_in).
	 * @return array { @type bool $success, @type string $code, @type int $id }
	 */
	public static function admin_add( $email, $name = '', $status = self::STATUS_SUBSCRIBED, $source = 'admin', $basis = self::BASIS_CONSENT ) {
		$email = sanitize_email( $email );
		if ( ! $email || ! is_email( $email ) ) {
			return array(
				'success' => false,
				'code'    => 'invalid_email',
				'id'      => 0,
			);
		}

		$basis = array_key_exists( $basis, self::bases() ) ? $basis : self::BASIS_CONSENT;

		// Never let an admin/import silently re-add someone who unsubscribed or
		// was erased. The person can always opt back in themselves via the form.
		if ( SEMNEWS_Suppression::is_suppressed( $email ) ) {
			return array(
				'success' => false,
				'code'    => 'suppressed',
				'id'      => 0,
			);
		}

		$existing = self::get_by_email( $email );
		$becomes_confirmed = ( self::STATUS_SUBSCRIBED === $status );

		$now = current_time( 'mysql', true );

		if ( $existing ) {
			$id = (int) $existing->id;
			self::update(
				$id,
				array(
					'name'            => $name ? sanitize_text_field( $name ) : $existing->name,
					'status'          => $status,
					'consent_basis'   => $basis,
					'confirmed_at'    => $becomes_confirmed ? ( $existing->confirmed_at ? $existing->confirmed_at : $now ) : $existing->confirmed_at,
					'unsubscribed_at' => ( self::STATUS_UNSUBSCRIBED === $status ) ? $now : null,
					'updated_at'      => $now,
				)
			);
		} else {
			$id = self::insert(
				array(
					'email'         => $email,
					'name'          => sanitize_text_field( $name ),
					'status'        => $status,
					'token'         => semnews_generate_token(),
					'consent_text'  => '',
					'consent_basis' => $basis,
					'source'        => $source,
					'ip_signup'     => '',
					'created_at'    => $now,
					'updated_at'    => $now,
					'confirmed_at'  => $becomes_confirmed ? $now : null,
				)
			);
		}

		if ( ! $id ) {
			return array(
				'success' => false,
				'code'    => 'error',
				'id'      => 0,
			);
		}

		SEMNEWS_Consent_Log::add(
			$id,
			self::BASIS_SOFT_OPTIN === $basis ? 'soft_opt_in' : 'admin_add',
			array(
				'email'  => $email,
				'source' => $source . ' (' . $basis . ')',
				'ip'     => semnews_get_ip(),
			)
		);

		return array(
			'success' => true,
			'code'    => 'ok',
			'id'      => $id,
		);
	}

	/**
	 * Query subscribers for the admin list table.
	 *
	 * @param array $args { status, search, orderby, order, per_page, paged }.
	 * @return array { @type array $items, @type int $total }
	 */
	public static function query( $args = array() ) {
		global $wpdb;
		$table = SEMNEWS_Install::table( 'subscribers' );

		$defaults = array(
			'status'   => '',
			'search'   => '',
			'orderby'  => 'created_at',
			'order'    => 'DESC',
			'per_page' => 20,
			'paged'    => 1,
		);
		$args = wp_parse_args( $args, $defaults );

		$where  = 'WHERE 1=1';
		$params = array();

		if ( $args['status'] && array_key_exists( $args['status'], self::statuses() ) ) {
			$where   .= ' AND status = %s';
			$params[] = $args['status'];
		}

		if ( '' !== $args['search'] ) {
			$like     = '%' . $wpdb->esc_like( $args['search'] ) . '%';
			$where   .= ' AND ( email LIKE %s OR name LIKE %s )';
			$params[] = $like;
			$params[] = $like;
		}

		// Whitelist orderby / order to avoid injection.
		$allowed_orderby = array( 'id', 'email', 'name', 'status', 'created_at', 'confirmed_at' );
		$orderby         = in_array( $args['orderby'], $allowed_orderby, true ) ? $args['orderby'] : 'created_at';
		$order           = ( 'ASC' === strtoupper( $args['order'] ) ) ? 'ASC' : 'DESC';

		$per_page = max( 1, (int) $args['per_page'] );
		$paged    = max( 1, (int) $args['paged'] );
		$offset   = ( $paged - 1 ) * $per_page;

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared -- $count_sql/$sql are assembled above from fixed clauses containing only %s/%d placeholders; user values are passed exclusively through $wpdb->prepare(), and the no-params branch contains no placeholders and no user input.

		// Total.
		$count_sql = "SELECT COUNT(*) FROM {$table} {$where}";
		$total     = (int) ( $params ? $wpdb->get_var( $wpdb->prepare( $count_sql, $params ) ) : $wpdb->get_var( $count_sql ) );

		// Page of results ($orderby/$order are whitelisted above).
		$sql          = "SELECT * FROM {$table} {$where} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d";
		$query_params = array_merge( $params, array( $per_page, $offset ) );
		$items        = $wpdb->get_results( $wpdb->prepare( $sql, $query_params ) );

		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared

		return array(
			'items' => $items,
			'total' => $total,
		);
	}

}
