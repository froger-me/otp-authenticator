<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class Otpa_Passwordless {
	protected $otpa;

	public function __construct( $otpa, $init_hooks = false ) {
		$this->otpa = $otpa;

		if ( $init_hooks ) {
			add_action( 'init', array( $this, 'register_otp_api_callback' ), 10, 0 );
			add_action( 'otpa_page_passwordless_login', array( $this, 'login_page' ), 10, 0 );
			add_action( 'login_footer', array( $this, 'auth_link' ), 10, 0 );
			add_action( 'login_enqueue_scripts', array( $this, 'add_login_scripts' ), 99, 1 );

			add_filter( 'otpa_page_endpoints', array( $this, 'add_page_endpoint' ), 10, 1 );
			add_filter( 'nonce_user_logged_out', array( $this, 'noncefield_from_ip' ), 10, 2 );
		}
	}

	/*******************************************************************
	 * Public methods
	 *******************************************************************/

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

	public function auth_link( $output = true, $class = '', $target = '' ) {

		if ( get_current_user_id() ) {

			return;
		}

		$class    = ( 'login_footer' === current_filter() ) ? 'otpa-passwordless-login-form-link' : $class;
		$url      = home_url( 'otpa/passwordless-login' );
		$redirect = filter_input( INPUT_GET, 'redirect_to', FILTER_SANITIZE_STRING );
		$url      = ( ! empty( $redirect ) ) ? add_query_arg( 'redirect_to', rawurlencode( $redirect ), $url ) : $url;

		set_query_var( 'class', $class );
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
			echo $html; // @codingStandardsIgnoreLine
		}

		return ( $output ) ? $html : false;
	}

	public function set_otp_form_vars( $vars ) {
		$vars['otp_form_type']      = $this->get_otp_type();
		$vars['otp_form_title']     = __( 'Passwordless Authentication', 'otpa' );
		$vars['otp_footer_message'] = $this->get_back_markup();

		return $vars;
	}

	public function otp_api_valid_callback( $valid, $handler, $otp_type ) {
		return $valid && $otp_type === $this->get_otp_type();
	}

	public function otp_api_callback( $callback, $handler, $otp_type ) {

		if ( in_array( $handler, array( 'verify_otp_code', 'request_otp_code' ), true ) ) {
			$callback = array( $this, $handler );
		}

		return $callback;
	}

	public function add_login_scripts( $hook ) {
		$debug   = apply_filters( 'otpa_debug', (bool) ( constant( 'WP_DEBUG' ) ) );
		$css_ext = ( $debug ) ? '.css' : '.min.css';
		$version = filemtime( OTPA_PLUGIN_PATH . 'css/admin/login' . $css_ext );

		wp_enqueue_style( 'otpa-passwordless-style', OTPA_PLUGIN_URL . 'css/admin/login' . $css_ext, array(), $version );
	}

	public function request_otp_code( $payload ) {
		$user = $this->otpa->find_user( wp_get_current_user(), $payload );

		wp_set_current_user( $user->ID );

		return $this->otpa->request_otp_code( $payload );
	}

	public function verify_otp_code( $payload ) {
		$user = $this->otpa->find_user( wp_get_current_user(), $payload );

		wp_set_current_user( $user->ID );

		$result = $this->otpa->verify_otp_code( $payload );

		if ( ! is_wp_error( $result ) ) {
			clean_user_cache( $user );
			do_action( 'wp_login', $user->user_login, $user );
			wp_set_auth_cookie( $user->ID );

			$redirect              = isset( $payload['redirect'] ) ? filter_var( $payload['redirect'], FILTER_VALIDATE_URL ) : false;
			$redirect_to           = ( $redirect ) ? $redirect : home_url();
			$requested_redirect_to = $redirect;
			$result['message']     = __( 'Welcome!', 'otpa' ) . '<br/>' . __( 'Redirecting...', 'otpa' );
			$result['redirect']    = apply_filters( 'login_redirect', $redirect_to, $requested_redirect_to, $user );
		}

		return $result;
	}

	public function noncefield_from_ip( $uid, $action ) {
		return isset( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ? $_SERVER['HTTP_X_FORWARDED_FOR'] : $_SERVER['REMOTE_ADDR'];
	}

	/*******************************************************************
	 * Protected methods
	 *******************************************************************/

	protected function get_otp_type() {
		return str_replace( 'otpa_', '', strtolower( get_class() ) );
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
