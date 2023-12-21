<?php

namespace MediaWiki\Extension\DiscussionTools;

use ApiBase;
use ApiMain;
use ApiUsageException;
use MediaWiki\Extension\DiscussionTools\ThreadItem\DatabaseThreadItem;
use MediaWiki\Title\Title;
use TitleFormatter;
use Wikimedia\ParamValidator\ParamValidator;

class ApiDiscussionToolsFindComment extends ApiBase {

	private ThreadItemStore $threadItemStore;
	private TitleFormatter $titleFormatter;

	public function __construct(
		ApiMain $main,
		string $name,
		ThreadItemStore $threadItemStore,
		TitleFormatter $titleFormatter
	) {
		parent::__construct( $main, $name );
		$this->threadItemStore = $threadItemStore;
		$this->titleFormatter = $titleFormatter;
	}

	/**
	 * @inheritDoc
	 * @throws ApiUsageException
	 */
	public function execute() {
		$params = $this->extractRequestParams();

		$idOrName = $params['idorname'];

		$values = [];

		$byId = $this->threadItemStore->findNewestRevisionsById( $idOrName );
		foreach ( $byId as $item ) {
			$values[] = $this->getValue( $item, 'id' );
		}

		$byName = $this->threadItemStore->findNewestRevisionsByName( $idOrName );
		foreach ( $byName as $item ) {
			$values[] = $this->getValue( $item, 'name' );
		}

		$redirects = 0;
		foreach ( $values as $value ) {
			if ( $value['couldredirect'] ) {
				$redirects++;
				if ( $redirects > 1 ) {
					break;
				}
			}
		}
		foreach ( $values as $value ) {
			if ( $redirects === 1 && $value['couldredirect'] ) {
				$value['shouldredirect'] = true;
			}
			unset( $value['couldredirect'] );
			$this->getResult()->addValue( $this->getModuleName(), null, $value );
		}
	}

	/**
	 * Get a value to add to the results
	 *
	 * @param DatabaseThreadItem $item Thread item
	 * @param string $matchedBy How the thread item was matched (id or name)
	 * @return array
	 */
	private function getValue( DatabaseThreadItem $item, string $matchedBy ): array {
		$title = Title::castFromPageReference( $item->getPage() );

		return [
			'id' => $item->getId(),
			'name' => $item->getName(),
			'title' => $this->titleFormatter->getPrefixedText( $item->getPage() ),
			'oldid' => !$item->getRevision()->isCurrent() ? $item->getRevision()->getId() : null,
			'matchedby' => $matchedBy,
			// Could this be an automatic redirect? Will be converted to 'shouldredirect'
			// if there is only one of these in the result set.
			// Matches logic in Special:GoToComment
			'couldredirect' => $item->getRevision()->isCurrent() && !is_string( $item->getTranscludedFrom() )
		];
	}

	/**
	 * @inheritDoc
	 */
	public function getAllowedParams() {
		return [
			'idorname' => [
				ParamValidator::PARAM_REQUIRED => true,
			],
		];
	}

	/**
	 * @inheritDoc
	 */
	public function needsToken() {
		return false;
	}

	/**
	 * @inheritDoc
	 */
	public function isWriteMode() {
		return false;
	}
}
