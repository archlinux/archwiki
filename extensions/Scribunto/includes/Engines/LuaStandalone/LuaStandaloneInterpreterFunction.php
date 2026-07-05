<?php

namespace MediaWiki\Extension\Scribunto\Engines\LuaStandalone;

class LuaStandaloneInterpreterFunction {
	/** @var bool[] */
	public static $anyChunksDestroyed = [];
	/** @var int[][] */
	public static $activeChunkIds = [];

	public function __construct(
		public readonly int $interpreterId,
		public readonly int $id,
	) {
		$this->incrementRefCount();
	}

	public function __clone() {
		$this->incrementRefCount();
	}

	public function __wakeup() {
		$this->incrementRefCount();
	}

	public function __destruct() {
		$this->decrementRefCount();
	}

	private function incrementRefCount() {
		if ( !isset( self::$activeChunkIds[$this->interpreterId] ) ) {
			self::$activeChunkIds[$this->interpreterId] = [ $this->id => 1 ];
		} elseif ( !isset( self::$activeChunkIds[$this->interpreterId][$this->id] ) ) {
			self::$activeChunkIds[$this->interpreterId][$this->id] = 1;
		} else {
			self::$activeChunkIds[$this->interpreterId][$this->id]++;
		}
	}

	private function decrementRefCount() {
		if ( isset( self::$activeChunkIds[$this->interpreterId][$this->id] ) ) {
			if ( --self::$activeChunkIds[$this->interpreterId][$this->id] <= 0 ) {
				unset( self::$activeChunkIds[$this->interpreterId][$this->id] );
				self::$anyChunksDestroyed[$this->interpreterId] = true;
			}
		} else {
			self::$anyChunksDestroyed[$this->interpreterId] = true;
		}
	}
}
