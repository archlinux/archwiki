<?php

namespace MediaWiki\Extension\Notifications\Formatters;

use MediaWiki\Extension\Notifications\Special\SpecialNotificationsMarkRead;
use MediaWiki\Html\Html;
use MediaWiki\Output\OutputPage;
use MediaWiki\Utils\MWTimestamp;
use OOUI\IconWidget;

/**
 * A formatter for Special:Notifications
 *
 * This formatter uses OOUI libraries. Any calls to this formatter must
 * also call OutputPage::enableOOUI() before calling this formatter.
 */
class SpecialNotificationsFormatter extends EchoEventFormatter {
	protected function formatModel( EchoEventPresentationModel $model ) {
		$markReadSpecialPage = new SpecialNotificationsMarkRead();
		$id = $model->getEventId();

		$icon = Html::element(
			'img',
			[
				'class' => 'mw-echo-icon',
				'src' => $this->getIconUrl( $model ),
			]
		);

		OutputPage::setupOOUI();

		$markAsReadIcon = new IconWidget( [
			'icon' => 'close',
			'title' => wfMessage( 'echo-notification-markasread' )->text(),
		] );

		$markAsReadForm = $markReadSpecialPage->getMinimalForm(
			$id,
			$this->msg( 'echo-notification-markasread' )->text(),
			false,
			$markAsReadIcon->toString()
		);

		$markAsReadButton = Html::rawElement(
			'div',
			[ 'class' => 'mw-echo-markAsReadButton' ],
			// First submission attempt
			$markAsReadForm->prepareForm()->getHTML( false )
		);

		$html = Html::rawElement(
			'div',
			[ 'class' => 'mw-echo-title' ],
			$model->getHeaderMessage()->parse()
		) . "\n";

		$body = $model->getBodyMessage();
		if ( $body ) {
			$html .= Html::element(
				'div',
				[ 'class' => 'mw-echo-payload' ],
				$body->text()
			) . "\n";
		}

		$ts = $this->language->getHumanTimestamp(
			new MWTimestamp( $model->getTimestamp() ),
			null,
			$this->user
		);

		$footerItems = [ Html::element( 'span', [ 'class' => 'mw-echo-notification-footer-element' ], $ts ) ];

		// Add links to the footer, primary goes first, then secondary ones
		$links = [];
		$primaryLink = $model->getPrimaryLinkWithMarkAsRead();
		if ( $primaryLink !== false ) {
			$links[] = $primaryLink;
		}
		$links = array_merge( $links, array_filter( $model->getSecondaryLinks() ) );
		foreach ( $links as $link ) {
			$footerAttributes = [
				'href' => $link['url'],
				'class' => 'mw-echo-notification-footer-element',
			];

			if ( isset( $link['tooltip'] ) ) {
				$footerAttributes['title'] = $link['tooltip'];
			}

			$footerItems[] = Html::element(
				'a',
				$footerAttributes,
				$link['label']
			);
		}

		$pipe = wfMessage( 'pipe-separator' )->inLanguage( $this->language )->text();
		$html .= Html::rawElement(
			'div',
			[ 'class' => 'mw-echo-notification-footer' ],
			implode(
				Html::element( 'span', [ 'class' => 'mw-echo-notification-footer-element' ], $pipe ),
				$footerItems
			)
		) . "\n";

		return Html::rawElement( 'div', [ 'class' => 'mw-echo-state' ],
			$markAsReadButton .
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
