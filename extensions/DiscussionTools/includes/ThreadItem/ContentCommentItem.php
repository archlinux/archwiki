<?php

namespace MediaWiki\Extension\DiscussionTools\ThreadItem;

use DateTimeImmutable;
use MediaWiki\Extension\DiscussionTools\CommentModifier;
use MediaWiki\Extension\DiscussionTools\CommentUtils;
use MediaWiki\Extension\DiscussionTools\ImmutableRange;
use MediaWiki\MediaWikiServices;
use Sanitizer;
use Title;
use Wikimedia\Parsoid\DOM\DocumentFragment;
use Wikimedia\Parsoid\DOM\Text;
use Wikimedia\Parsoid\Utils\DOMCompat;
use Wikimedia\Parsoid\Utils\DOMUtils;

class ContentCommentItem extends ContentThreadItem implements CommentItem {
	use CommentItemTrait {
		getHeading as protected traitGetHeading;
		getSubscribableHeading as protected traitGetSubscribableHeading;
	}

	private $signatureRanges;
	private $timestamp;
	private $author;

	/**
	 * @param int $level
	 * @param ImmutableRange $range
	 * @param ImmutableRange[] $signatureRanges Objects describing the extent of signatures (plus
	 *  timestamps) for this comment. There is always at least one signature, but there may be
	 *  multiple. The author and timestamp of the comment is determined from the first signature.
	 *  The last node in every signature range is a node containing the timestamp.
	 * @param DateTimeImmutable $timestamp
	 * @param string $author Comment author's username
	 */
	public function __construct(
		int $level, ImmutableRange $range,
		array $signatureRanges, DateTimeImmutable $timestamp, string $author
	) {
		parent::__construct( 'comment', $level, $range );
		$this->signatureRanges = $signatureRanges;
		$this->timestamp = $timestamp;
		$this->author = $author;
	}

	/**
	 * Get the HTML of this comment's body
	 *
	 * @param bool $stripTrailingSeparator Strip a trailing separator between the body and
	 *  the signature which consists of whitespace and hyphens e.g. ' --'
	 * @return DocumentFragment Cloned fragment of the body content
	 */
	private function getBodyFragment( bool $stripTrailingSeparator = false ): DocumentFragment {
		$fragment = $this->getBodyRange()->cloneContents();
		CommentModifier::unwrapFragment( $fragment );

		if ( $stripTrailingSeparator ) {
			// Find a trailing text node
			$lastChild = $fragment->lastChild;
			while (
				$lastChild &&
				!( $lastChild instanceof Text )
			) {
				$lastChild = $lastChild->lastChild;
			}
			if (
				$lastChild instanceof Text &&
				preg_match( '/[\s\-~\x{2010}-\x{2015}\x{2043}\x{2060}]+$/u', $lastChild->nodeValue ?? '', $matches )
			) {
				$lastChild->nodeValue =
					substr( $lastChild->nodeValue ?? '', 0, -strlen( $matches[0] ) );
			}
		}
		return $fragment;
	}

	/**
	 * Get the HTML of this comment's body
	 *
	 *
	 * @param bool $stripTrailingSeparator See getBodyFragment
	 * @return string HTML
	 */
	public function getBodyHTML( bool $stripTrailingSeparator = false ): string {
		$fragment = $this->getBodyFragment( $stripTrailingSeparator );
		return DOMUtils::getFragmentInnerHTML( $fragment );
	}

	/**
	 * Get the text of this comment's body
	 *
	 * @param bool $stripTrailingSeparator See getBodyFragment
	 * @return string Text
	 */
	public function getBodyText( bool $stripTrailingSeparator = false ): string {
		$html = $this->getBodyHTML( $stripTrailingSeparator );
		return Sanitizer::stripAllTags( $html );
	}

	/**
	 * Get a list of all users mentioned
	 *
	 * @return Title[] Title objects for mentioned user pages
	 */
	public function getMentions(): array {
		$fragment = $this->getBodyRange()->cloneContents();
		// Note: DOMCompat::getElementsByTagName() doesn't take a DocumentFragment argument
		$links = DOMCompat::querySelectorAll( $fragment, 'a' );
		$users = [];
		foreach ( $links as $link ) {
			$href = $link->getAttribute( 'href' );
			if ( $href ) {
				$siteConfig = MediaWikiServices::getInstance()->getMainConfig();
				$title = Title::newFromText( CommentUtils::getTitleFromUrl( $href, $siteConfig ) );
				if ( $title && $title->getNamespace() === NS_USER ) {
					// TODO: Consider returning User objects
					$users[] = $title;
				}
			}
		}
		return array_unique( $users );
	}

	/**
	 * @return ImmutableRange[] Comment signature ranges
	 */
	public function getSignatureRanges(): array {
		return $this->signatureRanges;
	}

	/**
	 * @return ImmutableRange Range of the thread item's "body"
	 */
	public function getBodyRange(): ImmutableRange {
		// Exclude last signature from body
		$signatureRanges = $this->getSignatureRanges();
		$lastSignature = end( $signatureRanges );
		return $this->getRange()->setEnd( $lastSignature->startContainer, $lastSignature->startOffset );
	}

	/**
	 * @return DateTimeImmutable Comment timestamp
	 */
	public function getTimestamp(): DateTimeImmutable {
		return $this->timestamp;
	}

	/**
	 * @return string Comment author
	 */
	public function getAuthor(): string {
		return $this->author;
	}

	/**
	 * @inheritDoc CommentItemTrait::getHeading
	 * @suppress PhanTypeMismatchReturnSuperType
	 */
	public function getHeading(): ContentHeadingItem {
		return $this->traitGetHeading();
	}

	/**
	 * @inheritDoc CommentItemTrait::getSubscribableHeading
	 */
	public function getSubscribableHeading(): ?ContentHeadingItem {
		return $this->traitGetSubscribableHeading();
	}

	/**
	 * @param ImmutableRange $signatureRange Comment signature range to add
	 */
	public function addSignatureRange( ImmutableRange $signatureRange ): void {
		$this->signatureRanges[] = $signatureRange;
	}

	/**
	 * @param ImmutableRange[] $signatureRanges Comment signature ranges
	 */
	public function setSignatureRanges( array $signatureRanges ): void {
		$this->signatureRanges = $signatureRanges;
	}

	/**
	 * @param DateTimeImmutable $timestamp Comment timestamp
	 */
	public function setTimestamp( DateTimeImmutable $timestamp ): void {
		$this->timestamp = $timestamp;
	}

	/**
	 * @param string $author Comment author
	 */
	public function setAuthor( string $author ): void {
		$this->author = $author;
	}
}
