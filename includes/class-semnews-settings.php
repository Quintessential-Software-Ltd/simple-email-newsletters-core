<?php
/**
 * Settings registration (WordPress Settings API).
 *
 * All settings live in a single option, `semnews_settings`, sanitised through one
 * callback. The settings screen renders the fields itself (admin/views/settings.php)
 * and relies on settings_fields() for the nonce + option group plumbing.
 *
 * @package SimpleEmailNewsletters
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Settings controller.
 */
class SEMNEWS_Settings {

	const OPTION_GROUP = 'semnews_settings_group';
	const OPTION_NAME  = 'semnews_settings';

	/**
	 * Register the setting and its sanitiser.
	 *
	 * @return void
	 */
	public static function register() {
		register_setting(
			self::OPTION_GROUP,
			self::OPTION_NAME,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( __CLASS__, 'sanitize' ),
				'default'           => semnews_default_settings(),
			)
		);
	}

	/**
	 * Sanitise the whole settings array.
	 *
	 * @param array $input Raw submitted values.
	 * @return array
	 */
	public static function sanitize( $input ) {
		$current  = semnews_get_settings();
		$defaults = semnews_default_settings();
		$out      = array();

		$input = is_array( $input ) ? $input : array();

		$out['from_name']    = isset( $input['from_name'] ) ? sanitize_text_field( $input['from_name'] ) : $defaults['from_name'];
		$out['from_email']   = isset( $input['from_email'] ) && is_email( $input['from_email'] ) ? sanitize_email( $input['from_email'] ) : $defaults['from_email'];
		$out['reply_to']     = isset( $input['reply_to'] ) && is_email( $input['reply_to'] ) ? sanitize_email( $input['reply_to'] ) : $out['from_email'];
		$out['company_name'] = isset( $input['company_name'] ) ? sanitize_text_field( $input['company_name'] ) : $defaults['company_name'];
		$out['postal_address'] = isset( $input['postal_address'] ) ? sanitize_textarea_field( $input['postal_address'] ) : '';

		$out['double_optin']  = empty( $input['double_optin'] ) ? 0 : 1;
		$out['send_welcome']  = empty( $input['send_welcome'] ) ? 0 : 1;

		$out['privacy_policy_page'] = isset( $input['privacy_policy_page'] ) ? absint( $input['privacy_policy_page'] ) : 0;

		$out['consent_text']           = isset( $input['consent_text'] ) ? sanitize_text_field( $input['consent_text'] ) : $defaults['consent_text'];
		$out['success_message']        = isset( $input['success_message'] ) ? sanitize_text_field( $input['success_message'] ) : $defaults['success_message'];
		$out['success_message_single'] = isset( $input['success_message_single'] ) ? sanitize_text_field( $input['success_message_single'] ) : $defaults['success_message_single'];
		$out['confirmation_subject']   = isset( $input['confirmation_subject'] ) ? sanitize_text_field( $input['confirmation_subject'] ) : '';
		$out['confirmation_intro']     = isset( $input['confirmation_intro'] ) ? sanitize_textarea_field( $input['confirmation_intro'] ) : '';
		$out['welcome_subject']        = isset( $input['welcome_subject'] ) ? sanitize_text_field( $input['welcome_subject'] ) : '';
		$out['welcome_intro']          = isset( $input['welcome_intro'] ) ? sanitize_textarea_field( $input['welcome_intro'] ) : '';

		$out['batch_size']     = isset( $input['batch_size'] ) ? max( 1, min( 500, absint( $input['batch_size'] ) ) ) : $defaults['batch_size'];
		$out['retention_days'] = isset( $input['retention_days'] ) ? max( 0, absint( $input['retention_days'] ) ) : $defaults['retention_days'];

		$out['delete_data_on_uninstall'] = empty( $input['delete_data_on_uninstall'] ) ? 0 : 1;

		// If the consent wording changed, bump a version stamp so future signups
		// are recorded against the new text without rewriting past proof.
		if ( isset( $current['consent_text'] ) && $current['consent_text'] !== $out['consent_text'] ) {
			$out['consent_version'] = gmdate( 'Ymd-His' );
		} elseif ( isset( $current['consent_version'] ) ) {
			$out['consent_version'] = $current['consent_version'];
		}

		/**
		 * Lets add-ons sanitise and persist their own keys inside semnews_settings.
		 *
		 * @param array $out     Sanitised settings so far.
		 * @param array $input   Raw submitted values.
		 * @param array $current Previously stored settings.
		 */
		return apply_filters( 'semnews_sanitize_settings', $out, $input, $current );
	}
}
