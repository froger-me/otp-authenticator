<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class Otpa_Settings_Renderer {

	protected $settings_fields;
	protected $style_dependencies  = array();
	protected $script_dependencies = array();

	public function __construct() {}

	/*******************************************************************
	 * Public methods
	 *******************************************************************/

	public function __call( $method_name, $args ) {

		if ( false !== strpos( $method_name, Otpa_Settings::get_current_gateway_id() ) ) {
			$gateway_id     = Otpa_Settings::get_current_gateway_id();
			$settings_group = $gateway_id . '_settings';
			$field_key      = str_replace( $gateway_id . '_', '', str_replace( '_render', '', $method_name ) );
			$this->settings = Otpa_Abstract_Gateway::get_gateway_options( $gateway_id );
		} elseif ( false !== strpos( $method_name, 'otpa_style' ) ) {
			$settings_group = 'otpa_style_settings';
			$field_key      = str_replace( 'otpa_style_', '', str_replace( '_render', '', $method_name ) );
			$this->settings = Otpa_Style_Settings::get_options();
		} else {
			$settings_group = 'otpa_settings';
			$field_key      = str_replace( 'otpa_', '', str_replace( '_render', '', $method_name ) );
			$this->settings = Otpa_Settings::get_options();
		}

		$is_field_render     = ( strpos( $method_name, 'otpa_' ) !== false );
		$is_field_render     = $is_field_render && ( strpos( $method_name, '_render' ) !== false );
		$is_field_render     = $is_field_render && $this->get_field_attr( $field_key, 'id' );
		$section_key         = str_replace( '_settings_section_callback', '', $method_name );
		$is_section_callback = ( strpos( $method_name, '_settings_section_callback' ) !== false );
		$is_section_callback = $is_section_callback && isset( $this->settings_fields[ $section_key ] );

		if ( $is_field_render ) {
			call_user_func_array( array( $this, 'field_render' ), array( $field_key, $settings_group ) );
		} elseif ( $is_section_callback ) {
			call_user_func_array( array( $this, 'section_render' ), array( $section_key ) );
		} else {
			trigger_error( 'Call to undefined method ' . __CLASS__ . '::' . esc_html( $method_name ) . '()', E_USER_ERROR ); // @codingStandardsIgnoreLine
		}
	}

	public function register_settings( $settings_fields, $page ) {
		$this->settings_fields = $settings_fields;

		foreach ( $this->settings_fields as $section_name => $section ) {
			$title = isset( $section['title'] ) ? $section['title'] : '';
			add_settings_section(
				'otpa_' . $section_name . '_section',
				$title,
				array( $this, $section_name . '_settings_section_callback' ),
				$page
			);

			foreach ( $section as $field ) {

				if ( is_array( $field ) ) {
					$id          = 'otpa_' . $field['id'];
					$title       = $field['label'];
					$section_key = 'otpa_' . $section_name . '_section';
					$class       = 'otpa-' . $section_name . '-section otpa-' . $field['id'] . '-field';

					if (
						isset( $section['class'] ) && false !== strpos( $section['class'], 'hidden' ) ||
						isset( $field['class'] ) && false !== strpos( $field['class'], 'hidden' )
					) {
						$class .= ' hidden';
					}

					if ( isset( $field['script_dependencies'] ) && is_array( $field['script_dependencies'] ) ) {
						$this->script_dependencies = array_merge( $this->script_dependencies, $field['script_dependencies'] );
					}

					if ( isset( $field['style_dependencies'] ) && is_array( $field['style_dependencies'] ) ) {
						$this->style_dependencies = array_merge( $this->style_dependencies, $field['style_dependencies'] );
					}

					if ( isset( $field['type'] ) && false !== strpos( $field['type'], 'wp_media' ) ) {
						$this->script_dependencies[] = 'wp_media';
					}

					$args     = array( 'class' => $class );
					$callback = apply_filters(
						'otpa_settings_renderer_field_callback',
						array( $this, $page . '_' . $field['id'] . '_render' ),
						$page,
						$section_name,
						$field
					);

					add_settings_field( $id, $title, $callback, $page, $section_key, $args );
				}
			}
		}

		add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ), 10, 1 );
	}

	public function admin_enqueue_scripts( $hook_suffix ) {
		$debug       = apply_filters( 'otpa_debug', (bool) ( constant( 'WP_DEBUG' ) ) );
		$js_ext      = ( $debug ) ? '.js' : '.min.js';
		$css_ext     = ( $debug ) ? '.css' : '.min.css';
		$version_js  = filemtime( OTPA_PLUGIN_PATH . 'js/admin/settings-renderer' . $js_ext );
		$version_css = filemtime( OTPA_PLUGIN_PATH . 'css/admin/settings-renderer' . $css_ext );

		if ( ! empty( $this->style_dependencies ) ) {

			foreach ( $this->style_dependencies as $handle ) {
				wp_enqueue_style( $handle );
			}
		}

		wp_enqueue_style(
			'otpa-settings-renderer-style',
			OTPA_PLUGIN_URL . 'css/admin/settings-renderer' . $css_ext,
			array(),
			$version_css
		);

		if ( ! empty( $this->script_dependencies ) ) {

			foreach ( $this->script_dependencies as $handle ) {

				if ( 'wp_media' === $handle ) {
					wp_enqueue_media();
				} else {
					wp_enqueue_script( $handle );
				}
			}
		}

		wp_enqueue_script(
			'otpa-settings-renderer-script',
			OTPA_PLUGIN_URL . 'js/admin/settings-renderer' . $js_ext,
			array( 'jquery' ),
			$version_js,
			true
		);
	}

	/*******************************************************************
	 * Protected methods
	 *******************************************************************/

	protected function get_input_text_option( $key, $settings_group ) {
		$value  = isset( $this->settings[ $key ] ) ? $this->settings[ $key ] : $this->get_field_attr( $key, 'default' );
		$input  = '<input type="text" id="otpa_' . $key . '" name="' . $settings_group . '[' . $key . ']" value="';
		$input .= $value;
		$input .= '"' . $this->get_field_class( $key ) . '>';

		return $input;
	}

	protected function get_textarea_option( $key, $settings_group ) {
		$value  = isset( $this->settings[ $key ] ) ? $this->settings[ $key ] : $this->get_field_attr( $key, 'default' );
		$rows   = $this->get_field_attr( $key, 'rows' );
		$cols   = $this->get_field_attr( $key, 'cols' );
		$input  = '<textarea id="otpa_' . $key . '" name="' . $settings_group . '[' . $key . ']" ';
		$input .= ( ! empty( $rows ) ) ? ' rows="' . $rows . '" ' : '';
		$input .= ( ! empty( $cols ) ) ? ' cols="' . $cols . '" ' : '';
		$input .= $this->get_field_class( $key ) . ' >';
		$input .= $value;
		$input .= '</textarea>';

		return $input;
	}

	protected function get_input_number_option( $key, $settings_group ) {
		$value  = isset( $this->settings[ $key ] ) ? $this->settings[ $key ] : $this->get_field_attr( $key, 'default' );
		$min    = $this->get_field_attr( $key, 'min' );
		$max    = $this->get_field_attr( $key, 'max' );
		$step   = $this->get_field_attr( $key, 'step' );
		$input  = '<input type="number" id="otpa_' . $key . '" name="' . $settings_group . '[' . $key . ']" value="';
		$input .= $value;
		$input .= '"' . $this->get_field_class( $key );
		$input .= ( 0 === $min || ! empty( $min ) ) ? ' min="' . $min . '"' : '';
		$input .= ( 0 === $max || ! empty( $max ) ) ? ' max="' . $min . '"' : '';
		$input .= ( ! empty( $step ) ) ? ' step="' . $step . '"' : '';
		$input .= '>';

		return $input;
	}

	protected function get_input_checkbox_option( $key, $settings_group ) {
		$checked = '';

		if ( isset( $this->settings[ $key ] ) && $this->settings[ $key ] ) {
			$checked = 'checked';
		}

		$input  = '<input type="checkbox" id="otpa_' . $key . '" name="' . $settings_group . '[' . $key . ']" value="1" ';
		$input .= $checked;
		$input .= $this->get_field_class( $key ) . '>';

		return $input;
	}

	protected function get_checkbox_group_option( $key, $settings_group ) {
		$values = $this->get_field_attr( $key, 'value' );
		$input  = '<div ' . $this->get_field_class( $key ) . '>';

		if ( $this->get_field_attr( $key, 'sort' ) ) {

			if ( 'asc' === $this->get_field_attr( $key, 'sort' ) ) {
				asort( $values );
			} else {
				arsort( $values );
			}
		}

		foreach ( $values as $option => $option_value ) {
			$checked = '';

			if ( is_array( $this->settings[ $key ] ) && in_array( $option, $this->settings[ $key ], true ) ) {
				$checked = 'checked';
			}

			$input .= '<div><input type="checkbox" id="otpa_' . $key . '-' . $option . '"';
			$input .= ' name="' . $settings_group . '[' . $key . '][]" value="' . $option . '" ';
			$input .= $checked;
			$input .= '> <label for="otpa_' . $key . '-' . $option . '">' . $option_value . '</label></div>';
		}

		$input .= '</div>';

		return $input;
	}

	protected function get_input_select_option( $key, $settings_group ) {
		$values    = $this->get_field_attr( $key, 'value' );
		$input     = '<select id="otpa_' . $key . '" name="' . $settings_group . '[' . $key . ']"';
		$input    .= $this->get_field_class( $key ) . '>';
		$has_value = isset( $this->settings[ $key ] ) && ! empty( $this->settings[ $key ] );

		if ( $this->get_field_attr( $key, 'sort' ) ) {

			if ( 'asc' === $this->get_field_attr( $key, 'sort' ) ) {
				asort( $values );
			} else {
				arsort( $values );
			}
		}

		if ( ! empty( $values ) ) {

			foreach ( $values as $option_value => $option ) {

				if ( $has_value ) {
					$condition = isset( $this->settings[ $key ] ) && $this->settings[ $key ] === (string) $option_value;
				} else {
					$condition = $this->get_field_attr( $key, 'default' ) === (string) $option_value;
				}

				$selected = ( $condition ) ? ' selected' : '';
				$input   .= '<option value="' . $option_value . '"' . $selected . '>' . $option . '</option>';
			}
		}

		$input .= '</select>';

		return $input;
	}

	protected function get_input_password_option( $key, $settings_group ) {
		$value  = isset( $this->settings[ $key ] ) ? $this->settings[ $key ] : $this->get_field_attr( $key, 'default' );
		$input  = '<input type="password" id="otpa_' . $key . '"';
		$input .= ' name="' . $settings_group . '[' . $key . ']" value="';
		$input .= $value;
		$input .= '"' . $this->get_field_class( $key ) . '>';

		return $input;
	}

	protected function get_raw_text_option( $key, $settings_group ) {
		$paragraph  = '<p' . $this->get_field_class( $key ) . ' id="otpa_' . $key . '">';
		$paragraph .= $this->get_field_attr( $key, 'value' ) . '</p>';

		return $paragraph;
	}

	protected function get_color_picker_option( $key, $settings_group ) {
		$value  = isset( $this->settings[ $key ] ) ? $this->settings[ $key ] : $this->get_field_attr( $key, 'default' );
		$input  = '<input type="text" id="otpa_' . $key . '" name="' . $settings_group . '[' . $key . ']" value="';
		$input .= $value;
		$input .= '"' . $this->get_field_class( $key, 'otpa-color-picker' ) . '>';

		return $input;
	}

	protected function get_wp_media_image_option( $key, $settings_group ) {

		if ( isset( $this->settings[ $key ] ) && filter_var( $this->settings[ $key ], FILTER_VALIDATE_URL ) ) {
			$value = $this->settings[ $key ];
		} else {
			$value = $this->get_field_attr( $key, 'default' );
		}

		$remove_class    = empty( $value ) ? ' hidden' : '';
		$container_class = empty( $value ) ? ' empty' : '';
		$input           = '<div class="otpa-style-preview-image-container' . $container_class . '">';
		$input          .= '<img class="otpa-style-preview-image" src=" ' . $value . ' " /></div>';
		$input          .= '<input type="hidden" name="' . $settings_group . '[' . $key . ']" ';
		$input          .= 'id="otpa_' . $key . '" value="' . $value . '" />';
		$input          .= '<input type="button" class="button button-primary otpa-media-select" ';
		$input          .= 'value="' . __( 'Select Image', 'otpa' ) . '" class=""/>';
		$input          .= '<input type="button" class="button otpa-media-reset' . $remove_class;
		$input          .= '" value="' . __( 'Remove', 'otpa' ) . '"/>';

		return $input;
	}

	protected function field_render( $key, $settings_group ) {
		$type = $this->get_field_attr( $key, 'type' );

		if ( method_exists( $this, 'get_' . $type . '_option' ) ) {
			echo call_user_func_array( array( $this, 'get_' . $type . '_option' ), array( $key, $settings_group ) ); // @codingStandardsIgnoreLine
		} else {
			$output = apply_filters(
				'otpa_field_render',
				// translators: %1$s is the field type, %2$s is the field ID
				sprintf( __( 'Unsupported field type %1$s for field ID %2$s', 'otpa' ), $type, $key ),
				$type,
				$key,
				$settings_group,
				$this->settings_fields,
				$this->settings
			);

			echo $output; // @codingStandardsIgnoreLine
		}

		if ( $this->get_field_attr( $key, 'help' ) ) {
			echo '<p class="description">' . $this->get_field_attr( $key, 'help' ) . '</p>'; // @codingStandardsIgnoreLine
		}
	}

	protected function section_render( $key ) {
		$hide   = isset( $this->settings_fields[ $key ]['class'] );
		$hide   = $hide && false !== strpos( $this->settings_fields[ $key ]['class'], 'hidden' );
		$class  = 'otpa-' . $key . '-description';
		$class .= ( $hide ) ? ' hidden' : '';

		if ( isset( $this->settings_fields[ $key ]['class'] ) ) {
			$output  = '<span class="section-class-holder hidden" data-section_class="';
			$output .= $this->settings_fields[ $key ]['class'];
			$output .= '"></span>';

			echo $output; // @codingStandardsIgnoreLine
		}

		if ( isset( $this->settings_fields[ $key ]['description'] ) ) {
			echo '<div class="' . $class . '">' . $this->settings_fields[ $key ]['description'] . '</div>'; // @codingStandardsIgnoreLine
		}
	}

	protected function get_field_class( $key, $classes = '' ) {
		$class = $this->get_field_attr( $key, 'class' );
		$class = empty( $class ) && empty( $classes ) ? ' ' : ' class="' . $class . ' ' . $classes . '" ';

		return $class;
	}

	protected function get_field_attr( $key, $attr ) {

		foreach ( $this->settings_fields as $section ) {

			foreach ( $section as $field ) {

				if ( is_array( $field ) && $field['id'] === $key && isset( $field[ $attr ] ) ) {

					return $field[ $attr ];
				}
			}
		}

		return false;
	}

}
