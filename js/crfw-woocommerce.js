jQuery(document).ready(function() {
	jQuery(document).on('blur', '#billing_email, #billing_first_name, #billing_last_name ', function() {
		crfw_record_checkout(
			jQuery('#billing_email').val(),
			jQuery('#billing_first_name').val(),
			jQuery('#billing_last_name').val()
		);
	});
});

