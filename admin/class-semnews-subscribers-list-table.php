<?php
/**
 * Subscribers admin list table.
 *
 * @package QuintessentialNewsletters
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * Lists subscribers with search, status filter, sorting and bulk actions.
 */
class SEMNEWS_Subscribers_List_Table extends WP_List_Table {

	/**
	 * Constructor.
	 */
	public function __construct() {
		parent::__construct(
			array(
				'singular' => 'subscriber',
				'plural'   => 'subscribers',
				'ajax'     => false,
			)
		);
	}

	/**
	 * Columns.
	 *
	 * @return array
	 */
	public function get_columns() {
		return array(
			'cb'           => '<input type="checkbox" />',
			'email'        => __( 'Email', 'quintessential-newsletters' ),
			'name'         => __( 'Name', 'quintessential-newsletters' ),
			'status'       => __( 'Status', 'quintessential-newsletters' ),
			'basis'        => __( 'Basis', 'quintessential-newsletters' ),
			'source'       => __( 'Source', 'quintessential-newsletters' ),
			'created_at'   => __( 'Subscribed', 'quintessential-newsletters' ),
			'confirmed_at' => __( 'Confirmed', 'quintessential-newsletters' ),
		);
	}

	/**
	 * Sortable columns.
	 *
	 * @return array
	 */
	protected function get_sortable_columns() {
		return array(
			'email'        => array( 'email', false ),
			'name'         => array( 'name', false ),
			'status'       => array( 'status', false ),
			'created_at'   => array( 'created_at', true ),
			'confirmed_at' => array( 'confirmed_at', false ),
		);
	}

	/**
	 * Bulk actions.
	 *
	 * @return array
	 */
	protected function get_bulk_actions() {
		return array(
			'unsubscribe' => __( 'Unsubscribe', 'quintessential-newsletters' ),
			'resend'      => __( 'Resend confirmation', 'quintessential-newsletters' ),
			'bounce'      => __( 'Mark as bounced (suppress)', 'quintessential-newsletters' ),
			'delete'      => __( 'Delete', 'quintessential-newsletters' ),
		);
	}

	/**
	 * Checkbox column.
	 *
	 * @param object $item Row.
	 * @return string
	 */
	public function column_cb( $item ) {
		return sprintf( '<input type="checkbox" name="subscriber[]" value="%d" />', (int) $item->id );
	}

	/**
	 * Email column with row actions.
	 *
	 * @param object $item Row.
	 * @return string
	 */
	public function column_email( $item ) {
		$view_url = admin_url( 'admin.php?page=semnews-subscribers&action=view&subscriber=' . (int) $item->id );

		$actions = array(
			'view' => sprintf( '<a href="%s">%s</a>', esc_url( $view_url ), esc_html__( 'View', 'quintessential-newsletters' ) ),
		);

		return sprintf(
			'<strong><a href="%s">%s</a></strong>%s',
			esc_url( $view_url ),
			esc_html( $item->email ),
			$this->row_actions( $actions )
		);
	}

	/**
	 * Default column rendering.
	 *
	 * @param object $item        Row.
	 * @param string $column_name Column key.
	 * @return string
	 */
	public function column_default( $item, $column_name ) {
		switch ( $column_name ) {
			case 'name':
				return esc_html( $item->name );
			case 'status':
				$labels = SEMNEWS_Subscribers::statuses();
				$label  = isset( $labels[ $item->status ] ) ? $labels[ $item->status ] : $item->status;
				return '<span class="semnews-status semnews-status-' . esc_attr( $item->status ) . '">' . esc_html( $label ) . '</span>';
			case 'basis':
				$bases = SEMNEWS_Subscribers::bases();
				$b     = isset( $item->consent_basis ) ? $item->consent_basis : SEMNEWS_Subscribers::BASIS_CONSENT;
				return esc_html( isset( $bases[ $b ] ) ? $bases[ $b ] : $b );
			case 'source':
				return esc_html( $item->source );
			case 'created_at':
				return esc_html( $item->created_at ? mysql2date( get_option( 'date_format' ), $item->created_at ) : '—' );
			case 'confirmed_at':
				return esc_html( $item->confirmed_at ? mysql2date( get_option( 'date_format' ), $item->confirmed_at ) : '—' );
			default:
				return '';
		}
	}

	/**
	 * Read the search/filter/sort parameters for this screen from the request.
	 *
	 * The search and status inputs are only honoured when the
	 * `semnews_filter_subscribers` nonce sent by this screen's own forms
	 * verifies; otherwise the unfiltered defaults are returned. Sort
	 * parameters are restricted to a fixed whitelist, so no request value
	 * ever reaches the query layer unchecked.
	 *
	 * @return array Keys: search, status, orderby, order.
	 */
	public static function request_filters() {
		$filters = array(
			'search'  => '',
			'status'  => '',
			'orderby' => 'created_at',
			'order'   => 'desc',
		);

		$nonce = isset( $_REQUEST['_semnews_filter'] ) ? sanitize_key( wp_unslash( $_REQUEST['_semnews_filter'] ) ) : '';
		if ( $nonce && wp_verify_nonce( $nonce, 'semnews_filter_subscribers' ) ) {
			if ( isset( $_REQUEST['s'] ) ) {
				$filters['search'] = sanitize_text_field( wp_unslash( $_REQUEST['s'] ) );
			}
			if ( isset( $_REQUEST['status'] ) ) {
				$status   = sanitize_key( wp_unslash( $_REQUEST['status'] ) );
				$statuses = SEMNEWS_Subscribers::statuses();
				if ( isset( $statuses[ $status ] ) ) {
					$filters['status'] = $status;
				}
			}
		}

		// Sorting comes from column-header links (no form, so no nonce), but
		// both values are restricted to this fixed whitelist.
		if ( isset( $_REQUEST['orderby'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$orderby = sanitize_key( wp_unslash( $_REQUEST['orderby'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			if ( in_array( $orderby, array( 'email', 'name', 'status', 'created_at', 'confirmed_at' ), true ) ) {
				$filters['orderby'] = $orderby;
			}
		}
		if ( isset( $_REQUEST['order'] ) && 'asc' === sanitize_key( wp_unslash( $_REQUEST['order'] ) ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$filters['order'] = 'asc';
		}

		return $filters;
	}

	/**
	 * Status filter dropdown above the table.
	 *
	 * @param string $which Top or bottom.
	 * @return void
	 */
	protected function extra_tablenav( $which ) {
		if ( 'top' !== $which ) {
			return;
		}
		$filters = self::request_filters();
		$current = $filters['status'];
		?>
		<div class="alignleft actions">
			<label class="screen-reader-text" for="semnews-status-filter"><?php esc_html_e( 'Filter by status', 'quintessential-newsletters' ); ?></label>
			<select name="status" id="semnews-status-filter">
				<option value=""><?php esc_html_e( 'All statuses', 'quintessential-newsletters' ); ?></option>
				<?php foreach ( SEMNEWS_Subscribers::statuses() as $key => $label ) : ?>
					<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $current, $key ); ?>><?php echo esc_html( $label ); ?></option>
				<?php endforeach; ?>
			</select>
			<?php submit_button( __( 'Filter', 'quintessential-newsletters' ), '', 'filter_action', false ); ?>
		</div>
		<?php
	}

	/**
	 * Prepare items for display.
	 *
	 * @return void
	 */
	public function prepare_items() {
		$this->_column_headers = array( $this->get_columns(), array(), $this->get_sortable_columns() );

		$per_page = 20;
		$paged    = $this->get_pagenum();

		$filters = self::request_filters();

		$data = SEMNEWS_Subscribers::query(
			array(
				'status'   => $filters['status'],
				'search'   => $filters['search'],
				'orderby'  => $filters['orderby'],
				'order'    => $filters['order'],
				'per_page' => $per_page,
				'paged'    => $paged,
			)
		);

		$this->items = $data['items'];

		$this->set_pagination_args(
			array(
				'total_items' => $data['total'],
				'per_page'    => $per_page,
				'total_pages' => (int) ceil( $data['total'] / $per_page ),
			)
		);
	}

	/**
	 * Message when there are no subscribers.
	 *
	 * @return void
	 */
	public function no_items() {
		esc_html_e( 'No subscribers yet. Add your signup form to a page with the [semnews_newsletter] shortcode or the Newsletter Signup block.', 'quintessential-newsletters' );
	}
}
