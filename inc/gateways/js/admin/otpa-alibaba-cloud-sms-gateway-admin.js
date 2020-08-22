/* global OTPA */
jQuery(document).ready(function($) {
	updateUI();

	$('#otpa_intl').on('change', function() {
		updateUI();
	});

	function updateUI() {

		if ( $('#otpa_intl').prop('checked') ) {
			$('.otpa-china_us-field').show();
		} else {
			$('#otpa_china_us').prop('checked', false);
			$('.otpa-china_us-field').hide();
		}
	}

	updateUI();
});
