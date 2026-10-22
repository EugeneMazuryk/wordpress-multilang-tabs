<?php
/**
 * Plugin Name:       WebLorem Multilang Tabs
 * Description:       Multilingual content stored inside a single post/term, with one language tab bar in the editor.
 * Version:           0.1.0
 * Requires at least: 6.4
 * Requires PHP:      8.0
 * Author:            WebLorem
 * Text Domain:       weblorem-multilang-tabs
 * Domain Path:       /languages
 */

defined( 'ABSPATH' ) || exit;

define( 'WL_MLT_VERSION', '0.1.0' );
define( 'WL_MLT_FILE', __FILE__ );
define( 'WL_MLT_DIR', plugin_dir_path( __FILE__ ) );
define( 'WL_MLT_URL', plugin_dir_url( __FILE__ ) );

require_once WL_MLT_DIR . 'src/Core/Autoloader.php';
WebLorem\MultilangTabs\Core\Autoloader::register( 'WebLorem\\MultilangTabs\\', WL_MLT_DIR . 'src/' );

require_once WL_MLT_DIR . 'src/Api/functions.php';

register_activation_hook( __FILE__, array( WebLorem\MultilangTabs\Core\Settings::class, 'ensure_defaults' ) );

add_action( 'plugins_loaded', array( WebLorem\MultilangTabs\Plugin::class, 'boot' ), 1 );
