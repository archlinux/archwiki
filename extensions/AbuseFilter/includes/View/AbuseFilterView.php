<?php

namespace MediaWiki\Extension\AbuseFilter\View;

use Flow\Data\Listener\RecentChangesListener;
use MediaWiki\Context\ContextSource;
use MediaWiki\Context\IContextSource;
use MediaWiki\Extension\AbuseFilter\AbuseFilterPermissionManager;
use MediaWiki\Html\Html;
use MediaWiki\Linker\LinkRenderer;
use MediaWiki\Permissions\Authority;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\SpecialPage\SpecialPage;
use MediaWiki\Title\Title;
use OOUI;
use RecentChange;
use UnexpectedValueException;
use Wikimedia\Assert\Assert;
use Wikimedia\Rdbms\IExpression;
use Wikimedia\Rdbms\IReadableDatabase;
use Wikimedia\Rdbms\Platform\ISQLPlatform;

abstract class AbuseFilterView extends ContextSource {

	private const MAP_ACTION_TO_LOG_TYPE = [
		// action => [ rc_log_type, rc_log_action ]
		'move' => [ 'move', [ 'move', 'move_redir' ] ],
		'createaccount' => [ 'newusers', [ 'create', 'create2', 'byemail', 'autocreate' ] ],
		'delete' => [ 'delete', 'delete' ],
		'upload' => [ 'upload', [ 'upload', 'overwrite', 'revert' ] ],
	];

	protected AbuseFilterPermissionManager $afPermManager;

	/**
	 * @var array The parameters of the current request
	 */
	protected array $mParams;

	protected LinkRenderer $linkRenderer;

	protected string $basePageName;

	/**
	 * @param AbuseFilterPermissionManager $afPermManager
	 * @param IContextSource $context
	 * @param LinkRenderer $linkRenderer
	 * @param string $basePageName
	 * @param array $params
	 */
	public function __construct(
		AbuseFilterPermissionManager $afPermManager,
		IContextSource $context,
		LinkRenderer $linkRenderer,
		string $basePageName,
		array $params
	) {
		$this->mParams = $params;
		$this->setContext( $context );
		$this->linkRenderer = $linkRenderer;
		$this->basePageName = $basePageName;
		$this->afPermManager = $afPermManager;
	}

	/**
	 * @param string|int $subpage
	 * @return Title
	 */
	public function getTitle( $subpage = '' ) {
		return SpecialPage::getTitleFor( $this->basePageName, $subpage );
	}

	/**
	 * Function to show the page
	 */
	abstract public function show();

	/**
	 * Build input and button for loading a filter
	 *
	 * @return string
	 */
	public function buildFilterLoader() {
		$loadText =
			new OOUI\TextInputWidget(
				[
					'type' => 'number',
					'name' => 'wpInsertFilter',
					'id' => 'mw-abusefilter-load-filter'
				]
			);
		$loadButton =
			new OOUI\ButtonWidget(
				[
					'label' => $this->msg( 'abusefilter-test-load' )->text(),
					'id' => 'mw-abusefilter-load'
				]
			);
		$loadGroup =
			new OOUI\ActionFieldLayout(
				$loadText,
				$loadButton,
				[
					'label' => $this->msg( 'abusefilter-test-load-filter' )->text()
				]
			);
		// CSS class for reducing default input field width
		return Html::rawElement(
			'div',
			[ 'class' => 'mw-abusefilter-load-filter-id' ],
			$loadGroup
		);
	}

	/**
	 * @param IReadableDatabase $db
	 * @param string|false $action 'edit', 'move', 'createaccount', 'delete' or false for all
	 * @return IExpression
	 */
	public function buildTestConditions( IReadableDatabase $db, $action = false ) {
		Assert::parameterType( [ 'string', 'false' ], $action, '$action' );
		$editSources = [
			RecentChange::SRC_EDIT,
			RecentChange::SRC_NEW,
		];
		if ( in_array( 'flow', $this->getConfig()->get( 'AbuseFilterValidGroups' ), true ) ) {
			// TODO Should this be separated somehow? Also, this case should be handled via a hook, not
			// by special-casing Flow here.
			// @phan-suppress-next-line PhanUndeclaredClassConstant Temporary solution
			$editSources[] = RecentChangesListener::SRC_FLOW;
		}
		if ( $action === 'edit' ) {
			return $db->expr( 'rc_source', '=', $editSources );
		}
		if ( $action !== false ) {
			if ( !isset( self::MAP_ACTION_TO_LOG_TYPE[$action] ) ) {
				throw new UnexpectedValueException( __METHOD__ . ' called with invalid action: ' . $action );
			}
			[ $logType, $logAction ] = self::MAP_ACTION_TO_LOG_TYPE[$action];
			return $db->expr( 'rc_source', '=', RecentChange::SRC_LOG )
				->and( 'rc_log_type', '=', $logType )
				->and( 'rc_log_action', '=', $logAction );
		}

		// filter edit and log actions
		$conds = [];
		foreach ( self::MAP_ACTION_TO_LOG_TYPE as [ $logType, $logAction ] ) {
			$conds[] = $db->expr( 'rc_log_type', '=', $logType )
				->and( 'rc_log_action', '=', $logAction );
		}

		return $db->expr( 'rc_source', '=', $editSources )
			->orExpr(
				$db->expr( 'rc_source', '=', RecentChange::SRC_LOG )
					->andExpr( $db->orExpr( $conds ) )
			);
	}

	/**
	 * @todo Core should provide a method for this (T233222)
	 * @param ISQLPlatform $db
	 * @param Authority $authority
	 * @return array
	 */
	public function buildVisibilityConditions( ISQLPlatform $db, Authority $authority ): array {
		if ( !$authority->isAllowed( 'deletedhistory' ) ) {
			$bitmask = RevisionRecord::DELETED_USER;
		} elseif ( !$authority->isAllowedAny( 'suppressrevision', 'viewsuppressed' ) ) {
			$bitmask = RevisionRecord::DELETED_USER | RevisionRecord::DELETED_RESTRICTED;
		} else {
			$bitmask = 0;
		}
		return $bitmask
			? [ $db->bitAnd( 'rc_deleted', $bitmask ) . " != $bitmask" ]
			: [];
	}

	/**
	 * @param string|int $id
	 * @param string|null $text
	 * @return string HTML
	 */
	public function getLinkToLatestDiff( $id, $text = null ) {
		return $this->linkRenderer->makeKnownLink(
			$this->getTitle( "history/$id/diff/prev/cur" ),
			$text
		);
	}

}
