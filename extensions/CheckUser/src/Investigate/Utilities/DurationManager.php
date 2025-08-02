<?php

namespace MediaWiki\CheckUser\Investigate\Utilities;

use DateInterval;
use DateTime;
use Exception;
use MediaWiki\Request\WebRequest;
use MediaWiki\Utils\MWTimestamp;

class DurationManager {

	/**
	 * Retrieves a valid duration from the request.
	 *
	 * @param WebRequest $request
	 * @return string
	 */
	public function getFromRequest( WebRequest $request ): string {
		$value = $request->getVal( 'duration', '' );

		if ( !$this->isValid( $value ) ) {
			return '';
		}

		return $value;
	}

	/**
	 * Return the timestamp from the duration.
	 *
	 * @param WebRequest $request
	 * @return string
	 */
	public function getTimestampFromRequest( WebRequest $request ): string {
		$duration = $this->getFromRequest( $request );
		if ( $duration === '' ) {
			return $duration;
		}

		try {
			$interval = new DateInterval( $duration );
			$now = DateTime::createFromFormat( 'U', (string)MWTimestamp::time() );
			return MWTimestamp::convert( TS_MW, $now->sub( $interval ) );
		} catch ( Exception $e ) {
			return '';
		}
	}

	/**
	 * Determine if duration is valid.
	 *
	 * @param string $value
	 * @return bool
	 */
	public function isValid( string $value ): bool {
		// No value implies "all"
		if ( $value === '' ) {
			return true;
		}

		try {
			// @phan-suppress-next-line PhanNoopNew
			new DateInterval( $value );
			return true;
		} catch ( Exception $e ) {
			return false;
		}
	}
}
