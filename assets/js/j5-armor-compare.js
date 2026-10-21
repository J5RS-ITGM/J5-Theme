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
	function tier(l) {
		l = String(l || '').toUpperCase().trim();
		if (['IIA', 'II', 'IIIA', 'HG1', 'HG2'].indexOf(l) !== -1) return 'hg';
		if (['III', 'III+'].indexOf(l) !== -1) return 'rifle';
		if (['RF1', 'RF2', 'RF3'].indexOf(l) !== -1) return 'rf';
		if (l === 'IV') return 'ap';
		return 'other';
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
		this.helpBtn = section.querySelector('[data-j5ac-help]');
		this.helpNote = section.querySelector('[data-j5ac-helpnote]');
		this.out = section.querySelector('[data-j5ac-compare-out]');
		this.picked = [];
		this.bind();
	}

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
		this.helpBtn.addEventListener('click', function () { if (self.picked.length) self.helpCompare(self.picked[0]); });
		this.tbody.addEventListener('click', function (e) {
			var b = e.target.closest('[data-j5ac-rowhelp]');
			if (b) self.helpCompare(b.getAttribute('data-j5ac-rowhelp'));
		});
	};

	/**
	 * Help me compare: start from one model and pull in the closest matches from
	 * other manufacturers. Same protection level first, same standard preferred,
	 * then nearest by coverage and weight, one per manufacturer before doubling up.
	 */
	Panel.prototype.helpCompare = function (slug) {
		var anchor = this.rowBySlug(slug);
		if (!anchor) return;
		var a = anchor.data, aLevel = a.specs.level, aStd = a.specs.standard;
		var aArea = num(a.derived.area_sqin), aWt = num(a.specs.weight_lb);
		var want = MAX_PICK - 1;

		var pool = this.rows.filter(function (r) { return r !== anchor && r.data.manufacturer !== a.manufacturer; });
		var tiers = [
			function (r) { return r.data.specs.level === aLevel && r.data.specs.standard === aStd; },
			function (r) { return r.data.specs.level === aLevel; },
			function (r) { return tier(r.data.specs.level) === tier(aLevel); }
		];
		function closeness(r) {
			var d = 0, area = num(r.data.derived.area_sqin), wt = num(r.data.specs.weight_lb);
			if (aArea && area) d += Math.abs(area - aArea) / aArea;
			if (aWt && wt) d += Math.abs(wt - aWt) / aWt;
			if (r.data.carried) d -= 0.05;
			return d;
		}
		var chosen = [], makers = {}, usedTier = -1;
		for (var t = 0; t < tiers.length && chosen.length < want; t++) {
			var cands = pool.filter(tiers[t]).filter(function (r) { return chosen.indexOf(r) === -1; }).sort(function (x, y) { return closeness(x) - closeness(y); });
			// one per manufacturer first
			cands.forEach(function (r) { if (chosen.length < want && !makers[r.data.manufacturer]) { chosen.push(r); makers[r.data.manufacturer] = 1; if (usedTier < t) usedTier = t; } });
			cands.forEach(function (r) { if (chosen.length < want && chosen.indexOf(r) === -1) { chosen.push(r); if (usedTier < t) usedTier = t; } });
		}

		this.clearPicks();
		this.picked = [anchor].concat(chosen).map(function (r) { return r.data.slug; });
		this.picked.forEach(function (sl) { var row = this.rowBySlug(sl); row.tr.classList.add('is-picked'); row.tr.querySelector('.j5ac-pick').checked = true; }, this);
		this.anchorSlug = slug;
		this.hint.textContent = 'Comparing ' + this.picked.length + '.';
		this.clearBtn.hidden = false;
		this.helpBtn.hidden = true;

		var how = chosen.length ? (usedTier === 0 ? 'the same protection level and test standard' : usedTier === 1 ? 'the same protection level' : 'the same threat class') : '';
		this.helpNote.textContent = chosen.length
			? a.manufacturer + ' ' + a.model + ' against ' + chosen.length + ' from other manufacturers with ' + how + ', closest in size and weight first.'
			: 'Nothing from another manufacturer is rated like the ' + a.model + ' in this list.';
		this.helpNote.hidden = false;
		this.compare(true);
	};

	Panel.prototype.clearPicks = function () {
		this.picked.slice().forEach(function (slug) {
			var row = this.rowBySlug(slug);
			if (row) { row.tr.classList.remove('is-picked'); row.tr.querySelector('.j5ac-pick').checked = false; }
		}, this);
		this.picked = [];
		this.anchorSlug = null;
		this.hint.textContent = 'Check up to ' + MAX_PICK + ' to compare side by side, or check one and use Help me compare.';
		this.clearBtn.hidden = true;
		this.helpBtn.hidden = true;
		this.helpNote.hidden = true;
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
		var shown = 0;
		this.rows.forEach(function (row) {
			var ok = true, k, i, v;
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
		this.hint.textContent = this.picked.length ? (this.picked.length < MAX_PICK ? 'Comparing ' + this.picked.length + '. Check up to ' + MAX_PICK + '.' : 'Comparing ' + MAX_PICK + '.') : 'Check up to ' + MAX_PICK + ' to compare side by side, or check one and use Help me compare.';
		this.clearBtn.hidden = this.picked.length === 0;
		this.helpBtn.hidden = this.picked.length !== 1;
		if (this.anchorSlug && this.picked.indexOf(this.anchorSlug) === -1) { this.anchorSlug = null; this.helpNote.hidden = true; }
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
			var nums = rows.map(function (r) { return num(sr.derived ? r.data.derived[sr.key] : r.data.specs[sr.key]); }).filter(function (n) { return n !== null; });
			if (nums.length < 2 || Math.min.apply(null, nums) === Math.max.apply(null, nums)) return;
			if (LOWER_BETTER.test(sr.key)) best[sr.key] = Math.min.apply(null, nums);
			else if (HIGHER_BETTER.test(sr.key)) best[sr.key] = Math.max.apply(null, nums);
		});
		// Best all-around: ounces per sq in (lower), coverage (higher), viewport present, viewport size (higher).
		// Handle, light mounts and carry options are not scored yet.
		var scored = null;
		if (rows.length > 1) {
			var W = { eff: 0.40, area: 0.25, vp: 0.20, vparea: 0.15 };
			var effs = rows.map(function (r) { return num(r.data.derived.oz_per_sqin); });
			var areas = rows.map(function (r) { return num(r.data.derived.area_sqin); });
			var vps = rows.map(function (r) { return viewportArea(r.data.specs.viewport); });
			function span(arr) { var v = arr.filter(function (n) { return n !== null; }); return v.length ? [Math.min.apply(null, v), Math.max.apply(null, v)] : null; }
			var sE = span(effs), sA = span(areas), sV = span(vps);
			function hi(n, sp) { return n === null || !sp || sp[1] === sp[0] ? 0.5 : (n - sp[0]) / (sp[1] - sp[0]); }
			scored = rows.map(function (r, i) {
				var sc = 0;
				if (cfg.category !== 'helmet') {
					sc += W.eff * (effs[i] === null ? 0 : 1 - hi(effs[i], sE));
					sc += W.area * hi(areas[i], sA);
				} else {
					var wts = rows.map(function (x) { return num(x.data.specs.weight_lb); }), sW = span(wts);
					sc += (W.eff + W.area) * (1 - hi(wts[i], sW));
				}
				if (cfg.category === 'shield') {
					sc += W.vp * (vps[i] > 0 ? 1 : 0);
					sc += W.vparea * (vps[i] > 0 ? hi(vps[i], sV) : 0);
				} else sc += W.vp + W.vparea > 0 ? 0 : 0;
				return sc;
			});
		}
		var bestScore = scored ? Math.max.apply(null, scored) : null;
		if (cfg.category === 'shield') { best.viewport_sqin = Math.max.apply(null, rows.map(function (r) { return viewportArea(r.data.specs.viewport); })); if (!(best.viewport_sqin > 0)) delete best.viewport_sqin; }

		if (scored) {
			var how = el('p', 'j5ac-scorenote');
			how.textContent = cfg.category === 'shield'
				? 'Best all-around weighs ounces per square inch, coverage, and the viewport (whether there is one, and its size). Handle, lights and carry options are not scored.'
				: 'Best all-around weighs ' + (cfg.category === 'helmet' ? 'weight.' : 'ounces per square inch and coverage.');
			this.out.appendChild(how);
		}
		var grid = el('div', 'j5ac-cards');
		rows.forEach(function (r, idx) {
			var card = el('div', 'j5ac-card');
			var isAnchor = this.anchorSlug === r.data.slug;
			var isTop = scored && scored[idx] === bestScore && scored.filter(function (x) { return x === bestScore; }).length === 1;
			if (isAnchor) card.classList.add('is-anchor');
			if (isTop) { card.classList.add('is-best'); card.appendChild(el('p', 'j5ac-card-best', (isAnchor ? 'Your pick, ' : '') + 'best all-around')); }
			else if (isAnchor) card.appendChild(el('p', 'j5ac-card-best', 'Your pick'));
			card.appendChild(el('span', 'j5ac-model', r.data.model));
			card.appendChild(el('span', 'j5ac-maker', r.data.manufacturer));

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
				else if (sr.sizes) { td.textContent = text; if (r.data.derived.size_list) { td.appendChild(el('small', null, ' ' + r.data.derived.size_list)); } }
				else td.textContent = sr.unit && typeof v === 'number' ? text + ' ' + sr.unit : text;
				if (best[sr.key] !== undefined && num(v) === best[sr.key]) td.classList.add('is-best');
				if (sr.key === 'viewport' && best.viewport_sqin !== undefined && viewportArea(v) === best.viewport_sqin) td.classList.add('is-best');
				tr.appendChild(td);
				tb.appendChild(tr);
			});
			t.appendChild(tb);
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
		var reset = el('button', 'j5ac-float-btn j5ac-float-btn--reset', 'Reset all'); reset.type = 'button'; reset.setAttribute('aria-label', 'Clear all selections and filters');
		bar.appendChild(top); bar.appendChild(reset);
		root.appendChild(bar);
		top.addEventListener('click', function () { root.scrollIntoView({ behavior: reduced() ? 'auto' : 'smooth', block: 'start' }); });
		reset.addEventListener('click', function () {
			Object.keys(panels).forEach(function (k) { panels[k].clearPicks(); panels[k].form.reset(); });
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
				if (history.replaceState) history.replaceState(null, '', '#j5ac-' + cat);
			});
		});
		var hash = (location.hash || '').replace('#j5ac-', '');
		if (hash && panels[hash]) show(hash);
		floatingBar(root, panels);
	}

	function boot() { Array.prototype.forEach.call(document.querySelectorAll('[data-j5ac]'), init); }
	if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot); else boot();
})();
