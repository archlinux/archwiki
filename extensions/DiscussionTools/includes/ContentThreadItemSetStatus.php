<?php

namespace MediaWiki\Extension\DiscussionTools;

use MediaWiki\Status\Status;
use StatusValue;
use Wikimedia\NormalizedException\NormalizedException;

class ContentThreadItemSetStatus extends StatusValue {

	/**
	 * Convenience method.
	 */
	public static function wrap( StatusValue $other ): self {
		return ( new self )->merge( $other, true );
	}

	/**
	 * Like getValue(), but will throw if the value is null. Check isOK() first to avoid errors.
	 */
	public function getValueOrThrow(): ContentThreadItemSet {
		$value = $this->getValue();
		if ( $value === null ) {
			throw new NormalizedException( ...Status::wrap( $this )->getPsr3MessageAndContext() );
		}
		return $value;
	}

}
