<?php

namespace MediaWiki\Extension\Notifications\Formatters;

use MediaWiki\Html\Html;
use MediaWiki\Utils\MWTimestamp;

/**
 * A formatter for the notification flyout popup
 *
 * Ideally we wouldn't need this, and we'd just pass the
 * presentation model to the client, but we need to continue
 * sending HTML for backwards compatibility.
 */
class EchoFlyoutFormatter extends EchoEventFormatter {
	protected function formatModel( EchoEventPresentationModel $model ) {
		$icon = Html::element(
			'img',
			[
				'class' => 'mw-echo-icon',
				'src' => $this->getIconUrl( $model ),
			]
		);

		$html = Html::rawElement(
			'div',
			[ 'class' => 'mw-echo-title' ],
			$model->getHeaderMessage()->parse()
		) . "\n";

		$body = $model->getBodyMessage();
		if ( $body ) {
			$html .= Html::rawElement(
				'div',
				[ 'class' => 'mw-echo-payload' ],
				$body->parse()
			) . "\n";
		}

		$ts = $this->language->getHumanTimestamp(
			new MWTimestamp( $model->getTimestamp() ),
			null,
			$this->user
		);

		$footerItems = [ $ts ];
		$secondaryLinks = array_filter( $model->getSecondaryLinks() );
		foreach ( $secondaryLinks as $link ) {
			$footerItems[] = Html::element( 'a', [ 'href' => $link['url'] ], $link['label'] );
		}
		$html .= Html::rawElement(
			'div',
			[ 'class' => 'mw-echo-notification-footer' ],
			$this->language->pipeList( $footerItems )
		) . "\n";

		// Add the primary link afterwards, if it has one
		$primaryLink = $model->getPrimaryLinkWithMarkAsRead();
		if ( $primaryLink !== false ) {
			$html .= Html::element(
				'a',
				[ 'class' => 'mw-echo-notification-primary-link', 'href' => $primaryLink['url'] ],
				$primaryLink['label']
			) . "\n";
		}

		return Html::rawElement( 'div', [ 'class' => 'mw-echo-state' ],
			$icon .
			Html::rawElement( 'div', [ 'class' => 'mw-echo-content' ], $html )
		);
	}

	private function getIconUrl( EchoEventPresentationModel $model ) {
		return EchoIcon::getUrl(
			$model->getIconType(),
			$this->language->getDir()
		);
	}

}
