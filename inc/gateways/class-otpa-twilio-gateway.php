<?php

use Twilio\Rest\Client;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class Otpa_Twilio_Gateway extends Otpa_Abstract_Gateway {
	protected $identifier_meta = 'otpa_twilio_phone';
	protected $code_length     = 4;
	protected $code_chars      = '0123456789';
	protected $client;

	public function __construct( $init_hooks = false, $settings_renderer = false, $otpa_settings = false ) {
		$this->name = __( 'Twilio', 'otpa' );

		parent::__construct( $init_hooks, $settings_renderer, $otpa_settings );

		if ( $init_hooks ) {
			add_action( 'otpa_before_otp_form', array( $this, 'print_form_hint' ), 10, 1 );

			add_filter( 'otpa_otp_widget_identifier_placeholder', array( $this, 'identifier_name' ), 10, 1 );
			add_filter( 'otpa_otp_identifier_field_label', array( $this, 'identifier_name' ), 10, 1 );
			add_filter( 'otpa_wp_error_message', array( $this, 'error_message_alter' ), 10, 3 );
		}
	}

	/*******************************************************************
	 * Public methods
	 *******************************************************************/

	public function validate_settings( $valid, $main_settings ) {
		$errors = is_array( $valid ) ? $valid : array();

		if (
			isset( $main_settings['enable_2fa'] ) && $main_settings['enable_2fa'] ||
			isset( $main_settings['enable_passwordless'] ) && $main_settings['enable_passwordless'] ||
			isset( $main_settings['enable_validation'] ) && $main_settings['enable_validation']
		) {
			// translators: %s is the name of the required field
			$missing_field_format = __( 'Authentication Gateway setting value "%s" is missing.', 'otpa' );

			if ( ! isset( $this->settings['account_sid'] ) || empty( $this->settings['account_sid'] ) ) {
				$errors[] = sprintf(
					$missing_field_format,
					__( 'Account SID', 'otpa' )
				);
			}

			if ( ! isset( $this->settings['auth_token'] ) || empty( $this->settings['auth_token'] ) ) {
				$errors[] = sprintf(
					$missing_field_format,
					__( 'Auth Token', 'otpa' )
				);
			}

			if ( ! isset( $this->settings['from_number'] ) || empty( $this->settings['from_number'] ) ) {
				$errors[] = sprintf(
					$missing_field_format,
					__( 'Twilio Phone Number', 'otpa' )
				);
			}

			if ( ! version_compare( PHP_VERSION, '7.2.0', '>=' ) ) {
				$errors[] = __( 'Twilio PHP Libraries require PHP 7.2 or highier. Please update PHP on your server to use this AUthentication Gateway.', 'otpa' );
			}

			if (
				isset( $this->settings['min_phone_length'] ) &&
				! empty( $this->settings['min_phone_length'] ) &&
				isset( $this->settings['max_phone_length'] ) &&
				! empty( $this->settings['max_phone_length'] ) &&
				$this->settings['max_phone_length'] < $this->settings['min_phone_length']
			) {
				$errors[] = __( 'The Phone Max. Digits value must be greater than or equal to the Phone Min. Digits value.', 'otpa' );
			}
		}

		return empty( $errors ) ? true : $errors;
	}

	public function init_settings_definition() {
		$default_settings      = self::get_default_settings();
		$this->settings_fields = array(
			'main'           => array(
				array(
					'id'    => 'account_sid',
					'label' => __( 'Account SID', 'otpa' ) . ' <span class="required">*</span>',
					'type'  => 'input_password',
					'class' => 'regular-text toggle',
					'help'  => sprintf(
						// translators: %s is the link to the Twilio console.
						__( 'The Account SID found in the %s.', 'otpa' ),
						'<a target="_blank" href="https://www.twilio.com/console">' . __( 'Twilio console' ) . '</a>'
					),
				),
				array(
					'id'    => 'auth_token',
					'label' => __( 'Auth Token', 'otpa' ) . ' <span class="required">*</span>',
					'type'  => 'input_password',
					'class' => 'regular-text toggle',
					'help'  => sprintf(
						// translators: %s is the link to the Twilio console.
						__( 'The Auth Token found in the %s.', 'otpa' ),
						'<a target="_blank" href="https://www.twilio.com/console">' . __( 'Twilio console' ) . '</a>'
					),
				),
				array(
					'id'    => 'from_number',
					'label' => __( 'Twilio Phone Number', 'otpa' ) . ' <span class="required">*</span>',
					'type'  => 'input_password',
					'class' => 'regular-text toggle',
					'help'  => sprintf(
						// translators: %s is the link to the Twilio console.
						__( 'A Twilio phone number used to send the messages, found in the %s.', 'otpa' ),
						'<a target="_blank" href="https://www.twilio.com/console">' . __( 'Twilio console' ) . '</a>'
					),
				),
				// translators: %s is the Gateway name
				'title' => sprintf( __( '%s Gateway Settings', 'otpa' ), $this->name ),
			),
			'phone'          => array(
				array(
					'id'      => 'sync_metakey',
					'label'   => __( 'Phone Field Meta key', 'otpa' ),
					'type'    => 'input_text',
					'default' => $default_settings['sync_metakey'],
					'class'   => '',
					'help'    => __( 'The key of a User Metadata field holding the value of mobile phones in the WordPress database.', 'otpa' ) . '<br/>' . __( 'Upon update, to keep identifiers unique, if the mobile phone is already registered with another user account, the metadata will not be saved.', 'otpa' ) . '<br/>' . __( 'If left empty or the meta key does not exist, a default field for the mobile phone number will be displayed on the WordPress default user profile edit forms.', 'otpa' ),
				),
				array(
					'id'      => 'max_phone_length',
					'label'   => __( 'Phone Max. Digits', 'otpa' ),
					'type'    => 'input_number',
					'min'     => 0,
					'step'    => 1,
					'default' => $default_settings['max_phone_length'],
					'class'   => '',
					'help'    => __( 'The maximum amount of digits of mobile phone numbers (excluding prefix)', 'otpa' ) . '<br/>' . __( 'If left empty, "10" (USA) will be used.', 'otpa' ),
				),
				array(
					'id'      => 'min_phone_length',
					'label'   => __( 'Phone Min. Digits', 'otpa' ),
					'type'    => 'input_number',
					'min'     => 0,
					'step'    => 1,
					'default' => $default_settings['min_phone_length'],
					'class'   => '',
					'help'    => __( 'The minimum amount of digits of mobile phone numbers (excluding prefix)', 'otpa' ) . '<br/>' . __( 'If left empty, "10" (USA) will be used.', 'otpa' ),
				),
				array(
					'id'      => 'allowed_phone_prefixes',
					'label'   => __( 'Allowed Phone Prefixes', 'otpa' ),
					'type'    => 'input_text',
					'min'     => 0,
					'step'    => 1,
					'default' => $default_settings['allowed_phone_prefixes'],
					'class'   => '',
					'help'    => __( 'A comma-separated list of prefixes allowed at user input (for example: "+1,+33,+44").', 'otpa' ) . '<br/>' . sprintf(
						// translators: %s is the link to Twilio Messaging Geographic Permissions settings
						__( 'Prefixes must be authorized first in the %s.' ),
						'<a target="__blank" href="https://www.twilio.com/console/sms/settings/geo-permissions">' . __( 'Twilio Messaging Geographic Permissions settings' ) . '</a>'
					) . '<br/>' . __( 'If left empty or none is provided at user input, the Default Phone Prefix will be used for the input phone number.', 'otpa' ),
				),
				array(
					'id'      => 'default_phone_prefix',
					'label'   => __( 'Default Phone Prefix', 'otpa' ),
					'type'    => 'input_text',
					'min'     => 0,
					'step'    => 1,
					'default' => $default_settings['default_phone_prefix'],
					'class'   => '',
					'help'    => __( 'The default prefix to add to the phone numbers by default if none is provided at user input.', 'otpa' ) . '<br/>' . sprintf(
						// translators: %s is the link to Twilio Messaging Geographic Permissions settings
						__( 'Prefixes must be authorized first in the %s.' ),
						'<a target="__blank" href="https://www.twilio.com/console/sms/settings/geo-permissions">' . __( 'Twilio Messaging Geographic Permissions settings' ) . '</a>'
					) . '<br/>' . __( 'If left empty, "+1" (USA) will be used.', 'otpa' ),
				),
				'title' => __( 'Mobile Phone Number Settings', 'otpa' ),
			),
			'sms_attributes' => array(
				array(
					'id'    => 'message',
					'label' => __( 'Message', 'otpa' ),
					'type'  => 'input_text',
					'class' => 'regular-text',
					'help'  => __( 'The message sent to phones requesting an OTP Verification Code.', 'otpa' ) . '<br/>' . __( 'If left empty, a default value will be used.', 'otpa' ),
				),
				'title' => __( 'SMS Settings', 'otpa' ),
			),
		);

		$this->settings_fields = apply_filters( 'otpa_settings_fields_' . $this->get_gateway_id(), $this->settings_fields );
	}

	public function sanitize_settings( $settings, $old_settings = array() ) {
		$settings         = parent::sanitize_settings( $settings, $old_settings );
		$default_settings = self::get_default_settings();

		if ( empty( $settings['default_phone_prefix'] ) ) {
			$settings['default_phone_prefix'] = $default_settings['default_phone_prefix'];
		}

		if ( empty( $settings['max_phone_length'] ) ) {
			$settings['max_phone_length'] = $default_settings['max_phone_length'];
		}

		if ( empty( $settings['min_phone_length'] ) ) {
			$settings['min_phone_length'] = $default_settings['min_phone_length'];
		}

		if ( empty( $settings['message'] ) ) {
			$settings['message'] = $default_settings['message'];
		}

		$settings['allowed_phone_prefixes'] = str_replace( ' ', '', $settings['allowed_phone_prefixes'] );
		$settings['default_phone_prefix']   = str_replace( ' ', '', $settings['default_phone_prefix'] );

		return apply_filters( $this->get_gateway_id() . '_sanitize_settings', $settings );
	}

	public function print_form_hint( $otp_form_type ) {
		$output = '<p class="message">';
		$phone  = $this->get_user_identifier();

		if ( ! empty( $phone ) ) {
			$output .= sprintf(
				// translators: %s is the masked email address
				__( 'Enter your mobile phone number %s to request a Verification Code.', 'otpa' ),
				'<br/><strong>' . otpa_mask_phone( $phone ) . '</strong><br/>'
			);
		} else {
			$output .= __( 'Enter your registered mobile phone number to request a Verification Code.', 'otpa' );
		}

		$output .= '</p>';

		echo $output; // @codingStandardsIgnoreLine
	}

	public function identifier_name( $placeholder ) {
		return __( 'Mobile Phone Number', 'otpa' );
	}

	public function sanitize_user_identifier( $phone, $user_id = false ) {
		$phone           = filter_var( $phone, FILTER_SANITIZE_NUMBER_INT );
		$prefixes_string = $this->get_option( 'allowed_phone_prefixes' );
		$prefixes        = ( empty( $prefixes_string ) ) ? '' : array_map( 'trim', explode( ',', $prefixes_string ) );
		$prefix          = '';
		$phone           = preg_replace( '/[\(\)\_\- ]/', '', $phone );

		if ( ! empty( $prefixes ) ) {

			foreach ( $prefixes as $maybe_prefix ) {

				if ( 0 === stripos( $phone, $maybe_prefix ) ) {
					$phone  = str_replace( $maybe_prefix, '', $phone );
					$prefix = $maybe_prefix;

					break;
				}
			}
		}

		if ( empty( $prefix ) ) {
			$default_prefix = $this->get_option( 'default_phone_prefix' );
			$prefix         = ( 0 !== stripos( $default_prefix, '+' ) ) ? '+' . $default_prefix : $default_prefix;
		}

		$phone = str_replace( '+', '', str_replace( $prefix, '', $phone ) );

		return empty( $phone ) ? '' : $prefix . $phone;
	}

	public function is_valid_identifier( $phone ) {
		$phone           = filter_var( $phone, FILTER_SANITIZE_NUMBER_INT );
		$prefixes_string = $this->get_option( 'allowed_phone_prefixes' );
		$prefixes        = ( empty( $prefixes_string ) ) ? '' : array_map( 'trim', explode( ',', $prefixes_string ) );
		$min_length      = $this->get_option( 'min_phone_length' );
		$max_length      = $this->get_option( 'min_phone_length' );
		$prefix          = '';
		$phone           = preg_replace( '/[\(\)\_\- ]/', '', $phone );

		if ( ! empty( $prefixes ) ) {

			foreach ( $prefixes as $maybe_prefix ) {

				if ( 0 !== stripos( $maybe_prefix, '+' ) ) {
					$maybe_prefix = '+' . $maybe_prefix;
				}

				if ( 0 === stripos( $phone, $maybe_prefix ) ) {
					$phone  = str_replace( $maybe_prefix, '', $phone );
					$prefix = $maybe_prefix;

					break;
				}
			}
		}

		if ( empty( $prefix ) ) {
			$default_prefix = $this->get_option( 'default_phone_prefix' );
			$prefix         = ( 0 !== stripos( $default_prefix, '+' ) ) ? '+' . $default_prefix : $default_prefix;
		}

		$phone = str_replace( $prefix, '', $phone );

		if ( 0 === stripos( $phone, '+' ) ) {

			return false;
		}

		if ( strlen( $phone ) > $max_length || strlen( $phone ) < $min_length ) {

			return false;
		}

		return true;
	}

	public function error_message_alter( $message, $code, $data ) {

		switch ( $code ) {
			case 'OTPA_INVALID_IDENTIFIER':
				$prefixes_string = $this->get_option( 'allowed_phone_prefixes' );

				if ( empty( $prefixes_string ) ) {
					$prefixes = array( $this->get_option( 'default_phone_prefix' ) );
				} else {
					$prefixes = array_map( 'trim', explode( ',', $prefixes_string ) );
				}

				foreach ( $prefixes as $prefix ) {

					if ( 0 !== stripos( $prefix, '+' ) ) {
						$prefix = '+' . $prefix;
					}
				}

				$prefix_string  = implode( ', ', $prefixes );
				$min_length     = $this->get_option( 'min_phone_length' );
				$max_length     = $this->get_option( 'min_phone_length' );
				$default_prefix = $this->get_option( 'default_phone_prefix' );

				if ( $min_length === $max_length ) {
					// translators: %s is the phone length
					$length_string = sprintf( __( '%s digits', 'otpa' ), $min_length );
				} else {
					// translators: %1$s is the phone min length, %2$s is the phone max length
					$length_string = sprintf( __( 'between %1$s and %2$s digits', 'otpa' ), $min_length );
				}
				// translators: %1$s is the phone length hint, %2$s is the phone prefix hint %3$s is the default prefix hint
				$message = __( 'The mobile phone number is invalid.', 'otpa' ) . ' ' . sprintf( __( 'Format: %1$s, allowed prefixes: %2$s ; default %3$s.', 'otpa' ), $length_string, $prefix_string, $default_prefix ) . '<br/>' . __( 'Please enter a valid phone number.' );
				break;
			case 'OTPA_DUPLICATE_IDENTIFIER':
				$message = __( 'The mobile phone number is already registered.', 'otpa' ) . '<br/>' . __( 'Please enter another valid mobile phone number and try again.' );
				break;
			default:
				break;
		}

		return $message;
	}

	/*******************************************************************
	 * Protected methods
	 *******************************************************************/

	protected static function get_default_settings() {

		return array(
			'message'                => 'Verification Code: ###CODE###',
			'sync_metakey'           => '',
			'max_phone_length'       => 10,
			'min_phone_length'       => 10,
			'allowed_phone_prefixes' => '',
			'default_phone_prefix'   => '+1',
		);
	}

	protected function validate_input_identifier( $phone ) {

		if ( ! parent::validate_input_identifier( $phone ) ) {

			return new WP_Error(
				'OTPA_INVALID_PHONE_NUMBER',
				__( 'Invalid mobile phone number. Please enter your registered phone number and try again.' ),
				array(
					'method'     => __METHOD__,
					'identifier' => $phone,
				)
			);
		}

		return true;
	}

	protected function load_library() {

		try {
			require_once OTPA_PLUGIN_PATH . 'libraries/twilio/Twilio/autoload.php';

			return true;
		} catch ( Throwable $e ) {

			if ( method_exists( $e, 'getCode' ) && method_exists( $e, 'getMessage' ) ) {
				otpa_db_log( __METHOD__ . ' - error ' . $e->getCode() . ': ' . $e->getMessage(), 'alert', true );
			} else {
				otpa_db_log( __METHOD__ . ' - error ' . print_r( $e, true ), 'alert', true ); // @codingStandardsIgnoreLine
			}

			return false;
		}
	}

	protected function init_api() {

		try {
			$this->client = new Client( $this->settings['account_sid'], $this->settings['auth_token'] );

			return true;
		} catch ( Throwable $e ) {

			if ( method_exists( $e, 'getCode' ) && method_exists( $e, 'getMessage' ) ) {
				otpa_db_log( __METHOD__ . ' - error ' . $e->getCode() . ': ' . $e->getMessage(), 'alert', true );
			} else {
				otpa_db_log( __METHOD__ . ' - error ' . print_r( $e, true ), 'alert', true ); // @codingStandardsIgnoreLine
			}

			return false;
		}
	}

	protected function send_sandox_request( $phone, $otp_code ) {

		otpa_db_log(
			array(
				'message' => __( 'Sandbox simulated request - data sent to the Authentication Gateway: ', 'otpa' ),
				'data'    => array(
					'to'      => $this->sanitize_user_identifier( $phone ),
					'options' => array(
						'from' => $this->settings['from_number'],
						'body' => str_replace( '###CODE###', $otp_code, $this->settings['message'] ),
					),
				),
			)
		);

		return array(
			'status'  => true,
			// translators: %s is the user's OTP identifier
			'message' => sprintf( __( 'An SMS with a Verification Code was sent to %s (sandbox).', 'otpa' ), $phone ),
			'code'    => 'OK',
		);
	}

	protected function send_request( $phone, $otp_code ) {
		$message = __( 'The Authentication Gateway has experienced a problem.', 'otpa' ) . '<br/>' . __( 'Please contact an administrator.', 'otpa' );

		try {

			$result = $this->client->messages->create(
				$this->sanitize_user_identifier( $phone ),
				array(
					'from' => $this->settings['from_number'],
					'body' => str_replace( '###CODE###', $otp_code, $this->settings['message'] ),
				)
			);

			if ( ! $result ) {

				return array(
					'status'  => false,
					'message' => __( 'An undefined error occured - please try again or use another phone number.', 'otpa' ),
				);
			} elseif ( null !== $result->errorCode ) {  // @codingStandardsIgnoreLine
				$illegal_number_err_codes = array(
					21211,
					21214,
					21217,
					21401,
					21407,
					21408,
					21421,
					21422,
					21452,
					21601,
					21612,
					21614,
					22102,
				);

				if ( in_array( intval( $result->errorCode ), $illegal_number_err_codes, true ) ) {  // @codingStandardsIgnoreLine
					$message = __( 'The phone number is invalid. Please try again with a valid mobile phone number.', 'otpa' );
				}

				otpa_db_log( __METHOD__ . ' - error ' . $result->errorCode . ': ' . $result->errorMessage, 'alert', true );  // @codingStandardsIgnoreLine

				return array(
					'status'  => false,
					'message' => $message,
					'code'    => $result->errorCode, // @codingStandardsIgnoreLine
				);
			}

			return array(
				'status'  => true,
				// translators: %s is the user's mobile phone number
				'message' => sprintf( __( 'An SMS with a Verification Code was sent to %s.', 'otpa' ), $phone ),
				'code'    => 'OK',
			);
		} catch ( Exception $e ) {
			otpa_db_log( __METHOD__ . ' - error ' . $e->getCode() . ': ' . $e->getMessage(), 'alert', true );

			return array(
				'status'  => false,
				'message' => $message,
				'code'    => $e->getCode(),
			);
		}
	}

}
