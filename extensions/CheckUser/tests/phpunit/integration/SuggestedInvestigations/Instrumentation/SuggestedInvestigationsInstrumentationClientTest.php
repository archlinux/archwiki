<?php

namespace MediaWiki\CheckUser\Tests\Integration\SuggestedInvestigations\Instrumentation;

use MediaWiki\CheckUser\SuggestedInvestigations\Instrumentation\SuggestedInvestigationsInstrumentationClient;
use MediaWiki\Context\RequestContext;
use MediaWiki\Extension\EventLogging\MetricsPlatform\MetricsClientFactory;
use MediaWikiIntegrationTestCase;
use Wikimedia\MetricsPlatform\MetricsClient;

/**
 * @covers \MediaWiki\CheckUser\SuggestedInvestigations\Instrumentation\SuggestedInvestigationsInstrumentationClient
 */
class SuggestedInvestigationsInstrumentationClientTest extends MediaWikiIntegrationTestCase {

	public function testSubmitInteractionWhenMetricsClientFactoryIsNull() {
		// Will throw an exception if the MetricsClientFactory is null and the code tries to use it,
		// so no need to assert otherwise
		$this->expectNotToPerformAssertions();
		$objectUnderTest = new SuggestedInvestigationsInstrumentationClient( null );
		$objectUnderTest->submitInteraction( RequestContext::getMain(), 'test', [] );
	}

	public function testSubmitInteraction() {
		$this->markTestSkippedIfExtensionNotLoaded( 'EventLogging' );

		// Mock the MetricsClientFactory to return a mock MetricsClient so that we can check that the
		// event is being created with the correct stream name etc.
		$mockEventLoggingMetricsClient = $this->createMock( MetricsClient::class );
		$mockEventLoggingMetricsClient->expects( $this->once() )
			->method( 'submitInteraction' )
			->with(
				'mediawiki.product_metrics.suggested_investigations_interaction',
				'/analytics/product_metrics/web/base/1.4.3',
				'test',
				[ 'action_context' => 'test' ]
			);

		$mockEventLoggingMetricsClientFactory = $this->createMock( MetricsClientFactory::class );
		$mockEventLoggingMetricsClientFactory->expects( $this->once() )
			->method( 'newMetricsClient' )
			->with( RequestContext::getMain() )
			->willReturn( $mockEventLoggingMetricsClient );
		$this->setService( 'EventLogging.MetricsClientFactory', $mockEventLoggingMetricsClientFactory );

		/** @var SuggestedInvestigationsInstrumentationClient $objectUnderTest */
		$objectUnderTest = $this->getServiceContainer()->get(
			'CheckUserSuggestedInvestigationsInstrumentationClient'
		);
		$objectUnderTest->submitInteraction( RequestContext::getMain(), 'test', [ 'action_context' => 'test' ] );
	}
}
