<?php

use MediaWiki\User\UserFactory;

/**
 * @covers HTMLUserTextFieldTest
 */
class HTMLUserTextFieldTest extends MediaWikiIntegrationTestCase {

	/**
	 * @dataProvider provideInputs
	 */
	public function testInputs( array $config, string $value, $expected ) {
		$origUserFactory = $this->getServiceContainer()->getUserFactory();
		$userFactory = $this->createMock( UserFactory::class );
		$userFactory->method( 'newFromName' )->willReturnCallback( static function () use ( $origUserFactory ) {
			$user = $origUserFactory->newFromName( ...func_get_args() );
			if ( $user ) {
				$user->mId = 0;
				$user->setItemLoaded( 'id' );
			}
			return $user;
		} );
		$this->setService( 'UserFactory', $userFactory );
		$htmlForm = $this->createMock( HTMLForm::class );
		$htmlForm->method( 'msg' )->willReturnCallback( 'wfMessage' );

		$field = new HTMLUserTextField( $config + [ 'fieldname' => 'foo', 'parent' => $htmlForm ] );
		$result = $field->validate( $value, [ 'foo' => $value ] );
		if ( $result instanceof Message ) {
			$this->assertSame( $expected, $result->getKey() );
		} else {
			$this->assertSame( $expected, $result );
		}
	}

	public static function provideInputs() {
		return [
			'valid username' => [
				[],
				'SomeUser',
				true
			],
			'external username when not allowed' => [
				[],
				'imported>SomeUser',
				'htmlform-user-not-valid'
			],
			'external username when allowed' => [
				[ 'external' => true ],
				'imported>SomeUser',
				true
			],
			'valid IP' => [
				[ 'ipallowed' => true ],
				'1.2.3.4',
				true
			],
			'valid IP, but not allowed' => [
				[ 'ipallowed' => false ],
				'1.2.3.4',
				'htmlform-user-not-valid'
			],
			'invalid IP' => [
				[ 'ipallowed' => true ],
				'1.2.3.456',
				'htmlform-user-not-valid'
			],
			'valid IP range' => [
				[ 'iprange' => true ],
				'1.2.3.4/30',
				true
			],
			'valid IP range, but not allowed' => [
				[ 'iprange' => false ],
				'1.2.3.4/30',
				'htmlform-user-not-valid'
			],
			'invalid IP range (bad syntax)' => [
				[ 'iprange' => true ],
				'1.2.3.4/x',
				'htmlform-user-not-valid'
			],
			'invalid IP range (exceeds limits)' => [
				[
					'iprange' => true,
					'iprangelimits' => [
						'IPv4' => 11,
						'IPv6' => 11,
					],
				],
				'1.2.3.4/10',
				'ip_range_exceeded'
			],
			'valid username, but does not exist' => [
				[ 'exists' => true ],
				'SomeUser',
				'htmlform-user-not-exists'
			],
		];
	}

}
