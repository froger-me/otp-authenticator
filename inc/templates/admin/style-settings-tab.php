<?php if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

?>

<?php do_action( 'otpa_before_style_tab_settings', $active_tab ); ?>
<a href="<?php echo esc_url( admin_url( 'options-general.php?page=otpa&tab=style-settings' ) ); ?>" class="nav-tab<?php echo ( 'style-settings' === $active_tab ) ? ' nav-tab-active' : ''; ?>">
	<?php esc_html_e( 'OTP Form Style Settings', 'otpa' ); ?>
</a>
<?php do_action( 'otpa_after_style_tab_settings', $active_tab ); ?>
