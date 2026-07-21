<?php
/**
 * Campaign (newsletter) data access.
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
 * Campaign repository.
 */
class SEMNEWS_Campaigns {

	const STATUS_DRAFT     = 'draft';
	const STATUS_SCHEDULED = 'scheduled';
	const STATUS_SENDING   = 'sending';
	const STATUS_SENT      = 'sent';
	const STATUS_PAUSED    = 'paused';

	/**
	 * Fetch a campaign by ID.
	 *
	 * @param int $id Campaign ID.
	 * @return object|null
	 */
	public static function get( $id ) {
		global $wpdb;
		$table = SEMNEWS_Install::table( 'campaigns' );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", absint( $id ) ) );
	}

	/**
	 * Create a campaign.
	 *
	 * @param array $data { subject, preheader, body, status, scheduled_at }.
	 * @return int|false Insert ID.
	 */
	public static function create( $data ) {
		global $wpdb;
		$now = current_time( 'mysql', true );

		$row = array(
			'subject'      => isset( $data['subject'] ) ? sanitize_text_field( $data['subject'] ) : '',
			'preheader'    => isset( $data['preheader'] ) ? sanitize_text_field( $data['preheader'] ) : '',
			'body'         => isset( $data['body'] ) ? self::sanitize_body( $data['body'] ) : '',
			'status'       => isset( $data['status'] ) ? sanitize_key( $data['status'] ) : self::STATUS_DRAFT,
			'type'         => isset( $data['type'] ) ? sanitize_key( $data['type'] ) : 'manual',
			'template'     => isset( $data['template'] ) ? sanitize_key( $data['template'] ) : null,
			'sender_id'    => isset( $data['sender_id'] ) ? sanitize_key( $data['sender_id'] ) : null,
			'author_id'    => get_current_user_id(),
			'scheduled_at' => ! empty( $data['scheduled_at'] ) ? $data['scheduled_at'] : null,
			'created_at'   => $now,
			'updated_at'   => $now,
		);

		$result = $wpdb->insert( SEMNEWS_Install::table( 'campaigns' ), $row ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		return $result ? (int) $wpdb->insert_id : false;
	}

	/**
	 * Update a campaign.
	 *
	 * @param int   $id   Campaign ID.
	 * @param array $data Columns to update (raw values; body is sanitized here).
	 * @return bool
	 */
	public static function update( $id, $data ) {
		global $wpdb;

		if ( isset( $data['subject'] ) ) {
			$data['subject'] = sanitize_text_field( $data['subject'] );
		}
		if ( isset( $data['preheader'] ) ) {
			$data['preheader'] = sanitize_text_field( $data['preheader'] );
		}
		if ( isset( $data['body'] ) ) {
			$data['body'] = self::sanitize_body( $data['body'] );
		}
		if ( isset( $data['status'] ) ) {
			$data['status'] = sanitize_key( $data['status'] );
		}
		if ( isset( $data['type'] ) ) {
			$data['type'] = sanitize_key( $data['type'] );
		}
		if ( isset( $data['template'] ) ) {
			$data['template'] = sanitize_key( $data['template'] );
		}
		if ( isset( $data['sender_id'] ) ) {
			$data['sender_id'] = sanitize_key( $data['sender_id'] );
		}
		$data['updated_at'] = current_time( 'mysql', true );

		return false !== $wpdb->update( SEMNEWS_Install::table( 'campaigns' ), $data, array( 'id' => absint( $id ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}

	/**
	 * Delete a campaign and its queue rows.
	 *
	 * @param int $id Campaign ID.
	 * @return void
	 */
	public static function delete( $id ) {
		global $wpdb;
		$id = absint( $id );
		$wpdb->delete( SEMNEWS_Install::table( 'campaigns' ), array( 'id' => $id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery

		/**
		 * Fires after a campaign was deleted, so add-ons can clean their own
		 * relations (e.g. Pro campaign segments).
		 *
		 * @param int $id Deleted campaign id.
		 */
		do_action( 'semnews_campaign_deleted', $id );
		$wpdb->delete( SEMNEWS_Install::table( 'queue' ), array( 'campaign_id' => $id ), array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
	}

	/**
	 * Sanitize campaign HTML body. We allow a generous-but-safe subset.
	 *
	 * @param string $body Raw body.
	 * @return string
	 */
	public static function sanitize_body( $body ) {
		$allowed = wp_kses_allowed_html( 'post' );

		// Permit a few presentation attributes commonly needed in emails.
		foreach ( array( 'p', 'div', 'span', 'a', 'td', 'th', 'table', 'tr', 'img', 'h1', 'h2', 'h3', 'h4' ) as $tag ) {
			if ( ! isset( $allowed[ $tag ] ) ) {
				$allowed[ $tag ] = array();
			}
			$allowed[ $tag ]['style'] = true;
			$allowed[ $tag ]['class'] = true;
		}

		return wp_kses( $body, $allowed );
	}

	/**
	 * List campaigns for the admin table.
	 *
	 * @param array $args { per_page, paged, status, exclude_type }.
	 * @return array { items, total }
	 */
	public static function query( $args = array() ) {
		global $wpdb;
		$table = SEMNEWS_Install::table( 'campaigns' );

		$defaults = array(
			'per_page'     => 20,
			'paged'        => 1,
			'status'       => '',
			'exclude_type' => '',
		);
		$args = wp_parse_args( $args, $defaults );

		$where  = 'WHERE 1=1';
		$params = array();
		if ( $args['status'] ) {
			$where   .= ' AND status = %s';
			$params[] = sanitize_key( $args['status'] );
		}
		if ( $args['exclude_type'] ) {
			$where   .= ' AND type != %s';
			$params[] = sanitize_key( $args['exclude_type'] );
		}

		$per_page = max( 1, (int) $args['per_page'] );
		$paged    = max( 1, (int) $args['paged'] );
		$offset   = ( $paged - 1 ) * $per_page;

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared -- $count_sql/$sql are assembled above from fixed clauses containing only %s/%d placeholders; user values are passed exclusively through $wpdb->prepare(), and the no-params branch contains no placeholders and no user input.

		$count_sql = "SELECT COUNT(*) FROM {$table} {$where}";
		$total     = (int) ( $params ? $wpdb->get_var( $wpdb->prepare( $count_sql, $params ) ) : $wpdb->get_var( $count_sql ) );

		$sql          = "SELECT * FROM {$table} {$where} ORDER BY created_at DESC LIMIT %d OFFSET %d";
		$query_params = array_merge( $params, array( $per_page, $offset ) );
		$items        = $wpdb->get_results( $wpdb->prepare( $sql, $query_params ) );

		// phpcs:enable WordPress.DB.PreparedSQL.NotPrepared

		return array(
			'items' => $items,
			'total' => $total,
		);
	}
}
