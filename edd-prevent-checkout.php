<?php
/*
Plugin Name: EDD - Prevent Checkout for the EU
Plugin URI: 
Description: Prevents customer from being able to checkout until a minimum cart total is reached
Version: 1.0
Author: Andrew Munro, Sumobi
Author URI: http://sumobi.com/
License: GPL-2.0+
License URI: http://www.opensource.org/licenses/gpl-license.php

Forked from http://sumobi.com/shop/edd-prevent-eu-checkout/
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

			do_action( 'edd_pc_setup_actions' );

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
		 * Check if restrictions need to be applied
		 *
		 * @since 1.0
		*/
		function block_eu_required() {
			global $edd_options;

			$checkbox = isset( $edd_options['edd_pc_checkbox'] ) ? $edd_options['edd_pc_checkbox'] : '';
			
			$ip_check = $this->ip_validation();

			if ( $checkbox == TRUE && $ip_check == TRUE ) {
				$block_eu = TRUE;
			} else {
				$block_eu = FALSE;
			}

			return $block_eu;
		}

		/**
		 * Set error message
		 *
		 * @since 1.0
		*/
		function set_error() {
			
			global $edd_options;

			if ( $this->block_eu_required() == TRUE ) {
				edd_set_error( 'eu_not_allowed', apply_filters( 'edd_pc_error_message', $edd_options['edd_pc_checkout_message'] ) );
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
		
			if ( $this->block_eu_required() == TRUE && $this->ip_validation() == TRUE ) {
				$can_checkout = false;
			}

			return $can_checkout;
		}

		/**
		 * Validate IP to see if it's European
		 *
		 * @since 1.0
		*/
		function ip_validation( ) {
			
			global $edd_options;
			
			//$countries = "Austria, Belgium, Bulgaria, Croatia, Republic of Cyprus, Czech Republic, Denmark, Estonia, Finland, France, Germany, Greece, Hungary, Ireland, Italy, Latvia, Lithuania, Luxembourg, Malta, Netherlands, Poland, Portugal, Romania, Slovakia, Slovenia, Spain, Sweden, UK";
			
			$countries = array("AT"=>"Austria","BE"=>"Belgium","BG"=>"Bulgaria","HR"=>"Croatia","CY"=>"Republic of Cyprus","CZ"=>"Czech Republic","DK"=>"Denmark","EE"=>"Estonia","FI"=>"Finland","FR"=>"France","DE"=>"Germany","GR"=>"Greece","HU"=>"Hungary","IE"=>"Ireland","IT"=>"Italy","LV"=>"Latvia","LT"=>"Lithuania","LU"=>"Luxembourg","MT"=>"Malta","NL"=>"Netherlands","PL"=>"Poland","PT"=>"Portugal","RO"=>"Romania","SK"=>"Slovakia","SI"=>"Slovenia","ES"=>"Spain","SE"=>"Sweden", "GB"=>"United Kingdom");

			if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
				$ip=$_SERVER['HTTP_CLIENT_IP'];
			} elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
				$ip=$_SERVER['HTTP_X_FORWARDED_FOR'];
		    } else {
				$ip=$_SERVER['REMOTE_ADDR'];
    		}
		
			$this_country = geoip_country_code_by_name( $ip );
			
			if ( in_array( $this_country, $countries ) && !in_array(  $edd_options['edd_pc_exclude'], $countries ) ) {
				$ip_check = TRUE;
			} else {
				$ip_check = FALSE;
			}
			
			return $ip_check;
			
		}

		/**
		 * Settings
		 *
		 * @since 1.0
		*/
		function settings( $settings ) {

		  $edd_pc_settings = array(
				array(
					'id' => 'edd_pc_header',
					'name' => '<strong>' . __( 'Prevent EU Checkout', 'edd-prevent-eu-checkout' ) . '</strong>',
					'type' => 'header'
				),

				array(
					'id' => 'edd_pc_checkbox',
					'name' => __( 'Enable Blocking of EU Sales', 'edd-prevent-eu-checkout' ),
					'desc' => __( 'Check this box to prevent EU customers from completing checkout.', 'edd-prevent-eu-checkout' ),
					'type' => 'checkbox',
					'std' => ''
				),

				array(
					'id' => 'edd_pc_general_message',
					'name' => __( 'General Message', 'edd-prevent-eu-checkout' ),
					'desc' => __( 'Will be displayed at the top of every page where [downloads] is used.', 'edd-prevent-eu-checkout' ),
					'type' => 'textarea',
					'std' => 'At this time we are unable to complete sales to EU residents. <a href="#">Why?</a>'
				),

				array(
					'id' => 'edd_pc_checkout_message',
					'name' => __( 'Checkout Message', 'edd-prevent-eu-checkout' ),
					'desc' => __( 'Will be displayed on attempt to checkout by someone in the EU.', 'edd-prevent-eu-checkout' ),
					'type' => 'textarea',
					'std' => 'At this time we are unable to complete sales to EU residents. <a href="#">Why?</a>'
				),

				array(
					'id' => 'edd_pc_exclude',
					'name' => __( 'Exclude Country', 'edd-prevent-eu-checkout' ),
					'desc' => __( 'If sales are permitted from your own country, select it from this dropdown.', 'edd-prevent-eu-checkout' ),
					'type' => 'select',
					'options' => edd_get_country_list()
				),
			);

			return array_merge( $settings, $edd_pc_settings );
		}

		/**
		 * Sanitize settings
		 *
		 * @since 1.0
		*/
		function sanitize_settings( $input ) {

			// only allow number, eg 10 or 10.00
			$input['edd_pc_checkbox'] = is_numeric( $input['edd_pc_checkbox'] ) ? $input['edd_pc_checkbox'] : '';

			return $input;
		}
		
	}

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