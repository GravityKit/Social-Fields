<?php
/*
Plugin Name: Gravity Forms Social Profiles Validator
Plugin URI: https://katz.co
Description: Validate Twitter and Facebook fields in Gravity Forms by using `validate-twitter` or `validate-facebook` field   class names
Author: Katz Web Services, Inc.
Version: 0.1
Author URI: http://katzwebservices.com
*/


/**
 * Class for validating social profiles.
 *
 * Add "CSS Class Name" field settings to validate a form in the format of "validate-{service}":
 *
 * Examples:
 *
 *     * validate-twitter
 *     * validate-facebook
 *
 * @link http://pastie.org/1769048 Original Gravity Forms code.
 */
class KWS_GF_Validate_Social {

    var $accounts = array();

    function __construct() {

        /**
         * Tap in here to modify what accounts are validated.
         * @var [type]
         */
        $this->accounts = apply_filters( 'kws_gf_validate_social_accounts', array(
            'twitter',
            'facebook',
        ));

        // 1 - Tie our validation function to the 'gform_validation' hook
        add_filter('gform_validation', array(&$this, 'validate_form'));

        add_filter('kws_gf_is_valid_twitter', array(&$this, 'is_valid_twitter'), 10, 2);

        add_filter('kws_gf_is_valid_facebook', array(&$this, 'is_valid_facebook'), 10, 2);

    }

    function is_valid_twitter($bool, $value) {

        if(trim(rtrim($value)) === '') { return true; }

        return preg_match('/^@?[A-Za-z0-9_]{1,15}$/', $value);
    }

    /**
     * Check Facebook Graph for account
     *
     * @param  [type]  $bool  [description]
     * @param  [type]  $value [description]
     * @return boolean        [description]
     */
    function is_valid_facebook($bool, $value) {

        if(trim(rtrim($value)) === '') { return true; }

        // Match Groups:
        // 1. http or https
        // 2. account name
        // 3. query string
        preg_match('/^^(http\:\/\/|https\:\/\/)?(?:www\.)?facebook\.com\/(?:(?:\w\.)*#!\/)?(?:pages\/)?(?:[\w\-\.]*\/)*([\w\-\.]*)(\?.+)?/ism', $value, $matches);

        // A Facebook URL has been entered.
        if(!empty($matches)) {
            if(!empty($matches[2])) {
                $account = $matches[2];
            }
        } else {
            $account = $value;
        }

        $fb_url = sprintf('https://graph.facebook.com/%s', $account);

        $json_txt = $this->get_url($fb_url);

        $json = json_decode($json_txt, true);

        // Error 803 is alias doesn't exist
        return (false == (!empty($json['error']) && (int)$json['error']['code'] === 803));
    }

    function get_url($url) {

        $cache_key = 'gfvl'.sha1($url);

        $response = get_transient( $cache_key );

        if($response === false) {

            $request = wp_remote_get( $url, array(
                'timeout' => 10,
                'sslverify' => false
            ));

            if(!is_wp_error( $request )) {
                $response = wp_remote_retrieve_body( $request );

                set_transient( $cache_key, $response, WEEK_IN_SECONDS );
            }
        }

        return $response;
    }

    /**
     * Check whether a field has the
     * @param  [type] $field [description]
     * @return string|boolean        If field has validation, return string key, otherwise false.
     */
    function get_field_validation_class($field) {

        foreach((array)$this->accounts as $account) {
            $validate_css_class = sprintf('validate-%s', $account);

            // CSS classes, separated by spaces
            $classes = explode(' ', $field['cssClass']);

            // 5 - If the field does not have our designated CSS class, skip it
            if(!in_array($validate_css_class, $classes)) {
                continue;
            }

            // We want to use this key for this field.
            return $account;
        }

        return false;
    }

    function validate_form($validation_result) {

        // 2 - Get the form object from the validation result
        $form = $validation_result["form"];

        // 3 - Get the current page being validated
        $current_page = rgpost('gform_source_page_number_' . $form['id']) ? rgpost('gform_source_page_number_' . $form['id']) : 1;

        // 4 - Loop through the form fields
        foreach($form['fields'] as &$field){

            if(!$validate_key = $this->get_field_validation_class($field)) { continue; }

            // 6 - Get the field's page number
            $field_page = $field['pageNumber'];

            // 7 - Check if the field is hidden by GF conditional logic
            $is_hidden = RGFormsModel::is_field_hidden($form, $field, array());

            // 8 - If the field is not on the current page OR if the field is hidden, skip it
            if($field_page != $current_page || $is_hidden)
                continue;

            // 9 - Get the submitted value from the $_POST
            $field_value = rgpost("input_{$field['id']}");

            // 10 - Make a call to your validation function to validate the value
            $is_valid = $this->is_valid($validate_key, $field_value);

            // 11 - If the field is valid we don't need to do anything, skip it
            if($is_valid)
                continue;

            // 12 - The field field validation, so first we'll need to fail the validation for the entire form
            $validation_result['is_valid'] = false;

            // 13 - Next we'll mark the specific field that failed and add a custom validation message
            $field['failed_validation'] = true;
            $field['validation_message'] = !empty($field['validation_message']) ? $field['validation_message'] : $this->get_invalid_message($validate_key);

        }

        // 14 - Assign our modified $form object back to the validation result
        $validation_result['form'] = $form;

        // 15 - Return the validation result
        return $validation_result;
    }

    /**
     * Get the invalid message for each validated social profile.
     * @param  string $key The key of the social profile, eg: `twitter`
     * @return string      Error message. Returns default if none found for `$key`
     */
    function get_invalid_message($key) {

        $messages = apply_filters('kws_gf_validate_social_invalid_message', array(
            'twitter' => __('The Twitter account is not valid', 'kws-gf-validate-social'),
            'facebook' => __('This is not a valid Facebook page or account.', 'kws-gf-validate-social')
        ));

        return isset($messages[$key]) ? $messages[$key] : 'The value for this field is invalid';
    }

    function is_valid($key, $value) {

        $key = esc_attr($key);

        return (boolean)apply_filters( 'kws_gf_is_valid_'.$key , true, $value );
    }


}

new KWS_GF_Validate_Social;
