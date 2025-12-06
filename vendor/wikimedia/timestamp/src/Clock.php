<?php

namespace Wikimedia\Timestamp;

use DateTimeImmutable;
use Psr\Clock\ClockInterface;

/**
 * PSR-20 adapter for the fake timer in ConvertibleTimestamp
 * @see ConvertibleTimestamp::setFakeTime()
 */
class Clock implements ClockInterface {

	/** @inheritDoc */
	public function now(): DateTimeImmutable {
		// TODO use DateTimeImmutable::createFromTimestamp( ConvertibleTimestamp::time() ) once we are PHP 8.4+
		$datetime = ( new ConvertibleTimestamp() )->timestamp;
		return DateTimeImmutable::createFromMutable( $datetime );
	}

}
