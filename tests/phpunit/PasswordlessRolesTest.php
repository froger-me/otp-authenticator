<?php
/**
 * Passwordless role feature tests.
 *
 * @package Otpa
 */

use AnyapeWPTestTools\IntegrationTestCase;

require_once __DIR__ . '/includes/class-otpa-test-passwordless.php';

/** Tests which WordPress users may use passwordless authentication. */
final class PasswordlessRolesTest extends IntegrationTestCase {
	/**
	 * Subscriber user number.
	 *
	 * @var int
	 */
	private $user_id;

	/**
	 * Subscriber user.
	 *
	 * @var WP_User
	 */
	private $user;

	/**
	 * Passwordless service.
	 *
	 * @var Otpa_Test_Passwordless
	 */
	private $passwordless;

	/** Create a subscriber and the default passwordless service. */
	protected function setUp(): void {
		parent::setUp();

		$this->user_id      = self::factory()->user->create(
			array(
				'role'       => 'subscriber',
				'user_email' => 'passwordless-role-user@example.test',
				'user_login' => 'otpa_passwordless_role_user',
			)
		);
		$this->user         = get_user_by( 'ID', $this->user_id );
		$this->passwordless = $this->create_passwordless( array( 'subscriber' ) );

		wp_set_current_user( 0 );
		unset( $_COOKIE[ Otpa_Passwordless::REQUEST_COOKIE ] );
	}

	/** Remove a request binding and restore the anonymous current user. */
	protected function tearDown(): void {
		$request_token = isset( $_COOKIE[ Otpa_Passwordless::REQUEST_COOKIE ] )
			? sanitize_text_field( wp_unslash( $_COOKIE[ Otpa_Passwordless::REQUEST_COOKIE ] ) )
			: '';

		if ( $request_token ) {
			delete_transient( $this->storage_key( $request_token ) );
		}

		unset( $_COOKIE[ Otpa_Passwordless::REQUEST_COOKIE ] );
		wp_set_current_user( 0 );
		parent::tearDown();
	}

	/** Confirm missing, empty, duplicate, and unknown role values use safe settings. */
	public function test_role_settings_use_subscriber_as_the_fallback(): void {
		$settings = Otpa_Settings::sanitize_settings(
			array(
				'passwordless_roles' => array( 'subscriber', 'missing_role', 'SUBSCRIBER' ),
			)
		);

		$this->assertSame( array( 'subscriber' ), $settings['passwordless_roles'] );
		$this->assertSame( array( 'subscriber' ), Otpa_Settings::sanitize_settings( array() )['passwordless_roles'] );
		$this->assertSame(
			array( 'subscriber' ),
			Otpa_Settings::sanitize_settings( array( 'passwordless_roles' => array( '' ) ) )['passwordless_roles']
		);
	}

	/** Confirm another selected role can replace Subscriber. */
	public function test_another_role_can_replace_subscriber(): void {
		$settings = Otpa_Settings::sanitize_settings(
			array( 'passwordless_roles' => array( 'administrator' ) )
		);

		$this->assertSame( array( 'administrator' ), $settings['passwordless_roles'] );
	}

	/** Confirm a user with an included role can request a passwordless code. */
	public function test_user_with_included_role_can_request_code(): void {
		$result = $this->passwordless->request_otp_code(
			array( 'identifier' => $this->user->user_email )
		);

		$this->assertNotWPError( $result );
		$this->assertArrayHasKey( Otpa_Passwordless::REQUEST_COOKIE, $_COOKIE );
	}

	/** Confirm a user without an included role receives the public error. */
	public function test_user_without_included_role_cannot_request_code(): void {
		$passwordless = $this->create_passwordless( array( 'administrator' ) );
		$result       = $passwordless->request_otp_code(
			array( 'identifier' => $this->user->user_email )
		);

		$this->assertWPError( $result );
		$this->assertSame( 'OTPA_PASSWORDLESS_NOT_AVAILABLE', $result->get_error_code() );
		$this->assertSame( 'Passwordless authentication is not available for this user.', $result->get_error_message() );
		$this->assertArrayNotHasKey( Otpa_Passwordless::REQUEST_COOKIE, $_COOKIE );
	}

	/** Confirm the user's role is checked again when a code is submitted. */
	public function test_role_is_checked_again_during_verification(): void {
		$request_token                                = str_repeat( 'R', 64 );
		$storage_key                                  = $this->storage_key( $request_token );
		$_COOKIE[ Otpa_Passwordless::REQUEST_COOKIE ] = $request_token;
		set_transient( $storage_key, array( 'user_id' => $this->user_id ), 5 * MINUTE_IN_SECONDS );

		$this->user->set_role( 'administrator' );
		$result = $this->passwordless->verify_otp_code( array( 'code' => 'ABC123' ) );

		$this->assertWPError( $result );
		$this->assertSame( 'OTPA_PASSWORDLESS_NOT_AVAILABLE', $result->get_error_code() );
		$this->assertFalse( get_transient( $storage_key ) );
		$this->assertArrayNotHasKey( Otpa_Passwordless::REQUEST_COOKIE, $_COOKIE );
	}

	/**
	 * Build the passwordless service with a selected role list.
	 *
	 * @param array<int, string> $roles Included WordPress roles.
	 * @return Otpa_Test_Passwordless Passwordless service.
	 */
	private function create_passwordless( $roles ) {
		$otpa = new Otpa(
			new Otpa_Test_Settings( array( 'passwordless_roles' => $roles ) ),
			new Otpa_Test_Gateway()
		);

		return new Otpa_Test_Passwordless( $otpa );
	}

	/**
	 * Return the saved request location for a token.
	 *
	 * @param string $request_token Passwordless request token.
	 * @return string Transient name.
	 */
	private function storage_key( $request_token ) {
		$method = new ReflectionMethod( Otpa_Passwordless::class, 'get_storage_key' );

		return $method->invoke( $this->passwordless, $request_token );
	}
}
