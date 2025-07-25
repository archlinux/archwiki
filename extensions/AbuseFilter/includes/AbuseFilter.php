<?php

namespace MediaWiki\Extension\AbuseFilter;

use Wikimedia\Rdbms\IExpression;
use Wikimedia\Rdbms\IReadableDatabase;
use Wikimedia\Rdbms\LikeValue;

class AbuseFilter {

	/**
	 * @deprecated
	 * @todo Phase out
	 */
	public const HISTORY_MAPPINGS = [
		'af_pattern' => 'afh_pattern',
		'af_user' => 'afh_user',
		'af_user_text' => 'afh_user_text',
		'af_actor' => 'afh_actor',
		'af_timestamp' => 'afh_timestamp',
		'af_comments' => 'afh_comments',
		'af_public_comments' => 'afh_public_comments',
		'af_deleted' => 'afh_deleted',
		'af_id' => 'afh_filter',
		'af_group' => 'afh_group',
	];

	/**
	 * Convenience wrapper around IReadableDatabase::expr simulating MySQL's FIND_IN_SET.
	 *
	 * This method returns an IExpression corresponding to a FIND_IN_SET(needle, field)
	 * SQL condition, which checks if the string `$needle` is present
	 * in the comma-separated list stored in the column `$field`.
	 */
	public static function findInSet( IReadableDatabase $db, string $field, string $needle ): IExpression {
		return $db->expr( $field, '=', $needle )
			->or( $field, IExpression::LIKE, new LikeValue(
				$needle, ',', $db->anyString()
			) )
			->or( $field, IExpression::LIKE, new LikeValue(
				$db->anyString(), ',', $needle
			) )
			->or( $field, IExpression::LIKE, new LikeValue(
				$db->anyString(),
				',', $needle, ',',
				$db->anyString()
			) );
	}

}
