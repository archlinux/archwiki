<?php

namespace MediaWiki\Extension\AbuseFilter;

use MediaWiki\Extension\AbuseFilter\Hooks\AbuseFilterHookRunner;

/**
 * This service can be used to manage the list of keywords recognized by the Parser
 */
class KeywordsManager {
	public const SERVICE_NAME = 'AbuseFilterKeywordsManager';

	private const BUILDER_VALUES = [
		'op-arithmetic' => [
			'+' => 'addition',
			'-' => 'subtraction',
			'*' => 'multiplication',
			'/' => 'divide',
			'%' => 'modulo',
			'**' => 'pow'
		],
		'op-comparison' => [
			'==' => 'equal',
			'===' => 'equal-strict',
			'!=' => 'notequal',
			'!==' => 'notequal-strict',
			'<' => 'lt',
			'>' => 'gt',
			'<=' => 'lte',
			'>=' => 'gte'
		],
		'op-bool' => [
			'!' => 'not',
			'&' => 'and',
			'|' => 'or',
			'^' => 'xor'
		],
		'misc' => [
			'in' => 'in',
			'contains' => 'contains',
			'like' => 'like',
			'""' => 'stringlit',
			'rlike' => 'rlike',
			'irlike' => 'irlike',
			'cond ? iftrue : iffalse' => 'tern',
			'if cond then iftrue else iffalse end' => 'cond',
			'if cond then iftrue end' => 'cond-short',
		],
		'funcs' => [
			'length(string)' => 'length',
			'lcase(string)' => 'lcase',
			'ucase(string)' => 'ucase',
			'ccnorm(string)' => 'ccnorm',
			'ccnorm_contains_any(haystack,needle1,needle2,..)' => 'ccnorm-contains-any',
			'ccnorm_contains_all(haystack,needle1,needle2,..)' => 'ccnorm-contains-all',
			'rmdoubles(string)' => 'rmdoubles',
			'specialratio(string)' => 'specialratio',
			'norm(string)' => 'norm',
			'count(needle,haystack)' => 'count',
			'rcount(needle,haystack)' => 'rcount',
			'get_matches(needle,haystack)' => 'get_matches',
			'rmwhitespace(text)' => 'rmwhitespace',
			'rmspecials(text)' => 'rmspecials',
			'ip_in_range(ip, range)' => 'ip_in_range',
			'contains_any(haystack,needle1,needle2,...)' => 'contains-any',
			'contains_all(haystack,needle1,needle2,...)' => 'contains-all',
			'equals_to_any(haystack,needle1,needle2,...)' => 'equals-to-any',
			'substr(subject, offset, length)' => 'substr',
			'strpos(haystack, needle)' => 'strpos',
			'str_replace(subject, search, replace)' => 'str_replace',
			'rescape(string)' => 'rescape',
			'set_var(var,value)' => 'set_var',
			'sanitize(string)' => 'sanitize',
		],
		'vars' => [
			'timestamp' => 'timestamp',
			'accountname' => 'accountname',
			'action' => 'action',
			'added_lines' => 'addedlines',
			'edit_delta' => 'delta',
			'edit_diff' => 'diff',
			'new_size' => 'newsize',
			'old_size' => 'oldsize',
			'new_content_model' => 'new-content-model',
			'old_content_model' => 'old-content-model',
			'removed_lines' => 'removedlines',
			'summary' => 'summary',
			'page_id' => 'page-id',
			'page_namespace' => 'page-ns',
			'page_title' => 'page-title',
			'page_prefixedtitle' => 'page-prefixedtitle',
			'page_age' => 'page-age',
			'moved_from_id' => 'movedfrom-id',
			'moved_from_namespace' => 'movedfrom-ns',
			'moved_from_title' => 'movedfrom-title',
			'moved_from_prefixedtitle' => 'movedfrom-prefixedtitle',
			'moved_from_age' => 'movedfrom-age',
			'moved_to_id' => 'movedto-id',
			'moved_to_namespace' => 'movedto-ns',
			'moved_to_title' => 'movedto-title',
			'moved_to_prefixedtitle' => 'movedto-prefixedtitle',
			'moved_to_age' => 'movedto-age',
			'user_editcount' => 'user-editcount',
			'user_age' => 'user-age',
			'user_name' => 'user-name',
			'user_groups' => 'user-groups',
			'user_rights' => 'user-rights',
			'user_blocked' => 'user-blocked',
			'user_emailconfirm' => 'user-emailconfirm',
			'old_wikitext' => 'old-wikitext',
			'new_wikitext' => 'new-wikitext',
			'added_links' => 'added-links',
			'removed_links' => 'removed-links',
			'all_links' => 'all-links',
			'new_pst' => 'new-pst',
			'edit_diff_pst' => 'diff-pst',
			'added_lines_pst' => 'addedlines-pst',
			'new_text' => 'new-text',
			'new_html' => 'new-html',
			'page_restrictions_edit' => 'restrictions-edit',
			'page_restrictions_move' => 'restrictions-move',
			'page_restrictions_create' => 'restrictions-create',
			'page_restrictions_upload' => 'restrictions-upload',
			'page_recent_contributors' => 'recent-contributors',
			'page_first_contributor' => 'first-contributor',
			'moved_from_restrictions_edit' => 'movedfrom-restrictions-edit',
			'moved_from_restrictions_move' => 'movedfrom-restrictions-move',
			'moved_from_restrictions_create' => 'movedfrom-restrictions-create',
			'moved_from_restrictions_upload' => 'movedfrom-restrictions-upload',
			'moved_from_recent_contributors' => 'movedfrom-recent-contributors',
			'moved_from_first_contributor' => 'movedfrom-first-contributor',
			'moved_to_restrictions_edit' => 'movedto-restrictions-edit',
			'moved_to_restrictions_move' => 'movedto-restrictions-move',
			'moved_to_restrictions_create' => 'movedto-restrictions-create',
			'moved_to_restrictions_upload' => 'movedto-restrictions-upload',
			'moved_to_recent_contributors' => 'movedto-recent-contributors',
			'moved_to_first_contributor' => 'movedto-first-contributor',
			'old_links' => 'old-links',
			'file_sha1' => 'file-sha1',
			'file_size' => 'file-size',
			'file_mime' => 'file-mime',
			'file_mediatype' => 'file-mediatype',
			'file_width' => 'file-width',
			'file_height' => 'file-height',
			'file_bits_per_channel' => 'file-bits-per-channel',
			'wiki_name' => 'wiki-name',
			'wiki_language' => 'wiki-language',
		],
	];

	/** @var array Old vars which aren't in use anymore */
	private const DISABLED_VARS = [
		'old_text' => 'old-text',
		'old_html' => 'old-html',
		'minor_edit' => 'minor-edit'
	];

	private const DEPRECATED_VARS = [
		'article_text' => 'page_title',
		'article_prefixedtext' => 'page_prefixedtitle',
		'article_namespace' => 'page_namespace',
		'article_articleid' => 'page_id',
		'article_restrictions_edit' => 'page_restrictions_edit',
		'article_restrictions_move' => 'page_restrictions_move',
		'article_restrictions_create' => 'page_restrictions_create',
		'article_restrictions_upload' => 'page_restrictions_upload',
		'article_recent_contributors' => 'page_recent_contributors',
		'article_first_contributor' => 'page_first_contributor',
		'moved_from_text' => 'moved_from_title',
		'moved_from_prefixedtext' => 'moved_from_prefixedtitle',
		'moved_from_articleid' => 'moved_from_id',
		'moved_to_text' => 'moved_to_title',
		'moved_to_prefixedtext' => 'moved_to_prefixedtitle',
		'moved_to_articleid' => 'moved_to_id',
	];

	/** @var string[][] Final list of builder values */
	private $builderValues;

	/** @var string[] Final list of deprecated vars */
	private $deprecatedVars;

	/** @var AbuseFilterHookRunner */
	private $hookRunner;

	/**
	 * @param AbuseFilterHookRunner $hookRunner
	 */
	public function __construct( AbuseFilterHookRunner $hookRunner ) {
		$this->hookRunner = $hookRunner;
	}

	/**
	 * @return array
	 */
	public function getDisabledVariables(): array {
		return self::DISABLED_VARS;
	}

	/**
	 * @return array
	 */
	public function getDeprecatedVariables(): array {
		if ( $this->deprecatedVars === null ) {
			$this->deprecatedVars = self::DEPRECATED_VARS;
			$this->hookRunner->onAbuseFilter_deprecatedVariables( $this->deprecatedVars );
		}
		return $this->deprecatedVars;
	}

	/**
	 * @return array
	 */
	public function getBuilderValues(): array {
		if ( $this->builderValues === null ) {
			$this->builderValues = self::BUILDER_VALUES;
			$this->hookRunner->onAbuseFilter_builder( $this->builderValues );
		}
		return $this->builderValues;
	}

	/**
	 * @param string $name
	 * @return bool
	 */
	public function isVarDisabled( string $name ): bool {
		return array_key_exists( $name, self::DISABLED_VARS );
	}

	/**
	 * @param string $name
	 * @return bool
	 */
	public function isVarDeprecated( string $name ): bool {
		return array_key_exists( $name, $this->getDeprecatedVariables() );
	}

	/**
	 * @param string $name
	 * @return bool
	 */
	public function isVarInUse( string $name ): bool {
		return array_key_exists( $name, $this->getVarsMappings() );
	}

	/**
	 * Check whether the given name corresponds to a known variable.
	 * @param string $name
	 * @return bool
	 */
	public function varExists( string $name ): bool {
		return $this->isVarInUse( $name ) ||
			$this->isVarDisabled( $name ) ||
			$this->isVarDeprecated( $name );
	}

	/**
	 * Get the message for a builtin variable; takes deprecated variables into account.
	 * Returns null for non-builtin variables.
	 *
	 * @param string $var
	 * @return string|null
	 */
	public function getMessageKeyForVar( string $var ): ?string {
		if ( !$this->varExists( $var ) ) {
			return null;
		}
		if ( $this->isVarDeprecated( $var ) ) {
			$var = $this->getDeprecatedVariables()[$var];
		}

		$key = self::DISABLED_VARS[$var] ??
			$this->getVarsMappings()[$var];
		return "abusefilter-edit-builder-vars-$key";
	}

	/**
	 * @return array
	 */
	public function getVarsMappings(): array {
		return $this->getBuilderValues()['vars'];
	}

	/**
	 * Get a list of core variables, i.e. variables defined in AbuseFilter (ignores hooks).
	 * You usually want to use getVarsMappings(), not this one.
	 * @return string[]
	 */
	public function getCoreVariables(): array {
		return array_keys( self::BUILDER_VALUES['vars'] );
	}
}
