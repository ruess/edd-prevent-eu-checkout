<?php
/*
Plugin Name: EDD - Prevent Checkout for the EU
Plugin URI:
Description: Prevents customer from being able to checkout if they're from the EU because VAT laws are stupid.
Version: 1.0-BETA
Author: Andrew Munro (Sumobi), Mika A. Epstein (Ipstenu)
Author URI: http://sumobi.com/
License: GPL-2.0+
License URI: http://www.opensource.org/licenses/gpl-license.php

Forked from http://sumobi.com/shop/edd-prevent-checkout/
*/

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! class_exists( 'EDD_Prevent_EU_Checkout' ) ) {

	class EDD_Prevent_EU_Checkout {

		private static $instance;

		/**
		 * Main Instance
		 *
		 * Ensures that only one instance exists in memory at any one
		 * time. Also prevents needing to define globals all over the place.
		 *
		 * @since 1.0
		 *
		 */
		public static function instance() {
			if ( ! isset ( self::$instance ) ) {
				self::$instance = new self;
			}

			return self::$instance;
		}


		/**
		 * Start your engines
		 *
		 * @since 1.0
		 *
		 * @return void
		 */
		public function __construct() {
			$this->setup_actions();
		}

		/**
		 * Setup the default hooks and actions
		 *
		 * @since 1.0
		 *
		 * @return void
		 */
		private function setup_actions() {

			// text domain
			add_action( 'init', array( $this, 'textdomain' ) );

			// show error before purchase form
			add_action( 'edd_before_purchase_form', array( $this, 'set_error' ) );

			// prevent form from being loaded
			add_filter( 'edd_can_checkout',  array( $this, 'can_checkout' ) );

			// add settings
			add_filter( 'edd_settings_extensions', array( $this, 'settings' ) );

			// sanitize settings
			add_filter( 'edd_settings_extensions_sanitize', array( $this, 'sanitize_settings' ) );

			// Add checkout field
			add_action('edd_purchase_form_user_info', array( $this, 'custom_checkout_fields') );

			// Validate checkout field
			add_action('edd_checkout_error_checks', array( $this, 'validate_custom_fields'), 10, 2);

			do_action( 'edd_pceu_setup_actions' );

		}

		/**
		 * Internationalization
		 *
		 * @since 1.0
		 */
		function textdomain() {
			load_plugin_textdomain( 'edd-prevent-eu-checkout', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
		}

		/**
		 * Get EU (and related) Country List
		 *
		 * @access      public
		 * @since       1.0
		 * @return      array
		 */

		public function eu_get_country_list() {

			$countries = array(
				'AT' => 'Austria',
				'BE' => 'Belgium',
				'BG' => 'Bulgaria',
				'CY' => 'Republic of Cyprus',
				'CZ' => 'Czech Republic',
				'DE' => 'Germany',
				'DK' => 'Denmark',
				'EE' => 'Estonia',
				'EL' => 'Greece', // Shouldn't need both, but just in case
				'ES' => 'Spain',
				'FI' => 'Finland',
				'FR' => 'France',
				'GB' => 'United Kingdom',
				'GR' => 'Greece',
				'HR' => 'Croatia',
				'HU' => 'Hungary',
				'IE' => 'Ireland',
				'IT' => 'Italy',
				'LT' => 'Lithuania',
				'LU' => 'Luxembourg',
				'LV' => 'Latvia',
				'MT' => 'Malta',
				'NL' => 'Netherlands',
				'PL' => 'Poland',
				'PT' => 'Portugal',
				'RO' => 'Romania',
				'SE' => 'Sweden',
				'SI' => 'Slovenia',
				'SK' => 'Slovakia',
				'ZA' => 'South Africa',
				//'XX' => 'Unknown',
			);

			return apply_filters( 'eu_country_list', $countries );
			return $countries;
		}

		/**
		 * Get list of EU Exclusions
		 * Some territories/areas are not applicable for VAT
		 *
		 * @access      public
		 * @since       1.0
		 * @param null $country
		 * @return mixed|void  A list of territories excluded, based on the customer's country
		 */

		public function eu_get_territory_exclustion_list( $country = null ) {

			if( empty( $country ) )
				$country = $this->eu_get_ip();

			switch( $country ) :

				// Denmark:
				case 'DK' :
					$territories = array(
						'FO' => 'Faroe Islands',
						'GL' => 'Greenland',
					);
				break;
				// Spain
				case 'ES' :
					$territories =  array(
						'ES' => 'Canary Islands',
						'ES' => 'Ceuta',
						'ES' => 'Melilla',
					);
				break;
				// Finland:
				case 'FI' :
					$territories =  array(
						'AX' => '&#197;land Islands',
					);
				break;
				// France:
				case 'FR' :
					$territories =  array(
						'RE' => 'Réunion',
						'GP' => 'Guadeloupe',
						'MF' => 'Saint Martin (French part)',
						'FR' => 'Overseas departments',
					);
				break;
				//Germany:
				case 'DE' :
					$territories =  array(
						'DE' => 'Büsingen am Hochrhein',
						'DE' => 'Heligoland',
					);
				break;
				//Greece:
				case 'GR' :
					$territories =  array(
						'DE' => 'Mount Athos',
					);
				break;
				//Italy:
				case 'IT' :
					$territories =  array(
						'IT' => 'some parts bordering to Switzerland',
						'IT' => 'Campione d\'Italia',
						'IT' => 'Livigno',
						'IT' => 'The Italian waters of Lake Lugano',
					);
				break;
				//Netherlands:
				case 'NL' :
					$territories =  array(
						'AW' => 'Aruba',
						'CW' => 'Curaçao',
						'SX' => 'Saint Martin (Dutch part)',
						'NL' => 'The Caribbean Netherlands',
					);
				break;
				//United Kingdom:
				case 'UK' :
					$territories =  array(
						'GI' => 'Gibraltar',
						'NL' => 'Channel Islands',
						'NL' => 'British Overseas Territories',
					);

			endswitch;

			return apply_filters( 'eu_territory_exclustion_list', $territories );

		}

		/**
		 * Check if the plugin is active
		 *
		 * @since 1.0
		*/
		function eu_get_running() {

			global $edd_options;

			// Set the checkbox
			$checkbox = isset( $edd_options['edd_pceu_checkbox'] ) ? $edd_options['edd_pceu_checkbox'] : '';

			return $checkbox;
		}

		/**
		 * Get the user's IP
		 *
		 * @since 1.0
		*/
		function eu_get_user_ip() {

			if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
				$ip=$_SERVER['HTTP_CLIENT_IP'];
			} elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
				$ip=$_SERVER['HTTP_X_FORWARDED_FOR'];
		    } else {
				$ip=$_SERVER['REMOTE_ADDR'];
    		}

			return $ip;

		}

		/**
		 * Get the user's Country
		 *
		 * @since 1.0
		*/
		function eu_get_user_country() {

			if (function_exists('geoip_country_code_by_name')) {
				// If you have GeoIP installed, it's much easier: http://php.net/manual/en/book.geoip.php
				$this_country = geoip_country_code_by_name( $this->eu_get_user_ip() );
			} else {
				// Otherwise we use HostIP.info which is GPL
				$this_country = file_get_contents('http://api.hostip.info/country.php?ip=' . $this->eu_get_user_ip() );
			}

			return $this_country;
		}

		/**
		 * Check if restrictions need to be applied
		 * If the checkbox is true and the country is on the list, we block
		 *
		 * @since 1.0
		*/
		function block_eu_required() {

			global $edd_options;

			if (
				$this->eu_get_running() == TRUE &&
				$this->eu_get_user_country() != $edd_options['edd_pceu_exclude'] &&
				array_key_exists( $this->eu_get_user_country(), $this->eu_get_country_list() )
			) {
				$canblock = TRUE;
			} else {
				$canblock = FALSE;
			}

			return $canblock;
		}

		/**
		 * Set error message
		 *
		 * @since 1.0
		*/
		function set_error() {

			global $edd_options;

			if ( $this->block_eu_required() == TRUE ) {
				edd_set_error( 'eu_not_allowed', apply_filters( 'edd_pceu_error_message', $edd_options['edd_pceu_checkout_message'] ) );
			}
			else {
				edd_unset_error( 'eu_not_allowed' );
			}

			edd_print_errors();
		}

		/**
		 * Can checkout?
		 * Prevents the form from being displayed at all until the user's IP is outside the EU
		 *
		 * @since 1.0
		*/
		function can_checkout( $can_checkout  ) {

			if ( $this->block_eu_required() == TRUE ) {
				$can_checkout = false;
			}

			return $can_checkout;
		}

		/**
		 * Custom Checkout Field
		 * A confirmation box. In the event someone made it all the way through IP checks
		 * we STILL need to cover our damn asses and make sure they're not really in the
		 * EU, so we put the onus on them to confirm 'I confirm I do not reside in the EU.'
		 *
		 * @since 1.0
		*/

		function custom_checkout_fields() {

			global $edd_options;

			$checkbox = isset( $edd_options['edd_pceu_checkbox'] ) ? $edd_options['edd_pceu_checkbox'] : '';
			if ( $checkbox == TRUE ) {
				?>
				<p id='edd-eu-wrap'>
					<label class='edd-label' for='edd-eu'><?php _e('EU VAT Compliance Confirmation', 'edd-prevent-eu-checkout', 'edd-prevent-eu-checkout'); ?></label>
					<span class='edd-description'><input class='edd-checkbox' type='checkbox' id='edd-eu' value='1' /> <?php _e('By checking this box you confirm you are either a business or not a legal EU resident.', 'edd-prevent-eu-checkout', 'edd-prevent-eu-checkout'); ?></span>
				</p>
				<?php
			}
		}

		/**
		 * Custom Checkout Field Sanitization
		 *
		 * @since 1.0
		*/
		function validate_custom_fields($valid_data, $data) {

			global $edd_options;

			if ( !isset( $data['edd_eu'] ) || $data['edd_eu'] != '1' ) {
				$data['edd_eu'] = 0;
				edd_set_error( 'eu_not_checked', apply_filters( 'edd_pceu_error_message', $edd_options['edd_pceu_checkout_message'] ) );
			} else {
				$data['edd_eu'] = 1;
			}

		}

		/**
		 * Settings
		 *
		 * @since 1.0
		*/
		function settings( $settings ) {

		  $edd_pceu_settings = array(
				array(
					'id' => 'edd_pceu_header',
					'name' => '<strong>' . __( 'Prevent EU Checkout', 'edd-prevent-eu-checkout' ) . '</strong>',
					'type' => 'header'
				),

				array(
					'id' => 'edd_pceu_checkbox',
					'name' => __( 'Enable Blocking of EU Sales', 'edd-prevent-eu-checkout' ),
					'desc' => __( 'Check this box to prevent EU customers from completing checkout.', 'edd-prevent-eu-checkout' ),
					'type' => 'checkbox',
					'std' => ''
				),

				array(
					'id' => 'edd_pceu_general_message',
					'name' => __( 'General Message', 'edd-prevent-eu-checkout' ),
					'desc' => __( 'Will be displayed at the top of every page where [downloads] is used.', 'edd-prevent-eu-checkout' ),
					'type' => 'textarea',
					'std' => 'At this time we are unable to complete sales to EU residents. <a href='#'>Why?</a>'
				),

				array(
					'id' => 'edd_pceu_checkout_message',
					'name' => __( 'Checkout Message', 'edd-prevent-eu-checkout' ),
					'desc' => __( 'Will be displayed on attempt to checkout by someone in the EU.', 'edd-prevent-eu-checkout' ),
					'type' => 'textarea',
					'std' => 'At this time we are unable to complete sales to EU residents. <a href='#'>Why?</a>'
				),

				array(
					'id' => 'edd_pceu_exclude',
					'name' => __( 'Exclude Country', 'edd-prevent-eu-checkout' ),
					'desc' => __( 'If sales are permitted from your own country, select it from this dropdown. (Invalid countries cannot be selected.)', 'edd-prevent-eu-checkout' ),
					'type' => 'select',
					'options' => edd_get_country_list()
				),

			);

			return array_merge( $settings, $edd_pceu_settings );
		}

		/**
		 * Sanitize settings
		 *
		 * @since 1.0
		*/
		function sanitize_settings( $input ) {

			// Sanitize checkbox
			if ( ! isset( $input['edd_pceu_checkbox'] ) || $input['edd_pceu_checkbox'] != '1' ) {
				$input['edd_pceu_checkbox'] = 0;
			} else {
				$input['edd_pceu_checkbox'] = 1;
			}

			// Sanitize edd_pceu_general_message
			$input['edd_pceu_general_message'] = wp_kses_post( $input['edd_pceu_general_message'] );

			// Sanitize edd_pceu_checkout_message
			$input['edd_pceu_checkout_message'] = wp_kses_post( $input['edd_pceu_checkout_message'] );

			// Sanitize edd_pceu_exclude
			if ( in_array($input['edd_pceu_exclude'], $this->eu_get_country_list()) || array_key_exists($input['edd_pceu_exclude'], $this->eu_get_country_list()) ) {
				$input['edd_pceu_exclude'] = $input['edd_pceu_exclude'];
			} else {
				$input['edd_pceu_exclude'] = null;
			}

			return $input;
		}

	} // END CLASS

}

/**
 * Get everything running
 *
 * @since 1.0
 *
 * @access private
 * @return void
 */
function edd_prevent_eu_checkout_load() {
	$edd_prevent_checkout = new EDD_Prevent_EU_Checkout();
}
add_action( 'plugins_loaded', 'edd_prevent_eu_checkout_load' );