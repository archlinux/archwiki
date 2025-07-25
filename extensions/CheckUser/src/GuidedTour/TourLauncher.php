<?php

namespace MediaWiki\CheckUser\GuidedTour;

use HtmlArmor;
use MediaWiki\CheckUser\Investigate\SpecialInvestigate;
use MediaWiki\Extension\GuidedTour\GuidedTourLauncher;
use MediaWiki\Linker\LinkRenderer;
use MediaWiki\Linker\LinkTarget;
use MediaWiki\Registration\ExtensionRegistry;

class TourLauncher {
	private ExtensionRegistry $extensionRegistry;
	private LinkRenderer $linkRenderer;

	public function __construct(
		ExtensionRegistry $extensionRegistry,
		LinkRenderer $linkRenderer
	) {
		$this->extensionRegistry = $extensionRegistry;
		$this->linkRenderer = $linkRenderer;
	}

	/**
	 * Launch a Guided Tour if the extension is loaded.
	 *
	 * @see GuidedTourLauncher::launchTour
	 *
	 * @param string $tourName
	 * @param string $step
	 */
	public function launchTour( string $tourName, string $step ): void {
		if ( !$this->extensionRegistry->isLoaded( 'GuidedTour' ) ) {
			return;
		}

		GuidedTourLauncher::launchTour( $tourName, $step );
	}

	/**
	 * Creates a link which can be used to reset the visited status of a tour and
	 * cause the next page load to show the tour.
	 *
	 * @param string $tourName One of SpecialInvestigate::TOUR_*
	 * @param LinkTarget $target
	 * @param string|HtmlArmor|null $text
	 * @param array $extraAttribs
	 * @param array $query
	 * @return string HTML
	 */
	public function makeTourLink(
		string $tourName,
		LinkTarget $target,
		$text = null,
		array $extraAttribs = [],
		array $query = []
	): string {
		if ( !$this->extensionRegistry->isLoaded( 'GuidedTour' ) ) {
			return '';
		}

		if ( !isset( $extraAttribs['class'] ) ) {
			$extraAttribs['class'] = [];
		} elseif ( !is_array( $extraAttribs['class'] ) ) {
			$extraAttribs['class'] = [ $extraAttribs['class'] ];
		}
		$extraAttribs['class'][] = 'ext-checkuser-investigate-reset-guided-tour';
		if ( $tourName === SpecialInvestigate::TOUR_INVESTIGATE_FORM ) {
			$extraAttribs['class'][] = 'ext-checkuser-investigate-reset-form-guided-tour';
		}

		return $this->linkRenderer->makeLink(
			$target,
			$text,
			$extraAttribs,
			$query
		);
	}
}
