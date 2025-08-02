<?php

declare( strict_types=1 );

namespace MediaWiki\CheckUser\Tests\Integration;

use MediaWiki\Tests\Structure\AbstractSchemaTestBase;

/**
 * @coversNothing
 */
class CheckUserSchemaTest extends AbstractSchemaTestBase {
	protected static function getSchemasDirectory(): string {
		return __DIR__ . '/../../../schema';
	}

	protected static function getSchemaChangesDirectory(): string {
		return __DIR__ . '/../../../schema/abstractSchemaChanges/';
	}

	protected static function getSchemaSQLDirs(): array {
		return [
			'mysql' => __DIR__ . '/../../../schema/mysql',
			'sqlite' => __DIR__ . '/../../../schema/sqlite',
			'postgres' => __DIR__ . '/../../../schema/postgres',
		];
	}
}
