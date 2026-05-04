<?php
/**
 * Booking Wizard V2 — Step 3: Date & time selection
 *
 * @package    Bookit_Booking_System
 * @subpackage Bookit_Booking_System/public/templates
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

require_once BOOKIT_PLUGIN_DIR . 'includes/core/class-session-manager.php';
Bookit_Session_Manager::init();

$wizard_data      = Bookit_Session_Manager::get_data();
$service_id       = isset( $wizard_data['service_id'] ) ? absint( $wizard_data['service_id'] ) : 0;
$staff_id         = isset( $wizard_data['staff_id'] ) ? absint( $wizard_data['staff_id'] ) : 0;
$service_name     = isset( $wizard_data['service_name'] ) ? $wizard_data['service_name'] : '';
$service_duration = isset( $wizard_data['service_duration'] ) ? (int) $wizard_data['service_duration'] : 0;
$staff_name       = isset( $wizard_data['staff_name'] ) ? $wizard_data['staff_name'] : '';
$selected_date    = isset( $wizard_data['date'] ) ? $wizard_data['date'] : '';
$selected_time    = isset( $wizard_data['time'] ) ? $wizard_data['time'] : '';

if ( ! $service_id ) {
	?>
<div class="bookit-v2-step bookit-v2-step--3">
	<div class="bookit-v2-step-body">
		<p><?php esc_html_e( 'Please select a service first.', 'bookit-booking-system' ); ?></p>
	</div>
</div>
	<?php
	return;
}

require_once BOOKIT_PLUGIN_DIR . 'includes/models/class-datetime-model.php';
$datetime_model = new Bookit_DateTime_Model();

$current_ym = date( 'Y-m' );
// phpcs:ignore WordPress.Security.NonceVerification.Recommended
$month_param = isset( $_GET['month'] ) ? sanitize_text_field( wp_unslash( $_GET['month'] ) ) : '';
$view_month  = $current_ym;
if ( $month_param && preg_match( '/^\d{4}-\d{2}$/', $month_param ) ) {
	$y = (int) substr( $month_param, 0, 4 );
	$m = (int) substr( $month_param, 5, 2 );
	if ( $y >= 1970 && $m >= 1 && $m <= 12 ) {
		$view_month = sprintf( '%04d-%02d', $y, $m );
	}
}
if ( $view_month < $current_ym ) {
	$view_month = $current_ym;
}

$view_year      = (int) substr( $view_month, 0, 4 );
$view_month_num = (int) substr( $view_month, 5, 2 );
$can_go_prev    = ( $view_month !== $current_ym );
$permalink      = get_permalink();
$prev_month_url = add_query_arg( 'month', date( 'Y-m', strtotime( $view_month . '-01 -1 month' ) ), $permalink );
$next_month_url = add_query_arg( 'month', date( 'Y-m', strtotime( $view_month . '-01 +1 month' ) ), $permalink );

$first_day_of_month = mktime( 0, 0, 0, $view_month_num, 1, $view_year );
$days_in_month      = (int) date( 't', $first_day_of_month );
$first_dow          = (int) date( 'N', $first_day_of_month );
$today              = date( 'Y-m-d' );

$dow_labels = array( 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun' );

$day_cells = array();
$pad_count = $first_dow - 1;
for ( $i = 0; $i < $pad_count; $i++ ) {
	$day_cells[] = array( 'type' => 'pad' );
}
for ( $day = 1; $day <= $days_in_month; $day++ ) {
	$date_str = sprintf( '%04d-%02d-%02d', $view_year, $view_month_num, $day );
	$classes  = array( 'bookit-v2-day' );
	$is_past  = $datetime_model->is_past_date( $date_str );
	$is_bank  = $datetime_model->is_bank_holiday( $date_str );
	$disabled = $is_past || $is_bank;

	if ( $date_str === $selected_date ) {
		$classes[] = 'bookit-v2-day--selected';
	}
	if ( $date_str === $today ) {
		$classes[] = 'bookit-v2-day--today';
	}
	if ( $disabled ) {
		$classes[] = 'bookit-v2-day--disabled';
	} else {
		$classes[] = 'bookit-v2-day--available';
	}

	$day_cells[] = array(
		'type'     => 'day',
		'day'      => $day,
		'date_str' => $date_str,
		'classes'  => $classes,
		'disabled' => $disabled,
	);
}

$selected_date_usable = ( '' !== $selected_date
	&& ! $datetime_model->is_past_date( $selected_date )
	&& ! $datetime_model->is_bank_holiday( $selected_date ) );

$slots_grouped = array(
	'morning'   => array(),
	'afternoon' => array(),
	'evening'   => array(),
);
if ( $selected_date_usable ) {
	$slots_raw     = $datetime_model->get_available_slots( $selected_date, $service_id, $staff_id );
	$slots_grouped = $datetime_model->group_time_slots( $slots_raw );
}

$banner_text = trim( $service_name . ' · ' . $service_duration . ' min · ' . $staff_name );

$normalize_time_his = static function ( $time_str ) {
	if ( '' === $time_str || null === $time_str ) {
		return '';
	}
	$ts = strtotime( $time_str );
	return false !== $ts ? date( 'H:i:s', $ts ) : '';
};
$selected_time_norm = $normalize_time_his( $selected_time );

$section_labels = array(
	'morning'   => __( 'Morning', 'bookit-booking-system' ),
	'afternoon' => __( 'Afternoon', 'bookit-booking-system' ),
	'evening'   => __( 'Evening', 'bookit-booking-system' ),
);
?>
<div class="bookit-v2-step bookit-v2-step--3">
	<div class="bookit-v2-step-body">

		<div class="bookit-v2-confirm-banner">
			<span class="bookit-v2-confirm-banner-text"><?php echo esc_html( $banner_text ); ?></span>
			<button type="button" class="bookit-v2-confirm-banner-change" data-goto-step="2"><?php esc_html_e( 'Change', 'bookit-booking-system' ); ?></button>
		</div>

		<h2 class="bookit-v2-step-heading"><?php esc_html_e( 'When would you like to come in?', 'bookit-booking-system' ); ?></h2>
		<p class="bookit-v2-step-subheading"><?php esc_html_e( 'Choose a date and time for your appointment.', 'bookit-booking-system' ); ?></p>

		<div class="bookit-v2-calendar">
			<div class="bookit-v2-calendar-header">
				<?php if ( $can_go_prev ) : ?>
					<a href="<?php echo esc_url( $prev_month_url ); ?>" class="bookit-v2-calendar-nav" aria-label="<?php esc_attr_e( 'Previous month', 'bookit-booking-system' ); ?>">&#8592;</a>
				<?php else : ?>
					<span class="bookit-v2-calendar-nav bookit-v2-calendar-nav--hidden" aria-hidden="true"></span>
				<?php endif; ?>
				<span class="bookit-v2-calendar-title"><?php echo esc_html( date_i18n( 'F Y', strtotime( $view_month . '-01' ) ) ); ?></span>
				<a href="<?php echo esc_url( $next_month_url ); ?>" class="bookit-v2-calendar-nav" aria-label="<?php esc_attr_e( 'Next month', 'bookit-booking-system' ); ?>">&#8594;</a>
			</div>
			<div class="bookit-v2-calendar-grid" role="grid" aria-label="<?php esc_attr_e( 'Calendar', 'bookit-booking-system' ); ?>">
				<?php foreach ( $dow_labels as $dow_label ) : ?>
					<div class="bookit-v2-calendar-dow"><?php echo esc_html( $dow_label ); ?></div>
				<?php endforeach; ?>
				<?php foreach ( $day_cells as $cell ) : ?>
					<?php if ( 'pad' === $cell['type'] ) : ?>
						<span class="bookit-v2-day-empty"></span>
					<?php else : ?>
						<button
							type="button"
							class="<?php echo esc_attr( implode( ' ', $cell['classes'] ) ); ?>"
							data-date="<?php echo esc_attr( $cell['date_str'] ); ?>"
							<?php echo $cell['disabled'] ? 'disabled aria-disabled="true"' : ''; ?>
						><?php echo esc_html( (string) $cell['day'] ); ?></button>
					<?php endif; ?>
				<?php endforeach; ?>
			</div>
		</div>

		<?php if ( '' === $selected_date || ! $selected_date_usable ) : ?>
			<div class="bookit-v2-time-sections" id="bookit-v2-time-sections"></div>
		<?php else : ?>
			<div class="bookit-v2-time-sections" id="bookit-v2-time-sections">
				<?php foreach ( $section_labels as $period_key => $period_label ) : ?>
					<?php if ( empty( $slots_grouped[ $period_key ] ) ) : ?>
						<?php continue; ?>
					<?php endif; ?>
					<div class="bookit-v2-time-section">
						<p class="bookit-v2-time-section-label"><?php echo esc_html( $period_label ); ?></p>
						<div class="bookit-v2-slots-grid">
							<?php foreach ( $slots_grouped[ $period_key ] as $slot ) : ?>
								<?php
								$slot_selected = ( '' !== $selected_time_norm && $normalize_time_his( $slot ) === $selected_time_norm );
								$slot_class    = 'bookit-v2-slot bookit-v2-slot--available' . ( $slot_selected ? ' bookit-v2-slot--selected' : '' );
								?>
								<button type="button" class="<?php echo esc_attr( $slot_class ); ?>" data-time="<?php echo esc_attr( $slot ); ?>"><?php echo esc_html( date( 'H:i', strtotime( $slot ) ) ); ?></button>
							<?php endforeach; ?>
						</div>
					</div>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>

		<div class="bookit-v2-sticky-footer">
			<div class="bookit-v2-footer-inner">
				<button type="button" class="bookit-v2-cta-btn" id="bookit-v2-continue"<?php echo ( empty( $selected_date ) || empty( $selected_time ) ) ? ' disabled' : ''; ?>><?php esc_html_e( 'Continue', 'bookit-booking-system' ); ?></button>
				<a href="?step=2" class="bookit-v2-btn-back"><?php esc_html_e( 'Back', 'bookit-booking-system' ); ?></a>
			</div>
		</div>

	</div>
</div>
