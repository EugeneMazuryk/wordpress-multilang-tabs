<?php
namespace WebLorem\MultilangTabs\Admin;

use WebLorem\MultilangTabs\Core\Languages;
use WebLorem\MultilangTabs\Core\Settings;

defined( 'ABSPATH' ) || exit;

final class SettingsPage {

	public const SLUG = 'weblorem-multilang-tabs';

	public function register(): void {
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_init', array( $this, 'register_setting' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
	}

	public function menu(): void {
		add_options_page(
			__( 'WebLorem Multilang Tabs', 'weblorem-multilang-tabs' ),
			__( 'WebLorem Multilang Tabs', 'weblorem-multilang-tabs' ),
			'manage_options',
			self::SLUG,
			array( $this, 'render' )
		);
	}

	public function register_setting(): void {
		register_setting( 'wl_mlt', Settings::OPTION, array( 'sanitize_callback' => array( Settings::class, 'sanitize' ) ) );
	}

	public function assets( string $hook ): void {
		if ( 'settings_page_' . self::SLUG !== $hook ) {
			return;
		}

		wp_enqueue_style( 'wl-mlt-admin', WL_MLT_URL . 'assets/css/admin.css', array(), WL_MLT_VERSION );
		wp_enqueue_script( 'wl-mlt-settings', WL_MLT_URL . 'assets/js/settings.js', array(), WL_MLT_VERSION, true );
	}

	public function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$settings = Settings::get();
		$default  = Languages::default_code();
		$rows     = array_values( Languages::all() );
		?>
		<div class="wrap wl-mlt-settings">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			<?php settings_errors(); ?>
			<?php ImportExport::notice(); ?>
			<form action="options.php" method="post">
				<?php settings_fields( 'wl_mlt' ); ?>

				<h2><?php esc_html_e( 'Languages', 'weblorem-multilang-tabs' ); ?></h2>
				<p class="description"><?php esc_html_e( 'The default language has no URL prefix (/page/); the others do (/en/page/). The order here is the order of the editor tabs.', 'weblorem-multilang-tabs' ); ?></p>

				<table class="widefat striped wl-mlt-languages" id="wl-mlt-languages">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Default', 'weblorem-multilang-tabs' ); ?></th>
							<th><?php esc_html_e( 'Code (URL)', 'weblorem-multilang-tabs' ); ?></th>
							<th><?php esc_html_e( 'Name', 'weblorem-multilang-tabs' ); ?></th>
							<th><?php esc_html_e( 'Locale', 'weblorem-multilang-tabs' ); ?></th>
							<th><?php esc_html_e( 'Flag (country code)', 'weblorem-multilang-tabs' ); ?></th>
							<th></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $rows as $i => $row ) : ?>
							<?php $this->row( (string) $i, $row, $row['code'] === $default ); ?>
						<?php endforeach; ?>
					</tbody>
				</table>
				<p><button type="button" class="button" id="wl-mlt-add"><?php esc_html_e( 'Add language', 'weblorem-multilang-tabs' ); ?></button></p>

				<template id="wl-mlt-row-template">
					<?php $this->row( '__i__', array( 'code' => '', 'name' => '', 'locale' => '', 'flag' => '' ), false ); ?>
				</template>

				<h2><?php esc_html_e( 'Translatable content', 'weblorem-multilang-tabs' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th><?php esc_html_e( 'Post types', 'weblorem-multilang-tabs' ); ?></th>
						<td><?php $this->checkboxes( 'post_types', get_post_types( array( 'public' => true ), 'objects' ), $settings['post_types'], array( 'attachment' ) ); ?></td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Taxonomies', 'weblorem-multilang-tabs' ); ?></th>
						<td><?php $this->checkboxes( 'taxonomies', get_taxonomies( array( 'public' => true ), 'objects' ), $settings['taxonomies'] ); ?></td>
					</tr>
				</table>

				<h2><?php esc_html_e( 'Behaviour', 'weblorem-multilang-tabs' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th><?php esc_html_e( 'Browser language', 'weblorem-multilang-tabs' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="<?php echo esc_attr( Settings::OPTION ); ?>[auto_redirect]" value="1" <?php checked( $settings['auto_redirect'] ); ?>>
								<?php esc_html_e( 'On the first visit to the home page, redirect to the language from the Accept-Language header', 'weblorem-multilang-tabs' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'On uninstall', 'weblorem-multilang-tabs' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="<?php echo esc_attr( Settings::OPTION ); ?>[delete_data]" value="1" <?php checked( $settings['delete_data'] ); ?>>
								<?php esc_html_e( 'Delete all translations and settings when the plugin is deleted', 'weblorem-multilang-tabs' ); ?>
							</label>
						</td>
					</tr>
				</table>

				<?php submit_button(); ?>
			</form>

			<h2><?php esc_html_e( 'Export and import', 'weblorem-multilang-tabs' ); ?></h2>
			<p class="description"><?php esc_html_e( 'Translations only. Posts, terms and menu items are matched by slug on import, so the file works between environments.', 'weblorem-multilang-tabs' ); ?></p>
			<p><a class="button" href="<?php echo esc_url( ImportExport::export_url() ); ?>"><?php esc_html_e( 'Download export (JSON)', 'weblorem-multilang-tabs' ); ?></a></p>
			<form action="<?php echo esc_url( ImportExport::import_action() ); ?>" method="post" enctype="multipart/form-data">
				<?php ImportExport::import_fields(); ?>
				<p><input type="file" name="wl_mlt_file" accept="application/json,.json"></p>
				<p>
					<label><input type="checkbox" name="replace" value="1"> <?php esc_html_e( 'Replace existing translations of the matched items', 'weblorem-multilang-tabs' ); ?></label><br>
					<label><input type="checkbox" name="dry_run" value="1"> <?php esc_html_e( 'Dry run (only report)', 'weblorem-multilang-tabs' ); ?></label>
				</p>
				<?php submit_button( __( 'Import', 'weblorem-multilang-tabs' ), 'secondary' ); ?>
			</form>
		</div>
		<?php
	}

	private function row( string $index, array $row, bool $is_default ): void {
		$name = Settings::OPTION . '[languages][' . $index . ']';
		?>
		<tr class="wl-mlt-row">
			<td><input type="radio" name="<?php echo esc_attr( Settings::OPTION ); ?>[default]" value="<?php echo esc_attr( $index ); ?>" <?php checked( $is_default ); ?>></td>
			<td><input type="text" class="small-text" name="<?php echo esc_attr( $name ); ?>[code]" value="<?php echo esc_attr( $row['code'] ); ?>" pattern="[a-z]{2,3}(-[a-z0-9]{2,8})?" required></td>
			<td><input type="text" name="<?php echo esc_attr( $name ); ?>[name]" value="<?php echo esc_attr( $row['name'] ); ?>"></td>
			<td><input type="text" class="small-text" name="<?php echo esc_attr( $name ); ?>[locale]" value="<?php echo esc_attr( $row['locale'] ); ?>" placeholder="en_US"></td>
			<td>
				<input type="text" class="small-text" name="<?php echo esc_attr( $name ); ?>[flag]" value="<?php echo esc_attr( $row['flag'] ); ?>" maxlength="2" placeholder="gb">
				<span class="wl-mlt-flag"><?php echo esc_html( Languages::flag_emoji( $row['flag'] ) ); ?></span>
			</td>
			<td class="wl-mlt-actions">
				<button type="button" class="button-link" data-wl-mlt="up" title="<?php esc_attr_e( 'Move up', 'weblorem-multilang-tabs' ); ?>">&uarr;</button>
				<button type="button" class="button-link" data-wl-mlt="down" title="<?php esc_attr_e( 'Move down', 'weblorem-multilang-tabs' ); ?>">&darr;</button>
				<button type="button" class="button-link-delete" data-wl-mlt="remove"><?php esc_html_e( 'Remove', 'weblorem-multilang-tabs' ); ?></button>
			</td>
		</tr>
		<?php
	}

	private function checkboxes( string $key, array $objects, array $checked, array $skip = array() ): void {
		foreach ( $objects as $slug => $object ) {
			if ( in_array( $slug, $skip, true ) ) {
				continue;
			}
			?>
			<label class="wl-mlt-check">
				<input type="checkbox" name="<?php echo esc_attr( Settings::OPTION ); ?>[<?php echo esc_attr( $key ); ?>][]" value="<?php echo esc_attr( $slug ); ?>" <?php checked( in_array( $slug, $checked, true ) ); ?>>
				<?php echo esc_html( $object->labels->name ); ?>
			</label>
			<?php
		}
	}
}
