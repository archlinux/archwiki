<?php

namespace MediaWiki\RecentChanges\ChangesListQuery;

/**
 * @internal For use by ChangesListQuery
 * @since 1.45
 * @ingroup RecentChanges
 */
class ChangesListHighlight {
	public function __construct(
		public bool $sense,
		public string $moduleName,
		public mixed $value
	) {
	}
}
