<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class Otpa_Logger {
	const DEFAULT_MIN_LOG = 100;

	public static $log_settings;
	public static $log_types;

	protected static $settings_class;

	protected $settings;

	public function __construct( $init_hooks = false ) {
		self::$log_types = array(
			'info'    => __( 'Info', 'otpa' ),
			'success' => __( 'Success', 'otpa' ),
			'warning' => __( 'Warning', 'otpa' ),
			'alert'   => __( 'Alert', 'otpa' ),
		);

		if ( $init_hooks ) {
			add_action( 'wp', array( get_class(), 'register_logs_cleanup' ) );
			add_action( 'otpa_logs_cleanup', array( get_class(), 'clear_logs' ) );
			add_action( 'wp_ajax_otpa_refresh_logs', array( $this, 'refresh_logs_async' ), 10, 0 );
			add_action( 'wp_ajax_otpa_clear_logs', array( $this, 'clear_logs_async' ), 10, 0 );
			add_action( 'admin_menu', array( $this, 'register_settings' ), 10, 0 );
			add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ), 10, 1 );
			add_action( 'otpa_after_main_settings', array( $this, 'print_logs_settings' ), 10, 1 );
			add_action( 'otpa_after_main_tab_settings', array( $this, 'print_logs_settings_tab' ), 10, 1 );
			add_action( 'otpa_wp_error', array( $this, 'log_otpa_wp_error' ), 10, 1 );
		}

		self::init();
	}

	/*******************************************************************
	 * Public methods
	 *******************************************************************/

	public function log_otpa_wp_error( $wp_error ) {
		$data  = $wp_error->get_error_data();
		$type  = ( isset( $data['force_log'] ) ) ? 'alert' : 'warning';
		$force = isset( $data['force_log'] ) || apply_filters( 'otpa_debug', (bool) ( constant( 'WP_DEBUG' ) ) );
		$data  = array(
			'message' => $wp_error->get_error_code() . ': ' . $wp_error->get_error_message(),
			'data'    => $wp_error->get_error_data(),
		);

		unset( $data['data']['force_log'] );
		self::log(
			$data,
			$type,
			'db_log',
			$force
		);
	}

	public static function install() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = '';

		if ( ! empty( $wpdb->charset ) ) {
			$charset_collate = "DEFAULT CHARACTER SET {$wpdb->charset}";
		}

		if ( ! empty( $wpdb->collate ) ) {
			$charset_collate .= " COLLATE {$wpdb->collate}";
		}

		$table_name = $wpdb->prefix . 'otpa_logs';
		$sql        =
			'CREATE TABLE ' . $table_name . ' (
				id int(12) NOT NULL auto_increment,
				timestamp int(12) NOT NULL,
				type varchar(10) NOT NULL,
				message text NOT NULL,
				data text,
				PRIMARY KEY (id),
				KEY timestamp (timestamp)
			)' . $charset_collate . ';';

		dbDelta( $sql );

		$table_name = $wpdb->get_var( "SHOW TABLES LIKE '" . $wpdb->prefix . "otpa_logs'" );

		if ( $wpdb->prefix . 'otpa_logs' !== $table_name ) {

			return false;
		}

		return true;
	}

	public static function register_logs_cleanup() {

		if ( ! wp_next_scheduled( 'otpa_logs_cleanup' ) ) {
			wp_schedule_event( time(), 'hourly', 'otpa_logs_cleanup' );
		}
	}

	public static function log( $expression, $extend = '', $destination = 'error_log', $force = false ) {
		$destination = ( 'dblog' === $destination ) ? 'db_log' : $destination;

		if ( method_exists( get_class(), $destination ) ) {
			call_user_func_array( array( get_class(), $destination ), array( $expression, $extend, $force ) );
		} else {
			self::error_log( $expression, $extend, $force );
		}
	}

	public static function clear_logs( $force = false ) {

		if ( defined( 'WP_SETUP_CONFIG' ) || defined( 'WP_INSTALLING' ) ) {

			return;
		}

		global $wpdb;

		$result = $wpdb->query(
			$wpdb->prepare( "
				DELETE FROM {$wpdb->prefix}otpa_logs
				WHERE id <= (
					SELECT id
					FROM (
						SELECT id
						FROM {$wpdb->prefix}otpa_logs
						ORDER BY id DESC
						LIMIT 1 OFFSET %d
						) temp
					);",
				self::$log_settings['min_num']
			)
		);

		return (bool) $result;
	}

	public static function get_logs_count() {
		global $wpdb;

		$count = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}otpa_logs WHERE 1 = 1;" );

		return absint( $count );
	}

	public static function get_logs() {
		global $wpdb;

		$logs = '';
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}otpa_logs ORDER BY timestamp ASC LIMIT %d;",
				self::$log_settings['min_num']
			)
		);

		if ( ! empty( $rows ) ) {

			foreach ( $rows as $log ) {
				$type_output = self::$log_types[ $log->type ];

				ob_start();

				include apply_filters(
					'otpa_template_log-row', // @codingStandardsIgnoreLine
					OTPA_PLUGIN_PATH . 'inc/templates/admin/log-row.php'
				);

				$logs .= ob_get_clean();
			}
		}

		return $logs;
	}

	public function refresh_logs_async() {
		wp_send_json_success(
			array(
				'html'               => self::get_logs(),
				'clean_trigger_text' => sprintf(
					// translators: %d is the current number of log entries
					__( 'Clear All (%d entries)', 'otpa' ),
					self::get_logs_count()
				),
			)
		);
	}

	public function clear_logs_async() {
		global $wpdb;

		$wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}otpa_logs;" );
	}

	public function init() {
		self::$log_settings = get_option(
			'otpa_logs_settings',
			array(
				'enable'  => false,
				'min_num' => 100,
			)
		);
	}

	public function print_logs_settings( $active_tab ) {
		$num_logs      = self::get_logs_count();
		$logs          = self::get_logs();
		$logs_settings = self::$log_settings;

		do_action( 'otpa_logs_settings_page', $active_tab );
		ob_start();

		require_once apply_filters(
			'otpa_template_logs-settings-page', // @codingStandardsIgnoreLine
			OTPA_PLUGIN_PATH . 'inc/templates/admin/logs-settings-page.php'
		);

		echo ob_get_clean(); // @codingStandardsIgnoreLine
	}

	public function print_logs_settings_tab( $active_tab ) {
		do_action( 'otpa_logs_settings_tab', $active_tab );
		ob_start();

		require_once apply_filters(
			'otpa_template_logs-settings-tab', // @codingStandardsIgnoreLine
			OTPA_PLUGIN_PATH . 'inc/templates/admin/logs-settings-tab.php'
		);

		echo ob_get_clean(); // @codingStandardsIgnoreLine
	}

	public function register_settings() {
		register_setting(
			'otpa_logs',
			'otpa_logs_settings'
		);
	}

	public function admin_enqueue_scripts( $hook_suffix ) {

		if ( 'settings_page_otpa' === $hook_suffix ) {
			$active_tab = filter_input( INPUT_GET, 'tab', FILTER_SANITIZE_STRING );

			if ( 'logs-settings' !== $active_tab ) {

				return;
			}

			$debug         = apply_filters( 'otpa_debug', (bool) ( constant( 'WP_DEBUG' ) ) );
			$script_params = array(
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'debug'    => $debug,
			);
			$key           = 'otpa-logs-script';
			$js_ext        = ( $debug ) ? '.js' : '.min.js';
			$version       = filemtime( OTPA_PLUGIN_PATH . 'js/admin/logs' . $js_ext );

			wp_enqueue_script( 'otpa-logs-script', OTPA_PLUGIN_URL . 'js/admin/logs' . $js_ext, array( 'jquery' ), $version, true );
			wp_localize_script( $key, 'OTPA', $script_params );
		}
	}

	/*******************************************************************
	 * Protected methods
	 *******************************************************************/

	protected static function db_log( $expression, $type, $force = false ) {

		if ( ! self::$log_settings['enable'] && ! $force ) {

			return false;
		}

		global $wpdb;

		$data = null;

		if ( ! is_string( $expression ) ) {

			if ( is_array( $expression ) ) {

				if ( isset( $expression['message'] ) ) {
					$message = $expression['message'];

					unset( $expression['message'] );
				}
			}

			if ( empty( $message ) ) {
				$message = __( '(No message)', 'otpa' );
			}

			if ( is_array( $expression ) && 1 >= count( $expression ) && isset( $expression['data'] ) ) {
				$data = $expression['data'];
			} else {
				$data = $expression;
			}
		} else {
			$message = $expression;
		}

		$log = array(
			'timestamp' => time(),
			'type'      => ( ! in_array( $type, array_keys( self::$log_types ), true ) ) ? 'info' : $type,
			'message'   => $message,
			'data'      => maybe_serialize( $data ),
		);

		$result = $wpdb->insert(
			$wpdb->prefix . 'otpa_logs',
			$log
		);

		if ( (bool) $result ) {

			return $log;
		}

		return false;
	}

	protected static function error_log( $expression, $extend_context = '', $force = false ) {

		if ( ! is_string( $expression ) ) {
			$alternatives = array(
				array(
					'func' => 'print_r',
					'args' => array( $expression, true ),
				),
				array(
					'func' => 'var_export',
					'args' => array( $expression, true ),
				),
				array(
					'func' => 'json_encode',
					'args' => array( $expression ),
				),
				array(
					'func' => 'serialize',
					'args' => array( $expression ),
				),
			);

			foreach ( $alternatives as $alternative ) {

				if ( function_exists( $alternative['func'] ) ) {
					$expression = call_user_func_array( $alternative['func'], $alternative['args'] );

					break;
				}
			}
		}

		$extend_context      = ( $extend_context ) ? ' - ' . $extend_context : '';
		$trace               = debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 3 ); // @codingStandardsIgnoreLine
		$caller_line_holder  = $trace[1];
		$caller_class_holder = $trace[2];
		$class               = isset( $caller_class_holder['class'] ) ? $caller_class_holder['class'] : '';
		$type                = isset( $caller_class_holder['type'] ) ? $caller_class_holder['type'] : '';
		$function            = isset( $caller_class_holder['function'] ) ? $caller_class_holder['function'] : '';
		$context             = $class . $type . $function . ' on line ' . $caller_line_holder['line'] . $extend_context . ': ';

		error_log( $context . $expression ); // @codingStandardsIgnoreLine
	}

}
