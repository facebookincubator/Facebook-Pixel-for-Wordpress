/**
 * Copyright (C) 2017-present, Meta, Inc.
 *
 * This source code is licensed under the license found in the
 * LICENSE file in the root directory of this source tree.
 */

/**
 * Unit tests for js/facebook_pixel_add_to_cart.js, the Easy Digital Downloads
 * add-to-cart listener.
 *
 * The script fires the Pixel AddToCart with the event id rendered into the
 * form, which the form also posts to the server for the Conversions API event.
 * On a page served from a cache that id is shared by every visitor, so the
 * script must replace it before anything fires or posts. jQuery is replaced by
 * a stand-in that supports only the calls the script makes, and the
 * FacebookSignal runtime by a recorder.
 */

'use strict';

const { test, describe, beforeEach, afterEach } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const SOURCE = fs.readFileSync(
  path.resolve(__dirname, '../../js/facebook_pixel_add_to_cart.js'),
  'utf8',
);

const AGENT = 'wordpress_0-6.8-5.2.2';
const CACHED_AGENT = 'wordpress_0_c-6.8-5.2.2';
const INPUT_SELECTOR = "input[name='facebook_event_id']";

let fbqCalls;
let trackEventCalls;

/**
 * A page with one add-to-cart form per download, each carrying the hidden
 * event id input the server renders.
 */
function createPage(eventIds) {
  const forms = eventIds.map((eventId, index) => {
    const input = { name: 'facebook_event_id', value: eventId };
    const form = { inputs: [input] };
    const button = {
      form,
      data: {
        'download-id': String(100 + index),
        'variable-price': 'no',
        price: 10,
      },
    };
    return { form, input, button };
  });
  return {
    forms,
    inputs: forms.map((entry) => entry.input),
    buttons: forms.map((entry) => entry.button),
    clickHandlers: [],
  };
}

/** The subset of jQuery the script uses, over the page model above. */
function makeJQuery(page) {
  const collection = (nodes) => ({
    each: (fn) => nodes.forEach((node) => fn.call(node)),
    val: (value) => {
      if (value === undefined) {
        return nodes.length ? nodes[0].value : undefined;
      }
      nodes.forEach((node) => {
        node.value = value;
      });
    },
  });
  const formWrapper = (form) => ({
    find: (selector) =>
      selector.indexOf(INPUT_SELECTOR) === 0
        ? collection(form.inputs)
        : collection([]),
  });
  const buttonWrapper = (button) => ({
    closest: () => formWrapper(button.form),
    parents: () => ({ last: () => formWrapper(button.form) }),
    data: (key) => button.data[key],
  });

  const $ = (selector) => {
    if (selector === global.document) {
      return { ready: (fn) => fn($) };
    }
    if (selector === '.edd-add-to-cart') {
      return { click: (fn) => page.clickHandlers.push(fn) };
    }
    if (
      typeof selector === 'string' &&
      selector.indexOf(INPUT_SELECTOR) === 0
    ) {
      return collection(page.inputs);
    }
    if (
      typeof selector === 'string' &&
      selector.indexOf('.edd_purchase_') === 0
    ) {
      return { attr: () => 'USD' };
    }
    if (selector && selector.name === 'facebook_event_id') {
      return collection([selector]);
    }
    if (selector && selector.form) {
      return buttonWrapper(selector);
    }
    throw new Error('Unexpected jQuery call: ' + String(selector));
  };
  return $;
}

/**
 * Loads the script against a page, with the FacebookSignal runtime replaced
 * by a recorder, or absent when `signal` is null.
 */
function loadScript(page, signal) {
  global.document = {};
  global.window = signal === null ? {} : { FacebookSignal: signal };
  global.jQuery = makeJQuery(page);
  global.facebookPixelData = {
    fbIntegrationKey: 'easy-digital-downloads',
    trackingName: 'easy-digital-downloads',
    agentString: AGENT,
    pixelId: '123',
  };
  global.fbq = function () {
    fbqCalls.push(Array.from(arguments));
  };
  vm.runInThisContext(SOURCE, { filename: 'facebook_pixel_add_to_cart.js' });
}

function makeSignal({ stale, held = false }) {
  let minted = 0;
  return {
    _held: held,
    isStalePage: () => stale,
    tagAgent: (agent) =>
      stale ? agent.replace(/(wordpress_[0-7])(?!_c)/, '$1_c') : agent,
    generateEventId: () => 'minted-' + ++minted,
    trackEvent: function () {
      trackEventCalls.push(Array.from(arguments));
    },
  };
}

function click(page, index) {
  page.clickHandlers[0].call(page.buttons[index], { preventDefault() {} });
}

function addToCartCalls() {
  return fbqCalls.filter(
    (call) => call[0] === 'track' && call[1] === 'AddToCart',
  );
}

beforeEach(() => {
  fbqCalls = [];
  trackEventCalls = [];
});

afterEach(() => {
  delete global.document;
  delete global.window;
  delete global.jQuery;
  delete global.facebookPixelData;
  delete global.fbq;
});

describe('on a page generated for this request', () => {
  test('keeps the rendered id in the form and fires it', () => {
    const page = createPage(['baked-1', 'baked-2']);
    loadScript(page, makeSignal({ stale: false }));

    assert.deepEqual(
      page.inputs.map((input) => input.value),
      ['baked-1', 'baked-2'],
    );

    click(page, 1);
    assert.deepEqual(fbqCalls[0], ['set', 'agent', AGENT, '123']);
    assert.equal(addToCartCalls().length, 1);
    assert.deepEqual(addToCartCalls()[0][3], { eventID: 'baked-2' });
    assert.equal(addToCartCalls()[0][2].content_ids[0], '101');
  });
});

describe('on a page served from a cache', () => {
  test('replaces every rendered id before any click, so the form posts the minted id', () => {
    const page = createPage(['baked-1', 'baked-2']);
    loadScript(page, makeSignal({ stale: true }));

    assert.deepEqual(
      page.inputs.map((input) => input.value),
      ['minted-1', 'minted-2'],
    );
    assert.equal(page.clickHandlers.length, 1);
  });

  test('fires the Pixel with the same minted id the form now carries', () => {
    const page = createPage(['baked-1', 'baked-2']);
    loadScript(page, makeSignal({ stale: true }));

    click(page, 1);
    assert.deepEqual(fbqCalls[0], ['set', 'agent', CACHED_AGENT, '123']);
    assert.deepEqual(addToCartCalls()[0][3], { eventID: 'minted-2' });
    assert.equal(page.inputs[1].value, 'minted-2');
  });

  test('queues the minted id while signals are held', () => {
    const page = createPage(['baked-1']);
    loadScript(page, makeSignal({ stale: true, held: true }));

    click(page, 0);
    assert.equal(fbqCalls.length, 0);
    assert.equal(trackEventCalls.length, 1);
    assert.equal(trackEventCalls[0][0], 'AddToCart');
    assert.equal(trackEventCalls[0][1].eventID, 'minted-1');
  });

  test('fires without replacing anything when the runtime cannot mint ids', () => {
    const page = createPage(['baked-1']);
    const signal = makeSignal({ stale: true });
    delete signal.generateEventId;
    loadScript(page, signal);

    assert.equal(page.inputs[0].value, 'baked-1');
    click(page, 0);
    assert.deepEqual(addToCartCalls()[0][3], { eventID: 'baked-1' });
  });
});

describe('without the FacebookSignal runtime', () => {
  test('fires the rendered id with the plain agent', () => {
    const page = createPage(['baked-1']);
    loadScript(page, null);

    assert.equal(page.inputs[0].value, 'baked-1');
    click(page, 0);
    assert.deepEqual(fbqCalls[0], ['set', 'agent', AGENT, '123']);
    assert.deepEqual(addToCartCalls()[0][3], { eventID: 'baked-1' });
  });
});
