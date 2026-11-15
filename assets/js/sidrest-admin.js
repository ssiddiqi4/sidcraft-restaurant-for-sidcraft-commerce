/**
 * Sidcraft Restaurant — admin: sortable pickers (menus → sections → items,
 * related items) and repeating rows (prices, suppliers, custom fields).
 * No jQuery; drag and drop plus keyboard-friendly up/down buttons.
 */
( function () {
	'use strict';

	var i18n = window.sidrestAdmin || {};

	/* ---------------- Sortable pickers ---------------- */

	function ownPicker( el ) {
		return el.closest( '[data-sidrest-picker]' );
	}

	function list( picker ) {
		return picker.querySelector( ':scope > [data-list]' );
	}

	function rows( picker ) {
		return Array.prototype.slice.call( list( picker ).children );
	}

	function refreshEmpty( picker ) {
		var empty = picker.querySelector( ':scope > [data-empty]' );
		if ( empty ) {
			empty.hidden = rows( picker ).length > 0;
		}
		var select = picker.querySelector( ':scope > .sidrest-picker__add [data-add-select]' );
		if ( select ) {
			var taken = rows( picker ).map( function ( r ) {
				return r.dataset.id;
			} );
			Array.prototype.forEach.call( select.options, function ( o ) {
				o.disabled = !! o.value && taken.indexOf( o.value ) > -1;
			} );
		}
	}

	document.addEventListener( 'click', function ( e ) {
		var btn = e.target.closest( '[data-add], [data-up], [data-down], [data-remove]' );
		if ( ! btn ) {
			return;
		}
		var picker = ownPicker( btn );
		if ( ! picker ) {
			return;
		}
		e.preventDefault();

		if ( btn.hasAttribute( 'data-add' ) ) {
			var select = btn.parentNode.querySelector( '[data-add-select]' );
			var id = select.value;
			if ( ! id || rows( picker ).some( function ( r ) {
				return r.dataset.id === id;
			} ) ) {
				return;
			}
			var tpl = picker.querySelector( ':scope > template[data-row]' );
			var row = tpl.content.firstElementChild.cloneNode( true );
			row.dataset.id = id;
			row.querySelector( '[data-value]' ).value = id;
			row.querySelector( '[data-label]' ).textContent = select.options[ select.selectedIndex ].text;
			if ( picker.dataset.name === 'sidrest_sections[]' && i18n.saveTo ) {
				var note = document.createElement( 'p' );
				note.className = 'description sidrest-picker__note';
				note.textContent = i18n.saveTo;
				row.appendChild( note );
			}
			list( picker ).appendChild( row );
			select.value = '';
			refreshEmpty( picker );
			return;
		}

		var item = btn.closest( '.sidrest-picker__row' );
		if ( ! item || ownPicker( item ) !== picker ) {
			return;
		}
		if ( btn.hasAttribute( 'data-remove' ) ) {
			item.remove();
		} else if ( btn.hasAttribute( 'data-up' ) && item.previousElementSibling ) {
			item.parentNode.insertBefore( item, item.previousElementSibling );
			btn.focus();
		} else if ( btn.hasAttribute( 'data-down' ) && item.nextElementSibling ) {
			item.parentNode.insertBefore( item.nextElementSibling, item );
			btn.focus();
		}
		refreshEmpty( picker );
	} );

	// Drag and drop within one list.
	var dragged = null;
	document.addEventListener( 'dragstart', function ( e ) {
		var row = e.target.closest && e.target.closest( '.sidrest-picker__row' );
		if ( ! row || ( e.target !== row && e.target.closest( '.sidrest-picker__row' ) !== row ) ) {
			return;
		}
		if ( e.target.closest( 'input, select, textarea, details > *:not(summary)' ) && e.target !== row ) {
			return;
		}
		dragged = row;
		row.classList.add( 'is-dragging' );
		e.dataTransfer.effectAllowed = 'move';
		try {
			e.dataTransfer.setData( 'text/plain', row.dataset.id );
		} catch ( err ) {}
		e.stopPropagation();
	} );
	document.addEventListener( 'dragover', function ( e ) {
		if ( ! dragged ) {
			return;
		}
		var over = e.target.closest && e.target.closest( '.sidrest-picker__row' );
		while ( over && over.parentNode !== dragged.parentNode ) {
			over = over.parentNode.closest( '.sidrest-picker__row' );
		}
		if ( ! over || over === dragged ) {
			return;
		}
		e.preventDefault();
		var rect = over.getBoundingClientRect();
		var after = e.clientY > rect.top + rect.height / 2;
		over.parentNode.insertBefore( dragged, after ? over.nextSibling : over );
	} );
	document.addEventListener( 'dragend', function () {
		if ( dragged ) {
			dragged.classList.remove( 'is-dragging' );
			dragged = null;
		}
	} );

	/* ---------------- Repeating rows ---------------- */

	document.addEventListener( 'click', function ( e ) {
		var add = e.target.closest( '[data-add-row]' );
		var remove = e.target.closest( '[data-remove-row]' );
		if ( ! add && ! remove ) {
			return;
		}
		var rep = ( add || remove ).closest( '[data-sidrest-repeater]' );
		if ( ! rep ) {
			return;
		}
		e.preventDefault();
		var body = rep.querySelector( '[data-rows]' );
		if ( add ) {
			var tpl = rep.querySelector( 'template[data-template]' );
			var index = 'n' + Date.now().toString( 36 );
			var html = tpl.innerHTML.replace( /__i__/g, index );
			var holder = document.createElement( 'tbody' );
			holder.innerHTML = html;
			var row = holder.firstElementChild;
			body.appendChild( row );
			var first = row.querySelector( 'input' );
			if ( first ) {
				first.focus();
			}
		} else {
			var tr = remove.closest( '[data-row]' );
			if ( tr ) {
				tr.remove();
			}
		}
	} );

	function boot() {
		document.querySelectorAll( '[data-sidrest-picker]' ).forEach( refreshEmpty );
	}
	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', boot );
	} else {
		boot();
	}
}() );
