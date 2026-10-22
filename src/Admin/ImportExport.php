<?php
namespace WebLorem\MultilangTabs\Admin;

use WebLorem\MultilangTabs\Tools\Exporter;
use WebLorem\MultilangTabs\Tools\Importer;

defined( 'ABSPATH' ) || exit;

/**
 * Export and import of translations from the settings page (the same code as `wp wl-mlt export|import`).
 */
final class ImportExport {

	private const EXPORT = 'wl_mlt_export';
	private const IMPORT = 'wl_mlt_import';
	private const NOTICE = 'wl_mlt_import_notice_';

	public function register(): void {
		add_action( 'admin_post_' . self::EXPORT, array( $this, 'export' ) );
		add_action( 'admin_post_' . self::IMPORT, array( $this, 'import' ) );
	}

	public static function export_url(): string {
		return wp_nonce_url( admin_url( 'admin-post.php?action=' . self::EXPORT ), self::EXPORT );
	}

	public static function import_action(): string {
		return admin_url( 'admin-post.php' );
	}

	public static function import_fields(): void {
		wp_nonce_field( self::IMPORT );
		echo '<input type="hidden" name="action" value="' . esc_attr( self::IMPORT ) . '">';
	}

	/** Shows (once) what the last import did. */
	public static function notice(): void {
		$message = get_transient( self::NOTICE . get_current_user_id() );

		if ( is_array( $message ) ) {
			delete_transient( self::NOTICE . get_current_user_id() );
			printf( '<div class="notice notice-%s"><p>%s</p></div>', esc_attr( $message['type'] ), esc_html( $message['text'] ) );
		}
	}

	public function export(): void {
		$this->guard( self::EXPORT, '_wpnonce' );

		$json = wp_json_encode( Exporter::build(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );

		nocache_headers();
		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="wl-mlt-translations-' . gmdate( 'Y-m-d' ) . '.json"' );
		echo $json; // phpcs:ignore WordPress.Security.EscapeOutput -- JSON download.
		exit;
	}

	public function import(): void {
		$this->guard( self::IMPORT, '_wpnonce' );

		$type = 'error';
		$tmp  = (string) ( $_FILES['wl_mlt_file']['tmp_name'] ?? '' ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput

		if ( '' === $tmp || ! is_uploaded_file( $tmp ) ) {
			$text = __( 'Choose a JSON file exported by this plugin.', 'weblorem-multilang-tabs' );
		} else {
			$data = json_decode( (string) file_get_contents( $tmp ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions

			try {
				if ( ! is_array( $data ) ) {
					throw new \InvalidArgumentException( __( 'The file is not valid JSON.', 'weblorem-multilang-tabs' ) );
				}

				$report = Importer::run(
					$data,
					array(
						'replace' => ! empty( $_POST['replace'] ),
						'dry_run' => ! empty( $_POST['dry_run'] ),
					)
				);

				$type = $report['missing'] ? 'warning' : 'success';
				$text = sprintf(
					/* translators: 1: dry run prefix, 2: keys, 3: posts, 4: terms, 5: menu items, 6: options, 7: not found */
					__( '%1$s%2$d keys: %3$d posts, %4$d terms, %5$d menu items, %6$d options. Not found on this site: %7$d.', 'weblorem-multilang-tabs' ),
					! empty( $_POST['dry_run'] ) ? __( '[Dry run] ', 'weblorem-multilang-tabs' ) : '',
					$report['keys'],
					$report['updated']['posts'],
					$report['updated']['terms'],
					$report['updated']['menu_items'],
					$report['updated']['options'],
					count( $report['missing'] )
				);
			} catch ( \InvalidArgumentException $e ) {
				$text = $e->getMessage();
			}
		}

		set_transient( self::NOTICE . get_current_user_id(), compact( 'type', 'text' ), MINUTE_IN_SECONDS );
		wp_safe_redirect( admin_url( 'options-general.php?page=' . SettingsPage::SLUG ) );
		exit;
	}

	private function guard( string $action, string $field ): void {
		if ( ! current_user_can( 'manage_options' ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_REQUEST[ $field ] ?? '' ) ), $action ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'weblorem-multilang-tabs' ), '', array( 'response' => 403 ) );
		}
	}
}
