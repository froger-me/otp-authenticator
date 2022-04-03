<?php if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

?>
<?php if ( is_admin() && $show_admin_otp_section ) : ?>
<h3><?php echo esc_html( $title ); ?></h3>
	<?php wp_nonce_field( 'otpa_user_info_nonce', 'otpa_user_info_nonce' ); ?>
	<?php if ( $show_identifier_field ) : ?>
		<table class="form-table">
			<tr>
				<th><label for="otpa_otp_identifier"><?php echo esc_html( $identifier_field_label ); ?></label> 
					<?php if ( $identifier_field_editable ) : ?>
						<span class="description"><?php esc_html_e( '(required)', 'otpa' ); ?></span>
					<?php endif; ?>
				</th>
				<td>
				<?php if ( $identifier_field_editable ) : ?>
					<input type="text" id="otpa_otp_identifier" name="otp_identifier" value="<?php echo esc_attr( $identifier ); ?>" class="regular-text"/>
					<?php if ( $identifier_field_description ) : ?>
						<p class="description">
							<?php echo $identifier_field_description; // @codingStandardsIgnoreLine ?>
						</p>
					<?php endif; ?>
				<?php else : ?>
					<p>
						<?php echo esc_html( $identifier ); ?>
					</p>
				<?php endif; ?>
				</td>
			</tr>
			<?php do_action( 'otpa_after_otp_identifier_field', $user->ID, $identifier ); ?>
		</table>
	<?php endif; ?>
	<?php if ( $otpa_validation_enabled ) : ?>
		<table class="otpa-otp-info">
			<tr>
				<th><label><?php esc_html_e( 'Account Validation Status', 'otpa' ); ?></label></th>
				<td>
					<?php echo esc_html( $validation_status ); ?>
				</td>
			</tr>
			<tr>
				<th><label><?php esc_html_e( 'Excluded from Account Validation?', 'otpa' ); ?></label></th>
				<td>
					<?php echo esc_html( $validation_exluded ); ?>
				</td>
			</tr>
			<tr>
				<th><label><?php esc_html_e( 'Account Validation needed?', 'otpa' ); ?></label></th>
				<td>
					<?php echo esc_html( $validation_needed ); ?>
				</td>
			</tr>
			<tr>
				<th><label>
				<?php
					printf(
						// translators: %s is the OTP identifier label
						esc_html( __( '%s recently updated?', 'otpa' ) ),
						esc_html( $identifier_field_label )
					);
				?>
				</label></th>
				<td>
					<?php echo esc_html( $validation_forced ); ?>
				</td>
			</tr>
			<tr>
				<th><label><?php esc_html_e( 'Account Validation Expiry:', 'otpa' ); ?></label></th>
				<td>
					<?php echo esc_html( $validation_expiry ); ?>
				</td>
			</tr>
		</table>
	<?php endif; ?>
	<?php if ( $show_admin_validation_button ) : ?>
	<table class="form-table">
		<tr>
			<th><label for="otpa_toggle_validation"><?php esc_html_e( 'Toggle Account Validation', 'otpa' ); ?></label></th>
			<td>
				<p>
					<button data-action="otpa_toggle_user_validation" data-result_handler="otpa_toggle_validation_result" data-user_id="<?php echo esc_attr( $user->ID ); ?>" type="button" class="button otpa-user-toggle-handle" id="otpa_toggle_validation">
						<?php if ( $validation_info['validated'] ) : ?>
							<?php esc_html_e( 'Reset Account Validation', 'otpa' ); ?>
						<?php else : ?>
							<?php esc_html_e( 'Validate Account', 'otpa' ); ?>
						<?php endif; ?>
					</button>
				</p>
				<p class="description">
					<?php esc_html_e( 'Reloads the page - make sure all other changes are already saved.', 'otpa' ); ?>
				</p>
				<p class="description hidden" id="otpa_toggle_validation_result">
					<?php esc_html_e( 'An undefined error occured. Please reload the page and try again', 'otpa' ); ?>
				</p>
			</td>
		</tr>
	</table>
	<?php endif; ?>
	<?php if ( $show_admin_2fa_button ) : ?>
	<table class="form-table">
		<tr>
			<th><label for="otpa_toggle_2fa"><?php esc_html_e( 'Two-Factor Authentication', 'otpa' ); ?></label></th>
			<td>
				<p>
					<button data-action="otpa_toggle_user_2fa_active" data-result_handler="otpa_toggle_2fa_active_result" data-user_id="<?php echo esc_attr( $user->ID ); ?>" type="button" class="button otpa-user-toggle-handle" id="otpa_toggle_2fa_active">
						<?php if ( $user_2fa_active ) : ?>
							<?php esc_html_e( '2FA is ON - Click to Disable', 'otpa' ); ?>
						<?php else : ?>
							<?php esc_html_e( '2FA is OFF - Click to Enable', 'otpa' ); ?>
						<?php endif; ?>
					</button>
				</p>
				<p class="description">
					<?php esc_html_e( 'Reloads the page - make sure all other changes are already saved.', 'otpa' ); ?>
				</p>
				<p class="description hidden" id="otpa_toggle_2fa_active_result">
					<?php esc_html_e( 'An undefined error occured. Please reload the page and try again', 'otpa' ); ?>
				</p>
			</td>
		</tr>
	</table>
	<?php endif; ?>
<?php elseif ( $show_identifier_field ) : ?>
<p>
	<label for="otpa_otp_identifier"><?php echo esc_html( $label ); ?></label><br/>	
	<input type="text" id="otpa_otp_identifier" name="otp_identifier" value="<?php echo esc_attr( $identifier ); ?>" class="input"/>
</p>
<?php endif; ?>
<?php if ( otpa_is_email_gateway() ) : ?>
	<input type="hidden" id="otpa_otp_identifier" name="otp_identifier" value="<?php echo esc_attr( $user->user_email ); ?>" class="input"/>
<?php endif; ?>
