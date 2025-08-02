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

	private ?string $inReferencesGroup;
	private bool $isKnownName;
	private bool $isSectionPreview;

	/**
	 * @param string|null $inReferencesGroup Group name of the <references> context to consider
	 *  during validation. Null if we are currently not in a <references> context.
	 * @param bool $isKnownName
	 * @param bool $isSectionPreview Validation is relaxed when previewing parts of a page
	 */
	public function __construct(
		?string $inReferencesGroup,
		bool $isKnownName,
		bool $isSectionPreview
	) {
		$this->inReferencesGroup = $inReferencesGroup;
		$this->isKnownName = $isKnownName;
		$this->isSectionPreview = $isSectionPreview;
	}

	/**
	 * @param array<string|int,?string> $argv The original arguments from the <ref …> tag
	 * @param string[] $allowedArguments
	 * @return StatusValue Always returns the complete dictionary of allowed argument names and
	 *  values. Missing arguments are present, but null. Invalid arguments are stripped.
	 */
	private static function filterArguments( array $argv, array $allowedArguments ): StatusValue {
		$expected = count( $allowedArguments );
		$allValues = array_merge( array_fill_keys( $allowedArguments, null ), $argv );

		$status = StatusValue::newGood( array_slice( $allValues, 0, $expected ) );

		if ( count( $allValues ) > $expected ) {
			// Use different error messages for <ref> vs. <references> (cannot have a name)
			$isReferencesTag = !in_array( 'name', $allowedArguments, true );
			// TODO: Show at least the first invalid argument name!
			$status->fatal( $isReferencesTag ?
				'cite_error_references_invalid_parameters' :
				'cite_error_ref_too_many_keys'
			);
		}

		return $status;
	}

	/**
	 * Filters the raw <ref> arguments and turns them into a predictable format with all
	 * elements guaranteed to be present.
	 *
	 * @param array<string|int,?string> $argv The original arguments from the <references …> tag
	 * @param bool $isSubreferenceSupported Temporary feature flag
	 * @return StatusValue Always returns the complete dictionary of allowed argument names and
	 *  values. Missing arguments are present, but null. Invalid arguments are stripped.
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
	 * @return StatusValue Always returns the complete dictionary of allowed argument names and
	 *  values. Missing arguments are present, but null. Invalid arguments are stripped.
	 */
	public static function filterReferenceListArguments( array $argv ): StatusValue {
		return self::filterArguments( $argv, [ 'group', 'responsive' ] );
	}

	/**
	 * Collection of all validation and sanitization steps that can be done in place while parsing
	 * a <ref> tag. This excludes failures like "there was never a <ref name=… /> with content" that
	 * can only be decided at the very end.
	 *
	 * @param string|null $text
	 * @param array{group: ?string, name: ?string, follow: ?string, dir: ?string, details: ?string} $arguments
	 * @return StatusValue Returns a sanitized version of the dictionary of argument names and
	 *  values. Some errors are fatals, meaning the <ref> tag shouldn't be used. Some are warnings.
	 */
	public function validateRef( ?string $text, array $arguments ): StatusValue {
		$status = StatusValue::newGood();

		// Use the default group, or the references group when inside one
		$arguments['group'] ??= $this->inReferencesGroup ?? Cite::DEFAULT_GROUP;

		// We never care about the difference between empty name="" and non-existing attribute
		$name = (string)$arguments['name'];
		$follow = (string)$arguments['follow'];

		if ( ctype_digit( $name ) || ctype_digit( $follow ) ) {
			// Numeric names mess up the resulting id's, potentially producing
			// duplicate id's in the XHTML.  The Right Thing To Do
			// would be to mangle them, but it's not really high-priority
			// (and would produce weird id's anyway).
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
			$status->fatal( 'cite_error_references_group_mismatch',
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

		// Section previews are exempt from some rules.
		if ( !$this->isSectionPreview ) {
			if ( !$this->isKnownName ) {
				// No such named ref exists in this group.
				$status->fatal( 'cite_error_references_missing_key',
					Sanitizer::safeEncodeAttribute( $name )
				);
			}
		}

		// Return the sanitized set of <ref …> arguments
		$status->value = $arguments;
		return $status;
	}

}
