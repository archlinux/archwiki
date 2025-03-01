<?php
/**
 * Our override of the built-in Echo helper for displaying section titles.
 *
 * @file
 * @ingroup Extensions
 * @license MIT
 */

namespace MediaWiki\Extension\DiscussionTools\Notifications;

use MediaWiki\Extension\Notifications\DiscussionParser;
use MediaWiki\Extension\Notifications\Formatters\EchoPresentationModelSection;
use RuntimeException;

/**
 * Built-in Echo events store section titles as wikitext, and when displaying or linking to them,
 * they parse it and then strip the formatting to get the plaintext versions.
 *
 * Our subscription notifications store section titles as plaintext already, so this processing is
 * unnecessary and incorrect (text that looks like markup can disappear).
 */
class PlaintextEchoPresentationModelSection extends EchoPresentationModelSection {

	/**
	 * @inheritDoc
	 */
	protected function getParsedSectionTitle() {
		$plaintext = $this->getRawSectionTitle();
		if ( !$plaintext ) {
			return false;
		}
		$plaintext = trim( $plaintext );
		return $this->language->truncateForVisual( $plaintext, DiscussionParser::DEFAULT_SNIPPET_LENGTH );
	}

	/**
	 * @inheritDoc
	 */
	public function getTitleWithSection() {
		$title = $this->event->getTitle();
		if ( $title === null ) {
			throw new RuntimeException( 'Event #' . $this->event->getId() . ' with no title' );
		}
		$section = $this->getParsedSectionTitle();
		if ( $section ) {
			$title = $title->createFragmentTarget( $section );
		}
		return $title;
	}

	/**
	 * Get truncated section title, according to user's language
	 * or a placeholder text if the section title is not available.
	 *
	 * @return string
	 */
	public function getTruncatedSectionTitle() {
		if ( $this->exists() ) {
			return parent::getTruncatedSectionTitle();
		}

		return wfMessage( 'discussiontools-notification-topic-hidden' )->inLanguage( $this->language )->text();
	}
}
