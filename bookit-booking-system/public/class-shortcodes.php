<?php
/**
 * Shortcode handler for booking wizard.
 *
 * @package    Bookit_Booking_System
 * @subpackage Bookit_Booking_System/public
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Shortcode handler class.
 */
class Bookit_Shortcodes {

	/**
	 * Initialize shortcodes.
	 *
	 * @return void
	 */
	public function __construct() {
		add_shortcode( 'bookit_wizard_v2', array( $this, 'render_booking_wizard_v2' ) );
		add_shortcode( 'bookit_booking_confirmed_v2', array( $this, 'render_booking_confirmed_v2' ) );
		add_shortcode( 'bookit_cancel_booking', array( $this, 'render_cancel_booking' ) );
		add_shortcode( 'bookit_reschedule_booking', array( $this, 'render_reschedule_booking' ) );
		add_shortcode( 'bookit_email_changed', array( $this, 'render_email_changed' ) );
		add_shortcode( 'bookit_my_packages', array( $this, 'render_my_packages' ) );

		// Prevent wptexturize from encoding JS operators in these shortcodes.
		add_filter( 'no_texturize_shortcodes', array( $this, 'get_no_texturize_shortcodes' ) );

		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_wizard_assets' ) );
		add_filter( 'theme_page_templates', array( $this, 'register_wizard_v2_page_template' ), 10, 4 );
		add_filter( 'template_include', array( $this, 'load_wizard_v2_page_template' ), 99 );
	}

	/**
	 * Render booking wizard V2 shortcode.
	 *
	 * @param array  $atts
	 * @param string $content
	 * @return string
	 */
	public function render_booking_wizard_v2( $atts = array(), $content = '' ) {
		// Initialize session.
		require_once BOOKIT_PLUGIN_DIR . 'includes/core/class-session-manager.php';
		Bookit_Session_Manager::init();
		Bookit_Session_Manager::set( 'wizard_version', 'v2' );

		// Check if session expired.
		if ( Bookit_Session_Manager::is_expired() ) {
			Bookit_Session_Manager::clear();
		}

		// Get current step from session.
		$current_step = (int) Bookit_Session_Manager::get( 'current_step', 1 );

		// Allow backward navigation via ?step= URL parameter.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['step'] ) ) {
			$requested_step = (int) $_GET['step'];

			// Only allow navigating backwards (to a step already completed) or to the current step.
			if ( $requested_step >= 1 && $requested_step <= $current_step ) {
				$current_step = $requested_step;
				Bookit_Session_Manager::set( 'current_step', $current_step );
			}
		}

		// Validate step range.
		if ( $current_step < 1 || $current_step > 5 ) {
			$current_step = 1;
			Bookit_Session_Manager::set( 'current_step', 1 );
		}

		// Start output buffering.
		ob_start();

		// Load wizard shell template.
		Bookit_Template_Loader::get_template( 'booking-wizard-v2-shell.php' );

		return ob_get_clean();
	}

	/**
	 * Render V2 booking confirmation shortcode (parallel layout; used on /booking-confirmed-v2/ or similar).
	 *
	 * @param array  $atts Shortcode attributes.
	 * @param string $content Shortcode content.
	 * @return string Confirmation HTML.
	 */
	public function render_booking_confirmed_v2( $atts = array(), $content = '' ) {
		require_once BOOKIT_PLUGIN_DIR . 'includes/core/class-session-manager.php';
		Bookit_Session_Manager::init();

		ob_start();
		Bookit_Template_Loader::get_template( 'booking-confirmed-v2.php' );
		return ob_get_clean();
	}

	/**
	 * Magic-link cancel page shortcode (templates: 5A-3b).
	 *
	 * @param array  $atts Shortcode attributes.
	 * @param string $content Shortcode content.
	 * @return string
	 */
	public function render_cancel_booking( $atts = array(), $content = '' ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$booking_id = isset( $_GET['booking_id'] ) ? absint( $_GET['booking_id'] ) : 0;
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$token = isset( $_GET['token'] ) ? sanitize_text_field( wp_unslash( $_GET['token'] ) ) : '';

		if ( $booking_id <= 0 || '' === $token ) {
			return '<p class="bookit-error">' . esc_html__( 'Invalid booking link.', 'bookit-booking-system' ) . '</p>';
		}

		$rest_url = rest_url( 'bookit/v1/wizard/' );

		ob_start();
		Bookit_Template_Loader::get_template(
			'cancel-booking.php',
			array(
				'booking_id' => $booking_id,
				'token'      => $token,
				'rest_url'   => $rest_url,
			)
		);
		$output = ob_get_clean();

		// Only add the script if the confirm button is being shown.
		if ( false !== strpos( $output, 'bookit-cancel-confirm' ) ) {
			add_action( 'wp_footer', array( $this, 'render_cancel_script' ) );
		}

		return $output;
	}

	/**
	 * Magic-link reschedule page shortcode (templates: 5A-3b).
	 *
	 * @param array  $atts Shortcode attributes.
	 * @param string $content Shortcode content.
	 * @return string
	 */
	public function render_reschedule_booking( $atts = array(), $content = '' ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$booking_id = isset( $_GET['booking_id'] ) ? absint( $_GET['booking_id'] ) : 0;
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$token = isset( $_GET['token'] ) ? sanitize_text_field( wp_unslash( $_GET['token'] ) ) : '';

		if ( $booking_id <= 0 || '' === $token ) {
			return '<p class="bookit-error">' . esc_html__( 'Invalid booking link.', 'bookit-booking-system' ) . '</p>';
		}

		$rest_url = rest_url( 'bookit/v1/wizard/' );

		ob_start();
		Bookit_Template_Loader::get_template(
			'reschedule-booking.php',
			array(
				'booking_id' => $booking_id,
				'token'      => $token,
				'rest_url'   => $rest_url,
			)
		);
		$output = ob_get_clean();

		// Only add the script if the calendar is being shown.
		if ( false !== strpos( $output, 'bookit-reschedule-calendar' ) ) {
			add_action( 'wp_footer', array( $this, 'render_reschedule_script' ) );
		}

		return $output;
	}

	/**
	 * Render my packages shortcode.
	 *
	 * @param array  $atts Shortcode attributes.
	 * @param string $content Shortcode content.
	 * @return string My packages HTML.
	 */
	public function render_my_packages( $atts = array(), $content = '' ) {
		ob_start();
		Bookit_Template_Loader::get_template( 'my-packages.php' );
		return ob_get_clean();
	}

	/**
	 * Enqueue wizard-specific assets.
	 *
	 * @return void
	 */
	public function enqueue_wizard_assets() {
		global $post;
		$has_confirmation_v2 = is_a( $post, 'WP_Post' ) && has_shortcode( $post->post_content, 'bookit_booking_confirmed_v2' );
		$has_cancel          = is_a( $post, 'WP_Post' ) && has_shortcode( $post->post_content, 'bookit_cancel_booking' );
		$has_reschedule      = is_a( $post, 'WP_Post' ) && has_shortcode( $post->post_content, 'bookit_reschedule_booking' );
		$has_my_packages     = is_a( $post, 'WP_Post' ) && has_shortcode( $post->post_content, 'bookit_my_packages' );
		$has_wizard_v2       = is_a( $post, 'WP_Post' ) && has_shortcode( $post->post_content, 'bookit_wizard_v2' );
		if ( ! $has_wizard_v2 && ! $has_confirmation_v2 && ! $has_my_packages && ! $has_cancel && ! $has_reschedule ) {
			return;
		}

		$current_step = 1;
		if ( class_exists( 'Bookit_Session_Manager' ) ) {
			Bookit_Session_Manager::init();
			$current_step = (int) Bookit_Session_Manager::get( 'current_step', 1 );
		}

		require_once BOOKIT_PLUGIN_DIR . 'includes/class-csrf-protection.php';

		// Shared `--bookit-*` tokens (and wizard layout when applicable) live in booking-wizard-v2.css.
		$needs_v2_tokens = $has_wizard_v2 || $has_cancel || $has_reschedule || $has_confirmation_v2 || $has_my_packages;
		if ( $needs_v2_tokens ) {
			wp_enqueue_style(
				'bookit-wizard-v2',
				BOOKIT_PLUGIN_URL . 'public/assets/css/booking-wizard-v2.css',
				array(),
				BOOKIT_VERSION,
				'all'
			);
		}

		if ( $has_confirmation_v2 ) {
			wp_enqueue_style(
				'bookit-confirmation-v2',
				BOOKIT_PLUGIN_URL . 'public/assets/css/confirmation-page-v2.css',
				array( 'bookit-wizard-v2' ),
				BOOKIT_VERSION,
				'all'
			);
		}

		if ( $has_cancel || $has_reschedule ) {
			wp_enqueue_style(
				'bookit-confirmation-v2',
				BOOKIT_PLUGIN_URL . 'public/assets/css/confirmation-page-v2.css',
				array( 'bookit-wizard-v2' ),
				BOOKIT_VERSION,
				'all'
			);
			wp_enqueue_style(
				'bookit-magic-link-pages',
				BOOKIT_PLUGIN_URL . 'public/assets/css/magic-link-pages.css',
				array( 'bookit-confirmation-v2', 'bookit-wizard-v2' ),
				BOOKIT_VERSION,
				'all'
			);
		}

		if ( $has_my_packages ) {
			wp_enqueue_style(
				'bookit-my-packages',
				BOOKIT_PLUGIN_URL . 'public/assets/css/my-packages.css',
				array( 'bookit-wizard-v2' ),
				BOOKIT_VERSION,
				'all'
			);
			wp_enqueue_script( 'jquery' );
			wp_localize_script(
				'jquery',
				'bookitMyPackages',
				array(
					'restUrl' => rest_url( 'bookit/v1/wizard/package-redemptions' ),
					'nonce'   => wp_create_nonce( 'wp_rest' ),
				)
			);
		}

		if ( $has_wizard_v2 ) {
			require_once BOOKIT_PLUGIN_DIR . 'includes/wizard-v2-payment-amounts.php';
			$v2_deposit_amount      = (float) Bookit_Session_Manager::get( 'deposit_due', 0.00 );
			$v2_total_amount        = (float) Bookit_Session_Manager::get( 'total_price', 0.00 );
			$v2_show_online_payment = true;
			if ( 5 === (int) $current_step ) {
				$v2_service_id = (int) Bookit_Session_Manager::get( 'service_id', 0 );
				if ( $v2_service_id > 0 ) {
					global $wpdb;
					// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
					$v2_service_row = $wpdb->get_row(
						$wpdb->prepare(
							"SELECT * FROM {$wpdb->prefix}bookings_services WHERE id = %d",
							$v2_service_id
						),
						ARRAY_A
					);
					if ( $v2_service_row ) {
						$v2_amounts               = bookit_v2_compute_payment_amounts_from_service( $v2_service_row );
						$v2_deposit_amount        = $v2_amounts['has_deposit'] ? (float) $v2_amounts['deposit_due'] : 0.0;
						$v2_total_amount          = (float) $v2_amounts['total_price'];
						$v2_show_online_payment = bookit_v2_stripe_charge_amount( $v2_amounts ) > 0;
					}
				}
			}

			wp_enqueue_script(
				'bookit-wizard-v2',
				BOOKIT_PLUGIN_URL . 'public/assets/js/booking-wizard-v2.js',
				array( 'jquery' ),
				BOOKIT_VERSION,
				true
			);
			wp_localize_script(
				'bookit-wizard-v2',
				'bookitWizardV2',
				array(
					'restUrl'             => rest_url(),
					'ajaxUrl'             => rest_url( 'bookit/v1/wizard/session' ),
					'nonce'               => wp_create_nonce( 'wp_rest' ),
					'bookingNonce'        => Bookit_CSRF_Protection::get_nonce(),
					'currentStep'         => $current_step,
					'depositAmount'       => $v2_deposit_amount,
					'totalAmount'         => $v2_total_amount,
					'showOnlinePayment'   => $v2_show_online_payment,
					'confirmed_v2_url'    => home_url( '/booking-confirmed-v2/' ),
				)
			);
		}
	}

	/**
	 * Register the Bookit Wizard V2 page template for the Page editor dropdown.
	 *
	 * @param array       $post_templates Array of template header names keyed by filename.
	 * @param WP_Theme    $theme            Current theme object.
	 * @param WP_Post     $post             The post being edited, null in list context.
	 * @param string      $post_type        Post type.
	 * @return array
	 */
	public function register_wizard_v2_page_template( $post_templates, $theme = null, $post = null, $post_type = 'page' ) {
		$post_templates['bookit-wizard-v2.php'] = __( 'Bookit Wizard V2', 'bookit-booking-system' );
		return $post_templates;
	}

	/**
	 * Load the plugin page template when the Bookit Wizard V2 template is selected.
	 *
	 * @param string $template Path to the template file.
	 * @return string
	 */
	public function load_wizard_v2_page_template( $template ) {
		if ( ! is_singular( 'page' ) ) {
			return $template;
		}
		$slug = get_page_template_slug();
		if ( 'bookit-wizard-v2.php' === $slug ) {
			$path = BOOKIT_PLUGIN_DIR . 'public/templates/page-wizard-v2.php';
			if ( file_exists( $path ) ) {
				return $path;
			}
		}
		return $template;
	}

	/**
	 * Prevent wptexturize from encoding JS operators in these shortcodes.
	 *
	 * @param array $shortcodes Shortcode tags to exclude from texturizing.
	 * @return array
	 */
	public function get_no_texturize_shortcodes( $shortcodes ) {
		$shortcodes[] = 'bookit_reschedule_booking';
		$shortcodes[] = 'bookit_cancel_booking';
		$shortcodes[] = 'bookit_email_changed';
		return $shortcodes;
	}

	/**
	 * Render email changed confirmation page shortcode.
	 *
	 * @return string
	 */
	public function render_email_changed() {
		return '
<div class="bookit-confirmation-page bookit-magic-link-page">
  <div class="bookit-confirmation-card">
    <h2>Email Updated</h2>
    <p>Your email address has been updated. Future booking communications will be sent to your new address.</p>
  </div>
</div>';
	}

	/**
	 * Output the reschedule page script in the footer (outside the_content filtering).
	 *
	 * @return void
	 */
	public function render_reschedule_script() {
		static $done = false;
		if ( $done ) {
			return;
		}
		$done = true;

		$home_url = wp_json_encode( esc_url_raw( home_url( '/' ) ) );
		?>
		<script>
		(function () {
			'use strict';

			var calendar     = document.getElementById( 'bookit-reschedule-calendar' );
			var slotsWrap    = document.getElementById( 'bookit-reschedule-slots' );
			var slotsList    = document.getElementById( 'bookit-reschedule-slots-list' );
			var slotsEmpty   = document.getElementById( 'bookit-reschedule-slots-empty' );
			var slotsLoading = document.getElementById( 'bookit-reschedule-slots-loading' );
			var confirmBtn   = document.getElementById( 'bookit-reschedule-confirm' );
			var msgEl        = document.getElementById( 'bookit-reschedule-message' );
			var prevBtn      = document.getElementById( 'bookit-reschedule-prev-month' );
			var nextBtn      = document.getElementById( 'bookit-reschedule-next-month' );

			if ( ! calendar || ! confirmBtn ) { return; }

			var calTitle = calendar.querySelector( '.bookit-v2-calendar-title' );
			var calGrid  = calendar.querySelector( '.bookit-v2-calendar-grid' );

			var staffId      = calendar.dataset.staffId;
			var serviceId    = calendar.dataset.serviceId;
			var slotsUrl     = calendar.dataset.timeslotsUrl;
			var restUrl      = confirmBtn.dataset.restUrl;
			var bookingId    = parseInt( confirmBtn.dataset.bookingId, 10 );
			var token        = confirmBtn.dataset.token;

			var selectedDate = null;
			var selectedTime = null;

			var today      = new Date();
			var todayY     = today.getFullYear();
			var todayM     = today.getMonth();  // 0-based
			var todayD     = today.getDate();
			var currentY   = todayY;
			var currentM   = todayM;

			var DOW = [ 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun' ];

			/* ---- helpers ---- */
			function pad( n ) { return String( n ).padStart( 2, '0' ); }

			function ymd( y, m0, d ) {
				return y + '-' + pad( m0 + 1 ) + '-' + pad( d );
			}

			function updateHeader() {
				if ( ! calTitle ) { return; }
				var dt = new Date( currentY, currentM, 1 );
				calTitle.textContent = dt.toLocaleString( undefined, { month: 'long', year: 'numeric' } );
			}

			function updatePrev() {
				if ( ! prevBtn ) { return; }
				prevBtn.disabled = ( currentY === todayY && currentM === todayM );
			}

			/* ---- reset slots panel ---- */
			function resetSlots() {
				selectedDate = null;
				selectedTime = null;
				confirmBtn.disabled = true;
				if ( slotsWrap )    { slotsWrap.style.display    = 'none'; }
				if ( slotsList )    { slotsList.innerHTML         = ''; }
				if ( slotsLoading ) { slotsLoading.style.display  = 'none'; }
				if ( slotsEmpty )   { slotsEmpty.style.display    = 'none'; }
				calGrid.querySelectorAll( '.bookit-v2-day--selected' )
					.forEach( function ( b ) { b.classList.remove( 'bookit-v2-day--selected' ); } );
			}

			/* ---- build calendar grid ---- */
			function buildGrid() {
				if ( ! calGrid ) { return; }
				calGrid.innerHTML = '';

				DOW.forEach( function ( label ) {
					var d = document.createElement( 'div' );
					d.className   = 'bookit-v2-calendar-dow';
					d.textContent = label;
					calGrid.appendChild( d );
				} );

				var first  = new Date( currentY, currentM, 1 );
				var dow    = first.getDay();               // 0=Sun
				var iso    = ( dow === 0 ) ? 7 : dow;     // 1=Mon..7=Sun
				var pads   = iso - 1;
				var days   = new Date( currentY, currentM + 1, 0 ).getDate();

				for ( var p = 0; p < pads; p++ ) {
					var sp = document.createElement( 'span' );
					sp.className = 'bookit-v2-day-empty';
					calGrid.appendChild( sp );
				}

				for ( var day = 1; day <= days; day++ ) {
					var dateStr  = ymd( currentY, currentM, day );
					var isPast   = ( currentY < todayY ) ||
						( currentY === todayY && currentM < todayM ) ||
						( currentY === todayY && currentM === todayM && day < todayD );
					var btn      = document.createElement( 'button' );
					btn.type      = 'button';
					btn.className = 'bookit-v2-day' + ( isPast ? ' bookit-v2-day--disabled' : ' bookit-v2-day--available' );
					if ( currentY === todayY && currentM === todayM && day === todayD ) {
						btn.classList.add( 'bookit-v2-day--today' );
					}
					btn.textContent    = String( day );
					btn.dataset.date   = dateStr;
					btn.disabled       = isPast;
					if ( isPast ) { btn.setAttribute( 'aria-disabled', 'true' ); }
					calGrid.appendChild( btn );
				}

				updateHeader();
				updatePrev();
			}

			/* ---- flatten timeslots response ---- */
			function flatSlots( data ) {
				if ( ! data ) { return []; }
				if ( Array.isArray( data ) ) { return data; }
				var raw = data.slots;
				if ( ! raw ) { return []; }
				if ( Array.isArray( raw ) ) { return raw; }
				var out = [];
				[ 'morning', 'afternoon', 'evening' ].forEach( function ( p ) {
					if ( raw[ p ] ) { out = out.concat( raw[ p ] ); }
				} );
				return out;
			}

			function slotTime( s ) {
				var t = ( typeof s === 'string' ) ? s : ( s && s.time ? s.time : '' );
				if ( ! t ) { return ''; }
				var p = t.split( ':' );
				return pad( p[0] ) + ':' + pad( p[1] ) + ( p[2] ? ':' + pad( p[2] ) : ':00' );
			}

			function slotLabel( s ) {
				var t = ( typeof s === 'string' ) ? s : ( s && s.time ? s.time : '' );
				if ( ! t ) { return ''; }
				var p = t.split( ':' );
				return pad( p[0] ) + ':' + pad( p[1] );
			}

			/* ---- fetch and render slots ---- */
			function fetchSlots( dateStr ) {
				if ( ! dateStr ) { return; }
				selectedDate = dateStr;
				selectedTime = null;
				confirmBtn.disabled = true;

				slotsWrap.style.display    = 'block';
				slotsLoading.style.display = 'block';
				slotsList.innerHTML        = '';
				slotsEmpty.style.display   = 'none';

				calGrid.querySelectorAll( '.bookit-v2-day--selected' )
					.forEach( function ( b ) { b.classList.remove( 'bookit-v2-day--selected' ); } );
				var active = calGrid.querySelector( '[data-date="' + dateStr + '"]' );
				if ( active ) { active.classList.add( 'bookit-v2-day--selected' ); }

				var url = slotsUrl
					+ '?staff_id='   + encodeURIComponent( staffId )
					+ '&service_id=' + encodeURIComponent( serviceId )
					+ '&date='       + encodeURIComponent( dateStr );

				fetch( url, { credentials: 'same-origin' } )
					.then( function ( r ) { return r.json().then( function ( b ) { return { ok: r.ok, body: b }; } ); } )
					.then( function ( res ) {
						slotsLoading.style.display = 'none';
						var slots = flatSlots( res.body );
						if ( ! slots.length ) {
							slotsEmpty.style.display = 'block';
							return;
						}
						slots.forEach( function ( slot ) {
							var tv  = slotTime( slot );
							var lbl = slotLabel( slot );
							var sb  = document.createElement( 'button' );
							sb.type        = 'button';
							sb.className   = 'bookit-v2-slot bookit-v2-slot--available';
							sb.textContent = lbl;
							sb.dataset.time = tv;
							sb.addEventListener( 'click', function () {
								slotsList.querySelectorAll( '.bookit-v2-slot--selected' )
									.forEach( function ( s ) { s.classList.remove( 'bookit-v2-slot--selected' ); } );
								sb.classList.add( 'bookit-v2-slot--selected' );
								selectedTime        = tv;
								confirmBtn.disabled = false;
							} );
							slotsList.appendChild( sb );
						} );
					} )
					.catch( function () {
						slotsLoading.style.display = 'none';
						slotsEmpty.style.display   = 'block';
					} );
			}

			/* ---- auto-select first available day ---- */
			function autoSelect() {
				var start = ( currentY === todayY && currentM === todayM ) ? todayD : 1;
				for ( var d = start; d <= 31; d++ ) {
					var el = calGrid.querySelector( '[data-date="' + ymd( currentY, currentM, d ) + '"]' );
					if ( el && ! el.disabled ) { fetchSlots( el.dataset.date ); return; }
				}
			}

			/* ---- month navigation ---- */
			if ( prevBtn ) {
				prevBtn.addEventListener( 'click', function () {
					if ( prevBtn.disabled ) { return; }
					currentM--;
					if ( currentM < 0 ) { currentM = 11; currentY--; }
					resetSlots();
					buildGrid();
					autoSelect();
				} );
			}

			if ( nextBtn ) {
				nextBtn.addEventListener( 'click', function () {
					currentM++;
					if ( currentM > 11 ) { currentM = 0; currentY++; }
					resetSlots();
					buildGrid();
					autoSelect();
				} );
			}

			/* ---- day click (delegated) ---- */
			calendar.addEventListener( 'click', function ( e ) {
				var day = e.target.closest( '[data-date]' );
				if ( ! day || day.disabled || day.classList.contains( 'bookit-v2-day--disabled' ) ) { return; }
				fetchSlots( day.dataset.date );
			} );

			/* ---- confirm button ---- */
			confirmBtn.addEventListener( 'click', function () {
				if ( ! selectedDate || ! selectedTime ) { return; }
				confirmBtn.disabled    = true;
				confirmBtn.textContent = 'Confirming...';

				fetch( restUrl, {
					method:  'POST',
					headers: { 'Content-Type': 'application/json' },
					body:    JSON.stringify( {
						booking_id: bookingId,
						token:      token,
						new_date:   selectedDate,
						new_time:   selectedTime
					} )
				} )
				.then( function ( r ) { return r.json(); } )
				.then( function ( data ) {
					if ( data.success ) {
						msgEl.style.display = 'block';
						msgEl.className     = 'bookit-magic-message bookit-magic-message--success';
						msgEl.textContent   = 'Your booking has been rescheduled. \u2713';
						confirmBtn.disabled    = true;
						confirmBtn.textContent = 'Rescheduled';
						if ( prevBtn ) { prevBtn.disabled = true; }
						if ( nextBtn ) { nextBtn.disabled = true; }
						calGrid.querySelectorAll( 'button' )
							.forEach( function ( b ) { b.disabled = true; } );
						setTimeout( function () {
							window.location.href = <?php echo $home_url; ?>;
						}, 3000 );
					} else {
						msgEl.style.display    = 'block';
						msgEl.className        = 'bookit-magic-message bookit-magic-message--error';
						msgEl.textContent      = ( data.message ) || 'Something went wrong. Please try again.';
						confirmBtn.disabled    = false;
						confirmBtn.textContent = 'Confirm Reschedule';
					}
				} )
				.catch( function () {
					msgEl.style.display    = 'block';
					msgEl.className        = 'bookit-magic-message bookit-magic-message--error';
					msgEl.textContent      = 'A network error occurred. Please try again.';
					confirmBtn.disabled    = false;
					confirmBtn.textContent = 'Confirm Reschedule';
				} );
			} );

			/* ---- initialise ---- */
			updatePrev();
			autoSelect();

		}() );
		</script>
		<?php
	}

	/**
	 * Output the cancel page script in the footer (outside the_content filtering).
	 *
	 * @return void
	 */
	public function render_cancel_script() {
		static $done = false;
		if ( $done ) {
			return;
		}
		$done = true;
		?>
		<script>
		(function () {
			'use strict';

			var btn = document.getElementById( 'bookit-cancel-confirm' );
			if ( ! btn ) { return; }

			var defaultLabel = btn.textContent || 'Confirm Cancellation';

			btn.addEventListener( 'click', function () {
				btn.disabled = true;
				btn.textContent = btn.getAttribute( 'data-confirming-label' ) || 'Cancelling...';

				var msg = document.getElementById( 'bookit-cancel-message' );
				var actionEl = document.getElementById( 'bookit-cancel-action' );

				fetch( btn.dataset.restUrl, {
					method:  'POST',
					headers: { 'Content-Type': 'application/json' },
					body:    JSON.stringify( {
						booking_id: parseInt( btn.dataset.bookingId, 10 ),
						token:      btn.dataset.token
					} )
				} )
				.then( function ( r ) { return r.json(); } )
				.then( function ( data ) {
					if ( msg ) { msg.style.display = 'block'; }

					if ( data && data.success ) {
						if ( msg ) {
							msg.className   = 'bookit-magic-message bookit-magic-message--success';
							msg.textContent = ( data.message ) || 'Your booking has been cancelled.';
						}
						var b = actionEl && actionEl.querySelector( 'button' );
						if ( b ) { b.remove(); }
					} else {
						if ( msg ) {
							msg.className   = 'bookit-magic-message bookit-magic-message--error';
							msg.textContent = ( data && data.message ) || 'Something went wrong. Please try again.';
						}
						btn.disabled = false;
						btn.textContent = defaultLabel;
					}
				} )
				.catch( function () {
					if ( msg ) {
						msg.style.display = 'block';
						msg.className = 'bookit-magic-message bookit-magic-message--error';
						msg.textContent = 'A network error occurred. Please try again.';
					}
					btn.disabled = false;
					btn.textContent = defaultLabel;
				} );
			} );
		}() );
		</script>
		<?php
	}
}
