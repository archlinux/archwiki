<?php

namespace MediaWiki\Deferred;

/**
 * Deferrable update that must run outside of any explicit LBFactory transaction round
 *
 * @since 1.31
 */
class TransactionRoundDefiningUpdate
	implements DeferrableUpdate, DeferrableCallback, TransactionRoundAwareUpdate
{
	/** @var callable|null */
	private $callback;
	/** @var string */
	private $fname;

	/**
	 * @param callable $callback
	 * @param string $fname Calling method
	 */
	public function __construct( callable $callback, $fname = 'unknown' ) {
		$this->callback = $callback;
		$this->fname = $fname;
	}

	public function doUpdate() {
		call_user_func( $this->callback );
	}

	public function getOrigin() {
		return $this->fname;
	}

	/**
	 * @return int One of the class TRX_ROUND_* constants
	 * @since 1.34
	 */
	final public function getTransactionRoundRequirement() {
		return self::TRX_ROUND_ABSENT;
	}
}

/** @deprecated class alias since 1.42 */
class_alias( TransactionRoundDefiningUpdate::class, 'TransactionRoundDefiningUpdate' );
