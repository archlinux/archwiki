<?php

namespace MediaWiki\Extension\Scribunto\Engines\LuaCommon;

class HashLibrary extends LibraryBase {

	/** @inheritDoc */
	public function register() {
		$lib = [
			'listAlgorithms' => $this->listAlgorithms( ... ),
			'hashValue' => $this->hashValue( ... ),
		];

		return $this->getEngine()->registerInterface( 'mw.hash.lua', $lib );
	}

	/**
	 * Returns a list of known/ supported hash algorithms
	 *
	 * @return string[][]
	 */
	private function listAlgorithms() {
		$algos = hash_algos();
		$algos = array_combine( range( 1, count( $algos ) ), $algos );

		return [ $algos ];
	}

	/**
	 * Hash a given value.
	 *
	 * @param string $algo
	 * @param string $value
	 * @return string[]
	 * @throws LuaError
	 */
	private function hashValue( $algo, $value ) {
		if ( !in_array( $algo, hash_algos() ) ) {
			throw new LuaError( "Unknown hashing algorithm: $algo" );
		}

		$hash = hash( $algo, $value );

		return [ $hash ];
	}

}
