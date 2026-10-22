/* global wlMltAcf, acf, jQuery */
/**
 * Puts the per-language copies of translatable ACF fields (printed as <template>s by AdminCopies)
 * next to their originals and shows the ones that belong to the language chosen in the tab bar.
 * The tab bar itself comes from the editor scripts; on screens without one a small bar is created.
 */
( function ( $ ) {
	'use strict';

	if ( typeof wlMltAcf === 'undefined' || typeof acf === 'undefined' ) {
		return;
	}

	var def = wlMltAcf.defaultLang;
	var current = window.wlMltLang || def;
	var placed = {};
	var pending = 0;

	function originalOf( key ) {
		return $( '.acf-field[data-key="' + key + '"]' ).not( '.wl-mlt-acf-copy' ).first();
	}

	function place() {
		var waiting = 0;

		$( 'template.wl-mlt-acf-copy' ).each( function () {
			var id = this.getAttribute( 'data-key' ) + '|' + this.getAttribute( 'data-lang' );

			if ( placed[ id ] ) {
				return;
			}

			var key = this.getAttribute( 'data-key' );
			var lang = this.getAttribute( 'data-lang' );
			var $orig = originalOf( key );

			if ( ! $orig.length ) {
				waiting++;

				return;
			}

			var $copy = $( $.parseHTML( this.innerHTML.trim() ) ).filter( '.acf-field' ).first();

			if ( ! $copy.length ) {
				placed[ id ] = true;

				return;
			}

			$orig.addClass( 'wl-mlt-acf-orig' );
			$copy.addClass( 'wl-mlt-acf-copy wl-mlt-hidden' ).attr( 'data-wl-lang', lang );

			// Keep the languages in order: the copy goes after the last copy of this field, or the original.
			var $anchor = $orig.nextAll( '.wl-mlt-acf-copy[data-key="' + key + '"]' ).last();

			( $anchor.length ? $anchor : $orig ).after( $copy );
			placed[ id ] = true;

			// ACF initialises fields (TinyMCE, repeaters, uploaders) when they are appended.
			acf.doAction( 'append', $copy );
			show( current );
		} );

		return waiting;
	}

	// A copy sits in the same ACF tab as its original: mirror the original's hidden state (ACF hides the fields of inactive tabs with acf-hidden).
	function syncTabs() {
		$( '.wl-mlt-acf-copy' ).each( function () {
			var $orig = originalOf( this.getAttribute( 'data-key' ) );

			$( this ).toggleClass( 'acf-hidden', $orig.hasClass( 'acf-hidden' ) );
		} );
	}

	function show( lang ) {
		current = lang;
		window.wlMltLang = lang;

		$( '.wl-mlt-acf-orig' ).toggleClass( 'wl-mlt-hidden', lang !== def );
		$( '.wl-mlt-acf-copy' ).each( function () {
			$( this ).toggleClass( 'wl-mlt-hidden', this.getAttribute( 'data-wl-lang' ) !== lang );
		} );
		syncTabs();
	}

	// A bar of our own, only where no editor script has put one.
	function ensureBar() {
		if ( $( '.wl-mlt-bar' ).length ) {
			return;
		}

		var $bar = $( '<div class="wl-mlt-bar"><div class="wl-mlt-tabs" role="tablist"></div></div>' );
		var $list = $bar.find( '.wl-mlt-tabs' );

		wlMltAcf.languages.forEach( function ( lang ) {
			$list.append(
				$( '<button type="button" role="tab" class="wl-mlt-tab" aria-selected="false"></button>' )
					.attr( 'data-lang', lang.code )
					.append( $( '<span class="wl-mlt-flag"></span>' ).text( lang.flag ) )
					.append( $( '<span class="wl-mlt-name"></span>' ).text( lang.name ) )
			);
		} );

		$list.on( 'click', '.wl-mlt-tab', function () {
			var lang = this.getAttribute( 'data-lang' );

			$list.find( '.wl-mlt-tab' ).each( function () {
				this.setAttribute( 'aria-selected', this.getAttribute( 'data-lang' ) === lang ? 'true' : 'false' );
			} );
			document.dispatchEvent( new CustomEvent( 'wl-mlt:switch', { detail: { lang: lang } } ) );
		} );
		$list.find( '[data-lang="' + def + '"]' ).attr( 'aria-selected', 'true' );

		var $target = $( '#poststuff, .acf-settings-wrap form' ).first();

		if ( $target.length ) {
			$target.before( $bar );
		} else {
			$( '#wpbody-content .wrap > h1' ).first().after( $bar );
		}
	}

	document.addEventListener( 'wl-mlt:switch', function ( e ) {
		show( e.detail.lang );
	} );

	// ACF toggles acf-hidden on the originals when a tab is chosen (and once more when it initialises).
	function watchTabs() {
		if ( 'undefined' === typeof MutationObserver ) {
			return;
		}

		new MutationObserver( syncTabs ).observe( document.body, { subtree: true, attributes: true, attributeFilter: [ 'class' ] } );
	}

	// ACF (and the block editor's meta boxes) may need a moment before the originals exist.
	function start() {
		ensureBar();
		watchTabs();
		setTimeout( syncTabs, 300 );

		var timer = setInterval( function () {
			if ( 0 === place() || ++pending > 100 ) {
				clearInterval( timer );
			}
		}, 250 );

		place();
	}

	if ( acf.addAction ) {
		acf.addAction( 'ready', start );
	} else {
		$( start );
	}
} )( jQuery );
