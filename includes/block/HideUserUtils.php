<?php

namespace MediaWiki\Block;

use Wikimedia\Rdbms\IReadableDatabase;
use Wikimedia\Rdbms\SelectQueryBuilder;

/**
 * Helpers for building queries that determine whether a user is hidden
 * @since 1.42
 */
class HideUserUtils {
	/**
	 * Select users that are not hidden
	 */
	public const SHOWN_USERS = 1;

	/**
	 * Select users that are hidden
	 */
	public const HIDDEN_USERS = 2;

	/** @var int */
	private $readStage;

	public function __construct( $blockTargetMigrationStage ) {
		$this->readStage = $blockTargetMigrationStage & SCHEMA_COMPAT_READ_MASK;
	}

	/**
	 * Get an SQL expression suitable for use in WHERE clause which will be
	 * true for either hidden or non-hidden users as specified.
	 *
	 * The expression will contain a subquery.
	 *
	 * @param IReadableDatabase $dbr
	 * @param string $userIdField The field to be used as the user_id when
	 *   joining on block/ipblocks. Defaults to "user_id".
	 * @param int $status Either self::SHOWN_USERS or self::HIDDEN_USERS
	 *   depending on what sort of user you want to match.
	 * @return string
	 */
	public function getExpression(
		IReadableDatabase $dbr,
		string $userIdField = 'user_id',
		$status = self::SHOWN_USERS
	) {
		if ( $this->readStage === SCHEMA_COMPAT_READ_OLD ) {
			$cond = $status === self::HIDDEN_USERS ? '' : 'NOT ';
			$cond .= 'EXISTS (' .
				 $dbr->newSelectQueryBuilder()
					 ->select( '1' )
					 ->from( 'ipblocks', 'hu_ipblocks' )
					 ->where( [ "hu_ipblocks.ipb_user=$userIdField", 'hu_ipblocks.ipb_deleted' => 1 ] )
					 ->caller( __METHOD__ )
					 ->getSQL() .
				 ')';
		} else {
			// Use a scalar subquery, not IN/EXISTS, to avoid materialization (T360160)
			$cond = '(' .
				$dbr->newSelectQueryBuilder()
					->select( '1' )
					// $userIdField may be e.g. block_target.bt_user so an inner table
					// alias is necessary to ensure that that refers to the outer copy
					// of the block_target table.
					->from( 'block_target', 'hu_block_target' )
					->join( 'block', 'hu_block', 'hu_block.bl_target=hu_block_target.bt_id' )
					->where( [ "hu_block_target.bt_user=$userIdField", 'hu_block.bl_deleted' => 1 ] )
					->caller( __METHOD__ )
					->getSQL() .
				') ' .
				( $status === self::HIDDEN_USERS ? 'IS NOT NULL' : 'IS NULL' );
		}
		return $cond;
	}

	/**
	 * Add a field and related joins to the query builder. The field in the
	 * query result will be true if the user is hidden or false otherwise.
	 *
	 * Note that a GROUP BY option will be set, to avoid duplicating the result
	 * row if the user is hidden by more than one block.
	 *
	 * @param SelectQueryBuilder $qb The query builder to be modified
	 * @param string $userIdField The name of the user_id field to use in the join
	 * @param string $deletedFieldAlias The field alias which will contain the
	 *   true if the user is deleted.
	 */
	public function addFieldToBuilder(
		SelectQueryBuilder $qb,
		$userIdField = 'user_id',
		$deletedFieldAlias = 'hu_deleted'
	) {
		if ( $this->readStage === SCHEMA_COMPAT_READ_OLD ) {
			$qb
				->select( [ $deletedFieldAlias => 'ipb_deleted IS NOT NULL' ] )
				->leftJoin(
					'ipblocks', 'hide_user_ipblocks',
					[ "ipb_user=$userIdField", 'ipb_deleted' => 1 ]
				);
		} else {
			$group = $qb->newJoinGroup()
				->table( 'block_target' )
				->join( 'block', 'hide_user_block', 'bl_target=bt_id' );
			$qb
				->select( [ $deletedFieldAlias => 'bl_deleted IS NOT NULL' ] )
				->leftJoin(
					$group,
					'hide_user_block_group',
					[ "bt_user=$userIdField", 'bl_deleted' => 1 ]
				)
				->groupBy( $userIdField );
		}
	}
}
