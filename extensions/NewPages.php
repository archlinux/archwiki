<?php
#
# This Mediawiki extension creates a bullet list of the most
# recent new pages.  This can be useful for a small project's
# main page to give visitors a quick view of the new pages
# created since the last visit.
#
# The wiki syntax is,
#
#  <newpages>
#     limit=10
#  </newpages>
#
# where limit is the maximum number of new pages to show.
#
# To activate the extension, include it from your LocalSettings.php
# with: require_once("extensions/NewPages.php");
#
# Author: Michael Meffie
# Date: Jan 17 2006
# Credits: This extension was derived from SpecialNewpages.php.
# License: GPL v2.0
#

$wgExtensionFunctions[] = "wfNewPagesExtension";

$wgExtensionCredits['parserhook'][] = array(
    'name' => 'NewPages',
    'author' => 'Michael Meffie',
    'url' => 'http://meta.wikimedia.org/wiki/User:Meffiem',
);

function wfNewPagesExtension() {
    global $wgParser;
    $wgParser->setHook( "newpages", "renderNewPages" );
}

function renderNewPages( $input, $args=null, &$parser) {
    $localParser = new Parser();

    $output = "<br />Keine neuen Seiten<br />";
    $limit = 5;
    getBoxOption($limit,$input,'limit',true);

    $dbr =& wfGetDB( DB_SLAVE );
    extract( $dbr->tableNames( 'recentchanges', 'page' ) );

    $query_limit = $limit + 1;  # to determine if we should display (more...)
    $sql = "SELECT  rc_namespace AS namespace,
                    rc_title AS title,
                    rc_cur_id AS value,
                    rc_user AS user,
                    rc_user_text AS user_text,
                    rc_comment as comment,
                    rc_timestamp AS timestamp,
                    rc_id AS rcid,
                    page_len as length,
                    page_latest as rev_id
            FROM $recentchanges,$page
            WHERE rc_cur_id=page_id AND rc_new=1
              AND rc_namespace=".NS_MAIN." AND page_is_redirect=0
              ORDER BY value DESC
              LIMIT $query_limit";

    $result = $dbr->query( $sql );
    $num = $dbr->numRows( $result );
    if ($num > 0) {
        $output = "<ul>\n";
        for ($i=0; $i<$num && $i<$limit; $i++) {
           $row = $dbr->fetchObject( $result );
           $s = formatRow( $row );
           $output .= "<li>$s</li>\n";
        }
        if ($num > $limit) {
           $more = $localParser->parse("[[Special:Newpages|mehr...]]", $parser->mTitle, $parser->mOptions);
                 $output .= "<li>".$more->getText()."</li>\n";
        }
        $output .= "</ul>\n";
    }

    $dbr->freeResult( $result );

return $output;
}

function formatRow( $row ) {
    global $wgLang, $wgUser;

    $skin = $wgUser->getSkin();
    $link = $skin->makeKnownLink( $row->title, '' );
    $d = $wgLang->date( $row->timestamp, true );

    $s = "$link, $d";
    return $s;
}

function getBoxOption(&$value,&$input,$name,$isNumber=false) {
    if(preg_match("/^\s*$name\s*=\s*(.*)/mi",$input,$matches)) {
         if($isNumber) {
            $value=intval($matches[1]);
        } else {
            $value=htmlspecialchars($matches[1]);
        }
    }
}

?>