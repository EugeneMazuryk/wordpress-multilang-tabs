<?php
namespace WebLorem\MultilangTabs\Editor;

use WebLorem\MultilangTabs\Core\Languages;
use WebLorem\MultilangTabs\Storage\PostFields;
use WebLorem\MultilangTabs\Storage\Translator;

defined( 'ABSPATH' ) || exit;

/**
 * One tab bar above the classic edit screen that switches title, editor and excerpt together.
 */
final class ClassicEditor {

	public function register(): void {
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );
		add_action( 'edit_form_top', array( $this, 'render' ) );
	}

	private function applies( ?\WP_Post $post ): bool {
		return $post && PostFields::is_translatable_type( $post->post_type ) && ! use_block_editor_for_post( $post ) && count( Languages::all() ) > 1;
	}

	public function assets( string $hook ): void {
		if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
			return;
		}

		$post = get_post();

		if ( ! $this->applies( $post ) ) {
			return;
		}

		$fields = PostFields::for_post_type( $post->post_type );
		$langs  = array();

		foreach ( Languages::all() as $code => $language ) {
			$langs[] = array(
				'code'      => $code,
				'name'      => $language['name'],
				'flag'      => Languages::flag_emoji( $language['flag'] ),
				'isDefault' => Languages::default_code() === $code,
			);
		}

		wp_enqueue_style( 'wl-mlt-editor', WL_MLT_URL . 'assets/css/editor.css', array(), WL_MLT_VERSION );
		wp_enqueue_script( 'wl-mlt-classic', WL_MLT_URL . 'assets/js/classic.js', array( 'jquery', 'editor', 'autosave' ), WL_MLT_VERSION, true );
		wp_localize_script(
			'wl-mlt-classic',
			'wlMlt',
			array(
				'defaultLang' => Languages::default_code(),
				'languages'   => $langs,
				'fields'      => array_values( array_diff( $fields, PostFields::meta_fields( $post->post_type ) ) ),
				'meta'        => PostFields::meta_fields( $post->post_type ),
				'data'        => Translator::all( $post->ID, $fields, array_keys( Languages::secondary() ) ),
			)
		);
	}

	public function render( $post ): void {
		if ( ! $post instanceof \WP_Post || ! $this->applies( $post ) ) {
			return;
		}

		?>
		<div id="wl-mlt-bar" class="wl-mlt-bar">
			<div class="wl-mlt-tabs" role="tablist" aria-label="<?php esc_attr_e( 'Content language', 'weblorem-multilang-tabs' ); ?>">
				<?php foreach ( Languages::all() as $code => $language ) : ?>
					<button type="button" role="tab" class="wl-mlt-tab" data-lang="<?php echo esc_attr( $code ); ?>" aria-selected="false">
						<span class="wl-mlt-flag"><?php echo esc_html( Languages::flag_emoji( $language['flag'] ) ); ?></span>
						<span class="wl-mlt-name"><?php echo esc_html( $language['name'] ); ?></span>
					</button>
				<?php endforeach; ?>
			</div>
			<input type="hidden" name="<?php echo esc_attr( PostSaver::DATA_FIELD ); ?>" id="wl-mlt-data" value="">
			<?php wp_nonce_field( PostSaver::NONCE_ACTION, PostSaver::NONCE_FIELD, false ); ?>
		</div>
		<?php
	}
}
