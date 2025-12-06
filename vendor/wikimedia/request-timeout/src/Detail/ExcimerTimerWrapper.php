<?php

namespace Wikimedia\RequestTimeout\Detail;

use ExcimerTimer;
use Wikimedia\RequestTimeout\EmergencyTimeoutException;
use Wikimedia\RequestTimeout\RequestTimeoutException;
use Wikimedia\RequestTimeout\TimeoutException;

/**
 * It's difficult to avoid the circular reference in $this->timer due to the
 * callback closure, which means this object is not destroyed implicitly when
 * it goes out of scope. So ExcimerRequestTimeout is split into an implicitly
 * destructible part (ExcimerRequestTimeout) and a part which must be
 * explicitly destroyed (this class).
 *
 * @internal
 */
class ExcimerTimerWrapper {
	/** @var ExcimerTimer|null */
	private $timer;

	/** @var int The next critical section ID to use */
	private $nextCriticalId = 1;

	/** @var ExcimerTimer[] */
	private $emergencyTimers = [];

	/** @var bool[] */
	private $emergencyRunning = [];

	/** @var int[] The active critical section IDs */
	private $criticalSections = [];

	/** @var array|null Data about the pending timeout, or null if no timeout is pending */
	private $pending;

	/** @var float */
	private $limit = INF;

	/**
	 * @param string $name
	 * @param float $emergencyLimit
	 * @param callable|null $emergencyCallback
	 * @return int
	 */
	public function enterCriticalSection( $name, $emergencyLimit, $emergencyCallback ) {
		if ( !$emergencyCallback ) {
			/** @return never */
			$emergencyCallback = static function () use ( $name, $emergencyLimit ) {
				throw new EmergencyTimeoutException( $name, $emergencyLimit );
			};
		}

		$id = $this->nextCriticalId++;

		// Appending to the array in the usual way causes it to skip unset
		// indexes at the end. Use array_key_last() so that indexes will be
		// densely assigned.
		$lastIndex = array_key_last( $this->criticalSections );
		$curIndex = $lastIndex === null ? 0 : $lastIndex + 1;
		$this->criticalSections[$curIndex] = $id;

		if ( $emergencyLimit > 0 && $emergencyLimit !== INF ) {
			$emergencyTimer = $this->getEmergencyTimer( $curIndex );
			$emergencyTimer->setInterval( $emergencyLimit );
			$emergencyTimer->setCallback( $emergencyCallback );
			$emergencyTimer->start();
			$this->emergencyRunning[$curIndex] = true;
		}

		return $id;
	}

	/**
	 * @param int $id
	 * @throws TimeoutException
	 */
	public function exitCriticalSection( $id ) {
		$stackIndex = array_search( $id, $this->criticalSections );
		if ( $stackIndex !== false ) {
			unset( $this->criticalSections[$stackIndex] );
			// TODO: add ExcimerTimer::isRunning()
			if ( !empty( $this->emergencyRunning[$stackIndex] ) ) {
				$this->emergencyTimers[$stackIndex]->stop();
				$this->emergencyRunning[$stackIndex] = false;
			}
		}
		if ( !count( $this->criticalSections ) && $this->pending ) {
			$limit = $this->pending['limit'];
			$this->pending = null;
			throw new RequestTimeoutException( $limit );
		}
	}

	/**
	 * @param float $limit The limit in seconds
	 */
	public function setWallTimeLimit( $limit ) {
		if ( $limit > 0 && $limit !== INF ) {
			$this->limit = (float)$limit;
			$this->timer = new ExcimerTimer;
			$this->timer->setInterval( $limit );
			$this->timer->setCallback( function () use ( $limit ) {
				$this->onTimeout( $limit );
			} );
			$this->timer->start();
		} else {
			$this->stop();
			$this->limit = INF;
		}
	}

	/**
	 * Callback function for the main request timeout. If any critical section
	 * is open, queue the event. Otherwise, throw the exception now.
	 *
	 * The limit is passed as a parameter, instead of using an object property,
	 * to provide greater assurance that the reported limit is the one that is
	 * actually timing out and not the result of a separate call to
	 * setWallTimeLimit().
	 *
	 * @param float $limit
	 * @throws TimeoutException
	 */
	private function onTimeout( $limit ) {
		if ( count( $this->criticalSections ) ) {
			$this->pending = [ 'limit' => $limit ];
		} else {
			throw new RequestTimeoutException( $limit );
		}
	}

	/**
	 * Get the amount of time remaining of the limit.
	 *
	 * @return float
	 */
	public function getWallTimeRemaining() {
		if ( $this->timer ) {
			return $this->timer->getTime();
		} else {
			return INF;
		}
	}

	/**
	 * Get the current wall time limit, or INF if there is no limit
	 *
	 * @return float
	 */
	public function getWallTimeLimit() {
		return $this->limit;
	}

	/**
	 * Stop and destroy the underlying timer.
	 */
	public function stop() {
		if ( $this->timer ) {
			$this->timer->stop();
		}
		$this->timer = null;

		foreach ( $this->emergencyTimers as $timer ) {
			$timer->stop();
		}
		$this->emergencyTimers = [];
		$this->emergencyRunning = [];
	}

	/**
	 * Keep a pool of emergency timers, since timer creation is slow after T391426
	 *
	 * @param int $stackIndex
	 * @return ExcimerTimer
	 */
	private function getEmergencyTimer( $stackIndex ) {
		if ( !isset( $this->emergencyTimers[$stackIndex] ) ) {
			$this->emergencyTimers[$stackIndex] = new ExcimerTimer;
		}
		return $this->emergencyTimers[$stackIndex];
	}
}
