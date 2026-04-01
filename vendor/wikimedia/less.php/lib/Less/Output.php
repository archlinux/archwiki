<?php
/**
 * Parser output
 *
 * @private
 */
class Less_Output {

	/**
	 * Output holder
	 *
	 * @var string[]
	 */
	protected $strs = [];

	/**
	 * Adds a chunk to the stack
	 *
	 * @param string $chunk The chunk to output
	 * @param array|null $fileInfo The file information
	 * @param int $index The index
	 * @param bool|null $mapLines
	 */
	public function add( $chunk, $fileInfo = null, $index = 0, $mapLines = null ) {
		$this->strs[] = $chunk;
	}

	/**
	 * Converts the output to string
	 *
	 * @return string
	 */
	public function toString() {
		return implode( '', $this->strs );
	}

}
