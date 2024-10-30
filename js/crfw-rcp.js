function crfw_rcp_record_checkout() {
	// Check whether the email field is present. If not, this is probably a
	// renewal and we shouldn't do anything.
	var emailField = jQuery('#rcp_user_email');
	if (jQuery('[name="rcp_level"]:checked').attr('type') == 'radio') {
		var rcp_subscription_id = jQuery('[name="rcp_level"]:checked').val();
	} else {
		var rcp_subscription_id = jQuery('[name="rcp_level"]').val();
	}
	if ( emailField.length > 0 ) {
		crfw_record_checkout(
			jQuery('#rcp_user_email').val(),
			jQuery('#rcp_user_first').val(),
			jQuery('#rcp_user_last').val(),
			{
				rcp_subscription_id: rcp_subscription_id,
				registration_path: typeof window.crfw_settings.registration_path !== 'undefined' ?
									window.crfw_settings.registration_path :
									window.location.path + window.location.search,
			}
		);
	}
}
jQuery(document).ready(function() {
	jQuery(document).on(
		'blur',
		'#rcp_user_email, #rcp_user_first, #rcp_user_last',
		crfw_rcp_record_checkout
	);
	jQuery(document).on(
		'change',
		'[name="rcp_level"]',
		crfw_rcp_record_checkout
	);
});
