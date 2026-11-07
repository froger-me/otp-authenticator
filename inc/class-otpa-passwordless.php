<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class Otpa_Passwordless {
	const REQUEST_COOKIE       = 'otpa_passwordless_request';
	const NONCE_SESSION_COOKIE = 'otpa_passwordless_nonce_session';
	const NONCE_ACTION         = 'otpa_passwordless_nonce';

	protected $otpa;
	protected $nonce_session = '';

	public function __construct( $otpa, $init_hooks = false ) {
		$this->otpa = $otpa;

		if ( $init_hooks ) {
			add_action( 'init', array( $this, 'register_otp_api_callback' ), 10, 0 );
			add_action( 'otpa_page_passwordless_login', array( $this, 'login_page' ), 10, 0 );
			add_action( 'login_footer', array( $this, 'auth_link' ), 10, 0 );
			add_action( 'login_enqueue_scripts', array( $this, 'add_login_scripts' ), 99, 1 );

			add_filter( 'otpa_page_endpoints', array( $this, 'add_page_endpoint' ), 10, 1 );
			add_filter( 'nonce_user_logged_out', array( $this, 'nonce_user_logged_out' ), 10, 2 );
		}
	}

	// Public methods.

	public function add_page_endpoint( $endpoints ) {
		$endpoints['passwordless_login'] = 'passwordless-login';

		return $endpoints;
	}

	public function register_otp_api_callback() {

		if ( ! is_user_logged_in() ) {
			add_filter( 'otpa_otp_api_valid_callback', array( $this, 'otp_api_valid_callback' ), 10, 3 );
			add_filter( 'otpa_otp_api_callback', array( $this, 'otp_api_callback' ), 10, 3 );
		}
	}

	public function login_page() {

		if ( ! is_user_logged_in() ) {
			add_action( 'template_redirect', array( $this->otpa, 'otp_form_page' ), PHP_INT_MIN, 0 );
			add_filter( 'otpa_otp_form_vars', array( $this, 'set_otp_form_vars' ), 10, 1 );
		} else {
			wp_safe_redirect( home_url() );

			exit();
		}
	}

	public function auth_link( $output = true, $_class = '', $target = '' ) {

		if ( get_current_user_id() ) {

			return;
		}

		$_class   = ( 'login_footer' === current_filter() ) ? 'otpa-passwordless-login-form-link' : $_class;
		$url      = home_url( 'otpa/passwordless-login' );
		$redirect = Otpa::get_safe_redirect( (string) filter_input( INPUT_GET, 'redirect_to', FILTER_UNSAFE_RAW ) );
		$url      = ( ! empty( $redirect ) ) ? add_query_arg( 'redirect_to', rawurlencode( $redirect ), $url ) : $url;

		set_query_var( 'class', $_class );
		set_query_var( 'target', $target );
		set_query_var( 'link_text', apply_filters( 'otpa_passwordless_auth_link_text', __( 'Passwordless Authentication', 'otpa' ) ) );
		set_query_var( 'url', $url );

		$template = locate_template( 'otpa-passwordless-form-link.php', true );

		ob_start();

		if ( ! $template ) {
			$template = OTPA_PLUGIN_PATH . 'inc/templates/otpa-passwordless-form-link.php';

			load_template( $template );
		}

		$html = ob_get_clean();

		if ( $output ) {
			echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}

		return ( $output ) ? $html : false;
	}

	public function set_otp_form_vars( $vars ) {
		$this->ensure_nonce_session();

		$vars['otp_form_type']      = $this->get_otp_type();
		$vars['otp_form_title']     = __( 'Passwordless Authentication', 'otpa' );
		$vars['otp_footer_message'] = $this->get_back_markup();
		$vars['otp_nonce_action']   = self::NONCE_ACTION;

		return $vars;
	}

	public function otp_api_valid_callback( $valid, $handler, $otp_type ) {
		return $valid && $otp_type === $this->get_otp_type();
	}

	// phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- The filter supplies the OTP type as its third argument.
	public function otp_api_callback( $callback, $handler, $otp_type ) {

		if ( in_array( $handler, array( 'verify_otp_code', 'request_otp_code' ), true ) ) {
			$callback = array( $this, $handler );
		}

		return $callback;
	}

	// phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found -- WordPress supplies the hook name to this action callback.
	public function add_login_scripts( $hook ) {
		$debug   = apply_filters( 'otpa_debug', (bool) ( constant( 'WP_DEBUG' ) ) );
		$css_ext = ( $debug ) ? '.css' : '.min.css';
		$version = filemtime( OTPA_PLUGIN_PATH . 'css/admin/login' . $css_ext );

		wp_enqueue_style( 'otpa-passwordless-style', OTPA_PLUGIN_URL . 'css/admin/login' . $css_ext, array(), $version );
	}

	public function request_otp_code( $payload ) {
		$payload['otp_purpose'] = Otpa::OTP_PURPOSE_PASSWORDLESS;
		$user                   = $this->otpa->find_user( wp_get_current_user(), $payload );

		if ( ! $user instanceof WP_User || ! $user->ID ) {
			return Otpa::get_wp_error(
				'OTPA_INVALID_USER',
				array(
					'method' => __METHOD__,
				)
			);
		}

		if ( ! $this->is_user_role_included( $user ) ) {
			return Otpa::get_wp_error(
				'OTPA_PASSWORDLESS_NOT_AVAILABLE',
				array(
					'method' => __METHOD__,
				)
			);
		}

		$previous_user_id = get_current_user_id();
		wp_set_current_user( $user->ID );

		try {
			$result = $this->otpa->request_otp_code( $payload );
		} finally {
			wp_set_current_user( $previous_user_id );
		}

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$this->clear_request_binding();

		$request_token = wp_generate_password( 64, false, false );
		$storage_key   = $this->get_storage_key( $request_token );
		$lifetime      = max( MINUTE_IN_SECONDS, absint( $this->otpa->get_setting( 'otp_expiry' ) ) * MINUTE_IN_SECONDS );
		$expires       = time() + $lifetime;

		set_transient(
			$storage_key,
			array( 'user_id' => (int) $user->ID ),
			$lifetime
		);

		if ( ! $this->set_request_cookie( $request_token, $expires ) ) {
			delete_transient( $storage_key );

			return Otpa::get_wp_error(
				'OTPA_UNKNOWN_ERROR',
				array(
					'method' => __METHOD__,
				)
			);
		}

		return $result;
	}

	public function verify_otp_code( $payload ) {
		$request_token = $this->get_request_token();
		$binding       = $request_token ? get_transient( $this->get_storage_key( $request_token ) ) : false;
		$user          = is_array( $binding ) && ! empty( $binding['user_id'] )
			? get_user_by( 'ID', absint( $binding['user_id'] ) )
			: false;

		if ( ! $user instanceof WP_User || ! $user->ID ) {
			$this->clear_request_binding();

			return Otpa::get_wp_error(
				'OTPA_CODE_NOT_FOUND',
				array(
					'method' => __METHOD__,
				)
			);
		}

		if ( ! $this->is_user_role_included( $user ) ) {
			$this->clear_request_binding();

			return Otpa::get_wp_error(
				'OTPA_PASSWORDLESS_NOT_AVAILABLE',
				array(
					'method' => __METHOD__,
				)
			);
		}

		$verification_payload = $payload;
		unset( $verification_payload['identifier'] );
		$verification_payload['otp_purpose'] = Otpa::OTP_PURPOSE_PASSWORDLESS;

		$previous_user_id = get_current_user_id();
		wp_set_current_user( $user->ID );

		try {
			$result = $this->otpa->verify_otp_code( $verification_payload );
		} finally {
			wp_set_current_user( $previous_user_id );
		}

		if ( ! is_wp_error( $result ) || 'OTPA_INVALID_CODE' !== $result->get_error_code() ) {
			$this->clear_request_binding();
		}

		if ( ! is_wp_error( $result ) ) {
			clean_user_cache( $user );
			do_action( 'wp_login', $user->user_login, $user );
			wp_set_auth_cookie( $user->ID );

			$redirect              = isset( $payload['redirect'] ) && is_string( $payload['redirect'] )
				? Otpa::get_safe_redirect( $payload['redirect'] )
				: false;
			$redirect_to           = ( $redirect ) ? $redirect : home_url();
			$requested_redirect_to = $redirect;
			$result['message']     = __( 'Welcome!', 'otpa' ) . '<br/>' . __( 'Redirecting...', 'otpa' );
			$result['redirect']    = Otpa::get_safe_redirect(
				apply_filters( 'login_redirect', $redirect_to, $requested_redirect_to, $user ),
				home_url( '/' )
			);
		}

		return $result;
	}

	/**
	 * Check whether passwordless authentication includes one of a user's roles.
	 *
	 * @param WP_User $user User attempting passwordless authentication.
	 * @return bool Whether passwordless authentication is available.
	 */
	protected function is_user_role_included( $user ) {
		$included_roles = (array) $this->otpa->get_setting( 'passwordless_roles' );

		return ! empty( array_intersect( $included_roles, (array) $user->roles ) );
	}

	public function nonce_user_logged_out( $user_id, $action ) {

		if ( self::NONCE_ACTION !== $action ) {

			return $user_id;
		}

		$session = $this->get_nonce_session_cookie();

		return $session
			? hash_hmac( 'sha256', $session, wp_salt( 'nonce' ) )
			: $user_id;
	}

	// Protected methods.

	protected function get_otp_type() {
		return str_replace( 'otpa_', '', strtolower( get_class( $this ) ) );
	}

	protected function ensure_nonce_session() {
		$session = $this->get_nonce_session_cookie();

		if ( $session ) {

			return $session;
		}

		$session = wp_generate_password( 64, false, false );

		if ( $this->set_nonce_session_cookie( $session, time() + DAY_IN_SECONDS ) ) {
			$this->nonce_session = $session;

			return $session;
		}

		return '';
	}

	protected function set_nonce_session_cookie( $session, $expires ) {
		return ! headers_sent() && setcookie(
			self::NONCE_SESSION_COOKIE,
			$session,
			array(
				'expires'  => $expires,
				'path'     => '/',
				'domain'   => defined( 'COOKIE_DOMAIN' ) ? (string) COOKIE_DOMAIN : '',
				'secure'   => is_ssl(),
				'httponly' => true,
				'samesite' => 'Lax',
			)
		);
	}

	protected function get_nonce_session_cookie() {

		if ( $this->nonce_session ) {

			return $this->nonce_session;
		}

		$session = sanitize_text_field( (string) filter_input( INPUT_COOKIE, self::NONCE_SESSION_COOKIE, FILTER_UNSAFE_RAW ) );

		return preg_match( '/^[A-Za-z0-9]{64}$/D', $session ) ? $session : '';
	}

	protected function set_request_cookie( $request_token, $expires ) {
		return ! headers_sent() && setcookie(
			self::REQUEST_COOKIE,
			$request_token,
			array(
				'expires'  => $expires,
				'path'     => '/',
				'domain'   => defined( 'COOKIE_DOMAIN' ) ? (string) COOKIE_DOMAIN : '',
				'secure'   => is_ssl(),
				'httponly' => true,
				'samesite' => 'Lax',
			)
		);
	}

	protected function get_request_token() {

		if ( ! isset( $_COOKIE[ self::REQUEST_COOKIE ] ) || ! is_string( $_COOKIE[ self::REQUEST_COOKIE ] ) ) {
			return '';
		}

		$request_token = sanitize_text_field( wp_unslash( $_COOKIE[ self::REQUEST_COOKIE ] ) );

		return preg_match( '/^[A-Za-z0-9]{64}$/D', $request_token ) ? $request_token : '';
	}

	protected function get_storage_key( $request_token ) {
		return 'otpa_passwordless_' . hash_hmac( 'sha256', $request_token, wp_salt( 'auth' ) );
	}

	protected function clear_request_binding() {
		$request_token = $this->get_request_token();

		if ( $request_token ) {
			delete_transient( $this->get_storage_key( $request_token ) );
		}

		if ( ! headers_sent() ) {
			setcookie(
				self::REQUEST_COOKIE,
				'',
				array(
					'expires'  => time() - YEAR_IN_SECONDS,
					'path'     => '/',
					'domain'   => defined( 'COOKIE_DOMAIN' ) ? (string) COOKIE_DOMAIN : '',
					'secure'   => is_ssl(),
					'httponly' => true,
					'samesite' => 'Lax',
				)
			);
		}

		unset( $_COOKIE[ self::REQUEST_COOKIE ] );
	}

	protected function get_back_markup() {
		$output  = '<a class="otpa-cancel-link" href="';
		$output .= esc_url( home_url( '/' ) );
		/* translators: %s: Site title. */
		$output .= '">' . sprintf( _x( '&larr; Back to %s', 'site' ), get_bloginfo( 'title', 'display' ) ) . '</a>';
		$output .= get_the_privacy_policy_link( '<br><span class="privacy-policy-page-link">', '</span>' );

		return $output;
	}
}
