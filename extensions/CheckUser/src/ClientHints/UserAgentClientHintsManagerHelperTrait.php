<?php

namespace MediaWiki\Extension\CheckUser\ClientHints;

use MediaWiki\Extension\CheckUser\Jobs\StoreClientHintsDataJob;
use MediaWiki\Extension\CheckUser\Services\UserAgentClientHintsManager;
use MediaWiki\JobQueue\JobQueueGroup;
use MediaWiki\Profiler\Profiler;
use MediaWiki\Request\WebRequest;
use Psr\Log\LoggerInterface;
use TypeError;

/**
 * Helper logic for classes that take client hints in request headers
 * and store them in client hints database tables, but otherwise do not
 * have a shared hierarchy.
 */
trait UserAgentClientHintsManagerHelperTrait {

	private readonly UserAgentClientHintsManager $userAgentClientHintsManager;
	private readonly JobQueueGroup $jobQueueGroup;
	private readonly LoggerInterface $logger;

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

			$this->commonStoreClientHintsData( $clientHintsData, $eventId, $eventType );
		} catch ( TypeError $e ) {
			$this->commonHandleInvalidClientHintsData(
				$request,
				array_keys( ClientHintsData::HEADER_TO_CLIENT_HINTS_DATA_PROPERTY_NAME ),
				$eventId,
				$eventType,
				$e
			);
		}
	}

	/**
	 * Stores Client Hints data which is only stored via HTTP headers from the HTTP headers in the given $request
	 * and associate it with the given event ID. If calling {@link self::storeClientHintsDataFromHeaders} then you do
	 * not need to call this method too.
	 *
	 * @param int $eventId The identifier of the event (e.g. logging table ID, cu_private_log ID)
	 * @param string $eventType The type of event to associate with the data (e.g. "privatelog", "log")
	 * @param WebRequest $request
	 * @return void
	 */
	private function storeHeaderOnlyClientHintsData(
		int $eventId,
		string $eventType,
		WebRequest $request
	): void {
		try {
			$clientHintsData = ClientHintsData::newFromRequestHeaders(
				$request,
				UserAgentClientHintsManager::HEADER_ONLY_CLIENT_HINTS_DATA
			);

			$this->commonStoreClientHintsData( $clientHintsData, $eventId, $eventType );
		} catch ( TypeError $e ) {
			$this->commonHandleInvalidClientHintsData(
				$request,
				array_map(
					static fn ( $propertyName ) => array_search(
						$propertyName,
						ClientHintsData::HEADER_TO_CLIENT_HINTS_DATA_PROPERTY_NAME
					),
					UserAgentClientHintsManager::HEADER_ONLY_CLIENT_HINTS_DATA
				),
				$eventId,
				$eventType,
				$e
			);
		}
	}

	/**
	 * @internal Not for use outside of {@link UserAgentClientHintsManagerHelperTrait}
	 */
	private function commonStoreClientHintsData(
		ClientHintsData $clientHintsData,
		int $eventId,
		string $eventType
	): void {
		// Only perform writes on the main request if this is allowed by the TransactionProfiler.
		// If no writes are allowed in the request, then insert the Client Hints data via a job.
		$transactionProfiler = Profiler::instance()->getTransactionProfiler();
		if (
			$transactionProfiler->getExpectation( 'writes' ) == 0 ||
			$transactionProfiler->getExpectation( 'masterConns' ) == 0
		) {
			if ( count( $clientHintsData->toDatabaseRows() ) ) {
				$this->jobQueueGroup->push( StoreClientHintsDataJob::newSpec(
					$clientHintsData,
					$eventId,
					$eventType
				) );
			}
		} else {
			$this->userAgentClientHintsManager->insertClientHintValues(
				$clientHintsData,
				$eventId,
				$eventType
			);
		}
	}

	/**
	 * @internal Not for use outside of {@link UserAgentClientHintsManagerHelperTrait}
	 */
	private function commonHandleInvalidClientHintsData(
		WebRequest $request,
		array $headersCollected,
		int $eventId,
		string $eventType,
		TypeError $exception
	): void {
		$clientHintsHeaders = [];
		foreach ( $headersCollected as $header ) {
			$headerValue = $request->getHeader( $header );
			if ( $headerValue !== false ) {
				$clientHintsHeaders[$header] = $headerValue;
			}
		}
		$this->logger->warning(
			'Invalid data present in Client Hints headers when storing Client Hints data for {eventType} ID ' .
			'{eventId}. Not storing this data. Client Hints headers: {clientHintsHeaders}',
			[
				'eventType' => $eventType,
				'eventId' => $eventId,
				'clientHintsHeaders' => $clientHintsHeaders,
				'exception' => $exception,
			]
		);
	}
}
