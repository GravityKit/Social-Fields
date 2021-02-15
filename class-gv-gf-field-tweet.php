<?php

namespace GV;

class GF_Field_Tweet extends \GF_Field_Website {

	/**
	 * Defines the field type.
	 * @var string The field type.
	 */
	public $type = 'tweet';

	public $failed_validation = false;

	public $validation_message = '';

	public function __construct( $data = array() ) {
		parent::__construct( $data );

		add_filter( 'gravityview/template/field/tweet/output', array( $this, 'filter_gravityview_output' ), 10, 2 );

		add_filter( 'gravityview_template_tweet_options', array( $this, 'modify_gravityview_field_options' ) );
	}

	/**
	 * Removes irrelevant settings from GravityView fields ("Show as link" and "Open in new window")
	 *
	 * @param array $field_options GravityView field options
	 *
	 * @return array
	 */
	public function modify_gravityview_field_options( $field_options ) {

		unset( $field_options['show_as_link'], $field_options['new_window'] );

		$field_options['oembed_tweet'] = array(
			'type' => 'checkbox',
			'value' => true,
			'label' => __( 'Show as Embedded Tweet', 'gravityview' ),
			'desc' => __( 'Display the tweet as an embedded card. If disabled, will display a link to the tweet.', 'gravityview' ),
		);

		return $field_options;
	}

	/**
	 * Converts the Tweet URL into an embedded tweet.
	 *
	 * @param string $output The current output.
	 * @param Template_Context The template context this is being called from.
	 *
	 * @return string HTML embed code, if fetchable using oEmbed. Original anchor tag HTML if not.
	 */
	public function filter_gravityview_output( $output, $context ) {

		if ( empty( $context->entry['id'] ) ) {
			return $output;
		}

		if ( ! $context->field instanceof Field ) {
			return $output;
		}

		if ( ! $context->field->oembed_tweet ) {
			return $output;
		}

		$use_cache = true;
		if ( \GVCommon::has_cap( 'publish_gravityviews' ) ) {
			$use_cache =  ! isset( $_GET['cache'] ) && ! isset( $_GET['nocache'] );
		}

		$embed_code = $this->get_tweet_embed( $context, $use_cache, $use_cache );

		return $embed_code ? $embed_code : $output;
	}

	/**
	 * Fetches the tweet embed HTML from cache or oEmbed
	 *
	 * @param Template_Context The template context this is being called from.
	 * @param bool $use_cache Whether to fetch cached content from entry meta. Default: true.
	 * @param bool $set_cache Whether to set the oEmbed-fetched content as entry meta cache. Default: true.
	 *
	 * @return string|false HTML string of embedded tweet, or false if not fetchable.
	 */
	protected function get_tweet_embed( $context, $use_cache = true, $set_cache = true ) {

		$meta_key = self::get_tweet_cache_meta_key( $context );

		if ( $use_cache ) {

			$cached_output = \gform_get_meta( $context->entry['id'], $meta_key );

			if ( $cached_output ) {
				return $cached_output;
			}
		}

		$value = wp_oembed_get( $context->value );

		// Fetching oEmbed failed. Return original value.
		if ( ! $value ) {
			return false;
		}

		if ( $set_cache ) {
			\gform_add_meta( $context->entry['id'], $meta_key, $value, $context->entry['form_id'] );
		}

		return $value;
	}

	/**
	 * @param Template_Context The template context this is being called from.
	 *
	 * @return string key used to store oEmbed HTML of rendered tweet
	 */
	public static function get_tweet_cache_meta_key( $context ) {

		$form_id = $context->field->field->formId;
		$field_id = $context->field->ID;

		return sprintf( 'tweet_output_%d:%d', $form_id, $field_id );
	}

	/**
	 * Defines the field title to be used in the form editor.
	 *
	 * @since  Unknown
	 * @access public
	 *
	 * @used-by GFCommon::get_field_type_title()
	 *
	 * @return string The field title. Translatable and escaped.
	 */
	public function get_form_editor_field_title() {
		return esc_attr__( 'Tweet', 'gravityforms' );
	}

	/**
	 * Validates inputs for the URL field.
	 *
	 * @uses    GF_Field_Website::validate()
	 *
	 * @param array|string $value The field value to be validated.
	 * @param array        $form  The Form Object.
	 *
	 * @return void
	 */
	public function validate( $value, $form ) {

		parent::validate( $value, $form );

		if ( $this->failed_validation ) {
			return;
		}

		preg_match( '#https?://(?:www.)?twitter.com/(?:.*?)/status/([\d]+)/?#ism', $value, $matches );

		if ( empty( $matches ) ) {
			$this->failed_validation = true;
			$this->validation_message = __('Not a valid Tweet URL.');
			return;
		}
	}

}

\GF_Fields::register( new GF_Field_Tweet() );