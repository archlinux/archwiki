<?php
/**
 * Variant of QueryPage which formats the result as a simple link to the page.
 *
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
 * @file
 * @ingroup SpecialPage
 */

use MediaWiki\Html\Html;
use MediaWiki\Linker\Linker;
use MediaWiki\MediaWikiServices;
use MediaWiki\Title\Title;
use Wikimedia\Rdbms\IDatabase;
use Wikimedia\Rdbms\IResultWrapper;

/**
 * Variant of QueryPage which formats the result as a simple link to the page
 *
 * @stable to extend
 * @ingroup SpecialPage
 */
abstract class PageQueryPage extends QueryPage {

	/** @var ILanguageConverter|null */
	private $languageConverter = null;

	/**
	 * Run a LinkBatch to pre-cache LinkCache information,
	 * like page existence and information for stub color and redirect hints.
	 * This should be done for live data and cached data.
	 *
	 * @stable to override
	 *
	 * @param IDatabase $db
	 * @param IResultWrapper $res
	 */
	public function preprocessResults( $db, $res ) {
		$this->executeLBFromResultWrapper( $res );
	}

	/**
	 * @since 1.36
	 * @param ILanguageConverter $languageConverter
	 */
	final protected function setLanguageConverter( ILanguageConverter $languageConverter ) {
		$this->languageConverter = $languageConverter;
	}

	/**
	 * @note Call self::setLanguageConverter in your constructor when overriding
	 *
	 * @since 1.36
	 * @return ILanguageConverter
	 */
	final protected function getLanguageConverter(): ILanguageConverter {
		if ( $this->languageConverter === null ) {
			// Fallback if not provided
			// TODO Change to wfWarn in a future release
			$this->languageConverter = MediaWikiServices::getInstance()->getLanguageConverterFactory()
				->getLanguageConverter( $this->getContentLanguage() );
		}
		return $this->languageConverter;
	}

	/**
	 * Format the result as a simple link to the page
	 *
	 * @stable to override
	 *
	 * @param Skin $skin
	 * @param stdClass $row Result row
	 * @return string
	 */
	public function formatResult( $skin, $row ) {
		$title = Title::makeTitleSafe( $row->namespace, $row->title );
		if ( $title instanceof Title ) {

			$text = $this->getLanguageConverter()->convertHtml( $title->getPrefixedText() );
			return $this->getLinkRenderer()->makeLink( $title, new HtmlArmor( $text ) );
		} else {
			return Html::element( 'span', [ 'class' => 'mw-invalidtitle' ],
				Linker::getInvalidTitleDescription( $this->getContext(), $row->namespace, $row->title ) );
		}
	}
}
