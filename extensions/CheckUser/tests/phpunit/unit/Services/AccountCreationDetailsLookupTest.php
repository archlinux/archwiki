<?php
namespace MediaWiki\CheckUser\Tests\Unit\Services;

use MediaWiki\CheckUser\Services\AccountCreationDetailsLookup;
use MediaWiki\Config\HashConfig;
use MediaWiki\Config\ServiceOptions;
use MediaWiki\MainConfigNames;
use MediaWikiUnitTestCase;
use Psr\Log\NullLogger;
use Wikimedia\Rdbms\FakeResultWrapper;
use Wikimedia\Rdbms\IDatabase;

/**
 * @covers \MediaWiki\CheckUser\Services\AccountCreationDetailsLookup
 */
class AccountCreationDetailsLookupTest extends MediaWikiUnitTestCase {

	public function testGetIPAndUserAgent() {
		$mockDB = $this->createMock( IDatabase::class );
		$mockLogger = $this->createMock( NullLogger::class );

		// unclear if these methods of getMockBuilder will be around too long, see
		// https://github.com/sebastianbergmann/phpunit/issues/5252
		$lookup = $this->getMockBuilder( AccountCreationDetailsLookup::class )
			->setConstructorArgs( [ new NullLogger(),
				new ServiceOptions(
					AccountCreationDetailsLookup::CONSTRUCTOR_OPTIONS,
					new HashConfig( [ MainConfigNames::NewUserLog => true ] )
				) ] )
			->onlyMethods( [ 'getIPAndUserAgentFromDB' ] )
			->getMock();

		$lookup->method( 'getIPAndUserAgentFromDB' )
			->willReturn( new FakeResultWrapper( [
				[
					'cule_ip_hex' => 'C0A80105',
					'cule_agent' => 'Junk User Agent',
				]
			] ) );

		$result = $lookup->getAccountCreationIPAndUserAgent( 'JunkUserName', $mockDB );
		$expected = [ 'ip' => '192.168.1.5', 'agent' => 'Junk User Agent' ];
		$this->assertEquals(
			$expected,
			$result,
			'Bad ip or user agent returned.'
		);
	}
}
