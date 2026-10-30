<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class Otpa_2FA {
	protected static $authorized_api_handlers;

	protected $otpa;
	protected $otpa_settings;

	public function __construct( $otpa, $init_hooks = false ) {
		$this->otpa_settings           = $otpa->get_settings();
		$this->otpa                    = $otpa;
		self::$authorized_api_handlers = apply_filters(
			'otpa_api_2fa_handlers',
			array(
				'switch_2fa',
			)
		);

		add_shortcode( 'otpa_2fa_switch', array( $this, 'add_2fa_button_switch_shortcode' ) );
		add_action( 'init', array( $this, 'add_2fa_button_switch_editor_block' ), 10, 0 );

		if ( $init_hooks ) {
			add_action( 'admin_init', array( $this, 'admin_redirect' ), 10, 0 );
			add_action( 'init', array( $this, 'check_2fa' ), 10, 0 );
			add_action( 'wp_login', array( $this, 'set_need_2fa_check' ), 10, 2 );
			add_action( 'otpa_otp_code_verified', array( $this, 'do_2fa' ), 10, 2 );
			add_action( 'wp_enqueue_scripts', array( $this, 'add_current_user_2fa_button_switch_scripts' ), 10, 0 );
			add_action( 'otpa_api_2fa', array( $this, 'handle_api_request' ), 10, 0 );

			add_filter( 'login_redirect', array( $this, 'login_redirect' ), 10, 3 );
			add_filter( 'show_admin_bar', array( $this, 'hide_admin_bar' ) );
			add_filter( 'otpa_api_endpoints', array( $this, 'add_api_endpoints' ), 10, 1 );
			add_filter( 'authenticate', array( $this, 'deny_xmlrpc_authentication' ), 99, 3 );
		}
	}

	// Public methods.

	public function add_api_endpoints( $endpoints ) {
		$endpoints['2fa'] = '2fa';

		return $endpoints;
	}

	public function login_redirect( $redirect_to, $requested_redirect_to, $user ) {

		if ( ! is_wp_error( $user ) && $this->need_2fa_check( $user ) ) {
			update_user_meta( $user->ID, 'otpa_2fa_redirect', $requested_redirect_to );

			$redirect_to = home_url();
		}

		return $redirect_to;
	}

	public function admin_redirect() {
		$user = wp_get_current_user();

		if ( $this->need_2fa_check( $user ) ) {

			if ( wp_doing_ajax() ) {
				wp_send_json_error(
					array(
						'message' => __( 'OTP Authenticator: Two-Factor Authentication required.', 'otpa' ),
					),
					403
				);
			}

			update_user_meta( $user->ID, 'otpa_2fa_redirect', otpa_get_current_url() );
			wp_safe_redirect( home_url() );

			exit();
		}
	}

	public function hide_admin_bar( $show ) {
		return $show && ! $this->need_2fa_check();
	}

	public function handle_api_request() {
		$request = filter_input_array( INPUT_POST, FILTER_UNSAFE_RAW );
		$request = is_array( $request ) ? $request : array();
		$nonce   = isset( $request['nonce'] ) && is_string( $request['nonce'] )
			? sanitize_text_field( $request['nonce'] )
			: '';

		if ( ! wp_verify_nonce( $nonce, 'otpa_2fa_nonce' ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'Error: unauthorized access - please reload the page and try again.', 'otpa' ),
				)
			);
		}

		$handler       = isset( $request['handler'] ) && is_string( $request['handler'] )
			? sanitize_key( $request['handler'] )
			: '';
		$valid_handler = apply_filters(
			'otpa_2fa_api_valid_callback',
			method_exists( $this, $this->sanitizer_api_handler( $handler ) ),
			$handler
		);

		if ( ! $valid_handler ) {
			wp_send_json_error(
				array(
					'message' => __( 'Error: unauthorized operation - if the problem persists, please contact an administrator.', 'otpa' ),
				)
			);
		}
		$callback = apply_filters(
			'otpa_2fa_api_callback',
			array( $this, $handler ),
			$handler
		);
		$args     = apply_filters(
			'otpa_2fa_api_callback_args',
			isset( $request['payload'] ) && is_array( $request['payload'] )
				? map_deep( $request['payload'], 'sanitize_text_field' )
				: array(),
			$handler
		);

		$result = call_user_func_array( $callback, array( $args ) );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error(
				array(
					'code'    => $result->get_error_code(),
					'message' => $result->get_error_message(),
					'data'    => apply_filters(
						'otpa_api_error_data',
						$result->get_error_data( $result->get_error_code() ),
						$result->get_error_code()
					),
				)
			);
		}

		wp_send_json_success( $result );
	}

	public function check_2fa() {

		if ( $this->need_2fa_check() ) {
			$user = wp_get_current_user();

			if ( ! metadata_exists( 'user', $user->ID, 'otpa_2fa_redirect' ) ) {
				update_user_meta( $user->ID, 'otpa_2fa_redirect', otpa_get_current_url() );
			}

			add_action( 'parse_request', array( $this, 'deny_state_changing_request' ), PHP_INT_MIN, 1 );

			add_filter( 'rest_pre_dispatch', array( $this, 'deny_rest' ), PHP_INT_MIN, 3 );

			if ( $this->otpa->allow_template_redirect() ) {
				add_action( 'template_redirect', array( $this->otpa, 'otp_form_page' ), PHP_INT_MIN, 0 );

				add_filter( 'otpa_otp_form_vars', array( $this, 'set_otp_form_vars' ), 10, 1 );
				add_filter( 'otpa_otp_api_valid_callback', array( $this, 'otp_api_valid_callback' ), 10, 3 );
				add_filter( 'otpa_otp_api_callback', array( $this, 'otp_api_callback' ), 10, 3 );
			}
		}
	}

	public function set_otp_form_vars( $vars ) {
		$vars['otp_form_type']  = $this->get_otp_type();
		$vars['otp_form_title'] = __( 'Two-Factor Authentication', 'otpa' );

		return $vars;
	}

	public function otp_api_valid_callback( $valid, $handler, $otp_type ) {
		return $valid && $otp_type === $this->get_otp_type();
	}

	// phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- The filter supplies the OTP type as its third argument.
	public function otp_api_callback( $callback, $handler, $otp_type ) {
		return ( 'verify_otp_code' === $handler ) ? array( $this, $handler ) : $callback;
	}

	public function is_user_2fa_checked( $user = false ) {
		$checked = true;
		$user    = ( $user ) ? $user : wp_get_current_user();

		if ( $user->ID ) {
			$checked = (bool) get_user_meta( $user->ID, 'otpa_2fa_checked', true );

			if ( $checked ) {
				delete_user_meta( $user->ID, 'otpa_2fa_redirect' );
			}
		}

		return $checked;
	}

	public function deny_rest( $result, $wp_rest_server, $wp_rest_request ) {
		$deny = apply_filters(
			'otpa_2fa_deny_rest',
			true,
			$result,
			$wp_rest_server,
			$wp_rest_request
		);

		if ( $deny ) {
			return new WP_Error(
				'otpa_rest_denied',
				__( 'OTP Authenticator: Two-Factor Authentication required.', 'otpa' ),
				array(
					'status' => 403,
				)
			);
		}

		return $result;
	}

	public function deny_state_changing_request( $wp ) {
		$is_otp_endpoint = (
			isset( $wp->query_vars['__otpa_api'], $wp->query_vars['action'] ) &&
			'otp' === $wp->query_vars['action']
		);
		$request_method  = strtoupper( sanitize_key( (string) filter_input( INPUT_SERVER, 'REQUEST_METHOD', FILTER_UNSAFE_RAW ) ) );

		if ( ! $is_otp_endpoint && in_array( $request_method, array( 'POST', 'PUT', 'PATCH', 'DELETE' ), true ) ) {
			wp_die(
				esc_html__( 'OTP Authenticator: Two-Factor Authentication required.', 'otpa' ),
				esc_html__( 'Access denied', 'otpa' ),
				array( 'response' => 403 )
			);
		}
	}

	// phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed, Generic.CodeAnalysis.UnusedFunctionParameter.Found -- WordPress supplies credentials to this authentication filter.
	public function deny_xmlrpc_authentication( $user, $username, $password ) {
		if (
			defined( 'XMLRPC_REQUEST' ) &&
			XMLRPC_REQUEST &&
			$user instanceof WP_User &&
			$this->need_2fa_check( $user )
		) {
			return new WP_Error(
				'otpa_2fa_required',
				__( 'OTP Authenticator: Two-Factor Authentication required.', 'otpa' )
			);
		}

		return $user;
	}

	public function is_user_2fa_active( $user = false ) {
		$user = ( $user ) ? $user : wp_get_current_user();

		return (bool) get_user_meta( $user->ID, 'otpa_2fa_active', true );
	}

	public function set_user_2fa_active( $user = false, $active = true ) {
		$user = ( $user ) ? $user : wp_get_current_user();

		update_user_meta( $user->ID, 'otpa_2fa_active', $active );
	}

	public function need_2fa_check( $user = false ) {
		$user            = ( $user ) ? $user : wp_get_current_user();
		$force_2fa_check = $this->otpa_settings->get_option( 'force_2fa' );
		$need_2fa_check  = (bool) get_user_meta( $user->ID, 'otpa_need_2fa_check', true );

		return (
			( $this->is_user_2fa_active( $user ) || $force_2fa_check ) &&
			$need_2fa_check &&
			! $this->is_user_2fa_checked( $user )
		);
	}

	public function set_need_2fa_check( $user_login = false, $user = false, $force = false ) {
		$user = ( $user ) ? $user : wp_get_current_user();

		if ( ! $user->ID ) {

			return;
		}

		$meta = get_user_meta( $user->ID, 'otpa_2fa_checked', true );

		if ( $meta && $force ) {
			delete_user_meta( $user->ID, 'otpa_2fa_checked', $meta );
			update_user_meta( $user->ID, 'otpa_need_2fa_check', true );

			return;
		}

		if ( ! metadata_exists( 'user', $user->ID, 'otpa_2fa_active' ) && $this->otpa_settings->get_option( 'default_2fa' ) ) {
			$this->set_user_2fa_active( $user );

			$is_user_active_2fa = true;
		} else {
			$is_user_active_2fa = $this->is_user_2fa_active( $user );
		}

		if ( $is_user_active_2fa || $this->otpa_settings->get_option( 'force_2fa' ) ) {

			if ( $meta ) {
				delete_user_meta( $user->ID, 'otpa_2fa_checked', $meta );
				update_user_meta( $user->ID, 'otpa_need_2fa_check', true );
			} else {
				update_user_meta( $user->ID, 'otpa_need_2fa_check', true );
			}
		}
	}

	public function do_2fa( $user_id, $purpose ) {

		if ( '2fa' !== $purpose ) {

			return;
		}

		update_user_meta( $user_id, 'otpa_2fa_checked', true );
		delete_user_meta( $user_id, 'otpa_need_2fa_check' );
	}

	public function verify_otp_code( $payload ) {
		$payload['otp_purpose'] = '2fa';
		$result                 = $this->otpa->verify_otp_code( $payload );

		if ( ! is_wp_error( $result ) ) {
			$user               = wp_get_current_user();
			$result['redirect'] = get_user_meta( $user->ID, 'otpa_2fa_redirect', true );

			delete_user_meta( $user->ID, 'otpa_2fa_redirect' );
		}

		return $result;
	}

	public function add_current_user_2fa_button_switch_scripts() {
		$debug   = apply_filters( 'otpa_debug', (bool) ( constant( 'WP_DEBUG' ) ) );
		$js_ext  = ( $debug ) ? '.js' : '.min.js';
		$version = filemtime( OTPA_PLUGIN_PATH . 'js/user-2fa-switch' . $js_ext );

		wp_register_script(
			'otpa-user-2fa-switch-script',
			OTPA_PLUGIN_URL . 'js/user-2fa-switch' . $js_ext,
			array( 'jquery' ),
			$version,
			true
		);
	}

	public function block_current_user_2fa_button_switch( $attrs ) {
		$content = $this->get_user_2fa_button_switch(
			$attrs['label'],
			$attrs['turnOffText'],
			$attrs['turnOnText'],
			$attrs['className']
		);

		return $content;
	}

	public function add_2fa_button_switch_shortcode( $attrs = array(), $content = '' ) {
		$attrs = shortcode_atts(
			array(
				'label'         => '',
				'turn_on_text'  => '',
				'turn_off_text' => '',
				'class'         => '',
			),
			$attrs,
			'otpa_2fa_switch'
		);

		$content = $this->get_user_2fa_button_switch(
			$attrs['label'],
			$attrs['turn_off_text'],
			$attrs['turn_on_text'],
			$attrs['class']
		);

		return $content;
	}

	public function add_2fa_button_switch_editor_block() {

		if ( ! function_exists( 'register_block_type' ) ) {

			return;
		}

		$debug   = apply_filters( 'otpa_debug', (bool) ( constant( 'WP_DEBUG' ) ) );
		$js_ext  = ( $debug ) ? '.js' : '.min.js';
		$version = filemtime( OTPA_PLUGIN_PATH . 'js/user-2fa-switch' . $js_ext );

		wp_register_script(
			'otpa-block-2fa-switch-script',
			OTPA_PLUGIN_URL . 'js/admin/block-2fa-switch' . $js_ext,
			array(
				'wp-blocks',
				'wp-i18n',
				'wp-element',
				'wp-components',
				'wp-editor',
			),
			$version,
			false
		);

		$version = filemtime( OTPA_PLUGIN_PATH . 'js/user-2fa-switch' . $js_ext );

		wp_register_script(
			'otpa-user-2fa-switch-script',
			OTPA_PLUGIN_URL . 'js/user-2fa-switch' . $js_ext,
			array( 'jquery' ),
			$version,
			true
		);

		register_block_type(
			'otpa/otpa-2fa-switch',
			array(
				'editor_script'   => 'otpa-block-2fa-switch-script',
				'script'          => 'otpa-user-2fa-switch-script',
				'render_callback' => array( $this, 'block_current_user_2fa_button_switch' ),
				'attributes'      => array(
					'label'       => array(
						'default' => '',
						'type'    => 'string',
					),
					'turnOnText'  => array(
						'default' => '',
						'type'    => 'string',
					),
					'turnOffText' => array(
						'default' => '',
						'type'    => 'string',
					),
					'className'   => array(
						'default' => '',
						'type'    => 'string',
					),
				),
			)
		);
	}

	public function switch_2fa( $payload ) {
		$result = false;

		if ( ! $this->otpa_settings->get_option( 'force_2fa' ) ) {

			if ( isset( $payload['user_id'] ) ) {
				$user_id = intval( $payload['user_id'] );
				$user    = wp_get_current_user();

				if ( $user->ID !== $user_id || ! is_user_logged_in() ) {
					$result = $this->otpa->get_wp_error(
						'OTPA_INVALID_USER',
						array(
							'method'  => __METHOD__,
							'user_id' => $user_id,
						)
					);
				} else {
					$result = true;

					$this->set_user_2fa_active( $user, ! $this->is_user_2fa_active() );
				}
			} else {
				$result = $this->otpa->get_wp_error(
					'OTPA_INVALID_USER',
					array(
						'method' => __METHOD__,
					)
				);
			}

			if ( ! is_wp_error( $result ) ) {
				$result = array(
					'active' => $this->is_user_2fa_active(),
				);
			} else {
				$result = array(
					'message' => __( 'An error occured.', 'otpa' ) . '<br/>' . __( 'Please reload the page and try again - if the problem persists, please contact an administrator.', 'otpa' ),
				);
			}
		}

		return $result;
	}

	public function get_user_2fa_button_switch( $label = '', $on_text = '', $off_text = '', $_class = '' ) {
		$user = wp_get_current_user();

		if ( ! $this->is_block_editor() ) {

			if (
				! is_user_logged_in() ||
				$this->otpa_settings->get_option( 'force_2fa' ) ||
				! $this->otpa_settings->get_option( 'enable_2fa' )
			) {

				return '';
			}
		}

		$debug  = apply_filters( 'otpa_debug', (bool) ( constant( 'WP_DEBUG' ) ) );
		$params = apply_filters(
			'otpa_user_2fa_switch_scripts_params',
			array(
				'debug'        => $debug,
				'otpa_api_url' => home_url( 'otpa-api/2fa' ),
			)
		);

		wp_enqueue_script( 'otpa-user-2fa-switch-script' );
		wp_localize_script( 'otpa-user-2fa-switch-script', 'OTPA', $params );

		$_class   = ( empty( $_class ) ) ? 'otpa-user-2fa-button-switch' : esc_attr( $_class ) . ' otpa-user-2fa-button-switch';
		$on_text  = ( empty( $on_text ) ) ? __( 'Two-Factor Authentication is ON - Click to Disable', 'otpa' ) : $on_text;
		$off_text = ( empty( $off_text ) ) ? __( 'Two-Factor Authentication is OFF - Click to Enable', 'otpa' ) : $off_text;
		$active   = $this->is_user_2fa_active( $user );
		$nonce    = wp_nonce_field( 'otpa_2fa_nonce', 'otpa_2fa_nonce', true, false );
		$output   = '<div class="otpa-user-2fa-button-switch-container">';
		$output  .= ( ! empty( $label ) ) ? '<label>' . esc_html( $label ) . '</label> ' : '';
		$output  .= '<button data-handler="switch_2fa" data-user_id="' . esc_attr( $user->ID );
		$output  .= '" data-active_text="' . esc_attr( $on_text ) . '" data-inactive_text="' . esc_attr( $off_text ) . '"';
		$output  .= '" data-active="' . esc_attr( $active ) . '"';
		$output  .= '" type="button" class="' . $_class . '">';
		$output  .= ( $active ) ? esc_html( $on_text ) : esc_html( $off_text ) . '</button>' . $nonce;
		$output  .= '<div class="error otpa-error" style="display:none;">';
		$output  .= __( 'An undefined error occured.', 'otpa' ) . '<br/>';
		$output  .= __( 'Please reload the page and try again - if the problem persists, please contact an administrator.', 'otpa' );
		$output  .= '</div>';
		$output  .= '</div>';

		return apply_filters( 'otpa_2fa_button_switch_markup', $output, $label, $on_text, $off_text, $_class, $nonce );
	}

	// Protected methods.

	protected function sanitizer_api_handler( $handler ) {

		if ( ! in_array( $handler, self::$authorized_api_handlers, true ) ) {

			$handler = false;
		}

		return $handler;
	}

	protected function get_otp_type() {
		return str_replace( 'otpa_', '', strtolower( get_class( $this ) ) );
	}

	protected function is_block_editor() {
		$context = sanitize_key( (string) filter_input( INPUT_GET, 'context', FILTER_UNSAFE_RAW ) );

		return (
			defined( 'REST_REQUEST' ) &&
			true === REST_REQUEST &&
			'edit' === $context
		);
	}
}
