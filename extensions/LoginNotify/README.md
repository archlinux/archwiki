The LoginNotify extension notifies you when someone logs into your account. It can be configured to give warnings after a certain number of failed login attempts (The number is configurable, and can be different between unknown IPs/devices and known IP/devices). It can also give echo/email notices for successful logins from IPs you don't normally use. It can optionally integrate into the CheckUser extension in order to determine if the login is from an IP address you don't normally use. It can also set a cookie to try and determine if the login is from a device you normally use.

#### Installation
* This extension requires the Echo extension to be installed. This extension can optionally integrate with the CheckUser extension if it is installed, but does not require it.
* Download and place the file(s) in a directory called LoginNotify in your extensions/ folder.
* Add the following code at the bottom of your LocalSettings.php: `wfLoadExtension( 'LoginNotify' );`
* Navigate to Special:Version on your wiki to verify that the extension is successfully installed.

#### Configuration parameters
	"@doc": "The number of failed login attempts to permit from a known IP before a notification is triggered.",
	"LoginNotifyAttemptsKnownIP": 10
	"@doc": "The time-to-live of the count of failed login attempts from a known IP (from the time of the first failed attempt).",
	"LoginNotifyExpiryKnownIP": 604800,
	"@doc": "The number of failed login attempts to permit from a new IP before a notification is triggered.",
	"LoginNotifyAttemptsNewIP": 3,
	"@doc": "The time-to-live of the count of failed login attempts from a new IP (from the time of the first failed attempt).",
	"LoginNotifyExpiryNewIP": 1209600,
	"@doc": "Whether to trigger a notification after failed logins from known IPs.",
	"LoginNotifyCheckKnownIPs": true,
	"@doc": "Whether to trigger a notification after successful logins from unknown IPs.",
	"LoginNotifyEnableOnSuccess": true,
	"@doc": "Set different default notification preferences for different user groups. For user groups that have any of the user rights listed in this array, the preferences specified in Hooks:getOverriddenOptions() are on by default.",
	"LoginNotifyEnableForPriv": [ "editinterface", "userrights" ],
	"@doc": "Override this to use a different secret than $wgSecretKey",
	"LoginNotifySecretKey": null,
	"@doc": "Expiry in seconds. Default is 180 days",
	"LoginNotifyCookieExpire": 15552000,
	"@doc": "Override to allow sharing login cookies between sites on different subdomains",
	"LoginNotifyCookieDomain": null,
	"@doc": "Maximum number of users (records) to track as having successfully logged in on a particular device.",
	"LoginNotifyMaxCookieRecords": 6,
	"@doc": "Set to false to disable caching IPs in memcache. Set to 0 to cache forever. Default 60 days.",
	"LoginNotifyCacheLoginIPExpiry": 5184000
