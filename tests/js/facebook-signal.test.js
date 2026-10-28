/**
 * Copyright (C) 2017-present, Meta, Inc.
 *
 * This source code is licensed under the license found in the
 * LICENSE file in the root directory of this source tree.
 */

/**
 * Unit tests for js/facebook_signal.js, the FacebookSignal runtime that holds,
 * queues and releases Pixel events and tells a page served from a full-page
 * cache from one generated for the request.
 *
 * Runs on Node's built-in test runner (`node --test tests/js`) with no DOM
 * library: the script only touches window.location, window.sessionStorage,
 * window.performance, window.crypto, document.cookie, fbq and XMLHttpRequest,
 * all stubbed below. fbq stays a recorder, so every call the runtime makes can
 * be read back verbatim.
 */

'use strict';

const { test, describe, beforeEach, afterEach } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const SOURCE = fs.readFileSync(
  path.resolve(__dirname, '../../js/facebook_signal.js'),
  'utf8',
);

const NOW = 1800000000; // seconds
const AGENT = 'wordpress_0-6.8-5.2.2';
const CACHED_AGENT = 'wordpress_0_c-6.8-5.2.2';
const STORAGE_KEY = 'fbpix_seen_event_ids';
const PIXEL_ID = '123';

let fbqCalls;
let xhrRequests;
let navigationStartMs;
const realDateNow = Date.now;

function makeSessionStorage(initial) {
  const map = new Map(Object.entries(initial || {}));
  return {
    getItem: (key) => (map.has(key) ? map.get(key) : null),
    setItem: (key, value) => map.set(key, String(value)),
    removeItem: (key) => map.delete(key),
    clear: () => map.clear(),
  };
}

class FakeXMLHttpRequest {
  open(method, url) {
    this.method = method;
    this.url = url;
  }
  setRequestHeader() {}
  send(body) {
    xhrRequests.push({
      method: this.method,
      url: this.url,
      body: body ? JSON.parse(body) : null,
    });
    this.status = 200;
    this.responseText = JSON.stringify(FakeXMLHttpRequest.response);
    if (this.onload) {
      this.onload();
    }
  }
}
FakeXMLHttpRequest.response = { success: true, data: { sent_count: 0 } };

/**
 * Loads a fresh FacebookSignal the way the page does: the script runs, then
 * PHP's inline init() call follows with the given config.
 *
 * @param {Object} options
 * @param {Object} options.config  Init config fields, e.g. generatedAt and staleAfter.
 * @param {string} options.cookie  document.cookie at load time.
 * @param {Object} options.stored  Ids already in sessionStorage.
 * @param {Object} options.performance window.performance to expose.
 * @return {Object} window.FacebookSignal
 */
function loadSignal({
  config = {},
  cookie = '',
  stored = null,
  performance,
} = {}) {
  global.window = {
    location: {
      search: '',
      href: 'https://example.test/page/',
      origin: 'https://example.test',
    },
    sessionStorage: makeSessionStorage(
      stored ? { [STORAGE_KEY]: JSON.stringify(stored) } : {},
    ),
    performance: performance || { timeOrigin: navigationStartMs },
    crypto: globalThis.crypto,
    facebookSignalConfig: {},
  };
  global.document = { cookie };
  global.fbq = function () {
    fbqCalls.push(Array.from(arguments));
  };
  global.XMLHttpRequest = FakeXMLHttpRequest;

  vm.runInThisContext(SOURCE, { filename: 'facebook_signal.js' });

  const signal = window.FacebookSignal;
  signal.init(
    Object.assign(
      {
        held: false,
        ajaxUrl: '/wp-admin/admin-ajax.php',
        releaseAction: 'fbpix_release_signals',
        attribution: {},
      },
      config,
    ),
  );
  return signal;
}

/**
 * Loads the runtime for a page with the given generation time and threshold
 * and runs the inline initPixel() call.
 */
function loadPage({
  generatedAt,
  staleAfter = 60,
  userInfo = {},
  cookie = '',
  stored = null,
  performance,
} = {}) {
  const config = {};
  if (generatedAt !== undefined) {
    config.generatedAt = generatedAt;
  }
  if (staleAfter !== undefined) {
    config.staleAfter = staleAfter;
  }
  const signal = loadSignal({ config, cookie, stored, performance });
  signal.initPixel(PIXEL_ID, userInfo, { agent: AGENT });
  return signal;
}

function trackCalls() {
  return fbqCalls.filter(
    (call) => call[0] === 'track' || call[0] === 'trackCustom',
  );
}

function initCalls() {
  return fbqCalls.filter((call) => call[0] === 'init');
}

function storedIds() {
  const raw = window.sessionStorage.getItem(STORAGE_KEY);
  return raw ? JSON.parse(raw) : null;
}

beforeEach(() => {
  fbqCalls = [];
  xhrRequests = [];
  navigationStartMs = NOW * 1000;
  Date.now = () => NOW * 1000;
  FakeXMLHttpRequest.response = { success: true, data: { sent_count: 0 } };
});

afterEach(() => {
  Date.now = realDateNow;
  delete global.window;
  delete global.document;
  delete global.fbq;
  delete global.XMLHttpRequest;
});

describe('isStalePage', () => {
  test('treats the page as fresh when it was generated for this request', () => {
    // PHP stamps the page slightly after the browser starts the navigation.
    const signal = loadSignal({
      config: { generatedAt: NOW + 1, staleAfter: 60 },
    });
    assert.equal(signal.isStalePage(), false);
  });

  test('treats the page as fresh within the threshold', () => {
    const signal = loadSignal({
      config: { generatedAt: NOW - 60, staleAfter: 60 },
    });
    assert.equal(signal.isStalePage(), false);
  });

  test('treats the page as stale once it is older than the threshold', () => {
    const signal = loadSignal({
      config: { generatedAt: NOW - 61, staleAfter: 60 },
    });
    assert.equal(signal.isStalePage(), true);
  });

  test('measures the age from the navigation, not from when the check runs', () => {
    // The visitor requested a fresh page and then sat on it for ten minutes.
    navigationStartMs = (NOW - 600) * 1000;
    const signal = loadSignal({
      config: { generatedAt: NOW - 600, staleAfter: 60 },
    });
    assert.equal(signal.isStalePage(), false);
  });

  test('honours a threshold of zero', () => {
    assert.equal(
      loadSignal({ config: { generatedAt: NOW, staleAfter: 0 } }).isStalePage(),
      false,
    );
    assert.equal(
      loadSignal({
        config: { generatedAt: NOW - 1, staleAfter: 0 },
      }).isStalePage(),
      true,
    );
  });

  test('falls back to the legacy navigation timing API', () => {
    const signal = loadSignal({
      config: { generatedAt: NOW - 120, staleAfter: 60 },
      performance: { timing: { navigationStart: NOW * 1000 } },
    });
    assert.equal(signal.isStalePage(), true);
  });

  test('reports fresh when the page carries no generation time or threshold', () => {
    assert.equal(
      loadSignal({ config: { staleAfter: 60 } }).isStalePage(),
      false,
    );
    assert.equal(
      loadSignal({ config: { generatedAt: NOW - 3600 } }).isStalePage(),
      false,
    );
    assert.equal(loadSignal({ config: {} }).isStalePage(), false);
  });
});

describe('tagAgent', () => {
  test('leaves the agent alone on a fresh page', () => {
    const signal = loadSignal({ config: { generatedAt: NOW, staleAfter: 60 } });
    assert.equal(signal.tagAgent(AGENT), AGENT);
  });

  test('inserts _c after the hosting flag on a stale page', () => {
    const signal = loadSignal({
      config: { generatedAt: NOW - 3600, staleAfter: 60 },
    });
    assert.equal(signal.tagAgent(AGENT), CACHED_AGENT);
    assert.equal(
      signal.tagAgent('wordpress_1-6.8-5.2.2'),
      'wordpress_1_c-6.8-5.2.2',
    );
    assert.equal(
      signal.tagAgent('wordpress_0_fbl4b-6.8-5.2.2'),
      'wordpress_0_c_fbl4b-6.8-5.2.2',
    );
  });

  test('does not tag an agent twice', () => {
    const signal = loadSignal({
      config: { generatedAt: NOW - 3600, staleAfter: 60 },
    });
    assert.equal(signal.tagAgent(CACHED_AGENT), CACHED_AGENT);
    assert.equal(signal.tagAgent(signal.tagAgent(AGENT)), CACHED_AGENT);
    assert.equal(
      signal.tagAgent('wordpress_0_c_fbl4b-6.8-5.2.2'),
      'wordpress_0_c_fbl4b-6.8-5.2.2',
    );
  });

  test('returns an empty agent unchanged', () => {
    const signal = loadSignal({
      config: { generatedAt: NOW - 3600, staleAfter: 60 },
    });
    assert.equal(signal.tagAgent(''), '');
    assert.equal(signal.tagAgent(undefined), undefined);
  });
});

describe('initPixel', () => {
  test('keeps the agent and matching data for a page generated for this request', () => {
    loadPage({ generatedAt: NOW, userInfo: { em: 'a@b.c' } });
    assert.deepEqual(initCalls(), [
      ['init', PIXEL_ID, { em: 'a@b.c' }, { agent: AGENT }],
    ]);
  });

  test('flags the agent and drops the matching data for a page served from a cache', () => {
    loadPage({ generatedAt: NOW - 3600, userInfo: { em: 'a@b.c' } });
    assert.deepEqual(initCalls(), [
      ['init', PIXEL_ID, {}, { agent: CACHED_AGENT }],
    ]);
  });

  test('keeps matching data the release endpoint supplied, even on a cached page', async () => {
    const signal = loadPage({
      generatedAt: NOW - 3600,
      userInfo: { em: 'stale@b.c' },
      cookie: 'wc_facebook_signals_state=held',
    });
    assert.equal(initCalls().length, 0);
    FakeXMLHttpRequest.response = {
      success: true,
      data: { sent_count: 0, user_info: { em: 'live@b.c' } },
    };
    await signal.release();
    assert.deepEqual(initCalls(), [
      ['init', PIXEL_ID, { em: 'live@b.c' }, { agent: CACHED_AGENT }],
    ]);
  });
});

describe('queueEvent while signals are active', () => {
  test('fires right away and never sends an id rendered into cacheable markup', () => {
    const signal = loadPage({ generatedAt: NOW });
    signal.queueEvent({
      event_name: 'ViewContent',
      custom_data: { content_ids: ['1'] },
      event_id: 'baked-id',
      cacheable: true,
    });
    assert.deepEqual(trackCalls(), [
      ['track', 'ViewContent', { content_ids: ['1'] }],
    ]);
  });

  test('treats an event without the flag as rendered into cacheable markup', () => {
    const signal = loadPage({ generatedAt: NOW });
    signal.queueEvent({
      event_name: 'ViewContent',
      custom_data: {},
      event_id: 'baked-id',
    });
    assert.deepEqual(trackCalls(), [['track', 'ViewContent', {}]]);
    assert.equal(storedIds(), null);
  });

  test('uses trackCustom for a custom event', () => {
    const signal = loadPage({ generatedAt: NOW });
    signal.queueEvent({
      event_name: 'MyEvent',
      custom_data: {},
      event_id: 'baked-id',
      is_custom: true,
    });
    assert.deepEqual(trackCalls(), [['trackCustom', 'MyEvent', {}]]);
  });

  test('fires only once per page for the same rendered id', () => {
    const signal = loadPage({ generatedAt: NOW });
    const event = {
      event_name: 'PageView',
      custom_data: {},
      event_id: 'baked-id',
      cacheable: true,
    };
    signal.queueEvent(event);
    signal.queueEvent(Object.assign({}, event));
    assert.equal(trackCalls().length, 1);
    assert.equal(storedIds(), null);
  });

  test('sends an id delivered by a live request and remembers it for the session', () => {
    const signal = loadPage({ generatedAt: NOW });
    signal.queueEvent({
      event_name: 'Lead',
      custom_data: {},
      event_id: 'live-id',
      cacheable: false,
    });
    assert.deepEqual(trackCalls(), [
      ['track', 'Lead', {}, { eventID: 'live-id' }],
    ]);
    assert.deepEqual(storedIds(), { 'live-id': 1 });
  });

  test('skips a live id remembered from an earlier page load', () => {
    const signal = loadPage({ generatedAt: NOW, stored: { 'live-id': 1 } });
    signal.queueEvent({
      event_name: 'Lead',
      custom_data: {},
      event_id: 'live-id',
      cacheable: false,
    });
    assert.equal(trackCalls().length, 0);
  });

  test('ignores unusable stored ids', () => {
    const signal = loadSignal({ config: { generatedAt: NOW, staleAfter: 60 } });
    window.sessionStorage.setItem(STORAGE_KEY, '"not an object"');
    signal.init({ generatedAt: NOW, staleAfter: 60 });
    assert.deepEqual(signal._storedEventIds, {});
  });
});

describe('queueEvent while signals are held', () => {
  const HELD = 'wc_facebook_signals_state=held';

  test('replaces a rendered id with one minted for this visitor and keeps the server time on a fresh page', () => {
    const signal = loadPage({ generatedAt: NOW, cookie: HELD });
    signal.queueEvent({
      event_name: 'ViewContent',
      custom_data: {},
      event_id: 'baked-id',
      event_time: NOW - 2,
      cacheable: true,
    });
    assert.equal(signal._queue.length, 1);
    assert.notEqual(signal._queue[0].event_id, 'baked-id');
    assert.match(signal._queue[0].event_id, /^[0-9a-f-]{36}$/);
    assert.equal(signal._queue[0].event_time, NOW - 2);
    assert.equal(storedIds(), null);
  });

  test('takes the event time from the browser clock on a cached copy', () => {
    const signal = loadPage({ generatedAt: NOW - 3600, cookie: HELD });
    signal.queueEvent({
      event_name: 'ViewContent',
      custom_data: {},
      event_id: 'baked-id',
      event_time: NOW - 3600,
      cacheable: true,
    });
    assert.equal(signal._queue[0].event_time, NOW);
    assert.notEqual(signal._queue[0].event_id, 'baked-id');
  });

  test('keeps the id and time of a live request and remembers the id for the session', () => {
    const signal = loadPage({ generatedAt: NOW - 3600, cookie: HELD });
    signal.queueEvent({
      event_name: 'Lead',
      custom_data: {},
      event_id: 'live-id',
      event_time: NOW - 3600,
      cacheable: false,
    });
    assert.equal(signal._queue[0].event_id, 'live-id');
    assert.equal(signal._queue[0].event_time, NOW - 3600);
    assert.deepEqual(storedIds(), { 'live-id': 1 });
  });

  test('queues the same rendered id only once per page', () => {
    const signal = loadPage({ generatedAt: NOW, cookie: HELD });
    const event = {
      event_name: 'PageView',
      custom_data: {},
      event_id: 'baked-id',
      cacheable: true,
    };
    signal.queueEvent(event);
    signal.queueEvent(Object.assign({}, event));
    assert.equal(signal._queue.length, 1);
  });

  test('posts the minted ids on release and fires the Pixel with the same ids', async () => {
    const signal = loadPage({ generatedAt: NOW - 3600, cookie: HELD });
    signal.queueEvent({
      event_name: 'PageView',
      custom_data: {},
      event_id: null,
      event_time: NOW - 3600,
      cacheable: true,
    });
    signal.queueEvent({
      event_name: 'ViewContent',
      custom_data: { content_ids: ['1'] },
      event_id: 'baked-id',
      event_time: NOW - 3600,
      cacheable: true,
    });
    const queuedIds = signal._queue.map((event) => event.event_id);
    FakeXMLHttpRequest.response = { success: true, data: { sent_count: 2 } };

    await signal.release();

    assert.equal(xhrRequests.length, 1);
    assert.match(xhrRequests[0].url, /action=fbpix_release_signals/);
    assert.deepEqual(
      xhrRequests[0].body.events.map((event) => event.event_id),
      queuedIds,
    );
    assert.deepEqual(
      xhrRequests[0].body.events.map((event) => event.event_time),
      [NOW, NOW],
    );
    assert.deepEqual(
      xhrRequests[0].body.events.map((event) => event.event_age),
      [0, 0],
    );
    assert.equal(xhrRequests[0].body.events[0].queued_at, undefined);
    assert.deepEqual(trackCalls(), [
      ['track', 'PageView', {}, { eventID: queuedIds[0] }],
      [
        'track',
        'ViewContent',
        { content_ids: ['1'] },
        { eventID: queuedIds[1] },
      ],
    ]);
    assert.equal(signal._held, false);
    assert.equal(signal._queue.length, 0);
  });

  test('reports how long ago each event was queued, on the browser clock alone', async () => {
    // The browser clock runs two hours ahead of the server. Only the time that
    // passes between queueing and release reaches the server, so the server
    // can place the events on its own clock.
    const SKEW = 7200;
    Date.now = () => (NOW + SKEW) * 1000;
    const signal = loadPage({ generatedAt: NOW - 3600, cookie: HELD });
    signal.queueEvent({
      event_name: 'PageView',
      custom_data: {},
      event_id: null,
      event_time: NOW - 3600,
      cacheable: true,
    });
    Date.now = () => (NOW + SKEW + 125) * 1000;
    signal.queueEvent({
      event_name: 'ViewContent',
      custom_data: {},
      event_id: 'baked-id',
      event_time: NOW - 3600,
      cacheable: true,
    });
    Date.now = () => (NOW + SKEW + 130) * 1000;

    await signal.release();

    const events = xhrRequests[0].body.events;
    assert.deepEqual(
      events.map((event) => event.event_age),
      [130, 5],
    );
    assert.deepEqual(
      events.map((event) => event.event_time),
      [NOW + SKEW, NOW + SKEW + 125],
    );
    assert.equal(events[0].queued_at, undefined);
    assert.equal(signal._queue.length, 0);
  });
});

describe('trackEvent', () => {
  test('queues the event with its cacheability while held', () => {
    const signal = loadPage({
      generatedAt: NOW,
      cookie: 'wc_facebook_signals_state=held',
    });
    signal.trackEvent('AddToCart', { value: 1 }, null, 'track', 'baked-id');
    signal.trackEvent('Lead', {}, null, 'track', 'live-id', false);
    assert.equal(signal._queue.length, 2);
    assert.notEqual(signal._queue[0].event_id, 'baked-id');
    assert.equal(signal._queue[0].cacheable, true);
    assert.equal(signal._queue[1].event_id, 'live-id');
    assert.equal(signal._queue[1].cacheable, false);
    assert.deepEqual(storedIds(), { 'live-id': 1 });
  });

  test('fires a live id once per session', () => {
    let signal = loadPage({ generatedAt: NOW });
    signal.trackEvent('Lead', {}, null, 'track', 'live-id', false);
    signal.trackEvent('Lead', {}, null, 'track', 'live-id', false);
    assert.equal(trackCalls().length, 1);
    assert.deepEqual(storedIds(), { 'live-id': 1 });

    // A later page load in the same session.
    const stored = storedIds();
    fbqCalls = [];
    signal = loadPage({ generatedAt: NOW, stored });
    signal.trackEvent('Lead', {}, null, 'track', 'live-id', false);
    assert.equal(trackCalls().length, 0);
  });

  test('keeps a rendered id on a fresh page and drops it on a cached copy', () => {
    let signal = loadPage({ generatedAt: NOW });
    signal.trackEvent('AddToCart', { eventID: 'baked-id' }, null, 'track');
    assert.deepEqual(trackCalls(), [
      ['track', 'AddToCart', { eventID: 'baked-id' }, { eventID: 'baked-id' }],
    ]);
    assert.equal(storedIds(), null);

    fbqCalls = [];
    signal = loadPage({ generatedAt: NOW - 3600 });
    signal.trackEvent('AddToCart', { eventID: 'baked-id' }, null, 'track');
    assert.deepEqual(trackCalls(), [
      ['track', 'AddToCart', { eventID: 'baked-id' }],
    ]);
  });
});
