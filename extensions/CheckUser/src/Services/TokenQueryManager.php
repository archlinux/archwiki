<?php

namespace MediaWiki\CheckUser\Services;

use MediaWiki\Request\WebRequest;

class TokenQueryManager {
	public TokenManager $tokenManager;

	public function __construct( TokenManager $tokenManager ) {
		$this->tokenManager = $tokenManager;
	}

	/**
	 * Conceal the offset in pager pagination params
	 * which may reveal sensitive data.
	 *
	 * @param WebRequest $request
	 * @param array $queries
	 * @return array
	 */
	public function getPagingQueries( WebRequest $request, array $queries ): array {
		$tokenData = $this->getDataFromRequest( $request );
		foreach ( $queries as &$query ) {
			if ( $query === false ) {
				continue;
			}

			if ( isset( $query['offset'] ) ) {
				// Move the offset into the token since it may contain sensitive information
				$query['token'] = $this->updateToken( $request, [ 'offset' => $query['offset'] ] );
				unset( $query['offset'] );
			} elseif ( isset( $tokenData['offset'] ) ) {
				// Remove the offset.
				$query['token'] = $this->updateToken( $request, [ 'offset' => null ] );
			}
		}

		return $queries;
	}

	/**
	 * Preforms an array merge on the updates with what is in the current token.
	 * Setting a value to null will remove it.
	 *
	 * @param WebRequest $request
	 * @param array $update
	 * @return string
	 */
	public function updateToken( WebRequest $request, array $update ): string {
		$tokenData = $this->getDataFromRequest( $request );
		$data = array_filter( array_merge( $tokenData, $update ), static function ( $value ) {
			return $value !== null;
		} );

		return $this->tokenManager->encode( $request->getSession(), $data );
	}

	/**
	 * Get token data
	 *
	 * @param WebRequest $request
	 * @return array
	 */
	public function getDataFromRequest( WebRequest $request ): array {
		$token = $request->getVal( 'token', '' );

		if ( $token === '' ) {
			return [];
		}

		try {
			return $this->tokenManager->decode( $request->getSession(), $token );
		} catch ( \Exception $e ) {
			return [];
		}
	}
}
