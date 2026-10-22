# WebLorem Multilang Tabs

Multilingual content in a single record (OpenCart style): translations live in post/term meta next to the
original, with one language tab bar in every editor. A missing translation falls back to the default language.

Requires WordPress 6.4+, PHP 8.0+.

## Storage

| What | Key |
| --- | --- |
| Post / term / menu item translation | `_wl_mlt_{lang}_{field}` (meta) |
| Option translation | `_wl_mlt_{lang}_{option}` |
| ACF value / ACF reference row | `_wl_mlt_{lang}_{name}` / `__wl_mlt_{lang}_{name}` |
| Settings | option `wl_mlt_settings` |

An empty translation deletes its meta row, so "no row" always means "use the default language".

## URLs

The default language has no prefix (`/page/`), others do (`/en/page/`). Slugs are shared by all languages:
`/page/` and `/en/page/` differ only by the prefix. Only title, content, excerpt, meta and term name/description
are translated.

## Front-end API

```php
wl_mlt_current_lang();                       // 'en'
wl_mlt_default_lang();                       // 'uk'
wl_mlt_is_default_lang();
wl_mlt_get_languages();
wl_mlt_url( $url, 'en' );                    // rewrite a site URL to a language
wl_mlt_get_alternates();                     // [ lang => url ] of the current page
wl_mlt_language_switcher( array( 'style' => 'dropdown' ) );
wl_mlt_register_translatable_field( 'page', '_my_meta' );
wl_mlt_register_translatable_option( 'my_theme_option' );
```

Switcher: function above, `[wl_mlt_switcher style="list|dropdown" show_flags="1" show_names="1" hide_untranslated="0"]`,
the "Language switcher" widget and block. Styles: `assets/css/switcher.css` (classes `wl-mlt-switcher*`).

Themes print the switcher with `do_action( 'wl_language_switcher', $context )` (`$context`: `'default'`, `'mobile'`; arguments
via the `wl_mlt_switcher_args` filter, `style` defaults to `dropdown`). The markup is `templates/switcher.php`; a theme
overrides it with `{theme}/weblorem-multilang-tabs/switcher.php` (then `switcher.css` is not loaded).

Filters: `wl_mlt_reserved_codes`, `wl_mlt_translatable_fields`, `wl_mlt_hreflang`.

## SEO

hreflang + `x-default` in `<head>`, canonical to the original for untranslated pages (which are not listed in
hreflang), core sitemaps with `xhtml:link` alternates, `og:locale` and canonical for Yoast SEO / Rank Math.
Yoast and Rank Math sitemaps are not modified.

## WP-CLI

```
wp wl-mlt status
wp wl-mlt export translations.json [--lang=en] [--post-type=page,post]
wp wl-mlt import translations.json [--lang=en] [--replace] [--dry-run]
wp wl-mlt clear en --yes
```

The same export/import is on Settings → WebLorem Multilang Tabs. Posts (by type + path), terms (taxonomy + slug)
and menu items (menu + target) are matched without numeric IDs; objects missing on the target site are reported
and skipped. Values are copied as raw database strings, serialized ACF data included.

## Revisions

Each revision stores a copy of the translations; restoring a revision restores all languages. Revisions made
before the plugin was active leave translations untouched.

## Caching

With "redirect to browser language" on, the unprefixed home page sends `Vary: Accept-Language, Cookie` and the
redirect itself is `no-cache`. Prefixed URLs are plain per-URL pages and cache normally.

## Tests

```
wp eval-file web/app/plugins/weblorem-multilang-tabs/tests/run.php
```

Runs against the live site, creates temporary pages/terms/menu and removes them. Needs languages `uk` (default) and `en`.

## Translations of the plugin UI

`languages/weblorem-multilang-tabs.pot`, Ukrainian in `weblorem-multilang-tabs-uk.po/.mo`.
Regenerate the POT: `wp i18n make-pot . languages/weblorem-multilang-tabs.pot --exclude=tests`.
