<?php

namespace MediaWiki\Extension\AbuseFilter\View;

use ChangesList;
use HTMLForm;
use IContextSource;
use LogicException;
use MediaWiki\Extension\AbuseFilter\AbuseFilterChangesList;
use MediaWiki\Extension\AbuseFilter\AbuseFilterPermissionManager;
use MediaWiki\Extension\AbuseFilter\CentralDBNotAvailableException;
use MediaWiki\Extension\AbuseFilter\EditBox\EditBoxBuilderFactory;
use MediaWiki\Extension\AbuseFilter\FilterLookup;
use MediaWiki\Extension\AbuseFilter\Pager\AbuseFilterExaminePager;
use MediaWiki\Extension\AbuseFilter\Special\SpecialAbuseLog;
use MediaWiki\Extension\AbuseFilter\VariableGenerator\VariableGeneratorFactory;
use MediaWiki\Extension\AbuseFilter\Variables\VariableHolder;
use MediaWiki\Extension\AbuseFilter\Variables\VariablesBlobStore;
use MediaWiki\Extension\AbuseFilter\Variables\VariablesFormatter;
use MediaWiki\Extension\AbuseFilter\Variables\VariablesManager;
use MediaWiki\Linker\LinkRenderer;
use MediaWiki\Revision\RevisionRecord;
use OOUI;
use RecentChange;
use Title;
use Xml;

class AbuseFilterViewExamine extends AbuseFilterView {
	/**
	 * @var string The user whose entries we're examinating
	 */
	public $mSearchUser;
	/**
	 * @var string The start time of the search period
	 */
	public $mSearchPeriodStart;
	/**
	 * @var string The end time of the search period
	 */
	public $mSearchPeriodEnd;
	/**
	 * @var string The ID of the filter we're examinating
	 */
	public $mTestFilter;
	/**
	 * @var FilterLookup
	 */
	private $filterLookup;
	/**
	 * @var EditBoxBuilderFactory
	 */
	private $boxBuilderFactory;
	/**
	 * @var VariablesBlobStore
	 */
	private $varBlobStore;
	/**
	 * @var VariablesFormatter
	 */
	private $variablesFormatter;
	/**
	 * @var VariablesManager
	 */
	private $varManager;
	/**
	 * @var VariableGeneratorFactory
	 */
	private $varGeneratorFactory;

	/**
	 * @param AbuseFilterPermissionManager $afPermManager
	 * @param FilterLookup $filterLookup
	 * @param EditBoxBuilderFactory $boxBuilderFactory
	 * @param VariablesBlobStore $varBlobStore
	 * @param VariablesFormatter $variablesFormatter
	 * @param VariablesManager $varManager
	 * @param VariableGeneratorFactory $varGeneratorFactory
	 * @param IContextSource $context
	 * @param LinkRenderer $linkRenderer
	 * @param string $basePageName
	 * @param array $params
	 */
	public function __construct(
		AbuseFilterPermissionManager $afPermManager,
		FilterLookup $filterLookup,
		EditBoxBuilderFactory $boxBuilderFactory,
		VariablesBlobStore $varBlobStore,
		VariablesFormatter $variablesFormatter,
		VariablesManager $varManager,
		VariableGeneratorFactory $varGeneratorFactory,
		IContextSource $context,
		LinkRenderer $linkRenderer,
		string $basePageName,
		array $params
	) {
		parent::__construct( $afPermManager, $context, $linkRenderer, $basePageName, $params );
		$this->filterLookup = $filterLookup;
		$this->boxBuilderFactory = $boxBuilderFactory;
		$this->varBlobStore = $varBlobStore;
		$this->variablesFormatter = $variablesFormatter;
		$this->variablesFormatter->setMessageLocalizer( $context );
		$this->varManager = $varManager;
		$this->varGeneratorFactory = $varGeneratorFactory;
	}

	/**
	 * Shows the page
	 */
	public function show() {
		$out = $this->getOutput();
		$out->setPageTitle( $this->msg( 'abusefilter-examine' ) );
		$out->addHelpLink( 'Extension:AbuseFilter/Rules format' );
		if ( $this->afPermManager->canUseTestTools( $this->getUser() ) ) {
			$out->addWikiMsg( 'abusefilter-examine-intro' );
		} else {
			$out->addWikiMsg( 'abusefilter-examine-intro-examine-only' );
		}

		$this->loadParameters();

		// Check if we've got a subpage
		if ( count( $this->mParams ) > 1 && is_numeric( $this->mParams[1] ) ) {
			$this->showExaminerForRC( $this->mParams[1] );
		} elseif ( count( $this->mParams ) > 2
			&& $this->mParams[1] === 'log'
			&& is_numeric( $this->mParams[2] )
		) {
			$this->showExaminerForLogEntry( $this->mParams[2] );
		} else {
			$this->showSearch();
		}
	}

	/**
	 * Shows the search form
	 */
	public function showSearch() {
		$RCMaxAge = $this->getConfig()->get( 'RCMaxAge' );
		$min = wfTimestamp( TS_ISO_8601, time() - $RCMaxAge );
		$max = wfTimestampNow();
		$formDescriptor = [
			'SearchUser' => [
				'label-message' => 'abusefilter-test-user',
				'type' => 'user',
				'ipallowed' => true,
				'default' => $this->mSearchUser,
			],
			'SearchPeriodStart' => [
				'label-message' => 'abusefilter-test-period-start',
				'type' => 'datetime',
				'default' => $this->mSearchPeriodStart,
				'min' => $min,
				'max' => $max,
			],
			'SearchPeriodEnd' => [
				'label-message' => 'abusefilter-test-period-end',
				'type' => 'datetime',
				'default' => $this->mSearchPeriodEnd,
				'min' => $min,
				'max' => $max,
			],
		];
		$htmlForm = HTMLForm::factory( 'ooui', $formDescriptor, $this->getContext() );
		$htmlForm->setWrapperLegendMsg( 'abusefilter-examine-legend' )
			->setSubmitTextMsg( 'abusefilter-examine-submit' )
			->setFormIdentifier( 'examine-select-date' )
			->setSubmitCallback( [ $this, 'showResults' ] )
			->showAlways();
	}

	/**
	 * Show search results, called as submit callback by HTMLForm
	 * @param array $formData
	 * @param HTMLForm $form
	 * @return bool
	 */
	public function showResults( array $formData, HTMLForm $form ): bool {
		$changesList = new AbuseFilterChangesList( $this->getContext(), $this->mTestFilter );
		$pager = new AbuseFilterExaminePager( $this, $changesList );

		$output = $changesList->beginRecentChangesList()
			. $pager->getNavigationBar()
			. $pager->getBody()
			. $pager->getNavigationBar()
			. $changesList->endRecentChangesList();

		$form->addPostText( $output );
		return true;
	}

	/**
	 * @param int $rcid
	 */
	public function showExaminerForRC( $rcid ) {
		// Get data
		$rc = RecentChange::newFromId( $rcid );
		$out = $this->getOutput();
		if ( !$rc ) {
			$out->addWikiMsg( 'abusefilter-examine-notfound' );
			return;
		}

		if ( !ChangesList::userCan( $rc, RevisionRecord::SUPPRESSED_ALL ) ) {
			$out->addWikiMsg( 'abusefilter-log-details-hidden-implicit' );
			return;
		}

		$varGenerator = $this->varGeneratorFactory->newRCGenerator( $rc, $this->getUser() );
		$vars = $varGenerator->getVars() ?: new VariableHolder();
		$out->addJsConfigVars( [
			'wgAbuseFilterVariables' => $this->varManager->dumpAllVars( $vars, true ),
			'abuseFilterExamine' => [ 'type' => 'rc', 'id' => $rcid ]
		] );

		$this->showExaminer( $vars );
	}

	/**
	 * @param int $logid
	 */
	public function showExaminerForLogEntry( $logid ) {
		// Get data
		$dbr = wfGetDB( DB_REPLICA );
		$user = $this->getUser();
		$out = $this->getOutput();

		$row = $dbr->selectRow(
			'abuse_filter_log',
			[
				'afl_deleted',
				'afl_var_dump',
				'afl_rev_id',
				'afl_filter_id',
				'afl_global'
			],
			[ 'afl_id' => $logid ],
			__METHOD__
		);

		if ( !$row ) {
			$out->addWikiMsg( 'abusefilter-examine-notfound' );
			return;
		}

		try {
			$isHidden = $this->filterLookup->getFilter( $row->afl_filter_id, $row->afl_global )->isHidden();
		} catch ( CentralDBNotAvailableException $_ ) {
			// Conservatively assume that it's hidden, like in SpecialAbuseLog
			$isHidden = true;
		}
		if ( !$this->afPermManager->canSeeLogDetailsForFilter( $user, $isHidden ) ) {
			$out->addWikiMsg( 'abusefilter-log-cannot-see-details' );
			return;
		}

		$visibility = SpecialAbuseLog::getEntryVisibilityForUser( $row, $user, $this->afPermManager );
		if ( $visibility !== SpecialAbuseLog::VISIBILITY_VISIBLE ) {
			if ( $visibility === SpecialAbuseLog::VISIBILITY_HIDDEN ) {
				$msg = 'abusefilter-log-details-hidden';
			} elseif ( $visibility === SpecialAbuseLog::VISIBILITY_HIDDEN_IMPLICIT ) {
				$msg = 'abusefilter-log-details-hidden-implicit';
			} else {
				throw new LogicException( "Unexpected visibility $visibility" );
			}
			$out->addWikiMsg( $msg );
			return;
		}

		$vars = $this->varBlobStore->loadVarDump( $row->afl_var_dump );
		$out->addJsConfigVars( [
			'wgAbuseFilterVariables' => $this->varManager->dumpAllVars( $vars, true ),
			'abuseFilterExamine' => [ 'type' => 'log', 'id' => $logid ]
		] );
		$this->showExaminer( $vars );
	}

	/**
	 * @param VariableHolder|null $vars
	 */
	public function showExaminer( ?VariableHolder $vars ) {
		$output = $this->getOutput();
		$output->enableOOUI();

		if ( !$vars ) {
			$output->addWikiMsg( 'abusefilter-examine-incompatible' );
			return;
		}

		$html = '';

		$output->addModules( 'ext.abuseFilter.examine' );

		// Add test bit
		if ( $this->afPermManager->canUseTestTools( $this->getUser() ) ) {
			$boxBuilder = $this->boxBuilderFactory->newEditBoxBuilder(
				$this,
				$this->getUser(),
				$output
			);

			$tester = Xml::tags( 'h2', null, $this->msg( 'abusefilter-examine-test' )->parse() );
			$tester .= $boxBuilder->buildEditBox( $this->mTestFilter, false, false, false );
			$tester .= $this->buildFilterLoader();
			$html .= Xml::tags( 'div', [ 'id' => 'mw-abusefilter-examine-editor' ], $tester );
			$html .= Xml::tags( 'p',
				null,
				new OOUI\ButtonInputWidget(
					[
						'label' => $this->msg( 'abusefilter-examine-test-button' )->text(),
						'id' => 'mw-abusefilter-examine-test',
						'flags' => [ 'primary', 'progressive' ]
					]
				) .
				Xml::element( 'div',
					[
						'id' => 'mw-abusefilter-syntaxresult',
						'style' => 'display: none;'
					], '&#160;'
				)
			);
		}

		// Variable dump
		$html .= Xml::tags(
			'h2',
			null,
			$this->msg( 'abusefilter-examine-vars' )->parse()
		);
		$html .= $this->variablesFormatter->buildVarDumpTable( $vars );

		$output->addHTML( $html );
	}

	/**
	 * Loads parameters from request
	 */
	public function loadParameters() {
		$request = $this->getRequest();
		$this->mSearchPeriodStart = $request->getText( 'wpSearchPeriodStart' );
		$this->mSearchPeriodEnd = $request->getText( 'wpSearchPeriodEnd' );
		$this->mTestFilter = $request->getText( 'testfilter' );

		// Normalise username
		$searchUsername = $request->getText( 'wpSearchUser' );
		$userTitle = Title::newFromText( $searchUsername, NS_USER );
		$this->mSearchUser = $userTitle ? $userTitle->getText() : '';
	}
}
