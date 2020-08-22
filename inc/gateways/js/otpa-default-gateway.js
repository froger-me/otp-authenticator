/* global OTPA, window */
jQuery(document).ready(function($) {
	var requestCodeHandle         = $('#otpa_send_code'),
		requestCodeHandleText     = requestCodeHandle.html(),
		requestCodeHandleDisabled = false;

	$('#otpa_id_widget').on('keyup', function() {

		if ( ! requestCodeHandleDisabled && 0 < $(this).val().length) {
			requestCodeHandle.removeAttr('disabled');
		} else {
			requestCodeHandle.attr('disabled', 'disabled');
		}
	});

	requestCodeHandle.on('click', function(e) {
		e.preventDefault();
		enableHandleCountdown(OTPA.otp_code_request_throttle);
		window.requestOTPCode();
	});

	if ( 0 < OTPA.otp_code_request_wait ) {
		enableHandleCountdown(OTPA.otp_code_request_wait);
	}

	function enableHandleCountdown(time) {
		requestCodeHandleDisabled = true;

		requestCodeHandle.attr('disabled', 'disabled');

		setTimeout(function() {

			if (0 !== time) {
				time--;
				requestCodeHandle.html(time + 's');
				enableHandleCountdown(time);
			} else {
				requestCodeHandleDisabled = false;

				requestCodeHandle.html(requestCodeHandleText);
				requestCodeHandle.removeAttr('disabled');
			}
		}, 1000);
	}
});
