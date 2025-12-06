<?php

namespace MediaWiki\Extension\Math\Tests;

use MediaWiki\Config\ServiceOptions;
use MediaWiki\Extension\Math\MathConfig;
use MediaWiki\Extension\Math\MathRenderer;
use MediaWiki\Extension\Math\Render\RendererFactory;
use MediaWiki\Revision\RevisionRecord;
use Psr\Log\NullLogger;
use Wikimedia\ObjectCache\WANObjectCache;

trait HookIntegrationSetupTrait {
	private array $hookCalls = [];

	private function setupTestEnviroment() {
		$this->overrideConfigValue(
			'MathValidModes',
			MathConfig::SUPPORTED_MODES
		);
		$mathRendererFactoryMock = new class(
			new ServiceOptions(
				RendererFactory::CONSTRUCTOR_OPTIONS, [
					'MathoidCli' => false,
					'MathEnableExperimentalInputFormats' => false,
					'MathValidModes' => MathConfig::SUPPORTED_MODES
				]
			),
			$this->createNoOpMock( MathConfig::class ),
			$this->getServiceContainer()->getUserOptionsLookup(),
			new NullLogger(),
			WANObjectCache::newEmpty(),
		) extends RendererFactory {
			public function determineMode(
				string $mode = MathConfig::MODE_MATHML,
				array $params = [],
			): array {
				if ( isset( $params['forcemathmode'] ) ) {
					$mode = $params['forcemathmode'];
				}

				return [
					$mode,
					$params
				];
			}

			public function getRenderer(
				string $tex,
				array $params = [],
				string $mode = MathConfig::MODE_MATHML
			): MathRenderer {
				if ( isset( $params['forcemathmode'] ) ) {
					$mode = $params['forcemathmode'];
				}

				return new class( $mode, $tex, $params ) extends MathRenderer {
					/**
					 * @param string $mode
					 * @param string $tex
					 * @param array $params
					 */
					public function __construct( $mode, $tex = '', $params = [] ) {
						parent::__construct( $tex, $params );
						$this->mode = $mode;
					}

					/**
					 * @return true
					 */
					public function render() {
						return true;
					}

					/**
					 * @return true
					 */
					public function checkTeX() {
						return true;
					}

					/**
					 * @param bool $svg
					 *
					 * @return string
					 */
					public function getHtmlOutput( bool $svg = true ): string {
						return "<render>$this->mode:$this->tex</render>";
					}

					/**
					 * @return string
					 */
					protected function getMathTableName() {
						return 'whatever';
					}

					/**
					 * @return string
					 */
					public function getMode(): string {
						return $this->mode;
					}
				};
			}
		};
		$this->setService( 'Math.RendererFactory', $mathRendererFactoryMock );

		$this->setTemporaryHook(
			"MathFormulaPostRenderRevision",
			[
				$this,
				'onMathFormulaPostRenderRevision'
			]
		);
	}

	public function onMathFormulaPostRenderRevision(
		?RevisionRecord $revisionRecord,
		MathRenderer $renderer,
		?string &$text
	) {
		$this->hookCalls[] = $text;
		$text = str_replace( '</render>', ':modified</render>', $text );
	}

	public function assertMathHookFiredAll( array $wanted ) {
		$this->assertArrayEquals( $wanted, $this->hookCalls );
		$this->hookCalls = [];
	}
}
