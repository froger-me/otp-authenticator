<?php

if ( ! defined( 'ABSPATH' ) || ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit; // Exit if accessed directly
}

global $wpdb;

$option_prefix = 'otpa';
$sql           = "DELETE FROM $wpdb->options WHERE `option_name` LIKE %s";

$wpdb->query( $wpdb->prepare( $sql, '%' . $option_prefix . '%' ) ); // @codingStandardsIgnoreLine

$meta_prefix = 'otpa';
$sql         = "DELETE FROM $wpdb->usermeta WHERE `meta_key` LIKE %s";

$wpdb->query( $wpdb->prepare( $sql, '%' . $meta_prefix . '%' ) ); // @codingStandardsIgnoreLine

wp_clear_scheduled_hook( 'otpa_logs_cleanup' );

$sql = "DROP TABLE IF EXISTS {$wpdb->prefix}otpa_logs;";

$wpdb->query( $sql ); // @codingStandardsIgnoreLine
