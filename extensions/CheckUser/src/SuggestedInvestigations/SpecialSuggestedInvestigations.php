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

namespace MediaWiki\CheckUser\SuggestedInvestigations;

use MediaWiki\CheckUser\Hook\HookRunner;
use MediaWiki\CheckUser\SuggestedInvestigations\Instrumentation\SuggestedInvestigationsInstrumentationClient;
use MediaWiki\Html\Html;
use MediaWiki\Linker\UserLinkRenderer;
use MediaWiki\Parser\ParserOptions;
use MediaWiki\SpecialPage\SpecialPage;
use MediaWiki\User\UserFactory;
use Wikimedia\Rdbms\IConnectionProvider;

class SpecialSuggestedInvestigations extends SpecialPage {

	public function __construct(
		private readonly IConnectionProvider $connectionProvider,
		private readonly UserLinkRenderer $userLinkRenderer,
		private readonly UserFactory $userFactory,
		private readonly HookRunner $hookRunner,
		private readonly SuggestedInvestigationsInstrumentationClient $instrumentationClient,
	) {
		parent::__construct( 'SuggestedInvestigations', 'checkuser' );
	}

	/** @inheritDoc */
	public function execute( $subPage ) {
		parent::execute( $subPage );
		$this->addNavigationLinks();

		$this->addHelpLink( 'Help:Extension:CheckUser/Suggested investigations' );

		$output = $this->getOutput();
		$output->addHtml( '<div id="ext-suggestedinvestigations-change-status-app"></div>' );
		$output->addHTML( '<div id="ext-suggestedinvestigations-signals-popover-app"></div>' );
		$output->addModules( 'ext.checkUser.suggestedInvestigations' );
		$output->addModuleStyles( 'ext.checkUser.styles' );

		$pager = new SuggestedInvestigationsTablePager(
			$this->connectionProvider,
			$this->userLinkRenderer,
			$this->userFactory,
			$this->getContext()
		);

		$this->instrumentationClient->submitInteraction(
			$this->getContext(),
			'page_load',
			[
				'action_context' => json_encode( [
					'is_paging_results' => $pager->mOffset || $pager->mIsBackwards,
					'limit' => $pager->mLimit,
				] ),
			]
		);

		$output->addParserOutputContent(
			$pager->getFullOutput(),
			ParserOptions::newFromContext( $this->getContext() )
		);
	}

	/**
	 * Adds the suggested investigations summary to the page, including the signals popover icon
	 * used to inform the user what the risk signals mean.
	 *
	 * @inheritDoc
	 */
	protected function outputHeader( $summaryMessageKey = '' ): void {
		$descriptionHtml = Html::rawElement(
			'span', [], $this->msg( 'checkuser-suggestedinvestigations-summary' )->parse()
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
			'div', [ 'class' => 'ext-checkuser-suggestedinvestigations-description' ], $descriptionHtml
		) );

		$signals = [];
		$this->hookRunner->onCheckUserSuggestedInvestigationsGetSignals( $signals );
		$this->getOutput()->addJsConfigVars( 'wgCheckUserSuggestedInvestigationsSignals', $signals );
	}

	/** @inheritDoc */
	public function getDescription() {
		return $this->msg( 'checkuser-suggestedinvestigations' );
	}

	/** @inheritDoc */
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
