<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

?>
<?php do_action( 'otpa_before_logs_settings', $active_tab ); ?>
<?php if ( 'logs-settings' === $active_tab ) : ?>
	<div class="stuffbox otpa-logs-settings">
		<div class="inside">
			<?php do_action( 'otpa_before_logs_settings_inner', $active_tab ); ?>
			<form action="options.php" method="post">
				<?php settings_fields( 'otpa_logs' ); ?>
				<div class="otpa-container" data-postbox_class="otpa-logs otpa-togglable">
					<table class="form-table otpa-logs-settings">
						<tr>
							<th>
								<label for="otpa_enable_logs"><?php esc_html_e( 'Enable Logs', 'otpa' ); ?></label>
							</th>
							<td>
								<input type="checkbox" id="otpa_enable_logs" name="otpa_logs_settings[enable]" <?php checked( (bool) $logs_settings['enable'], true ); ?>>
							</td>
						</tr>
						<tr>
							<th>
								<label for="otpa_logs_min_num"><?php esc_html_e( 'Number of Log Entries', 'otpa' ); ?></label>
							</th>
							<td>
								<input class="regular-text" type="number" id="otpa_logs_min_num" name="otpa_logs_settings[min_num]" value="<?php echo esc_attr( $logs_settings['min_num'] ); ?>"> <input type="button" value="
								<?php
								echo esc_html(
									// translators: %d is the current number of log entries
									sprintf( __( 'Clear All (%d entries)', 'otpa' ), $num_logs )
								);
								?>
								" class="button logs-clean-trigger">
								<p class="howto">
									<?php esc_html_e( 'Number of log entries to display, and the minimum number of rows to keep in the database during cleanup. Logs are cleaned up automatically every hour. The number indicated in the "Clear All" button is the real current number of rows in the database, and clicking it deletes all of them.', 'otpa' ); ?>
								</p>
							</td>
						</tr>
					</table>
					<?php submit_button(); ?>
					<div class="logs-container">
						<div id="logs_view">
							<?php echo $logs; // WPCS XSS OK ?>
						</div>
						<button class="button" id="otpa_log_refresh"><?php esc_html_e( 'Refresh', 'otpa' ); ?></button>		
					</div>
				</div>
			</form>
			<?php do_action( 'otpa_after_logs_settings_inner', $active_tab ); ?>
		</div>
	</div>
<?php endif; ?>
<?php do_action( 'otpa_after_logs_settings', $active_tab ); ?>
