<?php
/**
 * Deliverability health checks (SPF / DKIM / DMARC) and a self-test email.
 *
 * Looks up the live DNS for the From-address domain and reports what is in
 * place, what is missing, and gives copy-paste records to fix gaps. Also sends a
 * "deliverability test" to the site owner so they can inspect the real headers
 * their mailbox provider sees. Everything here is read-only DNS + one test email.
 *
 * @package QuintessentialNewsletters
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Deliverability helper.
 */
class SEMNEWS_Deliverability {

	/**
	 * DKIM selectors commonly used by ESPs / hosts, probed when auto-detecting.
	 *
	 * @return string[]
	 */
	public static function common_selectors() {
		return apply_filters(
			'semnews_dkim_selectors',
			array( 'default', 'google', 'k1', 'k2', 'dkim', 'mail', 'selector1', 'selector2', 's1', 's2', 'smtp', 'mandrill', 'sendgrid', 'mailjet', 'zoho', 'pm', 'dkim1' )
		);
	}

	/**
	 * The domain part of the configured From address.
	 *
	 * @return string
	 */
	public static function from_domain() {
		$email = sanitize_email( (string) semnews_get_option( 'from_email' ) );
		$parts = explode( '@', $email );
		return isset( $parts[1] ) ? strtolower( $parts[1] ) : '';
	}

	/**
	 * The site host.
	 *
	 * @return string
	 */
	public static function site_domain() {
		$host = wp_parse_url( home_url( '/' ), PHP_URL_HOST );
		return $host ? strtolower( preg_replace( '/^www\./', '', $host ) ) : '';
	}

	/**
	 * Whether PHP DNS lookups are available on this host.
	 *
	 * @return bool
	 */
	public static function dns_available() {
		return function_exists( 'dns_get_record' );
	}

	/**
	 * Full report, cached for an hour unless $force.
	 *
	 * @param bool $force Bypass the cache.
	 * @return array
	 */
	public static function report( $force = false ) {
		$domain = self::from_domain();
		$key    = 'semnews_deliverability_' . md5( (string) $domain );

		if ( ! $force ) {
			$cached = get_transient( $key );
			if ( is_array( $cached ) ) {
				return $cached;
			}
		}

		$report = array(
			'domain'        => $domain,
			'site_domain'   => self::site_domain(),
			'dns_available' => self::dns_available(),
			'aligned'       => $domain && ( $domain === self::site_domain() ),
			'spf'           => self::check_spf( $domain ),
			'dmarc'         => self::check_dmarc( $domain ),
			'dkim'          => self::check_dkim( $domain ),
			'checked_at'    => time(),
		);

		set_transient( $key, $report, HOUR_IN_SECONDS );

		return $report;
	}

	/**
	 * Clear the cached report.
	 *
	 * @return void
	 */
	public static function clear_cache() {
		delete_transient( 'semnews_deliverability_' . md5( (string) self::from_domain() ) );
	}

	/**
	 * Fetch TXT records for a host (lower-cased strings), tolerant of failures.
	 *
	 * @param string $host Host.
	 * @return string[]
	 */
	protected static function txt_records( $host ) {
		if ( ! self::dns_available() || '' === $host ) {
			return array();
		}

		$records = @dns_get_record( $host, DNS_TXT ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- DNS lookups can warn on failure; we handle the empty result.
		if ( ! is_array( $records ) ) {
			return array();
		}

		$out = array();
		foreach ( $records as $record ) {
			if ( ! empty( $record['txt'] ) ) {
				$out[] = $record['txt'];
			} elseif ( ! empty( $record['entries'] ) && is_array( $record['entries'] ) ) {
				$out[] = implode( '', $record['entries'] );
			}
		}
		return $out;
	}

	/**
	 * SPF check.
	 *
	 * @param string $domain Domain.
	 * @return array { found, record }
	 */
	public static function check_spf( $domain ) {
		foreach ( self::txt_records( $domain ) as $txt ) {
			if ( 0 === stripos( trim( $txt ), 'v=spf1' ) ) {
				return array( 'found' => true, 'record' => $txt );
			}
		}
		return array( 'found' => false, 'record' => '' );
	}

	/**
	 * DMARC check.
	 *
	 * @param string $domain Domain.
	 * @return array { found, record, policy }
	 */
	public static function check_dmarc( $domain ) {
		if ( '' === $domain ) {
			return array( 'found' => false, 'record' => '', 'policy' => '' );
		}
		foreach ( self::txt_records( '_dmarc.' . $domain ) as $txt ) {
			if ( 0 === stripos( trim( $txt ), 'v=DMARC1' ) ) {
				$policy = '';
				if ( preg_match( '/\bp\s*=\s*([a-z]+)/i', $txt, $m ) ) {
					$policy = strtolower( $m[1] );
				}
				return array( 'found' => true, 'record' => $txt, 'policy' => $policy );
			}
		}
		return array( 'found' => false, 'record' => '', 'policy' => '' );
	}

	/**
	 * Best-effort DKIM check by probing common selectors for a TXT or CNAME at
	 * <selector>._domainkey.<domain>.
	 *
	 * @param string $domain Domain.
	 * @return array { found, selectors, checked }
	 */
	public static function check_dkim( $domain ) {
		$found = array();
		if ( self::dns_available() && '' !== $domain ) {
			foreach ( self::common_selectors() as $selector ) {
				$host = $selector . '._domainkey.' . $domain;

				$txt = self::txt_records( $host );
				foreach ( $txt as $rec ) {
					if ( false !== stripos( $rec, 'v=DKIM1' ) || false !== stripos( $rec, 'k=rsa' ) || false !== stripos( $rec, 'p=' ) ) {
						$found[] = $selector;
						continue 2;
					}
				}

				$cname = @dns_get_record( $host, DNS_CNAME ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
				if ( is_array( $cname ) && ! empty( $cname ) ) {
					$found[] = $selector;
				}
			}
		}

		return array(
			'found'     => ! empty( $found ),
			'selectors' => array_values( array_unique( $found ) ),
			'checked'   => count( self::common_selectors() ),
		);
	}

	/**
	 * A suggested SPF record (starting point — owners must include their sender).
	 *
	 * @return string
	 */
	public static function suggested_spf() {
		return 'v=spf1 include:_spf.' . self::from_domain() . ' ~all';
	}

	/**
	 * A suggested DMARC record (gentle monitoring policy to start).
	 *
	 * @return string
	 */
	public static function suggested_dmarc() {
		$report_to = sanitize_email( (string) semnews_get_option( 'reply_to' ) );
		if ( ! $report_to ) {
			$report_to = 'postmaster@' . self::from_domain();
		}
		return 'v=DMARC1; p=none; rua=mailto:' . $report_to . '; fo=1; adkim=s; aspf=s';
	}

	/**
	 * Where the suggested DMARC record goes.
	 *
	 * @return string
	 */
	public static function dmarc_host() {
		return '_dmarc.' . self::from_domain();
	}

	/**
	 * Overall status: good | warn | bad.
	 *
	 * @param array $report Report from report().
	 * @return string
	 */
	public static function overall_status( $report ) {
		if ( empty( $report['dns_available'] ) ) {
			return 'warn';
		}
		$spf   = ! empty( $report['spf']['found'] );
		$dmarc = ! empty( $report['dmarc']['found'] );
		if ( $spf && $dmarc ) {
			return 'good';
		}
		if ( $spf || $dmarc ) {
			return 'warn';
		}
		return 'bad';
	}

	/**
	 * Send a deliverability self-test email to an address so the owner can inspect
	 * the real headers (SPF/DKIM/DMARC alignment as their provider sees it).
	 *
	 * @param string $to Recipient.
	 * @return bool
	 */
	public static function send_test( $to ) {
		$to = sanitize_email( $to );
		if ( ! is_email( $to ) ) {
			return false;
		}

		$report = self::report( true );
		$rows   = array(
			array( __( 'From address', 'quintessential-newsletters' ), (string) semnews_get_option( 'from_email' ) ),
			array( __( 'Reply-To', 'quintessential-newsletters' ), (string) semnews_get_option( 'reply_to' ) ),
			array( __( 'Sending domain', 'quintessential-newsletters' ), $report['domain'] ),
			array( __( 'Matches your site domain', 'quintessential-newsletters' ), $report['aligned'] ? __( 'Yes', 'quintessential-newsletters' ) : __( 'No', 'quintessential-newsletters' ) ),
			array( 'SPF', $report['spf']['found'] ? __( 'Found', 'quintessential-newsletters' ) : __( 'Missing', 'quintessential-newsletters' ) ),
			array( 'DKIM', $report['dkim']['found'] ? __( 'Found', 'quintessential-newsletters' ) : __( 'Not detected', 'quintessential-newsletters' ) ),
			array( 'DMARC', $report['dmarc']['found'] ? __( 'Found', 'quintessential-newsletters' ) : __( 'Missing', 'quintessential-newsletters' ) ),
		);

		$content  = '<h2 style="margin:0 0 16px;">' . esc_html__( 'Deliverability test', 'quintessential-newsletters' ) . '</h2>';
		$content .= '<p style="margin:0 0 16px;">' . esc_html__( 'If you received this email, sending works. Open the original message in your mailbox and check that SPF, DKIM and DMARC all pass in the headers.', 'quintessential-newsletters' ) . '</p>';
		$content .= '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;">';
		foreach ( $rows as $row ) {
			$content .= '<tr>'
				. '<td style="padding:6px 10px;border:1px solid #e6e6e6;font-weight:600;">' . esc_html( $row[0] ) . '</td>'
				. '<td style="padding:6px 10px;border:1px solid #e6e6e6;">' . esc_html( $row[1] ) . '</td>'
				. '</tr>';
		}
		$content .= '</table>';

		$subject = sprintf(
			/* translators: %s: site name. */
			__( '[Deliverability test] %s', 'quintessential-newsletters' ),
			get_bloginfo( 'name' )
		);

		$html = SEMNEWS_Mailer::wrap( $subject, $content, null );

		return SEMNEWS_Mailer::send( $to, $subject, $html, null );
	}
}
