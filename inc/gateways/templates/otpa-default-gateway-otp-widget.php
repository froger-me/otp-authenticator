<?php if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

?>
<div class="otpa-button-field">
	<input id="otpa_id_widget" type="text" placeholder="<?php echo esc_html( apply_filters( 'otpa_otp_widget_identifier_placeholder', __( 'Identifier', 'otpa' ) ) ); ?>"/><button disabled="disabled" id="otpa_send_code"><span class="dashicons dashicons-upload"></span></button>
</div>
<input id="otpa_code_widget" type="text" placeholder="<?php echo esc_html( apply_filters( 'otpa_otp_widget_code_placeholder', __( 'Code', 'otpa' ) ) ); ?>"/>
