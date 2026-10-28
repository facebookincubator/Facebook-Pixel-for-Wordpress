/**
 * Copyright (C) 2017-present, Meta, Inc.
 *
 * This source code is licensed under the license found in the
 * LICENSE file in the root directory of this source tree.
 */

window.FacebookSignal = window.FacebookSignal || {
  _held: false,
  _releasing: false,
  _pixelInitialized: false,
  _pixelId: null,
  _pixelUserInfo: {},
  _pixelOptions: {},
  _pendingPixelEvents: [],
  _queue: [],
  _config: {},
  _seenEventIds: {},
  _storedEventIds: {},
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

    // Ids that belong to this visitor alone are remembered for the session, so
    // a script a page replays on a later load is not queued again. Ids rendered
    // into cacheable markup are never stored: a cached copy repeats them for
    // every visitor and every load.
    var stored = {};
    try {
      var raw = window.sessionStorage.getItem('fbpix_seen_event_ids');
      var parsed = raw ? JSON.parse(raw) : null;
      if (parsed && typeof parsed === 'object' && !Array.isArray(parsed)) {
        stored = parsed;
      }
    } catch (e) {}
    this._storedEventIds = stored;
    for (var storedId in stored) {
      if (Object.prototype.hasOwnProperty.call(stored, storedId)) {
        this._seenEventIds[storedId] = 1;
      }
    }
  },

  // True when the page HTML is older than the configured threshold, which
  // means it was served from a page cache rather than generated for this
  // request. The age is measured from the moment the browser requested the
  // page, not from when the check runs, so a visitor who generated the page
  // stays fresh however long they take before an event fires, such as
  // granting consent. Pages no cache stores carry no generation time and are
  // never stale.
  isStalePage: function () {
    var generatedAt = this._config.generatedAt;
    var staleAfter = this._config.staleAfter;
    if (!generatedAt || typeof staleAfter !== 'number') {
      return false;
    }
    return (
      Math.floor(this._navigationStart() / 1000) - generatedAt > staleAfter
    );
  },

  // Milliseconds since the epoch at which the browser requested this page.
  _navigationStart: function () {
    try {
      var perf = window.performance;
      if (perf) {
        if (perf.timeOrigin) {
          return perf.timeOrigin;
        }
        if (perf.timing && perf.timing.navigationStart) {
          return perf.timing.navigationStart;
        }
      }
    } catch (e) {}
    return Date.now();
  },

  // Inserts "_c" after the hosting flag in the agent string when the page
  // is stale, e.g. "wordpress_0-6.8-5.2.2" -> "wordpress_0_c-6.8-5.2.2".
  // An agent that already carries the flag is returned unchanged.
  tagAgent: function (agent) {
    if (agent && this.isStalePage()) {
      agent = agent.replace(/(wordpress_[0-7])(?!_c)/, '$1_c');
    }
    return agent;
  },

  initPixel: function (pixelId, userInfo, options) {
    var stale = this.isStalePage();
    this._pixelId = pixelId;
    // Drop embedded user info on cached pages, since it may belong to the
    // visitor the page was originally generated for.
    this._pixelUserInfo =
      !stale &&
      userInfo &&
      typeof userInfo === 'object' &&
      !Array.isArray(userInfo)
        ? userInfo
        : {};
    this._pixelOptions = options || {};
    if (this._pixelOptions.agent) {
      this._pixelOptions.agent = this.tagAgent(this._pixelOptions.agent);
    }
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

  // Takes an event PHP rendered into the page. While signals are held the
  // event waits for the release; otherwise it fires right away, which a
  // cached copy of a page generated while held needs when the visitor who
  // receives it has already granted consent.
  queueEvent: function (eventData) {
    if (!eventData || !eventData.event_name) {
      return;
    }

    var originalId = eventData.event_id || null;
    if (originalId && this._seenEventIds[originalId]) {
      return;
    }

    // An id rendered into cacheable markup is shared by every visitor who
    // receives a cached copy, and no server event of theirs carries it, so it
    // is never sent as eventID and is remembered for this page load only, to
    // tell re-executions of the same script apart. An id delivered by a live
    // request, such as a form plugin's AJAX response, belongs to this visitor
    // alone: it is sent, and remembered for the session.
    var shared = eventData.cacheable !== false;
    var method =
      eventData.method || (eventData.is_custom ? 'trackCustom' : 'track');

    if (!this._held) {
      if (originalId) {
        this._rememberEventId(originalId, !shared);
      }
      this._fireOrQueuePixelEvent(
        eventData.event_name,
        eventData.custom_data || {},
        method,
        shared ? null : originalId,
      );
      return;
    }

    var stale = shared && this.isStalePage();

    // The queued copy is this visitor's own, so the caller's object is left
    // alone. The release endpoint sends the queued id to the Conversions API
    // and the browser fires it after the release, so a shared id is replaced
    // by one minted here that only this visitor's pair of events will carry.
    eventData = this._cloneEventData(eventData);
    if (shared) {
      eventData.event_id = this._generateEventId();
    }
    // A cached copy carries the generating request's event_time. The release
    // endpoint rejects events older than 30 minutes, and the visitor's own
    // view time is the right one anyway, so it comes from the browser clock.
    eventData.event_time =
      (!stale && eventData.event_time) || Math.floor(Date.now() / 1000);
    eventData.event_source_url =
      eventData.event_source_url || window.location.href;
    this._queue.push(eventData);

    var idToMark = originalId || eventData.event_id;
    if (idToMark) {
      this._rememberEventId(idToMark, !shared && !!originalId);
    }
  },

  _cloneEventData: function (eventData) {
    var copy = {};
    for (var key in eventData) {
      if (Object.prototype.hasOwnProperty.call(eventData, key)) {
        copy[key] = eventData[key];
      }
    }
    return copy;
  },

  _generateEventId: function () {
    try {
      if (window.crypto && window.crypto.randomUUID) {
        return window.crypto.randomUUID();
      }
    } catch (e) {}
    return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(
      /[xy]/g,
      function (c) {
        var r = (Math.random() * 16) | 0;
        return (c === 'x' ? r : (r & 0x3) | 0x8).toString(16);
      },
    );
  },

  // Remembers an id for this page load, and for the session when it belongs
  // to this visitor alone.
  _rememberEventId: function (id, persist) {
    this._seenEventIds[id] = 1;
    if (!persist) {
      return;
    }
    this._storedEventIds[id] = 1;
    try {
      window.sessionStorage.setItem(
        'fbpix_seen_event_ids',
        JSON.stringify(this._storedEventIds),
      );
    } catch (e) {}
  },

  trackEvent: function (name, params, userData, method, eventId, cacheable) {
    method = method || 'track';
    eventId = eventId || (params && params.eventID) || null;
    // Callers that say nothing count as cacheable markup, whose ids are never
    // remembered across page loads and are dropped on a stale copy.
    var shared = cacheable !== false;

    if (this._held) {
      this.queueEvent({
        event_name: name,
        custom_data: params || {},
        event_id: eventId,
        event_time: Math.floor(Date.now() / 1000),
        method: method,
        cacheable: shared,
      });
      return;
    }

    if (eventId) {
      if (shared) {
        if (this.isStalePage()) {
          eventId = null;
        }
      } else {
        // A live id identifies one occurrence, so a script the page replays
        // on a later load in this session is not fired again.
        if (this._seenEventIds[eventId]) {
          return;
        }
        this._rememberEventId(eventId, true);
      }
    }
    this._fireOrQueuePixelEvent(name, params || {}, method, eventId);
  },

  hold: function () {
    this._held = true;
    if (typeof fbq === 'function') {
      fbq('consent', 'revoke');
    }
  },

  release: function () {
    var self = this;

    if (!self._held || self._releasing || !self._config.ajaxUrl) {
      return Promise.resolve({ success: true, data: { sent_count: 0 } });
    }

    self._releasing = true;

    return new Promise(function (resolve, reject) {
      var xhr = new XMLHttpRequest();
      var url =
        self._config.ajaxUrl +
        (self._config.ajaxUrl.indexOf('?') === -1 ? '?' : '&') +
        'action=' +
        encodeURIComponent(self._config.releaseAction);

      xhr.open('POST', url, true);
      xhr.setRequestHeader('Content-Type', 'application/json');
      xhr.onload = function () {
        if (xhr.status < 200 || xhr.status >= 300) {
          self._releasing = false;
          reject(new Error('Release AJAX failed: ' + xhr.status));
          return;
        }

        try {
          var response = JSON.parse(xhr.responseText);
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
          events: self._queue,
          fbclid: self._fbclid,
          fbp: self._readCookie('_fbp') || attribution.fbp || null,
          fbc: self._readCookie('_fbc') || attribution.fbc || null,
        }),
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

  function updateState(state) {
    var normalizedState = state === 'active' ? 'active' : 'held';
    setCookie(normalizedState);

    return new Promise(function (resolve, reject) {
      var xhr = new XMLHttpRequest();
      xhr.open('POST', ajaxUrl, true);
      xhr.setRequestHeader(
        'Content-Type',
        'application/x-www-form-urlencoded; charset=UTF-8',
      );
      xhr.onload = function () {
        if (xhr.status < 200 || xhr.status >= 300) {
          reject(new Error('Signals AJAX failed: ' + xhr.status));
          return;
        }

        try {
          var response = JSON.parse(xhr.responseText);
          if (normalizedState === 'held') {
            window.FacebookSignal.hold();
            resolve(response);
            return;
          }

          if (window.FacebookSignal && window.FacebookSignal._held) {
            window.FacebookSignal.release().then(
              function () {
                resolve(response);
              },
              function (error) {
                reject(error);
              },
            );
            return;
          }

          resolve(response);
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
    return updateState('held');
  };

  api.release = function () {
    return updateState('active');
  };

  api.getState = function () {
    var value = getCookie();
    if (value === 'active' || value === 'held') {
      return value;
    }
    return null;
  };
})(window.fbwcsignal);
