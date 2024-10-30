function crfw_record_checkout( email_address, first_name, surname, extra_info ) {
	data = {
		email:      email_address,
		first_name: first_name,
		surname:    surname,
		action:     'crfw_record_cart'
	};
	if ( typeof extra_info !== 'undefined' ) {
		data.extra_info = extra_info;
	}
	jQuery.post(
		{
			url: crfw_settings.ajax_url,
			data: data,
			xhrFields: {
				withCredentials: true,
			}
		}
	);
}

jQuery(document).ready(function() {
	if ( jQuery('[data-remodal-id=crfw-unsubscribe]').length > 0 ) {
		var crfw_modal = jQuery('[data-remodal-id=crfw-unsubscribe]').remodal();
		crfw_modal.open();
	}
});
