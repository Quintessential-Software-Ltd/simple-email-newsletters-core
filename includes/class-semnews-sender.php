<?php
/**
 * Batch campaign sender (runs on WP-Cron).
 *
 * Concurrency safety: a per-campaign lock is taken atomically (add_option —
 * a row INSERT that fails if the lock exists), so the recurring queue cron,
 * the kick-off single event and `wp semnews queue run` can never process the same
 * campaign at once. Rows are claimed before sending (see SEMNEWS_Queue), so a
 * crash mid-batch never double-sends. Each batch also respects a wall-clock
 * budget so shared hosts don't kill the request mid-flight.
 *
 * @package QuintessentialNewsletters
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Sender.
 */
class SEMNEWS_Sender {

	/**
	 * Seconds a campaign lock may be held before it is considered stale
	 * (crashed process) and can be stolen.
	 */
	const LOCK_TIMEOUT = 120;

	/**
	 * Hook registration.
	 *
	 * @return void
	 */
	public function init() {
		add_action( SEMNEWS_Install::CRON_PROCESS_QUEUE, array( $this, 'process_queue' ) );
		add_action( 'semnews_send_now', array( $this, 'process_campaign_batch' ) );
	}

	/**
	 * Queue a campaign for sending: build the recipient list and flip status.
	 *
	 * Note: sent_at is set by finalize_campaign() when sending completes, not here.
	 *
	 * @param int $campaign_id Campaign ID.
	 * @return int Recipients enqueued.
	 */
	public static function start_campaign( $campaign_id ) {
		$total = SEMNEWS_Queue::build_for_campaign( $campaign_id );

		SEMNEWS_Campaigns::update(
			$campaign_id,
			array(
				'status' => SEMNEWS_Campaigns::STATUS_SENDING,
			)
		);

		// Kick off the first batch immediately rather than waiting for cron.
		if ( ! wp_next_scheduled( 'semnews_send_now', array( $campaign_id ) ) ) {
			wp_schedule_single_event( time() + 5, 'semnews_send_now', array( $campaign_id ) );
		}

		return $total;
	}

	/**
	 * Cron callback: reap abandoned claims, then walk every campaign that still
	 * has pending recipients and send one batch each.
	 *
	 * @return void
	 */
	public function process_queue() {
		SEMNEWS_Queue::reap_stale_claims();

		/**
		 * Fires at the start of each queue tick, before pending batches are sent.
		 * Core sends immediately and has no scheduling feature of its own; the
		 * Pro add-on hooks this to launch any of its scheduled campaigns whose
		 * time has arrived (via SEMNEWS_Sender::start_campaign()).
		 *
		 * @param SEMNEWS_Sender $sender The sender instance.
		 */
		do_action( 'semnews_process_queue', $this );

		$campaigns = SEMNEWS_Queue::campaigns_with_pending();
		foreach ( $campaigns as $campaign_id ) {
			$this->process_campaign_batch( $campaign_id );
		}
	}

	/**
	 * Atomically acquire the per-campaign send lock.
	 *
	 * add_option() maps to an INSERT that fails if the row exists, which makes
	 * the check-and-set a single atomic operation (unlike get/set_transient).
	 * A lock older than LOCK_TIMEOUT belonged to a crashed process and is stolen.
	 *
	 * @param int $campaign_id Campaign ID.
	 * @return bool Whether the lock was acquired.
	 */
	protected function acquire_lock( $campaign_id ) {
		$key = 'semnews_lock_campaign_' . (int) $campaign_id;

		if ( add_option( $key, (string) time(), '', false ) ) {
			return true;
		}

		$held_since = (int) get_option( $key );
		if ( $held_since && ( time() - $held_since ) > self::LOCK_TIMEOUT ) {
			// Stale lock from a dead process: steal it.
			delete_option( $key );
			return (bool) add_option( $key, (string) time(), '', false );
		}

		return false;
	}

	/**
	 * Release the per-campaign send lock.
	 *
	 * @param int $campaign_id Campaign ID.
	 * @return void
	 */
	protected function release_lock( $campaign_id ) {
		delete_option( 'semnews_lock_campaign_' . (int) $campaign_id );
	}

	/**
	 * Send a single batch for one campaign.
	 *
	 * @param int $campaign_id Campaign ID.
	 * @return void
	 */
	public function process_campaign_batch( $campaign_id ) {
		$campaign = SEMNEWS_Campaigns::get( $campaign_id );
		if ( ! $campaign ) {
			return;
		}

		if ( ! in_array( $campaign->status, array( SEMNEWS_Campaigns::STATUS_SENDING ), true ) ) {
			return; // Paused, draft, scheduled or already sent — nothing to do.
		}

		if ( ! $this->acquire_lock( $campaign_id ) ) {
			return; // Another worker is on this campaign right now.
		}

		try {
			$this->send_one_batch( $campaign );
		} finally {
			$this->release_lock( $campaign_id );
		}
	}

	/**
	 * The actual batch work, run under the campaign lock.
	 *
	 * @param object $campaign Campaign row.
	 * @return void
	 */
	protected function send_one_batch( $campaign ) {
		$campaign_id = (int) $campaign->id;

		$batch_size = (int) semnews_get_option( 'batch_size', 50 );
		$batch_size = max( 1, min( 500, $batch_size ) );

		/**
		 * Wall-clock budget (seconds) for one batch request. Rows not reached in
		 * time are released back to pending for the next run.
		 *
		 * @param int $budget Seconds.
		 */
		$budget  = (int) apply_filters( 'semnews_batch_time_budget', 20 );
		$started = microtime( true );

		$rows = SEMNEWS_Queue::claim_batch( $campaign_id, $batch_size );

		foreach ( $rows as $i => $row ) {
			if ( ( microtime( true ) - $started ) > $budget ) {
				// Out of time: hand the rest of the claim back for the next run.
				for ( $j = $i, $n = count( $rows ); $j < $n; $j++ ) {
					SEMNEWS_Queue::release( $rows[ $j ]->id );
				}
				break;
			}

			// Skip people who unsubscribed (or were deleted) after the queue was built.
			if ( empty( $row->s_email ) || SEMNEWS_Subscribers::STATUS_SUBSCRIBED !== $row->s_status ) {
				SEMNEWS_Queue::mark_sent( $row->id ); // Treat as handled; do not email.
				continue;
			}

			// Build the subscriber object the mailer expects from the joined data
			// (saves one SELECT per recipient).
			$subscriber         = new stdClass();
			$subscriber->id     = (int) $row->subscriber_id;
			$subscriber->email  = $row->s_email;
			$subscriber->name   = $row->s_name;
			$subscriber->token  = $row->s_token;
			$subscriber->status = $row->s_status;

			$sent = SEMNEWS_Mailer::send_campaign_to( $campaign, $subscriber );

			if ( $sent ) {
				SEMNEWS_Queue::mark_sent( $row->id );
			} else {
				SEMNEWS_Queue::mark_attempt_failed( $row, __( 'wp_mail returned false', 'quintessential-newsletters' ) );
			}
		}

		// Update progress counters.
		$counts = SEMNEWS_Queue::counts( $campaign_id );
		SEMNEWS_Campaigns::update(
			$campaign_id,
			array(
				'sent_count'   => $counts['sent'],
				'failed_count' => $counts['failed'],
			)
		);

		if ( 0 === $counts['pending'] ) {
			$this->finalize_campaign( $campaign_id );
		} elseif ( ! wp_next_scheduled( 'semnews_send_now', array( $campaign_id ) ) ) {
			// Schedule the next batch shortly to keep large sends moving between
			// cron ticks. (Also reached when every remaining row is backing off —
			// the retry becomes due before or at the next recurring cron tick.)
			wp_schedule_single_event( time() + 30, 'semnews_send_now', array( $campaign_id ) );
		}
	}

	/**
	 * Mark a campaign as fully sent. sent_at records COMPLETION time.
	 *
	 * @param int $campaign_id Campaign ID.
	 * @return void
	 */
	protected function finalize_campaign( $campaign_id ) {
		$campaign = SEMNEWS_Campaigns::get( $campaign_id );
		if ( $campaign && SEMNEWS_Campaigns::STATUS_SENT !== $campaign->status ) {
			SEMNEWS_Campaigns::update(
				$campaign_id,
				array(
					'status'  => SEMNEWS_Campaigns::STATUS_SENT,
					'sent_at' => current_time( 'mysql', true ),
				)
			);
			do_action( 'semnews_campaign_sent', $campaign_id );
		}
	}
}
