<?php

/**
 * Parser Exception
 */
class Less_Exception_Parser extends Exception {

	/**
	 * The current file
	 *
	 * @var array
	 */
	public $currentFile;

	/**
	 * The current parser index
	 *
	 * @var int
	 */
	public $index;

	/** @var string */
	public $finalMessage = '';

	/** @var string|null */
	protected $input;

	/**
	 * @param string|null $message
	 * @param Exception|null $previous Previous exception
	 * @param int|null $index The current parser index
	 * @param array|null $currentFile The file
	 * @param int $code The exception code
	 */
	public function __construct( $message = null, ?Exception $previous = null, $index = null, $currentFile = null, $code = 0 ) {
		parent::__construct( $message, $code, $previous );

		$this->currentFile = $currentFile;
		$this->index = $index;

		$this->genMessage();
	}

	protected function getInput() {
		if ( !$this->input && $this->currentFile && $this->currentFile['filename'] && file_exists( $this->currentFile['filename'] ) ) {
			$this->input = file_get_contents( $this->currentFile['filename'] );
		}
	}

	/**
	 * Set a message based on the exception info
	 */
	public function genMessage() {
		if ( $this->currentFile && $this->currentFile['filename'] ) {
			$this->finalMessage .= ' in ' . basename( $this->currentFile['filename'] );
		}

		if ( $this->index !== null ) {
			$this->getInput();
			if ( $this->input ) {
				$line = self::getLineNumber();
				$this->finalMessage .= ' on line ' . $line . ', column ' . self::getColumn();

				$lines = explode( "\n", $this->input );

				$count = count( $lines );
				$start_line = max( 0, $line - 3 );
				$last_line = min( $count, $start_line + 6 );
				$num_len = strlen( $last_line );
				for ( $i = $start_line; $i < $last_line; $i++ ) {
					$this->finalMessage .= "\n" . str_pad( (string)( $i + 1 ), $num_len, '0', STR_PAD_LEFT ) . '| ' . $lines[$i];
				}
			}
		}
	}

	/**
	 * Returns the line number the error was encountered
	 *
	 * @return int
	 */
	public function getLineNumber() {
		if ( $this->index ) {
			return substr_count( $this->input, "\n", 0, $this->index ) + 1;
		}
		return 1;
	}

	/**
	 * Returns the column the error was encountered
	 *
	 * @return int
	 */
	public function getColumn() {
		$part = substr( $this->input, 0, $this->index );
		$pos = strrpos( $part, "\n" );
		return $this->index - $pos;
	}

	public function getFinalMessage() {
		$this->message .= $this->finalMessage;
	}
}
