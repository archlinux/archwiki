<?php

namespace MediaWiki\Extension\Nuke\Test\Unit;

use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\Nuke\Form\HTMLForm\NukeDateTimeField;
use MediaWiki\HTMLForm\HTMLForm;
use MediaWikiIntegrationTestCase;

/**
 * @covers \MediaWiki\Extension\Nuke\Form\HTMLForm\NukeDateTimeField
 */
class NukeDateTimeFieldTest extends MediaWikiIntegrationTestCase {

	public function testValidation() {
		$time = time();

		$form = HTMLForm::factory( 'ooui', [
			'required' => [
				'class' => NukeDateTimeField::class,
				'required' => true
			],
			'minOnly' => [
				'class' => NukeDateTimeField::class,
				'min' => date( 'Y-m-d', $time )
			],
			'maxOnly' => [
				'class' => NukeDateTimeField::class,
				'max' => date( 'Y-m-d', $time )
			],
			'maxAge' => [
				'class' => NukeDateTimeField::class,
				// If maxAge == 0, it will be ignored.
				'maxAge' => 1
			]
		], RequestContext::getMain() );

		$this->setUserLang( 'qqx' );
		$this->assertStringContainsString(
			'htmlform-required',
			$form->getField( 'required' )
				->validate( '', [] )
		);
		$this->assertStringContainsString(
			'htmlform-date-toolow',
			$form->getField( 'minOnly' )
				->validate( date( 'Y-m-d', $time - 86400 ), [] )
		);
		$this->assertStringContainsString(
			'htmlform-date-toohigh',
			$form->getField( 'maxOnly' )
				->validate( date( 'Y-m-d', $time + 86400 ), [] )
		);
		$this->assertStringContainsString(
			'nuke-date-limited',
			$form->getField( 'maxAge' )
				->validate( date( 'Y-m-d', $time - 86400 ), [] )
		);
	}

}
