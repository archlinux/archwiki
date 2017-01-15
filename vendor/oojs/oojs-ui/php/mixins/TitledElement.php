<?php

namespace OOUI;

/**
 * Element with a title.
 *
 * Titles are rendered by the browser and are made visible when hovering the element. Titles are
 * not visible on touch devices.
 *
 * @abstract
 */
trait TitledElement {
	/**
	 * Title text.
	 *
	 * @var string
	 */
	protected $title = null;

	/**
	 * @var Element
	 */
	protected $titled;

	/**
	 * @param array $config Configuration options
	 * @param string $config['title'] Title. If not provided, the static property 'title' is used.
	 */
	public function initializeTitledElement( array $config = [] ) {
		// Properties
		$this->titled = isset( $config['titled'] ) ? $config['titled'] : $this;

		// Initialization
		$this->setTitle(
			isset( $config['title'] ) ? $config['title'] : null
		);

		$this->registerConfigCallback( function( &$config ) {
			if ( $this->title !== null ) {
				$config['title'] = $this->title;
			}
		} );

	}

	/**
	 * Set title.
	 *
	 * @param string|null $title Title text or null for no title
	 * @return $this
	 */
	public function setTitle( $title ) {
		$title = $title !== '' ? $title : null;

		if ( $this->title !== $title ) {
			$this->title = $title;
			if ( $title !== null ) {
				$this->titled->setAttributes( [ 'title' => $title ] );
			} else {
				$this->titled->removeAttributes( [ 'title' ] );
			}
		}

		return $this;
	}

	/**
	 * Get title.
	 *
	 * @return string Title string
	 */
	public function getTitle() {
		return $this->title;
	}
}
