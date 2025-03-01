<?php
/**
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 * http://www.gnu.org/copyleft/gpl.html
 * @file
 */

declare( strict_types=1 );

namespace Wikimedia\Stats\Metrics;

use Wikimedia\Stats\Exceptions\IllegalOperationException;
use Wikimedia\Stats\Sample;

/**
 * Timing Metric Implementation
 *
 * Timing metrics track duration data which can be broken into histograms.
 * They are identified by type "ms".
 *
 * @author Cole White
 * @since 1.38
 */
class TimingMetric implements MetricInterface {
	use MetricTrait;

	/**
	 * The StatsD protocol type indicator:
	 * https://github.com/statsd/statsd/blob/v0.9.0/docs/metric_types.md
	 * https://docs.datadoghq.com/developers/dogstatsd/datagram_shell/?tab=metrics
	 */
	private const TYPE_INDICATOR = "ms";

	/** @var float|null */
	private ?float $startTime = null;

	/**
	 * Starts a timer.
	 *
	 * @return void
	 */
	public function start(): void {
		$this->startTime = hrtime( true );
	}

	/**
	 * Stops a running timer.
	 *
	 * @return void
	 */
	public function stop(): void {
		if ( $this->startTime === null ) {
			trigger_error( "Stats: stop() called before start() for metric '{$this->getName()}'", E_USER_WARNING );
			return;
		}
		$this->observeNanoseconds( hrtime( true ) - $this->startTime );
		$this->startTime = null;
	}

	/**
	 * Records a previously calculated observation in milliseconds.
	 *
	 * @param float $milliseconds
	 * @return void
	 */
	public function observe( float $milliseconds ): void {
		foreach ( $this->baseMetric->getStatsdNamespaces() as $namespace ) {
			$this->baseMetric->getStatsdDataFactory()->timing( $namespace, $milliseconds );
		}

		try {
			$this->baseMetric->addSample( new Sample( $this->baseMetric->getLabelValues(), $milliseconds ) );
		} catch ( IllegalOperationException $ex ) {
			// Log the condition and give the caller something that will absorb calls.
			trigger_error( $ex->getMessage(), E_USER_WARNING );
		}
	}

	/**
	 * Record a previously calculated observation in seconds.
	 *
	 * Common usage:
	 *  ```php
	 *  $startTime = microtime( true )
	 *  # work to be measured...
	 *  $metric->observeSeconds( microtime( true ) - $startTime )
	 *  ```
	 *
	 * @param float $seconds
	 * @return void
	 * @since 1.43
	 */
	public function observeSeconds( float $seconds ): void {
		$this->observe( $seconds * 1000 );
	}

	/**
	 * Record a previously calculated observation in nanoseconds.
	 *
	 *  Common usage:
	 *  ```php
	 *  $startTime = hrtime( true )
	 *  # work to be measured...
	 *  $metric->observeNanoseconds( hrtime( true ) - $startTime )
	 *  ```
	 * @param float $nanoseconds
	 * @return void
	 * @since 1.43
	 */
	public function observeNanoseconds( float $nanoseconds ): void {
		$this->observe( $nanoseconds * 1e-6 );
	}

	/** @inheritDoc */
	public function getTypeIndicator(): string {
		return self::TYPE_INDICATOR;
	}
}
