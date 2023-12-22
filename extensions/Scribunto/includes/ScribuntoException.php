<?php

namespace MediaWiki\Extension\Scribunto;

use Exception;
use MediaWiki\Title\Title;
use Status;

/**
 * An exception class which represents an error in the script. This does not
 * normally abort the request, instead it is caught and shown to the user.
 */
class ScribuntoException extends Exception {
	/**
	 * @var string
	 */
	public $messageName;

	/**
	 * @var array
	 */
	public $messageArgs;

	/**
	 * @var array
	 */
	public $params;

	/**
	 * @param string $messageName
	 * @param array $params
	 */
	public function __construct( $messageName, $params = [] ) {
		$this->messageArgs = $params['args'] ?? [];
		if ( isset( $params['module'] ) && isset( $params['line'] ) ) {
			$codeLocation = false;
			if ( isset( $params['title'] ) ) {
				$moduleTitle = Title::newFromText( $params['module'] );
				if ( $moduleTitle && $moduleTitle->equals( $params['title'] ) ) {
					$codeLocation = wfMessage( 'scribunto-line', $params['line'] )->inContentLanguage()->text();
				}
			}
			if ( $codeLocation === false ) {
				$codeLocation = wfMessage(
					'scribunto-module-line',
					$params['module'],
					$params['line']
				)->inContentLanguage()->text();
			}
		} else {
			$codeLocation = '[UNKNOWN]';
		}
		array_unshift( $this->messageArgs, $codeLocation );
		$msg = wfMessage( $messageName )->params( $this->messageArgs )->inContentLanguage()->text();
		parent::__construct( $msg );

		$this->messageName = $messageName;
		$this->params = $params;
	}

	/**
	 * @return string
	 */
	public function getMessageName() {
		return $this->messageName;
	}

	public function toStatus() {
		$status = Status::newFatal( $this->messageName, ...$this->messageArgs );
		$status->value = $this;
		return $status;
	}

	/**
	 * Get the backtrace as HTML, or false if there is none available.
	 * @param array $options
	 * @return bool|string
	 */
	public function getScriptTraceHtml( $options = [] ) {
		return false;
	}
}
