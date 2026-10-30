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

	/** @var Otpa_Settings */
	protected $otpa_settings;

	/** @var Otpa_Abstract_Gateway */
	protected $gateway;

	/** @var Otpa */
	protected $otpa;

	/** @var Otpa_Passwordless */
	protected $otpa_passwordless;

	/** @var Otpa_2FA */
	protected $otpa_2fa;

	/** @var Otpa_Account_Validation */
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
		if (
			! isset(
				$otpa_objects['settings'],
				$otpa_objects['gateway'],
				$otpa_objects['otpa'],
				$otpa_objects['otpa_passwordless'],
				$otpa_objects['otpa_2fa'],
				$otpa_objects['otpa_account_validation']
			) ||
			! $otpa_objects['settings'] instanceof Otpa_Settings ||
			! $otpa_objects['gateway'] instanceof Otpa_Abstract_Gateway ||
			! $otpa_objects['otpa'] instanceof Otpa ||
			! $otpa_objects['otpa_passwordless'] instanceof Otpa_Passwordless ||
			! $otpa_objects['otpa_2fa'] instanceof Otpa_2FA ||
			! $otpa_objects['otpa_account_validation'] instanceof Otpa_Account_Validation
		) {
			return;
		}

		$this->otpa_settings           = $otpa_objects['settings'];
		$this->gateway                 = $otpa_objects['gateway'];
		$this->otpa                    = $otpa_objects['otpa'];
		$this->otpa_passwordless       = $otpa_objects['otpa_passwordless'];
		$this->otpa_2fa                = $otpa_objects['otpa_2fa'];
		$this->otpa_account_validation = $otpa_objects['otpa_account_validation'];

		$this->init_hooks();
		do_action( 'otpa_integration_run', $this );
	}

	public function init_hooks() {}
}
