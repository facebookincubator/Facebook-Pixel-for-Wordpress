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
    if (!this._pixelInitialized) {
      this._pendingPixelEvents.push({
        name: name,
        params: params || {},
        method: method || 'track',
        eventId: eventId || null,
      });
      return;
    }
    this._firePixelEvent(name, params || {}, method || 'track', eventId || null);
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
    this._held = true;
    fbq('consent', 'revoke');
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
        '_fbp=' + encodeURIComponent(fbpValue) + ';path=/;max-age=7776000;SameSite=Lax';
      if (attr.fbpDomain) {
        fbpCookie += ';domain=' + attr.fbpDomain;
      }
      document.cookie = fbpCookie;
    }

    if (fbcValue) {
      var fbcCookie =
        '_fbc=' + encodeURIComponent(fbcValue) + ';path=/;max-age=7776000;SameSite=Lax';
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

    fbq('consent', 'grant');

    for (var i = 0; i < this._queue.length; i++) {
      var queuedEvent = this._queue[i];
      var customData = queuedEvent.custom_data || {};
      var trackMethod = queuedEvent.method || (queuedEvent.is_custom ? 'trackCustom' : 'track');
      if (queuedEvent.event_id) {
        fbq(trackMethod, queuedEvent.event_name, customData, {
          eventID: queuedEvent.event_id,
        });
      } else {
        fbq(trackMethod, queuedEvent.event_name, customData);
      }
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
