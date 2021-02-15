<?php

namespace GV;

/**
 * Class for validating social profiles.
 *
 * Add "CSS Class Name" field settings to validate a form in the format of "validate-{service}":
 *
 * Examples:
 *  - validate-twitter
 *  - validate-facebook
 *
 * @link http://pastie.org/1769048 Original Gravity Forms code.
 */
class Validate_Social_Profiles {

	var $accounts = array();

	function __construct() {

		/**
		 * Tap in here to modify what accounts are validated.
		 *
		 * Each account has `gravityview/social_fields/profile/is_valid/{$key}` filters dynamically created.
		 *
		 * @var string[] Types of accounts to validate.
		 */
		$this->accounts = apply_filters( 'gravityview/social_fields/accounts', array(
			'twitter',
			'facebook',
		) );

		// 1 - Tie our validation function to the 'gform_validation' hook
		add_filter( 'gform_validation', array( $this, 'validate_form' ) );

		add_filter( 'gravityview/social_fields/profile/twitter/is_valid', array( $this, 'is_valid_twitter' ), 10, 2 );

		add_filter( 'gravityview/social_fields/profile/facebook/is_valid', array( $this, 'is_valid_facebook' ), 10, 2 );
	}

	/**
	 * Verifies Twitter account
	 *
	 * @param bool   $bool Is the submitted value valid? Default: true.
	 * @param string $value The submitted value to verify.
	 *
	 * @return bool True: Account is valid or empty. False: account is invalid.
	 */
	function is_valid_twitter( $bool, $value ) {

		if ( trim( rtrim( $value ) ) === '' ) {
			return true;
		}

		if ( ! preg_match( '/^@?[A-Za-z0-9_]{1,15}$/', $value ) ) {
			return false;
		}

		if ( apply_filters( 'gravityview/social_fields/profile/twitter/verify_remote', true ) ) {

			// Use head, since it might be faster.
			$response = $this->get_url( 'https://twitter.com/' . $value, 'HEAD' );

			if ( is_wp_error( $response ) ) {
				return true;
			}

			return wp_remote_retrieve_response_code( $response ) !== 404;
		}

		return true;
	}

	/**
	 * Check Facebook Graph for account
	 *
	 * @param bool   $bool Is the submitted value valid? Default: true.
	 * @param string $value The submitted value to verify.
	 *
	 * @return bool True: Account is valid or empty. False: account is invalid.
	 */
	public function is_valid_facebook( $bool, $value ) {

		if ( trim( rtrim( $value ) ) === '' ) {
			return true;
		}

		// Match Groups:
		// 1. http or https
		// 2. account name
		// 3. query string
		preg_match( '/^^(http\:\/\/|https\:\/\/)?(?:www\.)?facebook\.com\/(?:(?:\w\.)*#!\/)?(?:pages\/)?(?:[\w\-\.]*\/)*([\w\-\.]*)(\?.+)?/ism', $value, $matches );

		$account = $value;

		// A Facebook URL has been entered.
		if ( ! empty( $matches ) ) {
			if ( ! empty( $matches[2] ) ) {
				$account = $matches[2];
			}
		}

		$fb_url = sprintf( 'https://graph.facebook.com/%s', $account );

		$response = $this->get_url( $fb_url );

		// The request shouldn't cause an error; this likely has nothing to do with valid profile URL or not.
		if ( is_wp_error( $response ) ) {
			return true;
		}

		$json_txt = wp_remote_retrieve_body( $response );

		$json = json_decode( $json_txt, true );

		// Error 803 is alias doesn't exist
		return ( false == ( ! empty( $json['error'] ) && (int) $json['error']['code'] === 803 ) );
	}

	/**
	 * Fetch remote URL for the validators
	 *
	 * @param string $url URL to fetch
	 *
	 * @return array|\WP_Error WordPress response array or error
	 */
	function get_url( $url, $method = 'GET' ) {

		$cache_key = 'gvsfp' . sha1( $url );

		$response = get_transient( $cache_key );

		if ( false === $response ) {

			$response = wp_remote_request( $url, array(
				'timeout'   => 10,
				'sslverify' => false,
				'method'    => $method
			) );

			// If it's an error, set null transient
			if ( is_wp_error( $response ) ) {
				set_transient( $cache_key, null, WEEK_IN_SECONDS );
			} else {
				set_transient( $cache_key, $response, WEEK_IN_SECONDS );
			}
		}

		return $response;
	}

	/**
	 * Check whether a field has the `validate-{account}` CSS class.
	 *
	 * @param \GF_Field $field Field to check for the class existing.
	 *
	 * @return string|boolean   If field has validation, return string key, otherwise false.
	 */
	function get_field_validation_class( $field ) {

		if ( empty( $field['cssClass'] ) ) {
			return false;
		}

		foreach ( (array) $this->accounts as $account ) {
			$validate_css_class = sprintf( 'validate-%s', $account );

			// CSS classes, separated by spaces
			$classes = explode( ' ', $field['cssClass'] );

			// 5 - If the field does not have our designated CSS class, skip it
			if ( ! in_array( $validate_css_class, $classes ) ) {
				continue;
			}

			// We want to use this key for this field.
			return $account;
		}

		return false;
	}

	/**
	 * Validates the form.
	 *
	 * Check each field for the social profiles Custom CSS class. If exists, validates.
	 *
	 * @param  array $validation_result {
	 *   @type bool $is_valid
	 *   @type array $form
	 *   @type int $failed_validation_page The page number which has failed validation.
	 * }
	 *
	 * @return mixed
	 */
	public function validate_form( $validation_result ) {

		// 2 - Get the form object from the validation result
		$form = $validation_result['form'];

		// 3 - Get the current page being validated
		$current_page = rgpost( 'gform_source_page_number_' . $form['id'] ) ? rgpost( 'gform_source_page_number_' . $form['id'] ) : 1;

		// 4 - Loop through the form fields
		foreach ( $form['fields'] as &$field ) {

			if ( ! $validate_key = $this->get_field_validation_class( $field ) ) {
				continue;
			}

			// 6 - Get the field's page number
			if ( (int) $field['pageNumber'] !== (int) $current_page ) {
				continue;
			}

			// 7 - Check if the field is hidden by GF conditional logic
			$is_hidden = \RGFormsModel::is_field_hidden( $form, $field, array() );

			// 8 - If the field is not on the current page OR if the field is hidden, skip it
			if ( $is_hidden ) {
				continue;
			}

			// 9 - Get the submitted value from the $_POST
			$field_value = rgpost( "input_{$field['id']}" );

			// 10 - Make a call to your validation function to validate the value
			$is_valid = $this->is_valid( $validate_key, $field_value );

			// 11 - If the field is valid we don't need to do anything, skip it
			if ( $is_valid ) {
				continue;
			}

			// 12 - The field field validation, so first we'll need to fail the validation for the entire form
			$validation_result['is_valid'] = false;

			// 13 - Next we'll mark the specific field that failed and add a custom validation message
			$field['failed_validation']  = true;
			$field['validation_message'] = ! empty( $field['validation_message'] ) ? $field['validation_message'] : $this->get_invalid_message( $validate_key );

		}

		// 14 - Assign our modified $form object back to the validation result
		$validation_result['form'] = $form;

		// 15 - Return the validation result
		return $validation_result;
	}

	/**
	 * Get the invalid message for each validated social profile.
	 *
	 * @param string $key The key of the social profile, eg: `twitter`
	 *
	 * @return string      Error message. Returns default if none found for `$key`
	 */
	function get_invalid_message( $key ) {

		$messages = apply_filters( 'gravityview/social_fields/profiles/invalid_message', array(
			'twitter'  => __( 'The Twitter account is not valid', 'gravityview-social-fields' ),
			'facebook' => __( 'This is not a valid Facebook page or account.', 'gravityview-social-fields' )
		) );

		return isset( $messages[ $key ] ) ? $messages[ $key ] : __( 'The value for this field is invalid', 'gravityview-social-fields' );
	}

	/**
	 * Runs apply_filters() to validate the field content.
	 *
	 * @param string $key Social profile key ('facebook', 'twitter').
	 * @param string $value Submitted value.
	 *
	 * @return bool True: valid; False: invalid.
	 */
	function is_valid( $key, $value ) {

		$key = esc_attr( $key );

		return (bool) apply_filters( 'gravityview/social_fields/profile/' . $key . '/is_valid', true, $value );
	}

}

new Validate_Social_Profiles;
