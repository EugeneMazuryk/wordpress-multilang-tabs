<?php
namespace WebLorem\MultilangTabs\Frontend;

use WebLorem\MultilangTabs\Core\Context;
use WebLorem\MultilangTabs\Core\Languages;
use WebLorem\MultilangTabs\Routing\Url;
use WebLorem\MultilangTabs\Seo\Alternates;

defined( 'ABSPATH' ) || exit;

/**
 * Front-end language switcher: function wl_mlt_language_switcher(), [wl_mlt_switcher] shortcode,
 * widget and block all render through here.
 */
final class Switcher {

	public const SHORTCODE = 'wl_mlt_switcher';
	public const BLOCK     = 'weblorem-multilang-tabs/switcher';

	public static function defaults(): array {
		return array(
			'style'             => 'list',
			'show_flags'        => true,
			'show_names'        => true,
			'hide_untranslated' => false,
			'context'           => 'default',
		);
	}

	public function register(): void {
		add_shortcode( self::SHORTCODE, array( $this, 'shortcode' ) );
		add_action( 'widgets_init', static fn() => register_widget( SwitcherWidget::class ) );
		add_action( 'init', array( $this, 'register_block' ) );
		add_action( 'wl_language_switcher', array( $this, 'action' ) );
	}

	/**
	 * do_action( 'wl_language_switcher', $context ): the hook for themes, which can override templates/switcher.php.
	 */
	public function action( $context = 'default' ): void {
		/**
		 * Filters the arguments of the switcher printed by do_action( 'wl_language_switcher' ).
		 */
		$context = true === $context ? 'mobile' : $context;
		$args    = apply_filters( 'wl_mlt_switcher_args', array( 'style' => 'dropdown' ), $context );

		echo self::render( array_merge( (array) $args, array( 'context' => is_string( $context ) && '' !== $context ? $context : 'default' ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in Switcher::render().
	}

	public function shortcode( $atts ): string {
		$atts = shortcode_atts(
			array(
				'style'             => 'list',
				'show_flags'        => '1',
				'show_names'        => '1',
				'hide_untranslated' => '0',
			),
			(array) $atts,
			self::SHORTCODE
		);

		return self::render(
			array(
				'style'             => $atts['style'],
				'show_flags'        => filter_var( $atts['show_flags'], FILTER_VALIDATE_BOOLEAN ),
				'show_names'        => filter_var( $atts['show_names'], FILTER_VALIDATE_BOOLEAN ),
				'hide_untranslated' => filter_var( $atts['hide_untranslated'], FILTER_VALIDATE_BOOLEAN ),
			)
		);
	}

	public function register_block(): void {
		wp_register_script(
			'wl-mlt-switcher-block',
			WL_MLT_URL . 'assets/js/switcher-block.js',
			array( 'wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-server-side-render', 'wp-i18n' ),
			WL_MLT_VERSION,
			true
		);

		register_block_type(
			self::BLOCK,
			array(
				'api_version'     => 3,
				'title'           => __( 'Language switcher', 'weblorem-multilang-tabs' ),
				'category'        => 'widgets',
				'icon'            => 'translation',
				'editor_script'   => 'wl-mlt-switcher-block',
				'render_callback' => array( $this, 'render_block' ),
				'attributes'      => array(
					'style'            => array( 'type' => 'string', 'default' => 'list' ),
					'showFlags'        => array( 'type' => 'boolean', 'default' => true ),
					'showNames'        => array( 'type' => 'boolean', 'default' => true ),
					'hideUntranslated' => array( 'type' => 'boolean', 'default' => false ),
				),
			)
		);
	}

	public function render_block( $attributes ): string {
		return self::render(
			array(
				'style'             => $attributes['style'] ?? 'list',
				'show_flags'        => $attributes['showFlags'] ?? true,
				'show_names'        => $attributes['showNames'] ?? true,
				'hide_untranslated' => $attributes['hideUntranslated'] ?? false,
			)
		);
	}

	/**
	 * @return array<int,array{code:string,name:string,flag:string,url:string,current:bool,translated:bool}>
	 */
	public static function items( bool $hide_untranslated = false ): array {
		// Editor previews (REST) and pages without a counterpart link to the home page of each language.
		$alternates = defined( 'REST_REQUEST' ) && REST_REQUEST ? array() : Alternates::for_current();
		$items      = array();

		foreach ( Languages::all() as $code => $language ) {
			$translated = isset( $alternates[ $code ] ) || ! $alternates;

			if ( $hide_untranslated && ! $translated ) {
				continue;
			}

			$is_current = Context::lang() === $code;

			$items[] = array(
				'code'       => $code,
				'name'       => $language['name'],
				'flag'       => Languages::flag_emoji( $language['flag'] ),
				'url'        => $alternates[ $code ] ?? ( $is_current && $alternates ? Url::current() : Alternates::in_lang( $code, static fn() => home_url( '/' ) ) ),
				'current'    => $is_current,
				'translated' => $translated,
			);
		}

		return $items;
	}

	public static function render( array $args = array() ): string {
		$args  = array_merge( self::defaults(), $args );
		$items = self::items( (bool) $args['hide_untranslated'] );

		if ( count( $items ) < 2 ) {
			return '';
		}

		$current = current( array_filter( $items, static fn( $item ) => $item['current'] ) ) ?: $items[0];
		$label   = static function ( array $item ) use ( $args ): string {
			$out = '';

			if ( $args['show_flags'] && '' !== $item['flag'] ) {
				$out .= '<span class="wl-mlt-switcher__flag" aria-hidden="true">' . esc_html( $item['flag'] ) . '</span>';
			}

			if ( $args['show_names'] || ! $args['show_flags'] ) {
				$out .= '<span class="wl-mlt-switcher__name">' . esc_html( $item['name'] ) . '</span>';
			} else {
				$out .= '<span class="screen-reader-text">' . esc_html( $item['name'] ) . '</span>';
			}

			return $out;
		};

		$template = locate_template( 'weblorem-multilang-tabs/switcher.php' );

		if ( '' === $template ) {
			$template = WL_MLT_DIR . 'templates/switcher.php';
			wp_enqueue_style( 'wl-mlt-switcher', WL_MLT_URL . 'assets/css/switcher.css', array(), WL_MLT_VERSION );
		}

		ob_start();
		include $template;
		return (string) ob_get_clean();
	}
}
