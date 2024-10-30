// CRFW Recovery Graph
jQuery(document).ready(function() {
	if ( document.getElementById( 'crfw-recovery-graph' ) ) {
		c3.generate(
			{
			    bindto: '#crfw-recovery-graph',
			    data: crfw_recovery_graph.data,
			    axis: crfw_recovery_graph.axis,
			    color: crfw_recovery_graph.color,
			    legend: crfw_recovery_graph.legend
			}
		);
	}
});
