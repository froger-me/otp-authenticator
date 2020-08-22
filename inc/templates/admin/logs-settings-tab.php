<?php if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

?>

<?php do_action( 'otpa_before_logs_tab_settings', $active_tab ); ?>
<a href="<?php echo esc_url( admin_url( 'options-general.php?page=otpa&tab=logs-settings' ) ); ?>" class="nav-tab<?php echo ( 'logs-settings' === $active_tab ) ? ' nav-tab-active' : ''; ?>">
	<?php esc_html_e( 'Activity Logs', 'otpa' ); ?>
</a>
<?php do_action( 'otpa_after_logs_tab_settings', $active_tab ); ?>
