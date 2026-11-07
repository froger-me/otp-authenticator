<?php
/**
 * Tests Composer-managed delivery libraries and local delivery paths.
 */

use AnyapeWPTestTools\IntegrationTestCase;

final class Otpa_Test_Twilio_Gateway extends Otpa_Twilio_Gateway {
	public function configure( $settings ): void {
		$this->settings = $settings;
	}

	public function load_test_library() {
		return $this->load_library();
	}

	public function initialize_test_client() {
		return $this->init_api();
	}

	public function send_test_sandbox_request( $phone, $code ) {
		return $this->send_sandox_request( $phone, $code );
	}
}

final class Otpa_Test_Alibaba_Gateway extends Otpa_Alibaba_Cloud_Sms_Gateway {
	public function configure( $settings ): void {
		$this->settings = $settings;
	}

	public function load_test_library() {
		return $this->load_library();
	}

	public function initialize_test_client() {
		return $this->init_api();
	}

	public function send_test_sandbox_request( $phone, $code ) {
		return $this->send_sandox_request( $phone, $code );
	}
}

final class GatewayCompatibilityTest extends IntegrationTestCase {
	protected function tearDown(): void {
		remove_filter( 'otpa_wp_email_gateway_settings', array( $this, 'filter_email_settings' ) );
		delete_option( 'otpa_wp_email_gateway_settings' );
		wp_cache_delete( 'otpa_wp_email_gateway_settings', 'otpa' );
		wp_set_current_user( 0 );
		parent::tearDown();
	}

	public function filter_email_settings( $settings ) {
		return array_merge(
			$settings,
			array(
				'subject' => 'OTP test subject',
				'message' => 'Hello ###USERNAME###. Code: ###CODE###. Email: ###EMAIL###.',
			)
		);
	}

	public function test_composer_loader_provides_twilio_and_alibaba_classes(): void {
		require_once OTPA_PLUGIN_PATH . 'libraries/vendor/autoload.php';

		$this->assertTrue( class_exists( 'Twilio\\Rest\\Client' ) );
		$this->assertTrue( class_exists( 'AlibabaCloud\\SDK\\Dysmsapi\\V20170525\\Dysmsapi' ) );
	}

	public function test_twilio_library_client_and_sandbox_work_without_network(): void {
		$gateway = new Otpa_Test_Twilio_Gateway();
		$gateway->configure(
			array(
				'account_sid'            => 'AC00000000000000000000000000000000',
				'auth_token'             => 'test-auth-token',
				'from_number'            => '+12025550199',
				'message'                => 'Verification Code: ###CODE###',
				'sync_metakey'           => '',
				'max_phone_length'       => 10,
				'min_phone_length'       => 10,
				'allowed_phone_prefixes' => '',
				'default_phone_prefix'   => '+1',
			)
		);

		$this->assertTrue( $gateway->load_test_library() );
		$this->assertTrue( $gateway->initialize_test_client() );
		$result = $gateway->send_test_sandbox_request( '+12025550123', '1234' );

		$this->assertTrue( $result['status'] );
		$this->assertSame( 'OK', $result['code'] );
		$this->assertStringContainsString( '+12025550123', $result['message'] );
	}

	public function test_alibaba_library_client_and_sandbox_work_without_network(): void {
		$gateway = new Otpa_Test_Alibaba_Gateway();
		$gateway->configure(
			array(
				'access_key'             => 'test-access-key',
				'access_secret'          => 'test-access-secret',
				'allowed_phone_prefixes' => '',
				'china_us'               => false,
				'default_phone_prefix'   => '+86',
				'intl'                   => false,
				'intl_message'           => 'Verification Code: ###CODE###',
				'max_phone_length'       => 11,
				'min_phone_length'       => 11,
				'region'                 => 'cn-hangzhou',
				'signature_name'         => 'Test Signature',
				'sync_metakey'           => '',
				'template_code'          => 'SMS_TEST_TEMPLATE',
				'version'                => '2017-05-25',
			)
		);

		$this->assertTrue( $gateway->load_test_library() );
		$this->assertTrue( $gateway->initialize_test_client() );
		$result = $gateway->send_test_sandbox_request( '+8613812345678', '1234' );

		$this->assertTrue( $result['status'] );
		$this->assertSame( 'OK', $result['code'] );
		$this->assertStringContainsString( '+8613812345678', $result['message'] );
	}

	public function test_wordpress_email_gateway_sends_captured_code(): void {
		$user_id = self::factory()->user->create(
			array(
				'user_email'   => 'gateway-email@example.test',
				'user_login'   => 'otpa_gateway_email',
				'display_name' => 'Gateway Email User',
			)
		);
		wp_set_current_user( $user_id );
		add_filter( 'otpa_wp_email_gateway_settings', array( $this, 'filter_email_settings' ) );
		$this->enable_mail_capture();

		$gateway = new Otpa_WP_Email_Gateway();
		$result  = $gateway->request_code(
			'gateway-email@example.test',
			array( Otpa::class, 'get_wp_error' ),
			false
		);
		$mail    = $this->captured_mail();

		$this->assertNotWPError( $result );
		$this->assertTrue( $result['status'] );
		$this->assertArrayHasKey( 'otp_code', $result );
		$this->assertCount( 1, $mail );
		$this->assertSame( 'gateway-email@example.test', $mail[0]['to'] );
		$this->assertSame( 'OTP test subject', $mail[0]['subject'] );
		$this->assertStringContainsString( 'Gateway Email User', $mail[0]['message'] );
		$this->assertStringContainsString( 'gateway-email@example.test', $mail[0]['message'] );
		$this->assertStringContainsString( $result['otp_code'], $mail[0]['message'] );
	}

	/** Test that the email gateway supplies its defaults when no settings were saved. */
	public function test_wordpress_email_gateway_loads_its_defaults_without_saved_settings(): void {
		delete_option( 'otpa_wp_email_gateway_settings' );

		$gateway  = new Otpa_WP_Email_Gateway();
		$settings = $gateway->get_options();

		$this->assertArrayHasKey( 'subject', $settings );
		$this->assertArrayHasKey( 'message', $settings );
		$this->assertNotSame( '', $settings['subject'] );
		$this->assertStringContainsString( '###CODE###', $settings['message'] );
	}

	/** Test that the email gateway ignores the removed gateway-settings cache. */
	public function test_wordpress_email_gateway_reads_newly_saved_settings(): void {
		wp_cache_set(
			'otpa_wp_email_gateway_settings',
			array( 'sync_metakey' => '' ),
			'otpa'
		);
		update_option(
			'otpa_wp_email_gateway_settings',
			array(
				'subject' => 'Newly saved subject',
				'message' => 'Newly saved message: ###CODE###',
			)
		);

		$gateway  = new Otpa_WP_Email_Gateway();
		$settings = $gateway->get_options();

		$this->assertSame( 'Newly saved subject', $settings['subject'] );
		$this->assertSame( 'Newly saved message: ###CODE###', $settings['message'] );
	}

	/** Test that one profile save synchronizes a changed WordPress email address. */
	public function test_wordpress_email_gateway_updates_identifier_on_first_profile_save(): void {
		require_once OTPA_PLUGIN_PATH . 'inc/class-otpa-user-info.php';

		$force_email_gateway = static function ( $_value, $_key ) {
			return 'gateway_id' === $_key ? 'otpa_wp_email_gateway' : $_value;
		};
		add_filter( 'otpa_option', $force_email_gateway, 10, 2 );

		try {
			$old_email = 'old-profile-email@example.test';
			$new_email = 'new-profile-email@example.test';
			$user_id   = self::factory()->user->create( array( 'user_email' => $old_email ) );
			$old_user  = get_user_by( 'ID', $user_id );
			$gateway   = new Otpa_WP_Email_Gateway();
			$user_info = new class( $old_email ) extends Otpa_User_Info {
				private $posted_identifier;

				public function __construct( $posted_identifier ) {
					$this->posted_identifier = $posted_identifier;
				}

				protected function get_posted_identifier() {
					return $this->posted_identifier;
				}
			};

			update_user_meta( $user_id, 'otpa_mail', $old_email );
			$gateway->profile_update(
				$user_id,
				$old_user,
				array( 'user_email' => $new_email )
			);
			$user_info->save_user_info( $user_id, $old_user );

			$this->assertTrue( otpa_is_email_gateway() );
			$this->assertSame( $new_email, get_user_meta( $user_id, 'otpa_mail', true ) );
		} finally {
			remove_filter( 'otpa_option', $force_email_gateway, 10 );
		}
	}

	/** Test that only the WordPress email gateway is identified as an email gateway. */
	public function test_email_gateway_detection_distinguishes_gateway_classes(): void {
		$gateway_id    = 'otpa_wp_email_gateway';
		$force_gateway = static function ( $_value, $_key ) use ( &$gateway_id ) {
			return 'gateway_id' === $_key ? $gateway_id : $_value;
		};
		add_filter( 'otpa_option', $force_gateway, 10, 2 );

		try {
			$this->assertTrue( otpa_is_email_gateway() );

			$gateway_id = 'otpa_twilio_gateway';
			$this->assertFalse( otpa_is_email_gateway() );

			$gateway_id = 'otpa_alibaba_cloud_sms_gateway';
			$this->assertFalse( otpa_is_email_gateway() );
		} finally {
			remove_filter( 'otpa_option', $force_gateway, 10 );
		}
	}
}
