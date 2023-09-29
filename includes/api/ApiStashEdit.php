<?php
/**
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 * http://www.gnu.org/copyleft/gpl.html
 *
 * @file
 */

use MediaWiki\Content\IContentHandlerFactory;
use MediaWiki\Page\WikiPageFactory;
use MediaWiki\Revision\RevisionLookup;
use MediaWiki\Revision\SlotRecord;
use MediaWiki\Storage\PageEditStash;
use Wikimedia\ParamValidator\ParamValidator;

/**
 * Prepare an edit in shared cache so that it can be reused on edit
 *
 * This endpoint can be called via AJAX as the user focuses on the edit
 * summary box. By the time of submission, the parse may have already
 * finished, and can be immediately used on page save. Certain parser
 * functions like {{REVISIONID}} or {{CURRENTTIME}} may cause the cache
 * to not be used on edit. Template and files used are checked for changes
 * since the output was generated. The cache TTL is also kept low.
 *
 * @ingroup API
 * @since 1.25
 */
class ApiStashEdit extends ApiBase {

	/** @var IContentHandlerFactory */
	private $contentHandlerFactory;

	/** @var PageEditStash */
	private $pageEditStash;

	/** @var RevisionLookup */
	private $revisionLookup;

	/** @var IBufferingStatsdDataFactory */
	private $statsdDataFactory;

	/** @var WikiPageFactory */
	private $wikiPageFactory;

	/**
	 * @param ApiMain $main
	 * @param string $action
	 * @param IContentHandlerFactory $contentHandlerFactory
	 * @param PageEditStash $pageEditStash
	 * @param RevisionLookup $revisionLookup
	 * @param IBufferingStatsdDataFactory $statsdDataFactory
	 * @param WikiPageFactory $wikiPageFactory
	 */
	public function __construct(
		ApiMain $main,
		$action,
		IContentHandlerFactory $contentHandlerFactory,
		PageEditStash $pageEditStash,
		RevisionLookup $revisionLookup,
		IBufferingStatsdDataFactory $statsdDataFactory,
		WikiPageFactory $wikiPageFactory
	) {
		parent::__construct( $main, $action );

		$this->contentHandlerFactory = $contentHandlerFactory;
		$this->pageEditStash = $pageEditStash;
		$this->revisionLookup = $revisionLookup;
		$this->statsdDataFactory = $statsdDataFactory;
		$this->wikiPageFactory = $wikiPageFactory;
	}

	public function execute() {
		$user = $this->getUser();
		$params = $this->extractRequestParams();

		if ( $user->isBot() ) {
			$this->dieWithError( 'apierror-botsnotsupported' );
		}

		$page = $this->getTitleOrPageId( $params );
		$title = $page->getTitle();
		$this->getErrorFormatter()->setContextTitle( $title );

		if ( !$this->contentHandlerFactory
			->getContentHandler( $params['contentmodel'] )
			->isSupportedFormat( $params['contentformat'] )
		) {
			$this->dieWithError(
				[ 'apierror-badformat-generic', $params['contentformat'], $params['contentmodel'] ],
				'badmodelformat'
			);
		}

		$this->requireOnlyOneParameter( $params, 'stashedtexthash', 'text' );

		if ( $params['stashedtexthash'] !== null ) {
			// Load from cache since the client indicates the text is the same as last stash
			$textHash = $params['stashedtexthash'];
			if ( !preg_match( '/^[0-9a-f]{40}$/', $textHash ) ) {
				$this->dieWithError( 'apierror-stashedit-missingtext', 'missingtext' );
			}
			$text = $this->pageEditStash->fetchInputText( $textHash );
			if ( !is_string( $text ) ) {
				$this->dieWithError( 'apierror-stashedit-missingtext', 'missingtext' );
			}
		} else {
			// 'text' was passed.  Trim and fix newlines so the key SHA1's
			// match (see WebRequest::getText())
			$text = rtrim( str_replace( "\r\n", "\n", $params['text'] ) );
			$textHash = sha1( $text );
		}

		$textContent = $this->contentHandlerFactory
			->getContentHandler( $params['contentmodel'] )
			->unserializeContent( $text, $params['contentformat'] );

		$page = $this->wikiPageFactory->newFromTitle( $title );
		if ( $page->exists() ) {
			// Page exists: get the merged content with the proposed change
			$baseRev = $this->revisionLookup->getRevisionByPageId(
				$page->getId(),
				$params['baserevid']
			);
			if ( !$baseRev ) {
				$this->dieWithError( [ 'apierror-nosuchrevid', $params['baserevid'] ] );
			}
			$currentRev = $page->getRevisionRecord();
			if ( !$currentRev ) {
				$this->dieWithError( [ 'apierror-missingrev-pageid', $page->getId() ], 'missingrev' );
			}
			// Merge in the new version of the section to get the proposed version
			$editContent = $page->replaceSectionAtRev(
				$params['section'],
				$textContent,
				$params['sectiontitle'],
				$baseRev->getId()
			);
			if ( !$editContent ) {
				$this->dieWithError( 'apierror-sectionreplacefailed', 'replacefailed' );
			}
			if ( $currentRev->getId() == $baseRev->getId() ) {
				// Base revision was still the latest; nothing to merge
				$content = $editContent;
			} else {
				// Merge the edit into the current version
				$baseContent = $baseRev->getContent( SlotRecord::MAIN );
				$currentContent = $currentRev->getContent( SlotRecord::MAIN );
				if ( !$baseContent || !$currentContent ) {
					$this->dieWithError( [ 'apierror-missingcontent-pageid', $page->getId() ], 'missingrev' );
				}

				$baseModel = $baseContent->getModel();
				$currentModel = $currentContent->getModel();

				// T255700: Put this in try-block because if the models of these three Contents
				// happen to not be identical, the ContentHandler may throw exception here.
				try {
					$content = $this->contentHandlerFactory
						->getContentHandler( $baseModel )
						->merge3( $baseContent, $editContent, $currentContent );
				} catch ( Exception $e ) {
					$this->dieWithException( $e, [
						'wrap' => ApiMessage::create(
							[ 'apierror-contentmodel-mismatch', $currentModel, $baseModel ]
						)
					] );
				}

			}
		} else {
			// New pages: use the user-provided content model
			$content = $textContent;
		}

		if ( !$content ) { // merge3() failed
			$this->getResult()->addValue( null,
				$this->getModuleName(), [ 'status' => 'editconflict' ] );
			return;
		}

		if ( $user->pingLimiter( 'stashedit' ) ) {
			$status = 'ratelimited';
		} else {
			$updater = $page->newPageUpdater( $user );
			$status = $this->pageEditStash->parseAndCache( $updater, $content, $user, $params['summary'] );
			$this->pageEditStash->stashInputText( $text, $textHash );
		}

		$this->statsdDataFactory->increment( "editstash.cache_stores.$status" );

		$ret = [ 'status' => $status ];
		// If we were rate-limited, we still return the pre-existing valid hash if one was passed
		if ( $status !== 'ratelimited' || $params['stashedtexthash'] !== null ) {
			$ret['texthash'] = $textHash;
		}

		$this->getResult()->addValue( null, $this->getModuleName(), $ret );
	}

	public function getAllowedParams() {
		return [
			'title' => [
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => true
			],
			'section' => [
				ParamValidator::PARAM_TYPE => 'string',
			],
			'sectiontitle' => [
				ParamValidator::PARAM_TYPE => 'string'
			],
			'text' => [
				ParamValidator::PARAM_TYPE => 'text',
				ParamValidator::PARAM_DEFAULT => null
			],
			'stashedtexthash' => [
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_DEFAULT => null
			],
			'summary' => [
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_DEFAULT => ''
			],
			'contentmodel' => [
				ParamValidator::PARAM_TYPE => $this->contentHandlerFactory->getContentModels(),
				ParamValidator::PARAM_REQUIRED => true
			],
			'contentformat' => [
				ParamValidator::PARAM_TYPE => $this->contentHandlerFactory->getAllContentFormats(),
				ParamValidator::PARAM_REQUIRED => true
			],
			'baserevid' => [
				ParamValidator::PARAM_TYPE => 'integer',
				ParamValidator::PARAM_REQUIRED => true
			]
		];
	}

	public function needsToken() {
		return 'csrf';
	}

	public function mustBePosted() {
		return true;
	}

	public function isWriteMode() {
		return true;
	}

	public function isInternal() {
		return true;
	}
}
