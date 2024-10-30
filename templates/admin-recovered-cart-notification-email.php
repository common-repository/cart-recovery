<h3><?php echo esc_html( __( 'Congratulations, a cart has been successfully recovered!', 'cart-recovery' ) ); ?></h3>

<p>
	<?php echo esc_html( __( 'A cart was converted into an order, with a total order value of <strong>{value}</strong>.', 'cart-recovery' ) ); ?>
</p>
<p>
	<?php echo esc_html( __( 'The original cart details are below, check your order notifications for full details of the resulting order.', 'cart-recovery' ) ); ?>
</p>

{cart_table}


