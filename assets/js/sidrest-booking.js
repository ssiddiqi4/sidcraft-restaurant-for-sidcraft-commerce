/**
 * Table booking form: free times for the date and party, then the booking.
 */
( function () {
	'use strict';

	var config = window.sidrestBooking || {};

	function url( path, params ) {
		var base = config.api + path;
		var qs = Object.keys( params || {} ).map( function ( k ) {
			return encodeURIComponent( k ) + '=' + encodeURIComponent( params[ k ] );
		} ).join( '&' );
		return qs ? base + ( base.indexOf( '?' ) === -1 ? '?' : '&' ) + qs : base;
	}

	function init( form ) {
		var slots = form.querySelector( '[data-sidrest-slots]' );
		var hint = form.querySelector( '[data-sidrest-slots-hint]' );
		var message = form.querySelector( '[data-sidrest-booking-message]' );
		var button = form.querySelector( 'button[type="submit"]' );

		var latest = 0;

		function load() {
			var ticket = ++latest; // Only the newest answer is drawn.
			var date = form.elements.date.value;
			var party = form.elements.party.value;
			slots.textContent = '';
			hint.textContent = date ? config.i18n.loading : config.i18n.pickDate;
			if ( ! date ) {
				return;
			}
			fetch( url( 'bookings/times', { date: date, party: party } ), { credentials: 'same-origin', cache: 'no-store' } )
				.then( function ( r ) {
					return r.json();
				} )
				.then( function ( data ) {
					if ( ticket !== latest ) {
						return;
					}
					slots.textContent = '';
					var times = ( data && data.times ) || [];
					hint.textContent = times.length ? '' : config.i18n.none;
					times.forEach( function ( t, i ) {
						var label = document.createElement( 'label' );
						label.className = 'sidrest-chip sidrest-booking__slot';
						var input = document.createElement( 'input' );
						input.type = 'radio';
						input.name = 'time';
						input.value = t.value;
						input.required = true;
						if ( 0 === i ) {
							input.checked = true;
						}
						label.appendChild( input );
						label.appendChild( document.createTextNode( ' ' + t.label ) );
						slots.appendChild( label );
					} );
				} )
				.catch( function () {
					if ( ticket === latest ) {
						hint.textContent = config.i18n.error;
					}
				} );
		}

		form.elements.date.addEventListener( 'change', load );
		form.elements.party.addEventListener( 'change', load );
		load();

		form.addEventListener( 'submit', function ( e ) {
			e.preventDefault();
			var missing = Array.prototype.find.call( form.querySelectorAll( '[required]' ), function ( el ) {
				return 'radio' !== el.type && ! el.checkValidity();
			} );
			if ( missing ) {
				message.textContent = missing.validationMessage;
				missing.focus();
				return;
			}
			if ( ! form.querySelector( 'input[name="time"]:checked' ) ) {
				message.textContent = config.i18n.pickTime;
				return;
			}
			var body = {};
			new FormData( form ).forEach( function ( v, k ) {
				body[ k ] = v;
			} );
			button.disabled = true;
			fetch( url( 'bookings' ), {
				method: 'POST',
				credentials: 'same-origin',
				headers: { 'Content-Type': 'application/json' },
				body: JSON.stringify( body ),
			} )
				.then( function ( r ) {
					return r.json().then( function ( d ) {
						return { ok: r.ok, data: d };
					} );
				} )
				.then( function ( res ) {
					message.textContent = ( res.data && res.data.message ) || config.i18n.error;
					form.classList.toggle( 'is-done', res.ok );
					if ( res.ok ) {
						form.querySelectorAll( 'input, select, textarea, button' ).forEach( function ( el ) {
							el.disabled = true;
						} );
					} else {
						button.disabled = false;
						if ( res.data && res.data.code === 'sidrest_booking_invalid' ) {
							load();
						}
					}
				} )
				.catch( function () {
					message.textContent = config.i18n.error;
					button.disabled = false;
				} );
		} );
	}

	function boot() {
		document.querySelectorAll( '[data-sidrest-booking]' ).forEach( init );
	}
	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', boot );
	} else {
		boot();
	}
}() );
