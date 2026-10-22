( function ( wp ) {
	const { registerBlockType } = wp.blocks;
	const { createElement: el, Fragment } = wp.element;
	const { InspectorControls, useBlockProps } = wp.blockEditor;
	const { PanelBody, SelectControl, ToggleControl } = wp.components;
	const ServerSideRender = wp.serverSideRender;
	const __ = wp.i18n.__;

	registerBlockType( 'weblorem-multilang-tabs/switcher', {
		edit: function ( props ) {
			const a = props.attributes;
			const set = props.setAttributes;

			return el(
				Fragment,
				null,
				el(
					InspectorControls,
					null,
					el(
						PanelBody,
						{ title: __( 'Language switcher', 'weblorem-multilang-tabs' ) },
						el( SelectControl, {
							label: __( 'Style', 'weblorem-multilang-tabs' ),
							value: a.style,
							options: [
								{ label: __( 'List', 'weblorem-multilang-tabs' ), value: 'list' },
								{ label: __( 'Dropdown', 'weblorem-multilang-tabs' ), value: 'dropdown' },
							],
							onChange: ( v ) => set( { style: v } ),
						} ),
						el( ToggleControl, { label: __( 'Show flags', 'weblorem-multilang-tabs' ), checked: a.showFlags, onChange: ( v ) => set( { showFlags: v } ) } ),
						el( ToggleControl, { label: __( 'Show names', 'weblorem-multilang-tabs' ), checked: a.showNames, onChange: ( v ) => set( { showNames: v } ) } ),
						el( ToggleControl, { label: __( 'Hide untranslated languages', 'weblorem-multilang-tabs' ), checked: a.hideUntranslated, onChange: ( v ) => set( { hideUntranslated: v } ) } )
					)
				),
				el( 'div', useBlockProps(), el( ServerSideRender, { block: 'weblorem-multilang-tabs/switcher', attributes: a } ) )
			);
		},
		save: function () {
			return null;
		},
	} );
} )( window.wp );
