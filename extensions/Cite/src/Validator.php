<?php

namespace Cite;

use MediaWiki\Parser\Sanitizer;
use StatusValue;

/**
 * Context-aware, detailed validation of the arguments and content of <ref> and <references> tags.
 *
 * @license GPL-2.0-or-later
 */
class Validator {

	/**
	 * @param string|null $inReferencesGroup Group name of the <references> context to consider
	 *  during validation. Null if we are currently not in a <references> context.
	 */
	public function __construct(
		private readonly ?string $inReferencesGroup,
	) {
	}

	/**
	 * @param array<string|int,?string> $argv The original arguments from the <ref …> tag
	 * @param string[] $allowedArguments
	 * @return StatusValue<array<string,mixed>> Always returns the complete dictionary of allowed
	 *  argument names and values. Missing arguments are present, but null. Invalid arguments are
	 *  stripped.
	 */
	private static function filterArguments( array $argv, array $allowedArguments ): StatusValue {
		$expected = count( $allowedArguments );
		// Constructs a map of all expected arguments, followed by invalid arguments
		$allValues = array_merge( array_fill_keys( $allowedArguments, null ), $argv );

		$status = StatusValue::newGood( array_slice( $allValues, 0, $expected ) );

		// Unexpected arguments after the expected ones, hence the message "too many"
		if ( count( $allValues ) > $expected ) {
			$badArguments = array_keys( array_slice( $allValues, $expected ) );
			$firstBadArgument = $badArguments[0];
			$closestMatch = self::closestMatch( $firstBadArgument, $allowedArguments );

			// Use different error messages for <ref> vs. <references> (cannot have a name)
			$forReferenceList = !in_array( 'name', $allowedArguments, true );
			if ( $closestMatch && !$forReferenceList ) {
				$status->warning( 'cite_error_ref_parameter_suggestion',
					$firstBadArgument,
					$closestMatch
				);
			} else {
				sort( $allowedArguments );
				$status->warning( $forReferenceList ?
					'cite_error_references_invalid_parameters' :
					'cite_error_ref_too_many_keys',
					$firstBadArgument,
					implode( ', ', $allowedArguments )
				);
			}
		}

		return $status;
	}

	/**
	 * Tries to find a word that is as close as possible to the user input, from a list of
	 * possibilities. The minimum Levenshtein distance is used to find the closest match, see
	 * https://en.wikipedia.org/wiki/Levenshtein_distance. This is expensive but reasonable here
	 * because the strings and arrays are all very short.
	 *
	 * @param string $badArgument The user input
	 * @param string[] $suggestions Possible suggestions
	 * @return string|null Null when no meaningful suggestion can be made
	 */
	private static function closestMatch( string $badArgument, array $suggestions ): ?string {
		$badArgument = strtolower( trim( $badArgument ) );

		// Stop early when character-by-character comparisons are going to be pointless. The
		// longest possible answer is "responsive" (10 characters).
		if ( strlen( $badArgument ) > 20 ) {
			return null;
		}

		$bestMatch = '';
		$bestDistance = 0;
		foreach ( $suggestions as $suggestion ) {
			$distance = levenshtein( $badArgument, $suggestion );
			// It's not going to become better
			if ( $distance <= 1 ) {
				return $suggestion;
			}
			if ( !$bestMatch || $distance < $bestDistance ) {
				$bestMatch = $suggestion;
				$bestDistance = $distance;
			}
		}
		// It's probably not a useful suggestion if not even half of it is the same
		return $bestDistance <= strlen( $bestMatch ) / 2 ? $bestMatch : null;
	}

	/**
	 * Filters the raw <ref> arguments and turns them into a predictable format with all
	 * elements guaranteed to be present.
	 *
	 * @param array<string|int,?string> $argv The original arguments from the <references …> tag
	 * @param bool $isSubreferenceSupported Temporary feature flag
	 * @return StatusValue<array<string,mixed>> Always returns the complete dictionary of allowed
	 *  argument names and values. Missing arguments are present, but null. Invalid arguments are
	 *  stripped.
	 */
	public static function filterRefArguments(
		array $argv,
		bool $isSubreferenceSupported = false
	): StatusValue {
		$allowedArguments = [ 'group', 'name', 'follow', 'dir' ];
		if ( $isSubreferenceSupported ) {
			$allowedArguments[] = 'details';
		}
		return self::filterArguments( $argv, $allowedArguments );
	}

	/**
	 * Filters the raw <references> arguments and turns them into a predictable format with all
	 * elements guaranteed to be present.
	 *
	 * @param array<string|int,?string> $argv The original arguments from the <references …> tag
	 * @return StatusValue<array<string,mixed>> Always returns the complete dictionary of
	 *  allowed argument names and values. Missing arguments are present, but null. Invalid
	 *  arguments are stripped.
	 */
	public static function filterReferenceListArguments( array $argv ): StatusValue {
		$status = self::filterArguments( $argv, [ 'group', 'responsive' ] );
		/** @var array<string,string|null> $arguments */
		$arguments = $status->getValue();
		if ( $arguments['responsive'] !== null ) {
			// All strings including the empty string mean enabled, only "0" means disabled
			$status->value['responsive'] = $arguments['responsive'] !== '0';
		}
		return $status;
	}

	/**
	 * Collection of all validation and sanitization steps that can be done in place while parsing
	 * a <ref> tag. This excludes failures like "there was never a <ref name=… /> with content" that
	 * can only be decided at the very end.
	 *
	 * @param string|null $text
	 * @param array{group: ?string, name: ?string, follow: ?string, dir: ?string, details: ?string} $arguments
	 * @return StatusValue<array<string,mixed>> Returns a sanitized version of the dictionary of
	 *  argument names and values. Some errors are fatals, meaning the <ref> tag shouldn't be used.
	 *  Some are warnings.
	 */
	public function validateRef( ?string $text, array $arguments ): StatusValue {
		$status = StatusValue::newGood();

		// Use the default group, or the references group when inside one
		$arguments['group'] ??= $this->inReferencesGroup ?? Cite::DEFAULT_GROUP;

		// We never care about the difference between empty name="" and non-existing attribute
		$name = (string)$arguments['name'];
		$follow = (string)$arguments['follow'];

		// Disallow numeric names to avoid confusion with global ids
		if ( ctype_digit( $name ) ) {
			$arguments['name'] = null;
			$status->warning( 'cite_error_ref_numeric_key' );
		} elseif ( ctype_digit( $follow ) ) {
			$status->fatal( 'cite_error_ref_numeric_key' );
		}

		// The details attribute is a marker and shouldn't be ignored, even if empty
		if ( $follow && ( $name || isset( $arguments['details'] ) ) ) {
			$status->fatal( 'cite_error_ref_follow_conflicts' );
		}

		if ( isset( $arguments['dir'] ) ) {
			$lowerDir = strtolower( $arguments['dir'] );
			if ( $lowerDir !== 'rtl' && $lowerDir !== 'ltr' ) {
				$lowerDir = null;
				$status->warning( 'cite_error_ref_invalid_dir', $arguments['dir'] );
			}
			$arguments['dir'] = $lowerDir;
		}

		$sanitized = $this->inReferencesGroup === null ?
			$this->validateRefBeforeReferenceList( $text, $arguments ) :
			$this->validateRefInReferenceList( $text, $arguments );
		return $status->merge( $sanitized, true );
	}

	/**
	 * Validation steps specific to <ref> tags outside of (more specifically *before*) a
	 * <references> tag.
	 *
	 * @return StatusValue<array<string,mixed>> Sanitized arguments
	 */
	private function validateRefBeforeReferenceList( ?string $text, array $arguments ): StatusValue {
		$status = StatusValue::newGood();

		$name = (string)$arguments['name'];

		if ( !$name ) {
			$isSelfClosingTag = $text === null;
			$containsText = trim( $text ?? '' ) !== '';
			// The details attribute is a marker and shouldn't be ignored, even if empty
			if ( isset( $arguments['details'] ) && !$containsText ) {
				$status->fatal( 'cite_error_details_missing_parent' );
			} elseif ( ( $arguments['details'] ?? '' ) !== '' ) {
				// References with details must have a name.
				$status->warning( 'cite_error_ref_with_details_no_name' );
				$arguments['details'] = null;
			} elseif ( $isSelfClosingTag ) {
				// Completely empty ref like <ref /> is forbidden.
				$status->fatal( 'cite_error_ref_no_key' );
			} elseif ( !$containsText ) {
				// Must have content or reuse another ref by name.
				$status->fatal( 'cite_error_ref_no_input' );
			}
		}

		if ( $text !== null && preg_match(
				'/<ref(erences)?\b[^>]*+>/i',
				preg_replace( '#<(\w++)[^>]*+>.*?</\1\s*>|<!--.*?-->#s', '', $text )
			) ) {
			// (bug T8199) This most likely implies that someone left off the
			// closing </ref> tag, which will cause the entire article to be
			// eaten up until the next <ref>.  So we bail out early instead.
			// The fancy regex above first tries chopping out anything that
			// looks like a comment or SGML tag, which is a crude way to avoid
			// false alarms for <nowiki>, <pre>, etc.
			//
			// Possible improvement: print the warning, followed by the contents
			// of the <ref> tag.  This way no part of the article will be eaten
			// even temporarily.
			$status->fatal( 'cite_error_included_ref' );
		}

		// Return the sanitized set of <ref …> arguments
		$status->value = $arguments;
		return $status;
	}

	/**
	 * Validation steps specific to <ref> tags inside of a <references> tag.
	 *
	 * @return StatusValue<array<string,mixed>> Sanitized arguments
	 */
	private function validateRefInReferenceList( ?string $text, array $arguments ): StatusValue {
		$status = StatusValue::newGood();

		$group = $arguments['group'];
		$name = (string)$arguments['name'];

		// The details attribute is a marker and shouldn't be ignored, even if empty
		if ( isset( $arguments['details'] ) ) {
			$arguments['details'] = null;
			$status->warning( 'cite_error_details_unsupported_context',
				Sanitizer::safeEncodeAttribute( $name )
			);
		}

		if ( $group !== $this->inReferencesGroup ) {
			// <ref> and <references> have conflicting group attributes.
			$arguments['group'] = $this->inReferencesGroup;
			$status->warning( 'cite_error_references_group_mismatch',
				Sanitizer::safeEncodeAttribute( $group )
			);
		}

		if ( !$name ) {
			// <ref> calls inside <references> must be named
			$status->fatal( 'cite_error_references_no_key' );
		}

		if ( $text === null || trim( $text ) === '' ) {
			// <ref> called in <references> has no content.
			$status->fatal( 'cite_error_empty_references_define',
				Sanitizer::safeEncodeAttribute( $name ),
				Sanitizer::safeEncodeAttribute( $group )
			);
		}

		// Return the sanitized set of <ref …> arguments
		$status->value = $arguments;
		return $status;
	}

	public function validateListDefinedRefUsage( ?string $name, bool $isKnownName ): StatusValue {
		$status = StatusValue::newGood();

		if ( $this->inReferencesGroup !== null && $name && !$isKnownName ) {
			// No such named ref exists in this group.
			$status->fatal( 'cite_error_references_missing_key',
				Sanitizer::safeEncodeAttribute( $name )
			);
		}

		return $status;
	}

}
