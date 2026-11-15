/**
 * Sidcraft Restaurant — pickup or delivery at checkout, tips, and the
 * delivery fee by distance.
 *
 * When the customer changes "Pickup or delivery", the tip, or (with
 * delivery zones) the address, the choice is saved and the order summary
 * (delivery fee, tip, tax, total) is re-rendered by Sidcraft Commerce's
 * fragments. Payment then goes through the store's gateways.
 */
( function () {
	'use strict';

	var cfg = window.sidrestDelivery || {};
	if ( ! cfg.route || ! cfg.field ) {
		return;
	}
	var timers = {};

	function post( select, route, body ) {
		var form = select.closest( '[data-sc-checkout]' );
		var summary = form ? form.querySelector( '.sc-checkout-summary' ) : null;
		// One pending request per route, so a tip change can't cancel a choice change.
		clearTimeout( timers[ route ] );
		timers[ route ] = setTimeout( function () {
			if ( summary ) {
				summary.setAttribute( 'aria-busy', 'true' );
			}
			window.fetch( route, {
				method: 'POST',
				credentials: 'same-origin',
				headers: { 'Content-Type': 'application/json' },
				body: JSON.stringify( body ),
			} )
				.then( function ( r ) {
					return r.json();
				} )
				.then( function ( data ) {
					if ( data && data.fragments && window.sidccofoCart && window.sidccofoCart.applyFragments ) {
						window.sidccofoCart.applyFragments( data.fragments );
						document.dispatchEvent( new CustomEvent( 'sidrest:fulfilment_changed', { detail: { choice: select.value, data: data } } ) );
					}
					// Delivery choices change with the distance (fee, or not offered).
					if ( data && data.choices ) {
						var choice = form ? form.querySelector( 'select[name="' + cfg.field + '"]' ) : null;
						if ( choice ) {
							Array.prototype.slice.call( choice.options ).forEach( function ( opt ) {
								if ( data.choices[ opt.value ] ) {
									opt.textContent = data.choices[ opt.value ];
									opt.disabled = false;
								} else if ( opt.value ) {
									opt.disabled = true;
								}
							} );
						}
					}
				} )
				.catch( function () {} )
				.finally( function () {
					var s = form ? form.querySelector( '.sc-checkout-summary' ) : null;
					if ( s ) {
						s.removeAttribute( 'aria-busy' );
					}
				} );
		}, 150 );
	}

	function sync( select ) {
		if ( ! select.value ) {
			return; // "Select…": nothing chosen yet.
		}
		post( select, cfg.route, { choice: select.value } );
	}

	// Tip: "Another amount" shows the amount box.
	function tipBox( form ) {
		var select = form.querySelector( 'select[name="' + cfg.tipField + '"]' );
		var amount = form.querySelector( '[name="' + cfg.tipAmount + '"]' );
		if ( ! select || ! amount ) {
			return;
		}
		var wrap = amount.closest( '.sc-field' ) || amount.parentNode;
		var custom = 'custom' === select.value;
		wrap.hidden = ! custom;
		if ( ! custom ) {
			amount.value = '';
		}
	}

	function syncTip( el ) {
		var form = el.closest( '[data-sc-checkout]' );
		if ( ! form || ! cfg.tipRoute ) {
			return;
		}
		tipBox( form );
		var select = form.querySelector( 'select[name="' + cfg.tipField + '"]' );
		var amount = form.querySelector( '[name="' + cfg.tipAmount + '"]' );
		post( el, cfg.tipRoute, { tip: select ? select.value : 'none', amount: amount ? amount.value : '' } );
	}

	function syncAddress( el ) {
		var form = el.closest( '[data-sc-checkout]' );
		if ( ! form || ! cfg.addressRoute ) {
			return;
		}
		var body = {};
		[ 'address_1', 'city', 'postcode', 'country' ].forEach( function ( k ) {
			var f = form.querySelector( '[name="' + k + '"]' );
			body[ k ] = f ? f.value : '';
		} );
		if ( body.address_1 ) {
			post( el, cfg.addressRoute, body );
		}
	}

	function field() {
		return document.querySelector( '[data-sc-checkout] select[name="' + cfg.field + '"]' );
	}

	document.addEventListener( 'change', function ( e ) {
		if ( ! e.target || ! e.target.matches ) {
			return;
		}
		if ( e.target.matches( 'select[name="' + cfg.field + '"]' ) ) {
			sync( e.target );
		} else if ( e.target.matches( '[name="' + cfg.tipField + '"], [name="' + cfg.tipAmount + '"]' ) ) {
			syncTip( e.target );
		} else if ( e.target.matches( '[name="address_1"], [name="city"], [name="postcode"], [name="country"]' ) ) {
			syncAddress( e.target );
		}
	} );

	// Price the selection shown on load (the browser may restore an earlier choice).
	function init() {
		var select = field();
		if ( select ) {
			sync( select );
		}
		document.querySelectorAll( '[data-sc-checkout]' ).forEach( tipBox );
	}
	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
} )();
