/* global OTPA */
jQuery(document).ready(function($) {

	$('.otpa-user-2fa-button-switch').on('click', function(e) {
		e.preventDefault();

		var button  = $(this),
			buttons = $('.otpa-user-2fa-button-switch'),
			data    = {   
				nonce   : $('#otpa_2fa_nonce').val(),
				handler : button.data('handler'),
				payload : {
					user_id: button.data('user_id')
				}
			};

		buttons.attr('disabled', 'disabled');

		$.post(OTPA.otpa_api_url, data, function(response) {

			if (response.data && response.data.message) {
				buttons.nextAll('.otpa-error').html(response.data.message);
			}

			if (response.success && response.data && 'undefined' !== typeof response.data.active) {

				if (response.data.active) {
					buttons.each( function( idx, el ) {
						$(el).html($(el).data('active_text'));
					});
				} else {
					buttons.each( function( idx, el ) {
						$(el).html($(el).data('inactive_text'));
					});
				}

				buttons.removeAttr('disabled');
			} else {
				buttons.nextAll('.otpa-error').show();
			}

		}).fail(function() {
			buttons.removeAttr('disabled');
		});
	});

});