<?php

namespace MediaWiki\CheckUser\ClientHints;

use MediaWiki\CheckUser\Jobs\StoreClientHintsDataJob;
use MediaWiki\CheckUser\Services\UserAgentClientHintsManager;
use MediaWiki\JobQueue\JobQueueGroup;
use MediaWiki\Request\WebRequest;
use Profiler;
use Psr\Log\LoggerInterface;
use TypeError;

/**
 * Helper logic for classes that take client hints in request headers
 * and store them in client hints database tables, but otherwise do not
 * have a shared hierarchy.
 */
trait UserAgentClientHintsManagerHelperTrait {

	private UserAgentClientHintsManager $userAgentClientHintsManager;
	private JobQueueGroup $jobQueueGroup;
	private LoggerInterface $logger;

	/**
	 * Stores Client Hints data from the HTTP headers in the given $request and associate it with the
	 * given $eventId.
	 *
	 * @param int $eventId The identifier of the event (e.g. logging table ID, cu_private_log ID)
	 * @param string $eventType The type of event to associate with the data (e.g. "privatelog", "log")
	 * @param WebRequest $request Request from which to retrieve client hint header data.
	 * @return void
	 */
	private function storeClientHintsDataFromHeaders( int $eventId, string $eventType, WebRequest $request ): void {
		try {
			$clientHintsData = ClientHintsData::newFromRequestHeaders( $request );

			// Only perform writes on the main request if this is allowed by the TransactionProfiler.
			// If no writes are allowed in the request, then insert the Client Hints data via a job.
			$transactionProfiler = Profiler::instance()->getTransactionProfiler();
			if (
				$transactionProfiler->getExpectation( 'writes' ) == 0 ||
				$transactionProfiler->getExpectation( 'masterConns' ) == 0
			) {
				if ( count( $clientHintsData->toDatabaseRows() ) ) {
					$this->jobQueueGroup->push( StoreClientHintsDataJob::newSpec(
						$clientHintsData, $eventId, $eventType
					) );
				}
			} else {
				$this->userAgentClientHintsManager->insertClientHintValues(
					$clientHintsData, $eventId, $eventType
				);
			}
		} catch ( TypeError $e ) {
			$clientHintsHeaders = [];
			foreach ( array_keys( ClientHintsData::HEADER_TO_CLIENT_HINTS_DATA_PROPERTY_NAME ) as $header ) {
				$headerValue = $request->getHeader( $header );
				if ( $headerValue !== false ) {
					$clientHintsHeaders[$header] = $headerValue;
				}
			}
			$this->logger->warning(
				'Invalid data present in Client Hints headers when storing Client Hints data for {eventType} ID ' .
				'{eventId}. Not storing this data. Client Hints headers: {clientHintsHeaders}',
				[ $eventType, $eventId, $clientHintsHeaders ]
			);
		}
	}
}
