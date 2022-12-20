<?php
/**
 * Content object for wiki text pages.
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

use MediaWiki\MainConfigNames;
use MediaWiki\MediaWikiServices;

/**
 * Content object for wiki text pages.
 *
 * @newable
 * @ingroup Content
 */
class WikitextContent extends TextContent {
	private $redirectTargetAndText = null;

	/**
	 * @var string[] flags set by PST
	 */
	private $preSaveTransformFlags = [];

	/**
	 * @stable to call
	 *
	 * @param string $text
	 */
	public function __construct( $text ) {
		parent::__construct( $text, CONTENT_MODEL_WIKITEXT );
	}

	/**
	 * @param string|int $sectionId
	 *
	 * @return Content|bool|null
	 *
	 * @see Content::getSection()
	 */
	public function getSection( $sectionId ) {
		$text = $this->getText();
		$sect = MediaWikiServices::getInstance()->getParserFactory()->getInstance()
			->getSection( $text, $sectionId, false );

		if ( $sect === false ) {
			return false;
		} else {
			return new static( $sect );
		}
	}

	/**
	 * @param string|int|null|bool $sectionId
	 * @param Content $with
	 * @param string $sectionTitle
	 *
	 * @throws MWException
	 * @return Content
	 *
	 * @see Content::replaceSection()
	 */
	public function replaceSection( $sectionId, Content $with, $sectionTitle = '' ) {
		// @phan-suppress-previous-line PhanParamSignatureMismatch False positive
		$myModelId = $this->getModel();
		$sectionModelId = $with->getModel();

		if ( $sectionModelId != $myModelId ) {
			throw new MWException( "Incompatible content model for section: " .
				"document uses $myModelId but " .
				"section uses $sectionModelId." );
		}
		/** @var self $with $oldtext */
		'@phan-var self $with';

		$oldtext = $this->getText();
		$text = $with->getText();

		if ( strval( $sectionId ) === '' ) {
			return $with; # XXX: copy first?
		}

		if ( $sectionId === 'new' ) {
			# Inserting a new section
			$subject = strval( $sectionTitle ) !== '' ? wfMessage( 'newsectionheaderdefaultlevel' )
					->plaintextParams( $sectionTitle )->inContentLanguage()->text() . "\n\n" : '';
			if ( Hooks::runner()->onPlaceNewSection( $this, $oldtext, $subject, $text ) ) {
				$text = strlen( trim( $oldtext ) ) > 0
					? "{$oldtext}\n\n{$subject}{$text}"
					: "{$subject}{$text}";
			}
		} else {
			# Replacing an existing section; roll out the big guns
			$text = MediaWikiServices::getInstance()->getParserFactory()->getInstance()
				->replaceSection( $oldtext, $sectionId, $text );
		}

		$newContent = new static( $text );

		return $newContent;
	}

	/**
	 * Returns a new WikitextContent object with the given section heading
	 * prepended.
	 *
	 * @param string $header
	 *
	 * @return Content
	 */
	public function addSectionHeader( $header ) {
		$text = strval( $header ) !== '' ? wfMessage( 'newsectionheaderdefaultlevel' )
			->plaintextParams( $header )->inContentLanguage()->text() . "\n\n" : '';
		$text .= $this->getText();

		return new static( $text );
	}

	/**
	 * Extract the redirect target and the remaining text on the page.
	 *
	 * @note migrated here from Title::newFromRedirectInternal()
	 *
	 * @since 1.23
	 *
	 * @return array List of two elements: Title|null and string.
	 */
	public function getRedirectTargetAndText() {
		if ( $this->redirectTargetAndText !== null ) {
			return $this->redirectTargetAndText;
		}

		$redir = MediaWikiServices::getInstance()->getMagicWordFactory()->get( 'redirect' );
		$text = ltrim( $this->getText() );
		if ( $redir->matchStartAndRemove( $text ) ) {
			// Extract the first link and see if it's usable
			// Ensure that it really does come directly after #REDIRECT
			// Some older redirects included a colon, so don't freak about that!
			$m = [];
			if ( preg_match( '!^\s*:?\s*\[{2}(.*?)(?:\|.*?)?\]{2}\s*!', $text, $m ) ) {
				// Strip preceding colon used to "escape" categories, etc.
				// and URL-decode links
				if ( strpos( $m[1], '%' ) !== false ) {
					// Match behavior of inline link parsing here;
					$m[1] = rawurldecode( ltrim( $m[1], ':' ) );
				}
				$title = Title::newFromText( $m[1] );
				// If the title is a redirect to bad special pages or is invalid, return null
				if ( !$title instanceof Title || !$title->isValidRedirectTarget() ) {
					$this->redirectTargetAndText = [ null, $this->getText() ];
					return $this->redirectTargetAndText;
				}

				$this->redirectTargetAndText = [ $title, substr( $text, strlen( $m[0] ) ) ];
				return $this->redirectTargetAndText;
			}
		}

		$this->redirectTargetAndText = [ null, $this->getText() ];
		return $this->redirectTargetAndText;
	}

	/**
	 * Implement redirect extraction for wikitext.
	 *
	 * @return Title|null
	 *
	 * @see Content::getRedirectTarget
	 */
	public function getRedirectTarget() {
		list( $title, ) = $this->getRedirectTargetAndText();

		return $title;
	}

	/**
	 * This implementation replaces the first link on the page with the given new target
	 * if this Content object is a redirect. Otherwise, this method returns $this.
	 *
	 * @since 1.21
	 *
	 * @param Title $target
	 *
	 * @return Content
	 *
	 * @see Content::updateRedirect()
	 */
	public function updateRedirect( Title $target ) {
		if ( !$this->isRedirect() ) {
			return $this;
		}

		# Fix the text
		# Remember that redirect pages can have categories, templates, etc.,
		# so the regex has to be fairly general
		$newText = preg_replace( '/ \[ \[  [^\]]*  \] \] /x',
			'[[' . $target->getFullText() . ']]',
			$this->getText(), 1 );

		return new static( $newText );
	}

	/**
	 * Returns true if this content is not a redirect, and this content's text
	 * is countable according to the criteria defined by $wgArticleCountMethod.
	 *
	 * @param bool|null $hasLinks If it is known whether this content contains
	 *    links, provide this information here, to avoid redundant parsing to
	 *    find out (default: null).
	 * @param Title|null $title Optional title, defaults to the title from the current main request.
	 *
	 * @return bool
	 */
	public function isCountable( $hasLinks = null, Title $title = null ) {
		$articleCountMethod = MediaWikiServices::getInstance()->getMainConfig()
			->get( MainConfigNames::ArticleCountMethod );

		if ( $this->isRedirect() ) {
			return false;
		}

		if ( $articleCountMethod === 'link' ) {
			if ( $hasLinks === null ) { # not known, find out
				// @TODO: require an injected title
				if ( !$title ) {
					$context = RequestContext::getMain();
					$title = $context->getTitle();
				}
				$contentRenderer = MediaWikiServices::getInstance()->getContentRenderer();
				// @phan-suppress-next-line PhanTypeMismatchArgumentNullable getTitle does not return null here
				$po = $contentRenderer->getParserOutput( $this, $title, null, null, false );
				$links = $po->getLinks();
				$hasLinks = !empty( $links );
			}

			return $hasLinks;
		}

		return true;
	}

	/**
	 * @param int $maxlength
	 * @return string
	 */
	public function getTextForSummary( $maxlength = 250 ) {
		$truncatedtext = parent::getTextForSummary( $maxlength );

		# clean up unfinished links
		# XXX: make this optional? wasn't there in autosummary, but required for
		# deletion summary.
		$truncatedtext = preg_replace( '/\[\[([^\]]*)\]?$/', '$1', $truncatedtext );

		return $truncatedtext;
	}

	/**
	 * This implementation calls $word->match() on the this TextContent object's text.
	 *
	 * @param MagicWord $word
	 *
	 * @return bool
	 *
	 * @see Content::matchMagicWord()
	 */
	public function matchMagicWord( MagicWord $word ) {
		return $word->match( $this->getText() );
	}

	/**
	 * Records flags set by preSaveTransform
	 * @internal for use by WikitextContentHandler
	 * @param string[] $flags
	 */
	public function setPreSaveTransformFlags( array $flags ) {
		$this->preSaveTransformFlags = $flags;
	}

	/**
	 * Records flags set by preSaveTransform
	 * @internal for use by WikitextContentHandler
	 * @return string[]
	 */
	public function getPreSaveTransformFlags() {
		return $this->preSaveTransformFlags;
	}
}
