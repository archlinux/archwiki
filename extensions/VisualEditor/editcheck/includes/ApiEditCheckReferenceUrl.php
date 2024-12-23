<?php

namespace MediaWiki\Extension\VisualEditor\EditCheck;

use MediaWiki\Api\ApiBase;
use MediaWiki\Api\ApiMain;
use MediaWiki\Api\ApiUsageException;
use MediaWiki\Extension\AbuseFilter\BlockedDomainStorage;
use MediaWiki\Extension\SpamBlacklist\BaseBlacklist;
use MediaWiki\Registration\ExtensionRegistry;
use Wikimedia\ParamValidator\ParamValidator;

class ApiEditCheckReferenceUrl extends ApiBase {

	/** @phan-suppress-next-line PhanUndeclaredTypeProperty */
	private ?BlockedDomainStorage $blockedDomainStorage;

	public function __construct(
		ApiMain $main,
		string $name,
		// @phan-suppress-next-line PhanUndeclaredTypeParameter
		?BlockedDomainStorage $blockedDomainStorage
	) {
		parent::__construct( $main, $name );
		$this->blockedDomainStorage = $blockedDomainStorage;
	}

	/**
	 * @inheritDoc
	 * @throws ApiUsageException
	 */
	public function execute() {
		$params = $this->extractRequestParams();

		$url = $params['url'];

		if ( $this->isInBlockedExternalDomains( $url ) || $this->isInSpamBlackList( $url ) ) {
			$result = 'blocked';
		} else {
			$result = 'allowed';
		}

		$this->getResult()->addValue( null, $this->getModuleName(), [ $url => $result ] );
	}

	private function isInBlockedExternalDomains( string $url ): bool {
		if ( !$this->blockedDomainStorage ) {
			return false;
		}

		// @phan-suppress-next-line PhanUndeclaredClassMethod
		$domain = $this->blockedDomainStorage->validateDomain( $url );
		// @phan-suppress-next-line PhanUndeclaredClassMethod
		$blockedDomains = $this->blockedDomainStorage->loadComputed();
		return !empty( $blockedDomains[ $domain ] );
	}

	private function isInSpamBlackList( string $url ): bool {
		if ( !ExtensionRegistry::getInstance()->isLoaded( 'SpamBlacklist' ) ) {
			return false;
		}
		if ( !str_contains( $url, '//' ) ) {
			// SpamBlackist only detects full URLs
			$url = 'https://' . $url;
		}

		// @phan-suppress-next-line PhanUndeclaredClassMethod
		$matches = BaseBlacklist::getSpamBlacklist()->filter(
			[ $url ],
			null,
			$this->getUser(),
			true
		);

		return $matches !== false;
	}

	/**
	 * Check if the required extensions are available for this API to be usable
	 *
	 * @return bool
	 */
	public static function isAvailable(): bool {
		return ExtensionRegistry::getInstance()->isLoaded( 'SpamBlacklist' ) ||
			// BlockedExternalDomains is within AbuseFilter:
			ExtensionRegistry::getInstance()->isLoaded( 'Abuse Filter' );
	}

	/**
	 * @inheritDoc
	 */
	public function getAllowedParams() {
		return [
			'url' => [
				ParamValidator::PARAM_REQUIRED => true,
				ParamValidator::PARAM_TYPE => 'string',
			],
		];
	}

	/**
	 * @inheritDoc
	 */
	public function needsToken() {
		return false;
	}

	/**
	 * @inheritDoc
	 */
	public function isInternal() {
		return true;
	}

	/**
	 * @inheritDoc
	 */
	public function isWriteMode() {
		return false;
	}
}
