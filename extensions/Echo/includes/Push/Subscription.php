<?php

namespace MediaWiki\Extension\Notifications\Push;

use Wikimedia\Timestamp\ConvertibleTimestamp;

class Subscription {

	/**
	 * Construct a subscription from a DB result row.
	 * @param \stdClass $row echo_push_subscription row from IResultWrapper::fetchRow
	 * @return Subscription
	 */
	public static function newFromRow( object $row ) {
		return new self(
			$row->epp_name,
			$row->eps_token,
			$row->ept_text,
			new ConvertibleTimestamp( $row->eps_updated )
		);
	}

	public function __construct(
		private readonly string $provider,
		private readonly string $token,
		private readonly ?string $topic,
		private readonly ConvertibleTimestamp $updated,
	) {
	}

	/** @return string provider */
	public function getProvider(): string {
		return $this->provider;
	}

	/** @return string token */
	public function getToken(): string {
		return $this->token;
	}

	/** @return string|null topic */
	public function getTopic(): ?string {
		return $this->topic;
	}

	/** @return ConvertibleTimestamp last updated timestamp */
	public function getUpdated(): ConvertibleTimestamp {
		return $this->updated;
	}

}
