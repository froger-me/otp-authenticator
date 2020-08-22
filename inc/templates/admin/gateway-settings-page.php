<?php if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

?>

<?php do_action( 'otpa_before_gateway_settings', $active_tab ); ?>
<?php if ( 'gateway-settings' === $active_tab ) : ?>
	<div class="stuffbox <?php echo esc_attr( Otpa_Settings::get_current_gateway_id() ); ?>">
		<div class="inside">
			<?php do_action( 'otpa_before_gateway_settings_inner', $active_tab ); ?>
			<form action="options.php" method="post">
				<?php settings_fields( Otpa_Settings::get_current_gateway_id() ); ?>
				<?php do_settings_sections( Otpa_Settings::get_current_gateway_id() ); ?>
				<?php submit_button(); ?>
			</form>
			<?php do_action( 'otpa_after_gateway_settings_inner', $active_tab ); ?>
		</div>
	</div>
<?php endif; ?>
<?php do_action( 'otpa_after_gateway_settings', $active_tab ); ?>
