<?php
/**
 * Tests the passwordless account binding added for OTP Authenticator 1.2.
 */

use AnyapeWPTestTools\IntegrationTestCase;

final class Otpa_Test_Passwordless extends Otpa_Passwordless {
	protected function set_request_cookie( $request_token, $expires ) {
		unset( $expires );
		$_COOKIE[ self::REQUEST_COOKIE ] = $request_token;

		return true;
	}

	protected function set_nonce_session_cookie( $session, $expires ) {
		unset( $expires );
		$this->nonce_session = $session;

		return true;
	}
}

final class PasswordlessSecurityTest extends IntegrationTestCase {
	/** @var int */
	private $requested_user_id;

	/** @var WP_User */
	private $requested_user;

	/** @var WP_User */
	private $different_user;

	/** @var Otpa */
	private $otpa;

	/** @var Otpa_Passwordless */
	private $passwordless;

	protected function setUp(): void {
		parent::setUp();

		$this->requested_user_id = self::factory()->user->create(
			array(
				'user_email'   => 'passwordless-requested@example.test',
				'user_login'   => 'otpa_passwordless_requested',
				'display_name' => 'Requested Passwordless User',
			)
		);
		$different_user_id       = self::factory()->user->create(
			array(
				'user_email'   => 'passwordless-different@example.test',
				'user_login'   => 'otpa_passwordless_different',
				'display_name' => 'Different Passwordless User',
			)
		);
		$this->requested_user    = get_user_by( 'ID', $this->requested_user_id );
		$this->different_user    = get_user_by( 'ID', $different_user_id );
		$this->otpa              = new Otpa( new Otpa_Test_Settings(), new Otpa_Test_Gateway() );
		$this->passwordless      = new Otpa_Test_Passwordless( $this->otpa );

		wp_set_current_user( 0 );
		unset( $_COOKIE[ Otpa_Passwordless::REQUEST_COOKIE ], $_COOKIE[ Otpa_Passwordless::NONCE_SESSION_COOKIE ] );
	}

	protected function tearDown(): void {
		$this->delete_passwordless_transients();
		unset( $_COOKIE[ Otpa_Passwordless::REQUEST_COOKIE ], $_COOKIE[ Otpa_Passwordless::NONCE_SESSION_COOKIE ] );
		wp_set_current_user( 0 );
		parent::tearDown();
	}

	public function test_request_creates_server_binding_and_returns_no_code(): void {
		$result   = $this->passwordless->request_otp_code(
			array( 'identifier' => $this->requested_user->user_email )
		);
		$bindings = $this->get_passwordless_bindings();

		$this->assertNotWPError( $result );
		$this->assertArrayNotHasKey( 'otp_code', $result );
		$this->assertSame( array( 'status', 'message' ), array_keys( $result ) );
		$this->assertCount( 1, $bindings );
		$this->assertSame(
			array( 'user_id' => $this->requested_user_id ),
			array_values( $bindings )[0]
		);
		$this->assertSame(
			Otpa::OTP_PURPOSE_PASSWORDLESS,
			get_user_meta( $this->requested_user_id, 'otpa_verification_code', true )['purpose']
		);
	}

	public function test_changed_identifier_cannot_change_logged_in_account(): void {
		$request_token = str_repeat( 'A', 64 );
		$storage_key   = $this->bind_token( $request_token, $this->requested_user_id );
		$logged_in_id  = 0;
		$listener      = static function ( $user_login, $user ) use ( &$logged_in_id ): void {
			unset( $user_login );
			$logged_in_id = (int) $user->ID;
		};

		$this->save_code( $this->requested_user_id );
		add_action( 'wp_login', $listener, 10, 2 );
		$result = $this->passwordless->verify_otp_code(
			array(
				'identifier' => $this->different_user->user_email,
				'code'       => 'ABC123',
			)
		);
		remove_action( 'wp_login', $listener, 10 );

		$this->assertNotWPError( $result );
		$this->assertSame( $this->requested_user_id, $logged_in_id );
		$this->assertFalse( get_transient( $storage_key ) );
		$this->assertArrayNotHasKey( Otpa_Passwordless::REQUEST_COOKIE, $_COOKIE );
	}

	/**
	 * @dataProvider invalid_token_provider
	 *
	 * @param string|null $request_token Missing or invalid request token.
	 */
	public function test_missing_malformed_and_unknown_tokens_are_rejected( $request_token ): void {
		$login_count = 0;
		$listener    = static function () use ( &$login_count ): void {
			++$login_count;
		};

		if ( null !== $request_token ) {
			$_COOKIE[ Otpa_Passwordless::REQUEST_COOKIE ] = $request_token;
		}

		$this->save_code( $this->requested_user_id );
		add_action( 'wp_login', $listener );
		$result = $this->passwordless->verify_otp_code(
			array(
				'identifier' => $this->requested_user->user_email,
				'code'       => 'ABC123',
			)
		);
		remove_action( 'wp_login', $listener );

		$this->assertWPError( $result );
		$this->assertSame( 'OTPA_CODE_NOT_FOUND', $result->get_error_code() );
		$this->assertSame( 0, $login_count );
	}

	/** @return array<string, array{0: string|null}> */
	public static function invalid_token_provider(): array {
		return array(
			'missing token'   => array( null ),
			'malformed token' => array( 'not-a-valid-token' ),
			'unknown token'   => array( str_repeat( 'B', 64 ) ),
		);
	}

	public function test_expired_binding_is_rejected(): void {
		$request_token                                = str_repeat( 'C', 64 );
		$_COOKIE[ Otpa_Passwordless::REQUEST_COOKIE ] = $request_token;
		$this->save_code( $this->requested_user_id );

		$result = $this->passwordless->verify_otp_code(
			array(
				'identifier' => $this->requested_user->user_email,
				'code'       => 'ABC123',
			)
		);

		$this->assertWPError( $result );
		$this->assertSame( 'OTPA_CODE_NOT_FOUND', $result->get_error_code() );
		$this->assertArrayNotHasKey( Otpa_Passwordless::REQUEST_COOKIE, $_COOKIE );
	}

	public function test_token_cannot_be_reused_after_success(): void {
		$request_token = str_repeat( 'D', 64 );
		$this->bind_token( $request_token, $this->requested_user_id );
		$this->save_code( $this->requested_user_id );

		$first_result                                 = $this->passwordless->verify_otp_code(
			array(
				'identifier' => $this->requested_user->user_email,
				'code'       => 'ABC123',
			)
		);
		$_COOKIE[ Otpa_Passwordless::REQUEST_COOKIE ] = $request_token;
		$this->save_code( $this->requested_user_id );
		$second_result = $this->passwordless->verify_otp_code(
			array(
				'identifier' => $this->requested_user->user_email,
				'code'       => 'ABC123',
			)
		);

		$this->assertNotWPError( $first_result );
		$this->assertWPError( $second_result );
		$this->assertSame( 'OTPA_CODE_NOT_FOUND', $second_result->get_error_code() );
	}

	public function test_failed_verification_preserves_binding_for_retry(): void {
		$request_token = str_repeat( 'E', 64 );
		$storage_key   = $this->bind_token( $request_token, $this->requested_user_id );
		$this->save_code( $this->requested_user_id );

		$result = $this->passwordless->verify_otp_code(
			array(
				'identifier' => $this->requested_user->user_email,
				'code'       => 'WRONG1',
			)
		);

		$this->assertWPError( $result );
		$this->assertSame( 'OTPA_INVALID_CODE', $result->get_error_code() );
		$this->assertSame( array( 'user_id' => $this->requested_user_id ), get_transient( $storage_key ) );
		$this->assertArrayHasKey( Otpa_Passwordless::REQUEST_COOKIE, $_COOKIE );

		$retry_result = $this->passwordless->verify_otp_code(
			array(
				'identifier' => $this->requested_user->user_email,
				'code'       => 'ABC123',
			)
		);

		$this->assertNotWPError( $retry_result );
		$this->assertFalse( get_transient( $storage_key ) );
		$this->assertArrayNotHasKey( Otpa_Passwordless::REQUEST_COOKIE, $_COOKIE );
	}

	public function test_passwordless_nonce_uses_a_private_browser_session(): void {
		$first_vars  = $this->passwordless->set_otp_form_vars( array() );
		$first_value = $this->passwordless->nonce_user_logged_out( 0, Otpa_Passwordless::NONCE_ACTION );
		$second      = new Otpa_Test_Passwordless( $this->otpa );
		$second->set_otp_form_vars( array() );
		$second_value = $second->nonce_user_logged_out( 0, Otpa_Passwordless::NONCE_ACTION );

		$this->assertSame( Otpa_Passwordless::NONCE_ACTION, $first_vars['otp_nonce_action'] );
		$this->assertNotSame( 0, $first_value );
		$this->assertNotSame( $first_value, $second_value );
		$this->assertSame( 27, $this->passwordless->nonce_user_logged_out( 27, 'unrelated_action' ) );
	}

	public function test_unknown_identifier_returns_error_without_changing_user(): void {
		$result = $this->passwordless->request_otp_code(
			array( 'identifier' => 'missing-user@example.test' )
		);

		$this->assertWPError( $result );
		$this->assertSame( 'OTPA_INVALID_USER', $result->get_error_code() );
		$this->assertSame( 0, get_current_user_id() );
	}

	private function bind_token( $request_token, $user_id ) {
		$storage_key                                  = $this->storage_key( $request_token );
		$_COOKIE[ Otpa_Passwordless::REQUEST_COOKIE ] = $request_token;
		set_transient(
			$storage_key,
			array( 'user_id' => (int) $user_id ),
			5 * MINUTE_IN_SECONDS
		);

		return $storage_key;
	}

	private function storage_key( $request_token ) {
		$method = new ReflectionMethod( Otpa_Passwordless::class, 'get_storage_key' );

		return $method->invoke( $this->passwordless, $request_token );
	}

	private function save_code( $user_id ): void {
		update_user_meta(
			$user_id,
			'otpa_verification_code',
			array(
				'otp_code' => 'ABC123',
				'expiry'   => time() + 5 * MINUTE_IN_SECONDS,
				'purpose'  => Otpa::OTP_PURPOSE_PASSWORDLESS,
			)
		);
	}

	/** @return array<string, array<string, int>> */
	private function get_passwordless_bindings(): array {
		global $wpdb;

		$prefix       = '_transient_otpa_passwordless_';
		$option_names = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s",
				$wpdb->esc_like( $prefix ) . '%'
			)
		);
		$bindings     = array();

		foreach ( $option_names as $option_name ) {
			$transient_name              = substr( $option_name, strlen( '_transient_' ) );
			$bindings[ $transient_name ] = get_transient( $transient_name );
		}

		return $bindings;
	}

	private function delete_passwordless_transients(): void {
		foreach ( array_keys( $this->get_passwordless_bindings() ) as $transient_name ) {
			delete_transient( $transient_name );
		}
	}
}
