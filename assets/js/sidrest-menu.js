/**
 * Sidcraft Restaurant — menu search, category and dietary filters, sorting
 * and the section sidebar. Works on the rendered HTML, so menus stay fully
 * cacheable and readable without JavaScript.
 */
( function () {
	'use strict';

	var i18n = window.sidrestMenu || {};

	function count( n ) {
		var tpl = n === 1 ? ( i18n.one || '%d item' ) : ( i18n.count || '%d items' );
		return tpl.replace( '%d', n );
	}

	function init( root ) {
		if ( root.dataset.sidrestReady ) {
			return;
		}
		root.dataset.sidrestReady = '1';

		var state = { q: '', cat: '', diets: [], sort: 'menu' };
		var search = root.querySelector( '[data-sidrest-search]' );
		var sort = root.querySelector( '[data-sidrest-sort]' );
		var status = root.querySelector( '[data-sidrest-status]' );
		var empty = root.querySelector( '[data-sidrest-empty]' );
		var items = Array.prototype.slice.call( root.querySelectorAll( '[data-sidrest-item]' ) );
		var sections = Array.prototype.slice.call( root.querySelectorAll( '[data-sidrest-section]' ) );
		var filtered = false;

		function matches( el ) {
			if ( state.q && ( el.dataset.search || '' ).indexOf( state.q ) === -1 ) {
				return false;
			}
			if ( state.cat && ( ' ' + ( el.dataset.cats || '' ) + ' ' ).indexOf( ' ' + state.cat + ' ' ) === -1 ) {
				return false;
			}
			var diets = ' ' + ( el.dataset.diet || '' ) + ' ';
			for ( var i = 0; i < state.diets.length; i++ ) {
				if ( diets.indexOf( ' ' + state.diets[ i ] + ' ' ) === -1 ) {
					return false;
				}
			}
			return true;
		}

		function apply() {
			var shown = 0;
			items.forEach( function ( el ) {
				var ok = matches( el );
				el.hidden = ! ok;
				if ( ok ) {
					shown++;
				}
			} );
			sections.forEach( function ( sec ) {
				var any = sec.querySelector( '[data-sidrest-item]:not([hidden])' );
				sec.hidden = ! any;
				var link = root.querySelector( '[data-sidrest-nav="' + sec.id + '"]' );
				if ( link && link.parentNode ) {
					link.parentNode.hidden = ! any;
				}
			} );
			filtered = !! ( state.q || state.cat || state.diets.length );
			if ( empty ) {
				empty.hidden = shown > 0;
			}
			if ( status ) {
				status.textContent = filtered ? count( shown ) : '';
			}
		}

		function reorder() {
			root.querySelectorAll( '[data-sidrest-items]' ).forEach( function ( list ) {
				var kids = Array.prototype.slice.call( list.querySelectorAll( ':scope > [data-sidrest-item]' ) );
				kids.sort( function ( a, b ) {
					switch ( state.sort ) {
						case 'name-asc':
							return a.dataset.name.localeCompare( b.dataset.name );
						case 'name-desc':
							return b.dataset.name.localeCompare( a.dataset.name );
						case 'price-asc':
							return ( parseFloat( a.dataset.price ) || 0 ) - ( parseFloat( b.dataset.price ) || 0 );
						case 'price-desc':
							return ( parseFloat( b.dataset.price ) || 0 ) - ( parseFloat( a.dataset.price ) || 0 );
						default:
							return ( parseInt( a.dataset.order, 10 ) || 0 ) - ( parseInt( b.dataset.order, 10 ) || 0 );
					}
				} );
				kids.forEach( function ( k ) {
					list.appendChild( k );
				} );
			} );
		}

		if ( search ) {
			var timer;
			search.addEventListener( 'input', function () {
				clearTimeout( timer );
				timer = setTimeout( function () {
					state.q = search.value.trim().toLowerCase();
					apply();
				}, 120 );
			} );
		}
		if ( sort ) {
			sort.addEventListener( 'change', function () {
				state.sort = sort.value;
				reorder();
			} );
		}

		root.addEventListener( 'click', function ( e ) {
			var cat = e.target.closest( '[data-sidrest-cat]' );
			if ( cat && root.contains( cat ) ) {
				state.cat = cat.dataset.sidrestCat;
				root.querySelectorAll( '[data-sidrest-cat]' ).forEach( function ( b ) {
					b.setAttribute( 'aria-pressed', b === cat ? 'true' : 'false' );
				} );
				apply();
				return;
			}
			var diet = e.target.closest( '[data-sidrest-diet]' );
			if ( diet && root.contains( diet ) ) {
				var on = diet.getAttribute( 'aria-pressed' ) !== 'true';
				diet.setAttribute( 'aria-pressed', on ? 'true' : 'false' );
				state.diets = Array.prototype.map.call( root.querySelectorAll( '[data-sidrest-diet][aria-pressed="true"]' ), function ( b ) {
					return b.dataset.sidrestDiet;
				} );
				apply();
				return;
			}
			if ( e.target.closest( '[data-sidrest-clear]' ) ) {
				state.q = '';
				state.cat = '';
				state.diets = [];
				if ( search ) {
					search.value = '';
				}
				root.querySelectorAll( '[data-sidrest-cat]' ).forEach( function ( b ) {
					b.setAttribute( 'aria-pressed', b.dataset.sidrestCat === '' ? 'true' : 'false' );
				} );
				root.querySelectorAll( '[data-sidrest-diet]' ).forEach( function ( b ) {
					b.setAttribute( 'aria-pressed', 'false' );
				} );
				apply();
				if ( search ) {
					search.focus();
				}
				return;
			}
			var link = e.target.closest( '[data-sidrest-nav]' );
			if ( link ) {
				var sec = document.getElementById( link.dataset.sidrestNav );
				if ( sec ) {
					e.preventDefault();
					var reduce = window.matchMedia && window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches;
					sec.scrollIntoView( { behavior: reduce ? 'auto' : 'smooth', block: 'start' } );
					sec.setAttribute( 'tabindex', '-1' );
					sec.focus( { preventScroll: true } );
					if ( history.replaceState ) {
						history.replaceState( null, '', '#' + sec.id );
					}
				}
			}
		} );

		// Highlight the section in view in the sidebar.
		var links = root.querySelectorAll( '[data-sidrest-nav]' );
		if ( links.length && 'IntersectionObserver' in window ) {
			var current = null;
			var io = new IntersectionObserver( function ( entries ) {
				entries.forEach( function ( entry ) {
					if ( entry.isIntersecting ) {
						current = entry.target.id;
					}
				} );
				links.forEach( function ( l ) {
					if ( l.dataset.sidrestNav === current ) {
						l.setAttribute( 'aria-current', 'true' );
						var bar = l.closest( '.sidrest__nav' );
						if ( bar && bar.scrollWidth > bar.clientWidth ) {
							bar.scrollLeft = l.offsetLeft - 16;
						}
					} else {
						l.removeAttribute( 'aria-current' );
					}
				} );
			}, { rootMargin: '-20% 0px -70% 0px' } );
			sections.forEach( function ( s ) {
				io.observe( s );
			} );
		}
	}

	function scan( node ) {
		( node || document ).querySelectorAll( '[data-sidrest-menu]' ).forEach( init );
	}

	function boot() {
		scan( document );
		if ( window.MutationObserver ) {
			new MutationObserver( function ( list ) {
				list.forEach( function ( m ) {
					m.addedNodes.forEach( function ( n ) {
						if ( n.nodeType === 1 && n.querySelectorAll ) {
							if ( n.matches( '[data-sidrest-menu]' ) ) {
								init( n );
							} else {
								scan( n );
							}
						}
					} );
				} );
			} ).observe( document.documentElement, { childList: true, subtree: true } );
		}
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', boot );
	} else {
		boot();
	}
}() );
