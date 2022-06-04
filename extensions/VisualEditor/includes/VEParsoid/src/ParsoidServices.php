<?php
/**
 * Copyright (C) 2011-2020 Wikimedia Foundation and others.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA.
 */
declare( strict_types = 1 );

namespace VEParsoid;

use ConfigException;
use MediaWiki\MediaWikiServices;
use VEParsoid\Config\PageConfigFactory;
use Wikimedia\Parsoid\Config\DataAccess;
use Wikimedia\Parsoid\Config\SiteConfig;

// phpcs:disable MediaWiki.Commenting.FunctionComment.MissingDocumentationPublic
class ParsoidServices {

	/** @var MediaWikiServices */
	private $services;

	public function __construct( MediaWikiServices $services ) {
		$this->services = $services;
	}

	public function getParsoidSettings(): array {
		// This is a unified place to get Parsoid settings.
		$parsoidSettings = null;
		try {
			// This is where ParsoidSettings will live in MW 1.39
			$parsoidSettings =
				$this->services->getMainConfig()->get( 'ParsoidSettings' );
		} catch ( ConfigException $e ) {
			// Config option isn't defined (yet), use defaults */
		}
		// If VisualEditorParsoidSettings is defined, use that but
		// complain, since this is deprecated.
		$veConfig = $this->services->getConfigFactory()
			->makeConfig( 'visualeditor' );
		try {
			$parsoidSettings =
				$veConfig->get( 'VisualEditorParsoidSettings' );
			// If the above didn't throw, warn the user that
			// VisualEditorParsoidSettings is deprecated
			wfDeprecated(
				'$wgVisualEditorParsoidSettings',
				'1.38',
				'VisualEditor'
			);
		} catch ( ConfigException $e ) {
			// The deprecated Config option isn't defined, that's good.
		}
		return ( $parsoidSettings ?? [] ) + [
			# Default parsoid settings, for 'no config' install.
			'useSelser' => true,
		];
	}

	public function getParsoidSiteConfig(): SiteConfig {
		return $this->services->get( 'ParsoidSiteConfig' );
	}

	public function getParsoidPageConfigFactory(): PageConfigFactory {
		return $this->services->get( 'ParsoidPageConfigFactory' );
	}

	public function getParsoidDataAccess(): DataAccess {
		return $this->services->get( 'ParsoidDataAccess' );
	}

}
