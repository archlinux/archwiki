<?php

namespace MediaWiki\Extension\OATHAuth\HTMLForm;

use MediaWiki\Context\IContextSource;
use MediaWiki\Extension\OATHAuth\Module\IModule;
use MediaWiki\Extension\OATHAuth\OATHAuthModuleRegistry;
use MediaWiki\Extension\OATHAuth\OATHUser;
use MediaWiki\Extension\OATHAuth\OATHUserRepository;
use MediaWiki\HTMLForm\OOUIHTMLForm;
use MediaWiki\Logger\LoggerFactory;
use MediaWiki\Status\Status;
use OOUI\FieldsetLayout;
use OOUI\HtmlSnippet;
use OOUI\Layout;
use OOUI\PanelLayout;
use OOUI\Widget;
use Psr\Log\LoggerInterface;

abstract class OATHAuthOOUIHTMLForm extends OOUIHTMLForm {

	protected LoggerInterface $logger;

	protected ?Layout $layoutContainer = null;

	/**
	 * Make the form-wrapper panel padded
	 */
	protected bool $panelPadded = true;

	/**
	 * Make the form-wrapper panel framed
	 */
	protected bool $panelFramed = true;

	public function __construct(
		protected readonly OATHUser $oathUser,
		protected readonly OATHUserRepository $oathRepo,
		protected readonly IModule $module,
		IContextSource $context,
		protected readonly OATHAuthModuleRegistry $moduleRegistry,
	) {
		$this->logger = $this->getLogger();

		parent::__construct( $this->getDescriptors(), $context, "oathauth" );
	}

	/** @inheritDoc */
	public function show( $layout = null ): Status|bool {
		$this->layoutContainer = $layout;
		return parent::show();
	}

	/** @inheritDoc */
	public function displayForm( $submitResult ) {
		if ( !$this->layoutContainer instanceof Layout ) {
			parent::displayForm( $submitResult );
			return;
		}

		$this->layoutContainer->appendContent( new HtmlSnippet(
			$this->getHTML( $submitResult )
		) );
	}

	protected function getDescriptors(): array {
		return [];
	}

	private function getLogger(): LoggerInterface {
		return LoggerFactory::getInstance( 'authentication' );
	}

	/** @inheritDoc */
	protected function wrapFieldSetSection( $legend, $section, $attributes, $isRoot ) {
		// to get a user-visible effect, wrap the fieldset into a framed panel layout
		$layout = new PanelLayout( [
			'expanded' => false,
			'infusable' => false,
			'padded' => $this->panelPadded,
			'framed' => $this->panelFramed,
		] );

		$layout->appendContent(
			new FieldsetLayout( [
				'label' => $legend,
				'infusable' => false,
				'items' => [
					new Widget( [
						'content' => new HtmlSnippet( $section )
					] ),
				],
			] + $attributes )
		);
		return $layout;
	}

	abstract public function onSubmit( array $formData ): Status|bool|array|string;

	abstract public function onSuccess(): void;
}
