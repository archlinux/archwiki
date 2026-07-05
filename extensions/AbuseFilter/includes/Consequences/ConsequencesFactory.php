<?php

namespace MediaWiki\Extension\AbuseFilter\Consequences;

use MediaWiki\Block\BlockUserFactory;
use MediaWiki\Block\DatabaseBlockStore;
use MediaWiki\Block\UnblockUserFactory;
use MediaWiki\Config\ServiceOptions;
use MediaWiki\Extension\AbuseFilter\BlockAutopromoteStore;
use MediaWiki\Extension\AbuseFilter\ChangeTags\ChangeTagger;
use MediaWiki\Extension\AbuseFilter\Consequences\Consequence\Block;
use MediaWiki\Extension\AbuseFilter\Consequences\Consequence\BlockAutopromote;
use MediaWiki\Extension\AbuseFilter\Consequences\Consequence\Degroup;
use MediaWiki\Extension\AbuseFilter\Consequences\Consequence\Disallow;
use MediaWiki\Extension\AbuseFilter\Consequences\Consequence\RangeBlock;
use MediaWiki\Extension\AbuseFilter\Consequences\Consequence\Tag;
use MediaWiki\Extension\AbuseFilter\Consequences\Consequence\Throttle;
use MediaWiki\Extension\AbuseFilter\Consequences\Consequence\Warn;
use MediaWiki\Extension\AbuseFilter\FilterUser;
use MediaWiki\Extension\AbuseFilter\ServiceNames;
use MediaWiki\Extension\AbuseFilter\Variables\VariableHolder;
use MediaWiki\Language\MessageLocalizer;
use MediaWiki\Session\Session;
use MediaWiki\Session\SessionManager;
use MediaWiki\User\Registration\UserRegistrationLookup;
use MediaWiki\User\UserEditTracker;
use MediaWiki\User\UserGroupManager;
use MediaWiki\User\UserIdentityUtils;
use Psr\Log\LoggerInterface;
use Wikimedia\ObjectCache\BagOStuff;

class ConsequencesFactory {
	public const SERVICE_NAME = ServiceNames::ConsequencesFactory;

	public const CONSTRUCTOR_OPTIONS = [
		'AbuseFilterCentralDB',
		'AbuseFilterIsCentral',
		'AbuseFilterRangeBlockSize',
		'BlockCIDRLimit',
	];

	/** @var ?Session */
	private $session;

	/**
	 * @todo This might drag in unwanted dependencies. The alternative is to use ObjectFactory, but that's harder
	 *   to understand for humans and static analysis tools, so do that only if the dependencies list starts growing.
	 */
	public function __construct(
		private readonly ServiceOptions $options,
		private readonly LoggerInterface $logger,
		private readonly BlockUserFactory $blockUserFactory,
		private readonly UnblockUserFactory $unblockUserFactory,
		private readonly DatabaseBlockStore $databaseBlockStore,
		private readonly UserGroupManager $userGroupManager,
		private readonly BagOStuff $mainStash,
		private readonly ChangeTagger $changeTagger,
		private readonly BlockAutopromoteStore $blockAutopromoteStore,
		private readonly FilterUser $filterUser,
		private readonly MessageLocalizer $messageLocalizer,
		private readonly UserEditTracker $userEditTracker,
		private readonly UserRegistrationLookup $userRegistrationLookup,
		private readonly UserIdentityUtils $userIdentityUtils
	) {
		$options->assertRequiredOptions( self::CONSTRUCTOR_OPTIONS );
	}

	// Each class has its factory method for better type inference and static analysis

	/**
	 * @param Parameters $params
	 * @param string $expiry
	 * @param bool $preventsTalk
	 * @return Block
	 */
	public function newBlock( Parameters $params, string $expiry, bool $preventsTalk ): Block {
		return new Block(
			$params,
			$expiry,
			$preventsTalk,
			$this->blockUserFactory,
			$this->unblockUserFactory,
			$this->databaseBlockStore,
			$this->filterUser,
			$this->messageLocalizer,
			$this->logger
		);
	}

	/**
	 * @param Parameters $params
	 * @param string $expiry
	 * @return RangeBlock
	 */
	public function newRangeBlock( Parameters $params, string $expiry ): RangeBlock {
		return new RangeBlock(
			$params,
			$expiry,
			$this->blockUserFactory,
			$this->filterUser,
			$this->messageLocalizer,
			$this->logger,
			$this->options->get( 'AbuseFilterRangeBlockSize' ),
			$this->options->get( 'BlockCIDRLimit' )
		);
	}

	/**
	 * @param Parameters $params
	 * @param VariableHolder $vars
	 * @return Degroup
	 */
	public function newDegroup( Parameters $params, VariableHolder $vars ): Degroup {
		return new Degroup(
			$params,
			$vars,
			$this->userGroupManager,
			$this->userIdentityUtils,
			$this->filterUser,
			$this->messageLocalizer
		);
	}

	/**
	 * @param Parameters $params
	 * @param int $duration
	 * @return BlockAutopromote
	 */
	public function newBlockAutopromote( Parameters $params, int $duration ): BlockAutopromote {
		return new BlockAutopromote( $params, $duration, $this->blockAutopromoteStore, $this->messageLocalizer,
			$this->userIdentityUtils );
	}

	/**
	 * @param Parameters $params
	 * @param array{id:int|string,count:int,period:int,groups:string[]} $throttleParams
	 * @return Throttle
	 */
	public function newThrottle( Parameters $params, array $throttleParams ): Throttle {
		return new Throttle(
			$params,
			$throttleParams,
			$this->mainStash,
			$this->userEditTracker,
			$this->userRegistrationLookup,
			$this->logger,
			$this->options->get( 'AbuseFilterIsCentral' ),
			$this->options->get( 'AbuseFilterCentralDB' )
		);
	}

	/**
	 * @param Parameters $params
	 * @param string $message
	 * @return Warn
	 */
	public function newWarn( Parameters $params, string $message ): Warn {
		return new Warn( $params, $message, $this->getSession() );
	}

	/**
	 * @param Parameters $params
	 * @param string $message
	 * @return Disallow
	 */
	public function newDisallow( Parameters $params, string $message ): Disallow {
		return new Disallow( $params, $message );
	}

	/**
	 * @param Parameters $params
	 * @param string[] $tags
	 * @return Tag
	 */
	public function newTag( Parameters $params, array $tags ): Tag {
		return new Tag( $params, $tags, $this->changeTagger );
	}

	public function setSession( Session $session ): void {
		$this->session = $session;
	}

	private function getSession(): Session {
		if ( $this->session === null ) {
			$this->session = SessionManager::getGlobalSession();
		}

		return $this->session;
	}
}
