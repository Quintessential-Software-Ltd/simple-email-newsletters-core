<?php
/**
 * WP-CLI commands for power users.
 *
 *   wp semnews subscriber export [--status=<status>] [--file=<path>]
 *   wp semnews subscriber list   [--status=<status>] [--limit=<n>]
 *   wp semnews subscriber add <email> [--name=<name>] [--status=<status>] [--basis=<basis>]
 *   wp semnews queue run         [--max-batches=<n>]
 *   wp semnews bounce <email>    [--type=hard|soft]
 *   wp semnews complaint <email>
 *   wp semnews license
 *   wp semnews deliverability
 *
 * @package QuintessentialNewsletters
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * CLI command handlers (registered in the main plugin file under WP_CLI).
 */
class SEMNEWS_CLI {

	/**
	 * Export subscribers to CSV (stdout or a file).
	 *
	 * @param array $args       Positional args.
	 * @param array $assoc_args --status, --file.
	 * @return void
	 */
	public static function subscriber_export( $args, $assoc_args ) {
		$status = isset( $assoc_args['status'] ) ? sanitize_key( $assoc_args['status'] ) : '';
		$file   = isset( $assoc_args['file'] ) ? $assoc_args['file'] : '';

		$handle = $file ? fopen( $file, 'w' ) : fopen( 'php://output', 'w' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		if ( ! $handle ) {
			WP_CLI::error( 'Could not open output for writing.' );
		}

		fputcsv( $handle, array( 'email', 'name', 'status', 'consent_basis', 'created_at', 'confirmed_at', 'source', 'ip_signup' ) );

		// Stream in chunks so an unlimited-size list exports in constant memory.
		$chunk    = 2000;
		$paged    = 1;
		$exported = 0;
		do {
			$data = SEMNEWS_Subscribers::query(
				array(
					'status'   => $status,
					'per_page' => $chunk,
					'paged'    => $paged,
					'orderby'  => 'id',
					'order'    => 'ASC',
				)
			);
			foreach ( $data['items'] as $row ) {
				fputcsv(
					$handle,
					array_map(
						'semnews_csv_field',
						array(
							$row->email,
							$row->name,
							$row->status,
							isset( $row->consent_basis ) ? $row->consent_basis : '',
							$row->created_at,
							$row->confirmed_at,
							$row->source,
							$row->ip_signup,
						)
					)
				);
				$exported++;
			}
			$paged++;
		} while ( count( $data['items'] ) === $chunk );

		fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose

		if ( $file ) {
			WP_CLI::success( sprintf( 'Exported %d subscribers to %s', $exported, $file ) );
		}
	}

	/**
	 * List subscribers as a table.
	 *
	 * @param array $args       Positional args.
	 * @param array $assoc_args --status, --limit.
	 * @return void
	 */
	public static function subscriber_list( $args, $assoc_args ) {
		$status = isset( $assoc_args['status'] ) ? sanitize_key( $assoc_args['status'] ) : '';
		$limit  = isset( $assoc_args['limit'] ) ? max( 1, (int) $assoc_args['limit'] ) : 50;

		$data  = SEMNEWS_Subscribers::query(
			array(
				'status'   => $status,
				'per_page' => $limit,
				'paged'    => 1,
			)
		);
		$items = array();
		foreach ( $data['items'] as $row ) {
			$items[] = array(
				'id'     => $row->id,
				'email'  => $row->email,
				'name'   => $row->name,
				'status' => $row->status,
				'basis'  => isset( $row->consent_basis ) ? $row->consent_basis : '',
				'joined' => $row->created_at,
			);
		}

		if ( empty( $items ) ) {
			WP_CLI::log( 'No subscribers found.' );
			return;
		}

		WP_CLI\Utils\format_items( 'table', $items, array( 'id', 'email', 'name', 'status', 'basis', 'joined' ) );
		WP_CLI::log( sprintf( 'Total matching: %d', $data['total'] ) );
	}

	/**
	 * Add a subscriber.
	 *
	 * @param array $args       Positional: <email>.
	 * @param array $assoc_args --name, --status, --basis.
	 * @return void
	 */
	public static function subscriber_add( $args, $assoc_args ) {
		$email = isset( $args[0] ) ? $args[0] : '';
		if ( ! is_email( $email ) ) {
			WP_CLI::error( 'Please pass a valid email address.' );
		}

		$name   = isset( $assoc_args['name'] ) ? $assoc_args['name'] : '';
		$status = isset( $assoc_args['status'] ) ? sanitize_key( $assoc_args['status'] ) : SEMNEWS_Subscribers::STATUS_SUBSCRIBED;
		$basis  = isset( $assoc_args['basis'] ) ? sanitize_key( $assoc_args['basis'] ) : SEMNEWS_Subscribers::BASIS_CONSENT;

		$res = SEMNEWS_Subscribers::admin_add( $email, $name, $status, 'cli', $basis );

		if ( $res['success'] ) {
			WP_CLI::success( sprintf( 'Added %s (id %d).', $email, $res['id'] ) );
		} else {
			WP_CLI::error( sprintf( 'Could not add %s: %s', $email, $res['code'] ) );
		}
	}

	/**
	 * Process the send queue now (useful when WP-Cron is disabled).
	 *
	 * @param array $args       Positional args.
	 * @param array $assoc_args --max-batches.
	 * @return void
	 */
	public static function queue_run( $args, $assoc_args ) {
		$max    = isset( $assoc_args['max-batches'] ) ? max( 1, (int) $assoc_args['max-batches'] ) : 20;
		$sender = new SEMNEWS_Sender();

		for ( $i = 0; $i < $max; $i++ ) {
			$pending = SEMNEWS_Queue::campaigns_with_pending();
			if ( empty( $pending ) ) {
				break;
			}
			foreach ( $pending as $campaign_id ) {
				$sender->process_campaign_batch( $campaign_id );
			}
			WP_CLI::log( sprintf( 'Batch %d processed.', $i + 1 ) );
		}

		WP_CLI::success( 'Queue processing complete.' );
	}

	/**
	 * Record a bounce for an address.
	 *
	 * @param array $args       Positional: <email>.
	 * @param array $assoc_args --type=hard|soft.
	 * @return void
	 */
	public static function bounce( $args, $assoc_args ) {
		$email = isset( $args[0] ) ? $args[0] : '';
		$type  = isset( $assoc_args['type'] ) && 'soft' === $assoc_args['type'] ? 'soft' : 'hard';

		if ( ! is_email( $email ) ) {
			WP_CLI::error( 'Please pass a valid email address.' );
		}

		$result = SEMNEWS_Subscribers::record_bounce( $email, $type );
		WP_CLI::success( sprintf( '%s bounce recorded for %s: %s', $type, $email, $result ) );
	}

	/**
	 * Record a spam complaint for an address.
	 *
	 * @param array $args Positional: <email>.
	 * @return void
	 */
	public static function complaint( $args ) {
		$email = isset( $args[0] ) ? $args[0] : '';
		if ( ! is_email( $email ) ) {
			WP_CLI::error( 'Please pass a valid email address.' );
		}

		$result = SEMNEWS_Subscribers::record_complaint( $email );
		WP_CLI::success( sprintf( 'Complaint recorded for %s: %s', $email, $result ) );
	}

	/**
	 * Print the deliverability (SPF/DKIM/DMARC) report.
	 *
	 * @return void
	 */
	public static function deliverability() {
		$r = SEMNEWS_Deliverability::report( true );

		if ( empty( $r['dns_available'] ) ) {
			WP_CLI::warning( 'DNS lookups are not available on this host (dns_get_record disabled).' );
		}

		$items = array(
			array( 'check' => 'Sending domain', 'result' => $r['domain'] ),
			array( 'check' => 'Aligned with site', 'result' => $r['aligned'] ? 'yes' : 'no' ),
			array( 'check' => 'SPF', 'result' => $r['spf']['found'] ? 'found' : 'MISSING' ),
			array( 'check' => 'DKIM', 'result' => $r['dkim']['found'] ? ( 'found: ' . implode( ',', $r['dkim']['selectors'] ) ) : 'not detected' ),
			array( 'check' => 'DMARC', 'result' => $r['dmarc']['found'] ? ( 'found (p=' . $r['dmarc']['policy'] . ')' ) : 'MISSING' ),
		);
		WP_CLI\Utils\format_items( 'table', $items, array( 'check', 'result' ) );

		if ( ! $r['spf']['found'] ) {
			WP_CLI::log( 'Suggested SPF: ' . SEMNEWS_Deliverability::suggested_spf() );
		}
		if ( ! $r['dmarc']['found'] ) {
			WP_CLI::log( 'Suggested DMARC at ' . SEMNEWS_Deliverability::dmarc_host() . ': ' . SEMNEWS_Deliverability::suggested_dmarc() );
		}
	}
}
