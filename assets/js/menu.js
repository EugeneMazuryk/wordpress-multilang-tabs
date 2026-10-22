/* global wlMlt, jQuery */
/**
 * One language tab bar for the whole menu editor: it swaps "Navigation Label" and "Description"
 * of every menu item at once. Items added later through the editor are picked up as they appear.
 */
( function ( $ ) {
	'use strict';

	var $form = $( '#update-nav-menu' );

	if ( typeof wlMlt === 'undefined' || ! wlMlt.menu || ! $form.length ) {
		return;
	}

	var cfg = wlMlt.menu;
	var def = wlMlt.defaultLang;
	var FIELDS = [ 'title', 'description' ];
	var current = def;
	var defaults = {}; // item id -> { title, description } of the default language, kept while a translation is shown
	var trans = {}; // item id -> lang -> { title, description }
	var $tabs;
	var $data = $( '<input type="hidden">' ).attr( 'name', cfg.dataField );

	function itemId( li ) {
		var m = /menu-item-(\d+)/.exec( li.id || '' );

		return m ? m[ 1 ] : null;
	}

	function input( id, field ) {
		return $( '#edit-menu-item-' + field + '-' + id );
	}

	function ensure( id ) {
		if ( ! trans[ id ] ) {
			trans[ id ] = {};
			wlMlt.languages.forEach( function ( lang ) {
				if ( ! lang.isDefault ) {
					trans[ id ][ lang.code ] = $.extend( { title: '', description: '' }, ( cfg.data[ id ] || {} )[ lang.code ] || {} );
				}
			} );
		}
	}

	function read( id ) {
		return { title: input( id, 'title' ).val() || '', description: input( id, 'description' ).val() || '' };
	}

	function show( id, values ) {
		FIELDS.forEach( function ( field ) {
			input( id, field ).val( values[ field ] || '' );
		} );

		// Collapsed items show their label in the header.
		var label = values.title || ( defaults[ id ] || {} ).title || '';

		if ( label ) {
			$( '#menu-item-' + id ).find( '.menu-item-title' ).first().text( label );
		}
	}

	function ids() {
		var out = [];

		$( '#menu-to-edit li.menu-item' ).each( function () {
			var id = itemId( this );

			if ( id ) {
				out.push( id );
			}
		} );

		return out;
	}

	function snapshot() {
		ids().forEach( function ( id ) {
			ensure( id );

			if ( current === def ) {
				defaults[ id ] = read( id );
			} else {
				trans[ id ][ current ] = read( id );
			}
		} );
	}

	function loadAll( lang ) {
		ids().forEach( function ( id ) {
			ensure( id );
			show( id, lang === def ? ( defaults[ id ] || read( id ) ) : trans[ id ][ lang ] );
		} );
	}

	function updateTabs() {
		$tabs.each( function () {
			var lang = this.getAttribute( 'data-lang' );
			this.setAttribute( 'aria-selected', lang === current ? 'true' : 'false' );
			this.tabIndex = lang === current ? 0 : -1;
		} );
	}

	function switchTo( lang ) {
		if ( lang === current ) {
			return;
		}

		snapshot();
		current = lang;
		loadAll( lang );
		updateTabs();
	}

	function serialize() {
		var out = {};

		ids().forEach( function ( id ) {
			ensure( id );
			out[ id ] = {};
			Object.keys( trans[ id ] ).forEach( function ( lang ) {
				out[ id ][ lang ] = trans[ id ][ lang ];
			} );
		} );

		return JSON.stringify( out );
	}

	// The real inputs must hold the default language when the form is sent.
	function prepareSubmit() {
		var was = current;

		if ( was !== def ) {
			switchTo( def );
		}

		$data.val( serialize() );

		if ( was !== def ) {
			setTimeout( function () {
				switchTo( was );
			}, 0 );
		}
	}

	// A new item arrives with default-language values in its inputs.
	var list = document.getElementById( 'menu-to-edit' );

	if ( list && window.MutationObserver ) {
		new MutationObserver( function ( mutations ) {
			mutations.forEach( function ( mutation ) {
				Array.prototype.forEach.call( mutation.addedNodes, function ( node ) {
					var id = node.nodeType === 1 && node.matches( 'li.menu-item' ) ? itemId( node ) : null;

					if ( ! id || trans[ id ] ) {
						return;
					}

					ensure( id );
					defaults[ id ] = read( id );

					if ( current !== def ) {
						show( id, trans[ id ][ current ] );
					}
				} );
			} );
			updateTabs();
		} ).observe( list, { childList: true } );
	}

	var $bar = $( '<div class="wl-mlt-bar"><div class="wl-mlt-tabs" role="tablist"></div></div>' );
	var $tabList = $bar.find( '.wl-mlt-tabs' );

	wlMlt.languages.forEach( function ( lang ) {
		$tabList.append(
			$( '<button type="button" role="tab" class="wl-mlt-tab" aria-selected="false"></button>' )
				.attr( 'data-lang', lang.code )
				.append( $( '<span class="wl-mlt-flag"></span>' ).text( lang.flag ) )
				.append( $( '<span class="wl-mlt-name"></span>' ).text( lang.name ) )
		);
	} );

	$bar.append( $data ).append( $( '<input type="hidden">' ).attr( { name: cfg.nonceName, value: cfg.nonce } ) );
	$( '#nav-menu-header' ).after( $bar );
	$tabs = $tabList.find( '.wl-mlt-tab' );

	$tabs.on( 'click', function () {
		switchTo( this.getAttribute( 'data-lang' ) );
	} );

	$form.on( 'submit', prepareSubmit );

	ids().forEach( function ( id ) {
		ensure( id );
		defaults[ id ] = read( id );
	} );
	updateTabs();
} )( jQuery );
