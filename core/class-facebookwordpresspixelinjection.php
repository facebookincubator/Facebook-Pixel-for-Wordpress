<?php
/**
 * Facebook Pixel Plugin FacebookWordpressPixelInjection class.
 *
 * This file contains the main logic for FacebookWordpressPixelInjection.
 *
 * @package FacebookPixelPlugin
 */

/**
 * Define FacebookWordpressPixelInjection class.
 *
 * @return void
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

namespace FacebookPixelPlugin\Core;

defined( 'ABSPATH' ) || die( 'Direct access not allowed' );

/**
 * Class FacebookWordpressPixelInjection
 */
class FacebookWordpressPixelInjection {
    /**
     * Cache for rendered pixels.
     *
     * @var array
     */
    public static $render_cache = array();

    /**
     * Constructor for the FacebookWordpressPixelInjection class.
     */
    public function __construct() {
    }

    /**
     * Injects Facebook Pixel code into WordPress.
     *
     * This method injects the necessary Facebook Pixel code into WordPress by
     * using the `wp_head` and `wp_footer` actions.
     * It also injects the necessary code for the no-JavaScript
     * version of the Facebook Pixel.
     *
     * @return void
     */
    public function inject() {
        $pixel_id = FacebookWordpressOptions::get_active_pixel_id();
        if ( FacebookPluginUtils::is_positive_integer( $pixel_id ) ) {
            add_action(
                'wp_head',
                array( $this, 'inject_pixel_code' )
            );
            add_action(
                'wp_body_open',
                array( $this, 'inject_pixel_noscript_code' )
            );
            foreach (
                FacebookPluginConfig::integration_config() as $key => $value
                ) {
            $class_name = 'FacebookPixelPlugin\\Integration\\' . $value;
            $class_name::inject_pixel_code();
            }
            add_action(
                'wp_footer',
                array( $this, 'send_pending_events' )
            );
        }
    }

    /**
     * Sends any pending Facebook server-side events.
     *
     * This method checks if there are any pending Facebook server-side events,
     * and if so, it sends them by triggering the `send_server_events` action.
     *
     * @return void
     */
    public function send_pending_events() {
        if ( FacebookSignalState::is_paused() ) {
            return;
        }

        $pending_events =
        FacebookServerSideEvent::get_instance()->get_pending_events();
        if ( count( $pending_events ) > 0 ) {
            do_action(
                'send_server_events',
                $pending_events,
                count( $pending_events )
            );
        }
    }

    /**
     * Injects the Facebook pixel base code, Open Bridge configuration code
     * if CAI is enabled, Facebook pixel initialization code and Facebook
     * pixel page view code.
     *
     * This method is hooked into the `wp_head` action and is responsible
     * for injecting the necessary code to enable the Facebook pixel for
     * the current page. It uses the `FacebookPixel` class to generate
     * the necessary code and injects it into the page.
     *
     * @return void
     */
    public function inject_pixel_code() {
        $pixel_id = FacebookPixel::get_pixel_id();
        if (
            ( isset(
                self::$render_cache[ FacebookPluginConfig::IS_PIXEL_RENDERED ]
            ) &&
            true === self::$render_cache[ FacebookPluginConfig::IS_PIXEL_RENDERED ] )
            ||
            empty( $pixel_id )
            ) {
            return;
        }

        self::$render_cache[ FacebookPluginConfig::IS_PIXEL_RENDERED ] = true;
        echo FacebookPixel::get_pixel_base_code(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        echo $this->get_facebook_signal_bootstrap_code(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        $capi_integration_status =
        FacebookWordpressOptions::get_capi_integration_status();
        // Only include user info for frontend users, not internal/admin users.
        $user_info = FacebookPluginUtils::is_internal_user() ?
            array() : FacebookWordpressOptions::get_user_info();
        if ( FacebookSignalState::is_paused() ) {
            echo "<script type='text/javascript'>fbq('consent', 'revoke');</script>"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        }
        echo FacebookPixel::get_pixel_init_code( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            FacebookWordpressOptions::get_agent_string(),
            $user_info, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
            '1' === $capi_integration_status // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        );
        echo $this->get_facebook_signal_init_code(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        echo FacebookPixel::get_pixel_page_view_code(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    }

    /**
     * Injects the Facebook Pixel noscript code.
     *
     * This method is responsible for adding the noscript version of the
     * Facebook Pixel code to the page. It uses the `get_pixel_noscript_code`
     * method from the `FacebookPixel` class to generate the necessary code.
     *
     * @return void
     */
    public function inject_pixel_noscript_code() {
        echo FacebookPixel::get_pixel_noscript_code(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    }

    /**
     * Inline FacebookSignal and signals helper.
     *
     * @return string
     */
    private function get_facebook_signal_bootstrap_code() {
        $cookie_name    = FacebookPixelSignals::COOKIE_NAME;
        $signals_nonce  = wp_create_nonce( FacebookPixelSignals::NONCE_ACTION );
        $ajax_url       = admin_url( 'admin-ajax.php' );
        $signals_action = FacebookPixelSignals::AJAX_ACTION;

        $javascript = <<<'JS'
window.FacebookSignal = window.FacebookSignal || {
    _paused: false,
    _queue: [],
    _config: {},
    _seenEventIds: {},
    _fbclid: (function() {
        try {
            var match = window.location.search.match(/[?&]fbclid=([^&]*)/);
            return match ? decodeURIComponent(match[1]) : null;
        } catch (e) {
            return null;
        }
    })(),

    init: function(config) {
        this._config = config || {};
        this._paused = !!this._config.paused;

        try {
            var raw = window.sessionStorage.getItem('fbpix_seen_event_ids');
            this._seenEventIds = raw ? JSON.parse(raw) : {};
        } catch (e) {
            this._seenEventIds = this._seenEventIds || {};
        }
    },

    queueEvent: function(eventData) {
        if (!eventData || !eventData.event_name) {
            return;
        }

        if (eventData.event_id && this._seenEventIds[eventData.event_id]) {
            return;
        }

        eventData.event_time = eventData.event_time || Math.floor(Date.now() / 1000);
        eventData.event_source_url = eventData.event_source_url || window.location.href;
        this._queue.push(eventData);

        if (eventData.event_id) {
            this._seenEventIds[eventData.event_id] = 1;
            try {
                window.sessionStorage.setItem(
                    'fbpix_seen_event_ids',
                    JSON.stringify(this._seenEventIds)
                );
            } catch (e) {}
        }
    },

    trackEvent: function(name, params, userData) {
        var eventParams = params ? Object.assign({}, params) : {};
        var eventId = eventParams.eventID || null;

        if (eventId) {
            delete eventParams.eventID;
        }

        if (this._paused) {
            this.queueEvent({
                event_name: name,
                custom_data: eventParams,
                user_data: userData || null,
                event_id: eventId
            });
            return;
        }

        if (eventId) {
            fbq('track', name, eventParams, { eventID: eventId });
        } else {
            fbq('track', name, eventParams);
        }
    },

    setPaused: function(paused) {
        this._paused = !!paused;
        if (this._paused) {
            fbq('consent', 'revoke');
        }
    },

    resume: function() {
        var self = this;

        if (!self._paused || !self._config.ajaxUrl) {
            return Promise.resolve({ success: true, data: { sent_count: 0 } });
        }

        return new Promise(function(resolve, reject) {
            var xhr = new XMLHttpRequest();
            var url = self._config.ajaxUrl +
                (self._config.ajaxUrl.indexOf('?') === -1 ? '?' : '&') +
                'action=' + encodeURIComponent(self._config.resumeAction);

            xhr.open('POST', url, true);
            xhr.setRequestHeader('Content-Type', 'application/json');
            xhr.onload = function() {
                if (xhr.status < 200 || xhr.status >= 300) {
                    reject(new Error('Resume AJAX failed: ' + xhr.status));
                    return;
                }

                try {
                    var response = JSON.parse(xhr.responseText);
                    self._handleResumeResponse(response.data || {});
                    resolve(response);
                } catch (error) {
                    reject(error);
                }
            };
            xhr.onerror = function() {
                reject(new Error('Network error'));
            };
            xhr.send(JSON.stringify({
                security: self._config.resumeNonce,
                events: self._queue,
                fbclid: self._fbclid
            }));
        });
    },

    _handleResumeResponse: function(data) {
        if (data.fbp) {
            document.cookie = '_fbp=' + encodeURIComponent(data.fbp) + ';path=/;max-age=7776000;SameSite=Lax';
        }

        if (data.fbc) {
            document.cookie = '_fbc=' + encodeURIComponent(data.fbc) + ';path=/;max-age=7776000;SameSite=Lax';
        }

        fbq('consent', 'grant');

        for (var i = 0; i < this._queue.length; i++) {
            var queuedEvent = this._queue[i];
            var customData = queuedEvent.custom_data || {};
            var trackMethod = queuedEvent.is_custom ? 'trackCustom' : 'track';
            if (queuedEvent.event_id) {
                fbq(trackMethod, queuedEvent.event_name, customData, { eventID: queuedEvent.event_id });
            } else {
                fbq(trackMethod, queuedEvent.event_name, customData);
            }
        }

        this._queue = [];
        this._paused = false;
    }
};

window.fbpix = window.fbpix || {};
(function(api) {
    var cookieName = '__COOKIE_NAME__';
    var ajaxUrl = '__AJAX_URL__';
    var signalsAction = '__SIGNALS_ACTION__';
    var signalsNonce = '__SIGNALS_NONCE__';

    function setCookie(value) {
        var expires = new Date();
        expires.setTime(expires.getTime() + (365 * 24 * 60 * 60 * 1000));
        document.cookie = cookieName + '=' + encodeURIComponent(value) + ';expires=' + expires.toUTCString() + ';path=/;SameSite=Lax';
    }

    function getCookie() {
        var match = document.cookie.match(new RegExp('(?:^|;\\s*)' + cookieName + '=([^;]*)'));
        return match ? decodeURIComponent(match[1]) : null;
    }

    api.setSignals = function(granted) {
        var value = granted ? '1' : '0';
        setCookie(value);

        return new Promise(function(resolve, reject) {
            var xhr = new XMLHttpRequest();
            xhr.open('POST', ajaxUrl, true);
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded; charset=UTF-8');
            xhr.onload = function() {
                if (xhr.status < 200 || xhr.status >= 300) {
                    reject(new Error('Signals AJAX failed: ' + xhr.status));
                    return;
                }

                try {
                    var response = JSON.parse(xhr.responseText);
                    if (!granted) {
                        window.FacebookSignal.setPaused(true);
                        resolve(response);
                        return;
                    }

                    if (window.FacebookSignal && window.FacebookSignal._paused) {
                        window.FacebookSignal.resume().then(function() {
                            resolve(response);
                        }, function(error) {
                            reject(error);
                        });
                        return;
                    }

                    resolve(response);
                } catch (error) {
                    reject(error);
                }
            };
            xhr.onerror = function() {
                reject(new Error('Network error'));
            };
            xhr.send(
                'action=' + encodeURIComponent(signalsAction) +
                '&security=' + encodeURIComponent(signalsNonce) +
                '&granted=' + encodeURIComponent(value)
            );
        });
    };

    api.getSignals = function() {
        var value = getCookie();
        if (value === null) {
            return null;
        }
        return value === '1';
    };
})(window.fbpix);
JS;

        $replacements = array(
            '__COOKIE_NAME__'    => esc_js( $cookie_name ),
            '__AJAX_URL__'       => esc_js( $ajax_url ),
            '__SIGNALS_ACTION__' => esc_js( $signals_action ),
            '__SIGNALS_NONCE__'  => esc_js( $signals_nonce ),
        );

        return "<script type='text/javascript'>" .
            strtr( $javascript, $replacements ) .
            '</script>';
    }

    /**
     * Initialize FacebookSignal with current config.
     *
     * @return string
     */
    private function get_facebook_signal_init_code() {
        $config = array(
            'paused'       => FacebookSignalState::is_paused(),
            'ajaxUrl'      => admin_url( 'admin-ajax.php' ),
            'resumeAction' => ResumeTrackingAjax::ACTION,
            'resumeNonce'  => wp_create_nonce( ResumeTrackingAjax::NONCE_ACTION ),
            'pixelId'      => FacebookPixel::get_pixel_id(),
        );

        return "<script type='text/javascript'>FacebookSignal.init(" .
            wp_json_encode(
                $config,
                JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
            ) .
            ');</script>';
    }
}
