<?php

namespace PageImages;

use JsonSerializable;
use MediaWiki\FileRepo\File\File;

/**
 * Value object to hold information about page image candidates.
 * @package PageImages
 */
class PageImageCandidate implements JsonSerializable {

	private string $fileName;
	private int $fullWidth = 0;
	private int $fullHeight = 0;
	private int $handlerWidth = 0;
	private string $frameClass = '';

	/**
	 * Private constructor.
	 * Use self::newFromFileAndParams to instantiate.
	 */
	private function __construct() {
	}

	/**
	 * @param File $file
	 * @param array $fileParams from ParserMakeImageParams hook.
	 * @return self
	 */
	public static function newFromFileAndParams( File $file, array $fileParams ): self {
		$instance = new self();
		$instance->fileName = $file->getTitle()->getDBkey();
		$instance->fullWidth = $file->getWidth() ?? 0;
		$instance->fullHeight = $file->getHeight() ?? 0;
		if ( isset( $fileParams['handler']['width'] ) ) {
			$instance->handlerWidth = (int)( $fileParams['handler']['width'] ?? 0 );
		}
		if ( isset( $fileParams['frame']['class'] ) ) {
			// $fileParams['frame']['class'] is set in Parser::makeImage
			$instance->frameClass = $fileParams['frame']['class'] ?? '';
		}
		return $instance;
	}

	/**
	 * Instantiate PageImageCandidate from $json created with self::jsonSerialize
	 *
	 * @internal
	 */
	public static function newFromArray( array $array ): self {
		$instance = new self();
		$instance->fileName = $array['filename'];
		$instance->fullWidth = $array['fullwidth'] ?? 0;
		$instance->fullHeight = $array['fullheight'] ?? 0;
		if ( isset( $array['handler']['width'] ) ) {
			$instance->handlerWidth = $array['handler']['width'] ?? 0;
		}
		if ( isset( $array['frame']['class'] ) ) {
			$instance->frameClass = $array['frame']['class'] ?? '';
		}
		return $instance;
	}

	public function getFileName(): string {
		return $this->fileName;
	}

	public function getFullWidth(): int {
		return $this->fullWidth;
	}

	public function getFullHeight(): int {
		return $this->fullHeight;
	}

	public function getHandlerWidth(): int {
		return $this->handlerWidth;
	}

	public function getFrameClass(): string {
		return $this->frameClass;
	}

	/**
	 * @internal
	 */
	public function jsonSerialize(): array {
		return [
			'filename' => $this->getFileName(),
			'fullwidth' => $this->getFullWidth(),
			'fullheight' => $this->getFullHeight(),
			// Wrap in handler array for backwards-compatibility.
			'handler' => [
				'width' => $this->getHandlerWidth()
			],
			'frame' => [
				'class' => $this->getFrameClass()
			]
		];
	}
}
