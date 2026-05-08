/**
 * Gravity Forms TOPS — form settings (test auth).
 *
 * Uses dataType "text" + manual JSON parse so TowX errors still show when WordPress
 * returns HTTP 400/500 (jQuery would otherwise skip responseJSON on .fail()).
 */
(function ($) {
	'use strict';

	/**
	 * Clicks Gravity Forms’ primary form-settings save control (same as the footer save).
	 *
	 * @return {boolean} True if a control was triggered.
	 */
	function triggerGravityFormsSettingsSave() {
		var selectors = [
			'#gform-settings-save',
			'input[name="gform-settings-save"]',
			'button[name="gform-settings-save"]',
			'form#gform-settings input[type="submit"].button-primary',
			'form#gform-settings button[type="submit"].button-primary',
			'form#gaddon-form input[type="submit"].button-primary',
			'form#gaddon-form button[type="submit"].button-primary',
			'.gform-settings__footer input[type="submit"]',
			'.gform-settings__footer button[type="submit"]',
		];
		var i;
		for (i = 0; i < selectors.length; i++) {
			var $el = $(selectors[i]).first();
			if ($el.length) {
				$el[0].click();
				return true;
			}
		}
		return false;
	}

	function renderTestResult($out, data, ok) {
		$out
			.removeClass('gf-tops-success gf-tops-error')
			.addClass(ok ? 'gf-tops-success' : 'gf-tops-error')
			.empty();

		if (!data) {
			data = {};
		}
		if (!data.message && data.details) {
			data.message = gfTopsAdmin.i18n.testFail;
		}
		if (data.message) {
			$out.append(
				$('<p class="gf-tops-test-auth-summary"/>').text(data.message)
			);
		}
		if (data.details) {
			$out.append(
				$('<pre class="gf-tops-test-auth-details" spellcheck="false"/>').text(
					data.details
				)
			);
		}
		if (!data.message && !data.details) {
			$out.append(
				$('<p class="gf-tops-test-auth-summary"/>').text(
					gfTopsAdmin.i18n.testFail
				)
			);
		}
	}

	$(document).on('click', '.gf-tops-section-save', function (e) {
		e.preventDefault();
		if (!triggerGravityFormsSettingsSave()) {
			if (typeof gfTopsAdmin !== 'undefined' && gfTopsAdmin.i18n && gfTopsAdmin.i18n.saveNotFound) {
				window.alert(gfTopsAdmin.i18n.saveNotFound);
			}
		}
	});

	$(document).on('click', '.gf-tops-test-auth', function (e) {
		e.preventDefault();
		if (typeof gfTopsAdmin === 'undefined') {
			return;
		}

		var $btn = $(this);
		var fid = gfTopsAdmin.formId || 0;
		var $out =
			fid > 0
				? $('#gf-tops-test-auth-result-' + fid)
				: $();
		if (!$out.length) {
			$out = $btn.closest('td, .gform-settings-field__content').find('.gf-tops-test-auth-result').first();
		}
		if (!$out.length) {
			$out = $btn.siblings('.gf-tops-test-auth-result');
		}

		var $root = $btn.closest(
			'.gform-settings__content, .gform-settings-panel, .gform-settings, #gform-settings, form#gaddon-form, .wrap'
		);
		if (!$root.length) {
			$root = $(document.body);
		}

		function readAddonField(field) {
			var exact = '_gaddon_setting_' + field;
			var $exact = $root.find('[name="' + exact + '"]').filter('input, textarea, select');
			if ($exact.length) {
				return String($exact.val() || '').trim();
			}
			var $match = $root
				.find('input, textarea, select')
				.filter(function () {
					var n = this.name || '';
					return n === exact || (n.indexOf(field) !== -1 && n.indexOf('gaddon') !== -1);
				})
				.first();
			if ($match.length) {
				return String($match.val() || '').trim();
			}
			return '';
		}

		var session = readAddonField('tops_session_id');
		var authKey = readAddonField('tops_auth_key');

		$btn.prop('disabled', true);
		$out.removeClass('gf-tops-success gf-tops-error').text(gfTopsAdmin.i18n.testing);

		$.ajax({
			url: gfTopsAdmin.ajaxUrl,
			type: 'POST',
			data: {
				action: 'gf_tops_test_auth',
				nonce: gfTopsAdmin.nonce,
				form_id: fid,
				session_id: session,
				auth_key: authKey,
			},
			dataType: 'text',
			complete: function (jqXHR) {
				$btn.prop('disabled', false);

				var text = (jqXHR.responseText || '').trim();
				var status = jqXHR.status;
				var parsed = null;

				try {
					parsed = text ? JSON.parse(text) : null;
				} catch (err) {
					renderTestResult($out, {
						message: gfTopsAdmin.i18n.testFail,
						details:
							'HTTP ' +
							status +
							'\nJSON parse error: ' +
							err.message +
							'\n\nRaw response:\n' +
							text.substring(0, 8000),
					}, false);
					return;
				}

				if (!parsed || typeof parsed !== 'object') {
					renderTestResult($out, {
						message: gfTopsAdmin.i18n.testFail,
						details:
							'HTTP ' +
							status +
							'\n\nUnexpected empty or non-object JSON.\n\n' +
							text.substring(0, 8000),
					}, false);
					return;
				}

				if (parsed.success && parsed.data) {
					renderTestResult($out, parsed.data, true);
					return;
				}

				var err = parsed.data;
				if (err == null || typeof err !== 'object') {
					err = {
						message:
							err != null && err !== ''
								? String(err)
								: gfTopsAdmin.i18n.testFail,
					};
				}
				if (!err.message) {
					err.message = gfTopsAdmin.i18n.testFail;
				}
				if (!err.details) {
					if (text) {
						err.details =
							'HTTP ' +
							status +
							(jqXHR.statusText ? ' ' + jqXHR.statusText : '') +
							'\n\n' +
							(text.length > 8000 ? text.substring(0, 8000) + '\n…' : text);
					} else if (status === 0) {
						err.details =
							'No response body (HTTP 0). Often a network drop, timeout, or admin-ajax blocked. Check the browser Network tab for admin-ajax.php.';
					}
				}
				renderTestResult($out, err, false);
			},
		});
	});
})(jQuery);
