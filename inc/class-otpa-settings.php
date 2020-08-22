<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class Otpa_Settings {
	const DEFAULT_GATEWAY = 'otpa_wp_email_gateway';

	protected static $settings;

	protected $settings_fields;
	protected $error_message;
	protected $settings_renderer;

	public function __construct( $init_hooks = false, $settings_renderer = false ) {
		$this->load_textdomain();

		self::$settings          = self::get_options();
		$this->settings_renderer = $settings_renderer;

		if ( $init_hooks ) {
			add_action( 'init', array( $this, 'load_textdomain' ), 0, 0 );
			add_action( 'init', array( $this, 'set_cache_policy' ), 0, 0 );
			add_action( 'admin_menu', array( $this, 'plugin_options_menu_main' ), 10, 0 );
			add_action( 'wp_loaded', array( $this, 'init_settings_definition' ), 10, 0 );
			add_filter( 'plugin_action_links_otp-authenticator/otp-authenticator.php', array( $this, 'plugin_action_links' ), 10, 1 );

			add_filter( 'pre_update_option_otpa_settings', array( get_class(), 'sanitize_settings' ), 10, 2 );
			add_filter( 'default_option_otpa_settings', array( $this, 'maybe_init_settings' ), 10, 3 );
		}
	}

	/*******************************************************************
	 * Public methods
	 *******************************************************************/

	public static function get_options() {
		self::$settings = wp_cache_get( 'otpa_settings', 'otpa' );

		if ( ! self::$settings ) {
			self::$settings = get_option( 'otpa_settings' );

			wp_cache_set( 'otpa_settings', self::$settings, 'otpa' );
		}

		self::$settings = apply_filters( 'otpa_settings', self::sanitize_settings( self::$settings ) );

		return self::$settings;
	}

	public static function get_option( $key, $default = false ) {
		$options = self::get_options();
		$value   = isset( $options[ $key ] ) ? $options[ $key ] : $default;

		return apply_filters( 'otpa_option', $value, $key );
	}

	public static function get_current_gateway_id() {
		$gateway_id = self::get_option( 'gateway_id' );

		if ( ! $gateway_id ) {
			$gateway_id = apply_filters( 'otpa_default_gateway_id', self::DEFAULT_GATEWAY );
		}

		return $gateway_id;
	}

	public function plugin_action_links( $links ) {
		$links[] = '<a href="' . admin_url( 'options-general.php?page=otpa' ) . '">' . __( 'Settings' ) . '</a>';

		return $links;
	}

	public function set_cache_policy() {
		wp_cache_add_non_persistent_groups( 'otpa' );
	}

	public function load_textdomain() {
		load_plugin_textdomain( 'otpa', false, 'otp-authenticator/languages' );
	}

	public function validate() {
		$valid = apply_filters( 'otpa_settings_valid', true, self::get_options() );

		if ( true !== $valid ) {

			if ( is_array( $valid ) ) {
				$errors = $valid;
			} else {
				$errors = array( $valid );
			}

			$error_message = '<ul>';

			foreach ( $errors as $error ) {
				$error_message .= '<li>' . $error . '</li>';
			}

			$error_message      .= '</ul>';
			$this->error_message = $error_message;

			add_action( 'admin_notices', array( $this, 'settings_error' ) );

			$valid = false;
		}

		return $valid;
	}

	public function settings_error() {
		$href    = admin_url( 'options-general.php?page=otpa' );
		$link    = ' <a href="' . $href . '">' . __( 'Edit configuration', 'otpa' ) . '</a>';
		$class   = 'notice notice-error is-dismissible';
		$message = __( 'OTP Authenticator is not ready. ', 'otpa' );

		printf( '<div class="%1$s"><p>%2$s</p></div>', $class, $message . $link . $this->error_message ); // @codingStandardsIgnoreLine
	}

	public function plugin_options_menu_main() {
		register_setting(
			'otpa',
			'otpa_settings',
			array(
				'sanitize_callback' => array( get_class(), 'sanitize_settings' ),
			)
		);

		$title         = __( 'OTP Authenticator', 'otpa' );
		$capability    = 'manage_options';
		$menu_slug     = 'otpa';
		$parent_slug   = 'options-general.php';
		$callback      = array( $this, 'plugin_main_page' );
		$page_hook_id  = 'settings_page_otpa';
		$settings_page = add_submenu_page( $parent_slug, $title, $title, $capability, $menu_slug, $callback );

		if ( ! empty( $settings_page ) ) {
			add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ), 10, 1 );
		}

		$this->settings_renderer->register_settings( $this->settings_fields, 'otpa' );
	}

	public function admin_enqueue_scripts( $hook_suffix ) {

		if ( 'settings_page_otpa' === $hook_suffix ) {
			$debug   = apply_filters( 'otpa_debug', (bool) ( constant( 'WP_DEBUG' ) ) );
			$css_ext = ( $debug ) ? '.css' : '.min.css';
			$version = filemtime( OTPA_PLUGIN_PATH . 'css/admin/main' . $css_ext );

			wp_enqueue_style( 'otpa-main-style', OTPA_PLUGIN_URL . 'css/admin/main' . $css_ext, array(), $version );

			$script_params = apply_filters(
				'otpa_settings_js_parameters',
				array(
					'ajax_url' => admin_url( 'admin-ajax.php' ),
					'debug'    => $debug,
				)
			);

			$js_ext  = ( $debug ) ? '.js' : '.min.js';
			$version = filemtime( OTPA_PLUGIN_PATH . 'js/admin/main' . $js_ext );

			wp_enqueue_script(
				'otpa-main-script',
				OTPA_PLUGIN_URL . 'js/admin/main' . $js_ext,
				array( 'jquery' ),
				$version,
				true
			);
			wp_localize_script( 'otpa-main-script', 'OTPA', $script_params );
		}
	}

	public function plugin_main_page() {

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( __( 'Sorry, you are not allowed to access this page.', 'otpa' ) ); // @codingStandardsIgnoreLine
		}

		$active_tab = filter_input( INPUT_GET, 'tab', FILTER_SANITIZE_STRING );
		$active_tab = ( $active_tab ) ? $active_tab : 'settings';

		do_action( 'otpa_settings_page', $active_tab );
		ob_start();

		require_once apply_filters(
			'otpa_template_main-settings-page',  // @codingStandardsIgnoreLine
			OTPA_PLUGIN_PATH . 'inc/templates/admin/main-settings-page.php'
		);

		echo ob_get_clean(); // @codingStandardsIgnoreLine
	}

	public function maybe_init_settings( $default, $option, $passed_default ) {

		if ( ! is_array( $default ) ) {
			$default = array();
		}

		remove_filter( 'default_option_otpa_settings', array( $this, 'maybe_init_settings' ), 10 );
		update_option( 'otpa_settings', $default );
		add_filter( 'default_option_otpa_settings', array( $this, 'maybe_init_settings' ), 10, 3 );

		return $default;
	}

	public static function sanitize_settings( $settings, $old_settings = array() ) {

		if ( ! empty( $old_settings ) ) {
			set_transient( 'otpa_flush', 1, 60 );
		}

		if ( ! is_array( $settings ) ) {
			$settings = array();
		}

		$numeric = array(
			'validation_expiry',
			'max_verify',
			'otp_expiry',
			'track_expiry',
			'block_expiry',
		);

		$boolean = array(
			'enable_2fa',
			'enable_passwordless',
			'enable_validation',
		);

		$settings = array_merge( self::get_default_settings(), $settings );

		foreach ( $numeric as $index => $key ) {
			$settings[ $key ] = intval( $settings[ $key ] );
		}

		foreach ( $boolean as $index => $key ) {
			$settings[ $key ] = (bool) $settings[ $key ];
		}

		return apply_filters( 'otpa_sanitize_settings', $settings );
	}

	public function init_settings_definition() {
		$default_settings      = self::get_default_settings();
		$this->settings_fields = array(
			'main' => array(
				array(
					'id'      => 'gateway_id',
					'label'   => __( 'Authentication Gateway', 'otpa' ),
					'type'    => 'input_select',
					'value'   => apply_filters( 'otpa_authentication_gateways', array() ),
					'sort'    => 'asc',
					'default' => self::DEFAULT_GATEWAY,
					'class'   => '',
					'help'    => __( 'Authentication Gateway used to send the One-Time Password Verification Code.', 'otpa' ),
				),
				array(
					'id'    => 'enable_2fa',
					'label' => __( 'Enable Two-Factor Authentication', 'otpa' ),
					'type'  => 'input_checkbox',
					'class' => '',
					'help'  => __( 'When Two-Factor Authentication is enabled, users logging in with their login and password will be asked to login with the selected Authentication Gateway as well.', 'otpa' ) . '<br/>' . sprintf(
						// translators: %1$s is the 2FA shortcode, %2$s is the "OTPA 2FA Form" Gutenberg Block name
						__( 'Unless forced, users can opt in & out of Two Factor Authentication in their WordPress user profile, or with a button displayed either by the %1$s shortcode or with the %2$s Block.', 'otpa' ),
						'<code>[otpa_2fa_switch label="" class="" turn_on_text="" turn_off_text=""]</code>',
						'<code>' . __( 'OTPA 2FA Switch', 'otpa' ) . '</code>'
					) . '<br/>' . __( 'Enabling Two-Factor Authentication disables Passwordless Authentication and Account Validation.', 'otpa' ),
				),
				array(
					'id'    => 'force_2fa',
					'label' => __( 'Force Two-Factor Authentication', 'otpa' ),
					'type'  => 'input_checkbox',
					'class' => '',
					'help'  => __( 'When Two-Factor Authentication is enabled and this option is active, users cannot opt-out of using it during login.', 'otpa' ) . '<br/>' . __( 'If they have not set an identifier at registration, they will be asked to do so immediately at their next login.', 'otpa' ),
				),
				array(
					'id'    => 'default_2fa',
					'label' => __( 'Two-Factor Authentication enabled by default', 'otpa' ),
					'type'  => 'input_checkbox',
					'class' => '',
					'help'  => __( 'When Two-Factor Authentication is enabled and this option is active, users need to use Two-Factor Authentication at login by default, but can opt out at anytime.', 'otpa' ) . '<br/>' . __( 'If they have not set an identifier at registration, they will be asked to do so immediately at their next login.', 'otpa' ),
				),
				array(
					'id'    => 'enable_passwordless',
					'label' => __( 'Enable Passwordless Authentication', 'otpa' ),
					'type'  => 'input_checkbox',
					'class' => '',
					'help'  => __( 'When Passwordless Authentication is enabled, users can choose to log in with the selected Authentication Gateway instead of using their login and password.', 'otpa' ) . '<br/>' . __( 'Passwordless Authentication and Two-Factor Authentication cannot be enabled at the same time.', 'otpa' ),
				),
				array(
					'id'    => 'enable_validation',
					'label' => __( 'Enable Account Validation', 'otpa' ),
					'type'  => 'input_checkbox',
					'class' => '',
					'help'  => __( 'When Account Validation is enabled, logged in users with an account not previously validated or with an expired account validation are asked to validate their account with the selected Authentication Gateway before accessing the website.', 'otpa' ) . '<br/>' . __( 'Users will automatically be asked to validate their account upon editing the identifier used by the selected Authentication Gateway.', 'otpa' ) . '<br/>' . __( 'An account is validated whenever an OTP Verification Code is successfully used (including during Passwordless Authentication if enabled).', 'otpa' ) . '<br/>' . __( 'Account Validation and Two-Factor Authentication cannot be enabled at the same time.', 'otpa' ),
				),
				array(
					'id'      => 'validation_expiry',
					'label'   => __( 'Account Validation Expiry', 'otpa' ),
					'type'    => 'input_number',
					'min'     => -1,
					'step'    => 1,
					'default' => $default_settings['validation_expiry'],
					'class'   => '',
					'help'    => __( 'Expressed in hours.', 'otpa' ) . '<br/>' . __( 'After the specified duration, at their next login, users will be asked to validate their account again before accessing the website.', 'otpa' ) . '<br/>' . __( 'Set this value to -1 to never expire the validation ; set this value to 0 to force users to validate their account each time they log in.', 'otpa' ),
				),
				array(
					'id'    => 'validation_exclude_roles',
					'label' => __( 'Roles excluded from Account Validation', 'otpa' ),
					'type'  => 'checkbox_group',
					'value' => $this->get_roles(),
					'sort'  => 'asc',
					'class' => 'otpa-validation-exclude-roles-container',
					'help'  => __( 'Selected roles will not be asked to validate their account before accessing the website.', 'otpa' ),
				),
				array(
					'id'    => 'sandbox',
					'label' => __( 'Sandbox Mode', 'otpa' ),
					'type'  => 'input_checkbox',
					'class' => '',
					'help'  => __( 'When Sandbox Mode is on, the Authentication Gateway is bypassed and the One-Time Password Verification Code is send to the Activity Logs only.', 'otpa' ) . '<br/><strong>' . __( 'Do not use in production, users requesting OTP Verification Codes will not receive them!', 'otpa' ) . '</strong>',
				),
				array(
					'id'      => 'max_request',
					'label'   => __( 'OTP Verification Code Max. Requests', 'otpa' ),
					'type'    => 'input_number',
					'min'     => 0,
					'step'    => 1,
					'default' => $default_settings['max_request'],
					'class'   => '',
					'help'    => __( 'Maximum number of One-Time Password Verification Code requests allowed before the user gets temporarily blocked from requesting a new code.', 'otpa' ) . '<br/>' . __( 'If the number of requests reaches this value for a given user, OTP Verification Codes and requests are temporarily unusable.', 'otpa' ) . '<br/>' . __( 'Allowance is reset automatically after the user successfully used an OTP Verification Code, when the OTP Temporary Block expired, or when the OTP Tracking expired.', 'otpa' ) . '<br/>' . __( 'Set this value to 0 to allow unlimited requests (discouraged).', 'otpa' ),
				),
				array(
					'id'      => 'max_verify',
					'label'   => __( 'Max. Failed Verification Attempts', 'otpa' ),
					'type'    => 'input_number',
					'min'     => 0,
					'step'    => 1,
					'default' => $default_settings['max_verify'],
					'class'   => '',
					'help'    => __( 'Maximum number of failed One-Time Password Verification Code submission attempts allowed.', 'otpa' ) . '<br/>' . __( 'If the number of failed attempts reaches this value for a given user, OTP Verification Codes and requests are temporarily unusable.', 'otpa' ) . '<br/>' . __( 'Allowance is reset automatically after the user successfully used an OTP Verification Code, when the OTP Temporary Block expired, or when the OTP Tracking expired.', 'otpa' ) . '<br/>' . __( 'Set this value to 0 to allow unlimited attempts (discouraged).', 'otpa' ),
				),
				array(
					'id'      => 'request_frequency',
					'label'   => __( 'OTP Verification Code Request Frequency', 'otpa' ),
					'type'    => 'input_number',
					'min'     => 0,
					'step'    => 1,
					'default' => $default_settings['request_frequency'],
					'class'   => '',
					'help'    => __( 'Expressed in secondes.', 'otpa' ) . '<br/>' . __( 'Frequency of allowed One-Time Password Verification Code requests.', 'otpa' ) . '<br/>' . __( 'Prevents users from requesting codes too often.', 'otpa' ),
				),
				array(
					'id'      => 'otp_expiry',
					'label'   => __( 'OTP Verification Code Expiry', 'otpa' ),
					'type'    => 'input_number',
					'min'     => 1,
					'step'    => 1,
					'default' => $default_settings['otp_expiry'],
					'class'   => '',
					'help'    => __( 'Expressed in minutes.', 'otpa' ) . '<br/>' . __( 'The amount of time after which the One-Time Password Verification Code is no longer valid.', 'otpa' ) . '<br/>' . __( 'It is recommended to keep this value low, but no too low otherwise the users would be unable to receive and type their code before it expires.', 'otpa' ) . '<br/>' . __( 'Minimum 1 minute.', 'otpa' ),
				),
				array(
					'id'      => 'track_expiry',
					'label'   => __( 'OTP Tracking Expiry', 'otpa' ),
					'type'    => 'input_number',
					'min'     => 1,
					'step'    => 1,
					'default' => $default_settings['track_expiry'],
					'class'   => '',
					'help'    => __( 'Expressed in hours.', 'otpa' ) . '<br/>' . __( 'Used to determine when to expire "OTP Verification Code Max. Requests" and "Max. Failed Verification Attempts".', 'otpa' ) . '<br/>' . __( 'Minimum 1 hour.', 'otpa' ),
				),
				array(
					'id'      => 'block_expiry',
					'label'   => __( 'OTP Temporary Block Expiry', 'otpa' ),
					'type'    => 'input_number',
					'min'     => 1,
					'step'    => 1,
					'default' => $default_settings['block_expiry'],
					'class'   => '',
					'help'    => __( 'Expressed in minutes.', 'otpa' ) . '<br/>' . __( 'If one of "OTP Verification Code Max. Requests" or "Max. Failed Verification Attempts" is greater than 0 and is reached for a given user, this value determines how long the OTP Authentication is unusable.', 'otpa' ) . '<br/>' . __( 'Minimum 1 minute.', 'otpa' ),
				),
			),
		);

		$this->settings_fields = apply_filters( 'otpa_settings_fields', $this->settings_fields );
	}

	/*******************************************************************
	 * Protected methods
	 *******************************************************************/

	protected static function get_default_settings() {
		$default_settings = array(
			'gateway_id'               => self::DEFAULT_GATEWAY,
			'enable_2fa'               => false,
			'force_2fa'                => false,
			'default_2fa'              => false,
			'enable_passwordless'      => false,
			'enable_validation'        => false,
			'validation_expiry'        => -1,
			'validation_exclude_roles' => array(),
			'sandbox'                  => false,
			'otp_expiry'               => 5,
			'request_frequency'        => 30,
			'max_request'              => 10,
			'max_verify'               => 10,
			'track_expiry'             => 24,
			'block_expiry'             => 5,
		);

		return $default_settings;
	}

	protected function get_roles() {
		global $wp_roles;

		return $wp_roles->role_names;
	}

}
