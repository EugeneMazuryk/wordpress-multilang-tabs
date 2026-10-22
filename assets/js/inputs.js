/* global wlMlt, jQuery */
/**
 * Language tabs for plain admin forms (term edit screen, general settings, ...).
 * wlMlt.screen: { form, mount, fields: [{ key, selector }], data: { lang: { key: value } },
 *                 dataField, nonceName, nonce }
 */
( function ( $ ) {
	'use strict';

	if ( typeof wlMlt === 'undefined' || ! wlMlt.screen ) {
		return;
	}

	var cfg = wlMlt.screen;
	var def = wlMlt.defaultLang;
	var $form = $( cfg.form ).first();

	if ( ! $form.length ) {
		return;
	}

	// Only fields that exist on this screen.
	var fields = cfg.fields.filter( function ( f ) {
		return $( f.selector ).length;
	} );

	if ( ! fields.length ) {
		return;
	}

	var current = def;
	var buffers = {};
	var $tabs;
	var $data = $( '<input type="hidden">' ).attr( 'name', cfg.dataField );

	wlMlt.languages.forEach( function ( lang ) {
		if ( ! lang.isDefault ) {
			buffers[ lang.code ] = {};
			fields.forEach( function ( f ) {
				buffers[ lang.code ][ f.key ] = ( cfg.data[ lang.code ] || {} )[ f.key ] || '';
			} );
		}
	} );

	function read() {
		var buf = {};

		fields.forEach( function ( f ) {
			buf[ f.key ] = $( f.selector ).val();
		} );

		return buf;
	}

	function load( lang ) {
		var buf = buffers[ lang ] || {};

		fields.forEach( function ( f ) {
			$( f.selector ).val( buf[ f.key ] || '' );
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
		if ( lang === current || ( lang !== def && ! buffers[ lang ] ) ) {
			return;
		}

		buffers[ current ] = read();
		current = lang;
		load( lang );
		$( document.body ).toggleClass( 'wl-mlt-translating', lang !== def );
		updateTabs();
		window.wlMltLang = lang;
		document.dispatchEvent( new CustomEvent( 'wl-mlt:switch', { detail: { lang: lang } } ) );
	}

	function serialize() {
		var out = {};

		Object.keys( buffers ).forEach( function ( lang ) {
			if ( lang !== def ) {
				out[ lang ] = buffers[ lang ];
			}
		} );

		return JSON.stringify( out );
	}

	// The real fields must hold the default language when the form is sent.
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

	// Tab bar.
	var $bar = $( '<div class="wl-mlt-bar"><div class="wl-mlt-tabs" role="tablist"></div></div>' );
	var $list = $bar.find( '.wl-mlt-tabs' );

	wlMlt.languages.forEach( function ( lang ) {
		$list.append(
			$( '<button type="button" role="tab" class="wl-mlt-tab" aria-selected="false"></button>' )
				.attr( 'data-lang', lang.code )
				.append( $( '<span class="wl-mlt-flag"></span>' ).text( lang.flag ) )
				.append( $( '<span class="wl-mlt-name"></span>' ).text( lang.name ) )
		);
	} );

	$bar.append( $data ).append( $( '<input type="hidden">' ).attr( { name: cfg.nonceName, value: cfg.nonce } ) );
	$( cfg.mount ).first().before( $bar );
	$tabs = $list.find( '.wl-mlt-tab' );

	$tabs.on( 'click', function () {
		switchTo( this.getAttribute( 'data-lang' ) );
	} );

	$list.on( 'keydown', '.wl-mlt-tab', function ( e ) {
		var index = $tabs.index( this );
		var next = 'ArrowRight' === e.key ? index + 1 : ( 'ArrowLeft' === e.key ? index - 1 : null );

		if ( null === next || ! $tabs[ next ] ) {
			return;
		}

		e.preventDefault();
		$tabs.eq( next ).trigger( 'focus' ).trigger( 'click' );
	} );

	var timer;

	$form.on( 'input change', function () {
		clearTimeout( timer );
		timer = setTimeout( updateTabs, 300 );
	} );
	$form.on( 'submit', prepareSubmit );

	updateTabs();
} )( jQuery );
