<?php
/**
 * Booking Wizard V2 — Step 1: Service selection
 *
 * @package    Bookit_Booking_System
 * @subpackage Bookit_Booking_System/public/templates
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

require_once BOOKIT_PLUGIN_DIR . 'includes/core/class-session-manager.php';
Bookit_Session_Manager::init();

require_once BOOKIT_PLUGIN_DIR . 'includes/models/class-service-model.php';

$service_model           = new Bookit_Service_Model();
$services_by_category      = $service_model->get_active_services_by_category();
$selected_service_id     = (int) Bookit_Session_Manager::get( 'service_id', 0 );
$total_services            = array_sum( array_map( 'count', $services_by_category ) );

if ( 1 === $total_services ) {
	$single_service = null;
	foreach ( $services_by_category as $services ) {
		if ( ! empty( $services ) ) {
			$single_service = $services[0];
			break;
		}
	}
	if ( $single_service ) {
		Bookit_Session_Manager::set( 'service_id', $single_service['id'] );
		Bookit_Session_Manager::set( 'service_name', $single_service['name'] );
		Bookit_Session_Manager::set( 'service_duration', $single_service['duration'] );
		Bookit_Session_Manager::set( 'current_step', 2 );
		if ( wp_safe_redirect( get_permalink() ) ) {
			exit;
		}
	}
}

if ( empty( $services_by_category ) ) {
	?>
<div class="bookit-v2-step bookit-v2-step--1">
	<div class="bookit-v2-step-body">
		<h2 class="bookit-v2-step-heading"><?php esc_html_e( 'No Services Available', 'bookit-booking-system' ); ?></h2>
		<p class="bookit-v2-step-subheading"><?php esc_html_e( 'We\'re currently not taking new bookings. Please check back soon!', 'bookit-booking-system' ); ?></p>
	</div>
</div>
	<?php
	return;
}

$grid_few_class = ( $total_services <= 2 ) ? ' bookit-v2-services-grid--few' : '';
?>
<div class="bookit-v2-step bookit-v2-step--1">
	<div class="bookit-v2-step-body">
		<h2 class="bookit-v2-step-heading"><?php esc_html_e( 'What would you like to book?', 'bookit-booking-system' ); ?></h2>
		<p class="bookit-v2-step-subheading"><?php esc_html_e( 'Select a service to get started.', 'bookit-booking-system' ); ?></p>

		<?php foreach ( $services_by_category as $category_name => $services ) : ?>
			<p class="bookit-v2-category-label"><?php echo esc_html( $category_name ); ?></p>
			<div class="bookit-v2-services-grid<?php echo esc_attr( $grid_few_class ); ?>">
				<?php foreach ( $services as $service ) : ?>
					<?php
					$card_classes = 'bookit-v2-service-card';
					if ( (int) $service['id'] === $selected_service_id ) {
						$card_classes .= ' bookit-v2-service-card--selected';
					}
					?>
					<div
						class="<?php echo esc_attr( $card_classes ); ?>"
						data-service-id="<?php echo esc_attr( $service['id'] ); ?>"
						data-service-name="<?php echo esc_attr( $service['name'] ); ?>"
						data-service-duration="<?php echo esc_attr( $service['duration'] ); ?>"
					>
						<p class="bookit-v2-service-name"><?php echo esc_html( $service['name'] ); ?></p>
						<p class="bookit-v2-service-duration">
							<?php
							/* translators: %d: duration in minutes */
							echo esc_html( sprintf( __( '%d min', 'bookit-booking-system' ), (int) $service['duration'] ) );
							?>
						</p>
					</div>
				<?php endforeach; ?>
			</div>
		<?php endforeach; ?>

		<div class="bookit-v2-sticky-footer">
			<div class="bookit-v2-footer-inner">
				<button type="button" class="bookit-v2-cta-btn" id="bookit-v2-continue"
					<?php echo ( 0 === $selected_service_id ) ? 'disabled' : ''; ?>><?php esc_html_e( 'Continue', 'bookit-booking-system' ); ?></button>
				<button type="button" class="bookit-v2-btn-back bookit-v2-btn-back--disabled" disabled><?php esc_html_e( 'Back', 'bookit-booking-system' ); ?></button>
			</div>
		</div>
	</div>
</div>
