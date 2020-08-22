<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

?>
<div class="otpa-log-row <?php echo esc_attr( $log->type ); ?>">
	<span class="otpa-log-date"><?php echo esc_html( gmdate( 'Y-m-d H:i:s', $log->timestamp ) ); ?></span> - <span class="log-message"><?php echo esc_html( $type_output ); ?> - <?php echo $log->message; // @codingStandardsIgnoreLine ?></span>
	<?php if ( ! empty( $log->data ) ) : ?>
	<pre class="trace">data => <?php print_r( maybe_unserialize( $log->data ) ); // @codingStandardsIgnoreLine ?></pre>
	<?php endif; ?>
</div>
