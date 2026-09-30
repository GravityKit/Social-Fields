<?php
/**
 * Social Fields unit test bootstrap.
 *
 * Loads the WordPress test suite, Gravity Forms and this plugin. Runs under gkunit, which sets
 * WP_TESTS_DIR and GF_PLUGIN_DIR inside its container; GF_PLUGIN_DIR selects which Gravity Forms
 * build the suite runs against.
 */

ini_set( 'display_errors', 'on' );
error_reporting( E_ALL );

$_social_fields_tests_dir = __DIR__;
$_social_fields_dir       = dirname( $_social_fields_tests_dir );
$_wp_tests_dir            = getenv( 'WP_TESTS_DIR' ) ?: '/tmp/wordpress-tests-lib';
$_gf_plugin_dir           = getenv( 'GF_PLUGIN_DIR' ) ?: dirname( $_social_fields_dir ) . '/gravityforms';

if ( ! defined( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH' ) ) {
	define( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH', $_social_fields_dir . '/vendor/yoast/phpunit-polyfills' );
}

require_once $_wp_tests_dir . '/includes/functions.php';

// Social profile validation makes remote requests; tests must never reach the network.
tests_add_filter(
	'pre_http_request',
	function () {
		return new WP_Error( 'http_blocked', 'HTTP calls are blocked in tests.' );
	}
);

$_SERVER['REMOTE_ADDR'] = '127.0.0.1';

tests_add_filter(
	'muplugins_loaded',
	function () use ( $_gf_plugin_dir, $_social_fields_dir ) {
		if ( ! file_exists( $_gf_plugin_dir . '/gravityforms.php' ) ) {
			throw new RuntimeException( 'Gravity Forms not found at ' . $_gf_plugin_dir . '. Set GF_PLUGIN_DIR.' );
		}

		// GF's first run downloads its translations registry, which fatals before WP's cookie constants exist.
		add_filter(
			'pre_site_transient_t15s-registry-gforms',
			function () {
				$registry                = new stdClass();
				$registry->projects      = array();
				$registry->_last_checked = time();

				return $registry;
			}
		);

		// The plugin hooks `gform_loaded`, which Gravity Forms fires on `plugins_loaded`.
		require_once $_social_fields_dir . '/gravityforms-social-profiles-validator.php';

		require_once $_gf_plugin_dir . '/gravityforms.php';
		require_once GFCommon::get_base_path() . '/form_display.php';

		// The WordPress test suite rewrites tables to temporary ones, which GF's schema upgrade cannot use.
		remove_all_filters( 'query' );

		if ( function_exists( 'gf_upgrade' ) ) {
			@gf_upgrade()->maybe_upgrade();
		} else {
			@GFForms::setup( true );
		}
	}
);

require_once $_wp_tests_dir . '/includes/bootstrap.php';

if ( ! class_exists( 'GV\Validate_Social_Profiles' ) ) {
	throw new RuntimeException( 'Social Fields did not load; GV\Validate_Social_Profiles is unavailable.' );
}

register_shutdown_function(
	function () {
		if ( class_exists( 'RGFormsModel' ) ) {
			RGFormsModel::drop_tables();
		}
	}
);
