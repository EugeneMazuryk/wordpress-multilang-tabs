/* global wlMlt, wp */
( function ( wp ) {
	'use strict';

	if ( typeof wlMlt === 'undefined' ) {
		return;
	}

	var el = wp.element.createElement;
	var useState = wp.element.useState;
	var useEffect = wp.element.useEffect;
	var select = wp.data.select;
	var dispatch = wp.data.dispatch;
	var def = wlMlt.defaultLang;
	var META_PREFIX = '_wl_mlt_';
	var EMPTY = /^(?:\s|&nbsp;|<br\s*\/?>|<p>\s*<\/p>|<!--[^>]*-->)*$/i;
	var LOCK = 'wl-mlt';

	var meta = wlMlt.meta || [];
	var fields = wlMlt.fields;
	var allFields = fields.concat( meta );
	var state = { cur: def, buffers: {}, saved: {}, listeners: [], applying: false };

	wlMlt.languages.forEach( function ( lang ) {
		if ( ! lang.isDefault ) {
			state.buffers[ lang.code ] = Object.assign( { title: '', content: '', excerpt: '' }, wlMlt.data[ lang.code ] || {} );
			state.saved[ lang.code ] = Object.assign( {}, state.buffers[ lang.code ] );
		}
	} );

	function has( field ) {
		return fields.indexOf( field ) !== -1;
	}

	function metaOf( source ) {
		return source || {};
	}

	function isEmpty( value ) {
		return ! value || EMPTY.test( value );
	}

	function emit() {
		state.listeners.forEach( function ( fn ) {
			fn();
		} );
	}

	function editor() {
		return select( 'core/editor' );
	}

	// Reads what the editor currently shows, for the fields this post type has.
	function readLive() {
		var ed = editor();
		var buf = {};

		if ( has( 'title' ) ) {
			buf.title = ed.getEditedPostAttribute( 'title' ) || '';
		}

		if ( has( 'content' ) ) {
			buf.content = ed.getEditedPostContent() || '';
		}

		if ( has( 'excerpt' ) ) {
			buf.excerpt = ed.getEditedPostAttribute( 'excerpt' ) || '';
		}

		var postMeta = metaOf( ed.getEditedPostAttribute( 'meta' ) );

		meta.forEach( function ( key ) {
			buf[ key ] = postMeta[ key ] || '';
		} );

		return buf;
	}

	// The values WordPress just stored (content is filtered), as a buffer.
	function savedBuffer() {
		var buf = {};

		fields.forEach( function ( field ) {
			var value = editor().getCurrentPostAttribute( field );

			if ( value && 'object' === typeof value ) {
				value = value.raw;
			}

			buf[ field ] = value || '';
		} );

		var postMeta = metaOf( editor().getCurrentPostAttribute( 'meta' ) );

		meta.forEach( function ( key ) {
			buf[ key ] = postMeta[ key ] || '';
		} );

		return buf;
	}

	// Pushes a buffer into the editor without creating undo levels.
	function apply( buf ) {
		var edits = {};
		var ed = dispatch( 'core/editor' );

		state.applying = true;

		// Only touch attributes that differ, so returning to saved values leaves the post clean.
		fields.forEach( function ( field ) {
			if ( 'content' !== field && ( editor().getEditedPostAttribute( field ) || '' ) !== ( buf[ field ] || '' ) ) {
				edits[ field ] = buf[ field ] || '';
			}
		} );

		var currentMeta = metaOf( editor().getEditedPostAttribute( 'meta' ) );
		var metaEdits = {};

		meta.forEach( function ( key ) {
			if ( ( currentMeta[ key ] || '' ) !== ( buf[ key ] || '' ) ) {
				metaEdits[ key ] = buf[ key ] || '';
			}
		} );

		if ( Object.keys( metaEdits ).length ) {
			edits.meta = metaEdits;
		}

		if ( Object.keys( edits ).length ) {
			ed.editPost( edits, { undoIgnore: true } );
		}

		if ( has( 'content' ) ) {
			ed.resetEditorBlocks( wp.blocks.parse( buf.content || '' ), { __unstableShouldCreateUndoLevel: false } );
		}

		state.applying = false;
	}

	// Translations that differ from what is saved, as REST meta ('' becomes null, which deletes the row).
	function changedMeta( lang ) {
		var meta = {};
		var buf = state.buffers[ lang ];

		allFields.forEach( function ( field ) {
			var value = buf[ field ] || '';

			if ( value !== ( state.saved[ lang ][ field ] || '' ) ) {
				meta[ META_PREFIX + lang + '_' + field ] = isEmpty( value ) ? null : value;
			}
		} );

		return meta;
	}

	// Mirrors changed translations into the post's meta edits, so "Save" is enabled even when only a translation changed.
	function syncMetaEdits( lang ) {
		var meta = changedMeta( lang );

		if ( Object.keys( meta ).length ) {
			dispatch( 'core/editor' ).editPost( { meta: meta }, { undoIgnore: true } );
		}
	}

	function setAutosaveLock( locked ) {
		var ed = dispatch( 'core/editor' );

		if ( locked ) {
			ed.lockPostAutosaving( LOCK );
		} else {
			ed.unlockPostAutosaving( LOCK );
		}
	}

	// Lets other scripts (ACF copies) follow the language.
	function announce( lang ) {
		window.wlMltLang = lang;
		document.dispatchEvent( new CustomEvent( 'wl-mlt:switch', { detail: { lang: lang } } ) );
	}

	function switchTo( lang ) {
		if ( lang === state.cur || state.applying || editor().isSavingPost() ) {
			return;
		}

		state.buffers[ state.cur ] = readLive();

		if ( state.cur !== def ) {
			syncMetaEdits( state.cur );
		}

		state.cur = lang;
		apply( state.buffers[ lang ] );
		setAutosaveLock( lang !== def );
		emit();
		announce( lang );
	}

	// ---- save request rewriting -------------------------------------------------------------

	function isPostRequest( options ) {
		var postId = editor().getCurrentPostId();
		var path = ( options.path || '' ).split( '?' )[ 0 ];

		return !! postId && ( path === '/wp/v2/' + wlMlt.restBase + '/' + postId || path === '/wp/v2/' + wlMlt.restBase + '/' + postId + '/autosaves' );
	}

	wp.apiFetch.use( function ( options, next ) {
		var method = ( options.method || 'GET' ).toUpperCase();

		if ( 'GET' === method || ! options.data || state.cur === def || ! isPostRequest( options ) ) {
			return next( options );
		}

		// The editor holds a translation: send the default language in the standard fields
		// and every translation as meta.
		var data = Object.assign( {}, options.data );
		var defaults = state.buffers[ def ] || {};
		var sent = {};

		state.buffers[ state.cur ] = readLive();

		fields.forEach( function ( field ) {
			if ( field in defaults ) {
				data[ field ] = defaults[ field ];
			}
		} );

		data.meta = Object.assign( {}, data.meta );

		meta.forEach( function ( key ) {
			if ( key in defaults ) {
				data.meta[ key ] = defaults[ key ];
			}
		} );
		Object.keys( state.buffers ).forEach( function ( lang ) {
			if ( lang !== def ) {
				Object.assign( data.meta, changedMeta( lang ) );
				sent[ lang ] = Object.assign( {}, state.buffers[ lang ] );
			}
		} );

		return next( Object.assign( {}, options, { data: data } ) ).then( function ( response ) {
			Object.keys( sent ).forEach( function ( lang ) {
				state.saved[ lang ] = sent[ lang ];
			} );

			return response;
		} );
	} );

	// After a successful save the editor holds the default language again (that is what was saved),
	// so the tab follows instead of showing a stale translation.
	var wasSaving = false;

	wp.data.subscribe( function () {
		var ed = editor();

		if ( ! ed || ! ed.isSavingPost ) {
			return;
		}

		var saving = ed.isSavingPost();

		if ( wasSaving && ! saving && state.cur !== def && ! ed.didPostSaveRequestFail() ) {
			state.cur = def;
			announce( def );
			setAutosaveLock( false );
			wp.data.dispatch( 'core/notices' ).createInfoNotice( wlMlt.i18n.saved, { type: 'snackbar' } );

			// Let core finish storing the saved record, then put the default blocks back
			// (the block store still holds the translation's blocks).
			setTimeout( function () {
				state.buffers[ def ] = savedBuffer();
				apply( state.buffers[ def ] );
				emit();
			}, 50 );
		}

		wasSaving = saving;
	} );

	// ---- UI ---------------------------------------------------------------------------------

	function useWlState() {
		var tick = useState( 0 );

		useEffect( function () {
			var fn = function () {
				tick[ 1 ]( function ( n ) {
					return n + 1;
				} );
			};

			state.listeners.push( fn );

			return function () {
				state.listeners = state.listeners.filter( function ( f ) {
					return f !== fn;
				} );
			};
		}, [] );
	}

	function TabBar() {
		useWlState();

		return el(
			'div',
			{ className: 'wl-mlt-tabs', role: 'tablist', 'aria-label': wlMlt.i18n.language },
			wlMlt.languages.map( function ( lang ) {
				var selected = lang.code === state.cur;

				return el(
					'button',
					{
						key: lang.code,
						type: 'button',
						role: 'tab',
						className: 'wl-mlt-tab',
						'aria-selected': selected ? 'true' : 'false',
						tabIndex: selected ? 0 : -1,
						onClick: function () {
							switchTo( lang.code );
						},
					},
					el( 'span', { className: 'wl-mlt-flag' }, lang.flag ),
					el( 'span', { className: 'wl-mlt-name' }, lang.name )
				);
			} )
		);
	}

	function Panel() {
		var Fill = wp.editor.PluginDocumentSettingPanel;

		return el( Fill, { name: 'wl-mlt-language', title: wlMlt.i18n.language, className: 'wl-mlt-panel' }, el( TabBar ) );
	}

	wp.plugins.registerPlugin( 'wl-mlt', { render: Panel } );

	// The same tab bar above the canvas, so it is visible while writing. Falls back to the sidebar panel alone.
	function mountTopBar() {
		var canvas = document.querySelector( '.editor-visual-editor' );

		if ( ! canvas || document.getElementById( 'wl-mlt-bar' ) ) {
			return !! document.getElementById( 'wl-mlt-bar' );
		}

		var host = document.createElement( 'div' );

		host.id = 'wl-mlt-bar';
		host.className = 'wl-mlt-bar wl-mlt-bar--block-editor';
		canvas.parentNode.insertBefore( host, canvas );
		wp.element.createRoot( host ).render( el( TabBar ) );

		return true;
	}

	wp.domReady( function () {
		var tries = 0;
		var timer = setInterval( function () {
			if ( mountTopBar() || ++tries > 100 ) {
				clearInterval( timer );
			}
		}, 200 );
	} );
} )( window.wp );
