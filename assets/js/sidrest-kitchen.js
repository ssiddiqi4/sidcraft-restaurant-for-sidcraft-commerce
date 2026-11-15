/**
 * Kitchen screen: live orders in three columns (New, Preparing, Ready),
 * refreshed every 8 seconds while visible. New orders play a sound and can
 * print a ticket by themselves (switches kept in this browser).
 */
( function () {
	'use strict';

	var cfg = window.sidrestKitchen;
	var root = document.getElementById( 'sidrest-kitchen' );
	if ( ! cfg || ! root ) {
		return;
	}
	var t = cfg.i18n;
	var known = null; // Order ids seen (null until the first load).
	var orders = [];
	var timer = 0;
	var audio = null;
	var prefs = { sound: cfg.sound, print: false };
	try {
		prefs = Object.assign( prefs, JSON.parse( localStorage.getItem( 'sidrestKitchen' ) || '{}' ) );
	} catch ( e ) {}

	function save() {
		try {
			localStorage.setItem( 'sidrestKitchen', JSON.stringify( prefs ) );
		} catch ( e ) {}
	}

	function el( tag, cls, text ) {
		var n = document.createElement( tag );
		if ( cls ) {
			n.className = cls;
		}
		if ( undefined !== text && null !== text ) {
			n.textContent = text;
		}
		return n;
	}

	function route( path ) {
		var parts = path.split( '?' );
		var url = cfg.api + parts[ 0 ];
		return parts[ 1 ] ? url + ( url.indexOf( '?' ) === -1 ? '?' : '&' ) + parts[ 1 ] : url;
	}

	function api( path, method, body ) {
		return fetch( route( path ), {
			method: method || 'GET',
			credentials: 'same-origin',
			cache: 'no-store',
			headers: Object.assign( { 'X-WP-Nonce': cfg.nonce }, body ? { 'Content-Type': 'application/json' } : {} ),
			body: body ? JSON.stringify( body ) : undefined,
		} ).then( function ( r ) {
			if ( ! r.ok ) {
				throw new Error( r.status );
			}
			return r.json();
		} );
	}

	function clock( unix ) {
		return new Date( unix * 1000 ).toLocaleTimeString( [], { hour: '2-digit', minute: '2-digit' } );
	}

	/* ------------------------------------------------------------- Sound */

	function beep() {
		if ( ! prefs.sound ) {
			return;
		}
		try {
			audio = audio || new ( window.AudioContext || window.webkitAudioContext )();
			[ 0, 0.25 ].forEach( function ( delay ) {
				var osc = audio.createOscillator();
				var gain = audio.createGain();
				osc.frequency.value = 880;
				gain.gain.setValueAtTime( 0.25, audio.currentTime + delay );
				gain.gain.exponentialRampToValueAtTime( 0.001, audio.currentTime + delay + 0.2 );
				osc.connect( gain );
				gain.connect( audio.destination );
				osc.start( audio.currentTime + delay );
				osc.stop( audio.currentTime + delay + 0.22 );
			} );
		} catch ( e ) {}
	}

	/* ------------------------------------------------------------ Ticket */

	function ticketHtml( o ) {
		var esc = function ( s ) {
			return String( s ).replace( /[&<>"]/g, function ( c ) {
				return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[ c ];
			} );
		};
		var lines = o.items.map( function ( i ) {
			return '<div class="i"><b>' + i.qty + ' × ' + esc( i.name ) + '</b>' +
				i.options.map( function ( x ) {
					return '<div class="o">' + esc( x ) + '</div>';
				} ).join( '' ) +
				( i.allergens.length ? '<div class="a">⚠ ' + esc( i.allergens.join( ', ' ) ) + '</div>' : '' ) +
				'</div>';
		} ).join( '' );
		return '<!doctype html><html><head><meta charset="utf-8"><title>' + esc( o.number ) + '</title><style>' +
			'@page{size:' + cfg.ticketWidth + 'mm auto;margin:3mm}body{font:13px/1.35 monospace;margin:0;width:' + ( cfg.ticketWidth - 6 ) + 'mm;color:#000}' +
			'h1{font-size:20px;margin:0 0 4px}h2{font-size:14px;margin:0 0 6px}.i{border-top:1px dashed #000;padding:4px 0}.o{padding-left:10px}.a{padding-left:10px;font-weight:bold}' +
			'.m{margin:4px 0}.n{border:1px solid #000;padding:4px;margin:6px 0}</style></head><body>' +
			'<h2>' + esc( cfg.store ) + '</h2><h1>' + esc( o.number ) + ' · ' + esc( o.where ) + '</h1>' +
			'<div class="m">' + esc( t.due ) + ': ' + esc( o.when ) + '</div>' +
			'<div class="m">' + esc( t.placed ) + ': ' + clock( o.placed ) + '</div>' +
			( o.customer ? '<div class="m">' + esc( o.customer ) + ( o.phone ? ' · ' + esc( o.phone ) : '' ) + '</div>' : '' ) +
			lines +
			( o.notes ? '<div class="n">' + esc( t.notes ) + ': ' + esc( o.notes ) + '</div>' : '' ) +
			'<div class="i">' + esc( t.total ) + ': ' + esc( o.total ) + ( o.paid ? '' : ' — ' + esc( t.unpaid ) ) + '</div>' +
			'</body></html>';
	}

	function print( o ) {
		var frame = el( 'iframe' );
		frame.style.cssText = 'position:fixed;right:0;bottom:0;width:0;height:0;border:0';
		document.body.appendChild( frame );
		var doc = frame.contentWindow.document;
		doc.open();
		doc.write( ticketHtml( o ) );
		doc.close();
		setTimeout( function () {
			frame.contentWindow.focus();
			frame.contentWindow.print();
			setTimeout( function () {
				frame.remove();
			}, 2000 );
		}, 200 );
	}

	/* ------------------------------------------------------------- Board */

	var ui = {};

	function layout() {
		root.textContent = '';
		var bar = el( 'div', 'sk-bar' );
		ui.status = el( 'span', 'sk-status' );
		bar.appendChild( ui.status );
		ui.clock = el( 'span', 'sk-clock' );
		bar.appendChild( ui.clock );

		var sound = el( 'label', 'sk-switch' );
		var soundBox = el( 'input' );
		soundBox.type = 'checkbox';
		soundBox.checked = !! prefs.sound;
		soundBox.addEventListener( 'change', function () {
			prefs.sound = soundBox.checked;
			save();
			if ( prefs.sound ) {
				beep(); // Also unlocks audio in the browser.
			}
		} );
		sound.appendChild( soundBox );
		sound.appendChild( document.createTextNode( ' ' + t.sound ) );
		bar.appendChild( sound );

		var auto = el( 'label', 'sk-switch' );
		var autoBox = el( 'input' );
		autoBox.type = 'checkbox';
		autoBox.checked = !! prefs.print;
		autoBox.addEventListener( 'change', function () {
			prefs.print = autoBox.checked;
			save();
		} );
		auto.appendChild( autoBox );
		auto.appendChild( document.createTextNode( ' ' + t.autoPrint ) );
		bar.appendChild( auto );

		var sold = el( 'button', 'button', t.soldOut );
		sold.type = 'button';
		sold.addEventListener( 'click', openDrawer );
		bar.appendChild( sold );

		var full = el( 'button', 'button', t.full );
		full.type = 'button';
		full.addEventListener( 'click', function () {
			if ( document.fullscreenElement ) {
				document.exitFullscreen();
			} else if ( document.documentElement.requestFullscreen ) {
				document.documentElement.requestFullscreen();
			}
		} );
		bar.appendChild( full );

		var exit = el( 'a', 'button', t.exitLabel );
		exit.href = cfg.exit;
		bar.appendChild( exit );
		root.appendChild( bar );

		ui.notice = el( 'p', 'sk-notice' );
		ui.notice.hidden = true;
		root.appendChild( ui.notice );

		var cols = el( 'div', 'sk-cols' );
		ui.cols = {};
		[ 'new', 'preparing', 'ready' ].forEach( function ( stage ) {
			var col = el( 'section', 'sk-col sk-col--' + stage );
			var head = el( 'h2', 'sk-col__title' );
			head.appendChild( document.createTextNode( t[ stage ] + ' ' ) );
			var count = el( 'span', 'sk-count', '0' );
			head.appendChild( count );
			col.appendChild( head );
			var list = el( 'div', 'sk-list' );
			col.appendChild( list );
			cols.appendChild( col );
			ui.cols[ stage ] = { list: list, count: count };
		} );
		root.appendChild( cols );

		ui.drawer = el( 'aside', 'sk-drawer' );
		ui.drawer.hidden = true;
		root.appendChild( ui.drawer );
	}

	function card( o ) {
		var now = Date.now() / 1000;
		var c = el( 'article', 'sk-card sk-card--' + o.kind + ( ! o.paid ? ' is-unpaid' : '' ) + ( o.later ? ' is-later' : '' ) + ( o.readyBy && o.readyBy < now && 'ready' !== o.stage ? ' is-late' : '' ) );
		var head = el( 'header', 'sk-card__head' );
		head.appendChild( el( 'strong', 'sk-card__no', o.number ) );
		head.appendChild( el( 'span', 'sk-card__where', o.where ) );
		c.appendChild( head );
		var meta = el( 'p', 'sk-card__meta' );
		meta.textContent = t.due + ': ' + o.when + ( o.readyBy ? ' (' + clock( o.readyBy ) + ')' : '' ) + ' · ' + t.placed + ' ' + clock( o.placed );
		c.appendChild( meta );
		if ( o.readyBy && o.readyBy < now && 'ready' !== o.stage ) {
			c.appendChild( el( 'span', 'sk-flag sk-flag--late', t.late ) );
		}
		if ( ! o.paid ) {
			c.appendChild( el( 'span', 'sk-flag sk-flag--unpaid', t.unpaid ) );
		}
		var list = el( 'ul', 'sk-items' );
		o.items.forEach( function ( i ) {
			var li = el( 'li' );
			li.appendChild( el( 'b', '', i.qty + ' × ' + i.name ) );
			i.options.forEach( function ( x ) {
				li.appendChild( el( 'div', 'sk-opt', x ) );
			} );
			if ( i.allergens.length ) {
				li.appendChild( el( 'div', 'sk-allergen', '⚠ ' + t.allergens + ': ' + i.allergens.join( ', ' ) ) );
			}
			list.appendChild( li );
		} );
		c.appendChild( list );
		if ( o.notes ) {
			c.appendChild( el( 'p', 'sk-notes', t.notes + ': ' + o.notes ) );
		}
		if ( o.customer ) {
			c.appendChild( el( 'p', 'sk-who', o.customer + ( o.phone ? ' · ' + o.phone : '' ) ) );
		}
		var actions = el( 'div', 'sk-actions' );
		var next = { new: [ 'preparing', t.start ], preparing: [ 'ready', t.markReady ], ready: [ 'done', t.done ] }[ o.stage ];
		var prev = { preparing: 'new', ready: 'preparing' }[ o.stage ];
		if ( prev ) {
			actions.appendChild( button( t.back, 'button', function () {
				move( o, prev );
			} ) );
		}
		actions.appendChild( button( t.print, 'button', function () {
			print( o );
		} ) );
		var open = el( 'a', 'button', '↗' );
		open.href = o.edit;
		open.target = '_blank';
		open.rel = 'noopener';
		open.setAttribute( 'aria-label', t.open );
		actions.appendChild( open );
		actions.appendChild( button( next[ 1 ], 'button button-primary sk-next', function () {
			move( o, next[ 0 ] );
		} ) );
		c.appendChild( actions );
		return c;
	}

	function button( label, cls, fn ) {
		var b = el( 'button', cls, label );
		b.type = 'button';
		b.addEventListener( 'click', fn );
		return b;
	}

	function render() {
		[ 'new', 'preparing', 'ready' ].forEach( function ( stage ) {
			var col = ui.cols[ stage ];
			var mine = orders.filter( function ( o ) {
				return o.stage === stage;
			} );
			col.list.textContent = '';
			col.count.textContent = String( mine.length );
			if ( ! mine.length ) {
				col.list.appendChild( el( 'p', 'sk-empty', t.empty ) );
			}
			mine.sort( function ( a, b ) {
				return ( a.readyBy || a.placed ) - ( b.readyBy || b.placed );
			} ).forEach( function ( o ) {
				col.list.appendChild( card( o ) );
			} );
		} );
	}

	function move( o, stage ) {
		o.stage = stage;
		orders = orders.filter( function ( x ) {
			return 'done' !== x.stage;
		} );
		render();
		api( 'orders/' + o.id, 'POST', { stage: stage } ).catch( load );
	}

	function load() {
		clearTimeout( timer );
		return api( 'orders' )
			.then( function ( data ) {
				ui.notice.hidden = true;
				var fresh = [];
				orders = data.orders;
				if ( known ) {
					orders.forEach( function ( o ) {
						if ( ! known[ o.id ] ) {
							fresh.push( o );
						}
					} );
				}
				known = {};
				orders.forEach( function ( o ) {
					known[ o.id ] = true;
				} );
				ui.status.textContent = data.paused ? t.paused : ( data.open ? t.openNow : t.closed );
				ui.status.className = 'sk-status ' + ( data.paused || ! data.open ? 'is-off' : 'is-on' );
				render();
				if ( fresh.length ) {
					beep();
					document.title = '(' + fresh.length + ') ' + t.newOrder;
					if ( prefs.print ) {
						fresh.forEach( print );
					}
				}
			} )
			.catch( function () {
				ui.notice.textContent = t.offline;
				ui.notice.hidden = false;
			} )
			.finally( function () {
				if ( ! document.hidden ) {
					timer = setTimeout( load, 8000 );
				}
			} );
	}

	/* ------------------------------------------------- Sold-out drawer */

	function openDrawer() {
		var d = ui.drawer;
		d.textContent = '';
		d.hidden = false;
		var head = el( 'div', 'sk-drawer__head' );
		head.appendChild( el( 'h2', '', t.soldOut ) );
		head.appendChild( button( t.close, 'button', function () {
			d.hidden = true;
		} ) );
		d.appendChild( head );
		var search = el( 'input', 'sk-search' );
		search.type = 'search';
		search.placeholder = t.search;
		search.setAttribute( 'aria-label', t.search );
		d.appendChild( search );
		var list = el( 'ul', 'sk-dishes' );
		d.appendChild( list );
		api( 'items' ).then( function ( data ) {
			data.items.forEach( function ( item ) {
				var li = el( 'li' );
				li.dataset.name = item.name.toLowerCase();
				var label = el( 'label' );
				var box = el( 'input' );
				box.type = 'checkbox';
				box.checked = item.soldOut;
				box.addEventListener( 'change', function () {
					api( 'items/' + item.id, 'POST', { sold_out: box.checked ? 1 : 0 } ).catch( function () {
						box.checked = ! box.checked;
					} );
				} );
				label.appendChild( box );
				label.appendChild( document.createTextNode( ' ' + item.name ) );
				li.appendChild( label );
				list.appendChild( li );
			} );
		} );
		search.addEventListener( 'input', function () {
			var q = search.value.toLowerCase();
			list.querySelectorAll( 'li' ).forEach( function ( li ) {
				li.hidden = q && li.dataset.name.indexOf( q ) === -1;
			} );
		} );
		search.focus();
	}

	/* -------------------------------------------------------------- Boot */

	layout();
	setInterval( function () {
		ui.clock.textContent = new Date().toLocaleTimeString( [], { hour: '2-digit', minute: '2-digit' } );
	}, 1000 );
	load();
	document.addEventListener( 'visibilitychange', function () {
		if ( ! document.hidden ) {
			load();
		}
	} );
	window.addEventListener( 'focus', function () {
		document.title = document.title.replace( /^\(\d+\) /, '' );
	} );
}() );
