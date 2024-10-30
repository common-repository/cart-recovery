<div id="dashboard-widgets-wrap">
	<div id="dashboard-widgets" class="metabox-holder">
		<div id="recovery-graph" class="postbox">
			<div class="inside">
				<div class="main">
					{recovery_blocks}
					<div id="crfw-recovery-graph"></div>
					<div id="crfw-summary-stats">
                        <div class="crfw-summary-stat first crfw-summary-stat-pending"><p>{pending_cnt_wrapper_open}<strong>{pending_cnt}</strong><?php echo esc_html( __( 'Pending', 'cart-recovery' ) ); ?><span class="crfw-help" data-tippy-placement="bottom" data-tippy="<?php echo esc_html( __( 'Customers on your site with items in their cart', 'cart-recovery' ) ); ?>">?</span>{pending_cnt_wrapper_close}</p></div>
						<div class="crfw-summary-stat second crfw-summary-stat-recovery"><p>{recovery_cnt_wrapper_open}<strong>{recovery_cnt}</strong><?php echo esc_html( __( 'In recovery', 'cart-recovery' ) ); ?><span class="crfw-help" data-tippy-placement="bottom" data-tippy="<?php echo esc_html( __( 'Customers that have abandoned their purchase and are being sent recovery emails', 'cart-recovery' ) ); ?>">?</span>{recovery_cnt_wrapper_close}</p></div>
						<div class="crfw-summary-stat third crfw-summary-stat-unrecovered"><p>{unrecovered_cnt_wrapper_open}<strong>{unrecovered_cnt}</strong><?php echo esc_html( __( 'Abandoned', 'cart-recovery' ) ); ?><span class="crfw-help" data-tippy-placement="bottom" data-tippy="<?php echo esc_html( __( 'Customers that entered recovery but didn\'t go on to complete a purchase', 'cart-recovery' ) ); ?>">?</span>{unrecovered_cnt_wrapper_close}</p></div>
						<div class="crfw-summary-stat last crfw-summary-stat-recovered"><p>{recovered_cnt_wrapper_open}<strong>{recovered_cnt}</strong><?php echo esc_html( __( 'Recovered', 'cart-recovery' ) ); ?><span class="crfw-help" data-tippy-placement="bottom" data-tippy="<?php echo esc_html( __( 'Customers that entered recovery and were persuaded to complete a purchase', 'cart-recovery' ) ); ?>">?</span>{recovered_cnt_wrapper_close}</p></div>
					</div>
					<div class="clear"></div>
				</div>
			</div>
		</div>
		<p>{last_run_msg}</p>
	</div>
</div>
{cron_debug}
