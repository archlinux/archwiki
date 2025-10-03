<?php

namespace MediaWiki\Extension\OATHAuth\Tests\Integration\Auth;

use MediaWiki\Extension\OATHAuth\Auth\TwoFactorModuleSelectAuthenticationRequest;
use MediaWiki\Message\Message;
use MediaWiki\Tests\Auth\AuthenticationRequestTestCase;

/**
 * @covers \MediaWiki\Extension\OATHAuth\Auth\TwoFactorModuleSelectAuthenticationRequest
 */
class TwoFactorModuleSelectAuthenticationRequestTest extends AuthenticationRequestTestCase {

	protected function getInstance( array $args = [] ) {
		return new TwoFactorModuleSelectAuthenticationRequest(
			$args['currentModule'],
			$args['allowedModules']
		);
	}

	public static function provideGetFieldInfo() {
		return [
			[
				[
					'currentModule' => 'foo',
					'allowedModules' => [
						'foo' => new Message( 'foo' ),
						'bar' => new Message( 'bar' ),
					],
				],
			],
		];
	}

	public static function provideLoadFromSubmission() {
		return [
			[
				[
					'currentModule' => 'foo',
					'allowedModules' => [
						'foo' => new Message( 'foo' ),
						'bar' => new Message( 'bar' ),
					],
				],
				[],
				[
					'currentModule' => 'foo',
					'allowedModules' => [
						'foo' => new Message( 'foo' ),
						'bar' => new Message( 'bar' ),
					],
				],
			],
			[
				[
					'currentModule' => 'foo',
					'allowedModules' => [
						'foo' => new Message( 'foo' ),
						'bar' => new Message( 'bar' ),
					],
				],
				[
					'newModule' => 'bar',
				],
				[
					'currentModule' => 'foo',
					'allowedModules' => [
						'foo' => new Message( 'foo' ),
						'bar' => new Message( 'bar' ),
					],
					'newModule' => 'bar',
				],
			],
		];
	}

	public function testGetMetadata() {
		$req = new TwoFactorModuleSelectAuthenticationRequest( 'foo', [
			'foo' => $this->getMockMessage( 'msg-foo' ),
			'bar' => $this->getMockMessage( 'msg-bar' ),
			'baz' => $this->getMockMessage( 'msg-baz' ),
		] );
		$expectedMetadata = [
			'currentModule' => 'foo',
			'allowedModules' => [ 'foo', 'bar', 'baz' ],
			'moduleDescriptions' => [
				'foo' => 'msg-foo',
				'bar' => 'msg-bar',
				'baz' => 'msg-baz',
			],
		];
		$this->assertSame( $expectedMetadata, $req->getMetadata() );
	}
}
