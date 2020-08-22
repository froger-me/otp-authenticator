/* global OTPA, window */
jQuery(document).ready(function($) {
	$('link[rel="stylesheet"], style').each(function(index, el) {
		var style = $(el);

		if (
			style.attr('id') &&
			style.attr('id').indexOf('otpa') === -1 &&
			style.attr('id').indexOf('dashicons') === -1
		) {
			style.remove();
		}
	});

	if ( '' !== $('#otpa_logo').data('otp_logo_url') ) {
		var logo       = new Image(),
			logoHolder = $('#otpa_logo');

		logo.src = logoHolder.data('otp_logo_url');

		$(logo).load(function() {
			logoHolder.css('background-image', 'url(' + $(this).attr('src') + ')');
			$('.otpa-wrapper').fadeIn();
		});
	} else {
		$('.otpa-wrapper').fadeIn();
	}

	window.requestOTPCodeEvent = new CustomEvent('requestOTPCode');

	window.requestOTPCode = function() {
		window.dispatchEvent(window.requestOTPCodeEvent);
	};

	window.addEventListener('requestOTPCode', function() {
		procesRequestOTPCode();
	});

	var procesRequestOTPCode = function() {
		var data = {
				nonce   : $('#otpa_nonce').val(),
				handler : 'request_otp_code',
				otpType : $('#otpa_otp_form').data('otp_form_type'),
				payload : {
					identifier: $('#otpa_id').val()
				}
			};

		$('#otpa_result').html('');

		$.post(OTPA.otpa_api_url, data, function(response) {

			if (!response.success && response.data && response.data.message) {
				$('#otpa_result').html(response.data.message);
			} else if (response.data && response.data.message) {
				$('#otpa_result').html(response.data.message);
			} else {
				$('#otpa_result').html($('#otpa_unknown_result').html());
			}
		}).fail(function() {
			$('#otpa_result').html($('#otpa_unknown_result').html());
		});
	},
	getUrlParameter          = function getUrlParameter(param) {
		var pageUrl      = window.location.search.substring(1),
			urlVariables = pageUrl.split('&'),
			parameterName,
			i;

		for (i = 0; i < urlVariables.length; i++) {
			parameterName = urlVariables[i].split('=');

			if (parameterName[0] === param) {
				return parameterName[1] === undefined ? true : decodeURIComponent(parameterName[1]);
			}
		}
	};

	$('#otpa_id_widget').on('change', function() {
		$('#otpa_id').val($(this).val());
	});

	$('#otpa_code_widget').on('keyup', function() {
		$('#otpa_code_widget').trigger('change');
	});

	$('#otpa_code_widget').on('change', function() {
		var code = $(this).val();

		$('#otpa_code').val( code );

		if (0 < code.length) {
			$('#otpa_submit').removeAttr('disabled');
		} else {
			$('#otpa_submit').attr('disabled', 'disabled');
		}
	});

	$('#otpa_set_id_input').on('keyup', function() {
		var input = $(this).val();
		$('#otpa_id').val($(this).val());

		if (0 < input.length) {
			$('#otpa_submit').removeAttr('disabled');
		} else {
			$('#otpa_submit').attr('disabled', 'disabled');
		}
	});

	$('#otpa_submit').on('click', function(e) {
		e.preventDefault();

		var button = $(this),
			data   = {   
				nonce:   $('#otpa_nonce').val(),
				handler: $('#otpa_otp_form').data('handler'),
				otpType: $('#otpa_otp_form').data('otp_form_type'),
				payload: {
					code:       $('#otpa_code').val(),
					identifier: $('#otpa_id').val(),
					redirect:   getUrlParameter('redirect_to'),
				}
			};

		$('#otpa_result').html('');
		$('input').attr('disabled', 'disabled');
		button.attr('disabled', 'disabled');

		$.post(OTPA.otpa_api_url, data, function(response) {

			if (!response.success && response.data && response.data.message) {
				$('#otpa_result').html(response.data.message);
				$('input').removeAttr('disabled');
			} else if (response.data && response.data.message) {
				$('#otpa_result').html(response.data.message);

				if (response.data.redirect) {
					window.location.replace(response.data.redirect);
				} else {
					window.location.reload();
				}
			} else {
				$('#otpa_result').html($('#otpa_unknown_result').html());
				$('input').removeAttr('disabled');
				button.removeAttr('disabled');
			}
		}).fail(function() {
			$('#otpa_result').html($('#otpa_unknown_result').html());
		});
	});

});

