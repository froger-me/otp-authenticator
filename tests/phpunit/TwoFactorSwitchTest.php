<?php
/**
 * Two-factor switch feature tests.
 *
 * @package Otpa
 */

use AnyapeWPTestTools\IntegrationTestCase;

/** Tests the user-facing two-factor switch. */
final class TwoFactorSwitchTest extends IntegrationTestCase {
	/** Restore the anonymous current user after each test. */
	protected function tearDown(): void {
		wp_set_current_user( 0 );
		parent::tearDown();
	}

	/** Confirm the shortcode shows a plain-text label above the button by default. */
	public function test_shortcode_displays_plain_text_label_above_button_by_default(): void {
		$user_id    = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		$settings   = new Otpa_Test_Settings( array( 'enable_2fa' => true ) );
		$otpa       = new Otpa( $settings );
		$two_factor = new Otpa_2FA( $otpa );

		wp_set_current_user( $user_id );
		$output = do_shortcode( '[otpa_2fa_switch label="Two-factor <br> sign-in" turn_on_text="Turn on" turn_off_text="Turn off"]' );

		$this->assertStringContainsString(
			'<div class="otpa-user-2fa-button-switch-label"><label>Two-factor &lt;br&gt; sign-in</label></div><button',
			$output
		);
		$this->assertStringContainsString( 'data-active_text="Turn on"', $output );
		$this->assertStringContainsString( 'data-inactive_text="Turn off"', $output );
	}

	/** Confirm the shortcode can display its label beside the button. */
	public function test_shortcode_can_display_label_inline(): void {
		$user_id    = self::factory()->user->create( array( 'role' => 'subscriber' ) );
		$settings   = new Otpa_Test_Settings( array( 'enable_2fa' => true ) );
		$otpa       = new Otpa( $settings );
		$two_factor = new Otpa_2FA( $otpa );

		wp_set_current_user( $user_id );
		$output = do_shortcode( '[otpa_2fa_switch label="Two-factor sign-in" label_inline="true"]' );

		$this->assertStringContainsString( '<label>Two-factor sign-in</label> <button', $output );
		$this->assertStringNotContainsString( 'otpa-user-2fa-button-switch-label', $output );
	}
}
