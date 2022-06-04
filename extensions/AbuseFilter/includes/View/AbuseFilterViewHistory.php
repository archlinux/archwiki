<?php

namespace MediaWiki\Extension\AbuseFilter\View;

use HTMLForm;
use IContextSource;
use Linker;
use MediaWiki\Extension\AbuseFilter\AbuseFilterPermissionManager;
use MediaWiki\Extension\AbuseFilter\Filter\FilterNotFoundException;
use MediaWiki\Extension\AbuseFilter\FilterLookup;
use MediaWiki\Extension\AbuseFilter\Pager\AbuseFilterHistoryPager;
use MediaWiki\Extension\AbuseFilter\SpecsFormatter;
use MediaWiki\Linker\LinkRenderer;
use MediaWiki\User\UserNameUtils;
use OOUI;

class AbuseFilterViewHistory extends AbuseFilterView {

	/** @var int|null */
	private $filter;

	/** @var FilterLookup */
	private $filterLookup;

	/** @var SpecsFormatter */
	private $specsFormatter;

	/** @var UserNameUtils */
	private $userNameUtils;

	/**
	 * @param AbuseFilterPermissionManager $afPermManager
	 * @param FilterLookup $filterLookup
	 * @param SpecsFormatter $specsFormatter
	 * @param UserNameUtils $userNameUtils
	 * @param IContextSource $context
	 * @param LinkRenderer $linkRenderer
	 * @param string $basePageName
	 * @param array $params
	 */
	public function __construct(
		AbuseFilterPermissionManager $afPermManager,
		FilterLookup $filterLookup,
		SpecsFormatter $specsFormatter,
		UserNameUtils $userNameUtils,
		IContextSource $context,
		LinkRenderer $linkRenderer,
		string $basePageName,
		array $params
	) {
		parent::__construct( $afPermManager, $context, $linkRenderer, $basePageName, $params );
		$this->filterLookup = $filterLookup;
		$this->specsFormatter = $specsFormatter;
		$this->specsFormatter->setMessageLocalizer( $context );
		$this->filter = $this->mParams['filter'] ?? null;
		$this->userNameUtils = $userNameUtils;
	}

	/**
	 * Shows the page
	 */
	public function show() {
		$out = $this->getOutput();
		$out->enableOOUI();
		$filter = $this->getRequest()->getIntOrNull( 'filter' ) ?: $this->filter;
		$canViewPrivate = $this->afPermManager->canViewPrivateFilters( $this->getUser() );

		if ( $filter ) {
			try {
				$filterObj = $this->filterLookup->getFilter( $filter, false );
			} catch ( FilterNotFoundException $_ ) {
				$filter = null;
			}
			if ( isset( $filterObj ) && $filterObj->isHidden() && !$canViewPrivate ) {
				$out->addWikiMsg( 'abusefilter-history-error-hidden' );
				return;
			}
		}

		if ( $filter ) {
			$out->setPageTitle( $this->msg( 'abusefilter-history' )->numParams( $filter ) );
		} else {
			$out->setPageTitle( $this->msg( 'abusefilter-filter-log' ) );
		}

		// Useful links
		$links = [];
		if ( $filter ) {
			$links['abusefilter-history-backedit'] = $this->getTitle( $filter )->getFullURL();
		}

		foreach ( $links as $msg => $title ) {
			$links[$msg] =
				new OOUI\ButtonWidget( [
					'label' => $this->msg( $msg )->text(),
					'href' => $title
				] );
		}

		$backlinks =
			new OOUI\HorizontalLayout( [
				'items' => array_values( $links )
			] );
		$out->addHTML( $backlinks );

		// For user
		$user = $this->userNameUtils->getCanonical(
			$this->getRequest()->getText( 'user' ),
			UserNameUtils::RIGOR_VALID
		);
		if ( $user !== false ) {
			$out->addSubtitle(
				$this->msg(
					'abusefilter-history-foruser',
					// We don't really need to get a user ID
					Linker::userLink( 1, $user ),
					// For GENDER
					$user
				)->text()
			);
		} else {
			$user = null;
		}

		$formDescriptor = [
			'user' => [
				'type' => 'user',
				'name' => 'user',
				'default' => $user,
				'size' => '45',
				'label-message' => 'abusefilter-history-select-user'
			],
			'filter' => [
				'type' => 'int',
				'name' => 'filter',
				'default' => $filter ?: '',
				'size' => '45',
				'label-message' => 'abusefilter-history-select-filter'
			],
		];

		$htmlForm = HTMLForm::factory( 'ooui', $formDescriptor, $this->getContext() );
		$htmlForm->setSubmitTextMsg( 'abusefilter-history-select-submit' )
			->setWrapperLegendMsg( 'abusefilter-history-select-legend' )
			->setAction( $this->getTitle( 'history' )->getLocalURL() )
			->setMethod( 'get' )
			->prepareForm()
			->displayForm( false );

		$pager = new AbuseFilterHistoryPager(
			$this->getContext(),
			$this->linkRenderer,
			$this->filterLookup,
			$this->specsFormatter,
			$filter,
			$user,
			$canViewPrivate
		);

		$out->addParserOutputContent( $pager->getFullOutput() );
	}
}
