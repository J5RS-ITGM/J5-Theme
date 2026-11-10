/* J5 Armor Comparison Tool — inc/j5-armor-reference.php renders the full list
   server-side; this adds filtering, sorting and the compare cards. No deps. */
(function () {
	'use strict';

	var MAX_PICK = 6;
	var LOWER_BETTER  = /weight|oz_per_sqin/;
	var HIGHER_BETTER = /^(area_sqin|size_count)$/;

	function num(v) {
		if (typeof v === 'number') return v;
		if (typeof v === 'string') { var m = v.match(/-?\d+(?:\.\d+)?/); if (m) return parseFloat(m[0]); }
		return null;
	}
	function fmt(v, field) {
		if (v === null || v === undefined || v === '') return null;
		if (typeof v === 'string' && /^\[.*\]$/.test(v.trim())) v = toList(v);
		// Tables (threat matrix) are drawn on their own; "Array" is what old pushes stored for them.
		if (Array.isArray(v) && (v.some(function (x) { return x && typeof x === 'object'; }) || (v.length && v.every(function (x) { return x === 'Array'; })))) return null;
		if (typeof v === 'boolean') return v ? 'Yes' : 'No';
		if (Array.isArray(v)) return v.join(', ');
		if (typeof v === 'number') {
			var s = (Math.round(v * 100) / 100).toLocaleString('en-US', { maximumFractionDigits: 2 });
			return field && field.unit ? s + ' ' + field.unit : s;
		}
		return String(v);
	}
	function viewportArea(v) {
		if (!v || typeof v !== 'string') return 0;
		var m = v.match(/(\d+(?:\.\d+)?)\s*[x×]\s*(\d+(?:\.\d+)?)/i);
		if (m) return parseFloat(m[1]) * parseFloat(m[2]);
		return /^(no|none|n\/a|without)/i.test(v.trim()) ? 0 : 1; // present, size unknown
	}
	/** Standard SAPI / ESAPI plate cuts, inches. */
	var SAPI = [
		{ name: 'SAPI XS', w: 7.25, h: 11.5 },
		{ name: 'SAPI Small', w: 8.75, h: 11.75 },
		{ name: 'SAPI Medium', w: 9.5, h: 12.5 },
		{ name: 'SAPI Large', w: 10.25, h: 13.25 },
		{ name: 'SAPI XL', w: 11, h: 14 }
	];
	/** Parsed, normalized size: { w, h, key, label, sub } or null. 8.0 x 10.0 and 8 x 10 become one size. */
	function sizeOf(d) {
		var w = null, h = null, raw = d.specs && d.specs.size ? String(d.specs.size) : '';
		var m = raw.match(/(\d+(?:\.\d+)?)\s*[x×]\s*(\d+(?:\.\d+)?)/i);
		if (m) { w = parseFloat(m[1]); h = parseFloat(m[2]); }
		else { w = num(d.specs && d.specs.width_in); h = num(d.specs && d.specs.height_in); }
		if (!w || !h) return raw ? { w: 0, h: 0, key: raw.toLowerCase().replace(/\s+/g, ''), label: raw, sub: '' } : null;
		var sapi = null;
		for (var i = 0; i < SAPI.length; i++) if (Math.abs(SAPI[i].w - w) <= 0.1 && Math.abs(SAPI[i].h - h) <= 0.1) { sapi = SAPI[i]; w = sapi.w; h = sapi.h; break; }
		var fmtn = function (n) { return String(Math.round(n * 100) / 100); };
		return { w: w, h: h, key: fmtn(w) + 'x' + fmtn(h), label: fmtn(w) + ' x ' + fmtn(h) + ' in', sub: sapi ? sapi.name : '' };
	}
	function sizeKey(d) { var sz = sizeOf(d); return sz ? sz.key : null; }
	/** Steel plates (AR500 and the like) are hidden unless the viewer opts in. */
	function isSteel(d) {
		var hay = [d.specs && d.specs.material, d.model, d.family].join(' ');
		return /\bsteel\b|\bar\s?-?(500|550|600|650)\b/i.test(hay);
	}
	/** One model is a side plate: named as one (HESCO "-SP", "side plate"), or 8 in or smaller on both sides. */
	function isSideModel(d) {
		if (/-SP\b|\bside\s*plates?\b/i.test([d.model, d.family].join(' '))) return true;
		var z = sizeOf(d);
		return !!(z && z.w && Math.max(z.w, z.h) <= 8);
	}
	/** Size family for the size step: side plate, shooter's cut, or SAPI. */
	function sizeGroup(sz) {
		if (!sz || !sz.w) return { id: 'other', label: 'Other sizes', order: 3 };
		if (sz.sub) return { id: 'sapi', label: 'SAPI cut', order: 2 };
		if (sz.h <= 8) return { id: 'side', label: 'Side plates', order: 0 };
		return { id: 'shooter', label: "Shooter's cut", order: 1 };
	}
	function tier(l) {
		l = String(l || '').toUpperCase().trim();
		if (['IIA', 'II', 'IIIA', 'HG1', 'HG2'].indexOf(l) !== -1) return 'hg';
		if (['III', 'III+'].indexOf(l) !== -1) return 'rifle';
		if (['RF1', 'RF2', 'RF3'].indexOf(l) !== -1) return 'rf';
		if (l === 'IV') return 'ap';
		return 'other';
	}
	var THREATS = [
		{ id: 'iiia', label: 'Handgun', sub: 'IIIA', levels: ['IIA', 'II', 'IIIA', 'HG1', 'HG2'] },
		{ id: 'iii',  label: 'Rifle', sub: 'III / III+', levels: ['III', 'III+'] },
		{ id: 'iv',   label: 'Rifle', sub: 'IV', levels: ['IV'] },
		{ id: 'rf1',  label: 'Rifle', sub: 'RF1', levels: ['RF1'] },
		{ id: 'rf2',  label: 'Rifle', sub: 'RF2 / RF3', levels: ['RF2', 'RF3'] }
	];
	var STEPS = {
		shield: ['threat', 'type'],
		plate:  ['threat', 'nij', 'size'],
		helmet: ['threat', 'cut'],
		soft_armor: ['threat', 'size']
	};
	var STEP_Q = { threat: 'What does it need to stop?', type: 'What kind of shield?', nij: 'Do you need an NIJ certified model?', size: 'What size?', cut: 'What cut?' };
	var ANY = 'any'; // answer that keeps every option (the "Show both" tile)
	function threatOf(level) {
		var l = String(level || '').toUpperCase().trim();
		for (var i = 0; i < THREATS.length; i++) if (THREATS[i].levels.indexOf(l) !== -1) return THREATS[i].id;
		return null;
	}
	function stepValue(d, step) {
		if (step === 'threat') return threatOf(d.specs.level);
		if (step === 'type') return d.specs.type ? String(d.specs.type) : '';
		if (step === 'nij') { var v = d.specs.nij_certified; if (v === undefined || v === null || v === '') return ''; return (v === true || /^(yes|true|1)$/i.test(String(v))) ? 'yes' : 'no'; }
		if (step === 'size') return sizeKey(d) || '';
		if (step === 'cut') return d.specs.cut ? String(d.specs.cut) : '';
		return '';
	}
	function stepLabel(d, step, val) {
		if (step === 'threat') { for (var i = 0; i < THREATS.length; i++) if (THREATS[i].id === val) return THREATS[i]; return { label: val, sub: '' }; }
		if (step === 'nij') { if (val === ANY) return { label: 'Show both', sub: 'Certified and not' }; return { label: val === 'yes' ? 'NIJ certified' : 'Not certified', sub: val === 'yes' ? 'On the NIJ CPL' : 'Not on the NIJ CPL' }; }
		if (step === 'size') { var sz = sizeOf(d); return sz ? { label: sz.label, sub: sz.sub } : { label: 'Not listed', sub: '' }; }
		return { label: val || 'Not specified', sub: '' };
	}
	function el(tag, cls, text) {
		var e = document.createElement(tag);
		if (cls) e.className = cls;
		if (text !== undefined && text !== null) e.textContent = text;
		return e;
	}
	function reduced() { return window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches; }

	function Panel(section, root) {
		this.section = section;
		this.root = root;
		this.config = JSON.parse(section.querySelector('.j5ac-config').textContent);
		this.fieldsByKey = {};
		this.config.fields.forEach(function (f) { this.fieldsByKey[f.key] = f; }, this);
		this.tbody = section.querySelector('tbody');
		this.rows = Array.prototype.map.call(this.tbody.querySelectorAll('tr[data-row]'), function (tr) {
			var r = { tr: tr, data: JSON.parse(tr.getAttribute('data-row')) };
			tr._j5r = r;
			return r;
		});
		this.cardList = section.querySelector('[data-j5ac-cardlist]');
		this.form = section.querySelector('[data-j5ac-filters]');
		this.count = section.querySelector('[data-j5ac-count]');
		this.none = section.querySelector('[data-j5ac-none]');
		this.hint = section.querySelector('[data-j5ac-hint]');
		this.clearBtn = section.querySelector('[data-j5ac-clear]');
		this.out = section.querySelector('[data-j5ac-compare-out]');
		this.picked = [];
		this.guide = {};
		this.guideOn = false;
		this.helpOpen = false;
		this.steps = STEPS[this.config.category] || ['threat'];
		this.guideBox = section.querySelector('[data-j5ac-guide]');
		this.listWrap = section.querySelector('.j5ac-table-wrap');
		this.viewInit();
		this.bind();
		this.renderGuide();
		this.applyGuide();
		this.ready = true;
	}

	Panel.prototype.steelOn = function () { var cb = this.form.elements.steel; return !!(cb && cb.checked); };
	/*
	 * J5-AC-HELP: the tool opens straight to the list. "Help me choose" opens a short panel of
	 * questions; the answers only set the sidebar filters, so there is no separate guided mode.
	 */
	var HELP_Q = { threat: 'What does it need to stop?', nij: 'Do you need NIJ certification?', size: 'What size?', type: 'What kind of shield?', cut: 'What cut?' };
	Panel.prototype.helpSteps = function () {
		var f = this.form, self = this;
		return this.steps.filter(function (st) {
			if (st === 'threat') return !!f.querySelector('input[name=level]');
			if (st === 'nij') return !!f.elements['only:nij_certified'];
			if (st === 'size') return !!f.querySelector('input[name=sizegrp]');
			return !!f.querySelector('input[name="' + st + '"]');
		}).filter(function (st) { return self.helpOpts(st).length > 1 || st === 'nij'; });
	};
	Panel.prototype.helpOpts = function (st) {
		var f = this.form, rows = this.rows;
		if (st === 'threat') {
			return THREATS.filter(function (t) { return rows.some(function (r) { return threatOf(r.data.specs.level) === t.id; }); })
				.map(function (t) { return { v: t.id, l: t.id === 'iiia' ? 'Handgun' : 'Rifle ' + t.sub }; });
		}
		if (st === 'nij') return [{ v: 'yes', l: 'Yes, certified' }, { v: ANY, l: 'Doesn’t matter' }];
		if (st === 'size') {
			var o = Array.prototype.map.call(f.querySelectorAll('input[name=sizegrp]'), function (i) { var lb = i.closest('label'); return { v: i.value, l: lb ? lb.textContent.trim() : i.value }; });
			o.push({ v: ANY, l: 'Any size' });
			return o;
		}
		return Array.prototype.map.call(f.querySelectorAll('input[name="' + st + '"]'), function (i) { return { v: i.value, l: i.value }; });
	};
	/** Current answer for a question, read back from the filters so reopening the panel shows what is set. */
	Panel.prototype.helpRead = function (st) {
		var f = this.form, on = function (sel) { return Array.prototype.filter.call(f.querySelectorAll(sel), function (i) { return i.checked; }).map(function (i) { return i.value; }); };
		if (st === 'threat') {
			var lv = on('input[name=level]');
			if (!lv.length) return undefined;
			for (var i = 0; i < THREATS.length; i++) if (lv.every(function (l) { return THREATS[i].levels.indexOf(l) !== -1; })) return THREATS[i].id;
			return undefined;
		}
		if (st === 'nij') return f.elements['only:nij_certified'].checked ? 'yes' : undefined;
		var c = on('input[name="' + (st === 'size' ? 'sizegrp' : st) + '"]');
		return c.length === 1 ? c[0] : undefined;
	};
	Panel.prototype.helpApply = function (ans) {
		var f = this.form;
		var set = function (name, vals) { Array.prototype.forEach.call(f.querySelectorAll('input[name="' + name + '"]'), function (i) { i.checked = vals.indexOf(i.value) !== -1; }); };
		Object.keys(ans).forEach(function (st) {
			var v = ans[st];
			if (v === undefined) return;
			if (st === 'threat') { var t = THREATS.filter(function (x) { return x.id === v; })[0]; set('level', t ? t.levels : []); }
			else if (st === 'nij') f.elements['only:nij_certified'].checked = v === 'yes';
			else set(st === 'size' ? 'sizegrp' : st, v === ANY ? [] : [v]);
		});
		this.clearPicks();
		this.filter();
		this.sort(f.elements.sort.value);
	};
	Panel.prototype.renderGuide = function () {
		var self = this, box = this.guideBox;
		this.guideOn = false; this.guide = {};
		this.listWrap.hidden = false; this.form.hidden = false; this.hint.parentNode.hidden = false;
		if (this.helpBtn) this.helpBtn.setAttribute('aria-expanded', this.helpOpen ? 'true' : 'false');
		box.textContent = '';
		if (!this.helpOpen) { box.hidden = true; return; }
		box.hidden = false;
		var steps = this.helpSteps(), ans = this.helpAns || (this.helpAns = {});
		var panel = el('div', 'j5ac-help');
		var hd = el('div', 'j5ac-help-hd');
		var h = el('h3', 'j5ac-help-t', 'Help me choose'); h.id = 'j5ac-help-' + this.config.category; h.tabIndex = -1;
		hd.appendChild(h);
		var x = el('button', 'j5ac-help-x'); x.type = 'button'; x.setAttribute('aria-label', 'Close'); x.innerHTML = '&times;';
		x.addEventListener('click', function () { self.closeHelp(); });
		hd.appendChild(x);
		panel.appendChild(hd);
		panel.appendChild(el('p', 'j5ac-help-p', 'A few quick questions. Your answers set the filters, and you can change them any time.'));
		steps.forEach(function (st) {
			var q = el('fieldset', 'j5ac-help-q');
			q.appendChild(el('legend', null, HELP_Q[st] || st));
			var opts = el('div', 'j5ac-help-opts');
			self.helpOpts(st).forEach(function (o) {
				var b = el('button', 'j5ac-help-o' + (ans[st] === o.v ? ' is-on' : ''), o.l); b.type = 'button';
				b.setAttribute('aria-pressed', ans[st] === o.v ? 'true' : 'false');
				b.addEventListener('click', function () {
					ans[st] = ans[st] === o.v ? undefined : o.v;
					Array.prototype.forEach.call(opts.children, function (c) { var on = c === b && ans[st] !== undefined; c.classList.toggle('is-on', on); c.setAttribute('aria-pressed', on ? 'true' : 'false'); });
				});
				opts.appendChild(b);
			});
			q.appendChild(opts);
			panel.appendChild(q);
		});
		var acts = el('div', 'j5ac-help-acts');
		var go = el('button', 'j5ac-help-go', 'Show ' + this.config.label.toLowerCase()); go.type = 'button';
		go.addEventListener('click', function () {
			self.helpApply(ans);
			self.closeHelp(true);
		});
		var skip = el('button', 'j5ac-help-skip', 'Skip'); skip.type = 'button';
		skip.addEventListener('click', function () { self.closeHelp(); });
		acts.appendChild(go); acts.appendChild(skip);
		panel.appendChild(acts);
		box.appendChild(panel);
	};
	Panel.prototype.openHelp = function () {
		var self = this;
		this.helpAns = {};
		this.helpSteps().forEach(function (st) { self.helpAns[st] = self.helpRead(st); });
		this.helpOpen = true;
		this.renderGuide();
		var h = this.guideBox.querySelector('.j5ac-help-t');
		if (h) { h.focus(); this.guideBox.scrollIntoView({ behavior: reduced() ? 'auto' : 'smooth', block: 'nearest' }); }
	};
	Panel.prototype.closeHelp = function (toResults) {
		this.helpOpen = false;
		this.renderGuide();
		if (toResults && this.count) { this.count.setAttribute('tabindex', '-1'); this.count.focus({ preventScroll: true }); (this.toolbar || this.count).scrollIntoView({ behavior: reduced() ? 'auto' : 'smooth', block: 'start' }); }
		else if (this.helpBtn) this.helpBtn.focus();
	};
	Panel.prototype.startOver = function () {
		this.sizeTarget = null; this.sizeSel = {};
		this.form.reset(); this.clearPicks(); this.filter();
	};
	Panel.prototype.applyGuide = function () { this.filter(); this.sort(this.form.elements.sort.value); };
	Panel.prototype.writeHash = function () {
		if (!history.replaceState || !this.ready || this.section.hidden) return;
		history.replaceState(null, '', '#j5ac-' + this.config.category);
	};
	Panel.prototype.readHash = function () { this.renderGuide(); };

	Panel.prototype.value = function (row, key) {
		if (key === 'model') return row.data.model.toLowerCase();
		if (key === 'maker') return (row.data.manufacturer + ' ' + row.data.model).toLowerCase();
		if (row.data.derived && key in row.data.derived) return row.data.derived[key];
		return row.data.specs ? row.data.specs[key] : undefined;
	};

	Panel.prototype.bind = function () {
		var self = this;
		this.desc = false;
		// J5-AC-SIDEBAR: search and sort live in the toolbar over the results (form="" ties them to the filter form).
		this.dirBtn = this.section.querySelector('[data-j5ac-dir]');
		this.toolbar = this.section.querySelector('[data-j5ac-toolbar]');
		var onInput = function (e) {
			if (e.target.type === 'range') self.syncOutput(e.target);
			if (e.target.name === 'sort') self.sort(e.target.value); else self.filter();
		};
		this.form.addEventListener('input', onInput);
		if (this.toolbar) this.toolbar.addEventListener('input', onInput);
		this.helpBtn = this.section.querySelector('[data-j5ac-help]');
		if (this.helpBtn) {
			if (!this.helpSteps().length) this.helpBtn.hidden = true;
			this.helpBtn.addEventListener('click', function () { if (self.helpOpen) self.closeHelp(); else self.openHelp(); });
		}
		this.section.addEventListener('keydown', function (e) { if (e.key === 'Escape' && self.helpOpen) self.closeHelp(); });
		var ftoggle = this.section.querySelector('[data-j5ac-ftoggle]');
		if (ftoggle) ftoggle.addEventListener('click', function () {
			var on = !self.section.classList.contains('is-fopen');
			self.section.classList.toggle('is-fopen', on);
			ftoggle.setAttribute('aria-expanded', on ? 'true' : 'false');
		});
		this.form.addEventListener('submit', function (e) { e.preventDefault(); });
		this.dirBtn.addEventListener('click', function () {
			self.desc = !self.desc;
			self.dirBtn.innerHTML = self.desc ? '&#9660;' : '&#9650;';
			self.sort(self.form.elements.sort.value);
		});
		this.form.addEventListener('reset', function () {
			setTimeout(function () { self.desc = false; self.dirBtn.innerHTML = '&#9650;'; self.syncOutputs(); self.filter(); self.sort(self.form.elements.sort.value); }, 0);
		});
		this.syncOutputs();

		this.tbody.addEventListener('click', function (e) {
			var t = e.target;
			if (!t.closest) return;
			var open = t.closest('[data-j5ac-open]');
			if (!open && t.closest('input, a, button, label, select')) return;
			var tr = t.closest('tr[data-row]');
			if (!tr) return;
			self.openDetail(tr.getAttribute('data-fam') || tr.getAttribute('data-slug'), open || tr.querySelector('[data-j5ac-open]'));
		});
		this.tbody.addEventListener('change', function (e) {
			if (!e.target.classList.contains('j5ac-pick')) return;
			self.pick(e.target.value, e.target.checked, e.target);
		});
		this.clearBtn.addEventListener('click', function () { self.clearPicks(); });
	};

	Panel.prototype.clearPicks = function () {
		this.picked.slice().forEach(function (slug) {
			var row = this.rowBySlug(slug);
			if (row) { row.tr.classList.remove('is-picked'); row.tr.querySelector('.j5ac-pick').checked = false; }
		}, this);
		this.picked = [];
		this.hint.textContent = 'Check up to ' + MAX_PICK + ' to compare side by side.';
		this.clearBtn.hidden = true;
		this.lockSizes();
		this.renderCards();
		this.compare();
	};

	Panel.prototype.syncOutputs = function () {
		Array.prototype.forEach.call(this.form.querySelectorAll('input[type=range]'), this.syncOutput, this);
	};
	Panel.prototype.syncOutput = function (input) {
		var out = input.parentNode.querySelector('output');
		if (!out) return;
		out.textContent = fmt(parseFloat(input.value), this.fieldsByKey[input.name.replace(/^max:/, '')]);
	};

	Panel.prototype.filter = function () {
		var lists = {}, maxes = {}, onlys = [], has = [], q = '', makers = [], sizegrps = [], carriedOnly = false, steelOn = this.steelOn();
		Array.prototype.forEach.call(this.form.elements, function (inp) {
			if (!inp.name || inp.name === 'sort' || inp.name === 'steel') return;
			if (inp.name === 'q') { q = inp.value.trim().toLowerCase(); return; }
			if (inp.name === 'maker') { if (inp.checked) makers.push(inp.value); return; }
			if (inp.name === 'carried') { carriedOnly = inp.checked; return; }
			if (inp.name === 'sizegrp') { if (inp.checked) sizegrps.push(inp.value); return; }
			if (inp.type === 'checkbox' && inp.name.indexOf('only:') === 0) { if (inp.checked) onlys.push(inp.name.slice(5)); }
			else if (inp.type === 'checkbox' && inp.name.indexOf('has:') === 0) { if (inp.checked) has.push(inp.name.slice(4)); }
			else if (inp.type === 'checkbox') { if (inp.checked) (lists[inp.name] = lists[inp.name] || []).push(inp.value); }
			else if (inp.type === 'range') { if (parseFloat(inp.value) < parseFloat(inp.max)) maxes[inp.name.slice(4)] = parseFloat(inp.value); }
		});
		var shown = 0, g = this.guideOn ? this.guide : {};
		this.rows.forEach(function (row) {
			var ok = true, k, i, v;
			for (k in g) if (g[k] !== undefined && g[k] !== '*' && g[k] !== ANY && stepValue(row.data, k) !== g[k]) { ok = false; break; }
			if (!steelOn && isSteel(row.data)) ok = false;
			if (ok && q) { var hay = (row.data.manufacturer + ' ' + row.data.model + ' ' + (row.data.family || '')).toLowerCase(); if (hay.indexOf(q) === -1) ok = false; }
			if (ok && makers.length && makers.indexOf(row.data.manufacturer) === -1) ok = false;
			if (ok && carriedOnly && !row.data.carried) ok = false;
			if (ok && sizegrps.length && sizegrps.indexOf(isSideModel(row.data) ? 'side' : 'full') === -1) ok = false;
			if (ok) for (k in lists) { if (lists[k].indexOf(String(this.value(row, k))) === -1) { ok = false; break; } }
			if (ok) for (k in maxes) { var n = num(this.value(row, k)); if (n === null || n > maxes[k]) { ok = false; break; } }
			if (ok) for (i = 0; i < onlys.length; i++) { v = this.value(row, onlys[i]); if (!v || v === 'No' || v === 'ICW') { ok = false; break; } }
			if (ok) for (i = 0; i < has.length; i++) { v = this.value(row, has[i]); if (v === undefined || v === null || v === '' || v === false) { ok = false; break; } }
			row.tr.classList.toggle('is-hidden', !ok);
			if (ok) shown++;
		}, this);
		this.count.textContent = shown + ' of ' + this.rows.length;
		this.none.hidden = shown > 0;
		this.renderActive();
		this.renderCards();
	};

	/** Active filters as removable chips over the results, plus the count on the mobile Filters button. */
	Panel.prototype.renderActive = function () {
		var box = this.section.querySelector('[data-j5ac-active]');
		if (!box) return;
		var self = this, n = 0;
		box.textContent = '';
		Array.prototype.forEach.call(this.form.elements, function (inp) {
			if (!inp.name || inp.name === 'sort' || inp.name === 'q') return;
			var label = null, undo = null;
			if (inp.type === 'checkbox' && inp.checked) {
				var l = inp.closest('label');
				label = l ? l.textContent.replace(/\s+/g, ' ').trim() : inp.value;
				undo = function () { inp.checked = false; };
			} else if (inp.type === 'range' && parseFloat(inp.value) < parseFloat(inp.max)) {
				var out = inp.parentNode.querySelector('output');
				label = 'Max ' + (out ? out.textContent : inp.value);
				undo = function () { inp.value = inp.max; self.syncOutput(inp); };
			}
			if (!label) return;
			n++;
			var b = el('button', 'j5ac-achip', label);
			b.type = 'button';
			b.setAttribute('aria-label', 'Remove filter: ' + label);
			b.addEventListener('click', function () {
				undo(); self.filter();
				var next = box.querySelector('.j5ac-achip');
				if (next) next.focus();
			});
			box.appendChild(b);
		});
		var all = this.section.querySelector('[data-j5ac-clearall]');
		if (all) all.hidden = n === 0;
		var fc = this.section.querySelector('[data-j5ac-fcount]');
		if (fc) fc.textContent = n ? '(' + n + ')' : '';
	};

	Panel.prototype.featuredRank = function (d) {
		var f = this.config.featured || [], m = String(d.manufacturer || '').toLowerCase();
		for (var i = 0; i < f.length; i++) if (f[i] && m.indexOf(String(f[i]).toLowerCase()) !== -1) return i;
		return 999;
	};
	Panel.prototype.sort = function (key) {
		var self = this, sign = this.desc ? -1 : 1;
		if (key === 'featured') {
			var inner = this.config.eff ? 'oz_per_sqin' : 'weight_lb';
			var sorted0 = this.rows.slice().sort(function (a, b) {
				var ra = self.featuredRank(a.data), rb = self.featuredRank(b.data);
				if (ra !== rb) return (ra - rb) * sign;
				var na = num(self.value(a, inner)), nb = num(self.value(b, inner));
				if (na === null && nb === null) return 0; if (na === null) return 1; if (nb === null) return -1;
				return na - nb;
			});
			sorted0.forEach(function (row) { this.tbody.appendChild(row.tr); }, this);
			this.renderCards();
			return;
		}
		var numeric = key !== 'model' && key !== 'maker';
		var sorted = this.rows.slice().sort(function (a, b) {
			var va = self.value(a, key), vb = self.value(b, key);
			if (numeric) {
				var na = num(va), nb = num(vb);
				if (na === null && nb === null) return 0;
				if (na === null) return 1;
				if (nb === null) return -1;
				return (na - nb) * sign;
			}
			return String(va).localeCompare(String(vb), undefined, { numeric: true, sensitivity: 'base' }) * sign;
		});
		sorted.forEach(function (row) { this.tbody.appendChild(row.tr); }, this);
		this.renderCards();
	};

	/** Shields: picture cards in place of the table, built from the table rows (same data, same order, same filters). */
	/** Listing (family) helpers: one card per listing, sizes picked on the card. */
	function famKey(d) { return d.fslug || d.slug; }
	function famName(d) { return d.family || d.model; }
	function sizeSortKey(d) { var sz = sizeOf(d) || { h: 0, w: 0 }; return [sz.h || 0, sz.w || 0, num(d.specs && d.specs.weight_lb) || 0]; }
	/** Size label for a picker: dimensions, plus the weight when two sizes share dimensions. */
	function sizeLabel(d, group) {
		var sz = sizeOf(d), base = sz && sz.w ? sz.label : (d.specs && d.specs.size ? String(d.specs.size) : d.model);
		var dup = group.filter(function (o) { var z = sizeOf(o); return z && sz && z.key === sz.key; }).length > 1;
		if (dup && d.specs && d.specs.weight_lb !== undefined) base += ', ' + d.specs.weight_lb + ' lb';
		return base;
	}

	/** The member of a listing closest in size to the target (height first, then width, then lighter), or null. */
	function nearestSize(items, target) {
		if (!target || !target.w) return null;
		var best = null, bd = Infinity;
		items.forEach(function (i) {
			var z = sizeOf(i.d); if (!z || !z.w) return;
			var dist = Math.abs(z.h - target.h) * 2 + Math.abs(z.w - target.w) + (num(i.d.specs && i.d.specs.weight_lb) || 0) / 1000;
			if (dist < bd) { bd = dist; best = i; }
		});
		return best;
	}

	/**
	 * J5-AC-FAMILY: one table row per listing. Of the sizes that pass the filters, show a picked one,
	 * else one matching the compare lock size, else the first in sort order. The rest are hidden siblings.
	 */
	Panel.prototype.collapseFamilies = function () {
		var groups = {}, order = [], total = {};
		Array.prototype.forEach.call(this.tbody.querySelectorAll('tr[data-row]'), function (tr) {
			var k = tr.getAttribute('data-fam') || tr.getAttribute('data-slug');
			total[k] = 1;
			tr.classList.remove('is-sib');
			if (tr.classList.contains('is-hidden')) return;
			if (!groups[k]) { groups[k] = []; order.push(k); }
			groups[k].push(tr);
		});
		var picked = this.picked, self = this, sel = this.famSel || (this.famSel = {}), uneq = this.unequal || {};
		// Shown size: a picked one, else the one the viewer chose on the listing, else the closest match to the
		// picks, else 10 x 12, else SAPI Medium, else the first.
		var pref = function (tr) { var z = sizeOf(tr._j5r.data); if (!z) return 2; if (z.key === '10x12') return 0; if (z.sub === 'SAPI Medium') return 1; return 2; };
		order.forEach(function (k) {
			var trs = groups[k], rep = null;
			trs.forEach(function (tr) { if (!rep && picked.indexOf(tr.getAttribute('data-slug')) !== -1) rep = tr; });
			if (!rep && sel[k]) trs.forEach(function (tr) { if (!rep && tr.getAttribute('data-slug') === sel[k]) rep = tr; });
			if (!rep && self.fit) trs.forEach(function (tr) { if (!rep && fitKey(tr._j5r.data) === self.fit) rep = tr; }); // J5-AC-FIT-1
			if (!rep) {
				var best = null, bs = Infinity;
				trs.forEach(function (tr) {
					var sc = (picked.length ? (uneq[tr.getAttribute('data-slug')] || []).length * 10 : 0) + pref(tr);
					if (sc < bs) { bs = sc; best = tr; }
				});
				rep = best;
			}
			if (!rep) rep = trs[0];
			trs.forEach(function (tr) { if (tr !== rep) tr.classList.add('is-sib'); });
		});
		this.count.textContent = order.length + ' of ' + Object.keys(total).length;
	};
	Panel.prototype.rowByTr = function (tr) { return tr._j5r || null; };

	/** Detail overlay for one listing: key specs, every size with its weight, the spec sheet, buy or quote. */
	Panel.prototype.openDetail = function (famKey, opener) {
		var self = this, cfg = this.config, root = this.root;
		var members = this.rows.filter(function (r) { return (r.tr.getAttribute('data-fam') || r.tr.getAttribute('data-slug')) === famKey; });
		if (!members.length) return;
		members.sort(bySizeOrder);
		var first = members[0].data, quote = root.getAttribute('data-quote') || '/request-a-quote/';

		var modal = root.querySelector('[data-j5ac-detail]');
		if (!modal) {
			modal = el('div', 'j5ac-modal j5ac-detail'); modal.setAttribute('data-j5ac-detail', ''); modal.hidden = true;
			var box0 = el('div', 'j5ac-modal-box j5ac-detail-box');
			box0.setAttribute('role', 'dialog'); box0.setAttribute('aria-modal', 'true'); box0.setAttribute('aria-labelledby', 'j5ac-detail-title'); box0.tabIndex = -1;
			modal.appendChild(box0);
			root.appendChild(modal);
			modal.addEventListener('click', function (e) { if (e.target === modal) closeDetail(root); });
			document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && !modal.hidden) closeDetail(root); });
		}
		var box = modal.firstChild;
		box.textContent = '';
		var x = el('button', 'j5ac-modal-x', '\u00d7'); x.type = 'button'; x.setAttribute('aria-label', 'Close');
		x.addEventListener('click', function () { closeDetail(root); });
		box.appendChild(x);

		// Header
		var head = el('div', 'j5ac-det-head');
		if (first.image) { var im = el('img', 'j5ac-det-img'); im.src = first.image; im.alt = famName(first); im.loading = 'lazy'; head.appendChild(im); }
		var ht = el('div', 'j5ac-det-title');
		var h = el('h3', null, members.length > 1 ? famName(first) : first.model); h.id = 'j5ac-detail-title';
		ht.appendChild(h);
		ht.appendChild(el('p', 'j5ac-maker', first.manufacturer));
		var badges = el('div', 'j5ac-det-badges');
		if (first.specs.level) badges.appendChild(el('span', 'j5ac-lv j5ac-lv--' + tier(first.specs.level), first.specs.level));
		if (first.specs.standard) badges.appendChild(el('span', 'j5ac-tag', first.specs.standard));
		var cert = stepValue(first, 'nij');
		if (cert) badges.appendChild(el('span', 'j5ac-tag' + (cert === 'yes' ? ' j5ac-tag--ok' : ''), cert === 'yes' ? 'NIJ certified' : 'Not NIJ certified'));
		if (first.specs.standalone) badges.appendChild(el('span', 'j5ac-tag', first.specs.standalone));
		cutsOf(members).forEach(function (c) { badges.appendChild(el('span', 'j5ac-tag', /cut$/i.test(c) ? c : c + ' cut')); });
		if (first.carried) badges.appendChild(el('span', 'j5ac-stock', first.wc ? 'Sold by J5' : 'Quote from J5'));
		ht.appendChild(badges);
		head.appendChild(ht);
		box.appendChild(head);

		// Which fields differ between sizes: those go in the sizes table, the rest in the spec list.
		var perSize = { size: 1, width_in: 1, height_in: 1, weight_lb: 1, sizes: 1, cut: 1 };
		var varies = {};
		cfg.fields.forEach(function (f) {
			var seen = {};
			members.forEach(function (m) { var v = fmt(m.data.specs[f.key], f); if (v !== null) seen[v] = 1; });
			if (Object.keys(seen).length > 1) varies[f.key] = f;
		});

		// Sizes
		var sec1 = el('section', 'j5ac-det-sec');
		sec1.appendChild(el('h4', null, members.length > 1 ? members.length + ' sizes' : 'Size'));
		var wrap = el('div', 'j5ac-det-tw'), t = el('table', 'j5ac-det-table'), th = el('thead'), hr = el('tr');
		var extra = Object.keys(varies).filter(function (k) { return !perSize[k]; });
		var anySapi = members.some(function (m) { var z = sizeOf(m.data); return z && z.sub; });
		var anyDims = members.some(function (m) { var z = sizeOf(m.data); return z && z.w; });
		var heads = ['Size'].concat(anySapi && anyDims ? ['Dimensions'] : []).concat(['Weight']).concat(cfg.eff ? ['Oz / sq in', 'Coverage'] : []).concat(extra.map(function (k) { return varies[k].label; })).concat(['Compare']);
		heads.forEach(function (lab) { var c = el('th', lab === 'Compare' ? 'is-cmp' : null, lab); c.scope = 'col'; hr.appendChild(c); });
		th.appendChild(hr); t.appendChild(th);
		var tb = el('tbody'), boxes = [];
		members.forEach(function (m) {
			var d = m.data, tr = el('tr'), sz = sizeOf(d);
			// SAPI plates are known by name ("SAPI Medium"); the inches go in Dimensions.
			var c0 = el('td', 'j5ac-det-size');
			c0.textContent = sz && sz.sub ? sz.sub : (sz ? sz.label : (d.specs.sizes || d.model));
			tr.appendChild(c0);
			if (anySapi && anyDims) tr.appendChild(el('td', 'j5ac-det-dim', sz && sz.w ? sz.label : '\u2014'));
			tr.appendChild(el('td', null, fmt(d.specs.weight_lb, { unit: 'lb' }) || 'not listed'));
			if (cfg.eff) {
				tr.appendChild(el('td', null, d.derived && typeof d.derived.oz_per_sqin === 'number' ? d.derived.oz_per_sqin.toFixed(3) : '\u2014'));
				tr.appendChild(el('td', null, d.derived && d.derived.area_sqin ? fmt(d.derived.area_sqin) + ' sq in' : '\u2014'));
			}
			extra.forEach(function (k) { tr.appendChild(el('td', null, fmt(d.specs[k], varies[k]) || '\u2014')); });
			var cc = el('td', 'is-cmp');
			boxes.push({ td: cc, m: m });
			tr.appendChild(cc);
			tb.appendChild(tr);
		});
		function sync() {
			boxes.forEach(function (b) {
				var on = self.picked.indexOf(b.m.data.slug) !== -1, why = !on && self.unequal ? (self.unequal[b.m.data.slug] || []) : [];
				var z = sizeOf(b.m.data), lab = b.m.data.manufacturer + ' ' + famName(b.m.data) + (z ? ' ' + (z.sub || z.label) : '');
				var btn = cmpBtn(on, false, lab, why.length > 0);
				b.td.parentNode.classList.toggle('is-unequal', why.length > 0);
				if (b.whyRow) { b.whyRow.parentNode.removeChild(b.whyRow); b.whyRow = null; }
				if (why.length) {
					var wr = el('tr', 'j5ac-det-why'), wd = el('td');
					wd.colSpan = b.td.parentNode.children.length;
					wd.appendChild(el('strong', null, 'Not an equal comparison to your selected models. '));
					wd.appendChild(document.createTextNode(why.join('  \u00b7  ')));
					wr.appendChild(wd);
					b.td.parentNode.parentNode.insertBefore(wr, b.td.parentNode.nextSibling);
					b.whyRow = wr;
				}
				btn.addEventListener('click', function () {
					var tcb = b.m.tr.querySelector('.j5ac-pick'), nowOn = self.picked.indexOf(b.m.data.slug) === -1;
					tcb.checked = nowOn;
					self.pick(b.m.data.slug, nowOn, tcb);
					sync();
					var again = b.td.querySelector('.j5ac-cmp'); if (again) again.focus();
				});
				b.td.textContent = '';
				b.td.appendChild(btn);
			});
		}
		sync();
		t.appendChild(tb); labelCells(t); wrap.appendChild(t); sec1.appendChild(wrap);
		box.appendChild(sec1);

		// Special threats, grouped by caliber
		var tg = threatGroups(first.specs.special_threats);
		if (tg.length && !varies.special_threats) {
			var secT = el('section', 'j5ac-det-sec');
			secT.appendChild(el('h4', null, (this.fieldsByKey.special_threats && this.fieldsByKey.special_threats.label) || 'Special threats listed'));
			var tl = el('div', 'j5ac-thr');
			tg.forEach(function (g) {
				var row = el('div', 'j5ac-thr-row' + (g.rounds.some(function (r) { return r.ap; }) ? ' is-ap' : ''));
				row.appendChild(el('span', 'j5ac-thr-cal', g.cal));
				var rs = el('div', 'j5ac-thr-rds');
				g.rounds.forEach(function (r) { rs.appendChild(el('span', 'j5ac-thr-rd' + (r.ap ? ' is-ap' : ''), r.name)); });
				row.appendChild(rs);
				tl.appendChild(row);
			});
			secT.appendChild(tl);
			secT.appendChild(el('p', 'j5ac-thr-note', 'Grouped by caliber' + (tg.some(function (g) { return g.rounds.some(function (r) { return r.ap; }); }) ? '; armor piercing rounds in red' : '') + '. As listed on the manufacturer\u2019s spec sheet.'));
			box.appendChild(secT);
		}

		// Threat matrix: rows of { round, core, fps, shots, base } from the DP
		var mkey = null;
		cfg.fields.forEach(function (f) { if (!mkey && matrixRows(first.specs[f.key]).length) mkey = f.key; });
		if (!mkey) Object.keys(first.specs).forEach(function (k) { if (!mkey && matrixRows(first.specs[k]).length) mkey = k; });
		if (mkey) box.appendChild(matrixSection(matrixRows(first.specs[mkey]), (this.fieldsByKey[mkey] && this.fieldsByKey[mkey].label) || 'Threat matrix'));

		// Specifications (same for every size)
		var specs = [];
		cfg.fields.forEach(function (f) {
			if (perSize[f.key] || varies[f.key] || f.key === 'level' || f.key === 'standalone' || f.key === 'nij_certified' || f.key === 'standard' || f.key === 'special_threats') return;
			var v = fmt(first.specs[f.key], f);
			if (v !== null) specs.push([f.label, v]);
		});
		if (specs.length) {
			var sec2 = el('section', 'j5ac-det-sec');
			sec2.appendChild(el('h4', null, 'Specifications'));
			var dl = el('dl', 'j5ac-det-specs');
			specs.forEach(function (p) { dl.appendChild(el('dt', null, p[0])); dl.appendChild(el('dd', null, p[1])); });
			sec2.appendChild(dl);
			box.appendChild(sec2);
		}

		// Spec sheet(s)
		var sheets = {}, sheetOrder = [];
		members.forEach(function (m) { var sh = m.data.sheet; if (sh && sh.url && !sheets[sh.url]) { sheets[sh.url] = { sh: sh, sizes: [] }; sheetOrder.push(sh.url); } if (sh && sh.url) { var z = sizeOf(m.data); sheets[sh.url].sizes.push(z ? z.label.replace(' in', '') : m.data.model); } });
		var sec3 = el('section', 'j5ac-det-sec');
		sec3.appendChild(el('h4', null, 'Spec sheet'));
		if (!sheetOrder.length) sec3.appendChild(el('p', 'j5ac-na', 'Spec sheet not on file.'));
		sheetOrder.forEach(function (u) {
			var s0 = sheets[u], pr = el('p', 'j5ac-det-sheet');
			var a = el('a', 'j5ac-btn j5ac-btn--sheet', sheetOrder.length > 1 ? 'Spec sheet: ' + s0.sizes.join(', ') : 'Open spec sheet (PDF)');
			a.href = u; a.target = '_blank'; a.rel = 'noopener';
			pr.appendChild(a);
			pr.appendChild(el('span', null, first.manufacturer + (s0.sh.pulled_at ? ', pulled ' + s0.sh.pulled_at : '')));
			sec3.appendChild(pr);
		});
		box.appendChild(sec3);

		// Buy, quote, or reference only
		var act = el('div', 'j5ac-det-actions');
		var wc = null; members.forEach(function (m) { if (!wc && m.data.wc) wc = m.data.wc; });
		if (wc) { var buy = el('a', 'j5ac-btn j5ac-btn--buy', 'View product'); buy.href = wc; act.appendChild(buy); }
		else if (first.carried) {
			var q = el('a', 'j5ac-btn j5ac-btn--quote', 'Request a quote');
			q.href = quote + (quote.indexOf('?') === -1 ? '?' : '&') + 'item=' + encodeURIComponent(first.manufacturer + ' ' + famName(first)) + '&ref=armor-compare';
			act.appendChild(q);
		} else act.appendChild(el('p', 'j5ac-card-nocarry', 'Reference only. Not a J5 product.'));
		var rp = el('button', 'j5ac-report', 'Report incorrect information'); rp.type = 'button';
		rp.addEventListener('click', function () { closeDetail(root); openReport(root, cfg.category, first); });
		act.appendChild(rp);
		box.appendChild(act);

		modal._opener = opener || null;
		modal.hidden = false;
		document.body.classList.add('j5ac-modal-open');
		box.scrollTop = 0;
		x.focus();
	};
	/** Phones stack overlay tables into cards; each cell carries its column name for the label. */
	function labelCells(table) {
		var heads = Array.prototype.map.call(table.querySelectorAll('thead th'), function (th) { return th.textContent; });
		Array.prototype.forEach.call(table.querySelectorAll('tbody tr'), function (tr) {
			if (tr.classList.contains('j5ac-det-why') || tr.classList.contains('j5ac-mtx-g')) return;
			Array.prototype.forEach.call(tr.children, function (cell, i) {
				if (i > 0 && heads[i] && !cell.classList.contains('is-cmp')) cell.setAttribute('data-k', heads[i]);
			});
		});
	}
	function matrixRows(v) {
		return Array.isArray(v) ? v.filter(function (x) { return x && typeof x === 'object' && !Array.isArray(x); }) : [];
	}
	function cellText(v) {
		if (v === null || v === undefined || v === '') return '';
		if (typeof v === 'boolean') return v ? 'Yes' : 'No';
		if (Array.isArray(v)) return v.map(cellText).filter(Boolean).join(', ');
		if (typeof v === 'object') return Object.keys(v).map(function (k) { return k + ': ' + cellText(v[k]); }).join(', ');
		return String(v);
	}
	/** Threat matrix as a table: Round, Core, ft/s, shots stopped, plus any other columns the DP sends. */
	function matrixSection(rows, title) {
		var KNOWN = { round: 'Round', threat: 'Round', name: 'Round', core: 'Core', fps: 'ft/s', velocity_fps: 'ft/s', velocity: 'Velocity', shots: 'Shots stopped' };
		var cols = [], seen = {};
		var addCol = function (k) { var lab = KNOWN[k] || k.replace(/_/g, ' ').replace(/^./, function (c) { return c.toUpperCase(); }); if (seen[lab]) return; seen[lab] = 1; cols.push({ k: k, lab: lab }); };
		['round', 'threat', 'name', 'core', 'fps', 'velocity_fps', 'velocity'].forEach(function (k) { if (rows.some(function (r) { return r[k] !== undefined && r[k] !== ''; })) addCol(k); });
		rows.forEach(function (r) { Object.keys(r).forEach(function (k) { if (k !== 'base' && k !== 'shots' && !KNOWN[k]) addCol(k); }); });
		var hasShots = rows.some(function (r) { return r.shots !== undefined; });
		var sec = el('section', 'j5ac-det-sec');
		sec.appendChild(el('h4', null, title));
		var wrap = el('div', 'j5ac-det-tw'), t = el('table', 'j5ac-det-table j5ac-mtx'), th = el('thead'), hr = el('tr');
		cols.forEach(function (c) { var x = el('th', null, c.lab); x.scope = 'col'; hr.appendChild(x); });
		if (hasShots) { var xs = el('th', 'is-num', 'Shots stopped'); xs.scope = 'col'; hr.appendChild(xs); }
		th.appendChild(hr); t.appendChild(th);
		var groups = [['Certification test round', rows.filter(function (r) { return r.base; })], ['Special threats', rows.filter(function (r) { return !r.base; })]];
		groups.forEach(function (g) {
			if (!g[1].length) return;
			var tb = el('tbody');
			if (groups[0][1].length && groups[1][1].length) { var gr = el('tr', 'j5ac-mtx-g'), gc = el('th', null, g[0]); gc.colSpan = cols.length + (hasShots ? 1 : 0); gc.scope = 'rowgroup'; gr.appendChild(gc); tb.appendChild(gr); }
			g[1].forEach(function (r) {
				var tr = el('tr');
				cols.forEach(function (c, i) {
					var txt = cellText(r[c.k]), td = el(i === 0 ? 'th' : 'td', i === 0 ? 'j5ac-mtx-r' : null, txt || '\u2014');
					if (i === 0) { td.scope = 'row'; if (/\bAP\b|M2\s?AP|M61\b|M993|M995/i.test(txt)) td.classList.add('is-ap'); }
					tr.appendChild(td);
				});
				if (hasShots) {
					var sv = r.shots, st;
					if (sv && typeof sv === 'object' && !Array.isArray(sv)) { var ks = Object.keys(sv); st = ks.length === 1 ? cellText(sv[ks[0]]) : ks.map(function (k) { return k + ': ' + cellText(sv[k]); }).join(', '); }
					else st = cellText(sv);
					tr.appendChild(el('td', 'is-num', st || '\u2014'));
				}
				tb.appendChild(tr);
			});
			t.appendChild(tb);
		});
		labelCells(t);
		wrap.appendChild(t); sec.appendChild(wrap);
		return sec;
	}
	function closeDetail(root) {
		var modal = root.querySelector('[data-j5ac-detail]');
		if (!modal || modal.hidden) return;
		modal.hidden = true;
		document.body.classList.remove('j5ac-modal-open');
		if (modal._opener && modal._opener.focus) modal._opener.focus();
	}

	/* ---------------------------------------------------------------------
	 * J5-AC-VIEWS: Rows / Cards / Chart, all drawn from the filtered table
	 * rows (one entry per listing). The table stays in the page as the data
	 * source and for search engines; CSS hides it while a view is on.
	 * ------------------------------------------------------------------- */
	/* J5-AC-SIZES: Shooter's (and other dimensioned sizes) first by area, then SAPI XS to XL. */
	var SAPI_SHORT = { 'SAPI XS': 'XS', 'SAPI Small': 'S', 'SAPI Medium': 'M', 'SAPI Large': 'L', 'SAPI XL': 'XL' };
	var SAPI_RANK = { 'SAPI XS': 0, 'SAPI Small': 1, 'SAPI Medium': 2, 'SAPI Large': 3, 'SAPI XL': 4 };
	function sizeOrder(d) {
		var sz = sizeOf(d);
		if (!sz) return [2, 0, 0];
		if (sz.sub && SAPI_RANK[sz.sub] !== undefined) return [1, SAPI_RANK[sz.sub], 0];
		return [0, (sz.w || 0) * (sz.h || 0), num(d.specs && d.specs.weight_lb) || 0];
	}
	function bySizeOrder(a, b) { var x = sizeOrder(a.data), y = sizeOrder(b.data); return x[0] - y[0] || x[1] - y[1] || x[2] - y[2]; }
	/** "Shooter's & SAPI": every distinct cut in a listing, in size order. */
	function cutsOf(members) {
		var c = [];
		members.forEach(function (r) { var v = r.data.specs.cut; if (v && c.indexOf(String(v)) === -1) c.push(String(v)); });
		return c;
	}
	/* J5-AC-FIT-1 (2026-11-09): "Your plate size" picker. A size's fit key is "10 x 12" for
	   dimensioned sizes and "SAPI M" for SAPI letter sizes, so a Shooter's and a Rectangle 10 x 12 match together. */
	function fitKey(d) {
		var sz = sizeOf(d);
		if (!sz || !sz.w) return null;
		if (sz.sub && SAPI_SHORT[sz.sub]) return 'SAPI ' + SAPI_SHORT[sz.sub];
		return sz.label.replace(' in', '');
	}
	/** "Shooter's 10 x 12" / "SAPI M" / "10 x 12" for a member that matches the picked size. */
	function fitLabel(d) {
		var k = fitKey(d), cut = String(d.specs && d.specs.cut || '');
		if (!k) return '';
		return /^SAPI /.test(k) || !cut || /sapi/i.test(cut) ? k : cut + ' ' + k;
	}
	/** Size chips grouped under a label: [{ label: "Shooter's", sizes: ['8 x 10', ...] }, { label: 'SAPI', sizes: ['S', 'M'] }]. */
	function sizeGroups(members, d) {
		var groups = [], idx = {};
		members.forEach(function (r) {
			var sz = sizeOf(r.data);
			if (!sz || !sz.w) return;
			var sapi = sz.sub && SAPI_SHORT[sz.sub], cut = String(r.data.specs.cut || '');
			var label = sapi ? 'SAPI' : (/shoot/i.test(cut) ? cut : '');
			var name = sapi ? SAPI_SHORT[sz.sub] : sz.label.replace(' in', '');
			if (idx[label] === undefined) { idx[label] = groups.length; groups.push({ label: label, sizes: [], items: [] }); }
			var g = groups[idx[label]];
			if (g.sizes.indexOf(name) === -1) { g.sizes.push(name); g.items.push({ n: name, slug: r.data.slug }); }
		});
		if (!groups.length) {
			var list = d.specs.sizes ? String(d.specs.sizes).split(/[,\/;|]+/).map(function (x) { return x.trim(); }).filter(Boolean) : (d.specs.size ? [String(d.specs.size)] : []);
			if (list.length) groups.push({ label: '', sizes: list, items: list.map(function (n) { return { n: n, slug: null }; }) });
		}
		return groups;
	}

	/* J5-AC-THREATS: special threats as a list, grouped by caliber, armor piercing flagged. */
	function toList(v) {
		if (Array.isArray(v)) return v.map(String);
		if (v === null || v === undefined || v === '') return [];
		var s = String(v).trim();
		if (/^\[.*\]$/.test(s)) s = s.slice(1, -1);
		return s.split(/\s*[,;|]\s*/).map(function (x) { return x.replace(/^['"\s]+|['"\s]+$/g, ''); }).filter(Boolean);
	}
	var CALIBERS = [
		{ name: '5.56 NATO', re: /5\.56|\.223|M193|M855|SS109|M995|MK\s?262/i, strip: /^(5\.56(x45)?(\s*NATO)?|\.223)\s+/i },
		{ name: '5.45x39', re: /5\.45|7N6|7N10/i, strip: /^5\.45(x39)?\s+/i },
		{ name: '7.62x39', re: /7\.62\s*x\s*39|M43\b|\bPS\b/i, strip: /^7\.62\s*x\s*39\s+/i },
		{ name: '7.62x51 NATO', re: /7\.62\s*x\s*51|\.308|M80\b|M61\b|M993/i, strip: /^(7\.62\s*x\s*51(\s*NATO)?|\.308)\s+/i },
		{ name: '7.62x54R', re: /7\.62\s*x\s*54|54R|\bLPS\b/i, strip: /^7\.62\s*x\s*54R?\s+/i },
		{ name: '.30-06', re: /30[.\-]?06|M2\s?AP/i, strip: /^\.?30[.\-]?06\s+/i }
	];
	function threatGroups(v) {
		var out = [], idx = {};
		toList(v).forEach(function (raw) {
			var cal = null;
			for (var i = 0; i < CALIBERS.length; i++) if (CALIBERS[i].re.test(raw)) { cal = CALIBERS[i]; break; }
			var name = cal ? raw.replace(cal.strip, '') : raw;
			if (!name) name = raw;
			name = name.replace(/^M2AP$/i, 'M2 AP');
			var key = cal ? cal.name : 'Other';
			if (idx[key] === undefined) { idx[key] = out.length; out.push({ cal: key, order: cal ? CALIBERS.indexOf(cal) : 99, rounds: [] }); }
			out[idx[key]].rounds.push({ name: name, ap: /\bAP\b|M2\s?AP|M61\b|M993|M995|\bAPM2\b/i.test(raw) });
		});
		return out.sort(function (a, b) { return a.order - b.order; });
	}

	var LEVEL_ORDER = ['IV', 'RF3', 'RF2', 'RF1', 'III+', 'III', 'IIIA', 'HG2', 'HG1', 'II', 'IIA'];
	function levelTitle(l) { l = String(l || ''); return /^(II|III|IV)A?\+?$/.test(l) || l === 'IIA' ? 'Level ' + l : (l || 'Level not listed'); }
	function tierColor(l) { return { hg: '#8fb5c9', rifle: '#f0bc5a', rf: '#f07a52', ap: '#ff5a6e', other: '#a4a49a' }[tier(l)]; }
	function rangeText(lo, hi, places, unit) {
		if (lo === null) return null;
		var f = function (n) { return places !== undefined ? n.toFixed(places) : fmt(n); };
		return (lo === hi ? f(lo) : f(lo) + '–' + f(hi)) + (unit ? ' ' + unit : '');
	}
	function niceStep(raw) {
		var p = Math.pow(10, Math.floor(Math.log(raw) / Math.LN10)), n = raw / p;
		return (n <= 1 ? 1 : n <= 2 ? 2 : n <= 2.5 ? 2.5 : n <= 5 ? 5 : 10) * p;
	}

	Panel.prototype.viewInit = function () {
		this.viewBox = this.section.querySelector('[data-j5ac-view]');
		if (!this.viewBox) return;
		var self = this;
		this.famTotal = {};
		this.rows.forEach(function (r) { var k = r.tr.getAttribute('data-fam'); self.famTotal[k] = (self.famTotal[k] || 0) + 1; });
		var saved = null;
		try { saved = window.localStorage.getItem('j5ac-view-' + this.config.category); } catch (e) {}
		this.view = saved === 'cards' || saved === 'chart' ? saved : 'rows';
		this.vbtns = this.section.querySelectorAll('[data-j5ac-vbtn]');
		this.groupSel = this.section.querySelector('[data-j5ac-group]');
		this.measureSel = this.section.querySelector('[data-j5ac-measure]');
		this.measureWrap = this.section.querySelector('[data-j5ac-measurewrap]');
		Array.prototype.forEach.call(this.vbtns, function (b) {
			b.addEventListener('click', function () {
				self.view = b.getAttribute('data-j5ac-vbtn');
				try { window.localStorage.setItem('j5ac-view-' + self.config.category, self.view); } catch (e) {}
				self.renderView();
			});
		});
		this.section.classList.add('has-view');
		this.viewBox.addEventListener('change', function (e) {
			if (e.target && e.target.matches && e.target.matches('[data-j5ac-fitsel]') && e.target.value) self.setFit(e.target.value);
		});
		this.viewBox.addEventListener('click', function (e) {
			var t = e.target;
			if (!t.closest) return;
			var szb = t.closest('[data-j5ac-size]');
			if (szb) { self.famSel = self.famSel || {}; self.famSel[szb.getAttribute('data-fam')] = szb.getAttribute('data-j5ac-size'); self.renderCards(); var again = self.viewBox.querySelector('[data-j5ac-size="' + szb.getAttribute('data-j5ac-size') + '"]'); if (again) again.focus(); return; }
			var fb = t.closest('[data-j5ac-fit]');
			if (fb) { self.setFit(fb.getAttribute('data-j5ac-fit')); return; }
			var mo = t.closest('[data-j5ac-more]');
			if (mo) { var mk = mo.getAttribute('data-j5ac-more'); self.moreOpen = self.moreOpen || {}; self.moreOpen[mk] = !self.moreOpen[mk]; self.renderView(); var mb = self.viewBox.querySelector('[data-j5ac-more="' + mk + '"]'); if (mb) mb.focus(); return; }
			var clr = t.closest('[data-j5ac-clearpicks]');
			if (clr) { self.clearPicks(); return; }
			if (t.closest('[data-j5ac-opencmp]')) { self.openCompare(); return; }
			var cmp = t.closest('[data-j5ac-cmp]');
			if (cmp) { if (!cmp.disabled) self.toggleFam(cmp.getAttribute('data-j5ac-cmp'), cmp.getAttribute('data-slug')); return; }
			var open = t.closest('[data-j5ac-fam]');
			if (!open && t.closest('input, a, button, label, select')) return;
			var item = open || t.closest('[data-j5ac-item]');
			if (!item) return;
			self.openDetail(item.getAttribute(open ? 'data-j5ac-fam' : 'data-j5ac-item'), item.querySelector('[data-j5ac-fam]') || item);
		});
	};
	/** Compare on/off for a whole listing: on picks the shown size, off drops every picked size of it. */
	Panel.prototype.toggleFam = function (k, slug) {
		var self = this, any = false;
		this.picked.slice().forEach(function (s) {
			var r = self.rowBySlug(s);
			if (r && r.tr.getAttribute('data-fam') === k) { any = true; r.tr.querySelector('.j5ac-pick').checked = false; self.pick(s, false, null); }
		});
		if (any) return;
		var r = this.rowBySlug(slug), tcb = r && r.tr.querySelector('.j5ac-pick');
		if (tcb) tcb.checked = true;
		this.pick(slug, true, tcb);
	};

	/** Visible listings in the current sort order: { key, rep, members[] }. */
	Panel.prototype.viewFamilies = function () {
		var fams = {}, order = [];
		Array.prototype.forEach.call(this.tbody.querySelectorAll('tr[data-row]'), function (tr) {
			if (tr.classList.contains('is-hidden')) return;
			var k = tr.getAttribute('data-fam') || tr.getAttribute('data-slug'), r = tr._j5r;
			if (!fams[k]) { fams[k] = { key: k, rep: null, members: [] }; order.push(fams[k]); }
			fams[k].members.push(r);
			if (!tr.classList.contains('is-sib')) fams[k].rep = r;
		});
		var self = this;
		return order.map(function (f) {
			if (!f.rep) f.rep = f.members[0];
			f.members.sort(bySizeOrder);
			return self.famSummary(f);
		});
	};

	Panel.prototype.famSummary = function (f) {
		var d = f.rep.data, m = f.members, self = this;
		var range = function (get) {
			var n = m.map(get).map(num).filter(function (x) { return x !== null; });
			return n.length ? [Math.min.apply(null, n), Math.max.apply(null, n)] : [null, null];
		};
		var sgroups = sizeGroups(m, d), sizes = [];
		sgroups.forEach(function (g) { g.sizes.forEach(function (x) { sizes.push(x); }); });
		var cuts = cutsOf(m);
		var wc = null; m.forEach(function (r) { if (!wc && r.data.wc) wc = r.data.wc; });
		var picked = m.some(function (r) { return self.picked.indexOf(r.data.slug) !== -1; });
		return {
			key: f.key, rep: f.rep, members: m, d: d,
			name: (this.famTotal[f.key] || 1) > 1 && d.family ? famName(d) : d.model,
			weight: range(function (r) { return r.data.specs.weight_lb; }),
			eff: range(function (r) { return r.data.derived ? r.data.derived.oz_per_sqin : null; }),
			area: range(function (r) { return r.data.derived ? r.data.derived.area_sqin : null; }),
			sizes: sizes, sgroups: sgroups, cuts: cuts, cut: cuts.join(' & '), cert: stepValue(d, 'nij'),
			stock: d.carried ? (wc ? 'Sold by J5' : 'Quote from J5') : 'Reference only',
			carried: !!d.carried, wc: wc, picked: picked,
			locked: false,
			why: !picked && this.unequal ? (this.unequal[f.rep.data.slug] || []) : [],
			// "Full & Shooter's 10 x 12" when a listing makes the picked size in more than one cut.
			fitHit: this.fit ? (function () { var hit = '', cuts = []; m.forEach(function (r) { if (fitKey(r.data) !== self.fit) return; var l = fitLabel(r.data), c = l.slice(0, l.length - self.fit.length).trim(); if (!hit) hit = l; if (c && cuts.indexOf(c) === -1) cuts.push(c); }); return cuts.length > 1 ? cuts.join(' & ') + ' ' + self.fit : hit; })() : '',
			shownLabel: (function () { var z = sizeOf(d); return z && z.w ? (z.sub || z.label.replace(' in', '')) : ''; })(),
			shownW: num(d.specs.weight_lb),
			shownEff: d.derived && typeof d.derived.oz_per_sqin === 'number' ? d.derived.oz_per_sqin : null
		};
	};

	Panel.prototype.viewGroups = function (fams) {
		var by = this.groupSel ? this.groupSel.value : 'level', groups = {}, order = [];
		fams.forEach(function (f) {
			var k, title, color = null;
			if (by === 'level') { k = String(f.d.specs.level || ''); title = levelTitle(k); color = tierColor(k); }
			else if (by === 'cut') { k = f.cut || 'Cut not listed'; title = k; }
			else if (by === 'maker') { k = f.d.manufacturer || 'Manufacturer not listed'; title = k; }
			else { k = '*'; title = ''; }
			if (!groups[k]) { groups[k] = { key: k, title: title, color: color, items: [] }; order.push(groups[k]); }
			groups[k].items.push(f);
		});
		if (by === 'level') order.sort(function (a, b) { var x = LEVEL_ORDER.indexOf(a.key), y = LEVEL_ORDER.indexOf(b.key); return (x === -1 ? 99 : x) - (y === -1 ? 99 : y); });
		if (by === 'maker' || by === 'cut') order.sort(function (a, b) { return a.key.localeCompare(b.key); });
		// J5-AC-SIDE: side plates get their own sub heading under each group, after the full-size plates.
		// J5-AC-FIT-1: with a plate size picked, listings made in it come first in each group (order otherwise kept).
		var fitOn = !!this.fit;
		if (this.config.category === 'plate') order.forEach(function (g) {
			if (fitOn) g.items = g.items.filter(function (f) { return !!f.fitHit; }).concat(g.items.filter(function (f) { return !f.fitHit; }));
			var full = g.items.filter(function (f) { return !isSidePlate(f); }), side = g.items.filter(isSidePlate);
			if (!side.length) return;
			g.items = full.concat(side);
			if (full.length) full[0].subHead = { t: 'Full-size plates', n: full.length };
			side[0].subHead = { t: 'Side plates', n: side.length };
		});
		return order;
	};

	/** Compare pill: "+ Compare" / "✓ Comparing". */
	function cmpBtn(on, locked, label, anyway) {
		var b = el('button', 'j5ac-cmp' + (on ? ' is-on' : '') + (anyway && !on ? ' is-anyway' : ''));
		b.type = 'button';
		b.setAttribute('aria-pressed', on ? 'true' : 'false');
		b.setAttribute('aria-label', (on ? 'Remove from compare: ' : 'Compare ') + label);
		b.appendChild(el('span', 'j5ac-cmp-i'));
		b.appendChild(el('span', 'j5ac-cmp-t', on ? 'Added' : (anyway ? 'Compare anyway' : 'Compare')));
		if (locked) { b.disabled = true; b.title = 'Different size from your first pick. Clear the selection to compare this one.'; }
		return b;
	}
	function vpick(f) {
		var b = cmpBtn(f.picked, false, f.d.manufacturer + ' ' + f.name, f.why.length > 0);
		b.setAttribute('data-j5ac-cmp', f.key);
		b.setAttribute('data-slug', f.rep.data.slug);
		return b;
	}
	function vname(f, cls) {
		var b = el('button', 'j5ac-model j5ac-open ' + (cls || ''), f.name);
		b.type = 'button'; b.setAttribute('data-j5ac-fam', f.key); b.setAttribute('aria-haspopup', 'dialog');
		return b;
	}
	function vkv(k, v, cls) { var w = el('div', 'j5ac-kv' + (cls ? ' ' + cls : '')); w.appendChild(el('span', 'j5ac-k', k)); w.appendChild(el('span', 'j5ac-v', v === null || v === undefined || v === '' ? 'not listed' : v)); return w; }
	var SIZE_SHOW = 6; // (kept for reference; J5-AC-SIZES-B shows a summary and opens every size on demand)
	/**
	 * J5-AC-SIZES-B (2026-11-09): the sizes cell.
	 *   - Picked size (J5-AC-FIT-1): "✓ Shooter's 10 x 12" or "Not made in 10 x 12".
	 *   - Summary: one tag per cut with its size count, and the size shown ("Showing SAPI M").
	 *   - "All N sizes" opens every size, grouped by cut; each is a button that switches the shown size.
	 * One size: just that size, no toggle.
	 */
	function vsizes(f, more, fit) {
		var w = el('div', 'j5ac-vsizes');
		w.appendChild(el('span', 'j5ac-k', f.sizes.length > 1 ? 'Sizes' : 'Size'));
		if (!f.sgroups.length) { w.appendChild(el('span', 'j5ac-na', 'not listed')); return w; }
		var open = !!(more && more[f.key]), total = 0;
		f.sgroups.forEach(function (g) { total += (g.items || g.sizes).length; });
		var other = f.cuts.filter(function (c) { return !/shoot|sapi/i.test(c); }).join(' / ');
		var gname = function (g) { return g.label || other || 'Sizes'; };
		if (fit) {
			if (f.fitHit) w.appendChild(el('span', 'j5ac-vfit is-yes', '✓ ' + f.fitHit));
			else w.appendChild(el('span', 'j5ac-vfit is-no', 'Not made in ' + fit));
		}
		var chips = function () {
			f.sgroups.forEach(function (g) {
				var c = el('div', 'j5ac-vchips');
				if (g.label || f.sgroups.length > 1) c.appendChild(el('span', 'j5ac-vchips-l', gname(g)));
				var list = el('div', 'j5ac-vchips-s'); c.appendChild(list);
				(g.items || g.sizes.map(function (n) { return { n: n, slug: null }; })).forEach(function (it) {
					if (!it.slug || f.members.length < 2) { list.appendChild(el('span', 'j5ac-vchip' + (it.slug && it.slug === f.rep.data.slug && f.members.length > 1 ? ' is-on' : ''), it.n)); return; }
					var b = el('button', 'j5ac-vchip' + (it.slug === f.rep.data.slug ? ' is-on' : ''), it.n);
					b.type = 'button';
					b.setAttribute('data-j5ac-size', it.slug);
					b.setAttribute('data-fam', f.key);
					b.setAttribute('aria-pressed', it.slug === f.rep.data.slug ? 'true' : 'false');
					b.setAttribute('aria-label', 'Show ' + f.name + ' ' + it.n);
					list.appendChild(b);
				});
				if (list.firstChild) w.appendChild(c);
			});
		};
		if (total <= 1) { chips(); return w; }
		var sum = el('div', 'j5ac-vcuts');
		f.sgroups.forEach(function (g) {
			var t = el('span', 'j5ac-vcut', gname(g) + ' ');
			t.appendChild(el('span', 'j5ac-vcut-n', String((g.items || g.sizes).length)));
			sum.appendChild(t);
		});
		w.appendChild(sum);
		var line = el('div', 'j5ac-vshownl');
		if (f.shownLabel) {
			var sh = el('span', 'j5ac-vshown', 'Showing ');
			sh.appendChild(el('strong', null, f.shownLabel));
			line.appendChild(sh);
		}
		var mb = el('button', 'j5ac-vmore', open ? 'Hide sizes' : 'All ' + total + ' sizes');
		mb.type = 'button'; mb.setAttribute('data-j5ac-more', f.key); mb.setAttribute('aria-expanded', open ? 'true' : 'false');
		line.appendChild(mb);
		w.appendChild(line);
		if (open) {
			var panel = el('div', 'j5ac-vall');
			var keep = w; w = panel; chips(); w = keep;
			w.appendChild(panel);
		}
		return w;
	}
	/** Side plate: named as one (HESCO "-SP", "side plate"), or every size is 8 in or smaller on both sides. */
	function isSidePlate(f) {
		var hay = [f.name, f.d.model, f.d.family].join(' ');
		if (/-SP\b|\bside\s*plates?\b/i.test(hay)) return true;
		var dims = f.members.map(function (r) { return sizeOf(r.data); }).filter(function (z) { return z && z.w; });
		return dims.length > 0 && dims.every(function (z) { return Math.max(z.w, z.h) <= 8; });
	}
	function vsubhead(h) {
		var d = el('div', 'j5ac-vsubh');
		d.appendChild(el('h4', 'j5ac-vsubh-t', h.t));
		return d;
	}
	function vwhy(f) {
		var p = el('p', 'j5ac-why');
		p.appendChild(el('span', 'j5ac-why-i'));
		p.appendChild(el('strong', null, 'Not an equal comparison to your selected models.'));
		p.appendChild(el('span', 'j5ac-why-r', f.why.join('  \u00b7  ')));
		return p;
	}
	function vweight(f) {
		if (f.members.length < 2 || !f.shownLabel) return vkv('Weight', rangeText(f.weight[0], f.weight[1], undefined, 'lb'), 'is-strong');
		var w = vkv('Weight \u00b7 ' + f.shownLabel, f.shownW !== null ? fmt(f.shownW) + ' lb' : null, 'is-strong');
		var r = rangeText(f.weight[0], f.weight[1], undefined, 'lb');
		if (r && f.weight[0] !== f.weight[1]) w.appendChild(el('span', 'j5ac-vsm', r + ', all sizes'));
		return w;
	}
	function veff(f) {
		if (f.members.length < 2 || !f.shownLabel) return vkv('Oz / sq in', rangeText(f.eff[0], f.eff[1], 3));
		return vkv('Oz / sq in', f.shownEff !== null ? f.shownEff.toFixed(3) : null);
	}
	/** J5-AC-J5MARK: a small J5 mark on listings J5 sells; nothing on reference-only listings. */
	function j5mark(wc) {
		var m = el('span', 'j5ac-j5', 'J5');
		var t = wc ? 'Sold by J5' : 'Available from J5 by quote';
		m.title = t; m.setAttribute('role', 'img'); m.setAttribute('aria-label', t);
		return m;
	}
	function vstock(f) { return f.carried ? j5mark(!!f.wc) : null; }
	function vnameline(f) {
		var w = el('div', 'j5ac-vnl');
		w.appendChild(vname(f));
		var m = vstock(f); if (m) w.appendChild(m);
		return w;
	}
	/** J5-AC-TYPE: standalone or ICW as a badge; ICW says it needs soft armor. */
	function isICW(v) { return /icw|conjunction/i.test(String(v || '')); }
	function vtype(v) {
		var icw = isICW(v), b = el('span', 'j5ac-type' + (icw ? ' is-icw' : ''), icw ? 'ICW' : (v || 'Standalone'));
		if (icw) b.title = 'In conjunction with soft armor: rated only when worn over the soft armor on its spec sheet';
		return b;
	}
	function vtypekv(v) {
		var w = el('div', 'j5ac-kv j5ac-kv--type');
		w.appendChild(el('span', 'j5ac-k', 'Type'));
		var vv = el('span', 'j5ac-v'); vv.appendChild(vtype(v));
		if (isICW(v)) vv.appendChild(el('span', 'j5ac-type-n', 'Needs soft armor'));
		w.appendChild(vv);
		return w;
	}
	function vhead(g, count) {
		var h = el('div', 'j5ac-vgroup');
		if (g.color) { var dot = el('span', 'j5ac-vgroup-dot'); dot.style.background = g.color; dot.setAttribute('aria-hidden', 'true'); h.appendChild(dot); }
		var t = el('h3', 'j5ac-vgroup-t', g.title); if (g.color) t.style.color = g.color;
		h.appendChild(t);
		if (g.color) h.style.borderBottomColor = g.color;
		return h;
	}

	Panel.prototype.renderView = function () {
		if (!this.viewBox) return;
		var self = this, cfg = this.config, view = this.view;
		Array.prototype.forEach.call(this.vbtns, function (b) { var on = b.getAttribute('data-j5ac-vbtn') === view; b.setAttribute('aria-pressed', on ? 'true' : 'false'); b.classList.toggle('is-on', on); });
		if (this.measureWrap) this.measureWrap.hidden = view !== 'chart';
		var fams = this.viewFamilies(), groups = this.viewGroups(fams);
		var box = this.viewBox;
		box.textContent = '';
		box.className = 'j5ac-view j5ac-view--' + view;
		if (cfg.category === 'plate') { try { box.appendChild(this.fitBar(fams)); } catch (err) { if (window.console) console.error('j5ac fit bar', err); } } // J5-AC-FIT-1
		if (this.picked.length) {
			var p0 = this.rowBySlug(this.picked[0]), bd = p0 ? p0.data : null, bsz = bd ? sizeOf(bd) : null;
			var bar = el('div', 'j5ac-cmpbar'); bar.setAttribute('role', 'status');
			bar.appendChild(el('span', 'j5ac-cmpbar-h', 'Comparing ' + this.picked.length));
			var facts = bd ? [bd.specs.level, bd.specs.standard, bsz ? (bsz.sub || bsz.label) : null, bd.specs.weight_lb !== undefined ? fmt(bd.specs.weight_lb) + ' lb' : null].filter(Boolean).join(' \u00b7 ') : '';
			bar.appendChild(el('span', 'j5ac-cmpbar-t', (facts ? facts + '. ' : '') + 'Models that differ in size, threat level, standard or weight are marked with the reason. You can still add them.'));
			var oc = el('button', 'j5ac-cmpbar-go', 'Open compare'); oc.type = 'button'; oc.setAttribute('data-j5ac-opencmp', '');
			bar.appendChild(oc);
			var cl = el('button', 'j5ac-cmpbar-x', 'Clear selection'); cl.type = 'button'; cl.setAttribute('data-j5ac-clearpicks', '');
			bar.appendChild(cl);
			box.appendChild(bar);
		}
		if (!fams.length) return;
		var hasCert = !!this.fieldsByKey.nij_certified, hasCut = !!this.fieldsByKey.cut, hasStd = !!this.fieldsByKey.standard, hasType = !!this.fieldsByKey.standalone;

		if (view === 'rows') {
			groups.forEach(function (g) {
				var sec = el('section', 'j5ac-vsec');
				if (g.title) sec.appendChild(vhead(g, g.items.length));
				g.items.forEach(function (f) {
					if (f.subHead) sec.appendChild(vsubhead(f.subHead));
					var row = el('div', 'j5ac-vrow' + (f.carried ? ' is-carried' : '') + (f.picked ? ' is-picked' : '') + (f.why.length ? ' is-unequal' : '') + (self.fit && !f.fitHit ? ' is-nofit' : ''));
					row.setAttribute('data-j5ac-item', f.key);
					var nm = el('div', 'j5ac-vname');
					nm.appendChild(vnameline(f));
					nm.appendChild(el('span', 'j5ac-maker j5ac-maker--lg', f.d.manufacturer));
					row.appendChild(nm);
					var specs = el('div', 'j5ac-vspecs');
					if (!g.color && f.d.specs.level) { var lv = el('div', 'j5ac-kv'); lv.appendChild(el('span', 'j5ac-k', 'Level')); var lvv = el('span', 'j5ac-v'); lvv.appendChild(el('span', 'j5ac-lv j5ac-lv--' + tier(f.d.specs.level), f.d.specs.level)); lv.appendChild(lvv); specs.appendChild(lv); }
					if (hasStd) specs.appendChild(vkv('Standard', f.d.specs.standard));
					if (hasCert) specs.appendChild(vkv('NIJ certified', f.cert === 'yes' ? 'Yes' : (f.cert === 'no' ? 'No' : null), f.cert === 'yes' ? 'is-yes' : ''));
					if (hasType) specs.appendChild(vtypekv(f.d.specs.standalone));
					if (hasCut) specs.appendChild(vkv('Cut', f.cuts.length ? f.cuts.join(' &\n') : null, 'is-cut'));
					specs.appendChild(vweight(f));
					if (cfg.eff) specs.appendChild(veff(f));
					row.appendChild(specs);
					row.appendChild(vsizes(f, self.moreOpen, self.fit));
					row.appendChild(vpick(f));
					if (f.why.length) row.appendChild(vwhy(f));
					sec.appendChild(row);
				});
				box.appendChild(sec);
			});
		} else if (view === 'cards') {
			groups.forEach(function (g) {
				var sec = el('section', 'j5ac-vsec');
				if (g.title) sec.appendChild(vhead(g, g.items.length));
				var grid = el('div', 'j5ac-vgrid');
				g.items.forEach(function (f) {
					if (f.subHead) { var shc = vsubhead(f.subHead); shc.classList.add('j5ac-vsubh--grid'); grid.appendChild(shc); }
					var c = el('article', 'j5ac-vcard' + (f.carried ? ' is-carried' : '') + (f.picked ? ' is-picked' : '') + (f.why.length ? ' is-unequal' : '') + (self.fit && !f.fitHit ? ' is-nofit' : ''));
					c.setAttribute('data-j5ac-item', f.key);
					if (f.d.image) { var im = el('img', 'j5ac-vcard-img'); im.src = f.d.image; im.alt = f.d.manufacturer + ' ' + f.name; im.loading = 'lazy'; c.appendChild(im); }
					var body = el('div', 'j5ac-vcard-b');
					var top = el('div', 'j5ac-vcard-top');
					var tl = el('div');
					tl.appendChild(vnameline(f));
					tl.appendChild(el('span', 'j5ac-maker j5ac-maker--lg', f.d.manufacturer));
					top.appendChild(tl);
					if (f.d.specs.level) top.appendChild(el('span', 'j5ac-lv j5ac-lv--' + tier(f.d.specs.level), f.d.specs.level));
					body.appendChild(top);
					var tags = el('div', 'j5ac-vtags');
					if (f.d.specs.standalone) { tags.appendChild(vtype(f.d.specs.standalone)); if (isICW(f.d.specs.standalone)) tags.appendChild(el('span', 'j5ac-type-n', 'Needs soft armor')); }
					f.cuts.forEach(function (c) { tags.appendChild(el('span', 'j5ac-tag', c)); });
					if (f.cert === 'yes') tags.appendChild(el('span', 'j5ac-tag j5ac-tag--ok', 'NIJ certified'));
					if (tags.firstChild) body.appendChild(tags);
					var st = el('div', 'j5ac-vcard-stats');
					st.appendChild(vweight(f));
					st.appendChild(cfg.eff ? veff(f) : vkv('Standard', f.d.specs.standard));
					body.appendChild(st);
					body.appendChild(vsizes(f, self.moreOpen, self.fit));
					if (f.why.length) body.appendChild(vwhy(f));
					var foot = el('div', 'j5ac-vcard-foot');
					foot.appendChild(vpick(f));
					body.appendChild(foot);
					c.appendChild(body);
					grid.appendChild(c);
				});
				sec.appendChild(grid);
				box.appendChild(sec);
			});
		} else {
			var key = this.measureSel ? this.measureSel.value : 'weight_lb';
			var meta = {
				weight_lb: { m: 'Weight', sub: 'Pounds, from each listing\u2019s smallest size to its largest', unit: 'lb', axis: 'Weight (lb)', get: 'weight' },
				oz_per_sqin: { m: 'Oz per sq in', sub: 'Weight for the coverage. Lower is lighter', unit: '', places: 3, axis: 'Ounces per sq in', get: 'eff' },
				area_sqin: { m: 'Coverage', sub: 'Square inches, from each listing\u2019s smallest size to its largest', unit: 'sq in', axis: 'Coverage (sq in)', get: 'area' }
			}[key] || { m: 'Weight', sub: '', unit: 'lb', axis: 'Weight (lb)', get: 'weight' };
			var hi = 0;
			fams.forEach(function (f) { if (f[meta.get][1] !== null) hi = Math.max(hi, f[meta.get][1]); });
			if (!(hi > 0)) hi = 1;
			var step = niceStep(hi / 6), max = Math.ceil(hi / step) * step;
			var wrap = el('div', 'j5ac-chart');
			var head = el('div', 'j5ac-chart-h');
			var ht = el('div', 'j5ac-chart-ht');
			ht.appendChild(el('span', 'j5ac-chart-eyebrow', 'Chart'));
			ht.appendChild(el('h3', null, meta.m));
			head.appendChild(ht);
			head.appendChild(el('p', null, (meta.sub ? meta.sub + '. ' : '') + 'Click a model to see every size.'));
			wrap.appendChild(head);
			var axis = el('div', 'j5ac-crow j5ac-crow--axis');
			axis.appendChild(el('span', 'j5ac-caxis-l', meta.axis));
			var ax = el('div', 'j5ac-ctrack');
			for (var v = 0; v <= max + 1e-9; v += step) { var t = el('span', 'j5ac-ctick', (meta.places ? v.toFixed(2) : fmt(Math.round(v * 100) / 100))); t.style.left = (v / max * 100) + '%'; ax.appendChild(t); }
			axis.appendChild(ax);
			axis.appendChild(el('span', 'j5ac-k', ''));
			wrap.appendChild(axis);
			groups.forEach(function (g) {
				if (g.title) { var gh = vhead(g, g.items.length); gh.classList.add('j5ac-vgroup--chart'); wrap.appendChild(gh); }
				g.items.forEach(function (f) {
					var r = f[meta.get];
					if (f.subHead) { var shr = vsubhead(f.subHead); shr.classList.add('j5ac-vsubh--chart'); wrap.appendChild(shr); }
					var row = el('div', 'j5ac-crow' + (f.picked ? ' is-picked' : '') + (f.why.length ? ' is-unequal' : ''));
					if (f.why.length) row.title = 'Not an equal comparison to your selected models. ' + f.why.join('. ');
					row.setAttribute('data-j5ac-item', f.key);
					var nm = el('div', 'j5ac-cname');
					nm.appendChild(vnameline(f));
					var sub = el('div', 'j5ac-vsub');
					sub.appendChild(el('span', 'j5ac-maker j5ac-maker--lg', f.d.manufacturer));
					if (f.cert === 'yes') sub.appendChild(el('span', 'j5ac-tag j5ac-tag--ok', 'NIJ'));
					if (isICW(f.d.specs.standalone)) sub.appendChild(vtype(f.d.specs.standalone));
					nm.appendChild(sub);
					row.appendChild(nm);
					var tr = el('div', 'j5ac-ctrack');
					tr.style.backgroundSize = (step / max * 100) + '% 100%';
					if (r[0] === null) tr.appendChild(el('span', 'j5ac-na j5ac-cnone', 'not listed'));
					else {
						var bar = el('span', 'j5ac-cbar');
						bar.style.left = (r[0] / max * 100) + '%';
						bar.style.width = ((r[1] - r[0]) / max * 100) + '%';
						bar.style.background = tierColor(f.d.specs.level);
						tr.appendChild(bar);
						var val = el('span', 'j5ac-cval', rangeText(r[0], r[1], meta.places, meta.unit));
						val.style.left = 'calc(' + (r[1] / max * 100) + '% + 12px)';
						if (r[1] / max > .78) { val.style.left = 'auto'; val.style.right = 'calc(' + (100 - r[0] / max * 100) + '% + 12px)'; }
						tr.appendChild(val);
					}
					row.appendChild(tr);
					var end = el('div', 'j5ac-cend');
					end.appendChild(el('span', null, f.sizes.length > 1 ? f.sizes.length + ' sizes' : '1 size'));
					row.appendChild(end);
					wrap.appendChild(row);
				});
			});
			box.appendChild(wrap);
		}
	};

	/** J5-AC-FIT-1: "Your plate size" bar. The most common sizes as buttons, the rest in a list. */
	Panel.prototype.fitBar = function (fams) {
		var self = this, counts = {}, order = [];
		this.rows.forEach(function (r) {
			if (isSideModel && isSideModel(r.data)) return;
			var k = fitKey(r.data);
			if (!k) return;
			var fk = r.tr.getAttribute('data-fam') || r.data.slug;
			counts[k] = counts[k] || {};
			if (!counts[k][fk]) { counts[k][fk] = 1; if (order.indexOf(k) === -1) order.push(k); }
		});
		var n = function (k) { return Object.keys(counts[k]).length; };
		order.sort(function (a, b) { return n(b) - n(a) || (a < b ? -1 : 1); });
		if (this.fit && order.indexOf(this.fit) === -1) this.fit = '';
		var bar = el('div', 'j5ac-fitbar');
		bar.appendChild(el('span', 'j5ac-fitbar-h', 'Your plate size'));
		var btns = el('div', 'j5ac-fitbar-b');
		var top = ['', ].concat(order.slice(0, 6));
		if (this.fit && top.indexOf(this.fit) === -1) top.push(this.fit);
		top.forEach(function (k) {
			var b = el('button', 'j5ac-fitbtn' + (k === self.fit ? ' is-on' : ''), k || 'Any size');
			b.type = 'button'; b.setAttribute('data-j5ac-fit', k); b.setAttribute('aria-pressed', k === self.fit ? 'true' : 'false');
			btns.appendChild(b);
		});
		var rest = order.slice(6).filter(function (k) { return top.indexOf(k) === -1; });
		if (rest.length) {
			var sel = el('select', 'j5ac-fitsel'); sel.setAttribute('data-j5ac-fitsel', ''); sel.setAttribute('aria-label', 'Other plate sizes');
			var o0 = el('option', null, 'Other sizes'); o0.value = ''; sel.appendChild(o0);
			rest.sort().forEach(function (k) { var o = el('option', null, k); o.value = k; sel.appendChild(o); });
			btns.appendChild(sel);
		}
		bar.appendChild(btns);
		var hits = this.fit ? fams.filter(function (f) { return !!f.fitHit; }).length : 0;
		bar.appendChild(el('span', 'j5ac-fitbar-s', this.fit ? hits + ' of ' + fams.length + ' plates come in ' + this.fit : 'Pick a size to see which plates come in it'));
		bar.setAttribute('role', 'group'); bar.setAttribute('aria-label', 'Your plate size');
		return bar;
	};
	Panel.prototype.setFit = function (k) {
		this.fit = k || '';
		this.famSel = {}; // the picked size decides the shown size again
		this.renderCards();
		var b = this.viewBox.querySelector(k ? '[data-j5ac-fit="' + k.replace(/"/g, '') + '"]' : '[data-j5ac-fit=""]');
		if (b) b.focus();
	};

	Panel.prototype.renderCards = function () {
		this.collapseFamilies();
		this.renderView();
		if (!this.cardList) return;
		var self = this, cfg = this.config;
		this.cardList.textContent = '';
		var open = this.openCards || (this.openCards = {});
		var sel = this.sizeSel || (this.sizeSel = {});

		// Group the visible rows by listing, keeping the table's order for the first member.
		var groups = [], byKey = {};
		Array.prototype.forEach.call(this.tbody.querySelectorAll('tr[data-row]'), function (tr) {
			if (tr.classList.contains('is-hidden')) return;
			var d = JSON.parse(tr.getAttribute('data-row')), k = famKey(d);
			if (!byKey[k]) { byKey[k] = { key: k, items: [] }; groups.push(byKey[k]); }
			byKey[k].items.push({ d: d, tr: tr });
		});
		this.listingCount = groups.length;

		groups.forEach(function (g) {
			g.items.sort(function (a, b) { var x = sizeSortKey(a.d), y = sizeSortKey(b.d); return (x[0] - y[0]) || (x[1] - y[1]) || (x[2] - y[2]); });
			var models = g.items.map(function (i) { return i.d; });
			// Selected size: a picked member wins, then the remembered choice, then the first.
			var cur = g.items.filter(function (i) { return self.picked.indexOf(i.d.slug) !== -1; })[0]
				|| g.items.filter(function (i) { return i.d.slug === sel[g.key]; })[0] || nearestSize(g.items, self.sizeTarget) || g.items[0];
			var d = cur.d, tr = cur.tr;
			var card = el('article', 'j5ac-pcard' + (self.picked.indexOf(d.slug) !== -1 ? ' is-picked' : '') + (d.carried ? ' is-carried' : '') + (tr.classList.contains('is-locked') ? ' is-locked' : ''));
			card.setAttribute('data-slug', d.slug);

			var fig = el('div', 'j5ac-pcard-img');
			var ph = '<svg viewBox="0 0 64 80" width="56" height="70" aria-hidden="true"><path d="M8 6h48v44c0 14-10 22-24 26C18 72 8 64 8 50V6z" fill="none" stroke="currentColor" stroke-width="2.5"/><rect x="24" y="14" width="16" height="22" rx="2" fill="none" stroke="currentColor" stroke-width="2"/></svg>';
			if (d.image) { var img = el('img'); img.src = d.image; img.alt = d.manufacturer + ' ' + famName(d); img.loading = 'lazy'; img.addEventListener('error', function () { img.remove(); fig.classList.add('is-empty'); fig.insertAdjacentHTML('afterbegin', ph); }); fig.appendChild(img); }
			else { fig.classList.add('is-empty'); fig.innerHTML = ph; }
			if (d.carried) fig.appendChild(el('span', 'j5ac-pcard-flag' + (d.wc ? ' is-sold' : ' is-quote'), d.wc ? 'Sold by J5' : 'Quote from J5'));
			card.appendChild(fig);

			var body = el('div', 'j5ac-pcard-body');
			var head = el('div', 'j5ac-pcard-head');
			var pick = el('label', 'j5ac-pcard-pick');
			var cb = el('input'); cb.type = 'checkbox'; cb.checked = self.picked.indexOf(d.slug) !== -1; cb.disabled = tr.classList.contains('is-locked');
			cb.setAttribute('aria-label', 'Compare ' + d.manufacturer + ' ' + d.model);
			cb.addEventListener('change', function () { if (cb.checked && !self.sizeTarget) self.sizeTarget = sizeOf(d); var tcb = tr.querySelector('.j5ac-pick'); tcb.checked = cb.checked; self.pick(d.slug, cb.checked, tcb); });
			pick.appendChild(cb); pick.appendChild(el('span', null, 'Compare'));
			head.appendChild(pick);
			body.appendChild(head);
			body.appendChild(el('h3', 'j5ac-pcard-name', famName(d)));
			body.appendChild(el('p', 'j5ac-pcard-maker', d.manufacturer));

			// Size picker (only when the listing has more than one size).
			if (models.length > 1) {
				var sopen = self.sizeOpen || (self.sizeOpen = {});
				var curSz = sizeOf(d), curLabel = curSz && curSz.w ? curSz.label : (d.specs && d.specs.size ? String(d.specs.size) : d.model);
				var sizes = el('div', 'j5ac-pcard-sizes' + (sopen[g.key] ? ' is-open' : ''));
				var tog = el('button', 'j5ac-size-toggle'); tog.type = 'button'; tog.setAttribute('aria-expanded', sopen[g.key] ? 'true' : 'false');
				tog.appendChild(el('span', 'j5ac-size-toggle-k', 'Size'));
				tog.appendChild(el('span', 'j5ac-size-toggle-v', curLabel + (d.specs && d.specs.weight_lb !== undefined ? ', ' + d.specs.weight_lb + ' lb' : '')));
				tog.appendChild(el('span', 'j5ac-size-toggle-n', models.length + ' sizes'));
				tog.addEventListener('click', function () { sopen[g.key] = !sopen[g.key]; self.renderCards(); });
				sizes.appendChild(tog);
				var list = el('div', 'j5ac-size-list'); list.setAttribute('role', 'group'); list.setAttribute('aria-label', 'Choose a size'); list.hidden = !sopen[g.key];
				g.items.forEach(function (i) {
					var isz = sizeOf(i.d), ih = isz && isz.w ? isz.label : (i.d.specs && i.d.specs.size ? String(i.d.specs.size) : i.d.model);
					var b = el('button', 'j5ac-size' + (i.d.slug === d.slug ? ' is-on' : '')); b.type = 'button';
					b.appendChild(el('span', 'j5ac-size-d', ih));
					var meta = [];
					if (i.d.specs && i.d.specs.weight_lb !== undefined) meta.push(i.d.specs.weight_lb + ' lb');
					// Same dimensions twice: add the handle only when it actually tells them apart.
					var twins = models.filter(function (o) { var z = sizeOf(o); return z && isz && z.key === isz.key; });
					if (twins.length > 1 && i.d.specs && i.d.specs.handle && twins.some(function (o) { return String(o.specs.handle || '') !== String(i.d.specs.handle); })) meta.push(String(i.d.specs.handle));
					b.appendChild(el('span', 'j5ac-size-w', meta.join(', ')));
					if (i.d.carried) { var dot = el('span', 'j5ac-size-c' + (i.d.wc ? ' is-sold' : ' is-quote')); dot.title = i.d.wc ? 'Sold by J5' : 'Quote from J5'; b.appendChild(dot); }
					b.setAttribute('aria-pressed', i.d.slug === d.slug ? 'true' : 'false');
					b.addEventListener('click', function () {
						sopen[g.key] = false;
						// This size becomes the target: every other card snaps to its closest size.
						self.sizeTarget = sizeOf(i.d);
						self.sizeSel = sel = {};
						if (i.d.slug === d.slug) { self.renderCards(); return; }
						// Move an existing pick to the new size so compare follows the card.
						var was = self.picked.indexOf(d.slug) !== -1;
						sel[g.key] = i.d.slug;
						if (was) { var oldcb = tr.querySelector('.j5ac-pick'); oldcb.checked = false; self.pick(d.slug, false, oldcb); var ncb = i.tr.querySelector('.j5ac-pick'); ncb.checked = true; self.pick(i.d.slug, true, ncb); }
						else self.renderCards();
					});
					list.appendChild(b);
				});
				sizes.appendChild(list);
				body.appendChild(sizes);
			}

			// Key facts for the selected size.
			var facts = el('div', 'j5ac-pcard-facts');
			if (d.specs.level) facts.appendChild(el('span', 'j5ac-lv j5ac-lv--' + tier(d.specs.level), d.specs.level));
			var sz = sizeOf(d); if (sz && sz.w) facts.appendChild(el('span', null, sz.label));
			if (d.specs.weight_lb !== undefined) facts.appendChild(el('span', null, fmt(d.specs.weight_lb, self.fieldsByKey.weight_lb)));
			if (cfg.category === 'shield') facts.appendChild(el('span', null, viewportArea(d.specs.viewport) > 0 ? 'Viewport' : 'No viewport'));
			if (d.derived && d.derived.oz_per_sqin !== undefined) facts.appendChild(el('span', 'is-gold', d.derived.oz_per_sqin.toFixed(3) + ' oz/sq in'));
			body.appendChild(facts);

			// Expandable specs for the selected size.
			var isOpen = !!open[g.key];
			var more = el('button', 'j5ac-pcard-more', isOpen ? 'Hide specs' : 'Specs'); more.type = 'button'; more.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
			var specs = el('dl', 'j5ac-pcard-specs'); specs.hidden = !isOpen;
			cfg.fields.forEach(function (f) {
				if (d.specs[f.key] === undefined || f.key === 'family') return;
				specs.appendChild(el('dt', null, f.label));
				var dd = el('dd'); dd.textContent = fmt(d.specs[f.key], f); specs.appendChild(dd);
			});
			if (d.derived && d.derived.area_sqin !== undefined) { specs.appendChild(el('dt', null, 'Coverage')); specs.appendChild(el('dd', null, d.derived.area_sqin + ' sq in')); }
			if (models.length > 1) { specs.appendChild(el('dt', null, 'Sizes offered')); var sdd = el('dd', 'j5ac-pcard-sizelist'); models.forEach(function (m) { sdd.appendChild(el('span', null, sizeLabel(m, models))); }); specs.appendChild(sdd); }
			if (d.sheet && d.sheet.url) {
				specs.appendChild(el('dt', null, 'Spec sheet')); var dd2 = el('dd');
				var a = el('a', null, d.sheet.pages ? 'PDF, page ' + d.sheet.pages : 'PDF'); a.href = d.sheet.url; a.target = '_blank'; a.rel = 'noopener'; dd2.appendChild(a);
				if (d.sheet.full_url) { dd2.appendChild(document.createTextNode(' / ')); var fa = el('a', null, 'full catalog sheet'); fa.href = d.sheet.full_url; fa.target = '_blank'; fa.rel = 'noopener'; dd2.appendChild(fa); }
				if (d.sheet.pulled_at) dd2.appendChild(document.createTextNode(', pulled ' + d.sheet.pulled_at));
				specs.appendChild(dd2);
			}
			var rep = el('button', 'j5ac-report', 'Report incorrect information'); rep.type = 'button';
			rep.addEventListener('click', function () { openReport(self.root, cfg.category, d); });
			more.addEventListener('click', function () { var o = specs.hidden; specs.hidden = !o; open[g.key] = o; more.textContent = o ? 'Hide specs' : 'Specs'; more.setAttribute('aria-expanded', o ? 'true' : 'false'); });
			var actions = el('div', 'j5ac-pcard-actions');
			actions.appendChild(more);
			if (d.sheet && d.sheet.url) { var pdf = el('a', 'j5ac-pcard-pdf', 'Spec sheet'); pdf.href = d.sheet.url; pdf.target = '_blank'; pdf.rel = 'noopener'; pdf.title = 'Manufacturer spec sheet (PDF)'; actions.appendChild(pdf); }
			if (d.wc) { var buy = el('a', 'j5ac-btn j5ac-btn--buy', 'View product'); buy.href = d.wc; actions.appendChild(buy); }
			else if (d.carried) { var q = el('a', 'j5ac-btn j5ac-btn--quote', 'Request a quote'); q.href = (self.root.getAttribute('data-quote') || '/request-a-quote/') + '?item=' + encodeURIComponent(d.manufacturer + ' ' + d.model) + '&ref=armor-compare'; actions.appendChild(q); }
			body.appendChild(actions);
			body.appendChild(specs);
			var repWrap = el('p', 'j5ac-pcard-report'); repWrap.appendChild(rep); body.appendChild(repWrap);
			card.appendChild(body);
			self.cardList.appendChild(card);
		});
		this.cardList.hidden = this.listWrap.hidden;
		// The "N match" line counts listings when cards are showing.
		var done = this.guideBox && this.guideBox.querySelector('.j5ac-guide-done');
		if (done && !this.cardList.hidden) {
			var n = groups.length, t = this.sizeTarget;
			done.textContent = n + (n === 1 ? ' model matches. ' : ' models match. ') + (t && t.w ? 'Cards show the size closest to ' + t.label + '. ' : 'Pick a size on a card and the others follow. ') + 'Check any to compare side by side.';
		}
	};

	/** "Report incorrect information" dialog, shared by every panel on the page. */
	function openReport(root, category, d) {
		var modal = root.querySelector('[data-j5ac-modal]'); if (!modal) return;
		var form = modal.querySelector('[data-j5ac-report-form]'), msg = modal.querySelector('[data-j5ac-report-msg]');
		form.reset(); msg.hidden = true; msg.className = 'j5ac-modal-msg';
		form.elements.category.value = category; form.elements.slug.value = d.slug; form.elements.model.value = d.model; form.elements.manufacturer.value = d.manufacturer;
		modal.querySelector('[data-j5ac-report-model]').textContent = d.manufacturer + ' ' + d.model;
		form.querySelector('button[type=submit]').disabled = false;
		modal.hidden = false; document.body.classList.add('j5ac-modal-open');
		setTimeout(function () { form.elements.field.focus(); }, 30);
	}
	function bindReport(root) {
		var modal = root.querySelector('[data-j5ac-modal]'); if (!modal) return;
		var form = modal.querySelector('[data-j5ac-report-form]'), msg = modal.querySelector('[data-j5ac-report-msg]');
		function close() { modal.hidden = true; document.body.classList.remove('j5ac-modal-open'); }
		Array.prototype.forEach.call(modal.querySelectorAll('[data-j5ac-modal-close]'), function (b) { b.addEventListener('click', close); });
		modal.addEventListener('click', function (e) { if (e.target === modal) close(); });
		document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && !modal.hidden) close(); });
		form.addEventListener('submit', function (e) {
			e.preventDefault();
			var btn = form.querySelector('button[type=submit]'); btn.disabled = true;
			var data = {};
			Array.prototype.forEach.call(form.elements, function (i) { if (i.name) data[i.name] = i.value; });
			data.page = location.href;
			fetch(root.getAttribute('data-report-url'), { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(data) })
				.then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); })
				.then(function (x) {
					msg.hidden = false;
					if (x.ok) { msg.className = 'j5ac-modal-msg is-ok'; msg.textContent = 'Thanks. We will check it against the manufacturer sheet and fix it if it is off.'; setTimeout(close, 2200); }
					else { msg.className = 'j5ac-modal-msg is-err'; msg.textContent = (x.j && x.j.message) || 'Could not send. Try again.'; btn.disabled = false; }
				})
				.catch(function () { msg.hidden = false; msg.className = 'j5ac-modal-msg is-err'; msg.textContent = 'Could not send. Try again.'; btn.disabled = false; });
		});
		// Table rows (plates, helmets) carry a small Report button in the sheet column.
		root.addEventListener('click', function (e) {
			var b = e.target.closest && e.target.closest('[data-j5ac-report]'); if (!b) return;
			var tr = b.closest('tr[data-row]'), panel = b.closest('.j5ac-panel');
			if (tr && panel) openReport(root, panel.getAttribute('data-cat'), JSON.parse(tr.getAttribute('data-row')));
		});
	}

	/**
	 * J5-AC-EQUAL: nothing is locked any more. Every model that differs from the picked ones in
	 * coverage (more than 20%), protection level, test standard or weight (more than 30%) is marked
	 * with the reasons, and can still be compared ("Compare anyway").
	 */
	Panel.prototype.lockSizes = function () {
		var self = this, picks = this.picked.slice(0, 1).map(this.rowBySlug, this).filter(Boolean).map(function (r) { return r.data; }); // the first pick is the reference
		this.unequal = {};
		this.rows.forEach(function (row) {
			var why = picks.length && self.picked.indexOf(row.data.slug) === -1 ? unequalReasons(row.data, picks, self.config.unequal) : [];
			if (why.length) self.unequal[row.data.slug] = why;
			row.tr.classList.toggle('is-unequal', why.length > 0);
			row.tr.classList.remove('is-locked');
			var cb = row.tr.querySelector('.j5ac-pick');
			cb.disabled = false;
			cb.title = why.length ? 'Not an equal comparison to your selected models. ' + why.join('. ') : '';
		});
		return null;
	};
	function sizeName(sz) { return sz.sub ? sz.sub + ' (' + sz.label + ')' : sz.label; }
	function unequalReasons(d, picks, th) {
		th = th || {};
		var sizeT = th.size !== undefined ? th.size : 0.2, wT = th.weight !== undefined ? th.weight : 0.3;
		var out = [], seen = {}, sz = sizeOf(d), w = num(d.specs.weight_lb);
		var add = function (k, t) { if (!seen[k]) { seen[k] = 1; out.push(t); } };
		picks.forEach(function (p) {
			var ps = sizeOf(p), pw = num(p.specs.weight_lb);
			if (sz && ps && sz.w && ps.w) {
				var a = sz.w * sz.h, b = ps.w * ps.h;
				if (Math.max(a, b) / Math.min(a, b) - 1 > sizeT) add('size', 'Size: ' + sizeName(sz) + ' vs your ' + sizeName(ps));
			}
			if (d.specs.level && p.specs.level && String(d.specs.level) !== String(p.specs.level)) add('level', 'Threat level: ' + d.specs.level + ' vs your ' + p.specs.level);
			if (d.specs.standard && p.specs.standard && String(d.specs.standard) !== String(p.specs.standard)) add('std', 'Standard: ' + d.specs.standard + ' vs your ' + p.specs.standard);
			if (w && pw && Math.abs(w - pw) / Math.min(w, pw) > wT) {
				add('wt', 'Weight: ' + fmt(w) + ' lb vs your ' + fmt(pw) + ' lb, ' + Math.round(Math.abs(w - pw) / pw * 100) + '% ' + (w < pw ? 'lighter' : 'heavier'));
			}
		});
		return out;
	}

	Panel.prototype.rowBySlug = function (slug) {
		for (var i = 0; i < this.rows.length; i++) if (this.rows[i].data.slug === slug) return this.rows[i];
		return null;
	};

	Panel.prototype.pick = function (slug, on, input) {
		var i = this.picked.indexOf(slug);
		if (on && i === -1) {
			if (this.picked.length >= MAX_PICK) {
				if (input) input.checked = false;
				this.hint.textContent = 'Up to ' + MAX_PICK + ' at a time. Uncheck one to add another.';
				if (this.root.j5acFlash) this.root.j5acFlash('Up to ' + MAX_PICK);
				return;
			}
			this.picked.push(slug);
		} else if (!on && i !== -1) {
			this.picked.splice(i, 1);
		}
		var row = this.rowBySlug(slug);
		if (row) row.tr.classList.toggle('is-picked', on);
		var key = this.lockSizes();
		this.renderCards();
		this.hint.textContent = this.picked.length
			? (this.picked.length < MAX_PICK ? 'Comparing ' + this.picked.length + '. Add up to ' + MAX_PICK + '.' : 'Comparing ' + MAX_PICK + '.')
			: 'Check up to ' + MAX_PICK + ' to compare side by side.';
		this.clearBtn.hidden = this.picked.length === 0;
		this.compare();
	};

	/**
	 * J5-AC-CMPGRID (2026-11-08): the comparison opens as a grid overlay from the sticky Compare
	 * button instead of a section under the results. Specs run down the side, picked models across.
	 * At 900px and under the same table restacks to one block per spec (no sideways scrolling).
	 */
	Panel.prototype.compareShell = function () {
		if (this.ov) return this.ov;
		var self = this, root = this.root;
		var ov = el('div', 'j5ac-cmpov'); ov.hidden = true;
		var box = el('div', 'j5ac-cmpov-box'); box.setAttribute('role', 'dialog'); box.setAttribute('aria-modal', 'true'); box.tabIndex = -1;
		var head = el('div', 'j5ac-cmpov-head');
		var h = el('h2', 'j5ac-cmpov-h', 'Compare ' + String(this.config.label || 'models').toLowerCase()); h.id = 'j5ac-cmpov-h-' + this.config.category;
		box.setAttribute('aria-labelledby', h.id);
		var tools = el('div', 'j5ac-cmpov-tools');
		var dl = el('label', 'j5ac-cmpov-diff'); var dcb = el('input'); dcb.type = 'checkbox'; dl.appendChild(dcb); dl.appendChild(el('span', null, 'Only rows that differ'));
		var key = el('span', 'j5ac-cmpov-key', '★ leads on that spec');
		var clr = el('button', 'j5ac-cmpov-clear', 'Clear all'); clr.type = 'button';
		var x = el('button', 'j5ac-cmpov-x'); x.type = 'button'; x.setAttribute('aria-label', 'Close compare'); x.textContent = '✕';
		tools.appendChild(dl); tools.appendChild(key); tools.appendChild(clr); tools.appendChild(x);
		head.appendChild(h); head.appendChild(tools);
		box.appendChild(head);
		box.appendChild(this.out);
		this.out.hidden = false;
		ov.appendChild(box);
		root.appendChild(ov);
		dcb.addEventListener('change', function () { self.out.classList.toggle('is-diffonly', dcb.checked); });
		clr.addEventListener('click', function () { self.closeCompare(); self.clearPicks(); });
		x.addEventListener('click', function () { self.closeCompare(); });
		ov.addEventListener('click', function (e) {
			if (e.target === ov) { self.closeCompare(); return; }
			var rm = e.target.closest && e.target.closest('[data-j5ac-cg-rm]');
			if (rm) { self.unpick(rm.getAttribute('data-j5ac-cg-rm')); return; }
			if (e.target.closest && e.target.closest('[data-j5ac-cg-add]')) self.closeCompare();
		});
		document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && !ov.hidden) self.closeCompare(); });
		this.ov = ov; this.ovBox = box;
		return ov;
	};
	Panel.prototype.openCompare = function () {
		if (!this.picked.length) return;
		this.compareShell();
		this.compare(false);
		this.ovReturn = document.activeElement;
		this.ov.hidden = false;
		document.documentElement.classList.add('j5ac-cmp-open');
		this.ovBox.focus({ preventScroll: true });
		if (this.root.j5acSync) this.root.j5acSync();
	};
	Panel.prototype.closeCompare = function () {
		if (!this.ov || this.ov.hidden) return;
		this.ov.hidden = true;
		document.documentElement.classList.remove('j5ac-cmp-open');
		if (this.root.j5acSync) this.root.j5acSync();
		var back = this.ovReturn; this.ovReturn = null;
		if (back && back.focus && document.body.contains(back)) back.focus({ preventScroll: true });
	};
	Panel.prototype.isComparing = function () { return !!(this.ov && !this.ov.hidden); };
	Panel.prototype.unpick = function (slug) {
		var r = this.rowBySlug(slug), cb = r && r.tr.querySelector('.j5ac-pick');
		if (cb) cb.checked = false;
		this.pick(slug, false, cb);
	};

	Panel.prototype.compare = function () {
		var self = this;
		var rows = this.picked.map(this.rowBySlug, this).filter(Boolean);
		if (this.root.j5acSync) this.root.j5acSync();
		if (!rows.length) { this.out.textContent = ''; this.closeCompare(); return; }
		if (!this.ov) return; // built the first time Compare is pressed
		this.out.textContent = '';
		var cfg = this.config, quote = this.root.getAttribute('data-quote') || '/request-a-quote/';

		var specRows = [];
		cfg.fields.forEach(function (f) {
			if (rows.some(function (r) { return r.data.specs && r.data.specs[f.key] !== undefined; })) specRows.push({ key: f.key, label: f.label, field: f, type: f.type });
		});
		if (rows.some(function (r) { return r.data.derived && r.data.derived.area_sqin !== undefined; }) && cfg.category !== 'helmet') specRows.push({ key: 'area_sqin', label: 'Coverage', type: 'number', unit: 'sq in', derived: true });
		if (cfg.eff) specRows.push({ key: 'oz_per_sqin', label: 'Oz / sq in', type: 'number', derived: true, places: 3 });
		if (cfg.sizes) specRows.push({ key: 'size_count', label: 'Sizes offered', type: 'number', derived: true, sizes: true });

		// Not the same thing: say so before the numbers get compared.
		if (rows.length > 1) {
			var levels = {}, standards = {}, types = {};
			rows.forEach(function (r) {
				if (r.data.specs.level) levels[r.data.specs.level] = 1;
				if (r.data.specs.standard) standards[r.data.specs.standard] = 1;
				if (r.data.specs.standalone) types[r.data.specs.standalone] = 1;
			});
			var parts = [];
			if (Object.keys(levels).length > 1) parts.push('different protection levels (' + Object.keys(levels).join(', ') + ')');
			if (Object.keys(standards).length > 1) parts.push('different test standards (' + Object.keys(standards).join(', ') + ')');
			if (Object.keys(types).length > 1) parts.push('standalone and in-conjunction plates');
			if (parts.length) {
				var warn = el('p', 'j5ac-mixed');
				warn.appendChild(el('strong', null, 'Not a like-for-like comparison. '));
				warn.appendChild(document.createTextNode('These have ' + parts.join(' and ') + '. A higher rating usually costs weight, so judge weight and coverage against the threat each one is built to stop.'));
				this.out.appendChild(warn);
			}
		}

		// Best per numeric row, only when there is a real spread.
		var best = {};
		specRows.forEach(function (sr) {
			if (sr.type !== 'number' || rows.length < 2) return;
			var nums = rows.map(function (r) { var x = sr.derived ? r.data.derived[sr.key] : r.data.specs[sr.key]; if (sr.sizes && (x === undefined || x === null)) x = 1; return num(x); }).filter(function (n) { return n !== null; });
			if (nums.length < 2 || Math.min.apply(null, nums) === Math.max.apply(null, nums)) return;
			if (LOWER_BETTER.test(sr.key)) best[sr.key] = Math.min.apply(null, nums);
			else if (HIGHER_BETTER.test(sr.key)) best[sr.key] = Math.max.apply(null, nums);
		});
		if (cfg.category === 'shield') { best.viewport_sqin = Math.max.apply(null, rows.map(function (r) { return viewportArea(r.data.specs.viewport); })); if (!(best.viewport_sqin > 0) || rows.length < 2) delete best.viewport_sqin; }
		this.ov.querySelector('.j5ac-cmpov-key').hidden = !(rows.length > 1 && Object.keys(best).length);

		var first = rows[0].data;
		function nameOf(d) { var csz = sizeOf(d); return famName(d) + (d.family && csz && csz.w ? ' ' + csz.label.replace(' in', '') : ''); }
		var t = el('table', 'j5ac-cg');
		t.style.setProperty('--n', rows.length);
		var cap = el('caption', 'j5ac-sr', 'Side by side specs for ' + rows.length + ' picked models'); t.appendChild(cap);
		var thead = el('thead'), htr = el('tr');
		htr.appendChild(el('td', 'j5ac-cg-corner', rows.length + ' of ' + MAX_PICK + ' picked'));
		rows.forEach(function (r, idx) {
			var d = r.data, th = el('th', 'j5ac-cg-plate'); th.scope = 'col';
			var rm = el('button', 'j5ac-cg-rm'); rm.type = 'button'; rm.setAttribute('data-j5ac-cg-rm', d.slug); rm.setAttribute('aria-label', 'Remove ' + d.manufacturer + ' ' + nameOf(d) + ' from compare'); rm.textContent = '✕';
			th.appendChild(rm);
			var nm = el('span', 'j5ac-cg-name', nameOf(d));
			if (d.carried) { var mk = el('span', 'j5ac-j5', 'J5'); mk.title = 'Available from J5'; nm.appendChild(document.createTextNode(' ')); nm.appendChild(mk); }
			th.appendChild(nm);
			th.appendChild(el('span', 'j5ac-cg-maker', d.manufacturer));
			if (idx > 0) {
				var why = unequalReasons(d, [first], cfg.unequal);
				if (why.length) { var u = el('span', 'j5ac-cg-uneq', 'Not an equal comparison'); u.title = why.join('\n'); th.appendChild(u); }
			}
			htr.appendChild(th);
		});
		var addCell = rows.length < MAX_PICK;
		if (addCell) { var at = el('td', 'j5ac-cg-addc'); var ab = el('button', 'j5ac-cg-add', '+ Add a ' + (cfg.category === 'plate' ? 'plate' : 'model')); ab.type = 'button'; ab.setAttribute('data-j5ac-cg-add', ''); at.appendChild(ab); htr.appendChild(at); }
		thead.appendChild(htr); t.appendChild(thead);

		var tb = el('tbody');
		function rowEl(label, cells, cls) {
			var tr = el('tr', cls || '');
			var th = el('th', null, label); th.scope = 'row'; tr.appendChild(th);
			cells.forEach(function (td) { tr.appendChild(td); });
			if (addCell) tr.appendChild(el('td', 'j5ac-cg-addc'));
			tb.appendChild(tr);
			return tr;
		}
		specRows.forEach(function (sr) {
			var texts = [];
			var cells = rows.map(function (r) {
				var v = sr.derived ? r.data.derived[sr.key] : r.data.specs[sr.key];
				var td = el('td'); td.setAttribute('data-m', nameOf(r.data));
				var text = sr.places !== undefined && typeof v === 'number' ? v.toFixed(sr.places) : fmt(v, sr.field);
				if (sr.sizes) { v = v || 1; text = String(v); }
				var box = el('span', 'j5ac-cg-v');
				if (text === null) box.appendChild(el('span', 'j5ac-na', 'not listed'));
				else if (sr.key === 'level') box.appendChild(el('span', 'j5ac-lv j5ac-lv--' + tier(v), text));
				else if (sr.key === 'size' && sizeOf(r.data) && sizeOf(r.data).w) { var szc = sizeOf(r.data); box.textContent = szc.label; if (szc.sub) box.appendChild(el('small', null, ' ' + szc.sub)); }
				else if (sr.sizes) { box.textContent = text; if (r.data.derived.size_list) box.appendChild(el('small', null, ' ' + r.data.derived.size_list)); }
				else box.textContent = sr.unit && typeof v === 'number' ? text + ' ' + sr.unit : text;
				if ((best[sr.key] !== undefined && num(v) === best[sr.key]) || (sr.key === 'viewport' && best.viewport_sqin !== undefined && viewportArea(v) === best.viewport_sqin)) td.classList.add('is-best');
				texts.push(box.textContent);
				td.appendChild(box);
				return td;
			});
			var same = texts.every(function (s) { return s === texts[0]; });
			rowEl(sr.label, cells, same ? 'is-same' : 'is-diff');
		});
		rowEl('Spec sheet', rows.map(function (r) {
			var td = el('td', 'j5ac-cg-src'); td.setAttribute('data-m', nameOf(r.data));
			if (r.data.sheet && r.data.sheet.url) {
				var a = el('a', null, r.data.manufacturer + ' sheet'); a.href = r.data.sheet.url; a.target = '_blank'; a.rel = 'noopener';
				td.appendChild(a);
				if (r.data.sheet.pulled_at) td.appendChild(el('small', null, ' · pulled ' + r.data.sheet.pulled_at));
			} else td.appendChild(el('span', 'j5ac-na', 'not on file'));
			return td;
		}), 'j5ac-cg-meta');
		rowEl('Buy', rows.map(function (r) {
			var td = el('td', 'j5ac-cg-buy'); td.setAttribute('data-m', nameOf(r.data));
			// Only what we carry gets a button: a store page if it has one, otherwise a quote.
			if (r.data.wc) { var buy = el('a', 'j5ac-btn j5ac-btn--buy', 'View product'); buy.href = r.data.wc; td.appendChild(buy); }
			else if (r.data.carried) {
				var q = el('a', 'j5ac-btn j5ac-btn--quote', 'Request a quote');
				q.href = quote + (quote.indexOf('?') === -1 ? '?' : '&') + 'item=' + encodeURIComponent(r.data.manufacturer + ' ' + r.data.model) + '&ref=armor-compare';
				td.appendChild(q);
			} else td.appendChild(el('span', 'j5ac-cg-ref', 'Reference only'));
			var crep = el('button', 'j5ac-report', 'Report an error'); crep.type = 'button';
			crep.addEventListener('click', function () { openReport(self.root, cfg.category, r.data); });
			td.appendChild(crep);
			return td;
		}), 'j5ac-cg-meta');
		t.appendChild(tb);
		this.out.appendChild(t);
	};

	/** Sticky buttons: Compare (when anything is picked), Top and Reset all (once scrolled). */
	function floatingBar(root, panels) {
		var bar = el('div', 'j5ac-float');
		var cmp = el('button', 'j5ac-float-btn j5ac-float-btn--cmp'); cmp.type = 'button'; cmp.hidden = true;
		var cmpT = el('span', 'j5ac-float-t', 'Compare'), cmpN = el('b', 'j5ac-float-n', '0');
		cmp.appendChild(cmpT); cmp.appendChild(cmpN);
		var top = el('button', 'j5ac-float-btn j5ac-float-btn--top', 'Top'); top.type = 'button'; top.setAttribute('aria-label', 'Back to the top of the comparison tool');
		var reset = el('button', 'j5ac-float-btn j5ac-float-btn--reset', 'Reset all'); reset.type = 'button'; reset.setAttribute('aria-label', 'Clear all selections and filters');
		bar.appendChild(cmp); bar.appendChild(top); bar.appendChild(reset);
		root.appendChild(bar);
		function active() { var k = Object.keys(panels).filter(function (c) { return !panels[c].section.hidden; })[0]; return k ? panels[k] : null; }
		cmp.addEventListener('click', function () {
			var p = active(); if (!p) return;
			if (p.isComparing()) p.closeCompare(); else p.openCompare();
		});
		top.addEventListener('click', function () { root.scrollIntoView({ behavior: reduced() ? 'auto' : 'smooth', block: 'start' }); });
		reset.addEventListener('click', function () {
			Object.keys(panels).forEach(function (k) { var p = panels[k]; p.closeCompare(); p.clearPicks(); p.form.reset(); p.sizeTarget = null; p.sizeSel = {}; p.helpOpen = false; p.renderGuide(); p.filter(); });
			root.scrollIntoView({ behavior: reduced() ? 'auto' : 'smooth', block: 'start' });
		});
		var flashT = 0;
		root.j5acFlash = function (msg) {
			cmpT.textContent = msg; cmp.classList.add('is-flash');
			clearTimeout(flashT); flashT = setTimeout(function () { cmp.classList.remove('is-flash'); tick(); }, 2200);
		};
		function tick() {
			var p = active(), n = p ? p.picked.length : 0, open = !!(p && p.isComparing());
			var r = root.getBoundingClientRect();
			var scrolled = r.top < -240 && r.bottom > 160;
			cmp.hidden = !n && !open;
			if (!cmp.classList.contains('is-flash')) cmpT.textContent = open ? 'Exit compare' : 'Compare';
			cmpN.textContent = String(n); cmpN.hidden = open;
			cmp.classList.toggle('is-exit', open);
			cmp.setAttribute('aria-label', open ? 'Exit compare' : 'Compare ' + n + ' picked');
			top.hidden = open || !scrolled;
			reset.hidden = open || !scrolled;
			bar.classList.toggle('is-open', open);
			bar.classList.toggle('is-on', open || n > 0 || scrolled);
		}
		root.j5acSync = tick;
		window.addEventListener('scroll', tick, { passive: true });
		window.addEventListener('resize', tick);
		tick();
	}


	function init(root) {
		var panels = {};
		Array.prototype.forEach.call(root.querySelectorAll('.j5ac-panel'), function (s) { panels[s.getAttribute('data-cat')] = new Panel(s, root); });
		bindReport(root);
		var links = root.querySelectorAll('.j5ac-cat[data-cat]');
		function show(cat) {
			Array.prototype.forEach.call(root.querySelectorAll('.j5ac-panel'), function (s) { s.hidden = s.getAttribute('data-cat') !== cat; });
			Array.prototype.forEach.call(links, function (a) {
				var on = a.getAttribute('data-cat') === cat;
				a.classList.toggle('is-on', on);
				if (on) a.setAttribute('aria-current', 'true'); else a.removeAttribute('aria-current');
			});
		}
		Array.prototype.forEach.call(links, function (a) {
			a.addEventListener('click', function (e) {
				e.preventDefault();
				var cat = a.getAttribute('data-cat');
				show(cat);
				if (panels[cat]) panels[cat].writeHash();
			});
		});
		var hash = (location.hash || '').replace('#j5ac-', '');
		if (hash) {
			var segs = hash.split('/'), cat = segs.shift();
			if (panels[cat]) show(cat);
		}
		floatingBar(root, panels);
	}

	function boot() { Array.prototype.forEach.call(document.querySelectorAll('[data-j5ac]'), init); }
	if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot); else boot();
})();

/* J5-AC-GUIDE (2026-11-08): quick start band hide/show (remembered per visitor) and the
   "On this page" highlight in the guide below the tool. Markup is page content. */
(function () {
	'use strict';
	var KEY = 'j5ac_qs_closed';
	function store(v) { try { if (v) localStorage.setItem(KEY, '1'); else localStorage.removeItem(KEY); } catch (e) {} }
	function stored() { try { return localStorage.getItem(KEY) === '1'; } catch (e) { return false; } }

	function band(el) {
		var btn = el.querySelector('[data-j5ac-qs-toggle]');
		var body = el.querySelector('.j5ac-qs-body');
		if (!btn || !body) return;
		if (!body.id) body.id = 'j5ac-qs-body';
		btn.setAttribute('aria-controls', body.id);
		function set(closed, save) {
			el.classList.toggle('is-closed', closed);
			btn.setAttribute('aria-expanded', closed ? 'false' : 'true');
			btn.textContent = closed ? 'Show' : 'Hide';
			if (save) store(closed);
		}
		set(stored(), false);
		btn.hidden = false;
		btn.addEventListener('click', function () { set(!el.classList.contains('is-closed'), true); });
		// "Full guide" link inside the band, or any link to the guide, opens nothing extra: plain anchors.
	}

	function nav(gd) {
		var links = gd.querySelectorAll('.j5ac-gd-nav a[href^="#"]');
		if (!links.length || !('IntersectionObserver' in window)) return;
		var map = {};
		Array.prototype.forEach.call(links, function (a) { var t = document.getElementById(a.getAttribute('href').slice(1)); if (t) map[t.id] = a; });
		var io = new IntersectionObserver(function (entries) {
			entries.forEach(function (en) {
				if (!en.isIntersecting) return;
				Array.prototype.forEach.call(links, function (a) { a.classList.toggle('is-on', a === map[en.target.id]); });
			});
		}, { rootMargin: '-20% 0px -60% 0px' });
		Object.keys(map).forEach(function (id) { io.observe(document.getElementById(id)); });
	}

	function boot() {
		Array.prototype.forEach.call(document.querySelectorAll('[data-j5ac-qs]'), band);
		Array.prototype.forEach.call(document.querySelectorAll('[data-j5ac-gd]'), nav);
	}
	if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot); else boot();
})();
