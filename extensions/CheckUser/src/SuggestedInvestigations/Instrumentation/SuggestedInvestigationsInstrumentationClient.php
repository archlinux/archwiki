<?php

namespace MediaWiki\CheckUser\SuggestedInvestigations\Instrumentation;

use MediaWiki\Context\IContextSource;
use MediaWiki\Extension\EventLogging\MetricsPlatform\MetricsClientFactory;

/**
 * Wrapper class for emitting server-side interaction events to the Suggested Investigations
 * Metrics Platform instrument.
 */
class SuggestedInvestigationsInstrumentationClient {

	/**
	 * @param MetricsClientFactory|null $metricsClientFactory null if EventLogging is not installed
	 */
	public function __construct( private $metricsClientFactory ) {
	}

	/**
	 * Emit an interaction event to the Suggested Investigations Metrics Platform instrument.
	 *
	 * Does nothing if EventLogging is not installed
	 *
	 * @internal For use by Suggested Investigations code only
	 * @param IContextSource $context
	 * @param string $action The action name to use for the interaction
	 * @param array $interactionData Interaction data for the event
	 */
	public function submitInteraction(
		IContextSource $context,
		string $action,
		array $interactionData
	): void {
		if ( $this->metricsClientFactory === null ) {
			return;
		}

		$client = $this->metricsClientFactory->newMetricsClient( $context );

		$client->submitInteraction(
			'mediawiki.product_metrics.suggested_investigations_interaction',
			'/analytics/product_metrics/web/base/1.4.3',
			$action,
			$interactionData
		);
	}
}
