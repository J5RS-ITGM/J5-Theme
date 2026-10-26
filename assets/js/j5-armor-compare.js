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
	/** Steel plates (AR500 and the like) are hidden unless the viewer opts in. */
	function isSteel(d) {
		var hay = [d.specs && d.specs.material, d.model, d.family].join(' ');
		return /\bsteel\b|\bar\s?-?(500|550|600|650)\b/i.test(hay);
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
			return { tr: tr, data: JSON.parse(tr.getAttribute('data-row')) };
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
		this.guideOn = true;
		this.steps = STEPS[this.config.category] || ['threat'];
		this.guideBox = section.querySelector('[data-j5ac-guide]');
		this.listWrap = section.querySelector('.j5ac-table-wrap');
		this.bind();
		this.renderGuide();
		this.ready = true;
	}

	/** Rows that satisfy every answered guide step. */
	Panel.prototype.steelOn = function () { var cb = this.form.elements.steel; return !!(cb && cb.checked); };
	Panel.prototype.guideRows = function () {
		var g = this.guide, steel = this.steelOn();
		return this.rows.filter(function (r) {
			if (!steel && isSteel(r.data)) return false;
			for (var k in g) if (g[k] !== undefined && g[k] !== '*' && g[k] !== ANY && stepValue(r.data, k) !== g[k]) return false;
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

		if (this.form.elements.steel) {
			var steelLab = el('label', 'j5ac-steel');
			var steelCb = el('input'); steelCb.type = 'checkbox'; steelCb.checked = this.steelOn();
			steelCb.addEventListener('change', function () { self.form.elements.steel.checked = steelCb.checked; self.clearPicks(); self.renderGuide(); self.filter(); });
			steelLab.appendChild(steelCb); steelLab.appendChild(el('span', null, 'Include steel plates'));
			crumbs.insertBefore(steelLab, all);
		}

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
			else if (step === 'type') {
				var to = (self.config.type_order || []).map(function (x) { return String(x).toLowerCase(); });
				order.sort(function (a, b) { var ia = to.indexOf(a.toLowerCase()), ib = to.indexOf(b.toLowerCase()); if (ia === -1) ia = 999; if (ib === -1) ib = 999; return ia - ib || a.localeCompare(b); });
			}
			else order.sort(function (a, b) { return (a === '') - (b === '') || a.localeCompare(b); });
			var tiles = el('div', 'j5ac-tiles');
			function tile(v) {
				var g = groups[v], lab = stepLabel(g.sample, step, v);
				var t = el('button', 'j5ac-tile'); t.type = 'button';
				var top, main;
				if (step === 'threat') { top = lab.label; main = lab.sub; }
				else if (step === 'size') { var sz = sizeOf(g.sample), grp = sizeGroup(sz); if (grp.id === 'sapi') { top = lab.label; main = lab.sub; } else { top = ''; main = lab.label; } }
				else { top = lab.sub; main = lab.label; }
				t.appendChild(el('span', 'j5ac-tile-k', top || '\u00a0'));
				t.appendChild(el('span', 'j5ac-tile-l', main));
				t.appendChild(el('span', 'j5ac-tile-n', g.n + (g.n === 1 ? ' model' : ' models')));
				t.addEventListener('click', function () { self.answer(step, v); });
				return t;
			}
			if (step === 'size') {
				// Sections: side plates, shooter's cut, SAPI, each smallest to largest. Order and visibility from J5 Apps > Armor Comparison.
				var sgs = self.config.size_groups || { order: [], hidden: [] }, sgl = self.config.size_group_labels || {};
				var sections = {};
				order.forEach(function (v) { var grp = sizeGroup(sizeOf(groups[v].sample)); if ((sgs.hidden || []).indexOf(grp.id) !== -1) return; (sections[grp.id] = sections[grp.id] || { grp: grp, keys: [] }).keys.push(v); });
				var so = sgs.order && sgs.order.length ? sgs.order : [];
				Object.keys(sections).sort(function (a, b) { var ia = so.indexOf(a), ib = so.indexOf(b); if (ia === -1) ia = 50 + sections[a].grp.order; if (ib === -1) ib = 50 + sections[b].grp.order; return ia - ib; }).forEach(function (id) {
					var sec = sections[id];
					tiles.appendChild(el('h4', 'j5ac-tiles-h', sgl[id] || sec.grp.label));
					var row = el('div', 'j5ac-tiles-row');
					sec.keys.forEach(function (v) { row.appendChild(tile(v)); });
					tiles.appendChild(row);
				});
				tiles.classList.add('j5ac-tiles--grouped');
			} else {
				order.forEach(function (v) { tiles.appendChild(tile(v)); });
			}
			if (step === 'nij' && order.length > 1) {
				var both = el('button', 'j5ac-tile j5ac-tile--any'); both.type = 'button';
				both.appendChild(el('span', 'j5ac-tile-k', 'Certified and not'));
				both.appendChild(el('span', 'j5ac-tile-l', 'Show both'));
				both.appendChild(el('span', 'j5ac-tile-n', pool.length + ' models'));
				both.addEventListener('click', function () { self.answer(step, ANY); });
				tiles.appendChild(both);
			}
			box.appendChild(tiles);
			this.listWrap.hidden = true;
			if (this.cardList) this.cardList.hidden = true;
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

	/** Back one question: drop the last answered step (or leave See all mode). */
	Panel.prototype.backStep = function () {
		if (!this.guideOn) { this.guideOn = true; this.form.reset(); this.clearPicks(); this.renderGuide(); this.filter(); }
		else {
			var answered = this.steps.filter(function (st) { return this.guide[st] !== undefined && this.guide[st] !== '*'; }, this);
			if (!answered.length) return;
			this.backTo(answered[answered.length - 1]);
		}
		this.section.scrollIntoView({ behavior: reduced() ? 'auto' : 'smooth', block: 'start' });
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
		// re-evaluate skipped steps before it too, since the pool changes
		for (var k = 0; k < i; k++) if (this.guide[this.steps[k]] === '*') delete this.guide[this.steps[k]];
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
		this.renderCards();
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
		var lists = {}, maxes = {}, onlys = [], has = [], q = '', maker = '', carriedOnly = false, steelOn = this.steelOn();
		Array.prototype.forEach.call(this.form.elements, function (inp) {
			if (!inp.name || inp.name === 'sort' || inp.name === 'steel') return;
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
			for (k in g) if (g[k] !== undefined && g[k] !== '*' && g[k] !== ANY && stepValue(row.data, k) !== g[k]) { ok = false; break; }
			if (!steelOn && isSteel(row.data)) ok = false;
			if (ok && q) { var hay = (row.data.manufacturer + ' ' + row.data.model + ' ' + (row.data.family || '')).toLowerCase(); if (hay.indexOf(q) === -1) ok = false; }
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
		this.renderCards();
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

	Panel.prototype.renderCards = function () {
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
				|| g.items.filter(function (i) { return i.d.slug === sel[g.key]; })[0] || g.items[0];
			var d = cur.d, tr = cur.tr;
			var card = el('article', 'j5ac-pcard' + (self.picked.indexOf(d.slug) !== -1 ? ' is-picked' : '') + (d.carried ? ' is-carried' : '') + (tr.classList.contains('is-locked') ? ' is-locked' : ''));
			card.setAttribute('data-slug', d.slug);

			var fig = el('div', 'j5ac-pcard-img');
			var ph = '<svg viewBox="0 0 64 80" width="56" height="70" aria-hidden="true"><path d="M8 6h48v44c0 14-10 22-24 26C18 72 8 64 8 50V6z" fill="none" stroke="currentColor" stroke-width="2.5"/><rect x="24" y="14" width="16" height="22" rx="2" fill="none" stroke="currentColor" stroke-width="2"/></svg>';
			if (d.image) { var img = el('img'); img.src = d.image; img.alt = d.manufacturer + ' ' + famName(d); img.loading = 'lazy'; img.addEventListener('error', function () { img.remove(); fig.classList.add('is-empty'); fig.innerHTML = ph; }); fig.appendChild(img); }
			else { fig.classList.add('is-empty'); fig.innerHTML = ph; }
			card.appendChild(fig);

			var body = el('div', 'j5ac-pcard-body');
			var head = el('div', 'j5ac-pcard-head');
			var pick = el('label', 'j5ac-pcard-pick');
			var cb = el('input'); cb.type = 'checkbox'; cb.checked = self.picked.indexOf(d.slug) !== -1; cb.disabled = tr.classList.contains('is-locked');
			cb.setAttribute('aria-label', 'Compare ' + d.manufacturer + ' ' + d.model);
			cb.addEventListener('change', function () { var tcb = tr.querySelector('.j5ac-pick'); tcb.checked = cb.checked; self.pick(d.slug, cb.checked, tcb); });
			pick.appendChild(cb); pick.appendChild(el('span', null, 'Compare'));
			head.appendChild(pick);
			body.appendChild(head);
			body.appendChild(el('h3', 'j5ac-pcard-name', famName(d)));
			var mk = el('p', 'j5ac-pcard-maker', d.manufacturer);
			if (d.carried) mk.appendChild(el('span', 'j5ac-stock', d.wc ? ' Sold by J5' : ' Quote from J5'));
			body.appendChild(mk);

			// Size picker (only when the listing has more than one size).
			if (models.length > 1) {
				var sizes = el('div', 'j5ac-pcard-sizes'); sizes.setAttribute('role', 'group'); sizes.setAttribute('aria-label', 'Size');
				sizes.appendChild(el('span', 'j5ac-pcard-sizes-k', models.length + ' sizes'));
				g.items.forEach(function (i) {
					var isz = sizeOf(i.d), ih = isz && isz.w ? isz.label : (i.d.specs && i.d.specs.size ? String(i.d.specs.size) : i.d.model);
					var b = el('button', 'j5ac-size' + (i.d.slug === d.slug ? ' is-on' : '')); b.type = 'button';
					b.appendChild(el('span', 'j5ac-size-d', ih));
					var meta = [];
					if (i.d.specs && i.d.specs.weight_lb !== undefined) meta.push(i.d.specs.weight_lb + ' lb');
					if (i.d.specs && i.d.specs.handle && models.filter(function (o) { var z = sizeOf(o); return z && isz && z.key === isz.key; }).length > 1) meta.push(String(i.d.specs.handle));
					b.appendChild(el('span', 'j5ac-size-w', meta.join(', ')));
					if (i.d.carried) b.appendChild(el('span', 'j5ac-size-c', i.d.wc ? 'Sold by J5' : 'Quote'));
					b.setAttribute('aria-pressed', i.d.slug === d.slug ? 'true' : 'false');
					b.addEventListener('click', function () {
						if (i.d.slug === d.slug) return;
						// Move an existing pick to the new size so compare follows the card.
						var was = self.picked.indexOf(d.slug) !== -1;
						sel[g.key] = i.d.slug;
						if (was) { var oldcb = tr.querySelector('.j5ac-pick'); oldcb.checked = false; self.pick(d.slug, false, oldcb); var ncb = i.tr.querySelector('.j5ac-pick'); ncb.checked = true; self.pick(i.d.slug, true, ncb); }
						else self.renderCards();
					});
					sizes.appendChild(b);
				});
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
		if (done && !this.cardList.hidden) { var n = groups.length; done.textContent = n + (n === 1 ? ' model matches. ' : ' models match. ') + 'Pick a size on a card, then check any to compare side by side.'; }
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

	Panel.prototype.lockSizes = function () {
		var first = this.picked.length ? this.rowBySlug(this.picked[0]) : null;
		var key = first && this.config.category === 'plate' ? sizeKey(first.data) : null;
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
		this.renderCards();
		var first = this.picked.length ? this.rowBySlug(this.picked[0]).data : null;
		var fsz = first ? sizeOf(first) : null;
		var sizeText = fsz && key ? fsz.label + (fsz.sub ? ', ' + fsz.sub : '') : '';
		this.hint.textContent = this.picked.length
			? (this.picked.length < MAX_PICK ? 'Comparing ' + this.picked.length + '. Check up to ' + MAX_PICK + (sizeText ? ', same size only (' + sizeText + ').' : '.') : 'Comparing ' + MAX_PICK + '.')
			: 'Check up to ' + MAX_PICK + ' to compare side by side.';
		this.clearBtn.hidden = this.picked.length === 0;
		this.compare(on && this.picked.length === 1);
	};

	Panel.prototype.compare = function (scroll) {
		var self = this;
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

			var csz = sizeOf(r.data);
			card.appendChild(el('span', 'j5ac-model', famName(r.data) + (r.data.family && csz && csz.w ? ' ' + csz.label.replace(' in', '') : '')));
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
			var crep = el('button', 'j5ac-report', 'Report incorrect information'); crep.type = 'button';
			crep.addEventListener('click', function () { openReport(self.root, cfg.category, r.data); });
			var crw = el('p', 'j5ac-pcard-report'); crw.appendChild(crep); card.appendChild(crw);
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
		var back = el('button', 'j5ac-float-btn j5ac-float-btn--back', 'Back'); back.type = 'button'; back.setAttribute('aria-label', 'Back one question');
		var over = el('button', 'j5ac-float-btn j5ac-float-btn--over', 'Start over'); over.type = 'button'; over.setAttribute('aria-label', 'Back to the first question on this tab');
		var reset = el('button', 'j5ac-float-btn j5ac-float-btn--reset', 'Reset all'); reset.type = 'button'; reset.setAttribute('aria-label', 'Clear all selections and filters');
		bar.appendChild(top); bar.appendChild(back); bar.appendChild(over); bar.appendChild(reset);
		back.addEventListener('click', function () {
			var open = root.querySelector('.j5ac-panel:not([hidden])'), pn = open && panels[open.getAttribute('data-cat')];
			if (pn) pn.backStep();
		});
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
			if (panels[cat]) { show(cat); if (segs.length) panels[cat].readHash(segs); }
		}
		floatingBar(root, panels);
	}

	function boot() { Array.prototype.forEach.call(document.querySelectorAll('[data-j5ac]'), init); }
	if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot); else boot();
})();
