<?php
/**
 * Default language switcher.
 *
 * Override: copy to {theme}/weblorem-multilang-tabs/switcher.php. The plugin stylesheet is not loaded for an override.
 *
 * @var array  $args    style (list|dropdown), show_flags, show_names, hide_untranslated, context (the do_action( 'wl_language_switcher' ) argument, default 'default').
 * @var array  $items   Output of wl_mlt_get_switcher_items().
 * @var array  $current The current language item.
 * @var Closure $label  Escaped label markup of an item: $label( $item ).
 */

use WebLorem\MultilangTabs\Seo\Alternates;

defined( 'ABSPATH' ) || exit;

$dropdown = 'dropdown' === $args['style'];
?>
<nav class="wl-mlt-switcher wl-mlt-switcher--<?php echo $dropdown ? 'dropdown' : 'list'; ?>" aria-label="<?php esc_attr_e( 'Language', 'weblorem-multilang-tabs' ); ?>">
	<?php if ( $dropdown ) : ?>
	<details class="wl-mlt-switcher__details">
		<summary class="wl-mlt-switcher__current"><?php echo $label( $current ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in $label. ?></summary>
	<?php endif; ?>
		<ul class="wl-mlt-switcher__list">
			<?php foreach ( $items as $item ) : ?>
				<li class="wl-mlt-switcher__item<?php echo $item['current'] ? ' is-current' : ''; ?><?php echo $item['translated'] ? '' : ' is-untranslated'; ?>">
					<a href="<?php echo esc_url( $item['url'] ); ?>" lang="<?php echo esc_attr( Alternates::hreflang( $item['code'] ) ); ?>" hreflang="<?php echo esc_attr( Alternates::hreflang( $item['code'] ) ); ?>" data-lang="<?php echo esc_attr( $item['code'] ); ?>"<?php echo $item['current'] ? ' aria-current="true"' : ''; ?>>
						<?php echo $label( $item ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in $label. ?>
					</a>
				</li>
			<?php endforeach; ?>
		</ul>
	<?php if ( $dropdown ) : ?>
	</details>
	<?php endif; ?>
</nav>
