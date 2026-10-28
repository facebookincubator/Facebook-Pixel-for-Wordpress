<?php
/**
 * Mailchimp API Mock - stand-in Mailchimp Marketing API for the Lead E2E tests
 *
 * Location: tests/e2e/helpers/php/mailchimp-api-mock.php
 *
 * Installed as an mu-plugin by tests/e2e/scripts/seed-store.sh. Mailchimp for WP
 * only fires `mc4wp_form_subscribed` (the hook the plugin's Lead integration
 * listens on) after the Mailchimp API accepts the subscriber, and CI has no real
 * Mailchimp account. This short-circuits wp_remote_request() for
 * *.api.mailchimp.com only, answering the three calls a subscribe makes:
 *
 * 1. GET  /lists/{id}/merge-fields       -> the list's merge fields (FNAME)
 * 2. GET  /lists/{id}/members/{hash}     -> 404, so the subscriber is new
 * 3. POST /lists/{id}/members            -> the created member
 */

add_filter(
    'pre_http_request',
    function ( $preempt, $args, $url ) {
        $host = (string) wp_parse_url( $url, PHP_URL_HOST );
        if ( ! preg_match( '/(^|\.)api\.mailchimp\.com$/', $host ) ) {
            return $preempt;
        }

        $path   = (string) wp_parse_url( $url, PHP_URL_PATH );
        $method = strtoupper( isset( $args['method'] ) ? $args['method'] : 'GET' );
        $code   = 200;

        if ( 'GET' === $method && preg_match( '#/lists/[^/]+/members/[^/]+$#', $path ) ) {
            $code = 404;
            $body = array(
                'status' => 404,
                'title'  => 'Resource Not Found',
            );
        } elseif ( 'POST' === $method && preg_match( '#/lists/[^/]+/members$#', $path ) ) {
            $request = json_decode( isset( $args['body'] ) ? $args['body'] : '', true );
            $email   = isset( $request['email_address'] ) ? $request['email_address'] : '';
            $body    = array(
                'id'            => md5( strtolower( $email ) ),
                'email_address' => $email,
                'status'        => isset( $request['status'] ) ? $request['status'] : 'subscribed',
            );
        } elseif ( preg_match( '#/lists/[^/]+/merge-fields$#', $path ) ) {
            $body = array(
                'merge_fields' => array(
                    array(
                        'name'          => 'First Name',
                        'tag'           => 'FNAME',
                        'type'          => 'text',
                        'required'      => false,
                        'default_value' => '',
                        'options'       => new stdClass(),
                        'public'        => true,
                    ),
                ),
            );
        } else {
            $body = new stdClass();
        }

        return array(
            'headers'  => array( 'content-type' => 'application/json' ),
            'body'     => wp_json_encode( $body ),
            'response' => array(
                'code'    => $code,
                'message' => 404 === $code ? 'Not Found' : 'OK',
            ),
            'cookies'  => array(),
            'filename' => null,
        );
    },
    10,
    3
);
