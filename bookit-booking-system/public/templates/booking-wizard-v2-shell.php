<?php
/**
 * Booking Wizard V2 shell template.
 *
 * @package    Bookit_Booking_System
 * @subpackage Bookit_Booking_System/public/templates
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

$current_step = (int) Bookit_Session_Manager::get( 'current_step', 1 );

$html  = '<div class="bookit-v2-wizard-container" data-step="' . esc_attr( $current_step ) . '">';
$html .= Bookit_Template_Loader::get_template( 'partials/booking-wizard-v2-progress.php', array( 'current_step' => $current_step ), true );

if ( 1 === $current_step ) {
	$html .= Bookit_Template_Loader::get_template( 'booking-wizard-v2-step-1.php', array(), true );
} elseif ( 2 === $current_step ) {
	$html .= Bookit_Template_Loader::get_template( 'booking-wizard-v2-step-2.php', array(), true );
} elseif ( 3 === $current_step ) {
	$html .= Bookit_Template_Loader::get_template( 'booking-wizard-v2-step-3.php', array(), true );
} elseif ( 4 === $current_step ) {
	$html .= Bookit_Template_Loader::get_template( 'booking-wizard-v2-step-4.php', array(), true );
} elseif ( 5 === $current_step ) {
	$html .= Bookit_Template_Loader::get_template( 'booking-wizard-v2-step-5.php', array(), true );
}

$html .= '</div>';

echo $html;
