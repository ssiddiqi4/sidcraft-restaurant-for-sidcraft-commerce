/**
 * Sidcraft Restaurant — block editor controls.
 *
 * Every block is rendered on the server (the same output as the Sidcraft Page
 * Builder Units and shortcodes). This file builds each block's sidebar
 * settings from the schema the server sends (window.sidrestBlocks).
 */
( function ( wp ) {
	'use strict';

	var cfg = window.sidrestBlocks;
	if ( ! wp || ! wp.blocks || ! cfg || ! cfg.blocks ) {
		return;
	}
	var el = wp.element.createElement;
	var Fragment = wp.element.Fragment;
	var be = wp.blockEditor;
	var c = wp.components;
	var ServerSideRender = wp.serverSideRender;

	function visible( control, attributes ) {
		if ( ! control.condition ) {
			return true;
		}
		return Object.keys( control.condition ).every( function ( key ) {
			var want = control.condition[ key ];
			return Array.isArray( want ) ? want.indexOf( attributes[ key ] ) > -1 : attributes[ key ] === want;
		} );
	}

	function field( key, control, attributes, setAttributes ) {
		var set = function ( value ) {
			var patch = {};
			patch[ key ] = value;
			setAttributes( patch );
		};
		var common = { key: key, label: control.label, help: control.help || undefined, __nextHasNoMarginBottom: true };

		switch ( control.type ) {
			case 'select':
				return el( c.SelectControl, Object.assign( {}, common, {
					value: String( attributes[ key ] == null ? '' : attributes[ key ] ),
					options: control.options,
					onChange: set,
				} ) );
			case 'switch':
				return el( c.ToggleControl, Object.assign( {}, common, {
					checked: !! attributes[ key ],
					onChange: set,
				} ) );
			case 'number':
				return el( c.RangeControl, Object.assign( {}, common, {
					value: Number( attributes[ key ] ) || 0,
					min: control.min == null ? 0 : control.min,
					max: control.max == null ? 100 : control.max,
					onChange: function ( v ) {
						set( Number( v ) || 0 );
					},
				} ) );
			case 'color':
				return el( 'div', { key: key, className: 'sidrest-block-color' },
					el( 'p', { className: 'components-base-control__label' }, control.label ),
					el( c.ColorPalette, {
						value: attributes[ key ] || undefined,
						onChange: function ( v ) {
							set( v || '' );
						},
					} )
				);
			default:
				return el( c.TextControl, Object.assign( {}, common, {
					value: attributes[ key ] || '',
					onChange: set,
				} ) );
		}
	}

	Object.keys( cfg.blocks ).forEach( function ( name ) {
		var block = cfg.blocks[ name ];

		wp.blocks.registerBlockType( name, {
			apiVersion: 3,
			title: block.title,
			description: block.description,
			icon: block.icon,
			category: 'sidcraft-restaurant',
			keywords: [ 'restaurant', 'menu', 'food' ],
			edit: function ( props ) {
				var blockProps = be.useBlockProps ? be.useBlockProps() : {};
				var fields = Object.keys( block.controls )
					.filter( function ( key ) {
						return visible( block.controls[ key ], props.attributes );
					} )
					.map( function ( key ) {
						return field( key, block.controls[ key ], props.attributes, props.setAttributes );
					} );

				return el( Fragment, null,
					el( be.InspectorControls, null,
						el( c.PanelBody, { title: block.title, initialOpen: true }, fields )
					),
					el( 'div', blockProps,
						el( ServerSideRender, {
							block: name,
							attributes: props.attributes,
							EmptyResponsePlaceholder: function () {
								return el( c.Placeholder, { icon: block.icon, label: block.title, instructions: block.description } );
							},
						} )
					)
				);
			},
			save: function () {
				return null;
			},
		} );
	} );
}( window.wp ) );
