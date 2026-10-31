<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class Otpa_Account_Validation {
	const NO_VALIDATION_EXPIRY     = -1;
	const VALIDATION_LOGOUT_EXPIRY = 0;

	protected $otpa;
	protected $otpa_settings;

	public function __construct( $otpa, $init_hooks = false ) {
		$this->otpa_settings = $otpa->get_settings();
		$this->otpa          = $otpa;

		if ( $init_hooks ) {
			$this->deny_state_changing_request();
			add_action( 'admin_init', array( $this, 'admin_redirect' ), 10, 0 );
			add_action( 'init', array( $this, 'account_validation' ), 10, 0 );
			add_action( 'wp_login', array( $this, 'set_need_account_validation' ), 10, 2 );
			add_action( 'otpa_otp_code_verified', array( $this, 'validate_account' ), 10, 2 );
			add_action( 'otpa_identifier_updated', array( $this, 'set_force_validation' ), 10, 3 );

			if ( self::VALIDATION_LOGOUT_EXPIRY === $this->otpa_settings->get_option( 'validation_expiry' ) ) {
				add_action( 'clear_auth_cookie', array( $this, 'set_need_account_validation' ), 10, 0 );
			}

			add_filter( 'login_redirect', array( $this, 'login_redirect' ), 10, 3 );
			add_filter( 'show_admin_bar', array( $this, 'hide_admin_bar' ) );
			add_filter( 'authenticate', array( $this, 'deny_xmlrpc_authentication' ), 99, 3 );
		}
	}

	// Public methods.

	public function login_redirect( $redirect_to, $requested_redirect_to, $user ) {

		if ( ! is_wp_error( $user ) && $this->need_account_validation( $user ) ) {
			update_user_meta( $user->ID, 'otpa_account_validation_redirect', $requested_redirect_to );

			$redirect_to = home_url();
		}

		return $redirect_to;
	}

	public function admin_redirect() {
		$user = wp_get_current_user();

		if ( $this->need_account_validation( $user ) ) {

			if ( wp_doing_ajax() ) {
				wp_send_json_error(
					array(
						'message' => __( 'OTP Authenticator: Account Validation required.', 'otpa' ),
					),
					403
				);
			}

			update_user_meta( $user->ID, 'otpa_account_validation_redirect', otpa_get_current_url() );
			wp_safe_redirect( home_url() );

			exit();
		}
	}

	public function hide_admin_bar( $show ) {
		return $show && ! $this->need_account_validation();
	}

	public function account_validation() {

		if ( $this->need_account_validation() ) {
			$user       = wp_get_current_user();
			$two_factor = new Otpa_2FA( $this->otpa );

			if ( $two_factor->need_2fa_check( $user ) ) {

				return;
			}

			if ( ! metadata_exists( 'user', $user->ID, 'otpa_account_validation_redirect' ) ) {
				update_user_meta( $user->ID, 'otpa_account_validation_redirect', otpa_get_current_url() );
			}

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
		$vars['otp_form_title'] = __( 'Validate your account', 'otpa' );

		return $vars;
	}

	public function otp_api_valid_callback( $valid, $handler, $otp_type ) {
		return $valid && $otp_type === $this->get_otp_type();
	}

	// phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- The filter supplies the OTP type as its third argument.
	public function otp_api_callback( $callback, $handler, $otp_type ) {
		return ( 'verify_otp_code' === $handler ) ? array( $this, $handler ) : $callback;
	}

	public function deny_rest( $result, $wp_rest_server, $wp_rest_request ) {
		$deny = apply_filters(
			'otpa_account_validation_deny_rest',
			true,
			$result,
			$wp_rest_server,
			$wp_rest_request
		);

		if ( $deny ) {
			return new WP_Error(
				'otpa_rest_denied',
				__( 'OTP Authenticator: Account Validation required.', 'otpa' ),
				array(
					'status' => 403,
				)
			);
		}

		return $result;
	}

	public function deny_state_changing_request() {
		if ( ! $this->need_account_validation() ) {

			return;
		}

		$request_method = strtoupper( sanitize_key( (string) filter_input( INPUT_SERVER, 'REQUEST_METHOD', FILTER_UNSAFE_RAW ) ) );

		if ( ! in_array( $request_method, array( 'POST', 'PUT', 'PATCH', 'DELETE' ), true ) ) {

			return;
		}

		$request_uri  = (string) filter_input( INPUT_SERVER, 'REQUEST_URI', FILTER_UNSAFE_RAW );
		$request_path = untrailingslashit( (string) wp_parse_url( esc_url_raw( $request_uri ), PHP_URL_PATH ) );
		$rest_path    = untrailingslashit( (string) wp_parse_url( rest_url(), PHP_URL_PATH ) );
		$xmlrpc_path  = untrailingslashit( (string) wp_parse_url( site_url( 'xmlrpc.php' ), PHP_URL_PATH ) );

		if (
			$xmlrpc_path === $request_path ||
			$rest_path === $request_path ||
			( $rest_path && str_starts_with( $request_path, $rest_path . '/' ) )
		) {

			return;
		}

		$otp_path = untrailingslashit( (string) wp_parse_url( home_url( 'otpa-api/otp' ), PHP_URL_PATH ) );

		if ( $otp_path === $request_path ) {
			$handler = sanitize_key( (string) filter_input( INPUT_POST, 'handler', FILTER_UNSAFE_RAW ) );

			if ( $this->otpa->is_pending_otp_request_allowed( $handler, wp_get_current_user() ) ) {

				return;
			}
		}

		$wc_ajax = sanitize_key( (string) filter_input( INPUT_GET, 'wc-ajax', FILTER_UNSAFE_RAW ) );

		if ( wp_doing_ajax() || $wc_ajax ) {
			wp_send_json_error(
				array(
					'message' => __( 'OTP Authenticator: Account Validation required.', 'otpa' ),
				),
				403
			);
		}

		wp_die(
			esc_html__( 'OTP Authenticator: Account Validation required.', 'otpa' ),
			esc_html__( 'Access denied', 'otpa' ),
			array( 'response' => 403 )
		);
	}

	// phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed, Generic.CodeAnalysis.UnusedFunctionParameter.Found -- WordPress supplies credentials to this authentication filter.
	public function deny_xmlrpc_authentication( $user, $username, $password ) {
		if (
			defined( 'XMLRPC_REQUEST' ) &&
			XMLRPC_REQUEST &&
			$user instanceof WP_User &&
			$this->is_user_account_validation_required( $user )
		) {
			return new WP_Error(
				'otpa_account_validation_required',
				__( 'OTP Authenticator: Account Validation required.', 'otpa' )
			);
		}

		return $user;
	}

	public function is_user_validated( $user = false, $force_check = false ) {
		$validated = true;
		$user      = ( $user ) ? $user : wp_get_current_user();

		if ( $user->ID ) {

			if ( $this->is_user_validation_excluded( $user->ID ) && ! $force_check ) {

				return $validated;
			}

			$meta = get_user_meta( $user->ID, 'otpa_validated', true );

			if ( $meta ) {
				$validation_expiry = $this->otpa_settings->get_option( 'validation_expiry' );

				if (
					self::VALIDATION_LOGOUT_EXPIRY <= $validation_expiry &&
					(
						! isset( $meta['validation_expiry'] ) ||
						intval( $meta['validation_expiry'] ) < time()
					)
				) {
					delete_user_meta( $user->ID, 'otpa_validated', $meta );

					$validated = false;
				} else {
					$validated = true;
				}
			} else {
				$validated = false;
			}

			if ( $validated ) {
				delete_user_meta( $user->ID, 'otpa_account_validation_redirect' );
			}
		}

		return $validated;
	}

	public function need_account_validation( $user = false ) {
		$user = ( $user ) ? $user : wp_get_current_user();

		$need_validation  = (bool) get_user_meta( $user->ID, 'otpa_need_account_validation', true );
		$force_validation = (bool) get_user_meta( $user->ID, 'otpa_force_account_validation', true );

		if ( $this->is_user_validation_excluded( $user->ID ) ) {
			$force_validation = false;
		}

		return ( $need_validation && ! $this->is_user_validated( $user ) ) || $force_validation;
	}

	/**
	 * Check whether an account requires validation independently of a login marker.
	 *
	 * @param WP_User|false $user User to check, or false for the current user.
	 * @return bool Whether the account requires validation.
	 */
	public function is_user_account_validation_required( $user = false ) {
		$user = $user instanceof WP_User ? $user : wp_get_current_user();

		return (
			$user->ID &&
			$this->otpa_settings->get_option( 'enable_validation' ) &&
			! $this->is_user_validation_excluded( $user->ID ) &&
			! $this->is_user_validated( $user )
		);
	}

	public function set_need_account_validation( $user_login = false, $user = false, $force = false ) {
		$user = ( $user ) ? $user : wp_get_current_user();

		if ( ! $user->ID ) {

			return;
		}

		$meta = get_user_meta( $user->ID, 'otpa_validated', true );

		if ( $meta && $force ) {
			delete_user_meta( $user->ID, 'otpa_validated', $meta );
			update_user_meta( $user->ID, 'otpa_need_account_validation', true );

			return;
		}

		if ( $meta ) {
			$validation_expiry = $this->otpa_settings->get_option( 'validation_expiry' );

			if (
				self::VALIDATION_LOGOUT_EXPIRY <= $validation_expiry &&
				(
					! isset( $meta['validation_expiry'] ) ||
					intval( $meta['validation_expiry'] ) < time()
				)
			) {
				delete_user_meta( $user->ID, 'otpa_validated', $meta );
				update_user_meta( $user->ID, 'otpa_need_account_validation', true );
			}
		} else {
			update_user_meta( $user->ID, 'otpa_need_account_validation', true );
		}
	}

	// phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- The action supplies both identifier values as part of its public callback signature.
	public function set_force_validation( $user_id = 0, $identifier = '', $old_identifier = '' ) {

		if ( ! $user_id ) {
			$user    = wp_get_current_user();
			$user_id = $user->ID;
		}

		delete_user_meta( $user_id, 'otpa_validated' );
		update_user_meta( $user_id, 'otpa_force_account_validation', true );
	}

	public function validate_account( $user_id, $purpose ) {

		if ( 'account_validation' !== $purpose ) {

			return;
		}

		$validation_expiry = $this->otpa_settings->get_option( 'validation_expiry' );
		$meta              = array(
			'status'            => true,
			'validation_expiry' => ( self::VALIDATION_LOGOUT_EXPIRY <= $validation_expiry ) ? time() + $validation_expiry * 3600 : 0,
		);

		update_user_meta( $user_id, 'otpa_validated', $meta );
		delete_user_meta( $user_id, 'otpa_need_account_validation' );
		delete_user_meta( $user_id, 'otpa_force_account_validation' );
	}

	public function verify_otp_code( $payload ) {
		$payload['otp_purpose'] = 'account_validation';
		$result                 = $this->otpa->verify_otp_code( $payload );

		if ( ! is_wp_error( $result ) ) {
			$user               = wp_get_current_user();
			$result['message']  = __( 'Account successfully validated!', 'otpa' ) . '<br/>' . __( 'Redirecting...', 'otpa' );
			$result['redirect'] = get_user_meta( $user->ID, 'otpa_account_validation_redirect', true );

			delete_user_meta( $user->ID, 'otpa_account_validation_redirect' );
		}

		return $result;
	}

	public function is_user_validation_excluded( $user_id = false ) {

		if ( ! $user_id ) {
			$user = wp_get_current_user();
		} else {
			$user = get_user_by( 'ID', $user_id );
		}

		$excluded       = false;
		$excluded_roles = $this->otpa_settings->get_option( 'validation_exclude_roles' );

		if ( ! $user->ID ) {
			$excluded = true;
		} else {
			$user_roles = (array) $user->roles;

			foreach ( $excluded_roles as $role ) {

				if ( in_array( $role, $user_roles, true ) ) {
					$excluded = true;

					break;
				}
			}
		}

		return $excluded;
	}

	public function get_user_validation_expiry( $user = false ) {
		$user = ( $user ) ? $user : wp_get_current_user();

		$meta              = get_user_meta( $user->ID, 'otpa_validated', true );
		$validation_expiry = $this->otpa_settings->get_option( 'validation_expiry' );

		if ( self::VALIDATION_LOGOUT_EXPIRY === $validation_expiry ) {

			return self::VALIDATION_LOGOUT_EXPIRY;
		} elseif ( self::NO_VALIDATION_EXPIRY === $validation_expiry ) {

			return self::NO_VALIDATION_EXPIRY;
		} else {

			if ( ! $meta ) {

				return self::VALIDATION_LOGOUT_EXPIRY;
			}

			return intval( $meta['validation_expiry'] );
		}
	}

	// Protected methods.

	protected function get_otp_type() {
		return str_replace( 'otpa_', '', strtolower( get_class( $this ) ) );
	}
}
