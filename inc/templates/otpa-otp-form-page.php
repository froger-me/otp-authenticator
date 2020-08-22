<?php if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

?>
<!doctype html>
<html <?php language_attributes(); ?>>
	<head>
		<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1.0, user-scalable=no">
		<?php wp_head(); ?>
	</head>
	<body class="otpa-<?php echo esc_attr( str_replace( '_', '-', $otp_form_type ) ); ?> otpa-page">
		<div class="otpa-inner">
			<div class="otpa-wrapper">
				<div id="otpa_logo" class="otpa-logo" data-otp_logo_url="<?php echo esc_url( $otp_logo_url ); ?>"></div>
				<div id="otpa_otp_form" data-otp_form_type="<?php echo esc_attr( $otp_form_type ); ?>" data-handler="verify_otp_code" class="otpa-form">
					<h1><?php echo esc_html( $otp_form_title ); ?></h1>
					<?php do_action( 'otpa_before_otp_form', $otp_form_type ); ?>
					<form>
						<?php wp_nonce_field( 'otpa_nonce', 'otpa_nonce' ); ?>
						<?php do_action( 'otpa_before_otp_widget', $otp_form_type ); ?>
						<div class='otpa-widget'>
							<?php echo $otp_widget; // @codingStandardsIgnoreLine ?>
						</div>
						<?php do_action( 'otpa_after_otp_widget', $otp_form_type ); ?>
						<p id="otpa_result" class="message result"></p>
						<span id="otpa_unknown_result" class="hidden"><?php esc_html_e( 'An undefined error occured.', 'otpa' ); ?><br/><?php esc_html_e( 'Make sure you have a working internet connection and try again. If the problem persists, please contact an administrator.', 'otpa' ); ?></span>
						<?php do_action( 'otpa_before_otp_submit_button', $otp_form_type ); ?>
						<button disabled="disabled" id="otpa_submit" class="submit"><?php esc_html_e( 'Submit', 'otpa' ); ?></button>
						<?php do_action( 'otpa_after_otp_submit_button', $otp_form_type ); ?>
						<input type="hidden" id="otpa_id">
						<input type="hidden" id="otpa_code">
						<p class="message footer">
							<?php do_action( 'otpa_before_otp_footer_message', $otp_form_type ); ?>
							<?php echo $otp_footer_message; // @codingStandardsIgnoreLine ?>
							<?php do_action( 'otpa_after_otp_footer_message', $otp_form_type ); ?>
						</p>
					</form>
					<?php do_action( 'otpa_after_otp_form', $otp_form_type ); ?>
				</div>
			</div>
		</div>
		<?php wp_footer(); ?>
	</body>
</html>
