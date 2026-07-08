<?php

namespace MediaWiki\Extension\AbuseFilter\View;

use LogicException;
use MediaWiki\Context\IContextSource;
use MediaWiki\Extension\AbuseFilter\AbuseFilterChangesList;
use MediaWiki\Extension\AbuseFilter\AbuseFilterPermissionManager;
use MediaWiki\Extension\AbuseFilter\AbuseLoggerFactory;
use MediaWiki\Extension\AbuseFilter\CentralDBNotAvailableException;
use MediaWiki\Extension\AbuseFilter\EditBox\EditBoxBuilderFactory;
use MediaWiki\Extension\AbuseFilter\Filter\MutableFilter;
use MediaWiki\Extension\AbuseFilter\FilterLookup;
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
use MediaWiki\RecentChanges\RecentChange;
use MediaWiki\RecentChanges\RecentChangeStore;
use MediaWiki\Title\Title;
use OOUI;
use Wikimedia\Rdbms\LBFactory;
use Wikimedia\Rdbms\ReadOnlyMode;

class AbuseFilterViewExamine extends AbuseFilterView {
	/**
	 * @var string The rules of the filter we're examining
	 */
	private $testFilter;

	public function __construct(
		private readonly LBFactory $lbFactory,
		AbuseFilterPermissionManager $afPermManager,
		private readonly FilterLookup $filterLookup,
		private readonly EditBoxBuilderFactory $boxBuilderFactory,
		private readonly VariablesBlobStore $varBlobStore,
		private readonly VariablesFormatter $variablesFormatter,
		private readonly VariablesManager $varManager,
		private readonly VariableGeneratorFactory $varGeneratorFactory,
		private readonly AbuseLoggerFactory $abuseLoggerFactory,
		private readonly RecentChangeStore $recentChangeStore,
		private readonly ReadOnlyMode $readOnlyMode,
		IContextSource $context,
		LinkRenderer $linkRenderer,
		string $basePageName,
		array $params
	) {
		parent::__construct( $afPermManager, $context, $linkRenderer, $basePageName, $params );
		$this->variablesFormatter->setMessageLocalizer( $context );
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
			$this->recentChangeStore,
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
		$rc = $this->recentChangeStore->getRecentChangeById( $rcid );
		$out = $this->getOutput();
		if ( !$rc ) {
			$out->addWikiMsg( 'abusefilter-examine-notfound' );
			return;
		}

		if ( !$this->afPermManager::hasRCEntryAccess( $rc, $this->getAuthority() ) ) {
			$out->addWikiMsg( 'abusefilter-log-details-hidden-implicit' );
			return;
		}

		$varGenerator = $this->varGeneratorFactory->newRCGenerator( $rc, $this->getUser() );
		$vars = $varGenerator->getVars();
		if ( !$vars ) {
			$out->addWikiMsg( 'abusefilter-examine-incompatible' );
			return;
		}

		// We compute all lazily loaded variables here, because we want to display all variables in ::showExaminer
		$varsArray = $this->varManager->dumpAllVars( $vars, true );

		// Filter out any protected variables that the user cannot see. Keep any protected variables that the user
		// can see. This will unconditionally generate log entries when viewing the examiner that contains protected
		// variables, as there is no way to specify the variables to select in the RecentChanges examiner.
		$protectedVariableValuesShown = [];
		foreach ( $this->afPermManager->getProtectedVariables() as $protectedVariable ) {
			if ( array_key_exists( $protectedVariable, $varsArray ) ) {
				// Try each variable at a time, as the user may be able to see some but not all of the
				// protected variables.
				$canViewProtectedVariable = $this->afPermManager
					->canViewProtectedVariables( $this->getAuthority(), [ $protectedVariable ] )->isGood();
				if ( !$canViewProtectedVariable ) {
					// Remove protected variables the user cannot see because they didn't specifically ask for them.
					unset( $varsArray[$protectedVariable] );
				} else {
					// Only log if there was a value set. This is to be consistent with
					// self::showExaminerForLogEntry
					if ( $varsArray[$protectedVariable] !== null ) {
						$protectedVariableValuesShown[] = $protectedVariable;
					}
				}
			}
		}
		$vars = VariableHolder::newFromArray( $varsArray );

		if ( count( $protectedVariableValuesShown ) ) {
			if ( $this->readOnlyMode->isReadOnly() ) {
				$out->addWikiMsg( 'readonlytext', $this->readOnlyMode->getReason() );
				return;
			}

			$logger = $this->abuseLoggerFactory->getProtectedVarsAccessLogger();
			$logger->logViewProtectedVariableValue(
				$this->getUser(),
				$varsArray['user_name'] ?? $varsArray['account_name'],
				$protectedVariableValuesShown
			);
		}

		$out->addJsConfigVars( [
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
				'afl_ip_hex',
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
			$filter = $this->filterLookup->getFilter( $row->afl_filter_id, $row->afl_global );
		} catch ( CentralDBNotAvailableException ) {
			// Conservatively assume that it's hidden and protected and suppressed, like in AbuseLogPager::doFormatRow
			$filter = MutableFilter::newDefault();
			$filter->setProtected( true );
			$filter->setHidden( true );
			$filter->setSuppressed( true );
		}
		if ( !$this->afPermManager->canSeeLogDetailsForFilter( $performer, $filter ) ) {
			$out->addWikiMsg( 'abusefilter-log-cannot-see-details' );
			return;
		}

		$visibility = SpecialAbuseLog::getEntryVisibilityForUser( $row, $performer, $this->afPermManager );
		if ( $visibility !== SpecialAbuseLog::VISIBILITY_VISIBLE ) {
			if ( $visibility === SpecialAbuseLog::VISIBILITY_HIDDEN ) {
				$msg = 'abusefilter-log-details-hidden';
			} elseif ( $visibility === SpecialAbuseLog::VISIBILITY_HIDDEN_IMPLICIT ) {
				$msg = 'abusefilter-log-details-hidden-implicit';
			} elseif ( $visibility === SpecialAbuseLog::VISIBILITY_SUPPRESSED ) {
				$msg = 'abusefilter-log-details-suppressed';
			} else {
				throw new LogicException( "Unexpected visibility $visibility" );
			}
			$out->addWikiMsg( $msg );
			return;
		}

		$vars = $this->varBlobStore->loadVarDump( $row );
		$varsArray = $this->varManager->dumpAllVars( $vars, $this->afPermManager->getProtectedVariables() );

		// Check that the user can see the protected variables that are being examined if the filter is protected.
		$userAuthority = $this->getAuthority();
		if ( $filter->isProtected() ) {
			$permStatus = $this->afPermManager->canViewProtectedVariables(
				$userAuthority, array_keys( $vars->getVars() )
			);
			if ( !$permStatus->isGood() ) {
				if ( $permStatus->getPermission() ) {
					$out->addWikiMsg(
						$this->msg(
							'abusefilter-examine-error-protected-due-to-permission',
							$this->msg( "action-{$permStatus->getPermission()}" )->plain()
						)
					);
					return;
				}

				// Add any messages in the status after a generic error message.
				$additional = '';
				foreach ( $permStatus->getMessages() as $message ) {
					$additional .= $this->msg( $message )->parseAsBlock();
				}

				$out->addWikiMsg(
					$this->msg( 'abusefilter-examine-error-protected' )->rawParams( $additional )
				);
				return;
			}

			$protectedVariableValuesShown = [];
			foreach ( $this->afPermManager->getProtectedVariables() as $protectedVariable ) {
				if ( isset( $varsArray[$protectedVariable] ) ) {
					$protectedVariableValuesShown[] = $protectedVariable;
				}
			}

			if ( count( $protectedVariableValuesShown ) ) {
				if ( $this->readOnlyMode->isReadOnly() ) {
					$out->addWikiMsg( 'readonlytext', $this->readOnlyMode->getReason() );
					return;
				}

				$logger = $this->abuseLoggerFactory->getProtectedVarsAccessLogger();
				$logger->logViewProtectedVariableValue(
					$userAuthority->getUser(),
					$varsArray['user_name'] ?? $varsArray['account_name'],
					$protectedVariableValuesShown
				);
			}
		}

		$out->addJsConfigVars( [
			'abuseFilterExamine' => [ 'type' => 'log', 'id' => $logid ]
		] );
		$this->showExaminer( $vars );
	}

	public function showExaminer( VariableHolder $vars ) {
		$output = $this->getOutput();
		$output->enableOOUI();

		$html = '';

		$output->addModules( 'ext.abuseFilter.examine' );
		$output->addJsConfigVars( [
			'wgAbuseFilterVariables' => $this->varManager->dumpAllVars( $vars, true ),
		] );

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
