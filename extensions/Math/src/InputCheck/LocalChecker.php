<?php

namespace MediaWiki\Extension\Math\InputCheck;

use Exception;
use MediaWiki\Extension\Math\TexVC\TexVC;
use Message;
use WANObjectCache;

class LocalChecker extends BaseChecker {

	public const VERSION = 1;
	private const VALID_TYPES = [ 'tex', 'inline-tex', 'chem' ];
	private ?Message $error = null;
	private ?string $mathMl = null;

	private string $type;
	private WANObjectCache $cache;

	private bool $isChecked = false;

	public function __construct( WANObjectCache $cache, $tex = '', string $type = 'tex' ) {
		$this->cache = $cache;
		parent::__construct( $tex );
		$this->type = $type;
	}

	public function isValid(): bool {
		$this->run();
		return parent::isValid();
	}

	public function getValidTex(): ?string {
		$this->run();
		return parent::getValidTex();
	}

	public function run() {
		if ( $this->isChecked ) {
			return;
		}
		if ( !in_array( $this->type, self::VALID_TYPES, true ) ) {
			$this->error = $this->errorObjectToMessage(
				(object)[ "error" => "Unsupported type passed to LocalChecker: " . $this->type ], "LocalCheck" );
			return;
		}
		try {
			$result = $this->cache->getWithSetCallback(
				$this->getInputCacheKey(),
				WANObjectCache::TTL_INDEFINITE,
				[ $this, 'runCheck' ],
				[ 'version' => self::VERSION ],
			);
		} catch ( Exception $e ) { // @codeCoverageIgnoreStart
			// This is impossible since errors are thrown only if the option debug would be set.
			$this->error = Message::newFromKey( 'math_failure' );
			return;
			// @codeCoverageIgnoreEnd
		}
		if ( $result['status'] === '+' ) {
			$this->isValid = true;
			$this->validTeX = $result['output'];
			$this->mathMl = $result['mathml'];
		} else {
			$this->error = $this->errorObjectToMessage(
				(object)[ "error" => (object)$result["error"] ],
				"LocalCheck" );
		}
		$this->isChecked = true;
	}

	/**
	 * Returns the string of the last error.
	 * @return ?Message
	 */
	public function getError(): ?Message {
		$this->run();
		return $this->error;
	}

	public function getPresentationMathMLFragment(): ?string {
		$this->run();
		return $this->mathMl;
	}

	public function getInputCacheKey(): string {
		return $this->cache->makeGlobalKey(
			self::class,
			md5( $this->type . '-' . $this->inputTeX )
		);
	}

	public function runCheck(): array {
		$options = $this->type === 'chem' ? [ "usemhchem" => true ] : null;
		try {
			$result = ( new TexVC() )->check( $this->inputTeX, $options );
		} catch ( Exception $e ) { // @codeCoverageIgnoreStart
			// This is impossible since errors are thrown only if the option debug would be set.
			$this->error = Message::newFromKey( 'math_failure' );

			return [];
			// @codeCoverageIgnoreEnd
		}
		if ( $result['status'] === '+' ) {
			return [
				'status' => '+',
				'output' => $result['output'],
				'mathml' => $result['input']->renderMML()
			];
		} else {
			return [
				'status' => $result['status'],
				'error' => $result['error'],
			];
		}
	}
}
