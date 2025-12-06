<?php

namespace MediaWiki\Extension\AbuseFilter\Filter;

use LogicException;
use MediaWiki\User\UserIdentity;
use MediaWiki\User\UserIdentityValue;

/**
 * Value object representing a filter that can be mutated (i.e. provides setters); this representation can
 * be used to modify an existing database filter before saving it back to the DB.
 */
class MutableFilter extends Filter {
	/**
	 * Convenience shortcut to get a 'default' filter, using the defaults for the editing interface.
	 *
	 * @return self
	 * @codeCoverageIgnore
	 */
	public static function newDefault(): self {
		return new self(
			new Specs(
				'',
				'',
				'',
				[],
				''
			),
			new Flags(
				true,
				false,
				Flags::FILTER_PUBLIC,
				false
			),
			[],
			new LastEditInfo(
				UserIdentityValue::newAnonymous( '' ),
				''
			)
		);
	}

	public static function newFromParentFilter( Filter $filter ): self {
		return new self(
			$filter->getSpecs(),
			$filter->getFlags(),
			// @phan-suppress-next-line PhanTypeMismatchArgumentNullable One is guaranteed to be set
			$filter->actions ?? $filter->actionsCallback,
			$filter->getLastEditInfo(),
			$filter->getID(),
			$filter->getHitCount(),
			$filter->isThrottled()
		);
	}

	public function setRules( string $rules ): void {
		$this->specs->setRules( $rules );
	}

	public function setComments( string $comments ): void {
		$this->specs->setComments( $comments );
	}

	public function setName( string $name ): void {
		$this->specs->setName( $name );
	}

	/**
	 * @throws LogicException if $actions are already set; use $this->setActions to update names
	 * @param string[] $actionsNames
	 */
	public function setActionsNames( array $actionsNames ): void {
		if ( $this->actions !== null ) {
			throw new LogicException( 'Cannot set actions names with actions already set' );
		}
		$this->specs->setActionsNames( $actionsNames );
	}

	public function setGroup( string $group ): void {
		$this->specs->setGroup( $group );
	}

	public function setEnabled( bool $enabled ): void {
		$this->flags->setEnabled( $enabled );
	}

	public function setDeleted( bool $deleted ): void {
		$this->flags->setDeleted( $deleted );
	}

	public function setHidden( bool $hidden ): void {
		$this->flags->setHidden( $hidden );
	}

	public function setProtected( bool $protected ): void {
		$this->flags->setProtected( $protected );
	}

	public function setGlobal( bool $global ): void {
		$this->flags->setGlobal( $global );
	}

	/**
	 * @note This also updates action names
	 * @param array[] $actions
	 */
	public function setActions( array $actions ): void {
		parent::setActions( $actions );
	}

	public function setUserIdentity( UserIdentity $user ): void {
		$this->lastEditInfo->setUserIdentity( $user );
	}

	public function setTimestamp( string $timestamp ): void {
		$this->lastEditInfo->setTimestamp( $timestamp );
	}

	public function setID( ?int $id ): void {
		$this->id = $id;
	}

	public function setHitCount( int $hitCount ): void {
		$this->hitCount = $hitCount;
	}

	public function setThrottled( bool $throttled ): void {
		$this->throttled = $throttled;
	}
}
