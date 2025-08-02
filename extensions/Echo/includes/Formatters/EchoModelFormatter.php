<?php

namespace MediaWiki\Extension\Notifications\Formatters;

use MediaWiki\MediaWikiServices;

/**
 * A formatter for the notification flyout popup. Just the bare data needed to
 * render everything client-side.
 */
class EchoModelFormatter extends EchoEventFormatter {

	protected function formatModel( EchoEventPresentationModel $model ): array {
		$data = $model->jsonSerialize();
		$data['iconUrl'] = EchoIcon::getUrl( $model->getIconType(), $this->language->getDir() );

		$urlUtils = MediaWikiServices::getInstance()->getUrlUtils();

		if ( isset( $data['links']['primary']['url'] ) ) {
			$data['links']['primary']['url'] = $urlUtils->expand( $data['links']['primary']['url'] );
		}

		// @phan-suppress-next-line PhanTypePossiblyInvalidDimOffset
		foreach ( $data['links']['secondary'] as &$link ) {
			$link['url'] = $urlUtils->expand( $link['url'] ?? '' );
		}
		unset( $link );

		$bundledIds = $model->getBundledIds();
		if ( $bundledIds ) {
			$data[ 'bundledIds' ] = $bundledIds;
		}

		return $data;
	}
}
