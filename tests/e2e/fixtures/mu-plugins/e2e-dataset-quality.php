<?php
/**
 * E2E only: answers the plugin's Dataset Quality API requests without calling
 * Meta, and records them.
 *
 * Installed into wp-content/mu-plugins by tests/e2e/scripts/seed-field-mapping.sh
 * for tests/e2e/admin/field-mapping.spec.js. Never ship this.
 *
 * - The answer is the option e2e_dataset_quality_response:
 *   [ 'code' => int, 'body' => array ]. Without it the request fails, as it
 *   would with the placeholder credentials the E2E site uses.
 * - Each request is appended to the option e2e_dataset_quality_requests as
 *   [ 'url' => string, 'authorization' => string ].
 *
 * @package FacebookPixelPlugin
 */

add_filter(
	'pre_http_request',
	function ( $preempt, $args, $url ) {
		if ( false === strpos( $url, 'graph.facebook.com/' ) || false === strpos( $url, '/dataset_quality' ) ) {
			return $preempt;
		}

		$requests   = get_option( 'e2e_dataset_quality_requests', array() );
		$requests[] = array(
			'url'           => $url,
			'authorization' => isset( $args['headers']['Authorization'] ) ? $args['headers']['Authorization'] : '',
		);
		update_option( 'e2e_dataset_quality_requests', $requests, false );

		$response = get_option( 'e2e_dataset_quality_response' );
		if ( ! is_array( $response ) ) {
			return new WP_Error( 'e2e_no_response', 'No Dataset Quality response set for the E2E.' );
		}
		return array(
			'headers'  => array(),
			'body'     => wp_json_encode( $response['body'] ),
			'response' => array(
				'code'    => $response['code'],
				'message' => '',
			),
			'cookies'  => array(),
			'filename' => null,
		);
	},
	10,
	3
);
