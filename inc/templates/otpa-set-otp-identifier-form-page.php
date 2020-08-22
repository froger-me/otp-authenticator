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
				<div id="otpa_otp_form" data-otp_form_type="<?php echo esc_attr( $otp_form_type ); ?>" data-handler="set_otp_identifier" class="otpa-form">
					<h1><?php echo $otp_form_title; // @codingStandardsIgnoreLine ?></h1>
					<?php do_action( 'otpa_before_set_otp_form', $otp_form_type ); ?>
					<form>
						<?php wp_nonce_field( 'otpa_nonce', 'otpa_nonce' ); ?>
						<p class="message">
							<?php esc_html_e( 'A One Time Password Identifier is required.', 'otpa' ); ?>
						</p>
						<?php if ( $otp_2fa_enabled ) : ?>
						<p class="message align-left">
							<strong>
								<?php
									printf(
										// translators: %s is the OTP Identifier label
										esc_html( __( 'Warning! This %s will be required to access the website after login at your next visit.', 'otpa' ) ),
										esc_html(
											apply_filters(
												'otpa_otp_identifier_field_label',
												__( 'OTP Identifier', 'otpa' )
											)
										)
									);
								?>
							</strong>
						</p>
						<p class="message align-left">
							<strong><?php esc_html_e( 'If it is invalid, you will be unable to access the website while logged in.', 'otpa' ); ?></strong>
						</p>
						<?php else : ?>
						<p class="message align-left">
							<strong>
								<?php
									printf(
										// translators: %s is the OTP Identifier label
										esc_html( __( 'Warning! This %s will be used to validate your account immediately after submitting this form.', 'otpa' ) ),
										esc_html(
											apply_filters(
												'otpa_otp_identifier_field_label',
												__( 'OTP Identifier', 'otpa' )
											)
										)
									);
								?>
							</strong>
						</p>
						<p class="message align-left">
							<strong><?php esc_html_e( 'If it is invalid, you will be unable to validate your account, and unable to access the website while logged in.', 'otpa' ); ?></strong>
						</p>
						<?php endif; ?>
						<p class="message">
							<?php esc_html_e( 'Make sure it is correct before saving.', 'otpa' ); ?>
						</p>
						<?php do_action( 'otpa_before_set_otp_input', $otp_form_type ); ?>
							<input id="otpa_set_id_input" type="text" placeholder="<?php echo esc_html( apply_filters( 'otpa_otp_identifier_field_label', __( 'Identifier', 'otpa' ) ) ); ?>"/>
						<?php do_action( 'otpa_after_set_otp_input', $otp_form_type ); ?>
						<p id="otpa_result" class="message result"></p>
						<span id="otpa_unknown_result" class="hidden"><?php esc_html_e( 'An undefined error occured.', 'otpa' ); ?><br/><?php esc_html_e( 'Make sure you have a working internet connection and try again. If the problem persists, please contact an administrator.', 'otpa' ); ?></span>
						<?php do_action( 'otpa_before_set_otp_submit_button', $otp_form_type ); ?>
						<button disabled="disabled" id="otpa_submit" class="submit"><?php esc_html_e( 'Save', 'otpa' ); ?></button>
						<?php do_action( 'otpa_after_set_otp_submit_button', $otp_form_type ); ?>
						<input type="hidden" id="otpa_id">
						<p class="message footer">
							<?php do_action( 'otpa_before_set_otp_footer_message', $otp_form_type ); ?>
							<?php echo $otp_footer_message; // @codingStandardsIgnoreLine ?>
							<?php do_action( 'otpa_after_set_otp_footer_message', $otp_form_type ); ?>
						</p>
					</form>
					<?php do_action( 'otpa_after_set_otp_form', $otp_form_type ); ?>
				</div>
			</div>
		</div>
		<?php wp_footer(); ?>
	</body>
</html>
