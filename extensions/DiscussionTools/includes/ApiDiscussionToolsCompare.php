<?php

namespace MediaWiki\Extension\DiscussionTools;

use MediaWiki\Api\ApiBase;
use MediaWiki\Api\ApiMain;
use MediaWiki\Api\ApiUsageException;
use MediaWiki\Extension\DiscussionTools\Hooks\HookUtils;
use MediaWiki\Extension\VisualEditor\VisualEditorParsoidClientFactory;
use MediaWiki\Revision\RevisionLookup;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Title\Title;
use Wikimedia\ParamValidator\ParamValidator;
use Wikimedia\Parsoid\Core\ResourceLimitExceededException;

class ApiDiscussionToolsCompare extends ApiBase {

	private CommentParser $commentParser;
	private VisualEditorParsoidClientFactory $parsoidClientFactory;
	private RevisionLookup $revisionLookup;

	public function __construct(
		ApiMain $main,
		string $name,
		VisualEditorParsoidClientFactory $parsoidClientFactory,
		CommentParser $commentParser,
		RevisionLookup $revisionLookup
	) {
		parent::__construct( $main, $name );
		$this->parsoidClientFactory = $parsoidClientFactory;
		$this->commentParser = $commentParser;
		$this->revisionLookup = $revisionLookup;
	}

	/**
	 * @throws ApiUsageException
	 */
	private function getRevision( array $params, string $prefix ): RevisionRecord {
		if ( isset( $params["{$prefix}rev"] ) ) {
			$rev = $this->revisionLookup->getRevisionById( $params["{$prefix}rev"] );
			if ( !$rev ) {
				$this->dieWithError( [ 'apierror-nosuchrevid', $params["{$prefix}rev"] ] );
			}

		} else {
			$title = Title::newFromText( $params["{$prefix}title"] );
			if ( !$title ) {
				$this->dieWithError( [ 'apierror-invalidtitle', wfEscapeWikiText( $params["{$prefix}title"] ) ] );
			}
			$rev = $this->revisionLookup->getRevisionByTitle( $title );
			if ( !$rev ) {
				$this->dieWithError(
					[ 'apierror-missingrev-title', wfEscapeWikiText( $title->getPrefixedText() ) ],
					'nosuchrevid'
				);
			}
		}
		// To keep things simple, don't allow viewing deleted revisions through this API
		// (even if the current user could view them if we checked with userCan()).
		if ( !$rev->audienceCan( RevisionRecord::DELETED_TEXT, RevisionRecord::FOR_PUBLIC ) ) {
			$this->dieWithError( [ 'apierror-missingcontent-revid', $rev->getId() ], 'missingcontent' );
		}
		return $rev;
	}

	/**
	 * @inheritDoc
	 * @throws ApiUsageException
	 */
	public function execute() {
		$params = $this->extractRequestParams();

		$this->requireOnlyOneParameter( $params, 'fromtitle', 'fromrev' );
		$this->requireOnlyOneParameter( $params, 'totitle', 'torev' );

		$toRev = $this->getRevision( $params, 'to' );

		// When polling for new comments this is an important optimisation,
		// as usually there is no new revision.
		if ( $toRev->getId() === $params['fromrev'] ) {
			$this->addResult( $toRev, $toRev );
			return;
		}

		$fromRev = $this->getRevision( $params, 'from' );

		if ( $fromRev->hasSameContent( $toRev ) ) {
			$this->addResult( $fromRev, $toRev );
			return;
		}

		try {
			$fromItemSet = HookUtils::parseRevisionParsoidHtml( $fromRev, __METHOD__ );
			$toItemSet = HookUtils::parseRevisionParsoidHtml( $toRev, __METHOD__ );
		} catch ( ResourceLimitExceededException $e ) {
			$this->dieWithException( $e );
		}

		$removedComments = [];
		foreach ( $fromItemSet->getCommentItems() as $fromComment ) {
			if ( !$toItemSet->findCommentById( $fromComment->getId() ) ) {
				$removedComments[] = $fromComment->jsonSerializeForDiff();
			}
		}

		$addedComments = [];
		foreach ( $toItemSet->getCommentItems() as $toComment ) {
			if ( !$fromItemSet->findCommentById( $toComment->getId() ) ) {
				$addedComments[] = $toComment->jsonSerializeForDiff();
			}
		}

		$this->addResult( $fromRev, $toRev, $removedComments, $addedComments );
	}

	/**
	 * Add the result object from revisions and comment lists
	 *
	 * @param RevisionRecord $fromRev From revision
	 * @param RevisionRecord $toRev To revision
	 * @param array $removedComments Removed comments
	 * @param array $addedComments Added comments
	 */
	protected function addResult(
		RevisionRecord $fromRev, RevisionRecord $toRev, array $removedComments = [], array $addedComments = []
	) {
		$fromTitle = Title::newFromLinkTarget(
			$fromRev->getPageAsLinkTarget()
		);
		$toTitle = Title::newFromLinkTarget(
			$toRev->getPageAsLinkTarget()
		);
		$result = [
			'fromrevid' => $fromRev->getId(),
			'fromtitle' => $fromTitle->getPrefixedText(),
			'torevid' => $toRev->getId(),
			'totitle' => $toTitle->getPrefixedText(),
			'removedcomments' => $removedComments,
			'addedcomments' => $addedComments,
		];
		$this->getResult()->addValue( null, $this->getModuleName(), $result );
	}

	/**
	 * @inheritDoc
	 */
	public function getAllowedParams() {
		return [
			'fromtitle' => [
				ApiBase::PARAM_HELP_MSG => 'apihelp-compare-param-fromtitle',
			],
			'fromrev' => [
				ParamValidator::PARAM_TYPE => 'integer',
				ApiBase::PARAM_HELP_MSG => 'apihelp-compare-param-fromrev',
			],
			'totitle' => [
				ApiBase::PARAM_HELP_MSG => 'apihelp-compare-param-totitle',
			],
			'torev' => [
				ParamValidator::PARAM_TYPE => 'integer',
				ApiBase::PARAM_HELP_MSG => 'apihelp-compare-param-torev',
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
	public function isInternal() {
		return true;
	}

	/**
	 * @inheritDoc
	 */
	public function isWriteMode() {
		return false;
	}
}
