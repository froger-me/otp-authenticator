<?php
/**
 * Fixed settings used by OTP Authenticator PHP tests.
 */

final class Otpa_Test_Settings {
	/**
	 * Test settings.
	 *
	 * @var array<string, mixed>
	 */
	private $settings;

	/**
	 * Create a complete settings collection with optional overrides.
	 *
	 * @param array<string, mixed> $overrides Settings to override.
	 */
	public function __construct( $overrides = array() ) {
		$this->settings = array_merge(
			array(
				'block_expiry'             => 5,
				'default_2fa'               => false,
				'enable_2fa'                => false,
				'enable_passwordless'       => true,
				'enable_validation'         => false,
				'force_2fa'                 => false,
				'max_request'               => 10,
				'max_verify'                => 10,
				'otp_expiry'                => 5,
				'request_frequency'         => 0,
				'sandbox'                   => true,
				'track_expiry'              => 24,
				'validation_exclude_roles'  => array(),
				'validation_expiry'         => -1,
			),
			$overrides
		);
	}

	/**
	 * Return one setting.
	 *
	 * @param string $key     Setting name.
	 * @param mixed  $default Value returned when the setting is absent.
	 * @return mixed
	 */
	public function get_option( $key, $default = false ) {
		return array_key_exists( $key, $this->settings ) ? $this->settings[ $key ] : $default;
	}
}
