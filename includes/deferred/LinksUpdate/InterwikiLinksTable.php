<?php

namespace MediaWiki\Deferred\LinksUpdate;

use ParserOutput;

/**
 * iwlinks
 *
 * Link ID format: string[]
 *    - 0: Interwiki prefix
 *    - 1: Foreign title
 *
 * @since 1.38
 */
class InterwikiLinksTable extends LinksTable {
	/** @var array */
	private $newLinks = [];

	/** @var array|null */
	private $existingLinks;

	public function setParserOutput( ParserOutput $parserOutput ) {
		$this->newLinks = $parserOutput->getInterwikiLinks();
	}

	protected function getTableName() {
		return 'iwlinks';
	}

	protected function getFromField() {
		return 'iwl_from';
	}

	protected function getExistingFields() {
		return [ 'iwl_prefix', 'iwl_title' ];
	}

	protected function getNewLinkIDs() {
		foreach ( $this->newLinks as $prefix => $links ) {
			foreach ( $links as $title => $unused ) {
				yield [ (string)$prefix, (string)$title ];
			}
		}
	}

	/**
	 * Get the existing links as a 2-d array, with the prefix in the first key,
	 * the title in the second key, and the value arbitrary.
	 *
	 * @return array|null
	 */
	private function getExistingLinks() {
		if ( $this->existingLinks === null ) {
			$this->existingLinks = [];
			foreach ( $this->fetchExistingRows() as $row ) {
				$this->existingLinks[$row->iwl_prefix][$row->iwl_title] = true;
			}
		}
		return $this->existingLinks;
	}

	protected function getExistingLinkIDs() {
		foreach ( $this->getExistingLinks() as $prefix => $links ) {
			foreach ( $links as $title => $unused ) {
				yield [ (string)$prefix, (string)$title ];
			}
		}
	}

	protected function isExisting( $linkId ) {
		$links = $this->getExistingLinks();
		[ $prefix, $title ] = $linkId;
		return isset( $links[$prefix][$title] );
	}

	protected function isInNewSet( $linkId ) {
		[ $prefix, $title ] = $linkId;
		return isset( $this->newLinks[$prefix][$title] );
	}

	protected function insertLink( $linkId ) {
		[ $prefix, $title ] = $linkId;
		$this->insertRow( [
			'iwl_prefix' => $prefix,
			'iwl_title' => $title
		] );
	}

	protected function deleteLink( $linkId ) {
		[ $prefix, $title ] = $linkId;
		$this->deleteRow( [
			'iwl_prefix' => $prefix,
			'iwl_title' => $title
		] );
	}
}
