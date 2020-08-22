jQuery(document).ready(function($) {
	
	if ( $('.otpa-color-picker').length ) {
		$('.otpa-color-picker').wpColorPicker();
	}

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
		previewHolder.attr('src', '');
		removeButton.addClass('hidden');
	});

});
