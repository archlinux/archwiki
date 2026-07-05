<?php

namespace MediaWiki\Extension\TemplateData;

use StatusValue;

/**
 * @license GPL-2.0-or-later
 */
class TemplateDataStatus {

	/**
	 * @return array contains StatusValue ok and errors fields (does not serialize value)
	 */
	public static function jsonSerialize( StatusValue $status ): array {
		if ( $status->isOK() ) {
			return [ 'ok' => true ];
		}

		[ $errorsOnlyStatus, $warningsOnlyStatus ] = $status->splitByErrorType();
		// note that non-scalar values are not supported in errors or warnings
		return [
			'ok' => false,
			'errors' => $errorsOnlyStatus->getErrors(),
			'warnings' => $warningsOnlyStatus->getErrors()
		];
	}

	/**
	 * @param StatusValue|array|null $json contains StatusValue ok and errors fields (does not serialize value)
	 * @return StatusValue|null
	 */
	public static function newFromJson( $json ): ?StatusValue {
		if ( !is_array( $json ) ) {
			return $json;
		}

		if ( $json['ok'] ) {
			return StatusValue::newGood();
		}

		$status = new StatusValue();
		foreach ( $json['errors'] as $error ) {
			$status->fatal( $error['message'], ...$error['params'] );
		}
		foreach ( $json['warnings'] as $warning ) {
			$status->warning( $warning['message'], ...$warning['params'] );
		}
		return $status;
	}

}
