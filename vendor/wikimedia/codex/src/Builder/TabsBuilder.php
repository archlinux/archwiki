<?php
/**
 * TabsBuilder.php
 *
 * This file is part of the Codex design system, the official design system
 * for Wikimedia projects. It provides the `Tabs` class, a builder for constructing
 * tabs components using the Codex design system.
 *
 * Tabs consist of two or more tab items created for navigating between different sections of content.
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
use Wikimedia\Codex\Component\Tab;
use Wikimedia\Codex\Component\Tabs;
use Wikimedia\Codex\Renderer\TabsRenderer;

/**
 * TabsBuilder
 *
 * This class implements the builder pattern to construct instances of Tabs.
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
class TabsBuilder {

	/**
	 * The ID for the tabs.
	 */
	protected string $id = '';

	/**
	 * An array of Tab objects representing each tab in the component.
	 */
	private array $tabs = [];

	/**
	 * Additional MarkupHandler attributes for the `<form>` element.
	 */
	protected array $attributes = [];

	/**
	 * The renderer instance used to render the tabs.
	 */
	protected TabsRenderer $renderer;

	/**
	 * Constructor for the TabsBuilder class.
	 *
	 * @param TabsRenderer $renderer The renderer to use for rendering the tabs.
	 */
	public function __construct( TabsRenderer $renderer ) {
		$this->renderer = $renderer;
	}

	/**
	 * Set the Tabs HTML ID attribute.
	 *
	 * @since 0.1.0
	 * @param string $id The ID for the Tabs element.
	 * @return $this
	 */
	public function setId( string $id ): self {
		$this->id = $id;

		return $this;
	}

	/**
	 * Add one or more tabs to the tabs component.
	 *
	 * This method allows the addition of one or more `Tab` objects to the tabs component.
	 * Each tab's properties, such as label, content, selected state, and disabled state,
	 * are used to construct and configure the tabs.
	 *
	 * Example usage:
	 *
	 *     $tabs->setTab($tab1)
	 *         ->setTab([$tab2, $tab3]);
	 *
	 * @since 0.1.0
	 * @param TabBuilder|array $tab A `Tab` object or an array of `Tab` objects to add.
	 * @return $this Returns the Tabs instance for method chaining.
	 */
	public function setTab( $tab ): self {
		if ( is_array( $tab ) ) {
			foreach ( $tab as $t ) {
				$this->tabs[] = $t;
			}
		} else {
			$this->tabs[] = $tab;
		}

		return $this;
	}

	/**
	 * Set additional HTML attributes for the `<form>` element.
	 *
	 * This method allows custom HTML attributes to be added to the `<form>` element that wraps the tabs,
	 * such as `id`, `data-*`, `aria-*`, or any other valid attributes. These attributes can be used
	 * to enhance accessibility or integrate with JavaScript.
	 *
	 * Example usage:
	 *
	 *     $tabs->setAttributes([
	 *         'id' => 'tabs-form',
	 *         'data-category' => 'navigation',
	 *     ]);
	 *
	 * @since 0.1.0
	 * @param array $attributes An associative array of HTML attributes.
	 * @return $this Returns the Tabs instance for method chaining.
	 */
	public function setAttributes( array $attributes ): self {
		foreach ( $attributes as $key => $value ) {
			$this->attributes[$key] = $value;
		}
		return $this;
	}

	/**
	 * Build and return the Tabs component object.
	 * This method constructs the immutable Tabs object with all the properties set via the builder.
	 *
	 * @since 0.1.0
	 * @return Tabs The constructed Tabs.
	 */
	public function build(): Tabs {
		$tabComponents = [];
		foreach ( $this->tabs as $tab ) {
			if ( $tab instanceof TabBuilder ) {
				$tabComponents[] = $tab->build();
			} elseif ( $tab instanceof Tab ) {
				$tabComponents[] = $tab;
			} else {
				throw new InvalidArgumentException( 'All tabs must be instances of TabBuilder or Tab' );
			}
		}

		return new Tabs(
			$this->id,
			$tabComponents,
			$this->attributes,
			$this->renderer
		);
	}
}
