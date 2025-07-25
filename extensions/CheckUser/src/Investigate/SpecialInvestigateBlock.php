<?php

namespace MediaWiki\CheckUser\Investigate;

use Exception;
use MediaWiki\Api\ApiMain;
use MediaWiki\Block\BlockPermissionCheckerFactory;
use MediaWiki\Block\BlockUser;
use MediaWiki\Block\BlockUserFactory;
use MediaWiki\CheckUser\Investigate\Utilities\EventLogger;
use MediaWiki\Exception\PermissionsError;
use MediaWiki\HTMLForm\HTMLForm;
use MediaWiki\Linker\Linker;
use MediaWiki\MainConfigNames;
use MediaWiki\Permissions\PermissionManager;
use MediaWiki\Request\DerivativeRequest;
use MediaWiki\SpecialPage\FormSpecialPage;
use MediaWiki\Title\TitleFormatter;
use MediaWiki\Title\TitleValue;
use MediaWiki\User\User;
use MediaWiki\User\UserFactory;
use MediaWiki\User\UserNameUtils;
use OOUI\FieldLayout;
use OOUI\Widget;
use Wikimedia\IPUtils;

class SpecialInvestigateBlock extends FormSpecialPage {
	private BlockUserFactory $blockUserFactory;
	private BlockPermissionCheckerFactory $blockPermissionCheckerFactory;
	private PermissionManager $permissionManager;
	private TitleFormatter $titleFormatter;
	private UserFactory $userFactory;
	private EventLogger $eventLogger;

	private array $blockedUsers = [];

	private bool $noticesFailed = false;

	public function __construct(
		BlockUserFactory $blockUserFactory,
		BlockPermissionCheckerFactory $blockPermissionCheckerFactory,
		PermissionManager $permissionManager,
		TitleFormatter $titleFormatter,
		UserFactory $userFactory,
		EventLogger $eventLogger
	) {
		parent::__construct( 'InvestigateBlock', 'checkuser' );

		$this->blockUserFactory = $blockUserFactory;
		$this->blockPermissionCheckerFactory = $blockPermissionCheckerFactory;
		$this->permissionManager = $permissionManager;
		$this->titleFormatter = $titleFormatter;
		$this->userFactory = $userFactory;
		$this->eventLogger = $eventLogger;
	}

	/**
	 * @inheritDoc
	 */
	public function userCanExecute( User $user ) {
		return parent::userCanExecute( $user ) &&
			$this->permissionManager->userHasRight( $user, 'block' );
	}

	/**
	 * @inheritDoc
	 */
	public function checkPermissions() {
		$user = $this->getUser();
		if ( !parent::userCanExecute( $user ) ) {
			$this->displayRestrictionError();
		}

		// User is a checkuser, but now to check for if they can block.
		if ( !$this->permissionManager->userHasRight( $user, 'block' ) ) {
			throw new PermissionsError( 'block' );
		}
	}

	/**
	 * @inheritDoc
	 */
	protected function getDisplayFormat() {
		return 'ooui';
	}

	/**
	 * @inheritDoc
	 */
	public function getFormFields() {
		$this->getOutput()->addModules( [
			'ext.checkUser'
		] );
		$this->getOutput()->addModuleStyles( [
			'mediawiki.widgets.TagMultiselectWidget.styles',
			'ext.checkUser.styles',
		] );
		$this->getOutput()->enableOOUI();

		$fields = [];

		$maxBlocks = $this->getConfig()->get( 'CheckUserMaxBlocks' );
		$fields['Targets'] = [
			'type' => 'usersmultiselect',
			'ipallowed' => true,
			'iprange' => true,
			'autofocus' => true,
			'required' => true,
			'exists' => true,
			'input' => [
				'autocomplete' => false,
			],
			// The following message key is generated:
			// * checkuser-investigateblock-target
			'section' => 'target',
			'default' => '',
			'max' => $maxBlocks,
			// Show a warning message to the user if the user loaded Special:InvestigateBlock via some kind of
			// pre-filled link, and the number of users provided exceeds the limit. This warning is displayed
			// elsewhere as an error if the form is submitted.
			'filter-callback' => function ( $users, $_, ?HTMLForm $htmlForm ) use ( $maxBlocks ) {
				if (
					$users !== null && $htmlForm !== null &&
					// If wpEditToken is set, then the user is attempting to submit the form and this will be
					// shown as an error instead of a warning by HTMLForm.
					!$this->getRequest()->getVal( 'wpEditToken' ) &&
					count( explode( "\n", $users ) ) > $maxBlocks
				) {
					// Show a warning message if the number of users provided exceeds the limit.
					$htmlForm->addHeaderHtml( new FieldLayout(
						new Widget( [] ),
						[
							'classes' => [ 'mw-htmlform-ooui-header-warnings' ],
							'warnings' => [
								$this->msg( 'checkuser-investigateblock-warning-users-truncated', $maxBlocks )->parse()
							],
						]
					) );
				}

				return $users;
			},
		];

		if (
			$this->blockPermissionCheckerFactory
				->newChecker( $this->getUser() )
				->checkEmailPermissions()
		) {
			$fields['DisableEmail'] = [
				'type' => 'check',
				'label-message' => 'checkuser-investigateblock-email-label',
				'default' => false,
				'section' => 'actions',
			];
		}

		if ( $this->getConfig()->get( MainConfigNames::BlockAllowsUTEdit ) ) {
			$fields['DisableUTEdit'] = [
				'type' => 'check',
				'label-message' => 'checkuser-investigateblock-usertalk-label',
				'default' => false,
				'section' => 'actions',
			];
		}

		if ( $this->getConfig()->get( MainConfigNames::EnableMultiBlocks ) ) {
			$fields['NewBlock'] = [
				'type' => 'check',
				'label-message' => 'checkuser-investigateblock-newblock-label',
				'default' => false,
				// The following message key is generated:
				// * checkuser-investigateblock-actions
				'section' => 'actions',
			];
		} else {
			$fields['Reblock'] = [
				'type' => 'check',
				'label-message' => 'checkuser-investigateblock-reblock-label',
				'default' => false,
				// The following message key is generated:
				// * checkuser-investigateblock-actions
				'section' => 'actions',
			];
		}

		$fields['Reason'] = [
			'type' => 'selectandother',
			'options-message' => 'checkuser-block-reason-dropdown',
			'maxlength' => 150,
			'required' => true,
			'autocomplete' => false,
			// The following message key is generated:
			// * checkuser-investigateblock-reason
			'section' => 'reason',
		];

		$pageNoticeClass = 'ext-checkuser-investigate-block-notice';
		$pageNoticePosition = [
			'type' => 'select',
			'cssclass' => $pageNoticeClass,
			'label-message' => 'checkuser-investigateblock-notice-position-label',
			'options-messages' => [
				'checkuser-investigateblock-notice-prepend' => 'prependtext',
				'checkuser-investigateblock-notice-replace' => 'text',
				'checkuser-investigateblock-notice-append' => 'appendtext',
			],
			// The following message key is generated:
			// * checkuser-investigateblock-options
			'section' => 'options',
		];
		$pageNoticeText = [
			'type' => 'text',
			'cssclass' => $pageNoticeClass,
			'label-message' => 'checkuser-investigateblock-notice-text-label',
			'default' => '',
			'section' => 'options',
		];

		// Check for SocialProfile being installed (T390774)
		// Using the wAvatar class existence check as a proxy because as of
		// early April 2025 SocialProfile lacks an extension.json entry point, which
		// thus prevents using ExtensionRegistry to check if SP is installed
		if ( !class_exists( 'wAvatar' ) ) {
			$fields['UserPageNotice'] = [
				'type' => 'check',
				'label-message' => 'checkuser-investigateblock-notice-user-page-label',
				'default' => false,
				'section' => 'options',
			];
			$fields['UserPageNoticePosition'] = array_merge(
				$pageNoticePosition,
				[ 'default' => 'prependtext' ]
			);
			$fields['UserPageNoticeText'] = $pageNoticeText;
		}

		$fields['TalkPageNotice'] = [
			'type' => 'check',
			'label-message' => 'checkuser-investigateblock-notice-talk-page-label',
			'default' => false,
			'section' => 'options',
		];
		$fields['TalkPageNoticePosition'] = array_merge(
			$pageNoticePosition,
			[ 'default' => 'appendtext' ]
		);
		$fields['TalkPageNoticeText'] = $pageNoticeText;

		$fields['Confirm'] = [
			'type' => $this->showConfirmationCheckbox() ? 'check' : 'hidden',
			'default' => '',
			'label-message' => 'checkuser-investigateblock-confirm-blocks-label',
			'cssclass' => 'ext-checkuser-investigateblock-block-confirm',
		];

		return $fields;
	}

	/**
	 * Should the 'Confirm blocks' checkbox be shown?
	 *
	 * @return bool True if the form was submitted and the targets input has both IPs and users. Otherwise false.
	 */
	private function showConfirmationCheckbox(): bool {
		// We cannot access HTMLForm->mWasSubmitted directly to work out if the form was submitted, as this has not
		// been generated yet. However, we can approximate this by checking if the request was POSTed and if the
		// wpEditToken is set.
		return $this->getRequest()->wasPosted() &&
			$this->getRequest()->getVal( 'wpEditToken' ) &&
			$this->checkForIPsAndUsersInTargetsParam( $this->getRequest()->getText( 'wpTargets' ) );
	}

	/**
	 * Returns whether the 'Targets' parameter contains both IPs and usernames.
	 *
	 * @param string $targets The value of the 'Targets' parameter, either from the request via ::getText or (if in
	 *    ::onSubmit) from the data array.
	 * @return bool True if the 'Targets' parameter contains both IPs and usernames, false otherwise.
	 */
	private function checkForIPsAndUsersInTargetsParam( string $targets ): bool {
		// The 'usersmultiselect' field data is formatted by each username being seperated by a newline (\n).
		$targets = explode( "\n", $targets );
		// Get an array of booleans indicating whether each target is an IP address. If the array contains both true and
		// false, then the 'Targets' parameter contains both IPs and usernames. Otherwise it does not.
		$areTargetsIPs = array_map( [ IPUtils::class, 'isIPAddress' ], $targets );
		return in_array( true, $areTargetsIPs, true ) && in_array( false, $areTargetsIPs, true );
	}

	/**
	 * @inheritDoc
	 */
	public function getDescription() {
		return $this->msg( 'checkuser-investigateblock' );
	}

	/**
	 * @inheritDoc
	 */
	protected function getMessagePrefix() {
		return 'checkuser-' . strtolower( $this->getName() );
	}

	/**
	 * @inheritDoc
	 */
	protected function getGroupName() {
		return 'users';
	}

	/**
	 * @inheritDoc
	 */
	public function onSubmit( array $data ) {
		$this->blockedUsers = [];

		// This might have been a hidden field or a checkbox, so interesting data can come from it. This handling is
		// copied from SpecialBlock::processFormInternal.
		$data['Confirm'] = !in_array( $data['Confirm'], [ '', '0', null, false ], true );

		// If the targets are both IPs and usernames, we should warn the CheckUser before allowing them to proceed to
		// avoid inadvertently violating any privacy policies.
		if ( $this->checkForIPsAndUsersInTargetsParam( $data['Targets'] ) && !$data['Confirm'] ) {
			return [
				'checkuser-investigateblock-warning-ips-and-users-in-targets',
				'checkuser-investigateblock-warning-confirmaction'
			];
		}

		$targets = explode( "\n", $data['Targets'] );
		// Format of $data['Reason'] is an array with items as documented in
		// HTMLSelectAndOtherField::loadDataFromRequest. The value in this should not be empty, as the field is marked
		// as required and as such the validation will be done by HTMLForm.
		$reason = $data['Reason'][0];

		$enableMulti = $this->getConfig()->get( MainConfigNames::EnableMultiBlocks );
		foreach ( $targets as $target ) {
			$isIP = IPUtils::isIPAddress( $target );

			if ( !$isIP ) {
				$user = $this->userFactory->newFromName( $target );
				if ( !$user || !$user->getId() ) {
					continue;
				}
			}

			$expiry = $isIP ? '1 week' : 'indefinite';

			if ( $enableMulti ) {
				$conflictMode = $data['NewBlock']
					? BlockUser::CONFLICT_NEW : BlockUser::CONFLICT_FAIL;
			} else {
				$conflictMode = $data['Reblock']
					? BlockUser::CONFLICT_REBLOCK : BlockUser::CONFLICT_FAIL;
			}

			$status = $this->blockUserFactory->newBlockUser(
				$target,
				$this->getUser(),
				$expiry,
				$reason,
				[
					'isHardBlock' => !$isIP,
					'isCreateAccountBlocked' => true,
					'isAutoblocking' => true,
					'isEmailBlocked' => $data['DisableEmail'] ?? false,
					'isUserTalkEditBlocked' => $data['DisableUTEdit'] ?? false,
				]
			)->placeBlock( $conflictMode );

			if ( $status->isOK() ) {
				$this->blockedUsers[] = $target;

				// Check for SocialProfile being installed (T390774)
				if ( !class_exists( 'wAvatar' ) && $data['UserPageNotice'] ) {
					$this->addNoticeToPage(
						$this->getTargetPage( NS_USER, $target ),
						$data['UserPageNoticeText'],
						$data['UserPageNoticePosition'],
						$reason
					);
				}

				if ( $data['TalkPageNotice'] ) {
					$this->addNoticeToPage(
						$this->getTargetPage( NS_USER_TALK, $target ),
						$data['TalkPageNoticeText'],
						$data['TalkPageNoticePosition'],
						$reason
					);
				}
			}
		}

		$blockedUsersCount = count( $this->blockedUsers );

		$this->eventLogger->logEvent( [
			'action' => 'block',
			'targetsCount' => count( $targets ),
			'relevantTargetsCount' => $blockedUsersCount,
		] );

		if ( $blockedUsersCount === 0 ) {
			return [ 'checkuser-investigateblock-failure' . ( $enableMulti ? '-multi' : '' ) ];
		}

		return true;
	}

	/**
	 * @param int $namespace
	 * @param string $target Must be a valid IP address or a valid user name
	 * @return string
	 */
	private function getTargetPage( int $namespace, string $target ): string {
		if ( IPUtils::isValidRange( $target ) ) {
			$target = IPUtils::sanitizeRange( $target );
		}

		return $this->titleFormatter->getPrefixedText(
			new TitleValue( $namespace, $target )
		);
	}

	/**
	 * Add a notice to a given page. The notice may be prepended or appended,
	 * or it may replace the page.
	 *
	 * @param string $title Page to which to add the notice
	 * @param string $notice The notice, as wikitext
	 * @param string $position One of 'prependtext', 'appendtext' or 'text'
	 * @param string $summary Edit summary
	 */
	private function addNoticeToPage(
		string $title,
		string $notice,
		string $position,
		string $summary
	): void {
		$apiParams = [
			'action' => 'edit',
			'title' => $title,
			$position => $notice,
			'summary' => $summary,
			'token' => $this->getContext()->getCsrfTokenSet()->getToken(),
		];

		$api = new ApiMain(
			new DerivativeRequest(
				$this->getRequest(),
				$apiParams,
				// was posted
				true
			),
			// enable write
			true
		);

		try {
			$api->execute();
		} catch ( Exception $e ) {
			$this->noticesFailed = true;
		}
	}

	/**
	 * @inheritDoc
	 */
	public function onSuccess() {
		$blockedUsers = array_map( function ( $userName ) {
			$user = $this->userFactory->newFromName(
				$userName,
				UserNameUtils::RIGOR_NONE
			);
			return Linker::userLink( $user->getId(), $userName );
		}, $this->blockedUsers );

		$language = $this->getLanguage();

		$blockedMessage = $this->msg( 'checkuser-investigateblock-success' )
			->rawParams( $language->listToText( $blockedUsers ) )
			->params( $language->formatNum( count( $blockedUsers ) ) )
			->parseAsBlock();

		$out = $this->getOutput();
		$out->setPageTitleMsg( $this->msg( 'blockipsuccesssub' ) );
		$out->addHtml( $blockedMessage );

		if ( $this->noticesFailed ) {
			$failedNoticesMessage = $this->msg( 'checkuser-investigateblock-notices-failed' );
			$out->addHtml( $failedNoticesMessage );
		}
	}

	/**
	 * InvestigateBlock writes to the DB when the form is submitted.
	 *
	 * @return true
	 */
	public function doesWrites() {
		return true;
	}
}
