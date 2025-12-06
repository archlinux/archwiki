ConfirmEdit
=========

ConfirmEdit extension for MediaWiki

This extension provides various CAPTCHA tools for MediaWiki, to allow
for protection against spambots and other automated tools.

You must set `$wgCaptchaClass` to a chosen module, otherwise
the demo captcha will be used. For example, to use FancyCaptcha:

```php
$wgCaptchaClass = 'FancyCaptcha';
````

The following modules are included in ConfirmEdit:

* `SimpleCaptcha` - users have to solve an arithmetic math problem
* `FancyCaptcha` - users have to identify a series of characters, displayed
in a stylized way
* `QuestyCaptcha` - users have to answer a question, out of a series of
questions defined by the administrator(s)
* `ReCaptchaNoCaptcha` - users have to solve different types of visually or
audially tasks.
* `hCaptcha` - users have to solve visual tasks
* `Turnstile` - users check a box, which runs some client-side JS
heuristics

For more information, see the extension homepage at:
https://www.mediawiki.org/wiki/Extension:ConfirmEdit

### License

ConfirmEdit is published under the GPL license.

### Authors

The main framework, and the SimpleCaptcha and FancyCaptcha modules, were
written by Brooke Vibber.

The QuestyCaptcha module was written by Benjamin Lees.

Additional maintenance work was done by Yaron Koren.

### Configuration comments
```php
/**
 * Needs to be explicitly set to the default Captcha implementation you want to use. Otherwise, it will use a demo captcha, which will likely be ineffective.
 *
 * Can be overridden on an action-by-action basis using $wgCaptchaTriggers.
 *
 * For example, to use FancyCaptcha:
 * ```
 * $wgCaptchaClass ='FancyCaptcha';
 * ```
 */
$wgCaptchaClass = 'SimpleCaptcha';

/**
 * List of IP ranges to allow to skip the captcha, similar to the group setting:
 * "$wgGroupPermission[...]['skipcaptcha'] = true"
 *
 * Specific IP addresses or CIDR-style ranges may be used,
 * for instance:
 * $wgCaptchaBypassIPs = [ '192.168.1.0/24', '10.1.0.0/16' ];
 */
$wgCaptchaBypassIPs = false;

/**
 * Actions which can trigger a captcha
 *
 * If the 'edit' trigger is on, *every* edit will trigger the captcha.
 * This may be useful for protecting against vandal bot attacks.
 *
 * If using the default 'addurl' trigger, the captcha will trigger on
 * edits that include URLs that aren't in the current version of the page.
 * This should catch automated link spammers without annoying people when
 * they make more typical edits.
 *
 * The captcha code should not use $wgCaptchaTriggers, but SimpleCaptcha::triggersCaptcha()
 * which also takes into account per namespace triggering.
 */
$wgCaptchaTriggers = [
    // Show a captcha on every edit
    'edit' => false,
    // Show a captcha on page creation
    'create' => false,
    // Show a captcha on Special:Emailuser
    'sendemail' => false,
    // Show a captcha on edits that add URLs
    'addurl' => true,
    // Show a captcha on Special:CreateAccount
    'createaccount' => true,
    // Special:Userlogin after failure
    'badlogin' => true,
];

/**
 * You can also override the captcha type (by default $wgCaptchaClass) for a specific trigger.
 *
 * If you set 'trigger' to false, you can turn off showing a captcha for that action but still leave the desired
 * Captcha type in place to turn it on quickly in the future.
 *
 * Please note that you still need to enable the captcha implementation using wLoadExtension() like normal.
 *
 * For example, to use QuestyCaptcha on the 'createaccount' action:
 */
 $wgCaptchaTriggers = [
    // Show a captcha on every edit
    'edit' => false,
    // Show a captcha on page creation
    'create' => false,
    // Show a captcha on Special:Emailuser
    'sendemail' => false,
    // Show a captcha on edits that add URLs
    'addurl' => true,
    // Show a captcha on Special:CreateAccount
    'createaccount' => [
        'trigger' => true,
        'class' => 'QuestyCaptcha',
    ],
    // Special:Userlogin after failure
    'badlogin' => true,
];

/**
 * You may wish to apply special rules for captcha triggering on some namespaces.
 * $wgCaptchaTriggersOnNamespace[<namespace id>][<trigger>] forces an always on /
 * always off configuration with that trigger for the given namespace.
 * Leave unset to use the global options ($wgCaptchaTriggers).
 *
 * Shall not be used with 'createaccount' (it is not checked).
 */
$wgCaptchaTriggersOnNamespace = [];

# Example:
# $wgCaptchaTriggersOnNamespace[NS_TALK]['create'] = false; // Allow creation of talk pages without captchas.
# $wgCaptchaTriggersOnNamespace[NS_PROJECT]['edit'] = true; // Show captcha whenever editing Project pages.

/**
 * Indicate how to store per-session data required to match up the
 * internal captcha data with the editor.
 *
 * 'MediaWiki\Extension\ConfirmEdit\Store\CaptchaSessionStore' uses PHP's session storage, which is cookie-based
 * and may fail for anons with cookies disabled.
 *
 * 'CaptchaCacheStore' uses MediaWiki core's MicroStash,
 * for storing captch data with a TTL eviction strategy.
 */
$wgCaptchaStorageClass = MediaWiki\Extension\ConfirmEdit\Store\CaptchaSessionStore::class;

/**
 * Number of seconds a captcha session should last in the data cache
 * before expiring when managing through CaptchaCacheStore class.
 *
 * Default is a half-hour.
 */
$wgCaptchaSessionExpiration = 30 * 60;

/**
 * Number of seconds after a bad login (from a specific IP address) that a captcha will be shown to
 * that client on the login form to slow down password-guessing bots.
 *
 * A longer expiration time of $wgCaptchaBadLoginExpiration * 300 will also be applied against a
 * login attempt count of $wgCaptchaBadLoginAttempts * 30.
 *
 * Has no effect if 'badlogin' is disabled in $wgCaptchaTriggers or
 * if there is not a caching engine enabled.
 *
 * Default is five minutes.
 */
$wgCaptchaBadLoginExpiration = 5 * 60;

/**
 * Number of seconds after a bad login (for a specific user account) that a captcha will be shown to
 * that client on the login form to slow down password-guessing bots.
 *
 * A longer expiration time of $wgCaptchaBadLoginExpiration * 300 will be applied against a login
 * attempt count of $wgCaptchaBadLoginAttempts * 30.
 *
 * Has no effect if 'badlogin' is disabled in $wgCaptchaTriggers or
 * if there is not a caching engine enabled.
 *
 * Default is 10 minutes
 */
$wgCaptchaBadLoginPerUserExpiration = 10 * 60;

/**
 * Allow users who have confirmed their email addresses to skip being shown a captcha.
 *
 * $wgAllowConfirmedEmail was deprecated in 1.36, and removed in 1.45. Use this config instead.
 */
$wgGroupPermissions['emailconfirmed']['skipcaptcha'] = true;

/**
 * Number of bad login attempts (from a specific IP address) before triggering the captcha. 0 means that the
 * captcha is presented on the first login.
 *
 * A captcha will also be triggered if the number of failed logins exceeds $wgCaptchaBadLoginAttempts * 30
 * in a period of $wgCaptchaBadLoginExpiration * 300.
 */
$wgCaptchaBadLoginAttempts = 3;

/**
 * Number of bad login attempts (for a specific user account) before triggering the captcha. 0 means the
 * captcha is presented on the first login.
 *
 * A captcha will also be triggered if the number of failed logins exceeds $wgCaptchaBadLoginPerUserAttempts * 30
 * in a period of $wgCaptchaBadLoginPerUserExpiration * 300.
 */
$wgCaptchaBadLoginPerUserAttempts = 20;

/**
 * Regex to ignore URLs to known-good sites...
 * For instance:
 * $wgCaptchaIgnoredUrls = '#^https?://([a-z0-9-]+\\.)?(wikimedia|wikipedia)\.org/#i';
 * Local admins can define a local allow list under [[MediaWiki:captcha-addurl-whitelist]]
 */
$wgCaptchaIgnoredUrls = false;

/**
 * Additional regexes to check for. Use full regexes; can match things
 * other than URLs such as junk edits.
 *
 * If the new version matches one and the old version doesn't,
 * show the captcha screen.
 *
 * @fixme Add a message for local admins to add items as well.
 */
$wgCaptchaRegexes = [];

/**
 * Feature flag to toggle the list of available custom actions to enable in AbuseFilter. See AbuseFilterHooks::onAbuseFilterCustomActions
 */
$wgConfirmEditEnabledAbuseFilterCustomActions = [];
```
