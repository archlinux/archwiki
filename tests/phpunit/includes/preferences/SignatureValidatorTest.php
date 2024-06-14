<?php

use MediaWiki\MainConfigNames;
use MediaWiki\Preferences\SignatureValidator;
use Wikimedia\TestingAccessWrapper;

/**
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 * http://www.gnu.org/copyleft/gpl.html
 *
 * @file
 */

/**
 * @group Preferences
 * @group Database
 */
class SignatureValidatorTest extends MediaWikiIntegrationTestCase {

	private $validator;

	protected function setUp(): void {
		parent::setUp();
		$this->overrideConfigValue( MainConfigNames::ParsoidSettings, [
			'linting' => true
		] );
		// For testing SignatureAllowedLintErrors in ::testValidateSignature
		$this->overrideConfigValue( MainConfigNames::SignatureAllowedLintErrors, [
			// No allowed lint errors in default set up
		] );
		// For testing hidden category support in ::testValidateSignature
		$this->overrideConfigValue( 'LinterCategories', [
			// No hidden categories in default set up
		] );
		$extReg = $this->createMock( ExtensionRegistry::class );
		$extReg->method( 'isLoaded' )->willReturnCallback( static function ( string $which ) {
			return $which == 'Linter';
		} );
		$this->setService( 'ExtensionRegistry', $extReg );
		$this->validator = $this->getSignatureValidator();
	}

	/**
	 * Get a basic SignatureValidator for testing with.
	 * @return SignatureValidator
	 */
	protected function getSignatureValidator() {
		$services = $this->getServiceContainer();
		$lang = $services->getLanguageFactory()->getLanguage( 'en' );
		$user = $services->getUserFactory()->newFromName( 'SignatureValidatorTest' );
		$validator = $services->getSignatureValidatorFactory()->newSignatureValidator(
			$user,
			null,
			ParserOptions::newFromUserAndLang( $user, $lang )
		);

		return TestingAccessWrapper::newFromObject( $validator );
	}

	/**
	 * @covers \MediaWiki\Preferences\SignatureValidator::applyPreSaveTransform()
	 * @dataProvider provideApplyPreSaveTransform
	 */
	public function testApplyPreSaveTransform( $signature, $expected ) {
		$pstSig = $this->validator->applyPreSaveTransform( $signature );
		$this->assertSame( $expected, $pstSig );
	}

	public static function provideApplyPreSaveTransform() {
		return [
			'Pipe trick' =>
				[ '[[test|]]', '[[test|test]]' ],
			'One level substitution' =>
				[ '{{subst:uc:whatever}}', 'WHATEVER' ],
			'Hidden nested substitution' =>
				[ '{{subst:uc:{}}{{subst:uc:{subst:uc:}}}{{subst:uc:}}}', false ],
			'Hidden nested signature' =>
				[ '{{subst:uc:~~}}{{subst:uc:~~}}', false ],
		];
	}

	/**
	 * @covers \MediaWiki\Preferences\SignatureValidator::checkUserLinks()
	 * @dataProvider provideCheckUserLinks
	 */
	public function testCheckUserLinks( $signature, $expected ) {
		$isValid = $this->validator->checkUserLinks( $signature );
		$this->assertSame( $expected, $isValid );
	}

	public static function provideCheckUserLinks() {
		return [
			'Perfect' =>
				[ '[[User:SignatureValidatorTest|Signature]] ([[User talk:SignatureValidatorTest|talk]])', true ],
			'User link' =>
				[ '[[User:SignatureValidatorTest|Signature]]', true ],
			'User talk link' =>
				[ '[[User talk:SignatureValidatorTest]]', true ],
			'Contributions link' =>
				[ '[[Special:Contributions/SignatureValidatorTest]]', true ],
			'Silly formatting permitted' =>
				[ '[[_uSeR :_signatureValidatorTest_]]', true ],
			'Contributions of wrong user' =>
				[ '[[Special:Contributions/SignatureValidatorTestNot]]', false ],
			'Link to subpage only' =>
				[ '[[User:SignatureValidatorTest/blah|Signature]]', false ],
		];
	}

	/**
	 * @covers \MediaWiki\Preferences\SignatureValidator::checkLintErrors()
	 * @dataProvider provideCheckLintErrors
	 */
	public function testCheckLintErrors( $signature, $expected ) {
		$errors = $this->validator->checkLintErrors( $signature );
		$this->assertSame( $expected, $errors );
	}

	public static function provideCheckLintErrors() {
			yield 'Perfect' => [ '<strong>Foo</strong>', [] ];
			yield 'Unclosed tag' => [
				'<strong>Foo',
				[
					[
						'type' => 'missing-end-tag',
						'dsr' => [ 0, 11, 8, 0 ],
						'templateInfo' => null,
						'params' => [
							'name' => 'strong',
							'inTable' => false,
						]
					]
				]
			];
	}

	/**
	 * @covers \MediaWiki\Preferences\SignatureValidator::validateSignature()
	 * @dataProvider provideValidateSignature
	 */
	public function testValidateSignature( string $signature, $expected ) {
		$result = $this->validator->validateSignature( $signature );
		if ( is_string( $expected ) ) {
			// All special cases should report errors here.
			$expected = true;
		}
		$this->assertSame( $expected, $result );
	}

	/**
	 * @covers \MediaWiki\Preferences\SignatureValidator::validateSignature()
	 * @dataProvider provideValidateSignature
	 */
	public function testValidateSignatureAllowed( string $signature, $expected ) {
		$this->overrideConfigValue( MainConfigNames::SignatureAllowedLintErrors, [
			'obsolete-tag'
		] );
		$this->validator = $this->getSignatureValidator();
		$result = $this->validator->validateSignature( $signature );
		if ( $expected === 'allowed' ) {
			$expected = false;
		} elseif ( is_string( $expected ) ) {
			$expected = true;
		}
		$this->assertSame( $expected, $result );
	}

	/**
	 * @covers \MediaWiki\Preferences\SignatureValidator::validateSignature()
	 * @dataProvider provideValidateSignature
	 */
	public function testValidateSignatureHidden( string $signature, $expected ) {
		// For testing hidden category support in ::testValidateSignature
		$this->overrideConfigValue( 'LinterCategories', [
			'fostered' => [ 'priority' => 'medium' ],
			// A hidden category, for testing.
			'wikilink-in-extlink' => [ 'priority' => 'none' ],
		] );
		$this->validator = $this->getSignatureValidator();
		$result = $this->validator->validateSignature( $signature );
		if ( $expected === 'hidden' ) {
			$expected = false;
		} elseif ( is_string( $expected ) ) {
			$expected = true;
		}
		$this->assertSame( $expected, $result );
	}

	public function provideValidateSignature() {
		yield 'Perfect' => [
			'[[User:SignatureValidatorTest|Signature]] ([[User talk:SignatureValidatorTest|talk]])',
			// no complaints from lint
			false
		];
		yield 'Missing end tag' => [
			'<span>[[User:SignatureValidatorTest|Signature]] ([[User talk:SignatureValidatorTest|talk]])',
			// missing-end-tag is never allowed
			true
		];
		yield 'Obsolete tag' => [
			'<font color="red">RED</font> [[User:SignatureValidatorTest|Signature]] ([[User talk:SignatureValidatorTest|talk]])',
			// This is allowed by SignatureAllowedLintErrors
			'allowed'
		];
		// Testing hidden category support; 'wikilink-in-extlink' has been
		// made hidden.
		yield 'Wikilink in Extlint (hidden)' => [
			'[http://example.com [[Foo]]!] [[User:SignatureValidatorTest|Signature]] ([[User talk:SignatureValidatorTest|talk]])',
			// This is allowed because the category is hidden
			'hidden'
		];
	}

	/**
	 * @covers \MediaWiki\Preferences\SignatureValidator::checkLineBreaks()
	 * @dataProvider provideCheckLineBreaks
	 */
	public function testCheckLineBreaks( $signature, $expected ) {
		$isValid = $this->validator->checkLineBreaks( $signature );
		$this->assertSame( $expected, $isValid );
	}

	public static function provideCheckLineBreaks() {
		return [
			'Perfect' =>
				[ '[[User:SignatureValidatorTest|Signature]] ([[User talk:SignatureValidatorTest|talk]])', true ],
			'Line break' =>
				[ "[[User:SignatureValidatorTest|Signature]] ([[User talk:SignatureValidatorTest|talk\n]])", false ],
		];
	}

}
