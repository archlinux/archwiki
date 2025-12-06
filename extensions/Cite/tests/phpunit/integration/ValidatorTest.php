<?php

namespace Cite\Tests\Integration;

use Cite\Validator;
use Wikimedia\TestingAccessWrapper;

/**
 * @covers \Cite\Validator
 * @license GPL-2.0-or-later
 */
class ValidatorTest extends \MediaWikiIntegrationTestCase {

	/**
	 * @dataProvider provideValidateRef
	 */
	public function testValidateRef(
		bool $isKnownName,
		?string $inReferencesGroup,
		?string $text,
		array $arguments,
		?string $expected
	) {
		$validator = new Validator( $inReferencesGroup );

		$status = $validator->validateRef( $text, $arguments );
		$status->merge( $validator->validateListDefinedRefUsage( $arguments['name'], $isKnownName ) );

		if ( $expected ) {
			$this->assertStatusMessage( $expected, $status );
		} else {
			$this->assertStatusGood( $status );
		}
	}

	public static function provideValidateRef() {
		return [
			// Shared <ref> validations regardless of context
			'Numeric name' => [
				'isKnownName' => true,
				'inReferencesGroup' => null,
				'text' => null,
				[
					'group' => '',
					'name' => '1',
					'follow' => null,
					'dir' => null,
					'details' => null,
				],
				'expected' => 'cite_error_ref_numeric_key',
			],
			'Numeric follow' => [
				'isKnownName' => true,
				'inReferencesGroup' => null,
				'text' => 't',
				[
					'group' => '',
					'name' => null,
					'follow' => '1',
					'dir' => null,
					'details' => null,
				],
				'expected' => 'cite_error_ref_numeric_key',
			],
			'Follow with name' => [
				'isKnownName' => true,
				'inReferencesGroup' => null,
				'text' => 't',
				[
					'group' => '',
					'name' => 'n',
					'follow' => 'f',
					'dir' => null,
					'details' => null,
				],
				'expected' => 'cite_error_ref_follow_conflicts',
			],
			'Follow and invalid name 0' => [
				'isKnownName' => true,
				'inReferencesGroup' => null,
				'text' => 't',
				[
					'group' => '',
					'name' => '0',
					'follow' => 'f',
					'dir' => null,
					'details' => null,
				],
				'expected' => 'cite_error_ref_numeric_key',
			],
			'Follow with details not allowed, even if 0' => [
				'isKnownName' => true,
				'inReferencesGroup' => null,
				'text' => 't',
				[
					'group' => '',
					'name' => null,
					'follow' => 'f',
					'dir' => null,
					'details' => '0',
				],
				'expected' => 'cite_error_ref_follow_conflicts',
			],
			'Follow with empty details' => [
				'isKnownName' => true,
				'inReferencesGroup' => null,
				'text' => 't',
				[
					'group' => '',
					'name' => null,
					'follow' => 'f',
					'dir' => null,
					'details' => '',
				],
				'expected' => 'cite_error_ref_follow_conflicts',
			],

			// Validating <ref> outside of <references>
			'text-only <ref>' => [
				'isKnownName' => true,
				'inReferencesGroup' => null,
				'text' => 't',
				[
					'group' => '',
					'name' => null,
					'follow' => null,
					'dir' => null,
					'details' => null,
				],
				'expected' => null,
			],
			'Whitespace or empty text' => [
				'isKnownName' => true,
				'inReferencesGroup' => null,
				'text' => '',
				[
					'group' => '',
					'name' => null,
					'follow' => null,
					'dir' => null,
					'details' => null,
				],
				'expected' => 'cite_error_ref_no_input',
			],
			'totally empty <ref>' => [
				'isKnownName' => true,
				'inReferencesGroup' => null,
				'text' => null,
				[
					'group' => '',
					'name' => null,
					'follow' => null,
					'dir' => null,
					'details' => null,
				],
				'expected' => 'cite_error_ref_no_key',
			],
			'empty-name <ref>' => [
				'isKnownName' => true,
				'inReferencesGroup' => null,
				'text' => 't',
				[
					'group' => '',
					'name' => '',
					'follow' => null,
					'dir' => null,
					'details' => null,
				],
				'expected' => null,
			],
			'contains <ref>-like text' => [
				'isKnownName' => true,
				'inReferencesGroup' => null,
				'text' => 'Foo <ref name="bar">',
				[
					'group' => '',
					'name' => 'n',
					'follow' => null,
					'dir' => null,
					'details' => null,
				],
				'expected' => 'cite_error_included_ref',
			],

			// Validating a <ref> in <references>
			'most trivial <ref> in <references>' => [
				'isKnownName' => true,
				'inReferencesGroup' => 'g',
				'text' => 'not empty',
				[
					'group' => 'g',
					'name' => 'n',
					'follow' => null,
					'dir' => null,
					'details' => null,
				],
				'expected' => null,
			],
			'Different group than <references>' => [
				'isKnownName' => true,
				'inReferencesGroup' => 'g1',
				'text' => 't',
				[
					'group' => 'g2',
					'name' => 'n',
					'follow' => null,
					'dir' => null,
					'details' => null,
				],
				'expected' => 'cite_error_references_group_mismatch',
			],
			'Unnamed in <references>' => [
				'isKnownName' => true,
				'inReferencesGroup' => 'g',
				'text' => 't',
				[
					'group' => 'g',
					'name' => null,
					'follow' => null,
					'dir' => null,
					'details' => null,
				],
				'expected' => 'cite_error_references_no_key',
			],
			'Empty name in <references>' => [
				'isKnownName' => true,
				'inReferencesGroup' => 'g',
				'text' => 't',
				[
					'group' => 'g',
					'name' => '',
					'follow' => null,
					'dir' => null,
					'details' => null,
				],
				'expected' => 'cite_error_references_no_key',
			],
			'Empty text in <references>' => [
				'isKnownName' => true,
				'inReferencesGroup' => 'g',
				'text' => '',
				[
					'group' => 'g',
					'name' => 'n',
					'follow' => null,
					'dir' => null,
					'details' => null,
				],
				'expected' => 'cite_error_empty_references_define',
			],
			'details does not make any sense in <references>' => [
				'isKnownName' => true,
				'inReferencesGroup' => 'g',
				'text' => 't',
				[
					'group' => 'g',
					'name' => 'n',
					'follow' => null,
					'dir' => null,
					'details' => '0',
				],
				'expected' => 'cite_error_details_unsupported_context',
			],
			'empty details in <references>' => [
				'isKnownName' => true,
				'inReferencesGroup' => 'g',
				'text' => 't',
				[
					'group' => 'g',
					'name' => 'n',
					'follow' => null,
					'dir' => null,
					'details' => '',
				],
				'expected' => 'cite_error_details_unsupported_context',
			],

			'Ref never used' => [
				'isKnownName' => false,
				'inReferencesGroup' => 'g',
				'text' => 'not empty',
				[
					'group' => 'g',
					'name' => 'n2',
					'follow' => null,
					'dir' => null,
					'details' => null,
				],
				'expected' => 'cite_error_references_missing_key',
			],
			'Good dir' => [
				'isKnownName' => true,
				'inReferencesGroup' => null,
				'text' => 'not empty',
				[
					'group' => '',
					'name' => 'n',
					'follow' => null,
					'dir' => 'rtl',
					'details' => null,
				],
				'expected' => null,
			],
			'Bad dir' => [
				'isKnownName' => true,
				'inReferencesGroup' => null,
				'text' => 'not empty',
				[
					'group' => '',
					'name' => 'n',
					'follow' => null,
					'dir' => 'foobar',
					'details' => null,
				],
				'expected' => 'cite_error_ref_invalid_dir',
			],
		];
	}

	/**
	 * @dataProvider provideClosestMatch
	 */
	public function testClosestMatch( string $input, ?string $expected ) {
		/** @var Validator $validator */
		$validator = TestingAccessWrapper::newFromClass( Validator::class );
		$allowed = [ 'group', 'name', 'follow', 'dir', 'details' ];
		$this->assertSame( $expected, $validator->closestMatch( $input, $allowed ) );
	}

	public function provideClosestMatch() {
		return [
			[ 'gruop', 'group' ],
			[ 'folow', 'follow' ],
			[ 'detail', 'details' ],
			[ 'nonsense', null ],
			[ 'responsiveresponsiver', null ],
			[ 'nosame', 'name' ],
			[ 'GROU', 'group' ],
		];
	}

}
