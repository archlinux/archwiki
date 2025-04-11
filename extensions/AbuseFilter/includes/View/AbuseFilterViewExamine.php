<?php

namespace MediaWiki\Extension\AbuseFilter\View;

use ChangesList;
use LogicException;
use MediaWiki\Context\IContextSource;
use MediaWiki\Extension\AbuseFilter\AbuseFilterChangesList;
use MediaWiki\Extension\AbuseFilter\AbuseFilterPermissionManager;
use MediaWiki\Extension\AbuseFilter\AbuseLoggerFactory;
use MediaWiki\Extension\AbuseFilter\CentralDBNotAvailableException;
use MediaWiki\Extension\AbuseFilter\EditBox\EditBoxBuilderFactory;
use MediaWiki\Extension\AbuseFilter\Filter\Flags;
use MediaWiki\Extension\AbuseFilter\FilterLookup;
use MediaWiki\Extension\AbuseFilter\FilterUtils;
use MediaWiki\Extension\AbuseFilter\Pager\AbuseFilterExaminePager;
use MediaWiki\Extension\AbuseFilter\Special\SpecialAbuseLog;
use MediaWiki\Extension\AbuseFilter\VariableGenerator\VariableGeneratorFactory;
use MediaWiki\Extension\AbuseFilter\Variables\VariableHolder;
use MediaWiki\Extension\AbuseFilter\Variables\VariablesBlobStore;
use MediaWiki\Extension\AbuseFilter\Variables\VariablesFormatter;
use MediaWiki\Extension\AbuseFilter\Variables\VariablesManager;
use MediaWiki\Html\Html;
use MediaWiki\HTMLForm\HTMLForm;
use MediaWiki\Linker\LinkRenderer;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Title\Title;
use OOUI;
use RecentChange;
use Wikimedia\Rdbms\LBFactory;

class AbuseFilterViewExamine extends AbuseFilterView {
	/**
	 * @var string The rules of the filter we're examining
	 */
	private $testFilter;
	/**
	 * @var LBFactory
	 */
	private $lbFactory;
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

	private AbuseLoggerFactory $abuseLoggerFactory;

	/**
	 * @param LBFactory $lbFactory
	 * @param AbuseFilterPermissionManager $afPermManager
	 * @param FilterLookup $filterLookup
	 * @param EditBoxBuilderFactory $boxBuilderFactory
	 * @param VariablesBlobStore $varBlobStore
	 * @param VariablesFormatter $variablesFormatter
	 * @param VariablesManager $varManager
	 * @param VariableGeneratorFactory $varGeneratorFactory
	 * @param AbuseLoggerFactory $abuseLoggerFactory
	 * @param IContextSource $context
	 * @param LinkRenderer $linkRenderer
	 * @param string $basePageName
	 * @param array $params
	 */
	public function __construct(
		LBFactory $lbFactory,
		AbuseFilterPermissionManager $afPermManager,
		FilterLookup $filterLookup,
		EditBoxBuilderFactory $boxBuilderFactory,
		VariablesBlobStore $varBlobStore,
		VariablesFormatter $variablesFormatter,
		VariablesManager $varManager,
		VariableGeneratorFactory $varGeneratorFactory,
		AbuseLoggerFactory $abuseLoggerFactory,
		IContextSource $context,
		LinkRenderer $linkRenderer,
		string $basePageName,
		array $params
	) {
		parent::__construct( $afPermManager, $context, $linkRenderer, $basePageName, $params );
		$this->lbFactory = $lbFactory;
		$this->filterLookup = $filterLookup;
		$this->boxBuilderFactory = $boxBuilderFactory;
		$this->varBlobStore = $varBlobStore;
		$this->variablesFormatter = $variablesFormatter;
		$this->variablesFormatter->setMessageLocalizer( $context );
		$this->varManager = $varManager;
		$this->varGeneratorFactory = $varGeneratorFactory;
		$this->abuseLoggerFactory = $abuseLoggerFactory;
	}

	/**
	 * Shows the page
	 */
	public function show() {
		$out = $this->getOutput();
		$out->setPageTitleMsg( $this->msg( 'abusefilter-examine' ) );
		$out->addHelpLink( 'Extension:AbuseFilter/Rules format' );
		if ( $this->afPermManager->canUseTestTools( $this->getAuthority() ) ) {
			$out->addWikiMsg( 'abusefilter-examine-intro' );
		} else {
			$out->addWikiMsg( 'abusefilter-examine-intro-examine-only' );
		}

		$this->testFilter = $this->getRequest()->getText( 'testfilter' );

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
			],
			'SearchPeriodStart' => [
				'label-message' => 'abusefilter-test-period-start',
				'type' => 'datetime',
				'min' => $min,
				'max' => $max,
			],
			'SearchPeriodEnd' => [
				'label-message' => 'abusefilter-test-period-end',
				'type' => 'datetime',
				'min' => $min,
				'max' => $max,
			],
		];
		HTMLForm::factory( 'ooui', $formDescriptor, $this->getContext() )
			->addHiddenField( 'testfilter', $this->testFilter )
			->setWrapperLegendMsg( 'abusefilter-examine-legend' )
			->setSubmitTextMsg( 'abusefilter-examine-submit' )
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
		$changesList = new AbuseFilterChangesList( $this->getContext(), $this->testFilter );

		$dbr = $this->lbFactory->getReplicaDatabase();
		$conds = $this->buildVisibilityConditions( $dbr, $this->getAuthority() );
		$conds[] = $this->buildTestConditions( $dbr );

		// Normalise username
		$userTitle = Title::newFromText( $formData['SearchUser'], NS_USER );
		$userName = $userTitle ? $userTitle->getText() : '';

		if ( $userName !== '' ) {
			$rcQuery = RecentChange::getQueryInfo();
			$conds[$rcQuery['fields']['rc_user_text']] = $userName;
		}

		$startTS = strtotime( $formData['SearchPeriodStart'] );
		if ( $startTS ) {
			$conds[] = $dbr->expr( 'rc_timestamp', '>=', $dbr->timestamp( $startTS ) );
		}
		$endTS = strtotime( $formData['SearchPeriodEnd'] );
		if ( $endTS ) {
			$conds[] = $dbr->expr( 'rc_timestamp', '<=', $dbr->timestamp( $endTS ) );
		}
		$pager = new AbuseFilterExaminePager(
			$changesList,
			$this->linkRenderer,
			$dbr,
			$this->getTitle( 'examine' ),
			$conds
		);

		$output = $changesList->beginRecentChangesList()
			. $pager->getNavigationBar()
			. $pager->getBody()
			. $pager->getNavigationBar()
			. $changesList->endRecentChangesList();

		$form->addPostHtml( $output );
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
		$dbr = $this->lbFactory->getReplicaDatabase();
		$performer = $this->getAuthority();
		$out = $this->getOutput();

		$row = $dbr->newSelectQueryBuilder()
			->select( [
				'afl_deleted',
				'afl_ip',
				'afl_var_dump',
				'afl_rev_id',
				'afl_filter_id',
				'afl_global'
			] )
			->from( 'abuse_filter_log' )
			->where( [ 'afl_id' => $logid ] )
			->caller( __METHOD__ )
			->fetchRow();

		if ( !$row ) {
			$out->addWikiMsg( 'abusefilter-examine-notfound' );
			return;
		}

		try {
			$privacyLevel = $this->filterLookup->getFilter( $row->afl_filter_id, $row->afl_global )->getPrivacyLevel();
		} catch ( CentralDBNotAvailableException $_ ) {
			// Conservatively assume that it's hidden and protected, like in AbuseLogPager::doFormatRow
			$privacyLevel = Flags::FILTER_HIDDEN | Flags::FILTER_USES_PROTECTED_VARS;
		}
		if ( !$this->afPermManager->canSeeLogDetailsForFilter( $performer, $privacyLevel ) ) {
			$out->addWikiMsg( 'abusefilter-log-cannot-see-details' );
			return;
		}

		$visibility = SpecialAbuseLog::getEntryVisibilityForUser( $row, $performer, $this->afPermManager );
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

		$shouldLogProtectedVarAccess = false;

		// Logs that reveal the values of protected variables are gated behind:
		// 1. the `abusefilter-access-protected-vars` right
		// 2. agreement to the `abusefilter-protected-vars-view-agreement` preference
		$userAuthority = $this->getAuthority();
		$canViewProtectedVars = $this->afPermManager->canViewProtectedVariableValues( $userAuthority );
		if ( FilterUtils::isProtected( $privacyLevel ) ) {
			if ( !$canViewProtectedVars ) {
				$out->addWikiMsg( 'abusefilter-examine-protected-vars-permission' );
				return;
			} else {
				$shouldLogProtectedVarAccess = true;
			}
		}

		// If a non-protected filter and a protected filter have overlapping conditions,
		// it's possible for a hit to contain a protected variable and for that variable
		// to be dumped and displayed on a detail page that wouldn't be considered
		// protected (because it caught on the public filter).
		// We shouldn't block access to the details of an otherwise public filter hit so
		// instead only check for access to the protected variables and redact them if the
		// user shouldn't see them.
		$vars = $this->varBlobStore->loadVarDump( $row );
		$varsArray = $this->varManager->dumpAllVars( $vars, true );

		foreach ( $this->afPermManager->getProtectedVariables() as $protectedVariable ) {
			if ( isset( $varsArray[$protectedVariable] ) ) {
				if ( !$canViewProtectedVars ) {
					$varsArray[$protectedVariable] = '';
				} else {
					// Protected variable in protected filters logs access in the general permission check
					// Log access to non-protected filters that happen to expose protected variables here
					if ( !FilterUtils::isProtected( $privacyLevel ) ) {
						$shouldLogProtectedVarAccess = true;
					}
				}
			}
		}
		$vars = VariableHolder::newFromArray( $varsArray );

		if ( $shouldLogProtectedVarAccess ) {
			$logger = $this->abuseLoggerFactory->getProtectedVarsAccessLogger();
			$logger->logViewProtectedVariableValue(
				$userAuthority->getUser(),
				$varsArray['user_name'] ?? $varsArray['accountname']
			);
		}

		$out->addJsConfigVars( [
			'wgAbuseFilterVariables' => $varsArray,
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
		if ( $this->afPermManager->canUseTestTools( $this->getAuthority() ) ) {
			$boxBuilder = $this->boxBuilderFactory->newEditBoxBuilder(
				$this,
				$this->getAuthority(),
				$output
			);

			$tester = Html::rawElement( 'h2', [], $this->msg( 'abusefilter-examine-test' )->parse() );
			$tester .= $boxBuilder->buildEditBox( $this->testFilter, false, false, false );
			$tester .= $this->buildFilterLoader();
			$html .= Html::rawElement( 'div', [ 'id' => 'mw-abusefilter-examine-editor' ], $tester );
			$html .= Html::rawElement( 'p',
				[],
				new OOUI\ButtonInputWidget(
					[
						'label' => $this->msg( 'abusefilter-examine-test-button' )->text(),
						'id' => 'mw-abusefilter-examine-test',
						'flags' => [ 'primary', 'progressive' ]
					]
				) .
				Html::element( 'div',
					[
						'id' => 'mw-abusefilter-syntaxresult',
						'style' => 'display: none;'
					]
				)
			);
		}

		// Variable dump
		$html .= Html::rawElement(
			'h2',
			[],
			$this->msg( 'abusefilter-examine-vars' )->parse()
		);
		$html .= $this->variablesFormatter->buildVarDumpTable( $vars );

		$output->addHTML( $html );
	}

}
