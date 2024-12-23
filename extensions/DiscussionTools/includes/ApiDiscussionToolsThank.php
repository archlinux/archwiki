<?php

namespace MediaWiki\Extension\DiscussionTools;

use MediaWiki\Api\ApiBase;
use MediaWiki\Api\ApiMain;
use MediaWiki\Api\ApiUsageException;
use MediaWiki\Extension\DiscussionTools\Hooks\HookUtils;
use MediaWiki\Extension\DiscussionTools\ThreadItem\ContentCommentItem;
use MediaWiki\Extension\Notifications\Model\Event;
use MediaWiki\Extension\Thanks\Api\ApiThank;
use MediaWiki\Extension\Thanks\Storage\LogStore;
use MediaWiki\Extension\VisualEditor\ApiParsoidTrait;
use MediaWiki\Permissions\PermissionManager;
use MediaWiki\Revision\RevisionLookup;
use MediaWiki\Title\Title;
use MediaWiki\User\UserFactory;
use Wikimedia\ParamValidator\ParamValidator;
use Wikimedia\Parsoid\Core\ResourceLimitExceededException;

/**
 * API module to send DiscussionTools comment thanks notifications
 *
 * @ingroup API
 * @ingroup Extensions
 */

class ApiDiscussionToolsThank extends ApiThank {

	use ApiDiscussionToolsTrait;
	use ApiParsoidTrait;

	private RevisionLookup $revisionLookup;
	private UserFactory $userFactory;

	/**
	 * @inheritDoc
	 */
	public function __construct(
		ApiMain $main,
		$action,
		PermissionManager $permissionManager,
		LogStore $storage,
		RevisionLookup $revisionLookup,
		UserFactory $userFactory
	) {
		parent::__construct( $main, $action, $permissionManager, $storage );
		$this->revisionLookup = $revisionLookup;
		$this->userFactory = $userFactory;
	}

	/**
	 * @inheritDoc
	 * @throws ApiUsageException
	 * @throws ResourceLimitExceededException
	 */
	public function execute() {
		$user = $this->getUser();
		$this->dieOnBadUser( $user );
		$this->dieOnUserBlockedFromThanks( $user );

		$params = $this->extractRequestParams();

		$title = Title::newFromText( $params['page'] );
		$commentId = $params['commentid'];

		if ( !$title ) {
			$this->dieWithError( [ 'apierror-invalidtitle', wfEscapeWikiText( $params['page'] ) ] );
		}

		// TODO: Using the data in the permalinks database would be much
		// faster, we just wouldn't have the comment content.

		// Support oldid?
		$revision = $this->revisionLookup->getRevisionByTitle( $title );
		if ( !$revision ) {
			throw ApiUsageException::newWithMessage(
				$this,
				[ 'apierror-missingrev-title', wfEscapeWikiText( $title->getPrefixedText() ) ],
				'nosuchrevid'
			);
		}
		$threadItemSet = HookUtils::parseRevisionParsoidHtml( $revision, __METHOD__ );

		$comment = $threadItemSet->findCommentById( $commentId );

		if ( !$comment || !( $comment instanceof ContentCommentItem ) ) {
			$this->dieWithError( [ 'apierror-discussiontools-commentid-notfound', $commentId ] );
		}

		if ( $user->getRequest()->getSessionData( "discussiontools-thanked-{$comment->getId()}" ) ) {
			$this->markResultSuccess( $comment->getAuthor() );
			return;
		}

		$uniqueId = "discussiontools-{$comment->getId()}";
		// Do one last check to make sure we haven't sent Thanks before
		if ( $this->haveAlreadyThanked( $user, $uniqueId ) ) {
			// Pretend the thanks were sent
			$this->markResultSuccess( $comment->getAuthor() );
			return;
		}

		$recipient = $this->userFactory->newFromName( $comment->getAuthor() );
		if ( !$recipient || !$recipient->getId() ) {
			$this->dieWithError( 'thanks-error-invalidrecipient', 'invalidrecipient' );
		}

		$this->dieOnBadRecipient( $user, $recipient );

		$heading = $comment->getSubscribableHeading();
		if ( !$heading ) {
			$heading = $comment->getHeading();
		}

		// Create the notification via Echo extension
		Event::create( [
			'type' => 'dt-thank',
			'title' => $title,
			'extra' => [
				'comment-id' => $comment->getId(),
				'comment-name' => $comment->getName(),
				'content' => $comment->getBodyText( true ),
				'section-title' => $heading->getLinkableTitle(),
				'thanked-user-id' => $recipient->getId(),
				'revid' => $revision->getId(),
			],
			'agent' => $user,
		] );

		// And mark the thank in session for a cheaper check to prevent duplicates (T48690).
		$user->getRequest()->setSessionData( "discussiontools-thanked-{$comment->getId()}", true );
		// Set success message.
		$this->markResultSuccess( $recipient->getName() );
		$this->logThanks( $user, $recipient, $uniqueId );
	}

	/**
	 * @inheritDoc
	 */
	public function getAllowedParams() {
		return [
			'page' => [
				ParamValidator::PARAM_REQUIRED => true,
				// Message will exist if DiscussionTools is installed as VE is a dependency
				ApiBase::PARAM_HELP_MSG => 'apihelp-visualeditoredit-param-page',
			],
			'commentid' => [
				ParamValidator::PARAM_REQUIRED => true,
				ParamValidator::PARAM_TYPE => 'string',
			],
			'token' => [
				ParamValidator::PARAM_REQUIRED => true,
				ParamValidator::PARAM_TYPE => 'string',
			],
		];
	}
}
