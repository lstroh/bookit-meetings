<?php
/**
 * Template Name: Bookit Wizard V2
 *
 * @package    Bookit_Booking_System
 * @subpackage Bookit_Booking_System/public/templates
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

get_header();
echo do_shortcode( '[bookit_wizard_v2]' );
get_footer();
