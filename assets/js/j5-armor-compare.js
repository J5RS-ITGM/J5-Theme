/* J5 Armor Comparison Tool — inc/j5-armor-reference.php renders the full list
   server-side; this adds filtering, sorting and the compare cards. No deps. */
(function () {
	'use strict';

	var MAX_PICK = 3;
	var LOWER_BETTER  = /weight|oz_per_sqin/;
	var HIGHER_BETTER = /^area_sqin$/;

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
		this.out = section.querySelector('[data-j5ac-compare-out]');
		this.picked = [];
		this.bind();
	}

	Panel.prototype.value = function (row, key) {
		if (key === 'model') return (row.data.manufacturer + ' ' + row.data.model).toLowerCase();
		if (row.data.derived && key in row.data.derived) return row.data.derived[key];
		return row.data.specs ? row.data.specs[key] : undefined;
	};

	Panel.prototype.bind = function () {
		var self = this;
		this.form.addEventListener('input', function (e) {
			if (e.target.type === 'range') self.syncOutput(e.target);
			if (e.target.name === 'sort') self.sort(e.target.value); else self.filter();
		});
		this.form.addEventListener('reset', function () {
			setTimeout(function () { self.syncOutputs(); self.filter(); self.sort(self.form.elements.sort.value); }, 0);
		});
		this.syncOutputs();

		this.tbody.addEventListener('change', function (e) {
			if (!e.target.classList.contains('j5ac-pick')) return;
			self.pick(e.target.value, e.target.checked, e.target);
		});
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
		var lists = {}, maxes = {}, onlys = [], has = [];
		Array.prototype.forEach.call(this.form.elements, function (inp) {
			if (!inp.name || inp.name === 'sort') return;
			if (inp.type === 'checkbox' && inp.name.indexOf('only:') === 0) { if (inp.checked) onlys.push(inp.name.slice(5)); }
			else if (inp.type === 'checkbox' && inp.name.indexOf('has:') === 0) { if (inp.checked) has.push(inp.name.slice(4)); }
			else if (inp.type === 'checkbox') { if (inp.checked) (lists[inp.name] = lists[inp.name] || []).push(inp.value); }
			else if (inp.type === 'range') { if (parseFloat(inp.value) < parseFloat(inp.max)) maxes[inp.name.slice(4)] = parseFloat(inp.value); }
		});
		var shown = 0;
		this.rows.forEach(function (row) {
			var ok = true, k, i, v;
			for (k in lists) { if (lists[k].indexOf(String(this.value(row, k))) === -1) { ok = false; break; } }
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
		var self = this, numeric = key !== 'model';
		var sorted = this.rows.slice().sort(function (a, b) {
			var va = self.value(a, key), vb = self.value(b, key);
			if (numeric) {
				var na = num(va), nb = num(vb);
				if (na === null && nb === null) return 0;
				if (na === null) return 1;
				if (nb === null) return -1;
				return na - nb;
			}
			return String(va).localeCompare(String(vb), undefined, { numeric: true, sensitivity: 'base' });
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
		this.hint.textContent = this.picked.length ? (this.picked.length < MAX_PICK ? 'Comparing ' + this.picked.length + '. Check up to ' + MAX_PICK + '.' : 'Comparing ' + MAX_PICK + '.') : 'Check up to ' + MAX_PICK + ' to compare side by side.';
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

		// Best per numeric row, only when there is a real spread.
		var best = {};
		specRows.forEach(function (sr) {
			if (sr.type !== 'number' || rows.length < 2) return;
			var nums = rows.map(function (r) { return num(sr.derived ? r.data.derived[sr.key] : r.data.specs[sr.key]); }).filter(function (n) { return n !== null; });
			if (nums.length < 2 || Math.min.apply(null, nums) === Math.max.apply(null, nums)) return;
			if (LOWER_BETTER.test(sr.key)) best[sr.key] = Math.min.apply(null, nums);
			else if (HIGHER_BETTER.test(sr.key)) best[sr.key] = Math.max.apply(null, nums);
		});
		var headlineKey = cfg.eff ? 'oz_per_sqin' : 'weight_lb';
		var headline = cfg.eff ? 'Lightest per sq in' : 'Lightest';

		var grid = el('div', 'j5ac-cards');
		rows.forEach(function (r) {
			var card = el('div', 'j5ac-card');
			var hv = num(r.data.derived && r.data.derived[headlineKey] !== undefined ? r.data.derived[headlineKey] : r.data.specs[headlineKey]);
			if (best[headlineKey] !== undefined && hv === best[headlineKey]) { card.classList.add('is-best'); card.appendChild(el('p', 'j5ac-card-best', headline)); }
			card.appendChild(el('span', 'j5ac-model', r.data.model));
			card.appendChild(el('span', 'j5ac-maker', r.data.manufacturer));

			var t = el('table'), tb = el('tbody');
			specRows.forEach(function (sr) {
				var v = sr.derived ? r.data.derived[sr.key] : r.data.specs[sr.key];
				var tr = el('tr');
				tr.appendChild(el('td', null, sr.label));
				var td = el('td');
				var text = sr.places !== undefined && typeof v === 'number' ? v.toFixed(sr.places) : fmt(v, sr.field);
				if (text === null) td.appendChild(el('span', 'j5ac-na', 'not listed'));
				else if (sr.key === 'level') td.appendChild(el('span', 'j5ac-lv j5ac-lv--' + tier(v), text));
				else td.textContent = sr.unit && typeof v === 'number' ? text + ' ' + sr.unit : text;
				if (best[sr.key] !== undefined && num(v) === best[sr.key]) td.classList.add('is-best');
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

			var btn;
			if (r.data.wc) { btn = el('a', 'j5ac-btn j5ac-btn--buy', 'View product'); btn.href = r.data.wc; }
			else {
				btn = el('a', 'j5ac-btn j5ac-btn--quote', 'Request a quote');
				btn.href = quote + (quote.indexOf('?') === -1 ? '?' : '&') + 'item=' + encodeURIComponent(r.data.manufacturer + ' ' + r.data.model) + '&ref=armor-compare';
			}
			card.appendChild(btn);
			grid.appendChild(card);
		});
		this.out.appendChild(grid);
		this.out.hidden = false;
		if (scroll) this.out.scrollIntoView({ behavior: reduced() ? 'auto' : 'smooth', block: 'nearest' });
	};

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
	}

	function boot() { Array.prototype.forEach.call(document.querySelectorAll('[data-j5ac]'), init); }
	if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot); else boot();
})();
