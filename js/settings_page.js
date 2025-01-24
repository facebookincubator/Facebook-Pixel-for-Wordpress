window.fbAsyncInit = function () {
	FB.init({
		appId: "221646389321681",
		autoLogAppEvents: true,
		xfbml: true,
		version: "v13.0",
	});
};

window.facebookBusinessExtensionConfig = {
	pixelId: meta_wc_params.pixelId,
	popupOrigin: "https://business.facebook.com",
	setSaveSettingsRoute: meta_wc_params.setSaveSettingsRoute,
	externalBusinessId: meta_wc_params.externalBusinessId,
	fbeLoginUrl: "https://business.facebook.com/fbe-iframe-get-started/?",
	deleteConfigKeys: meta_wc_params.deleteConfigKeys,
	appId: "221646389321681",
	timeZone: "America/Los_Angeles",
	installed: meta_wc_params.installed,
	systemUserName: meta_wc_params.systemUserName + "_system_user",
	businessVertical: "ECOMMERCE",
	version: "v8.0",
	currency: "USD",
	businessName: "Solutions Engineering Team",
	debug: true,
	channel: "DEFAULT",
};

var hasAccessToken = jQuery("#fb-adv-conf").attr("data-access-token");

if ("false" == hasAccessToken) {
	jQuery("#fb-adv-conf").hide();
} else {
	// Set advanced configuration top relative to fbe iframe
	setFbAdvConfTop();
	jQuery("#fb-adv-conf").show();
	jQuery("#fb-capi-ef").show();

	var enablePiiCachingCheckbox = document.getElementById("capi-ch");
	var piiCachingStatus = meta_wc_params.piiCachingStatus;
	updateCapiPiiCachingCheckbox(piiCachingStatus);
	enablePiiCachingCheckbox.addEventListener("change", function () {
		if (this.checked) {
			saveCapiPiiCachingStatus("1");
		} else {
			saveCapiPiiCachingStatus("0");
		}
	});
	function setFbAdvConfTop() {
		var fbeIframeTop = 0;
		// Add try catch to handle any error and avoid breaking js
		try {
			fbeIframeTop = jQuery("#fbe-iframe")[0].getBoundingClientRect().top;
		} catch (e) {}

		var fbAdvConfTop = meta_wc_params.fbAdvConfTop + fbeIframeTop;
		jQuery("#fb-adv-conf").css({ top: fbAdvConfTop + "px" });
	}

	var enablePageViewFilterCheckBox = document.getElementById("capi-ef");
	var capiIntegrationPageViewFiltered =
		meta_wc_params.capiIntegrationPageViewFiltered === "true" ? "1" : "0";
	updateCapiIntegrationEventsFilter(capiIntegrationPageViewFiltered);
	enablePageViewFilterCheckBox.addEventListener("change", function () {
		saveCapiIntegrationEventsFilter(this.checked ? "1" : "0");
	});
	function updateCapiPiiCachingCheckbox(val) {
		if (val === "1") {
			enablePiiCachingCheckbox.checked = true;
		} else {
			enablePiiCachingCheckbox.checked = false;
		}
	}
	function updateCapiIntegrationEventsFilter(val) {
		enablePageViewFilterCheckBox.checked = val === "1" ? true : false;
	}
	function saveCapiPiiCachingStatus(new_val) {
		jQuery
			.ajax({
				type: "post",
				dataType: "json",
				url: meta_wc_params.capiPiiCachingStatusSaveUrl,
				data: {
					action: meta_wc_params.capiPiiCachingStatusActionName,
					val: new_val,
				},
				success: function (response) {
					updateCapiPiiCachingCheckbox(new_val);
				},
			})
			.fail(function (jqXHR, textStatus, error) {
				jQuery("#fb-capi-se").text(
					meta_wc_params.capiPiiCachingStatusUpdateError
				);
				jQuery("#fb-capi-se").show().delay(3000).fadeOut();
				updateCapiPiiCachingCheckbox(new_val === "1" ? "0" : "1");
			});
	}
	function saveCapiIntegrationEventsFilter(new_val) {
		jQuery
			.ajax({
				type: "post",
				dataType: "json",
				url: meta_wc_params.capiIntegrationEventsFilterSaveUrl,
				data: {
					action: meta_wc_params.capiIntegrationEventsFilterActionName,
					val: new_val,
				},
				success: function (response) {},
			})
			.fail(function (jqXHR, textStatus, error) {
				jQuery("#fb-capi-ef-se").text(
					meta_wc_params.capiIntegrationEventsFilterUpdateError
				);
				jQuery("#fb-capi-ef-se").show().delay(3000).fadeOut();
				updateCapiIntegrationEventsFilter(new_val === "1" ? "0" : "1");
			});
	}
}
var currentFBEInstalledStatus = meta_wc_params.installed;
jQuery("#ad-creation-plugin-iframe").attr("data-fbe-extras", getFBEExtras());
jQuery("#ad-insights-plugin-iframe").attr("data-fbe-extras", getFBEExtras());
updateAdInsightsPlugin(currentFBEInstalledStatus);

function getFBEExtras() {
	$fbeConfig = window.facebookBusinessExtensionConfig;
	return JSON.stringify({
		business_config: {
			business: {
				name: $fbeConfig.businessName,
			},
		},
		setup: {
			external_business_id: $fbeConfig.externalBusinessId,
			timezone: $fbeConfig.timeZone,
			currency: $fbeConfig.currency,
			business_vertical: $fbeConfig.businessVertical,
			channel: $fbeConfig.channel,
		},
		repeat: false,
	});
}
function updateAdInsightsPlugin(isFBEInstalled) {
	if (isFBEInstalled) {
		jQuery("#meta-ads-plugin").show();
	} else {
		jQuery("#meta-ads-plugin").hide();
	}
}

function sendTestEvent(e) {
	e.preventDefault();
	var advancedPayloadElement = document.getElementById("advanced-payload");
	var testEventCode = "";
	var testEventName = "";
	var data = "";
	if (advancedPayloadElement.classList.contains("open")) {
		if (!advancedPayloadElement.value) {
			alert("You must enter payload.");
			return;
		}
		advancedPayload = advancedPayloadElement.value;
		try {
			data = JSON.parse(advancedPayload);
			if (data.test_event_code) {
				testEventCode = data.test_event_code;
			}
			testEventName += data.data
				.map((event) => event.event_name)
				.join(", ");
		} catch (e) {
			alert("Invalid JSON in payload.");
			return;
		}
	} else {
		testEventCode = document.getElementById("event-test-code").value;
		testEventName = document.getElementById("test-event-name").value;
	}

	if (!testEventCode) {
		alert("You must enter test event code.");
		return;
	}

	jQuery.ajax({
		type: "POST",
		url: meta_wc_params.ajax_url,
		data: {
			action: "send_capi_event",
			nonce: meta_wc_params.send_capi_event_nonce,
			event_name: testEventName,
			test_event_code: testEventCode,
			payload: data,
			custom_data: setCustomData(data, testEventName),
			user_data: {
				ph: "254aa248acb47dd654ca3ea53f48c2c26d641d23d7e2e93a1ec56258df7674c4",
				em: "309a0a5c3e211326ae75ca18196d301a9bdbd1a882a4d2569511033da23f0abd",
			},
		},
		success: function (response) {
			data = JSON.parse(response.data);
			if (!data.error) {
				document
					.querySelector(".event-log-block>table>tbody")
					.insertAdjacentHTML(
						"beforeend",
						`<tr><td clas="test-event-td">${testEventCode}</td><td><span class="test-event-pill test-event-pill--type">${testEventName}</span></td><td><span class="test-event-pill test-event-pill--success">Success</span></td></tr>`
					);
			} else {
				let tableRow = `
				<tr class="test-event--error">
					<td class="test-event-td test-event-td--error">
						${data.error.message}
					</td>
					<td class="test-event-pill test-event-pill--type">${testEventName}</td>
					<td style="margin-left:auto;">
						<span class="test-event-pill test-event-button--error">
							Error
							<svg id="show-error-btn" class="show-error-icon" width="12" height="8" viewBox="0 0 14 8" fill="none" xmlns="http://www.w3.org/2000/svg">
								<path d="M2 0L7 5L12 0L14 1L7 8L0 1L2 0Z" fill="#555555"></path>
							</svg>
						</span>
					</td>
					<td class="test-event-msg--error hidden">
						${data.error.error_user_msg}
					</td>
				</tr>
				`;

				document
					.querySelector(".event-log-block>table>tbody")
					.insertAdjacentHTML("beforeend", tableRow);

				const testErrorButton = document.querySelectorAll(
					".test-event-button--error"
				);
				testErrorButton.forEach((button) => {
					button.addEventListener("click", handleErrorMessageToggle);
				});
			}

			const eventHintsText = document.querySelector(".event-hints__text");

			if (eventHintsText.classList.contains("initial-text")) {
				const noteCloseButton = document.querySelector(
					".event-hints__close-icon"
				);

				noteCloseButton.addEventListener("click", () => {
					document
						.querySelector(".event-hints")
						.classList.add("hidden");
				});
				eventHintsText.textContent =
					"Note that events can take up to a few minutes to appear in the Events Manager.";
				eventHintsText.classList.remove("initial-text");
				noteCloseButton.classList.remove("hidden");
			}
		},

		error: function (error) {
			console.log(error);
		},
	});
}

function setCustomData(data, testEventName) {
	if (data) {
		return;
	} else {
		if (
			[
				"Purchase",
				"AddToCart",
				"InitiateCheckout",
				"ViewContent",
				"Search",
				"AddPaymentInfo",
				"AddToWishlist",
			].includes(testEventName)
		) {
			return {
				value: 123.321,
				currency: "USD",
				content_type: "product",
			};
		} else {
			return null;
		}
	}
}

function handleErrorMessageToggle() {
	const errorRow = this.closest(".test-event--error");

	if (!errorRow) {
		return;
	}

	this.firstElementChild.classList.toggle("open");
	const errorMessage = errorRow.querySelector(".test-event-msg--error");

	if (errorMessage) {
		toggleHeight(errorMessage);
	}
}

function toggleAdvancedPayload() {
	document.getElementById("advanced-payload").classList.toggle("open");
	document.getElementById("populate-payload-button").classList.toggle("show");
	document
		.querySelector(".advanced-edit-toggle-arrow")
		.classList.toggle("open");

	if (
		!document.getElementById("advanced-payload").value &&
		document.getElementById("advanced-payload").classList.contains("open")
	) {
		populateAdvancedEvent();
	}
}

function populateAdvancedEvent() {
	testEventName = document.getElementById("test-event-name").value;
	testEventCode = document.getElementById("event-test-code").value;
	var exampleEvent = {
		data: [
			{
				event_name: testEventName,
				event_time: Math.floor(Date.now() / 1000),
				event_id: "event.id." + Math.floor(Math.random() * 901 + 100),
				event_source_url: window.location.origin,
				action_source: "website",
				user_data: {
					em: [
						"309a0a5c3e211326ae75ca18196d301a9bdbd1a882a4d2569511033da23f0abd",
					],
					ph: [
						"254aa248acb47dd654ca3ea53f48c2c26d641d23d7e2e93a1ec56258df7674c4",
					],
				},
				custom_data: {
					value: 100.2,
					currency: "USD",
					content_ids: ["product.id.123"],
					content_type: "product",
				},
			},
		],
		test_event_code: testEventCode ? testEventCode : "TEST4039",
	};
	if (
		![
			"Purchase",
			"AddToCart",
			"InitiateCheckout",
			"ViewContent",
			"Search",
			"AddPaymentInfo",
			"AddToWishlist",
		].includes(testEventName)
	) {
		delete exampleEvent.data[0].custom_data;
	}
	document.getElementById("advanced-payload").value = JSON.stringify(
		exampleEvent,
		null,
		2
	);
}

// Function to toggle height with transition
function toggleHeight(element) {
	if (element.style.height === "0px" || element.style.height === "") {
		element.style.height = `fit-content`;
		element.classList.remove("hidden");
	} else {
		element.style.height = "0";
		element.classList.add("hidden");
	}
}
