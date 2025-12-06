<?php

namespace Cite;

use MediaWiki\Config\Config;
use MediaWiki\Extension\CommunityConfiguration\Provider\ConfigurationProviderFactory;
use MediaWiki\Parser\Parser;
use WeakMap;

/**
 * @license GPL-2.0-or-later
 */
class CiteFactory {
	/** @var WeakMap<Parser,Cite> */
	private WeakMap $citeForParser;

	public function __construct(
		private readonly Config $config,
		private readonly AlphabetsProvider $alphabetsProvider,
		private readonly ?ConfigurationProviderFactory $configurationProviderFactory,
	) {
		$this->citeForParser = new WeakMap();
	}

	public function newCite( Parser $parser ): Cite {
		return new Cite(
			$parser,
			$this->config,
			$this->alphabetsProvider,
			$this->configurationProviderFactory,
		);
	}

	public function getCiteForParser( Parser $parser ): Cite {
		$this->citeForParser[$parser] ??= $this->newCite( $parser );
		return $this->citeForParser[$parser];
	}

	public function destroyCiteForParser( Parser $parser ): void {
		unset( $this->citeForParser[$parser] );
	}

	public function peekCiteForParser( Parser $parser ): ?Cite {
		return $this->citeForParser[$parser] ?? null;
	}
}
