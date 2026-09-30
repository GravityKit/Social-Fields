<?php
/**
 * Social Fields validation for fields placed inside a Gravity Forms Repeater.
 *
 * Repeater sub-fields are not in the top-level `$form['fields']`, and Gravity Forms validates each
 * of them once per row. These tests cover both validators (the `validate-{service}` CSS class and
 * the Tweet field type) at the top level, inside a repeater, and inside a nested repeater.
 */
class Social_Fields_Repeater_Validation_Test extends WP_UnitTestCase {

	/**
	 * Form IDs created by a test, deleted in tear_down().
	 *
	 * @var int[]
	 */
	private $form_ids = array();

	public function set_up() {
		parent::set_up();

		// Twitter validation otherwise sends a HEAD request to twitter.com; only the format check is under test.
		add_filter( 'gravityview/social_fields/profile/twitter/verify_remote', '__return_false' );

		$_POST = array();
	}

	public function tear_down() {
		foreach ( $this->form_ids as $form_id ) {
			GFAPI::delete_form( $form_id );
		}

		$this->form_ids = array();
		$_POST          = array();

		remove_filter( 'gravityview/social_fields/profile/twitter/verify_remote', '__return_false' );

		parent::tear_down();
	}

	/**
	 * Creates a form and remembers it for cleanup.
	 *
	 * @param array $fields Field arrays.
	 *
	 * @return int Form ID.
	 */
	private function create_form( $fields ) {
		$form_id = GFAPI::add_form(
			array(
				'title'  => 'Social Fields repeater test ' . wp_generate_uuid4(),
				'fields' => $fields,
			)
		);

		$this->assertIsInt( $form_id, 'The fixture form could not be created.' );

		$this->form_ids[] = $form_id;

		return $form_id;
	}

	/**
	 * A repeater (ID 10) holding a text field (ID 11) that validates as a Twitter handle.
	 *
	 * @return int Form ID.
	 */
	private function create_twitter_repeater_form() {
		return $this->create_form(
			array(
				array(
					'type'   => 'repeater',
					'id'     => 10,
					'label'  => 'Team',
					'fields' => array(
						array(
							'type'     => 'text',
							'id'       => 11,
							'label'    => 'Twitter handle',
							'cssClass' => 'validate-twitter',
						),
						array(
							'type'  => 'name',
							'id'    => 12,
							'label' => 'Name',
							'inputs' => array(
								array( 'id' => '12.3', 'label' => 'First' ),
								array( 'id' => '12.6', 'label' => 'Last' ),
							),
						),
					),
				),
			)
		);
	}

	/**
	 * Control: a top-level field with the class is validated, so the fixture and filter are wired up.
	 */
	public function test_top_level_twitter_field_is_validated() {
		$form_id = $this->create_form(
			array(
				array(
					'type'     => 'text',
					'id'       => 1,
					'label'    => 'Twitter handle',
					'cssClass' => 'validate-twitter',
				),
			)
		);

		$result = GFAPI::validate_form( $form_id, array( 'input_1' => 'not a handle!' ) );

		$this->assertFalse( $result['is_valid'] );
		$this->assertSame( 'The Twitter account is not valid', $result['validation_messages'][1] );
	}

	/**
	 * An invalid handle in the second row of a repeater fails the form.
	 */
	public function test_invalid_twitter_handle_in_repeater_row_fails_validation() {
		$form_id = $this->create_twitter_repeater_form();

		$result = GFAPI::validate_form(
			$form_id,
			array(
				'input_11'   => array( 'valid_handle', 'not a handle!' ),
				'input_12_3' => array( 'Ann', 'Bob' ),
				'input_12_6' => array( 'Lee', 'Ray' ),
			)
		);

		$this->assertFalse( $result['is_valid'], 'An invalid Twitter handle inside a repeater row was accepted.' );
		$this->assertArrayHasKey( 10, $result['validation_messages'], 'The repeater should carry the failure.' );
	}

	/**
	 * Only the invalid row is flagged, with the Social Fields message.
	 */
	public function test_repeater_validation_is_reported_per_row() {
		$form_id = $this->create_twitter_repeater_form();

		$seen = array();

		$recorder = function ( $result, $value, $form, $field ) use ( &$seen ) {
			if ( 11 === (int) $field->id ) {
				$seen[ (string) $field->get_context_property( 'itemIndex' ) ] = $result;
			}

			return $result;
		};

		add_filter( 'gform_field_validation', $recorder, 1000, 4 );

		GFAPI::validate_form(
			$form_id,
			array(
				'input_11'   => array( 'valid_handle', 'not a handle!', '' ),
				'input_12_3' => array( 'Ann', 'Bob', 'Cy' ),
				'input_12_6' => array( 'Lee', 'Ray', 'Do' ),
			)
		);

		remove_filter( 'gform_field_validation', $recorder, 1000 );

		$this->assertCount( 3, $seen, 'Gravity Forms should validate the sub-field once per row.' );
		$this->assertTrue( $seen['0']['is_valid'], 'Row 0 holds a valid handle.' );
		$this->assertFalse( $seen['1']['is_valid'], 'Row 1 holds an invalid handle.' );
		$this->assertSame( 'The Twitter account is not valid', $seen['1']['message'] );
		$this->assertTrue( $seen['2']['is_valid'], 'An empty row is not a Twitter error.' );
	}

	/**
	 * Valid handles in every row, and an empty row, pass.
	 */
	public function test_valid_twitter_handles_in_repeater_pass() {
		$form_id = $this->create_twitter_repeater_form();

		$result = GFAPI::validate_form(
			$form_id,
			array(
				'input_11'   => array( 'valid_handle', '@other_one', '' ),
				'input_12_3' => array( 'Ann', 'Bob', 'Cy' ),
				'input_12_6' => array( 'Lee', 'Ray', 'Do' ),
			)
		);

		$this->assertTrue( $result['is_valid'], print_r( $result['validation_messages'], true ) );
	}

	/**
	 * A custom validation message on the sub-field replaces the default one.
	 */
	public function test_repeater_row_uses_field_custom_message() {
		$form_id = $this->create_form(
			array(
				array(
					'type'   => 'repeater',
					'id'     => 10,
					'label'  => 'Team',
					'fields' => array(
						array(
							'type'         => 'text',
							'id'           => 11,
							'label'        => 'Twitter handle',
							'cssClass'     => 'validate-twitter',
							'errorMessage' => 'Custom Twitter error',
						),
					),
				),
			)
		);

		$seen = array();

		$recorder = function ( $result, $value, $form, $field ) use ( &$seen ) {
			if ( 11 === (int) $field->id ) {
				$seen[] = $result;
			}

			return $result;
		};

		add_filter( 'gform_field_validation', $recorder, 1000, 4 );

		$result = GFAPI::validate_form( $form_id, array( 'input_11' => array( 'not a handle!' ) ) );

		remove_filter( 'gform_field_validation', $recorder, 1000 );

		$this->assertFalse( $result['is_valid'] );
		$this->assertSame( 'Custom Twitter error', $seen[0]['message'] );
	}

	/**
	 * A handle inside a repeater nested in another repeater is validated too.
	 */
	public function test_invalid_twitter_handle_in_nested_repeater_fails_validation() {
		$form_id = $this->create_form(
			array(
				array(
					'type'   => 'repeater',
					'id'     => 20,
					'label'  => 'Teams',
					'fields' => array(
						array(
							'type'  => 'text',
							'id'    => 21,
							'label' => 'Team name',
						),
						array(
							'type'   => 'repeater',
							'id'     => 22,
							'label'  => 'Members',
							'fields' => array(
								array(
									'type'     => 'text',
									'id'       => 23,
									'label'    => 'Twitter handle',
									'cssClass' => 'validate-twitter',
								),
							),
						),
					),
				),
			)
		);

		$valid = GFAPI::validate_form(
			$form_id,
			array(
				'input_21' => array( 'Red' ),
				'input_23' => array( array( 'valid_one', 'valid_two' ) ),
			)
		);

		$this->assertTrue( $valid['is_valid'], print_r( $valid['validation_messages'], true ) );

		$invalid = GFAPI::validate_form(
			$form_id,
			array(
				'input_21' => array( 'Red' ),
				'input_23' => array( array( 'valid_one', 'not a handle!' ) ),
			)
		);

		$this->assertFalse( $invalid['is_valid'], 'An invalid handle in a nested repeater row was accepted.' );
	}

	/**
	 * Tweet URL validation runs once per row when the Tweet field sits in a repeater.
	 */
	public function test_invalid_tweet_url_in_repeater_row_fails_validation() {
		$form_id = $this->create_form(
			array(
				array(
					'type'   => 'repeater',
					'id'     => 30,
					'label'  => 'Tweets',
					'fields' => array(
						array(
							'type'  => 'tweet',
							'id'    => 31,
							'label' => 'Tweet',
						),
					),
				),
			)
		);

		$valid = GFAPI::validate_form(
			$form_id,
			array( 'input_31' => array( 'https://twitter.com/gravityview/status/123', 'https://twitter.com/gravityview/status/456' ) )
		);

		$this->assertTrue( $valid['is_valid'], print_r( $valid['validation_messages'], true ) );

		$invalid = GFAPI::validate_form(
			$form_id,
			array( 'input_31' => array( 'https://twitter.com/gravityview/status/123', 'https://example.com/' ) )
		);

		$this->assertFalse( $invalid['is_valid'], 'A non-Tweet URL in a repeater row was accepted.' );
	}

	/**
	 * The embed path returns false, instead of fataling, when handed a repeater's array value.
	 */
	public function test_tweet_embed_ignores_array_value() {
		$field  = new GV\GF_Field_Tweet( array( 'id' => 31, 'formId' => 1 ) );
		$method = new ReflectionMethod( $field, 'get_tweet_embed' );
		$method->setAccessible( true );

		$embed = $method->invoke( $field, array( 'https://twitter.com/gravityview/status/123' ), 1, 1, 31, false, false );

		$this->assertFalse( $embed );
	}
}
