<?php

$wgExtensionCredits['other'][] = array(
	'name' => 'ArchLinuxAds',
	'version' => '1.0',
	'description' => 'Display ads on wiki.archlinux.org',
	'author' => 'Pierre Schmitz',
	'url' => 'http://www.archlinux.org'
);

$wgHooks['SkinAfterBottomScripts'][] = 'ArchLinuxAds::addAds';


class ArchLinuxAds {

public static function addAds($skin, $text) {
	$text .= '<div style="z-index:0; position:absolute; top:40px; right:10px;">
			<script type="text/javascript"><!--
				google_ad_client = "pub-3170555743375154";
				google_ad_width = 468;
				google_ad_height = 60;
				google_ad_format = "468x60_as";
				google_color_border = "ffffff";
				google_color_bg = "ffffff";
				google_color_link = "0771A6";
				google_color_url = "99AACC";
				google_color_text = "000000";
				//--></script>
			<script type="text/javascript" src="http://pagead2.googlesyndication.com/pagead/show_ads.js"></script>
		</div>';

	return true;
}

}

?>
