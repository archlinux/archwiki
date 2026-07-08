<?php

namespace MediaWiki\Extension\AbuseFilter\Hooks\Handlers;

use MediaWiki\ChangeTags\Hook\ChangeTagsListActiveHook;
use MediaWiki\ChangeTags\Hook\ListDefinedTagsHook;
use MediaWiki\Extension\AbuseFilter\ChangeTags\ChangeTagsManager;

class ChangeTagsHandler implements
	ListDefinedTagsHook,
	ChangeTagsListActiveHook
{

	public function __construct( private readonly ChangeTagsManager $changeTagsManager ) {
	}

	/**
	 * @param string[] &$tags
	 */
	public function onListDefinedTags( &$tags ) {
		$tags = array_merge(
			$tags,
			$this->changeTagsManager->getTagsDefinedByFilters(),
			[ $this->changeTagsManager->getCondsLimitTag() ]
		);
	}

	/**
	 * @param string[] &$tags
	 */
	public function onChangeTagsListActive( &$tags ) {
		$tags = array_merge(
			$tags,
			$this->changeTagsManager->getTagsDefinedByActiveFilters(),
			[ $this->changeTagsManager->getCondsLimitTag() ]
		);
	}
}
