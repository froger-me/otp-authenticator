/* global OTPA */
jQuery(document).ready(function($) {
	updateUI();

	$('#otpa_enable_validation, #otpa_enable_2fa, #otpa_enable_passwordless, #otpa_force_2fa, #otpa_default_2fa').on('change', function() {
		updateUI();
	});

	$('.settings_page_otpa input[type="password"]').on('focus', function() {
		$(this).attr('type', 'text');
	} );

	$('.settings_page_otpa input[type="password"]').on('blur', function() {
		$(this).attr('type', 'password');
	} );

	function updateUI() {

		if ( $('#otpa_enable_validation').prop('checked') ) {
			$('.otpa-validation_expiry-field, .otpa-validation_exclude_roles-field').show();
		} else {
			$('.otpa-validation_expiry-field, .otpa-validation_exclude_roles-field').hide();
		}

		if ( $('#otpa_enable_2fa').prop('checked') ) {
			$('.otpa-force_2fa-field, .otpa-default_2fa-field').show();
			$('.otpa-enable_passwordless-field, .otpa-enable_validation-field').hide();
			$('#otpa_enable_validation, #otpa_enable_passwordless').prop('checked', false);

			if ( $('#otpa_default_2fa').prop('checked') ) {
				$('#otpa_force_2fa').prop('checked', false);
				$('.otpa-force_2fa-field').hide();
			} else {
				$('.otpa-force_2fa-field').show();
			}

			if ( $('#otpa_force_2fa').prop('checked') ) {
				$('#otpa_default_2fa').prop('checked', false);
				$('.otpa-default_2fa-field').hide();
			} else {
				$('.otpa-default_2fa-field').show();
			}
		} else {
			$('.otpa-force_2fa-field, .otpa-default_2fa-field').hide();
			$('.otpa-enable_passwordless-field, .otpa-enable_validation-field').show();
		}

		if ( $('#otpa_enable_validation').prop('checked') || $('#otpa_enable_passwordless').prop('checked') ) {
			$('#otpa_enable_2fa').prop('checked', false);
			$('.otpa-enable_2fa-field').hide();
		} else {
			$('.otpa-enable_2fa-field').show();
		}

		$('.settings_page_otpa .stuffbox .inside').css('visibility', 'visible');
	}

});

