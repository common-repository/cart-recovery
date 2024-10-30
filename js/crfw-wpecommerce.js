jQuery(document).ready(function() {
	// Pre 4.0 theme engine.
	jQuery(document).on(
		'blur',
		'[data-wpsc-meta-key="billingfirstname"], [data-wpsc-meta-key="billinglastname"], [data-wpsc-meta-key="billingemail"]',
		function() {
			crfw_record_checkout(
				jQuery('[data-wpsc-meta-key="billingemail"]').val(),
				jQuery('[data-wpsc-meta-key="billingfirstname"]').val(),
				jQuery('[data-wpsc-meta-key="billinglastname"]').val()
			);
		}
	);
	// 4.0 Theme engine support
	jQuery(document).on(
		'blur',
		'#wpsc-checkout-field-billingemail-control, #wpsc-checkout-field-billingfirstname-control, #wpsc-checkout-field-billinglastname-control',
		function() {
			crfw_record_checkout(
				jQuery('#wpsc-checkout-field-billingemail-control').val(),
				jQuery('#wpsc-checkout-field-billingfirstname-control').val(),
				jQuery('#wpsc-checkout-field-billinglastname-control').val()
			);
		}
	);
});
