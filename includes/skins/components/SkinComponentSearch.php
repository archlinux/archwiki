<?php

namespace MediaWiki\Skin;

use Config;
use Html;
use Linker;
use MediaWiki\MainConfigNames;
use Message;
use MessageLocalizer;
use MWException;
use SpecialPage;
use Title;
use User;

/**
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 * http://www.gnu.org/copyleft/gpl.html
 *
 * @internal for use inside Skin and SkinTemplate classes only
 */
class SkinComponentSearch implements SkinComponent {
	/** @var Config */
	private $config;
	/** @var User */
	private $user;
	/** @var MessageLocalizer */
	private $localizer;
	/** @var Title|null */
	private $relevantTitle;
	/** @var Title */
	private $searchTitle;
	/** @var array|null */
	private $cachedData;

	/**
	 * @param Config $config
	 * @param User $user
	 * @param MessageLocalizer $localizer
	 * @param Title|null $searchTitle
	 * @param Title|null $relevantTitle
	 */
	public function __construct(
		Config $config,
		User $user,
		MessageLocalizer $localizer,
		$searchTitle,
		$relevantTitle
	) {
		$this->config = $config;
		$this->user = $user;
		$this->localizer = $localizer;
		$this->searchTitle = $searchTitle ?? SpecialPage::newSearchPage(
			$user
		);
		$this->relevantTitle = $relevantTitle;
		$this->cachedData = null;
	}

	/**
	 * @return Title|null
	 */
	private function getRelevantTitle() {
		return $this->relevantTitle;
	}

	/**
	 * @return MessageLocalizer
	 */
	private function getMessageLocalizer(): MessageLocalizer {
		return $this->localizer;
	}

	/**
	 * @param string $key
	 * @return Message
	 */
	private function msg( string $key ): Message {
		return $this->localizer->msg( $key );
	}

	/**
	 * @return Config
	 */
	private function getConfig(): Config {
		return $this->config;
	}

	/**
	 * @return User
	 */
	private function getUser(): User {
		return $this->user;
	}

	/**
	 * @param array $attrs (optional) will be passed to tooltipAndAccesskeyAttribs
	 *  and decorate the resulting input
	 * @return string of HTML input
	 */
	private function makeSearchInput( array $attrs = [] ) {
		return Html::element( 'input', $this->getSearchInputAttributes( $attrs ) );
	}

	/**
	 * @param string $mode representing the type of button wanted
	 *  either `go` OR `fulltext`.
	 * @param array $attrs (optional)
	 * @throws MWException if bad value of $mode passed in
	 * @return string of HTML button
	 */
	private function makeSearchButton( string $mode, array $attrs = [] ) {
		switch ( $mode ) {
			case 'go':
			case 'fulltext':
				$realAttrs = [
					'type' => 'submit',
					'name' => $mode,
					'value' => $this->msg( $mode == 'go' ? 'searcharticle' : 'searchbutton' )->text(),
				];
				$realAttrs = array_merge(
					$realAttrs,
					Linker::tooltipAndAccesskeyAttribs(
						"search-$mode",
						[],
						null,
						$this->getMessageLocalizer(),
						$this->getUser(),
						$this->getConfig(),
						$this->getRelevantTitle()
					),
					$attrs
				);
				return Html::element( 'input', $realAttrs );
			default:
				throw new MWException( 'Unknown mode passed to ' . __METHOD__ );
		}
	}

	/**
	 * @param array $attrs (optional) will be passed to tooltipAndAccesskeyAttribs
	 *  and decorate the resulting input
	 * @return array attributes of HTML input
	 */
	private function getSearchInputAttributes( array $attrs = [] ) {
		$autoCapHint = $this->getConfig()->get( MainConfigNames::CapitalLinks );
		$realAttrs = [
			'type' => 'search',
			'name' => 'search',
			'placeholder' => $this->msg( 'searchsuggest-search' )->text(),
			'aria-label' => $this->msg( 'searchsuggest-search' )->text(),
			// T251664: Disable autocapitalization of input
			// method when using fully case-sensitive titles.
			'autocapitalize' => $autoCapHint ? 'sentences' : 'none',
		];

		return array_merge(
			$realAttrs,
			Linker::tooltipAndAccesskeyAttribs(
				'search',
				[],
				null,
				$this->getMessageLocalizer(),
				$this->getUser(),
				$this->getConfig(),
				$this->getRelevantTitle()
			),
			$attrs
		);
	}

	/**
	 * @inheritDoc
	 * Since 1.38:
	 * - string html-button-fulltext-attributes HTML attributes for usage on a button
	 *    that redirects user to a search page with the current query.
	 * - string html-button-go-attributes HTML attributes for usage on a search
	 *   button that redirects user to a title that matches the query.
	 * - string html-input-attributes HTML attributes for input on an input field
	 *    that is used to construct a search query.
	 * Since 1.35:
	 * - string form-action Where the form should post to e.g. /w/index.php
	 * - string html-button-search Search button with label
	 *    derived from `html-button-go-attributes`.
	 * - string html-button-search-fallback Search button with label
	 *    derived from `html-button-fulltext-attributes`.
	 * - string html-input An input element derived from `html-input-attributes`.
	 */
	public function getTemplateData(): array {
		// Optimization: Generate once.
		if ( $this->cachedData ) {
			return $this->cachedData;
		}

		$config = $this->getConfig();
		$user = $this->getUser();
		$relevantTitle = $this->getRelevantTitle();
		$localizer = $this->getMessageLocalizer();
		$searchButtonAttributes = [
			'class' => 'searchButton'
		];
		$fallbackButtonAttributes = [
			'class' => 'searchButton mw-fallbackSearchButton'
		];
		$buttonAttributes = [
			'type' => 'submit',
		];

		$searchTitle = $this->searchTitle;

		$inputAttrs = $this->getSearchInputAttributes( [] );
		$goButtonAttributes = $searchButtonAttributes + $buttonAttributes + [
			'name' => 'go',
		] + Linker::tooltipAndAccesskeyAttribs(
			'search-go',
			[],
			null,
			$localizer,
			$user,
			$config,
			$relevantTitle
		);
		$fulltextButtonAttributes = $fallbackButtonAttributes + $buttonAttributes + [
			'name' => 'fulltext'
		] + Linker::tooltipAndAccesskeyAttribs(
			'search-fulltext',
			[],
			null,
			$localizer,
			$user,
			$config,
			$relevantTitle
		);

		$this->cachedData = [
			'search-special-page-title' => $searchTitle->getText(),
			'form-action' => $config->get( MainConfigNames::Script ),
			'html-button-search-fallback' => $this->makeSearchButton(
				'fulltext',
				$fallbackButtonAttributes + [
					'id' => 'mw-searchButton',
				]
			),
			'html-button-search' => $this->makeSearchButton(
				'go',
				$searchButtonAttributes + [
					'id' => 'searchButton',
				]
			),
			'html-input' => $this->makeSearchInput( [ 'id' => 'searchInput' ] ),
			'msg-search' => $this->msg( 'search' )->text(),
			'page-title' => $searchTitle->getPrefixedDBkey(),
			'array-button-go-attributes' => $goButtonAttributes,
			'html-button-go-attributes' => Html::expandAttributes(
				$goButtonAttributes
			),
			'array-button-fulltext-attributes' => $fulltextButtonAttributes,
			'html-button-fulltext-attributes' => Html::expandAttributes(
				$fulltextButtonAttributes
			),
			'array-input-attributes' => $inputAttrs,
			'html-input-attributes' => Html::expandAttributes(
				$inputAttrs
			),
		];
		return $this->cachedData;
	}
}
