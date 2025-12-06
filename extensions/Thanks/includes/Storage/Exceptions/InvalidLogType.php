<?php

namespace MediaWiki\Extension\Thanks\Storage\Exceptions;

use Exception;

class InvalidLogType extends Exception {
	public function __construct(
		private readonly string $logType,
	) {
		parent::__construct();
	}

	public function getLogType(): string {
		return $this->logType;
	}
}
