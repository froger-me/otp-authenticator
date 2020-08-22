<?php if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

?>
<div class="wrap">
	<h1><?php esc_html_e( 'OTP Authenticator', 'otpa' ); ?></h1>
	<h2 class="nav-tab-wrapper">
		<?php do_action( 'otpa_before_tabs_settings', $active_tab ); ?>
		<?php do_action( 'otpa_before_main_tab_settings', $active_tab ); ?>
		<a href="<?php echo esc_url( admin_url( 'options-general.php?page=otpa&tab=settings' ) ); ?>" class="nav-tab<?php echo ( 'settings' === $active_tab ) ? ' nav-tab-active' : ''; ?>">
			<?php esc_html_e( 'General Settings', 'otpa' ); ?>
		</a>
		<?php do_action( 'otpa_after_main_tab_settings', $active_tab ); ?>
		<?php do_action( 'otpa_after_tabs_settings', $active_tab ); ?>
	</h2>
	<?php do_action( 'otpa_before_settings', $active_tab ); ?>
	<?php do_action( 'otpa_before_main_settings', $active_tab ); ?>
	<?php if ( 'settings' === $active_tab ) : ?>
		<div class="stuffbox">
			<div class="inside">
				<?php do_action( 'otpa_before_main_settings_inner' ); ?>
				<form action="options.php" method="post">
					<?php settings_fields( 'otpa' ); ?>
					<?php do_settings_sections( 'otpa' ); ?>
					<?php submit_button(); ?>
				</form>
				<?php do_action( 'otpa_after_main_settings_inner' ); ?>
			</div>
		</div>
	<?php endif; ?>
	<?php do_action( 'otpa_after_main_settings', $active_tab ); ?>
	<?php do_action( 'otpa_after_settings', $active_tab ); ?>
</div>
