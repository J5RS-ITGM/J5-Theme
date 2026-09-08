# J5 Rescue Supply — Standalone Theme

Migrated from `astra-child` 2026-09. **No parent theme required.** Repo: `J5RS-ITGM/J5-Theme`.
Deploy target: `wp-content/themes/j5-theme/` (folder name matters — it is the theme's identity).

## What changed vs astra-child

- `style.css` header: standalone (no `Template:` line), version 2.0.0
- `functions.php`: no parent CSS dependency; loads new `inc/j5-theme-setup.php`
- `inc/j5-theme-setup.php` (NEW): theme supports Astra used to declare
  (title-tag, post-thumbnails, custom-logo, html5, WooCommerce + gallery)
- `page.php` (NEW): default page template — **checkout renders through this**
- `index.php` (NEW): required standalone fallback (also serves as 404)
- `astra-theme-css` removed from all enqueue dependency arrays
  (j5-shop-setup, j5-checkout-setup, j5-cart-checkout-setup, j5-account-setup)
- 89 `.bak-*` / `.unused-*` / `.broken-*` files and `j5-shop-drop-in.zip` NOT migrated

## Deliberately kept

- Text domain `astra-child` (hundreds of call sites; site is English-only; cosmetic)
- Style handle `astra-child-theme-css` (other enqueues depend on the handle name)
- Version constant `CHILD_THEME_ASTRA_CHILD_VERSION` (referenced by inc modules)
- `.ast-container` class emitted by page.php/index.php alongside `.j5-container`
  (15 selectors in assets/css/j5-checkout.css target it; rename is a later pass)

## Activation (theme mods do NOT transfer between themes)

After `wp theme activate j5-theme`, run:

    wp theme mod set custom_logo 5589
    wp eval 'set_theme_mod("nav_menu_locations", array("primary" => 933));'
    wp cache flush

(Values verified on staging 2026-09-07: logo attachment 5589, Primary Menu term 933.)
