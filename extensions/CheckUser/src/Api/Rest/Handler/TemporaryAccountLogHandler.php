<?php

namespace MediaWiki\CheckUser\Api\Rest\Handler;

use MediaWiki\Rest\LocalizedHttpException;
use Wikimedia\Message\DataMessageValue;
use Wikimedia\ParamValidator\ParamValidator;
use Wikimedia\Rdbms\IReadableDatabase;

class TemporaryAccountLogHandler extends AbstractTemporaryAccountNameHandler {

	use TemporaryAccountLogTrait;

	/**
	 * @inheritDoc
	 */
	protected function getData( $actorId, IReadableDatabase $dbr ): array {
		$ids = $this->getValidatedParams()['ids'];
		if ( !count( $ids ) ) {
			throw new LocalizedHttpException(
				DataMessageValue::new( 'paramvalidator-missingparam', [], 'missingparam' )
					->plaintextParams( "ids" ),
				400,
				[
					'error' => 'parameter-validation-failed',
					'name' => 'ids',
					'value' => '',
					'failureCode' => "missingparam",
					'failureData' => null,
				]
			);
		}

		return [ 'ips' => $this->getLogIps( $actorId, $ids, $dbr ) ];
	}

	/**
	 * @inheritDoc
	 */
	public function getParamSettings() {
		$settings = parent::getParamSettings();
		$settings['ids'] = [
			self::PARAM_SOURCE => 'path',
			ParamValidator::PARAM_TYPE => 'integer',
			ParamValidator::PARAM_REQUIRED => true,
			ParamValidator::PARAM_ISMULTI => true,
		];
		return $settings;
	}
}
