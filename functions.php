<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/**
 * Get the current URL
 *
 * @return string the current URL
 */
function otpa_get_current_url() {
	return home_url( $_SERVER['REQUEST_URI'] );
}

/**
 * Get the currently active Authentication Gateway class name
 *
 * @return string the currently active Authentication Gateway class name
 */
function otpa_get_active_gateway_class_name() {
	return Otpa_Abstract_Gateway::get_gateway_class_name( Otpa_Settings::get_current_gateway_id() );
}

/**
 * Generate a One-Time Password code
 *
 * @param int $length The OTP Code length
 * @param string  $chars The characters to randomly choose from when generating the code
 * @return string the generated OTP Code
 */
function otpa_generate_otp_code( $length, $chars ) {
	$code = '';

	for ( $i = 0; $i < $length; $i++ ) {
		$code .= substr( $chars, wp_rand( 0, strlen( $chars ) - 1 ), 1 );
	}

	return $code;
}

/**
 * Mask an email address with "*"
 *
 * @param string  $email The email address to mask
 * @param int $username_char_front The number of leading characters to leave unmasked for the username part of the email address
 * @param int $username_char_back The number of trailing characters to leave unmasked for the username part of the email address
 * @param int $domain_char_front The number of leading characters to leave unmasked for the domain part of the email address
 * @param int $domain_char_back The number of trailing characters to leave unmasked for the domain part of the email address
 * @return string the masked email
 */
function otpa_mask_email(
	$email,
	$username_char_front = 1,
	$username_char_back = 1,
	$domain_char_front = 1,
	$domain_char_back = 1
) {
	$mail_parts    = explode( '@', $email );
	$mail_parts[0] = otpa_mask_string( $mail_parts[0], $username_char_front, $username_char_back );
	$domain_parts  = explode( '.', $mail_parts[1] );
	$domain_tld    = end( $domain_parts );

	array_pop( $domain_parts );

	$domain_name   = implode( '.', $domain_parts );
	$mail_parts[1] = otpa_mask_string( $domain_name, $domain_char_front, $domain_char_back ) . '.' . $domain_tld;

	return implode( '@', $mail_parts );
}

/**
 * Mask a phone number with "*"
 *
 * @param string  $phone The phone number to mask
 * @param int $char_front The number of leading characters to leave unmasked
 * @param int $char_back The number of trailing characters to leave unmasked
 * @param string $prefix The phone prefix (left unmasked)
 * @return string the masked email
 */
function otpa_mask_phone( $phone, $char_front = 3, $char_back = 3, $prefix = '' ) {
	return $prefix . otpa_mask_string( str_replace( $prefix, '', $phone ), $char_front, $char_back );
}

/**
 * Mask a string with "*"
 *
 * @param string  $string The string to mask
 * @param int $char_front The number of leading characters to leave unmasked
 * @param int $char_back The number of trailing characters to leave unmasked
 * @return string the masked string
 */
function otpa_mask_string( $string, $char_front = 1, $char_back = 1 ) {
	$len = strlen( $string );

	if ( $len <= $char_front || $len <= $char_front || $len === $char_front + $char_back ) {
		$char_front = 0;
		$char_back  = 0;
	}

	$masked_string  = substr( $string, 0, $char_front );
	$masked_string .= str_repeat( '*', $len - $char_front - $char_back );
	$masked_string .= substr( $string, $len - $char_back, $char_back );

	return $masked_string;
}

/**
 * Adjust a hex color brightness
 *
 * @param string $hex Hex color e.g. #111111.
 * @param int $steps Factor by which to brighten/darken ranging from -255 (darken) to 255 (brighten).
 * @return string brightened/darkened hex color
 */
function otpa_adjust_color_brightness( $hex, $steps ) {
	$steps = max( -255, min( 255, $steps ) );
	$hex   = str_replace( '#', '', $hex );

	if ( 3 === strlen( $hex ) ) {
		$hex  = str_repeat( substr( $hex, 0, 1 ), 2 );
		$hex .= str_repeat( substr( $hex, 1, 1 ), 2 );
		$hex .= str_repeat( substr( $hex, 2, 1 ), 2 );
	}

	$r     = hexdec( substr( $hex, 0, 2 ) );
	$g     = hexdec( substr( $hex, 2, 2 ) );
	$b     = hexdec( substr( $hex, 4, 2 ) );
	$r     = max( 0, min( 255, $r + $steps ) );
	$g     = max( 0, min( 255, $g + $steps ) );
	$b     = max( 0, min( 255, $b + $steps ) );
	$r_hex = str_pad( dechex( $r ), 2, '0', STR_PAD_LEFT );
	$g_hex = str_pad( dechex( $g ), 2, '0', STR_PAD_LEFT );
	$b_hex = str_pad( dechex( $b ), 2, '0', STR_PAD_LEFT );

	return '#' . $r_hex . $g_hex . $b_hex;
}

/**
 * Minify a CSS string (imperfect)
 *
 * @param string $css The CSS to minify
 * @see https://datayze.com/howto/minify-css-with-php
 * @return string the minified CSS
 */
function otpa_simple_minify_css( $css ) {
	$css = preg_replace( '/\/\*((?!\*\/).)*\*\//', '', $css ); // negative look ahead
	$css = preg_replace( '/\s{2,}/', ' ', $css );
	$css = preg_replace( '/\s*([:;{}])\s*/', '$1', $css );
	$css = preg_replace( '/;}/', '}', $css );

	return $css;
}

/**
 * Convert a timestamp into a human-readable duration in hours, minutes, seconds.
 * Hours, minutes and seconds are included in the final string only if not 0.
 *
 * @param int $timestamp The timestamp to convert
 * @return string the human-readable duration
 */
function otpa_human_timing( $timestamp ) {
	$now     = time();
	$diff    = $timestamp - $now;
	$hours   = floor( $diff / 3600 );
	$minutes = floor( ( $diff / 60 ) % 60 );
	$seconds = $diff % 60;

	if ( 0 < $hours && 0 < $minutes && 0 < $seconds ) {
		$timing = sprintf(
			sprintf(
				// translators: %1$s hours, %2$s minutes and %2$s seconds
				__( '%1$s, %2$s and %3$s', 'otpa' ),
				// translators: %s number of hours
				_n( '%s hour', '%s hours', $hours, 'otpa' ),
				// translators: %s number of minutes
				_n( '%s minute', '%s minutes', $minutes, 'otpa' ),
				// translators: %s number of seconds
				_n( '%s second', '%s seconds', $seconds, 'otpa' )
			),
			$hours,
			$minutes,
			$seconds
		);
	} elseif ( 0 < $hours && 0 < $minutes ) {
		$timing = sprintf(
			sprintf(
				// translators: %1$s hours and %2$s minutes
				__( '%1$s and %2$s', 'otpa' ),
				// translators: %s number of hours
				_n( '%s hour', '%s hours', $hours, 'otpa' ),
				// translators: %s number of minutes
				_n( '%s minute', '%s minutes', $minutes, 'otpa' )
			),
			$hours,
			$minutes
		);
	} elseif ( 0 < $minutes && 0 < $seconds ) {
		$timing = sprintf(
			sprintf(
				// translators: %1$s minutes and %2$s seconds
				__( '%1$s and %2$s', 'otpa' ),
				// translators: %s number of minutes
				_n( '%s minute', '%s minutes', $minutes, 'otpa' ),
				// translators: %s number of seconds
				_n( '%s second', '%s seconds', $seconds, 'otpa' )
			),
			$minutes,
			$seconds
		);
	} elseif ( 0 < $hours && 0 < $seconds ) {
		$timing = sprintf(
			sprintf(
				// translators: %1$s hours and %2$s seconds
				__( '%1$s and %2$s', 'otpa' ),
				// translators: %s number of hours
				_n( '%s hour', '%s hours', $hours, 'otpa' ),
				// translators: %s number of seconds
				_n( '%s second', '%s seconds', $seconds, 'otpa' )
			),
			$hours,
			$seconds
		);
	} elseif ( 0 < $hours ) {
		// translators: %s number of hours
		$timing = sprintf( _n( '%s hour', '%s hours', $hours, 'otpa' ), $hours );
	} elseif ( 0 < $minutes ) {
		// translators: %s number of minutes
		$timing = sprintf( _n( '%s minute', '%s minutes', $minutes, 'otpa' ), $minutes );
	} elseif ( 0 < $seconds ) {
		// translators: %s number of seconds
		$timing = sprintf( _n( '%s second', '%s seconds', $seconds, 'otpa' ), $seconds );
	}

	return $timing;
}

/**
 * Log an expression with optional information in log file
 *
 * @param mixed $expression The expression to log
 * @param string $extended Additional information - default empty
 */
function otpa_log( $expression, $extend = '' ) {
	Otpa_Logger::log( $expression, $extend );
}

/**
 * Log an expression in database
 *
 * @param mixed $expression The expression to log
 * @param string $log_level The log severity level - one of 'info', 'success', 'warning', 'alert' - default 'info'
 * @param string $force Whether to force the log when logging is disabled - default false
 */
function otpa_db_log( $expression, $log_level = 'info', $force = false ) {
	Otpa_Logger::log( $expression, $log_level, 'db_log', $force );
}

/**
 * Log an expression in database
 *
 * @see otpa_db_log
 */
function otpa_dblog( $expression, $log_level = 'info', $force = false ) {
	otpa_db_log( $expression, $log_level, $force );
}

/**
 * Get the account validation information
 *
 * @param int $user_id If falsey, the current user's ID ; the ID of a user otherwise - default false
 * @return array {
 *     An array of account validation information
 *
 *     @type bool $validated Whether the user account is validated
 *     @type int $expiry Timestamp when the account validation expires
 *     @type bool $need_validation Whether the account needs validation a next login
 *     @type bool $force_validation Whether account validation is currently forced even if the user is currently logged in
 *     @type bool $role_excluded Whether the user of the account has a role bypassing validation
 * }
 */
function otpa_get_user_account_validation_info( $user_id = false ) {
	$user                    = ( $user_id ) ? get_user_by( 'ID', $user_id ) : wp_get_current_user();
	$otpa_settings           = new Otpa_Settings();
	$otpa                    = new Otpa( $otpa_settings );
	$otpa_account_validation = new Otpa_Account_Validation( $otpa );
	$expiry                  = $otpa_account_validation->get_user_validation_expiry( $user );

	switch ( $expiry ) {
		case Otpa_Account_Validation::NO_VALIDATION_EXPIRY:
			$expiry = 'identifier_changed';
			break;
		case Otpa_Account_Validation::VALIDATION_LOGOUT_EXPIRY:
			$expiry = 'next_login';
			break;
		default:
			$expiry = ( time() > $expiry ) ? 'next_login' : $expiry;
			break;
	}

	return array(
		'validated'        => $otpa_account_validation->is_user_validated( $user, true ),
		'expiry'           => $expiry,
		'need_validation'  => $otpa_account_validation->need_account_validation( $user ),
		'force_validation' => (bool) get_user_meta( $user->ID, 'otpa_force_account_validation', true ),
		'role_excluded'    => $otpa_account_validation->is_user_validation_excluded( $user->ID ),
	);
}

/**
 * Validate a user account
 *
 * @param int $user_id If falsey, the current user's ID ; the ID of a user otherwise - default false
 */
function otpa_do_account_validation( $user_id = false ) {
	$user                    = ( $user_id ) ? get_user_by( 'ID', $user_id ) : wp_get_current_user();
	$otpa_settings           = new Otpa_Settings();
	$otpa                    = new Otpa( $otpa_settings );
	$otpa_account_validation = new Otpa_Account_Validation( $otpa );

	$otpa_account_validation->validate_account( $user->ID );
}

/**
 * Invalidate a user account
 *
 * @param int $user_id If falsey, the current user's ID ; the ID of a user otherwise - default false
 */
function otpa_reset_account_validation( $user_id = false ) {
	$user                    = ( $user_id ) ? get_user_by( 'ID', $user_id ) : wp_get_current_user();
	$otpa_settings           = new Otpa_Settings();
	$otpa                    = new Otpa( $otpa_settings );
	$otpa_account_validation = new Otpa_Account_Validation( $otpa );

	$otpa_account_validation->set_need_account_validation( $user->user_login, $user, true );
}

/**
 * Check whether the user of the account has a role bypassing validation
 *
 * @param int $user_id If falsey, the current user's ID ; the ID of a user otherwise - default false
 * @return bool whether the user of the account has a role bypassing validation
 */
function otpa_is_user_account_validation_excluded( $user_id = false ) {
	$user                    = ( $user_id ) ? get_user_by( 'ID', $user_id ) : wp_get_current_user();
	$otpa_settings           = new Otpa_Settings();
	$otpa                    = new Otpa( $otpa_settings );
	$otpa_account_validation = new Otpa_Account_Validation( $otpa );

	return $otpa_account_validation->is_user_validation_excluded( $user_id );
}

/**
 * Get the 2fa information
 *
 * @param int $user_id If falsey, the current user's ID ; the ID of a user otherwise - default false
 * @return array {
 *     An array of 2fa information
 *
 *     @type bool $active Whether 2fa is active for the user account
 * }
 */
function otpa_get_user_2fa_info( $user_id = false ) {
	$user          = ( $user_id ) ? get_user_by( 'ID', $user_id ) : wp_get_current_user();
	$otpa_settings = new Otpa_Settings();
	$otpa          = new Otpa( $otpa_settings );
	$otpa_2fa      = new Otpa_2FA( $otpa );

	return array(
		'active' => $otpa_2fa->is_user_2fa_active( $user, true ),
	);
}

/**
 * Set the 2fa status of a user account
 *
 * @param int $user_id If falsey, the current user's ID ; the ID of a user otherwise - default false
 * @param bool $active Whether 2fa should be active for the account
 */
function otpa_set_user_2fa_active( $user_id = false, $active = true ) {
	$user          = ( $user_id ) ? get_user_by( 'ID', $user_id ) : wp_get_current_user();
	$otpa_settings = new Otpa_Settings();
	$otpa          = new Otpa( $otpa_settings );
	$otpa_2fa      = new Otpa_2FA( $otpa );

	$otpa_2fa->set_user_2fa_active( $user, $active );
}

/**
 * Check whether the OTP identifiers are synced with an existing user meta
 *
 * @return bool whether the OTP identifiers are synced with an existing user meta
 */
function otpa_is_identifier_synced() {
	$gateway_class = otpa_get_active_gateway_class_name();
	$gateway       = new $gateway_class( false );

	return ! empty( $gateway->get_option( 'sync_metakey' ) );
}

/**
 * Check whether the OTP gateway is the WordPress User email
 *
 * @return bool whether the OTP gateway is the WordPress User email
 */
function otpa_is_email_gateway() {
	return 'Otpa_WP_Email_Gateway' !== otpa_get_active_gateway_class_name();
}

/**
 * Get a user's OTP identifier
 *
 * @param int $user_id If falsey, the current user's ID ; the ID of a user otherwise - default false
 * @return string the user's identifier
 */
function otpa_get_user_identifier( $user_id = false ) {
	$gateway_class = otpa_get_active_gateway_class_name();
	$gateway       = new $gateway_class( false );

	return $gateway->get_user_identifier( $user_id );
}

/**
 * Get a user by specified OTP identifier
 *
 * @param string $identifier The identifier to get a user by
 * @return bool|WP_User false if no user found, the user oject otherwise
 */
function otpa_get_user_by_identifier( $identifier ) {
	$gateway_class = otpa_get_active_gateway_class_name();
	$gateway       = new $gateway_class( false );

	return $gateway->get_user_by_identifier( $identifier );
}

/**
 * Check whether the specified OTP identifier is a valid identifier
 *
 * @param string the specified OTP identifier
 * @return bool whether the specified OTP identifier is a valid identifier
 */
function otpa_is_valid_identifier( $identifier ) {
	$gateway_class = otpa_get_active_gateway_class_name();
	$gateway       = new $gateway_class( false );

	return $gateway->is_valid_identifier( $identifier );
}

/**
 * Sanitize specified OTP identifier
 *
 * @param string the specified OTP identifier
 * @return string the sanitized OTP identifier
 */
function otpa_sanitize_user_identifier( $identifier ) {
	$gateway_class = otpa_get_active_gateway_class_name();
	$gateway       = new $gateway_class( false );

	return $gateway->sanitize_user_identifier( $identifier );
}

/**
 * Set a user's OTP identifier
 *
 * @param string the OTP identifier
 * @param int the user ID
 * @return bool|string false on failure, the new identifier on success
 */
function otpa_set_user_identifier( $identifier, $user_id ) {
	$gateway_class = otpa_get_active_gateway_class_name();
	$gateway       = new $gateway_class( false );

	return $gateway->set_user_identifier( $identifier, $user_id );
}

/**
 * Check whether the active Authentication Gateway allows to change OTP identifiers
 *
 * @return bool whether the active Authentication Gateway allows to change OTP identifiers
 */
function otpa_gateway_allow_edit_identifier() {
	$gateway_class = otpa_get_active_gateway_class_name();
	$gateway       = new $gateway_class( false );

	return $gateway->allow_edit_identifier();
}

/**
 * Get the plugin's settings
 *
 * @return array {
 *     An array of settings
 *
 *     @type array $general The general settings
 *     @type array $gateway The gateway settings
 *     @type array $style The OTP Form style settings
 * }
 */
function otpa_get_settings() {
	return array(
		'general' => Otpa_Settings::get_options(),
		'gateway' => Otpa_Abstract_Gateway::get_gateway_options( Otpa_Settings::get_current_gateway_id() ),
		'style'   => Otpa_Style_Settings::get_options(),
	);
}
