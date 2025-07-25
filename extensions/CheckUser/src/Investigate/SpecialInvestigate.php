<?php

namespace MediaWiki\CheckUser\Investigate;

use MediaWiki\CheckUser\GuidedTour\TourLauncher;
use MediaWiki\CheckUser\Hook\CheckUserSubtitleLinksHook;
use MediaWiki\CheckUser\HookHandler\Preferences;
use MediaWiki\CheckUser\Investigate\Pagers\ComparePager;
use MediaWiki\CheckUser\Investigate\Pagers\PagerFactory;
use MediaWiki\CheckUser\Investigate\Pagers\PreliminaryCheckPager;
use MediaWiki\CheckUser\Investigate\Pagers\TimelinePager;
use MediaWiki\CheckUser\Investigate\Pagers\TimelinePagerFactory;
use MediaWiki\CheckUser\Investigate\Utilities\DurationManager;
use MediaWiki\CheckUser\Investigate\Utilities\EventLogger;
use MediaWiki\CheckUser\Services\CheckUserLogService;
use MediaWiki\CheckUser\Services\TokenQueryManager;
use MediaWiki\Html\Html;
use MediaWiki\HTMLForm\HTMLForm;
use MediaWiki\Language\Language;
use MediaWiki\Linker\LinkRenderer;
use MediaWiki\Message\Message;
use MediaWiki\Parser\ParserOptions;
use MediaWiki\Parser\ParserOutput;
use MediaWiki\Permissions\PermissionManager;
use MediaWiki\SpecialPage\FormSpecialPage;
use MediaWiki\Status\Status;
use MediaWiki\User\Options\UserOptionsManager;
use MediaWiki\User\UserFactory;
use MediaWiki\User\UserIdentityLookup;
use MediaWiki\Utils\UrlUtils;
use OOUI\ButtonGroupWidget;
use OOUI\ButtonWidget;
use OOUI\Element;
use OOUI\FieldLayout;
use OOUI\FieldsetLayout;
use OOUI\HorizontalLayout;
use OOUI\HtmlSnippet;
use OOUI\IndexLayout;
use OOUI\MessageWidget;
use OOUI\TabOptionWidget;
use OOUI\Tag;
use OOUI\Widget;
use Wikimedia\IPUtils;
use Wikimedia\Timestamp\ConvertibleTimestamp;

class SpecialInvestigate extends FormSpecialPage {
	private Language $contentLanguage;
	private UserOptionsManager $userOptionsManager;
	private PagerFactory $preliminaryCheckPagerFactory;
	private PagerFactory $comparePagerFactory;
	private TimelinePagerFactory $timelinePagerFactory;
	private TokenQueryManager $tokenQueryManager;
	private DurationManager $durationManager;
	private EventLogger $eventLogger;
	private TourLauncher $tourLauncher;
	private CheckUserSubtitleLinksHook $subtitleLinksHookRunner;
	private PermissionManager $permissionManager;
	private CheckUserLogService $checkUserLogService;
	private UserIdentityLookup $userIdentityLookup;
	private UserFactory $userFactory;
	private UrlUtils $urlUtils;

	/** @var IndexLayout|null */
	private $layout;

	/** @var array|null */
	private $tokenData;

	/** @var HTMLForm|null */
	private $form;

	/** @var string|null */
	private $tokenWithoutPaginationData;

	/** @var int */
	private const MAX_TARGETS = 10;

	/** @var string */
	public const TOUR_INVESTIGATE = 'checkuserinvestigate';

	/** @var string */
	public const TOUR_INVESTIGATE_FORM = 'checkuserinvestigateform';

	public function __construct(
		LinkRenderer $linkRenderer,
		Language $contentLanguage,
		UserOptionsManager $userOptionsManager,
		PagerFactory $preliminaryCheckPagerFactory,
		PagerFactory $comparePagerFactory,
		PagerFactory $timelinePagerFactory,
		TokenQueryManager $tokenQueryManager,
		DurationManager $durationManager,
		EventLogger $eventLogger,
		TourLauncher $tourLauncher,
		CheckUserSubtitleLinksHook $subtitleLinksHookRunner,
		PermissionManager $permissionManager,
		CheckUserLogService $checkUserLogService,
		UserIdentityLookup $userIdentityLookup,
		UserFactory $userFactory,
		UrlUtils $urlUtils
	) {
		parent::__construct( 'Investigate', 'checkuser' );
		$this->setLinkRenderer( $linkRenderer );
		$this->contentLanguage = $contentLanguage;
		$this->userOptionsManager = $userOptionsManager;
		$this->preliminaryCheckPagerFactory = $preliminaryCheckPagerFactory;
		$this->comparePagerFactory = $comparePagerFactory;
		$this->timelinePagerFactory = $timelinePagerFactory;
		$this->tokenQueryManager = $tokenQueryManager;
		$this->durationManager = $durationManager;
		$this->eventLogger = $eventLogger;
		$this->tourLauncher = $tourLauncher;
		$this->subtitleLinksHookRunner = $subtitleLinksHookRunner;
		$this->permissionManager = $permissionManager;
		$this->checkUserLogService = $checkUserLogService;
		$this->userIdentityLookup = $userIdentityLookup;
		$this->userFactory = $userFactory;
		$this->urlUtils = $urlUtils;
	}

	/**
	 * @inheritDoc
	 */
	protected function preHtml() {
		// Add necessary styles
		$this->getOutput()->addModuleStyles( [
			'mediawiki.widgets.TagMultiselectWidget.styles',
			'ext.checkUser.styles',
		] );
		// Add button link to the log page on the main form.
		// Open in the current tab.
		$this->addIndicators( false );

		return '';
	}

	/**
	 * @inheritDoc
	 */
	public function execute( $par ) {
		// Always call the parent method in order to check execute permissions.
		parent::execute( $par );

		// If the form submission results in a redirect, there is no need to
		// generate content for the page.
		if ( $this->getOutput()->getRedirect() !== '' ) {
			return;
		}

		$this->getOutput()->addModules( [ 'ext.checkUser' ] );

		// Show the tabs if there is any request data.
		// The tabs should also be shown even if the form was a POST request because
		// the filters could have failed validation.
		if ( $par && $this->getTokenData() !== [] ) {
			// Remove the filters, unless a valid tab that supports filters is selected.
			if ( !in_array( $par, [
				$this->getTabParam( 'compare' ),
				$this->getTabParam( 'timeline' ),
			] ) ) {
				$this->getOutput()->clearHTML();
			}

			$this->addIndicators( true );
			$this->addBlockForm();
			$this->addTabs( $par )->addTabContent( $par );
			$this->getOutput()->addHTML( $this->getLayout() );
		} else {
			$this->launchTour( self::TOUR_INVESTIGATE_FORM );
		}

		// Add the links after any previous HTML has been cleared.
		$this->addSubtitle();
		$this->addHelpLink(
			'https://www.mediawiki.org/wiki/Special:MyLanguage/Help:Special_Investigate',
			true
		);
	}

	/**
	 * Returns the OOUI Index Layout and adds the module dependencies for OOUI.
	 *
	 * @return IndexLayout
	 */
	private function getLayout(): IndexLayout {
		if ( $this->layout === null ) {
			$this->getOutput()->enableOOUI();
			$this->getOutput()->addModuleStyles( [
				'oojs-ui-widgets.styles',
			] );

			$this->layout = new IndexLayout( [
				'framed' => false,
				'expanded' => false,
				'classes' => [ 'ext-checkuser-investigate-tabs-indexLayout' ],
			] );
		}

		return $this->layout;
	}

	/**
	 * Add tabs to the layout. Provide the current tab so that tab can be highlighted.
	 *
	 * @param string $par
	 * @return self
	 */
	private function addTabs( string $par ): self {
		$config = $this->getLayout()->getConfig( $config );

		/* @var TabSelectWidget $tabSelectWidget */
		$tabSelectWidget = $config['tabSelectWidget'];

		$token = $this->getTokenWithoutPaginationData();

		$tabs = array_map( function ( $tab ) use ( $par, $token ) {
			$label = $this->getTabMessage( $tab )->text();
			$param = $this->getTabParam( $tab );
			return new TabOptionWidget( [
				'label' => $label,
				'labelElement' => ( new Tag( 'a' ) )->setAttributes( [
					'href' => $this->getPageTitle( $param )->getLocalURL( [
						'token' => $token,
						'duration' => $this->getDuration() ?: null,
					] ),
				] ),
				'selected' => ( $par === $param ),
			] );
		}, [
			'preliminary-check',
			'compare',
			'timeline',
		] );

		$tabSelectWidget->addItems( $tabs );

		return $this;
	}

	/**
	 * @return string|null
	 */
	private function getTokenWithoutPaginationData() {
		if ( $this->tokenWithoutPaginationData === null ) {
			$this->tokenWithoutPaginationData = $this->getUpdatedToken( [
				'offset' => null,
			] );
		}
		return $this->tokenWithoutPaginationData;
	}

	/**
	 * Add HTML to Layout.
	 *
	 * @param string $html
	 * @return self
	 */
	private function addHtml( string $html ): self {
		$config = $this->getLayout()->getConfig( $config );

		/* @var StackLayout $contentPanel */
		$contentPanel = $config['contentPanel'];

		$contentPanel->addItems( [
			new Element( [
				'content' => new HtmlSnippet( $html ),
			] ),
		] );

		return $this;
	}

	/**
	 * Add Pager Output to Layout.
	 *
	 * @param ParserOutput $parserOutput
	 * @return self
	 */
	private function addParserOutput( ParserOutput $parserOutput ): self {
		$this->getOutput()->addParserOutputMetadata( $parserOutput );
		$popts = ParserOptions::newFromContext( $this->getContext() );
		$html = $parserOutput->runOutputPipeline( $popts, [] )->getContentHolderText();
		$this->addHTML( $html );

		return $this;
	}

	/**
	 * Add Tab content to Layout
	 *
	 * @param string $par
	 * @return self
	 */
	private function addTabContent( string $par ): self {
		$startTime = ConvertibleTimestamp::hrtime();
		$durationMs = static fn () => ( ConvertibleTimestamp::hrtime() - $startTime ) / 1e6;

		switch ( $par ) {
			case $this->getTabParam( 'preliminary-check' ):
				/** @var PreliminaryCheckPager $pager */
				$pager = $this->preliminaryCheckPagerFactory->createPager( $this->getContext() );
				$hasIpTargets = (bool)array_filter(
					$this->getTokenData()['targets'] ?? [],
					[ IPUtils::class, 'isIPAddress' ]
				);

				if ( $pager->getNumRows() ) {
					$this->addParserOutput( $pager->getFullOutput() );
				} elseif ( !$hasIpTargets ) {
					$this->addHTML(
						$this->msg( 'checkuser-investigate-notice-no-results' )->parse()
					);
				}

				if ( $hasIpTargets ) {
					$compareParam = $this->getTabParam( 'compare' );
					// getFullURL handles the query params:
					// https://www.mediawiki.org/wiki/Help:Links#External_links_to_internal_pages
					$link = $this->getPageTitle( $compareParam )->getFullURL( [
						'token' => $this->getTokenWithoutPaginationData(),
					] );
					$message = $this->msg( 'checkuser-investigate-preliminary-notice-ip-targets', $link )->parse();
					$this->addHTML( new MessageWidget( [
						'type' => 'notice',
						'label' => new HtmlSnippet( $message )
					] ) );
				}

				$this->logQuery( [
					'tab' => 'preliminary-check',
					'resultsCount' => $pager->getNumRows(),
					'resultsIncomplete' => false,
					'queryTime' => $durationMs(),
				] );

				break;

			case $this->getTabParam( 'compare' ):
				/** @var ComparePager $pager */
				$pager = $this->comparePagerFactory->createPager( $this->getContext() );
				$numRows = $pager->getNumRows();

				if ( $numRows ) {
					$targetsOverLimit = $pager->getTargetsOverLimit();
					if ( $targetsOverLimit ) {
						// Hide target usernames which the current authority cannot see.
						foreach ( $targetsOverLimit as &$target ) {
							$user = $this->userFactory->newFromName( $target );
							if (
								$user !== null &&
								$user->isHidden() &&
								!$this->getUser()->isAllowed( 'hideuser' )
							) {
								$target = $this->msg( 'rev-deleted-user' )->text();
							}
						}
						$message = $this->msg(
							'checkuser-investigate-compare-notice-exceeded-limit',
							$this->getLanguage()->commaList( $targetsOverLimit )
						)->parse();
						$this->addHTML( new MessageWidget( [
							'type' => 'warning',
							'label' => new HtmlSnippet( $message )
						] ) );
					}

					// Only start the tour if there are results on the page.
					$this->launchTour( self::TOUR_INVESTIGATE );

					$this->addParserOutput( $pager->getFullOutput() );
				} else {
					$messageKey = $this->usingFilters() ?
						'checkuser-investigate-compare-notice-no-results-filters' :
						'checkuser-investigate-compare-notice-no-results';
					$message = $this->msg( $messageKey )->parse();
					$this->addHTML( new MessageWidget( [
						'type' => 'warning',
						'label' => new HtmlSnippet( $message )
					] ) );
				}

				$this->logQuery( [
					'tab' => 'compare',
					'resultsCount' => $numRows,
					'resultsIncomplete' => $numRows && $targetsOverLimit,
					'queryTime' => $durationMs(),
				] );

				break;

			case $this->getTabParam( 'timeline' ):
				/** @var TimelinePager $pager */
				$pager = $this->timelinePagerFactory->createPager( $this->getContext() );
				$numRows = $pager->getNumRows();

				if ( $numRows ) {
					$this->addParserOutput( $pager->getFullOutput() );
				} else {
					$messageKey = $this->usingFilters() ?
						'checkuser-investigate-timeline-notice-no-results-filters' :
						'checkuser-investigate-timeline-notice-no-results';
					$message = $this->msg( $messageKey )->parse();
					$this->addHTML( new MessageWidget( [
						'type' => 'warning',
						'label' => new HtmlSnippet( $message )
					] ) );
				}

				$this->logQuery( [
					'tab' => 'timeline',
					'resultsCount' => $pager->getNumRows(),
					'resultsIncomplete' => false,
					'queryTime' => $durationMs(),
				] );

				break;
		}

		return $this;
	}

	/**
	 * @param array $logData
	 */
	private function logQuery( array $logData ): void {
		$relevantTargetsCount = count( array_diff(
			$this->getTokenData()['targets'] ?? [],
			$this->getTokenData()['exclude-targets'] ?? []
		) );

		$this->eventLogger->logEvent( array_merge(
			[
				'action' => 'query',
				'relevantTargetsCount' => $relevantTargetsCount,
			],
			$logData
		) );
	}

	/**
	 * Given a tab name, return the subpage $par.
	 *
	 * Since the page title is always in the content language, the subpage should be also.
	 *
	 * @param string $tab
	 *
	 * @return string
	 */
	private function getTabParam( string $tab ): string {
		$name = $this->getTabMessage( $tab )->inLanguage( $this->contentLanguage )->text();
		return str_replace( ' ', '_', $name );
	}

	/**
	 * Given a tab name, return the subpage tab message.
	 *
	 * @param string $tab
	 *
	 * @return Message
	 */
	private function getTabMessage( string $tab ): Message {
		// The following messages are generated here:
		// * checkuser-investigate-tab-preliminary-check
		// * checkuser-investigate-tab-compare
		// * checkuser-investigate-tab-timeline
		return $this->msg( 'checkuser-investigate-tab-' . $tab );
	}

	/**
	 * @inheritDoc
	 */
	public function getDescription() {
		return $this->msg( 'checkuser-investigate' );
	}

	/**
	 * @inheritDoc
	 */
	protected function getMessagePrefix() {
		return 'checkuser-' . strtolower( $this->getName() );
	}

	/**
	 * Add page subtitle including the name of the targets in the investigation,
	 * and a block form. Add the block form elements that are visible initially,
	 * to avoid a flicker on page load.
	 */
	private function addBlockForm() {
		$targets = $this->getTokenData()['targets'] ?? [];
		if ( $targets ) {
			$userCanBlock = $this->permissionManager->userHasRight( $this->getUser(), 'block' );
			$excludeTargets = $this->getTokenData()['exclude-targets'] ?? [];

			$this->getOutput()->addJsConfigVars( [
				'wgCheckUserInvestigateTargets' => $targets,
				'wgCheckUserInvestigateExcludeTargets' => $excludeTargets,
				'wgCheckUserInvestigateCanBlock' => $userCanBlock,
			] );

			$targetsText = $this->getLanguage()->listToText( array_map( static function ( $target ) {
				return Html::rawElement( 'strong', [], Html::rawElement( 'bdi', [], htmlspecialchars( $target ) ) );
			}, $targets ) );
			$subtitle = $this->msg( 'checkuser-investigate-page-subtitle', $targetsText );

			// Placeholder, to allow the FieldLayout label to be shown before the
			// JavaScript loads. This will be replaced by a TagMultiselect (which
			// has not yet been implemented in PHP).
			$placeholderWidget = new Widget( [
				'classes' => [ 'ext-checkuser-investigate-subtitle-placeholder-widget' ],
			] );
			$items = [];
			$items[] = new FieldLayout(
				$placeholderWidget,
				[
					'label' => new HtmlSnippet( $subtitle->parse() ),
					'align' => 'top',
					'infusable' => true,
					'classes' => [
						'ext-checkuser-investigate-subtitle-targets-layout'
					]
				]
			);
			if ( $userCanBlock ) {
				$blockAccountsButton = new ButtonWidget( [
					'infusable' => true,
					'label' => $this->msg( 'checkuser-investigate-subtitle-block-accounts-button-label' )->text(),
					'flags' => [ 'primary', 'progressive' ],
					'classes' => [
						'ext-checkuser-investigate-subtitle-block-button',
						'ext-checkuser-investigate-subtitle-block-accounts-button',
					],
				] );
				$blockIpsButton = new ButtonWidget( [
					'infusable' => true,
					'label' => $this->msg( 'checkuser-investigate-subtitle-block-ips-button-label' )->text(),
					'flags' => [ 'primary', 'progressive' ],
					'classes' => [
						'ext-checkuser-investigate-subtitle-block-button',
						'ext-checkuser-investigate-subtitle-block-ips-button',
					],
				] );
				$items[] = new FieldLayout(
					new Widget( [
						'content' => new HorizontalLayout( [
							'items' => [
								$blockAccountsButton,
								$blockIpsButton,
							]
						] )
					] ),
					[
						'align' => 'top',
						'infusable' => true,
					]
				);
			}

			$blockFieldset = new FieldsetLayout( [
				'classes' => [
					'ext-checkuser-investigate-subtitle-fieldset'
				],
				'items' => $items
			] );

			$this->getOutput()->prependHTML(
				$blockFieldset
			);
		}
	}

	/**
	 * Add buttons to start a new investigation and linking to log page
	 *
	 * @param bool $onSubpage whether the current page is a subpage of Special:Investigate
	 * 		(i.e. whether an investigation is currently happening).
	 */
	private function addIndicators( bool $onSubpage ) {
		$canViewLogs = $this->permissionManager->userHasRight( $this->getUser(), 'checkuser-log' );
		$buttons = [];
		if ( $canViewLogs ) {
			$buttons[] = new ButtonWidget( [
				'label' => $this->msg( 'checkuser-investigate-indicator-logs' )->text(),
				'href' => self::getTitleFor( 'CheckUserLog' )->getLinkURL(),
				'target' => $onSubpage ? '_blank' : '',
			] );
		}

		if ( $onSubpage ) {
			$buttons[] = new ButtonWidget( [
				'label' => $this->msg( 'checkuser-investigate-indicator-new-investigation' )->text(),
				'href' => $this->getPageTitle()->getLinkURL(),
				'target' => '_blank',
			] );
		}

		if ( count( $buttons ) > 0 ) {
			$this->getOutput()->setIndicators( [
				'ext-checkuser-investigation-btns' => new ButtonGroupWidget( [
					'classes' => [ 'ext-checkuser-investigate-indicators' ],
					'items' => $buttons,
				] ),
			] );
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
	protected function getForm() {
		if ( $this->form === null ) {
			$this->form = parent::getForm();
		}

		return $this->form;
	}

	/**
	 * @inheritDoc
	 */
	protected function getFormFields() {
		$data = $this->getTokenData();

		$duration = [
			'type' => 'select',
			'name' => 'duration',
			'id' => 'investigate-duration',
			'label-message' => 'checkuser-investigate-duration-label',
			'options-messages' => [
				'checkuser-investigate-duration-option-all' => '',
				'checkuser-investigate-duration-option-1w' => 'P1W',
				'checkuser-investigate-duration-option-2w' => 'P2W',
				'checkuser-investigate-duration-option-30d' => 'P30D',
			],
			// If this duration in the URL is not in the list, "all" is displayed.
			'default' => $this->getDuration(),
		];

		if ( $data === [] ) {
			$this->getOutput()->addJsConfigVars( 'wgCheckUserInvestigateMaxTargets', self::MAX_TARGETS );

			return [
				'Targets' => [
					'type' => 'usersmultiselect',
					'name' => 'targets',
					'label-message' => 'checkuser-investigate-targets-label',
					'placeholder' => $this->msg( 'checkuser-investigate-targets-placeholder' )->text(),
					'id' => 'targets',
					'required' => true,
					'max' => self::MAX_TARGETS,
					'exists' => true,
					'ipallowed' => true,
					'iprange' => true,
					'default' => '',
					'input' => [
						'autocomplete' => false,
					],
				],
				'Duration' => $duration,
				'Reason' => [
					'type' => 'text',
					'id' => 'investigate-reason',
					'name' => 'reason',
					'label-message' => 'checkuser-investigate-reason-label',
					'required' => true,
					'autocomplete' => false,
				],
			];
		}

		$fields = [];

		// Filters for both Compare & Timeline
		$compareTab = $this->getTabParam( 'compare' );
		$timelineTab = $this->getTabParam( 'timeline' );

		// Filters for both Compare & Timeline
		if ( in_array( $this->par, [ $compareTab, $timelineTab ], true ) ) {
			$fields['ExcludeTargets'] = [
				'type' => 'usersmultiselect',
				'name' => 'exclude-targets',
				'label-message' => 'checkuser-investigate-filters-exclude-targets-label',
				'exists' => true,
				'required' => false,
				'ipallowed' => true,
				'iprange' => false,
				'default' => implode( "\n", $data['exclude-targets'] ?? [] ),
				'input' => [
					'autocomplete' => false,
				],
			];
			$fields['Duration'] = $duration;
		}

		if ( $this->par === $compareTab ) {
			$fields['Targets'] = [
				'type' => 'hidden',
				'name' => 'targets',
			];
		}

		// if ( $this->par === $timelineTab ) {
			// @TODO Add filters specific to the timeline tab.
		// }

		return $fields;
	}

	/**
	 * @inheritDoc
	 */
	protected function alterForm( HTMLForm $form ) {
		// Not done by default in OOUI forms, but done here to match
		// intended design in T237034. See FormSpecialPage::getForm
		if ( $this->getTokenData() === [] ) {
			$form->setWrapperLegendMsg( 'checkuser-investigate-legend' );
		} else {
			$tabs = [ $this->getTabParam( 'compare' ), $this->getTabParam( 'timeline' ) ];
			if ( in_array( $this->par, $tabs ) ) {
				$form->setAction( $this->getRequest()->getRequestURL() );
				$form->setWrapperLegendMsg( 'checkuser-investigate-filters-legend' );
				// If the page is a result of a POST then validation failed, and the form should be open.
				// If the page is a result of a GET then validation succeeded and the form should be closed.
				$form->setCollapsibleOptions( !$this->getRequest()->wasPosted() );
			}
		}
	}

	/**
	 * Get data from the request token.
	 *
	 * @return array
	 */
	private function getTokenData(): array {
		if ( $this->tokenData === null ) {
			$this->tokenData = $this->tokenQueryManager->getDataFromRequest( $this->getRequest() );
		}

		return $this->tokenData;
	}

	/**
	 * @inheritDoc
	 */
	public function onSubmit( array $data ) {
		$update = [
			'offset' => null,
		];

		if ( isset( $data['Reason'] ) ) {
			$update['reason'] = $data['Reason'];
		}
		if ( isset( $data['ExcludeTargets' ] ) ) {
			$submittedExcludeTargets = $this->getArrayFromField( $data, 'ExcludeTargets' );
			$update['exclude-targets'] = $submittedExcludeTargets;
		}
		if ( isset( $data['Targets' ] ) ) {
			$tokenData = $this->getTokenData();

			$submittedTargets = $this->getArrayFromField( $data, 'Targets' );
			$update['targets'] = $submittedTargets;

			$this->addLogEntries(
				$update['targets'],
				$update['reason'] ?? $tokenData['reason']
			);

			$update['targets'] = array_unique( array_merge(
				$update['targets'],
				$tokenData['targets'] ?? []
			) );
		}

		$token = $this->getUpdatedToken( $update );

		if ( $this->par !== null && $this->par !== '' ) {
			// Redirect to the same subpage with an updated token.
			$url = $this->getRedirectUrl( [
				'token' => $token,
				'duration' => $data['Duration'] ?: null,
			] );
		} else {
			// Redirect to compare tab
			$url = $this->getPageTitle( $this->getTabParam( 'compare' ) )->getFullUrlForRedirect( [
				'token' => $token,
				'duration' => $data['Duration'] ?: null,
			] );
		}
		$this->getOutput()->redirect( $url );

		$this->eventLogger->logEvent( [
			'action' => 'submit',
			'targetsCount' => count( $submittedTargets ?? [] ),
			'excludeTargetsCount' => count( $submittedExcludeTargets ?? [] ),
		] );

		return Status::newGood();
	}

	/**
	 * Add a log entry for each target under investigation.
	 *
	 * @param string[] $targets
	 * @param string $reason
	 */
	protected function addLogEntries( array $targets, string $reason ) {
		$logType = 'investigate';
		$user = $this->getUser();

		foreach ( $targets as $target ) {
			if ( IPUtils::isIPAddress( $target ) ) {
				$targetType = 'ip';
				$targetId = 0;
			} else {
				// The form validated that the user exists on this wiki
				$targetType = 'user';
				$userIdentity = $this->userIdentityLookup->getUserIdentityByName( $target );
				$targetId = $userIdentity ? $userIdentity->getId() : 0;
			}

			$this->checkUserLogService->addLogEntry(
				$user,
				$logType,
				$targetType,
				$target,
				$reason,
				$targetId
			);
		}
	}

	/**
	 * @inheritDoc
	 */
	protected function getGroupName() {
		return 'users';
	}

	/**
	 * Get an updated token.
	 *
	 * Preforms an array merge on the updates with what is in the current token.
	 * Setting a value to null will remove it.
	 *
	 * @param array $update
	 * @return string
	 */
	private function getUpdatedToken( array $update ): string {
		return $this->tokenQueryManager->updateToken(
			$this->getRequest(),
			$update
		);
	}

	/**
	 * Get a redirect URL with a new query string.
	 *
	 * @param array $update
	 * @return string
	 */
	private function getRedirectUrl( array $update ): string {
		$parts = $this->urlUtils->parse( $this->getRequest()->getFullRequestURL() ) ?? [];
		$query = wfCgiToArray( $parts['query'] ?? '' );
		$data = array_filter( array_merge( $query, $update ), static function ( $value ) {
			return $value !== null;
		} );
		$parts['query'] = wfArrayToCgi( $data );
		return UrlUtils::assemble( $parts );
	}

	/**
	 * Get an array of values from a new line separated field.
	 *
	 * @param array $data
	 * @param string $field
	 * @return string[]
	 */
	private function getArrayFromField( array $data, string $field ): array {
		if ( !isset( $data[$field] ) ) {
			return [];
		}

		if ( !is_string( $data[$field] ) ) {
			return [];
		}

		if ( $data[$field] === '' ) {
			return [];
		}

		return explode( "\n", $data[$field] );
	}

	/**
	 * Determine if the filters are in use by the current request.
	 *
	 * @return bool
	 */
	private function usingFilters(): bool {
		return count( $this->getTokenData()['exclude-targets'] ?? [] ) > 0
			|| $this->getDuration() !== '';
	}

	/**
	 * Get the duration from the request.
	 *
	 * @return string
	 */
	private function getDuration(): string {
		return $this->durationManager->getFromRequest( $this->getRequest() );
	}

	/**
	 * Launches the tour unless the user has already completed or canceled it.
	 *
	 * @param string $tour
	 * @return void
	 */
	private function launchTour( string $tour ): void {
		$user = $this->getUser();

		switch ( $tour ) {
			case self::TOUR_INVESTIGATE_FORM:
				$preference = Preferences::INVESTIGATE_FORM_TOUR_SEEN;
				$step = 'targets';
				break;
			case self::TOUR_INVESTIGATE:
				$preference = Preferences::INVESTIGATE_TOUR_SEEN;
				$step = 'useragents';
				break;
			default:
				return;
		}

		if ( $this->userOptionsManager->getOption( $user, $preference ) ) {
			return;
		}

		$this->tourLauncher->launchTour( $tour, $step );
	}

	/**
	 * Add the subtitle to the page.
	 */
	private function addSubtitle(): void {
		$subpage = false;
		$token = null;
		$tour = self::TOUR_INVESTIGATE_FORM;

		if ( $this->getTokenData() !== [] ) {
			$token = $this->getTokenWithoutPaginationData();
			$subpage = $this->getTabParam( 'compare' );
			$tour = self::TOUR_INVESTIGATE;
		}

		$links = [
			$this->getLinkRenderer()->makeLink(
				self::getTitleValueFor( 'CheckUser' ),
				$this->msg( 'checkuser-showmain' )->text()
			),
			$this->tourLauncher->makeTourLink(
				$tour,
				$this->getPageTitle( $subpage ),
				$this->msg( 'checkuser-investigate-subtitle-link-restart-tour' )->text(),
				[],
				[
					'token' => $token,
					'duration' => $this->getDuration() ?: null,
				]
			),
		];

		$this->subtitleLinksHookRunner->onCheckUserSubtitleLinks( $this->getContext(), $links );

		$subtitle = implode( ' | ', array_filter( $links, static function ( $link ) {
			return (bool)$link;
		} ) );

		$this->getOutput()->setSubtitle( $subtitle );
	}
}
