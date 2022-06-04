<?php

namespace MediaWiki\Extension\AbuseFilter\View;

use HTMLForm;
use IContextSource;
use LogEventsList;
use LogPage;
use MediaWiki\Extension\AbuseFilter\AbuseFilterChangesList;
use MediaWiki\Extension\AbuseFilter\AbuseFilterPermissionManager;
use MediaWiki\Extension\AbuseFilter\EditBox\EditBoxBuilderFactory;
use MediaWiki\Extension\AbuseFilter\EditBox\EditBoxField;
use MediaWiki\Extension\AbuseFilter\Parser\RuleCheckerFactory;
use MediaWiki\Extension\AbuseFilter\VariableGenerator\VariableGeneratorFactory;
use MediaWiki\Linker\LinkRenderer;
use MediaWiki\Revision\RevisionRecord;
use RecentChange;
use Title;

class AbuseFilterViewTestBatch extends AbuseFilterView {
	/**
	 * @var int The limit of changes to test, hard coded for now
	 */
	protected static $mChangeLimit = 100;

	/**
	 * @var bool Whether to show changes that don't trigger the specified pattern
	 */
	public $mShowNegative;
	/**
	 * @var string The start time of the lookup period
	 */
	public $mTestPeriodStart;
	/**
	 * @var string The end time of the lookup period
	 */
	public $mTestPeriodEnd;
	/**
	 * @var string The page of which edits we're interested in
	 */
	public $mTestPage;
	/**
	 * @var string The user whose actions we want to test
	 */
	public $mTestUser;
	/**
	 * @var bool Whether to exclude bot edits
	 */
	public $mExcludeBots;
	/**
	 * @var string The action (performed by the user) we want to search for
	 */
	public $mTestAction;
	/**
	 * @var string The text of the rule to test changes against
	 */
	private $testPattern;
	/**
	 * @var EditBoxBuilderFactory
	 */
	private $boxBuilderFactory;
	/**
	 * @var RuleCheckerFactory
	 */
	private $ruleCheckerFactory;
	/**
	 * @var VariableGeneratorFactory
	 */
	private $varGeneratorFactory;

	/**
	 * @param AbuseFilterPermissionManager $afPermManager
	 * @param EditBoxBuilderFactory $boxBuilderFactory
	 * @param RuleCheckerFactory $ruleCheckerFactory
	 * @param VariableGeneratorFactory $varGeneratorFactory
	 * @param IContextSource $context
	 * @param LinkRenderer $linkRenderer
	 * @param string $basePageName
	 * @param array $params
	 */
	public function __construct(
		AbuseFilterPermissionManager $afPermManager,
		EditBoxBuilderFactory $boxBuilderFactory,
		RuleCheckerFactory $ruleCheckerFactory,
		VariableGeneratorFactory $varGeneratorFactory,
		IContextSource $context,
		LinkRenderer $linkRenderer,
		string $basePageName,
		array $params
	) {
		parent::__construct( $afPermManager, $context, $linkRenderer, $basePageName, $params );
		$this->boxBuilderFactory = $boxBuilderFactory;
		$this->ruleCheckerFactory = $ruleCheckerFactory;
		$this->varGeneratorFactory = $varGeneratorFactory;
	}

	/**
	 * Shows the page
	 */
	public function show() {
		$out = $this->getOutput();

		if ( !$this->afPermManager->canUseTestTools( $this->getUser() ) ) {
			// TODO: the message still refers to the old rights
			$out->addWikiMsg( 'abusefilter-mustviewprivateoredit' );
			return;
		}

		$this->loadParameters();

		$out->setPageTitle( $this->msg( 'abusefilter-test' ) );
		$out->addHelpLink( 'Extension:AbuseFilter/Rules format' );
		$out->addWikiMsg( 'abusefilter-test-intro', self::$mChangeLimit );
		$out->enableOOUI();

		$boxBuilder = $this->boxBuilderFactory->newEditBoxBuilder( $this, $this->getUser(), $out );

		$rulesFields = [
			'rules' => [
				'section' => 'abusefilter-test-rules-section',
				'class' => EditBoxField::class,
				'html' => $boxBuilder->buildEditBox(
					$this->testPattern,
					true,
					true,
					false
				) . $this->buildFilterLoader()
			]
		];

		$RCMaxAge = $this->getConfig()->get( 'RCMaxAge' );
		$min = wfTimestamp( TS_ISO_8601, time() - $RCMaxAge );
		$max = wfTimestampNow();

		$optionsFields = [];
		$optionsFields['wpTestAction'] = [
			'name' => 'wpTestAction',
			'type' => 'select',
			'label-message' => 'abusefilter-test-action',
			'options' => [
				$this->msg( 'abusefilter-test-search-type-all' )->text() => 0,
				$this->msg( 'abusefilter-test-search-type-edit' )->text() => 'edit',
				$this->msg( 'abusefilter-test-search-type-move' )->text() => 'move',
				$this->msg( 'abusefilter-test-search-type-delete' )->text() => 'delete',
				$this->msg( 'abusefilter-test-search-type-createaccount' )->text() => 'createaccount',
				$this->msg( 'abusefilter-test-search-type-upload' )->text() => 'upload'
			]
		];
		$optionsFields['wpTestUser'] = [
			'name' => 'wpTestUser',
			'type' => 'user',
			'ipallowed' => true,
			'label-message' => 'abusefilter-test-user',
			'default' => $this->mTestUser
		];
		$optionsFields['wpExcludeBots'] = [
			'name' => 'wpExcludeBots',
			'type' => 'check',
			'label-message' => 'abusefilter-test-nobots',
			'default' => $this->mExcludeBots
		];
		$optionsFields['wpTestPeriodStart'] = [
			'name' => 'wpTestPeriodStart',
			'type' => 'datetime',
			'label-message' => 'abusefilter-test-period-start',
			'default' => $this->mTestPeriodStart,
			'min' => $min,
			'max' => $max
		];
		$optionsFields['wpTestPeriodEnd'] = [
			'name' => 'wpTestPeriodEnd',
			'type' => 'datetime',
			'label-message' => 'abusefilter-test-period-end',
			'default' => $this->mTestPeriodEnd,
			'min' => $min,
			'max' => $max
		];
		$optionsFields['wpTestPage'] = [
			'name' => 'wpTestPage',
			'type' => 'title',
			'label-message' => 'abusefilter-test-page',
			'default' => $this->mTestPage,
			'creatable' => true,
			'required' => false
		];
		$optionsFields['wpShowNegative'] = [
			'name' => 'wpShowNegative',
			'type' => 'check',
			'label-message' => 'abusefilter-test-shownegative',
			'selected' => $this->mShowNegative
		];
		array_walk( $optionsFields, static function ( &$el ) {
			$el['section'] = 'abusefilter-test-options-section';
		} );
		$allFields = array_merge( $rulesFields, $optionsFields );

		HTMLForm::factory( 'ooui', $allFields, $this->getContext() )
			->setTitle( $this->getTitle( 'test' ) )
			->setId( 'wpFilterForm' )
			->setWrapperLegendMsg( 'abusefilter-test-legend' )
			->setSubmitTextMsg( 'abusefilter-test-submit' )
			->setMethod( 'post' )
			->prepareForm()
			->displayForm( false );

		if ( $this->getRequest()->wasPosted() ) {
			$this->doTest();
		}
	}

	/**
	 * Loads the revisions and checks the given syntax against them
	 */
	public function doTest() {
		// Quick syntax check.
		$out = $this->getOutput();
		$ruleChecker = $this->ruleCheckerFactory->newRuleChecker();

		if ( !$ruleChecker->checkSyntax( $this->testPattern )->isValid() ) {
			$out->addWikiMsg( 'abusefilter-test-syntaxerr' );
			return;
		}

		$dbr = wfGetDB( DB_REPLICA );
		$rcQuery = RecentChange::getQueryInfo();
		$conds = [];

		if ( (string)$this->mTestUser !== '' ) {
			$conds[$rcQuery['fields']['rc_user_text']] = $this->mTestUser;
		}

		if ( $this->mTestPeriodStart ) {
			$conds[] = 'rc_timestamp >= ' .
				$dbr->addQuotes( $dbr->timestamp( strtotime( $this->mTestPeriodStart ) ) );
		}
		if ( $this->mTestPeriodEnd ) {
			$conds[] = 'rc_timestamp <= ' .
				$dbr->addQuotes( $dbr->timestamp( strtotime( $this->mTestPeriodEnd ) ) );
		}
		if ( $this->mTestPage ) {
			$title = Title::newFromText( $this->mTestPage );
			if ( $title instanceof Title ) {
				$conds['rc_namespace'] = $title->getNamespace();
				$conds['rc_title'] = $title->getDBkey();
			} else {
				$out->addWikiMsg( 'abusefilter-test-badtitle' );
				return;
			}
		}

		if ( $this->mExcludeBots ) {
			$conds['rc_bot'] = 0;
		}

		$action = $this->mTestAction !== '0' ? $this->mTestAction : false;
		$conds[] = $this->buildTestConditions( $dbr, $action );
		$conds = array_merge( $conds, $this->buildVisibilityConditions( $dbr, $this->getAuthority() ) );

		$res = $dbr->select(
			$rcQuery['tables'],
			$rcQuery['fields'],
			$conds,
			__METHOD__,
			[ 'LIMIT' => self::$mChangeLimit, 'ORDER BY' => 'rc_timestamp desc' ],
			$rcQuery['joins']
		);

		// Get our ChangesList
		$changesList = new AbuseFilterChangesList( $this->getContext(), $this->testPattern );
		// Note, we're initializing some rows that will later be discarded. Hopefully this won't have any overhead.
		$changesList->initChangesListRows( $res );
		$output = $changesList->beginRecentChangesList();

		$counter = 1;

		$contextUser = $this->getUser();
		$ruleChecker->toggleConditionLimit( false );
		foreach ( $res as $row ) {
			$rc = RecentChange::newFromRow( $row );
			if ( !$this->mShowNegative ) {
				$type = (int)$rc->getAttribute( 'rc_type' );
				$deletedValue = $rc->getAttribute( 'rc_deleted' );
				if (
					(
						$type === RC_LOG &&
						!LogEventsList::userCanBitfield(
							$deletedValue,
							LogPage::SUPPRESSED_ACTION | LogPage::SUPPRESSED_USER,
							$contextUser
						)
					) || (
						$type !== RC_LOG &&
						!RevisionRecord::userCanBitfield( $deletedValue, RevisionRecord::SUPPRESSED_ALL, $contextUser )
					)
				) {
					// If the RC is deleted, the user can't see it, and we're only showing matches,
					// always skip this row. If mShowNegative is true, we can still show the row
					// because we won't tell whether it matches the given filter.
					continue;
				}
			}

			$varGenerator = $this->varGeneratorFactory->newRCGenerator( $rc, $contextUser );
			$vars = $varGenerator->getVars();

			if ( !$vars ) {
				continue;
			}

			$ruleChecker->setVariables( $vars );
			$result = $ruleChecker->checkConditions( $this->testPattern )->getResult();

			if ( $result || $this->mShowNegative ) {
				// Stash result in RC item
				$rc->filterResult = $result;
				$rc->counter = $counter++;
				$output .= $changesList->recentChangesLine( $rc, false );
			}
		}

		$output .= $changesList->endRecentChangesList();

		$out->addHTML( $output );
	}

	/**
	 * Loads parameters from request
	 */
	public function loadParameters() {
		$request = $this->getRequest();

		$this->testPattern = $request->getText( 'wpFilterRules' );
		$this->mShowNegative = $request->getBool( 'wpShowNegative' );
		$testUsername = $request->getText( 'wpTestUser' );
		$this->mTestPeriodEnd = $request->getText( 'wpTestPeriodEnd' );
		$this->mTestPeriodStart = $request->getText( 'wpTestPeriodStart' );
		$this->mTestPage = $request->getText( 'wpTestPage' );
		$this->mExcludeBots = $request->getBool( 'wpExcludeBots' );
		$this->mTestAction = $request->getText( 'wpTestAction' );

		if ( !$this->testPattern
			&& count( $this->mParams ) > 1
			&& is_numeric( $this->mParams[1] )
		) {
			$dbr = wfGetDB( DB_REPLICA );
			$this->testPattern = $dbr->selectField( 'abuse_filter',
				'af_pattern',
				[ 'af_id' => $this->mParams[1] ],
				__METHOD__
			);
		}

		// Normalise username
		$userTitle = Title::newFromText( $testUsername, NS_USER );
		$this->mTestUser = $userTitle ? $userTitle->getText() : null;
	}
}
