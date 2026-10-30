<?php
/**
 * Security regression tests for OTP Authenticator.
 *
 * @package Otpa
 */

use AnyapeWPTestTools\IntegrationTestCase;

/** Tests the security rules shared by OTP Authenticator features. */
final class OtpaSecurityRegressionTest extends IntegrationTestCase {
	/**
	 * Administrator user number.
	 *
	 * @var int
	 */
	private $administrator_id;

	/**
	 * Subscriber user number.
	 *
	 * @var int
	 */
	private $subscriber_id;

	/**
	 * Target user account.
	 *
	 * @var WP_User
	 */
	private $target_user;

	/** Create users used by each security test. */
	protected function setUp(): void {
		parent::setUp();
		require_once OTPA_PLUGIN_PATH . 'inc/class-otpa-user-info.php';

		$this->administrator_id = self::factory()->user->create( array( 'role' => 'administrator' ) );
		$this->subscriber_id    = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		$target_user_id         = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		$this->target_user      = get_user_by( 'ID', $target_user_id );
	}

	/** Restore the anonymous current user after each test. */
	protected function tearDown(): void {
		wp_set_current_user( 0 );
		parent::tearDown();
	}

	/** Confirm both broad and target-specific permissions are required. */
	public function test_profile_security_changes_require_broad_and_target_permissions(): void {
		$settings  = new Otpa_Test_Settings(
			array(
				'enable_2fa'        => true,
				'enable_validation' => true,
			)
		);
		$user_info = new Otpa_User_Info( $settings );

		wp_set_current_user( $this->subscriber_id );
		$this->assertFalse( $this->can_toggle( $user_info, 'can_toggle_user_validation' ) );
		$this->assertFalse( $this->can_toggle( $user_info, 'can_toggle_user_2fa' ) );

		wp_set_current_user( $this->administrator_id );
		$this->assertTrue( $this->can_toggle( $user_info, 'can_toggle_user_validation' ) );
		$this->assertTrue( $this->can_toggle( $user_info, 'can_toggle_user_2fa' ) );
	}

	/** Confirm disabled and forced features cannot be changed through profile actions. */
	public function test_profile_security_changes_enforce_feature_settings(): void {
		wp_set_current_user( $this->administrator_id );

		$disabled = new Otpa_User_Info( new Otpa_Test_Settings() );
		$forced   = new Otpa_User_Info(
			new Otpa_Test_Settings(
				array(
					'enable_2fa'        => true,
					'enable_validation' => true,
					'force_2fa'         => true,
					'validation_expiry' => 0,
				)
			)
		);

		$this->assertFalse( $this->can_toggle( $disabled, 'can_toggle_user_validation' ) );
		$this->assertFalse( $this->can_toggle( $disabled, 'can_toggle_user_2fa' ) );
		$this->assertFalse( $this->can_toggle( $forced, 'can_toggle_user_validation' ) );
		$this->assertFalse( $this->can_toggle( $forced, 'can_toggle_user_2fa' ) );
	}

	/** Confirm one code purpose cannot complete another security process. */
	public function test_code_completion_changes_only_its_own_security_process(): void {
		$settings   = new Otpa_Test_Settings(
			array(
				'enable_2fa'        => true,
				'enable_validation' => true,
			)
		);
		$otpa       = new Otpa( $settings, new Otpa_Test_Gateway() );
		$two_factor = new Otpa_2FA( $otpa );
		$validation = new Otpa_Account_Validation( $otpa );

		wp_set_current_user( $this->target_user->ID );
		update_user_meta( $this->target_user->ID, 'otpa_2fa_active', true );
		update_user_meta( $this->target_user->ID, 'otpa_need_2fa_check', true );
		update_user_meta( $this->target_user->ID, 'otpa_need_account_validation', true );
		delete_user_meta( $this->target_user->ID, 'otpa_2fa_checked' );
		delete_user_meta( $this->target_user->ID, 'otpa_validated' );

		add_action( 'otpa_otp_code_verified', array( $two_factor, 'do_2fa' ), 10, 2 );
		add_action( 'otpa_otp_code_verified', array( $validation, 'validate_account' ), 10, 2 );

		$otpa->save_otp_code( 'ABC123', Otpa::OTP_PURPOSE_2FA );
		$result = $two_factor->verify_otp_code( array( 'code' => 'ABC123' ) );

		remove_action( 'otpa_otp_code_verified', array( $two_factor, 'do_2fa' ), 10 );
		remove_action( 'otpa_otp_code_verified', array( $validation, 'validate_account' ), 10 );

		$this->assertNotWPError( $result );
		$this->assertTrue( (bool) get_user_meta( $this->target_user->ID, 'otpa_2fa_checked', true ) );
		$this->assertSame( '', get_user_meta( $this->target_user->ID, 'otpa_validated', true ) );
		$this->assertTrue( $validation->need_account_validation( $this->target_user ) );

		add_action( 'otpa_otp_code_verified', array( $two_factor, 'do_2fa' ), 10, 2 );
		add_action( 'otpa_otp_code_verified', array( $validation, 'validate_account' ), 10, 2 );
		$otpa->save_otp_code( 'DEF456', Otpa::OTP_PURPOSE_ACCOUNT_VALIDATION );
		$result = $validation->verify_otp_code( array( 'code' => 'DEF456' ) );
		remove_action( 'otpa_otp_code_verified', array( $two_factor, 'do_2fa' ), 10 );
		remove_action( 'otpa_otp_code_verified', array( $validation, 'validate_account' ), 10 );

		$this->assertNotWPError( $result );
		$this->assertTrue( (bool) get_user_meta( $this->target_user->ID, 'otpa_2fa_checked', true ) );
		$this->assertIsArray( get_user_meta( $this->target_user->ID, 'otpa_validated', true ) );
	}

	/** Confirm state checks use their user argument and hide the toolbar while pending. */
	public function test_pending_checks_use_the_supplied_user_and_hide_the_toolbar(): void {
		$settings   = new Otpa_Test_Settings(
			array(
				'enable_2fa'        => true,
				'enable_validation' => true,
			)
		);
		$otpa       = new Otpa( $settings );
		$two_factor = new Otpa_2FA( $otpa );
		$validation = new Otpa_Account_Validation( $otpa );

		wp_set_current_user( $this->subscriber_id );
		update_user_meta( $this->subscriber_id, 'otpa_2fa_checked', true );
		update_user_meta( $this->subscriber_id, 'otpa_validated', array( 'validation_expiry' => 0 ) );
		update_user_meta( $this->target_user->ID, 'otpa_2fa_active', true );
		update_user_meta( $this->target_user->ID, 'otpa_need_2fa_check', true );
		update_user_meta( $this->target_user->ID, 'otpa_need_account_validation', true );
		delete_user_meta( $this->target_user->ID, 'otpa_2fa_checked' );
		delete_user_meta( $this->target_user->ID, 'otpa_validated' );

		$this->assertTrue( $two_factor->need_2fa_check( $this->target_user ) );
		$this->assertTrue( $validation->need_account_validation( $this->target_user ) );

		wp_set_current_user( $this->target_user->ID );
		$this->assertFalse( $two_factor->hide_admin_bar( true ) );
		$this->assertFalse( $validation->hide_admin_bar( true ) );
	}

	/** Confirm REST callbacks do not run while a security check is pending. */
	public function test_pending_rest_request_is_denied_before_dispatch(): void {
		$callback_count = 0;
		$otpa           = new Otpa( new Otpa_Test_Settings( array( 'enable_2fa' => true ) ) );
		$two_factor     = new Otpa_2FA( $otpa );
		$request        = new WP_REST_Request( 'POST', '/otpa-test/v1/protected' );
		$server         = rest_get_server();
		$listener       = static function () use ( &$callback_count ) {
			++$callback_count;

			return rest_ensure_response( array( 'changed' => true ) );
		};

		register_rest_route(
			'otpa-test/v1',
			'/protected',
			array(
				'methods'             => 'POST',
				'callback'            => $listener,
				'permission_callback' => '__return_true',
			)
		);
		add_filter( 'rest_pre_dispatch', array( $two_factor, 'deny_rest' ), PHP_INT_MIN, 3 );

		$response = $server->dispatch( $request );

		remove_filter( 'rest_pre_dispatch', array( $two_factor, 'deny_rest' ), PHP_INT_MIN );
		$result = $response->as_error();

		$this->assertWPError( $result );
		$this->assertSame( 'otpa_rest_denied', $result->get_error_code() );
		$this->assertSame( 403, $result->get_error_data()['status'] );
		$this->assertSame( 0, $callback_count );
	}

	/**
	 * Run one protected profile permission decision.
	 *
	 * @param Otpa_User_Info $user_info  Profile information service.
	 * @param string         $method_name Permission method name.
	 * @return bool
	 */
	private function can_toggle( $user_info, $method_name ) {
		$method = new ReflectionMethod( Otpa_User_Info::class, $method_name );

		return $method->invoke( $user_info, $this->target_user );
	}
}
