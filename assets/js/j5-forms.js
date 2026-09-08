/* ============================================================================
 * J5 Forms — Frontend JS
 *   - Captures form mount timestamp (already in hidden field, but JS refreshes it)
 *   - Submits via fetch() to admin-ajax.php — no page reload
 *   - Inline error display per field
 *   - File-drop UX (drag/drop + selected file list)
 * ==========================================================================*/

(function () {
	if (typeof window === 'undefined' || !window.J5Forms) return;

	document.addEventListener('DOMContentLoaded', init);

	function init() {
		document.querySelectorAll('.j5-forms-wrap').forEach(setupWrap);
	}

	function setupWrap(wrap) {
		const form = wrap.querySelector('form.j5-form');
		if (!form) return;

		// Conditional fields (e.g. agency name shows when agency_flag is checked)
		setupConditionalFields(form);

		// File drop UX (quote form only)
		const fileDrop = wrap.querySelector('.file-drop');
		if (fileDrop) {
			setupFileDrop(fileDrop);
		}

		form.addEventListener('submit', function (e) {
			e.preventDefault();
			submitForm(wrap, form);
		});
	}

	/**
	 * Generic conditional field toggle.
	 * Any element with [data-j5f-conditional-on="<field-name>"] is shown only
	 * when the named field (assumed to be a checkbox) is checked. Disabled
	 * inputs inside it don't submit, so server-side validation is clean.
	 */
	function setupConditionalFields(form) {
		const targets = form.querySelectorAll('[data-j5f-conditional-on]');
		targets.forEach(function (target) {
			const triggerName = target.dataset.j5fConditionalOn;
			const trigger = form.querySelector('[name="' + triggerName + '"]');
			if (!trigger) return;

			const update = function () {
				const isOn = !!trigger.checked;
				target.classList.toggle('is-visible', isOn);
				target.querySelectorAll('input, select, textarea').forEach(function (el) {
					el.disabled = !isOn;
				});
			};

			trigger.addEventListener('change', update);
			update(); // initial state on load
		});
	}

	function setupFileDrop(drop) {
		const input = drop.querySelector('input[type=file]');
		const list  = drop.querySelector('.file-drop-list');
		if (!input) return;

		// Prevent label-click from bubbling weirdly when interacting with the list
		drop.addEventListener('click', function (e) {
			if (e.target.tagName !== 'INPUT') {
				input.click();
				e.preventDefault();
			}
		});

		drop.addEventListener('dragover', function (e) {
			e.preventDefault();
			drop.classList.add('is-dragover');
		});
		drop.addEventListener('dragleave', function () {
			drop.classList.remove('is-dragover');
		});
		drop.addEventListener('drop', function (e) {
			e.preventDefault();
			drop.classList.remove('is-dragover');
			if (e.dataTransfer && e.dataTransfer.files) {
				input.files = e.dataTransfer.files;
				renderFileList(input, list);
			}
		});

		input.addEventListener('change', function () {
			renderFileList(input, list);
		});
	}

	function renderFileList(input, list) {
		if (!list) return;
		list.innerHTML = '';
		if (!input.files || input.files.length === 0) {
			list.hidden = true;
			return;
		}
		list.hidden = false;
		const ul = document.createElement('ul');
		ul.style.listStyle = 'none';
		ul.style.padding = '0';
		ul.style.margin = '0';
		Array.prototype.forEach.call(input.files, function (f) {
			const li = document.createElement('li');
			li.textContent = f.name + '  (' + Math.round(f.size / 1024) + ' KB)';
			ul.appendChild(li);
		});
		list.appendChild(ul);
	}

	function submitForm(wrap, form) {
		const submitBtn = form.querySelector('button[type=submit]');
		const errorBox  = wrap.querySelector('.j5-forms-error');
		const successBox = wrap.querySelector('.j5-forms-success');

		// Clear prior error states
		errorBox.hidden = true;
		errorBox.textContent = '';
		form.querySelectorAll('.is-invalid').forEach(function (el) { el.classList.remove('is-invalid'); });

		// Build form data — copies all fields including files.
		const fd = new FormData(form);
		fd.append('action', 'j5_form_submit');

		// Disable submit, show loading state.
		if (submitBtn) {
			submitBtn.classList.add('is-loading');
			submitBtn.disabled = true;
			submitBtn.dataset.origLabel = submitBtn.innerHTML;
			submitBtn.innerHTML = 'Sending…';
		}

		fetch(window.J5Forms.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			body: fd,
		})
			.then(function (r) { return r.json().catch(function () { return { success: false, data: { message: 'Server returned a non-JSON response.' } }; }); })
			.then(function (json) {
				if (json && json.success) {
					form.hidden = true;
					if (successBox) {
						successBox.hidden = false;
						successBox.scrollIntoView({ behavior: 'smooth', block: 'center' });
					}
				} else {
					const data = (json && json.data) || {};
					errorBox.textContent = data.message || 'Something went wrong. Please try again.';
					errorBox.hidden = false;

					// Highlight invalid fields if the server sent per-field errors.
					if (data.errors && typeof data.errors === 'object') {
						Object.keys(data.errors).forEach(function (key) {
							const el = form.querySelector('[name="' + key + '"]');
							if (el) el.classList.add('is-invalid');
						});
						const firstBad = form.querySelector('.is-invalid');
						if (firstBad) firstBad.focus();
					}

					restoreSubmit(submitBtn);
				}
			})
			.catch(function () {
				errorBox.textContent = 'Network error. Please try again or call us at (630) 442-4938.';
				errorBox.hidden = false;
				restoreSubmit(submitBtn);
			});
	}

	function restoreSubmit(btn) {
		if (!btn) return;
		btn.classList.remove('is-loading');
		btn.disabled = false;
		if (btn.dataset.origLabel) {
			btn.innerHTML = btn.dataset.origLabel;
		}
	}
})();
