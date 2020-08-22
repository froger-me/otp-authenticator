<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class Otpa_Abstract_Gateway {
	/**
	 * The gateway name.
	 *
	 * @var string
	 */
	protected $name;
	/**
	 * The gateway settings.
	 *
	 * @var array
	 */
	protected $settings;
	/**
	 * The gateway setting fields definition.
	 *
	 * @var array
	 */
	protected $settings_fields;
	/**
	 * The object used to render the setting fields.
	 *
	 * @var Otpa_Settings_Renderer
	 */
	protected $settings_renderer;
	/**
	 * The generated verification code length.
	 *
	 * @var int
	 */
	protected $code_length = 6;
	/**
	 * The generated verification code characters.
	 *
	 * @var string
	 */
	protected $code_chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
	/**
	 * Whether users can change their OTP Identifier while using this gateway.
	 *
	 * @var bool
	 */
	protected $can_change_identifier = true;
	/**
	 * The name of the metadata holding the OTP identifier.
	 *
	 * @var array
	 */
	protected $identifier_meta;

	/**
	 * Constructor
	 *
	 * @param bool $init_hooks Whether to add WordPress action and filter hooks on object creation ; default `false`.
	 * @param Otpa_Settings_Renderer|bool $settings_renderer The object used to render the setting fields.
	 */
	public function __construct( $init_hooks = false, $settings_renderer = false ) {
		$this->settings          = $this->get_options();
		$this->settings_renderer = $settings_renderer;

		if ( $init_hooks ) {
			add_action( 'wp_loaded', array( $this, 'init_settings_definition' ), 10, 0 );
			add_action( 'admin_menu', array( $this, 'register_gateway_settings' ), 10, 0 );
			add_action( 'otpa_after_main_settings', array( $this, 'print_gateway_settings' ), 10, 1 );
			add_action( 'otpa_after_main_tab_settings', array( $this, 'print_gateway_settings_tab' ), 5, 1 );

			add_filter( 'otpa_settings_valid', array( $this, 'validate_settings' ), 10, 2 );
			add_filter( 'pre_update_option_' . $this->get_gateway_id() . '_settings', array( $this, 'sanitize_settings' ), 10, 2 );
			add_filter( 'default_option_' . $this->get_gateway_id() . '_settings', array( $this, 'maybe_init_settings' ), 10, 3 );

			if ( $this->get_option( 'sync_metakey' ) ) {
				add_filter( 'update_user_metadata', array( $this, 'sync_meta' ), 10, 5 );
				add_filter( 'sanitize_user_meta_' . $this->get_option( 'sync_metakey' ), array( $this, 'sanitize_meta' ), 10, 3 );
			}
		}
	}

	/*******************************************************************
	 * Public methods
	 *******************************************************************/

	/**
	 * Get a gateway class name from a string (file name or gateway_id)
	 *
	 * @param string $data The string describing a gateway class.
	 * @param string The type of string (`gateway_id`and `filename` supported by default ; default`gateway_id`)
	 * @return string the gateway class name or `$gateway_string` if not found
	 */
	public static function get_gateway_class_name( $gateway_string, $type = 'gateway_id' ) {

		if ( 'filename' === $type ) {
			$gateway_slug       = str_replace( '.php', '', str_replace( 'class-', '', $gateway_string ) );
			$slug_parts         = array_map( 'ucfirst', explode( '-', $gateway_slug ) );
			$gateway_class_name = implode( '_', $slug_parts );
		} elseif ( 'gateway_id' === $type ) {
			$gateway_slug       = $gateway_string;
			$slug_parts         = array_map( 'ucfirst', explode( '_', $gateway_slug ) );
			$gateway_class_name = implode( '_', $slug_parts );
		} else {
			$gateway_class_name = apply_filters( 'otpa_gateway_class_name', $gateway_string, $type );
		}

		return $gateway_class_name;
	}

	/**
	 * Register the current gateway to make it known to the plugin.
	 *
	 * @param array $gateways The collection of known gateways.
	 * @return array the collection of known gateways
	 */
	public static function register_authentication_gateway( $gateways ) {

		if ( ! is_array( $gateways ) ) {
			$gateways = array();
		}

		$class   = get_called_class();
		$gateway = new $class();

		if ( ! empty( $gateway->name ) ) {
			$gateways[ strtolower( $class ) ] = $gateway->get_name();
		}

		return $gateways;
	}

	/**
	 * Get gateway options of a specified gateway ID.
	 *
	 * @param string $gateway_id The gateway ID.
	 * @return array the gateway options
	 */
	public static function get_gateway_options( $gateway_id ) {
		$settings = wp_cache_get( $gateway_id . '_settings', 'otpa' );

		if ( ! $settings ) {
			$settings = self::sanitize_gateway_settings( get_option( $gateway_id . '_settings' ), $gateway_id );

			wp_cache_set( $gateway_id . '_settings', $settings, 'otpa' );
		}

		if ( empty( $settings ) ) {
			$settings = self::get_default_settings();
		}

		$settings = apply_filters( $gateway_id . '_settings', $settings );

		return $settings;
	}

	/**
	 * Get a gateway option of a specified gateway ID for a specified option key.
	 *
	 * @param string $key The option key.
	 * @param string $gateway_id The gateway ID.
	 * @param mixed $default A default value if the option is not found.
	 * @return mixed the option value
	 */
	public static function get_gateway_option( $key, $gateway_id, $default = false ) {
		$options = self::get_gateway_options( $gateway_id );
		$value   = isset( $options[ $key ] ) ? $options[ $key ] : $default;

		return apply_filters( $gateway_id . '_option', $value, $key );
	}

	/**
	 * Sanitize the specified gateway settings of a specified gateway ID.
	 *
	 * @param array $settings The settings to sanitize.
	 * @param string $gateway_id The gateway ID.
	 * @return array the sanitized gateway settings
	 */
	public static function sanitize_gateway_settings( $settings, $gateway_id ) {

		if ( ! is_array( $settings ) ) {
			$settings = array();
		}

		if ( ! isset( $settings['sync_metakey'] ) ) {
			$settings['sync_metakey'] = '';
		}

		$settings = array_merge( self::get_default_settings(), $settings );

		return apply_filters( $gateway_id . '_sanitize_settings', $settings );
	}

	/**
	 * Get the gateway name.
	 *
	 * @return string the gateway name
	 */
	public function get_name() {
		return $this->name;
	}

	/**
	 * Get gateway options.
	 *
	 * @return array the gateway options
	 */

	public function get_options() {
		return self::get_gateway_options( $this->get_gateway_id() );
	}

	/**
	 * Get a gateway option for a specified option key.
	 *
	 * @param string $key The option key.
	 * @param mixed $default A default value if the option is not found.
	 * @return mixed the option value
	 */
	public function get_option( $key, $default = false ) {
		return self::get_gateway_option( $key, $this->get_gateway_id(), $default );
	}

	/**
	 * Sanitize the specified gateway settings.
	 *
	 * @param array $settings The settings to sanitize.
	 * @param array $settings The settings value before sanitization.
	 * @return array the sanitized gateway settings
	 */
	public function sanitize_settings( $settings, $old_settings = array() ) {
		return self::sanitize_gateway_settings( $settings, $this->get_gateway_id() );
	}

	/**
	 * Print the gateway settings form.
	 *
	 * @param string $active_tab The settings tab currently active.
	 */
	public function print_gateway_settings( $active_tab ) {
		do_action( 'otpa_gateway_settings_page', $active_tab );
		ob_start();

		require_once apply_filters(
			'otpa_template_gateway-settings-page', // @codingStandardsIgnoreLine
			OTPA_PLUGIN_PATH . 'inc/templates/admin/gateway-settings-page.php'
		);

		echo ob_get_clean(); // @codingStandardsIgnoreLine
	}

	/**
	 * Print the gateway settings tab.
	 *
	 * @param string $active_tab The settings tab currently active.
	 */
	public function print_gateway_settings_tab( $active_tab ) {
		do_action( 'otpa_gateway_settings_tab', $active_tab );
		ob_start();

		require_once apply_filters(
			'otpa_template_gateway-settings-tab', // @codingStandardsIgnoreLine
			OTPA_PLUGIN_PATH . 'inc/templates/admin/gateway-settings-tab.php'
		);

		echo ob_get_clean(); // @codingStandardsIgnoreLine
	}

	/**
	 * Get the gateway ID.
	 *
	 * @return string the gateway ID
	 */
	public function get_gateway_id() {
		return strtolower( get_called_class() );
	}

	/**
	 * Validate the gateway settings.
	 *
	 * @return bool whether the gateway settings are valid
	 */
	public function validate_settings( $valid, $settings ) {
		return $valid;
	}

	/**
	 * Initialize the gateway setting fields definition.
	 *
	 * @return array the gateway setting fields definition
	 */
	public function init_settings_definition() {
		return array();
	}

	/**
	 * Register the gateway setting fields in WordPress Settings API.
	 */
	public function register_gateway_settings() {
		register_setting(
			$this->get_gateway_id(),
			$this->get_gateway_id() . '_settings',
			array(
				'sanitize_callback' => array( $this, 'sanitize_settings' ),
			)
		);

		if ( is_object( $this->settings_renderer ) ) {
			$this->settings_renderer->register_settings( $this->settings_fields, $this->get_gateway_id() );
		}
	}

	/**
	 * Initialize the gateway settings with default values if necessary.
	 *
	 * @param $default mixed The default value to return if the option does not exist in the database
	 * @param $option string Option name.
	 * @param $passed_default bool whether get_option() was passed a default value
	 * @return mixed the default value to return if the option does not exist in the database
	 */
	public function maybe_init_settings( $default, $option, $passed_default ) {

		if ( ! is_array( $default ) ) {
			$default = array();
		}

		remove_filter( 'default_option_' . $this->get_gateway_id() . '_settings', array( $this, 'maybe_init_settings' ), 10 );
		update_option( $this->get_gateway_id() . '_settings', $default );
		add_filter( 'default_option_' . $this->get_gateway_id() . '_settings', array( $this, 'maybe_init_settings' ), 10, 3 );

		return $default;
	}

	/**
	 * Get the One-Time Password HTML Widget to display on the OTP form for the gateway - register associated scripts if necessary.
	 *
	 * @return string the One-Time Password HTML Widget
	 */
	public function get_otp_widget() {
		$this->add_otp_widget_scripts();

		$widget = $this->get_otp_widget_markup();

		return $widget;
	}

	/**
	 * Request an OTP Verification Code.
	 *
	 * @param $identifier string The OTP user identifier
	 * @param $error_handler mixed An error handler method - params: string $error_code, array $data, string $message, WP_Error|bool $wp_error
	 * @param $sandbox bool whether to use sandbox mode - default `false`
	 * @return array|WP_Error the result of the request
	 */
	public function request_code( $identifier, $error_handler, $sandbox = false ) {
		$loaded = $this->load_library();

		if ( $loaded ) {
			$init = $this->init_api();

			if ( $init ) {
				$maybe_valid = $this->validate_input_identifier( $identifier );

				if ( is_wp_error( $maybe_valid ) ) {

					return call_user_func_array( $error_handler, array( '', array(), '', $maybe_valid ) );
				}

				$otp_code = otpa_generate_otp_code( $this->code_length, $this->code_chars );

				if ( $sandbox ) {
					$result = $this->send_sandox_request(
						$this->sanitize_user_identifier( $identifier ),
						$this->build_message( $identifier, $otp_code )
					);
				} else {
					$result = $this->send_request(
						$this->sanitize_user_identifier( $identifier ),
						$this->build_message( $identifier, $otp_code )
					);
				}

				if ( true !== $result['status'] ) {

					return call_user_func_array(
						$error_handler,
						array(
							$result['code'],
							array(
								'method'     => __METHOD__,
								'identifier' => $identifier,
							),
							$result['message'],
						)
					);
				} else {
					$result['otp_code'] = $otp_code;

					return $result;
				}
			} else {

				return call_user_func_array(
					$error_handler,
					array(
						'OTPA_INIT_API_FAILED',
						array(
							'method'     => __METHOD__,
							'identifier' => $identifier,
							'force_log'  => true,
						),
						__( 'A system error occurred: unable to initialize the Authentication Gateway API.', 'otpa' ) . '<br/>' . __( 'Please try again - if the problem persists, please contact an administrator.', 'otpa' ),
					)
				);
			}
		} else {

			return call_user_func_array(
				$error_handler,
				array(
					'OTPA_LOAD_LIBRARY_FAILED',
					array(
						'method'     => __METHOD__,
						'identifier' => $identifier,
						'force_log'  => true,
					),
					__( 'A system error occurred: unable to load the Authentication Gateway Library.', 'otpa' ) . '<br/>' . __( 'Please try again - if the problem persists, please contact an administrator.', 'otpa' ),
				)
			);
		}
	}

	/**
	 * Get the user's OTP identifier.
	 *
	 * @param $user_id int The user ID - if falsey, will try to use the current user ; default `false`
	 * @return string the user's OTP identifier
	 */
	public function get_user_identifier( $user_id = false ) {

		if ( ! $user_id ) {
			$user    = wp_get_current_user();
			$user_id = $user->ID;
		}

		$identifier = get_user_meta( $user_id, $this->identifier_meta, true );

		return $this->sanitize_user_identifier( $identifier, $user_id );
	}

	/**
	 * Set the user's OTP identifier.
	 *
	 * @param $identifier string The OTP identifier to set for the user
	 * @param $user_id int The user ID - if falsey, will try to use the current user ; default `false`
	 * @return bool|string false on failure, the new identifier on success
	 */
	public function set_user_identifier( $identifier, $user_id = false ) {

		if ( ! $user_id ) {
			$user    = wp_get_current_user();
			$user_id = $user->ID;
		}

		$sync_metakey   = $this->get_option( 'sync_metakey' );
		$old_identifier = $this->sanitize_user_identifier(
			get_user_meta( $user_id, $this->identifier_meta, true ),
			$user_id
		);

		if ( false !== $this->get_user_by_identifier( $identifier ) ) {

			return false;
		}

		$identifier = $this->sanitize_user_identifier( $identifier, $user_id );

		update_user_meta( $user_id, $this->identifier_meta, $identifier );

		if ( $sync_metakey ) {
			remove_filter( 'update_user_metadata', array( $this, 'sync_meta' ), 10 );
			update_user_meta( $user_id, $sync_metakey, $identifier );
			add_filter( 'update_user_metadata', array( $this, 'sync_meta' ), 10, 5 );
		}

		do_action( 'otpa_identifier_updated', $user_id, $identifier, $old_identifier );

		return $identifier;
	}

	/**
	 * Get a user by OTP identifier if exists.
	 *
	 * @param $identifier string The OTP identifier to get a user by.
	 * @return bool|WP_User false on failure, the user on success
	 */
	public function get_user_by_identifier( $identifier ) {
		$user = false;

		if ( ! empty( $identifier ) ) {
			$user_query = new WP_User_Query(
				array(
					'meta_key'   => $this->identifier_meta,
					'meta_value' => $this->sanitize_user_identifier( $identifier ),
				)
			);

			$result = $user_query->get_results();

			if ( ! empty( $result ) && 1 === count( $result ) ) {
				$user = reset( $result );
			}
		}

		return $user;
	}

	/**
	 * Synchronize the metadata holding the OTP identifier with a metadata if such a key was specified in the `sync_metakey` gateway settings.
	 *
	 * @param $check mixed|null null if continue saving the value into the database
	 * @param $object_id int ID of the object metadata is for
	 * @param $meta_key string Metadata key
	 * @param $meta_value mixed Metadata value. Must be serializable if non-scalar.
	 * @param $prev_value mixed The previous metadata value.
	 * @return $check mixed|null null if continue saving the value into the database
	 */
	public function sync_meta( $check, $object_id, $meta_key, $meta_value, $prev_value ) {

		if ( $this->get_option( 'sync_metakey' ) === $meta_key ) {

			if ( false === $this->get_user_by_identifier( $meta_value ) ) {
				$this->set_user_identifier( $meta_value, $object_id );

				$meta_value = $this->sanitize_user_identifier( $meta_value );
			} else {
				delete_user_meta( $object_id, $this->get_option( 'sync_metakey' ) );

				$check = false;
			}
		}

		return $check;
	}

	/**
	 * Sanitize the OTP identifier metadata before saving in the database
	 *
	 * @param $meta_value mixed Metadata value to sanitize.
	 * @param $meta_key string Metadata key.
	 * @param $object_type string Type of object metadata is for - 'user' here
	 * @return mixed the sanitized OTP identifier metadata
	 */
	public function sanitize_meta( $meta_value, $meta_key, $object_type ) {
		return $this->sanitize_user_identifier( $meta_value );
	}

	/**
	 * Sanitize the OTP identifier
	 *
	 * @param $identifier string The OTP identifier to sanitize.
	 * @param $user_id int The user ID - if falsey, should try to use the current user ; default `false`
	 * @return string the sanitized OTP identifier
	 */
	public function sanitize_user_identifier( $identifier, $user_id = false ) {
		return $identifier;
	}

	/**
	 * Check whether an OTP identifier is valid
	 *
	 * @param $identifier string The OTP identifier to check.
	 * @return bool whether the OTP identifier is valid
	 */
	public function is_valid_identifier( $identifier ) {
		return true;
	}

	/**
	 * Check whether the gateway allows the user OTP identifiers to be changed
	 *
	 * @return bool whether the gateway allows the user OTP identifiers to be changed
	 */
	public function allow_edit_identifier() {
		return $this->can_change_identifier;
	}

	/*******************************************************************
	 * Protected methods
	 *******************************************************************/

	/**
	 * Get gateway's default settings
	 *
	 * @return array the gateway's default settings
	 */
	protected static function get_default_settings() {
		return array();
	}

	/**
	 * Add the scripts associated with the One-Time Password HTML Widget to display on the OTP form for the gateway
	 *
	 * @param bool $css whether to add the CSS style
	 * @param bool $js whether to add the JavaScript scripts
	 */
	protected function add_otp_widget_scripts( $css = true, $js = true ) {
		$debug               = apply_filters( 'otpa_debug', (bool) ( constant( 'WP_DEBUG' ) ) );
		$script_name         = str_replace( '_', '-', $this->get_gateway_id() );
		$localisation_object = 'OTPA_' . $this->get_gateway_id();
		$js_ext              = ( $debug ) ? '.js' : '.min.js';
		$css_ext             = ( $debug ) ? '.css' : '.min.css';

		if ( $js ) {

			if ( ! is_file( OTPA_PLUGIN_PATH . 'inc/gateways/js/' . $script_name . $js_ext ) ) {
				$this->add_otp_widget_js( $js_ext, $debug );
			} else {
				$this->add_otp_widget_js( $js_ext, $debug, $script_name, $localisation_object );
			}
		}

		if ( $css ) {

			if ( ! is_file( OTPA_PLUGIN_PATH . 'inc/gateways/css/' . $script_name . $css_ext ) ) {
				$this->add_otp_widget_css( $css_ext, $debug );
			} else {
				$this->add_otp_widget_css( $css_ext, $debug, $script_name );
			}
		}
	}

	/**
	 * Add the JavaScript script associated with the One-Time Password HTML Widget to display on the OTP form for the gateway
	 *
	 * @param string $ext the extension of the script to add
	 * @param bool $debug whether to add debug JavaScript script
	 * @param string $script_name the name to register - default `'otpa-default-gateway'`
	 * @param string $localisation_object the name of the JavaScript object used for localization - default `'OTPA_default_gateway'`
	 */
	protected function add_otp_widget_js(
		$ext,
		$debug,
		$script_name = 'otpa-default-gateway',
		$localisation_object = 'OTPA_default_gateway'
	) {
		$version = filemtime( OTPA_PLUGIN_PATH . 'inc/gateways/js/' . $script_name . $ext );
		$params  = array(
			'debug' => $debug,
		);
		wp_enqueue_script(
			'otpa-' . $script_name . '-script',
			OTPA_PLUGIN_URL . 'inc/gateways/js/' . $script_name . $ext,
			array( 'jquery', 'otpa-main-script' ),
			$version,
			true
		);

		wp_localize_script( 'otpa-' . $script_name . '-script', $localisation_object, $params );
	}

	/**
	 * Add the CSS style associated with the One-Time Password HTML Widget to display on the OTP form for the gateway
	 *
	 * @param string $ext the extension of the style to add
	 * @param bool $debug whether to add debug CSS style
	 * @param string $style_name the name to register - default `'otpa-default-gateway'`
	 */
	protected function add_otp_widget_css( $ext, $debug, $style_name = 'otpa-default-gateway' ) {
		$version = filemtime( OTPA_PLUGIN_PATH . 'inc/gateways/css/' . $style_name . $ext );

		wp_enqueue_style(
			'otpa-' . $style_name . '-style',
			OTPA_PLUGIN_URL . 'inc/gateways/css/' . $style_name . $ext,
			array(),
			$version
		);
	}

	/**
	 * Get the One-Time Password HTML Widget markup for the gateway
	 *
	 * @return string the HTML Widget markup
	 */
	protected function get_otp_widget_markup() {
		$template_name = str_replace( '_', '-', $this->get_gateway_id() ) . '-otp-widget.php';

		if ( ! is_file( OTPA_PLUGIN_PATH . 'inc/gateways/templates/' . $template_name ) ) {
			$template_name = 'otpa-default-gateway-otp-widget.php';
		}

		ob_start();

		include OTPA_PLUGIN_PATH . 'inc/gateways/templates/' . $template_name;

		$widget = ob_get_clean();

		return $widget;
	}

	/**
	 * Load the PHP libraries needed by the gateway before sending messages
	 *
	 * @return bool whether the libraries were successfully loaded
	 */
	protected function load_library() {
		return true;
	}

	/**
	 * Initialize the API used by the gateway before sending messages
	 *
	 * @return bool whether the API was successfully initialized
	 */
	protected function init_api() {
		return true;
	}

	/**
	 * Check whether the specified OTP identifier matches the current user's OTP identifier
	 *
	 * @param string $identifier the specified OTP identifier
	 * @return bool whether the specified OTP identifier matches the current user's OTP identifier
	 */
	protected function validate_input_identifier( $identifier ) {
		return $this->get_user_identifier() === $this->sanitize_user_identifier( $identifier );
	}

	/**
	 * Build the message to send to the user via the gateway
	 *
	 * @param string $identifier the OTP identifier or the recipient
	 * @param string $otp_code the OTP code to send
	 * @return string the message to send
	 */
	protected function build_message( $identifier, $otp_code ) {
		return $otp_code;
	}

	/**
	 * Sent a request to the gateway's API
	 *
	 * @param string $identifier the OTP identifier or the recipient
	 * @param string $message the message to send
	 * @return array the result of the request ; keys: (bool) 'status', (string) message', (string) 'code'
	 */
	protected function send_request( $identifier, $message ) {
		return array(
			'status'  => true,
			// translators: %s is the user's OTP identifier
			'message' => sprintf( __( 'A Verification Code was sent to %s.', 'otpa' ), $identifier ),
			'code'    => 'OK',
		);
	}

	/**
	 * Simulate sending a request to the gateway's API
	 *
	 * @param string $identifier the OTP identifier or the recipient
	 * @param string $message the message to send
	 * @return array the result of the request ; keys: (bool) 'status', (string) message', (string) 'code'
	 */
	protected function send_sandox_request( $identifier, $message ) {
		otpa_db_log(
			array(
				'message' => __( 'Sandbox simulated request - data sent to the Authentication Gateway: ', 'otpa' ),
				'data'    => array(
					'identifier' => $identifier,
					'content'    => $message,
				),
			)
		);

		return array(
			'status'  => true,
			// translators: %s is the user's OTP identifier
			'message' => sprintf( __( 'An Verification Code was sent to %s (sandox).', 'otpa' ), $identifier ),
			'code'    => 'OK',
		);
	}
}
