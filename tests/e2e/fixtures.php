<?php
/**
 * Repeatable browser-test data for OTP Authenticator.
 */

$otpa_e2e_direct_request = ! defined( 'ABSPATH' );

if ( $otpa_e2e_direct_request ) {
	require_once dirname( __DIR__, 5 ) . '/wp-load.php';

	if (
		! defined( 'ANYAPE_WP_TEST_TOOLS_E2E' ) ||
		! ANYAPE_WP_TEST_TOOLS_E2E ||
		! is_user_logged_in() ||
		! current_user_can( 'manage_options' ) ||
		'POST' !== strtoupper( isset( $_SERVER['REQUEST_METHOD'] ) ? $_SERVER['REQUEST_METHOD'] : '' )
	) {
		wp_send_json_error(
			array( 'message' => 'Browser-test fixture preparation is unavailable.' ),
			403
		);
	}
}

$otpa_e2e_users = array(
	array(
		'user_login'   => 'otpa_passwordless_e2e',
		'user_pass'    => 'OTPA-Test-Password-2026!',
		'user_email'   => 'otpa-passwordless@example.test',
		'display_name' => 'OTP Passwordless Test User',
		'role'         => 'subscriber',
	),
	array(
		'user_login'   => 'otpa_two_factor_e2e',
		'user_pass'    => 'OTPA-Test-Password-2026!',
		'user_email'   => 'otpa-two-factor@example.test',
		'display_name' => 'OTP Two Factor Test User',
		'role'         => 'subscriber',
	),
	array(
		'user_login'   => 'otpa_validation_e2e',
		'user_pass'    => 'OTPA-Test-Password-2026!',
		'user_email'   => 'otpa-validation@example.test',
		'display_name' => 'OTP Validation Test User',
		'role'         => 'subscriber',
	),
);

foreach ( $otpa_e2e_users as $otpa_e2e_user_data ) {
	$otpa_e2e_user_id = username_exists( $otpa_e2e_user_data['user_login'] );

	if ( $otpa_e2e_user_id ) {
		$otpa_e2e_user_data['ID'] = $otpa_e2e_user_id;
		wp_update_user( $otpa_e2e_user_data );
	} else {
		$otpa_e2e_user_id = wp_insert_user( $otpa_e2e_user_data );
	}

	if ( is_wp_error( $otpa_e2e_user_id ) ) {
		throw new RuntimeException( $otpa_e2e_user_id->get_error_message() );
	}

	update_user_meta( $otpa_e2e_user_id, 'otpa_mail', $otpa_e2e_user_data['user_email'] );
	delete_user_meta( $otpa_e2e_user_id, 'otpa_verification_code' );
	delete_user_meta( $otpa_e2e_user_id, 'otpa_otp_attempts' );
	delete_user_meta( $otpa_e2e_user_id, 'otpa_2fa_checked' );
	delete_user_meta( $otpa_e2e_user_id, 'otpa_need_2fa_check' );
	delete_user_meta( $otpa_e2e_user_id, 'otpa_force_account_validation' );
	delete_user_meta( $otpa_e2e_user_id, 'otpa_account_validation_redirect' );

	if ( 'otpa_two_factor_e2e' === $otpa_e2e_user_data['user_login'] ) {
		update_user_meta( $otpa_e2e_user_id, 'otpa_2fa_active', true );
		update_user_meta(
			$otpa_e2e_user_id,
			'otpa_validated',
			array(
				'status'            => true,
				'validation_expiry' => 0,
			)
		);
		delete_user_meta( $otpa_e2e_user_id, 'otpa_need_account_validation' );
	} elseif ( 'otpa_validation_e2e' === $otpa_e2e_user_data['user_login'] ) {
		update_user_meta( $otpa_e2e_user_id, 'otpa_2fa_active', false );
		delete_user_meta( $otpa_e2e_user_id, 'otpa_validated' );
		update_user_meta( $otpa_e2e_user_id, 'otpa_need_account_validation', true );
	} else {
		update_user_meta( $otpa_e2e_user_id, 'otpa_2fa_active', false );
		update_user_meta(
			$otpa_e2e_user_id,
			'otpa_validated',
			array(
				'status'            => true,
				'validation_expiry' => 0,
			)
		);
		delete_user_meta( $otpa_e2e_user_id, 'otpa_need_account_validation' );
	}
}

$otpa_e2e_administrator = get_user_by( 'login', 'anyape_wp_test_tools_e2e_admin' );
if ( $otpa_e2e_administrator instanceof WP_User ) {
	update_user_meta( $otpa_e2e_administrator->ID, 'otpa_mail', $otpa_e2e_administrator->user_email );
	update_user_meta( $otpa_e2e_administrator->ID, 'otpa_twilio_phone', '+12025550123' );
	update_user_meta( $otpa_e2e_administrator->ID, 'otpa_aliyun_phone', '+8613812345678' );
}

update_option(
	'otpa_settings',
	array(
		'gateway_id'               => 'otpa_wp_email_gateway',
		'enable_2fa'               => true,
		'force_2fa'                => false,
		'default_2fa'              => false,
		'enable_passwordless'      => true,
		'enable_validation'        => true,
		'validation_expiry'        => -1,
		'validation_exclude_roles' => array( 'administrator' ),
		'sandbox'                  => false,
		'otp_expiry'               => 5,
		'request_frequency'        => 0,
		'max_request'              => 10,
		'max_verify'               => 10,
		'track_expiry'             => 24,
		'block_expiry'             => 5,
	)
);
update_option(
	'otpa_wp_email_gateway_settings',
	array(
		'subject' => 'OTP Authenticator browser test',
		'message' => 'OTP test code: ###CODE### Recipient: ###EMAIL###',
	)
);
wp_cache_flush();
set_transient( 'otpa_flush', 1, MINUTE_IN_SECONDS );

if ( $otpa_e2e_direct_request ) {
	wp_send_json_success(
		array( 'message' => 'OTP Authenticator browser fixtures are ready.' )
	);
}
