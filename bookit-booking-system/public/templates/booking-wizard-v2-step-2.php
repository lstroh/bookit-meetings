<?php
/**
 * Booking Wizard V2 — Step 2: Staff selection
 *
 * @package    Bookit_Booking_System
 * @subpackage Bookit_Booking_System/public/templates
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

if ( ! function_exists( 'bookit_v2_avatar_colour' ) ) {
	/**
	 * Deterministic avatar background colour from full name.
	 *
	 * @param string $full_name Staff full name.
	 * @return string Hex colour.
	 */
	function bookit_v2_avatar_colour( $full_name ) {
		$palette = array( '#1a7a6e', '#7c5cbf', '#c46b1a', '#2a6db5', '#b5481a', '#1a6b7a' );
		return $palette[ abs( crc32( $full_name ) ) % count( $palette ) ];
	}
}

require_once BOOKIT_PLUGIN_DIR . 'includes/core/class-session-manager.php';
Bookit_Session_Manager::init();

$wizard_data = Bookit_Session_Manager::get_data();

if ( empty( $wizard_data['service_id'] ) ) {
	?>
<div class="bookit-v2-step bookit-v2-step--2">
	<div class="bookit-v2-step-body">
		<p><?php esc_html_e( 'Please select a service first.', 'bookit-booking-system' ); ?></p>
	</div>
</div>
	<?php
	return;
}

$service_id       = absint( $wizard_data['service_id'] );
$service_name     = isset( $wizard_data['service_name'] ) ? $wizard_data['service_name'] : '';
$service_duration = isset( $wizard_data['service_duration'] ) ? (int) $wizard_data['service_duration'] : 0;

require_once BOOKIT_PLUGIN_DIR . 'includes/models/class-staff-model.php';
$staff_model   = new Bookit_Staff_Model();
$staff_members = $staff_model->get_staff_for_service( $service_id );

global $wpdb;
$staff_hidden = $wpdb->get_var(
	$wpdb->prepare(
		"SELECT setting_value FROM {$wpdb->prefix}bookings_settings WHERE setting_key = %s LIMIT 1",
		'staff_selection_hidden'
	)
);
if ( '1' === $staff_hidden ) {
	Bookit_Session_Manager::set( 'staff_id', 0 );
	Bookit_Session_Manager::set( 'staff_name', 'Any available' );
	Bookit_Session_Manager::set( 'current_step', 3 );
	if ( wp_safe_redirect( get_permalink() ) ) {
		exit;
	}
}

if ( count( $staff_members ) === 1 ) {
	$only = $staff_members[0];
	Bookit_Session_Manager::set( 'staff_id', $only['id'] );
	Bookit_Session_Manager::set( 'staff_name', $only['full_name'] );
	Bookit_Session_Manager::set( 'current_step', 3 );
	if ( wp_safe_redirect( get_permalink() ) ) {
		exit;
	}
}

$selected_staff_id = (int) Bookit_Session_Manager::get( 'staff_id', -1 );

if ( empty( $staff_members ) ) {
	?>
<div class="bookit-v2-step bookit-v2-step--2">
	<div class="bookit-v2-step-body">
		<h2 class="bookit-v2-step-heading"><?php esc_html_e( 'No Staff Available', 'bookit-booking-system' ); ?></h2>
		<p class="bookit-v2-step-subheading"><?php esc_html_e( 'All staff members are currently unavailable for this service.', 'bookit-booking-system' ); ?></p>
	</div>
</div>
	<?php
	return;
}

$staff_count        = count( $staff_members );
$use_list_layout    = ( $staff_count <= 3 );
$layout_container_tag = $use_list_layout ? 'bookit-v2-staff-list' : 'bookit-v2-staff-grid';
?>
<div class="bookit-v2-step bookit-v2-step--2">
	<div class="bookit-v2-step-body">
		<h2 class="bookit-v2-step-heading"><?php esc_html_e( 'Who would you like?', 'bookit-booking-system' ); ?></h2>
		<p class="bookit-v2-step-subheading"><?php esc_html_e( 'Choose a team member for your appointment.', 'bookit-booking-system' ); ?></p>

		<div class="bookit-v2-confirm-banner">
			<span class="bookit-v2-confirm-banner-text">
				<?php
				echo esc_html( $service_name );
				echo ' · ';
				echo esc_html( (string) $service_duration );
				echo ' ';
				esc_html_e( 'min', 'bookit-booking-system' );
				?>
			</span>
			<button type="button" class="bookit-v2-confirm-banner-change" data-goto-step="1"><?php esc_html_e( 'Change', 'bookit-booking-system' ); ?></button>
		</div>

		<div class="<?php echo esc_attr( $layout_container_tag ); ?>">
			<?php foreach ( $staff_members as $staff ) : ?>
				<?php
				$is_unavailable = false;
				if ( isset( $staff['has_availability'] ) ) {
					$is_unavailable = ! (bool) $staff['has_availability'];
				} elseif ( isset( $staff['working_hours_count'] ) ) {
					$is_unavailable = (int) $staff['working_hours_count'] <= 0;
				}

				$initials = strtoupper( substr( $staff['first_name'], 0, 1 ) . substr( $staff['last_name'], 0, 1 ) );

				if ( $use_list_layout ) {
					$row_classes = 'bookit-v2-staff-row';
					if ( $is_unavailable ) {
						$row_classes .= ' bookit-v2-staff-row--unavailable';
					}
					if ( (int) $staff['id'] === $selected_staff_id ) {
						$row_classes .= ' bookit-v2-staff-row--selected';
					}
					?>
				<div class="<?php echo esc_attr( $row_classes ); ?>" data-staff-id="<?php echo esc_attr( $staff['id'] ); ?>">
					<?php if ( ! empty( $staff['photo_url'] ) ) : ?>
						<img
							class="bookit-v2-avatar"
							src="<?php echo esc_url( $staff['photo_url'] ); ?>"
							alt=""
							style="object-fit:cover;border-radius:50%;"
						/>
					<?php else : ?>
						<span class="bookit-v2-avatar" style="background: <?php echo esc_attr( bookit_v2_avatar_colour( $staff['full_name'] ) ); ?>">
							<?php echo esc_html( $initials ); ?>
						</span>
					<?php endif; ?>
					<div class="bookit-v2-staff-info">
						<p class="bookit-v2-staff-name"><?php echo esc_html( $staff['full_name'] ); ?></p>
						<?php if ( ! empty( $staff['title'] ) ) : ?>
							<p class="bookit-v2-staff-title"><?php echo esc_html( $staff['title'] ); ?></p>
						<?php endif; ?>
						<?php if ( ! empty( $staff['bio'] ) ) : ?>
							<p class="bookit-v2-staff-bio" hidden><?php echo esc_html( $staff['bio'] ); ?></p>
						<?php endif; ?>
					</div>
					<?php if ( $is_unavailable ) : ?>
						<p class="bookit-v2-staff-price"><?php esc_html_e( 'No availability this month', 'bookit-booking-system' ); ?></p>
					<?php elseif ( isset( $staff['price'] ) ) : ?>
						<p class="bookit-v2-staff-price"><?php echo esc_html( '£' . number_format( (float) $staff['price'], 2 ) ); ?></p>
					<?php endif; ?>
				</div>
					<?php
				} else {
					$card_classes = 'bookit-v2-staff-card';
					if ( $is_unavailable ) {
						$card_classes .= ' bookit-v2-staff-card--unavailable';
					}
					if ( (int) $staff['id'] === $selected_staff_id ) {
						$card_classes .= ' bookit-v2-staff-card--selected';
					}
					?>
				<div class="<?php echo esc_attr( $card_classes ); ?>" data-staff-id="<?php echo esc_attr( $staff['id'] ); ?>">
					<?php if ( ! empty( $staff['photo_url'] ) ) : ?>
						<img
							class="bookit-v2-avatar"
							src="<?php echo esc_url( $staff['photo_url'] ); ?>"
							alt=""
							style="object-fit:cover;border-radius:50%;"
						/>
					<?php else : ?>
						<span class="bookit-v2-avatar" style="background: <?php echo esc_attr( bookit_v2_avatar_colour( $staff['full_name'] ) ); ?>">
							<?php echo esc_html( $initials ); ?>
						</span>
					<?php endif; ?>
					<p class="bookit-v2-staff-name"><?php echo esc_html( $staff['full_name'] ); ?></p>
					<?php if ( ! empty( $staff['title'] ) ) : ?>
						<p class="bookit-v2-staff-title"><?php echo esc_html( $staff['title'] ); ?></p>
					<?php endif; ?>
					<?php if ( $is_unavailable ) : ?>
						<p class="bookit-v2-staff-price"><?php esc_html_e( 'No availability this month', 'bookit-booking-system' ); ?></p>
					<?php elseif ( isset( $staff['price'] ) ) : ?>
						<p class="bookit-v2-staff-price"><?php echo esc_html( '£' . number_format( (float) $staff['price'], 2 ) ); ?></p>
					<?php endif; ?>
					<?php if ( ! empty( $staff['bio'] ) ) : ?>
						<p class="bookit-v2-staff-bio" hidden><?php echo esc_html( $staff['bio'] ); ?></p>
					<?php endif; ?>
				</div>
					<?php
				}
			endforeach;
			?>

			<?php if ( $use_list_layout ) : ?>
				<?php
				$any_classes = 'bookit-v2-staff-row bookit-v2-any-available';
				if ( 0 === $selected_staff_id ) {
					$any_classes .= ' bookit-v2-staff-row--selected';
				}
				?>
				<div class="<?php echo esc_attr( $any_classes ); ?>" data-staff-id="0">
					<div class="bookit-v2-avatar" style="background: #6b7280">?</div>
					<div class="bookit-v2-staff-info">
						<p class="bookit-v2-any-available-name"><?php esc_html_e( 'Any available team member', 'bookit-booking-system' ); ?></p>
						<p class="bookit-v2-any-available-sub"><?php esc_html_e( 'We\'ll match you with the first available person for your chosen time.', 'bookit-booking-system' ); ?></p>
					</div>
				</div>
			<?php else : ?>
				<div style="grid-column: 1 / -1">
					<?php
					$any_classes = 'bookit-v2-staff-row bookit-v2-any-available';
					if ( 0 === $selected_staff_id ) {
						$any_classes .= ' bookit-v2-staff-row--selected';
					}
					?>
					<div class="<?php echo esc_attr( $any_classes ); ?>" data-staff-id="0">
						<div class="bookit-v2-avatar" style="background: #6b7280">?</div>
						<div class="bookit-v2-staff-info">
							<p class="bookit-v2-any-available-name"><?php esc_html_e( 'Any available team member', 'bookit-booking-system' ); ?></p>
							<p class="bookit-v2-any-available-sub"><?php esc_html_e( 'We\'ll match you with the first available person for your chosen time.', 'bookit-booking-system' ); ?></p>
						</div>
					</div>
				</div>
			<?php endif; ?>
		</div>

		<div class="bookit-v2-sticky-footer">
			<div class="bookit-v2-footer-inner">
				<button type="button" class="bookit-v2-cta-btn" id="bookit-v2-continue"
					<?php echo ( -1 === $selected_staff_id ) ? 'disabled' : ''; ?>>
					<?php esc_html_e( 'Continue', 'bookit-booking-system' ); ?>
				</button>
				<a href="?step=1" class="bookit-v2-btn-back"><?php esc_html_e( 'Back', 'bookit-booking-system' ); ?></a>
			</div>
		</div>
	</div>
</div>
