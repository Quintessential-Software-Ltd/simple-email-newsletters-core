/**
 * Newsletter Signup block (buildless).
 *
 * Server-rendered via ServerSideRender so the editor preview matches the front
 * end exactly. No JSX/build step — uses wp.element.createElement directly.
 */
( function ( blocks, element, blockEditor, components, serverSideRender, i18n ) {
	'use strict';

	var el = element.createElement;
	var __ = i18n.__;
	var InspectorControls = blockEditor.InspectorControls;
	var PanelBody = components.PanelBody;
	var TextControl = components.TextControl;
	var ToggleControl = components.ToggleControl;
	var ServerSideRender = serverSideRender;

	blocks.registerBlockType( 'quintessential-newsletters/subscribe', {
		edit: function ( props ) {
			var attributes = props.attributes;

			return el(
				element.Fragment,
				{},
				el(
					InspectorControls,
					{},
					el(
						PanelBody,
						{ title: __( 'Form settings', 'quintessential-newsletters' ), initialOpen: true },
						el( TextControl, {
							label: __( 'Title', 'quintessential-newsletters' ),
							value: attributes.title,
							onChange: function ( value ) {
								props.setAttributes( { title: value } );
							}
						} ),
						el( TextControl, {
							label: __( 'Description', 'quintessential-newsletters' ),
							value: attributes.description,
							onChange: function ( value ) {
								props.setAttributes( { description: value } );
							}
						} ),
						el( TextControl, {
							label: __( 'Button label', 'quintessential-newsletters' ),
							value: attributes.button,
							onChange: function ( value ) {
								props.setAttributes( { button: value } );
							}
						} ),
						el( ToggleControl, {
							label: __( 'Show name field', 'quintessential-newsletters' ),
							checked: attributes.showName,
							onChange: function ( value ) {
								props.setAttributes( { showName: value } );
							}
						} )
					)
				),
				el( ServerSideRender, {
					block: 'quintessential-newsletters/subscribe',
					attributes: attributes
				} )
			);
		},
		save: function () {
			return null; // Dynamic block — rendered in PHP.
		}
	} );
}(
	window.wp.blocks,
	window.wp.element,
	window.wp.blockEditor,
	window.wp.components,
	window.wp.serverSideRender,
	window.wp.i18n
) );
