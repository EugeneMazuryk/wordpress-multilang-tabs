<?php
namespace WebLorem\MultilangTabs;

use WebLorem\MultilangTabs\Admin\ImportExport;
use WebLorem\MultilangTabs\Admin\SettingsPage;
use WebLorem\MultilangTabs\Cli\Command;
use WebLorem\MultilangTabs\Core\Settings;
use WebLorem\MultilangTabs\Editor\BlockEditor;
use WebLorem\MultilangTabs\Editor\ClassicEditor;
use WebLorem\MultilangTabs\Editor\MenuEditor;
use WebLorem\MultilangTabs\Editor\OptionsEditor;
use WebLorem\MultilangTabs\Editor\PostSaver;
use WebLorem\MultilangTabs\Editor\TermEditor;
use WebLorem\MultilangTabs\Frontend\ContentFilters;
use WebLorem\MultilangTabs\Frontend\Switcher;
use WebLorem\MultilangTabs\Integrations\Acf\Acf;
use WebLorem\MultilangTabs\Integrations\Cf7\Cf7;
use WebLorem\MultilangTabs\Seo\Head;
use WebLorem\MultilangTabs\Seo\SeoPlugins;
use WebLorem\MultilangTabs\Seo\Sitemap;
use WebLorem\MultilangTabs\Routing\LinkFilters;
use WebLorem\MultilangTabs\Storage\Revisions;
use WebLorem\MultilangTabs\Routing\Router;

defined( 'ABSPATH' ) || exit;

final class Plugin {

	public static function boot(): void {
		load_plugin_textdomain( 'weblorem-multilang-tabs', false, dirname( plugin_basename( WL_MLT_FILE ) ) . '/languages' );

		add_action( 'update_option_' . Settings::OPTION, array( Settings::class, 'flush_cache' ) );
		add_action( 'add_option_' . Settings::OPTION, array( Settings::class, 'flush_cache' ) );

		Settings::ensure_defaults();

		( new Router() )->register();
		( new LinkFilters() )->register();
		( new ContentFilters() )->register();
		( new Revisions() )->register();
		( new Acf() )->register();
		( new Cf7() )->register();
		( new Head() )->register();
		( new Sitemap() )->register();
		( new SeoPlugins() )->register();
		( new Switcher() )->register();

		( new PostSaver() )->register();
		( new BlockEditor() )->register();

		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			\WP_CLI::add_command( 'wl-mlt', Command::class );
		}

		if ( is_admin() ) {
			( new SettingsPage() )->register();
			( new ImportExport() )->register();
			( new ClassicEditor() )->register();
			( new TermEditor() )->register();
			( new OptionsEditor() )->register();
			( new MenuEditor() )->register();
		}
	}
}
