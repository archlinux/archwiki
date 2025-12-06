<?php
/**
 * Table.php
 *
 * This file is part of the Codex design system, the official design system for Wikimedia projects.
 * It contains the definition and implementation of the `Table` class, responsible for managing
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

use Wikimedia\Codex\Renderer\TableRenderer;

/**
 * Table
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
class Table {

	/**
	 * Sort direction for ascending order.
	 */
	public const SORT_ASCENDING = 'asc';

	/**
	 * Sort direction for descending order.
	 */
	public const SORT_DESCENDING = 'desc';

	/**
	 * The ID for the table.
	 */
	protected string $id;

	/**
	 * The caption for the table.
	 */
	protected string $caption;

	/**
	 * Whether the caption is hidden.
	 */
	protected bool $hideCaption;

	/**
	 * The content for the table header.
	 */
	protected ?string $headerContent;

	/**
	 * Array of columns for the table.
	 */
	protected array $columns;

	/**
	 * Array of data for the table rows.
	 */
	protected array $data;

	/**
	 * Whether row headers are used.
	 */
	protected bool $useRowHeaders;

	/**
	 * Array of sorting configurations.
	 */
	protected array $sort;

	/**
	 * The current sorted column.
	 */
	protected ?string $currentSortColumn;

	/**
	 * The current sort direction.
	 */
	protected string $currentSortDirection;

	/**
	 * Whether to show vertical borders.
	 */
	protected bool $showVerticalBorders;

	/**
	 * Additional HTML attributes for the table.
	 */
	protected array $attributes;

	/**
	 * Whether pagination is enabled.
	 */
	protected bool $paginate;

	/**
	 * The total number of rows in the table.
	 */
	protected int $totalRows;

	/**
	 * The pagination position ('top', 'bottom', or 'both').
	 */
	protected string $paginationPosition;

	/**
	 * The pager for handling pagination.
	 */
	protected ?Pager $pager;

	/**
	 * The footer content of the table.
	 */
	protected ?string $footer;

	/**
	 * The renderer instance used to render the table.
	 */
	protected TableRenderer $renderer;

	/**
	 * Constructor for the Table class.
	 *
	 * Initializes the Table with the necessary properties.
	 *
	 * @param string $id The ID for the table.
	 * @param string $caption The caption for the table.
	 * @param bool $hideCaption Whether the caption is hidden.
	 * @param array $columns Array of columns.
	 * @param-taint $columns exec_html Callers are responsible for escaping
	 * @param array $data Array of row data.
	 * @param bool $useRowHeaders Whether to use row headers.
	 * @param ?string $headerContent The header content.
	 * @param-taint $headerContent exec_html Callers are responsible for escaping
	 * @param array $sort Array of sorting configurations.
	 * @param ?string $currentSortColumn The current sorted column.
	 * @param string $currentSortDirection The current sort direction.
	 * @param bool $showVerticalBorders Whether to show vertical borders.
	 * @param array $attributes Additional HTML attributes.
	 * @param bool $paginate Whether pagination is enabled.
	 * @param int $totalRows The total number of rows.
	 * @param string $paginationPosition The pagination position.
	 * @param ?Pager $pager The pager for handling pagination.
	 * @param ?string $footer The footer content.
	 * @param TableRenderer $renderer The renderer instance.
	 */
	public function __construct(
		string $id,
		string $caption,
		bool $hideCaption,
		array $columns,
		array $data,
		bool $useRowHeaders,
		?string $headerContent,
		array $sort,
		?string $currentSortColumn,
		string $currentSortDirection,
		bool $showVerticalBorders,
		array $attributes,
		bool $paginate,
		int $totalRows,
		string $paginationPosition,
		?Pager $pager,
		?string $footer,
		TableRenderer $renderer
	) {
		$this->id = $id;
		$this->caption = $caption;
		$this->hideCaption = $hideCaption;
		$this->columns = $columns;
		$this->data = $data;
		$this->useRowHeaders = $useRowHeaders;
		$this->headerContent = $headerContent;
		$this->sort = $sort;
		$this->currentSortColumn = $currentSortColumn;
		$this->currentSortDirection = $currentSortDirection;
		$this->showVerticalBorders = $showVerticalBorders;
		$this->attributes = $attributes;
		$this->paginate = $paginate;
		$this->totalRows = $totalRows;
		$this->paginationPosition = $paginationPosition;
		$this->pager = $pager;
		$this->footer = $footer;
		$this->renderer = $renderer;
	}

	/**
	 * Get the HTML ID for the table.
	 *
	 * This method returns the HTML `id` attribute value for the table element.
	 *
	 * @since 0.1.0
	 * @return string The ID for the table.
	 */
	public function getId(): string {
		return $this->id;
	}

	/**
	 * Get the opposite sort direction.
	 *
	 * This method returns the opposite of the current sort direction.
	 *
	 * @since 0.1.0
	 * @param string $direction The current sort direction ('asc' or 'desc').
	 * @return string The opposite sort direction.
	 */
	public function oppositeSort( string $direction ): string {
		return $direction === self::SORT_ASCENDING ? self::SORT_DESCENDING : self::SORT_ASCENDING;
	}

	/**
	 * Get whether row headers are used.
	 *
	 * This method returns a boolean value indicating whether the first column of the table is treated as row headers.
	 * Row headers are useful for accessibility and provide additional context for each row.
	 *
	 * @since 0.1.0
	 * @return bool True if row headers are used, false otherwise.
	 */
	public function getUseRowHeaders(): bool {
		return $this->useRowHeaders;
	}

	/**
	 * Check if vertical borders are shown between columns.
	 *
	 * This method returns a boolean value indicating whether vertical borders are displayed between columns in the
	 * table.
	 *
	 * @since 0.1.0
	 * @return bool True if vertical borders are displayed, false otherwise.
	 */
	public function getShowVerticalBorders(): bool {
		return $this->showVerticalBorders;
	}

	/**
	 * Get the Pager instance for the table.
	 *
	 * This method returns the Pager instance if it is set, which provides pagination controls for the table.
	 *
	 * @since 0.1.0
	 * @return Pager|null Returns the Pager instance or null if not set.
	 */
	public function getPager(): ?Pager {
		return $this->pager;
	}

	/**
	 * Get the position of the pagination controls.
	 *
	 * This method returns the position of the pagination controls, which can be 'top', 'bottom', or 'both'.
	 *
	 * @since 0.1.0
	 * @return string The position of the pagination controls ('top', 'bottom', or 'both').
	 */
	public function getPaginationPosition(): string {
		return $this->paginationPosition;
	}

	/**
	 * Get the total number of rows.
	 *
	 * This method returns the total number of rows in the table, which is used for pagination and display purposes.
	 *
	 * @since 0.1.0
	 * @return int The total number of rows in the table.
	 */
	public function getTotalRows(): int {
		return $this->totalRows;
	}

	/**
	 * Get the footer content for the table.
	 *
	 * This method returns the footer content if it is set, which can contain additional information or actions related
	 * to the table.
	 *
	 * @since 0.1.0
	 * @return string|null The footer content or null if not set.
	 */
	public function getFooter(): ?string {
		return $this->footer;
	}

	/**
	 * Get the header content for the table.
	 *
	 * This method returns the custom content for the table's header if it is set, such as actions or additional text.
	 *
	 * @since 0.1.0
	 * @return string|null The header content or null if not set.
	 */
	public function getHeaderContent(): ?string {
		return $this->headerContent;
	}

	/**
	 * Get the caption for the table.
	 *
	 * This method returns the caption text that provides a description of the table's contents and purpose.
	 *
	 * @since 0.1.0
	 * @return string The caption text for the table.
	 */
	public function getCaption(): string {
		return $this->caption;
	}

	/**
	 * Check if the caption is hidden.
	 *
	 * This method returns a boolean value indicating whether the caption is visually hidden but still accessible to
	 * screen readers.
	 *
	 * @since 0.1.0
	 * @return bool True if the caption is hidden, false otherwise.
	 */
	public function getHideCaption(): bool {
		return $this->hideCaption;
	}

	/**
	 * Get the columns for the table.
	 *
	 * This method returns an array of columns defined for the table, where each column is an associative array
	 * containing column attributes.
	 *
	 * @since 0.1.0
	 * @return array The array of columns defined for the table.
	 */
	public function getColumns(): array {
		return $this->columns;
	}

	/**
	 * Get the data for the table.
	 *
	 * This method returns the array of data to be displayed in the table, where each row is an associative array with
	 * keys matching column IDs.
	 *
	 * @since 0.1.0
	 * @return array The array of data for the table.
	 */
	public function getData(): array {
		return $this->data;
	}

	/**
	 * Get the current sort column.
	 *
	 * This method returns the ID of the column currently used for sorting the table data.
	 *
	 * @since 0.1.0
	 * @return ?string The ID of the column used for sorting.
	 */
	public function getCurrentSortColumn(): ?string {
		return $this->currentSortColumn;
	}

	/**
	 * Get the current sort direction.
	 *
	 * This method returns the current sort direction, which can be either 'asc' for ascending or 'desc' for descending.
	 *
	 * @since 0.1.0
	 * @return string The current sort direction ('asc' or 'desc').
	 */
	public function getCurrentSortDirection(): string {
		return $this->currentSortDirection;
	}

	/**
	 * Get additional HTML attributes for the table element.
	 *
	 * This method returns an associative array of custom HTML attributes applied to the `<table>` element.
	 *
	 * @since 0.1.0
	 * @return array The additional attributes as an array.
	 */
	public function getAttributes(): array {
		return $this->attributes;
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
