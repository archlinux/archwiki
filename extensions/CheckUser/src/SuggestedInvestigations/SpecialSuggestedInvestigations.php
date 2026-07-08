<?php
/*
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

namespace MediaWiki\Extension\CheckUser\SuggestedInvestigations;

use MediaWiki\Extension\CheckUser\Hook\HookRunner;
use MediaWiki\Extension\CheckUser\HookHandler\Preferences;
use MediaWiki\Extension\CheckUser\SuggestedInvestigations\Instrumentation\ISuggestedInvestigationsInstrumentationClient;
use MediaWiki\Extension\CheckUser\SuggestedInvestigations\Pagers\SuggestedInvestigationsPagerFactory;
use MediaWiki\Extension\CheckUser\SuggestedInvestigations\Services\SuggestedInvestigationsCaseLookupService;
use MediaWiki\Extension\CheckUser\SuggestedInvestigations\Services\SuggestedInvestigationsMessageRenderer;
use MediaWiki\Html\Html;
use MediaWiki\Parser\ParserOptions;
use MediaWiki\SpecialPage\SpecialPage;
use MediaWiki\User\Options\UserOptionsLookup;
use Wikimedia\Message\MessageValue;

class SpecialSuggestedInvestigations extends SpecialPage {

	/**
	 * @var bool When true, the page shows one specific case row with additional information
	 *   about the case ("detail" view)
	 */
	private bool $isInDetailedView = false;

	/**
	 * @var int|null When {@link self::$isInDetailedView} is `true`, the ID of the case
	 *   being viewed in detailed view. Otherwise, `null`.
	 */
	private int|null $detailedViewCaseId = null;

	/** @var array The signals provided by the CheckUserSuggestedInvestigationsGetSignals hook */
	private array $signals = [];

	public function __construct(
		private readonly HookRunner $hookRunner,
		private readonly SuggestedInvestigationsCaseLookupService $suggestedInvestigationsCaseLookupService,
		private readonly ISuggestedInvestigationsInstrumentationClient $instrumentationClient,
		private readonly SuggestedInvestigationsPagerFactory $pagerFactory,
		private readonly SuggestedInvestigationsMessageRenderer $messageRenderer,
		private readonly UserOptionsLookup $userOptionsLookup,
	) {
		parent::__construct( 'SuggestedInvestigations' );
	}

	/** @inheritDoc */
	public function getRestriction(): string {
		return 'checkuser-suggested-investigations';
	}

	/** @inheritDoc */
	public function execute( $subPage ) {
		$subPageIsValid = $this->parseSubPage( $subPage );
		if ( !$subPageIsValid ) {
			return;
		}

		$this->hookRunner->onCheckUserSuggestedInvestigationsGetSignals( $this->signals );

		parent::execute( $subPage );
		$this->addNavigationLinks();

		$this->addHelpLink( 'Help:Extension:CheckUser/Suggested investigations' );

		$output = $this->getOutput();
		$output->addHtml( '<div id="ext-suggestedinvestigations-change-status-app"></div>' );
		$output->addHtml( '<div id="ext-suggestedinvestigations-filter-app"></div>' );
		$output->addHTML( '<div id="ext-suggestedinvestigations-signals-popover-app"></div>' );
		$output->addModules( 'ext.checkUser.suggestedInvestigations' );
		$output->addModuleStyles( 'ext.checkUser.styles' );

		$pager = $this->pagerFactory->createCasesPager(
			$this->getContext(),
			$this->signals,
			$this->detailedViewCaseId
		);

		// Map camel case filter names to snake case, as event logging schema
		// wants snake case
		$appliedFiltersForInstrumentation = [];
		foreach ( $pager->appliedFilters as $filterName => $filterValue ) {
			if ( $filterName === 'hideCasesWithNoUserEdits' ) {
				$filterName = 'hide_cases_with_no_user_edits';
			} elseif ( $filterName === 'hideCasesWithNoBlockedUsers' ) {
				$filterName = 'hide_cases_with_no_blocked_users';
			} elseif ( $filterName === 'lastUpdated' ) {
				if ( $filterValue === null ) {
					continue;
				}
				$filterName = 'last_updated';
			}
			$appliedFiltersForInstrumentation[$filterName] = $filterValue;
		}

		$pageLoadInstrumentationData = [
			'is_paging_results' => $pager->mOffset || $pager->mIsBackwards,
			'pager_limit' => $pager->mLimit,
			'is_in_detail_view' => $this->isInDetailedView,
			'applied_filters' => $appliedFiltersForInstrumentation,
			'performer' => [ 'id' => $this->getContext()->getUser()->getId() ],
		];
		if ( $this->isInDetailedView ) {
			$pageLoadInstrumentationData['case_id'] = $this->detailedViewCaseId;
		}
		$this->instrumentationClient->submitInteraction(
			$this->getContext(),
			'page_load',
			$pageLoadInstrumentationData
		);

		$output->addParserOutputContent(
			$pager->getFullOutput(),
			ParserOptions::newFromContext( $this->getContext() )
		);

		if ( $this->isInDetailedView ) {
			// Add additional content to the detail view after the single case row shown in the table
			$this->hookRunner->onCheckUserSuggestedInvestigationsOnDetailViewRender(
				$this->detailedViewCaseId,
				$output
			);
		}
	}

	/**
	 * Parses the subpage component to determine what view of suggested investigations we are on.
	 * Returns `false` if the subpage was parsed as invalid and an error page has been shown.
	 */
	protected function parseSubPage( string|null $subPage ): bool {
		$errorPageTitle = null;
		$errorPageText = null;

		$subPageParts = explode( '/', $subPage ?? '' );
		if ( $subPageParts[0] === 'detail' ) {
			$detailViewId = $this->suggestedInvestigationsCaseLookupService
				->getCaseIdForUrlIdentifier( $subPageParts[1] ?? '' );
			$this->isInDetailedView = $detailViewId !== false;
			if ( $this->isInDetailedView ) {
				$this->detailedViewCaseId = $detailViewId;
			} else {
				$errorPageTitle = 'checkuser-suggestedinvestigations-detail-view-not-found';
				$errorPageText = new MessageValue(
					'checkuser-suggestedinvestigations-detail-view-not-found-page-text',
					[ $subPageParts[1] ?? '' ]
				);
			}
		} elseif ( $subPageParts[0] !== '' ) {
			$errorPageTitle = 'checkuser-suggestedinvestigations-subpage-not-found';
			$errorPageText = 'checkuser-suggestedinvestigations-subpage-not-found-page-text';
		}

		// Display a 404 error page if requested by the code above
		if ( $errorPageTitle !== null && $errorPageText !== null ) {
			$output = $this->getOutput();
			$output->setStatusCode( 404 );
			$output->showErrorPage(
				$errorPageTitle,
				$errorPageText,
				[],
				SpecialPage::getTitleFor( 'SuggestedInvestigations' )
			);

			return false;
		}

		return true;
	}

	/**
	 * Adds the suggested investigations summary to the page, including the signals popover icon
	 * used to inform the user what the risk signals mean.
	 *
	 * @inheritDoc
	 */
	protected function outputHeader( $summaryMessageKey = '' ): void {
		if ( !$this->userOptionsLookup->getBoolOption(
			$this->getUser(),
			Preferences::SUGGESTED_INVESTIGATIONS_PRIVATE_DATA_WARNING_SEEN
		) ) {
			$this->getOutput()->addHTML( $this->messageRenderer->getUserDismissableWarning(
				$this->msg(
					'checkuser-suggestedinvestigations-private-data-warning'
				)->escaped(),
				'ext-checkuser-suggestedinvestigations-private-data-warning'
			) );
		}

		if ( $this->isInDetailedView ) {
			$descriptionHtml = $this->msg( 'checkuser-suggestedinvestigations-summary-detail-view' )
				->numParams( $this->detailedViewCaseId )
				->parse();
		} else {
			$descriptionHtml = $this->msg( 'checkuser-suggestedinvestigations-summary' )->parse();
		}
		$descriptionHtml = Html::rawElement(
			'span',
			[],
			$descriptionHtml
		);

		$popoverIcon = Html::element(
			'button',
			[
				'class' => 'ext-checkuser-suggestedinvestigations-signals-popover-icon',
				'title' => $this->msg(
					'checkuser-suggestedinvestigations-risk-signals-popover-open-label'
				)->text(),
				'aria-label' => $this->msg(
					'checkuser-suggestedinvestigations-risk-signals-popover-open-label'
				)->text(),
				'type' => 'button',
			]
		);
		$descriptionHtml .= Html::rawElement(
			'div',
			[ 'class' => 'ext-checkuser-suggestedinvestigations-signals-popover-icon-wrapper' ],
			$popoverIcon
		);

		$this->getOutput()->addHTML( Html::rawElement(
			'div',
			[ 'class' => 'ext-checkuser-suggestedinvestigations-description' ],
			$descriptionHtml
		) );

		$this->getOutput()->addJsConfigVars( 'wgCheckUserSuggestedInvestigationsSignals', $this->signals );
	}

	/** @inheritDoc */
	public function getDescription() {
		if ( $this->isInDetailedView ) {
			return $this->msg( 'checkuser-suggestedinvestigations-detail-view' )
				->numParams( $this->detailedViewCaseId );
		} else {
			return $this->msg( 'checkuser-suggestedinvestigations' );
		}
	}

	/**
	 * @codeCoverageIgnore Merely declarative
	 * @inheritDoc
	 */
	protected function getGroupName() {
		return 'users';
	}

	/**
	 * Returns an array of navigation links to be added to the subtitle area of the page.
	 * The syntax is [ message key => special page name ].
	 */
	private function getNavigationLinks(): array {
		$links = [
			'checkuser' => 'CheckUser',
			'checkuser-investigate' => 'Investigate',
		];

		if ( $this->getUser()->isAllowed( 'checkuser-log' ) ) {
			$links['checkuser-showlog'] = 'CheckUserLog';
		}

		if ( $this->isInDetailedView ) {
			$links['checkuser-suggestedinvestigations-back-to-main-page'] = 'SuggestedInvestigations';
		}

		return $links;
	}

	/**
	 * Adds navigation links to the subtitle area of the page.
	 */
	private function addNavigationLinks(): void {
		$links = $this->getNavigationLinks();

		if ( count( $links ) ) {
			$subtitle = '';
			foreach ( $links as $message => $page ) {
				$subtitle .= Html::rawElement(
					'span',
					[],
					$this->getLinkRenderer()->makeKnownLink(
						SpecialPage::getTitleFor( $page ),
						$this->msg( $message )->text()
					)
				);
			}

			$this->getOutput()->addSubtitle( Html::rawElement(
				'span',
				[ 'class' => 'mw-checkuser-links-no-parentheses' ],
				$subtitle
			) );
		}
	}
}
