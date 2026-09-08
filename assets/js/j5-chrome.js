/* ============================================================
 * J5 Rescue Supply — Site chrome JS
 * assets/js/j5-chrome.js
 * Handles: mobile drawer, search overlay, mega menu hover,
 *          primary nav submenus on touch, sticky header.
 * Vanilla JS — no jQuery dependency.
 * ============================================================ */
(function () {
	'use strict';

	if (typeof document === 'undefined') return;

	var body = document.body;
	if (!body.classList.contains('j5-custom-chrome')) return;

	/* ----- Helpers ----- */
	function qs(sel, ctx)  { return (ctx || document).querySelector(sel); }
	function qsa(sel, ctx) { return Array.prototype.slice.call((ctx || document).querySelectorAll(sel)); }
	function on(el, ev, fn, opts) { if (el) el.addEventListener(ev, fn, opts || false); }

	/* ============================================================
	   Mobile drawer (off-canvas)
	   ============================================================ */
	var drawer     = qs('#j5-mobile-drawer');
	var drawerOpen = qsa('[data-j5-drawer-open]');
	var drawerClose= qsa('[data-j5-drawer-close]');

	function openDrawer() {
		if (!drawer) return;
		drawer.hidden = false;
		// Force reflow so the transition animates.
		void drawer.offsetWidth;
		drawer.classList.add('is-open');
		body.style.overflow = 'hidden';
		drawerOpen.forEach(function (b) { b.setAttribute('aria-expanded', 'true'); });
		var focusable = qs('input[type="search"], button, a', drawer);
		if (focusable) focusable.focus();
	}
	function closeDrawer() {
		if (!drawer) return;
		drawer.classList.remove('is-open');
		body.style.overflow = '';
		drawerOpen.forEach(function (b) { b.setAttribute('aria-expanded', 'false'); });
		setTimeout(function () { drawer.hidden = true; }, 220);
	}
	drawerOpen.forEach(function (b)  { on(b, 'click', openDrawer); });
	drawerClose.forEach(function (b) { on(b, 'click', closeDrawer); });

	/* Mobile submenu expansion: tap parent to reveal children */
	qsa('.j5-mobile-nav .menu-item-has-children > a').forEach(function (a) {
		on(a, 'click', function (e) {
			var sub = a.parentNode.querySelector('.sub-menu');
			if (!sub) return;
			if (sub.style.display === 'block') {
				sub.style.display = 'none';
			} else {
				// First tap: expand; second tap (actually navigates). We only
				// prevent default on the first tap for hash links.
				if (a.getAttribute('href') === '#' || a.getAttribute('href') === '') {
					e.preventDefault();
				}
				sub.style.display = 'block';
			}
		});
	});

	/* ============================================================
	   Search overlay
	   ============================================================ */
	var searchOverlay = qs('#j5-search-overlay');
	var searchOpen    = qsa('[data-j5-search-open]');
	var searchClose   = qsa('[data-j5-search-close]');

	function openSearch() {
		if (!searchOverlay) return;
		searchOverlay.hidden = false;
		var input = qs('.j5-search-overlay__input', searchOverlay);
		if (input) setTimeout(function () { input.focus(); input.select(); }, 30);
	}
	function closeSearch() {
		if (!searchOverlay) return;
		searchOverlay.hidden = true;
	}
	searchOpen.forEach(function (b)  { on(b, 'click', openSearch); });
	searchClose.forEach(function (b) { on(b, 'click', closeSearch); });

	/* ============================================================
	   Escape key: close whatever's open
	   ============================================================ */
	on(document, 'keydown', function (e) {
		if (e.key !== 'Escape' && e.keyCode !== 27) return;
		if (searchOverlay && !searchOverlay.hidden) closeSearch();
		if (drawer && drawer.classList.contains('is-open')) closeDrawer();
		if (megaMenu && megaMenu.classList.contains('is-open')) closeMega();
	});

	/* ============================================================
	   Mega menu — hover-triggered on primary-nav Shop item
	   ============================================================ */
	var megaMenu   = qs('#j5-mega-menu');
	var primaryNav = qs('.j5-primary-nav');
	var shopTrigger = null;

	if (primaryNav) {
		qsa('.j5-primary-nav__list > li > a', primaryNav).forEach(function (a) {
			var href = a.getAttribute('href') || '';
			if (href.match(/\/shop\/?$/)) {
				shopTrigger = a.parentNode;
				shopTrigger.classList.add('j5-mega-trigger');
			}
		});
	}

	var megaHideTimer = null;
	function openMega() {
		if (!megaMenu) return;
		if (megaHideTimer) { clearTimeout(megaHideTimer); megaHideTimer = null; }
		megaMenu.classList.add('is-open');
		megaMenu.setAttribute('aria-hidden', 'false');
	}
	function closeMega() {
		if (!megaMenu) return;
		megaMenu.classList.remove('is-open');
		megaMenu.setAttribute('aria-hidden', 'true');
	}
	function scheduleCloseMega() {
		if (megaHideTimer) clearTimeout(megaHideTimer);
		megaHideTimer = setTimeout(closeMega, 180);
	}

	if (shopTrigger && megaMenu) {
		on(shopTrigger, 'mouseenter', openMega);
		on(shopTrigger, 'mouseleave', scheduleCloseMega);
		on(megaMenu, 'mouseenter', openMega);
		on(megaMenu, 'mouseleave', scheduleCloseMega);
	}

	/* Mega menu tab switching: hover category -> swap panel */
	if (megaMenu) {
		var cats = qsa('.j5-mega-menu__cat', megaMenu);
		var panels = qsa('.j5-mega-menu__panel', megaMenu);

		function activateCat(catBtn) {
			var targetId = catBtn.getAttribute('aria-controls');
			cats.forEach(function (c) {
				var active = (c === catBtn);
				c.classList.toggle('is-active', active);
				c.setAttribute('aria-selected', active ? 'true' : 'false');
			});
			panels.forEach(function (p) {
				var active = (p.id === targetId);
				p.classList.toggle('is-active', active);
				p.hidden = !active;
			});
		}
		cats.forEach(function (c) {
			on(c, 'mouseenter', function () { activateCat(c); });
			on(c, 'focus', function () { activateCat(c); });
			on(c, 'click', function (e) {
				e.preventDefault();
				var url = c.getAttribute('data-j5-mega-url');
				if (url) window.location.href = url;
			});
		});
	}

	/* ============================================================
	   Sticky header on scroll
	   ============================================================ */
	var header = qs('#j5-site-header');
	var STICKY_THRESHOLD = 40;
	var ticking = false;
	function onScroll() {
		if (ticking) return;
		window.requestAnimationFrame(function () {
			if (!header) { ticking = false; return; }
			if (window.scrollY > STICKY_THRESHOLD) {
				header.classList.add('is-sticky');
			} else {
				header.classList.remove('is-sticky');
			}
			ticking = false;
		});
		ticking = true;
	}
	on(window, 'scroll', onScroll, { passive: true });

})();


/* ============================================================
 * J5-CHROME-STICKY (2026-04-22)
 * Toggles body.is-header-stuck when the main header pins to top.
 * Used by the companion CSS to add a drop shadow.
 * Remove between sentinels to revert.
 * ============================================================ */
/* J5-CHROME-STICKY-JS-START */
(function () {
	'use strict';
	function initStickyHeader() {
		var header = document.querySelector('.j5-main-header');
		if (!header) { return; }
		if (typeof window.IntersectionObserver === 'undefined') { return; }

		// Create an invisible 1px sentinel just above the header.
		// When the sentinel is NOT intersecting the viewport, the header is pinned.
		var sentinel = document.createElement('div');
		sentinel.setAttribute('aria-hidden', 'true');
		sentinel.style.cssText = 'position:absolute;top:0;left:0;right:0;height:1px;pointer-events:none;';

		// Offset the observer root-margin to match any admin-bar offset,
		// so we toggle the stuck class at the right visual moment.
		var rootMargin = '0px';
		if (document.body.classList.contains('admin-bar')) {
			rootMargin = (window.innerWidth <= 782 ? '-46px' : '-32px') + ' 0px 0px 0px';
		}

		header.parentNode.insertBefore(sentinel, header);

		var observer = new IntersectionObserver(function (entries) {
			var entry = entries[0];
			document.body.classList.toggle('is-header-stuck', !entry.isIntersecting);
		}, { threshold: 0, rootMargin: rootMargin });

		observer.observe(sentinel);
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', initStickyHeader);
	} else {
		initStickyHeader();
	}
})();
/* J5-CHROME-STICKY-JS-END */
