<?php
/**
 * Per-recipient send queue.
 *
 * A campaign is "exploded" into one queue row per confirmed subscriber so that
 * sending can happen in small, resumable batches via WP-Cron. Rows are CLAIMED
 * (status → processing) before any mail is attempted, so a PHP crash mid-batch
 * can never re-send emails that already went out: a reaper simply returns
 * stale claims to pending. Failed attempts back off before retrying and stop
 * for good after MAX_ATTEMPTS.
 *
 * @package SimpleEmailNewsletters
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
 * Queue data access.
 */
class SEMNEWS_Queue {

	const STATUS_PENDING    = 'pending';
	const STATUS_PROCESSING = 'processing';
	const STATUS_SENT       = 'sent';
	const STATUS_FAILED     = 'failed';
	const MAX_ATTEMPTS      = 3;

	/**
	 * Claims older than this many seconds are considered abandoned (the PHP
	 * process died) and are returned to pending by the reaper.
	 */
	const CLAIM_TIMEOUT = 10 * MINUTE_IN_SECONDS;

	/**
	 * Build the queue for a campaign from the current confirmed subscribers.
	 *
	 * @param int $campaign_id Campaign ID.
	 * @return int Number of recipients enqueued.
	 */
	public static function build_for_campaign( $campaign_id ) {
		global $wpdb;
		$campaign_id = absint( $campaign_id );
		$table       = SEMNEWS_Install::table( 'queue' );
		$subscribers = SEMNEWS_Install::table( 'subscribers' );
		$now         = current_time( 'mysql', true );

		// Clear any prior queue for this campaign (e.g. re-send).
		$wpdb->delete( $table, array( 'campaign_id' => $campaign_id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

		/**
		 * Extra SQL restricting which confirmed subscribers receive this
		 * campaign — used by the Pro add-on's lists/tags segmentation. Must be
		 * a self-contained AND clause against the subscribers table (e.g.
		 * "AND id IN ( SELECT … )") built ONLY from trusted, already-sanitised
		 * values; it is interpolated into the query as-is.
		 *
		 * @param string $clause      Extra SQL ('' = everyone).
		 * @param int    $campaign_id Campaign id.
		 */
		$segment = (string) apply_filters( 'semnews_campaign_recipients_sql', '', $campaign_id );

		// Insert one row per confirmed subscriber in a single INSERT...SELECT.
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
		$wpdb->query(
			$wpdb->prepare(
				"INSERT INTO {$table} (campaign_id, subscriber_id, status, attempts, created_at)
				 SELECT %d, id, %s, 0, %s FROM {$subscribers} WHERE status = %s {$segment}", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- trusted filter, see above.
				$campaign_id,
				self::STATUS_PENDING,
				$now,
				SEMNEWS_Subscribers::STATUS_SUBSCRIBED
			)
		);

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
		$total = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE campaign_id = %d", $campaign_id ) );

		SEMNEWS_Campaigns::update(
			$campaign_id,
			array(
				'total_recipients' => $total,
				'sent_count'       => 0,
				'failed_count'     => 0,
			)
		);

		return $total;
	}

	/**
	 * Atomically claim the next batch of rows for a campaign and return them
	 * joined with the subscriber data the sender needs.
	 *
	 * The claim is a single UPDATE (atomic in MySQL), so two workers racing on
	 * the same campaign can never claim the same row. Rows whose retry backoff
	 * has not elapsed are skipped.
	 *
	 * @param int $campaign_id Campaign ID.
	 * @param int $limit       Batch size.
	 * @return array Rows: queue id/attempts + subscriber_id, s_email, s_name,
	 *               s_token, s_status (s_email NULL if the subscriber was deleted).
	 */
	public static function claim_batch( $campaign_id, $limit ) {
		global $wpdb;
		$table       = SEMNEWS_Install::table( 'queue' );
		$subscribers = SEMNEWS_Install::table( 'subscribers' );
		$claim       = substr( md5( uniqid( (string) wp_rand(), true ) ), 0, 32 );
		$now         = current_time( 'mysql', true );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
		$claimed = $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$table}
				 SET status = %s, claim_id = %s, claimed_at = %s
				 WHERE campaign_id = %d AND status = %s AND attempts < %d
				   AND ( next_attempt_at IS NULL OR next_attempt_at <= %s )
				 ORDER BY id ASC LIMIT %d",
				self::STATUS_PROCESSING,
				$claim,
				$now,
				absint( $campaign_id ),
				self::STATUS_PENDING,
				self::MAX_ATTEMPTS,
				$now,
				max( 1, (int) $limit )
			)
		);

		if ( ! $claimed ) {
			return array();
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT q.id, q.subscriber_id, q.attempts,
				        s.email AS s_email, s.name AS s_name, s.token AS s_token, s.status AS s_status
				 FROM {$table} q
				 LEFT JOIN {$subscribers} s ON s.id = q.subscriber_id
				 WHERE q.claim_id = %s AND q.status = %s
				 ORDER BY q.id ASC",
				$claim,
				self::STATUS_PROCESSING
			)
		);
	}

	/**
	 * Return a claimed-but-unsent row to pending (used when a batch runs out of
	 * time budget before reaching the row).
	 *
	 * @param int $queue_id Queue row ID.
	 * @return void
	 */
	public static function release( $queue_id ) {
		global $wpdb;
		$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			SEMNEWS_Install::table( 'queue' ),
			array(
				'status'     => self::STATUS_PENDING,
				'claim_id'   => null,
				'claimed_at' => null,
			),
			array( 'id' => absint( $queue_id ) )
		);
	}

	/**
	 * Reaper: return claims older than CLAIM_TIMEOUT to pending so a batch that
	 * died mid-run resumes with the unsent remainder (never the already-sent rows,
	 * which were marked sent one by one as they went out).
	 *
	 * @return int Rows reclaimed.
	 */
	public static function reap_stale_claims() {
		global $wpdb;
		$table  = SEMNEWS_Install::table( 'queue' );
		$cutoff = gmdate( 'Y-m-d H:i:s', time() - self::CLAIM_TIMEOUT );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
		return (int) $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$table} SET status = %s, claim_id = NULL, claimed_at = NULL
				 WHERE status = %s AND claimed_at < %s",
				self::STATUS_PENDING,
				self::STATUS_PROCESSING,
				$cutoff
			)
		);
	}

	/**
	 * Mark a queue row sent.
	 *
	 * @param int $queue_id Queue row ID.
	 * @return void
	 */
	public static function mark_sent( $queue_id ) {
		global $wpdb;
		$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			SEMNEWS_Install::table( 'queue' ),
			array(
				'status'   => self::STATUS_SENT,
				'claim_id' => null,
				'sent_at'  => current_time( 'mysql', true ),
			),
			array( 'id' => absint( $queue_id ) )
		);
	}

	/**
	 * Record a failed attempt. The row backs off before its next retry
	 * (attempts × 10 minutes, filterable) and is marked failed for good after
	 * MAX_ATTEMPTS.
	 *
	 * @param object $row   Queue row (needs id + attempts).
	 * @param string $error Error message.
	 * @return void
	 */
	public static function mark_attempt_failed( $row, $error = '' ) {
		global $wpdb;
		$attempts = (int) $row->attempts + 1;
		$failed   = ( $attempts >= self::MAX_ATTEMPTS );

		/**
		 * Seconds to wait before retrying a failed send, per attempt already made.
		 *
		 * @param int $backoff  Backoff in seconds.
		 * @param int $attempts Attempts made so far.
		 */
		$backoff = (int) apply_filters( 'semnews_retry_backoff', $attempts * 10 * MINUTE_IN_SECONDS, $attempts );

		$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			SEMNEWS_Install::table( 'queue' ),
			array(
				'status'          => $failed ? self::STATUS_FAILED : self::STATUS_PENDING,
				'attempts'        => $attempts,
				'claim_id'        => null,
				'claimed_at'      => null,
				'next_attempt_at' => $failed ? null : gmdate( 'Y-m-d H:i:s', time() + $backoff ),
				'last_error'      => substr( sanitize_text_field( $error ), 0, 255 ),
			),
			array( 'id' => absint( $row->id ) )
		);
	}

	/**
	 * Counts for a campaign queue. In-flight (processing) rows count as pending
	 * for progress purposes.
	 *
	 * @param int $campaign_id Campaign ID.
	 * @return array { pending, sent, failed }
	 */
	public static function counts( $campaign_id ) {
		global $wpdb;
		$table = SEMNEWS_Install::table( 'queue' );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
		$rows = $wpdb->get_results(
			$wpdb->prepare( "SELECT status, COUNT(*) AS c FROM {$table} WHERE campaign_id = %d GROUP BY status", absint( $campaign_id ) ),
			OBJECT_K
		);

		$get = function ( $status ) use ( $rows ) {
			return isset( $rows[ $status ] ) ? (int) $rows[ $status ]->c : 0;
		};

		return array(
			'pending' => $get( self::STATUS_PENDING ) + $get( self::STATUS_PROCESSING ),
			'sent'    => $get( self::STATUS_SENT ),
			'failed'  => $get( self::STATUS_FAILED ),
		);
	}

	/**
	 * The most recent per-recipient error recorded for a campaign (for display).
	 *
	 * @param int $campaign_id Campaign ID.
	 * @return string
	 */
	public static function last_error( $campaign_id ) {
		global $wpdb;
		$table = SEMNEWS_Install::table( 'queue' );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
		return (string) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT last_error FROM {$table}
				 WHERE campaign_id = %d AND last_error IS NOT NULL AND last_error <> ''
				 ORDER BY id DESC LIMIT 1",
				absint( $campaign_id )
			)
		);
	}

	/**
	 * Campaign IDs that still have pending recipients.
	 *
	 * @return int[]
	 */
	public static function campaigns_with_pending() {
		global $wpdb;
		$table = SEMNEWS_Install::table( 'queue' );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
		return array_map(
			'intval',
			$wpdb->get_col(
				$wpdb->prepare(
					"SELECT DISTINCT campaign_id FROM {$table} WHERE status = %s AND attempts < %d",
					self::STATUS_PENDING,
					self::MAX_ATTEMPTS
				)
			)
		);
	}

	/**
	 * Daily maintenance (hooked to the existing cleanup cron):
	 *  1. Purge queue rows for campaigns sent more than N days ago — the queue
	 *     is working data, not an archive, and would otherwise grow forever.
	 *  2. Finalize campaigns stuck in "sending" with nothing left to send (e.g.
	 *     their last pending recipients were deleted mid-send).
	 *
	 * @return void
	 */
	public static function daily_maintenance() {
		global $wpdb;
		$table     = SEMNEWS_Install::table( 'queue' );
		$campaigns = SEMNEWS_Install::table( 'campaigns' );

		self::reap_stale_claims();

		/**
		 * Days to keep queue rows after a campaign finished sending.
		 *
		 * @param int $days Retention days.
		 */
		$days   = max( 1, (int) apply_filters( 'semnews_queue_retention_days', 60 ) );
		$cutoff = gmdate( 'Y-m-d H:i:s', time() - ( $days * DAY_IN_SECONDS ) );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
		$wpdb->query(
			$wpdb->prepare(
				"DELETE q FROM {$table} q
				 INNER JOIN {$campaigns} c ON c.id = q.campaign_id
				 WHERE c.status = %s AND c.sent_at IS NOT NULL AND c.sent_at < %s",
				SEMNEWS_Campaigns::STATUS_SENT,
				$cutoff
			)
		);

		// Finalize "sending" campaigns with no pending/processing rows left.
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
		$stuck = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT c.id FROM {$campaigns} c
				 LEFT JOIN {$table} q ON q.campaign_id = c.id AND q.status IN (%s, %s)
				 WHERE c.status = %s AND q.id IS NULL",
				self::STATUS_PENDING,
				self::STATUS_PROCESSING,
				SEMNEWS_Campaigns::STATUS_SENDING
			)
		);
		foreach ( $stuck as $campaign_id ) {
			SEMNEWS_Campaigns::update(
				(int) $campaign_id,
				array(
					'status'  => SEMNEWS_Campaigns::STATUS_SENT,
					'sent_at' => current_time( 'mysql', true ),
				)
			);
		}
	}
}
