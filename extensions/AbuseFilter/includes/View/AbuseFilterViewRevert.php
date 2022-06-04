<?php

namespace MediaWiki\Extension\AbuseFilter\View;

use Html;
use HTMLForm;
use IContextSource;
use Linker;
use MediaWiki\Extension\AbuseFilter\AbuseFilterPermissionManager;
use MediaWiki\Extension\AbuseFilter\Consequences\Consequence\ReversibleConsequence;
use MediaWiki\Extension\AbuseFilter\Consequences\ConsequencesFactory;
use MediaWiki\Extension\AbuseFilter\Consequences\Parameters;
use MediaWiki\Extension\AbuseFilter\FilterLookup;
use MediaWiki\Extension\AbuseFilter\SpecsFormatter;
use MediaWiki\Extension\AbuseFilter\Variables\VariablesBlobStore;
use MediaWiki\Linker\LinkRenderer;
use MediaWiki\User\UserFactory;
use Message;
use MWException;
use PermissionsError;
use SpecialPage;
use TitleValue;
use UserBlockedError;
use Xml;

class AbuseFilterViewRevert extends AbuseFilterView {
	/** @var int */
	private $filter;
	/**
	 * @var string The start time of the lookup period
	 */
	private $origPeriodStart;
	/**
	 * @var string The end time of the lookup period
	 */
	private $origPeriodEnd;
	/**
	 * @var string|null The same as $origPeriodStart
	 */
	private $periodStart;
	/**
	 * @var string|null The same as $origPeriodEnd
	 */
	private $periodEnd;
	/**
	 * @var string|null The reason provided for the revert
	 */
	private $reason;
	/**
	 * @var UserFactory
	 */
	private $userFactory;
	/**
	 * @var FilterLookup
	 */
	private $filterLookup;
	/**
	 * @var ConsequencesFactory
	 */
	private $consequencesFactory;
	/**
	 * @var VariablesBlobStore
	 */
	private $varBlobStore;
	/**
	 * @var SpecsFormatter
	 */
	private $specsFormatter;

	/**
	 * @param UserFactory $userFactory
	 * @param AbuseFilterPermissionManager $afPermManager
	 * @param FilterLookup $filterLookup
	 * @param ConsequencesFactory $consequencesFactory
	 * @param VariablesBlobStore $varBlobStore
	 * @param SpecsFormatter $specsFormatter
	 * @param IContextSource $context
	 * @param LinkRenderer $linkRenderer
	 * @param string $basePageName
	 * @param array $params
	 */
	public function __construct(
		UserFactory $userFactory,
		AbuseFilterPermissionManager $afPermManager,
		FilterLookup $filterLookup,
		ConsequencesFactory $consequencesFactory,
		VariablesBlobStore $varBlobStore,
		SpecsFormatter $specsFormatter,
		IContextSource $context,
		LinkRenderer $linkRenderer,
		string $basePageName,
		array $params
	) {
		parent::__construct( $afPermManager, $context, $linkRenderer, $basePageName, $params );
		$this->userFactory = $userFactory;
		$this->filterLookup = $filterLookup;
		$this->consequencesFactory = $consequencesFactory;
		$this->varBlobStore = $varBlobStore;
		$this->specsFormatter = $specsFormatter;
		$this->specsFormatter->setMessageLocalizer( $this->getContext() );
	}

	/**
	 * Shows the page
	 */
	public function show() {
		$lang = $this->getLanguage();

		$user = $this->getUser();
		$out = $this->getOutput();

		if ( !$this->afPermManager->canRevertFilterActions( $user ) ) {
			throw new PermissionsError( 'abusefilter-revert' );
		}

		$block = $user->getBlock();
		if ( $block && $block->isSitewide() ) {
			throw new UserBlockedError( $block );
		}

		$this->loadParameters();

		if ( $this->attemptRevert() ) {
			return;
		}

		$filter = $this->filter;

		$out->addWikiMsg( 'abusefilter-revert-intro', Message::numParam( $filter ) );
		$out->setPageTitle( $this->msg( 'abusefilter-revert-title' )->numParams( $filter ) );

		// First, the search form. Limit dates to avoid huge queries
		$RCMaxAge = $this->getConfig()->get( 'RCMaxAge' );
		$min = wfTimestamp( TS_ISO_8601, time() - $RCMaxAge );
		$max = wfTimestampNow();
		$filterLink =
			$this->linkRenderer->makeLink(
				$this->getTitle( $filter ),
				$lang->formatNum( $filter )
			);
		$searchFields = [];
		$searchFields['filterid'] = [
			'type' => 'info',
			'default' => $filterLink,
			'raw' => true,
			'label-message' => 'abusefilter-revert-filter'
		];
		$searchFields['periodstart'] = [
			'type' => 'datetime',
			'name' => 'wpPeriodStart',
			'default' => $this->origPeriodStart,
			'label-message' => 'abusefilter-revert-periodstart',
			'min' => $min,
			'max' => $max
		];
		$searchFields['periodend'] = [
			'type' => 'datetime',
			'name' => 'wpPeriodEnd',
			'default' => $this->origPeriodEnd,
			'label-message' => 'abusefilter-revert-periodend',
			'min' => $min,
			'max' => $max
		];

		HTMLForm::factory( 'ooui', $searchFields, $this->getContext() )
			->setAction( $this->getTitle( "revert/$filter" )->getLocalURL() )
			->setWrapperLegendMsg( 'abusefilter-revert-search-legend' )
			->setSubmitTextMsg( 'abusefilter-revert-search' )
			->setMethod( 'get' )
			->setFormIdentifier( 'revert-select-date' )
			->setSubmitCallback( [ $this, 'showRevertableActions' ] )
			->showAlways();
	}

	/**
	 * Show revertable actions, called as submit callback by HTMLForm
	 * @param array $formData
	 * @param HTMLForm $dateForm
	 * @return bool
	 */
	public function showRevertableActions( array $formData, HTMLForm $dateForm ): bool {
		$lang = $this->getLanguage();
		$user = $this->getUser();
		$filter = $this->filter;

		// Look up all of them.
		$results = $this->doLookup();
		if ( $results === [] ) {
			$dateForm->addPostText( $this->msg( 'abusefilter-revert-preview-no-results' )->escaped() );
			return true;
		}

		// Add a summary of everything that will be reversed.
		$dateForm->addPostText( $this->msg( 'abusefilter-revert-preview-intro' )->parseAsBlock() );
		$list = [];

		foreach ( $results as $result ) {
			$displayActions = [];
			foreach ( $result['actions'] as $action ) {
				$displayActions[] = $this->specsFormatter->getActionDisplay( $action );
			}

			$msg = $this->msg( 'abusefilter-revert-preview-item' )
				->params(
					$lang->userTimeAndDate( $result['timestamp'], $user )
				)->rawParams(
					Linker::userLink( $result['userid'], $result['user'] )
				)->params(
					$result['action']
				)->rawParams(
					$this->linkRenderer->makeLink( $result['title'] )
				)->params(
					$lang->commaList( $displayActions )
				)->rawParams(
					$this->linkRenderer->makeLink(
						SpecialPage::getTitleFor( 'AbuseLog' ),
						$this->msg( 'abusefilter-log-detailslink' )->text(),
						[],
						[ 'details' => $result['id'] ]
					)
				)->params( $result['user'] )->parse();
			$list[] = Xml::tags( 'li', null, $msg );
		}

		$dateForm->addPostText( Xml::tags( 'ul', null, implode( "\n", $list ) ) );

		// Add a button down the bottom.
		$confirmForm = [];
		$confirmForm['edittoken'] = [
			'type' => 'hidden',
			'name' => 'editToken',
			'default' => $user->getEditToken( "abusefilter-revert-$filter" )
		];
		$confirmForm['title'] = [
			'type' => 'hidden',
			'name' => 'title',
			'default' => $this->getTitle( "revert/$filter" )->getPrefixedDBkey()
		];
		$confirmForm['wpPeriodStart'] = [
			'type' => 'hidden',
			'name' => 'wpPeriodStart',
			'default' => $this->origPeriodStart
		];
		$confirmForm['wpPeriodEnd'] = [
			'type' => 'hidden',
			'name' => 'wpPeriodEnd',
			'default' => $this->origPeriodEnd
		];
		$confirmForm['reason'] = [
			'type' => 'text',
			'label-message' => 'abusefilter-revert-reasonfield',
			'name' => 'wpReason',
			'id' => 'wpReason',
		];

		$revertForm = HTMLForm::factory( 'ooui', $confirmForm, $this->getContext() )
			->setAction( $this->getTitle( "revert/$filter" )->getLocalURL() )
			->setWrapperLegendMsg( 'abusefilter-revert-confirm-legend' )
			->setSubmitTextMsg( 'abusefilter-revert-confirm' )
			->prepareForm()
			->getHTML( true );
		$dateForm->addPostText( $revertForm );

		return true;
	}

	/**
	 * @return array[]
	 */
	public function doLookup() {
		$periodStart = $this->periodStart;
		$periodEnd = $this->periodEnd;
		$filter = $this->filter;
		$dbr = wfGetDB( DB_REPLICA );

		// Only hits from local filters can be reverted
		$conds = [ 'afl_filter_id' => $filter, 'afl_global' => 0 ];

		if ( $periodStart !== null ) {
			$conds[] = 'afl_timestamp >= ' . $dbr->addQuotes( $dbr->timestamp( $periodStart ) );
		}
		if ( $periodEnd !== null ) {
			$conds[] = 'afl_timestamp <= ' . $dbr->addQuotes( $dbr->timestamp( $periodEnd ) );
		}

		// Don't revert if there was no action, or the action was global
		$conds[] = 'afl_actions != ' . $dbr->addQuotes( '' );
		$conds[] = 'afl_wiki IS NULL';

		$selectFields = [
			'afl_id',
			'afl_user',
			'afl_user_text',
			'afl_action',
			'afl_actions',
			'afl_var_dump',
			'afl_timestamp',
			'afl_namespace',
			'afl_title',
		];
		$res = $dbr->select(
			'abuse_filter_log',
			$selectFields,
			$conds,
			__METHOD__,
			[ 'ORDER BY' => 'afl_timestamp DESC' ]
		);

		$results = [];
		foreach ( $res as $row ) {
			$actions = explode( ',', $row->afl_actions );
			// TODO: get the following from ConsequencesRegistry or sth else
			$reversibleActions = [ 'block', 'blockautopromote', 'degroup' ];
			$currentReversibleActions = array_intersect( $actions, $reversibleActions );
			if ( count( $currentReversibleActions ) ) {
				$results[] = [
					'id' => $row->afl_id,
					'actions' => $currentReversibleActions,
					'user' => $row->afl_user_text,
					'userid' => $row->afl_user,
					'vars' => $this->varBlobStore->loadVarDump( $row->afl_var_dump ),
					'title' => new TitleValue( (int)$row->afl_namespace, $row->afl_title ),
					'action' => $row->afl_action,
					'timestamp' => $row->afl_timestamp
				];
			}
		}

		return $results;
	}

	/**
	 * Loads parameters from request
	 */
	public function loadParameters() {
		$request = $this->getRequest();

		$this->filter = (int)$this->mParams[1];
		$this->origPeriodStart = $request->getText( 'wpPeriodStart' );
		$this->periodStart = strtotime( $this->origPeriodStart ) ?: null;
		$this->origPeriodEnd = $request->getText( 'wpPeriodEnd' );
		$this->periodEnd = strtotime( $this->origPeriodEnd ) ?: null;
		$this->reason = $request->getVal( 'wpReason' );
	}

	/**
	 * @return bool
	 */
	public function attemptRevert() {
		$filter = $this->filter;
		$token = $this->getRequest()->getVal( 'editToken' );
		if ( !$this->getUser()->matchEditToken( $token, "abusefilter-revert-$filter" ) ) {
			return false;
		}

		$results = $this->doLookup();
		foreach ( $results as $result ) {
			foreach ( $result['actions'] as $action ) {
				$this->revertAction( $action, $result );
			}
		}
		$this->getOutput()->addHTML( Html::successBox(
			$this->msg(
				'abusefilter-revert-success',
				$filter,
				$this->getLanguage()->formatNum( $filter )
			)->parse()
		) );

		return true;
	}

	/**
	 * Helper method for typing
	 * @param string $action
	 * @param array $result
	 * @return ReversibleConsequence
	 * @throws MWException
	 */
	private function getConsequence( string $action, array $result ): ReversibleConsequence {
		$params = new Parameters(
			$this->filterLookup->getFilter( $this->filter, false ),
			false,
			$this->userFactory->newFromAnyId(
				$result['userid'],
				$result['user'],
				null
			),
			$result['title'],
			$result['action']
		);

		switch ( $action ) {
			case 'block':
				return $this->consequencesFactory->newBlock( $params, '', false );
			case 'blockautopromote':
				$duration = $this->getConfig()->get( 'AbuseFilterBlockAutopromoteDuration' ) * 86400;
				return $this->consequencesFactory->newBlockAutopromote( $params, $duration );
			case 'degroup':
				return $this->consequencesFactory->newDegroup( $params, $result['vars'] );
			default:
				throw new MWException( "Invalid action $action" );
		}
	}

	/**
	 * @param string $action
	 * @param array $result
	 * @return bool
	 * @throws MWException
	 */
	public function revertAction( string $action, array $result ): bool {
		$message = $this->msg(
			'abusefilter-revert-reason', $this->filter, $this->reason
		)->inContentLanguage()->text();

		$consequence = $this->getConsequence( $action, $result );
		return $consequence->revert( $result, $this->getUser(), $message );
	}
}
