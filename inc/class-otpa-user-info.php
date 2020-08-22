<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class Otpa_User_Info {
	protected $is_identifier_synced = false;
	protected $otpa_settings;

	public function __construct( $otpa_settings, $init_hooks = false ) {
		$this->otpa_settings        = $otpa_settings;
		$this->is_identifier_synced = otpa_is_identifier_synced();

		if ( $init_hooks ) {

			if ( ! $this->is_identifier_synced ) {
				add_action( 'user_new_form', array( $this, 'admin_registration_form_alter' ), 10, 1 );
				add_action( 'user_profile_update_errors', array( $this, 'user_info_form_validate' ), 10, 3 );

				add_filter( 'registration_errors', array( $this, 'registration_form_validate' ), 10, 3 );
			}

			if ( $this->otpa_settings->get_option( 'enable_validation' ) ) {
				add_action( 'pre_user_query', array( $this, 'alter_user_sort_query' ), 10, 1 );

				add_filter( 'manage_users_columns', array( $this, 'alter_user_table_columns' ), 10, 1 );
				add_filter( 'manage_users_custom_column', array( $this, 'alter_user_table_rows' ), 10, 3 );
				add_filter( 'manage_users_sortable_columns', array( $this, 'alter_user_sortable_columns' ), 10, 1 );
			}

			add_action( 'show_user_profile', array( $this, 'user_profile_form_alter' ), 10, 1 );
			add_action( 'edit_user_profile', array( $this, 'user_profile_form_alter' ), 10, 1 );
			add_action( 'profile_update', array( $this, 'save_user_info' ), 10, 2 );
			add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ), 10, 1 );
			add_action( 'wp_ajax_otpa_toggle_user_validation', array( $this, 'toggle_user_validation' ), 10, 0 );
			add_action( 'wp_ajax_otpa_toggle_user_2fa_active', array( $this, 'toggle_user_2fa_active' ), 10, 0 );
		}
	}

	/*******************************************************************
	 * Public methods
	 *******************************************************************/

	public function user_profile_form_alter( $user ) {
		echo $this->get_otp_info_markup( otpa_get_user_identifier( $user->ID ), $user ); // @codingStandardsIgnoreLine
	}

	public function registration_form_validate( $errors, $sanitized_user_login, $user_email ) {

		if ( is_admin() ) {
			$identifier = filter_input( INPUT_POST, 'otp_identifier', FILTER_SANITIZE_STRING );
			$errors     = $this->validate_default_otp_info( $identifier, $errors );
		}

		return $errors;
	}

	public function admin_registration_form_alter( $operation ) {

		if ( 'add-new-user' !== $operation ) {

			return;
		}

		$this->registration_form_alter();
	}

	public function registration_form_alter() {
		$identifier = filter_input( INPUT_POST, 'otp_identifier', FILTER_SANITIZE_STRING );

		echo $this->get_otp_info_markup( $identifier ); // @codingStandardsIgnoreLine
	}


	public function user_info_form_validate( $errors, $update, $user ) {
		$identifier = filter_input( INPUT_POST, 'otp_identifier', FILTER_SANITIZE_STRING );
		$errors     = $this->validate_default_otp_info( $identifier, $errors, $user );
	}

	public function save_user_info( $user_id, $old_user_data = false ) {
		$identifier = filter_input( INPUT_POST, 'otp_identifier', FILTER_SANITIZE_STRING );

		if ( is_a( $old_user_data, 'WP_User' ) ) {

			if ( otpa_get_user_identifier( $user_id ) === $identifier ) {

				return;
			}
		}

		if ( ! empty( $identifier ) && false === otpa_get_user_by_identifier( $identifier ) ) {
			otpa_set_user_identifier( $identifier, $user_id );
		}
	}

	public function get_otp_info_markup( $identifier, $user = false ) {
		$show_identifier_field     = ! $this->is_identifier_synced;
		$otpa_validation_enabled   = $this->otpa_settings->get_option( 'enable_validation' );
		$otpa_2fa_enabled          = $this->otpa_settings->get_option( 'enable_2fa' );
		$otpa_passwordless_enabled = $this->otpa_settings->get_option( 'enable_passwordless' );

		if ( $user ) {
			$identifier = otpa_get_user_identifier( $user->ID );
		} else {
			$identifier = filter_input( INPUT_POST, 'otp_identifier', FILTER_SANITIZE_STRING );
		}

		if ( is_admin() ) {
			$title = apply_filters( 'otpa_profile_info_title', __( 'OTP Authenticator', 'otpa' ) );

			if ( $show_identifier_field ) {
				$identifier_field_label       = $this->get_identifier_field_label();
				$identifier_field_description = false;
				$identifier_field_editable    = otpa_gateway_allow_edit_identifier();
				$current_user                 = wp_get_current_user();
				$user_validation_info         = otpa_get_user_account_validation_info( $user->ID );
				$current_user_validation_info = otpa_get_user_account_validation_info( $current_user->ID );

				if (
					$otpa_validation_enabled && $user &&
					(
						( $user->ID === $current_user->ID && ! $current_user_validation_info['role_excluded'] ) ||
						( $user->ID !== $current_user->ID && ! $user_validation_info['role_excluded'] )
					)
				) {
					$identifier_field_description = '<strong>' . __( 'Warning - Account Validation is enabled: ', 'otpa' ) . '</strong>';

					if ( $user->ID === $current_user->ID ) {
						$identifier_field_description .= __( 'if changed, you will be asked to validate your account immediately.', 'otpa' );
					} else {
						$identifier_field_description .= __( 'if changed, the user will be asked to validate their account immediately.', 'otpa' );
					}
				}
			}

			if ( $otpa_validation_enabled && $user ) {
				$validation_info        = otpa_get_user_account_validation_info( $user->ID );
				$validation_status      = $validation_info['validated'] ? __( 'Validated', 'otpa' ) : __( 'Not validated', 'otpa' );
				$validation_exluded     = $validation_info['role_excluded'] ? __( 'Yes', 'otpa' ) : __( 'No', 'otpa' );
				$validation_needed      = $validation_info['need_validation'] ? __( 'Yes', 'otpa' ) : __( 'No', 'otpa' );
				$validation_forced      = $validation_info['force_validation'] ? __( 'Yes', 'otpa' ) : __( 'No', 'otpa' );
				$identifier_field_label = $this->get_identifier_field_label();

				switch ( $validation_info['expiry'] ) {
					case 'identifier_changed':
						$validation_expiry = sprintf(
							// translators: %s is the OTP identifier field label
							__( 'Only when the %s is updated', 'otpa' ),
							$this->get_identifier_field_label()
						);
						break;
					case 'next_login':
						$validation_expiry = __( 'At next login', 'otpa' );
						break;
					default:
						$validation_expiry = sprintf(
							// translators: %s is the duration before validation expiry
							__( 'in %s', 'otpa' ),
							otpa_human_timing( $validation_info['expiry'] )
						);
						break;
				}
			}

			if ( $otpa_2fa_enabled && $user ) {
				$user_2fa_info   = otpa_get_user_2fa_info( $user->ID );
				$user_2fa_active = $user_2fa_info['active'];
			}

			$show_admin_validation_button = (
				current_user_can( 'delete_users' ) &&
				! empty( $identifier ) &&
				$otpa_validation_enabled &&
				0 !== $this->otpa_settings->get_option( 'validation_expiry' )
			);
			$show_admin_2fa_button        = (
				current_user_can( 'edit_users' ) &&
				! $this->otpa_settings->get_option( 'force_2fa' ) &&
				$user &&
				$otpa_2fa_enabled
			);
			$show_admin_otp_section       = (
				$show_identifier_field &&
				( $otpa_validation_enabled || $otpa_2fa_enabled || $otpa_passwordless_enabled )
			) ||
			( $otpa_validation_enabled && $user ) ||
			$show_admin_2fa_button;

		}

		ob_start();

		require_once OTPA_PLUGIN_PATH . 'inc/templates/otpa-otp-info.php';

		$output = ob_get_clean();

		return $output;
	}

	public function validate_default_otp_info( $identifier, $errors, $user = false ) {
		$new_errors = array();

		if ( empty( $identifier ) ) {
			$new_errors[] = '<strong>' . __( 'Error: ', 'otpa' ) . '</strong>' . sprintf(
				// translators: %s is the OTP Identifier field label
				__( '%s field is required.', 'otpa' ),
				$this->get_identifier_field_label()
			);
		}

		if ( ! empty( $identifier ) && ! otpa_is_valid_identifier( $identifier ) ) {
			$wp_error     = Otpa::get_wp_error( 'OTPA_INVALID_IDENTIFIER' );
			$new_errors[] = '<strong>' . __( 'Error: ', 'otpa' ) . '</strong>' . str_replace( '<br/>', ' ', $wp_error->get_error_message() );
		}

		if (
			! empty( $identifier ) &&
			otpa_is_valid_identifier( $identifier ) &&
			false !== otpa_get_user_by_identifier( $identifier ) &&
			(
				false === $user ||
				otpa_get_user_identifier( $user->ID ) !== otpa_sanitize_user_identifier( $identifier )
			)
		) {
			$wp_error     = Otpa::get_wp_error( 'OTPA_DUPLICATE_IDENTIFIER' );
			$new_errors[] = '<strong>' . __( 'Error: ', 'otpa' ) . '</strong>' . str_replace( '<br/>', ' ', $wp_error->get_error_message() );
		}

		if ( ! empty( $new_errors ) ) {

			foreach ( $new_errors as $error_message ) {
				$errors->add( 'otp_identifier_error', $error_message );
			}
		}

		return $errors;
	}


	public function admin_enqueue_scripts( $hook_suffix ) {

		if ( 'users.php' === $hook_suffix || 'user-edit.php' === $hook_suffix || 'profile.php' === $hook_suffix ) {
			$debug   = apply_filters( 'otpa_debug', (bool) ( constant( 'WP_DEBUG' ) ) );
			$css_ext = ( $debug ) ? '.css' : '.min.css';
			$version = filemtime( OTPA_PLUGIN_PATH . 'css/admin/main' . $css_ext );

			wp_enqueue_style( 'otpa-main-style', OTPA_PLUGIN_URL . 'css/admin/main' . $css_ext, array(), $version );

			$script_params = apply_filters(
				'otpa_profile_js_parameters',
				array(
					'ajax_url' => admin_url( 'admin-ajax.php' ),
					'debug'    => $debug,
				)
			);

			$js_ext  = ( $debug ) ? '.js' : '.min.js';
			$version = filemtime( OTPA_PLUGIN_PATH . 'js/admin/user-info' . $js_ext );

			wp_enqueue_script(
				'otpa-user-info-script',
				OTPA_PLUGIN_URL . 'js/admin/user-info' . $js_ext,
				array( 'jquery' ),
				$version,
				true
			);
			wp_localize_script( 'otpa-user-info-script', 'OTPA', $script_params );
		}
	}

	public function toggle_user_validation() {
		$nonce = filter_input( INPUT_POST, 'nonce', FILTER_SANITIZE_STRING );

		if ( ! wp_verify_nonce( $nonce, 'otpa_user_info_nonce' ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'Error: unauthorized access - please reload the page and try again.', 'otpa' ),
				)
			);
		}

		$user_id = filter_input( INPUT_POST, 'user_id', FILTER_VALIDATE_INT );
		$user    = get_user_by( 'ID', $user_id );

		if ( ! $user ) {
			wp_send_json_error(
				array(
					'message' => __( 'Error: unauthorized access - please reload the page and try again.', 'otpa' ),
				)
			);
		}

		$validation_info = otpa_get_user_account_validation_info( $user->ID );

		if ( $validation_info['validated'] ) {
			otpa_reset_account_validation( $user_id );
		} else {
			otpa_do_account_validation( $user_id );
		}

		wp_send_json_success(
			array(
				'message' => __( 'Reloading page...', 'otpa' ),
			)
		);
	}

	public function toggle_user_2fa_active() {
		$nonce = filter_input( INPUT_POST, 'nonce', FILTER_SANITIZE_STRING );

		if ( ! wp_verify_nonce( $nonce, 'otpa_user_info_nonce' ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'Error: unauthorized access - please reload the page and try again.', 'otpa' ),
				)
			);
		}

		$user_id = filter_input( INPUT_POST, 'user_id', FILTER_VALIDATE_INT );
		$user    = get_user_by( 'ID', $user_id );

		if ( ! $user ) {
			wp_send_json_error(
				array(
					'message' => __( 'Error: unauthorized access - please reload the page and try again.', 'otpa' ),
				)
			);
		}

		$user_2fa_info = otpa_get_user_2fa_info( $user->ID );

		otpa_set_user_2fa_active( $user_id, ! $user_2fa_info['active'] );
		wp_send_json_success(
			array(
				'message' => __( 'Reloading page...', 'otpa' ),
			)
		);
	}

	public function alter_user_table_columns( $columns ) {
		$columns['otpa_validation_status'] = __( 'OTP Validated', 'otpa' );

		return $columns;
	}

	public function alter_user_table_rows( $val, $column_name, $user_id ) {

		if ( 'otpa_validation_status' === $column_name ) {
			$val = $this->build_validation_status_cell( $user_id );
		}

		return $val;
	}

	public function alter_user_sortable_columns( $columns ) {
		$columns['otpa_validation_status'] = 'otpa_validation_status';

		return $columns;
	}

	public function alter_user_sort_query( $userquery ) {

		if ( 'otpa_validation_status' === $userquery->query_vars['orderby'] ) {
			global $wpdb;

			$userquery->query_from    .= " LEFT JOIN {$wpdb->usermeta} m1 ON {$wpdb->users}.ID = m1.user_id AND (m1.meta_key = 'otpa_validated')";
			$userquery->query_orderby  = ' ORDER BY m1.meta_value ';
			$userquery->query_orderby .= ( 'ASC' === $userquery->query_vars['order'] ? 'asc ' : 'desc ' );
		}
	}

	/*******************************************************************
	 * Protected methods
	 *******************************************************************/

	protected function build_validation_status_cell( $user_id ) {
		$info    = otpa_get_user_account_validation_info( $user_id );
		$output  = '<span class="otpa-validation-status';
		$output .= ( $info['validated'] ) ? '' : ' not-validated';
		$output .= '">';
		$output .= ( $info['validated'] ) ? __( 'Validated', 'otpa' ) : __( 'Not validated', 'otpa' );
		$output .= '</span>';

		return $output;
	}

	protected function get_identifier_field_label() {

		return apply_filters(
			'otpa_otp_identifier_field_label',
			__( 'OTP Identifier', 'otpa' )
		);
	}
}
