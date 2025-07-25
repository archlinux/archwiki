<?php

namespace MediaWiki\Extension\Nuke\Test\Unit;

use MediaWiki\Config\Config;
use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\Nuke\NukeConfigNames;
use MediaWiki\Extension\Nuke\NukeContext;
use MediaWiki\MainConfigNames;
use MediaWiki\Request\FauxRequest;
use PHPUnit\Framework\TestCase;

/**
 * @covers \MediaWiki\Extension\Nuke\NukeContext
 */
class NukeContextTest extends TestCase {
	private Config $config;

	protected function setUp(): void {
		$this->config = $this->createMock( Config::class );
		$this->config->method( 'get' )->willReturnMap( [
			[ NukeConfigNames::MaxAge, 86400 * 5 ],
			[ MainConfigNames::RCMaxAge, 86400 * 3 ],
		] );
	}

	public function testGetNukeMaxAgeInDays() {
		$requestContext = new RequestContext();
		$requestContext->setRequest( new FauxRequest( [], true ) );
		$requestContext->setConfig( $this->config );

		$context = new NukeContext(
			[
				'requestContext' => $requestContext,
				'useTemporaryAccounts' => false,
				'nukeAccessStatus' => NukeContext::NUKE_ACCESS_GRANTED,
				'target' => "",
				'listedTarget' => "",
				'pattern' => "",
				'limit' => 3,
				'namespaces' => [ 0 ],
				'includeTalkPages' => true,
				'includeRedirects' => true,
				'pages' => [],
				'associatedPages' => [],
				'originalPages' => [],
				'minPageSize' => 0,
				'maxPageSize' => 500,
			]
		);

		$result = $context->getNukeMaxAgeInDays();
		$this->assertEquals( 5, $result );
	}

	public function testGetRecentChangesMaxAgeInDays() {
		$requestContext = new RequestContext();
		$requestContext->setRequest( new FauxRequest( [], true ) );
		$requestContext->setConfig( $this->config );

		$context = new NukeContext(
			[
				'requestContext' => $requestContext,
				'useTemporaryAccounts' => false,
				'nukeAccessStatus' => NukeContext::NUKE_ACCESS_GRANTED,
				'target' => "",
				'listedTarget' => "",
				'pattern' => "",
				'limit' => 3,
				'namespaces' => [ 0 ],
				'includeTalkPages' => true,
				'includeRedirects' => true,
				'pages' => [],
				'associatedPages' => [],
				'originalPages' => [],
				'minPageSize' => 0,
				'maxPageSize' => 500,
			]
		);

		$result = $context->getRecentChangesMaxAgeInDays();
		$this->assertEquals( 3, $result );
	}
}
