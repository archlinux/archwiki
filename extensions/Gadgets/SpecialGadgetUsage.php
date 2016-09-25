<?php
/**
 * Implements Special:GadgetUsage
 *
 * Copyright © 2015 Niharika Kohli
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301, USA.
 * http://www.gnu.org/copyleft/gpl.html
 *
 * @file
 * @ingroup SpecialPage
 * @author Niharika Kohli <niharika@wikimedia.org>
 */

/**
 * Special:GadgetUsage - Lists all the gadgets on the wiki along with number of users.
 * @ingroup SpecialPage
 */
class SpecialGadgetUsage extends QueryPage {
	function __construct( $name = 'GadgetUsage' ) {
		parent::__construct( $name );
		$this->limit = 1000; // Show all gadgets
		$this->shownavigation = false;
		$this->activeUsers = $this->getConfig()->get( 'SpecialGadgetUsageActiveUsers' );
	}


	/**
	 * Flag for holding the value of config variable SpecialGadgetUsageActiveUsers
	 *
	 * @var bool $activeUsers
	 */
	public $activeUsers;


	public function isExpensive() {
		return true;
	}

	/**
	 * Define the database query that is used to generate the stats table.
	 * This uses 1 of 2 possible queries, depending on $wgSpecialGadgetUsageActiveUsers.
	 *
	 * The simple query is essentially:
	 * SELECT up_property, SUM(up_value)
	 * FROM user_properties
	 * WHERE up_property LIKE 'gadget-%'
	 * GROUP BY up_property;
	 *
	 * The more expensive query is:
	 * SELECT up_property, SUM(up_value), count(qcc_title)
	 * FROM user_properties
	 * LEFT JOIN user ON up_user = user_id
	 * LEFT JOIN querycachetwo ON user_name = qcc_title AND qcc_type = 'activeusers' AND up_value = 1
	 * WHERE up_property LIKE 'gadget-%'
	 * GROUP BY up_property;
	 */
	public function getQueryInfo() {
		$dbr = wfGetDB( DB_SLAVE );
		if ( !$this->activeUsers ) {
			return array(
				'tables' => array( 'user_properties' ),
				'fields' => array(
					'title' => 'up_property',
					'value' => 'SUM( up_value )',
					'namespace' => NS_GADGET
				),
				'conds' => array(
					'up_property' . $dbr->buildLike( 'gadget-', $dbr->anyString() )
				),
				'options' => array(
					'GROUP BY' => array( 'up_property' )
				)
			);
		} else {
			return array(
				'tables' => array( 'user_properties', 'user', 'querycachetwo' ),
				'fields' => array(
					'title' => 'up_property',
					'value' => 'SUM( up_value )',
					// Need to pick fields existing in the querycache table so that the results are cachable
					'namespace' => 'COUNT( qcc_title )'
				),
				'conds' => array(
					'up_property' . $dbr->buildLike( 'gadget-', $dbr->anyString() )
				),
				'options' => array(
					'GROUP BY' => array( 'up_property' )
				),
				'join_conds' => array(
					'user' => array(
						'LEFT JOIN', array(
							'up_user = user_id'
						)
					),
					'querycachetwo' => array(
						'LEFT JOIN', array(
							'user_name = qcc_title',
							'qcc_type = "activeusers"',
							'up_value = 1'
						)
					)
				)
			);
		}
	}

	public function getOrderFields() {
		return array( 'value' );
	}

	/**
	 * Output the start of the table
	 * Including opening <table>, and first <tr> with column headers.
	 */
	protected function outputTableStart() {
		$html = Html::openElement( 'table', array( 'class' => array( 'sortable', 'wikitable' ) ) );
		$html .= Html::openElement( 'tr', array() );
		$headers = array( 'gadgetusage-gadget', 'gadgetusage-usercount' );
		if ( $this->activeUsers ) {
			$headers[] = 'gadgetusage-activeusers';
		}
		foreach( $headers as $h ) {
			if ( $h == 'gadgetusage-gadget' ) {
				$html .= Html::element( 'th', array(), $this->msg( $h )->text() );
			} else {
				$html .= Html::element( 'th', array( 'data-sort-type' => 'number' ),
					$this->msg( $h )->text() );
			}
		}
		$html .= Html::closeElement( 'tr' );
		$this->getOutput()->addHTML( $html );
	}

	/**
	 * @param Skin $skin
	 * @param object $result Result row
	 * @return string|bool String of HTML
	 */
	public function formatResult( $skin, $result ) {
		$gadgetTitle = substr( $result->title, 7 );
		$gadgetUserCount = $this->getLanguage()->formatNum( $result->value );
		if ( $gadgetTitle ) {
			$html = Html::openElement( 'tr', array() );
			$html .= Html::element( 'td', array(), $gadgetTitle );
			$html .= Html::element( 'td', array(), $gadgetUserCount );
			if ( $this->activeUsers == true ) {
				$activeUserCount = $this->getLanguage()->formatNum( $result->namespace );
				$html .= Html::element( 'td', array(), $activeUserCount );
			}
			$html .= Html::closeElement( 'tr' );
			return $html;
		}
		return false;
	}

	/**
	 * Get a list of default gadgets
	 * @param GadgetRepo $gadgetRepo
	 * @param array $gadgetIds list of gagdet ids registered in the wiki
	 * @return array
	*/
	protected function getDefaultGadgets( $gadgetRepo, $gadgetIds ) {
		$gadgetsList = array();
		foreach ( $gadgetIds as $g ) {
			$gadget = $gadgetRepo->getGadget( $g );
			if ( $gadget->isOnByDefault() ) {
				$gadgetsList[] = $gadget->getName();
			}
		}
		asort( $gadgetsList, SORT_STRING | SORT_FLAG_CASE );
		return $gadgetsList;
	}

	/**
	 * Format and output report results using the given information plus
	 * OutputPage
	 *
	 * @param OutputPage $out OutputPage to print to
	 * @param Skin $skin User skin to use
	 * @param IDatabase $dbr Database (read) connection to use
	 * @param ResultWrapper $res Result pointer
	 * @param int $num Number of available result rows
	 * @param int $offset Paging offset
	 */
	protected function outputResults( $out, $skin, $dbr, $res, $num, $offset ) {
		$gadgetRepo = GadgetRepo::singleton();
		$gadgetIds = $gadgetRepo->getGadgetIds();
		$defaultGadgets = $this->getDefaultGadgets( $gadgetRepo, $gadgetIds );
		if ( $this->activeUsers ) {
			$out->addHtml(
				$this->msg( 'gadgetusage-intro' )->numParams( $this->getConfig()->get( 'ActiveUserDays' ) )->parseAsBlock()
			);
		} else {
			$out->addHtml(
				$this->msg( 'gadgetusage-intro-noactive' )->parseAsBlock()
			);
		}
		if ( $num > 0 ) {
			$this->outputTableStart();
			// Append default gadgets to the table with 'default' in the total and active user fields
			foreach ( $defaultGadgets as $default ) {
				$html = Html::openElement( 'tr', array() );
				$html .= Html::element( 'td', array(), $default );
				$html .= Html::element( 'td', array(), $this->msg( 'gadgetusage-default' ) );
				if ( $this->activeUsers ) {
					$html .= Html::element( 'td', array(), $this->msg( 'gadgetusage-default' ) );
				}
				$html .= Html::closeElement( 'tr' );
				$out->addHTML( $html );
			}
			foreach ( $res as $row ) {
				// Remove the 'gadget-' part of the result string and compare if it's present
				// in $defaultGadgets, if not we format it and add it to the output
				if ( !in_array( substr( $row->title, 7 ), $defaultGadgets ) ) {
					// Only pick gadgets which are in the list $gadgetIds to make sure they exist
					if ( in_array( substr( $row->title, 7 ), $gadgetIds ) ) {
						$line = $this->formatResult( $skin, $row );
						if ( $line ) {
							$out->addHTML( $line );
						}
					}
				}
			}
			// Close table element
			$out->addHtml( Html::closeElement( 'table' ) );
		} else {
			$out->addHtml(
				$this->msg( 'gadgetusage-noresults' )->parseAsBlock()
			);
		}
	}

	protected function getGroupName() {
		return 'wiki';
	}
}
