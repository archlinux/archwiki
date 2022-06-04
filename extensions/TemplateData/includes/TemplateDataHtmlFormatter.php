<?php

namespace MediaWiki\Extension\TemplateData;

use Html;
use MessageLocalizer;
use stdClass;

class TemplateDataHtmlFormatter {

	/** @var MessageLocalizer */
	private $localizer;

	/** @var string */
	private $languageCode;

	/**
	 * @param MessageLocalizer $localizer
	 * @param string $languageCode
	 */
	public function __construct( MessageLocalizer $localizer, string $languageCode = 'en' ) {
		$this->localizer = $localizer;
		$this->languageCode = $languageCode;
	}

	/**
	 * @param TemplateDataBlob $templateData
	 *
	 * @return string HTML
	 */
	public function getHtml( TemplateDataBlob $templateData ): string {
		$data = $templateData->getDataInLanguage( $this->languageCode );

		if ( is_string( $data->format ) && isset( TemplateDataValidator::PREDEFINED_FORMATS[$data->format] ) ) {
			// The following icon names are used here:
			// * template-format-block
			// * template-format-inline
			// @phan-suppress-next-line PhanTypeSuspiciousStringExpression
			$icon = 'template-format-' . $data->format;
			$formatMsg = $data->format;
		} else {
			$icon = 'settings';
			$formatMsg = $data->format ? 'custom' : null;
		}
		$sorting = count( (array)$data->params ) > 1 ? " sortable" : "";
		$html = '<header>'
			. Html::element( 'p',
				[
					'class' => [
						'mw-templatedata-doc-desc',
						'mw-templatedata-doc-muted' => $data->description === null,
					]
				],
				$data->description ??
					$this->localizer->msg( 'templatedata-doc-desc-empty' )->text()
			)
			. '</header>'
			. '<table class="wikitable mw-templatedata-doc-params' . $sorting . '">'
			. Html::rawElement( 'caption', [],
				Html::element( 'p', [],
					$this->localizer->msg( 'templatedata-doc-params' )->text()
				)
				. ( $formatMsg !== null ?
					Html::rawElement( 'p', [],
						new \OOUI\IconWidget( [ 'icon' => $icon ] )
						. Html::element(
							'span',
							[ 'class' => 'mw-templatedata-format' ],
							// Messages that can be used here:
							// * templatedata-doc-format-block
							// * templatedata-doc-format-custom
							// * templatedata-doc-format-inline
							$this->localizer->msg( 'templatedata-doc-format-' . $formatMsg )->text()
						)
					) :
					''
				)
			)
			. '<thead><tr>'
			. Html::element( 'th', [ 'colspan' => 2 ],
				$this->localizer->msg( 'templatedata-doc-param-name' )->text()
			)
			. Html::element( 'th', [],
				$this->localizer->msg( 'templatedata-doc-param-desc' )->text()
			)
			. Html::element( 'th', [],
				$this->localizer->msg( 'templatedata-doc-param-type' )->text()
			)
			. Html::element( 'th', [],
				$this->localizer->msg( 'templatedata-doc-param-status' )->text()
			)
			. '</tr></thead>'
			. '<tbody>';

		$paramNames = $data->paramOrder ?? array_keys( (array)$data->params );
		if ( !$paramNames ) {
			// Display no parameters message
			$html .= '<tr>'
			. Html::element( 'td',
				[
					'class' => 'mw-templatedata-doc-muted',
					'colspan' => 7
				],
				$this->localizer->msg( 'templatedata-doc-no-params-set' )->text()
			)
			. '</tr>';
		}

		foreach ( $paramNames as $paramName ) {
			$html .= $this->formatParameterTableRow( $paramName, $data->params->$paramName );
		}
		$html .= '</tbody></table>';

		return Html::rawElement( 'section', [ 'class' => 'mw-templatedata-doc-wrap' ], $html );
	}

	/**
	 * @param int|string $paramName
	 * @param stdClass $param
	 *
	 * @return string HTML
	 */
	private function formatParameterTableRow( $paramName, stdClass $param ): string {
		'@phan-var object $param';

		$allParamNames = [ Html::element( 'code', [], $paramName ) ];
		foreach ( $param->aliases as $alias ) {
			$allParamNames[] = Html::element( 'code', [ 'class' => 'mw-templatedata-doc-param-alias' ],
				$alias
			);
		}

		$suggestedValues = [];
		foreach ( $param->suggestedvalues as $suggestedValue ) {
			$suggestedValues[] = Html::element( 'code', [ 'class' => 'mw-templatedata-doc-param-alias' ],
				$suggestedValue
			);
		}

		if ( $param->deprecated ) {
			$status = 'deprecated';
		} elseif ( $param->required ) {
			$status = 'required';
		} elseif ( $param->suggested ) {
			$status = 'suggested';
		} else {
			$status = 'optional';
		}

		return '<tr>'
			// Label
			. Html::element( 'th', [], $param->label ?? $paramName )
			// Parameters and aliases
			. Html::rawElement( 'td', [ 'class' => 'mw-templatedata-doc-param-name' ],
				implode( $this->localizer->msg( 'word-separator' )->escaped(), $allParamNames )
			)
			// Description
			. Html::rawElement( 'td', [
					'class' => [
						'mw-templatedata-doc-muted' => ( $param->description === null )
					]
				],
				Html::element( 'p', [],
					$param->description ??
						$this->localizer->msg( 'templatedata-doc-param-desc-empty' )->text()
				)
				. Html::rawElement( 'dl', [],
					// Suggested Values
					( $suggestedValues ? ( Html::element( 'dt', [],
						$this->localizer->msg( 'templatedata-doc-param-suggestedvalues' )->text()
					)
					. Html::rawElement( 'dd', [],
						implode( $this->localizer->msg( 'word-separator' )->escaped(), $suggestedValues )
					) ) : '' ) .
					// Default
					( $param->default !== null ? ( Html::element( 'dt', [],
						$this->localizer->msg( 'templatedata-doc-param-default' )->text()
					)
					. Html::element( 'dd', [],
						$param->default
					) ) : '' )
					// Example
					. ( $param->example !== null ? ( Html::element( 'dt', [],
						$this->localizer->msg( 'templatedata-doc-param-example' )->text()
					)
					. Html::element( 'dd', [],
						$param->example
					) ) : '' )
					// Auto value
					. ( $param->autovalue !== null ? ( Html::element( 'dt', [],
						$this->localizer->msg( 'templatedata-doc-param-autovalue' )->text()
					)
					. Html::rawElement( 'dd', [],
						Html::element( 'code', [], $param->autovalue )
					) ) : '' )
				)
			)
			// Type
			. Html::element( 'td',
				[
					'class' => [
						'mw-templatedata-doc-param-type',
						'mw-templatedata-doc-muted' => $param->type === 'unknown'
					]
				],
				// Known messages, for grepping:
				// templatedata-doc-param-type-boolean, templatedata-doc-param-type-content,
				// templatedata-doc-param-type-date, templatedata-doc-param-type-line,
				// templatedata-doc-param-type-number, templatedata-doc-param-type-string,
				// templatedata-doc-param-type-unbalanced-wikitext, templatedata-doc-param-type-unknown,
				// templatedata-doc-param-type-url, templatedata-doc-param-type-wiki-file-name,
				// templatedata-doc-param-type-wiki-page-name, templatedata-doc-param-type-wiki-template-name,
				// templatedata-doc-param-type-wiki-user-name
				$this->localizer->msg( 'templatedata-doc-param-type-' . $param->type )->text()
			)
			// Status
			. Html::element( 'td',
				[
					// CSS class names that can be used here:
					// mw-templatedata-doc-param-status-deprecated
					// mw-templatedata-doc-param-status-optional
					// mw-templatedata-doc-param-status-required
					// mw-templatedata-doc-param-status-suggested
					'class' => "mw-templatedata-doc-param-status-$status",
					'data-sort-value' => [
						'deprecated' => -1,
						'suggested' => 1,
						'required' => 2,
					][$status] ?? 0,
				],
				// Messages that can be used here:
				// templatedata-doc-param-status-deprecated
				// templatedata-doc-param-status-optional
				// templatedata-doc-param-status-required
				// templatedata-doc-param-status-suggested
				$this->localizer->msg( "templatedata-doc-param-status-$status" )->text()
			)
			. '</tr>';
	}

}
