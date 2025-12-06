<?php
/**
 * Pager.php
 *
 * This file is part of the Codex design system, the official design system for Wikimedia projects.
 * It contains the definition and implementation of the `Pager` class, responsible for managing
 * the behavior and properties of the corresponding component.
 *
 * @category Component
 * @package  Codex\Component
 * @since    0.1.0
 * @author   Doğu Abaris <abaris@null.net>
 * @license  https://www.gnu.org/copyleft/gpl.html GPL-2.0-or-later
 * @link     https://doc.wikimedia.org/codex/main/ Codex Documentation
 */

namespace Wikimedia\Codex\Component;

use Wikimedia\Codex\Renderer\PagerRenderer;

/**
 * Pager
 *
 * This class is part of the Codex PHP library and is responsible for
 * representing an immutable object. It is primarily intended for use
 * with a builder class to construct its instances.
 *
 * @category Component
 * @package  Codex\Component
 * @since    0.1.0
 * @author   Doğu Abaris <abaris@null.net>
 * @license  https://www.gnu.org/copyleft/gpl.html GPL-2.0-or-later
 * @link     https://doc.wikimedia.org/codex/main/ Codex Documentation
 */
class Pager {

	/**
	 * The ID for the pager.
	 */
	protected string $id;

	/**
	 * Available options for the number of results displayed per page.
	 */
	protected array $paginationSizeOptions;

	/**
	 * Default pagination size.
	 */
	protected int $paginationSizeDefault;

	/**
	 * Total number of pages in the dataset.
	 */
	protected int $totalPages;

	/**
	 * Total number of results in the dataset.
	 */
	protected int $totalResults;

	/**
	 * Position of the pagination controls ('top', 'bottom', or 'both').
	 */
	protected string $position;

	/**
	 * Array of additional attributes for the pager.
	 */
	protected array $attributes;

	/**
	 * Number of results to display per page.
	 */
	protected int $limit;

	/**
	 * Offset of the current page.
	 */
	protected ?int $currentOffset;

	/**
	 * Offset for the next page.
	 */
	protected ?int $nextOffset;

	/**
	 * Offset for the previous page.
	 */
	protected ?int $prevOffset;

	/**
	 * Offset for the first page.
	 */
	protected ?int $firstOffset;

	/**
	 * Offset for the last page.
	 */
	protected ?int $lastOffset;

	/**
	 * Start ordinal for the current page.
	 */
	protected int $startOrdinal;

	/**
	 * End ordinal for the current page.
	 */
	protected int $endOrdinal;

	/**
	 * The renderer instance used to render the pager.
	 */
	protected PagerRenderer $renderer;

	/**
	 * Constructor for the Pager class.
	 *
	 * Initializes the Pager with the necessary properties.
	 *
	 * @param string $id The ID for the pager.
	 * @param array $paginationSizeOptions Available pagination size options.
	 * @param int $paginationSizeDefault Default pagination size.
	 * @param int $totalPages Total number of pages in the dataset.
	 * @param int $totalResults Total number of results in the dataset.
	 * @param string $position Position of the pagination controls.
	 * @param array $attributes Additional HTML attributes for the pager.
	 * @param int $limit Number of results per page.
	 * @param int|null $currentOffset Offset of the current page.
	 * @param int|null $nextOffset Offset for the next page.
	 * @param int|null $prevOffset Offset for the previous page.
	 * @param int|null $firstOffset Offset for the first page.
	 * @param int|null $lastOffset Offset for the last page.
	 * @param int $startOrdinal Start ordinal for the current page.
	 * @param int $endOrdinal End ordinal for the current page.
	 * @param PagerRenderer $renderer Instance of the renderer for rendering the pager.
	 */
	public function __construct(
		string $id,
		array $paginationSizeOptions,
		int $paginationSizeDefault,
		int $totalPages,
		int $totalResults,
		string $position,
		array $attributes,
		int $limit,
		?int $currentOffset,
		?int $nextOffset,
		?int $prevOffset,
		?int $firstOffset,
		?int $lastOffset,
		int $startOrdinal,
		int $endOrdinal,
		PagerRenderer $renderer
	) {
		$this->id = $id;
		$this->paginationSizeOptions = $paginationSizeOptions;
		$this->paginationSizeDefault = $paginationSizeDefault;
		$this->totalPages = $totalPages;
		$this->totalResults = $totalResults;
		$this->position = $position;
		$this->attributes = $attributes;
		$this->limit = $limit;
		$this->currentOffset = $currentOffset;
		$this->nextOffset = $nextOffset;
		$this->prevOffset = $prevOffset;
		$this->firstOffset = $firstOffset;
		$this->lastOffset = $lastOffset;
		$this->startOrdinal = $startOrdinal;
		$this->endOrdinal = $endOrdinal;
		$this->renderer = $renderer;
	}

	/**
	 * Get the Pager's HTML ID attribute.
	 *
	 * This method returns the ID assigned to the pager element, which is used
	 * for identifying the pager in the HTML document.
	 *
	 * @since 0.1.0
	 * @return string The ID of the Pager.
	 */
	public function getId(): string {
		return $this->id;
	}

	/**
	 * Determine if the first button should be disabled.
	 *
	 * This method checks whether the first button should be disabled based on the current page.
	 *
	 * @since 0.1.0
	 * @return bool Returns true if the first button should be disabled, false otherwise.
	 */
	public function isFirstDisabled(): bool {
		return $this->firstOffset == null && $this->prevOffset == null;
	}

	/**
	 * Determine if the previous button should be disabled.
	 *
	 * This method checks whether the previous button should be disabled based on the current page.
	 *
	 * @since 0.1.0
	 * @return bool Returns true if the previous button should be disabled, false otherwise.
	 */
	public function isPrevDisabled(): bool {
		return $this->prevOffset == null;
	}

	/**
	 * Determine if the next button should be disabled.
	 *
	 * This method checks whether the next button should be disabled based on the total results and the current page.
	 *
	 * @since 0.1.0
	 * @return bool Returns true if the next button should be disabled, false otherwise.
	 */
	public function isNextDisabled(): bool {
		return $this->nextOffset == null || $this->currentOffset == $this->lastOffset;
	}

	/**
	 * Determine if the last button should be disabled.
	 *
	 * This method checks whether the last button should be disabled based on the indeterminate state.
	 *
	 * @since 0.1.0
	 * @return bool Returns true if the last button should be disabled, false otherwise.
	 */
	public function isLastDisabled(): bool {
		return $this->nextOffset == null || $this->currentOffset == $this->lastOffset;
	}

	/**
	 * Get the current offset for the pager.
	 *
	 * This method returns the current offset value, which determines the
	 * starting point for the data on the current page. In cursor-based
	 * pagination, this offset is usually a timestamp or unique identifier.
	 *
	 * @since 0.1.0
	 * @return ?int The offset value for the current page.
	 */
	public function getCurrentOffset(): ?int {
		return $this->currentOffset;
	}

	/**
	 * Get the offset for the first page.
	 *
	 * This method returns the offset for the first page in cursor-based
	 * pagination. The first page offset usually represents the earliest
	 * timestamp or unique identifier in the dataset.
	 *
	 * @since 0.1.0
	 * @return ?int The offset value for the first page, or null if not set.
	 */
	public function getFirstOffset(): ?int {
		return $this->firstOffset;
	}

	/**
	 * Get the offset for the previous page.
	 *
	 * This method returns the offset for the previous page in cursor-based
	 * pagination. The previous page offset is typically the timestamp or
	 * unique identifier of the first item in the current page.
	 *
	 * @since 0.1.0
	 * @return ?int The offset value for the previous page, or null if not set.
	 */
	public function getPrevOffset(): ?int {
		return $this->prevOffset;
	}

	/**
	 * Get the offset for the next page.
	 *
	 * This method returns the offset for the next page in cursor-based
	 * pagination. The next page offset is typically the timestamp or
	 * unique identifier of the last item on the current page.
	 *
	 * @since 0.1.0
	 * @return ?int The offset value for the next page, or null if not set.
	 */
	public function getNextOffset(): ?int {
		return $this->nextOffset;
	}

	/**
	 * Get the offset for the last page.
	 *
	 * This method returns the offset for the last page in cursor-based
	 * pagination. The last page offset typically represents the timestamp
	 * or unique identifier of the last item in the dataset.
	 *
	 * @since 0.1.0
	 * @return ?int The offset value for the last page, or null if not set.
	 */
	public function getLastOffset(): ?int {
		return $this->lastOffset;
	}

	/**
	 * Get the start ordinal for the current page.
	 *
	 * @since 0.1.0
	 * @return int The start ordinal.
	 */
	public function getStartOrdinal(): int {
		return $this->startOrdinal;
	}

	/**
	 * Get the end ordinal for the current page.
	 *
	 * @since 0.1.0
	 * @return int The end ordinal.
	 */
	public function getEndOrdinal(): int {
		return $this->endOrdinal;
	}

	/**
	 * Get the total number of pages.
	 *
	 * This method returns the total number of pages available based on the dataset.
	 *
	 * @since 0.1.0
	 * @return int The total number of pages.
	 */
	public function getTotalPages(): int {
		return $this->totalPages;
	}

	/**
	 * Get the total number of results.
	 *
	 * This method returns the total number of results in the dataset.
	 *
	 * @since 0.1.0
	 * @return int The total number of results.
	 */
	public function getTotalResults(): int {
		return $this->totalResults;
	}

	/**
	 * Get the limit for the pager.
	 *
	 * This method returns the number of results to be displayed per page.
	 *
	 * @since 0.1.0
	 * @return int The number of results per page.
	 */
	public function getLimit(): int {
		return $this->limit;
	}

	/**
	 * Get the position of the pagination controls.
	 *
	 * This method returns the position where the pagination controls are displayed. Valid positions
	 * are 'top', 'bottom', or 'both'.
	 *
	 * @since 0.1.0
	 * @return string The position of the pagination controls.
	 */
	public function getPosition(): string {
		return $this->position;
	}

	/**
	 * Get the pagination size options.
	 *
	 * This method returns the available options for the number of results displayed per page.
	 * Users can select from these options in a dropdown.
	 *
	 * @since 0.1.0
	 * @return array The array of pagination size options.
	 */
	public function getPaginationSizeOptions(): array {
		return $this->paginationSizeOptions;
	}

	/**
	 * Get the default pagination size.
	 *
	 * This method returns the default number of rows displayed per page.
	 *
	 * @since 0.1.0
	 * @return int The default pagination size.
	 */
	public function getPaginationSizeDefault(): int {
		return $this->paginationSizeDefault;
	}

	/**
	 * Get the component's HTML representation.
	 *
	 * This method generates the HTML markup for the component, incorporating relevant properties
	 * and any additional attributes. The component is structured using appropriate HTML elements
	 * as defined by the implementation.
	 *
	 * @since 0.1.0
	 * @return string The generated HTML string for the component.
	 */
	public function getHtml(): string {
		return $this->renderer->render( $this );
	}
}
