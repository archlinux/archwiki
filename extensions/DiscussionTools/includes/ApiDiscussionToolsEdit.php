<?php

namespace MediaWiki\Extension\DiscussionTools;

use ApiBase;
use ApiMain;
use ApiUsageException;
use Config;
use ConfigFactory;
use DerivativeContext;
use DerivativeRequest;
use MediaWiki\Extension\DiscussionTools\Hooks\HookUtils;
use MediaWiki\Extension\DiscussionTools\ThreadItem\ContentCommentItem;
use MediaWiki\Extension\VisualEditor\ApiParsoidTrait;
use MediaWiki\Extension\VisualEditor\VisualEditorParsoidClientFactory;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\Revision\RevisionLookup;
use SkinFactory;
use Title;
use Wikimedia\ParamValidator\ParamValidator;
use Wikimedia\ParamValidator\TypeDef\StringDef;
use Wikimedia\Parsoid\Utils\DOMCompat;
use Wikimedia\Parsoid\Utils\DOMUtils;

class ApiDiscussionToolsEdit extends ApiBase {
	use ApiDiscussionToolsTrait;
	use ApiParsoidTrait;

	private CommentParser $commentParser;
	private VisualEditorParsoidClientFactory $parsoidClientFactory;
	private SubscriptionStore $subscriptionStore;
	private SkinFactory $skinFactory;
	private Config $config;
	private RevisionLookup $revisionLookup;

	public function __construct(
		ApiMain $main,
		string $name,
		VisualEditorParsoidClientFactory $parsoidClientFactory,
		CommentParser $commentParser,
		SubscriptionStore $subscriptionStore,
		SkinFactory $skinFactory,
		ConfigFactory $configFactory,
		RevisionLookup $revisionLookup
	) {
		parent::__construct( $main, $name );
		$this->parsoidClientFactory = $parsoidClientFactory;
		$this->commentParser = $commentParser;
		$this->subscriptionStore = $subscriptionStore;
		$this->skinFactory = $skinFactory;
		$this->config = $configFactory->makeConfig( 'discussiontools' );
		$this->revisionLookup = $revisionLookup;
		$this->setLogger( LoggerFactory::getInstance( 'DiscussionTools' ) );
	}

	/**
	 * @inheritDoc
	 * @throws ApiUsageException
	 */
	public function execute() {
		$params = $this->extractRequestParams();
		$title = Title::newFromText( $params['page'] );
		$result = null;

		$autoSubscribe =
			$this->config->get( 'DiscussionToolsAutoTopicSubEditor' ) === 'discussiontoolsapi' &&
			HookUtils::shouldAddAutoSubscription( $this->getUser(), $title );
		$subscribableHeadingName = null;
		$subscribableSectionTitle = '';

		if ( !$title ) {
			$this->dieWithError( [ 'apierror-invalidtitle', wfEscapeWikiText( $params['page'] ) ] );
		}

		$this->getErrorFormatter()->setContextTitle( $title );

		$session = null;
		$usedFormTokensKey = 'DiscussionTools:usedFormTokens';
		$formToken = $params['formtoken'];
		if ( $formToken ) {
			$session = $this->getContext()->getRequest()->getSession();
			$usedFormTokens = $session->get( $usedFormTokensKey ) ?? [];
			if ( in_array( $formToken, $usedFormTokens ) ) {
				$this->dieWithError( [ 'apierror-discussiontools-formtoken-used' ] );
			}
		}

		$this->requireOnlyOneParameter( $params, 'wikitext', 'html' );
		if ( $params['paction'] === 'addtopic' ) {
			$this->requireAtLeastOneParameter( $params, 'sectiontitle' );
		}

		// To determine if we need to add a signature,
		// preview the comment without adding one and check if the result is signed properly.
		$previewResult = $this->previewMessage(
			$params['paction'] === 'addtopic' ? 'topic' : 'reply',
			$title,
			[
				'wikitext' => $params['wikitext'],
				'html' => $params['html'],
				'sectiontitle' => $params['sectiontitle'],
			],
			$params
		);
		$previewResultHtml = $previewResult->getResultData( [ 'parse', 'text' ] );
		$previewContainer = DOMCompat::getBody( DOMUtils::parseHTML( $previewResultHtml ) );
		$previewThreadItemSet = $this->commentParser->parse( $previewContainer, $title->getTitleValue() );
		if ( CommentUtils::isSingleCommentSignedBy(
			$previewThreadItemSet, $this->getUser()->getName(), $previewContainer
		) ) {
			$signature = null;
		} else {
			$signature = $this->msg( 'discussiontools-signature-prefix' )->inContentLanguage()->text() . '~~~~';
		}

		switch ( $params['paction'] ) {
			case 'addtopic':
				$wikitext = $params['wikitext'];
				$html = $params['html'];

				if ( $wikitext !== null ) {
					if ( $signature !== null ) {
						$wikitext = CommentModifier::appendSignatureWikitext( $wikitext, $signature );
					}
				} else {
					$doc = DOMUtils::parseHTML( '' );
					$container = DOMUtils::parseHTMLToFragment( $doc, $html );
					if ( $signature !== null ) {
						CommentModifier::appendSignature( $container, $signature );
					}
					$html = DOMUtils::getFragmentInnerHTML( $container );
					$wikitext = $this->transformHTML( $title, $html )[ 'body' ];
				}

				$mobileFormatParams = [];
				// Boolean parameters must be omitted completely to be treated as false.
				// Param is added by hook in MobileFrontend, so it may be unset.
				if ( isset( $params['mobileformat'] ) && $params['mobileformat'] ) {
					$mobileFormatParams['mobileformat'] = '1';
				}
				// As section=new this is append only so we don't need to
				// worry about edit-conflict params such as oldid/baserevid/etag.
				// Edit summary is also automatically generated when section=new
				$context = new DerivativeContext( $this->getContext() );
				$context->setRequest(
					new DerivativeRequest(
						$context->getRequest(),
						[
							'action' => 'visualeditoredit',
							'paction' => 'save',
							'page' => $params['page'],
							'token' => $params['token'],
							'wikitext' => $wikitext,
							// A default is provided automatically by the Edit API
							// for new sections when the summary is empty.
							'summary' => $params['summary'],
							'section' => 'new',
							'sectiontitle' => $params['sectiontitle'],
							'starttimestamp' => wfTimestampNow(),
							'useskin' => $params['useskin'],
							'watchlist' => $params['watchlist'],
							'captchaid' => $params['captchaid'],
							'captchaword' => $params['captchaword']
						] + $mobileFormatParams,
						/* was posted? */ true
					)
				);
				$api = new ApiMain(
					$context,
					/* enable write? */ true
				);

				$api->execute();

				$data = $api->getResult()->getResultData();
				$result = $data['visualeditoredit'];

				if ( $autoSubscribe && isset( $result['content'] ) ) {
					// Determining the added topic's name directly is hard (we'd have to ensure we have the
					// same timestamp, and replicate some CommentParser stuff). Just pull it out of the response.
					$doc = DOMUtils::parseHTML( $result['content'] );
					$container = DOMCompat::getBody( $doc );
					$threadItemSet = $this->commentParser->parse( $container, $title->getTitleValue() );
					$threads = $threadItemSet->getThreads();
					if ( count( $threads ) ) {
						$lastHeading = end( $threads );
						$subscribableHeadingName = $lastHeading->getName();
						$subscribableSectionTitle = $lastHeading->getLinkableTitle();
					}
				}

				break;

			case 'addcomment':
				$this->requireAtLeastOneParameter( $params, 'commentid', 'commentname' );

				$commentId = $params['commentid'] ?? null;
				$commentName = $params['commentname'] ?? null;

				if ( !$title->exists() ) {
					// The page does not exist, so the comment we're trying to reply to can't exist either.
					if ( $commentId ) {
						$this->dieWithError( [ 'apierror-discussiontools-commentid-notfound', $commentId ] );
					} else {
						$this->dieWithError( [ 'apierror-discussiontools-commentname-notfound', $commentName ] );
					}
				}

				// Fetch the latest revision
				$requestedRevision = $this->revisionLookup->getRevisionByTitle( $title );
				if ( !$requestedRevision ) {
					$this->dieWithError(
						[ 'apierror-missingrev-title', wfEscapeWikiText( $title->getPrefixedText() ) ],
						'nosuchrevid'
					);
				}

				$response = $this->requestRestbasePageHtml( $requestedRevision );

				$headers = $response['headers'];
				$doc = DOMUtils::parseHTML( $response['body'] );

				// Don't trust RESTBase to always give us the revision we requested,
				// instead get the revision ID from the document and use that.
				// Ported from ve.init.mw.ArticleTarget.prototype.parseMetadata
				$docRevId = null;
				$aboutDoc = $doc->documentElement->getAttribute( 'about' );

				if ( $aboutDoc ) {
					preg_match( '/revision\\/([0-9]+)$/', $aboutDoc, $docRevIdMatches );
					if ( $docRevIdMatches ) {
						$docRevId = (int)$docRevIdMatches[ 1 ];
					}
				}

				if ( !$docRevId ) {
					$this->dieWithError( 'apierror-visualeditor-docserver', 'docserver' );
				}

				if ( $docRevId !== $requestedRevision->getId() ) {
					// TODO: If this never triggers, consider removing the check.
					$this->getLogger()->warning(
						"Requested revision {$requestedRevision->getId()} " .
						"but received {$docRevId}."
					);
				}

				$container = DOMCompat::getBody( $doc );

				// Unwrap sections, so that transclusions overlapping section boundaries don't cause all
				// comments in the sections to be treated as transcluded from another page.
				CommentUtils::unwrapParsoidSections( $container );

				$threadItemSet = $this->commentParser->parse( $container, $title->getTitleValue() );

				if ( $commentId ) {
					$comment = $threadItemSet->findCommentById( $commentId );

					if ( !$comment || !( $comment instanceof ContentCommentItem ) ) {
						$this->dieWithError( [ 'apierror-discussiontools-commentid-notfound', $commentId ] );
					}

				} else {
					$comments = $threadItemSet->findCommentsByName( $commentName );
					$comment = $comments[ 0 ] ?? null;

					if ( count( $comments ) > 1 ) {
						$this->dieWithError( [ 'apierror-discussiontools-commentname-ambiguous', $commentName ] );
					} elseif ( !$comment || !( $comment instanceof ContentCommentItem ) ) {
						$this->dieWithError( [ 'apierror-discussiontools-commentname-notfound', $commentName ] );
					}
				}

				if ( $comment->getTranscludedFrom() ) {
					// Replying to transcluded comments should not be possible. We check this client-side, but
					// the comment might have become transcluded in the meantime (T268069), so check again. We
					// didn't have this check before T313100, since usually the reply would just disappear in
					// Parsoid, but now it would be placed after the transclusion, which would be wrong.
					$this->dieWithError( 'discussiontools-error-comment-not-saved', 'comment-became-transcluded' );
				}

				if ( $params['wikitext'] !== null ) {
					CommentModifier::addWikitextReply( $comment, $params['wikitext'], $signature );
				} else {
					CommentModifier::addHtmlReply( $comment, $params['html'], $signature );
				}

				if ( isset( $params['summary'] ) ) {
					$summary = $params['summary'];
				} else {
					$sectionTitle = $comment->getHeading()->getLinkableTitle();
					$summary = ( $sectionTitle ? '/* ' . $sectionTitle . ' */ ' : '' ) .
						$this->msg( 'discussiontools-defaultsummary-reply' )->inContentLanguage()->text();
				}

				if ( $autoSubscribe ) {
					$heading = $comment->getSubscribableHeading();
					if ( $heading ) {
						$subscribableHeadingName = $heading->getName();
						$subscribableSectionTitle = $heading->getLinkableTitle();
					}
				}

				$context = new DerivativeContext( $this->getContext() );
				$context->setRequest(
					new DerivativeRequest(
						$context->getRequest(),
						[
							'action' => 'visualeditoredit',
							'paction' => 'save',
							'page' => $params['page'],
							'token' => $params['token'],
							'oldid' => $docRevId,
							'html' => DOMCompat::getOuterHTML( $doc->documentElement ),
							'summary' => $summary,
							'baserevid' => $docRevId,
							'starttimestamp' => wfTimestampNow(),
							'etag' => $headers['etag'] ?? null,
							'useskin' => $params['useskin'],
							'watchlist' => $params['watchlist'],
							'captchaid' => $params['captchaid'],
							'captchaword' => $params['captchaword']
						],
						/* was posted? */ true
					)
				);
				$api = new ApiMain(
					$context,
					/* enable write? */ true
				);

				$api->execute();

				// TODO: Tags are only added by 'dttags' existing on the original request
				// context (see Hook::onRecentChangeSave). What tags (if any) should be
				// added in this API?

				$data = $api->getResult()->getResultData();
				$result = $data['visualeditoredit'];
				break;
		}

		if ( !isset( $result['newrevid'] ) && isset( $result['result'] ) && $result['result'] === 'success' ) {
			// No new revision, so no changes were made to the page (null edit).
			// Comment was not actually saved, so for this API, that's an error.
			// This should not be possible after T313100.
			$this->dieWithError( 'discussiontools-error-comment-not-saved', 'comment-comment-not-saved' );
		}

		if ( $autoSubscribe && $subscribableHeadingName ) {
			$subsTitle = $title->createFragmentTarget( $subscribableSectionTitle );
			$this->subscriptionStore
				->addAutoSubscriptionForUser( $this->getUser(), $subsTitle, $subscribableHeadingName );
		}

		// Check the post was successful (could have been blocked by ConfirmEdit) before
		// marking the form token as used.
		if ( $formToken && isset( $result['result'] ) && $result['result'] === 'success' ) {
			$usedFormTokens[] = $formToken;
			// Set an arbitrary limit of the number of form tokens to
			// store to prevent session storage from becoming full.
			// It is unlikely that form tokens other than the few most
			// recently used will be needed.
			while ( count( $usedFormTokens ) > 50 ) {
				// Discard the oldest tokens first
				array_shift( $usedFormTokens );
			}
			$session->set( $usedFormTokensKey, $usedFormTokens );
		}

		$this->getResult()->addValue( null, $this->getModuleName(), $result );
	}

	/**
	 * @inheritDoc
	 */
	public function getAllowedParams() {
		return [
			'paction' => [
				ParamValidator::PARAM_REQUIRED => true,
				ParamValidator::PARAM_TYPE => [
					'addcomment',
					'addtopic',
				],
				ApiBase::PARAM_HELP_MSG => 'apihelp-visualeditoredit-param-paction',
				ApiBase::PARAM_HELP_MSG_PER_VALUE => [],
			],
			'page' => [
				ParamValidator::PARAM_REQUIRED => true,
				ApiBase::PARAM_HELP_MSG => 'apihelp-visualeditoredit-param-page',
			],
			'token' => [
				ParamValidator::PARAM_REQUIRED => true,
			],
			'formtoken' => [
				ParamValidator::PARAM_TYPE => 'string',
				StringDef::PARAM_MAX_CHARS => 16,
			],
			'commentname' => null,
			'commentid' => null,
			'wikitext' => [
				ParamValidator::PARAM_TYPE => 'text',
				ParamValidator::PARAM_DEFAULT => null,
			],
			'html' => [
				ParamValidator::PARAM_TYPE => 'text',
				ParamValidator::PARAM_DEFAULT => null,
			],
			'summary' => [
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_DEFAULT => null,
				ApiBase::PARAM_HELP_MSG => 'apihelp-visualeditoredit-param-summary',
			],
			'sectiontitle' => [
				ParamValidator::PARAM_TYPE => 'string',
			],
			'useskin' => [
				ParamValidator::PARAM_TYPE => array_keys( $this->skinFactory->getInstalledSkins() ),
				ApiBase::PARAM_HELP_MSG => 'apihelp-parse-param-useskin',
			],
			'watchlist' => [
				ApiBase::PARAM_HELP_MSG => 'apihelp-edit-param-watchlist',
			],
			'captchaid' => [
				ApiBase::PARAM_HELP_MSG => 'apihelp-visualeditoredit-param-captchaid',
			],
			'captchaword' => [
				ApiBase::PARAM_HELP_MSG => 'apihelp-visualeditoredit-param-captchaword',
			],
		];
	}

	/**
	 * @inheritDoc
	 */
	public function needsToken() {
		return 'csrf';
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
		return true;
	}
}
