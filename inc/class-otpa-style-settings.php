<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class Otpa_Style_Settings {
	protected static $settings;

	protected $settings_renderer;
	protected $settings_fields;
	protected $otpa;

	public function __construct( $otpa = false, $settings_renderer = false, $init_hooks = false ) {
		self::$settings          = $this->sanitize_settings( self::get_options() );
		$this->settings_renderer = $settings_renderer;
		$this->otpa              = $otpa;

		if ( $init_hooks ) {
			add_action( 'wp_loaded', array( $this, 'init_settings_definition' ), 10, 0 );
			add_action( 'admin_menu', array( $this, 'register_style_settings' ), 10, 0 );
			add_action( 'otpa_after_main_settings', array( $this, 'print_style_settings' ), 10, 1 );
			add_action( 'otpa_after_main_tab_settings', array( $this, 'print_style_settings_tab' ), 6, 1 );
			add_filter( 'otpa_page_endpoints', array( $this, 'add_page_endpoint' ), 10, 1 );
			add_action( 'otpa_page_form_preview', array( $this, 'form_preview' ), 10, 0 );

			add_filter( 'pre_update_option_otpa_style_settings', array( get_class(), 'sanitize_settings' ), 10, 2 );
			add_filter( 'default_option_otpa_style_settings', array( $this, 'maybe_init_settings' ), 10, 3 );
		}
	}

	/*******************************************************************
	 * Public methods
	 *******************************************************************/

	public static function get_options() {
		self::$settings = wp_cache_get( 'otpa_style_settings', 'otpa' );

		if ( ! self::$settings ) {
			self::$settings = get_option( 'otpa_style_settings' );

			wp_cache_set( 'otpa_style_settings', self::$settings, 'otpa' );
		}

		self::$settings = apply_filters( 'otpa_style_settings', self::sanitize_settings( self::$settings ) );

		return self::$settings;
	}

	public static function get_option( $key, $default = false ) {
		$options = self::$settings;
		$value   = isset( $options[ $key ] ) ? $options[ $key ] : $default;

		return apply_filters( 'otpa_style_option', $value, $key );
	}

	public static function sanitize_settings( $settings, $old_settings = array() ) {

		if ( ! is_array( $settings ) ) {
			$settings = array();
		}

		$default  = self::get_default_settings();
		$settings = array_merge( $default, $settings );

		if ( empty( $settings['submit_button_background_color'] ) ) {
			$settings['submit_button_background_color'] = $default['submit_button_background_color'];
		}

		if ( empty( $settings['submit_button_text_color'] ) ) {
			$settings['submit_button_text_color'] = $default['submit_button_text_color'];
		}

		if ( empty( $settings['link_text_color'] ) ) {
			$settings['link_text_color'] = $default['link_text_color'];
		}

		return apply_filters( 'otpa_sanitize_style_settings', $settings );
	}

	public function add_page_endpoint( $endpoints ) {
		$endpoints['form_preview'] = 'form-preview';

		return $endpoints;
	}

	public function form_preview() {

		if ( current_user_can( 'manage_options' ) && $this->otpa ) {
			add_action( 'template_redirect', array( $this, 'form_preview_page' ), PHP_INT_MIN, 0 );
		}
	}

	public function form_preview_page() {
		remove_all_actions( 'wp_footer' );
		add_action( 'wp_footer', 'wp_print_footer_scripts', 20 );
		$this->otpa->add_frontend_scripts();

		$vars = array(
			'otp_widget'         => $this->otpa->get_otp_widget(),
			'otp_logo_url'       => self::get_option( 'logo_url' ),
			'otp_form_type'      => 'preview',
			'otp_form_title'     => __( 'Saved OTP Form Style', 'otpa' ),
			'otp_footer_message' => $this->get_preview_footer_markup(),
		);

		foreach ( $vars as $key => $value ) {
			set_query_var( $key, $value );
		}

		add_filter( 'template_include', array( $this, 'form_preview_template' ), 99, 1 );
	}

	public function form_preview_template( $template ) {
		$template = locate_template( 'otpa-form-preview-page.php', true );

		if ( ! $template ) {
			$template = OTPA_PLUGIN_PATH . 'inc/templates/admin/otpa-form-preview-page.php';

			load_template( $template );
		}

		return false;
	}

	public function register_style_settings() {
		register_setting(
			'otpa_style',
			'otpa_style_settings',
			array(
				'sanitize_callback' => array( get_class(), 'sanitize_settings' ),
			)
		);

		if ( is_object( $this->settings_renderer ) ) {
			$this->settings_renderer->register_settings( $this->settings_fields, 'otpa_style' );
		}
	}

	public function print_style_settings( $active_tab ) {
		do_action( 'otpa_style_settings_page', $active_tab );
		ob_start();

		require_once apply_filters(
			'otpa_template_style-settings-page', // @codingStandardsIgnoreLine
			OTPA_PLUGIN_PATH . 'inc/templates/admin/style-settings-page.php'
		);

		echo ob_get_clean(); // @codingStandardsIgnoreLine
	}

	public function print_style_settings_tab( $active_tab ) {
		do_action( 'otpa_style_settings_tab', $active_tab );
		ob_start();

		require_once apply_filters(
			'otpa_template_style-settings-tab', // @codingStandardsIgnoreLine
			OTPA_PLUGIN_PATH . 'inc/templates/admin/style-settings-tab.php'
		);

		echo ob_get_clean(); // @codingStandardsIgnoreLine
	}

	public function maybe_init_settings( $default, $option, $passed_default ) {

		if ( ! is_array( $default ) ) {
			$default = array();
		}

		remove_filter( 'default_option_otpa_style_settings', array( $this, 'maybe_init_settings' ), 10 );
		update_option( 'otpa_style_settings', $default );
		add_filter( 'default_option_otpa_style_settings', array( $this, 'maybe_init_settings' ), 10, 3 );

		return $default;
	}

	public function init_settings_definition() {
		$default_settings      = self::get_default_settings();
		$this->settings_fields = array(
			'main' => array(
				array(
					'id'    => 'logo_url',
					'label' => __( 'Logo', 'otpa' ),
					'type'  => 'wp_media_image',
					'help'  => __( 'Logo to display on the OTP Form pages.', 'otpa' ) . '<br/>' . __( 'Recommended maximum height: 75px.', 'otpa' ),
				),
				array(
					'id'                  => 'submit_button_background_color',
					'label'               => __( 'Submit Button Background Color', 'otpa' ),
					'type'                => 'color_picker',
					'default'             => '#4caf50',
					'style_dependencies'  => array( 'wp-color-picker' ),
					'script_dependencies' => array( 'wp-color-picker' ),
					'help'                => __( 'Background color of the OTP Form submit button.', 'otpa' ),
				),
				array(
					'id'                  => 'submit_button_text_color',
					'label'               => __( 'Submit Button Text Color', 'otpa' ),
					'type'                => 'color_picker',
					'default'             => '#ffffff',
					'style_dependencies'  => array( 'wp-color-picker' ),
					'script_dependencies' => array( 'wp-color-picker' ),
					'help'                => __( 'Text color of the OTP Form submit button.', 'otpa' ),
				),
				array(
					'id'                  => 'link_text_color',
					'label'               => __( 'Links Text Color', 'otpa' ),
					'type'                => 'color_picker',
					'default'             => '#21759b',
					'style_dependencies'  => array( 'wp-color-picker' ),
					'script_dependencies' => array( 'wp-color-picker' ),
					'help'                => __( 'Text color of the links displayed on the OTP Form pages.', 'otpa' ),
				),
			),
		);

		$this->settings_fields = apply_filters( 'otpa_style_settings_fields', $this->settings_fields );
	}

	/*******************************************************************
	 * Protected methods
	 *******************************************************************/

	protected static function get_default_settings() {
		$default_settings = array(
			'logo_url'                       => '',
			'submit_button_background_color' => '#4caf50',
			'submit_button_text_color'       => '#ffffff',
			'link_text_color'                => '#21759b',
		);

		return $default_settings;
	}

	protected function get_preview_footer_markup() {
		return '<a class="otpa-cancel-link" onclick="return false;"" href="#">' . __( 'Hyperlink', 'otpa' ) . '</a>';
	}

}
