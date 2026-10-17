/* J5 RFQ — native quote form backed by the DP public quote API. See inc/j5-rfq.php. */
(function () {
  'use strict';
  var root = document.getElementById('j5rfq');
  if (!root) return;
  var cfg = JSON.parse(root.getAttribute('data-config') || '{}');
  var API = cfg.api;
  var NS = '__not_sure__';
  var form = document.getElementById('j5rfq-form');
  var finderEl = document.getElementById('j5rfq-finder');
  var itemsEl = document.getElementById('j5rfq-items');
  var msgEl = document.getElementById('j5rfq-msg');
  var items = [];          // {variant_id?, sku, name, qty}
  var facets = null;
  var source = { source: 'site', source_url: location.href };

  /* ---------- tiny DOM helper (textContent only, never innerHTML for data) ---------- */
  function el(tag, attrs, kids) {
    var n = document.createElement(tag);
    if (attrs) Object.keys(attrs).forEach(function (k) {
      if (k === 'class') n.className = attrs[k];
      else if (k === 'text') n.textContent = attrs[k];
      else if (k.slice(0, 2) === 'on') n.addEventListener(k.slice(2), attrs[k]);
      else if (attrs[k] !== null && attrs[k] !== undefined && attrs[k] !== false) n.setAttribute(k, attrs[k]);
    });
    (kids || []).forEach(function (c) { if (c) n.appendChild(typeof c === 'string' ? document.createTextNode(c) : c); });
    return n;
  }
  function clear(n) { while (n.firstChild) n.removeChild(n.firstChild); }
  function getJSON(path) { return fetch(API + path, { credentials: 'omit' }).then(function (r) { if (!r.ok) throw r; return r.json(); }); }
  function postJSON(path, body) {
    return fetch(API + path, { method: 'POST', credentials: 'omit', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(body) })
      .then(function (r) { return r.json().catch(function () { return {}; }).then(function (j) { if (!r.ok) { j._status = r.status; throw j; } return j; }); });
  }
  function plural(n, w) { return n + ' ' + w + (n === 1 ? '' : 's'); }

  /* ---------- items ---------- */
  function addItem(it) {
    if (it.variant_id && items.some(function (x) { return x.variant_id === it.variant_id; })) return;
    items.push({ variant_id: it.variant_id || null, sku: it.sku || '', name: it.name || it.sku || 'Item', qty: it.qty || 1 });
    renderItems();
  }
  function picked(vid) { return items.some(function (x) { return x.variant_id && x.variant_id === vid; }); }
  function renderItems() {
    clear(itemsEl);
    if (!items.length) { itemsEl.appendChild(el('p', { class: 'j5rfq__empty', text: 'No items yet. Find products above, or describe what you need in the notes.' })); return; }
    var tb = el('tbody');
    items.forEach(function (it, i) {
      var q = el('input', { class: 'j5rfq__qty', inputmode: 'numeric', value: String(it.qty), 'aria-label': 'Quantity' });
      q.addEventListener('input', function () { it.qty = Math.max(1, parseInt(q.value, 10) || 1); });
      q.addEventListener('blur', function () { q.value = String(it.qty); });
      tb.appendChild(el('tr', null, [
        el('td', { class: 'j5rfq__mono', text: it.sku || '—' }),
        el('td', { text: it.name }),
        el('td', { class: 'r' }, [q]),
        el('td', { class: 'r' }, [el('button', { type: 'button', class: 'j5rfq__x', 'aria-label': 'Remove ' + it.name, text: '×', onclick: function () { items.splice(i, 1); renderItems(); refreshFinder(); } })])
      ]));
    });
    itemsEl.appendChild(el('table', { class: 'j5rfq__items' }, [
      el('thead', null, [el('tr', null, [el('th', { text: 'SKU' }), el('th', { text: 'Item' }), el('th', { class: 'r', text: 'Qty' }), el('th')])]),
      tb
    ]));
  }

  /* ---------- finder shell: "I know" / "I don't know" tabs ---------- */
  var path = 'know';
  var refreshFinder = function () {};
  function renderFinder() {
    clear(finderEl);
    var guided = facets && facets.guided === true;
    if (guided) {
      finderEl.appendChild(el('div', { class: 'j5rfq__paths', role: 'tablist' }, [
        el('button', { type: 'button', role: 'tab', class: path === 'know' ? 'is-on' : '', 'aria-selected': path === 'know' ? 'true' : 'false', onclick: function () { path = 'know'; renderFinder(); } },
          [el('b', { text: 'I know what I want' }), el('span', { text: 'Search by manufacturer, name or SKU' })]),
        el('button', { type: 'button', role: 'tab', class: path === 'guide' ? 'is-on' : '', 'aria-selected': path === 'guide' ? 'true' : 'false', onclick: function () { path = 'guide'; renderFinder(); } },
          [el('b', { text: 'I don\'t know what I want' }), el('span', { text: 'Answer a few questions and we\'ll narrow it down' })])
      ]));
    }
    var body = el('div');
    finderEl.appendChild(body);
    if (path === 'guide' && guided) guideFinder(body); else searchFinder(body);
  }

  /* ---------- "I know what I want": search ---------- */
  function searchFinder(box) {
    var mfr = el('select', { 'aria-label': 'Manufacturer' }, [el('option', { value: '', text: 'All manufacturers' })]);
    (facets ? facets.manufacturers : []).forEach(function (m) { mfr.appendChild(el('option', { value: m.name, text: m.name })); });
    var q = el('input', { type: 'search', placeholder: 'Search by product name or SKU…', 'aria-label': 'Search products', autocomplete: 'off' });
    var res = el('div', { class: 'j5rfq__results', 'aria-live': 'polite' });
    box.appendChild(el('div', { class: 'j5rfq__search' }, [mfr, q]));
    box.appendChild(res);
    var timer, seq = 0;
    function run() {
      var term = q.value.trim(), m = mfr.value;
      clear(res);
      if (!m && term.length < 2) return;
      var my = ++seq;
      res.appendChild(el('p', { class: 'j5rfq__muted', text: 'Searching…' }));
      var sp = new URLSearchParams(); if (term) sp.set('q', term); if (m) sp.set('manufacturer', m);
      getJSON('/products?' + sp.toString()).then(function (rows) {
        if (my !== seq) return;
        clear(res);
        rows.forEach(function (h) {
          var on = picked(h.variant_id);
          res.appendChild(el('div', { class: 'j5rfq__row' }, [
            el('div', { class: 'j5rfq__rowmain' }, [el('b', { text: h.name }), el('span', { text: [h.sku, h.manufacturer, h.category].filter(Boolean).join(' · ') })]),
            el('button', { type: 'button', class: 'j5rfq__add' + (on ? ' is-added' : ''), disabled: on ? 'disabled' : null, text: on ? 'Added ✓' : 'Add',
              onclick: function (e) { addItem({ variant_id: h.variant_id, sku: h.sku, name: h.name }); e.target.textContent = 'Added ✓'; e.target.disabled = true; e.target.classList.add('is-added'); } })
          ]));
        });
        if (term) {
          res.appendChild(el('button', { type: 'button', class: 'j5rfq__free', onclick: function () { addItem({ name: term }); q.value = ''; clear(res); } },
            [rows.length ? 'Not listed? ' : 'No matches. ', el('b', { text: 'Add "' + term + '" as a line item' })]));
        }
      }).catch(function () { if (my === seq) { clear(res); res.appendChild(el('p', { class: 'j5rfq__muted', text: 'Search is unavailable right now. Describe what you need in the notes below.' })); } });
    }
    q.addEventListener('input', function () { clearTimeout(timer); timer = setTimeout(run, 250); });
    mfr.addEventListener('change', run);
    refreshFinder = run;
  }

  /* ---------- "I don't know what I want": guided finder ---------- */
  var gs = { cat: null, sub: null, mfr: null, answers: {}, showAll: false, added: null };
  function guideFinder(box) {
    var cats = facets.categories || [];
    var byId = {}; cats.forEach(function (c) { byId[c.id] = c; });
    var tops = cats.filter(function (c) { return !c.parent_id; });
    var kids = gs.cat && gs.cat !== NS ? cats.filter(function (c) { return c.parent_id === gs.cat; }) : [];
    var subNeeded = gs.cat && gs.cat !== NS && kids.length > 0;
    var catDone = gs.cat && (!subNeeded || gs.sub);
    var catId = gs.sub && gs.sub !== NS ? gs.sub : (gs.cat && gs.cat !== NS ? gs.cat : null);

    function reset(to) {
      gs.showAll = false;
      if (to === 'cat') { gs.cat = gs.sub = gs.mfr = null; gs.answers = {}; gs._last = null; }
      else if (to === 'sub') { gs.sub = gs.mfr = null; gs.answers = {}; gs._last = null; }
      else if (to === 'mfr') { gs.mfr = null; gs.answers = {}; }
      else { var keep = {}, hit = false; Object.keys(gs.answers).forEach(function (k) { if (k === to) hit = true; if (!hit) keep[k] = gs.answers[k]; }); gs.answers = keep; }
      renderFinder();
    }
    function tile(label, hint, count, ns, onclick) {
      return el('button', { type: 'button', class: 'j5rfq__tile' + (ns ? ' is-ns' : ''), onclick: onclick }, [
        el('b', { text: label }), hint ? el('span', { text: hint }) : null,
        (count !== undefined && count !== null && !ns) ? el('em', { text: plural(count, 'product') }) : null
      ]);
    }
    function step(n, label, help, tiles) {
      return el('div', { class: 'j5rfq__step' }, [el('div', { class: 'j5rfq__stepno', text: 'Step ' + n }), el('h4', { text: label }), help ? el('p', { text: help }) : null, el('div', { class: 'j5rfq__tiles' }, tiles)]);
    }

    function draw(g, loading) {
      clear(box);
      var crumbs = [];
      if (gs.cat) crumbs.push(['cat', 'Looking for', gs.cat === NS ? 'Not sure' : (byId[gs.cat] || {}).name]);
      if (gs.sub) crumbs.push(['sub', 'Type', gs.sub === NS ? 'Any' : (byId[gs.sub] || {}).name]);
      if (gs.mfr) crumbs.push(['mfr', 'Manufacturer', gs.mfr === NS ? 'No preference' : gs.mfr]);
      (g ? g.questions : []).forEach(function (q) {
        var a = gs.answers[q.id];
        if (a) { var o = q.options.filter(function (x) { return x.id === a; })[0]; crumbs.push([q.id, q.label.replace(/\?$/, ''), a === NS ? 'Not sure' : (o ? o.label : a)]); }
      });
      if (crumbs.length) {
        var cb = el('div', { class: 'j5rfq__crumbs' });
        crumbs.forEach(function (c) { cb.appendChild(el('button', { type: 'button', class: 'j5rfq__crumb', title: 'Change this answer', onclick: function () { reset(c[0]); } }, [el('span', { text: c[1] }), el('b', { text: c[2] || '…' }), el('i', { text: '✎' })])); });
        cb.appendChild(el('button', { type: 'button', class: 'j5rfq__link', text: 'Start over', onclick: function () { reset('cat'); } }));
        box.appendChild(cb);
      }
      if (gs.added) box.appendChild(el('div', { class: 'j5rfq__added' }, [el('span', null, ['✓ Added ', el('b', { text: gs.added }), ' to your quote.']),
        el('button', { type: 'button', class: 'j5rfq__btn', text: 'Add another product', onclick: function () { gs.added = null; reset('cat'); } })]));

      var mfrNeeded = catDone && g && g.manufacturers.length > 1;
      var pending = g ? g.questions.filter(function (q) { return !(q.id in gs.answers); })[0] : null;
      var stepKey = null;
      if (!gs.cat) stepKey = 'cat'; else if (subNeeded && !gs.sub) stepKey = 'sub'; else if (mfrNeeded && !gs.mfr) stepKey = 'mfr'; else if (pending && !gs.showAll) stepKey = pending.id;
      var n = crumbs.length + 1;

      if (stepKey === 'cat') {
        box.appendChild(step(n, 'What are you shopping for?', 'Pick the closest match. Not sure? We\'ll show you everything we list.',
          tops.map(function (c) { return tile(c.name, null, c.count, false, function () { gs.cat = c.id; gs.added = null; renderFinder(); }); })
            .concat([tile('Not sure', 'Show me everything', null, true, function () { gs.cat = NS; renderFinder(); })])));
      } else if (stepKey === 'sub') {
        var cn = (byId[gs.cat] || {}).name || 'product';
        box.appendChild(step(n, 'What kind of ' + cn.toLowerCase() + '?', null,
          kids.map(function (c) { return tile(c.name, null, c.count, false, function () { gs.sub = c.id; renderFinder(); }); })
            .concat([tile('Not sure', 'All ' + cn, null, true, function () { gs.sub = NS; renderFinder(); })])));
      } else if (stepKey === 'mfr' && g) {
        box.appendChild(step(n, 'Do you have a preferred manufacturer?', 'Many agencies standardize on one brand. If you don\'t, skip this.',
          g.manufacturers.map(function (m) { return tile(m.name, null, m.count, false, function () { gs.mfr = m.name; renderFinder(); }); })
            .concat([tile('Not sure / no preference', 'Show all brands', null, true, function () { gs.mfr = NS; renderFinder(); })])));
      } else if (stepKey && pending) {
        box.appendChild(step(n, pending.label, pending.help,
          pending.options.filter(function (o) { return o.id !== NS; }).map(function (o) { return tile(o.label, o.hint, o.count, false, function () { gs.answers[pending.id] = o.id; renderFinder(); }); })
            .concat([tile('Not sure', 'Don\'t narrow on this', null, true, function () { gs.answers[pending.id] = NS; renderFinder(); })])));
      }

      if (g) {
        var head = el('div', { class: 'j5rfq__reshead' }, [el('span', null, [el('b', { text: String(g.total) }), ' ' + (g.total === 1 ? 'product matches' : 'products match') + (loading ? ' · updating…' : '')])]);
        if (stepKey && stepKey !== 'cat' && stepKey !== 'sub') head.appendChild(el('button', { type: 'button', class: 'j5rfq__link', text: 'Skip questions, show results', onclick: function () { gs.showAll = true; renderFinder(); } }));
        box.appendChild(head);
        if (!stepKey || gs.showAll || g.total <= 8) {
          if (!g.products.length) box.appendChild(el('p', { class: 'j5rfq__muted', text: 'Nothing in our catalog matches all of that. Change an answer above, or describe what you need in the notes and we\'ll find it.' }));
          g.products.forEach(function (p) {
            var sel = null, v0 = p.variants[0];
            if (p.variants.length > 1) { sel = el('select', { 'aria-label': 'Option' }); p.variants.forEach(function (v) { sel.appendChild(el('option', { value: v.variant_id, text: v.label })); }); }
            var btn = el('button', { type: 'button', class: 'j5rfq__add', text: 'Add' });
            function cur() { var id = sel ? sel.value : (v0 && v0.variant_id); return p.variants.filter(function (v) { return v.variant_id === id; })[0]; }
            function sync() { var v = cur(); var on = v && picked(v.variant_id); btn.textContent = on ? 'Added ✓' : 'Add'; btn.disabled = !!on || !v; btn.classList.toggle('is-added', !!on); }
            btn.addEventListener('click', function () { var v = cur(); if (!v) return; var nm = p.variants.length > 1 ? p.name + ' — ' + v.label : p.name; addItem({ variant_id: v.variant_id, sku: v.sku, name: nm }); gs.added = nm; renderFinder(); });
            if (sel) sel.addEventListener('change', sync);
            box.appendChild(el('div', { class: 'j5rfq__row' }, [
              el('div', { class: 'j5rfq__rowmain' }, [el('b', { text: p.name }), el('span', { text: [p.manufacturer, p.category, p.nij_rating ? 'NIJ ' + p.nij_rating : null].filter(Boolean).join(' · ') })]),
              sel || el('span', { class: 'j5rfq__mono', text: v0 ? v0.sku : '' }), btn
            ]));
            sync();
          });
          if (g.total > g.products.length) box.appendChild(el('p', { class: 'j5rfq__muted', text: 'Showing ' + g.products.length + ' of ' + g.total + '. Answer more questions to narrow it down.' }));
        } else {
          box.appendChild(el('p', { class: 'j5rfq__muted', text: 'Answer the question above to narrow these down, or skip to the results.' }));
        }
      }
    }

    if (!catDone) { draw(null, false); return; }
    draw(gs._last || null, true);
    var my = (gs._seq = (gs._seq || 0) + 1);
    postJSON('/guide', { category_id: catId, manufacturer: gs.mfr && gs.mfr !== NS ? gs.mfr : null, answers: gs.answers })
      .then(function (g) { if (my !== gs._seq) return; gs._last = g; draw(g, false); })
      .catch(function () { if (my === gs._seq) { clear(box); box.appendChild(el('p', { class: 'j5rfq__muted', text: 'The product finder is unavailable right now. Use search, or describe what you need in the notes.' })); } });
    refreshFinder = function () { if (gs._last) draw(gs._last, false); };
  }

  /* ---------- submit ---------- */
  function showMsg(text, ok) { msgEl.hidden = false; msgEl.className = 'j5rfq__msg' + (ok ? ' is-ok' : ' is-err'); msgEl.textContent = text; }
  form.addEventListener('submit', function (e) {
    e.preventDefault();
    var fd = new FormData(form), v = function (k) { return String(fd.get(k) || '').trim(); };
    if (v('organization').length < 2 || v('name').length < 2 || !/^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(v('email'))) {
      showMsg('Please fill in your organization, name and a valid email.', false); return;
    }
    if (!items.length && !v('notes')) { showMsg('Add at least one item, or describe what you need in the notes.', false); return; }
    var btn = document.getElementById('j5rfq-submit');
    btn.disabled = true; btn.textContent = 'Sending…'; msgEl.hidden = true;
    var officers = parseInt(v('officers'), 10);
    postJSON('/submit', {
      kind: 'quote', organization: v('organization'), name: v('name'), title: v('title') || null, email: v('email'),
      phone: v('phone') || null, state: v('state') || null, officers: isNaN(officers) ? null : officers,
      agency_type: v('agency_type') || null, timeline: v('timeline') || null, heard_from: v('heard_from') || null,
      notes: v('notes') || null, website: v('website'),
      items: items.map(function (it) { return { variant_id: it.variant_id, sku: it.sku || null, name: it.name, qty: it.qty }; }),
      source: source.source, source_url: source.source_url
    }).then(function (r) {
      clear(form);
      form.appendChild(el('div', { class: 'j5rfq__done' }, [
        el('div', { class: 'j5rfq__check', text: '✓' }),
        el('h2', { class: 'j5rfq__h', text: 'Quote request received' }),
        el('p', { text: 'Thanks, ' + v('name').split(' ')[0] + '. We\'ll send a written quote to ' + v('email') + ', usually the same business day.' + (r && r.id ? ' Reference #' + r.id + '.' : '') }),
        el('a', { class: 'j5rfq__btn', href: '/shop/', text: 'Continue shopping →' })
      ]));
      root.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }).catch(function (err) {
      btn.disabled = false; btn.textContent = 'Submit for quote';
      var c = cfg.contact || {};
      showMsg(err && err._status === 429 ? 'Too many requests. Please wait a minute and try again.'
        : err && err.detail && typeof err.detail === 'string' ? err.detail
        : 'We couldn\'t send your request. Please try again, or email ' + (c.email || '') + ' / call ' + (c.phone || '') + '.', false);
    });
  });

  /* ---------- boot ---------- */
  renderItems();
  if (cfg.prefill) {
    var p = cfg.prefill;
    source = { source: 'product_page', source_url: p.from || document.referrer || location.href };
    addItem({ sku: p.sku, name: p.name, qty: p.qty });
    if (p.sku) getJSON('/products?q=' + encodeURIComponent(p.sku)).then(function (rows) {
      var hit = rows.filter(function (h) { return (h.sku || '').toLowerCase() === p.sku.toLowerCase(); })[0];
      if (hit) { items[0] = { variant_id: hit.variant_id, sku: hit.sku, name: hit.name, qty: p.qty }; renderItems(); }
    }).catch(function () {});
  }
  getJSON('/facets').then(function (f) { facets = f; renderFinder(); })
    .catch(function () { facets = { manufacturers: [], categories: [], guided: false }; renderFinder(); });
})();
