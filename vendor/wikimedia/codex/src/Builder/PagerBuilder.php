<?php
/**
 * PagerBuilder.php
 *
 * This file is part of the Codex design system, the official design system
 * for Wikimedia projects. It provides the `Pager` class, a builder for constructing
 * pagination controls using the Codex design system.
 *
 * Pagers are used to navigate through pages of data, facilitating user interaction with large datasets.
 *
 * @category Builder
 * @package  Codex\Builder
 * @since    0.1.0
 * @author   Doğu Abaris <abaris@null.net>
 * @license  https://www.gnu.org/copyleft/gpl.html GPL-2.0-or-later
 * @link     https://doc.wikimedia.org/codex/main/ Codex Documentation
 */

namespace Wikimedia\Codex\Builder;

use InvalidArgumentException;
use Wikimedia\Codex\Component\Pager;
use Wikimedia\Codex\Renderer\PagerRenderer;

/**
 * PagerBuilder
 *
 * This class implements the builder pattern to construct instances of Pager.
 * It provides a fluent interface for setting various properties and building the
 * final immutable object with predefined configurations and immutability.
 *
 * @category Builder
 * @package  Codex\Builder
 * @since    0.1.0
 * @author   Doğu Abaris <abaris@null.net>
 * @license  https://www.gnu.org/copyleft/gpl.html GPL-2.0-or-later
 * @link     https://doc.wikimedia.org/codex/main/ Codex Documentation
 */
class PagerBuilder {

	/**
	 * Valid positions for pagination controls ('top', 'bottom', or 'both').
	 */
	private const TABLE_PAGINATION_POSITIONS = [
		'top',
		'bottom',
		'both',
	];

	/**
	 * Action for the first page.
	 */
	public const ACTION_FIRST = 'first';

	/**
	 * Action for the previous page.
	 */
	public const ACTION_PREVIOUS = 'previous';

	/**
	 * Action for the next page.
	 */
	public const ACTION_NEXT = 'next';

	/**
	 * Action for the last page.
	 */
	public const ACTION_LAST = 'last';

	/**
	 * The ID for the pager.
	 */
	protected string $id = '';

	/**
	 * Available options for the number of results displayed per page.
	 */
	protected array $paginationSizeOptions = [
		5,
		10,
		25,
		50,
		100,
	];

	/**
	 * Default pagination size.
	 */
	protected int $paginationSizeDefault = 10;

	/**
	 * Total number of pages in the dataset.
	 */
	protected int $totalPages = 1;

	/**
	 * Total number of results in the dataset.
	 */
	protected int $totalResults = 0;

	/**
	 * Position of the pagination controls ('top', 'bottom', or 'both').
	 */
	protected string $position = 'bottom';

	/**
	 * Array of additional attributes for the pager.
	 */
	protected array $attributes = [];

	/**
	 * Number of results to display per page.
	 */
	protected int $limit = 10;

	/**
	 * Offset of the current page.
	 */
	protected ?int $currentOffset = null;

	/**
	 * Offset for the next page.
	 */
	protected ?int $nextOffset = null;

	/**
	 * Offset for the previous page.
	 */
	protected ?int $prevOffset = null;

	/**
	 * Offset for the first page.
	 */
	protected ?int $firstOffset = 0;

	/**
	 * Offset for the last page.
	 */
	protected ?int $lastOffset = null;

	/**
	 * Start ordinal for the current page.
	 */
	protected int $startOrdinal = 1;

	/**
	 * End ordinal for the current page.
	 */
	protected int $endOrdinal = 1;

	/**
	 * The renderer instance used to render the pager.
	 */
	protected PagerRenderer $renderer;

	/**
	 * Constructor for the PagerBuilder class.
	 *
	 * @param PagerRenderer $renderer The renderer to use for rendering the pager.
	 */
	public function __construct( PagerRenderer $renderer ) {
		$this->renderer = $renderer;
	}

	/**
	 * Set the Pager's HTML ID attribute.
	 *
	 * @since 0.1.0
	 * @param string $id The ID for the Pager element.
	 * @return $this
	 */
	public function setId( string $id ): self {
		$this->id = $id;

		return $this;
	}

	/**
	 * Set the total number of pages.
	 *
	 * The total number of pages available based on the dataset.
	 *
	 * @since 0.1.0
	 * @param int $totalPages The total number of pages.
	 * @return $this Returns the Pager instance for method chaining.
	 */
	public function setTotalPages( int $totalPages ): self {
		$this->totalPages = $totalPages;

		return $this;
	}

	/**
	 * Set the total number of results.
	 *
	 * The total number of results in the dataset.
	 *
	 * @since 0.1.0
	 * @param int $totalResults The total number of results.
	 * @return $this Returns the Pager instance for method chaining.
	 */
	public function setTotalResults( int $totalResults ): self {
		$this->totalResults = $totalResults;

		return $this;
	}

	/**
	 * Set the limit for the pager.
	 *
	 * The number of results to be displayed per page. The limit must be at least 1.
	 *
	 * @since 0.1.0
	 * @param int $limit The number of results per page.
	 * @return $this Returns the Pager instance for method chaining.
	 */
	public function setLimit( int $limit ): self {
		if ( $limit < 1 ) {
			throw new InvalidArgumentException( 'The limit must be at least 1.' );
		}

		$this->limit = $limit;

		return $this;
	}

	/**
	 * Set the current offset for the pager.
	 *
	 * This method sets the current offset, typically a timestamp or unique
	 * identifier, for cursor-based pagination. The offset represents the
	 * position in the dataset from which to start fetching the next page
	 * of results.
	 *
	 * Example usage:
	 *
	 *     $pager->setCurrentOffset('20240918135942');
	 *
	 * @since 0.1.0
	 * @param ?int $currentOffset The offset value (usually a timestamp).
	 * @return $this Returns the Pager instance for method chaining.
	 */
	public function setCurrentOffset( ?int $currentOffset ): self {
		$this->currentOffset = $currentOffset;

		return $this;
	}

	/**
	 * Set the offset for the first page.
	 *
	 * This method sets the offset for the first page in cursor-based
	 * pagination. It usually represents the earliest timestamp in the
	 * dataset.
	 *
	 * Example usage:
	 *
	 *     $pager->setFirstOffset('20240918135942');
	 *
	 * @since 0.1.0
	 * @param ?int $firstOffset The offset for the first page.
	 * @return $this Returns the Pager instance for method chaining.
	 */
	public function setFirstOffset( ?int $firstOffset ): self {
		$this->firstOffset = $firstOffset;

		return $this;
	}

	/**
	 * Set the offset for the previous page.
	 *
	 * This method sets the offset for the previous page in cursor-based
	 * pagination. The offset is typically the timestamp of the first
	 * item in the current page.
	 *
	 * Example usage:
	 *
	 *     $pager->setPrevOffset('20240918135942');
	 *
	 * @since 0.1.0
	 * @param ?int $prevOffset The offset for the previous page.
	 * @return $this Returns the Pager instance for method chaining.
	 */
	public function setPrevOffset( ?int $prevOffset ): self {
		$this->prevOffset = $prevOffset;

		return $this;
	}

	/**
	 * Set the offset for the next page.
	 *
	 * This method sets the offset for the next page in cursor-based
	 * pagination. It is typically the timestamp of the last item on the
	 * current page.
	 *
	 * Example usage:
	 *
	 *     $pager->setNextOffset('20240918135942');
	 *
	 * @since 0.1.0
	 * @param ?int $nextOffset The offset for the next page.
	 * @return $this Returns the Pager instance for method chaining.
	 */
	public function setNextOffset( ?int $nextOffset ): self {
		$this->nextOffset = $nextOffset;

		return $this;
	}

	/**
	 * Set the offset for the last page.
	 *
	 * This method sets the offset for the last page in cursor-based
	 * pagination. It typically represents the timestamp of the last
	 * item in the dataset.
	 *
	 * Example usage:
	 *
	 *     $pager->setLastOffset('20240918135942');
	 *
	 * @since 0.1.0
	 * @param ?int $lastOffset The offset for the last page.
	 * @return $this Returns the Pager instance for method chaining.
	 */
	public function setLastOffset( ?int $lastOffset ): self {
		$this->lastOffset = $lastOffset;

		return $this;
	}

	/**
	 * Set the start and end ordinals for the current page.
	 *
	 * This method defines the range of items (ordinals) displayed on the
	 * current page of results. The ordinals represent the 1-based index
	 * of the first and last items shown on the page.
	 *
	 * Ordinals are typically determined based on the current page number
	 * and the limit, which is the number of items per page. The `startOrdinal`
	 * specifies the index of the first item on the page, while `endOrdinal`
	 * specifies the index of the last item. This ensures accurate display
	 * of the current page's item range.
	 *
	 * **Tip**: When working with cursor-based pagination (e.g., based on
	 * timestamps), ordinals can be calculated by determining the position
	 * of the current offset within the dataset. By tracking the relative
	 * position of items using their timestamps, the starting and ending
	 * ordinal values for each page can be derived.
	 *
	 * Example usage:
	 *
	 *     $pager->setOrdinals(6, 10);
	 *
	 * @since 0.1.0
	 * @param int $startOrdinal The 1-based index of the first item displayed.
	 * @param int $endOrdinal The 1-based index of the last item displayed.
	 * @return $this Returns the Pager instance for method chaining.
	 */
	public function setOrdinals( int $startOrdinal, int $endOrdinal ): self {
		$this->startOrdinal = $startOrdinal;
		$this->endOrdinal = $endOrdinal;

		return $this;
	}

	/**
	 * Set the position for the pager.
	 *
	 * This method specifies where the pagination controls should appear.
	 * Valid positions are 'top', 'bottom', or 'both'.
	 *
	 * Example usage:
	 *
	 *     $pager->setPosition('top');
	 *
	 * @since 0.1.0
	 * @param string $position The position of the pagination controls ('top', 'bottom', or 'both').
	 * @return $this Returns the Pager instance for method chaining.
	 */
	public function setPosition( string $position ): self {
		if ( !in_array( $position, self::TABLE_PAGINATION_POSITIONS, true ) ) {
			throw new InvalidArgumentException( "Invalid pagination position: $position" );
		}
		$this->position = $position;

		return $this;
	}

	/**
	 * Set the pagination size options.
	 *
	 * This method defines the available options for the number of results displayed per page.
	 * Users can select from these options in a dropdown, and the selected value will control
	 * how many items are displayed on each page.
	 *
	 * Example usage:
	 *
	 *     $pager->setPaginationSizeOptions([10, 20, 50]);
	 *
	 * @since 0.1.0
	 * @param array $paginationSizeOptions The array of pagination size options.
	 * @return $this Returns the Pager instance for method chaining.
	 */
	public function setPaginationSizeOptions( array $paginationSizeOptions ): self {
		if ( !$paginationSizeOptions ) {
			throw new InvalidArgumentException( 'Pagination size options cannot be empty.' );
		}
		$this->paginationSizeOptions = $paginationSizeOptions;

		return $this;
	}

	/**
	 * Set the default pagination size.
	 *
	 * This method specifies the default number of rows displayed per page.
	 *
	 * @since 0.1.0
	 * @param int $paginationSizeDefault The default number of rows per page.
	 * @return $this Returns the Table instance for method chaining.
	 */
	public function setPaginationSizeDefault( int $paginationSizeDefault ): self {
		if ( !in_array( $paginationSizeDefault, $this->paginationSizeOptions, true ) ) {
			throw new InvalidArgumentException( 'Default pagination size must be one of the pagination size options.' );
		}
		$this->paginationSizeDefault = $paginationSizeDefault;

		return $this;
	}

	/**
	 * Build and return the Pager component object.
	 * This method constructs the immutable Pager object with all the properties set via the builder.
	 *
	 * @since 0.1.0
	 * @return Pager The constructed Pager.
	 */
	public function build(): Pager {
		return new Pager(
			$this->id,
			$this->paginationSizeOptions,
			$this->paginationSizeDefault,
			$this->totalPages,
			$this->totalResults,
			$this->position,
			$this->attributes,
			$this->limit,
			$this->currentOffset,
			$this->nextOffset,
			$this->prevOffset,
			$this->firstOffset,
			$this->lastOffset,
			$this->startOrdinal,
			$this->endOrdinal,
			$this->renderer
		);
	}
}
