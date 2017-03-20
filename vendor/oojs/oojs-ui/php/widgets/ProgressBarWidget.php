<?php

namespace OOUI;

class ProgressBarWidget extends Widget {
	/**
	 * @var bool|int
	 */
	protected $progress;

	/**
	 * @var Tag
	 */
	protected $bar;

	/**
	 * @param array $config Configuration options
	 * @param bool|int $config['progress'] The type of progress bar (determinate or indeterminate).
	 *                                     To create a determinate progress bar,specify a number
	 *                                     that reflects the initial percent complete.
	 *                                     By default, the progress bar is indeterminate.
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
	}

	/**
	 * @return bool|int
	 */
	public function getProgress() {
		return $this->progress;
	}

	/**
	 * @param bool|int $progress
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

	public function getConfig( &$config ) {
		if ( $this->progress !== null ) {
			$config['progress'] = $this->progress;
		}
		return parent::getConfig( $config );
	}
}
