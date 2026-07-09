/**
 * Frontend signal hold/release helpers for E2E tests.
 */

const { TIMEOUTS } = require('../constants/timeouts');

async function waitForSignalBridge(page) {
  await page.waitForFunction(
    () => Boolean(window.fbwcsignal && typeof window.fbwcsignal.hold === 'function' && typeof window.fbwcsignal.release === 'function'),
    null,
    { timeout: TIMEOUTS.LONG }
  );
}

async function holdSignals(page) {
  await waitForSignalBridge(page);

  return page.evaluate(async () => {
    await window.fbwcsignal.hold();

    return {
      state: typeof window.fbwcsignal.getState === 'function' ? window.fbwcsignal.getState() : null,
      held: Boolean(window.FacebookSignal && window.FacebookSignal._held),
    };
  });
}

async function releaseSignals(page) {
  await waitForSignalBridge(page);

  return page.evaluate(async () => {
    const response = await window.fbwcsignal.release();

    return {
      response,
      state: typeof window.fbwcsignal.getState === 'function' ? window.fbwcsignal.getState() : null,
      held: Boolean(window.FacebookSignal && window.FacebookSignal._held),
    };
  });
}

async function getSignalState(page) {
  await waitForSignalBridge(page);

  return page.evaluate(() => ({
    state: typeof window.fbwcsignal.getState === 'function' ? window.fbwcsignal.getState() : null,
    held: Boolean(window.FacebookSignal && window.FacebookSignal._held),
  }));
}

async function getQueuedSignalEvents(page, eventName = null) {
  return page.evaluate((targetEventName) => {
    const queue = (window.FacebookSignal && Array.isArray(window.FacebookSignal._queue))
      ? window.FacebookSignal._queue
      : [];

    return queue
      .filter(event => !targetEventName || event.event_name === targetEventName)
      .map(event => ({
        event_name: event.event_name || null,
        event_id: event.event_id || null,
        custom_data: event.custom_data || {},
      }));
  }, eventName);
}

module.exports = {
  holdSignals,
  releaseSignals,
  getSignalState,
  getQueuedSignalEvents,
};
