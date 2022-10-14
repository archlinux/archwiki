<?php

namespace MediaWiki\Extension\AbuseFilter\LogFormatter;

use LogFormatter;
use MediaWiki\Extension\AbuseFilter\AbuseFilterServices;
use Message;
use SpecialPage;

/**
 * This class formats abuse log notifications.
 *
 * Uses logentry-abusefilter-hit
 */
class AbuseLogHitFormatter extends LogFormatter {

	/**
	 * @return array
	 */
	protected function getMessageParameters() {
		$entry = $this->entry->getParameters();
		$linkRenderer = $this->getLinkRenderer();
		$params = parent::getMessageParameters();

		$filter_title = SpecialPage::getTitleFor( 'AbuseFilter', $entry['filter'] );
		$filter_caption = $this->msg( 'abusefilter-log-detailedentry-local' )
			->params( $entry['filter'] )
			->text();
		$log_title = SpecialPage::getTitleFor( 'AbuseLog', $entry['log'] );
		$log_caption = $this->msg( 'abusefilter-log-detailslink' )->text();

		$params[4] = $entry['action'];

		if ( $this->plaintext ) {
			$params[3] = '[[' . $filter_title->getPrefixedText() . '|' . $filter_caption . ']]';
			$params[8] = '[[' . $log_title->getPrefixedText() . '|' . $log_caption . ']]';
		} else {
			$params[3] = Message::rawParam( $linkRenderer->makeLink(
				$filter_title,
				$filter_caption
			) );
			$params[8] = Message::rawParam( $linkRenderer->makeLink(
				$log_title,
				$log_caption
			) );
		}

		$actions_takenRaw = $entry['actions'];
		if ( !strlen( trim( $actions_takenRaw ) ) ) {
			$actions_taken = $this->msg( 'abusefilter-log-noactions' );
		} else {
			$actions = explode( ',', $actions_takenRaw );
			$displayActions = [];

			$specsFormatter = AbuseFilterServices::getSpecsFormatter();
			$specsFormatter->setMessageLocalizer( $this->context );
			foreach ( $actions as $action ) {
				$displayActions[] = $specsFormatter->getActionDisplay( $action );
			}
			$actions_taken = $this->context->getLanguage()->commaList( $displayActions );
		}
		$params[5] = Message::rawParam( $actions_taken );

		// Bad things happen if the numbers are not in correct order
		ksort( $params );

		return $params;
	}
}
