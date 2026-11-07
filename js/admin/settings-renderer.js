jQuery(document).ready(function($) {
	var stylePreviewFrame = $('.otpa-form-settings-preview iframe');

	function isHexColor(value) {
		return /^#(?:[0-9a-f]{3}|[0-9a-f]{6})$/i.test(value);
	}

	function adjustColorBrightness(value, steps) {
		var hex = value.replace('#', ''),
			red,
			green,
			blue;

		if (hex.length === 3) {
			hex = hex.charAt(0) + hex.charAt(0) + hex.charAt(1) + hex.charAt(1) + hex.charAt(2) + hex.charAt(2);
		}

		red   = Math.max(0, Math.min(255, parseInt(hex.substring(0, 2), 16) + steps));
		green = Math.max(0, Math.min(255, parseInt(hex.substring(2, 4), 16) + steps));
		blue  = Math.max(0, Math.min(255, parseInt(hex.substring(4, 6), 16) + steps));

		return '#' + [red, green, blue].map(function(channel) {
			return channel.toString(16).padStart(2, '0');
		}).join('');
	}

	function updateStylePreview() {
		var frame           = stylePreviewFrame.get(0),
			previewDocument,
			previewStyle,
			backgroundColor = $('#otpa_submit_button_background_color').val(),
			buttonTextColor = $('#otpa_submit_button_text_color').val(),
			linkTextColor   = $('#otpa_link_text_color').val(),
			logoUrl         = $('#otpa_logo_url').val(),
			styles          = '';

		if (!frame || !frame.contentDocument) {
			return;
		}

		previewDocument = $(frame.contentDocument);

		if (!previewDocument.find('body').length) {
			return;
		}

		if (isHexColor(backgroundColor)) {
			styles += '.otpa-form button.submit{background-color:' + backgroundColor + ';}';
			styles += '.otpa-form button.submit:not([disabled]):hover,.otpa-form button.submit:not([disabled]):active,.otpa-form button.submit:not([disabled]):focus{background-color:' + adjustColorBrightness(backgroundColor, -10) + ';}';
			styles += '.otpa-form button.submit:disabled{background-color:' + adjustColorBrightness(backgroundColor, -20) + ';}';
		}

		if (isHexColor(buttonTextColor)) {
			styles += '.otpa-form button.submit{color:' + buttonTextColor + ';}';
		}

		if (isHexColor(linkTextColor)) {
			styles += '.otpa-form .message a{color:' + linkTextColor + ';}';
			styles += '.otpa-form .message a:hover,.otpa-form .message a:active,.otpa-form .message a:focus{color:' + adjustColorBrightness(linkTextColor, 30) + ';}';
		}

		previewStyle = previewDocument.find('#otpa-live-style-preview');

		if (!previewStyle.length) {
			previewStyle = $('<style id="otpa-live-style-preview"></style>');
			previewDocument.find('head').append(previewStyle);
		}

		previewStyle.text(styles);
		previewDocument.find('#otpa_logo').css(
			'background-image',
			logoUrl ? 'url("' + encodeURI(logoUrl).replace(/"/g, '%22') + '")' : 'none'
		);
	}

	if ( $('.otpa-color-picker').length ) {
		$('.otpa-color-picker').wpColorPicker({
			change: updateStylePreview,
			clear: updateStylePreview,
		});
	}

	stylePreviewFrame.on('load', updateStylePreview);
	$('.otpa-style-settings-form').on('input change', 'input', updateStylePreview);

	$('.otpa-media-select').click(function(e) {
		e.preventDefault();

		var imageFrame,
			field            = $(this).parent(),
			valueHolder      = field.find('input[type="hidden"]'),
			removeButton     = field.find('.otpa-media-reset'),
			previewHolder    = field.find('.otpa-style-preview-image'),
			previewContainer = field.find('.otpa-style-preview-image-container');

		if (imageFrame) {
			imageFrame.open();
		}

		imageFrame = wp.media({
			multiple : false,
			library  : {
				type : 'image',
			}
		});

		imageFrame.on('close',function() {
			var selection  = imageFrame.state().get('selection'),
				galleryIDs = [],
				index      = 0;

			selection.each(function(attachment) {
				 galleryIDs[index] = attachment.id;
				 index++;
			});

			valueHolder.val(selection.models[0].attributes.url);
			valueHolder.trigger('change');
			previewHolder.attr('src', selection.models[0].attributes.url);
			removeButton.removeClass('hidden');
			previewContainer.removeClass('empty');
		});

		imageFrame.on('open',function() {
			var selection = imageFrame.state().get('selection'),
				ids       = valueHolder.val().split(',');

			ids.forEach(function(id) {
				var attachment = wp.media.attachment(id);

				attachment.fetch();
				selection.add( attachment ? [ attachment ] : [] );
			});

		});

		imageFrame.open();
	});

	$('.otpa-media-reset').on('click', function(e) {
		e.preventDefault();

		var field            = $(this).parent(),
			valueHolder      = field.find('input[type="hidden"]'),
			removeButton     = field.find('.otpa-media-reset'),
			previewHolder    = field.find('.otpa-style-preview-image'),
			previewContainer = field.find('.otpa-style-preview-image-container');

		previewContainer.addClass('empty');
		valueHolder.val('');
		valueHolder.trigger('change');
		previewHolder.attr('src', '');
		removeButton.addClass('hidden');
	});

});
