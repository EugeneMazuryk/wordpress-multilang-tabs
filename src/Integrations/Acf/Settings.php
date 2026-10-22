<?php
namespace WebLorem\MultilangTabs\Integrations\Acf;

defined( 'ABSPATH' ) || exit;

/**
 * The "Translatable" setting on fields, and its default on field groups.
 */
final class Settings {

	public function register(): void {
		add_action( 'acf/render_field_settings', array( $this, 'field_setting' ) );
		add_action( 'acf/field_group/render_additional_group_settings', array( $this, 'group_setting' ) );
	}

	public function field_setting( $field ): void {
		if ( ! Rules::supported_type( (string) ( $field['type'] ?? '' ) ) ) {
			return;
		}

		acf_render_field_setting(
			$field,
			array(
				'label'        => __( 'Translation', 'weblorem-multilang-tabs' ),
				'instructions' => __( 'A translatable container (group, repeater, flexible content) translates everything inside it as one unit.', 'weblorem-multilang-tabs' ),
				'name'         => Rules::SETTING,
				'type'         => 'select',
				'choices'      => array(
					''  => __( 'Inherit from the field group', 'weblorem-multilang-tabs' ),
					'1' => __( 'Translatable', 'weblorem-multilang-tabs' ),
					'0' => __( 'Shared by all languages', 'weblorem-multilang-tabs' ),
				),
				'ui'           => 0,
			),
			true
		);
	}

	public function group_setting( $field_group ): void {
		acf_render_field_wrap(
			array(
				'label'        => __( 'Translatable fields', 'weblorem-multilang-tabs' ),
				'instructions' => __( 'Make every field in this group translatable unless a field says otherwise (WebLorem Multilang Tabs)', 'weblorem-multilang-tabs' ),
				'type'         => 'true_false',
				'ui'           => 1,
				'name'         => Rules::SETTING,
				'prefix'       => 'acf_field_group',
				'value'        => ! empty( $field_group[ Rules::SETTING ] ) ? 1 : 0,
			),
			'div',
			'field'
		);
	}
}
