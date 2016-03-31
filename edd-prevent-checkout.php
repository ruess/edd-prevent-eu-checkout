<?php
/*
Plugin Name: Prevent EU Checkout for Easy Digital Downloads
Plugin URI: http://halfelf.org/plugins/edd-prevent-eu-checkout
Description: Prevents customer from being able to checkout if they're from the EU because VAT laws are stupid.
Version: 1.2.2
Author: Mika A. Epstein (Ipstenu)
Author URI: http://halfelf.org
License: GPL-2.0+
License URI: http://www.opensource.org/licenses/gpl-license.php
Text Domain: edd-prevent-eu-checkout

Forked from http://sumobi.com/shop/edd-prevent-checkout/ by Andrew Munro (Sumobi)

Copyright 2014-16 Mika A Epstein (ipstenu@halfelf.org)

*/

/* Preflight checklist */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) { wp_die( __( 'Cheatin&#8217; eh?' ) ); }

/**
 * Call the GeoIP reader code
 *
 * @since 1.0.5
 */
require_once 'GeoIP/vendor/autoload.php';
use GeoIp2\Database\Reader;

/* The Actual Plugin */

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
			if ( !in_array( 'easy-digital-downloads/easy-digital-downloads.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) || !file_exists( plugin_dir_path( __FILE__) . '../easy-digital-downloads/easy-digital-downloads.php' ) ) {
				add_action( 'admin_init', array( &$this, 'plugin_deactivate' ) );
			    add_action( 'admin_notices', array( &$this, 'plugin_deactivate_notice' ) );
			} else {
				$this->setup_actions();
			}
		}

		/**
		 * plugin_deactivate
		 *
		 * Deactive the plugin if called.
		 *
		 * @since 1.1
		 * @access public
		 */
		public function plugin_deactivate() {
			deactivate_plugins( plugin_basename( __FILE__ ) );
		}

		/**
		 * plugin_deactivate_message
		 *
		 * Why we deactivated the plugin
		 *
		 * @since 1.1
		 * @access public
		 */
		public function plugin_deactivate_notice() {
			
			?><div class="error notice is-dismissable"><p><?php _e('EDD - Prevent EU Checkout requires that Easy Digital Downloads be installed; the plug-in has been deactivated', 'edd-prevent-eu-checkout', 'edd-prevent-eu-checkout'); ?></p></div><?php
			
			if ( isset( $_GET['activate'] ) )
				unset( $_GET['activate'] );
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
			add_action( 'edd_before_purchase_form', array( $this, 'set_checkout_error' ) );

			// show message when [downloads] is called
			add_action( 'the_content', array( $this, 'set_downloads_message' )  );

			// prevent form from being loaded
			add_filter( 'edd_can_checkout', array( $this, 'can_checkout' ) );

			// prevent payment select box from showing (only if you show gateways in the first place)
			if ( edd_show_gateways() ) {
				add_filter( 'edd_show_gateways', array( $this, 'can_checkout' ) );
			}

			// prevent Buy Now button from displaying
			add_filter( 'edd_purchase_download_form', array( $this, 'prevent_purchase_button' ), 10, 2 );

			// add settings
			add_filter( 'edd_settings_extensions', array( $this, 'settings' ) );

			// sanitize settings
			add_filter( 'edd_settings_extensions_sanitize', array( $this, 'sanitize_settings' ) );

			// Add checkout field
			add_action('edd_purchase_form_user_info_fields', array( $this, 'custom_checkout_fields') );

			// Validate checkout field
			add_action('edd_checkout_error_checks', array( $this, 'validate_custom_fields'), 10, 2);

			do_action( 'edd_pceu_setup_actions' );
		}

		/**
		 * Internationalization
		 *
		 * @since 1.0
		 */
		public function textdomain() {
			load_plugin_textdomain( 'edd-prevent-eu-checkout' );
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
				'EL' => 'Greece', # Shouldn't need both, but just in case
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
				//'ZA' => 'South Africa', # Per http://www.kpmg.com/global/en/issuesandinsights/articlespublications/vat-gst-essentials/pages/south-africa.aspx the threshold is R50,000
				//'ZZ' => 'Unknown', # This is for testing only.
			);

			return apply_filters( 'eu_country_list', $countries );
			return $countries;
		}

		/**
		 * Check if the plugin is active
		 *
		 * @since 1.0
		*/
		public function eu_get_running() {

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
		public function eu_get_user_ip() {

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
		 * This tries to use GeoIP to get the user's country. If everything fails, it
		 * sets the country code to ZZ to denote 'Unknown or unspecified country,' aka
		 * a country we can't detect.
		 *
		 * @since 1.0
		*/
		public function eu_get_user_country() {

			if (function_exists('geoip_country_code_by_name')) {
				// If you have GeoIP installed, it's much easier: http://php.net/manual/en/book.geoip.php
				$this_country = geoip_country_code_by_name( $this->eu_get_user_ip() );
			} elseif ( file_exists( WP_CONTENT_DIR . '/edd-pec-geoip/GeoLite2-Country.mmdb' ) ) {
				try {
					$reader = new Reader( WP_CONTENT_DIR . '/edd-pec-geoip/GeoLite2-Country.mmdb' );
					$record = $reader->country( $this->eu_get_user_ip() );
					$this_country = $record->country->isoCode;
				} catch (Exception $e) {
					// If the IP isn't listed here, we have to do this
					$this_country = "ZZ";
				}
			} else {
				// Otherwise we use HostIP.info which is GPL
				try {
					// Setting timeout limit to speed up sites
					$context = stream_context_create(
						array(
					    	'http' => array(
					    		'timeout' => 1,
							),
						)
					);

					// Using @file... to supress errors
					$this_country = @file_get_contents('http://api.hostip.info/country.php?ip=' . $this->eu_get_user_ip(), false, $context);

				} catch (Exception $e) {
					// If the API isn't available, set to ZZ
					$this_country = "ZZ";
				}
			}

			if ( is_null( $this_country ) || empty( $this_country ) ) {
				// If nothing got set for whatever reason, we force ZZ
				$this_country = "ZZ";
			}

			return $this_country;
		}

		/**
		 * Check the country on the billing address
		 *
		 * This is an idiot walk. If someone claims not to be EU (i.e. checks
		 * the box) but puts in an EU country in the billing info, they should
		 * be stopped.
		 *
		 * @since 1.2
		*/
		public function eu_get_billing_country() {

			$user_address = edd_get_customer_address();

			if( empty( $country ) ) {
				if( ! empty( $_POST['billing_country'] ) ) {
					$country = sanitize_text_field( $_POST['billing_country'] );
				} elseif( is_user_logged_in() && ! empty( $user_address ) ) {
					$country = $user_address['country'];
				}
				$country = ! empty( $country ) ? $country : edd_get_shop_country();
			}
			
			return $country;
		}

		/**
		 * Jan 1 2015 or later?
		 *
		 * Checks to make sure it's time to envoke this plugin.
		 * Keeping this in case the law changes and we need to disable.
		 *
		 * @since 1.0
		*/
		public function eu_get_dates() {
			$baddates = FALSE;

			if( strtotime("01/01/2015") <= time() ) {
				$baddates = TRUE;
			}
			return $baddates;
		}

		/**
		 * Check if restrictions need to be applied
		 *
		 * Returns true if the following are also true:
		 *
		 * 1) Checkbox is checked
		 * 2) Dates are within the VAT range of applicability
		 * 3) User's detected country is NOT excluded
		 * 4) User's detected country IS on the list
		 *
		 * @since 1.0
		*/
		public function block_eu_required() {

			global $edd_options;

			$canblock = FALSE;

			if (
				$this->eu_get_running() == TRUE &&
				$this->eu_get_dates() == TRUE &&
				$this->eu_get_user_country() != $edd_options['edd_pceu_exclude'] &&
				array_key_exists( $this->eu_get_user_country(), $this->eu_get_country_list() )
			) {
				$canblock = TRUE;
			}

			return $canblock;
		}

		/**
		 * Can checkout
		 *
		 * Prevents the form from being displayed at all until the user's current
		 * physical location is outside the EU.
		 *
		 * @since 1.0
		*/
		public function can_checkout( $can_checkout  ) {

			$can_checkout = FALSE;

			if ( $this->block_eu_required() !== TRUE ) {
				$can_checkout = TRUE;
			}

			return $can_checkout;
		}

		/**
		 * Checkout error message
		 *
		 * Set the error message for checkout. This can be filtered!
		 *
		 * @since 1.0
		*/
		public function set_checkout_error() {

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
		 * Set Dowloads Message
		 *
		 * Conditionally add a message to content if [downloads] is loaded.
		 *
		 * @param  string  $content
		 * @return string
		 *
		 * @since 1.0
		*/
		public function set_downloads_message( $content ) {

			global $edd_options;

			$error = '<div class="edd_errors"><p class="edd_error" id="edd_error_no_eu">'.$edd_options['edd_pceu_general_message'].'</p></div>';

			if (
				$this->block_eu_required() == TRUE &&
				( is_singular( 'download' ) || has_shortcode( $content, 'downloads' ) || has_shortcode( $content, 'purchase_link' ) )
			) {
				return $error . $content;
			} else {
				return $content;
			}
		}

		/**
		 * Customize purchase button
		 *
		 * In order to prevent the Buy Now stuff from working, we're going to go
		 * hard core and just block it entirely.
		 *
		 * @since 1.0.4
		*/
		public function prevent_purchase_button( $content, $args) {

			global $edd_options;

			if ( $this->block_eu_required() == TRUE ) {
				$content = '<p><a href="#" class="button '. $args['color'] .' edd-submit">'. $edd_options['edd_pceu_button_message'] .'</a></p>';
			}

			if ( $this->eu_get_user_country() == "ZZ" && $args['direct'] != FALSE ) {
				$content = '<p><a href="#" class="button '. $args['color'] .' edd-submit">'. $edd_options['edd_pceu_button_message'] .'</a></p>';
			}

			return $content;
		}

		/**
		 * Custom Checkout Field
		 *
		 * A confirmation box. In the event someone made it all the way through IP checks
		 * we STILL need to cover our damn asses and make sure they're not really in the
		 * EU, so we put the onus on them to confirm 'I confirm I do not reside in the EU.'
		 *
		 * @since 1.0
		*/
		public function custom_checkout_fields() {

			// If the plugin is running and the dates are okay
			if ( $this->eu_get_running() == TRUE && $this->eu_get_dates() == TRUE ) {

				global $edd_options;

				?>
				<p id='edd-eu-wrap'>
					<label class='edd-label' for='edd-eu'><?php _e('EU VAT Compliance Confirmation', 'edd-prevent-eu-checkout', 'edd-prevent-eu-checkout'); ?></label>
					<span class='edd-description'><input class='edd-checkbox' type='checkbox' name='edd_eu' id='edd-eu' value='1' /> <?php _e($edd_options['edd_pceu_checkbox_message']); ?></span>
				</p>
				<?php
			}
		}

		/**
		 * Custom Checkout Field Sanitization
		 *
		 * This does one last check. If they're dumb enough to put an EU
		 * country in their bulling address, eu_get_billing_country() will get them.
		 *
		 * @since 1.0
		*/
		public function validate_custom_fields($valid_data, $data) {

			if ( $this->eu_get_running() == TRUE && $this->eu_get_dates() == TRUE ) {
				global $edd_options;

				if ( 
					!isset( $data['edd_eu'] ) || 
					$data['edd_eu'] != '1' ||
					( 
						$this->eu_get_billing_country() != $edd_options['edd_pceu_exclude'] &&
						array_key_exists( $this->eu_get_billing_country(), $this->eu_get_country_list() )
					)
				) {
					$data['edd_eu'] = 0;
					edd_set_error( 'eu_not_checked', apply_filters( 'edd_pceu_error_message', $edd_options['edd_pceu_checkout_message'] ) );
				} else {
					$data['edd_eu'] = 1;
				}
			}
		}

		/**
		 * Settings
		 *
		 * @since 1.0
		*/
		public function settings( $settings ) {

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
					'desc' => __( 'Will be displayed at the top of every page where downloads are shown. (HTML accepted)', 'edd-prevent-eu-checkout' ),
					'type' => 'textarea',
					'std' => 'At this time we are unable to complete sales to EU residents. <a href="#">Why?</a>'
				),

				array(
					'id' => 'edd_pceu_button_message',
					'name' => __( 'Button Content', 'edd-prevent-eu-checkout' ),
					'desc' => __( '<br />Will be displayed in lieu of "Add to Cart" or "Buy Now" buttons. Keep it short.', 'edd-prevent-eu-checkout' ),
					'type' => 'text',
					'std' => 'Purchase unavailable in your country'
				),

				array(
					'id' => 'edd_pceu_checkout_message',
					'name' => __( 'Checkout Message', 'edd-prevent-eu-checkout' ),
					'desc' => __( 'Will be displayed on attempt to checkout. (HTML accepted)', 'edd-prevent-eu-checkout' ),
					'type' => 'textarea',
					'std' => 'At this time we are unable to complete sales to EU residents. <a href="#">Why?</a>'
				),

				array(
					'id' => 'edd_pceu_checkbox_message',
					'name' => __( 'Checkbox Alert Message', 'edd-prevent-eu-checkout' ),
					'desc' => __( 'Will be displayed below a confirmation checkbox. (HTML accepted)', 'edd-prevent-eu-checkout' ),
					'type' => 'textarea',
					'std' => 'By checking this box you confirm you are either a business or not a legal EU resident.'
				),

				array(
					'id' => 'edd_pceu_exclude',
					'name' => __( 'Exclude Country', 'edd-prevent-eu-checkout' ),
					'desc' => __( '<br />If sales are permitted from your own country, select it from this dropdown.', 'edd-prevent-eu-checkout' ),
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
		public function sanitize_settings( $input ) {

			// Sanitize checkbox
			if ( ! isset( $input['edd_pceu_checkbox'] ) || $input['edd_pceu_checkbox'] != '1' ) {
				$input['edd_pceu_checkbox'] = 0;
			} else {
				$input['edd_pceu_checkbox'] = 1;
			}

			// Sanitize edd_pceu_general_message
			$input['edd_pceu_general_message'] = wp_kses_post( $input['edd_pceu_general_message'] );

			// Sanitize edd_pceu_button_message
			$input['edd_pceu_button_message'] = sanitize_text_field( $input['edd_pceu_button_message'] );

			// Sanitize edd_pceu_checkout_message
			$input['edd_pceu_checkout_message'] = wp_kses_post( $input['edd_pceu_checkout_message'] );

			// Sanitize edd_pceu_checkbox_message
			$input['edd_pceu_checkbox_message'] = wp_kses_post( $input['edd_pceu_checkbox_message'] );
			
			// Sanitize edd_pceu_exclude
			$input['edd_pceu_exclude'] = sanitize_text_field( $input['edd_pceu_exclude'] );

			// Validate edd_pceu_exclude
			if ( in_array($input['edd_pceu_exclude'], $this->eu_get_country_list()) || array_key_exists($input['edd_pceu_exclude'], $this->eu_get_country_list()) ) {
				$input['edd_pceu_exclude'] = $input['edd_pceu_exclude'];
			} else {
				//$input['edd_pceu_exclude'] = null;
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

add_action( 'plugins_loaded', 'edd_prevent_eu_checkout_load' );

function edd_prevent_eu_checkout_load() {
	$edd_prevent_checkout = new EDD_Prevent_EU_Checkout();
}