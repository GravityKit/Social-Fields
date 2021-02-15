<?php
/*
Plugin Name: Gravity Forms Social Fields
Plugin URI: https://katz.co
Description: Validate fields
Author: Katz Web Services, Inc.
Version: 0.2
Author URI: http://katzwebservices.com
*/

add_action( 'gform_loaded', function () {
	include plugin_dir_path( __FILE__ ) . 'class-gv-gf-field-tweet.php';
	include plugin_dir_path( __FILE__ ) . 'class-gv-validate-social-profiles.php';
});