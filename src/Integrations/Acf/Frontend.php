<?php
namespace WebLorem\MultilangTabs\Integrations\Acf;

use WebLorem\MultilangTabs\Core\Context;
use WebLorem\MultilangTabs\Storage\Translator;

defined( 'ABSPATH' ) || exit;

/**
 * On the front end a translatable field loads its _wl_mlt_{lang}_{name} value, falling back to the
 * default language's value when there is none. Sub-fields are loaded by ACF with their full names
 * (rep_0_title), so repeaters, groups and flexible content translate through the same hook.
 */
final class Frontend {

	public function register(): void {
		// Early, so the field type's own load_value() still processes whatever we return.
		add_filter( 'acf/load_value', array( $this, 'load_value' ), 5, 3 );
	}

	public function load_value( $value, $post_id, $field ) {
		if ( ! Context::translating() || ! is_array( $field ) || empty( $field['name'] ) || str_starts_with( (string) $field['name'], Translator::PREFIX ) ) {
			return $value;
		}

		if ( ! Rules::is_translatable( $field ) ) {
			return $value;
		}

		$translated_field         = $field;
		$translated_field['name'] = Translator::key( Context::lang(), $field['name'] );
		$translated               = acf_get_metadata_by_field( $post_id, $translated_field );

		return null === $translated || '' === $translated || array() === $translated ? $value : $translated;
	}
}
