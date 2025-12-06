<?php
/**
 * TableBuilder.php
 *
 * This file is part of the Codex design system, the official design system
 * for Wikimedia projects. It provides the `Table` class, a builder for constructing
 * table components using the Codex design system.
 *
 * Tables are used to arrange data in rows and columns, facilitating the comparison,
 * analysis, and management of information.
 *
 * @category Builder
 * @package  Codex\Builder
 * @since    0.1.0
 * @author   Doğu Abaris <abaris@null.net>
 * @license  https://www.gnu.org/copyleft/gpl.html GPL-2.0-or-later
 * @link     https://doc.wikimedia.org/codex/main/ Codex Documentation
 */

namespace Wikimedia\Codex\Builder;

use Wikimedia\Codex\Component\Pager;
use Wikimedia\Codex\Component\Table;
use Wikimedia\Codex\Renderer\TableRenderer;

/**
 * TableBuilder
 *
 * This class implements the builder pattern to construct instances of Table.
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
class TableBuilder {

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
	protected string $id = '';

	/**
	 * Caption for the table.
	 */
	protected string $caption = '';

	/**
	 * Flag to hide or show the caption.
	 */
	protected bool $hideCaption = false;

	/**
	 * Content for the table header.
	 */
	protected ?string $headerContent = null;

	/**
	 * Array of columns in the table.
	 */
	protected array $columns = [];

	/**
	 * Array of data rows in the table.
	 */
	protected array $data = [];

	/**
	 * Flag to use row headers.
	 */
	protected bool $useRowHeaders = false;

	/**
	 * Sorting configuration.
	 */
	protected array $sort = [];

	/**
	 * Currently sorted column.
	 */
	protected ?string $currentSortColumn = null;

	/**
	 * Current sort direction.
	 */
	protected string $currentSortDirection = self::SORT_ASCENDING;

	/**
	 * Flag to show vertical borders.
	 */
	protected bool $showVerticalBorders = false;

	/**
	 * Array of additional attributes.
	 */
	protected array $attributes = [];

	/**
	 * Flag to enable pagination.
	 */
	protected bool $paginate = false;

	/**
	 * Total number of rows for pagination.
	 */
	protected int $totalRows = 0;

	/**
	 * Position of the pagination controls ('top', 'bottom', or 'both').
	 */
	protected string $paginationPosition = 'bottom';

	/**
	 * Pager object for handling pagination.
	 */
	protected ?Pager $pager = null;

	/**
	 * Content for the table footer.
	 */
	protected ?string $footer = null;

	/**
	 * The renderer instance used to render the table.
	 */
	protected TableRenderer $renderer;

	/**
	 * Constructor for the TableBuilder class.
	 *
	 * @param TableRenderer $renderer The renderer to use for rendering the table.
	 */
	public function __construct( TableRenderer $renderer ) {
		$this->renderer = $renderer;
	}

	/**
	 * Set the Table HTML ID attribute.
	 *
	 * @since 0.1.0
	 * @param string $id The ID for the Table element.
	 * @return $this
	 */
	public function setId( string $id ): self {
		$this->id = $id;

		return $this;
	}

	/**
	 * Set the caption for the table.
	 *
	 * The caption provides a description of the table's contents and purpose. It is essential for accessibility
	 * as it helps screen readers convey the context of the table to users. To visually hide the caption while
	 * keeping it accessible, use the `setHideCaption()` method.
	 *
	 * Example usage:
	 *
	 *     $table->setCaption('Article List');
	 *
	 * @since 0.1.0
	 * @param string $caption The caption text to be displayed above the table.
	 * @return $this Returns the Table instance for method chaining.
	 */
	public function setCaption( string $caption ): self {
		$this->caption = $caption;

		return $this;
	}

	/**
	 * Set whether to hide the caption.
	 *
	 * If set to true, the caption will be visually hidden but still accessible to screen readers.
	 *
	 * @since 0.1.0
	 * @param bool $hideCaption Indicates if the caption should be visually hidden.
	 * @return $this Returns the Table instance for method chaining.
	 */
	public function setHideCaption( bool $hideCaption ): self {
		$this->hideCaption = $hideCaption;

		return $this;
	}

	/**
	 * Set the columns for the table.
	 *
	 * Each column is defined by an associative array with attributes such as 'id', 'label', 'sortable', etc.
	 *
	 * Example usage:
	 *
	 *     $table->setColumns([
	 *         ['id' => 'title', 'label' => 'Title', 'sortable' => true],
	 *         ['id' => 'creation_date', 'label' => 'Creation Date', 'sortable' => false]
	 *     ]);
	 *
	 * @since 0.1.0
	 * @param array $columns An array of columns, where each column is an associative array containing column
	 *                       attributes.
	 * @param-taint $columns exec_html Callers are responsible for escaping.
	 * @return $this Returns the Table instance for method chaining.
	 */
	public function setColumns( array $columns ): self {
		$this->columns = $columns;

		return $this;
	}

	/**
	 * Set the data for the table.
	 *
	 * The data array should correspond to the columns defined. Each row is an associative array where keys match
	 * column IDs.
	 *
	 * Example usage:
	 *
	 *     $table->setData([
	 *         ['title' => 'Mercury', 'creation_date' => '2024-01-01'],
	 *         ['title' => 'Venus', 'creation_date' => '2024-01-02'],
	 *     ]);
	 *
	 * @since 0.1.0
	 * @param array $data An array of data to be displayed in the table, where each row is an associative array with
	 *                    keys matching column IDs.
	 * @return $this Returns the Table instance for method chaining.
	 */
	public function setData( array $data ): self {
		$this->data = $data;

		return $this;
	}

	/**
	 * Set whether to use row headers.
	 *
	 * If enabled, the first column of the table will be treated as row headers. This is useful for accessibility
	 * and to provide additional context for each row.
	 *
	 * @since 0.1.0
	 * @param bool $useRowHeaders Indicates if row headers should be used.
	 * @return $this Returns the Table instance for method chaining.
	 */
	public function setUseRowHeaders( bool $useRowHeaders ): self {
		$this->useRowHeaders = $useRowHeaders;

		return $this;
	}

	/**
	 * Set whether to show vertical borders between columns.
	 *
	 * Vertical borders can help distinguish between columns, especially in tables with many columns.
	 *
	 * @since 0.1.0
	 * @param bool $showVerticalBorders Indicates if vertical borders should be displayed between columns.
	 * @return $this Returns the Table instance for method chaining.
	 */
	public function setShowVerticalBorders( bool $showVerticalBorders ): self {
		$this->showVerticalBorders = $showVerticalBorders;

		return $this;
	}

	/**
	 * Set the sort order for the table.
	 *
	 * This method defines the initial sort order for the table. The array should contain
	 * column IDs as keys and sort directions ('asc' or 'desc') as values.
	 *
	 * Example usage:
	 *
	 *     $table->setSort([
	 *         'column1' => 'asc',
	 *         'column2' => 'desc'
	 *     ]);
	 *
	 * @since 0.1.0
	 * @param array $sort An associative array of column IDs and their respective sort directions ('asc' or 'desc').
	 * @return $this Returns the Table instance for method chaining.
	 */
	public function setSort( array $sort ): self {
		$this->sort = $sort;

		return $this;
	}

	/**
	 * Set whether the table should be paginated.
	 *
	 * If enabled, pagination controls will be added to the table, allowing users to navigate through multiple pages of
	 * data.
	 *
	 * @since 0.1.0
	 * @param bool $paginate Indicates if the table should be paginated.
	 * @return $this Returns the Table instance for method chaining.
	 */
	public function setPaginate( bool $paginate ): self {
		$this->paginate = $paginate;

		return $this;
	}

	/**
	 * Set the total number of rows in the table.
	 *
	 * This value is used in conjunction with pagination to calculate the total number of pages and to display the
	 * current range of rows.
	 *
	 * @since 0.1.0
	 * @param int $totalRows The total number of rows in the table.
	 * @return $this Returns the Table instance for method chaining.
	 */
	public function setTotalRows( int $totalRows ): self {
		$this->totalRows = $totalRows;

		return $this;
	}

	/**
	 * Set the position of the pagination controls.
	 *
	 * The pagination controls can be displayed at the top, bottom, or both top and bottom of the table.
	 *
	 * @since 0.1.0
	 * @param string $paginationPosition The position of the pagination controls ('top', 'bottom', 'both').
	 * @return $this Returns the Table instance for method chaining.
	 */
	public function setPaginationPosition( string $paginationPosition ): self {
		$this->paginationPosition = $paginationPosition;

		return $this;
	}

	/**
	 * Set additional HTML attributes for the table element.
	 *
	 * This method allows custom HTML attributes to be added to the `<table>` element, such as `id`, `class`,
	 * or `data-*` attributes. These attributes are automatically escaped to prevent XSS vulnerabilities.
	 *
	 * Example usage:
	 *
	 *     $table->setAttributes(['class' => 'custom-table-class', 'data-info' => 'additional-info']);
	 *
	 * @since 0.1.0
	 * @param array $attributes An associative array of HTML attributes to be added to the `<table>` element.
	 *
	 * @return $this Returns the Table instance for method chaining.
	 */
	public function setAttributes( array $attributes ): self {
		foreach ( $attributes as $key => $value ) {
			$this->attributes[$key] = $value;
		}
		return $this;
	}

	/**
	 * Set the Pager instance for the table.
	 *
	 * The Pager instance provides pagination controls for the table. If set, pagination controls will be rendered
	 * according to the settings.
	 *
	 * @since 0.1.0
	 * @param Pager $pager The Pager instance.
	 * @return $this Returns the Table instance for method chaining.
	 */
	public function setPager( Pager $pager ): self {
		$this->pager = $pager;

		return $this;
	}

	/**
	 * Set the footer content for the table.
	 *
	 * The footer is an optional section that can contain additional information or actions related to the table.
	 *
	 * @since 0.1.0
	 * @param string $footer The footer content.
	 * @return $this Returns the Table instance for method chaining.
	 */
	public function setFooter( string $footer ): self {
		$this->footer = $footer;

		return $this;
	}

	/**
	 * Set the header content for the table.
	 *
	 * This method allows custom content to be added to the table's header, such as actions or additional text.
	 *
	 * Example usage:
	 *
	 *     $table->setHeaderContent('Custom Actions');
	 *
	 * @since 0.1.0
	 * @param string $headerContent The content to be displayed in the table header.
	 * @param-taint $headerContent exec_html Callers are responsible for escaping.
	 * @return $this Returns the Table instance for method chaining.
	 */
	public function setHeaderContent( string $headerContent ): self {
		$this->headerContent = $headerContent;

		return $this;
	}

	/**
	 * Set the current sort column.
	 *
	 * This method specifies which column is currently being used for sorting the table data.
	 * The column with this ID will be marked as sorted in the table header.
	 *
	 * Example usage:
	 *
	 *     $table->setCurrentSortColumn('title');
	 *
	 * @since 0.1.0
	 * @param string $currentSortColumn The ID of the column used for sorting.
	 *
	 * @return $this Returns the Table instance for method chaining.
	 */
	public function setCurrentSortColumn( string $currentSortColumn ): self {
		$this->currentSortColumn = $currentSortColumn;

		return $this;
	}

	/**
	 * Set the current sort direction.
	 *
	 * This method specifies the direction for sorting the table data. Acceptable values are 'asc' for ascending
	 * and 'desc' for descending. The method validates these values to ensure they are correct.
	 *
	 * Example usage:
	 *
	 *     $table->setCurrentSortDirection('asc');
	 *
	 * @since 0.1.0
	 * @param string $currentSortDirection The sort direction ('asc' or 'desc').
	 *
	 * @return $this Returns the Table instance for method chaining.
	 */
	public function setCurrentSortDirection( string $currentSortDirection ): self {
		if ( $currentSortDirection === self::SORT_ASCENDING || $currentSortDirection === self::SORT_DESCENDING ) {
			$this->currentSortDirection = $currentSortDirection;
		}

		return $this;
	}

	/**
	 * Build and return the Table component object.
	 * This method constructs the immutable Table object with all the properties set via the builder.
	 *
	 * @since 0.1.0
	 * @return Table The constructed Table.
	 */
	public function build(): Table {
		return new Table(
			$this->id,
			$this->caption,
			$this->hideCaption,
			$this->columns,
			$this->data,
			$this->useRowHeaders,
			$this->headerContent,
			$this->sort,
			$this->currentSortColumn,
			$this->currentSortDirection,
			$this->showVerticalBorders,
			$this->attributes,
			$this->paginate,
			$this->totalRows,
			$this->paginationPosition,
			$this->pager,
			$this->footer,
			$this->renderer
		);
	}
}
