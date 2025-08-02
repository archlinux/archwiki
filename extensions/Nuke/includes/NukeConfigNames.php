<?php

// phpcs:disable Generic.NamingConventions.UpperCaseConstantName.ClassConstantNotUpperCase
// phpcs:disable Generic.Files.LineLength.TooLong
namespace MediaWiki\Extension\Nuke;

class NukeConfigNames {

	/**
	 * The maximum age of a new page creation or file upload before it becomes ineligible
	 * for mass deletion. Defaults to the value of $wgRCMaxAge.
	 */
	public const MaxAge = "NukeMaxAge";

	/**
	 * The UI type to use for Special:Nuke. Used to switch between the standard HTMLForm-based
	 * interface and the (as of 2024-12) experimental Codex-based interface. Can be overridden
	 * by a request parameter.
	 *
	 * @since 1.44
	 */
	public const UIType = "NukeUIType";

}
