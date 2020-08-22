<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class Otpa_WP_Email_Gateway extends Otpa_Abstract_Gateway {
	protected $error                 = false;
	protected $error_code            = false;
	protected $can_change_identifier = false;
	protected $identifier_meta       = 'otpa_mail';

	public function __construct( $init_hooks = false, $settings_renderer = false, $otpa_settings = false ) {
		$this->name = __( 'WordPress Email', 'otpa' );

		parent::__construct( $init_hooks, $settings_renderer, $otpa_settings );

		if ( $init_hooks ) {
			add_action( 'otpa_before_otp_form', array( $this, 'print_form_hint' ), 10, 1 );
			add_action( 'profile_update', array( $this, 'profile_update' ), 10, 2 );

			add_filter( 'otpa_otp_widget_identifier_placeholder', array( $this, 'otp_widget_identifier_placeholder' ), 10, 1 );
		}
	}

	/*******************************************************************
	 * Public methods
	 *******************************************************************/

	public function profile_update( $user_id, $old_user_data ) {
		$user = get_user_by( 'ID', $user_id );

		if ( $old_user_data->user_email !== $user->user_email ) {
			$this->set_user_identifier( $user->user_email, $user_id );
		}
	}

	public function init_settings_definition() {
		$this->settings_fields = array(
			'main' => array(
				array(
					'id'      => 'subject',
					'label'   => __( 'Email Subject', 'otpa' ),
					'type'    => 'input_text',
					'class'   => 'regular-text',
					'default' => self::get_default_subject(),
					'help'    => __( 'The subject of the email containing the One-Time Password. If left empty, a default value will be used.', 'otpa' ),
				),
				array(
					'id'      => 'message',
					'label'   => __( 'Email Message', 'otpa' ),
					'type'    => 'textarea',
					'class'   => 'large-text code',
					'rows'    => 10,
					'cols'    => 50,
					'default' => self::get_default_message(),
					'help'    => __( 'The content of the email sent to users. Value keys between ### will be replaced dynamically. List of dynamic value keys: USERNAME, SITENAME, EMAIL, CODE, SITEURL. If left empty, a default value will be used.', 'otpa' ),
				),
				// translators: %s is the Gateway name
				'title' => sprintf( __( '%s Gateway Settings', 'otpa' ), $this->name ),
			),
		);

		$this->settings_fields = apply_filters( 'otpa_settings_fields_' . $this->get_gateway_id(), $this->settings_fields );
	}

	public function sanitize_settings( $settings, $old_settings = array() ) {
		$settings = parent::sanitize_settings( $settings, $old_settings );

		if ( empty( $settings['subject'] ) ) {
			$settings['subject'] = self::get_default_subject();
		}

		if ( empty( $settings['message'] ) ) {
			$settings['message'] = self::get_default_message();
		}

		return apply_filters( $this->get_gateway_id() . '_sanitize_settings', $settings );
	}

	public function set_error( $wp_error ) {
		$this->error      = $wp_error->get_error_data( 'phpmailer_exception_code' ) . ': ' . $wp_error->get_error_message();
		$this->error_code = $wp_error->get_error_code();
	}

	public function print_form_hint( $otp_form_type ) {
		$output = '<p class="message">';

		if ( is_user_logged_in() ) {
			$output .= sprintf(
			// translators: %s is the masked email address
				__( 'Enter your email address %s to request a Verification Code.', 'otpa' ),
				'<br/><strong>' . otpa_mask_email( $this->get_user_identifier() ) . '</strong><br/>'
			);
		} else {
			$output .= __( 'Enter your email address to request a Verification Code.', 'otpa' );
		}

		$output .= '</p>';

		echo $output; // @codingStandardsIgnoreLine
	}

	public function otp_widget_identifier_placeholder( $placeholder ) {

		return __( 'Email', 'otpa' );
	}

	public function get_user_identifier( $user_id = false ) {
		$email = parent::get_user_identifier( $user_id );

		if ( ! $email ) {

			if ( ! $user_id ) {
				$user = wp_get_current_user();
			} else {
				$user = get_user_by( 'ID', $user_id );
			}

			$email = $this->set_user_identifier( $user->user_email );
		}

		return $email;
	}

	public function sanitize_user_identifier( $email, $user_id = false ) {
		return trim( $email );
	}

	public function is_valid_identifier( $email ) {
		return filter_var( $email, FILTER_VALIDATE_EMAIL );
	}

	/*******************************************************************
	 * Protected methods
	 *******************************************************************/

	protected static function get_default_settings() {

		return array(
			'subject' => self::get_default_subject(),
			'message' => self::get_default_message(),
		);
	}

	protected static function get_default_subject() {
		return '[' . get_option( 'blogname' ) . ']' . __( ' OTP Verification Code', 'otpa' );
	}

	protected static function get_default_message() {
		/* translators: Do not translate USERNAME, SITENAME, EMAIL, CODE, SITEURL: those are placeholders. */
		$message = __(
			'Hi ###USERNAME###,

			A One-Time Password Verification Code was requested on ###SITENAME###.

			Your OTP Verification Code is:

			-----------
			###CODE###
			-----------

			If you did not request this Verification Code, please ignore this email.

			This email has been sent to ###EMAIL###.

			Regards,
			All at ###SITENAME###
			###SITEURL###'
		);

		return str_replace( "\t", '', $message );
	}

	protected function add_otp_widget_scripts( $css = true, $js = true ) {
		$debug   = apply_filters( 'otpa_debug', (bool) ( constant( 'WP_DEBUG' ) ) );
		$css_ext = ( $debug ) ? '.css' : '.min.css';

		parent::add_otp_widget_scripts( $css, $js );
		$this->add_otp_widget_css( $css_ext, $debug );
	}

	protected function validate_input_identifier( $email ) {

		if ( ! parent::validate_input_identifier( $email ) ) {

			return new WP_Error(
				'OTPA_INVALID_EMAIL',
				__( 'Invalid email address. Please enter your account email address and try again.' ),
				array(
					'method'     => __METHOD__,
					'identifier' => $email,
				)
			);
		}

		return true;
	}

	protected function build_message( $email, $otp_code ) {
		$user     = wp_get_current_user();
		$template = $this->get_option( 'message', $this->get_default_message() );
		$message  = str_replace( '###USERNAME###', $user->display_name, $template );
		$message  = str_replace( '###SITENAME###', get_option( 'blogname' ), $message );
		$message  = str_replace( '###EMAIL###', $email, $message );
		$message  = str_replace( '###CODE###', $otp_code, $message );
		$message  = str_replace( '###SITEURL###', home_url(), $message );

		return $message;
	}

	protected function send_sandox_request( $email, $message ) {

		otpa_db_log(
			array(
				'message' => __( 'Sandbox simulated request - data sent to the Authentication Gateway: ', 'otpa' ),
				'data'    => array(
					'email'   => $email,
					'subject' => $this->settings['subject'],
					'content' => $message,
				),
			)
		);

		return array(
			'status'  => true,
			// translators: %s is the user's OTP identifier
			'message' => sprintf( __( 'An email with a Verification Code was sent to %s (sandbox).', 'otpa' ), $email ),
			'code'    => 'OK',
		);
	}

	protected function send_request( $email, $message ) {
		add_action( 'wp_mail_failed', array( $this, 'set_error' ), 10, 1 );

		$result = wp_mail( $email, $this->settings['subject'], $message );

		remove_action( 'wp_mail_failed', array( $this, 'set_error' ), 10 );

		return array(
			'status'  => $result,
			'message' => ( $this->error ) ? $this->error : sprintf(
				// translators: %s is the user's email address
				__( 'An email with a Verification Code was sent to %s.', 'otpa' ),
				$email
			),
			'code'    => ( $this->error_code ) ? $this->error_code : 'OK',
		);
	}
}
