<?php

namespace MediaWiki\Tests\Api;

use ApiCreateTempUserTrait;
use MediaWiki\HookContainer\HookRunner;
use MediaWiki\Request\WebRequest;
use MediaWiki\User\User;
use MediaWikiIntegrationTestCase;
use Wikimedia\TestingAccessWrapper;

/**
 * @covers \ApiCreateTempUserTrait
 */
class ApiCreateTempUserTraitTest extends MediaWikiIntegrationTestCase {

	/**
	 * @dataProvider provideGetTempUserRedirectUrl
	 */
	public function testGetTempUserRedirectUrl( $params, $expected ) {
		$this->setTemporaryHook(
			'TempUserCreatedRedirect',
			static function (
				$session,
				$user,
				$returnTo,
				$returnToQuery,
				$returnToAnchor,
				&$redirectUrl
			) {
				$redirectUrl = $returnTo . $returnToQuery . $returnToAnchor;
				return false;
			}
		);

		$mock = $this->getMockForTrait( ApiCreateTempUserTrait::class );
		$mock->method( 'getHookRunner' )
			->willReturn( new HookRunner( $this->getServiceContainer()->getHookContainer() ) );
		$mock->method( 'getRequest' )
			->willReturn( $this->createMock( WebRequest::class ) );

		$url = TestingAccessWrapper::newFromObject( $mock )
			->getTempUserRedirectUrl( $params, $this->createMock( User::class ) );

		$this->assertSame( $expected, $url );
	}

	public static function provideGetTempUserRedirectUrl() {
		return [
			'Default params' => [
				[
					'returnto' => '',
					'returntoquery' => '',
					'returntoanchor' => '',
				],
				'',
			],
			'Missing returnto' => [
				[
					'returnto' => null,
					'returntoquery' => '',
					'returntoanchor' => '',
				],
				'',
			],
			'Params are parsed correctly' => [
				[
					'returnto' => 'Base',
					'returntoquery' => 'Query',
					'returntoanchor' => 'Anchor',
				],
				'BaseQuery#Anchor',
			],
			'Params are parsed correctly with anchor #' => [
				[
					'returnto' => 'Base',
					'returntoquery' => 'Query',
					'returntoanchor' => '#Anchor',
				],
				'BaseQuery#Anchor',
			],
		];
	}
}
