<?php
/**
 * Content object for CSS pages.
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
 * @since 1.21
 *
 * @file
 * @ingroup Content
 *
 * @author Daniel Kinzler
 */

/**
 * Content object for CSS pages.
 *
 * @newable
 * @ingroup Content
 */
class CssContent extends TextContent {

	/**
	 * @var bool|Title|null
	 */
	private $redirectTarget = false;

	/**
	 * @stable to call
	 * @param string $text CSS code.
	 * @param string $modelId the content content model
	 */
	public function __construct( $text, $modelId = CONTENT_MODEL_CSS ) {
		parent::__construct( $text, $modelId );
	}

	/**
	 * @param Title $target
	 * @return CssContent
	 */
	public function updateRedirect( Title $target ) {
		if ( !$this->isRedirect() ) {
			return $this;
		}

		// @phan-suppress-next-line PhanTypeMismatchReturnSuperType False positive
		return $this->getContentHandler()->makeRedirectContent( $target );
	}

	/**
	 * @return Title|null
	 */
	public function getRedirectTarget() {
		if ( $this->redirectTarget !== false ) {
			return $this->redirectTarget;
		}
		$this->redirectTarget = null;
		$text = $this->getText();
		if ( strpos( $text, '/* #REDIRECT */' ) === 0 ) {
			// Extract the title from the url
			if ( preg_match( '/title=(.*?)&action=raw/', $text, $matches ) ) {
				$title = Title::newFromText( urldecode( $matches[1] ) );
				if ( $title ) {
					// Have a title, check that the current content equals what
					// the redirect content should be
					if ( $this->equals( $this->getContentHandler()->makeRedirectContent( $title ) ) ) {
						$this->redirectTarget = $title;
					}
				}
			}
		}

		return $this->redirectTarget;
	}

}
