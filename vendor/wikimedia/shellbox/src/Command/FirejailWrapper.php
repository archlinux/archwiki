<?php

namespace Shellbox\Command;

use Shellbox\Shellbox;

/**
 * A wrapper that restricts the command using firejail
 */
class FirejailWrapper extends Wrapper {
	/**
	 * Firejail is a setuid-root executable which naturally goes inside systemd
	 * but outside BashWrapper, since it inherits and preserves most aspects of
	 * the system.
	 */
	public const PRIORITY = 40;

	/**
	 * @var string The path to firejail
	 */
	private $binaryPath;

	/**
	 * @var string The path to the profile file
	 */
	private $profilePath;

	/**
	 * @param string $binaryPath The path to firejail
	 * @param string $profilePath The path to the profile file
	 */
	public function __construct( $binaryPath, $profilePath ) {
		parent::__construct();
		$this->binaryPath = $binaryPath;
		$this->profilePath = $profilePath;
	}

	public function wrap( Command $command ) {
		// If there are no restrictions, don't use firejail
		if ( $command->getDisableSandbox() ) {
			$splitCommand = explode( ' ', $command->getCommandString(), 2 );
			$this->logger->debug(
				"firejail: Command {$splitCommand[0]} {params} has no restrictions",
				[ 'params' => $splitCommand[1] ?? '' ]
			);
			return;
		}

		// quiet has to come first to prevent firejail from adding
		// any output.
		$cmd = [ $this->binaryPath, '--quiet' ];
		// Use a profile that allows people to add local overrides
		// if their system is setup in an incompatible manner. Also it
		// prevents any default profiles from running.
		// FIXME: Doesn't actually override command-line switches?
		$cmd[] = '--profile=' . $this->profilePath;

		$whitelistedPaths = $command->getAllowedPaths();
		// Whitelist our own sources
		$whitelistedPaths[] = dirname( __DIR__ );

		foreach ( $command->getAllowedPaths() as $whitelistedPath ) {
			if ( $whitelistedPath === '/home' ) {
				$cmd[] = '--allusers';
			} else {
				$cmd[] = "--whitelist={$whitelistedPath}";
			}
		}

		foreach ( $command->getDisallowedPaths() as $path ) {
			$cmd[] = '--blacklist=' . $path;
		}

		if ( $command->getPrivateUserNamespace() ) {
			$cmd[] = '--noroot';
		}

		$extraSeccomp = $command->getDisabledSyscalls();
		$useSeccomp = $extraSeccomp || $command->getFirejailDefaultSeccomp();

		if ( in_array( 'execve', $extraSeccomp ) ) {
			// Normally firejail will run commands in a bash shell,
			// but that won't work if we ban the execve syscall, so
			// run the command without a shell.
			$cmd[] = '--shell=none';
		}

		if ( $useSeccomp ) {
			$seccomp = '--seccomp';
			if ( $extraSeccomp ) {
				// The "@default" seccomp group will always be enabled
				$seccomp .= '=' . implode( ',', $extraSeccomp );
			}
			$cmd[] = $seccomp;
		}

		if ( $command->getPrivateDev() ) {
			$cmd[] = '--private-dev';
		}

		if ( $command->getDisableNetwork() ) {
			$cmd[] = '--net=none';
		}

		foreach ( $command->getEnvironment() as $name => $value ) {
			$cmd[] = "--env=$name=$value";
		}

		$builtCmd = Shellbox::escape( $cmd );

		// Prefix the firejail command in front of the wanted command
		$command->unsafeCommand( "$builtCmd -- " . $command->getCommandString() );
	}

	public function getPriority() {
		return self::PRIORITY;
	}
}
