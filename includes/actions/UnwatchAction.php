<?php
/**
 * Performs the unwatch actions on a page
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA
 *
 * @file
 * @ingroup Actions
 */

use MediaWiki\Watchlist\WatchlistManager;

/**
 * Page removal from a user's watchlist
 *
 * @ingroup Actions
 */
class UnwatchAction extends WatchAction {

	/** @var WatchlistManager */
	private $watchlistManager;

	/**
	 * @param Article $article
	 * @param IContextSource $context
	 * @param WatchlistManager $watchlistManager
	 * @param WatchedItemStore $watchedItemStore
	 */
	public function __construct(
		Article $article,
		IContextSource $context,
		WatchlistManager $watchlistManager,
		WatchedItemStore $watchedItemStore
	) {
		parent::__construct( $article, $context, $watchlistManager, $watchedItemStore );
		$this->watchlistManager = $watchlistManager;
	}

	public function getName() {
		return 'unwatch';
	}

	public function onSubmit( $data ) {
		$this->watchlistManager->removeWatch(
			$this->getAuthority(),
			$this->getTitle()
		);

		return true;
	}

	protected function getFormFields() {
		return [
			'intro' => [
				'type' => 'info',
				'raw' => true,
				'default' => $this->msg( 'confirm-unwatch-top' )->parse()
			]
		];
	}

	protected function alterForm( HTMLForm $form ) {
		parent::alterForm( $form );
		$form->setWrapperLegendMsg( 'removewatch' );
		$form->setSubmitTextMsg( 'confirm-unwatch-button' );
	}

	public function onSuccess() {
		$msgKey = $this->getTitle()->isTalkPage() ? 'removedwatchtext-talk' : 'removedwatchtext';
		$this->getOutput()->addWikiMsg( $msgKey, $this->getTitle()->getPrefixedText() );
	}

	public function doesWrites() {
		return true;
	}
}
