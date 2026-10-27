/**
 * Copyright (C) 2017-present, Meta, Inc.
 *
 * This source code is licensed under the license found in the
 * LICENSE file in the root directory of this source tree.
 */

window.FacebookSignal = window.FacebookSignal || {
  _held: false,
  _releasing: false,
  _releasePromise: null,
  _holdGeneration: 0,
  _requestedState: null,
  _pixelInitialized: false,
  _pixelId: null,
  _pixelUserInfo: {},
  _pixelOptions: {},
  _pendingPixelEvents: [],
  _queue: [],
  _config: {},
  _seenEventIds: {},
  _fbclid: (function () {
    try {
      var match = window.location.search.match(/[?&]fbclid=([^&]*)/);
      return match ? decodeURIComponent(match[1]) : null;
    } catch (e) {
      return null;
    }
  })(),

  _readCookie: function (name) {
    try {
      var pairs = (document.cookie || '').split(';');
      for (var i = 0; i < pairs.length; i++) {
        var pair = pairs[i].replace(/^\s+/, '');
        if (pair.indexOf(name + '=') === 0) {
          return decodeURIComponent(pair.substring(name.length + 1));
        }
      }
    } catch (e) {}
    return null;
  },

  init: function (config) {
    this._config = config || {};
    this._attribution = this._config.attribution || {};

    // Read cookie directly so cached pages resolve the correct held/active
    // state even when PHP rendered held: false.
    var cookieState = this._readCookie('wc_facebook_signals_state');
    this._held = cookieState ? cookieState === 'held' : !!this._config.held;

    // Sync consent immediately so fbevents.js obeys the current state.
    if (typeof fbq === 'function') {
      fbq('consent', this._held ? 'revoke' : 'grant');
    }

    try {
      var raw = window.sessionStorage.getItem('fbpix_seen_event_ids');
      this._seenEventIds = raw ? JSON.parse(raw) : {};
    } catch (e) {
      this._seenEventIds = this._seenEventIds || {};
    }
  },

  initPixel: function (pixelId, userInfo, options) {
    this._pixelId = pixelId;
    this._pixelUserInfo =
      userInfo && typeof userInfo === 'object' && !Array.isArray(userInfo)
        ? userInfo
        : {};
    this._pixelOptions = options || {};
    if (!this._held) {
      this._runPixelInit();
    }
  },

  _runPixelInit: function () {
    if (this._pixelInitialized || !this._pixelId || typeof fbq !== 'function') {
      return;
    }
    if (this._pixelOptions && this._pixelOptions.includeCapiIntegration) {
      var url = window.location.origin + '?ob=open-bridge';
      fbq('set', 'openbridge', this._pixelId, url);
    }

    if (this._config.capig === '1') {
      fbq('optinMetaEnabledCapi', this._pixelId);
    }

    fbq('init', this._pixelId, this._pixelUserInfo, this._pixelOptions);

    this._pixelInitialized = true;
    this._flushPendingPixelEvents();
  },

  _flushPendingPixelEvents: function () {
    var pending = this._pendingPixelEvents;
    this._pendingPixelEvents = [];
    for (var i = 0; i < pending.length; i++) {
      var ev = pending[i];
      this._firePixelEvent(ev.name, ev.params, ev.method, ev.eventId);
    }
  },

  _fireOrQueuePixelEvent: function (name, params, method, eventId) {
    // fbq can appear after initPixel() or a release when a consent tool
    // delays the Pixel base code; initializing here flushes pending events.
    this._runPixelInit();
    if (!this._pixelInitialized) {
      this._pendingPixelEvents.push({
        name: name,
        params: params || {},
        method: method || 'track',
        eventId: eventId || null,
      });
      return;
    }
    this._firePixelEvent(
      name,
      params || {},
      method || 'track',
      eventId || null,
    );
  },

  _firePixelEvent: function (name, params, method, eventId) {
    method = method || 'track';
    if (eventId) {
      fbq(method, name, params || {}, { eventID: eventId });
    } else {
      fbq(method, name, params || {});
    }
  },

  _syncCookie: function (name, serverValue, domain) {
    if (!serverValue) {
      return;
    }
    var encoded = encodeURIComponent(serverValue);
    var match = document.cookie.match(
      new RegExp('(?:^|;\\s*)' + name + '=([^;]*)'),
    );
    var current = match ? match[1] : null;
    if (current && current !== encoded) {
      var cookie =
        name + '=' + encoded + ';path=/;max-age=7776000;SameSite=Lax';
      if (domain) {
        cookie += ';domain=' + domain;
      }
      document.cookie = cookie;
    }
  },

  queueEvent: function (eventData) {
    if (!eventData || !eventData.event_name) {
      return;
    }

    if (eventData.event_id && this._seenEventIds[eventData.event_id]) {
      return;
    }

    // Give every queued event an id so a release can report exactly which
    // ones it sent and Meta can deduplicate a resend.
    eventData.event_id = eventData.event_id || this._generateEventId();
    eventData.event_time =
      eventData.event_time || Math.floor(Date.now() / 1000);
    eventData.event_source_url =
      eventData.event_source_url || window.location.href;
    this._queue.push(eventData);

    if (eventData.event_id) {
      this._seenEventIds[eventData.event_id] = 1;
      try {
        window.sessionStorage.setItem(
          'fbpix_seen_event_ids',
          JSON.stringify(this._seenEventIds),
        );
      } catch (e) {}
    }
  },

  trackEvent: function (name, params, userData, method, eventId) {
    method = method || 'track';
    eventId = eventId || (params && params.eventID) || null;

    if (this._held) {
      this.queueEvent({
        event_name: name,
        custom_data: params || {},
        event_id: eventId,
        event_time: Math.floor(Date.now() / 1000),
        method: method,
      });
    } else {
      this._fireOrQueuePixelEvent(name, params || {}, method, eventId);
    }
  },

  hold: function () {
    // Hold a page that is active or mid-release. Bumping the generation makes
    // a release that is still in flight discard its response.
    this._held = true;
    this._holdGeneration++;
    this._requestedState = 'held';
    this._setStateCookie('held');
    if (typeof fbq === 'function') {
      fbq('consent', 'revoke');
    }
  },

  _setStateCookie: function (state) {
    // Same attributes as the fbwcsignal helper so both write one cookie.
    var expires = new Date();
    expires.setTime(expires.getTime() + 365 * 24 * 60 * 60 * 1000);
    document.cookie =
      'wc_facebook_signals_state=' +
      state +
      ';expires=' +
      expires.toUTCString() +
      ';path=/;SameSite=Lax';
  },

  _generateEventId: function () {
    if (window.crypto && typeof window.crypto.randomUUID === 'function') {
      return window.crypto.randomUUID();
    }
    return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(
      /[xy]/g,
      function (c) {
        var r = (Math.random() * 16) | 0;
        return (c === 'x' ? r : (r & 0x3) | 0x8).toString(16);
      },
    );
  },

  release: function () {
    var self = this;

    if (!self._held || !self._config.ajaxUrl) {
      return Promise.resolve({ success: true, data: { sent_count: 0 } });
    }

    if (self._releasing) {
      // A release is already in flight. Run this one once it settles, unless
      // a hold arrives in between. Otherwise a release requested after a
      // withdrawal would be dropped while the older response is discarded,
      // leaving the page held for good.
      self._requestedState = 'active';
      var requestedAt = self._holdGeneration;
      var rerun = function () {
        if (self._holdGeneration !== requestedAt) {
          return { success: true, data: { sent_count: 0 } };
        }
        return self.release();
      };
      return self._releasePromise.then(rerun, rerun);
    }

    // A hold written after the release was requested wins: keep the queue
    // and send nothing.
    if (self._readCookie('wc_facebook_signals_state') === 'held') {
      return Promise.resolve({ success: true, data: { sent_count: 0 } });
    }

    self._releasing = true;
    self._requestedState = 'active';
    var generation = self._holdGeneration;
    var postedEvents = self._queue.slice();

    self._releasePromise = new Promise(function (resolve, reject) {
      var xhr = new XMLHttpRequest();
      var url =
        self._config.ajaxUrl +
        (self._config.ajaxUrl.indexOf('?') === -1 ? '?' : '&') +
        'action=' +
        encodeURIComponent(self._config.releaseAction);

      xhr.open('POST', url, true);
      xhr.setRequestHeader('Content-Type', 'application/json');
      xhr.onload = function () {
        // A CMP bridge runs on this request too and may answer with a
        // Set-Cookie for the state it saw when the request was made. If a
        // hold happened since, that cookie is stale: put the latest requested
        // state back before anything reads it. That is `held`, or `active`
        // again if a newer release has been queued behind this request.
        if (self._holdGeneration !== generation && self._requestedState) {
          self._setStateCookie(self._requestedState);
        }

        if (xhr.status < 200 || xhr.status >= 300) {
          self._releasing = false;
          reject(new Error('Release AJAX failed: ' + xhr.status));
          return;
        }

        try {
          var response = JSON.parse(xhr.responseText);
          if (self._holdGeneration !== generation) {
            // Signals were held while this release was in flight. The server
            // has already sent the events it lists, so forget those, but
            // leave the browser held: no consent grant, no replay.
            self._discardPostedEvents(postedEvents, response.data || {});
            self._releasing = false;
            resolve(response);
            return;
          }
          self._handleReleaseResponse(response.data || {});
          self._releasing = false;
          resolve(response);
        } catch (error) {
          self._releasing = false;
          reject(error);
        }
      };
      xhr.onerror = function () {
        self._releasing = false;
        reject(new Error('Network error'));
      };
      var attribution = self._config.attribution || {};
      xhr.send(
        JSON.stringify({
          events: postedEvents,
          fbclid: self._fbclid,
          fbp: self._readCookie('_fbp') || attribution.fbp || null,
          fbc: self._readCookie('_fbc') || attribution.fbc || null,
        }),
      );
    });

    return self._releasePromise;
  },

  _discardPostedEvents: function (postedEvents, data) {
    // Drop only what the server says it sent. It caps and filters the batch,
    // so a count would also drop events that never went out. With no id list,
    // keep everything: a resend is deduplicated by event_id, a lost event is
    // not recoverable.
    var sentIds =
      data && Array.isArray(data.sent_event_ids) ? data.sent_event_ids : [];
    if (!sentIds.length) {
      return;
    }
    this._queue = this._queue.filter(function (ev) {
      return (
        postedEvents.indexOf(ev) === -1 ||
        !ev.event_id ||
        sentIds.indexOf(ev.event_id) === -1
      );
    });
  },

  _handleReleaseResponse: function (data) {
    var attr = this._config.attribution || {};
    var fbpValue = data.fbp || attr.fbp;
    var fbcValue = data.fbc || attr.fbc;

    if (fbpValue) {
      var fbpCookie =
        '_fbp=' +
        encodeURIComponent(fbpValue) +
        ';path=/;max-age=7776000;SameSite=Lax';
      if (attr.fbpDomain) {
        fbpCookie += ';domain=' + attr.fbpDomain;
      }
      document.cookie = fbpCookie;
    }

    if (fbcValue) {
      var fbcCookie =
        '_fbc=' +
        encodeURIComponent(fbcValue) +
        ';path=/;max-age=7776000;SameSite=Lax';
      if (attr.fbcDomain) {
        fbcCookie += ';domain=' + attr.fbcDomain;
      }
      document.cookie = fbcCookie;
    }

    // Update user_info with fresh AAM data from the release response, then
    // run (or re-run) fbq('init') so the pixel has correct user_info.
    if (
      data.user_info &&
      typeof data.user_info === 'object' &&
      !Array.isArray(data.user_info)
    ) {
      this._pixelUserInfo = data.user_info;
    }
    this._pixelInitialized = false;
    this._runPixelInit();

    if (typeof fbq === 'function') {
      fbq('consent', 'grant');
    }

    // Without fbq these events wait in the pending queue; the next event
    // after fbq loads initializes the Pixel and flushes them.
    for (var i = 0; i < this._queue.length; i++) {
      var queuedEvent = this._queue[i];
      var trackMethod =
        queuedEvent.method || (queuedEvent.is_custom ? 'trackCustom' : 'track');
      this._fireOrQueuePixelEvent(
        queuedEvent.event_name,
        queuedEvent.custom_data || {},
        trackMethod,
        queuedEvent.event_id,
      );
    }

    this._queue = [];
    this._held = false;
    this._releasing = false;
  },
};

window.fbwcsignal = window.fbwcsignal || {};
(function (api) {
  var signalConfig = window.facebookSignalConfig || {};
  var cookieName = signalConfig.cookieName || '';
  var ajaxUrl = signalConfig.ajaxUrl || '';
  var signalsAction = signalConfig.signalsAction || '';
  var signalsNonce = signalConfig.signalsNonce || '';

  function setCookie(value) {
    var expires = new Date();
    expires.setTime(expires.getTime() + 365 * 24 * 60 * 60 * 1000);
    document.cookie =
      cookieName +
      '=' +
      encodeURIComponent(value) +
      ';expires=' +
      expires.toUTCString() +
      ';path=/;SameSite=Lax';
  }

  function getCookie() {
    var match = document.cookie.match(
      new RegExp('(?:^|;\\s*)' + cookieName + '=([^;]*)'),
    );
    return match ? decodeURIComponent(match[1]) : null;
  }

  /**
   * Sequence number of the most recent state request, and the state it asked
   * for. The server answers each request with a Set-Cookie for the state it
   * applied, so when responses arrive out of order the older one would leave
   * the cookie on a state the visitor has since changed.
   */
  var latestRequest = 0;
  var latestState = null;

  function updateState(state) {
    var normalizedState = state === 'active' ? 'active' : 'held';
    var sequence = ++latestRequest;
    latestState = normalizedState;
    setCookie(normalizedState);

    return new Promise(function (resolve, reject) {
      var xhr = new XMLHttpRequest();
      xhr.open('POST', ajaxUrl, true);
      xhr.setRequestHeader(
        'Content-Type',
        'application/x-www-form-urlencoded; charset=UTF-8',
      );
      xhr.onload = function () {
        // A newer state was requested while this request was in flight. The
        // browser has just applied this response's Set-Cookie, so put the
        // newer state back before anything reads the cookie.
        if (sequence !== latestRequest && latestState) {
          setCookie(latestState);
        }

        if (xhr.status < 200 || xhr.status >= 300) {
          reject(new Error('Signals AJAX failed: ' + xhr.status));
          return;
        }

        try {
          resolve(JSON.parse(xhr.responseText));
        } catch (error) {
          reject(error);
        }
      };
      xhr.onerror = function () {
        reject(new Error('Network error'));
      };
      xhr.send(
        'action=' +
          encodeURIComponent(signalsAction) +
          '&security=' +
          encodeURIComponent(signalsNonce) +
          '&state=' +
          encodeURIComponent(normalizedState),
      );
    });
  }

  api.hold = function () {
    // Revoke browser consent right away, before the state update round-trip,
    // so nothing fires in the meantime and a release that is still in flight
    // is discarded when it returns.
    if (
      window.FacebookSignal &&
      typeof window.FacebookSignal.hold === 'function'
    ) {
      window.FacebookSignal.hold();
    }
    return updateState('held');
  };

  api.release = function () {
    // Remember which hold generation this release belongs to. A hold that
    // lands while the state update is in flight bumps it, and the queue must
    // then stay where it is.
    var generation = window.FacebookSignal
      ? window.FacebookSignal._holdGeneration
      : null;

    return updateState('active').then(function (response) {
      if (!window.FacebookSignal || !window.FacebookSignal._held) {
        return response;
      }
      if (
        generation !== null &&
        window.FacebookSignal._holdGeneration !== generation
      ) {
        return response;
      }
      return window.FacebookSignal.release().then(function () {
        return response;
      });
    });
  };

  api.getState = function () {
    var value = getCookie();
    if (value === 'active' || value === 'held') {
      return value;
    }
    return null;
  };
})(window.fbwcsignal);
