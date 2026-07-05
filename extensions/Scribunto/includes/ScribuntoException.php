<?php

namespace MediaWiki\Extension\Scribunto;

use Exception;
use MediaWiki\Status\Status;
use MediaWiki\Title\Title;

/**
 * An exception class which represents an error in the script. This does not
 * normally abort the request, instead it is caught and shown to the user.
 */
class ScribuntoException extends Exception {

	public array $messageArgs;
	/** Output of lua mw.log() */
	private ?string $log = null;

	/**
	 * @param string $messageName
	 * @param array{args?: array, module?: string, line?: string, title?: Title, trace?: array} $params
	 */
	public function __construct(
		public readonly string $messageName,
		public array $params = [],
	) {
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
			$codeLocation = '(unknown code location)';
		}
		array_unshift( $this->messageArgs, $codeLocation );
		$msg = wfMessage( $messageName )
			->params( $this->messageArgs )
			->inContentLanguage();
		if ( isset( $params['title'] ) ) {
			$msg = $msg->page( $params['title'] );
		}
		if ( isset( $params['log'] ) ) {
			$this->log = $params['log'];
		}
		parent::__construct( $msg->text() );
	}

	public function getMessageName(): string {
		return $this->messageName;
	}

	public function toStatus(): Status {
		$status = Status::newFatal( $this->messageName, ...$this->messageArgs );
		$status->value = $this;
		return $status;
	}

	/**
	 * Get the lua log buffer if available
	 *
	 * @return ?string Will return null if logs are not available.
	 */
	public function getLog(): ?string {
		return $this->log;
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
