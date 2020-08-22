<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class Otpa_Woocommerce_Integration extends Otpa_Integration {
	protected $sync_metakey;

	/*******************************************************************
	 * Public methods
	 *******************************************************************/

	public function init_hooks() {
		add_action( 'woocommerce_login_form_end', array( $this, 'auth_link' ), 0, 0 );

		$this->sync_metakey = $this->gateway->get_option( 'sync_metakey' );

		if ( $this->sync_metakey ) {
			add_filter( 'woocommerce_form_field', array( $this, 'alter_synced_field' ), 10, 4 );
			add_filter( 'woocommerce_customer_meta_fields', array( $this, 'alter_admin_synced_field' ), 99, 1 );
			add_filter( 'otpa_woocommerce_account_validation_otp_identifier_description', array( $this, 'alter_identifier_description' ), 10, 4 );
			add_filter( 'woocommerce_registration_errors', array( $this, 'validate_account_registration' ), 10, 1 );
			add_action( 'woocommerce_after_save_address_validation', array( $this, 'validate_address_update' ), 10, 4 );
		}

		add_filter( 'woocommerce_save_account_details_errors', array( $this, 'validate_account_update' ), 10, 2 );
		add_action( 'woocommerce_edit_account_form', array( $this, 'account_form_alter' ), 10, 0 );
	}

	public function alter_admin_synced_field( $meta_fields ) {
		$validation_enabled = $this->otpa_settings->get_option( 'enable_validation' );

		if ( $validation_enabled && $this->sync_metakey ) {

			foreach ( $meta_fields as $section_key => $section ) {

				foreach ( $section['fields'] as $meta_key => $field ) {

					if ( $this->sync_metakey === $meta_key ) {

						$user_id                      = filter_input( INPUT_GET, 'user_id', FILTER_VALIDATE_INT );
						$user                         = get_user_by( 'ID', $user_id );
						$current_user                 = wp_get_current_user();
						$user_validation_info         = otpa_get_user_account_validation_info( $user->ID );
						$current_user_validation_info = otpa_get_user_account_validation_info( $current_user->ID );

						if (
							( $user_id === $current_user->ID && ! $current_user_validation_info['role_excluded'] ) ||
							( $user_id !== $current_user->ID && ! $user_validation_info['role_excluded'] )
						) {
							$description  = '<div class="otpa-warning">';
							$description .= '<strong>' . __( 'Warning - OTP Account Validation is enabled: ', 'otpa' ) . '</strong>';

							if ( $user_id === $current_user->ID ) {
								$description .= __( 'if changed, you will be asked to validate your account immediately.', 'otpa' );
							} else {
								$description .= __( 'if changed, the user will be asked to validate their account immediately.', 'otpa' );
							}

							$description .= '</div>';

							$meta_fields[ $section_key ]['fields'][ $meta_key ]['description'] .= $description;
						}
					}
				}
			}
		}

		return $meta_fields;
	}

	public function alter_synced_field( $field, $key, $args, $value ) {

		if ( $this->sync_metakey === $key ) {
			$custom_attributes = array();

			if ( ! empty( $args['custom_attributes'] ) && is_array( $args['custom_attributes'] ) ) {

				foreach ( $args['custom_attributes'] as $attribute => $attribute_value ) {
					$custom_attributes[] = esc_attr( $attribute ) . '="' . esc_attr( $attribute_value ) . '"';
				}
			}

			if ( $args['required'] || $this->otpa_settings->get_option( 'enable_validation' ) || $this->otpa_settings->get_option( 'enable_2fa' ) ) {
				$args['class'][] = 'validate-required';
				$required        = '&nbsp;<abbr class="required" title="' . esc_attr__( 'required', 'woocommerce' ) . '">*</abbr>';
			} else {
				$required = '&nbsp;<span class="optional">(' . esc_html__( 'optional', 'woocommerce' ) . ')</span>';
			}

			$user        = wp_get_current_user();
			$description = '';

			if ( $this->otpa_settings->get_option( 'enable_passwordless' ) ) {
				$description .= __( 'Used for Passwordless Authentication', 'otpa' );
			} elseif ( $this->otpa_settings->get_option( 'enable_2fa' ) ) {
				$description .= __( 'Used for Two-Factor Authentication', 'otpa' );
			}

			if ( $this->otpa_settings->get_option( 'enable_validation' ) && is_user_logged_in() && ! otpa_is_user_account_validation_excluded( $user->ID ) ) {
 
				if ( ! empty( $description ) ) {
					$description .= '<br/>';
				}

				$description .= '<strong>' . __( 'User Account Validation is enabled: ', 'otpa' ) . '</strong>';

				if ( is_checkout() ) {

					if ( is_user_logged_in() ) {
						$description .= __( 'if changed, you will be asked to validate your account immediately upon checkout.', 'otpa' );
					} elseif ( 'yes' !== get_option( 'woocommerce_enable_guest_checkout' ) ) {
						$description .= __( 'you will be asked to validate your account immediately upon checkout.', 'otpa' );
					} else {
						$description .= __( 'you will be asked to validate your account immediately upon checkout if you choose to create an account.', 'otpa' );
					}
				} elseif ( is_user_logged_in() ) {
					$description .= __( 'if changed, you will be asked to validate your account immediately.', 'otpa' );
				} else {
					$description .= __( 'you will be asked to validate your account immediately.', 'otpa' );
				}

				$description = apply_filters(
					'otpa_woocommerce_account_validation_otp_identifier_description',
					$description,
					$key,
					is_user_logged_in(),
					is_checkout()
				);
			}

			$label_id        = $args['id'];
			$sort            = $args['priority'] ? $args['priority'] : '';
			$field_container = '<p class="form-row %1$s" id="%2$s" data-priority="' . esc_attr( $sort ) . '">%3$s</p>';
			$field_html      = '';
			$field           = '<input type="' . esc_attr( $args['type'] ) . '" class="input-text ' . esc_attr( implode( ' ', $args['input_class'] ) ) . '" name="' . esc_attr( $key ) . '" id="' . esc_attr( $args['id'] ) . '" placeholder="' . esc_attr( $args['placeholder'] ) . '"  value="' . esc_attr( $value ) . '" ' . implode( ' ', $custom_attributes ) . ' />';

			if ( $args['label'] ) {
				$field_html .= '<label for="' . esc_attr( $label_id ) . '" class="' . esc_attr( implode( ' ', $args['label_class'] ) ) . '">' . $args['label'] . $required . '</label>';
			}

			$field_html          .= '<span class="woocommerce-input-wrapper">' . $field;
			$args['description']  = ( $args['description'] ) ? $args['description'] . '<br/>' : $args['description'];
			$args['description'] .= $description;
			$field_html          .= '<span class="description otpa-warning" id="' . esc_attr( $args['id'] ) . '-description" aria-hidden="true">' . wp_kses_post( $args['description'] ) . '</span>';
			$field_html          .= '</span>';

			$container_class = esc_attr( implode( ' ', $args['class'] ) );
			$container_id    = esc_attr( $args['id'] ) . '_field';
			$field           = sprintf( $field_container, $container_class, $container_id, $field_html );
		}

		return $field;
	}

	public function alter_identifier_description( $description, $key, $is_user_logged_in, $is_checkout ) {

		if ( stripos( $key, 'phone' ) ) {
			$description = '<strong>' . __( 'User Account Validation is enabled: ', 'otpa' ) . '</strong>';

			if ( is_checkout() ) {

				if ( is_user_logged_in() ) {
					$description .= __( 'if changed, you will be asked to validate your account with this phone number immediately upon checkout.', 'otpa' );
				} elseif ( 'yes' !== get_option( 'woocommerce_enable_guest_checkout' ) ) {
					$description .= __( 'you will be asked to validate your account immediately with this phone number upon checkout.', 'otpa' );
				} else {
					$description .= __( 'you will be asked to validate your account immediately with this phone number upon checkout if you choose to create an account.', 'otpa' );
				}
			} elseif ( is_user_logged_in() ) {
				$description .= __( 'if changed, you will be asked to validate your account with this phone number immediately.', 'otpa' );
			} else {
				$description .= __( 'you will be asked to validate your account with this phone number immediately.', 'otpa' );
			}
		}

		return $description;
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

			$class = 'otpa-passwordless-woocommerce-' . str_replace( '_', '-', current_filter() );

			$this->otpa_passwordless->auth_link( true, $class );
		}
	}

	public function validate_account_registration( $errors, $username, $email ) {
		return $this->validate_account_details( $errors );
	}

	public function validate_account_update( $errors, $user ) {
		$required = (
			$this->otpa_settings->get_option( 'enable_validation' ) ||
			$this->otpa_settings->get_option( 'enable_2fa' )
		) && $this->gateway->allow_edit_identifier();

		$this->validate_account_details( $errors, $user->ID, $required );
	}

	public function validate_address_update( $user_id, $load_address, $address, $customer ) {
		$post_key     = ( $this->sync_metakey ) ? $this->sync_metakey : 'otp_identifier';
		$posted_value = filter_input( INPUT_POST, $post_key, FILTER_SANITIZE_STRING );
		$error        = false;

		if ( $posted_value ) {
			$sanitized_identifier = $this->gateway->sanitize_user_identifier( $posted_value );

			if (
				false !== $this->gateway->get_user_by_identifier( $sanitized_identifier ) &&
				$this->gateway->get_user_identifier( $user_id ) !== $sanitized_identifier
			) {
				$error = $this->otpa->get_wp_error( 'OTPA_DUPLICATE_IDENTIFIER' );
			} elseif ( ! $this->gateway->is_valid_identifier( $sanitized_identifier ) ) {
				$error = $this->otpa->get_wp_error( 'OTPA_INVALID_IDENTIFIER' );
			}
		}

		if ( $error ) {
			wc_add_notice( $error->get_error_message(), 'error' );
		}
	}

	public function account_form_alter() {
		$user                   = wp_get_current_user();
		$label                  = apply_filters( 'otpa_otp_identifier_field_label', __( 'One-Time Password Identifier', 'otpa' ) );
		$value                  = filter_input( INPUT_POST, 'otp_identifier', FILTER_SANITIZE_STRING );
		$identifier_description = '';
		$show_identifier_field  = ( ! $this->sync_metakey );
		$required               = (
			$this->otpa_settings->get_option( 'enable_validation' ) ||
			$this->otpa_settings->get_option( 'enable_2fa' )
		) && $this->gateway->allow_edit_identifier();

		if ( $this->otpa_settings->get_option( 'enable_passwordless' ) ) {
			$identifier_description .= __( 'Used for Passwordless Authentication', 'otpa' );
		} elseif ( $this->otpa_settings->get_option( 'enable_2fa' ) ) {
			$identifier_description .= __( 'Used for Two-Factor Authentication', 'otpa' );
		}

		if ( $this->otpa_settings->get_option( 'enable_validation' ) ) {
			$identifier_description .= ( empty( $identifier_description ) ) ? '' : '<br/>';
			$identifier_description .= '<strong>' . __( 'Account Validation is enabled: ', 'otpa' ) . '</strong>' . __( 'if changed, you will be asked to validate your account immediately.', 'otpa' );
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
					apply_filters( 'otpa_2fa_woocommerce_switch_button_label', '' ),
					apply_filters( 'otpa_2fa_woocommerce_switch_button_on_text', '' ),
					apply_filters( 'otpa_2fa_woocommerce_switch_button_off_text', '' ),
					apply_filters( 'otpa_2fa_woocommerce_switch_button_class', '' )
				);
			?>
		</p>
		<?php endif; ?>
		<?php if ( $show_identifier_field ) : ?>
		<p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
			<label for="otp_identifier"><?php echo esc_html( $label ); ?>
			<?php if ( $required ) : ?>
				&nbsp;<span class="required">*</span>
			<?php endif; ?>
			</label>

			<?php if ( $identifier_editable ) : ?>
				<input type="text" class="woocommerce-Input woocommerce-Input--text input-text otpa-otp-identifier" name="otp_identifier" id="otp_identifier" value="<?php echo esc_attr( $value ); ?>" />
				<?php if ( ! empty( $identifier_description ) ) : ?>
					<span class="description opta-warning"><em><?php echo $identifier_description; // @codingStandardsIgnoreLine ?></em></span>
				<?php endif; ?>
			<?php else : ?>
				<span class="otpa-otp-identifier" name="otp_identifier" id="otp_identifier"><?php echo esc_html( $value ); ?></span>
			<?php endif; ?>
		</p>
		<?php endif; ?>
		<?php
	}

	/*******************************************************************
	 * Protected methods
	 *******************************************************************/

	protected function validate_account_details( $errors, $user_id = false, $identifer_required = false ) {
		$post_key     = ( $this->sync_metakey ) ? $this->sync_metakey : 'otp_identifier';
		$posted_value = filter_input( INPUT_POST, $post_key, FILTER_SANITIZE_STRING );
		$error        = false;

		if ( $posted_value ) {
			$sanitized_identifier = $this->gateway->sanitize_user_identifier( $posted_value );

			if ( false !== $this->gateway->get_user_by_identifier( $sanitized_identifier ) ) {

				if (
					! $user_id ||
					(
						$user_id &&
						$this->gateway->get_user_identifier( $user_id ) !== $sanitized_identifier
					)
				) {
					$error = $this->otpa->get_wp_error( 'OTPA_DUPLICATE_IDENTIFIER' );
				}
			} elseif ( ! $this->gateway->is_valid_identifier( $sanitized_identifier ) ) {
				$error = $this->otpa->get_wp_error( 'OTPA_INVALID_IDENTIFIER' );
			}
		} elseif ( $required && ! $this->sync_metakey ) {
			$label = apply_filters( 'otpa_otp_identifier_field_label', __( 'OTP Identifier', 'otpa' ) );
			// translators: %s is the identifier's label
			$message = sprintf( __( 'The %s is required.', 'otpa' ), $label );
			$error   = $this->otpa->get_wp_error( 'OTPA_MISSING_IDENTIFIER', array(), $message );
		}

		if ( $error ) {
			$errors->add(
				$sync_metakey,
				__( '<strong>Error</strong>: ', 'otpa' ) . $error->get_error_message()
			);
		}

		return $errors;
	}
}
