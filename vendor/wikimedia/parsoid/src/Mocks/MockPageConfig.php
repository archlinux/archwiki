<?php
declare( strict_types = 1 );

namespace Wikimedia\Parsoid\Mocks;

use Wikimedia\Bcp47Code\Bcp47Code;
use Wikimedia\Parsoid\Config\PageConfig;
use Wikimedia\Parsoid\Config\PageContent;
use Wikimedia\Parsoid\Utils\Utils;

class MockPageConfig extends PageConfig {

	/** @var ?PageContent */
	private $content;

	/** @var int */
	private $pageid;

	/** @var int */
	private $pagens;

	/** @var string */
	private $title;

	/** @var ?string */
	private $pagelanguage;

	/** @var ?string */
	private $pagelanguageDir;

	/**
	 * Construct a mock environment object for use in tests
	 * @param array $opts
	 * @param ?PageContent $content
	 */
	public function __construct( array $opts, ?PageContent $content ) {
		$this->content = $content;
		$this->title = $opts['title'] ?? 'TestPage';
		$this->pageid = $opts['pageid'] ?? -1;
		$this->pagens = $opts['pagens'] ?? 0;
		$this->pagelanguage = $opts['pageLanguage'] ?? null;
		$this->pagelanguageDir = $opts['pageLanguageDir'] ?? null;
	}

	/** @inheritDoc */
	public function getContentModel(): string {
		return 'wikitext';
	}

	/** @inheritDoc */
	public function getTitle(): string {
		return $this->title;
	}

	/** @inheritDoc */
	public function getNs(): int {
		return $this->pagens;
	}

	/** @inheritDoc */
	public function getPageId(): int {
		return $this->pageid;
	}

	/** @inheritDoc */
	public function getPageLanguageBcp47(): Bcp47Code {
		return Utils::mwCodeToBcp47( $this->pagelanguage ?? 'en' );
	}

	/** @inheritDoc */
	public function getPageLanguageDir(): string {
		return $this->pagelanguageDir ?? 'rtl';
	}

	/** @inheritDoc */
	public function getRevisionId(): ?int {
		return 1;
	}

	/** @inheritDoc */
	public function getParentRevisionId(): ?int {
		return null;
	}

	/** @inheritDoc */
	public function getRevisionTimestamp(): ?string {
		return null;
	}

	/** @inheritDoc */
	public function getRevisionUser(): ?string {
		// @phan-suppress-previous-line PhanPluginNeverReturnMethod
		throw new \BadMethodCallException( 'Not implemented' );
	}

	/** @inheritDoc */
	public function getRevisionUserId(): ?int {
		// @phan-suppress-previous-line PhanPluginNeverReturnMethod
		throw new \BadMethodCallException( 'Not implemented' );
	}

	/** @inheritDoc */
	public function getRevisionSha1(): ?string {
		return null;
	}

	/** @inheritDoc */
	public function getRevisionSize(): ?int {
		// @phan-suppress-previous-line PhanPluginNeverReturnMethod
		throw new \BadMethodCallException( 'Not implemented' );
	}

	/** @inheritDoc */
	public function getRevisionContent(): ?PageContent {
		return $this->content;
	}

}
