<?php

namespace MediaWiki\Extension\AbuseFilter\VariableGenerator;

use MediaWiki\Extension\AbuseFilter\Hooks\AbuseFilterHookRunner;
use MediaWiki\Extension\AbuseFilter\Variables\VariableHolder;
use MediaWiki\User\UserIdentity;
use RecentChange;
use Title;
use User;
use WikiPage;

/**
 * Class used to generate variables, for instance related to a given user or title.
 */
class VariableGenerator {
	/**
	 * @var VariableHolder
	 */
	protected $vars;

	/** @var AbuseFilterHookRunner */
	protected $hookRunner;

	/**
	 * @param AbuseFilterHookRunner $hookRunner
	 * @param VariableHolder|null $vars
	 */
	public function __construct(
		AbuseFilterHookRunner $hookRunner,
		VariableHolder $vars = null
	) {
		$this->hookRunner = $hookRunner;
		$this->vars = $vars ?? new VariableHolder();
	}

	/**
	 * @return VariableHolder
	 */
	public function getVariableHolder(): VariableHolder {
		return $this->vars;
	}

	/**
	 * Computes all variables unrelated to title and user. In general, these variables may be known
	 * even without an ongoing action.
	 *
	 * @param RecentChange|null $rc If the variables should be generated for an RC entry,
	 *   this is the entry. Null if it's for the current action being filtered.
	 * @return $this For chaining
	 */
	public function addGenericVars( RecentChange $rc = null ): self {
		// These are lazy-loaded just to reduce the amount of preset variables, but they
		// shouldn't be expensive.
		$this->vars->setLazyLoadVar( 'wiki_name', 'get-wiki-name', [] );
		$this->vars->setLazyLoadVar( 'wiki_language', 'get-wiki-language', [] );

		$this->hookRunner->onAbuseFilter_generateGenericVars( $this->vars, $rc );
		return $this;
	}

	/**
	 * @param UserIdentity $userIdentity
	 * @param RecentChange|null $rc If the variables should be generated for an RC entry,
	 *   this is the entry. Null if it's for the current action being filtered.
	 * @return $this For chaining
	 */
	public function addUserVars( UserIdentity $userIdentity, RecentChange $rc = null ): self {
		$user = User::newFromIdentity( $userIdentity );

		$this->vars->setLazyLoadVar(
			'user_editcount',
			'user-editcount',
			[ 'user-identity' => $userIdentity ]
		);

		$this->vars->setVar( 'user_name', $user->getName() );

		$this->vars->setLazyLoadVar(
			'user_emailconfirm',
			'user-emailconfirm',
			[ 'user' => $user ]
		);

		$this->vars->setLazyLoadVar(
			'user_age',
			'user-age',
			[ 'user' => $user, 'asof' => wfTimestampNow() ]
		);

		$this->vars->setLazyLoadVar(
			'user_groups',
			'user-groups',
			[ 'user-identity' => $userIdentity ]
		);

		$this->vars->setLazyLoadVar(
			'user_rights',
			'user-rights',
			[ 'user-identity' => $userIdentity ]
		);

		$this->vars->setLazyLoadVar(
			'user_blocked',
			'user-block',
			[ 'user' => $user ]
		);

		$this->hookRunner->onAbuseFilter_generateUserVars( $this->vars, $user, $rc );

		return $this;
	}

	/**
	 * @param Title $title
	 * @param string $prefix
	 * @param RecentChange|null $rc If the variables should be generated for an RC entry,
	 *   this is the entry. Null if it's for the current action being filtered.
	 * @return $this For chaining
	 */
	public function addTitleVars(
		Title $title,
		string $prefix,
		RecentChange $rc = null
	): self {
		$this->vars->setVar( $prefix . '_id', $title->getArticleID() );
		$this->vars->setVar( $prefix . '_namespace', $title->getNamespace() );
		$this->vars->setVar( $prefix . '_title', $title->getText() );
		$this->vars->setVar( $prefix . '_prefixedtitle', $title->getPrefixedText() );

		// We only support the default values in $wgRestrictionTypes. Custom restrictions wouldn't
		// have i18n messages. If a restriction is not enabled we'll just return the empty array.
		$types = [ 'edit', 'move', 'create', 'upload' ];
		foreach ( $types as $action ) {
			$this->vars->setLazyLoadVar(
				"{$prefix}_restrictions_$action",
				'get-page-restrictions',
				[ 'title' => $title, 'action' => $action ]
			);
		}

		$this->vars->setLazyLoadVar(
			"{$prefix}_recent_contributors",
			'load-recent-authors',
			[ 'title' => $title ]
		);

		$this->vars->setLazyLoadVar(
			"{$prefix}_age",
			'page-age',
			[ 'title' => $title, 'asof' => wfTimestampNow() ]
		);

		$this->vars->setLazyLoadVar(
			"{$prefix}_first_contributor",
			'load-first-author',
			[ 'title' => $title ]
		);

		$this->hookRunner->onAbuseFilter_generateTitleVars( $this->vars, $title, $prefix, $rc );

		return $this;
	}

	/**
	 * @param WikiPage $page
	 * @param User $user The current user
	 * @return $this For chaining
	 */
	public function addEditVars( WikiPage $page, User $user ): self {
		$this->vars->setLazyLoadVar( 'edit_diff', 'diff',
			[ 'oldtext-var' => 'old_wikitext', 'newtext-var' => 'new_wikitext' ] );
		$this->vars->setLazyLoadVar( 'edit_diff_pst', 'diff',
			[ 'oldtext-var' => 'old_wikitext', 'newtext-var' => 'new_pst' ] );
		$this->vars->setLazyLoadVar( 'new_size', 'length', [ 'length-var' => 'new_wikitext' ] );
		$this->vars->setLazyLoadVar( 'old_size', 'length', [ 'length-var' => 'old_wikitext' ] );
		$this->vars->setLazyLoadVar( 'edit_delta', 'subtract-int',
			[ 'val1-var' => 'new_size', 'val2-var' => 'old_size' ] );

		// Some more specific/useful details about the changes.
		$this->vars->setLazyLoadVar( 'added_lines', 'diff-split',
			[ 'diff-var' => 'edit_diff', 'line-prefix' => '+' ] );
		$this->vars->setLazyLoadVar( 'removed_lines', 'diff-split',
			[ 'diff-var' => 'edit_diff', 'line-prefix' => '-' ] );
		$this->vars->setLazyLoadVar( 'added_lines_pst', 'diff-split',
			[ 'diff-var' => 'edit_diff_pst', 'line-prefix' => '+' ] );

		// Links
		$this->vars->setLazyLoadVar( 'added_links', 'link-diff-added',
			[ 'oldlink-var' => 'old_links', 'newlink-var' => 'all_links' ] );
		$this->vars->setLazyLoadVar( 'removed_links', 'link-diff-removed',
			[ 'oldlink-var' => 'old_links', 'newlink-var' => 'all_links' ] );
		$this->vars->setLazyLoadVar( 'new_text', 'strip-html',
			[ 'html-var' => 'new_html' ] );

		$this->vars->setLazyLoadVar( 'all_links', 'links-from-wikitext',
			[
				'text-var' => 'new_wikitext',
				'article' => $page,
				'contextUser' => $user
			] );
		$this->vars->setLazyLoadVar( 'old_links', 'links-from-wikitext-or-database',
			[
				'article' => $page,
				'text-var' => 'old_wikitext',
				'contextUser' => $user
			] );
		$this->vars->setLazyLoadVar( 'new_pst', 'parse-wikitext',
			[
				'wikitext-var' => 'new_wikitext',
				'article' => $page,
				'pst' => true,
				'contextUser' => $user
			] );
		$this->vars->setLazyLoadVar( 'new_html', 'parse-wikitext',
			[
				'wikitext-var' => 'new_wikitext',
				'article' => $page,
				'contextUser' => $user
			] );

		return $this;
	}
}
