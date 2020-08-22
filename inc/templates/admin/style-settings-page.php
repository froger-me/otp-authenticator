<?php if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

?>

<?php do_action( 'otpa_before_style_settings', $active_tab ); ?>
<?php if ( 'style-settings' === $active_tab ) : ?>
	<div class="stuffbox otpa-style-settings">
		<div class="inside">
			<?php do_action( 'otpa_before_style_settings_inner', $active_tab ); ?>
			<div class="otpa-style-settings-form otpa-style-settings-column">
				<form action="options.php" method="post">
					<?php settings_fields( 'otpa_style' ); ?>
					<?php do_settings_sections( 'otpa_style' ); ?>
					<?php submit_button(); ?>
				</form>
			</div>
			<div class="otpa-form-settings-preview otpa-style-settings-column">
				<iframe src="<?php echo esc_url( home_url( '/otpa/form-preview' ) ); ?>"></iframe>
			</div>
			<?php do_action( 'otpa_after_style_settings_inner', $active_tab ); ?>
		</div>
	</div>
<?php endif; ?>
<?php do_action( 'otpa_after_style_settings', $active_tab ); ?>
