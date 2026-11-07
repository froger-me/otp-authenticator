<?php
/**
 * Ultimate Member integration feature tests.
 *
 * @package Otpa
 */

use AnyapeWPTestTools\IntegrationTestCase;

require_once OTPA_PLUGIN_PATH . 'inc/integration/ultimate-member/class-otpa-um-integration.php';
require_once __DIR__ . '/includes/class-otpa-test-ultimate-member-object.php';

/** Tests how the integration reads Ultimate Member form definitions. */
final class UltimateMemberIntegrationTest extends IntegrationTestCase {
	/** Confirm validation reads saved fields and does not create submitted objects. */
	public function test_validation_reads_saved_form_fields_without_creating_objects(): void {
		$integration = new Otpa_UM_Integration();
		$method      = new ReflectionMethod( Otpa_UM_Integration::class, 'get_saved_form_fields' );
		$saved       = array(
			'otp_identifier' => array(
				'metakey'  => 'otp_identifier',
				'validate' => true,
			),
		);

		Otpa_Test_Ultimate_Member_Object::$wakeups = 0;
		$this->assertSame(
			$saved,
			$method->invoke( $integration, array( 'custom_fields' => maybe_serialize( $saved ) ) )
		);
		$this->assertSame(
			array(),
			$method->invoke(
				$integration,
				array( 'custom_fields' => maybe_serialize( new Otpa_Test_Ultimate_Member_Object() ) )
			)
		);
		$this->assertSame( 0, Otpa_Test_Ultimate_Member_Object::$wakeups );

		$integration->um_submit_form_errors_hook(
			array( 'custom_fields' => maybe_serialize( new Otpa_Test_Ultimate_Member_Object() ) ),
			array( 'custom_fields' => maybe_serialize( array() ) )
		);
		$this->assertSame( 0, Otpa_Test_Ultimate_Member_Object::$wakeups );
	}
}
