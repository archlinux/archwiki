<?php

namespace MediaWiki\Extension\TextExtracts;

use Generator;
use MediaWiki\Api\ApiMain;
use MediaWiki\Api\ApiResult;
use MediaWiki\Api\Hook\ApiOpenSearchSuggestHook;
use MediaWiki\Config\Config;
use MediaWiki\Config\ConfigFactory;
use MediaWiki\Request\FauxRequest;
use MediaWiki\Rest\Hook\SearchResultProvideDescriptionHook;

/**
 * @license GPL-2.0-or-later
 */
class Hooks implements
	ApiOpenSearchSuggestHook,
	SearchResultProvideDescriptionHook
{

	private Config $config;

	public function __construct(
		ConfigFactory $configFactory
	) {
		$this->config = $configFactory->makeConfig( 'textextracts' );
	}

	/**
	 * Trim an extract to a sensible length.
	 *
	 * Adapted from Extension:OpenSearchXml, which adapted it from
	 * Extension:ActiveAbstract.
	 *
	 * @param string $text
	 * @param int $length Target length; actual result will continue to the end of a sentence.
	 * @return string
	 */
	private static function trimExtract( $text, $length ) {
		static $regex = null;
		if ( $regex === null ) {
			$endchars = [
				// regular ASCII
				'([^\d])\.\s', '\!\s', '\?\s',
				// full-width ideographic full-stop
				'。',
				// double-width roman forms
				'．', '！', '？',
				// half-width ideographic full stop
				'｡',
			];
			$endgroup = implode( '|', $endchars );
			$end = "(?:$endgroup)";
			$sentence = ".{{$length},}?$end+";
			$regex = "/^($sentence)/u";
		}
		$matches = [];
		if ( preg_match( $regex, $text, $matches ) ) {
			return trim( $matches[1] );
		} else {
			// Just return the first line
			return trim( explode( "\n", $text )[0] );
		}
	}

	/**
	 * Retrieves extracts data for the given page IDs from the TextExtract API.
	 * The page IDs are chunked into the max limit of exlimit of the TextExtract API
	 *
	 * @param array $pageIds An array of page IDs to retrieve extracts for
	 * @return Generator Yields the result data from the API request
	 *   $data = [
	 *    'pageId' => [
	 *      'ns' => int of the namespace
	 *      'title' => string of the title of the page
	 *      'extract' => string of the text extracts of the page
	 *   ]
	 * ]
	 */
	private function getExtractsData( array $pageIds ) {
		foreach ( array_chunk( $pageIds, 20 ) as $chunkedPageIds ) {
			$api = new ApiMain( new FauxRequest(
				[
					'action' => 'query',
					'prop' => 'extracts',
					'explaintext' => true,
					'exintro' => true,
					'exlimit' => count( $chunkedPageIds ),
					'pageids' => implode( '|', $chunkedPageIds ),
				]
			) );
			$api->execute();
			yield $api->getResult()->getResultData( [ 'query', 'pages' ] );
		}
	}

	/**
	 * ApiOpenSearchSuggest hook handler
	 * @param array &$results Array of search results
	 */
	public function onApiOpenSearchSuggest( &$results ) {
		if ( !$this->config->get( 'ExtractsExtendOpenSearchXml' ) || $results === [] ) {
			return;
		}

		$pageIds = array_keys( $results );
		foreach ( $this->getExtractsData( $pageIds ) as $data ) {
			foreach ( $pageIds as $id ) {
				$contentKey = $data[$id]['extract'][ApiResult::META_CONTENT] ?? '*';
				if ( isset( $data[$id]['extract'][$contentKey] ) ) {
					$results[$id]['extract'] = $data[$id]['extract'][$contentKey];
					$results[$id]['extract trimmed'] = false;
				}
			}
		}
	}

	/**
	 * Used to update Search Results with descriptions for Search Engine.
	 * @param array $pageIdentities	Array (string=>SearchResultPageIdentity) where key is pageId
	 * @param array &$descriptions Output array (string=>string|null)
	 * where key is pageId and value is either a description for given page or null
	 */
	public function onSearchResultProvideDescription(
		array $pageIdentities,
		&$descriptions
	): void {
		if ( !$this->config->get( 'ExtractsExtendRestSearch' ) || $pageIdentities === [] ) {
			return;
		}

		$pageIds = array_map( static function ( $identity ) {
			return $identity->getId();
		}, $pageIdentities );
		foreach ( $this->getExtractsData( $pageIds ) as $data ) {
			foreach ( $pageIds as $id ) {
				$contentKey = $data[$id]['extract'][ApiResult::META_CONTENT] ?? '*';
				if ( isset( $data[$id]['extract'][$contentKey] ) ) {
					$descriptions[$id] = self::trimExtract( $data[$id]['extract'][$contentKey], 150 );
				}
			}
		}
	}
}
