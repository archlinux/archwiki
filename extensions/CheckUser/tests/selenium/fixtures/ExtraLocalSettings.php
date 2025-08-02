<?php
/**
 * Settings overrides for CheckUser selenium tests.
 */

// Grant the CheckUser group the ability to use IPInfo
$wgGroupPermissions['checkuser']['ipinfo'] = true;
$wgGroupPermissions['checkuser']['ipinfo-view-basic'] = true;
$wgGroupPermissions['checkuser']['ipinfo-view-full'] = true;
$wgGroupPermissions['checkuser']['ipinfo-view-log'] = true;

// Enable the IPInfo BetaFeature for everyone, so that the preference exists
// for users in the CheckUser group.
$wgDefaultUserOptions['ipinfo-beta-feature-enable'] = 1;
