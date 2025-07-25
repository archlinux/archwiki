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

	private ReferenceMessageLocalizer $messageLocalizer;

	public function __construct(
		ReferenceMessageLocalizer $messageLocalizer
	) {
		// TODO: deprecate the i18n mechanism.
		$this->messageLocalizer = $messageLocalizer;
	}

	public function makeLabel( string $group, int $number, ?int $extendsIndex = null ): string {
		$label = $this->fetchLegacyCustomLinkLabel( $group, $number ) ??
		$this->makeDefaultLabel( $group, $number );
		if ( $extendsIndex !== null ) {
			// TODO: design better behavior, especially when using custom group markers.
			$label .= '.' . $this->messageLocalizer->localizeDigits( (string)$extendsIndex );
		}
		return $label;
	}

	private function makeDefaultLabel( string $group, int $number ): string {
		$label = $this->messageLocalizer->localizeDigits( (string)$number );
		return $group === Cite::DEFAULT_GROUP ? $label : "$group $label";
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
