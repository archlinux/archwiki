<?php

namespace MediaWiki\Extension\AbuseFilter\BlockedDomains;

use MediaWiki\Extension\CommunityConfiguration\Provider\AbstractProvider;
use MediaWiki\Extension\CommunityConfiguration\Provider\ProviderServicesContainer;
use MediaWiki\Extension\CommunityConfiguration\Store\IConfigurationStore;
use MediaWiki\Extension\CommunityConfiguration\Validation\IValidator;
use MediaWiki\Message\Message;
use MediaWiki\Permissions\Authority;
use StatusValue;
use Wikimedia\ObjectCache\BagOStuff;

class BlockedDomainConfigProvider extends AbstractProvider implements IBlockedDomainStorage {

	public const PROVIDER_ID = 'BlockedDomain';

	private BagOStuff $cache;
	private BlockedDomainValidator $domainValidator;

	public function __construct(
		ProviderServicesContainer $providerServicesContainer,
		string $providerId,
		array $options,
		IConfigurationStore $store,
		IValidator $validator,
		BagOStuff $cache,
		BlockedDomainValidator $domainValidator
	) {
		parent::__construct( $providerServicesContainer, $providerId, $options, $store, $validator );

		$this->cache = $cache;
		$this->domainValidator = $domainValidator;
	}

	/** @inheritDoc */
	public function loadConfig( int $flags = 0 ): StatusValue {
		if ( $flags === 0 ) {
			return $this->loadValidConfiguration();
		} else {
			return $this->loadValidConfigurationUncached();
		}
	}

	private function processStoreStatus( StatusValue $value ): StatusValue {
		if ( !$value->isOK() ) {
			return $value;
		}
		return $value->setResult( true, wfObjectToArray( $value->getValue() ) );
	}

	public function loadValidConfiguration(): StatusValue {
		return $this->processStoreStatus( $this->getStore()->loadConfiguration() );
	}

	public function loadValidConfigurationUncached(): StatusValue {
		return $this->processStoreStatus( $this->getStore()->loadConfigurationUncached() );
	}

	/**
	 * Load the computed domain blocklist
	 *
	 * @return array<string,true> Flipped for performance reasons
	 */
	public function loadComputed(): array {
		return $this->cache->getWithSetCallback(
			$this->cache->makeKey( 'abusefilter-blockeddomains-computed' ),
			BagOStuff::TTL_MINUTE * 5,
			function () {
				$status = $this->loadValidConfiguration();
				if ( !$status->isGood() ) {
					return [];
				}
				$computedDomains = [];
				foreach ( $status->getValue() as $domain ) {
					if ( !( $domain['domain'] ?? null ) ) {
						continue;
					}
					$validatedDomain = $this->domainValidator->validateDomain( $domain['domain'] );
					if ( $validatedDomain ) {
						// It should be a map, benchmark at https://phabricator.wikimedia.org/P48956
						$computedDomains[$validatedDomain] = true;
					}
				}
				return $computedDomains;
			}
		);
	}

	/**
	 * This doesn't do validation.
	 *
	 * @param string $domain domain to be blocked
	 * @param string $notes User provided notes
	 * @param Authority $authority Performer
	 *
	 * @return StatusValue
	 */
	public function addDomain( string $domain, string $notes, Authority $authority ): StatusValue {
		$config = $this->loadValidConfigurationUncached();
		if ( !$config->isGood() ) {
			return $config;
		}
		$config = $config->getValue();
		$config[] = [ 'domain' => $domain, 'notes' => $notes, 'addedBy' => $authority->getUser()->getName() ];
		$comment = Message::newFromSpecifier( 'abusefilter-blocked-domains-domain-added-comment' )
			->params( $domain, $notes )
			->plain();
		return $this->storeValidConfiguration( $config, $authority, $comment );
	}

	/**
	 * This doesn't do validation.
	 *
	 * @param string $domain domain to be unblocked
	 * @param string $notes User provided notes
	 * @param Authority $authority Performer
	 *
	 * @return StatusValue
	 */
	public function removeDomain( string $domain, string $notes, Authority $authority ): StatusValue {
		$config = $this->loadValidConfigurationUncached();
		if ( !$config->isGood() ) {
			return $config;
		}

		$config = $config->getValue();
		foreach ( $config as $key => $value ) {
			if ( ( $value['domain'] ?? '' ) == $domain ) {
				unset( $config[$key] );
			}
		}

		$comment = Message::newFromSpecifier( 'abusefilter-blocked-domains-domain-removed-comment' )
			->params( $domain, $notes )
			->plain();
		return $this->storeValidConfiguration( array_values( $config ), $authority, $comment );
	}
}
