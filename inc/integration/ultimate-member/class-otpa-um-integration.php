<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class Otpa_UM_Integration extends Otpa_Integration {
	protected $sync_metakey;

	/*******************************************************************
	 * Public methods
	 *******************************************************************/

	public function init_hooks() {
		add_action( 'um_after_form', array( $this, 'auth_link' ), 0, 1 );
		add_filter( 'um_get_option_filter__accessible', array( $this, 'um_allow_access' ), 10, 1 );

		$this->sync_metakey = $this->gateway->get_option( 'sync_metakey' );

		if ( $this->sync_metakey ) {
			add_action( 'um_submit_form_errors_hook', array( $this, 'um_submit_form_errors_hook' ), 10 );

			add_filter( 'um_' . $this->sync_metakey . '_form_edit_field', array( $this, 'alter_synced_field' ), 10, 2 );
		} else {
			add_action( 'um_submit_account_errors_hook', array( $this, 'um_submit_form_errors_hook' ), 10 );
			add_action( 'um_after_user_account_updated', array( $this, 'save_account_details' ), 10, 2 );
			add_action( 'um_custom_error_message_handler', array( $this, 'account_identifier_error' ), 10, 2 );
		}

		add_action( 'um_after_account_general', array( $this, 'account_form_alter' ), 10, 1 );
	}

	public function alter_synced_field( $output, $set_mode ) {
		$description = '';

		if ( $this->otpa_settings->get_option( 'enable_passwordless' ) ) {
			$description .= __( 'Used for Passwordless Authentication', 'otpa' );
		} elseif ( $this->otpa_settings->get_option( 'enable_2fa' ) ) {
			$description .= __( 'Used for Two-Factor Authentication', 'otpa' );
		}

		if ( $this->otpa_settings->get_option( 'enable_validation' ) ) {

			if ( ! empty( $description ) ) {
				$description .= '<br/>';
			}

			$current_user = wp_get_current_user();
			$description .= '<span class="otpa-warning"><strong>' . __( 'Account Validation is enabled: ', 'otpa' ) . '</strong>';

			if ( um_profile_id() === $current_user->ID ) {
				$description .= __( 'if changed, you will be asked to validate your account immediately.', 'otpa' );
			} else {
				$description .= __( 'if changed, the user will be asked to validate their account immediately.', 'otpa' );
			}

			$description .= '</span>';
		}

		return ( empty( $description ) ) ? $output : $output . '<p class="otpa-description">' . $description . '<p>';
	}

	public function auth_link( $args = null ) {

		if ( $this->otpa_settings->get_option( 'enable_passwordless' ) ) {

			if ( ! empty( $args ) && is_array( $args ) ) {

				if (
					isset( $args['template'], $args['mode'] ) &&
					'login' === $args['template'] &&
					'login' === $args['mode']
				) {
					$display = true;
				}
			} else {
				$display = true;
			}

			$class = 'otpa-passwordless-um-' . str_replace( '_', '-', current_filter() );

			$this->otpa_passwordless->auth_link( true, $class );
		}
	}

	public function um_allow_access( $access ) {
		global $wp;

		if ( isset( $wp->query_vars['__otpa_api'] ) || isset( $wp->query_vars['__otpa_page'] ) ) {
			$action          = $wp->query_vars['action'];
			$allowed_actions = array(
				'passwordless_login',
				'2fa',
				'otp',
			);

			if ( in_array( $action, $allowed_actions, true ) ) {
				$access = 0;
			}
		}

		return $access;
	}

	public function um_submit_form_errors_hook( $args ) {
		$post_key = ( $this->sync_metakey ) ? $this->sync_metakey : 'otp_identifier';
		$fields   = isset( $args['custom_fields'] ) ? unserialize( $args['custom_fields'] ) : false; // @codingStandardsIgnoreLine

		if ( ! empty( $fields ) && is_array( $fields ) ) {

			foreach ( $fields as $key => $array ) {

				if (
					isset( $array['validate'] ) &&
					isset( $array['metakey'] ) &&
					isset( $args['submitted'], $args['submitted'][ $post_key ], $args[ $post_key ] ) &&
					$post_key === $array['metakey']
				) {
					$sanitized_identifier = $this->gateway->sanitize_user_identifier( $args['submitted'][ $post_key ] );

					if (
						false !== $this->gateway->get_user_by_identifier( $args['submitted'][ $post_key ] ) &&
						$this->gateway->get_user_identifier( $args['user_id'] ) !== $sanitized_identifier &&
						! UM()->form()->has_error( $key )
					) {
						$error = $this->otpa->get_wp_error( 'OTPA_DUPLICATE_IDENTIFIER' );

						UM()->form()->add_error( $key, $error->get_error_message() );
					} elseif ( ! $this->gateway->is_valid_identifier( $args['submitted'][ $post_key ] ) ) {
						$error = $this->otpa->get_wp_error( 'OTPA_INVALID_IDENTIFIER' );

						UM()->form()->add_error( $key, $error->get_error_message() );
					}
				}
			}
		} elseif (
			isset( $args['_um_account'], $args['_um_account_tab'] ) &&
			$args['_um_account'] &&
			'general' === $args['_um_account_tab'] &&
			'otp_identifier' === $post_key
		) {
			$sanitized_identifier = $this->gateway->sanitize_user_identifier( $args[ $post_key ] );

			if (
				empty( $args[ $post_key ] ) &&
				(
					$this->otpa_settings->get_option( 'enable_validation' ) ||
					$this->otpa_settings->get_option( 'enable_2fa' )
				) &&
				$this->gateway->allow_edit_identifier()
			) {
				$url = UM()->account()->tab_link( $args['_um_account_tab'] );
				$url = add_query_arg( 'err', 'OTPA_MISSING_IDENTIFIER', $url );

				wp_redirect( $url );

				exit();
			} elseif ( ! empty( $args[ $post_key ] ) && $this->gateway->allow_edit_identifier() ) {
				$sanitized_identifier = $this->gateway->sanitize_user_identifier( $args[ $post_key ] );

				if (
					false !== $this->gateway->get_user_by_identifier( $args[ $post_key ] ) &&
					$this->gateway->get_user_identifier( get_current_user_id() ) !== $sanitized_identifier
				) {
					$url = UM()->account()->tab_link( $args['_um_account_tab'] );
					$url = add_query_arg( 'err', 'OTPA_DUPLICATE_IDENTIFIER', $url );

					wp_redirect( $url );

					exit();
				}
			}
		}
	}

	public function account_identifier_error( $error, $request_error ) {
		$message = $request_error;
		$label   = apply_filters( 'otpa_otp_identifier_field_label', __( 'OTP Identifier', 'otpa' ) );

		switch ( $request_error ) {
			case 'OTPA_MISSING_IDENTIFIER':
				// translators: %s is the identifier's label
				$message = sprintf( __( 'The %s is required.', 'otpa' ), $label );
				$error   = $this->otpa->get_wp_error( $request_error, array(), $message );
				$error   = $error->get_error_message();
				break;
			case 'OTPA_DUPLICATE_IDENTIFIER':
				$error = $this->otpa->get_wp_error( $request_error, array(), $message );
				$error = $error->get_error_message();
				break;
		}

		return $error;
	}

	public function save_account_details( $user_id, $changes ) {
		$value = filter_input( INPUT_POST, 'otp_identifier', FILTER_SANITIZE_STRING );

		if ( $value ) {
			$this->gateway->set_user_identifier( $value, $user_id );
		}
	}

	public function account_form_alter( $args ) {
		$user                   = wp_get_current_user();
		$label                  = apply_filters(
			'otpa_otp_identifier_field_label',
			__( 'One-Time Password Identifier', 'otpa' )
		);
		$value                  = filter_input( INPUT_POST, 'otp_identifier', FILTER_SANITIZE_STRING );
		$identifier_description = '';
		$show_identifier_field  = ( ! $this->sync_metakey );

		if ( $this->otpa_settings->get_option( 'enable_passwordless' ) ) {
			$identifier_description .= __( 'Used for Passwordless Authentication', 'otpa' );
		} elseif ( $this->otpa_settings->get_option( 'enable_2fa' ) ) {
			$identifier_description .= __( 'Used for Two-Factor Authentication', 'otpa' );
		}

		if ( $this->otpa_settings->get_option( 'enable_validation' ) ) {
			$identifier_description .= ( empty( $identifier_description ) ) ? '' : '<br/>';
			$identifier_description .= '<strong>' . __( 'Account Validation is enabled: ', 'otpa' ) . '</strong>';

			if ( um_profile_id() === $user->ID ) {
				$identifier_description .= __( 'if changed, you will be asked to validate your account immediately.', 'otpa' );
			} else {
				$identifier_description .= __( 'if changed, the user will be asked to validate their account immediately.', 'otpa' );
			}
		}

		$identifier_editable = $this->gateway->allow_edit_identifier();
		$can_change_2fa      = $this->otpa_settings->get_option( 'enable_2fa' ) && false === $this->otpa_settings->get_option( 'force_2fa' );

		if ( ! $value ) {
			$value = $this->gateway->get_user_identifier( $user->ID );
		}

		?>
		<?php if ( $can_change_2fa ) : ?>
		<p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
			<?php
				echo $this->otpa_2fa->get_user_2fa_button_switch( // @codingStandardsIgnoreLine
					apply_filters( 'otpa_2fa_um_switch_button_label', '' ),
					apply_filters( 'otpa_2fa_um_switch_button_on_text', '' ),
					apply_filters( 'otpa_2fa_um_switch_button_off_text', '' ),
					apply_filters( 'otpa_2fa_um_switch_button_class', '' )
				);
			?>
		</p>
		<?php endif; ?>
		<?php if ( $show_identifier_field ) : ?>
		<div id="um_field_general_otp_identifier" class="um-field um-field-text um-field-otp_identifier um-field-type_text">
			<div class="um-field-label">
				<label for="otp_identifier"><?php echo esc_html( $label ); ?></label>
				<div class="um-clear"></div>
			</div>

			<?php if ( $identifier_editable ) : ?>
				<div class="um-field-area">
					<input type="text" class="um-form-field otpa-otp-identifier" name="otp_identifier" id="otp_identifier" data-key="otp_identifier" value="<?php echo esc_attr( $value ); ?>" />
				</div>

				<?php if ( ! empty( $identifier_description ) ) : ?>
					<span class="description opta-warning"><em><?php echo $identifier_description; // @codingStandardsIgnoreLine ?></em></span>
				<?php endif; ?>
			<?php else : ?>
				<span class="otpa-otp-identifier" name="otp_identifier" id="otp_identifier"><?php echo esc_html( $value ); ?></span>
			<?php endif; ?>
		</div>
		<?php endif; ?>
		<?php
	}

	/*******************************************************************
	 * Protected methods
	 *******************************************************************/

}
