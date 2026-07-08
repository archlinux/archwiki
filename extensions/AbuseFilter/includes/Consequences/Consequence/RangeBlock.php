<?php

namespace MediaWiki\Extension\AbuseFilter\Consequences\Consequence;

use MediaWiki\Block\BlockUserFactory;
use MediaWiki\Extension\AbuseFilter\Consequences\Parameters;
use MediaWiki\Extension\AbuseFilter\FilterUser;
use MediaWiki\Extension\AbuseFilter\GlobalNameUtils;
use MediaWiki\Language\MessageLocalizer;
use Psr\Log\LoggerInterface;
use Wikimedia\IPUtils;

/**
 * Consequence that blocks an IP range (retrieved from the current request for both anons and registered users).
 */
class RangeBlock extends BlockingConsequence {

	public function __construct(
		Parameters $parameters,
		string $expiry,
		BlockUserFactory $blockUserFactory,
		FilterUser $filterUser,
		MessageLocalizer $messageLocalizer,
		LoggerInterface $logger,
		private readonly array $rangeBlockSize,
		private readonly array $blockCIDRLimit
	) {
		parent::__construct( $parameters, $expiry, $blockUserFactory, $filterUser, $messageLocalizer, $logger );
	}

	/**
	 * @inheritDoc
	 */
	public function execute(): bool {
		$requestIP = $this->parameters->getActionSpecifier()->getIP();
		$type = IPUtils::isIPv6( $requestIP ) ? 'IPv6' : 'IPv4';
		$CIDRsize = max( $this->rangeBlockSize[$type], $this->blockCIDRLimit[$type] );
		$blockCIDR = $requestIP . '/' . $CIDRsize;

		$target = IPUtils::sanitizeRange( $blockCIDR );
		$status = $this->doBlockInternal(
			$this->parameters->getFilter()->getName(),
			$this->parameters->getFilter()->getID(),
			$target,
			$this->expiry,
			false,
			false
		);
		return $status->isOK();
	}

	/**
	 * @inheritDoc
	 */
	public function getMessage(): array {
		$filter = $this->parameters->getFilter();
		return [
			'abusefilter-blocked-display',
			$filter->getName(),
			GlobalNameUtils::buildGlobalName( $filter->getID(), $this->parameters->getIsGlobalFilter() )
		];
	}
}
