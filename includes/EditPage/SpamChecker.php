<?php

namespace MediaWiki\EditPage;

/**
 * Service to check if text (either content or a summary) qualifies as spam
 *
 * Text qualifies as spam if it matches the global $wgSpamRegex
 * Summaries qualify as spam if they match the global $wgSummarySpamRegex
 *
 * @author DannyS712
 * @since 1.35
 */
class SpamChecker {

	/**
	 * @param string[] $spamRegex
	 * @param string[] $summaryRegex
	 */
	public function __construct(
		private readonly array $spamRegex,
		private readonly array $summaryRegex
	) {
	}

	/**
	 * Check whether content text is considered spam
	 *
	 * @return string|false Matching string or false
	 */
	public function checkContent( string $text ): string|false {
		return self::checkInternal( $text, $this->spamRegex );
	}

	/**
	 * Check whether summary text is considered spam
	 *
	 * @return string|false Matching string or false
	 */
	public function checkSummary( string $summary ): string|false {
		return self::checkInternal( $summary, $this->summaryRegex );
	}

	private static function checkInternal( string $text, array $regexes ): string|false {
		foreach ( $regexes as $regex ) {
			$matches = [];
			if ( preg_match( $regex, $text, $matches ) ) {
				return $matches[0];
			}
		}
		return false;
	}
}
