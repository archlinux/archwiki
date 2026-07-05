<?php
/**
 * Refresh image metadata fields. See also rebuildImages.php
 *
 * Usage: php refreshImageMetadata.php
 *
 * Copyright © 2011 Brian Wolff
 * https://www.mediawiki.org/
 *
 * @license GPL-2.0-or-later
 * @file
 * @author Brian Wolff
 * @ingroup Maintenance
 */

// @codeCoverageIgnoreStart
require_once __DIR__ . '/Maintenance.php';
// @codeCoverageIgnoreEnd

use MediaWiki\FileRepo\File\File;
use MediaWiki\FileRepo\File\FileSelectQueryBuilder;
use MediaWiki\FileRepo\LocalRepo;
use MediaWiki\Maintenance\Maintenance;
use MediaWiki\Title\MalformedTitleException;
use MediaWiki\Title\TitleParser;
use Wikimedia\Rdbms\IExpression;
use Wikimedia\Rdbms\IReadableDatabase;
use Wikimedia\Rdbms\IResultWrapper;
use Wikimedia\Rdbms\LikeValue;
use Wikimedia\Rdbms\SelectQueryBuilder;

/**
 * Maintenance script to refresh image metadata fields.
 *
 * @ingroup Maintenance
 */
class RefreshImageMetadata extends Maintenance {
	private ?TitleParser $titleParser = null;

	public function __construct() {
		parent::__construct();

		$this->addDescription( 'Script to update image metadata records' );
		$this->setBatchSize( 200 );

		$this->addOption(
			'force',
			'Reload metadata from file even if the metadata looks ok',
			false,
			false,
			'f'
		);
		$this->addOption(
			'broken-only',
			'Only fix really broken records, leave old but still compatible records alone.'
		);
		$this->addOption(
			'convert-to-json',
			'Fix records with an out of date serialization format.'
		);
		$this->addOption(
			'split',
			'Enable splitting out large metadata items to the text table. Implies --convert-to-json.'
		);
		$this->addOption(
			'verbose',
			'Output extra information about each upgraded/non-upgraded file.',
			false,
			false,
			'v'
		);
		$this->addOption( 'start', 'Name of file to start with', false, true );
		$this->addOption( 'end', 'Name of file to end with', false, true );

		$this->addOption(
			'mediatype',
			'Only refresh files with this media type, e.g. BITMAP, UNKNOWN etc.',
			false,
			true
		);
		$this->addOption(
			'mime',
			"Only refresh files with this MIME type. Can accept wild-card 'image/*'. "
				. "Potentially inefficient unless 'mediatype' is also specified",
			false,
			true
		);
		$this->addOption(
			'metadata-contains',
			'(Inefficient!) Only refresh files where the img_metadata field '
				. 'contains this string. Can be used if its known a specific '
				. 'property was being extracted incorrectly.',
			false,
			true
		);
		$this->addOption(
			'sleep',
			'Time to sleep between each batch (in seconds). Default: 0',
			false,
			true
		);
		$this->addOption( 'oldimage', 'Run and refresh on oldimage table.' );
		$this->addOption(
			'listfile',
			'File with file titles to refresh, one per line (newline delimited). '
				. 'If provided, other filtering options (--mime, --mediatype, --metadata-contains, '
				. '--start, --end) will be ignored.',
			false,
			true
		);
	}

	public function execute() {
		$force = $this->hasOption( 'force' );
		$brokenOnly = $this->hasOption( 'broken-only' );
		$verbose = $this->hasOption( 'verbose' );
		$split = $this->hasOption( 'split' );
		$sleep = (int)$this->getOption( 'sleep', 0 );
		$reserialize = $this->hasOption( 'convert-to-json' );
		$oldimage = $this->hasOption( 'oldimage' );
		$listFile = $this->getOption( 'listfile', false );

		$batchSize = (int)$this->getBatchSize();
		if ( $batchSize <= 0 ) {
			$this->fatalError( "Batch size is too low...", 12 );
		}

		$this->titleParser = $this->getServiceContainer()->getTitleParser();
		$repo = $this->newLocalRepo( $force, $brokenOnly, $reserialize, $split );

		if ( $listFile !== false ) {
			// Process files by title list
			$this->processFilesByTitles( $listFile, $repo, $oldimage, $force, $verbose, $sleep );
		} else {
			// Process files by database query
			$this->processFilesByDatabase( $repo, $oldimage, $force, $verbose, $sleep );
		}
	}

	/**
	 * Process files specified in a list file using batch DB queries
	 *
	 * @param string $listFile Path to file with titles
	 * @param LocalRepo $repo
	 * @param bool $oldimage
	 * @param bool $force
	 * @param bool $verbose
	 * @param int $sleep
	 */
	private function processFilesByTitles(
		string $listFile,
		LocalRepo $repo,
		bool $oldimage,
		bool $force,
		bool $verbose,
		int $sleep
	): void {
		if ( !file_exists( $listFile ) ) {
			$this->fatalError( "List file does not exist: $listFile" );
		}

		$file = fopen( $listFile, 'r' );
		if ( !$file ) {
			$this->fatalError( "Unable to read list file: $listFile" );
		}

		// Read and validate all titles from the file
		$requestedNames = [];
		$invalidTitles = [];
		$lineNum = 0;

		while ( !feof( $file ) ) {
			$line = trim( fgets( $file ) );
			$lineNum++;

			if ( $line === '' ) {
				continue;
			}

			try {
				$titleValue = $this->titleParser->parseTitle( $line, NS_FILE );
				if ( $titleValue->getNamespace() !== NS_FILE ) {
					$invalidTitles[] = [ 'line' => $lineNum, 'title' => $line ];
					continue;
				}
				$requestedNames[$titleValue->getDBkey()] = $line;
			} catch ( MalformedTitleException ) {
				$invalidTitles[] = [ 'line' => $lineNum, 'title' => $line ];
				continue;
			}
		}
		fclose( $file );

		// Report invalid titles
		foreach ( $invalidTitles as $invalid ) {
			$this->output( "Invalid file title on line {$invalid['line']}: '{$invalid['title']}'\n" );
		}

		if ( count( $requestedNames ) === 0 ) {
			$this->output( "No valid titles found in list file.\n" );
			return;
		}

		$this->output( "Found " . count( $requestedNames ) . " valid title(s) to process.\n" );

		$dbw = $this->getPrimaryDB();
		$batchSize = (int)$this->getBatchSize();
		$nameField = $oldimage ? 'oi_name' : 'img_name';
		$upgraded = 0;
		$leftAlone = 0;
		$foundNames = [];

		$nameBatches = array_chunk( array_keys( $requestedNames ), $batchSize );

		foreach ( $nameBatches as $nameBatch ) {
			if ( $oldimage ) {
				$queryBuilder = FileSelectQueryBuilder::newForOldFile( $dbw );
			} else {
				$queryBuilder = FileSelectQueryBuilder::newForFile( $dbw );
			}
			$queryBuilder
				->where( [ $nameField => $nameBatch ] )
				->caller( __METHOD__ );

			$res = $queryBuilder->fetchResultSet();

			[ $up, $left, $found ] = $this->processBatch( $res, $repo, $nameField, $force, $verbose );
			$upgraded += $up;
			$leftAlone += $left;
			$foundNames += $found;

			$this->waitForReplication();
			if ( $sleep ) {
				sleep( $sleep );
			}
		}

		// Report files that were not found in the database
		$notFound = array_diff_key( $requestedNames, $foundNames );
		if ( count( $notFound ) > 0 ) {
			$this->output( "\nThe following " . count( $notFound ) . " file(s) were not found in the database:\n" );
			foreach ( $notFound as $dbKey => $originalTitle ) {
				$this->output( "  - $originalTitle\n" );
			}
		}

		$this->outputResult( $upgraded, $leftAlone, $force );
	}

	/**
	 * Process files using database query with filters
	 *
	 * @param LocalRepo $repo
	 * @param bool $oldimage
	 * @param bool $force
	 * @param bool $verbose
	 * @param int $sleep
	 */
	private function processFilesByDatabase(
		LocalRepo $repo,
		bool $oldimage,
		bool $force,
		bool $verbose,
		int $sleep
	): void {
		$start = $this->getOption( 'start', false );
		$dbw = $this->getPrimaryDB();
		if ( $oldimage ) {
			$fieldPrefix = 'oi_';
			$queryBuilderTemplate = FileSelectQueryBuilder::newForOldFile( $dbw );
		} else {
			$fieldPrefix = 'img_';
			$queryBuilderTemplate = FileSelectQueryBuilder::newForFile( $dbw );
		}

		$batchSize = (int)$this->getBatchSize();
		$nameField = $fieldPrefix . 'name';
		$upgraded = 0;
		$leftAlone = 0;

		$this->setConditions( $dbw, $queryBuilderTemplate, $fieldPrefix );
		$queryBuilderTemplate
			->orderBy( $nameField, SelectQueryBuilder::SORT_ASC )
			->limit( $batchSize );

		$batchCondition = [];
		if ( $start !== false ) {
			$batchCondition[] = $dbw->expr( $nameField, '>=', $start );
		}

		do {
			$queryBuilder = clone $queryBuilderTemplate;
			$res = $queryBuilder->andWhere( $batchCondition )
				->caller( __METHOD__ )->fetchResultSet();

			[ $up, $left, $found ] = $this->processBatch( $res, $repo, $nameField, $force, $verbose );
			$upgraded += $up;
			$leftAlone += $left;

			if ( $res->numRows() > 0 ) {
				$lastName = array_key_last( $found );
				$batchCondition = [ $dbw->expr( $nameField, '>', $lastName ) ];
			}

			$this->waitForReplication();
			if ( $sleep ) {
				sleep( $sleep );
			}
		} while ( $res->numRows() === $batchSize );

		$this->outputResult( $upgraded, $leftAlone, $force );
	}

	/**
	 * Process a batch of file rows
	 *
	 * @param IResultWrapper $res Database result set
	 * @param LocalRepo $repo
	 * @param string $nameField Field name containing the file name
	 * @param bool $force
	 * @param bool $verbose
	 * @return array [ upgraded count, left alone count, found names keyed by name ]
	 */
	private function processBatch(
		IResultWrapper $res,
		LocalRepo $repo,
		string $nameField,
		bool $force,
		bool $verbose
	): array {
		$upgraded = 0;
		$leftAlone = 0;
		$foundNames = [];

		if ( $res->numRows() > 0 ) {
			$firstRow = $res->current();
			$firstName = $firstRow->$nameField;
			$res->rewind();
			$this->output( "Processing next {$res->numRows()} row(s) starting with $firstName.\n" );
		}

		foreach ( $res as $row ) {
			$name = $row->$nameField;
			$foundNames[$name] = true;

			try {
				$file = $repo->newFileFromRow( $row );
				$file->maybeUpgradeRow();
				if ( $file->getUpgraded() ) {
					$this->output( "Refreshed File:$name.\n" );
					$upgraded++;
				} else {
					if ( $force ) {
						$file->upgradeRow();
						if ( $verbose ) {
							$this->output( "Forcibly refreshed File:$name.\n" );
						}
					} elseif ( $verbose ) {
						$this->output( "Skipping File:$name.\n" );
					}
					$leftAlone++;
				}
			} catch ( Exception $e ) {
				$this->output( "$name failed. {$e->getMessage()}\n" );
			}
		}

		return [ $upgraded, $leftAlone, $foundNames ];
	}

	/**
	 * Output the final result summary
	 *
	 * @param int $upgraded
	 * @param int $leftAlone
	 * @param bool $force
	 */
	private function outputResult( int $upgraded, int $leftAlone, bool $force ): void {
		$total = $upgraded + $leftAlone;
		if ( $force ) {
			$this->output( "\nFinished refreshing file metadata for $total files. "
				. "$upgraded needed to be refreshed, $leftAlone did not need to "
				. "be but were refreshed anyways.\n" );
		} else {
			$this->output( "\nFinished refreshing file metadata for $total files. "
				. "$upgraded were refreshed, $leftAlone were already up to date.\n" );
		}
	}

	/**
	 * @param IReadableDatabase $dbw
	 * @param SelectQueryBuilder $queryBuilder
	 * @param string $fieldPrefix like img_ or oi_
	 */
	private function setConditions(
		IReadableDatabase $dbw,
		SelectQueryBuilder $queryBuilder,
		string $fieldPrefix
	): void {
		$end = $this->getOption( 'end', false );
		$mime = $this->getOption( 'mime', false );
		$mediatype = $this->getOption( 'mediatype', false );
		$like = $this->getOption( 'metadata-contains', false );

		if ( $end !== false ) {
			$queryBuilder->andWhere( $dbw->expr( $fieldPrefix . 'name', '<=', $end ) );
		}
		if ( $mime !== false ) {
			[ $major, $minor ] = File::splitMime( $mime );
			$queryBuilder->andWhere( [ $fieldPrefix . 'major_mime' => $major ] );
			if ( $minor !== '*' ) {
				$queryBuilder->andWhere( [ $fieldPrefix . 'minor_mime' => $minor ] );
			}
		}
		if ( $mediatype !== false ) {
			$queryBuilder->andWhere( [ $fieldPrefix . 'media_type' => $mediatype ] );
		}
		if ( $like ) {
			$queryBuilder->andWhere(
				$dbw->expr( $fieldPrefix . 'metadata', IExpression::LIKE,
					new LikeValue( $dbw->anyString(), $like, $dbw->anyString() ) )
			);
		}
	}

	/**
	 * @param bool $force
	 * @param bool $brokenOnly
	 * @param bool $reserialize
	 * @param bool $split
	 *
	 * @return LocalRepo
	 */
	private function newLocalRepo( bool $force, bool $brokenOnly, bool $reserialize, bool $split ): LocalRepo {
		if ( $brokenOnly && $force ) {
			$this->fatalError( 'Cannot use --broken-only and --force together. ', 2 );
		}
		$reserialize = $reserialize || $split;
		if ( $brokenOnly && $reserialize ) {
			$this->fatalError( 'Cannot use --broken-only with --convert-to-json or --split. ',
				2 );
		}

		$overrides = [
			'updateCompatibleMetadata' => !$brokenOnly,
		];
		if ( $reserialize ) {
			$overrides['reserializeMetadata'] = true;
			$overrides['useJsonMetadata'] = true;
		}
		if ( $split ) {
			$overrides['useSplitMetadata'] = true;
		}

		return $this->getServiceContainer()->getRepoGroup()
			->newCustomLocalRepo( $overrides );
	}
}

// @codeCoverageIgnoreStart
$maintClass = RefreshImageMetadata::class;
require_once RUN_MAINTENANCE_IF_MAIN;
// @codeCoverageIgnoreEnd
