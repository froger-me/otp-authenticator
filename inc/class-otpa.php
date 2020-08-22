<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class Otpa {
	protected static $authorized_api_handlers;

	protected $otpa_settings;
	protected $gateway;
	protected $authorised_api_endpoints;
	protected $authorised_page_endpoints;
	protected $is_setting_identifier = false;

	public function __construct( $otpa_settings, $gateway = false, $init_hooks = false ) {
		$this->otpa_settings           = $otpa_settings;
		$this->gateway                 = $gateway;
		self::$authorized_api_handlers = apply_filters(
			'otpa_api_otp_handlers',
			array(
				'set_otp_identifier',
				'request_otp_code',
				'verify_otp_code',
			)
		);

		if ( $init_hooks ) {
			add_action( 'init', array( $this, 'add_endpoints' ), 0, 0 );
			add_action( 'parse_request', array( $this, 'parse_request' ), 10, 0 );
			add_action( 'otpa_api_otp', array( $this, 'handle_api_request' ), 10, 0 );

			add_filter( 'query_vars', array( $this, 'add_query_vars' ), 10, 1 );
			add_filter( 'otpa_api_error_data', array( $this, 'filter_authorized_error_data' ), 10, 1 );

			if (
				$this->otpa_settings->get_option( 'enable_2fa' ) ||
				$this->otpa_settings->get_option( 'enable_validation' )
			) {
				add_action( 'admin_init', array( $this, 'admin_redirect' ), 5, 0 );
				add_filter( 'login_redirect', array( $this, 'login_redirect' ), 5, 3 );
				add_action( 'init', array( $this, 'check_otp_identifier' ), 5, 0 );
			}
		}
	}

	/*******************************************************************
	 * Public methods
	 *******************************************************************/

	public static function activate() {
		$result = Otpa_Logger::install();

		if ( ! $result ) {
			die( esc_html( __( 'Failed to create the necessary database table(s).', 'otpa' ) ) );
		}

		set_transient( 'otpa_flush', 1, 60 );
	}

	public static function deactivate() {
		global $wpdb;

		$prefix = $wpdb->esc_like( '_transient_otpa' );
		$sql    = "DELETE FROM $wpdb->options WHERE `option_name` LIKE '%s'";

		$wpdb->query( $wpdb->prepare( $sql, $prefix . '%' ) ); // @codingStandardsIgnoreLine
	}

	public static function uninstall() {
		include_once OTPA_PLUGIN_PATH . 'uninstall.php';
	}

	public static function get_wp_error( $code = 'OTPA_UNKNOWN_ERROR', $data = array(), $message = '', $wp_error = false ) {

		if ( is_wp_error( $wp_error ) ) {

			if ( 'OTPA_UNKNOWN_ERROR' === $code ) {
				$code = $wp_error->get_error_code();
			}

			if ( empty( $data ) ) {
				$data = $wp_error->get_error_data();
			}

			if ( empty( $message ) ) {
				$message = $wp_error->get_error_message();
			}
		}

		if ( empty( $message ) ) {

			switch ( $code ) {
				case 'OTPA_INVALID_GATEWAY':
					$message = __( 'The OTP Gateway is invalid.', 'otpa' ) . '<br/>' . __( 'Please reload the page and try again - if the problem persists, please contact an administrator.' );
					break;
				case 'OTPA_OTP_BLOCKED':
					$message = __( 'Too many failed attempts or too many code requests have been detected.', 'otpa' ) . '<br/>' . sprintf(
						// translators: %s is the human-readable time to wait
						__( 'Please try again in %s.', 'otpa' ),
						otpa_human_timing( $data['block_expiry'] )
					);
					break;
				case 'OTPA_INVALID_USER':
					$message = __( 'User not found.', 'otpa' ) . '<br/>' . __( 'Please request a new code and try again.', 'otpa' );
					break;
				case 'OTPA_CODE_PERSIST_FAILURE':
					$message = __( 'A database error occured: the new OTP Verification Code could not be persisted', 'otpa' ) . '<br/>' . __( 'Please request a new code and try again.', 'otpa' );
					break;
				case 'OTPA_EXPIRED_CODE':
						$message = __( 'The provided Verification Code has expired.', 'otpa' ) . '<br/>' . __( 'Please request a new code and try again.', 'otpa' );
					break;
				case 'OTPA_INVALID_CODE':
						$message = __( 'The provided Verification Code is invalid.', 'otpa' ) . '<br/>' . __( 'Please request a new code and try again.', 'otpa' );
					break;
				case 'OTPA_CODE_NOT_FOUND':
					$message = __( 'The provided Verification Code is invalid.', 'otpa' ) . '<br/>' . __( 'Please request a new code and try again.', 'otpa' );
					break;
				case 'OTPA_INVALID_IDENTIFIER':
					$message = __( 'The identifier is invalid.', 'otpa' ) . '<br/>' . __( 'Please enter a valid identifier and try again.' );
					break;
				case 'OTPA_DUPLICATE_IDENTIFIER':
					$message = __( 'The identifier is already registered.', 'otpa' ) . '<br/>' . __( 'Please enter another valid identifier and try again.' );
					break;
				case 'OTPA_MISSING_CODE':
					$message = __( 'An undefined error occured: missing code.', 'otpa' ) . '<br/>' . __( 'Please reload the page and try again - if the problem persists, please contact an administrator.' );
					break;
				case 'OTPA_THROTTLE':
					$message = __( 'Please wait a moment before requesting a new code.', 'otpa' );
					break;
				default:
					$message = __( 'An undefined error occured.', 'otpa' ) . '<br/>' . __( 'Please reload the page and try again - if the problem persists, please contact an administrator.', 'otpa' );
					break;
			}
		}

		$message = apply_filters( 'otpa_wp_error_message', $message, $code, $data );

		if ( is_wp_error( $wp_error ) ) {
			$wp_error->remove( $wp_error->get_error_code() );
			$wp_error->add( $code, $message, $data );
		} else {
			$wp_error = new WP_Error( $code, $message, $data );
		}

		do_action( 'otpa_wp_error', $wp_error );

		return $wp_error;
	}

	public function get_settings() {
		return $this->otpa_settings;
	}

	public function add_query_vars( $vars ) {
		$vars[] = '__otpa_api';
		$vars[] = '__otpa_page';
		$vars[] = 'action';
		$vars[] = 'tab';

		return $vars;
	}

	public function add_endpoints() {

		if ( get_transient( 'otpa_flush' ) ) {
			delete_transient( 'otpa_flush' );
			flush_rewrite_rules();
		}

		$this->authorised_api_endpoints = apply_filters(
			'otpa_api_endpoints',
			array(
				'otp' => 'otp',
			)
		);

		foreach ( $this->authorised_api_endpoints as $action => $url_suffix ) {
			add_rewrite_rule(
				'^otpa-api/' . $url_suffix,
				'index.php?__otpa_api=1&action=' . $action,
				'top'
			);
		}

		$this->authorised_page_endpoints = apply_filters( 'otpa_page_endpoints', array() );

		foreach ( $this->authorised_page_endpoints as $action => $url_suffix ) {
			add_rewrite_rule(
				'^otpa/' . $url_suffix,
				'index.php?__otpa_page=1&action=' . $action,
				'top'
			);
		}
	}

	public function parse_request() {
		global $wp;

		if ( isset( $wp->query_vars['__otpa_api'] ) ) {
			$action      = isset( $wp->query_vars['action'] ) ? $wp->query_vars['action'] : false;
			$api_actions = array_keys( $this->authorised_api_endpoints );

			if ( has_action( 'otpa_api_' . $action ) && in_array( $action, $api_actions, true ) ) {
				do_action( 'otpa_api_' . $action );
			} else {
				$this->parse_error();
			}

			exit();
		}

		if ( isset( $wp->query_vars['__otpa_page'] ) ) {
			$action      = isset( $wp->query_vars['action'] ) ? $wp->query_vars['action'] : false;
			$api_actions = array_keys( $this->authorised_page_endpoints );

			if ( has_action( 'otpa_page_' . $action ) && in_array( $action, $api_actions, true ) ) {
				do_action( 'otpa_page_' . $action );
			} else {
				$this->parse_error();

				exit();
			}
		}
	}

	public function handle_api_request() {
		$nonce = filter_input( INPUT_POST, 'nonce', FILTER_SANITIZE_STRING );

		if ( ! wp_verify_nonce( $nonce, 'otpa_nonce' ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'Error: unauthorized access - please reload the page and try again.', 'otpa' ),
				)
			);
		}

		$otp_type      = filter_input( INPUT_POST, 'otpType', FILTER_SANITIZE_STRING );
		$handler       = filter_input( INPUT_POST, 'handler', FILTER_SANITIZE_STRING );
		$valid_handler = apply_filters(
			'otpa_otp_api_valid_callback',
			method_exists( $this, $this->sanitizer_api_handler( $handler ) ),
			$handler,
			$otp_type
		);

		if ( ! $valid_handler ) {
			wp_send_json_error(
				array(
					'message' => __( 'Error: unauthorized operation - if the problem persists, please contact an administrator.', 'otpa' ),
				)
			);
		}

		$callback = apply_filters(
			'otpa_otp_api_callback',
			array( $this, $handler ),
			$handler,
			$otp_type
		);
		$args     = apply_filters(
			'otpa_otp_api_callback_args',
			filter_input( INPUT_POST, 'payload', FILTER_SANITIZE_STRING, FILTER_REQUIRE_ARRAY ),
			$handler,
			$otp_type
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

	public function otp_form_page() {
		$this->add_frontend_scripts();

		$default_vars = array(
			'otp_widget'         => $this->get_otp_widget(),
			'otp_logo_url'       => Otpa_Style_Settings::get_option( 'logo_url' ),
			'otp_form_type'      => 'default',
			'otp_form_title'     => __( 'Get a Verification Code', 'otpa' ),
			'otp_footer_message' => $this->get_logout_markup(),
		);
		$vars         = array_merge( $default_vars, apply_filters( 'otpa_otp_form_vars', $default_vars ) );

		foreach ( $vars as $key => $value ) {
			set_query_var( $key, $value );
		}

		add_filter( 'template_include', array( $this, 'otp_form_template' ), 99, 1 );
	}

	public function otp_form_template( $template ) {
		$template = locate_template( 'otpa-otp-form-page.php', true );

		if ( ! $template ) {
			$template = OTPA_PLUGIN_PATH . 'inc/templates/otpa-otp-form-page.php';

			load_template( $template );
		}

		return false;
	}

	public function set_otp_identifier_form_page() {
		$this->add_frontend_scripts();

		$default_vars = array(
			'otp_widget'             => $this->get_otp_widget(),
			'otp_logo_url'           => Otpa_Style_Settings::get_option( 'logo_url' ),
			'otp_form_type'          => 'set_otp_identifier',
			'otp_form_title'         => sprintf(
				// translators: %s is the OTP Identifier label on the next line
				__( 'Set your %s', 'otpa' ),
				'<br/>' . esc_html(
					apply_filters(
						'otpa_otp_identifier_field_label',
						__( 'OTP Identifier', 'otpa' )
					)
				)
			),
			'otp_footer_message'     => $this->get_logout_markup(),
			'otp_2fa_enabled'        => $this->otpa_settings->get_option( 'enable_2fa' ),
			'otp_validation_enabled' => $this->otpa_settings->get_option( 'enable_validation' ),
		);
		$vars         = array_merge( $default_vars, apply_filters( 'otpa_set_otp_identifier_form_vars', $default_vars ) );

		foreach ( $vars as $key => $value ) {
			set_query_var( $key, $value );
		}

		remove_filter( 'template_include', array( $this, 'otp_form_template' ), 99 );
		add_filter( 'template_include', array( $this, 'set_otp_identifier_form_template' ), 99, 1 );
	}

	public function set_otp_identifier_form_template( $template ) {
		$template = locate_template( 'otpa-set-otp-identifier-form-page.php', true );

		if ( ! $template ) {
			$template = OTPA_PLUGIN_PATH . 'inc/templates/otpa-set-otp-identifier-form-page.php';

			load_template( $template );
		}

		return false;
	}

	public function get_otp_widget() {
		return $this->gateway->get_otp_widget();
	}

	public function add_frontend_scripts() {
		$debug   = apply_filters( 'otpa_debug', (bool) ( constant( 'WP_DEBUG' ) ) );
		$css_ext = ( $debug ) ? '.css' : '.min.css';
		$version = filemtime( OTPA_PLUGIN_PATH . 'css/main' . $css_ext );
		$key     = 'otpa-main-style';

		wp_enqueue_style( $key, OTPA_PLUGIN_URL . 'css/main' . $css_ext, array(), $version );
		wp_enqueue_style( 'dashicons' );
		wp_register_style( 'otpa-otp-form-inline-style', false, array(), time() );
		wp_enqueue_style( 'otpa-otp-form-inline-style' );
		wp_add_inline_style( 'otpa-otp-form-inline-style', $this->get_css() );

		$js_ext  = ( $debug ) ? '.js' : '.min.js';
		$version = filemtime( OTPA_PLUGIN_PATH . 'js/main' . $js_ext );
		$user    = wp_get_current_user();

		if ( ! $user->ID ) {
			$wait = filter_input( INPUT_COOKIE, 'otpa_otp_request_wait', FILTER_VALIDATE_INT ) - time();
		} else {
			$wait = intval( get_user_meta( $user->ID, 'otpa_otp_request_wait', true ) ) - time();
		}

		$params = apply_filters(
			'otpa_otp_form_scripts_params',
			array(
				'debug'                     => $debug,
				'otpa_api_url'              => home_url( 'otpa-api/otp' ),
				'otp_code_request_wait'     => $wait,
				'otp_code_request_throttle' => $this->otpa_settings->get_option( 'request_frequency' ),
			)
		);

		wp_enqueue_script(
			'otpa-main-script',
			OTPA_PLUGIN_URL . 'js/main' . $js_ext,
			array( 'jquery' ),
			$version,
			true
		);

		do_action( 'otpa_otp_form_scripts' );
		wp_localize_script( 'otpa-main-script', 'OTPA', $params );
	}

	public function is_identifier_required( $user_id ) {
		$required = (
			$user_id &&
			empty( $this->gateway->get_user_identifier( $user_id ) ) &&
			(
				$this->otpa_settings->get_option( 'enable_2fa' ) ||
				! otpa_is_user_account_validation_excluded( $user_id )
			)
		);

		return $required;
	}

	public function admin_redirect() {
		$user = wp_get_current_user();

		if ( $this->is_identifier_required( $user->ID ) ) {
			update_user_meta( $user->ID, 'otpa_set_otp_redirect', otpa_get_current_url() );
			wp_safe_redirect( home_url() );

			exit();
		}
	}

	public function login_redirect( $redirect_to, $requested_redirect_to, $user ) {

		if ( ! is_wp_error( $user ) && $this->is_identifier_required( $user->ID ) ) {
			update_user_meta( $user->ID, 'otpa_set_otp_redirect', $requested_redirect_to );

			$redirect_to = home_url();
		}

		return $redirect_to;
	}

	public function check_otp_identifier() {
		$user = wp_get_current_user();

		if ( $this->is_identifier_required( $user->ID ) ) {
			$this->is_setting_identifier = true;

			if ( ! metadata_exists( 'user', $user->ID, 'otpa_set_otp_redirect' ) ) {
				update_user_meta( $user->ID, 'otpa_set_otp_redirect', otpa_get_current_url() );
			}

			add_action( 'template_redirect', array( $this, 'set_otp_identifier_form_page' ), PHP_INT_MIN, 0 );
		}
	}

	public function allow_template_redirect() {
		return ! $this->is_setting_identifier;
	}

	public function request_otp_code( $payload ) {

		if ( isset( $payload['identifier'] ) ) {
			$identifier = $payload['identifier'];
			$user       = wp_get_current_user();
			$wait       = time() + $this->otpa_settings->get_option( 'request_frequency' );
			$throttled  = (
				filter_input( INPUT_COOKIE, 'otpa_otp_request_wait', FILTER_VALIDATE_INT ) > time() ||
				intval( get_user_meta( $user->ID, 'otpa_otp_request_wait', true ) ) > time()
			);

			if ( ! $throttled ) {
				setcookie( 'otpa_otp_request_wait', $wait, $wait, '/', COOKIE_DOMAIN );

				if ( $user->ID ) {
					$blocked = $this->is_otp_blocked( $user->ID );

					update_user_meta( $user->ID, 'otpa_otp_request_wait', $wait );

					if ( ! $blocked ) {

						if (
							$this->gateway &&
							is_object( $this->gateway ) &&
							method_exists( $this->gateway, 'request_code' )
						) {
							$result   = $this->gateway->request_code(
								$identifier,
								array( get_class(), 'get_wp_error' ),
								$this->otpa_settings->get_option( 'sandbox' )
							);
							$otp_code = ( is_array( $result ) && isset( $result['otp_code'] ) ) ? $result['otp_code'] : false;

							if ( ! is_wp_error( $result ) && $otp_code ) {
								$saved = $this->save_otp_code( $otp_code );

								if ( is_wp_error( $saved ) ) {
									$result = $saved;
								} else {
									$this->process_otp_request( $user->ID );
								}
							} elseif ( ! is_wp_error( $result ) && ! $otp_code ) {
								$result = self::get_wp_error(
									'OTPA_MISSING_CODE',
									array(
										'method'     => __METHOD__,
										'user_id'    => $user->ID,
										'identifier' => $identifier,
										'gateway'    => $this->gateway,
									)
								);
							} else {
								do_action( 'otpa_otp_code_requested', $user->ID );
							}
						} else {
							$result = self::get_wp_error(
								'OTPA_INVALID_GATEWAY',
								array(
									'method'     => __METHOD__,
									'user_id'    => $user->ID,
									'identifier' => $identifier,
									'gateway'    => $this->gateway,
								)
							);
						}
					} else {
						$result = self::get_wp_error(
							'OTPA_OTP_BLOCKED',
							array(
								'method'       => __METHOD__,
								'user_id'      => $user->ID,
								'identifier'   => $identifier,
								'block_expiry' => $blocked,
							)
						);
					}
				} else {
					$result = self::get_wp_error(
						'OTPA_INVALID_USER',
						array(
							'method'     => __METHOD__,
							'identifier' => $identifier,
						)
					);
				}
			} else {
				$result = self::get_wp_error(
					'OTPA_THROTTLE',
					array(
						'method'     => __METHOD__,
						'user_id'    => $user->ID,
						'identifier' => $identifier,
					)
				);
			}
		} else {
			$result = self::get_wp_error(
				'OTPA_MISSING_IDENTIFIER',
				array(
					'method'  => __METHOD__,
					'payload' => $payload,
				)
			);
		}

		return $result;
	}

	public function save_otp_code( $otp_code ) {
		$user = wp_get_current_user();

		if ( $user->ID ) {
			$expiry_length = $this->otpa_settings->get_option( 'otp_expiry' ) * 60;
			$expiry        = time() + $expiry_length;
			$meta          = array(
				'otp_code' => $otp_code,
				'expiry'   => $expiry,
			);

			$result = update_user_meta( $user->ID, 'otpa_verification_code', $meta );

			if ( ! $result ) {
				$result = self::get_wp_error(
					'OTPA_CODE_PERSIST_FAILURE',
					array(
						'method'   => __METHOD__,
						'otp_code' => $otp_code,
					)
				);
			} else {
				do_action( 'otpa_otp_code_saved', $user->ID );
			}
		} else {
			$result = self::get_wp_error(
				'OTPA_INVALID_USER',
				array(
					'method'   => __METHOD__,
					'otp_code' => $otp_code,
				)
			);
		}

		return $result;
	}

	public function verify_otp_code( $payload ) {

		if ( isset( $payload['code'] ) ) {
			$input_code = $payload['code'];
			$user       = wp_get_current_user();

			if ( $user->ID ) {
				$blocked = $this->is_otp_blocked( $user->ID );

				if ( ! $blocked ) {
					$meta = get_user_meta( $user->ID, 'otpa_verification_code', true );

					if ( $meta && isset( $meta['otp_code'], $meta['expiry'] ) ) {

						if ( $meta['expiry'] < time() ) {
							$verify_count = $this->process_otp_verify( $user->ID, false );
							$result       = self::get_wp_error(
								'OTPA_EXPIRED_CODE',
								array(
									'method'       => __METHOD__,
									'user_id'      => $user->ID,
									'input_code'   => $input_code,
									'otp_code'     => $meta['otp_code'],
									'expiry'       => $meta['expiry'],
									'verify_count' => $verify_count,
								)
							);
						} elseif ( $meta['otp_code'] !== $input_code ) {
							$verify_count = $this->process_otp_verify( $user->ID, false );
							$result       = self::get_wp_error(
								'OTPA_INVALID_CODE',
								array(
									'method'       => __METHOD__,
									'user_id'      => $user->ID,
									'input_code'   => $input_code,
									'otp_code'     => $meta['otp_code'],
									'expiry'       => $meta['expiry'],
									'verify_count' => $verify_count,
								)
							);
						} else {
							$this->process_otp_verify( $user->ID, true );
							do_action( 'otpa_otp_code_verified', $user->ID );

							$result = true;
						}

						delete_user_meta( $user->ID, 'otpa_verification_code' );
					} elseif ( ! is_wp_error( $result ) ) {
						$verify_count = $this->process_otp_verify( $user->ID, false );
						$result       = self::get_wp_error(
							'OTPA_CODE_NOT_FOUND',
							array(
								'method'       => __METHOD__,
								'user_id'      => $user->ID,
								'input_code'   => $input_code,
								'verify_count' => $verify_count,
							)
						);
					}
				} else {
					$result = self::get_wp_error(
						'OTPA_OTP_BLOCKED',
						array(
							'method'       => __METHOD__,
							'user_id'      => $user->ID,
							'input_code'   => $input_code,
							'block_expiry' => $blocked,
						)
					);
				}
			} else {
				$result = self::get_wp_error(
					'OTPA_INVALID_USER',
					array(
						'method'     => __METHOD__,
						'input_code' => $input_code,
					)
				);
			}

			if ( ! is_wp_error( $result ) ) {

				$result = array(
					'status'   => true,
					'message'  => __( 'Code successfully verified!', 'otpa' ) . '<br/>' . __( 'Redirecting...', 'otpa' ),
					'redirect' => apply_filters( 'otpa_otp_form_redirect_url', home_url(), $user ),
				);
			}
		} else {
			$result = self::get_wp_error(
				'OTPA_MISSING_CODE',
				array(
					'method'  => __METHOD__,
					'payload' => $payload,
				)
			);
		}

		return $result;
	}

	public function set_otp_identifier( $payload ) {

		if ( isset( $payload['identifier'] ) ) {
			$identifier = $payload['identifier'];
			$user       = wp_get_current_user();

			if ( $user->ID ) {
				$redirect = get_user_meta( $user->ID, 'otpa_set_otp_redirect', true );

				if ( $this->gateway->is_valid_identifier( $identifier ) ) {
					$result = $this->gateway->set_user_identifier( $identifier, $user->ID );

					if ( ! $result ) {
						$result = self::get_wp_error(
							'OTPA_DUPLICATE_IDENTIFIER',
							array(
								'method'     => __METHOD__,
								'user_id'    => $user->ID,
								'identifier' => $identifier,
								'redirect'   => $redirect,
							)
						);
					} else {
						delete_user_meta( $user->ID, 'otpa_set_otp_redirect' );

						$result = array(
							'status'   => true,
							'message'  => __( 'Identifier saved!', 'otpa' ) . '<br/>' . __( 'Redirecting...', 'otpa' ),
							'redirect' => $redirect ? $redirect : home_url(),
						);
					}
				} else {
					$result = self::get_wp_error(
						'OTPA_INVALID_IDENTIFIER',
						array(
							'method'     => __METHOD__,
							'user_id'    => $user->ID,
							'identifier' => $identifier,
							'redirect'   => $redirect,
						)
					);
				}
			} else {
				$result = self::get_wp_error(
					'OTPA_INVALID_USER',
					array(
						'method'   => __METHOD__,
						'otp_code' => $otp_code,
					)
				);
			}
		} else {
			$result = self::get_wp_error(
				'OTPA_MISSING_IDENTIFIER',
				array(
					'method'  => __METHOD__,
					'payload' => $payload,
				)
			);
		}

		return $result;
	}

	public function is_otp_blocked( $user_id ) {
		$meta       = $this->get_otp_attempts_meta( $user_id );
		$is_blocked = ( $meta['block_expiry'] > time() ) ? $meta['block_expiry'] : false;

		return $is_blocked;
	}

	public function find_user( $user, $payload ) {

		if ( ! $user->ID && isset( $payload['identifier'] ) && ! empty( $payload['identifier'] ) ) {
			$result = $this->gateway->get_user_by_identifier( $payload['identifier'] );

			if ( $result ) {
				$user = $result;
			}
		}

		return $user;
	}

	public function filter_authorized_error_data( $data ) {

		if ( is_array( $data ) ) {
			$allowed_keys = array(
				'identifier',
				'expiry',
				'verify_count',
				'method',
				'input_code',
				'block_expiry',
			);

			foreach ( $data as $key => $value ) {

				if ( ! in_array( $key, $allowed_keys, true ) ) {
					unset( $data[ $key ] );
				}
			}

			return $data;
		}

		return array();
	}

	/*******************************************************************
	 * Protected methods
	 *******************************************************************/

	protected function get_otp_type() {
		return 'default';
	}

	protected function sanitizer_api_handler( $handler ) {

		if ( ! in_array( $handler, self::$authorized_api_handlers, true ) ) {
			$handler = false;
		}

		return $handler;
	}

	protected function process_otp_verify( $user_id, $success ) {
		$verify_count = 0;

		if ( $success ) {
			$this->reset_otp_attempts( $user_id );
		} else {
			$max_verify = $this->otpa_settings->get_option( 'max_verify' );

			if ( 0 < $max_verify ) {
				$meta = $this->get_otp_attempts_meta( $user_id );

				if ( $meta['verify_count'] < $max_verify ) {
					$meta['verify_count']++;

					update_user_meta( $user_id, 'otpa_otp_attempts', $meta );
				}

				$verify_count = $meta['verify_count'];
			}
		}

		return $verify_count;
	}

	protected function process_otp_request( $user_id ) {
		$request_count = 0;
		$max_request   = $this->otpa_settings->get_option( 'max_request' );

		if ( 0 < $max_request ) {
			$meta = $this->get_otp_attempts_meta( $user_id );

			if ( $meta['request_count'] < $max_request ) {
				$meta['request_count']++;

				update_user_meta( $user_id, 'otpa_otp_attempts', $meta );
			}

			$request_count = $meta['request_count'];
		}

		return $request_count;
	}

	protected function reset_otp_attempts( $user_id ) {
		$track_expiry = $this->otpa_settings->get_option( 'track_expiry' );
		$meta         = array(
			'verify_count'  => 0,
			'request_count' => 0,
			'track_expiry'  => time() + $track_expiry * 60,
			'block_expiry'  => 0,
		);

		update_user_meta( $user_id, 'otpa_otp_attempts', $meta );

		return $meta;
	}

	protected function get_otp_attempts_meta( $user_id ) {
		$meta         = get_user_meta( $user_id, 'otpa_otp_attempts', true );
		$max_verify   = $this->otpa_settings->get_option( 'max_verify' );
		$max_request  = $this->otpa_settings->get_option( 'max_request' );
		$needs_update = false;
		$now          = time();

		if (
			! $meta ||
			! is_array( $meta ) ||
			isset( $meta['track_expiry'] ) && $meta['track_expiry'] < time() ||
			isset( $meta['block_expiry'] ) && 0 < $meta['block_expiry'] && $meta['block_expiry'] < time()
		) {

			return $this->reset_otp_attempts( $user_id );
		}

		if ( ! isset( $meta['verify_count'] ) ) {
			$needs_update         = true;
			$meta['verify_count'] = 0;
		}

		if ( ! isset( $meta['request_count'] ) ) {
			$needs_update          = true;
			$meta['request_count'] = 0;
		}

		if ( ! isset( $meta['track_expiry'] ) ) {
			$needs_update         = true;
			$track_expiry         = $this->otpa_settings->get_option( 'track_expiry' );
			$meta['track_expiry'] = time() + $track_expiry * 60;
		}

		if ( ! isset( $meta['block_expiry'] ) ) {
			$needs_update         = true;
			$meta['block_expiry'] = 0;
		}

		if (
			(
				0 < $max_verify && $max_verify <= $meta['verify_count'] ||
				0 < $max_request && $max_request <= $meta['request_count']
			) &&
			$meta['block_expiry'] < $now
		) {
			$needs_update         = true;
			$block_expiry         = $this->otpa_settings->get_option( 'block_expiry' );
			$meta['block_expiry'] = $now + $block_expiry * 60;
		} elseif ( 0 < $meta['block_expiry'] && $meta['block_expiry'] < $now ) {
			$needs_update         = true;
			$meta['block_expiry'] = 0;
		}

		if ( $needs_update ) {
			update_user_meta( $user_id, 'otpa_otp_attempts', $meta );

			if ( $now < $meta['block_expiry'] ) {
				do_action( 'otpa_otp_blocked', $user_id, $meta['block_expiry'] );
			}
		}

		return $meta;
	}

	protected function get_css() {
		$debug                          = apply_filters( 'otpa_debug', (bool) ( constant( 'WP_DEBUG' ) ) );
		$submit_button_background_color = Otpa_Style_Settings::get_option( 'submit_button_background_color' );
		$submit_button_text_color       = Otpa_Style_Settings::get_option( 'submit_button_text_color' );
		$link_text_color                = Otpa_Style_Settings::get_option( 'link_text_color' );
		$styles                         = '
			.otpa-form button.submit {
				background-color: ' . $submit_button_background_color . ';
				color: ' . $submit_button_text_color . ';
			}

			.otpa-form button.submit:not([disabled]):hover,
			.otpa-form button.submit:not([disabled]):active,
			.otpa-form button.submit:not([disabled]):focus {
				background-color: ' . otpa_adjust_color_brightness( $submit_button_background_color, -10 ) . ';
			}

			.otpa-form button.submit:disabled {
				background-color: ' . otpa_adjust_color_brightness( $submit_button_background_color, -20 ) . ';
			}

			.otpa-form .message a {
				color: ' . $link_text_color . ';
			}

			.otpa-form .message a:hover,
			.otpa-form .message a:active,
			.otpa-form .message a:focus {
				color: ' . otpa_adjust_color_brightness( $link_text_color, +30 ) . ';
			}
		';

		$minified_styles = ( $debug ) ? $styles : otpa_simple_minify_css( $styles );
		$styles          = apply_filters( 'otpa_otp_form_inline_style', $minified_styles, $styles );

		return $styles;
	}

	protected function get_logout_markup() {
		$output  = '<a class="otpa-cancel-link" href="';
		$output .= wp_logout_url( home_url() );
		$output .= '">' . __( 'Log Out' ) . '</a>';

		return $output;
	}

	protected function parse_error() {
		global $wp_query;

		$wp_query->set_404();
		status_header( 404 );

		include get_query_template( '404' );
	}

}
