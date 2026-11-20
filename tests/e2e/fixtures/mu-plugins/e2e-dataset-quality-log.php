<?php
/**
 * E2E only: logs the URL of every Dataset Quality API request the plugin
 * makes, after it is made. It changes nothing: the request goes to Meta and
 * the plugin gets Meta's real response.
 *
 * Installed into wp-content/mu-plugins by tests/e2e/scripts/seed-field-mapping.sh
 * for tests/e2e/admin/field-mapping.spec.js, which reads the option
 * e2e_dataset_quality_requests (a list of URLs) to count the requests and
 * check their parameters. The access token travels in a header and is not
 * logged.
 *
 * @package FacebookPixelPlugin
 */

add_action(
	'http_api_debug',
	function ( $response, $context, $transport, $args, $url ) {
		if ( 'response' !== $context || false === strpos( $url, 'graph.facebook.com/' ) || false === strpos( $url, '/dataset_quality' ) ) {
			return;
		}
		$requests   = get_option( 'e2e_dataset_quality_requests', array() );
		$requests[] = $url;
		update_option( 'e2e_dataset_quality_requests', $requests, false );
	},
	10,
	5
);
