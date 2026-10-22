/* global wlMlt, jQuery, tinymce, switchEditors, wp */
( function ( $ ) {
	'use strict';

	var $form = $( '#post' );

	if ( typeof wlMlt === 'undefined' || ! $form.length ) {
		return;
	}

	var def = wlMlt.defaultLang;
	var $bar = $( '#wl-mlt-bar' );
	var $data = $( '#wl-mlt-data' );
	var $title = $( '#title' );
	var $excerpt = $( '#excerpt' );
	var $content = $( '#content' );
	var $tabs = $bar.find( '.wl-mlt-tab' );

	var meta = wlMlt.meta || [];
	var current = def;
	var dirty = false;
	var buffers = {};

	wlMlt.languages.forEach( function ( lang ) {
		if ( ! lang.isDefault ) {
			buffers[ lang.code ] = $.extend( { title: '', content: '', excerpt: '' }, wlMlt.data[ lang.code ] || {} );
		}
	} );

	function has( field ) {
		return wlMlt.fields.indexOf( field ) !== -1;
	}

	// Registered custom meta: the input of a meta box named after the key.
	function metaInput( key ) {
		return $( '[name="' + key + '"]' ).not( '[type="hidden"]' ).first();
	}

	function visualEditor() {
		var ed = typeof tinymce !== 'undefined' ? tinymce.get( 'content' ) : null;

		return ed && ! ed.isHidden() ? ed : null;
	}

	function getContent() {
		var ed = visualEditor();

		if ( ed ) {
			ed.save();
		}

		return $content.val();
	}

	function setContent( html ) {
		var ed = visualEditor();

		$content.val( html );

		if ( ! ed ) {
			return;
		}

		ed.setContent( typeof switchEditors !== 'undefined' ? switchEditors.wpautop( html ) : html );

		try {
			ed.undoManager.clear();
			ed.setDirty( false );
		} catch ( e ) {}
	}

	function syncTitlePrompt() {
		$( '#title-prompt-text' ).toggleClass( 'screen-reader-text', '' !== $title.val() );
	}

	function read( lang ) {
		var buf = {};

		if ( has( 'title' ) ) {
			buf.title = $title.val();
		}

		if ( has( 'content' ) ) {
			buf.content = getContent();
		}

		if ( has( 'excerpt' ) ) {
			buf.excerpt = $excerpt.val();
		}

		meta.forEach( function ( key ) {
			buf[ key ] = metaInput( key ).val() || '';
		} );

		return buf;
	}

	function load( lang ) {
		var buf = buffers[ lang ] || {};

		if ( has( 'title' ) ) {
			$title.val( buf.title || '' );
			syncTitlePrompt();
		}

		if ( has( 'content' ) ) {
			setContent( buf.content || '' );
		}

		if ( has( 'excerpt' ) ) {
			$excerpt.val( buf.excerpt || '' );
		}

		meta.forEach( function ( key ) {
			metaInput( key ).val( buf[ key ] || '' );
		} );
	}

	function updateTabs() {
		$tabs.each( function () {
			var lang = this.getAttribute( 'data-lang' );
			this.setAttribute( 'aria-selected', lang === current ? 'true' : 'false' );
			this.tabIndex = lang === current ? 0 : -1;
		} );
	}

	function suspendAutosave( suspend ) {
		var method = suspend ? 'suspend' : 'resume';

		try {
			wp.autosave.server[ method ]();
			wp.autosave.local[ method ]();
		} catch ( e ) {}
	}

	function switchTo( lang ) {
		if ( lang === current || ( lang !== def && ! buffers[ lang ] ) ) {
			return;
		}

		buffers[ current ] = read( current );
		current = lang;
		load( lang );

		$( document.body ).toggleClass( 'wl-mlt-translating', lang !== def );
		suspendAutosave( lang !== def );
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

	// The standard fields always carry the default language when the form is sent.
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

	var timer;

	function scheduleUpdate() {
		clearTimeout( timer );
		timer = setTimeout( updateTabs, 400 );
	}

	function markDirty() {
		if ( current !== def ) {
			dirty = true;
		}

		scheduleUpdate();
	}

	$tabs.on( 'click', function () {
		switchTo( this.getAttribute( 'data-lang' ) );
	} );

	$bar.on( 'keydown', '.wl-mlt-tab', function ( e ) {
		var index = $tabs.index( this );
		var next = 'ArrowRight' === e.key ? index + 1 : ( 'ArrowLeft' === e.key ? index - 1 : null );

		if ( null === next || ! $tabs[ next ] ) {
			return;
		}

		e.preventDefault();
		$tabs.eq( next ).trigger( 'focus' ).trigger( 'click' );
	} );

	$title.add( $excerpt ).add( $content ).on( 'input change', markDirty );
	meta.forEach( function ( key ) {
		metaInput( key ).on( 'input change', markDirty );
	} );

	$( document ).on( 'tinymce-editor-init', function ( e, ed ) {
		if ( ed && 'content' === ed.id ) {
			ed.on( 'input change keyup', markDirty );
			updateTabs();
		}
	} );

	$form.on( 'submit', prepareSubmit );

	// Do not warn about "unsaved changes" just because a translation is on screen.
	if ( window.wp && wp.autosave && wp.autosave.server && wp.autosave.server.postChanged ) {
		var postChanged = wp.autosave.server.postChanged;

		wp.autosave.server.postChanged = function () {
			return current === def ? postChanged.apply( this, arguments ) || dirty : dirty;
		};
	}

	updateTabs();
} )( jQuery );
