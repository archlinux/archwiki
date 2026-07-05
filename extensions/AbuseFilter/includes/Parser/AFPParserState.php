<?php

namespace MediaWiki\Extension\AbuseFilter\Parser;

class AFPParserState {
	public function __construct(
		public readonly AFPToken $token,
		public readonly int $pos
	) {
	}
}
