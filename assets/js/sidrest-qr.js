/**
 * Sidcraft Restaurant — QR codes (front-end Unit/block and the admin tool).
 * Uses the bundled qrcode-generator library (MIT) in vendor/qrcode.js.
 */
( function () {
	'use strict';

	if ( typeof window.qrcode !== 'function' ) {
		return;
	}

	/** Module matrix for a text (UTF-8, error correction M, size picked automatically). */
	function matrix( text ) {
		window.qrcode.stringToBytes = window.qrcode.stringToBytesFuncs[ 'UTF-8' ];
		var qr = window.qrcode( 0, 'M' );
		qr.addData( text );
		qr.make();
		return qr;
	}

	/** SVG markup with a quiet zone of 4 modules. */
	function svg( text, color, bg ) {
		var qr = matrix( text );
		var n = qr.getModuleCount();
		var q = 4;
		var size = n + q * 2;
		var path = '';
		for ( var r = 0; r < n; r++ ) {
			for ( var c = 0; c < n; c++ ) {
				if ( qr.isDark( r, c ) ) {
					path += 'M' + ( c + q ) + ' ' + ( r + q ) + 'h1v1h-1z';
				}
			}
		}
		return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' + size + ' ' + size + '" shape-rendering="crispEdges">' +
			'<rect width="100%" height="100%" fill="' + ( bg || '#ffffff' ) + '"/>' +
			'<path d="' + path + '" fill="' + ( color || '#111111' ) + '"/></svg>';
	}

	/** Draw onto a canvas of a given pixel size. */
	function canvas( text, px, color, bg ) {
		var qr = matrix( text );
		var n = qr.getModuleCount();
		var q = 4;
		var cell = Math.max( 1, Math.floor( px / ( n + q * 2 ) ) );
		var size = cell * ( n + q * 2 );
		var cv = document.createElement( 'canvas' );
		cv.width = size;
		cv.height = size;
		var ctx = cv.getContext( '2d' );
		ctx.fillStyle = bg || '#ffffff';
		ctx.fillRect( 0, 0, size, size );
		ctx.fillStyle = color || '#111111';
		for ( var r = 0; r < n; r++ ) {
			for ( var c = 0; c < n; c++ ) {
				if ( qr.isDark( r, c ) ) {
					ctx.fillRect( ( c + q ) * cell, ( r + q ) * cell, cell, cell );
				}
			}
		}
		return cv;
	}

	function download( href, name ) {
		var a = document.createElement( 'a' );
		a.href = href;
		a.download = name;
		document.body.appendChild( a );
		a.click();
		a.remove();
	}

	/* ---------------- Front end: [data-sidrest-qr] ---------------- */

	function renderFigure( fig ) {
		if ( fig.dataset.sidrestQrDone ) {
			return;
		}
		var box = fig.querySelector( '.sidrest-qr__code' );
		if ( ! box ) {
			return;
		}
		try {
			box.innerHTML = svg( fig.dataset.url, fig.dataset.color, '#ffffff' );
			fig.dataset.sidrestQrDone = '1';
		} catch ( e ) {
			return;
		}
		var btn = fig.querySelector( '[data-sidrest-qr-download]' );
		if ( btn ) {
			btn.addEventListener( 'click', function () {
				var cv = canvas( fig.dataset.url, 1024, fig.dataset.color, '#ffffff' );
				download( cv.toDataURL( 'image/png' ), 'menu-qr-code.png' );
			} );
		}
	}

	function scan( root ) {
		( root || document ).querySelectorAll( '[data-sidrest-qr]' ).forEach( renderFigure );
	}

	/* ---------------- Admin tool ---------------- */

	function tool( wrap ) {
		var $ = function ( sel ) {
			return wrap.querySelector( sel );
		};
		var target = $( '[data-qr-target]' );
		var custom = $( '[data-qr-url]' );
		var customWrap = $( '[data-qr-custom-wrap]' );
		var preview = $( '[data-qr-preview]' );
		var link = $( '[data-qr-link]' );

		function url() {
			return target.value || custom.value.trim();
		}
		function color() {
			return $( '[data-qr-color]' ).value;
		}
		function bg() {
			return $( '[data-qr-bg]' ).value;
		}
		function refresh() {
			customWrap.hidden = !! target.value;
			var u = url();
			link.textContent = u;
			if ( ! u ) {
				preview.innerHTML = '';
				return;
			}
			try {
				preview.innerHTML = svg( u, color(), bg() );
			} catch ( e ) {
				preview.textContent = e.message || String( e );
			}
		}

		wrap.addEventListener( 'input', refresh );
		wrap.addEventListener( 'change', refresh );
		$( '[data-qr-png]' ).addEventListener( 'click', function () {
			if ( url() ) {
				var px = Math.max( 128, Math.min( 2048, parseInt( $( '[data-qr-size]' ).value, 10 ) || 512 ) );
				download( canvas( url(), px, color(), bg() ).toDataURL( 'image/png' ), ( window.sidrestQr && window.sidrestQr.filename || 'menu-qr-code' ) + '.png' );
			}
		} );
		$( '[data-qr-svg]' ).addEventListener( 'click', function () {
			if ( url() ) {
				var blob = new Blob( [ svg( url(), color(), bg() ) ], { type: 'image/svg+xml' } );
				var href = URL.createObjectURL( blob );
				download( href, ( window.sidrestQr && window.sidrestQr.filename || 'menu-qr-code' ) + '.svg' );
				setTimeout( function () {
					URL.revokeObjectURL( href );
				}, 2000 );
			}
		} );
		$( '[data-qr-print]' ).addEventListener( 'click', function () {
			if ( ! url() ) {
				return;
			}
			var caption = $( '[data-qr-caption]' ).value;
			var esc = function ( s ) {
				var d = document.createElement( 'div' );
				d.textContent = s;
				return d.innerHTML;
			};
			var card = '<div class="card">' + svg( url(), color(), bg() ) + '<p>' + esc( caption ) + '</p></div>';
			var w = window.open( '', '_blank' );
			if ( ! w ) {
				return;
			}
			w.document.write(
				'<!doctype html><html><head><meta charset="utf-8"><title>' + esc( caption ) + '</title>' +
				'<style>body{margin:0;font-family:system-ui,sans-serif}.sheet{display:grid;grid-template-columns:1fr 1fr;gap:12mm;padding:12mm}' +
				'.card{border:1px dashed #bbb;border-radius:6mm;padding:8mm;text-align:center;break-inside:avoid}.card svg{width:55mm;height:55mm}' +
				'.card p{margin:4mm 0 0;font-size:14pt;font-weight:600}@media print{.card{border-color:#ddd}}</style></head><body>' +
				'<div class="sheet">' + card + card + card + card + '</div></body></html>'
			);
			w.document.close();
			w.focus();
			setTimeout( function () {
				w.print();
			}, 300 );
		} );
		refresh();
		tables( wrap.parentNode, url, color, bg );
	}

	// One code per table (dine-in), for the menu chosen above.
	function tables( page, url, color, bg ) {
		var box = page.querySelector( '[data-qr-tables]' );
		if ( ! box ) {
			return;
		}
		var list = JSON.parse( box.getAttribute( 'data-qr-tables' ) || '[]' );
		var label = box.getAttribute( 'data-qr-table-label' ) || '%s';
		var esc = function ( s ) {
			var d = document.createElement( 'div' );
			d.textContent = s;
			return d.innerHTML;
		};
		function linkFor( t ) {
			var base = url();
			return base + ( base.indexOf( '?' ) === -1 ? '?' : '&' ) + 'sidrest_table=' + encodeURIComponent( t.name ) + '&k=' + encodeURIComponent( t.k );
		}
		function draw() {
			box.textContent = '';
			if ( ! url() ) {
				return;
			}
			list.forEach( function ( t ) {
				var fig = document.createElement( 'figure' );
				fig.className = 'sidrest-qr-tables__item';
				fig.innerHTML = svg( linkFor( t ), color(), bg() ) + '<figcaption>' + esc( label.replace( '%s', t.name ) ) + '</figcaption>';
				box.appendChild( fig );
			} );
		}
		page.querySelectorAll( '[data-qr-target], [data-qr-url], [data-qr-color], [data-qr-bg]' ).forEach( function ( el ) {
			el.addEventListener( 'input', draw );
			el.addEventListener( 'change', draw );
		} );
		draw();
		var btn = page.querySelector( '[data-qr-tables-print]' );
		if ( btn ) {
			btn.addEventListener( 'click', function () {
				var cards = list.map( function ( t ) {
					return '<div class="card">' + svg( linkFor( t ), color(), bg() ) + '<p>' + esc( label.replace( '%s', t.name ) ) + '</p><small>' + esc( ( page.querySelector( '[data-qr-caption]' ) || {} ).value || '' ) + '</small></div>';
				} ).join( '' );
				var w = window.open( '', '_blank' );
				if ( ! w ) {
					return;
				}
				w.document.write(
					'<!doctype html><html><head><meta charset="utf-8"><title>QR</title>' +
					'<style>body{margin:0;font-family:system-ui,sans-serif}.sheet{display:grid;grid-template-columns:1fr 1fr;gap:12mm;padding:12mm}' +
					'.card{border:1px dashed #bbb;border-radius:6mm;padding:8mm;text-align:center;break-inside:avoid}.card svg{width:55mm;height:55mm}' +
					'.card p{margin:4mm 0 0;font-size:18pt;font-weight:700}.card small{font-size:11pt}</style></head><body>' +
					'<div class="sheet">' + cards + '</div></body></html>'
				);
				w.document.close();
				w.focus();
				setTimeout( function () {
					w.print();
				}, 300 );
			} );
		}
	}

	function boot() {
		scan( document );
		var t = document.querySelector( '[data-sidrest-qr-tool]' );
		if ( t ) {
			tool( t );
		}
		// Blocks and builder previews add codes later.
		if ( window.MutationObserver ) {
			new MutationObserver( function ( list ) {
				list.forEach( function ( m ) {
					m.addedNodes.forEach( function ( node ) {
						if ( node.nodeType === 1 ) {
							if ( node.matches && node.matches( '[data-sidrest-qr]' ) ) {
								renderFigure( node );
							} else if ( node.querySelectorAll ) {
								scan( node );
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

	window.sidrestQrCode = { svg: svg, canvas: canvas };
}() );
