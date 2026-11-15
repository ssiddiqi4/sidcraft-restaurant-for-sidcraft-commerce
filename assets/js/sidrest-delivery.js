/**
 * Sidcraft Restaurant — pickup or delivery at checkout.
 *
 * When the customer changes "Pickup or delivery", the choice is saved and
 * the order summary (delivery fee, tax, total) is re-rendered by Sidcraft
 * Commerce's fragments. Payment then goes through the store's gateways.
 */
( function () {
	'use strict';

	var cfg = window.sidrestDelivery || {};
	if ( ! cfg.route || ! cfg.field ) {
		return;
	}
	var timer;

	function sync( select ) {
		var form = select.closest( '[data-sc-checkout]' );
		var summary = form ? form.querySelector( '.sc-checkout-summary' ) : null;
		clearTimeout( timer );
		timer = setTimeout( function () {
			if ( summary ) {
				summary.setAttribute( 'aria-busy', 'true' );
			}
			window.fetch( cfg.route, {
				method: 'POST',
				credentials: 'same-origin',
				headers: { 'Content-Type': 'application/json' },
				body: JSON.stringify( { choice: select.value } ),
			} )
				.then( function ( r ) {
					return r.json();
				} )
				.then( function ( data ) {
					if ( data && data.fragments && window.sidccofoCart && window.sidccofoCart.applyFragments ) {
						window.sidccofoCart.applyFragments( data.fragments );
						document.dispatchEvent( new CustomEvent( 'sidrest:fulfilment_changed', { detail: { choice: select.value, data: data } } ) );
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

	function field() {
		return document.querySelector( '[data-sc-checkout] select[name="' + cfg.field + '"]' );
	}

	document.addEventListener( 'change', function ( e ) {
		if ( e.target && e.target.matches && e.target.matches( 'select[name="' + cfg.field + '"]' ) ) {
			sync( e.target );
		}
	} );

	// Price the selection shown on load (the browser may restore an earlier choice).
	function init() {
		var select = field();
		if ( select ) {
			sync( select );
		}
	}
	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	} else {
		init();
	}
} )();
