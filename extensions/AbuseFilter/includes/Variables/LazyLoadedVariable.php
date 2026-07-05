<?php

namespace MediaWiki\Extension\AbuseFilter\Variables;

class LazyLoadedVariable {

	public function __construct(
		private readonly string $method,
		private readonly array $parameters
	) {
	}

	public function getMethod(): string {
		return $this->method;
	}

	public function getParameters(): array {
		return $this->parameters;
	}
}
