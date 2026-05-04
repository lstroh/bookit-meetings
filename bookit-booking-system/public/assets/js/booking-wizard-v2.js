/**
 * Booking wizard V2 — step navigation and interactions (vanilla JS).
 *
 * @package Bookit_Booking_System
 */
( function() {
	'use strict';

	document.addEventListener( 'DOMContentLoaded', function() {
		// Only the V2 wizard shell adds data-step; reschedule/cancel reuse calendar markup inside .bookit-v2-wizard-container without it.
		if ( ! document.querySelector( '.bookit-v2-wizard-container[data-step]' ) ) {
			return;
		}

		var wizard = typeof bookitWizardV2 !== 'undefined' ? bookitWizardV2 : {};
		var currentStep = parseInt( wizard.currentStep, 10 ) || 1;

		initStep( currentStep );
	} );

	function initStep( step ) {
		if ( step === 1 ) {
			initStep1();
		}
		if ( step === 2 ) {
			initStep2();
		}
		if ( step === 3 ) {
			initStep3();
		}
		if ( step === 4 ) {
			initStep4();
		}
		if ( step === 5 ) {
			initStep5();
		}
		initNavigation( step );
	}

	function postToSession( data ) {
		var w = typeof bookitWizardV2 !== 'undefined' ? bookitWizardV2 : {};
		return fetch( w.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			headers: {
				'Content-Type': 'application/json',
				'X-WP-Nonce': w.nonce
			},
			body: JSON.stringify( data )
		} ).then( function( r ) {
			return r.json();
		} );
	}

	function advanceStep( step ) {
		postToSession( { current_step: step + 1 } ).then( function() {
			// Navigate to the base page URL without ?step= to prevent
			// the PHP shell from clamping back to a previous step
			var base = window.location.pathname;
			window.location.href = base;
		} );
	}

	function initNavigation( step ) {
		var continueBtn = document.getElementById( 'bookit-v2-continue' );
		if ( continueBtn ) {
			continueBtn.addEventListener( 'click', function() {
				advanceStep( step );
			} );
		}

		document.querySelectorAll( '.bookit-v2-confirm-banner-change' ).forEach( function( btn ) {
			btn.addEventListener( 'click', function() {
				var n = parseInt( btn.getAttribute( 'data-goto-step' ), 10 );
				if ( ! n || n < 1 ) {
					return;
				}
				postToSession( { current_step: n } ).then( function() {
					window.location.reload();
				} );
			} );
		} );
	}

	function initStep1() {
		document.querySelectorAll( '.bookit-v2-service-card' ).forEach( function( card ) {
			card.addEventListener( 'click', function() {
				document.querySelectorAll( '.bookit-v2-service-card' ).forEach( function( c ) {
					c.classList.remove( 'bookit-v2-service-card--selected' );
				} );
				card.classList.add( 'bookit-v2-service-card--selected' );
				postToSession( {
					current_step: 1,
					service_id: parseInt( card.dataset.serviceId, 10 ),
					service_name: card.dataset.serviceName || '',
					service_duration: parseInt( card.dataset.serviceDuration, 10 ) || 0
				} ).then( function() {
					var continueBtn = document.getElementById( 'bookit-v2-continue' );
					if ( continueBtn ) {
						continueBtn.removeAttribute( 'disabled' );
					}
				} );
			} );
		} );

		// Set initial Continue button state based on whether a card is already selected
		var continueBtn = document.getElementById( 'bookit-v2-continue' );
		if ( continueBtn ) {
			var alreadySelected = document.querySelector( '.bookit-v2-service-card--selected' );
			if ( ! alreadySelected ) {
				continueBtn.setAttribute( 'disabled', 'disabled' );
			}
		}
	}

	function initStep2() {
		var w = typeof bookitWizardV2 !== 'undefined' ? bookitWizardV2 : {};
		var rows = document.querySelectorAll( '.bookit-v2-staff-row, .bookit-v2-staff-card' );
		rows.forEach( function( el ) {
			if ( el.classList.contains( 'bookit-v2-staff-row--unavailable' ) || el.classList.contains( 'bookit-v2-staff-card--unavailable' ) ) {
				return;
			}
			el.addEventListener( 'click', function() {
				document.querySelectorAll( '.bookit-v2-staff-row--selected, .bookit-v2-staff-card--selected' ).forEach( function( s ) {
					s.classList.remove( 'bookit-v2-staff-row--selected' );
					s.classList.remove( 'bookit-v2-staff-card--selected' );
				} );
				if ( el.classList.contains( 'bookit-v2-staff-row' ) ) {
					el.classList.add( 'bookit-v2-staff-row--selected' );
				}
				if ( el.classList.contains( 'bookit-v2-staff-card' ) ) {
					el.classList.add( 'bookit-v2-staff-card--selected' );
				}
				var cont = document.getElementById( 'bookit-v2-continue' );
				if ( cont ) {
					cont.removeAttribute( 'disabled' );
				}
				var sid = el.dataset.staffId;
				var staffId = sid === undefined || sid === '' ? 0 : parseInt( sid, 10 );
				fetch( w.restUrl + 'bookit/v1/staff/select', {
					method: 'POST',
					credentials: 'same-origin',
					headers: {
						'Content-Type': 'application/json',
						'X-WP-Nonce': w.nonce
					},
					body: JSON.stringify( { staff_id: staffId } )
				} ).then( function( r ) {
					return r.json();
				} ).then( function( res ) {
					if ( res && res.success ) {
						window.location.reload();
					}
				} );
			} );
		} );

		// Set initial Continue button state
		var continueBtn = document.getElementById( 'bookit-v2-continue' );
		if ( continueBtn && ! document.querySelector( '.bookit-v2-staff-row--selected, .bookit-v2-staff-card--selected' ) ) {
			continueBtn.setAttribute( 'disabled', 'disabled' );
		}
	}

	function formatSlotButtonLabel( slot ) {
		var p = String( slot ).split( ':' );
		var h = p[0 ] !== undefined ? p[0 ] : '00';
		var m = p[1 ] !== undefined ? p[1 ] : '00';
		if ( h.length < 2 ) {
			h = ( '0' + h ).slice( -2 );
		}
		if ( m.length < 2 ) {
			m = ( '0' + m ).slice( -2 );
		}
		return h + ':' + m;
	}

	function renderTimeSections( slots ) {
		var container = document.getElementById( 'bookit-v2-time-sections' );
		if ( ! container || ! slots ) {
			return;
		}
		var labels = {
			morning: 'Morning',
			afternoon: 'Afternoon',
			evening: 'Evening'
		};
		var order = [ 'morning', 'afternoon', 'evening' ];
		var html = '';
		order.forEach( function( key ) {
			var list = slots[ key ] || [];
			if ( ! list.length ) {
				return;
			}
			html += '<div class="bookit-v2-time-section">';
			html += '<p class="bookit-v2-time-section-label">' + labels[ key ] + '</p>';
			html += '<div class="bookit-v2-slots-grid">';
			list.forEach( function( slot ) {
				var raw = String( slot );
				var esc = raw.replace( /&/g, '&amp;' ).replace( /"/g, '&quot;' );
				html += '<button type="button" class="bookit-v2-slot bookit-v2-slot--available" data-time="' + esc + '">' + formatSlotButtonLabel( raw ) + '</button>';
			} );
			html += '</div></div>';
		} );
		container.innerHTML = html;
		container.scrollIntoView( { behavior: 'smooth', block: 'nearest' } );
	}

	function initStep3() {
		var w = typeof bookitWizardV2 !== 'undefined' ? bookitWizardV2 : {};
		var selInitial = document.querySelector( '.bookit-v2-day--selected.bookit-v2-day--available' );
		var currentSelectedDate = selInitial && selInitial.dataset.date ? selInitial.dataset.date : '';

		document.querySelectorAll( '.bookit-v2-day--available' ).forEach( function( dayBtn ) {
			dayBtn.addEventListener( 'click', function() {
				document.querySelectorAll( '.bookit-v2-day--selected' ).forEach( function( d ) {
					d.classList.remove( 'bookit-v2-day--selected' );
				} );
				dayBtn.classList.add( 'bookit-v2-day--selected' );
				currentSelectedDate = dayBtn.dataset.date || '';
				postToSession( {
					current_step: 3,
					date: currentSelectedDate
				} ).then( function() {
					var url = w.restUrl + 'bookit/v1/wizard/timeslots?date=' + encodeURIComponent( currentSelectedDate );
					return fetch( url, {
						credentials: 'same-origin',
						headers: {
							'X-WP-Nonce': w.nonce
						}
					} ).then( function( r ) {
						return r.json();
					} );
				} ).then( function( data ) {
					var container = document.getElementById( 'bookit-v2-time-sections' );
					if ( data && data.success && data.slots ) {
						var hasSlots = ( data.slots.morning && data.slots.morning.length ) ||
							( data.slots.afternoon && data.slots.afternoon.length ) ||
							( data.slots.evening && data.slots.evening.length );
						if ( hasSlots ) {
							renderTimeSections( data.slots );
						} else {
							// No slots — show message and unselect the day
							dayBtn.classList.remove( 'bookit-v2-day--selected' );
							dayBtn.classList.remove( 'bookit-v2-day--available' );
							dayBtn.classList.add( 'bookit-v2-day--disabled' );
							if ( container ) {
								container.innerHTML = '<p style="font-size:13px;color:var(--bookit-text-muted);padding:8px 0;">No availability on this date. Please choose another day.</p>';
							}
						}
					}
				} );
			} );
		} );

		var timeSections = document.getElementById( 'bookit-v2-time-sections' );
		if ( timeSections ) {
			timeSections.addEventListener( 'click', function( e ) {
				var slot = e.target.closest( '.bookit-v2-slot--available' );
				if ( ! slot ) {
					return;
				}
				var dateVal = currentSelectedDate;
				if ( ! dateVal ) {
					var sel = document.querySelector( '.bookit-v2-day--selected' );
					if ( sel ) {
						dateVal = sel.dataset.date || '';
					}
				}
				document.querySelectorAll( '.bookit-v2-slot--selected' ).forEach( function( s ) {
					s.classList.remove( 'bookit-v2-slot--selected' );
				} );
				slot.classList.add( 'bookit-v2-slot--selected' );
				postToSession( {
					current_step: 3,
					date: dateVal,
					time: slot.dataset.time
				} ).then( function() {
					var cont = document.getElementById( 'bookit-v2-continue' );
					if ( cont ) {
						cont.removeAttribute( 'disabled' );
					}
				} );
			} );
		}
	}

	function initStep4() {
		var toggle = document.getElementById( 'bookit-v2-special-requests-toggle' );
		var textarea = document.getElementById( 'special-requests' );
		if ( toggle && textarea ) {
			toggle.addEventListener( 'click', function() {
				toggle.style.display = 'none';
				textarea.style.display = '';
				textarea.focus();
			} );
		}

		var form = document.getElementById( 'bookit-contact-form' );
		if ( ! form ) {
			return;
		}

		function clearStep4FieldErrors() {
			form.querySelectorAll( '.bookit-v2-field-error' ).forEach( function( el ) {
				el.textContent = '';
			} );
			var submitErr = form.querySelector( '.bookit-v2-step4-submit-error' );
			if ( submitErr ) {
				submitErr.textContent = '';
				submitErr.style.display = 'none';
			}
		}

		function setStep4FieldError( fieldId, message ) {
			var el = document.getElementById( fieldId );
			if ( el ) {
				el.textContent = message || '';
			}
		}

		function showStep4SubmitError( message ) {
			var submitErr = form.querySelector( '.bookit-v2-step4-submit-error' );
			if ( ! submitErr ) {
				submitErr = document.createElement( 'p' );
				submitErr.className = 'bookit-v2-step4-submit-error bookit-error';
				submitErr.setAttribute( 'role', 'alert' );
				form.insertBefore( submitErr, form.firstChild );
			}
			submitErr.textContent = message || '';
			submitErr.style.display = message ? 'block' : 'none';
		}

		function isValidEmail( s ) {
			if ( ! s || typeof s !== 'string' ) {
				return false;
			}
			var t = s.trim();
			if ( ! t ) {
				return false;
			}
			// Practical RFC-like check without being overly strict.
			return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test( t );
		}

		function validateStep4() {
			clearStep4FieldErrors();
			var ok = true;
			var fn = ( document.getElementById( 'first-name' ) && document.getElementById( 'first-name' ).value ) ? document.getElementById( 'first-name' ).value.trim() : '';
			var ln = ( document.getElementById( 'last-name' ) && document.getElementById( 'last-name' ).value ) ? document.getElementById( 'last-name' ).value.trim() : '';
			var em = ( document.getElementById( 'email' ) && document.getElementById( 'email' ).value ) ? document.getElementById( 'email' ).value.trim() : '';
			var ph = ( document.getElementById( 'phone' ) && document.getElementById( 'phone' ).value ) ? document.getElementById( 'phone' ).value.trim() : '';

			if ( ! fn ) {
				setStep4FieldError( 'first-name-error', 'Please enter your first name.' );
				ok = false;
			}
			if ( ! ln ) {
				setStep4FieldError( 'last-name-error', 'Please enter your last name.' );
				ok = false;
			}
			if ( ! em ) {
				setStep4FieldError( 'email-error', 'Please enter your email address.' );
				ok = false;
			} else if ( ! isValidEmail( em ) ) {
				setStep4FieldError( 'email-error', 'Please enter a valid email address.' );
				ok = false;
			}
			if ( ! ph ) {
				setStep4FieldError( 'phone-error', 'Please enter your phone number.' );
				ok = false;
			}

			var waiverCb = document.getElementById( 'cooling-off-waiver' );
			var waiverGroup = document.getElementById( 'cooling-off-waiver-group' );
			if ( waiverCb && waiverGroup ) {
				var st = window.getComputedStyle( waiverGroup );
				var visible = st.display !== 'none' && st.visibility !== 'hidden' && waiverGroup.offsetParent !== null;
				if ( visible && ! waiverCb.checked ) {
					setStep4FieldError( 'cooling-off-waiver-error', 'Please confirm the waiver to continue.' );
					ok = false;
				}
			}

			return ok;
		}

		form.addEventListener( 'submit', function( ev ) {
			ev.preventDefault();
			if ( ! validateStep4() ) {
				return;
			}

			var w = typeof bookitWizardV2 !== 'undefined' ? bookitWizardV2 : {};
			var nonceInput = form.querySelector( 'input[name="bookit_booking_nonce"]' );
			var bookitNonce = nonceInput && nonceInput.value ? nonceInput.value : '';

			var marketing = document.getElementById( 'marketing-consent' );
			var waiverCb = document.getElementById( 'cooling-off-waiver' );
			var sr = document.getElementById( 'special-requests' );
			var payload = {
				current_step: 4,
				customer_first_name: document.getElementById( 'first-name' ).value.trim(),
				customer_last_name: document.getElementById( 'last-name' ).value.trim(),
				customer_email: document.getElementById( 'email' ).value.trim(),
				customer_phone: document.getElementById( 'phone' ).value.trim(),
				customer_special_requests: sr ? sr.value.trim() : '',
				cooling_off_waiver: waiverCb && waiverCb.checked ? 1 : 0,
				marketing_consent: marketing && marketing.checked ? 1 : 0,
				bookit_booking_nonce: bookitNonce
			};

			var submitBtn = form.querySelector( 'button[type="submit"].bookit-v2-cta-btn' );
			if ( submitBtn ) {
				submitBtn.disabled = true;
			}

			postToSession( payload ).then( function( res ) {
				if ( ! res || ! res.success ) {
					var msg = 'Unable to save your details. Please try again.';
					if ( res && res.message ) {
						msg = res.message;
					} else if ( res && res.data && res.data.message ) {
						msg = res.data.message;
					}
					showStep4SubmitError( msg );
					if ( submitBtn ) {
						submitBtn.disabled = false;
					}
					return;
				}
				advanceStep( 4 );
			} ).catch( function() {
				showStep4SubmitError( 'A network error occurred. Please try again.' );
				if ( submitBtn ) {
					submitBtn.disabled = false;
				}
			} );
		} );
	}

	function updateCtaLabel( value ) {
		var btn = document.getElementById( 'bookit-v2-cta-btn' );
		if ( ! btn ) {
			return;
		}
		var w = typeof bookitWizardV2 !== 'undefined' ? bookitWizardV2 : {};
		var deposit = parseFloat( w.depositAmount ) || 0;
		var total = parseFloat( w.totalAmount ) || 0;
		var amount = deposit > 0 ? deposit : total;
		var formatted = amount > 0 ? '\u00a3' + amount.toFixed( 2 ) : '';

		if ( value === 'card' ) {
			btn.textContent = formatted ? 'Pay ' + formatted + ' now' : 'Pay now';
		} else if ( value === 'paypal' ) {
			btn.textContent = 'Continue to PayPal';
		} else if ( value === 'person' ) {
			btn.textContent = 'Confirm booking';
		} else if ( value === 'use_package' || ( typeof value === 'string' && value.indexOf( 'use_package_' ) === 0 ) ) {
			btn.textContent = 'Use my package';
		} else if ( typeof value === 'string' && value.indexOf( 'buy_' ) === 0 ) {
			btn.textContent = 'Buy package & confirm';
		} else {
			btn.textContent = 'Continue';
		}
	}

	function getPaymentChoiceValue() {
		var checked = document.querySelector( 'input[name="bookit_v2_payment_choice"]:checked' );
		return checked ? checked.value : 'card';
	}

	function initStep5() {
		var w0 = typeof bookitWizardV2 !== 'undefined' ? bookitWizardV2 : {};
		var showOnlinePayment = w0.showOnlinePayment !== false;
		updateCtaLabel( showOnlinePayment ? 'card' : 'person' );

		document.querySelectorAll( '#bookit-v2-zone-c .bookit-v2-payment-row' ).forEach( function( row ) {
			row.addEventListener( 'click', function() {
				document.querySelectorAll( '#bookit-v2-zone-c .bookit-v2-payment-row' ).forEach( function( r ) {
					r.classList.remove( 'bookit-v2-payment-row--selected' );
					r.classList.remove( 'bookit-v2-payment-row--disabled' );
				} );
				row.classList.add( 'bookit-v2-payment-row--selected' );
				var radio = row.querySelector( 'input[type="radio"]' );
				if ( radio ) {
					radio.checked = true;
				}
				document.querySelectorAll( '.bookit-v2-package-row input[type="radio"]' ).forEach( function( pr ) {
					pr.checked = false;
				} );
				document.querySelectorAll( '.bookit-v2-package-row' ).forEach( function( pr ) {
					pr.classList.remove( 'bookit-v2-package-row--selected' );
				} );
				updateCtaLabel( row.dataset.value || ( radio ? radio.value : 'card' ) );
			} );
		} );

		document.querySelectorAll( '.bookit-v2-package-row' ).forEach( function( row ) {
			row.addEventListener( 'click', function() {
				var isAlreadySelected = row.classList.contains( 'bookit-v2-package-row--selected' );

				if ( isAlreadySelected ) {
					// Deselect — re-enable Zone C and reset to card (or pay in person if online options hidden)
					row.classList.remove( 'bookit-v2-package-row--selected' );
					var radio = row.querySelector( 'input[type="radio"]' );
					if ( radio ) {
						radio.checked = false;
					}
					// Re-enable Zone C rows
					document.querySelectorAll( '#bookit-v2-zone-c .bookit-v2-payment-row' ).forEach( function( pr ) {
						pr.classList.remove( 'bookit-v2-payment-row--disabled' );
					} );
					var wPkg = typeof bookitWizardV2 !== 'undefined' ? bookitWizardV2 : {};
					var showOnline = wPkg.showOnlinePayment !== false;
					document.querySelectorAll( '#bookit-v2-zone-c .bookit-v2-payment-row' ).forEach( function( pr ) {
						pr.classList.remove( 'bookit-v2-payment-row--selected' );
					} );
					var cardRow = document.querySelector( '#bookit-v2-pay-card' );
					var cardRadio = document.querySelector( '#bookit-v2-radio-card' );
					if ( showOnline && cardRow ) {
						cardRow.classList.add( 'bookit-v2-payment-row--selected' );
					}
					if ( showOnline && cardRadio ) {
						cardRadio.checked = true;
					}
					if ( ! showOnline ) {
						var personRow = document.querySelector( '#bookit-v2-pay-person' );
						var personRadio = document.querySelector( '#bookit-v2-radio-person' );
						if ( personRow ) {
							personRow.classList.add( 'bookit-v2-payment-row--selected' );
						}
						if ( personRadio ) {
							personRadio.checked = true;
						}
					}
					updateCtaLabel( showOnline ? 'card' : 'person' );
				} else {
					// Select this package row
					document.querySelectorAll( '.bookit-v2-package-row' ).forEach( function( r ) {
						r.classList.remove( 'bookit-v2-package-row--selected' );
					} );
					row.classList.add( 'bookit-v2-package-row--selected' );
					var radio = row.querySelector( 'input[type="radio"]' );
					if ( radio ) {
						radio.checked = true;
					}
					// Disable Zone C
					document.querySelectorAll( '#bookit-v2-zone-c .bookit-v2-payment-row' ).forEach( function( pr ) {
						pr.classList.add( 'bookit-v2-payment-row--disabled' );
						pr.classList.remove( 'bookit-v2-payment-row--selected' );
					} );
					document.querySelectorAll( '#bookit-v2-zone-c input[type="radio"]' ).forEach( function( pr ) {
						pr.checked = false;
					} );
					var val = row.dataset.value || ( radio ? radio.value : '' );
					updateCtaLabel( val );
				}
			} );
		} );

		var cta = document.getElementById( 'bookit-v2-cta-btn' );
		if ( cta ) {
			cta.addEventListener( 'click', function() {
				var choice = getPaymentChoiceValue();
				postToSession( {
					current_step: 5,
					payment_method: choice
				} ).then( function() {
					var w = typeof bookitWizardV2 !== 'undefined' ? bookitWizardV2 : {};
					var btn = document.getElementById( 'bookit-v2-cta-btn' );
					if ( btn ) {
						btn.disabled = true;
						btn.textContent = 'Confirming\u2026';
					}
					return fetch( w.restUrl + 'bookit/v1/wizard/complete', {
						method: 'POST',
						credentials: 'same-origin',
						headers: {
							'Content-Type': 'application/json',
							'X-WP-Nonce': w.nonce,
							'X-Bookit-Nonce': w.bookingNonce
						},
						body: JSON.stringify( {} )
					} );
				} )
					.then( function( r ) { return r.json(); } )
					.then( function( data ) {
						if ( data && data.success && data.redirect_url ) {
							window.location.href = data.redirect_url;
						} else {
							var msg = ( data && data.message ) ? data.message : 'Unable to complete booking. Please try again.';
							alert( msg );
							var btn = document.getElementById( 'bookit-v2-cta-btn' );
							if ( btn ) {
								btn.disabled = false;
								updateCtaLabel( getPaymentChoiceValue() );
							}
						}
					} )
					.catch( function() {
						alert( 'A network error occurred. Please try again.' );
						var btn = document.getElementById( 'bookit-v2-cta-btn' );
						if ( btn ) {
							btn.disabled = false;
							updateCtaLabel( getPaymentChoiceValue() );
						}
					} );
			} );
		}
	}
} )();
