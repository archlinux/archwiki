<?php

namespace OOUI;

class ProgressBarWidget extends Widget {
	/**
	 * @var bool|int
	 */
	protected $progress;

	/**
	 * @var bool
	 */
	protected $inline;

	/**
	 * @var Tag
	 */
	protected $bar;

	/**
	 * @param array $config Configuration options
	 *      - bool|int $config['progress'] Numeric value between 0 and 100 (the percent complete)
	 *          for determinate progress bar, or `false` for indeterminate progress bar
	 *          (default: false)
	 *      - bool $config['inline'] Use a smaller inline variant on the progress bar
	 *          (default: false)
	 */
	public function __construct( array $config = [] ) {
		parent::__construct( $config );

		$this->bar = new Tag( 'div' );
		$this->bar->addClasses( [ 'oo-ui-progressBarWidget-bar' ] );

		$this->setProgress( array_key_exists( 'progress', $config ) ? $config['progress'] : false );

		$this
			->setAttributes( [
				'role' => 'progressbar',
				'aria-valuemin' => 0,
				'aria-valuemax' => 100,
				] )
			->addClasses( [ 'oo-ui-progressBarWidget' ] )
			->appendContent( $this->bar );

		$this->inline = $config['inline'] ?? false;

		if ( $this->inline ) {
			$this->addClasses( [ 'oo-ui-progressBarWidget-inline' ] );
		}
	}

	/**
	 * @return bool|int
	 */
	public function getProgress() {
		return $this->progress;
	}

	/**
	 * @param bool|int $progress Numeric value between 0 and 100 (the percent complete)
	 *     for determinate progress bar, or `false` for indeterminate progress bar (default: false)
	 */
	public function setProgress( $progress ) {
		$this->progress = $progress;

		if ( $progress !== false ) {
			$this->bar->setAttributes( [ 'style' => 'width: ' . $this->progress . '%;' ] );
			$this->setAttributes( [ 'aria-valuenow' => $this->progress ] );
		} else {
			$this->removeAttributes( [ 'aria-valuenow' ] );
		}
		$this->toggleClasses( [ 'oo-ui-progressBarWidget-indeterminate' ], $progress === false );
	}

	/** @inheritDoc */
	public function getConfig( &$config ) {
		if ( $this->progress !== null ) {
			$config['progress'] = $this->progress;
		}
		if ( $this->inline ) {
			$config['inline'] = $this->inline;
		}
		return parent::getConfig( $config );
	}
}
