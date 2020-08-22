/* global OTPA, console */
jQuery(document).ready(function($) {
	
	var refreshLogs     = function(handle) {

			if ( 'undefined' !== typeof handle ) {
				handle.attr('disabled', 'disabled');
			}

			$.ajax({
				url: OTPA.ajax_url,
				type: 'POST',
				data: { action: 'otpa_refresh_logs' },
				success: function(response) {

					if ( response.success ) {
						$('#logs_view').html(response.data.html);
						$('.logs-clean-trigger').val(response.data.clean_trigger_text);
					}
				},
				error: function(jqXHR, textStatus) {
					OTPA.debug && console.log(textStatus);
				},
				complete: function() {
					updateLogScroll();

					if ( 'undefined' !== typeof handle ) {
						handle.removeAttr('disabled');
					}
				}
			});
		},
		updateLogScroll = function() {
			var element = document.getElementById('logs_view');

			element.scrollTop = element.scrollHeight;
		};

	updateLogScroll();

	$('#otpa_log_refresh').on('click', function(e) {
		e.preventDefault();
		refreshLogs($(this));
	});

	$('.logs-clean-trigger').on('click', function(e) {
		e.preventDefault();

		var button = $(this);

		button.attr('disabled', 'disabled');

		$.ajax({
			url: OTPA.ajax_url,
			type: 'POST',
			data: { action: 'otpa_clear_logs' },
			error: function(jqXHR, textStatus) {
				OTPA.debug && console.log(textStatus);
			},
			complete: function() {
				button.removeAttr('disabled');
				refreshLogs();
			}
		});
	});
});