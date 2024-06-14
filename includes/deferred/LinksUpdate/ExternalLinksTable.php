<?php

namespace MediaWiki\Deferred\LinksUpdate;

use MediaWiki\ExternalLinks\LinkFilter;
use MediaWiki\Parser\ParserOutput;

/**
 * externallinks
 *
 * Link ID format: string URL
 *
 * @since 1.38
 */
class ExternalLinksTable extends LinksTable {
	private $newLinks = [];
	private $existingLinks;

	public function setParserOutput( ParserOutput $parserOutput ) {
		foreach ( $parserOutput->getExternalLinks() as $url => $unused ) {
			foreach ( LinkFilter::makeIndexes( $url ) as [ $domainIndex, $path ] ) {
				$this->newLinks[$domainIndex][$path] = true;
			}
		}
	}

	protected function getTableName() {
		return 'externallinks';
	}

	protected function getFromField() {
		return 'el_from';
	}

	protected function getExistingFields() {
		return [ 'el_to_domain_index', 'el_to_path' ];
	}

	/**
	 * Get the existing links as an array
	 *
	 * @return array
	 */
	private function getExistingLinks() {
		if ( $this->existingLinks === null ) {
			$this->existingLinks = [];
			foreach ( $this->fetchExistingRows() as $row ) {
				$this->existingLinks[$row->el_to_domain_index][$row->el_to_path] = true;
			}
		}
		return $this->existingLinks;
	}

	protected function getNewLinkIDs() {
		foreach ( $this->newLinks as $domainIndex => $paths ) {
			foreach ( $paths as $path => $unused ) {
				yield [ (string)$domainIndex, (string)$path ];
			}
		}
	}

	protected function getExistingLinkIDs() {
		foreach ( $this->getExistingLinks() as $domainIndex => $paths ) {
			foreach ( $paths as $path => $unused ) {
				yield [ (string)$domainIndex, (string)$path ];
			}
		}
	}

	protected function isExisting( $linkId ) {
		[ $domainIndex, $path ] = $linkId;
		return isset( $this->getExistingLinks()[$domainIndex][$path] );
	}

	protected function isInNewSet( $linkId ) {
		[ $domainIndex, $path ] = $linkId;
		return isset( $this->newLinks[$domainIndex][$path] );
	}

	protected function insertLink( $linkId ) {
		[ $domainIndex, $path ] = $linkId;
		$params = [
			'el_to_domain_index' => substr( $domainIndex, 0, 255 ),
			'el_to_path' => $path,
		];
		$this->insertRow( $params );
	}

	protected function deleteLink( $linkId ) {
		[ $domainIndex, $path ] = $linkId;
		$this->deleteRow( [
			'el_to_domain_index' => substr( $domainIndex, 0, 255 ),
			'el_to_path' => $path
		] );
		if ( $path === '' ) {
			// el_to_path is nullable, but null is not valid in php arrays,
			// so both values are handled as one key, delete both rows when exists
			$this->deleteRow( [
				'el_to_domain_index' => substr( $domainIndex, 0, 255 ),
				'el_to_path' => null
			] );
		}
	}

	/**
	 * Get an array of URLs of the given type
	 *
	 * @param int $setType One of the link set constants as in LinksTable::getLinkIDs()
	 * @return string[]
	 */
	public function getStringArray( $setType ) {
		$ids = $this->getLinkIDs( $setType );
		$stringArray = [];
		foreach ( $ids as $linkId ) {
			[ $domainIndex, $path ] = $linkId;
			$stringArray[] = LinkFilter::reverseIndexes( $domainIndex ) . $path;
		}
		return $stringArray;
	}
}
