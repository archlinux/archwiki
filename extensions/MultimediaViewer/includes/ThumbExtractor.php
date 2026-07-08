<?php
declare( strict_types = 1 );

namespace MediaWiki\Extension\MultimediaViewer;

use MediaWiki\FileRepo\File\File;
use Wikimedia\Parsoid\Core\DOMCompat;
use Wikimedia\Parsoid\DOM\DocumentFragment;
use Wikimedia\Parsoid\DOM\Element;
use Wikimedia\Zest\Zest;

/**
 * Extract thumbnail image data from a wiki page.
 *
 * Applies the following set of filters:
 *   - CSS selectors that indicate navigational icons and SVG infobox images
 *   - CSS selectors as passed by a config variable via the constructor
 *   - file extension allowlist
 *   - minimum image size
 */
class ThumbExtractor {

	/**
	 * Minimum image width in pixels.
	 */
	private const MIN_WIDTH = 30;

	/**
	 * Minimum image height in pixels.
	 */
	private const MIN_HEIGHT = 30;

	private array $allowedExtensions;

	private array $excludedImageSelectors;

	/**
	 * @param string[] $allowedExtensions A file extension allowlist,
	 * typically based on https://commons.wikimedia.org/wiki/Special:MediaStatistics
	 * @param string[] $excludedImageSelectors CSS selectors whose ancestor presence causes an image to be excluded
	 */
	public function __construct( array $allowedExtensions, array $excludedImageSelectors ) {
		$this->allowedExtensions = $allowedExtensions;
		$this->excludedImageSelectors = $excludedImageSelectors;
	}

	/**
	 * Select and filter thumbnail elements from a DOM body.
	 *
	 * Applies CSS selector-based selection and exclusion filtering, but does
	 * not match against files. Useful when file metadata is unavailable (e.g.
	 * when working with proxied HTML from MobileFrontendContentProvider).
	 *
	 * @param DocumentFragment $body A wiki page's DOM body fragment
	 * @return Element[] The filtered thumbnail elements
	 */
	public function findThumbs( DocumentFragment $body ): array {
		$allThumbs = $this->select( $body );
		return $this->filterBySelectors( $allThumbs );
	}

	/**
	 * Extract thumbnail image data from a wiki page.
	 *
	 * Steps:
	 *   1. select and filter thumbnail elements via {@link findThumbs}
	 *   2. filter files that don't have an allowed extension or whose size is too small
	 *   3. match filtered thumbnails to filtered files by file name
	 *
	 * Return an array with the following data:
	 *   - name - file name, with underscores
	 *   - title - {@link Title} object
	 *   - extension - file extension
	 *   - width - original image width
	 *   - height - original image height
	 *   - url - original image URL
	 *   - thumb - thumbnail DOM element
	 *
	 * @param DocumentFragment|null $body A wiki page's DOM body fragment
	 * @param array<string,File> $files A map of (file name => File objects) as returned by
	 *   {@link RepoGroup::findFiles()}
	 * @return array[] The extracted image data. Empty array if no images
	 */
	public function extract( ?DocumentFragment $body, array $files ): array {
		if ( $body === null ) {
			return [];
		}

		$result = [];

		$thumbs = $this->findThumbs( $body );
		$filteredFiles = $this->filterFiles( $files );

		foreach ( $thumbs as $thumb ) {
			$src = DOMCompat::getAttribute( $thumb, 'src' )
				?? DOMCompat::getAttribute( $thumb, 'data-mw-src' );

			if ( $src === null ) {
				continue;
			}

			foreach ( $filteredFiles as $file ) {
				if ( str_contains( $src, $file['name'] ) ) {
					$file['thumb'] = $thumb;
					$result[] = $file;
				}
			}
		}

		return $result;
	}

	/**
	 * Select image thumbnails from a DOM body via CSS selectors.
	 *
	 * @param DocumentFragment $body A wiki page's DOM body fragment
	 * @return (iterable<Element>&\Countable)|array<Element> The extracted elements
	 */
	private function select( DocumentFragment $body ): iterable {
		$selectors = implode( ', ', [
			// Parsoid thumbs
			'[typeof~="mw:File"] a.mw-file-description img',
			'[typeof~="mw:File"] a.mw-file-description .lazy-image-placeholder',
			// Legacy parser thumbs
			'.gallery .image img',
			'.gallery .image .lazy-image-placeholder',
			'a.image img',
			'a.image .lazy-image-placeholder',
			'a.mw-file-description img',
			'#file a img',
		] );

		return DOMCompat::querySelectorAll( $body, $selectors );
	}

	/**
	 * Filter image thumbnails via CSS selectors.
	 *
	 * @param Element[] $thumbs Thumbnail elements
	 * @return Element[] The filtered elements. Empty array if no elements
	 */
	private function filterBySelectors( array $thumbs ): array {
		$filtered = [];

		foreach ( $thumbs as $thumb ) {
			if ( $this->isAllowedThumb( $thumb ) && !$this->isExcludedBySelector( $thumb ) ) {
				$filtered[] = $thumb;
			}
		}

		return $filtered;
	}

	/**
	 * Filter files that don't have an allowed extension or whose size is too small.
	 *
	 * A file is too small if both its width and height are less than or equal to
	 * {@link self::MIN_WIDTH} and {@link self::MIN_HEIGHT} respectively.
	 *
	 * @param array<string,File> $files A map of (file name => File objects)
	 * @return array[] The filtered files with minimal metadata. Empty array if no files
	 */
	private function filterFiles( array $files ): array {
		$filtered = [];

		foreach ( $files as $name => $file ) {
			if ( $file && $file->exists() ) {
				$extension = $file->getExtension();
				$width = $file->getWidth();
				$height = $file->getHeight();

				if (
					array_key_exists( $extension, $this->allowedExtensions ) &&
					( $width > self::MIN_WIDTH || $height > self::MIN_HEIGHT )
				) {
					$filtered[] = [
						'name' => $name,
						'title' => $file->getTitle(),
						'extension' => $extension,
						'width' => $width,
						'height' => $height,
						'url' => $file->getFullUrl(),
					];
				}
			}
		}

		return $filtered;
	}

	/**
	 * Check for exclusions of thumbnails in known non-content areas.
	 *
	 * Should be kept equivalent to MultimediaViewer's isAllowedThumb implementation.
	 *
	 * @param Element $thumb An image thumbnail element
	 * @return bool Whether the image is safe to use for carousel purposes
	 */
	private function isAllowedThumb( Element $thumb ): bool {
		$selectors = implode( ', ', [
			// This is inside an informational template like {{refimprove}} on enwiki
			'.metadata',
			// MediaViewer has been specifically disabled for this image
			'.noviewer',
			// We are on an error page for a non-existing article, the image is part of some template
			'.noarticletext',
			'#siteNotice',
			// Thumbnails of a slideshow gallery
			'ul.mw-gallery-slideshow li.gallerybox',
		] );

		return $this->closest( $thumb, $selectors ) === null;
	}

	/**
	 * Check if the thumbnail is excluded by any of the CSS selectors
	 * passed in this class constructor.
	 *
	 * These selectors should come from the $wgReaderExperimentsExcludedImageSelectors
	 * config variable.
	 *
	 * @param Element $thumb An image thumbnail element
	 * @return bool Whether the image should be excluded
	 */
	private function isExcludedBySelector( Element $thumb ): bool {
		foreach ( $this->excludedImageSelectors as $selector ) {
			if ( $this->closest( $thumb, $selector ) !== null ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Find the closest parent of a DOM element (including itself)
	 * that matches a CSS selector.
	 *
	 * @param Element|null $element A DOM element
	 * @param string $selector A CSS selector to match
	 * @return Element|null The matching element, or null if none
	 */
	private function closest( ?Element $element, string $selector ): ?Element {
		if ( $element === null ) {
			return null;
		}

		// Check the element itself first
		if ( Zest::matches( $element, $selector ) ) {
			return $element;
		}

		// Traverse up through parents
		$parent = DOMCompat::getParentElement( $element );
		while ( $parent !== null ) {
			if ( Zest::matches( $parent, $selector ) ) {
				return $parent;
			}
			$parent = DOMCompat::getParentElement( $parent );
		}

		return null;
	}
}
