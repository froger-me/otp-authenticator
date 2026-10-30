<?php
/**
 * Predictable delivery class used by OTP Authenticator PHP tests.
 */

final class Otpa_Test_Gateway {
	/**
	 * Code returned to the plugin's internal saving flow.
	 *
	 * @var string
	 */
	private $code;

	/**
	 * Create the fake delivery class.
	 *
	 * @param string $code Verification code to return.
	 */
	public function __construct( $code = 'ABC123' ) {
		$this->code = $code;
	}

	/**
	 * Return a successful delivery result without sending anything.
	 *
	 * @param string   $identifier    User identifier.
	 * @param callable $error_handler Error callback supplied by the plugin.
	 * @param bool     $sandbox       Whether sandbox delivery was requested.
	 * @return array<string, mixed>
	 */
	public function request_code( $identifier, $error_handler, $sandbox = false ) {
		unset( $identifier, $error_handler, $sandbox );

		return array(
			'status'   => true,
			'message'  => 'Test code sent.',
			'otp_code' => $this->code,
		);
	}

	/**
	 * Find a test user by email address.
	 *
	 * @param string $identifier Email address supplied by the request.
	 * @return WP_User|false
	 */
	public function get_user_by_identifier( $identifier ) {
		return get_user_by( 'email', sanitize_email( $identifier ) );
	}
}
