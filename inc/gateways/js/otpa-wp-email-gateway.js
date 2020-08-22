/* global OTPA, window */
jQuery(document).ready(function($) {
	$('#otpa_id_widget').on('keyup', function() {

		if (/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test($(this).val())) {
			$('#otpa_send_code').removeAttr('disabled');
		} else {
			$('#otpa_send_code').attr('disabled', 'disabled');
		}
	});

	$('#otpa_send_code').on('click', function(e) {
		var timer = 30,
			text  = $(this).html();

		e.preventDefault();
		$(this).attr('disabled', 'disabled');
		window.requestOTPCode();
		enableHandleCountdown(timer, text, $(this));
	});

	function enableHandleCountdown(timer, text, handle) {
		setTimeout(function() {

			if (0 !== timer) {
				timer--;
				handle.html(timer + 's');
				enableHandleCountdown(timer, text, handle);
			} else {
				handle.html(text);
				handle.removeAttr('disabled');
			}
		}, 1000);
	}
});
