/**
 * TowX model cascade + optional hidden sync + field labels (Gravity Forms TOPS).
 * Diagnostics go to the browser console only when enabled in form settings.
 */
(function ($) {
	'use strict';

	var LIGHTNING = '\u26A1';

	function fieldInputSelector(formId, fieldId) {
		if (!fieldId) {
			return '';
		}
		var fid = String(fieldId).replace(/\./g, '_');
		return '#input_' + formId + '_' + fid;
	}

	function i18n(key) {
		return typeof gf_tops_i18n !== 'undefined' && gf_tops_i18n[key] ? gf_tops_i18n[key] : key;
	}

	/** gfTopsStatus.debugConsole: Forms → Settings → TOPS (browser console) and/or per-form console toggle. */
	function topsConsole(formId, message, detail) {
		var st = window.gfTopsStatus && window.gfTopsStatus[formId];
		if (!st || !st.debugConsole) {
			return;
		}
		if (detail !== undefined && detail !== null) {
			console.info('[GF TOPS] form ' + formId + ' — ' + message, detail);
		} else {
			console.info('[GF TOPS] form ' + formId + ' — ' + message);
		}
	}

	function countMeaningfulOptions($select) {
		if (!$select || !$select.length || !$select.is('select')) {
			return null;
		}
		return $select.find('option').filter(function () {
			return $(this).val() !== '';
		}).length;
	}

	/** One-line integration summary when diagnostics are on (replaces old visible status box). */
	function logIntegrationSnapshot(formId) {
		var st = window.gfTopsStatus && window.gfTopsStatus[formId];
		if (!st || !st.debugConsole) {
			return;
		}
		var $makeSel = $(fieldInputSelector(formId, st.makeFieldId));
		var $colorSel = st.colorFieldId ? $(fieldInputSelector(formId, st.colorFieldId)) : $();
		var domMakes = countMeaningfulOptions($makeSel);
		var domColors = countMeaningfulOptions($colorSel);
		topsConsole(formId, 'Integration snapshot', {
			api: st.apiEnvironmentLabel,
			populateVehicleLists: st.populateVehicleLists,
			cascadeEnabled: st.cascadeEnabled,
			makesInDom: domMakes,
			colorsInDom: domColors,
			makeFieldId: st.makeFieldId,
			modelFieldId: st.modelFieldId,
			colorFieldId: st.colorFieldId,
		});
	}

	function bindMappedFieldConsoleLogging(formId) {
		var st = window.gfTopsStatus && window.gfTopsStatus[formId];
		if (!st || !st.debugConsole) {
			return;
		}
		function bindSel(label, fieldId) {
			if (!fieldId) {
				return;
			}
			var sel = fieldInputSelector(formId, fieldId);
			$(document)
				.off('change.gfTopsConsole', sel)
				.on('change.gfTopsConsole', sel, function () {
					var $o = $(this);
					topsConsole(formId, label + ' field changed', {
						fieldId: String(fieldId),
						value: $o.val(),
						label: $o.find('option:selected').text(),
					});
				});
		}
		bindSel('Make', st.makeFieldId);
		bindSel('Model', st.modelFieldId);
		bindSel('Color', st.colorFieldId);
		topsConsole(formId, 'Verbose console logging enabled (Forms → Settings → TOPS or Form Settings → TOPS).', {
			makeFieldId: st.makeFieldId,
			modelFieldId: st.modelFieldId,
			colorFieldId: st.colorFieldId,
			populateVehicleLists: st.populateVehicleLists,
			cascadeEnabled: st.cascadeEnabled,
		});
	}

	/**
	 * Append ⚡ to labels for TowX-mapped fields so production/staff can see what is API-backed.
	 */
	function annotateMappedFieldLabels(formId) {
		var st = window.gfTopsStatus && window.gfTopsStatus[formId];
		if (!st) {
			return;
		}
		function mark(fieldId) {
			if (!fieldId) {
				return;
			}
			var fid = String(fieldId).replace(/\./g, '_');
			var $field = $('#field_' + formId + '_' + fid);
			if (!$field.length) {
				return;
			}
			var $lab = $field.find('fieldset legend.gfield_label').first();
			if (!$lab.length) {
				$lab = $field.find('label.gfield_label').first();
			}
			if (!$lab.length) {
				$lab = $field.find('.gfield_label').first();
			}
			if (!$lab.length) {
				return;
			}
			if ($lab.find('.gf-tops-source-marker').length) {
				return;
			}
			$lab.append(
				$('<span class="gf-tops-source-marker" aria-label="TowX / TOPS" title="TowX / TOPS">' + LIGHTNING + '</span>')
			);
		}
		mark(st.makeFieldId);
		mark(st.modelFieldId);
		mark(st.colorFieldId);
	}

	function setHiddenVal(formId, fieldId, val) {
		if (!fieldId) {
			return;
		}
		var $h = $(fieldInputSelector(formId, fieldId));
		if ($h.length) {
			$h.val(val || '').trigger('change');
		}
	}

	function updateModelsStatusLine(formId, phase, message) {
		topsConsole(formId, 'Models (' + phase + ')', message);
	}

	function logCascade(formId, ok, msg, detail) {
		var st = window.gfTopsStatus && window.gfTopsStatus[formId];
		if (!st || !st.debugConsole) {
			return;
		}
		var prefix = ok ? '\u2713 ' : '\u2717 ';
		topsConsole(formId, prefix + msg, detail);
	}

	function bindForm(formId) {
		var cfg = window.gfTopsForms && window.gfTopsForms[formId];
		if (!cfg) {
			return;
		}

		var dbg = !!cfg.debug;

		var makeSel = fieldInputSelector(formId, cfg.makeFieldId);
		var modelSel = fieldInputSelector(formId, cfg.modelFieldId);
		var $make = $(makeSel);
		var $model = $(modelSel);

		if (!$make.length || !$model.length) {
			updateModelsStatusLine(formId, 'error', i18n('statusUnknownField') + ' (' + makeSel + ' / ' + modelSel + ')');
			if (dbg) {
				logCascade(formId, false, 'Could not find Make and/or Model fields.', {
					expectedMake: makeSel,
					expectedModel: modelSel,
					makeFound: $make.length,
					modelFound: $model.length,
				});
				console.warn('[GF TOPS] Missing fields for form ' + formId, {
					makeSel: makeSel,
					modelSel: modelSel,
				});
			}
			return;
		}

		if (dbg) {
			logCascade(
				formId,
				true,
				'Bound Make \u2192 Model cascade.',
				'Make #' +
					cfg.makeFieldId +
					' | Model #' +
					cfg.modelFieldId +
					(cfg.colorFieldId ? ' | Color #' + cfg.colorFieldId : '')
			);
			if (cfg.hiddenMakeId || cfg.hiddenModelId || cfg.hiddenColorId) {
				logCascade(formId, true, 'Hidden sync IDs', {
					make: cfg.hiddenMakeId || '\u2014',
					model: cfg.hiddenModelId || '\u2014',
					color: cfg.hiddenColorId || '\u2014',
				});
			}
		}

		var $color = cfg.colorFieldId ? $(fieldInputSelector(formId, cfg.colorFieldId)) : $();

		function syncFromSelections() {
			setHiddenVal(formId, cfg.hiddenMakeId, $make.val());
			setHiddenVal(formId, cfg.hiddenModelId, $model.val());
			if ($color.length) {
				setHiddenVal(formId, cfg.hiddenColorId, $color.val());
			}
		}

		$make.off('change.gfTopsSync').on('change.gfTopsSync', function () {
			setHiddenVal(formId, cfg.hiddenMakeId, $(this).val());
			if (!$(this).val()) {
				setHiddenVal(formId, cfg.hiddenModelId, '');
				setHiddenVal(formId, cfg.hiddenColorId, '');
			}
		});

		$model.off('change.gfTopsSync').on('change.gfTopsSync', function () {
			setHiddenVal(formId, cfg.hiddenModelId, $(this).val());
		});

		if ($color.length) {
			$color.off('change.gfTopsSync').on('change.gfTopsSync', function () {
				setHiddenVal(formId, cfg.hiddenColorId, $(this).val());
			});
		}

		$make.off('change.gfTops').on('change.gfTops', function () {
			var makeKey = $(this).val();
			$model.empty();
			if (!makeKey) {
				updateModelsStatusLine(formId, 'pending', i18n('statusModelsPending'));
				$model.append(
					$('<option></option>').val('').text(i18n('selectModel') || 'Select a model')
				);
				if (dbg) {
					logCascade(formId, true, 'Make cleared; model dropdown reset.', null);
				}
				return;
			}

			updateModelsStatusLine(formId, 'loading', i18n('statusModelsLoading'));

			var postData = {
				action: 'gf_tops_models',
				nonce: cfg.nonce,
				form_id: formId,
				make_key: makeKey,
				debug: cfg.debug ? '1' : '0',
			};

			if (dbg) {
				logCascade(formId, true, 'Requesting models for make_key', makeKey);
			}

			$.post(cfg.ajaxUrl, postData)
				.done(function (resp) {
					if (!resp || !resp.success) {
						updateModelsStatusLine(formId, 'error', i18n('statusModelsErr').replace('%s', 'AJAX'));
						if (dbg) {
							logCascade(formId, false, 'AJAX response not successful.', resp);
						}
						console.warn('[GF TOPS] gf_tops_models not successful', resp);
						return;
					}
					var data = resp.data || {};
					var models = data.models ? data.models : [];

					if (data.error) {
						var errMsg = i18n('statusModelsErr').replace('%s', data.error);
						updateModelsStatusLine(formId, 'error', errMsg);
						if (dbg) {
							logCascade(formId, false, data.error, data.debug);
						}
						console.warn('[GF TOPS] GetModelsForMake:', data.error, data.debug || '');
					} else {
						var okText = i18n('statusModelsOk').replace('%d', String(models.length));
						updateModelsStatusLine(formId, 'ok', okText);
					}

					$model.empty();
					$model.append(
						$('<option></option>').val('').text(i18n('selectModel') || 'Select a model')
					);
					models.forEach(function (m) {
						$model.append($('<option></option>').val(m.key).text(m.name));
					});

					if (dbg && !data.error) {
						logCascade(formId, true, 'Loaded ' + models.length + ' model(s).', data.debug);
					} else if (!dbg && models.length === 0 && !data.error) {
						console.warn('[GF TOPS] Zero models returned for make_key (no error message).');
					}

					setHiddenVal(formId, cfg.hiddenModelId, '');
				})
				.fail(function (xhr) {
					$model.empty();
					var shortErr =
						'HTTP ' +
						(xhr.status || '?') +
						(xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message
							? ': ' + xhr.responseJSON.data.message
							: '');
					updateModelsStatusLine(
						formId,
						'error',
						i18n('statusModelsErr').replace('%s', shortErr || xhr.statusText || 'error')
					);
					var msg =
						'HTTP ' +
						(xhr.status || '?') +
						(xhr.responseText ? ': ' + xhr.responseText.slice(0, 200) : '');
					if (dbg) {
						logCascade(formId, false, 'Model request failed.', msg);
					}
					console.warn('[GF TOPS] Model AJAX failed', xhr.status, xhr);
				});
		});

		syncFromSelections();

		var meaningfulModels = $model.find('option').filter(function () {
			return $(this).val() !== '';
		}).length;
		if ($make.val() && meaningfulModels === 0) {
			$make.trigger('change');
		}
	}

	function initTopsUi(formId) {
		formId = parseInt(formId, 10);
		if (!formId) {
			return;
		}
		$('#gf-tops-status-' + formId + ', #gf-tops-cascade-debug-' + formId).remove();

		annotateMappedFieldLabels(formId);
		bindForm(formId);
		logIntegrationSnapshot(formId);
		bindMappedFieldConsoleLogging(formId);
	}

	$(document).on('gform_post_render', function (e, formId) {
		initTopsUi(formId);
	});

	$(function () {
		if (typeof window.gfTopsStatus === 'object' && window.gfTopsStatus !== null) {
			Object.keys(window.gfTopsStatus).forEach(function (id) {
				initTopsUi(parseInt(id, 10));
			});
		}
	});
})(jQuery);
