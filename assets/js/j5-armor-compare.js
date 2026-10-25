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
		shield: ['threat', 'type', 'size'],
		plate:  ['threat', 'nij', 'size'],
		helmet: ['threat', 'cut'],
		soft_armor: ['threat', 'size']
	};
	var STEP_Q = { threat: 'What does it need to stop?', type: 'What kind of shield?', nij: 'NIJ certified?', size: 'What size?', cut: 'What cut?' };
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
		if (step === 'nij') return { label: val === 'yes' ? 'NIJ certified' : 'Not certified', sub: val === 'yes' ? 'On the NIJ Compliant Products List' : 'May be tested to a standard, but it is not on the NIJ CPL' };
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
			return { tr: tr, data: JSON.parse(tr.getAttribute('data-row')) };
		});
		this.form = section.querySelector('[data-j5ac-filters]');
		this.count = section.querySelector('[data-j5ac-count]');
		this.none = section.querySelector('[data-j5ac-none]');
		this.hint = section.querySelector('[data-j5ac-hint]');
		this.clearBtn = section.querySelector('[data-j5ac-clear]');
		this.out = section.querySelector('[data-j5ac-compare-out]');
		this.picked = [];
		this.guide = {};
		this.guideOn = true;
		this.steps = STEPS[this.config.category] || ['threat'];
		this.guideBox = section.querySelector('[data-j5ac-guide]');
		this.listWrap = section.querySelector('.j5ac-table-wrap');
		this.bind();
		this.renderGuide();
		this.ready = true;
	}

	/** Rows that satisfy every answered guide step. */
	Panel.prototype.guideRows = function () {
		var g = this.guide;
		return this.rows.filter(function (r) {
			for (var k in g) if (g[k] !== undefined && g[k] !== '*' && stepValue(r.data, k) !== g[k]) return false;
			return true;
		});
	};

	Panel.prototype.nextStep = function () {
		for (var i = 0; i < this.steps.length; i++) if (this.guide[this.steps[i]] === undefined) return this.steps[i];
		return null;
	};

	Panel.prototype.renderGuide = function () {
		var self = this, box = this.guideBox;
		box.textContent = '';
		if (!this.guideOn) { box.hidden = true; this.form.hidden = false; this.applyGuide(); return; }
		box.hidden = false;

		// Breadcrumb of answers so far
		var crumbs = el('div', 'j5ac-crumbs');
		this.steps.forEach(function (st) {
			if (self.guide[st] === undefined || self.guide[st] === '*') return;
			var sample = self.guideRows()[0];
			var lab = sample ? stepLabel(sample.data, st, self.guide[st]) : { label: self.guide[st] };
			var b = el('button', 'j5ac-crumb', (st === 'threat' || st === 'size') && lab.sub ? lab.label + ' ' + lab.sub : lab.label);
			b.type = 'button'; b.title = 'Change';
			b.addEventListener('click', function () { self.backTo(st); });
			crumbs.appendChild(b);
		});
		var all = el('button', 'j5ac-seeall', this.guide.threat === undefined ? 'See all ' + this.config.label.toLowerCase() : 'See all');
		all.type = 'button';
		all.addEventListener('click', function () { self.seeAll(); });
		crumbs.appendChild(all);
		box.appendChild(crumbs);

		var step = this.nextStep();
		var pool = this.guideRows();

		// Progress: which step we are on, out of the steps this category actually asks.
		var asked = this.steps.filter(function (st) { return this.guide[st] !== '*'; }, this);
		var done = asked.filter(function (st) { return this.guide[st] !== undefined; }, this).length;
		var prog = el('div', 'j5ac-prog');
		prog.appendChild(el('span', null, step ? 'Step ' + (done + 1) + ' of ' + asked.length : 'Done, ' + asked.length + ' of ' + asked.length));
		var bar = el('div', 'j5ac-prog-bar');
		asked.forEach(function (st, i) { var seg = el('i'); if (i < done || !step) seg.className = 'is-on'; else if (i === done) seg.className = 'is-now'; bar.appendChild(seg); });
		prog.appendChild(bar);
		box.appendChild(prog);

		// Skip a step the data can't answer yet (no model in the pool carries the field).
		while (step && !pool.some(function (r) { var v = stepValue(r.data, step); return v !== '' && v !== null; })) {
			this.guide[step] = '*';
			step = this.nextStep();
		}
		if (step) {
			var q = el('h3', 'j5ac-q', STEP_Q[step] || step);
			box.appendChild(q);
			var groups = {}, order = [];
			pool.forEach(function (r) {
				var v = stepValue(r.data, step);
				if (v === null) return;
				if (!groups[v]) { groups[v] = { n: 0, sample: r.data }; order.push(v); }
				groups[v].n++;
			});
			if (step === 'threat') order.sort(function (a, b) { return THREATS.map(function (t) { return t.id; }).indexOf(a) - THREATS.map(function (t) { return t.id; }).indexOf(b); });
			else if (step === 'size') order.sort(function (a, b) { var A = sizeOf(groups[a].sample), B = sizeOf(groups[b].sample); return (A.w * A.h) - (B.w * B.h) || A.w - B.w; });
			else if (step === 'nij') order.sort(function (a, b) { return (a === 'yes' ? 0 : 1) - (b === 'yes' ? 0 : 1); });
			else order.sort(function (a, b) { return (a === '') - (b === '') || a.localeCompare(b); });
			var tiles = el('div', 'j5ac-tiles');
			order.forEach(function (v) {
				var g = groups[v], lab = stepLabel(g.sample, step, v);
				var t = el('button', 'j5ac-tile'); t.type = 'button';
				// Threat tiles: small word on top (Handgun / Rifle), level as the main line.
				// Other steps: small qualifier on top when there is one (SAPI Medium), value as the main line.
				var top = step === 'threat' ? lab.label : lab.sub, main = step === 'threat' ? lab.sub : lab.label;
				t.appendChild(el('span', 'j5ac-tile-k', top || '\u00a0'));
				t.appendChild(el('span', 'j5ac-tile-l', main));
				t.appendChild(el('span', 'j5ac-tile-n', g.n + (g.n === 1 ? ' model' : ' models')));
				t.addEventListener('click', function () { self.answer(step, v); });
				tiles.appendChild(t);
			});
			box.appendChild(tiles);
			this.listWrap.hidden = true;
			this.form.hidden = true;
			this.hint.parentNode.hidden = true;
		} else {
			// All answered: show the matched list, compare-ready.
			var n = pool.length;
			box.appendChild(el('p', 'j5ac-guide-done', n + (n === 1 ? ' model matches. ' : ' models match. ') + 'Check any to compare side by side.'));
			this.listWrap.hidden = false;
			this.form.hidden = true;
			this.hint.parentNode.hidden = false;
			this.applyGuide();
		}
		this.writeHash();
	};

	Panel.prototype.startOver = function () {
		this.guide = {}; this.guideOn = true;
		this.form.reset(); this.clearPicks(); this.renderGuide(); this.filter();
		this.section.scrollIntoView({ behavior: reduced() ? 'auto' : 'smooth', block: 'start' });
	};
	Panel.prototype.answer = function (step, v) { this.guide[step] = v; this.clearPicks(); this.renderGuide(); };
	Panel.prototype.backTo = function (step) {
		var i = this.steps.indexOf(step);
		for (var j = i; j < this.steps.length; j++) delete this.guide[this.steps[j]];
		this.clearPicks(); this.renderGuide();
	};
	Panel.prototype.seeAll = function () {
		// Carry the threat answer into the level chips, drop the rest, show the full list and filters.
		var threat = this.guide.threat;
		this.guideOn = false; this.guide = {};
		this.form.reset();
		if (threat && threat !== '*') {
			var levels = THREATS.filter(function (t) { return t.id === threat; })[0].levels;
			Array.prototype.forEach.call(this.form.querySelectorAll('input[name=level]'), function (cb) { cb.checked = levels.indexOf(cb.value) !== -1; });
		}
		this.listWrap.hidden = false; this.hint.parentNode.hidden = false;
		this.clearPicks();
		this.renderGuide();
		var back = el('button', 'j5ac-seeall j5ac-seeall--back', 'Back to guided search'); back.type = 'button';
		var self = this;
		back.addEventListener('click', function () { self.guideOn = true; self.form.reset(); self.clearPicks(); self.renderGuide(); self.filter(); });
		this.guideBox.textContent = ''; this.guideBox.hidden = false; this.guideBox.appendChild(back);
		this.filter();
	};
	Panel.prototype.applyGuide = function () { this.filter(); this.sort(this.form.elements.sort.value); };
	Panel.prototype.writeHash = function () {
		if (!history.replaceState || !this.ready || this.section.hidden) return;
		var parts = ['j5ac-' + this.config.category];
		this.steps.forEach(function (st) { if (this.guide[st] !== undefined) parts.push(encodeURIComponent(this.guide[st] === '*' ? '-' : this.guide[st])); }, this);
		history.replaceState(null, '', '#' + parts.join('/'));
	};
	Panel.prototype.readHash = function (segs) {
		var self = this;
		segs.forEach(function (seg, i) { if (self.steps[i] !== undefined && seg !== '') self.guide[self.steps[i]] = seg === '-' ? '*' : decodeURIComponent(seg); });
		this.renderGuide();
	};

	Panel.prototype.value = function (row, key) {
		if (key === 'model') return row.data.model.toLowerCase();
		if (key === 'maker') return (row.data.manufacturer + ' ' + row.data.model).toLowerCase();
		if (row.data.derived && key in row.data.derived) return row.data.derived[key];
		return row.data.specs ? row.data.specs[key] : undefined;
	};

	Panel.prototype.bind = function () {
		var self = this;
		this.desc = false;
		this.dirBtn = this.form.querySelector('[data-j5ac-dir]');
		this.form.addEventListener('input', function (e) {
			if (e.target.type === 'range') self.syncOutput(e.target);
			if (e.target.name === 'sort') self.sort(e.target.value); else self.filter();
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
		this.compare(false);
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
		var lists = {}, maxes = {}, onlys = [], has = [], q = '', maker = '', carriedOnly = false;
		Array.prototype.forEach.call(this.form.elements, function (inp) {
			if (!inp.name || inp.name === 'sort') return;
			if (inp.name === 'q') { q = inp.value.trim().toLowerCase(); return; }
			if (inp.name === 'maker') { maker = inp.value; return; }
			if (inp.name === 'carried') { carriedOnly = inp.checked; return; }
			if (inp.type === 'checkbox' && inp.name.indexOf('only:') === 0) { if (inp.checked) onlys.push(inp.name.slice(5)); }
			else if (inp.type === 'checkbox' && inp.name.indexOf('has:') === 0) { if (inp.checked) has.push(inp.name.slice(4)); }
			else if (inp.type === 'checkbox') { if (inp.checked) (lists[inp.name] = lists[inp.name] || []).push(inp.value); }
			else if (inp.type === 'range') { if (parseFloat(inp.value) < parseFloat(inp.max)) maxes[inp.name.slice(4)] = parseFloat(inp.value); }
		});
		var shown = 0, g = this.guideOn ? this.guide : {};
		this.rows.forEach(function (row) {
			var ok = true, k, i, v;
			for (k in g) if (g[k] !== undefined && g[k] !== '*' && stepValue(row.data, k) !== g[k]) { ok = false; break; }
			if (q) { var hay = (row.data.manufacturer + ' ' + row.data.model + ' ' + (row.data.family || '')).toLowerCase(); if (hay.indexOf(q) === -1) ok = false; }
			if (ok && maker && row.data.manufacturer !== maker) ok = false;
			if (ok && carriedOnly && !row.data.carried) ok = false;
			if (ok) for (k in lists) { if (lists[k].indexOf(String(this.value(row, k))) === -1) { ok = false; break; } }
			if (ok) for (k in maxes) { var n = num(this.value(row, k)); if (n === null || n > maxes[k]) { ok = false; break; } }
			if (ok) for (i = 0; i < onlys.length; i++) { v = this.value(row, onlys[i]); if (!v || v === 'No' || v === 'ICW') { ok = false; break; } }
			if (ok) for (i = 0; i < has.length; i++) { v = this.value(row, has[i]); if (v === undefined || v === null || v === '' || v === false) { ok = false; break; } }
			row.tr.classList.toggle('is-hidden', !ok);
			if (ok) shown++;
		}, this);
		this.count.textContent = shown + ' of ' + this.rows.length;
		this.none.hidden = shown > 0;
	};

	Panel.prototype.sort = function (key) {
		var self = this, numeric = key !== 'model' && key !== 'maker', sign = this.desc ? -1 : 1;
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
	};

	Panel.prototype.lockSizes = function () {
		var first = this.picked.length ? this.rowBySlug(this.picked[0]) : null;
		var key = first ? sizeKey(first.data) : null;
		this.rows.forEach(function (row) {
			var lock = key !== null && sizeKey(row.data) !== key;
			row.tr.classList.toggle('is-locked', lock);
			var cb = row.tr.querySelector('.j5ac-pick');
			cb.disabled = lock;
			cb.title = lock ? 'Different size from your first pick. Clear the selection to compare this one.' : '';
		});
		return key;
	};

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
				return;
			}
			this.picked.push(slug);
		} else if (!on && i !== -1) {
			this.picked.splice(i, 1);
		}
		var row = this.rowBySlug(slug);
		if (row) row.tr.classList.toggle('is-picked', on);
		var key = this.lockSizes();
		var first = this.picked.length ? this.rowBySlug(this.picked[0]).data : null;
		var fsz = first ? sizeOf(first) : null;
		var sizeText = fsz ? fsz.label + (fsz.sub ? ', ' + fsz.sub : '') : '';
		this.hint.textContent = this.picked.length
			? (this.picked.length < MAX_PICK ? 'Comparing ' + this.picked.length + '. Check up to ' + MAX_PICK + (sizeText ? ', same size only (' + sizeText + ').' : '.') : 'Comparing ' + MAX_PICK + '.')
			: 'Check up to ' + MAX_PICK + ' to compare side by side.';
		this.clearBtn.hidden = this.picked.length === 0;
		this.compare(on && this.picked.length === 1);
	};

	Panel.prototype.compare = function (scroll) {
		var rows = this.picked.map(this.rowBySlug, this).filter(Boolean);
		this.out.textContent = '';
		if (!rows.length) { this.out.hidden = true; return; }
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
		if (rows.length > 1 && Object.keys(best).length) {
			var key = el('p', 'j5ac-scorenote');
			key.textContent = 'Gold marks where a model leads on a listed spec: lightest, most coverage, fewest ounces per square inch' + (cfg.category === 'shield' ? ', largest viewport' : '') + (cfg.sizes ? ', most sizes' : '') + '. No overall winner is picked.';
			this.out.appendChild(key);
		}
		var grid = el('div', 'j5ac-cards');
		rows.forEach(function (r, idx) {
			var card = el('div', 'j5ac-card');

			card.appendChild(el('span', 'j5ac-model', r.data.model));
			card.appendChild(el('span', 'j5ac-maker', r.data.manufacturer));

			var leads = 0;
			var t = el('table'), tb = el('tbody');
			specRows.forEach(function (sr) {
				var v = sr.derived ? r.data.derived[sr.key] : r.data.specs[sr.key];
				var tr = el('tr');
				tr.appendChild(el('td', null, sr.label));
				var td = el('td');
				var text = sr.places !== undefined && typeof v === 'number' ? v.toFixed(sr.places) : fmt(v, sr.field);
				if (sr.sizes) { v = v || 1; text = String(v); }
				if (text === null) td.appendChild(el('span', 'j5ac-na', 'not listed'));
				else if (sr.key === 'level') td.appendChild(el('span', 'j5ac-lv j5ac-lv--' + tier(v), text));
				else if (sr.key === 'size' && sizeOf(r.data) && sizeOf(r.data).w) { var szc = sizeOf(r.data); td.textContent = szc.label; if (szc.sub) td.appendChild(el('small', null, ' ' + szc.sub)); }
				else if (sr.sizes) { td.textContent = text; if (r.data.derived.size_list) { td.appendChild(el('small', null, ' ' + r.data.derived.size_list)); } }
				else td.textContent = sr.unit && typeof v === 'number' ? text + ' ' + sr.unit : text;
				if ((best[sr.key] !== undefined && num(v) === best[sr.key]) || (sr.key === 'viewport' && best.viewport_sqin !== undefined && viewportArea(v) === best.viewport_sqin)) { td.classList.add('is-best'); leads++; }
				tr.appendChild(td);
				tb.appendChild(tr);
			});
			t.appendChild(tb);
			if (leads) card.insertBefore(el('p', 'j5ac-card-best', 'Leads on ' + leads + (leads === 1 ? ' spec' : ' specs')), card.firstChild);
			card.appendChild(t);

			var src = el('p', 'j5ac-card-src');
			if (r.data.sheet && r.data.sheet.url) {
				src.appendChild(document.createTextNode('Source: '));
				var a = el('a', null, r.data.manufacturer + ' spec sheet');
				a.href = r.data.sheet.url; a.target = '_blank'; a.rel = 'noopener';
				src.appendChild(a);
				if (r.data.sheet.pulled_at) src.appendChild(document.createTextNode(', pulled ' + r.data.sheet.pulled_at));
			} else src.textContent = 'Source: spec sheet not on file';
			card.appendChild(src);

			// Only what we carry gets a button: a store page if it has one, otherwise a quote.
			if (r.data.wc) { var buy = el('a', 'j5ac-btn j5ac-btn--buy', 'View product'); buy.href = r.data.wc; card.appendChild(buy); }
			else if (r.data.carried) {
				var q = el('a', 'j5ac-btn j5ac-btn--quote', 'Request a quote');
				q.href = quote + (quote.indexOf('?') === -1 ? '?' : '&') + 'item=' + encodeURIComponent(r.data.manufacturer + ' ' + r.data.model) + '&ref=armor-compare';
				card.appendChild(q);
			} else card.appendChild(el('p', 'j5ac-card-nocarry', 'Reference only. Not a J5 product.'));
			if (r.data.carried) card.classList.add('is-carried');
			grid.appendChild(card);
		}, this);
		this.out.appendChild(grid);
		this.out.hidden = false;
		if (scroll) this.out.scrollIntoView({ behavior: reduced() ? 'auto' : 'smooth', block: 'nearest' });
	};

	function floatingBar(root, panels) {
		var bar = el('div', 'j5ac-float');
		var top = el('button', 'j5ac-float-btn j5ac-float-btn--top', 'Top'); top.type = 'button'; top.setAttribute('aria-label', 'Back to the top of the comparison tool');
		var over = el('button', 'j5ac-float-btn j5ac-float-btn--over', 'Start over'); over.type = 'button'; over.setAttribute('aria-label', 'Back to the first question on this tab');
		var reset = el('button', 'j5ac-float-btn j5ac-float-btn--reset', 'Reset all'); reset.type = 'button'; reset.setAttribute('aria-label', 'Clear all selections and filters');
		bar.appendChild(top); bar.appendChild(over); bar.appendChild(reset);
		over.addEventListener('click', function () {
			var open = root.querySelector('.j5ac-panel:not([hidden])');
			if (open && panels[open.getAttribute('data-cat')]) panels[open.getAttribute('data-cat')].startOver();
		});
		root.appendChild(bar);
		top.addEventListener('click', function () { root.scrollIntoView({ behavior: reduced() ? 'auto' : 'smooth', block: 'start' }); });
		reset.addEventListener('click', function () {
			Object.keys(panels).forEach(function (k) { var p = panels[k]; p.clearPicks(); p.form.reset(); p.guide = {}; p.guideOn = true; p.renderGuide(); p.filter(); });
			root.scrollIntoView({ behavior: reduced() ? 'auto' : 'smooth', block: 'start' });
		});
		function tick() {
			var r = root.getBoundingClientRect();
			var show = r.top < -240 && r.bottom > 160;
			bar.classList.toggle('is-on', show);
		}
		window.addEventListener('scroll', tick, { passive: true });
		window.addEventListener('resize', tick);
		tick();
	}

	function init(root) {
		var panels = {};
		Array.prototype.forEach.call(root.querySelectorAll('.j5ac-panel'), function (s) { panels[s.getAttribute('data-cat')] = new Panel(s, root); });
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
			if (panels[cat]) { show(cat); if (segs.length) panels[cat].readHash(segs); }
		}
		floatingBar(root, panels);
	}

	function boot() { Array.prototype.forEach.call(document.querySelectorAll('[data-j5ac]'), init); }
	if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot); else boot();
})();
