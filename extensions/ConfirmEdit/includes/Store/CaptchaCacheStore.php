<?php

namespace MediaWiki\Extension\ConfirmEdit\Store;

use BagOStuff;
use MediaWiki\MediaWikiServices;

class CaptchaCacheStore extends CaptchaStore {
	/** @var BagOStuff */
	private $store;

	public function __construct() {
		parent::__construct();

		$this->store = MediaWikiServices::getInstance()->getMainObjectStash();
	}

	/**
	 * @inheritDoc
	 */
	public function store( $index, $info ) {
		global $wgCaptchaSessionExpiration;

		$store = $this->store;
		$store->set(
			$store->makeKey( 'captcha', $index ),
			$info,
			$wgCaptchaSessionExpiration,
			// Assume the write will reach the master DC before the user sends the
			// HTTP POST request attempted to solve the captcha and perform an action
			$store::WRITE_BACKGROUND
		);
	}

	/**
	 * @inheritDoc
	 */
	public function retrieve( $index ) {
		$store = $this->store;
		return $store->get( $store->makeKey( 'captcha', $index ) ) ?: false;
	}

	/**
	 * @inheritDoc
	 */
	public function clear( $index ) {
		$store = $this->store;
		$store->delete( $store->makeKey( 'captcha', $index ) );
	}

	public function cookiesNeeded() {
		return false;
	}
}

class_alias( CaptchaCacheStore::class, 'CaptchaCacheStore' );
