<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

/** @var object $log The database log row supplied by the log manager. */
/** @var string $type_output The readable log type supplied by the log manager. */

?>
<div class="otpa-log-row <?php echo esc_attr( $log->type ); ?>">
	<span class="otpa-log-date"><?php echo esc_html( gmdate( 'Y-m-d H:i:s', $log->timestamp ) ); ?></span> - <span class="log-message"><?php echo esc_html( $type_output ); ?> - <?php echo $log->message; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
	<?php if ( ! empty( $log->data ) ) : ?>
	<pre class="trace">data => <?php print_r( maybe_unserialize( $log->data ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_print_r ?></pre>
	<?php endif; ?>
</div>
