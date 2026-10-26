# Meta pixel for WordPress: Configure Hold/Release Signals with CookieYes

This guide explains how to connect CookieYes to the Hold/Release Signals feature in Meta pixel for WordPress. It provides a technical implementation that starts signal delivery in the state that matches the visitor's CookieYes choice and responds when that choice changes.

The setup treats CookieYes's **Advertisement** category as permission to send Meta Signals. If the visitor allows Advertisement cookies, signal delivery is active. If the visitor denies Advertisement cookies or has not completed a choice, signal delivery stays held.

Hold/Release Signals does not provide a consent banner or determine whether permission is required. CookieYes collects and stores the visitor's choice; this integration communicates that choice to Meta pixel for WordPress.

## Before you begin

Confirm that:

- Meta pixel for WordPress is installed and connected to Meta.
- CookieYes is installed and its consent banner is configured.
- CookieYes includes the **Advertisement** category with the `advertisement` slug.
- You can add a file to the site's `wp-content` directory.
- Meta for WooCommerce is **inactive**. Both Meta plugins share `window.fbwcsignal` and the state cookie; running both is not supported by this example.
- This is the only consent bridge controlling these signals. Other hold filters are not coordinated with the browser or release endpoint.

The example expects the current CookieYes interfaces:

- The `cookieyes-consent` first-party cookie.
- The `advertisement` category.
- The `cookieyes_consent_update` browser event.
- The `window.getCkyConsent()` browser API.

If the category was renamed, or the site uses a legacy or standalone CookieYes setup, confirm the available category, event and browser API before adapting the example code.

### Avoid overlapping script blocking

CookieYes auto-blocking or custom script rules can separately block Meta pixel for WordPress scripts until Advertisement permission is allowed. Hold/Release Signals needs the plugin's browser helper to load while signal delivery is held so eligible current-page events can be kept temporarily in memory.

For this setup, exclude `facebook-signal`, `FacebookSignal` and the Meta pixel for WordPress event scripts from CookieYes script blocking. Do not add a second Meta Pixel through CookieYes or a tag manager.

On an uncached page, the must-use plugin establishes the held state when Advertisement permission is absent. The Meta Pixel library itself may still load while signals are held; distinguish loading the library from sending events. The inline Pixel base code must also run so `window.fbq` exists. Without it, the bridge reports a missing `fbq` and cannot release held events.

## How CookieYes controls Hold/Release Signals

CookieYes remains the source of the visitor's choice. The integration translates that choice into the state used by Meta pixel for WordPress:

- `held` means signal delivery is on hold.
- `active` means signal delivery is active.

The setup considers Advertisement cookies allowed only when the visitor has completed a choice and the Advertisement category is enabled:

```js
var allowed = consent.isUserActionCompleted && consent.categories.advertisement;
```

This prevents CookieYes default category settings from being treated as permission before the visitor makes a choice. Closing the banner with its close (X) button also records a completed choice while keeping the default category values, so an Advertisement category that is enabled by default counts as allowed on the next page. Keep Advertisement disabled by default wherever opt-in consent is required.

### State model

| CookieYes state                                | Meta state              | What happens                                                                                     |
| :--------------------------------------------- | :---------------------- | :----------------------------------------------------------------------------------------------- |
| The visitor has not made a choice              | `held`                  | Signals stay held.                                                                               |
| The visitor allows Advertisement cookies       | `active`                | Signals are released and begin sending normally.                                                 |
| The visitor denies Advertisement cookies       | `held`                  | Signals stay held.                                                                               |
| The visitor withdraws Advertisement permission | Transitioning to `held` | The integration requests a hold and reload; an in-flight release can still race with withdrawal. |
| The visitor allows Advertisement cookies again | `active`                | Signals are released again.                                                                      |

The integration uses two first-party cookies for different purposes:

| Cookie                      | Purpose                                             |
| :-------------------------- | :-------------------------------------------------- |
| `cookieyes-consent`         | Stores the visitor's CookieYes choices.             |
| `wc_facebook_signals_state` | Stores whether Meta Signals are `held` or `active`. |

The Meta state cookie contains only the current state. It does not contain event data. The integration recalculates this state from the CookieYes choice on each request that reaches WordPress. A full-page cache can serve a page without running the integration, so cached-page behavior must be tested separately.

This state model targets visitor activity on public pages. Backend, administrator and scheduled activity is outside it.

## How the integration works

The integration has two parts:

1. Backend code establishes the signal state before Meta pixel for WordPress processes the page.
2. Browser code responds when the visitor saves or changes their CookieYes choice.

Both parts are required. The backend controls the plugin's held rendering and server-side hold on visitor requests, including `admin-ajax.php`. Admin screens and cron jobs are exempt. The browser code requests release after permission and a hold/reload after withdrawal, subject to the in-flight release race above.

## Add the integration as a must-use plugin

A must-use plugin is appropriate for this integration because it loads before regular plugins, does not depend on the active theme and cannot be accidentally deactivated from the regular Plugins page.

Do not add this code directly to Meta pixel for WordPress, CookieYes or the active theme. Plugin and theme updates may overwrite it.

### 1. Create the must-use plugins folder

Inside `wp-content`, create the following folder if it does not already exist:

```text
wp-content/mu-plugins
```

WordPress automatically loads PHP files placed directly inside this folder. It does not automatically discover plugin files inside nested folders.

### 2. Create the integration file

Create this file:

```text
wp-content/mu-plugins/meta-cookieyes-signals.php
```

Add the following code:

```php
<?php
/**
 * Plugin Name: Meta pixel for WordPress Hold/Release Signals for CookieYes
 * Description: Connects CookieYes Advertisement permission to Meta pixel for WordPress signal delivery.
 */

defined( 'ABSPATH' ) || exit;

/**
 * Read the values stored in CookieYes's consent cookie.
 *
 * @return array<string, string>
 */
function meta_pixel_for_wordpress_cookieyes_get_consent_values() {
    $encoded = isset( $_COOKIE['cookieyes-consent'] )
        && is_string( $_COOKIE['cookieyes-consent'] )
            // Sanitization must happen after URL decoding.
            ? wp_unslash( $_COOKIE['cookieyes-consent'] ) // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
            : '';

    $raw    = sanitize_text_field( rawurldecode( $encoded ) );
    $values = array();

    foreach ( explode( ',', $raw ) as $entry ) {
        $parts = array_map( 'trim', explode( ':', $entry, 2 ) );

        if ( 2 !== count( $parts ) || '' === $parts[0] ) {
            continue;
        }

        $values[ $parts[0] ] = $parts[1];
    }

    return $values;
}

/**
 * Whether the visitor made a choice and allowed Advertisement cookies.
 *
 * @return bool
 */
function meta_pixel_for_wordpress_cookieyes_allows_advertising() {
    $consent = meta_pixel_for_wordpress_cookieyes_get_consent_values();

    return isset( $consent['action'], $consent['advertisement'] )
        && 'yes' === $consent['action']
        && 'yes' === $consent['advertisement'];
}

/**
 * Start the current page in the state that matches the CookieYes choice.
 */
function meta_pixel_for_wordpress_cookieyes_set_initial_state() {
    $state = meta_pixel_for_wordpress_cookieyes_allows_advertising()
        ? 'active'
        : 'held';

    // Make the state available during the current PHP request.
    $_COOKIE['wc_facebook_signals_state'] = $state;

    // Make the state available to JavaScript and later page requests.
    if ( ! headers_sent() ) {
        setcookie(
            'wc_facebook_signals_state',
            $state,
            array(
                'expires'  => time() + YEAR_IN_SECONDS,
                'path'     => defined( 'COOKIEPATH' ) ? COOKIEPATH : '/',
                'domain'   => defined( 'COOKIE_DOMAIN' ) ? COOKIE_DOMAIN : '',
                'secure'   => is_ssl(),
                'httponly' => false,
                'samesite' => 'Lax',
            )
        );
    }
}
meta_pixel_for_wordpress_cookieyes_set_initial_state();

/**
 * Keep public-page signal delivery held unless CookieYes allows advertising.
 */
add_filter(
    'facebook_signals_held',
    function ( $held ) {
        return $held
            || ! meta_pixel_for_wordpress_cookieyes_allows_advertising();
    }
);

/**
 * React when the visitor saves or changes their CookieYes choice.
 */
add_action(
    'wp_enqueue_scripts',
    function () {
        if ( ! wp_script_is( 'facebook-signal', 'enqueued' ) ) {
            return;
        }

        $script = <<<'JS'
( function () {
    'use strict';

    function cookieYesAllowsAdvertising() {
        if ( typeof window.getCkyConsent !== 'function' ) {
            return false;
        }

        var consent = window.getCkyConsent();

        return !! (
            consent &&
            consent.isUserActionCompleted &&
            consent.categories &&
            consent.categories.advertisement
        );
    }

    function reportError( action, error ) {
        if (
            window.console &&
            typeof window.console.error === 'function'
        ) {
            window.console.error(
                'Meta pixel for WordPress could not ' +
                    action +
                    ' signals.',
                error
            );
        }
    }

    function reloadPage() {
        window.location.reload();
    }

    var releasePending = false;
    var reloading = false;

    function updateMetaTracking() {
        if ( reloading ) {
            return;
        }

        var allowed = cookieYesAllowsAdvertising();
        var api = window.fbwcsignal;
        var signal = window.FacebookSignal;

        if (
            ! api || ! signal ||
            typeof api.getState !== 'function' ||
            typeof api.hold !== 'function' ||
            typeof api.release !== 'function' ||
            typeof signal.hold !== 'function' ||
            typeof signal._held !== 'boolean' ||
            ! signal._config || ! signal._config.ajaxUrl ||
            typeof window.fbq !== 'function'
        ) {
            reportError( 'update', new Error( 'Signal runtime or fbq is not ready.' ) );
            // Avoid a reload loop if script blocking persists on a held page.
            if (
                ! allowed && api && typeof api.getState === 'function' &&
                api.getState() !== 'held'
            ) {
                reloading = true;
                reloadPage();
            }
            return;
        }

        if ( allowed ) {
            // The cookie can say active even after a failed AJAX release.
            if ( releasePending || ( ! signal._held && api.getState() === 'active' ) ) {
                return;
            }

            releasePending = true;
            api.release().then(
                function () {
                    releasePending = false;
                },
                function ( error ) {
                    releasePending = false;
                    reportError( 'release', error );
                }
            );
            return;
        }

        if ( signal._held && api.getState() === 'held' && ! releasePending ) {
            return;
        }

        reloading = true;
        // Revoke now, rather than waiting for the state AJAX response.
        // This does NOT cancel an already-running release in the plugin.
        signal.hold();
        api.hold().then(
            reloadPage,
            function ( error ) {
                reportError( 'hold', error );
                reloadPage();
            }
        );
    }

    document.addEventListener(
        'cookieyes_consent_update',
        updateMetaTracking
    );
} )();
JS;

        wp_add_inline_script(
            'facebook-signal',
            $script,
            'after'
        );
    },
    20
);
```

Copy only the complete `<?php` block above into the MU plugin, not the explanatory PHP fragments below. Do not add a closing `?>` tag; accidental output can prevent WordPress from sending cookies and other headers.

### 3. Confirm that WordPress loaded it

In WordPress, go to **Plugins** and open the **Must-Use** section. The following entry should appear:

```text
Meta pixel for WordPress Hold/Release Signals for CookieYes
```

Must-use plugins do not require activation.

### 4. Bypass and clear cached pages

Configure WordPress, server and CDN caches to bypass pages using this setup, then purge existing copies. An old cached page may contain signal initialization generated before installation; even newly cached HTML is unsafe for a visitor with no state cookie. See [Test cached pages](#test-cached-pages).

## How the backend setup works

### Reading the CookieYes choice

CookieYes stores values in `cookieyes-consent` using entries such as:

```text
action:yes,necessary:yes,functional:no,analytics:no,advertisement:yes
```

The PHP code parses these into exact key/value pairs. Advertisement permission is allowed only when both of these values are present:

```php
'action'        => 'yes'
'advertisement' => 'yes'
```

Using exact values avoids treating an incomplete choice or a similarly named category as permission.

### Establishing the initial state

The must-use plugin runs before Meta pixel for WordPress. It translates the CookieYes choice into:

```php
$state = $allowed ? 'active' : 'held';
```

It then updates the state for the current PHP request:

```php
$_COOKIE['wc_facebook_signals_state'] = $state;
```

It also sends `wc_facebook_signals_state` to the browser with `setcookie()` so JavaScript and later requests can read it. This must-use plugin runs before WordPress defines `COOKIEPATH` and `COOKIE_DOMAIN`, so its fallbacks write the cookie at host-only `/` unless `wp-config.php` defines those constants. The plugin's own later hold and release writes use WordPress's `COOKIEPATH`/`COOKIE_DOMAIN`. The plugin's JavaScript still writes at host-only `/`, so this example supports only that default scope. Remove duplicate state cookies from old paths/domains before testing; two cookies with the same name can make PHP and JavaScript disagree.

### Applying the server-side hold

Meta pixel for WordPress exposes `facebook_signals_held` so integrations can control whether eligible public-page signals are held. The example returns `true` when CookieYes has not recorded Advertisement permission:

```php
return $held
    || ! meta_pixel_for_wordpress_cookieyes_allows_advertising();
```

This callback preserves `$held` **only at this PHP filter**. It does not coordinate other holds with JavaScript: browser initialization trusts the state cookie, and the release endpoint does not enforce this filter. The cookie is client-writable, not proof of consent. Do not combine this example with another consent bridge or assume another filter will veto a browser release.

## How the browser setup works

The CookieYes bridge is attached after Meta pixel for WordPress's signal helper:

```php
wp_add_inline_script(
    'facebook-signal',
    $script,
    'after'
);
```

This makes `window.fbwcsignal` available before the bridge runs. The `wp_enqueue_scripts` hook uses priority `20` because Meta pixel for WordPress normally enqueues its helper at the default priority before the integration attaches the inline script.

### Responding to CookieYes

The bridge listens for:

```text
cookieyes_consent_update
```

When CookieYes dispatches that event, the bridge reads the current choice through:

```js
window.getCkyConsent();
```

The bridge checks both `fbwcsignal.getState()` (the cookie) and `FacebookSignal._held` (the current browser runtime). The plugin writes the cookie before either AJAX request succeeds, so checking only the cookie would skip a retry after a failed release. `_held` and `_config` are implementation details; recheck them when upgrading the plugin.

Repeated grants during a pending release are ignored. After a rejection, another consent-update event allowing Advertisement can retry; there is no automatic retry loop. An initial denial does not reload an already-held, idle runtime. The bridge listens for changes; it does not reconcile arbitrary cached HTML on startup.

### Releasing Signals

When Advertisement permission is allowed, the bridge calls:

```js
window.fbwcsignal.release();
```

The helper writes `active` first, posts `fbpix_set_pixel_signals`, then posts queued events to `fbpix_release_signals` if the runtime is held. A successful release response triggers browser replay without reloading. The cookie alone does not prove release succeeded, and a successful response does not prove Meta accepted the server events.

### Holding Signals

When an active runtime is denied permission, the bridge calls `window.FacebookSignal.hold()` synchronously to revoke browser consent, then `window.fbwcsignal.hold()` to persist the state. It reloads after that request succeeds or rejects so PHP can recalculate consent.

This reduces the delay in the normal hold path but **does not cancel an in-flight release**. That response can still grant browser consent and replay queued events. A stalled hold request can also delay the reload. Do not treat this example as an atomic withdrawal mechanism.

If the release response arrives after withdrawal but before the hold request finishes, it replays the queued events, sets `_held` back to `false` and can reset the state cookie to `active` until the page reloads. If the reload comes first, the browser replay is cancelled, but the server has already sent the released events.

## What happens during each visitor flow

### First visit before the visitor makes a choice

When a visitor opens the website without an existing CookieYes choice:

1. The backend setup does not find a completed choice that allows Advertisement cookies.
2. It sets Meta Signals to `held` before Meta pixel for WordPress processes the page.
3. The Meta Pixel does not begin normal event delivery.
4. Conversions API events from visitor requests are held.
5. Eligible event data from the current page visit can be held temporarily in the browser's memory.

If the visitor leaves the page before giving permission, the held browser events are not saved or sent. They are lost when the page closes.

### The visitor allows Advertisement cookies

When the visitor allows Advertisement cookies:

1. CookieYes updates `cookieyes-consent`.
2. CookieYes dispatches `cookieyes_consent_update`.
3. The integration confirms that the visitor completed a choice and allowed the Advertisement category.
4. It calls `window.fbwcsignal.release()`.
5. Meta pixel for WordPress changes `wc_facebook_signals_state` to `active`.
6. The plugin can send eligible recent events held during that same page visit.
7. The Meta Pixel begins normal event delivery.
8. Signals remain active on later pages while Advertisement permission remains allowed.

Release does not recover events from an earlier page visit. It can only send eligible events still available from the current page.

### The visitor denies Advertisement cookies

When the visitor completes a choice without allowing Advertisement cookies:

1. CookieYes records the completed choice with Advertisement permission disabled.
2. The desired Meta state remains `held`.
3. The integration does not release signals.
4. The runtime remains held.

If signals were already held, no additional hold request or page reload is necessary.

### A returning visitor loads another page

At the beginning of each request that reaches WordPress, the backend setup reads the current CookieYes choice:

- If the visitor previously allowed Advertisement cookies, the page starts with signals `active`.
- If the visitor denied Advertisement cookies or has not completed a choice, the page starts with signals `held`.

### The visitor withdraws Advertisement permission

When a visitor disables the Advertisement category after signals were active:

1. CookieYes updates the visitor's choice.
2. The integration detects that Advertisement permission is no longer allowed.
3. It calls `window.FacebookSignal.hold()` immediately, then `window.fbwcsignal.hold()`.
4. Meta pixel for WordPress changes `wc_facebook_signals_state` to `held`.
5. The integration reloads the page after the hold request finishes.
6. The reloaded page begins with signals held.

The reload starts a fresh, uncached page under the new choice. It cannot retract earlier events or guarantee that an in-flight release did not replay events before navigation.

### The visitor grants permission again

If the visitor later enables Advertisement cookies again:

1. CookieYes records the new choice.
2. The integration calls `release()`.
3. Meta Signals become `active`.
4. Eligible recent events from the current page can be sent.
5. Signals continue normally on later pages.

## How to verify the setup

Test in a private browser window so existing CookieYes and Meta cookies do not affect the result. These Console commands show the current CookieYes choice and Meta state:

```js
window.getCkyConsent();
window.fbwcsignal.getState();
window.FacebookSignal._held;
typeof window.fbq;
```

Check these scenarios on uncached pages. `getState()` reads a cookie; `_held` is an internal diagnostic, not proof of server-side enforcement:

| Scenario                          | Expected result                                                                                                                                                                                                  |
| :-------------------------------- | :--------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| No choice or Advertisement denied | `getState()` returns `held` and `_held` is `true`. No new `_fbp` or `_fbc` cookies on an uncached denied page.                                                                                                   |
| Advertisement allowed             | CookieYes reports a completed choice with `categories.advertisement` set to `true`, and `getState()` returns `active`. The Network panel can show `fbpix_set_pixel_signals` followed by `fbpix_release_signals`. |
| Advertisement withdrawn           | The page reloads and `getState()` returns `held`. Events sent before withdrawal are not retracted.                                                                                                               |
| Later page load                   | The page starts `active` after permission or `held` after denial or withdrawal.                                                                                                                                  |

The Meta Pixel library itself may still load while signals are held. The important check is whether event delivery remains held.

Also confirm that `window.FacebookSignal` exists and `typeof window.fbq` is `function` while held. Review script-blocking settings if either is missing.

Test real banner choices (grant, deny, withdraw and re-grant), not just manually dispatched events. Also test:

- Block one `fbpix_set_pixel_signals` or `fbpix_release_signals` request. The cookie may read `active` while `_held` remains `true`. After fixing the error, dispatch another consent update through the banner; the bridge should retry. Expired nonces require a fresh uncached page, and refreshing loses the old queue.
- Delay release and withdraw before its response. The runtime should be held immediately by the bridge, but the old response can still undo it. This is an unresolved plugin bug.
- Submit each supported form/cart AJAX action while denied and check its **server** send path separately to confirm that no Conversions API event is sent.
- Load with mismatched cookies: Advertisement allowed plus Meta `held`, and Advertisement denied plus Meta `active`. On uncached requests, PHP and the browser should follow the completed CookieYes choice.

## Verify Pixel and Conversions API delivery

The browser Network panel can show Pixel requests and WordPress AJAX responses, but not WordPress's final server-to-server request to Meta.

1. On a fresh uncached page with Advertisement denied, observe the browser queue and check for Pixel event requests to `www.facebook.com/tr`. Loading `fbevents.js` alone is not an event.
2. Grant Advertisement using the banner. Inspect `fbpix_set_pixel_signals`, then `fbpix_release_signals`. After a normal successful response, `_held` becomes `false` and the browser queue empties.
3. Verify CAPI separately with a controlled event and a valid server connection. Use Meta Events Manager's regular event activity/diagnostics or developer instrumentation around the actual CAPI transport and its response. Correlate event name, time and ID where present. Do not log tokens, user data or complete request URLs.
4. **Test events requires a `test_event_code` for server events.** This guide's release path does not supply one. Browser Test events or a successful AJAX response does not establish server delivery; adding a server test code requires separate developer instrumentation.
5. Repeat while denied, including visitor AJAX submissions. Do not interpret an absent browser event as proof that no CAPI event was sent.

The release endpoint's `success` and `sent_count` describe its processing of the submitted queue, not Meta's acknowledgement. It can report success even when the CAPI sender fails or sends nothing. Browser replay can still occur in that case.

## Test cached pages

**Serve these pages uncached. The visitor's consent state is resolved in PHP on every request, so pages that use this example must be excluded from full-page caching.** PHP does not run on a cache hit. In the current plugin, `FacebookSignal.init()` receives `held: false` and falls back to it when the browser has no state cookie. A cached first visit can therefore start active even if the cached HTML was produced while held. The bridge only listens for subsequent consent changes.

To reproduce, prime a page cache, then open the cache hit in a fresh browser context with no signal-state cookie. Check the runtime and event requests before touching the banner. Also test HTML generated with permission served to a denied visitor, and the reverse. Never share cached `Set-Cookie` headers between visitors.

HTML generated with permission contains direct Pixel calls. Served to a visitor who has not chosen, it can send events to Meta before the bridge holds and reloads the page.

A rendered `held: true` alone would not solve every cross-visitor cache variant. Keep caching disabled for these pages rather than treating a single successful test or cache purge as a fix. Performance tools must also preserve the order of the helper, Pixel base code, initialization and bridge. The state-update endpoint uses a nonce that can expire in cached HTML.

## Troubleshooting

| Problem                                                | What to check                                                                                                                             |
| :----------------------------------------------------- | :---------------------------------------------------------------------------------------------------------------------------------------- |
| The must-use plugin does not appear in WordPress       | Confirm that the PHP file is directly inside `wp-content/mu-plugins`, not in a nested folder.                                             |
| `window.fbwcsignal` is undefined                       | Confirm that Meta pixel for WordPress is connected and `facebook-signal` is enqueued. Check for earlier JavaScript errors.                |
| `window.FacebookSignal` is undefined while held        | Exclude Meta pixel for WordPress's `facebook-signal` helper from CookieYes auto-blocking or custom script rules, then clear cached pages. |
| `window.getCkyConsent` is undefined                    | Confirm that the current CookieYes banner script is loaded. Legacy or standalone CookieYes setups may expose a different interface.       |
| Signals remain held after permission                   | Check that `cookieyes-consent` contains both `action:yes` and `advertisement:yes`.                                                        |
| Signals are active before a choice                     | Confirm that the must-use plugin is loading and clear all cached pages.                                                                   |
| Granting permission does not release events            | Check the two WordPress AJAX requests and the browser Console for a release error.                                                        |
| Withdrawing permission does not reload                 | Confirm that CookieYes dispatches `cookieyes_consent_update` after the preference is saved.                                               |
| The page repeatedly reloads                            | Check whether CookieYes saves the updated choice and whether `wc_facebook_signals_state` can be written.                                  |
| An AJAX request returns `403`                          | Clear cached pages and check for an expired nonce or a missing signal-state cookie.                                                       |
| Events from an earlier page are missing                | Only eligible events from the current page can be released. Previous-page events are not recovered.                                       |
| A Conversions API event is missing from Events Manager | Check the actual CAPI response or regular Events Manager activity. Server Test events requires separately configured `test_event_code`.   |

## Limitations

- Held event data exists only in the current page's browser memory.
- Held payloads are not persisted in a server queue or long-term browser storage. The plugin separately stores seen event IDs in `sessionStorage` under `fbpix_seen_event_ids` for deduplication; that is not a recoverable event queue.
- Closing or leaving the page before release discards the held events.
- The **server endpoint** considers only the first 20 queued events and rejects supplied timestamps over 30 minutes old or in the future. The browser replay has neither check and flushes its entire queue. These are not uniform browser/server limits; counts and delivery can diverge.
- Release requests are rate limited (default threshold: 80 accepted events per IP in a five-minute window). A `429` is a failed release, not permission to assume delivery.
- Calling `hold()` does not retract events already sent from an active page.
- Existing Meta attribution cookies are not deleted when signals are held. Meta pixel for WordPress avoids setting new attribution cookies while held.
- Visitors with JavaScript disabled may still register the basic Meta Pixel `noscript` PageView.
- Backend, administrator and scheduled events are outside this flow.
- If release fails because of a network or server error, held events may be lost when the visitor leaves or reloads the page.
- Custom events and events sent directly through `fbq` or a separate Conversions API integration may bypass this flow.
- A renamed CookieYes category, legacy CookieYes setup or future API change may require adapting the integration.

The plugin's built-in event types include the following. Coverage depends on the request path:

```text
PageView
ViewContent
AddToCart
InitiateCheckout
Purchase
Lead
```

Valid custom event names queued through Meta pixel for WordPress can also be released. Events sent directly through another script or integration are not added to this queue.

## Privacy responsibility

Hold/Release Signals does not make a site automatically compliant with privacy laws. It provides a technical way to connect CookieYes's Advertisement choice to Meta pixel for WordPress signal delivery.

The website owner remains responsible for determining:

- Whether and when permission is required.
- How the consent banner is presented.
- Which CookieYes category should control Meta Signals.
- How regional privacy requirements are handled.
- How third-party scripts and custom integrations respond to the visitor's choice.

Third-party tools may interfere with or bypass this setup, so the complete site should be tested before the integration is used in production.
