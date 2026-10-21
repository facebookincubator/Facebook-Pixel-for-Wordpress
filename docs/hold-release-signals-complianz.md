# Meta pixel for WordPress: Configure Hold/Release Signals with Complianz

This guide explains how to connect Complianz to the Hold/Release Signals feature in Meta pixel for WordPress. It provides a technical implementation that starts signal delivery in the state that matches the visitor's Complianz choice and responds when that choice changes.

The setup treats Complianz's **Marketing** category as permission to send Meta Signals. If Complianz reports that Marketing is allowed, signal delivery is active. If Complianz reports that Marketing is denied, signal delivery stays held.

Hold/Release Signals does not provide a consent banner or determine whether permission is required. Complianz applies the site's regional consent configuration and stores the visitor's choice; this integration communicates Complianz's result to Meta pixel for WordPress.

## Before you begin

Confirm that:

- Meta pixel for WordPress is installed and connected to Meta.
- Complianz is installed and its consent banner is configured.
- Complianz uses the **Marketing** category for Meta Signals.
- You can add a file to the site's `wp-content` directory.
- Meta for WooCommerce is **inactive**. Both Meta plugins share `window.fbwcsignal` and the state cookie; running both is not supported by this example.
- This is the only consent bridge controlling these signals. Other hold filters are not coordinated with the browser or release endpoint.

The example expects the current Complianz interfaces:

- The PHP function `cmplz_has_consent( 'marketing' )`.
- The browser function `window.cmplz_has_consent( 'marketing' )`.
- The `cmplz_status_change` and `cmplz_fire_categories` browser events.
- `cmplz_status_change` event details containing `category` and `value`.

### Configure the Complianz consent flow

Complete the Complianz setup wizard before adding the integration. In the wizard:

1. Select the region and consent behavior that apply to the site. For an opt-in flow, use an opt-in configuration such as the EU region or another Complianz option that requires permission before Marketing is allowed.
2. Confirm that the site uses advertising or marketing cookies so the Marketing category appears in the banner. In the **Services** step, answer **Yes** to advertising scripts and clear **Facebook** from the social media list (see [Avoid overlapping script blocking](#avoid-overlapping-script-blocking)).
3. Enable the consent banner and cookie blocker, then finish the wizard.

Do not select a region only to force a particular test result. The site's Complianz configuration should reflect the regions it targets and its privacy requirements.

For an opt-in test, open a private browser window before making a choice and confirm:

```js
window.cmplz_has_consent('marketing');
```

The result should be `false`. If it is `true`, review Complianz's region and consent-type settings before continuing.

### Avoid overlapping script blocking

Complianz can detect Meta pixel for WordPress as **Official Facebook Pixel** and separately block matching browser scripts until Marketing is allowed. Hold/Release Signals needs Meta pixel for WordPress's browser helper to load while signal delivery is held so eligible current-page events can be kept temporarily in memory.

For this setup, go to **Complianz > Integrations > Plugins** and disable the automatic **Official Facebook Pixel** integration. Also check the Complianz Script Center for a custom rule that blocks `facebook-signal`, `FacebookSignal` or the Meta pixel for WordPress event scripts.

Also check the wizard's **Services** step. Complianz can detect the Pixel and preselect **Facebook** under the social media question. While it is selected, the cookie blocker blocks the Pixel base code even with the plugin integration disabled:

- While signals are held, `window.fbq` is undefined.
- On later pages after permission, Complianz loads the base code only after Meta pixel for WordPress has tried to initialize the Pixel. The Pixel is not initialized and no browser `PageView` is tracked.

Clear **Facebook** from the social media list to avoid both problems. If the site also embeds Facebook content that Complianz must block, find another way to exempt only the Pixel base code, then confirm that `typeof window.fbq` is `function` on a held page.

On an uncached page, the must-use plugin establishes the held state when Marketing is denied. The Meta Pixel library itself may still load while signals are held; distinguish loading the library from sending events. The inline Pixel base code must also run so `window.fbq` exists. Without it, the bridge reports a missing `fbq` and cannot release held events.

## How Complianz controls Hold/Release Signals

Complianz remains the source of the visitor's choice. The integration translates the result of Complianz's Marketing consent check into the state used by Meta pixel for WordPress:

- `held` means signal delivery is on hold.
- `active` means signal delivery is active.

The backend asks Complianz directly whether Marketing is allowed:

```php
cmplz_has_consent( 'marketing' );
```

The result is region-aware. For an opt-in configuration, Marketing normally remains denied until the visitor allows it. For an opt-out configuration, Complianz may report Marketing as allowed before a category cookie exists. The integration follows that Complianz result instead of imposing a separate consent rule.

### State model

| Complianz result                                     | Meta state              | What happens                                                                                     |
| :--------------------------------------------------- | :---------------------- | :----------------------------------------------------------------------------------------------- |
| Marketing is not allowed                             | `held`                  | Signals stay held.                                                                               |
| Marketing is allowed                                 | `active`                | Signals are released and begin sending normally.                                                 |
| The visitor changes Marketing from allowed to denied | Transitioning to `held` | The integration requests a hold and reload; an in-flight release can still race with withdrawal. |
| The visitor changes Marketing from denied to allowed | `active`                | Signals are released again and eligible current-page events can be sent.                         |

The integration uses two first-party cookies for different purposes:

| Cookie                                                           | Purpose                                                                                                       |
| :--------------------------------------------------------------- | :------------------------------------------------------------------------------------------------------------ |
| Complianz category cookies, normally including `cmplz_marketing` | Store the visitor's Complianz choices. Complianz can use a different prefix on some multisite configurations. |
| `wc_facebook_signals_state`                                      | Stores whether Meta Signals are `held` or `active`.                                                           |

The Meta state cookie contains only the current state. It does not contain event data. The integration recalculates the state through Complianz on each request that reaches WordPress. A full-page cache can serve a page without running the integration, so cached-page behavior must be tested separately.

This state model targets visitor activity on public pages. Backend, administrator and scheduled activity is outside it.

## How the integration works

The integration has two parts:

1. Backend code asks Complianz whether Marketing is allowed and establishes the signal state before public-page events are processed.
2. Browser code responds when Complianz changes the Marketing choice.

Both parts are required. The backend controls the plugin's held rendering and server-side hold on visitor requests, including `admin-ajax.php`. Admin screens and cron jobs are exempt. The browser code requests release after permission and a hold/reload after withdrawal, subject to the in-flight release race above.

## Add the integration as a must-use plugin

A must-use plugin is appropriate for this integration because it loads before regular plugins, does not depend on the active theme and cannot be accidentally deactivated from the regular Plugins page.

Do not add this code directly to Meta pixel for WordPress, Complianz or the active theme. Plugin and theme updates may overwrite it.

### 1. Create the must-use plugins folder

Inside `wp-content`, create the following folder if it does not already exist:

```text
wp-content/mu-plugins
```

WordPress automatically loads PHP files placed directly inside this folder. It does not automatically discover plugin files inside nested folders.

### 2. Create the integration file

Create this file:

```text
wp-content/mu-plugins/meta-complianz-signals.php
```

Add the following code:

```php
<?php
/**
 * Plugin Name: Meta pixel for WordPress Hold/Release Signals for Complianz
 * Description: Connects Complianz Marketing permission to Meta pixel for WordPress signal delivery.
 */

defined( 'ABSPATH' ) || exit;

// This bridge owns the derived state cookie. Do not let an old Meta cookie
// latch the plugin's per-request hold before Complianz is ready at init.
// The filter below supplies the early hold; init writes the resolved state.
unset( $_COOKIE['wc_facebook_signals_state'] );

/**
 * Whether Complianz currently allows the Marketing category.
 *
 * Complianz applies its configured region and consent type when calculating
 * this result. After init, a missing Complianz API means denied.
 *
 * @return bool
 */
function meta_pixel_for_wordpress_complianz_allows_marketing() {
	if ( did_action( 'init' ) ) {
		return function_exists( 'cmplz_has_consent' )
			&& (bool) cmplz_has_consent( 'marketing' );
	}

	// Before init, use the stored category value and fail closed by default.
	// Complianz has not loaded its settings yet, so a custom cookie prefix
	// (for example on multisite) cannot be detected here.
	$cookie_name = 'cmplz_marketing';
	$value       = isset( $_COOKIE[ $cookie_name ] )
		&& is_string( $_COOKIE[ $cookie_name ] )
			? sanitize_text_field( wp_unslash( $_COOKIE[ $cookie_name ] ) )
			: '';

	return 'allow' === $value;
}

/**
 * Start the current page in the state that matches the Complianz choice.
 */
function meta_pixel_for_wordpress_complianz_set_initial_state() {
	$state = meta_pixel_for_wordpress_complianz_allows_marketing()
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

// Wait until Complianz and WordPress translations are ready for the full check.
add_action(
	'init',
	'meta_pixel_for_wordpress_complianz_set_initial_state',
	1
);

/**
 * Keep public-page signal delivery held unless Complianz allows Marketing.
 */
add_filter(
	'facebook_signals_held',
	function ( $held ) {
		return $held
			|| ! meta_pixel_for_wordpress_complianz_allows_marketing();
	}
);

/**
 * React when the visitor changes their Complianz Marketing choice.
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
	var releaseFailed = false;
	var reloading = false;

	function updateMetaTracking( allowed ) {
		if ( ! allowed ) {
			releaseFailed = false;
		}

		if ( reloading ) {
			return;
		}

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
			releaseFailed = false;
			api.release().then(
				function () {
					releasePending = false;
				},
				function ( error ) {
					releasePending = false;
					releaseFailed = ! reloading;
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
		'cmplz_status_change',
		function ( event ) {
			if (
				! event.detail ||
				event.detail.category !== 'marketing'
			) {
				return;
			}

			updateMetaTracking( event.detail.value === 'allow' );
		}
	);

	// Accept / Save preferences emits this even when Marketing is unchanged.
	// Retry only our failed release, and check the current consent again.
	document.addEventListener(
		'cmplz_fire_categories',
		function () {
			if (
				! releaseFailed || releasePending || reloading ||
				typeof window.cmplz_has_consent !== 'function' ||
				! window.cmplz_has_consent( 'marketing' )
			) {
				return;
			}

			updateMetaTracking( true );
		}
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
Meta pixel for WordPress Hold/Release Signals for Complianz
```

Must-use plugins do not require activation.

### 4. Bypass and clear cached pages

Configure WordPress, server and CDN caches to bypass pages using this setup, then purge existing copies. An old cached page may contain signal initialization generated before installation; even newly cached HTML is unsafe for a visitor with no state cookie. See [Test cached pages](#test-cached-pages).

## How the backend setup works

### Reading the Complianz result

After WordPress reaches `init`, the must-use plugin asks Complianz directly:

```php
cmplz_has_consent( 'marketing' );
```

Complianz uses its configured consent type and cookie prefix when producing this result. This is important for regional opt-out behavior and multisite configurations.

Meta pixel for WordPress can inspect signal state while regular plugins are still loading. Before `init`, the helper reads only Complianz's stored Marketing category value and defaults to denied when no value exists. Without a stored Marketing allowance, this early check holds attribution-cookie writes before the full Complianz API is ready. An old `allow` value can still allow early attribution processing; a missing API is treated as denied only once `init` begins. The early check always reads `cmplz_marketing`. Complianz loads its settings after this check runs, so a custom cookie prefix (for example on multisite) is not detected, and attribution cookies are not written early for those visitors. From `init` onward, `cmplz_has_consent()` applies the correct prefix.

The MU plugin removes the incoming **derived Meta state** from `$_COOKIE` before regular plugins load. Otherwise, an old `held` value would latch `FacebookSignalState` during plugin initialization even if Complianz now allows Marketing. This does not delete the browser cookie or the Complianz cookies. Before `init`, the hold filter uses the stored Complianz choice; at `init:1`, the callback writes the resolved Meta state for PHP and the browser.

After `init`, the helper uses only `cmplz_has_consent( 'marketing' )`. If that API is missing, it returns `false` even if an old Marketing cookie says `allow`. Before `init`, the cookie fallback is conservative when no stored value exists; it is not a full regional consent check.

### Establishing the initial state

The integration translates the Complianz result into:

```php
$state = $allowed ? 'active' : 'held';
```

It then updates the state for the current PHP request:

```php
$_COOKIE['wc_facebook_signals_state'] = $state;
```

It also sends `wc_facebook_signals_state` to the browser with `setcookie()` so JavaScript and later requests can read it. PHP uses the plugin's `COOKIEPATH`/`COOKIE_DOMAIN` scope. The plugin's JavaScript still writes at host-only `/`, so this example supports only that default scope. Remove duplicate state cookies from old paths/domains before testing; two cookies with the same name can make PHP and JavaScript disagree.

### Applying the server-side hold

Meta pixel for WordPress exposes `facebook_signals_held` so integrations can control whether eligible public-page signals are held. The example returns `true` when Complianz does not allow Marketing:

```php
return $held
	|| ! meta_pixel_for_wordpress_complianz_allows_marketing();
```

This callback preserves `$held` **only at this PHP filter**. It does not coordinate other holds with JavaScript: browser initialization trusts the state cookie, and the release endpoint does not enforce this filter. The cookie is client-writable, not proof of consent. Do not combine this example with another consent bridge or assume another filter will veto a browser release.

## How the browser setup works

The Complianz bridge is attached after Meta pixel for WordPress's signal helper:

```php
wp_add_inline_script(
	'facebook-signal',
	$script,
	'after'
);
```

This makes `window.fbwcsignal` available before the bridge runs. The `wp_enqueue_scripts` hook uses priority `20` because Meta pixel for WordPress normally enqueues its helper at the default priority before the integration attaches the inline script.

### Responding to Complianz

The bridge listens for:

```text
cmplz_status_change
```

Complianz dispatches this event when a category value changes. The bridge ignores other categories and reads the Marketing result from:

```js
event.detail.category === 'marketing';
event.detail.value === 'allow';
```

The bridge checks both `fbwcsignal.getState()` (the cookie) and `FacebookSignal._held` (the current browser runtime). The plugin writes the cookie before either AJAX request succeeds, so checking only the cookie would skip a retry after a failed release. `_held` and `_config` are implementation details; recheck them when upgrading the plugin.

After a failed release, the visitor can reopen the banner and click **Accept** or **Save preferences** with Marketing enabled. The `cmplz_fire_categories` event fires even when consent is unchanged, letting the bridge recheck consent and retry without reloading.

Only failed releases can be retried this way; pending releases, withdrawal and page initialization cannot trigger retries. There is no automatic retry or visitor-facing error notice. Initial denial leaves an already-held, idle runtime unchanged, and the bridge does not reconcile cached HTML on startup.

Leaving or reloading loses the queue. If CAPI delivery succeeded but its response was lost, a retry may duplicate events, especially without event IDs.

### Releasing Signals

When Marketing is allowed, the bridge calls:

```js
window.fbwcsignal.release();
```

The helper writes `active` first, posts `fbpix_set_pixel_signals`, then posts queued events to `fbpix_release_signals` if the runtime is held. A successful release response triggers browser replay without reloading. The cookie alone does not prove release succeeded, and a successful response does not prove Meta accepted the server events.

### Holding Signals

When an active runtime is denied permission, the bridge calls `window.FacebookSignal.hold()` synchronously to revoke browser consent, then `window.fbwcsignal.hold()` to persist the state. It reloads after that request succeeds or rejects so PHP can recalculate consent. Complianz may also reload after withdrawal. Complianz can reload before the hold request finishes. The reloaded page still starts held because PHP recalculates the state from Complianz.

This reduces the delay in the normal hold path but **does not cancel an in-flight release**. That response can still grant browser consent and replay queued events. A stalled hold request can also delay the reload. Do not treat this example as an atomic withdrawal mechanism.

If the release response arrives after withdrawal but before the hold request finishes, it replays the queued events, sets `_held` back to `false` and can reset the state cookie to `active` until the page reloads. If the Complianz reload comes first, the browser replay is cancelled, but the server has already sent the released events.

## What happens during each visitor flow

### First visit

On each request that reaches WordPress:

1. The backend asks Complianz whether Marketing is allowed for that visitor and region.
2. It sets Meta Signals to `active` or `held` before Meta pixel for WordPress processes public-page events.
3. If signals are held, eligible current-page event data can be kept temporarily in the browser's memory.

In an opt-in region, a visitor who has not made a choice normally begins held. In an opt-out region, Complianz may report Marketing as allowed before the visitor makes a choice, so the page can begin active. Configure Complianz according to the site's requirements before relying on this integration.

If a visitor leaves a held page before giving permission, the held browser events are not saved or sent. They are lost when the page closes.

### The visitor allows Marketing

When the visitor allows Marketing:

1. Complianz stores the Marketing result.
2. Complianz dispatches `cmplz_status_change` with `category: marketing` and `value: allow`.
3. The integration calls `window.fbwcsignal.release()` if signals are not already active.
4. Meta pixel for WordPress changes `wc_facebook_signals_state` to `active`.
5. The plugin can send eligible recent events held during that same page visit.
6. Signals remain active on later pages while Complianz continues to allow Marketing.

Release does not recover events from an earlier page visit. It can only send eligible events still available from the current page.

### The visitor denies Marketing

When the visitor denies Marketing:

1. Complianz stores the denial.
2. Complianz dispatches `cmplz_status_change` with `category: marketing` and `value: deny`.
3. If signals are already held, the integration does not make another hold request.
4. The runtime remains held.

### The visitor withdraws Marketing permission

When a visitor changes Marketing from allowed to denied:

1. The integration calls `window.FacebookSignal.hold()` immediately, then `window.fbwcsignal.hold()`.
2. Meta pixel for WordPress changes `wc_facebook_signals_state` to `held`.
3. The integration reloads the page after the hold request finishes.
4. The reloaded page asks Complianz for the current Marketing result and begins with signals held.

The reload starts a fresh, uncached page under the new choice. It cannot retract earlier events or guarantee that an in-flight release did not replay events before navigation.

### The visitor grants permission again

If the visitor later allows Marketing again:

1. Complianz records the new choice.
2. The integration calls `release()`.
3. Meta Signals become `active`.
4. Eligible recent events from the current page can be sent.
5. Signals continue normally on later pages.

## How to verify the setup

Test in a private browser window so existing Complianz and Meta cookies do not affect the result. These Console commands show the current Complianz result and Meta state:

```js
window.cmplz_has_consent('marketing');
window.fbwcsignal.getState();
window.FacebookSignal._held;
typeof window.fbq;
```

Check these scenarios on uncached pages. `getState()` reads a cookie; `_held` is an internal diagnostic, not proof of server-side enforcement:

| Scenario                   | Expected result                                                                                                                                            |
| :------------------------- | :--------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Complianz denies Marketing | `cmplz_has_consent( 'marketing' )` is `false`, `getState()` returns `held` and `_held` is `true`.                                                          |
| Complianz allows Marketing | Both checks indicate permission/`active`. The Network panel can show `fbpix_set_pixel_signals` followed by `fbpix_release_signals`.                        |
| Marketing is withdrawn     | The page reloads and `getState()` returns `held`. Events sent before withdrawal are not retracted.                                                         |
| A later page loads         | The page starts in the state that matches Complianz's current Marketing result.                                                                            |

Also confirm that `window.FacebookSignal` exists and `typeof window.fbq` is `function` while held. Review script-blocking settings if either is missing.

Test real banner choices (grant, deny, withdraw and re-grant), not just manually dispatched events. Also test:

- Block one `fbpix_set_pixel_signals` or `fbpix_release_signals` request. The cookie may read `active` while `_held` remains `true`. After fixing the error, reopen the banner and click **Accept** again, or **Save preferences** with Marketing still enabled. Confirm that a new `fbpix_set_pixel_signals` / `fbpix_release_signals` sequence drains the existing queue without a reload. Also retry while the request is pending and after it succeeds; neither should send another release. Denying Marketing after a failed release must hold/reload instead of retrying. Expired nonces require a fresh uncached page, and refreshing loses the old queue.
- Delay release and withdraw before its response. The runtime should be held immediately by the bridge, but the old response can still undo it. This is an unresolved plugin bug.
- Submit each supported form/cart AJAX action while denied and check its **server** send path separately to confirm that no Conversions API event is sent.
- Load with mismatched cookies: Complianz allowing plus Meta `held`, and Complianz denying plus Meta `active`. On uncached requests, PHP and the browser should follow the current Complianz result. Also test missing/inactive Complianz with an old `allow` cookie; after `init` the helper must deny.

## Verify Pixel and Conversions API delivery

The browser Network panel can show Pixel requests and WordPress AJAX responses, but not WordPress's final server-to-server request to Meta.

1. On a fresh uncached page with Marketing denied, observe the browser queue and check for Pixel event requests to `www.facebook.com/tr`. Loading `fbevents.js` alone is not an event.
2. Grant Marketing using the banner. Inspect `fbpix_set_pixel_signals`, then `fbpix_release_signals`. After a normal successful response, `_held` becomes `false` and the browser queue empties.
3. Verify CAPI separately with a controlled event and a valid server connection. Use Meta Events Manager's regular event activity/diagnostics or developer instrumentation around the actual CAPI transport and its response. Correlate event name, time and ID where present. Do not log tokens, user data or complete request URLs.
4. **Test events requires a `test_event_code` for server events.** This guide's release path does not supply one. Browser Test events or a successful AJAX response does not establish server delivery; adding a server test code requires separate developer instrumentation.
5. Repeat while denied, including visitor AJAX submissions. Do not interpret an absent browser event as proof that no CAPI event was sent.

The release endpoint's `success` and `sent_count` describe its processing of the submitted queue, not Meta's acknowledgement. It can report success even when the CAPI sender fails or sends nothing. Browser replay can still occur in that case.

## Test cached pages

**Serve these pages uncached. The visitor's consent state is resolved in PHP on every request, so pages that use this example must be excluded from full-page caching.** PHP does not run on a cache hit. In the current plugin, `FacebookSignal.init()` receives `held: false` and falls back to it when the browser has no state cookie. A cached first visit can therefore start active even if the cached HTML was produced while held. The bridge only listens for subsequent consent changes.

To reproduce, prime a page cache, then open the cache hit in a fresh browser context with no signal-state cookie. Check the runtime and event requests before touching the banner. Also test HTML generated with permission served to a denied visitor, and the reverse. Never share cached `Set-Cookie` headers between visitors.

HTML generated with permission contains direct Pixel calls. Served to a visitor who has not chosen, it sends events to Meta, and the bridge does not react because Complianz dispatches no consent change on load.

A rendered `held: true` alone would not solve every cross-visitor cache variant. Keep caching disabled for these pages rather than treating a single successful test or cache purge as a fix. Performance tools must also preserve the order of the helper, Pixel base code, initialization and bridge. The state-update endpoint uses a nonce that can expire in cached HTML.

## Troubleshooting

| Problem                                                | What to check                                                                                                                                                                                                                                                           |
| :----------------------------------------------------- | :---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| The must-use plugin does not appear in WordPress       | Confirm that the PHP file is directly inside `wp-content/mu-plugins`, not in a nested folder.                                                                                                                                                                           |
| `window.fbwcsignal` is undefined                       | Confirm that Meta pixel for WordPress is connected and `facebook-signal` is enqueued. Check for earlier JavaScript errors.                                                                                                                                              |
| `window.FacebookSignal` is undefined while held        | Disable Complianz's automatic Official Facebook Pixel integration for this setup, check custom Script Center rules and clear cached pages.                                                                                                                              |
| `window.fbq` is undefined while held                   | Clear **Facebook** from the social media list in the wizard's Services step; Complianz blocks the Pixel base code while it is selected.                                                                                                                                 |
| Pixel is not initialized on pages that start active    | Same cause: Complianz loaded the base code after initialization. Clear **Facebook** from the Services step's social media list.                                                                                                                                         |
| Consent is lost on other pages                         | If the site URL includes a port (common on local development sites), Complianz 7.5.5 builds an invalid cookie path and consent cookies are set only for the current page's path. Enable Complianz's option to set cookies on the root, or test on a URL without a port. |
| `window.cmplz_has_consent` is undefined                | Confirm that the current Complianz banner script is loaded and the Complianz setup is complete.                                                                                                                                                                         |
| Signals remain held after permission                   | Confirm that Complianz reports `cmplz_has_consent( 'marketing' )` as `true` and dispatches a Marketing `cmplz_status_change` event.                                                                                                                                     |
| Signals are active unexpectedly before a choice        | Check Complianz's regional consent type. In an opt-out configuration, Complianz can allow Marketing when no category cookie exists.                                                                                                                                     |
| Granting permission does not release events            | Check the two WordPress AJAX requests and the browser Console for a release error.                                                                                                                                                                                      |
| Withdrawing permission does not reload                 | Confirm that Complianz dispatches `cmplz_status_change` with `category: marketing` and `value: deny`.                                                                                                                                                                   |
| The page repeatedly reloads                            | Check whether Complianz saves the updated choice and whether `wc_facebook_signals_state` can be written.                                                                                                                                                                |
| An AJAX request returns `403`                          | Clear cached pages and check for an expired nonce or a missing signal-state cookie.                                                                                                                                                                                     |
| Events from an earlier page are missing                | Only eligible events from the current page can be released. Previous-page events are not recovered.                                                                                                                                                                     |
| A Conversions API event is missing from Events Manager | Check the actual CAPI response or regular Events Manager activity. Server Test events requires separately configured `test_event_code`.                                                                                                                                 |

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
- A different Complianz category, older Complianz version or future API change may require adapting the integration.

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

Hold/Release Signals does not make a site automatically compliant with privacy laws. It provides a technical way to connect Complianz's Marketing result to Meta pixel for WordPress signal delivery.

The website owner remains responsible for determining:

- Whether and when permission is required.
- How the consent banner is presented.
- Which Complianz category should control Meta Signals.
- How Complianz regions and consent types are configured.
- How third-party scripts and custom integrations respond to the visitor's choice.

Third-party tools may interfere with or bypass this setup, so the complete site should be tested before the integration is used in production.
