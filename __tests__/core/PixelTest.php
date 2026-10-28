<?php
/**
 * Facebook Pixel Plugin PixelTest class.
 *
 * This file contains the main logic for PixelTest.
 *
 * @package FacebookPixelPlugin
 */

/*
* Copyright (C) 2017-present, Meta, Inc.
*
* This program is free software; you can redistribute it and/or modify
* it under the terms of the GNU General Public License as published by
* the Free Software Foundation; version 2 of the License.
* This program is distributed in the hope that it will be useful,
* but WITHOUT ANY WARRANTY; without even the implied warranty of
* MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
* GNU General Public License for more details.
*/

namespace FacebookPixelPlugin\Tests\Core;

use FacebookPixelPlugin\Tests\FacebookWordpressTestBase;
use FacebookPixelPlugin\Core\FacebookSignalState;
use FacebookPixelPlugin\Core\Pixel;
use FacebookPixelPlugin\Core\FacebookPluginConfig;
use FacebookPixelPlugin\Core\FacebookWordpressOptions;
use FacebookPixelPlugin\FacebookAds\Object\ServerSide\Event;
use FacebookPixelPlugin\FacebookAds\Object\ServerSide\CustomData;

/**
 * PixelTest class.
 *
 * Migrated from PixelRendererTest: the browser-Pixel rendering that used to live
 * in PixelRenderer now lives in Pixel. Unlike PixelRenderer, Pixel does NOT
 * inject the fb_integration_tracking name — it is baked into the event's
 * custom_data at creation time (see ServerEventFactory), so these tests put it
 * on the event's custom data and assert it is rendered through.
 *
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
final class PixelTest extends FacebookWordpressTestBase {

    /**
     * Mocks the encoding functions the render path touches, and the request
     * context that decides whether the rendered markup may be stored by a page
     * cache (a guest page render by default).
     *
     * @param bool $cacheable  Whether a page cache may store the response.
     * @param bool $doing_ajax Whether the code is rendered into an AJAX response.
     * @return void
     */
    private function mock_wp_functions( $cacheable = true, $doing_ajax = false ) {
        $this->mockCacheableResponse( $cacheable, $doing_ajax );
        \WP_Mock::userFunction( 'get_option', array( 'return' => array() ) );
        \WP_Mock::userFunction(
            'wp_unslash',
            array(
                'return' => function ( $input ) {
                    return $input;
                },
            )
        );
        \WP_Mock::userFunction(
            'wp_json_encode',
            array(
                'return' => function ( $data, $options = 0 ) {
                    return json_encode( $data );
                },
            )
        );
    }

    /**
     * Wraps rendered fbq() options the way Pixel does for markup a page cache
     * may store: a stale cached copy fires without the event id.
     *
     * @param string $options The JSON options carrying the event id.
     * @return string The guarded JavaScript expression.
     */
    private function cacheable_options( $options ) {
        return '(window.FacebookSignal && FacebookSignal.isStalePage && FacebookSignal.isStalePage() ? {} : ' . $options . ')';
    }

    /**
     * Builds an event whose custom data already carries fb_integration_tracking
     * (as ServerEventFactory would bake it in at creation time).
     *
     * @param string     $event_name  The Meta event name.
     * @param string     $event_id    The event id.
     * @param CustomData $custom_data Optional base custom data to extend.
     * @return Event The event.
     */
    private function make_event( $event_name, $event_id, $custom_data = null ) {
        $custom_data = $custom_data ?? new CustomData();
        $custom_data->addCustomProperty( 'fb_integration_tracking', 'Test' );

        return ( new Event() )
            ->setEventName( $event_name )
            ->setEventId( $event_id )
            ->setEventTime( 1234 )
            ->setCustomData( $custom_data );
    }

    /**
     * Tests that a standard event renders an fbq('track', ...) call with the
     * event's custom data (including the baked-in fb_integration_tracking).
     *
     * @return void
     */
    public function testGenerateScriptForStandardEvent() {
        $this->mock_wp_functions();
        FacebookWordpressOptions::set_version_info();
        $agent_string = FacebookWordpressOptions::get_agent_string();
        $pixel_id     = '1234';

        $pixel = new Pixel( $agent_string, $pixel_id );
        $event = $this->make_event( 'Lead', 'TestEventId' );

        $code = $pixel->generate_script_for_event( $event, true );

        $expected = sprintf(
            "<script type='text/javascript'>fbq('set', 'agent', window.FacebookSignal && FacebookSignal.tagAgent ? FacebookSignal.tagAgent('%1\$s') : '%1\$s', '%2\$s');fbq('track', 'Lead', {\"fb_integration_tracking\":\"Test\"}, %3\$s);</script>",
            $agent_string,
            $pixel_id,
            $this->cacheable_options( '{"eventID":"TestEventId"}' )
        );

        $this->assertEquals( $expected, $code );
    }

    /**
     * An event delivered through an AJAX response, such as a form plugin's
     * submit endpoint, is never stored by a page cache, so its event id is
     * rendered plainly and always sent.
     *
     * @return void
     */
    public function testGenerateScriptKeepsEventIdForAjaxDeliveredEvent() {
        $this->mock_wp_functions( true, true );
        FacebookWordpressOptions::set_version_info();
        $agent_string = FacebookWordpressOptions::get_agent_string();
        $pixel_id     = '1234';

        $pixel = new Pixel( $agent_string, $pixel_id );
        $event = $this->make_event( 'Lead', 'TestEventId' );

        $code = $pixel->generate_script_for_event( $event, false );

        $expected = sprintf(
            "fbq('set', 'agent', window.FacebookSignal && FacebookSignal.tagAgent ? FacebookSignal.tagAgent('%1\$s') : '%1\$s', '%2\$s');fbq('track', 'Lead', {\"fb_integration_tracking\":\"Test\"}, {\"eventID\":\"TestEventId\"});",
            $agent_string,
            $pixel_id
        );

        $this->assertEquals( $expected, $code );
        $this->assertStringNotContainsString( 'isStalePage', $code );
    }

    /**
     * A page rendered for a logged-in visitor is never stored by a page cache
     * either, so its event ids are rendered plainly.
     *
     * @return void
     */
    public function testGenerateScriptKeepsEventIdForLoggedInVisitor() {
        $this->mock_wp_functions( false );
        FacebookWordpressOptions::set_version_info();

        $pixel = new Pixel( FacebookWordpressOptions::get_agent_string(), '1234' );
        $event = $this->make_event( 'Purchase', 'TestEventId' );

        $code = $pixel->generate_script_for_event( $event, true );

        $this->assertStringContainsString( '{"eventID":"TestEventId"});', $code );
        $this->assertStringNotContainsString( 'isStalePage', $code );
    }

    /**
     * Tests that a non-standard event renders an fbq('trackCustom', ...) call.
     *
     * @return void
     */
    public function testGenerateScriptForCustomEvent() {
        $this->mock_wp_functions();
        FacebookWordpressOptions::set_version_info();
        $agent_string = FacebookWordpressOptions::get_agent_string();
        $pixel_id     = '1234';

        $pixel = new Pixel( $agent_string, $pixel_id );
        $event = $this->make_event( 'Custom', 'TestEventId' );

        $code = $pixel->generate_script_for_event( $event, true );

        $expected = sprintf(
            "<script type='text/javascript'>fbq('set', 'agent', window.FacebookSignal && FacebookSignal.tagAgent ? FacebookSignal.tagAgent('%1\$s') : '%1\$s', '%2\$s');fbq('trackCustom', 'Custom', {\"fb_integration_tracking\":\"Test\"}, %3\$s);</script>",
            $agent_string,
            $pixel_id,
            $this->cacheable_options( '{"eventID":"TestEventId"}' )
        );

        $this->assertEquals( $expected, $code );
    }

    /**
     * Tests that custom data (currency/value) is normalized and rendered.
     *
     * @return void
     */
    public function testGenerateScriptForCustomData() {
        $this->mock_wp_functions();
        FacebookWordpressOptions::set_version_info();
        $agent_string = FacebookWordpressOptions::get_agent_string();
        $pixel_id     = '1234';

        $pixel       = new Pixel( $agent_string, $pixel_id );
        $custom_data = ( new CustomData() )
            ->setCurrency( 'USD' )
            ->setValue( '30.00' );
        $event       = $this->make_event( 'Purchase', 'TestEventId', $custom_data );

        $code = $pixel->generate_script_for_event( $event, true );

        $expected = sprintf(
            "<script type='text/javascript'>fbq('set', 'agent', window.FacebookSignal && FacebookSignal.tagAgent ? FacebookSignal.tagAgent('%1\$s') : '%1\$s', '%2\$s');fbq('track', 'Purchase', {\"value\":\"30.00\",\"currency\":\"usd\",\"fb_integration_tracking\":\"Test\"}, %3\$s);</script>",
            $agent_string,
            $pixel_id,
            $this->cacheable_options( '{"eventID":"TestEventId"}' )
        );

        $this->assertEquals( $expected, $code );
    }

    /**
     * Tests that multiple queued events are each rendered as their own fbq()
     * call by generate_script_for_tracked_events().
     *
     * @return void
     */
    public function testGenerateScriptForMultipleTrackedEvents() {
        $this->mock_wp_functions();
        FacebookWordpressOptions::set_version_info();
        $agent_string = FacebookWordpressOptions::get_agent_string();
        $pixel_id     = '1234';

        $pixel = new Pixel( $agent_string, $pixel_id );
        $pixel->enqueue( $this->make_event( 'Lead', 'TestEventId1' ) );
        $pixel->enqueue( $this->make_event( 'Lead', 'TestEventId2' ) );

        $code = $pixel->generate_script_for_tracked_events( true );

        $expected = sprintf(
            "<script type='text/javascript'>fbq('set', 'agent', window.FacebookSignal && FacebookSignal.tagAgent ? FacebookSignal.tagAgent('%1\$s') : '%1\$s', '%2\$s');fbq('track', 'Lead', {\"fb_integration_tracking\":\"Test\"}, %3\$s);fbq('track', 'Lead', {\"fb_integration_tracking\":\"Test\"}, %4\$s);</script>",
            $agent_string,
            $pixel_id,
            $this->cacheable_options( '{"eventID":"TestEventId1"}' ),
            $this->cacheable_options( '{"eventID":"TestEventId2"}' )
        );

        $this->assertEquals( $expected, $code );
    }

    /**
     * Tests that held rendering queues the event (FacebookSignal.queueEvent)
     * instead of firing fbq() directly.
     *
     * @return void
     */
    public function testGenerateQueueScriptWhenHeld() {
        $this->mock_wp_functions();
        FacebookSignalState::hold();
        FacebookWordpressOptions::set_version_info();

        $pixel = new Pixel( FacebookWordpressOptions::get_agent_string(), '1234' );
        $event = $this->make_event( 'Lead', 'TestEventId' );

        $code = $pixel->generate_queue_script_for_event( $event, true );

        $this->assertStringContainsString( 'FacebookSignal.queueEvent(', $code );
        $this->assertStringContainsString( '"event_name":"Lead"', $code );
        $this->assertStringContainsString( '"event_id":"TestEventId"', $code );
        $this->assertStringContainsString( '"cacheable":true', $code );
        $this->assertStringContainsString(
            '"fb_integration_tracking":"Test"',
            $code
        );
        $this->assertStringNotContainsString( "fbq('track'", $code );
    }

    /**
     * A held event delivered through an AJAX response tells the browser its id
     * belongs to this visitor alone, so the id is kept on release.
     *
     * @return void
     */
    public function testGenerateQueueScriptMarksAjaxMarkupAsNotCacheable() {
        $this->mock_wp_functions( true, true );
        FacebookSignalState::hold();
        FacebookWordpressOptions::set_version_info();

        $pixel = new Pixel( FacebookWordpressOptions::get_agent_string(), '1234' );
        $event = $this->make_event( 'Lead', 'TestEventId' );

        $code = $pixel->generate_queue_script_for_event( $event, false );

        $this->assertStringContainsString( '"event_id":"TestEventId"', $code );
        $this->assertStringContainsString( '"cacheable":false', $code );
    }

    /**
     * The cache threshold defaults to five minutes.
     *
     * @return void
     */
    public function testGetCacheThresholdDefaultsToFiveMinutes() {
        $this->assertSame( 300, FacebookPluginConfig::CACHE_STALE_THRESHOLD );
        $this->assertSame( 300, Pixel::get_cache_threshold() );
    }

    /**
     * Sites tune the cache threshold through a filter.
     *
     * @return void
     */
    public function testGetCacheThresholdIsFilterable() {
        \WP_Mock::onFilter( FacebookPluginConfig::CACHE_STALE_THRESHOLD_FILTER )
            ->with( FacebookPluginConfig::CACHE_STALE_THRESHOLD )
            ->reply( '120' );

        $this->assertSame( 120, Pixel::get_cache_threshold() );
    }

    /**
     * A filter cannot push the threshold below zero.
     *
     * @return void
     */
    public function testGetCacheThresholdIsNeverNegative() {
        \WP_Mock::onFilter( FacebookPluginConfig::CACHE_STALE_THRESHOLD_FILTER )
            ->with( FacebookPluginConfig::CACHE_STALE_THRESHOLD )
            ->reply( -5 );

        $this->assertSame( 0, Pixel::get_cache_threshold() );
    }

    /**
     * Tests that held raw rendering (no script tag) still returns queue-aware JS.
     *
     * @return void
     */
    public function testGenerateQueueScriptWhenHeldWithoutScriptTag() {
        $this->mock_wp_functions();
        FacebookSignalState::hold();
        FacebookWordpressOptions::set_version_info();

        $pixel = new Pixel( FacebookWordpressOptions::get_agent_string(), '1234' );
        $event = $this->make_event( 'Purchase', 'TestEventId' );

        $code = $pixel->generate_queue_script_for_event( $event, false );

        $this->assertStringContainsString( 'FacebookSignal.queueEvent(', $code );
        $this->assertStringContainsString( '"event_name":"Purchase"', $code );
        $this->assertStringContainsString( '"event_id":"TestEventId"', $code );
        $this->assertStringNotContainsString( '<script', $code );
    }

    /**
     * Tests that when held, all queued events are rendered as separate
     * FacebookSignal.queueEvent() calls (one per event) rather than fbq() calls.
     *
     * @return void
     */
    public function testGenerateScriptForMultipleTrackedEventsWhenHeld() {
        $this->mock_wp_functions();
        FacebookSignalState::hold();
        FacebookWordpressOptions::set_version_info();
        $agent_string = FacebookWordpressOptions::get_agent_string();
        $pixel_id     = '1234';

        $pixel = new Pixel( $agent_string, $pixel_id );
        $pixel->enqueue( $this->make_event( 'Lead', 'TestEventId1' ) );
        $pixel->enqueue( $this->make_event( 'Purchase', 'TestEventId2' ) );

        $code = $pixel->generate_script_for_tracked_events( true );

        // Agent line rendered once from the injected config.
        $this->assertStringContainsString(
            sprintf( "fbq('set', 'agent', window.FacebookSignal && FacebookSignal.tagAgent ? FacebookSignal.tagAgent('%1\$s') : '%1\$s', '%2\$s');", $agent_string, $pixel_id ),
            $code
        );
        // One queueEvent per event, and no direct fbq() firing.
        $this->assertSame( 2, substr_count( $code, 'FacebookSignal.queueEvent(' ) );
        $this->assertStringContainsString( '"event_name":"Lead"', $code );
        $this->assertStringContainsString( '"event_id":"TestEventId1"', $code );
        $this->assertStringContainsString( '"event_name":"Purchase"', $code );
        $this->assertStringContainsString( '"event_id":"TestEventId2"', $code );
        $this->assertStringContainsString(
            '"fb_integration_tracking":"Test"',
            $code
        );
        $this->assertStringNotContainsString( "fbq('track'", $code );
        $this->assertStringNotContainsString( "fbq('trackCustom'", $code );
    }
}
