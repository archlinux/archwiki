<?php

namespace MediaWiki\Extension\AbuseFilter\VariableGenerator;

use MediaWiki\Extension\AbuseFilter\Hooks\AbuseFilterHookRunner;
use MediaWiki\Extension\AbuseFilter\TextExtractor;
use MediaWiki\Extension\AbuseFilter\Variables\VariableHolder;
use MediaWiki\Page\WikiPageFactory;
use MimeAnalyzer;
use RecentChange;
use RepoGroup;
use Title;
use User;

class VariableGeneratorFactory {
	public const SERVICE_NAME = 'AbuseFilterVariableGeneratorFactory';

	/** @var AbuseFilterHookRunner */
	private $hookRunner;
	/** @var TextExtractor */
	private $textExtractor;
	/** @var MimeAnalyzer */
	private $mimeAnalyzer;
	/** @var RepoGroup */
	private $repoGroup;
	/** @var WikiPageFactory */
	private $wikiPageFactory;

	/**
	 * @param AbuseFilterHookRunner $hookRunner
	 * @param TextExtractor $textExtractor
	 * @param MimeAnalyzer $mimeAnalyzer
	 * @param RepoGroup $repoGroup
	 * @param WikiPageFactory $wikiPageFactory
	 */
	public function __construct(
		AbuseFilterHookRunner $hookRunner,
		TextExtractor $textExtractor,
		MimeAnalyzer $mimeAnalyzer,
		RepoGroup $repoGroup,
		WikiPageFactory $wikiPageFactory
	) {
		$this->hookRunner = $hookRunner;
		$this->textExtractor = $textExtractor;
		$this->mimeAnalyzer = $mimeAnalyzer;
		$this->repoGroup = $repoGroup;
		$this->wikiPageFactory = $wikiPageFactory;
	}

	/**
	 * @param VariableHolder|null $holder
	 * @return VariableGenerator
	 */
	public function newGenerator( VariableHolder $holder = null ): VariableGenerator {
		return new VariableGenerator( $this->hookRunner, $holder );
	}

	/**
	 * @param User $user
	 * @param Title $title
	 * @param VariableHolder|null $holder
	 * @return RunVariableGenerator
	 */
	public function newRunGenerator( User $user, Title $title, VariableHolder $holder = null ): RunVariableGenerator {
		return new RunVariableGenerator(
			$this->hookRunner,
			$this->textExtractor,
			$this->mimeAnalyzer,
			$this->wikiPageFactory,
			$user,
			$title,
			$holder
		);
	}

	/**
	 * @param RecentChange $rc
	 * @param User $contextUser
	 * @param VariableHolder|null $holder
	 * @return RCVariableGenerator
	 */
	public function newRCGenerator(
		RecentChange $rc,
		User $contextUser,
		VariableHolder $holder = null
	): RCVariableGenerator {
		return new RCVariableGenerator(
			$this->hookRunner,
			$this->mimeAnalyzer,
			$this->repoGroup,
			$this->wikiPageFactory,
			$rc,
			$contextUser,
			$holder
		);
	}
}
