<?php
/*
Plugin Name: OTP Authenticator
Plugin URI: https://github.com/froger-me/otp-authenticator
Description: One-Time Password Authentication: add Two-Factor Authentication, Passwordless Authentication and Account Validation to your WordPress website
Version: 1.0
Author: Alexandre Froger
Author URI: https://froger.me/
Text Domain: otpa
Domain Path: /languages
*/

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

if ( ! defined( 'OTPA_PLUGIN_PATH' ) ) {
	define( 'OTPA_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );
}

if ( ! defined( 'OTPA_PLUGIN_URL' ) ) {
	define( 'OTPA_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
}

require_once OTPA_PLUGIN_PATH . 'inc/class-otpa-logger.php';
require_once OTPA_PLUGIN_PATH . 'inc/class-otpa.php';

register_activation_hook( __FILE__, array( 'Otpa', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'Otpa', 'deactivate' ) );
register_uninstall_hook( __FILE__, array( 'Otpa', 'uninstall' ) );

function otpa_run() {
	require_once OTPA_PLUGIN_PATH . 'inc/class-otpa-account-validation.php';
	require_once OTPA_PLUGIN_PATH . 'inc/class-otpa-2fa.php';
	require_once OTPA_PLUGIN_PATH . 'inc/class-otpa-passwordless.php';
	require_once OTPA_PLUGIN_PATH . 'functions.php';
	require_once OTPA_PLUGIN_PATH . 'inc/gateways/class-otpa-abstract-gateway.php';
	require_once OTPA_PLUGIN_PATH . 'inc/class-otpa-settings.php';
	require_once OTPA_PLUGIN_PATH . 'inc/class-otpa-style-settings.php';
	require_once OTPA_PLUGIN_PATH . 'inc/integration/class-otpa-integration.php';

	do_action( 'otpa_init' );

	$otpa_objects = array( 'logger' => new Otpa_Logger( true ) );

	Otpa_Integration::init();

	foreach ( glob( OTPA_PLUGIN_PATH . 'inc/gateways/*.php' ) as $filepath ) {

		if ( 'class-otpa-abstract-gateway.php' === basename( $filepath ) ) {

			continue;
		}

		require $filepath;

		$class_name = Otpa_Abstract_Gateway::get_gateway_class_name( basename( $filepath ), 'filename' );

		add_filter( 'otpa_authentication_gateways', array( $class_name, 'register_authentication_gateway' ), 10, 1 );
	}

	if ( is_admin() ) {
		require_once OTPA_PLUGIN_PATH . 'inc/class-otpa-settings-renderer.php';

		$gateway_class                             = otpa_get_active_gateway_class_name();
		$otpa_objects['settings_renderer']         = new Otpa_Settings_Renderer();
		$otpa_objects['settings']                  = new Otpa_Settings( true, $otpa_objects['settings_renderer'] );
		$otpa_objects['gateway_settings_renderer'] = new Otpa_Settings_Renderer();
		$otpa_objects['gateway']                   = new $gateway_class(
			true,
			$otpa_objects['gateway_settings_renderer']
		);
	} else {
		$gateway_class            = otpa_get_active_gateway_class_name();
		$otpa_objects['settings'] = new Otpa_Settings( true );
		$otpa_objects['gateway']  = new $gateway_class( true );
	}

	if ( $otpa_objects['settings']->validate() ) {
		do_action( 'otpa_loaded', $otpa_objects );

		$otpa_objects['otpa'] = new Otpa( $otpa_objects['settings'], $otpa_objects['gateway'], true );

		if ( is_admin() ) {
			$otpa_objects['style_settings_renderer'] = new Otpa_Settings_Renderer();
			$otpa_objects['style_settings']          = new Otpa_Style_Settings(
				$otpa_objects['otpa'],
				$otpa_objects['style_settings_renderer'],
				true
			);
		} else {
			$otpa_objects['style_settings'] = new Otpa_Style_Settings( $otpa_objects['otpa'], false, true );
		}

		$otpa_validation_enabled                 = (bool) $otpa_objects['settings']->get_option( 'enable_validation' );
		$otpa_2fa_enabled                        = (bool) $otpa_objects['settings']->get_option( 'enable_2fa' );
		$otpa_passwordless_enabled               = (bool) $otpa_objects['settings']->get_option( 'enable_passwordless' );
		$otpa_objects['otpa_account_validation'] = new Otpa_Account_Validation(
			$otpa_objects['otpa'],
			$otpa_validation_enabled
		);
		$otpa_objects['otpa_2fa']                = new Otpa_2FA(
			$otpa_objects['otpa'],
			$otpa_2fa_enabled
		);
		$otpa_objects['otpa_passwordless']       = new Otpa_Passwordless(
			$otpa_objects['otpa'],
			$otpa_passwordless_enabled
		);

		if ( $otpa_validation_enabled || $otpa_2fa_enabled || $otpa_passwordless_enabled ) {
			require_once OTPA_PLUGIN_PATH . 'inc/class-otpa-user-info.php';

			$otpa_objects['user_info'] = new Otpa_User_Info( $otpa_objects['settings'], true );
		}

		do_action( 'otpa_ready', $otpa_objects );
	} else {
		do_action( 'otpa_invalid_settings', $otpa_objects );
	}
}
add_action( 'plugins_loaded', 'otpa_run', 10, 0 );
