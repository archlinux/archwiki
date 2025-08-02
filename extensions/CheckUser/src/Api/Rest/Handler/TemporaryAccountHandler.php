<?php

namespace MediaWiki\CheckUser\Api\Rest\Handler;

use MediaWiki\CheckUser\CheckUserQueryInterface;
use Wikimedia\ParamValidator\ParamValidator;
use Wikimedia\ParamValidator\TypeDef\IntegerDef;
use Wikimedia\Rdbms\IReadableDatabase;

class TemporaryAccountHandler extends AbstractTemporaryAccountNameHandler implements CheckUserQueryInterface {

	use TemporaryAccountNameTrait;

	/**
	 * @inheritDoc
	 */
	protected function getData( $actorId, IReadableDatabase $dbr ): array {
		// The limit is the smaller of the user-provided limit parameter and the maximum row count.
		$limit = min(
			$this->getValidatedParams()['limit'],
			$this->config->get( 'CheckUserMaximumRowCount' )
		);

		return [ 'ips' => $this->getActorIps( $actorId, $limit, $dbr ) ];
	}

	/**
	 * @inheritDoc
	 */
	public function getParamSettings() {
		$settings = parent::getParamSettings();
		$settings['limit'] = [
			self::PARAM_SOURCE => 'query',
			ParamValidator::PARAM_TYPE => 'integer',
			ParamValidator::PARAM_REQUIRED => false,
			ParamValidator::PARAM_DEFAULT => $this->config->get( 'CheckUserMaximumRowCount' ),
			IntegerDef::PARAM_MAX => $this->config->get( 'CheckUserMaximumRowCount' ),
			IntegerDef::PARAM_MIN => 1
		];
		return $settings;
	}
}
