<?php if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/** @var string $active_tab The active settings tab supplied by the gateway settings page. */

?>

<?php do_action( 'otpa_before_gateway_tab_settings', $active_tab ); ?>
<a href="<?php echo esc_url( admin_url( 'options-general.php?page=otpa&tab=gateway-settings' ) ); ?>" class="nav-tab<?php echo ( 'gateway-settings' === $active_tab ) ? ' nav-tab-active' : ''; ?>">
	<?php esc_html_e( 'Authentication Gateway Settings', 'otpa' ); ?>
</a>
<?php do_action( 'otpa_after_gateway_tab_settings', $active_tab ); ?>
