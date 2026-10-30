<?php
/**
 * Tests the one-time-code request and verification flow.
 */

use AnyapeWPTestTools\IntegrationTestCase;

final class OtpaCodeFlowTest extends IntegrationTestCase {
	/** @var int */
	private $user_id;

	/** @var WP_User */
	private $user;

	/** @var Otpa_Test_Settings */
	private $settings;

	/** @var Otpa */
	private $otpa;

	protected function setUp(): void {
		parent::setUp();

		$this->user_id = self::factory()->user->create(
			array(
				'user_email'   => 'code-flow@example.test',
				'user_login'   => 'otpa_code_flow',
				'display_name' => 'OTP Code Flow',
			)
		);
		$this->user     = get_user_by( 'ID', $this->user_id );
		$this->settings = new Otpa_Test_Settings();
		$this->otpa     = new Otpa( $this->settings, new Otpa_Test_Gateway() );

		wp_set_current_user( $this->user_id );
		unset( $_COOKIE['otpa_otp_request_wait'] );
	}

	protected function tearDown(): void {
		unset( $_COOKIE['otpa_otp_request_wait'] );
		wp_set_current_user( 0 );
		parent::tearDown();
	}

	public function test_request_saves_code_and_returns_only_public_fields(): void {
		$result = $this->request_code();
		$stored = get_user_meta( $this->user_id, 'otpa_verification_code', true );

		$this->assertSame(
			array(
				'status'  => true,
				'message' => 'Test code sent.',
			),
			$result
		);
		$this->assertArrayNotHasKey( 'otp_code', $result );
		$this->assertSame( 'ABC123', $stored['otp_code'] );
		$this->assertGreaterThan( time(), $stored['expiry'] );
	}

	public function test_correct_code_runs_hook_removes_code_and_resets_attempts(): void {
		$verified_user_id = 0;
		$listener         = static function ( $user_id ) use ( &$verified_user_id ): void {
			$verified_user_id = (int) $user_id;
		};

		update_user_meta(
			$this->user_id,
			'otpa_otp_attempts',
			array(
				'verify_count'  => 3,
				'request_count' => 2,
				'track_expiry'  => time() + HOUR_IN_SECONDS,
				'block_expiry'  => 0,
			)
		);
		$this->save_code();
		add_action( 'otpa_otp_code_verified', $listener );

		$result = $this->otpa->verify_otp_code( array( 'code' => 'ABC123' ) );
		remove_action( 'otpa_otp_code_verified', $listener );
		$attempts = get_user_meta( $this->user_id, 'otpa_otp_attempts', true );

		$this->assertNotWPError( $result );
		$this->assertSame( $this->user_id, $verified_user_id );
		$this->assertSame( 0, $attempts['verify_count'] );
		$this->assertSame( 0, $attempts['request_count'] );
		$this->assertSame( '', get_user_meta( $this->user_id, 'otpa_verification_code', true ) );
	}

	public function test_incorrect_code_records_failure_and_removes_code(): void {
		$this->save_code();

		$result   = $this->otpa->verify_otp_code( array( 'code' => 'WRONG1' ) );
		$attempts = get_user_meta( $this->user_id, 'otpa_otp_attempts', true );

		$this->assertWPError( $result );
		$this->assertSame( 'OTPA_INVALID_CODE', $result->get_error_code() );
		$this->assertSame( 1, $attempts['verify_count'] );
		$this->assertSame( '', get_user_meta( $this->user_id, 'otpa_verification_code', true ) );
	}

	public function test_expired_code_is_rejected_and_removed(): void {
		$this->save_code( 'ABC123', time() - MINUTE_IN_SECONDS );

		$result = $this->otpa->verify_otp_code( array( 'code' => 'ABC123' ) );

		$this->assertWPError( $result );
		$this->assertSame( 'OTPA_EXPIRED_CODE', $result->get_error_code() );
		$this->assertSame( '', get_user_meta( $this->user_id, 'otpa_verification_code', true ) );
	}

	public function test_missing_stored_code_is_rejected(): void {
		$result = $this->otpa->verify_otp_code( array( 'code' => 'ABC123' ) );

		$this->assertWPError( $result );
		$this->assertSame( 'OTPA_CODE_NOT_FOUND', $result->get_error_code() );
	}

	public function test_immediate_repeated_request_is_throttled(): void {
		$this->settings = new Otpa_Test_Settings( array( 'request_frequency' => 30 ) );
		$this->otpa     = new Otpa( $this->settings, new Otpa_Test_Gateway() );

		$this->request_code();
		$result = $this->request_code();

		$this->assertWPError( $result );
		$this->assertSame( 'OTPA_THROTTLE', $result->get_error_code() );
	}

	public function test_request_limit_blocks_later_requests(): void {
		$this->settings = new Otpa_Test_Settings(
			array(
				'max_request'       => 1,
				'request_frequency' => 0,
			)
		);
		$this->otpa = new Otpa( $this->settings, new Otpa_Test_Gateway() );

		$this->request_code();
		$result = $this->request_code();

		$this->assertWPError( $result );
		$this->assertSame( 'OTPA_OTP_BLOCKED', $result->get_error_code() );
		$this->assertGreaterThan( time(), $result->get_error_data()['block_expiry'] );
	}

	public function test_verification_limit_blocks_later_attempts(): void {
		$this->settings = new Otpa_Test_Settings( array( 'max_verify' => 1 ) );
		$this->otpa     = new Otpa( $this->settings, new Otpa_Test_Gateway() );
		$this->save_code();

		$first_result = $this->otpa->verify_otp_code( array( 'code' => 'WRONG1' ) );
		$this->save_code();
		$second_result = $this->otpa->verify_otp_code( array( 'code' => 'ABC123' ) );

		$this->assertWPError( $first_result );
		$this->assertSame( 'OTPA_INVALID_CODE', $first_result->get_error_code() );
		$this->assertWPError( $second_result );
		$this->assertSame( 'OTPA_OTP_BLOCKED', $second_result->get_error_code() );
		$this->assertGreaterThan( time(), $second_result->get_error_data()['block_expiry'] );
	}

	/** @return array<string, mixed>|WP_Error */
	private function request_code() {
		return $this->otpa->request_otp_code(
			array( 'identifier' => $this->user->user_email )
		);
	}

	private function save_code( $code = 'ABC123', $expiry = null ): void {
		update_user_meta(
			$this->user_id,
			'otpa_verification_code',
			array(
				'otp_code' => $code,
				'expiry'   => null === $expiry ? time() + 5 * MINUTE_IN_SECONDS : $expiry,
			)
		);
	}
}
