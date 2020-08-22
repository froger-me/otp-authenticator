<?php

use AlibabaCloud\Client\AlibabaCloud;
use AlibabaCloud\Client\Exception\ClientException;
use AlibabaCloud\Client\Exception\ServerException;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class Otpa_Alibaba_Cloud_Sms_Gateway extends Otpa_Abstract_Gateway {
	protected $identifier_meta = 'otpa_aliyun_phone';
	protected $code_length     = 4;
	protected $code_chars      = '0123456789';

	public function __construct( $init_hooks = false, $settings_renderer = false, $otpa_settings = false ) {
		$this->name = __( 'Alibaba Cloud SMS', 'otpa' );

		parent::__construct( $init_hooks, $settings_renderer, $otpa_settings );

		if ( $init_hooks ) {
			add_action( 'otpa_before_otp_form', array( $this, 'print_form_hint' ), 10, 1 );
			add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ), 10, 1 );

			add_filter( 'otpa_otp_widget_identifier_placeholder', array( $this, 'identifier_name' ), 10, 1 );
			add_filter( 'otpa_otp_identifier_field_label', array( $this, 'identifier_name' ), 10, 1 );
			add_filter( 'otpa_wp_error_message', array( $this, 'error_message_alter' ), 10, 3 );
		}
	}

	/*******************************************************************
	 * Public methods
	 *******************************************************************/

	public function admin_enqueue_scripts( $hook_suffix ) {

		if ( 'settings_page_otpa' === $hook_suffix ) {
			$debug   = apply_filters( 'otpa_debug', (bool) ( constant( 'WP_DEBUG' ) ) );
			$js_ext  = ( $debug ) ? '.js' : '.min.js';
			$version = filemtime( OTPA_PLUGIN_PATH . 'inc/gateways/js/admin/otpa-alibaba-cloud-sms-gateway-admin' . $js_ext );

			wp_enqueue_script(
				'otpa-alibaba-cloud-sms-gateway-admin-script',
				OTPA_PLUGIN_URL . 'inc/gateways/js/admin/otpa-alibaba-cloud-sms-gateway-admin' . $js_ext,
				array( 'jquery' ),
				$version,
				true
			);
		}
	}

	public function validate_settings( $valid, $main_settings ) {
		$errors = is_array( $valid ) ? $valid : array();

		if (
			isset( $main_settings['enable_2fa'] ) && $main_settings['enable_2fa'] ||
			isset( $main_settings['enable_passwordless'] ) && $main_settings['enable_passwordless'] ||
			isset( $main_settings['enable_validation'] ) && $main_settings['enable_validation']
		) {
			// translators: %s is the name of the required field
			$missing_field_format = __( 'Authentication Gateway setting value "%s" is missing.', 'otpa' );

			if ( ! isset( $this->settings['access_key'] ) || empty( $this->settings['access_key'] ) ) {
				$errors[] = sprintf(
					$missing_field_format,
					__( 'Access Key', 'otpa' )
				);
			}

			if ( ! isset( $this->settings['access_secret'] ) || empty( $this->settings['access_secret'] ) ) {
				$errors[] = sprintf(
					$missing_field_format,
					__( 'Access Secret', 'otpa' )
				);
			}

			if (
				( $this->settings['china_us'] || ! $this->settings['intl'] ) &&
				( ! isset( $this->settings['template_code'] ) || empty( $this->settings['template_code'] ) )
			) {
				$errors[] = sprintf(
					$missing_field_format,
					__( 'Template Code (模版CODE)', 'otpa' )
				);
			}

			if ( ! isset( $this->settings['signature_name'] ) || empty( $this->settings['signature_name'] ) ) {
				$errors[] = sprintf(
					$missing_field_format,
					__( 'Signature Name (签名名称)', 'otpa' )
				);
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
					'id'    => 'intl',
					'label' => __( 'Use International SMS Product', 'otpa' ),
					'type'  => 'input_checkbox',
					'class' => '',
					'help'  => __( 'When International SMS Product is used, SMS sent to non-chinese phone numbers will not use a template unless explicitely restricted to Chinese and US Phone Numbers.', 'otpa' ) . '<br/>' . __( 'Messages sent to Chinese phone numbers still require an approved Template (See "SMS Settings" below).', 'otpa' ) . '<br/>' . sprintf(
						// translators: %s is the link to the Alibaba Cloud Product.
						'<strong>' . __( 'If checked, requires the %s product.', 'otpa' ) . '</strong>',
						'<a target="_blank" href="https://www.alibabacloud.com/help/doc-detail/158393.htm">' . __( 'International Alibaba Cloud SMS' ) . '</a>'
					) . '<br/>' . sprintf(
						// translators: %s is the link to the Alibaba Cloud Product.
						'<strong>' . __( 'If unchecked, requires the %s product.', 'otpa' ) . '</strong>',
						'<a target="_blank" href="https://help.aliyun.com/product/44282.html">' . __( 'China Mainland Alibaba Cloud SMS' ) . '</a>'
					),
				),
				array(
					'id'    => 'china_us',
					'label' => __( 'International SMS Product - restricted to Chinese and US Phone Numbers', 'otpa' ),
					'type'  => 'input_checkbox',
					'class' => '',
					'help'  => __( 'When International SMS Product is restricted to Chinese and US Phone Numbers, SMS sent to user must use a pre-approved Template Code.', 'otpa' ) . '<br/>' . sprintf(
						// translators: %s is the link to the Alibaba Cloud Product.
						'<strong>' . __( 'Requires the %s product.', 'otpa' ) . '</strong>',
						'<a target="_blank" href="https://www.alibabacloud.com/help/doc-detail/158393.htm">' . __( 'International Alibaba Cloud SMS' ) . '</a>'
					),
				),
				array(
					'id'    => 'access_key',
					'label' => __( 'Access Key', 'otpa' ) . ' <span class="required">*</span>',
					'type'  => 'input_password',
					'class' => 'regular-text toggle',
					'help'  => sprintf(
						// translators: %1$s is the link to Mainland Alibaba Cloud console, %2$s is the link to the international one.
						__( 'The Access Key found in "Products and Services" > "Short Message Service" of the %1$s or the %2$s.', 'otpa' ),
						'<a target="_blank" href="https://console.aliyun.com">' . __( 'Mainland China Alibaba Cloud console' ) . '</a>',
						'<a target="_blank" href="https://home-intl.console.aliyun.com">' . __( 'International Alibaba Cloud console' ) . '</a>'
					),
				),
				array(
					'id'    => 'access_secret',
					'label' => __( 'Access Secret', 'otpa' ) . ' <span class="required">*</span>',
					'type'  => 'input_password',
					'class' => 'regular-text toggle',
					'help'  => sprintf(
						// translators: %1$s is the link to Mainland Alibaba Cloud console, %2$s is the link to the international one.
						__( 'The Access Secret found in "Products and Services" > "Short Message Service" of the %1$s or the %2$s.', 'otpa' ),
						'<a target="_blank" href="https://console.aliyun.com">' . __( 'Mainland China Alibaba Cloud console' ) . '</a>',
						'<a target="_blank" href="https://home-intl.console.aliyun.com">' . __( 'International Alibaba Cloud console' ) . '</a>'
					),
				),
				array(
					'id'      => 'region',
					'label'   => __( 'Region', 'otpa' ),
					'default' => 'cn-hangzhou',
					'type'    => 'input_text',
					'help'    => sprintf(
						// translators: %s is the link to Alibaba Cloud Regions and zones documentation.
						__( 'The Region identifier used by the Cloud API (see %s).', 'otpa' ),
						'<a target="_blank" href="https://www.alibabacloud.com/help/doc-detail/40654.html"> ' . __( 'Regions and zones documentation', 'otpa' ) . '</a>'
					) . '<br/>' . __( 'If left empty, a default value will be used.', 'otpa' ),
				),
				array(
					'id'      => 'version',
					'label'   => __( 'API Version', 'otpa' ),
					'default' => '2017-05-25',
					'type'    => 'input_text',
					'help'    => sprintf(
						// translators: %1$s is the link to China Mainland Alibaba Cloud SMS Service Public Request Parameters documentation, %2$s is the International one.
						__( 'The API Version for the SMS Service Product (see %1$s for the China Mainland Alibaba Cloud SMS Product, or the %2$s for the International Alibaba Cloud SMS Product).', 'otpa' ),
						'<a target="_blank" href="https://help.aliyun.com/document_detail/101341.html"> ' . __( '公共请求参数', 'otpa' ) . '</a>',
						'<a target="_blank" href="https://www.alibabacloud.com/help/doc-detail/162282.htm"> ' . __( 'Common parameters documentation', 'otpa' ) . '</a>'
					) . '<br/>' . __( 'If left empty, a default value will be used.', 'otpa' ),
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
					'help'    => __( 'The maximum amount of digits of mobile phone numbers (excluding prefix)', 'otpa' ) . '<br/>' . __( 'If left empty, "11" (Mainland China) will be used.', 'otpa' ),
				),
				array(
					'id'      => 'min_phone_length',
					'label'   => __( 'Phone Min. Digits', 'otpa' ),
					'type'    => 'input_number',
					'min'     => 0,
					'step'    => 1,
					'default' => $default_settings['min_phone_length'],
					'class'   => '',
					'help'    => __( 'The minimum amount of digits of mobile phone numbers (excluding prefix)', 'otpa' ) . '<br/>' . __( 'If left empty, "11" (Mainland China) will be used.', 'otpa' ),
				),
				array(
					'id'      => 'allowed_phone_prefixes',
					'label'   => __( 'Allowed Phone Prefixes', 'otpa' ),
					'type'    => 'input_text',
					'min'     => 0,
					'step'    => 1,
					'default' => $default_settings['allowed_phone_prefixes'],
					'class'   => '',
					'help'    => __( 'A comma-separated list of prefixes allowed at user input (for example: "+86,+1,+33").', 'otpa' ) . '<br/>' . __( 'If left empty or none is provided at user input, the Default Phone Prefix will be used for the input phone number.', 'otpa' ) . '<br/>' . __( 'Forced to "+86,+1" if the International SMS Product is used and restricted to Chinese and US Phone Numbers.', 'otpa' ),
				),
				array(
					'id'      => 'default_phone_prefix',
					'label'   => __( 'Default Phone Prefix', 'otpa' ),
					'type'    => 'input_text',
					'min'     => 0,
					'step'    => 1,
					'default' => $default_settings['default_phone_prefix'],
					'class'   => '',
					'help'    => __( 'The default prefix to add to the phone numbers by default if none is provided at user input.', 'otpa' ) . '<br/>' . __( 'If left empty, "+86" (Mainland China) will be used.', 'otpa' ),
				),
				'title' => __( 'Mobile Phone Number Settings', 'otpa' ),
			),
			'sms_attributes' => array(
				array(
					'id'    => 'intl_message',
					'label' => __( 'International SMS Product Message', 'otpa' ),
					'type'  => 'input_text',
					'class' => 'regular-text',
					'help'  => __( 'The message sent to non-chinese phones requesting an OTP Verification Code when using the International SMS Product, not restricted to Chinese and US Phone Numbers.', 'otpa' ) . '<br/>' . __( 'If left empty, a default value will be used.', 'otpa' ),
				),
				array(
					'id'    => 'template_code',
					'label' => __( 'Template Code (模版CODE)', 'otpa' ),
					'type'  => 'input_text',
					'help'  => sprintf(
						// translators: %1$s is the link to Mainland Alibaba Cloud console, %2$s is the link to the international one.
						__( 'A valid Template Code found in "Products and Services" > "Short Message Service" of the %1$s or the %2$s.', 'otpa' ),
						'<a target="_blank" href="https://console.aliyun.com">' . __( 'Mainland China Alibaba Cloud console' ) . '</a>',
						'<a target="_blank" href="https://home-intl.console.aliyun.com">' . __( 'International Alibaba Cloud console' ) . '</a>'
					) . '<br/><strong>' . __( 'Required to be able to send messages to chinese phone numbers, or if using the International SMS Product and restricted to Chinese and US Phone Numbers.', 'otpa' ) . '</strong>',
				),
				array(
					'id'    => 'signature_name',
					'label' => __( 'Signature Name (签名名称)', 'otpa' ) . ' <span class="required">*</span>',
					'type'  => 'input_text',
					'help'  => sprintf(
						// translators: %1$s is the link to Mainland Alibaba Cloud console, %2$s is the link to the international one.
						__( 'A valid Signature Name found in "Products and Services" > "Short Message Service" of the %1$s, or an 11 characters string if using the International SMS Product.', 'otpa' ),
						'<a target="_blank" href="https://console.aliyun.com">' . __( 'Mainland China Alibaba Cloud console' ) . '</a>'
					),
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

		if ( empty( $settings['intl_message'] ) ) {
			$settings['intl_message'] = $default_settings['intl_message'];
		}

		if ( empty( $settings['intl'] ) ) {
			$settings['intl'] = $default_settings['intl'];
		}

		if ( empty( $settings['china_us'] ) ) {
			$settings['china_us'] = $default_settings['china_us'];
		}

		$settings['allowed_phone_prefixes'] = str_replace( ' ', '', $settings['allowed_phone_prefixes'] );
		$settings['default_phone_prefix']   = str_replace( ' ', '', $settings['default_phone_prefix'] );

		if ( $settings['intl'] && $settings['china_us'] ) {
			$settings['allowed_phone_prefixes'] = '+86,+1';

			if ( '+1' !== $settings['default_phone_prefix'] && '+86' !== $settings['default_phone_prefix'] ) {
				$settings['default_phone_prefix'] = '+86';
			}
		}

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
			'intl'                   => false,
			'china_us'               => false,
			'intl_message'           => 'Verification Code: ###CODE###',
			'sync_metakey'           => '',
			'max_phone_length'       => 11,
			'min_phone_length'       => 11,
			'allowed_phone_prefixes' => '',
			'default_phone_prefix'   => '+86',
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
			require_once OTPA_PLUGIN_PATH . 'libraries/alibaba/autoload.php';

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
			AlibabaCloud::accessKeyClient(
				$this->settings['access_key'],
				$this->settings['access_secret']
			)->regionId( $this->settings['region'] )->asDefaultClient();

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

		if ( $this->settings['intl'] ) {
			$host = 'dysmsapi.ap-southeast-1.aliyuncs.com';

			if ( $this->settings['china_us'] || 0 === strpos( $this->sanitize_user_identifier( $phone ), '+86' ) ) {
				$action = 'SendMessageWithTemplate';
				$args   = array(
					'query' => array(
						'RegionId'      => $this->settings['region'],
						'To'            => str_replace( '+', '', $this->sanitize_user_identifier( $phone ) ),
						'From'          => $this->settings['signature_name'],
						'TemplateCode'  => $this->settings['template_code'],
						'TemplateParam' => wp_json_encode( array( 'code' => $otp_code ), JSON_UNESCAPED_UNICODE ),
					),
				);
			} else {
				$action = 'SendMessageToGlobe';
				$args   = array(
					'query' => array(
						'RegionId' => $this->settings['region'],
						'To'       => str_replace( '+', '', $this->sanitize_user_identifier( $phone ) ),
						'From'     => $this->settings['signature_name'],
						'Message'  => str_replace( '###CODE###', $otp_code, $this->settings['intl_message'] ),
					),
				);
			}
		} else {
			$host   = 'dysmsapi.aliyuncs.com';
			$action = 'SendSms';
			$args   = array(
				'query' => array(
					'RegionId'      => $this->settings['region'],
					'PhoneNumbers'  => $this->sanitize_user_identifier( $phone ),
					'SignName'      => $this->settings['signature_name'],
					'TemplateCode'  => $this->settings['template_code'],
					'TemplateParam' => wp_json_encode( array( 'code' => $otp_code ), JSON_UNESCAPED_UNICODE ),
				),
			);
		}

		otpa_db_log(
			array(
				'message' => __( 'Sandbox simulated request - data sent to the Authentication Gateway: ', 'otpa' ),
				'data'    => array(
					'version' => $this->settings['version'],
					'action'  => $action,
					'host'    => $host,
					'options' => $args,
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

		if ( $this->settings['intl'] ) {
			$host = 'dysmsapi.ap-southeast-1.aliyuncs.com';

			if ( $this->settings['china_us'] ) {
				$action = 'SendMessageWithTemplate';
				$args   = array(
					'query' => array(
						'RegionId'      => $this->settings['region'],
						'To'            => str_replace( '+', '', $this->sanitize_user_identifier( $phone ) ),
						'From'          => $this->settings['signature_name'],
						'TemplateCode'  => $this->settings['template_code'],
						'TemplateParam' => wp_json_encode( array( 'code' => $otp_code ), JSON_UNESCAPED_UNICODE ),
					),
				);
			} else {
				$action = 'SendMessageToGlobe';
				$args   = array(
					'query' => array(
						'RegionId' => $this->settings['region'],
						'To'       => str_replace( '+', '', $this->sanitize_user_identifier( $phone ) ),
						'From'     => $this->settings['signature_name'],
						'Message'  => str_replace( '###CODE###', $otp_code, $this->settings['intl_message'] ),
					),
				);
			}
		} else {
			$host   = 'dysmsapi.aliyuncs.com';
			$action = 'SendSms';
			$args   = array(
				'query' => array(
					'RegionId'      => $this->settings['region'],
					'PhoneNumbers'  => $this->sanitize_user_identifier( $phone ),
					'SignName'      => $this->settings['signature_name'],
					'TemplateCode'  => $this->settings['template_code'],
					'TemplateParam' => wp_json_encode( array( 'code' => $otp_code ), JSON_UNESCAPED_UNICODE ),
				),
			);
		}

		try {
			$result = AlibabaCloud::rpc()
					->product( 'Dysmsapi' )
					->version( $this->settings['version'] )
					->action( $action )
					->method( 'POST' )
					->host( $host )
					->options( $args )
					->request();

			if ( ! $result ) {

				return array(
					'status'  => false,
					'message' => __( 'An undefined error occured - please try again or use another phone number.', 'otpa' ),
				);
			} elseif ( 'OK' !== $result->Code ) { // @codingStandardsIgnoreLine

				if ( 'isv.BUSINESS_LIMIT_CONTROL' === $result->Code ) { // @codingStandardsIgnoreLine
					$message = __( 'You are submitting this form too many times or too fast - please try again in 1 minute.', 'otpa' );
				} elseif ( 'isv.MOBILE_NUMBER_ILLEGAL' === $result->Code ) { // @codingStandardsIgnoreLine
					$message = __( 'The phone number is invalid. Please try again with a valid mobile phone number.', 'otpa' );
				}

				otpa_db_log( __METHOD__ . ' - error ' . $result->Code . ': ' . $result->Message, 'alert', true );  // @codingStandardsIgnoreLine

				return array(
					'status'  => false,
					'message' => $message,
					'code'    => $result->Code, // @codingStandardsIgnoreLine
				);
			}

			return array(
				'status'  => true,
				// translators: %s is the user's mobile phone number
				'message' => sprintf( __( 'An SMS with a Verification Code was sent to %s.', 'otpa' ), $phone ),
				'code'    => 'OK',
			);
		} catch ( ClientException $e ) {
			otpa_db_log( __METHOD__ . ' - error ' . $e->getErrorCode() . ': ' . $e->getErrorMessage(), 'alert', true );

			return array(
				'status'  => false,
				'message' => $message,
				'code'    => $e->getErrorCode(),
			);
		} catch ( ServerException $e ) {
			otpa_db_log( __METHOD__ . ' - error ' . $e->getErrorCode() . ': ' . $e->getErrorMessage(), 'alert', true );

			return array(
				'status'  => false,
				'message' => $message,
				'code'    => $e->getErrorCode(),
			);
		}
	}

}
