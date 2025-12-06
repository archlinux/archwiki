<?php

namespace Cite;

/**
 * Render the label for footnote marks, for example "1", "2", …
 *
 * Marks can be customized by group.
 *
 * @license GPL-2.0-or-later
 */
class MarkSymbolRenderer {

	/** @var array<string,string[]> In-memory cache for the cite_link_label_group-… link label lists */
	private array $legacyLinkLabels = [];

	public function __construct(
		// TODO: deprecate the i18n mechanism.
		private readonly ReferenceMessageLocalizer $messageLocalizer,
	) {
	}

	/**
	 * Render the footnote marker symbols
	 *
	 * These are usually a number like "1" or "2.1", unless there is a custom
	 * group with overridden symbols eg "å".
	 *
	 * Uncustomized groups will include the group name to distinguish between eg.
	 * "1" and "g 1".
	 */
	public function renderFootnoteMarkLabel( string $group, int $number, ?int $extendsIndex = null ): string {
		return $this->makeLabel( $group, $number, $extendsIndex, false );
	}

	/**
	 * Render the footnote number with no group name
	 *
	 * Uncustomized groups will not include the group name, eg. "1" for group "g".
	 */
	public function renderFootnoteNumber( string $group, int $number, ?int $extendsIndex = null ): string {
		return $this->makeLabel( $group, $number, $extendsIndex, true );
	}

	private function makeLabel( string $group, int $number, ?int $extendsIndex, bool $suppressGroupName ): string {
		$label = $this->fetchLegacyCustomLinkLabel( $group, $number ) ??
			( $suppressGroupName || $group === Cite::DEFAULT_GROUP ? '' : "$group " ) .
				$this->messageLocalizer->localizeDigits( (string)$number );
		if ( $extendsIndex !== null ) {
			// TODO: design better behavior, especially when using custom group markers.
			$label .= '.' . $this->messageLocalizer->localizeDigits( (string)$extendsIndex );
		}
		return $label;
	}

	/**
	 * Look up the symbol in a literal sequence stored in a local system message override.
	 *
	 * @deprecated since 1.44
	 */
	private function fetchLegacyCustomLinkLabel( string $group, int $number ): ?string {
		if ( $group === Cite::DEFAULT_GROUP ) {
			// TODO: Possibly make the default group configurable, eg. to use a
			// different numeral system than the content language or Western
			// Arabic.
			return null;
		}

		// TODO: deprecate this mechanism.
		$message = "cite_link_label_group-$group";
		if ( !array_key_exists( $group, $this->legacyLinkLabels ) ) {
			$msg = $this->messageLocalizer->msg( $message );
			$this->legacyLinkLabels[$group] = $msg->isDisabled() ? [] : preg_split( '/\s+/', $msg->plain() );
		}

		// Expected behavior for groups without custom labels
		if ( !$this->legacyLinkLabels[$group] ) {
			return null;
		}

		return $this->legacyLinkLabels[$group][$number - 1] ?? null;
	}
}
