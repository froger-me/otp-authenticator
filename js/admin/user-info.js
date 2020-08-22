/* global OTPA, console */
jQuery(document).ready(function($) {

	$('.otpa-user-toggle-handle').on('click', function(e) {
		e.preventDefault();

		var button          = $(this),
			responseHandler = $('#' + button.data('result_handler'));

		responseHandler.hide();
		button.attr('disabled', 'disabled');

		$.ajax({
			url: OTPA.ajax_url,
			type: 'POST',
			data: {
				nonce  : $('#otpa_user_info_nonce').val(),
				action : button.data('action'),
				user_id: button.data('user_id'),
			},
			success: function(response) {

				if ( response.success ) {
					responseHandler.html(response.data.message);
					responseHandler.show();
					window.location.reload();
				} else {
					responseHandler.html(response.data.message);
					responseHandler.show();
				}
			},
			error: function(jqXHR, textStatus) {
				OTPA.debug && console.log(textStatus);
				responseHandler.show();
			}
		});
	});

});