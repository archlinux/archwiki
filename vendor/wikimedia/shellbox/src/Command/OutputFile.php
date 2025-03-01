<?php

namespace Shellbox\Command;

use Shellbox\ShellboxError;

/**
 * The base class for encapsulated output files.
 *
 * An OutputFile object is a declaration of a file expected to be created by a
 * command. These objects are created on the client side before command
 * execution, then they are serialized, sent to the server, unserialized,
 * populated with contents, then sent back to the client where the contents
 * will be put into its declared destination.
 */
abstract class OutputFile {
	use UserDataTrait;

	/** @var bool */
	protected $received = false;
	/** @var callable[] */
	private $receivedListeners = [];
	/** @var int|null */
	private $requiredExitCode;

	/**
	 * Add a callback to call after the file is received
	 *
	 * @since 4.1.0
	 * @param callable $callback
	 * @return $this
	 */
	public function onReceived( callable $callback ) {
		$this->receivedListeners[] = $callback;
		return $this;
	}

	/**
	 * Upload/return the file only if the command returns the specified exit
	 * code.
	 *
	 * @since 4.1.0
	 * @param ?int $status
	 * @return $this
	 */
	public function requireExitCode( ?int $status = 0 ) {
		$this->requiredExitCode = $status;
		return $this;
	}

	/**
	 * Return true if the file was received from the command or server.
	 *
	 * @return bool
	 */
	public function wasReceived() {
		return $this->received;
	}

	/**
	 * Set the received flag to true and notify the listeners
	 *
	 * @internal
	 */
	protected function setReceived() {
		$this->received = true;
		foreach ( $this->receivedListeners as $listener ) {
			$listener();
		}
	}

	/**
	 * Get data for JSON serialization by the client.
	 *
	 * @internal
	 * @return array
	 */
	public function getClientData() {
		$data = [];
		if ( $this->requiredExitCode !== null ) {
			$data['requiredExitCode'] = $this->requiredExitCode;
		}
		return $data;
	}

	/**
	 * @internal
	 * @return int|null
	 */
	public function getRequiredExitCode() {
		return $this->requiredExitCode;
	}

	/**
	 * This is used to create a placeholder object for use on the server side.
	 * It doesn't need to actually be functional since the server is responsible
	 * for reading output files.
	 *
	 * @internal
	 * @param array $data
	 * @return OutputFile
	 */
	public static function newFromClientData( $data ) {
		if ( ( $data['type'] ?? '' ) === 'url' ) {
			if ( !isset( $data['url'] ) ) {
				throw new ShellboxError( 'Missing required parameter for URL file: "url"' );
			}
			$file = new OutputFileToUrl( $data['url'] );
			if ( isset( $data['headers'] ) ) {
				$file->headers( $data['headers'] );
			}
			if ( isset( $data['mwContentHash'] ) ) {
				$file->enableMwContentHash( $data['mwContentHash'] );
			}
		} else {
			$file = new OutputFilePlaceholder;
		}
		if ( isset( $data['requiredExitCode'] ) ) {
			$file->requireExitCode( $data['requiredExitCode'] );
		}
		return $file;
	}
}
