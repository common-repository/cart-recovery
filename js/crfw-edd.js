jQuery(document).ready(function() {
	jQuery(document).on('blur', '#edd-email, #edd-first, #edd-last ', function() {
		crfw_record_checkout(
			jQuery('#edd-email').val(),
			jQuery('#edd-first').val(),
			jQuery('#edd-last').val()
		);
	});
});

