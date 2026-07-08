<?php
/**
 * @license GPL-2.0-or-later
 * @file
 */

namespace MediaWiki\User;

use Psr\Log\LoggerInterface;

class UserRequirementsConditionValidator {

	public function __construct(
		private readonly LoggerInterface $logger
	) {
	}

	/**
	 * Checks if the passed user requirements condition is valid.
	 * If it's invalid, the details of what's wrong are logged.
	 */
	public function isValid( mixed $condition ): bool {
		// Empty array at top level is allowed; elsewhere it's not
		if ( $condition === [] ) {
			return true;
		}
		return $this->isValidInternal( $condition );
	}

	private function isValidInternal( mixed $condition ): bool {
		if ( !is_array( $condition ) ) {
			$condition = [ $condition ];
		}
		if ( $condition === [] ) {
			$this->logger->warning( 'Empty array is invalid descriptor of a user requirements condition' );
			return false;
		}

		$condType = $condition[0];
		$args = array_slice( $condition, 1 );

		if ( in_array( $condType, UserRequirementsConditionChecker::VALID_OPS ) ) {
			// Ensure proper argument count: for XOR - exactly 2, for others - at least 1
			if ( $condType === '^' && count( $args ) !== 2 ) {
				$this->logger->warning( 'XOR (^) in user requirements conditions must have exactly two arguments' );
				return false;
			}
			if ( count( $args ) === 0 ) {
				$this->logger->warning( 'Compound conditions must have at least one argument' );
				return false;
			}

			foreach ( $args as $arg ) {
				if ( !$this->isValidInternal( $arg ) ) {
					// Don't log anything here, it's logged in the original place
					return false;
				}
			}
			return true;
		}

		if ( !is_string( $condType ) && !is_int( $condType ) ) {
			$this->logger->warning( 'User requirements conditions must be designated as string or int' );
			return false;
		}
		return true;
	}
}
