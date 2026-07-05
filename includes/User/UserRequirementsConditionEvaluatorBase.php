<?php
/**
 * @license GPL-2.0-or-later
 * @file
 */

namespace MediaWiki\User;

/**
 * A base class for classes that evaluate atomic user requirements conditions
 * (i.e., conditions checked through {@see UserRequirementsConditionChecker}).
 *
 * A main purpose for existence of this class (and subclasses) is to separate
 * processing compound conditions from the logic to evaluate the atomic ones.
 *
 * @since 1.46
 * @stable to extend
 */
abstract class UserRequirementsConditionEvaluatorBase {

	/**
	 * Evaluates the specified condition and returns whether the condition is met.
	 * If the condition is unsupported by this evaluator, return null.
	 *
	 * @param string|int $conditionType The condition type, one of constants like APCOND_AGE
	 * @param array $args Array of arguments to this condition
	 * @param UserIdentity $user The user against whom the condition is checked.
	 * @param bool $isPerformingRequest Whether the checked user is the one who performs current request.
	 *     If this value is `false`, the implementation should only perform checks that make sense against
	 *     users at rest (e.g., no current IP checks). For other checks a reasonable default value should
	 *     be returned.
	 */
	abstract public function checkCondition(
		string|int $conditionType,
		array $args,
		UserIdentity $user,
		bool $isPerformingRequest
	): ?bool;
}
