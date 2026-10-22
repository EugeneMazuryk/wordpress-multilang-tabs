( function () {
	var table = document.getElementById( 'wl-mlt-languages' );
	var template = document.getElementById( 'wl-mlt-row-template' );
	var add = document.getElementById( 'wl-mlt-add' );

	if ( ! table || ! template || ! add ) {
		return;
	}

	var body = table.tBodies[ 0 ];

	function reindex() {
		Array.prototype.forEach.call( body.rows, function ( row, i ) {
			row.querySelectorAll( 'input' ).forEach( function ( input ) {
				if ( input.type === 'radio' ) {
					input.value = i;
				} else {
					input.name = input.name.replace( /\[languages\]\[[^\]]*\]/, '[languages][' + i + ']' );
				}
			} );
		} );
	}

	function flag( row ) {
		var code = row.querySelector( 'input[name$="[flag]"]' ).value.toUpperCase();
		var out = '';

		if ( /^[A-Z]{2}$/.test( code ) ) {
			out = String.fromCodePoint( 0x1f1e6 + code.charCodeAt( 0 ) - 65, 0x1f1e6 + code.charCodeAt( 1 ) - 65 );
		}

		row.querySelector( '.wl-mlt-flag' ).textContent = out;
	}

	add.addEventListener( 'click', function () {
		body.appendChild( template.content.cloneNode( true ) );
		reindex();
	} );

	body.addEventListener( 'input', function ( e ) {
		if ( e.target.name && e.target.name.slice( -6 ) === '[flag]' ) {
			flag( e.target.closest( 'tr' ) );
		}
	} );

	body.addEventListener( 'click', function ( e ) {
		var action = e.target.getAttribute( 'data-wl-mlt' );
		var row = e.target.closest( 'tr' );

		if ( ! action || ! row ) {
			return;
		}

		if ( action === 'up' && row.previousElementSibling ) {
			body.insertBefore( row, row.previousElementSibling );
		} else if ( action === 'down' && row.nextElementSibling ) {
			body.insertBefore( row.nextElementSibling, row );
		} else if ( action === 'remove' && body.rows.length > 1 ) {
			row.remove();
		}

		reindex();
	} );

	table.closest( 'form' ).addEventListener( 'submit', reindex );
} )();
