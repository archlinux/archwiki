<?php

namespace MediaWiki\Extension\AbuseFilter\Parser;

use MediaWiki\Extension\AbuseFilter\Parser\Exception\ExceptionBase;
use MediaWiki\Extension\AbuseFilter\Parser\Exception\UserVisibleWarning;

class RuleCheckerStatus extends ParserStatus {
	/** @var bool */
	private $result;
	/** @var bool */
	private $warmCache;

	/**
	 * @param bool $result Whether the rule matched
	 * @param bool $warmCache Whether we retrieved the AST from cache
	 * @param ExceptionBase|null $excep An exception thrown while parsing, or null if it parsed correctly
	 * @param UserVisibleWarning[] $warnings
	 * @param int $condsUsed
	 */
	public function __construct(
		bool $result,
		bool $warmCache,
		?ExceptionBase $excep,
		array $warnings,
		int $condsUsed
	) {
		parent::__construct( $excep, $warnings, $condsUsed );
		$this->result = $result;
		$this->warmCache = $warmCache;
	}

	public function getResult(): bool {
		return $this->result;
	}

	public function getWarmCache(): bool {
		return $this->warmCache;
	}

	/**
	 * Serialize data for edit stash
	 */
	public function toArray(): array {
		return [
			'result' => $this->result,
			'warmCache' => $this->warmCache,
			'exception' => $this->excep?->toArray(),
			'warnings' => array_map(
				static function ( $warn ) {
					return $warn->toArray();
				},
				$this->warnings
			),
			'condsUsed' => $this->condsUsed,
		];
	}

	/**
	 * Deserialize data from edit stash
	 * @param array $value
	 * @return self
	 */
	public static function fromArray( array $value ): self {
		$excClass = $value['exception']['class'] ?? null;
		return new self(
			$value['result'],
			$value['warmCache'],
			$excClass !== null ? $excClass::fromArray( $value['exception'] ) : null,
			array_map( [ UserVisibleWarning::class, 'fromArray' ], $value['warnings'] ),
			$value['condsUsed']
		);
	}
}
