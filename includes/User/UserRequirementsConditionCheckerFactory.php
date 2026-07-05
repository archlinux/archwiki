<?php
/**
 * @license GPL-2.0-or-later
 * @file
 */

namespace MediaWiki\User;

use MediaWiki\Config\ServiceOptions;
use MediaWiki\Context\IContextSource;
use MediaWiki\HookContainer\HookContainer;
use MediaWiki\Permissions\GroupPermissionsLookup;
use MediaWiki\User\Registration\UserRegistrationLookup;

/**
 * @since 1.45
 */
class UserRequirementsConditionCheckerFactory {

	/** @internal For use by ServiceWiring */
	public const CONSTRUCTOR_OPTIONS = [
		...UserRequirementsConditionChecker::CONSTRUCTOR_OPTIONS,
		...UserRequirementsConditionEvaluator::CONSTRUCTOR_OPTIONS
	];

	/** @var UserRequirementsConditionChecker[] */
	private array $instances = [];

	private ServiceOptions $checkerOptions;
	private ServiceOptions $evaluatorOptions;

	public function __construct(
		ServiceOptions $options,
		private readonly GroupPermissionsLookup $groupPermissionsLookup,
		private readonly HookContainer $hookContainer,
		private readonly UserEditTracker $userEditTracker,
		private readonly UserRegistrationLookup $userRegistrationLookup,
		private readonly UserFactory $userFactory,
		private readonly IContextSource $context,
		private readonly UserRequirementsConditionValidator $userRequirementsConditionValidator,
	) {
		$options->assertRequiredOptions( self::CONSTRUCTOR_OPTIONS );
		$this->checkerOptions = new ServiceOptions(
			UserRequirementsConditionChecker::CONSTRUCTOR_OPTIONS,
			$options
		);
		$this->evaluatorOptions = new ServiceOptions(
			UserRequirementsConditionEvaluator::CONSTRUCTOR_OPTIONS,
			$options
		);
	}

	/**
	 * @param UserGroupManager $userGroupManager
	 * @param string|false $wikiId
	 * @return UserRequirementsConditionChecker
	 */
	public function getUserRequirementsConditionChecker(
		UserGroupManager $userGroupManager,
		$wikiId = UserIdentity::LOCAL
	): UserRequirementsConditionChecker {
		$key = (string)$wikiId;
		if ( !isset( $this->instances[$key] ) ) {
			$this->instances[$key] = new UserRequirementsConditionChecker(
				$this->checkerOptions,
				$this->hookContainer,
				$this->userFactory,
				$this->context,
				$this->userRequirementsConditionValidator,
				$this->getDefaultEvaluators( $userGroupManager )
			);
		}

		return $this->instances[$key];
	}

	/**
	 * Creates a condition checker with custom condition evaluators.
	 * It can be useful if caller needs to check a condition in a hypothetical situation,
	 * by simulating certain values the checker operates on.
	 *
	 * The custom evaluators passed to this method are invoked before any default ones,
	 * in the same order as provided. If no custom evaluator handles the condition, it will
	 * be processed as usual.
	 *
	 * @since 1.46
	 */
	public function getCheckerWithCustomConditions(
		UserGroupManager $userGroupManager,
		array $customEvaluators
	): UserRequirementsConditionChecker {
		$evaluators = array_merge(
			$customEvaluators,
			$this->getDefaultEvaluators( $userGroupManager )
		);
		return new UserRequirementsConditionChecker(
			$this->checkerOptions,
			$this->hookContainer,
			$this->userFactory,
			$this->context,
			$this->userRequirementsConditionValidator,
			$evaluators,
		);
	}

	private function getDefaultEvaluators( UserGroupManager $userGroupManager ): array {
		return [
			new UserRequirementsConditionEvaluator(
				$this->evaluatorOptions,
				$this->groupPermissionsLookup,
				$this->userEditTracker,
				$this->userRegistrationLookup,
				$this->userFactory,
				$this->context,
				$userGroupManager
			)
		];
	}
}
