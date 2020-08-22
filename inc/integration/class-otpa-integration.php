<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class Otpa_Integration {
	public static $integrations = array(
		'woocommerce'     => array(
			'plugin'     => 'woocommerce/woocommerce.php',
			'class_name' => 'Otpa_Woocommerce_Integration',
		),
		'ultimate-member' => array(
			'plugin'     => 'ultimate-member/ultimate-member.php',
			'class_name' => 'Otpa_UM_Integration',
		),
	);

	protected $otpa_settings;
	protected $gateway;
	protected $otpa;
	protected $otpa_passwordless;
	protected $otpa_2fa;
	protected $otpa_account_validation;

	public function __construct( $init_hooks = false ) {

		if ( $init_hooks ) {
			add_action( 'otpa_ready', array( $this, 'run' ), 10, 1 );
		}
	}

	/*******************************************************************
	 * Public methods
	 *******************************************************************/
	public static function init() {
		$integrations = apply_filters( 'otpa_registered_integration', self::$integrations );

		foreach ( $integrations as $slug => $info ) {

			if ( is_plugin_active( $info['plugin'] ) ) {
				$filename   = 'class-' . strtolower( str_replace( '_', '-', $info['class_name'] ) ) . '.php';
				$file_path  = OTPA_PLUGIN_PATH . 'inc/integration/' . $slug . '/' . $filename;
				$class_name = $info['class_name'];

				if ( ! class_exists( $class_name ) && file_exists( $file_path ) ) {
					require_once $file_path;
				} else {
					do_action( 'otpa_require_integration_file', $slug, $class_name );
				}

				if ( class_exists( $class_name ) ) {
					$integration = new $class_name( true );

					do_action( 'otpa_integration', $integration, $slug );
				}
			}
		}
	}

	public function run( $otpa_objects ) {
		extract( $otpa_objects ); // @codingStandardsIgnoreLine

		$this->otpa_settings           = isset( $settings ) ? $settings : false;
		$this->gateway                 = isset( $gateway ) ? $gateway : false;
		$this->otpa                    = isset( $otpa ) ? $otpa : false;
		$this->otpa_passwordless       = isset( $otpa_passwordless ) ? $otpa_passwordless : false;
		$this->otpa_2fa                = isset( $otpa_2fa ) ? $otpa_2fa : false;
		$this->otpa_account_validation = isset( $otpa_account_validation ) ? $otpa_account_validation : false;

		$this->init_hooks();
		do_action( 'otpa_integration_run', $this );
	}

	public function init_hooks() {}

	/*******************************************************************
	 * Protected methods
	 *******************************************************************/

}
