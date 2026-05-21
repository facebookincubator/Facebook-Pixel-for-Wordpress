window.FacebookSignal = window.FacebookSignal || {
  _held: false,
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
    this._held = !!this._config.held;

    try {
      var raw = window.sessionStorage.getItem('fbpix_seen_event_ids');
      this._seenEventIds = raw ? JSON.parse(raw) : {};
    } catch (e) {
      this._seenEventIds = this._seenEventIds || {};
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

  trackEvent: function (name, params, userData) {
    var eventParams = params ? Object.assign({}, params) : {};
    var eventId = eventParams.eventID || null;

    if (eventId) {
      delete eventParams.eventID;
    }

    if (this._held) {
      this.queueEvent({
        event_name: name,
        custom_data: eventParams,
        user_data: userData || null,
        event_id: eventId,
      });
      return;
    }

    if (eventId) {
      fbq('track', name, eventParams, { eventID: eventId });
    } else {
      fbq('track', name, eventParams);
    }
  },

  hold: function () {
    this._held = true;
    fbq('consent', 'revoke');
  },

  release: function () {
    var self = this;

    if (!self._held || !self._config.ajaxUrl) {
      return Promise.resolve({ success: true, data: { sent_count: 0 } });
    }

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
          reject(new Error('Release AJAX failed: ' + xhr.status));
          return;
        }

        try {
          var response = JSON.parse(xhr.responseText);
          self._handleReleaseResponse(response.data || {});
          resolve(response);
        } catch (error) {
          reject(error);
        }
      };
      xhr.onerror = function () {
        reject(new Error('Network error'));
      };
      var attribution = self._config.attribution || {};
      xhr.send(
        JSON.stringify({
          security: self._config.releaseNonce,
          events: self._queue,
          fbclid: self._fbclid,
          fbp: self._readCookie('_fbp') || attribution.fbp || null,
          fbc: self._readCookie('_fbc') || attribution.fbc || null,
        }),
      );
    });
  },

  _handleReleaseResponse: function (data) {
    if (data.fbp) {
      document.cookie =
        '_fbp=' +
        encodeURIComponent(data.fbp) +
        ';path=/;max-age=7776000;SameSite=Lax';
    }

    if (data.fbc) {
      document.cookie =
        '_fbc=' +
        encodeURIComponent(data.fbc) +
        ';path=/;max-age=7776000;SameSite=Lax';
    }

    fbq('consent', 'grant');

    for (var i = 0; i < this._queue.length; i++) {
      var queuedEvent = this._queue[i];
      var customData = queuedEvent.custom_data || {};
      var trackMethod = queuedEvent.is_custom ? 'trackCustom' : 'track';
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

  api.setState = function (state) {
    var granted = state === 'active';
    var cookieValue = granted ? 'active' : 'held';
    setCookie(cookieValue);

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
          if (!granted) {
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
          '&granted=' +
          encodeURIComponent(granted ? '1' : '0'),
      );
    });
  };

  api.hold = function () {
    return api.setState('held');
  };

  api.release = function () {
    return api.setState('active');
  };

  api.getState = function () {
    var value = getCookie();
    if (value === null) {
      return null;
    }
    return value === 'active' ? 'active' : 'held';
  };
})(window.fbwcsignal);
