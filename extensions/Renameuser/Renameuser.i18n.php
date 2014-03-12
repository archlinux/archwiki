<?php
/**
 * Internationalisation file for extension Renameuser.
 *
 * @file
 * @ingroup Extensions
 */

$messages = array();

$messages['en'] = array(
	'renameuser'          => 'Rename user',
	'renameuser-linkoncontribs' => 'rename user',
	'renameuser-linkoncontribs-text' => 'Rename this user',
	'renameuser-desc'     => 'Adds a [[Special:Renameuser|special page]] to rename a user (need \'\'renameuser\'\' right)',
	'renameuser-summary' => '', # do not translate or duplicate this message to other languages
	'renameuserold'       => 'Current username:',
	'renameusernew'       => 'New username:',
	'renameuserreason'    => 'Reason:',
	'renameusermove'      => 'Move user and talk pages (and their subpages) to new name',
	'renameusersuppress'  => 'Do not create redirects to the new name',
	'renameuserreserve'   => 'Block the old username from future use',
	'renameuserwarnings'  => 'Warnings:',
	'renameuserconfirm'   => 'Yes, rename the user',
	'renameusersubmit'    => 'Submit',
	'renameuser-submit-blocklog' => 'Show block log for user',

	'renameusererrordoesnotexist' => 'The user "<nowiki>$1</nowiki>" does not exist.',
	'renameusererrorexists'       => 'The user "<nowiki>$1</nowiki>" already exists.',
	'renameusererrorinvalid'      => 'The username "<nowiki>$1</nowiki>" is invalid.',
	'renameuser-error-request'    => 'There was a problem with receiving the request.
Please go back and try again.',
	'renameuser-error-same-user'  => 'You cannot rename a user to the same thing as before.',
	'renameusersuccess'           => 'The user "<nowiki>$1</nowiki>" has been renamed to "<nowiki>$2</nowiki>".',

	'renameuser-page-exists'  => 'The page $1 already exists and cannot be automatically overwritten.',
	'renameuser-page-moved'   => 'The page $1 has been moved to $2.',
	'renameuser-page-unmoved' => 'The page $1 could not be moved to $2.',

	'log-name-renameuser' => 'User rename log',
	'log-description-renameuser' => 'This is a log of changes to user names.',

	'logentry-renameuser-renameuser' => '$1 {{GENDER:$2|renamed}} user $4 ({{PLURAL:$6|$6 edit|$6 edits}}) to $5',
	'logentry-renameuser-renameuser-legacier' => '$1 renamed user $4 to $5',
	'logentry-renameuser-renameuser-legaciest' => '$1',
	'renameuser-move-log' => 'Automatically moved page while renaming the user "[[User:$1|$1]]" to "[[User:$2|$2]]"',

	'action-renameuser'     => 'rename users',
	'right-renameuser'      => 'Rename users',

	'renameuser-renamed-notice' => 'This user has been renamed.
The rename log is provided below for reference.', # Supports GENDER
);

/** Message documentation (Message documentation)
 * @author EugeneZelenko
 * @author Jon Harald Søby
 * @author Meno25
 * @author Nemo bis
 * @author Nike
 * @author SPQRobin
 * @author Shirayuki
 * @author Siebrand
 * @author The Evil IP address
 * @author Umherirrender
 */
$messages['qqq'] = array(
	'renameuser' => '{{doc-special|RenameUser}}
{{Identical|Rename user}}',
	'renameuser-linkoncontribs' => 'Link description used on [[Special:Contributions]] and [[Special:DeletedContributions]]. Only added if a user has rights to rename users.
{{Identical|Rename user}}',
	'renameuser-linkoncontribs-text' => 'Tooltip for {{msg-mw|renameuser-linkoncontribs}}.',
	'renameuser-desc' => '{{desc|name=Rename user|url=http://www.mediawiki.org/wiki/Extension:Renameuser}}',
	'renameuser-summary' => '{{notranslate}}',
	'renameuserold' => 'Used as label for the "Old username" input box in [[Special:RenameUser]].

See also:
* {{msg-mw|Renameusernew}}',
	'renameusernew' => 'Used as label for the "New username" input box in [[Special:RenameUser]].

See also:
* {{msg-mw|Renameuserold}}',
	'renameuserreason' => 'Used as label for the "Reason to rename user" input box in [[Special:RenameUser]].
{{Identical|Reason}}',
	'renameusermove' => 'Used as label for the "Move pages" checkbox in the "Rename user" form in [[Special:RenameUser]].',
	'renameusersuppress' => 'Used as label for the "Suppress redirect" checkbox in the "Rename user" form in [[Special:RenameUser]].',
	'renameuserreserve' => 'Option to block the old username (after it has been renamed) from being used again.',
	'renameuserwarnings' => 'Used as label in [[Special:RenameUser]].

Followed by a list of the warnings.
{{Identical|Warning}}',
	'renameuserconfirm' => 'Used as label for the "Confirm action" checkbox in the "Rename user" form in [[Special:RenameUser]].',
	'renameusersubmit' => 'Used as label for the Submit button in the "Rename user" form in [[Special:RenameUser]].
{{Identical|Submit}}',
	'renameuser-submit-blocklog' => 'Button text. When clicked, the block log entries for a given user will be displayed.',
	'renameusererrordoesnotexist' => 'Used as error message. Parameters:
* $1 - username
See also:
* {{msg-mw|Renameusererrorexists}}',
	'renameusererrorexists' => 'Used as error message. Parameters:
* $1 - username
See also:
* {{msg-mw|Renameusererrordoesnotexist}}',
	'renameusererrorinvalid' => 'Used as error message when renaming user in [[Special:Renameuser]]. Parameters:
* $1 - old username, or new username',
	'renameuser-error-request' => 'Used as error message when renaming user in [[Special:Renameuser]].',
	'renameuser-error-same-user' => 'Used as error message when renaming user in [[Special:Renameuser]].',
	'renameusersuccess' => 'Used as success message when renaming user in [[Special:Renameuser]]. Parameters:
* $1 - old username
* $2 - new username',
	'renameuser-page-exists' => 'Used when renaming user in [[Special:Renameuser]]. Parameters:
* $1 - new page title (with link)',
	'renameuser-page-moved' => 'Used as success message when renaming user in [[Special:Renameuser]]. Parameters:
* $1 - old page title (with link)
* $2 - new page title (with link)
See also:
* {{msg-mw|Renameuser-page-unmoved}}',
	'renameuser-page-unmoved' => 'Used as failure message when renaming user in [[Special:Renameuser]]. Parameters:
* $1 - old page title (with link)
* $2 - new page title (with link)
See also:
* {{msg-mw|Renameuser-page-moved}}',
	'log-name-renameuser' => '{{doc-logpage}}

As well as log page title and page header for [[Special:Log/renameuser]].',
	'log-description-renameuser' => 'Log description on [[Special:Log/renameuser]].',
	'logentry-renameuser-renameuser' => '{{logentry|[[Special:Log/renameuser]]}}
Parameters:
* $4 - the old name of the renamed user
* $5 - the new name of the renamed user
* $6 - number of edits made by the user',
	'logentry-renameuser-renameuser-legacier' => '{{logentry|[[Special:Log/renameuser]]}}
Parameters:
* $4 - the old name of the renamed user
* $5 - the new name of the renamed user',
	'logentry-renameuser-renameuser-legaciest' => 'Used in [[Special:Log/renameuser]]. {{logentry}}
Note that only user name is included in this legacy log entry, everything else is hardcoded into log comment.',
	'renameuser-move-log' => 'Reason for a page move when a page is moved because of a user rename. Parameters:
* $1 - the old username
* $2 - the new username',
	'action-renameuser' => '{{Doc-action|renameuser}}
{{Identical|Rename user}}',
	'right-renameuser' => '{{doc-right|renameuser}}
{{Identical|Rename user}}',
	'renameuser-renamed-notice' => 'Parameters:
* $1 - (Optional) username, for GENDER support',
);

/** Afrikaans (Afrikaans)
 * @author Naudefj
 * @author SPQRobin
 * @author පසිඳු කාවින්ද
 */
$messages['af'] = array(
	'renameuser' => 'Hernoem gebruiker',
	'renameuser-linkoncontribs' => 'hernoem gebruiker',
	'renameuser-linkoncontribs-text' => 'Hernoem hierdie gebruiker',
	'renameuser-desc' => "Herdoop gebruikers (benodig ''renameuser'' regte)",
	'renameuserold' => 'Huidige gebruikersnaam:',
	'renameusernew' => 'Nuwe gebruikersnaam:',
	'renameuserreason' => 'Rede vir hernoeming:', # Fuzzy
	'renameusermove' => 'Hernoem gebruikers- en besprekingsbladsye (met subblaaie) na nuwe naam',
	'renameusersuppress' => 'Moenie skep aansture na die nuwe naam',
	'renameuserreserve' => 'Voorkom dat die ou gebruiker in die toekoms weer gebruik kan word',
	'renameuserwarnings' => 'Waarskuwings:',
	'renameuserconfirm' => 'Ja, hernoem die gebruiker',
	'renameusersubmit' => 'Hernoem',
	'renameusererrordoesnotexist' => 'Die gebruiker "<nowiki>$1</nowiki>" bestaan nie',
	'renameusererrorexists' => 'Die gebruiker "<nowiki>$1</nowiki>" bestaan reeds',
	'renameusererrorinvalid' => '"<nowiki>$1</nowiki>" is \'n ongeldige gebruikernaam',
	'renameuser-error-request' => "Daar was 'n probleem met die ontvangs van die versoek. Gaan asseblief terug en probeer weer.",
	'renameuser-error-same-user' => 'U kan nie a gebruiker na dieselfde naam hernoem nie.',
	'renameusersuccess' => 'Die gebruiker "<nowiki>$1</nowiki>" is hernoem na "<nowiki>$2</nowiki>".',
	'renameuser-page-exists' => 'Die bladsy $1 bestaan reeds en kan nie outomaties oorskryf word nie.',
	'renameuser-page-moved' => 'Die bladsy $1 is na $2 geskuif.',
	'renameuser-page-unmoved' => 'Die bladsy $1 kon nie na $2 geskuif word nie.',
	'log-name-renameuser' => 'Logboek van gebruikershernoemings',
	'log-description-renameuser' => 'Hieronder is gebruikersname wat gewysig is.',
	'renameuser-move-log' => 'Bladsy is outomaties geskuif met die wysiging van die gebruiker "[[User:$1|$1]]" na "[[User:$2|$2]]"',
	'right-renameuser' => 'Hernoem gebruikers',
	'renameuser-renamed-notice' => 'Hierdie gebruiker is hernoem.
Relevante inligting uit die logboek van gebruikersnaamwysigings word hier onder ter verwysing weergegee.',
);

/** Aragonese (aragonés)
 * @author Juanpabl
 * @author SMP
 */
$messages['an'] = array(
	'renameuser' => 'Renombrar un usuario',
	'renameuser-linkoncontribs' => "cambiar o nombre d'iste usuario",
	'renameuser-linkoncontribs-text' => "Cambiar o nombre d'iste usuario",
	'renameuser-desc' => "Renombrar un usuario (amenista os dreitos de ''renameuser'')",
	'renameuserold' => 'Nombre actual:',
	'renameusernew' => 'Nombre nuevo:',
	'renameuserreason' => "Razón d'o cambeo de nombre:", # Fuzzy
	'renameusermove' => "Tresladar as pachinas d'usuario y de descusión (y as suyas sozpachinas) ta o nuevo nombre",
	'renameusersuppress' => 'No creyar reendreceras ta o nuevo nombre',
	'renameuserreserve' => "Bloqueyar l'antigo nombre d'usuario ta privar que torne a ser usau",
	'renameuserwarnings' => 'Alvertencias:',
	'renameuserconfirm' => "Sí, quiero cambiar o nombre de l'usuario",
	'renameusersubmit' => 'Ninviar',
	'renameusererrordoesnotexist' => 'L\'usuario "<nowiki>$1</nowiki>" no existe.',
	'renameusererrorexists' => 'L\'usuario "<nowiki>$1</nowiki>" ya existe.',
	'renameusererrorinvalid' => 'O nombre d\'usuario "<nowiki>$1</nowiki>" no ye conforme.',
	'renameuser-error-request' => 'Bi habió bell problema recullindo a demanda. Por favor, torne enta zaga y prebe una atra vegada.',
	'renameuser-error-same-user' => 'No puede renombrar un usuario con o mesmo nombre que ya teneba.',
	'renameusersuccess' => 'S\'ha renombrau l\'usuario "<nowiki>$1</nowiki>" como "<nowiki>$2</nowiki>".',
	'renameuser-page-exists' => 'A pachina $1 ya existe y no puede estar sustituyita automaticament.',
	'renameuser-page-moved' => "S'ha tresladato a pachina $1 ta $2.",
	'renameuser-page-unmoved' => "A pachina $1 no s'ha puesto tresladar ta $2.",
	'log-name-renameuser' => "Rechistro de cambios de nombre d'usuarios",
	'renameuser-move-log' => 'Pachina tresladata automaticament en renombrar o usuario "[[User:$1|$1]]" como "[[User:$2|$2]]"',
	'right-renameuser' => 'Renombrar usuarios',
	'renameuser-renamed-notice' => "O nombre d'iste usuario s'ha modificau.
O rechistro de cambeos de nombre d'usuario se proveye debaixo ta mas referencia.",
);

/** Old English (Ænglisc)
 * @author Spacebirdy
 */
$messages['ang'] = array(
	'renameuser' => 'Ednemnan brūcend',
	'renameuser-linkoncontribs' => 'ednemnan brūcend',
	'renameusersubmit' => 'Forþsendan',
);

/** Arabic (العربية)
 * @author Aiman titi
 * @author DRIHEM
 * @author Meno25
 * @author Mido
 * @author OsamaK
 */
$messages['ar'] = array(
	'renameuser' => 'إعادة تسمية مستخدم',
	'renameuser-linkoncontribs' => 'أعد تسمية المستخدم',
	'renameuser-linkoncontribs-text' => 'أعد تسمية هذا المستخدم',
	'renameuser-desc' => "يضيف [[Special:Renameuser|صفحة خاصة]] لإعادة تسمية مستخدم (يحتاج إلى صلاحية ''renameuser'')",
	'renameuserold' => 'اسم المستخدم الحالي:',
	'renameusernew' => 'الاسم الجديد:',
	'renameuserreason' => 'السبب:',
	'renameusermove' => 'انقل صفحات المستخدم ونقاشه (بالصفحات الفرعية) إلى الاسم الجديد',
	'renameusersuppress' => 'لا تقم بإنشاء تحويلات إلى الاسم الجديد',
	'renameuserreserve' => 'احفظ اسم المستخدم القديم ضد الاستخدام',
	'renameuserwarnings' => 'التحذيرات:',
	'renameuserconfirm' => 'نعم، أعد تسمية المستخدم',
	'renameusersubmit' => 'إرسال',
	'renameuser-submit-blocklog' => 'أظهر سجل المنع الخاص بالمستخدم',
	'renameusererrordoesnotexist' => 'لا يوجد مستخدم بالاسم "<nowiki>$1</nowiki>"',
	'renameusererrorexists' => 'المستخدم "<nowiki>$1</nowiki>" موجود بالفعل',
	'renameusererrorinvalid' => 'اسم المستخدم "<nowiki>$1</nowiki>" غير صحيح',
	'renameuser-error-request' => 'حدثت مشكلة أثناء استقبال الطلب.
من فضلك عد وحاول مرة ثانية.',
	'renameuser-error-same-user' => 'لا يمكنك إعادة تسمية مستخدم بنفس الاسم كما كان من قبل.',
	'renameusersuccess' => 'تمت إعادة تسمية المستخدم "<nowiki>$1</nowiki>" إلى "<nowiki>$2</nowiki>"',
	'renameuser-page-exists' => 'الصفحة $1 موجودة بالفعل ولا يمكن إنشاء أخرى مكانها أوتوماتيكيا.',
	'renameuser-page-moved' => 'تم نقل الصفحة $1 إلى $2.',
	'renameuser-page-unmoved' => 'لم يتمكن من نقل الصفحة $1 إلى $2.',
	'log-name-renameuser' => 'سجل إعادة تسمية المستخدمين',
	'log-description-renameuser' => 'هذا سجل بالتغييرات في أسماء المستخدمين.',
	'logentry-renameuser-renameuser-legacier' => '$1 أعاد تسمية $4 إلى $5',
	'renameuser-move-log' => 'نقل الصفحة تلقائيا خلال إعادة تسمية المستخدم من "[[User:$1|$1]]" إلى "[[User:$2|$2]]"',
	'action-renameuser' => 'إعادة تسمية المستخدمين',
	'right-renameuser' => 'إعادة تسمية المستخدمين',
	'renameuser-renamed-notice' => 'لقد تمت إعادة تسمية {{GENDER:$1|هذا المستخدم|هذه المستخدمة}}.
سجل إعادة التسمية معروض بالأسفل كمرجع:',
);

/** Aramaic (ܐܪܡܝܐ)
 * @author Basharh
 * @author Michaelovic
 */
$messages['arc'] = array(
	'renameuser' => 'ܬܢܝ ܫܘܡܗܐ ܕܡܦܠܚܢܐ',
	'renameuser-linkoncontribs' => 'ܬܢܝ ܫܘܡܗܐ ܕܡܦܠܚܢܐ',
	'renameuser-linkoncontribs-text' => 'ܬܢܝ ܫܘܡܗܐ ܕܗܢܐ ܡܦܠܚܢܐ',
	'renameuserold' => 'ܫܡܐ ܕܡܦܠܚܢܐ ܥܬܝܩܐ:',
	'renameusernew' => 'ܫܡܐ ܕܡܦܠܚܢܐ ܚܕܬܐ:',
	'renameuserreason' => 'ܥܠܬܐ:',
	'renameuserwarnings' => 'ܙܘܗܪ̈ܐ:',
	'renameuserconfirm' => 'ܐܝܢ، ܫܚܠܦ ܫܡܐ ܕܡܦܠܚܢܐ',
	'renameusersubmit' => 'ܫܕܪ',
	'log-name-renameuser' => 'ܣܓܠܐ ܕܬܘܢܝ ܫܘܡܗܐ ܕܡܦܠܚܢ̈ܐ',
	'logentry-renameuser-renameuser' => '$1 ܬܢܝ ܫܘܡܗܐ ܕ{{GENDER:$2|ܡܦܠܚܢܐ|ܡܦܠܚܢܬܐ}} $4 ({{PLURAL:$6|$6 ܫܘܚܠܦܐ|$6 ܫܘܚܠܦ̈ܐ}}) ܠ $5',
	'logentry-renameuser-renameuser-legacier' => '$1 ܬܢܝ ܫܘܡܗܐ ܕ $4 ܠ $5',
	'renameuser-move-log' => 'ܝܬܐܝܬ ܫܢܐ ܦܐܬܐ ܟܕ ܬܢܝ ܫܘܡܗܐ ܕܡܦܠܚܢܐ "[[User:$1|$1]]" ܠ "[[User:$2|$2]]"',
	'action-renameuser' => 'ܬܢܝ ܫܘܡܗܐ ܕܡܦܠܚܢܐ',
	'right-renameuser' => 'ܬܢܝ ܫܘܡܗܐ ܕܡܦܠܚܢܐ',
);

/** Egyptian Spoken Arabic (مصرى)
 * @author Ghaly
 * @author Meno25
 * @author Ramsis II
 */
$messages['arz'] = array(
	'renameuser' => 'تغيير تسمية يوزر',
	'renameuser-desc' => "بيضيف [[Special:Renameuser|صفحة مخصوصة]] علشان تغير اسم يوزر(محتاج صلاحية ''renameuser'')",
	'renameuserold' => 'اسم اليوزر الحالي:',
	'renameusernew' => 'اسم اليوزر الجديد:',
	'renameuserreason' => 'السبب لإعادة التسميه:', # Fuzzy
	'renameusermove' => 'انقل صفحات اليوزر و مناقشاته (بالصفحات الفرعية)للاسم الجديد.',
	'renameuserreserve' => 'احفظ اسم اليوزر القديم ضد الاستخدام',
	'renameuserwarnings' => 'التحذيرات:',
	'renameuserconfirm' => 'ايوه،سمى اليوزر دا من تاني',
	'renameusersubmit' => 'تقديم',
	'renameusererrordoesnotexist' => 'اليوزر"<nowiki>$1</nowiki>" مالوش وجود.',
	'renameusererrorexists' => 'اليوزر "<nowiki>$1</nowiki>" موجود من قبل كدا.',
	'renameusererrorinvalid' => 'اسم اليوزر "<nowiki>$1</nowiki>"مش صحيح.',
	'renameuser-error-request' => 'حصلت مشكلة فى استلام الطلب.
لو سمحت ارجع لورا و حاول تانى.',
	'renameuser-error-same-user' => 'ما ينفعش تغير اسم اليوزر لنفس الاسم من تانى.',
	'renameusersuccess' => 'اليوزر "<nowiki>$1</nowiki>" اتغير اسمه لـ"<nowiki>$2</nowiki>".',
	'renameuser-page-exists' => 'الصفحة $1 موجودة من قبل كدا و ماينفعش يتكتب عليها اوتوماتيكى.',
	'renameuser-page-moved' => 'تم نقل الصفحه $1 ل $2.',
	'renameuser-page-unmoved' => 'الصفحة $1 مانفعش تتنقل لـ$2.',
	'log-name-renameuser' => 'سجل تغيير تسمية اليوزرز',
	'renameuser-move-log' => 'الصفحة اتنقلت اوتوماتيكى لما اليوزر  "[[User:$1|$1]]" اتغير اسمه لـ "[[User:$2|$2]]"',
	'right-renameuser' => 'غير اسم اليوزرز',
);

/** Assamese (অসমীয়া)
 * @author Bishnu Saikia
 * @author Gitartha.bordoloi
 */
$messages['as'] = array(
	'renameuser' => 'ব্যৱহাৰকাৰীৰ নাম সলাওক',
	'renameuser-linkoncontribs' => 'ব্যৱহাৰীৰ নাম সলাওক',
	'renameuser-linkoncontribs-text' => 'এই ব্যৱহাৰকাৰীৰ পুনৰ্নামাকৰণ কৰক',
	'renameuser-desc' => "এজন ব্যৱহাৰকাৰীৰ পুনৰ্নামাকৰণ কৰিবলৈ এখন [[Special:Renameuser|বিশেষ পৃষ্ঠা]] যোগ দিয়ে (''renameuser'' অধিকাৰৰ প্ৰয়োজন)",
	'renameuserold' => 'বৰ্তমানৰ সদস্যনাম:',
	'renameusernew' => 'নতুন সদস্যনাম:',
	'renameuserreason' => 'কাৰণ:',
	'renameusermove' => 'সদস্যপৃষ্ঠা আৰু আলোচনা পৃষ্ঠা (আৰু সেইবোৰৰ উপপৃষ্ঠা) নতুন নামলৈ স্থানান্তৰ কৰক',
	'renameusersuppress' => 'নতুন নামলৈ পুনৰ্নিৰ্দেশ সৃষ্টি কৰিব নালাগে',
	'renameuserreserve' => 'ভৱিষ্যত ব্যৱহাৰৰ বাবে পুৰণা সদস্যনামটো বাৰণ কৰক',
	'renameuserwarnings' => 'সাৱধানবাণী:',
	'renameuserconfirm' => 'হয়, সদস্যজনৰ পুনৰ্নামাকৰণ কৰক',
	'renameusersubmit' => 'দাখিল কৰক',
	'renameuser-submit-blocklog' => 'ব্যৱহাৰকাৰীৰ প্ৰতিবন্ধক অভিলেখ দেখুৱাওক',
	'renameusererrordoesnotexist' => '"<nowiki>$1</nowiki>" নামৰ কোনো সদস্য নাই।',
	'renameusererrorexists' => '"<nowiki>$1</nowiki>" নামৰ সদস্য ইতিমধ্যে আছেই।',
	'renameusererrorinvalid' => '"<nowiki>$1</nowiki>" সদস্যনামটো অবৈধ।',
	'renameuser-error-request' => 'অনুৰোধ গ্ৰহণ কৰাত কিছু সমস্যা হৈছে।
অনুগ্ৰহ কৰি ঘূৰি গৈ পুনৰ চেষ্টা কৰক।',
	'renameuser-error-same-user' => 'আপুনি এজন সদস্যক আগৰ নামটোলৈকে নামান্তৰ কৰিব নোৱাৰে।',
	'renameusersuccess' => '"<nowiki>$1</nowiki>" সদস্যজনক "<nowiki>$2</nowiki>"লৈ নামান্তৰিত কৰা হৈছে।',
	'renameuser-page-exists' => '$1 পৃষ্ঠাখন ইতিমধ্যেই আছে আৰু তাৰ ওপৰত স্বয়ংক্ৰিয়ভাৱে লিখিব নোৱাৰি।',
	'renameuser-page-moved' => "$1 পৃষ্ঠাখন $2-লৈ স্থানান্তৰ কৰা হ'ল।",
	'renameuser-page-unmoved' => '$1 পৃষ্ঠাখন $2-লৈ স্থানান্তৰ কৰা সম্ভৱ নহয়।',
	'log-name-renameuser' => 'সদস্যৰ পুনৰ্নামাকৰণ অভিলেখ',
	'log-description-renameuser' => 'সদস্যনাম পৰিৱৰ্তনৰ অভিলেখ',
	'logentry-renameuser-renameuser-legacier' => 'সদস্য $4ৰ পৰা $5লৈ, $1’য়ে পুনৰ নামাকৰণ কৰিলে',
	'renameuser-move-log' => 'সদস্য "[[User:$1|$1]]"ক "[[User:$2|$2]]"লৈ পুনৰ্নামাকৰণ কৰোঁতে স্বয়ংক্ৰিয়ভাৱে পৃষ্ঠা স্থানান্তৰ হ\'ল।',
	'action-renameuser' => 'সদস্যৰ পুনৰ্নামাকৰণ কৰক',
	'right-renameuser' => 'সদস্যৰ পুনৰ্নামাকৰণ কৰক',
	'renameuser-renamed-notice' => "এই সদস্যজনৰ পুনৰ্নামাকৰণ কৰা হৈছে।
তথ্যসূত্ৰ হিচাপে পুনৰ্নামাকৰণ ল'গ তলত দিয়া হ'ল।",
);

/** Asturian (asturianu)
 * @author Esbardu
 * @author Xuacu
 */
$messages['ast'] = array(
	'renameuser' => 'Renomar usuariu',
	'renameuser-linkoncontribs' => 'renomar usuariu',
	'renameuser-linkoncontribs-text' => 'Renomar esti usuariu',
	'renameuser-desc' => "Renoma un usuariu (necesita'l permisu ''renameuser'')",
	'renameuserold' => "Nome d'usuariu actual:",
	'renameusernew' => "Nome d'usuariu nuevu:",
	'renameuserreason' => 'Motivu:',
	'renameusermove' => "Treslladar les páxines d'usuariu y d'alderique (y toles subpáxines) al nome nuevu",
	'renameusersuppress' => 'Nun crear redireiciones al nome nuevu',
	'renameuserreserve' => "Bloquiar el nome d'usuariu antiguu pa evitar usalu nun futuru",
	'renameuserwarnings' => 'Avisos:',
	'renameuserconfirm' => "Sí, renomar l'usuariu",
	'renameusersubmit' => 'Unviar',
	'renameuser-submit-blocklog' => 'Amosar el rexistru de bloqueos del usuariu',
	'renameusererrordoesnotexist' => 'L\'usuariu "<nowiki>$1</nowiki>" nun esiste.',
	'renameusererrorexists' => 'L\'usuariu "<nowiki>$1</nowiki>" yá esiste.',
	'renameusererrorinvalid' => 'El nome d\'usuariu "<nowiki>$1</nowiki>" nun ye válidu.',
	'renameuser-error-request' => 'Hebo un problema al recibir el pidimientu. Por favor vuelve atrás y inténtalo otra vuelta.',
	'renameuser-error-same-user' => 'Nun pues renomar un usuariu al mesmu nome que tenía.',
	'renameusersuccess' => 'L\'usuariu "<nowiki>$1</nowiki>" foi renomáu como "<nowiki>$2</nowiki>".',
	'renameuser-page-exists' => 'La páxina $1 yá esiste y nun pue ser sobreescrita automáticamente.',
	'renameuser-page-moved' => 'La páxina $1 treslladóse a $2.',
	'renameuser-page-unmoved' => 'La páxina $1 nun pudo treslladase a $2.',
	'log-name-renameuser' => "Rexistru de cambios de nome d'usuariu",
	'log-description-renameuser' => "Esti ye un rexistru de los cambios de nomes d'usuariu.",
	'logentry-renameuser-renameuser' => '$1 {{GENDER:$2|camudó de nome}} {{GENDER:$4|al usuariu|a la usuaria}} $4 ({{PLURAL:$6|$6 edición|$6 ediciones}}) a $5',
	'logentry-renameuser-renameuser-legacier' => '$1 camudó el nome {{GENDER:$4|del usuariu|de la usuaria}} $4 a $5',
	'renameuser-move-log' => 'Treslladóse la páxina automáticamente al renomar al usuariu "[[User:$1|$1]]" como "[[User:$2|$2]]"',
	'action-renameuser' => 'renomar usuarios',
	'right-renameuser' => 'Renomar usuarios',
	'renameuser-renamed-notice' => "Se renomó esti usuariu.
El rexistru de renomaos s'ufre darréu pa referencia.",
);

/** Azerbaijani (azərbaycanca)
 * @author Cekli829
 * @author Vago
 * @author Vugar 1981
 * @author Wertuose
 */
$messages['az'] = array(
	'renameuser' => 'İstifadəçi adını dəyiş',
	'renameuser-linkoncontribs' => 'istifadəçi adını dəyiş',
	'renameuser-linkoncontribs-text' => 'Bu istifadəçinin adını dəyiş',
	'renameusernew' => 'Yeni istifadəçi adı:',
	'renameuserwarnings' => 'Xəbərdarlıqlar:',
	'renameuserconfirm' => 'Bəli, istifadəçinin adını dəyiş',
	'renameusersubmit' => 'Təsdiq et',
	'renameusererrordoesnotexist' => '"<nowiki>$1</nowiki>" istifadəçi adı mövcud deyil.',
	'renameusererrorexists' => '"<nowiki>$1</nowiki>" istifadəçi adı artıq mövcuddur.',
	'renameusererrorinvalid' => '"<nowiki>$1</nowiki>" istifadəçi adı yolverilməzdir.',
	'renameuser-page-moved' => '$1 $2 səhifəsinə köçürülüb.',
	'renameuser-page-unmoved' => '$1 $2 səhifəsinə köçürülə bilinmir.',
	'log-name-renameuser' => 'İstifadəçi adı dəyişmə gündəliyi',
	'right-renameuser' => 'istifadəçilərin adını dəyiş',
);

/** South Azerbaijani (تورکجه)
 * @author Amir a57
 */
$messages['azb'] = array(
	'renameuser' => 'ایستیفاده‌چی آدینی دییش',
	'renameuser-linkoncontribs' => 'ایستیفاده‌چی آدینی دییش',
	'renameuser-linkoncontribs-text' => 'بو ایستیفاده‌چی‌نین آدینی دییش',
	'renameusernew' => 'یئنی ایستیفاده‌چی آدی:',
	'renameuserreason' => 'ندن:',
	'renameuserwarnings' => 'خبردارلیق‌لار:',
	'renameuserconfirm' => 'بلی، ایستیفاده‌چی‌نین آدینی دییش',
	'renameusersubmit' => 'گؤندر',
	'renameusererrordoesnotexist' => '"<nowiki>$1</nowiki>" ایستیفاده‌چی آدی مؤوجود دئییل.',
	'renameusererrorexists' => '"<nowiki>$1</nowiki>" ایستیفاده‌چی آدی آرتیق مؤوجوددور.',
	'renameusererrorinvalid' => '"<nowiki>$1</nowiki>" ایستیفاده‌چی آدی یولوئریلمزدیر.',
	'renameuser-page-exists' => '$1 مادده‌سی اونسوز دا وار اولماقدا‌دیر، و آوتوماتیک اولا‌راق یئنی‌دن یازیلا بیلمز.',
	'renameuser-page-moved' => '$1 صحیفه‌سی $2 صحیفه‌سینه کؤچورولوب.',
	'renameuser-page-unmoved' => '$1 صحیفه‌سی $2 صحیفه‌سینه کؤچوروله بیلینمیر.',
	'log-name-renameuser' => 'ایستیفاده‌چی آدی دییشمه گونده‌لیگی',
	'action-renameuser' => 'ایستیفاده‌چی‌لرین آدینی دییش',
	'right-renameuser' => 'ایستیفاده‌چی‌لرین آدینی دییش',
);

/** Bashkir (башҡортса)
 * @author Assele
 * @author ҒатаУлла
 */
$messages['ba'] = array(
	'renameuser' => 'Ҡатнашыусының исемен үҙгәртергә',
	'renameuser-linkoncontribs' => 'ҡатнашыусының исемен үҙгәртергә',
	'renameuser-linkoncontribs-text' => 'Был ҡатнашыусының исемен үҙгәртергә',
	'renameuser-desc' => "Ҡатнашыусы исемен үҙгәртеү өсөн [[Special:Renameuser|махсус бит]] өҫтәй (''renameuser'' хоҡуғы кәрәк)",
	'renameuserold' => 'Хәҙерге исеме:',
	'renameusernew' => 'Яңы исеме:',
	'renameuserreason' => 'Исемен үҙгәртеү сәбәбе:', # Fuzzy
	'renameusermove' => 'Шулай уҡ ҡатнашыусы битенең, фекер алышыу битенең (һәм уларҙың эске биттәренең) исемен үҙгәртергә',
	'renameusersuppress' => 'Яңы исемгә йүнәлтеүҙәр булдырмаҫҡа',
	'renameuserreserve' => 'Ҡатнашыусының элекке исемен киләсәктә ҡулланыу өсөн һаҡларға',
	'renameuserwarnings' => 'Киҫәтеүҙәр:',
	'renameuserconfirm' => 'Эйе, ҡатнашыусының исемен үҙгәртергә',
	'renameusersubmit' => 'Һаҡларға',
	'renameusererrordoesnotexist' => '"<nowiki>$1</nowiki>" исемле ҡатнашыусы теркәлмәгән.',
	'renameusererrorexists' => '"<nowiki>$1</nowiki>" исемле ҡатнашыусы теркәлгән инде.',
	'renameusererrorinvalid' => '"<nowiki>$1</nowiki>" ҡатнашыусы исеме дөрөҫ түгел.',
	'renameuser-error-request' => 'Һорауҙы алыу менән ҡыйынлыҡтар тыуҙы.
Зинһар, кире ҡайтығыҙ һәм яңынан ҡабатлап ҡарағыҙ.',
	'renameuser-error-same-user' => 'Һеҙ ҡатнашыусы исемен шул уҡ исемгә үҙгәртә алмайһығыҙ.',
	'renameusersuccess' => '"<nowiki>$1</nowiki>" ҡатнашыусыһының исеме "<nowiki>$2</nowiki>" исеменә үҙгәртелде.',
	'renameuser-page-exists' => '$1 бите бар инде һәм уның өҫтөнә автоматик рәүештә яҙҙырыу мөмкин түгел.',
	'renameuser-page-moved' => '$1 битенең исеме $2 тип үҙгәртелде.',
	'renameuser-page-unmoved' => '$1 битенең исеме $2 тип үҙгәртелә алмай.',
	'log-name-renameuser' => 'Ҡатнашыусы исемдәрен үҙгәртеү яҙмалары журналы',
	'log-description-renameuser' => 'Был — ҡатнашыусы исемдәрен үҙгәртеү яҙмалары журналы.',
	'renameuser-move-log' => 'Биттең исеме "[[User:$1|$1]]" ҡатнашыусыһының исемен "[[User:$2|$2]]" тип үҙгәртеү сәбәпле үҙенән-үҙе үҙгәргән',
	'action-renameuser' => 'Ҡатнашыусыларҙың исемен үҙгәртеү',
	'right-renameuser' => 'Ҡатнашыусыларҙың исемен үҙгәртеү',
	'renameuser-renamed-notice' => 'Был ҡатнашыусының исеме үҙгәртелгән.
Түбәндә белешмә өсөн исем үҙгәртеү яҙмалары журналы килтерелгән.',
);

/** Southern Balochi (بلوچی مکرانی)
 * @author Mostafadaneshvar
 */
$messages['bcc'] = array(
	'renameuser' => 'کاربر نامی بدل کن',
	'renameuser-desc' => "یک کاربر نامی بدیل کن(حق ''بدل نام''لازمن)",
	'renameuserold' => 'هنوکین نام کاربری:',
	'renameusernew' => 'نوکین نام کاربری:',
	'renameuserreason' => 'دلیل په نام بدل کتن:', # Fuzzy
	'renameusermove' => 'صفحات گپ و کاربر (و آیانی زیر صفحات) په نوکین نام جاه په جاه کن',
	'renameuserwarnings' => 'هوژاریان:',
	'renameuserconfirm' => 'بله، کاربر نامی عوض کن',
	'renameusersubmit' => 'دیم دی',
	'renameusererrordoesnotexist' => 'کاربر "<nowiki>$1</nowiki>" موجود نهنت.',
	'renameusererrorexists' => 'کاربر "<nowiki>$1</nowiki>" هنو هستن.',
	'renameusererrorinvalid' => 'نام کاربری "<nowiki>$1</nowiki>"  نامعتبر انت.',
	'renameuser-error-request' => 'مشکلی گون دریافت درخواست هستت.
لطفا برگردیت و دگه تلاش کنیت.',
	'renameuser-error-same-user' => 'شما نه تونیت یک کاربر په هما پیشگین چیزی نامی بدل کنیت',
	'renameusersuccess' => 'کاربر "<nowiki>$1</nowiki>" نامی بدل بوتت په "<nowiki>$2</nowiki>".',
	'renameuser-page-exists' => 'صفحه $1 الان هست و اتوماتیکی اور آی نوسیگ نه بیت.',
	'renameuser-page-moved' => 'صفحه $1 جاه په جاه بیت په $2.',
	'renameuser-page-unmoved' => 'صفحه $1 نه تونیت په $2 جاه په جاه بیت.',
	'log-name-renameuser' => 'آمار نام بدل کتن کاربر',
	'renameuser-move-log' => 'اتوماتیکی صفحه جاه په جاه بیت وهدی که کاربر نام بدل بی "[[User:$1|$1]]" به "[[User:$2|$2]]"',
	'right-renameuser' => 'عوض کتن نام کابران',
);

/** Bikol Central (Bikol Central)
 * @author Filipinayzd
 * @author Geopoet
 */
$messages['bcl'] = array(
	'renameuser' => 'Pangarani otro an paragamit',
	'renameuser-linkoncontribs' => 'pangarani otro an paragamit',
	'renameuser-linkoncontribs-text' => 'Pangarani otro ining paragamit',
	'renameuser-desc' => "Minadugang nin sarong [[Special:Renameuser|espesyal na pahina]] tanganing pangaranan otro an sarong paragamit (kaipuhan an ''renameuser'' na katanosan)",
	'renameuserold' => 'Sa ngunyan na ngaran-paragamit:',
	'renameusernew' => 'Baguhong ngaran-paragamit:',
	'renameuserreason' => 'Rason:',
	'renameusermove' => 'Ibalyo an paragamit asin mga pahina nin orolayan (asin an saindang mga sub-pahina) pasiring sa baguhong pangaran',
	'renameusersuppress' => 'Dae magmukna nin mga panlikwat pasiring sa baguhong pangaran',
	'renameuserreserve' => 'Kubkubon an lumaong ngaran-paragamit na magagamit sa paabuton',
	'renameuserwarnings' => 'Mga Patanid:',
	'renameuserconfirm' => 'Iyo, pangarani otro an paragamit',
	'renameusersubmit' => 'Isumitir',
	'renameuser-submit-blocklog' => 'Ipahiling an talaan kan kinubkob para sa paragamit',
	'renameusererrordoesnotexist' => 'An parágamit "<nowiki>$1</nowiki>" mayò man',
	'renameusererrorexists' => 'An parágamit "<nowiki>$1</nowiki>" yaon na',
	'renameusererrorinvalid' => 'An ngaran-paragamit "<nowiki>$1</nowiki>" sarong imbalido.',
	'renameuser-error-request' => 'Nagkaigwa nin sarong problema sa pagreresibe kan hinahagad.
Pakibalik tabi asin otroha giraray.',
	'renameuser-error-same-user' => 'Ika dae makakapangaran otro nin sarong paragamit na kaparehong bagay na siring sa dati.',
	'renameusersuccess' => 'An paragamit "<nowiki>$1</nowiki>" pinagngaranan otro na magin "<nowiki>$2</nowiki>".',
	'renameuser-page-exists' => 'An pahina na $1 eksistido na asin dae tabi awtomatikong masasalambawan.',
	'renameuser-page-moved' => 'An páhinang $1 pinagbalyo na sa $2.',
	'renameuser-page-unmoved' => 'An páhinang $1 dai maipagbabalyo pasiring sa $2.',
	'log-name-renameuser' => 'Talaan nin paragamit na pinagngaranan otro',
	'log-description-renameuser' => 'Iyo ini an sarong talaan kan mga kaliwatan sa mga pangaran nin paragamit.',
	'logentry-renameuser-renameuser' => '$1 {{GENDER:$2|pinagngaranan otro}} paragamit $4 ({{PLURAL:$6|$6 pagliwat|$6 mga pagliwat}}) na magin $5',
	'logentry-renameuser-renameuser-legacier' => '$1 pinagngaranan otro an paragamit na si $4 na magin $5',
	'renameuser-move-log' => 'Awtomatikong pinagbalyo an pahina mantang pinapangaranan otro an paragamit "[[User:$1|$1]]" na magin "[[User:$2|$2]]"',
	'action-renameuser' => 'pangaranan otro an mga paragamit',
	'right-renameuser' => 'Pangarani otro an mga paragamit',
	'renameuser-renamed-notice' => 'Ining paragamit pinagngaranan otro.
An talaan kan pagpangaran otrol pinagtao sa ibaba para sa reperensiya.',
);

/** Belarusian (Taraškievica orthography) (беларуская (тарашкевіца)‎)
 * @author EugeneZelenko
 * @author Jim-by
 * @author Red Winged Duck
 * @author Wizardist
 */
$messages['be-tarask'] = array(
	'renameuser' => 'Перайменаваць рахунак удзельніка',
	'renameuser-linkoncontribs' => 'перайменаваць удзельніка',
	'renameuser-linkoncontribs-text' => 'Перайменаваць рахунак гэтага ўдзельніка',
	'renameuser-desc' => "Дадае [[Special:Renameuser|спэцыяльную старонку]] для перайменаваньня рахунку ўдзельніка (неабходныя правы на ''перайменаваньне ўдзельніка'')",
	'renameuserold' => 'Цяперашняе імя ўдзельніка:',
	'renameusernew' => 'Новае імя:',
	'renameuserreason' => 'Прычына:',
	'renameusermove' => 'Перайменаваць старонкі ўдзельніка і гутарак (і іх падстаронкі)',
	'renameusersuppress' => 'Не ствараць перанакіраваньні на новую назву рахунку',
	'renameuserreserve' => 'Заблякаваць старое імя ўдзельніка для выкарыстаньня ў будучыні',
	'renameuserwarnings' => 'Папярэджаньні:',
	'renameuserconfirm' => 'Так, перайменаваць удзельніка',
	'renameusersubmit' => 'Перайменаваць',
	'renameuser-submit-blocklog' => 'Паказаць журнал блякаваньняў удзельніка',
	'renameusererrordoesnotexist' => 'Рахунак «<nowiki>$1</nowiki>» не існуе.',
	'renameusererrorexists' => 'Рахунак «<nowiki>$1</nowiki>» ужо існуе.',
	'renameusererrorinvalid' => 'Няслушнае імя ўдзельніка «<nowiki>$1</nowiki>».',
	'renameuser-error-request' => 'Узьніклі праблемы з атрыманьнем запыту.
Калі ласка, вярніцеся назад і паспрабуйце ізноў.',
	'renameuser-error-same-user' => 'Немагчыма перайменаваць рахунак удзельніка ў тое ж самае імя.',
	'renameusersuccess' => 'Рахунак «<nowiki>$1</nowiki>» быў перайменаваны ў «<nowiki>$2</nowiki>».',
	'renameuser-page-exists' => 'Старонка $1 ужо існуе і ня можа быць аўтаматычна перазапісаная.',
	'renameuser-page-moved' => 'Старонка $1 была перайменаваная ў $2.',
	'renameuser-page-unmoved' => 'Старонка $1 ня можа быць перайменаваная ў $2.',
	'log-name-renameuser' => 'Журнал перайменаваньняў удзельнікаў',
	'log-description-renameuser' => 'Гэта журнал перайменаваньняў рахункаў удзельнікаў.',
	'logentry-renameuser-renameuser' => '$1 {{GENDER:$2|перайменаваў|перайменавала}} $4 ($6 {{PLURAL:$6|праўка|праўкі|правак}}) у $5',
	'logentry-renameuser-renameuser-legacier' => '$1 перайменаваў удзельніка $4 у $5',
	'renameuser-move-log' => 'Аўтаматычнае перайменаваньне старонкі ў сувязі зь перайменаваньнем рахунку ўдзельніка з «[[User:$1|$1]]» у «[[User:$2|$2]]»',
	'action-renameuser' => 'пераймяноўваць удзельнікаў',
	'right-renameuser' => 'перайменаваньне ўдзельнікаў',
	'renameuser-renamed-notice' => '{{GENDER:$1|Гэты удзельнік быў перайменаваны|Гэтая удзельніца была перайменаваная}}.
Журнал перайменаваньняў удзельнікаў пададзены ніжэй для даведкі.',
);

/** Bulgarian (български)
 * @author Borislav
 * @author DCLXVI
 * @author Spiritia
 * @author Stanqo
 * @author Turin
 */
$messages['bg'] = array(
	'renameuser' => 'Преименуване на потребител',
	'renameuser-linkoncontribs' => 'преименуване на потребител',
	'renameuser-linkoncontribs-text' => 'Преименуване на този потребител',
	'renameuser-desc' => 'Добавя възможност за преименуване на потребители',
	'renameuserold' => 'Текущо потребителско име:',
	'renameusernew' => 'Ново потребителско име:',
	'renameuserreason' => 'Причина за преименуването:', # Fuzzy
	'renameusermove' => 'Преместване под новото име на потребителската лична страница и беседа (както и техните подстраници)',
	'renameusersuppress' => 'Без създаване на пренасочване към новото име',
	'renameuserreserve' => 'Блокиране на старото потребителско име срещу узурпация в бъдеще',
	'renameuserwarnings' => 'Предупреждения:',
	'renameuserconfirm' => 'Да, преименуване на потребителя',
	'renameusersubmit' => 'Изпълнение',
	'renameuser-submit-blocklog' => 'Показване дневника на блокиранията за потребителя',
	'renameusererrordoesnotexist' => 'Потребителят „<nowiki>$1</nowiki>“ не съществува.',
	'renameusererrorexists' => 'Потребителят „<nowiki>$1</nowiki>“ вече съществува.',
	'renameusererrorinvalid' => 'Потребителското име „<nowiki>$1</nowiki>“ е невалидно.',
	'renameuser-error-request' => 'Имаше проблем с приемането на заявката. Върнете се на предишната страница и опитайте отново!',
	'renameuser-error-same-user' => 'Новото потребителско име е същото като старото.',
	'renameusersuccess' => 'Потребителят „<nowiki>$1</nowiki>“ беше преименуван на „<nowiki>$2</nowiki>“',
	'renameuser-page-exists' => 'Страницата $1 вече съществува и не може да бъде автоматично заместена.',
	'renameuser-page-moved' => 'Страницата $1 беше преместена като $2.',
	'renameuser-page-unmoved' => 'Страницата $1 не можа да бъде преместена като $2.',
	'log-name-renameuser' => 'Дневник на преименуванията',
	'renameuser-move-log' => 'Автоматично преместена страница при преименуването на потребител "[[User:$1|$1]]" като "[[User:$2|$2]]"',
	'right-renameuser' => 'преименуване на потребители',
	'renameuser-renamed-notice' => 'Потребителят беше преименуван.
За справка по-долу е показан Дневникът на преименуванията.',
);

/** Bengali (বাংলা)
 * @author Bellayet
 * @author Nasir8891
 */
$messages['bn'] = array(
	'renameuser' => 'ব্যবহারকারী নামান্তর করো',
	'renameuser-linkoncontribs' => 'ব্যবহারকারী নামান্তর',
	'renameuser-linkoncontribs-text' => 'এই ব্যবহারকারী নামান্তর করো',
	'renameuser-desc' => "একজন ব্যবহারকারীকে নামান্তর করুন (''ব্যবহাকারী নামান্তর'' অধিকার প্রয়োজন)",
	'renameuserold' => 'বর্তমান ব্যবহারকারী নাম:',
	'renameusernew' => 'নতুন ব্যবহারকারী নাম:',
	'renameuserreason' => 'কারণ:',
	'renameusermove' => 'ব্যবহারকারী এবং আলাপের পাতা (এবং তার উপপাতাসমূহ) নতুন নামে সরিয়ে নাও',
	'renameusersuppress' => 'নতুন নামে রিডাইরেক্ট করবেন না',
	'renameuserreserve' => 'ভবিষ্যতে উদ্দেশ্যে পুরাতন ব্যবহারকারী নাম ব্লক করা হল',
	'renameuserwarnings' => 'সতর্কীকরণ:',
	'renameuserconfirm' => 'হ্যাঁ, ব্যবহারকারীর নাম পরিবর্তন করো',
	'renameusersubmit' => 'জমা দাও',
	'renameuser-submit-blocklog' => 'ব্যবহারকারীর ব্লক লগ দেখুন',
	'renameusererrordoesnotexist' => '"<nowiki>$1</nowiki>" নামের কোন ব্যবহারকারী নাই।',
	'renameusererrorexists' => '"<nowiki>$1</nowiki>" ব্যবহারকারী ইতিমধ্যে বিদ্যমান আছে।',
	'renameusererrorinvalid' => '"<nowiki>$1</nowiki>" ব্যবহারকারী নামটি ঠিক নয়।',
	'renameuser-error-request' => 'এই অনুরোধ গ্রহণে সমস্যা ছিল। দয়াকরে পেছনে যান এবং আবার চেষ্টা করুন।',
	'renameuser-error-same-user' => 'আপনি পূর্বের নামে নামান্তর করতে পারবেন না।',
	'renameusersuccess' => 'ব্যবহারকারী "<nowiki>$1</nowiki>" থেকে "<nowiki>$2</nowiki>" তে নামান্তরিত করা হয়েছে।',
	'renameuser-page-exists' => 'পাতা $1 বিদ্যমান এবং সয়ঙ্ক্রিয়ভাবে এটির উপর লেখা যাবে না',
	'renameuser-page-moved' => 'পাতাটি $1 থেকে $2 তে সরিয়ে নেওয়া হয়েছে।',
	'renameuser-page-unmoved' => 'পাতাটি $1 থেকে $2 তে সরিয়ে নেওয়া যাবে না।',
	'log-name-renameuser' => 'ব্যবহারকারী নামান্তরের লগ',
	'log-description-renameuser' => 'এটি ব্যাবহারকারী নামের পরিবর্তনের লগ',
	'renameuser-move-log' => 'যখন ব্যবহারকারী "[[User:$1|$1]]" থেকে "[[User:$2|$2]]" তে নামান্তরিত হবে তখন সয়ঙ্ক্রিয়ভাবে পাতা সরিয়ে নেওয়া হয়েছে',
	'action-renameuser' => 'ব্যবহারকারী নাম পরিবর্তন',
	'right-renameuser' => 'ব্যবহারকারীদের পুনরায় নাম দাও',
	'renameuser-renamed-notice' => 'এই ব্যবহারকারীর নাম পরিবর্তন করা হয়েছে।
সূত্র হিসাবে নিচে নাম পরিবর্তন লগ দেওয়া হল।',
);

/** Breton (brezhoneg)
 * @author Fohanno
 * @author Fulup
 * @author Gwendal
 * @author Y-M D
 */
$messages['br'] = array(
	'renameuser' => 'Adenvel an implijer',
	'renameuser-linkoncontribs' => 'adenvel an implijer',
	'renameuser-linkoncontribs-text' => 'adenvel an implijer-mañ',
	'renameuser-desc' => "Adenvel un implijer (ret eo kaout ''gwirioù adenvel'')",
	'renameuserold' => 'Anv a-vremañ an implijer :',
	'renameusernew' => 'Anv implijer nevez :',
	'renameuserreason' => 'Abeg :',
	'renameusermove' => 'Kas ar pajennoù implijer ha kaozeal (hag o ispajennoù) betek o anv nevez',
	'renameusersuppress' => 'Arabat krouiñ adkasoù war-du an anv nevez',
	'renameuserreserve' => "Mirout na vo implijet an anv kozh pelloc'h en dazont",
	'renameuserwarnings' => 'Diwallit :',
	'renameuserconfirm' => 'Ya, adenvel an implijer',
	'renameusersubmit' => 'Kas',
	'renameuser-submit-blocklog' => 'Diskwel marilh stankañ an implijer',
	'renameusererrordoesnotexist' => 'An implijer "<nowiki>$1</nowiki>" n\'eus ket anezhañ',
	'renameusererrorexists' => 'Krouet eo bet an anv implijer "<nowiki>$1</nowiki>" dija',
	'renameusererrorinvalid' => 'Faziek eo an anv implijer "<nowiki>$1</nowiki>"',
	'renameuser-error-request' => 'Ur gudenn zo bet gant degemer ar reked. Kit war-gil ha klaskit en-dro.',
	'renameuser-error-same-user' => "N'haller ket adenvel un implijer gant an hevelep anv hag a-raok.",
	'renameusersuccess' => 'Deuet eo an implijer "<nowiki>$1</nowiki>" da vezañ "<nowiki>$2</nowiki>"',
	'renameuser-page-exists' => "Bez' ez eus eus ar bajenn $1 dija, n'haller ket hec'h erlec'hiañ ent emgefreek.",
	'renameuser-page-moved' => 'Adkaset eo bet ar bajenn $1 da $2.',
	'renameuser-page-unmoved' => "N'eus ket bet gallet adkas ar bajenn $1 da $2.",
	'log-name-renameuser' => 'Roll an implijerien bet adanvet',
	'renameuser-move-log' => 'Pajenn dilec\'hiet ent emgefreek e-ser adenvel an implijer "[[User:$1|$1]]" e "[[User:$2|$2]]"',
	'action-renameuser' => 'Adenvel implijerien',
	'right-renameuser' => 'Adenvel implijerien',
	'renameuser-renamed-notice' => "Adanvet eo bet an implijer-mañ.
A-is emañ marilh an adanvadurioù, ma'z oc'h dedennet.",
);

/** Bosnian (bosanski)
 * @author CERminator
 */
$messages['bs'] = array(
	'renameuser' => 'Preimenuj korisnika',
	'renameuser-linkoncontribs' => 'preimenuj korisnika',
	'renameuser-linkoncontribs-text' => 'Preimenuj ovog korisnika',
	'renameuser-desc' => "Dodaje [[Special:Renameuser|posebnu stranicu]] u svrhu promjene imena korisnika (zahtjeva pravo ''preimenovanja korisnika'')",
	'renameuserold' => 'Trenutno ime korisnika:',
	'renameusernew' => 'Novo korisničko ime:',
	'renameuserreason' => 'Razlog promjene imena:', # Fuzzy
	'renameusermove' => 'Premještanje korisnika i njegove stranice za razgovor (zajedno sa podstranicama) na novo ime',
	'renameusersuppress' => 'Ne pravi preusmjerenja na novo ime',
	'renameuserreserve' => 'Blokiraj staro korisničko ime od kasnijeg korištenja',
	'renameuserwarnings' => 'Upozorenja:',
	'renameuserconfirm' => 'Da, promijeni ime korisnika',
	'renameusersubmit' => 'Pošalji',
	'renameusererrordoesnotexist' => 'Korisnik "<nowiki>$1</nowiki>" ne postoji.',
	'renameusererrorexists' => 'Korisnik "<nowiki>$1</nowiki>" već postoji.',
	'renameusererrorinvalid' => 'Korisničko ime "<nowiki>$1</nowiki>" nije valjano.',
	'renameuser-error-request' => 'Nastao je problem pri prijemu zahtjeva.
Molimo Vas da se vratite nazad i pokušate ponovo.',
	'renameuser-error-same-user' => 'Ne može se promijeniti ime korisnika u isto kao i ranije.',
	'renameusersuccess' => 'Ime korisnika "<nowiki>$1</nowiki>" je promijenjeno u "<nowiki>$2</nowiki>".',
	'renameuser-page-exists' => 'Stranica $1 već postoji i ne može biti automatski prepisana.',
	'renameuser-page-moved' => 'Stranica $1 je premještena na $2.',
	'renameuser-page-unmoved' => 'Stranica $1 nije mogla biti premještena na $2.',
	'log-name-renameuser' => 'Zapisnik preimenovanja korisnika',
	'renameuser-move-log' => 'Automatski premještena stranica pri promjeni korisničkog imena "[[User:$1|$1]]" u "[[User:$2|$2]]"',
	'right-renameuser' => 'Preimenovanje korisnika',
	'renameuser-renamed-notice' => 'Ovaj korisnik je promijenio ime.
Zapisnik preimenovanje je prikazan ispod kao referenca.',
);

/** Catalan (català)
 * @author Aleator
 * @author Arnaugir
 * @author El libre
 * @author Juanpabl
 * @author Paucabot
 * @author Qllach
 * @author SMP
 * @author Toniher
 * @author Vriullop
 */
$messages['ca'] = array(
	'renameuser' => "Reanomena l'usuari",
	'renameuser-linkoncontribs' => "Reanomena l'usuari/a",
	'renameuser-linkoncontribs-text' => "Canvia el nom d'aquest usuari/a",
	'renameuser-desc' => "Reanomena un usuari (necessita drets de ''renameuser'')",
	'renameuserold' => "Nom d'usuari actual:",
	'renameusernew' => "Nou nom d'usuari:",
	'renameuserreason' => 'Motiu:',
	'renameusermove' => "Reanomena la pàgina d'usuari, la de discussió i les subpàgines que tingui al nou nom",
	'renameusersuppress' => 'No creis redireccions cap al nou nom',
	'renameuserreserve' => "Bloca el nom d'usuari antic d'usos futurs",
	'renameuserwarnings' => 'Advertències:',
	'renameuserconfirm' => "Sí, reanomena l'usuari",
	'renameusersubmit' => 'Tramet',
	'renameuser-submit-blocklog' => "Mostra el registre de bloquejos per l'usuari",
	'renameusererrordoesnotexist' => "L'usuari «<nowiki>$1</nowiki>» no existeix",
	'renameusererrorexists' => "L'usuari «<nowiki>$1</nowiki>» ja existeix",
	'renameusererrorinvalid' => "El nom d'usuari «<nowiki>$1</nowiki>» no és vàlid",
	'renameuser-error-request' => "Hi ha hagut un problema en la recepció de l'ordre.
Torneu enrere i torneu-ho a intentar.",
	'renameuser-error-same-user' => 'No podeu reanomenar un usuari a un nom que ja tenia anteriorment.',
	'renameusersuccess' => "L'usuari «<nowiki>$1</nowiki>» s'ha reanomenat com a «<nowiki>$2</nowiki>»",
	'renameuser-page-exists' => 'La pàgina «$1» ja existeix i no pot ser sobreescrita automàticament',
	'renameuser-page-moved' => "La pàgina «$1» s'ha reanomenat com a «$2».",
	'renameuser-page-unmoved' => "La pàgina $1 no s'ha pogut reanomenar com a «$2».",
	'log-name-renameuser' => "Registre de canvis de nom d'usuari",
	'logentry-renameuser-renameuser' => "$1 {{GENDER:$2|ha reanomenat}} l'usuari $4 ({{PLURAL:$6|$6 edició|$6 edicions}}) a $5",
	'renameuser-move-log' => "S'ha reanomenat automàticament la pàgina mentre es reanomenava l'usuari «[[User:$1|$1]]» com «[[User:$2|$2]]»",
	'action-renameuser' => 'reanomena usuaris',
	'right-renameuser' => 'Reanomenar usuaris',
	'renameuser-renamed-notice' => "S'ha canviat el nom d'aquest usuari.
A continuació es proporciona el registre de reanomenaments per a més informació.",
);

/** Chechen (нохчийн)
 * @author Sasan700
 * @author Умар
 */
$messages['ce'] = array(
	'renameuser' => 'Декъашхочун цӀе хийца',
	'renameuser-linkoncontribs' => 'декъашхочун цӀе хийца',
	'renameuserreason' => 'Бахьан:',
	'renameusersubmit' => 'Кхочушдé',
	'renameuser-page-exists' => 'Агӏо $1 йолуш ю цундела и ша юху дӏаязъян йиш яц.',
	'renameuser-page-moved' => 'АгӀона $1 цӀе хийцина оцу $2.',
	'log-name-renameuser' => 'Декъашхойн цӀераш хийцар долу тептар',
	'renameuser-move-log' => 'Автоматически декъашхочун цӀе хийцина дела «[[User:$1|$1]]» оцу «[[User:$2|$2]]»',
	'action-renameuser' => 'декъашхойн цӀераш хийцар',
	'right-renameuser' => 'декъашхойн цӀераш хийцар',
);

/** Sorani Kurdish (کوردی)
 * @author Calak
 */
$messages['ckb'] = array(
	'renameusersubmit' => 'ناردن',
	'log-name-renameuser' => 'لۆگی گۆڕینی ناوی بەکارھێنەر',
	'logentry-renameuser-renameuser' => '$1 ناوی بەکارھێنەر $4ی ({{PLURAL:$6|$6 دەستکاری}}) {{GENDER:$2|گۆڕی}} بۆ $5',
	'right-renameuser' => 'گۆڕینی ناوی بەکارھێنەران',
);

/** Crimean Turkish (Cyrillic script) (къырымтатарджа (Кирилл)‎)
 * @author Don Alessandro
 */
$messages['crh-cyrl'] = array(
	'log-name-renameuser' => 'Къулланыджы ады денъишиклиги журналы',
);

/** Crimean Turkish (Latin script) (qırımtatarca (Latin)‎)
 * @author Don Alessandro
 */
$messages['crh-latn'] = array(
	'log-name-renameuser' => 'Qullanıcı adı deñişikligi jurnalı',
);

/** Czech (česky)
 * @author Danny B.
 * @author Li-sung
 * @author Martin Kozák
 * @author Matěj Grabovský
 * @author Mormegil
 */
$messages['cs'] = array(
	'renameuser' => 'Přejmenovat uživatele',
	'renameuser-linkoncontribs' => 'přejmenovat uživatele',
	'renameuser-linkoncontribs-text' => 'Přejmenovat tohoto uživatele',
	'renameuser-desc' => "Přejmenování uživatele (vyžadováno oprávnění ''renameuser'')",
	'renameuserold' => 'Stávající uživatelské jméno:',
	'renameusernew' => 'Nové uživatelské jméno:',
	'renameuserreason' => 'Důvod:',
	'renameusermove' => 'Přesunout uživatelské a diskusní stránky (a jejich podstránky) na nové jméno',
	'renameusersuppress' => 'Nevytvářet přesměrování na nové jméno',
	'renameuserreserve' => 'Zabránit nové registraci původního uživatelského jména',
	'renameuserwarnings' => 'Upozornění:',
	'renameuserconfirm' => 'Ano, přejmenovat uživatele',
	'renameusersubmit' => 'Přejmenovat',
	'renameuser-submit-blocklog' => 'Zobrazit knihu zablokování tohoto uživatele',
	'renameusererrordoesnotexist' => 'Uživatel se jménem „<nowiki>$1</nowiki>“ neexistuje',
	'renameusererrorexists' => 'Uživatel se jménem „<nowiki>$1</nowiki>“ již existuje',
	'renameusererrorinvalid' => 'Uživatelské jméno „<nowiki>$1</nowiki>“ nelze použít',
	'renameuser-error-request' => 'Při přijímání požadavku došlo k chybě. Vraťte se a zkuste to znovu.',
	'renameuser-error-same-user' => 'Nové uživatelské jméno je stejné jako dosavadní.',
	'renameusersuccess' => 'Uživatel „<nowiki>$1</nowiki>“ byl úspěšně přejmenován na „<nowiki>$2</nowiki>“',
	'renameuser-page-exists' => 'Stránka $1 již existuje a nelze ji automaticky přepsat.',
	'renameuser-page-moved' => 'Stránka $1 byla přesunuta na $2.',
	'renameuser-page-unmoved' => 'Stránku $1 se nepodařilo přesunout na $2.',
	'log-name-renameuser' => 'Kniha přejmenování uživatelů',
	'log-description-renameuser' => 'Toto je záznam přejmenování uživatelů (změn uživatelského jména).',
	'logentry-renameuser-renameuser' => '$1 {{GENDER:$2|přejmenoval|přejmenovala}} uživatele $4 ({{PLURAL:$6|$6 editace|$6 editace|$6 editací}}) na $5',
	'logentry-renameuser-renameuser-legacier' => '$1 přejmenoval uživatele $4 na $5',
	'renameuser-move-log' => 'Automatický přesun při přejmenování uživatele „[[User:$1|$1]]“ na „[[User:$2|$2]]“',
	'action-renameuser' => 'přejmenovávat uživatele',
	'right-renameuser' => 'Přejmenovávání uživatelů',
	'renameuser-renamed-notice' => 'Tento uživatel byl přejmenován.
Pro přehled je níže zobrazen výpis z knihy přejmenování uživatelů.',
);

/** Church Slavic (словѣ́ньскъ / ⰔⰎⰑⰂⰡⰐⰠⰔⰍⰟ)
 * @author Svetko
 * @author ОйЛ
 */
$messages['cu'] = array(
	'renameuser' => 'прѣимєноуи польꙃєватєл҄ь',
	'renameuserold' => 'нꙑнѣщьнѥѥ имѧ :',
	'renameusernew' => 'ново имѧ :',
	'renameuserreason' => 'какъ съмꙑслъ :',
	'renameusermove' => 'нарьци тако польꙃєватєлꙗ страницѫ · бєсѣдѫ и ихъ подъстраницѧ',
	'renameusersubmit' => 'єи',
	'renameusererrordoesnotexist' => 'польꙃєватєлꙗ ⁖ <nowiki>$1</nowiki> ⁖ нѣстъ',
	'renameusererrorexists' => 'польꙃєватєл҄ь ⁖ <nowiki>$1</nowiki> ⁖ ѥстъ ю',
	'renameusererrorinvalid' => 'имѧ ⁖ <nowiki>$1</nowiki> ⁖ нѣстъ годѣ',
	'log-name-renameuser' => 'польꙃєватєлъ прѣимєнованиꙗ їсторїꙗ',
	'log-description-renameuser' => 'сѥ ѥстъ їсторїꙗ польꙃєватєльскъ имєнъ иꙁмѣнѥниꙗ',
	'logentry-renameuser-renameuser' => '$1 {{GENDER:$2|нарєчє}} польꙃєватєлъ ⁖ $4 ⁖ ({{PLURAL:$6|$6 мѣна|$6 мѣни|$6 мѣнъ}}) имєньмь ⁖ $5 ⁖',
);

/** Chuvash (Чӑвашла)
 * @author FLAGELLVM DEI
 */
$messages['cv'] = array(
	'renameuserconfirm' => 'Çапла, хутшăнакан ятне улăштармалла',
	'renameuser-page-moved' => '$1 страницăн ятне $2 çине улăштарнă.',
);

/** Welsh (Cymraeg)
 * @author Lloffiwr
 */
$messages['cy'] = array(
	'renameuser' => 'Ail-enwi defnyddiwr',
	'renameuser-linkoncontribs' => "ail-enwi'r defnyddiwr",
	'renameuser-linkoncontribs-text' => "Ail-enwi'r defnyddiwr hwn",
	'renameuser-desc' => "Yn ychwanegu [[Special:Renameuser|tudalen arbennig]] er mwyn gallu ail-enwi cyfrif defnyddiwr (sydd angen y gallu ''renameuser'')",
	'renameuserold' => 'Enw presennol y defnyddiwr:',
	'renameusernew' => "Enw newydd i'r defnyddiwr:",
	'renameuserreason' => 'Rheswm:',
	'renameusermove' => "Symud y tudalennau defnyddiwr a sgwrs (ac unrhyw is-dudalennau) i'r enw newydd",
	'renameusersuppress' => "Peidiwch â gosod ailgyfeiriadau i'r enw newydd",
	'renameuserreserve' => 'Atal yr hen enw defnyddiwr rhag cael ei ddefnyddio rhagor',
	'renameuserwarnings' => 'Rhybuddion:',
	'renameuserconfirm' => "Parhau gyda'r ail-enwi",
	'renameusersubmit' => 'Anfon',
	'renameuser-submit-blocklog' => "Dangoser lòg rhwystro'r defnyddiwr",
	'renameusererrordoesnotexist' => 'Nid yw\'r defnyddiwr "<nowiki>$1</nowiki>" yn bodoli.',
	'renameusererrorexists' => 'Mae\'r defnyddiwr "<nowiki>$1</nowiki>" eisoes yn bodoli.',
	'renameusererrorinvalid' => 'Mae\'r enw defnyddiwr "<nowiki>$1</nowiki>" yn annilys',
	'renameuser-error-request' => 'Cafwyd trafferth yn derbyn y cais.
Ewch yn ôl a cheisio eto, os gwelwch yn dda.',
	'renameuser-error-same-user' => "Ni ellir ail-enwi defnyddiwr gyda'r un enw ag o'r blaen.",
	'renameusersuccess' => 'Mae\'r defnyddiwr "<nowiki>$1</nowiki>" wedi cael ei ail-enwi i "<nowiki>$2</nowiki>"',
	'renameuser-page-exists' => "Mae'r dudalen $1 ar gael yn barod ac ni ellir ei throsysgrifo.",
	'renameuser-page-moved' => 'Symudwyd $1 i $2.',
	'renameuser-page-unmoved' => 'Ni lwyddwyd i symud y dudalen $1 i $2.',
	'log-name-renameuser' => 'Lòg ail-enwi defnyddwyr',
	'log-description-renameuser' => "Dyma lòg o'r holl newidiadau i enwau defnyddwyr.",
	'logentry-renameuser-renameuser' => '{{GENDER:$2|Ailenwodd}} $1 y defnyddiwr $4 ($6 {{PLURAL:$6|golygiad|golygiad|olygiad|golygiad}}) yn $5',
	'logentry-renameuser-renameuser-legacier' => 'Ailenwodd $1 y defnyddiwr $4 yn $5',
	'renameuser-move-log' => 'Wedi symud y dudalen yn awtomatig wrth ail-enwi\'r defnyddiwr "[[User:$1|$1]]" i "[[User:$2|$2]]"',
	'action-renameuser' => 'ail-enwi defnyddwyr',
	'right-renameuser' => 'Ail-enwi defnyddwyr',
	'renameuser-renamed-notice' => "Mae'r defnyddiwr hwn wedi ei ail-enwi.
Mae'r lòg ail-enwi defnyddwyr i'w weld isod.",
);

/** Danish (dansk)
 * @author Byrial
 * @author Christian List
 * @author Froztbyte
 * @author Hylle
 * @author Peter Alberti
 */
$messages['da'] = array(
	'renameuser' => 'Omdøb bruger',
	'renameuser-linkoncontribs' => 'omdøb bruger',
	'renameuser-linkoncontribs-text' => 'Omdøb denne bruger',
	'renameuser-desc' => "Laver en [[Special:Renameuser|specialside]] til at omdøbe en bruger (kræver rettigheden ''renameuser'')",
	'renameuserold' => 'Nuværende brugernavn:',
	'renameusernew' => 'Nyt brugernavn:',
	'renameuserreason' => 'Begrundelse:',
	'renameusermove' => 'Flyt bruger- og diskussionssider (og deres undersider) til nyt navn',
	'renameusersuppress' => 'Opret ikke omdirigeringer til det nye navn',
	'renameuserreserve' => 'Bloker det gamle brugernavn fra fremtidig brug',
	'renameuserwarnings' => 'Advarsler:',
	'renameuserconfirm' => 'Ja, omdøb brugeren',
	'renameusersubmit' => 'Omdøb',
	'renameuser-submit-blocklog' => 'Vis blokeringslog for bruger',
	'renameusererrordoesnotexist' => 'Brugeren "<nowiki>$1</nowiki>" findes ikke.',
	'renameusererrorexists' => 'Brugeren "<nowiki>$1</nowiki>" findes allerede.',
	'renameusererrorinvalid' => 'Brugernavnet "<nowiki>$1</nowiki>" er ugyldigt.',
	'renameuser-error-request' => 'Det var et problem med at modtage forespørgslen.
Gå venligst tilbage og prøv igen.',
	'renameuser-error-same-user' => 'Du kan ikke omdøbe en bruger til det samme navn som før.',
	'renameusersuccess' => 'Brugeren "<nowiki>$1</nowiki>" er blevet omdøbt til "<nowiki>$2</nowiki>".',
	'renameuser-page-exists' => 'Siden $1 eksisterer allerede og kan ikke automatisk overskrives.',
	'renameuser-page-moved' => 'Siden $1 er flyttet til $2.',
	'renameuser-page-unmoved' => 'Siden $1 kunne ikke flyttes til $2.',
	'log-name-renameuser' => 'Brugeromdøbningslog',
	'log-description-renameuser' => 'Dette er en log over omdøbninger af brugernavne.',
	'logentry-renameuser-renameuser' => '$1 {{GENDER:$2|omdøbte}} bruger $4 ({{PLURAL:$6|$6 redigering|$6 redigeringer}}) til $5',
	'logentry-renameuser-renameuser-legacier' => '$1 omdøbte bruger $4 til $5',
	'renameuser-move-log' => 'Side automatisk flyttet ved omdøbning af bruger "[[User:$1|$1]]" til "[[User:$2|$2]]"',
	'action-renameuser' => 'omdøb brugere',
	'right-renameuser' => 'Omdøbe brugere',
	'renameuser-renamed-notice' => 'Denne bruger er blevet omdøbt.
Til information er omdøbningsloggen vist nedenfor.',
);

/** German (Deutsch)
 * @author Kghbln
 * @author Metalhead64
 * @author Raimond Spekking
 * @author Spacebirdy
 * @author The Evil IP address
 * @author Umherirrender
 */
$messages['de'] = array(
	'renameuser' => 'Benutzer umbenennen',
	'renameuser-linkoncontribs' => 'Benutzer umbenennen',
	'renameuser-linkoncontribs-text' => 'Diesen Benutzer umbenennen',
	'renameuser-desc' => 'Ergänzt eine [[Special:Renameuser|Spezialseite]] zum Ändern eines Benutzernamens',
	'renameuserold' => 'Bisheriger Benutzername:',
	'renameusernew' => 'Neuer Benutzername:',
	'renameuserreason' => 'Grund:',
	'renameusermove' => 'Benutzer-/Diskussionsseite (inkl. Unterseiten) auf den neuen Benutzernamen verschieben',
	'renameusersuppress' => 'Weiterleitung auf den neuen Benutzernamen unterdrücken',
	'renameuserreserve' => 'Alten Benutzernamen für eine Neuregistrierung blockieren',
	'renameuserwarnings' => 'Warnungen:',
	'renameuserconfirm' => 'Ja, Benutzer umbenennen',
	'renameusersubmit' => 'Umbenennen',
	'renameuser-submit-blocklog' => 'Benutzersperr-Logbuch zum Benutzer anzeigen',
	'renameusererrordoesnotexist' => 'Der Benutzername „<nowiki>$1</nowiki>“ ist nicht vorhanden.',
	'renameusererrorexists' => 'Der Benutzername „<nowiki>$1</nowiki>“ ist bereits vorhanden.',
	'renameusererrorinvalid' => 'Der Benutzername „<nowiki>$1</nowiki>“ ist ungültig.',
	'renameuser-error-request' => 'Es gab ein Problem beim Empfang der Anfrage.
Bitte nochmal versuchen.',
	'renameuser-error-same-user' => 'Alter und neuer Benutzername sind identisch.',
	'renameusersuccess' => 'Der Benutzer „<nowiki>$1</nowiki>“ wurde erfolgreich in „<nowiki>$2</nowiki>“ umbenannt.',
	'renameuser-page-exists' => 'Die Seite „$1“ ist bereits vorhanden und kann nicht automatisch überschrieben werden.',
	'renameuser-page-moved' => 'Die Seite „$1“ wurde nach „$2“ verschoben.',
	'renameuser-page-unmoved' => 'Die Seite „$1“ konnte nicht nach „$2“ verschoben werden.',
	'log-name-renameuser' => 'Benutzernamenänderungs-Logbuch',
	'log-description-renameuser' => 'In diesem Logbuch werden die Änderungen von Benutzernamen protokolliert.',
	'logentry-renameuser-renameuser' => '$1 {{GENDER:$2|hat}} Benutzer „$4“ (mit {{PLURAL:$6|einer Bearbeitung|$6 Bearbeitungen}}) in „$5“ umbenannt',
	'logentry-renameuser-renameuser-legacier' => '$1 hat Benutzer „$4“ in „$5“ umbenannt',
	'renameuser-move-log' => 'Seite während der Benutzerkontoumbenennung von „[[User:$1|$1]]“ in „[[User:$2|$2]]“ automatisch verschoben',
	'action-renameuser' => 'Benutzer umzubenennen',
	'right-renameuser' => 'Benutzer umbenennen',
	'renameuser-renamed-notice' => '{{GENDER:$1|Dieser Benutzer|Diese Benutzerin|Dieser Benutzer}} wurde umbenannt.
Zur Information folgt das Benutzernamenänderungs-Logbuch.',
);

/** Zazaki (Zazaki)
 * @author Aspar
 * @author Erdemaslancan
 * @author Mirzali
 * @author Xoser
 */
$messages['diq'] = array(
	'renameuser' => 'Karberi newe ra name ke',
	'renameuser-linkoncontribs' => 'karberi newe ra name ke',
	'renameuser-linkoncontribs-text' => 'Nê karberi newe ra name ke',
	'renameuser-desc' => "qey newe ra namedayişê karberi re yew [[Special:Renameuser|pelo xas]] têare keno (gani heqqê ''karberi re newe ra name bıde'' bıbo )",
	'renameuserold' => 'nameyê karberio nıkayên:',
	'renameusernew' => 'Nameyê karberio newe:',
	'renameuserreason' => 'Sebeb:',
	'renameusermove' => 'nameyê karberan u pelê werêaameyişan bıkırışi nameyo newe',
	'renameusersuppress' => 'Name de newi re hetenayışo newe vıraştış',
	'renameuserreserve' => 'nameyê karberi yo verini bloke bıker.',
	'renameuserwarnings' => 'hişyariyi',
	'renameuserconfirm' => 'bele karberi newe ra name bıker',
	'renameusersubmit' => 'bierşawê/biruşnê',
	'renameuser-submit-blocklog' => 'Rocekanê bloqandê karbari bıvin',
	'renameusererrordoesnotexist' => '"<nowiki>$1</nowiki>" no name de yew karber çino.',
	'renameusererrorexists' => '"<nowiki>$1</nowiki>" karber ca ra esto',
	'renameusererrorinvalid' => '"<nowiki>$1</nowiki>" nameyê karberi nemeqbulo',
	'renameuser-error-request' => 'ca ardışê waştışê şıma de yew problem veciya.
kerem kerê agêrê newe ra tesel bıkerê, bıcerbnê',
	'renameuser-error-same-user' => 'şıma nêşkeni nameyê karberi yo verini reyna biyarî pakerî',
	'renameusersuccess' => '"<nowiki>$1</nowiki>" rumuzê no karberi yo cıwa verın vuriya "<nowiki>$2</nowiki>" no rumuzi re.',
	'renameuser-page-exists' => '$1 pel ca ra esto newe ra ser nênusiyeno.',
	'renameuser-page-moved' => '$1 pel kırışiya no $2 pel',
	'renameuser-page-unmoved' => '$1 pel nêkırışiya no $2 pel.',
	'log-name-renameuser' => 'qeydê vuriyayişê nameyê karberi',
	'log-description-renameuser' => 'Eno yew qeydê vurnayışê nameyanê karberio.',
	'logentry-renameuser-renameuser' => '$1 {{GENDER:$2|Nameyê}} karberê $4 $5 ra ({{PLURAL:$6|$6 vurnayış|$6 vurnayışi}})',
	'logentry-renameuser-renameuser-legacier' => '$1i  $4 ra nameyê cı berd $5',
	'renameuser-move-log' => 'wexta ke karber "[[User:$1|$1]]" no name ra kırışiya "[[User:$2|$2]]" no name re ya newe ra name diyêne pel zi otomotikmen kırişiya',
	'action-renameuser' => 'karberan newe ra name ke',
	'right-renameuser' => 'Karberan newe ra name ke',
	'renameuser-renamed-notice' => 'nameyê na/no karberi/e vuriya.
qey referansi rocaneyê vuriyayişê nameyi cêr de yo.',
);

/** Lower Sorbian (dolnoserbski)
 * @author Michawiki
 */
$messages['dsb'] = array(
	'renameuser' => 'Wužywarja pśemjeniś',
	'renameuser-linkoncontribs' => 'wužywarja psemjenjowaś',
	'renameuser-linkoncontribs-text' => 'Toś togo wužywarja pśemjenjowaś',
	'renameuser-desc' => "Wužywarja pśemjeniś (pomina se pšawo ''renameuser'')",
	'renameuserold' => 'Aktualne wužywarske mě:',
	'renameusernew' => 'Nowe wužywarske mě:',
	'renameuserreason' => 'Pśicyna:',
	'renameusermove' => 'Wužywarski a diskusijny bok (a jich pódboki) do nowego mjenja pśesunuś',
	'renameusersuppress' => 'Dalejpósrědnjenja k nowemu mjenjoju njenapóraś',
	'renameuserreserve' => 'Stare wužywarske mě pśeśiwo pśichodnemu wužywanjeju blokěrowaś',
	'renameuserwarnings' => 'Warnowanja:',
	'renameuserconfirm' => 'Jo, wužywarja pśemjeniś',
	'renameusersubmit' => 'Pśemjeniś',
	'renameuser-submit-blocklog' => 'Blokěrowański protokol za wužywarja pokazaś',
	'renameusererrordoesnotexist' => 'Wužywaŕ "<nowiki>$1</nowiki>" njeeksistěrujo.',
	'renameusererrorexists' => 'Wužywaŕ "<nowiki>$1</nowiki>" južo eksistěrujo.',
	'renameusererrorinvalid' => 'Wužywarske mě "<nowiki>$1</nowiki>" jo njepłaśiwe.',
	'renameuser-error-request' => 'Problem jo pśi dostawanju napšašanja wustupił.
Źi pšosym slědk a wopytaj hyšći raz.',
	'renameuser-error-same-user' => 'Njamóžoš wužywarja do togo samogo mjenja pśemjeniś',
	'renameusersuccess' => 'Wužywaŕ "<nowiki>$1</nowiki>" jo se do "<nowiki>$2</nowiki>" pśemjenił.',
	'renameuser-page-exists' => 'Bok $1 južo eksistěrujo a njedajo se awtomatiski pśepisaś.',
	'renameuser-page-moved' => 'Bok $1 jo se do $2 pśesunuł.',
	'renameuser-page-unmoved' => 'Bok $1 njejo se do $2 pśesunuś dał.',
	'log-name-renameuser' => 'Protokol wužywarskich pśemjenjenjow',
	'log-description-renameuser' => 'Toś to jo protokol změnow na wužywarskich mjenjach.',
	'logentry-renameuser-renameuser' => '$1 jo wužywarja $4 ({{PLURAL:$6|$6 změna|$6 změnje|$6 změny|$6 změnow}}) do $5 {{GENDER:$2|pśemjenił|pśemjenił}}',
	'logentry-renameuser-renameuser-legacier' => '$1 jo wužywarja $4 do $5 pśemjenił',
	'renameuser-move-log' => 'Pśi pśemjenjowanju wužywarja "[[User:$1|$1]]" do "[[User:$2|$2]]" awtomatiski pśesunjony bok',
	'action-renameuser' => 'wužywarjow pśemjeniś',
	'right-renameuser' => 'Wužywarjow pśemjeniś',
	'renameuser-renamed-notice' => 'Toś ten wužywaŕ jo se pśemjenił.
Protokol pśemjenjowanjow jo dołojce ako referenca pódany.',
);

/** Greek (Ελληνικά)
 * @author Aitolos
 * @author Badseed
 * @author Consta
 * @author Dead3y3
 * @author Geraki
 * @author Glavkos
 * @author Kiriakos
 * @author MF-Warburg
 * @author Omnipaedista
 * @author ZaDiak
 */
$messages['el'] = array(
	'renameuser' => 'Μετονομασία χρήστη',
	'renameuser-linkoncontribs' => 'Μετονομασία χρήστη',
	'renameuser-linkoncontribs-text' => 'Μετονομασία αυτού του χρήστη',
	'renameuser-desc' => "Προσθέτει μια [[Special:Renameuser|ειδική σελίδα]] για την μετονομασία ενός χρήστη (είναι απαραίτητο το δικαίωμα ''renameuser'')",
	'renameuserold' => 'Τρέχον όνομα χρήστη:',
	'renameusernew' => 'Νέο όνομα χρήστη:',
	'renameuserreason' => 'Αιτία:',
	'renameusermove' => 'Μετακίνηση της σελίδας χρήστη και της σελίδας συζήτησης χρήστη (και των υποσελίδων τους) στο καινούργιο όνομα',
	'renameusersuppress' => 'Μην δημιουργείτε ανακατευθύνσεις στο νέο όνομα',
	'renameuserreserve' => 'Φραγή του παλιού ονόματος χρήστη/χρήστριας από μελλοντική χρήση',
	'renameuserwarnings' => 'Προειδοποιήσεις:',
	'renameuserconfirm' => 'Ναι, μετονομάστε τον χρήστη',
	'renameusersubmit' => 'Καταχώριση',
	'renameuser-submit-blocklog' => 'Εμφάνιση μητρώου φραγών του χρήστη',
	'renameusererrordoesnotexist' => 'Ο χρήστης "<nowiki>$1</nowiki>" δεν υπάρχει',
	'renameusererrorexists' => 'Ο χρήστης "<nowiki>$1</nowiki>" υπάρχει ήδη.',
	'renameusererrorinvalid' => 'Το όνομα χρήστη "<nowiki>$1</nowiki>" είναι άκυρο.',
	'renameuser-error-request' => 'Υπήρξε ένα πρόβλημα στην παραλαβή της αίτησης. Παρακαλούμε επιστρέψτε και ξαναδοκιμάστε.',
	'renameuser-error-same-user' => 'Δεν μπορείτε να μετονομάσετε έναν χρήστη σε όνομα ίδιο με το προηγούμενο.',
	'renameusersuccess' => 'Ο χρήστης ή η χρήστρια «<nowiki>$1</nowiki>» έχει μετονομαστεί σε «<nowiki>$2</nowiki>».',
	'renameuser-page-exists' => 'Η σελίδα $1 υπάρχει ήδη και δεν μπορεί να αντικατασταθεί αυτόματα.',
	'renameuser-page-moved' => 'Η σελίδα $1 μετακινήθηκε στο $2.',
	'renameuser-page-unmoved' => 'Η σελίδα $1 δεν μπόρεσε να μετακινηθεί στο $2.',
	'log-name-renameuser' => 'Αρχείο μετονομασίας χρηστών',
	'log-description-renameuser' => 'Αυτό είναι ένα αρχείο καταγραφής αλλαγών σε ονόματα χρηστών',
	'logentry-renameuser-renameuser' => '{{GENDER:$2|Ο|Η}} $1 μετονόμασε {{GENDER:$4|το χρήστη|τη χρήστρια}} $4 ({{PLURAL:$6|$6 επεξεργασία|$6 επεξεργασίες}}) σε $5',
	'logentry-renameuser-renameuser-legacier' => '{{GENDER:$2|Ο|Η}} $1 μετονόμασε {{GENDER:$4|το χρήστη|τη χρήστρια}} $4 σε $5',
	'renameuser-move-log' => 'Η σελίδα μετακινήθηκε αυτόματα κατά τη μετονομασία του χρήστη "[[User:$1|$1]]" σε "[[User:$2|$2]]"',
	'action-renameuser' => 'μετονομασία χρηστών',
	'right-renameuser' => 'Μετονομασία χρηστών',
	'renameuser-renamed-notice' => 'Αυτός ο χρήστης άλλαξε όνομα
Tο ημερολόγιο επανονομασιών δίνεται παρακάτω για αναφορά.',
);

/** Esperanto (Esperanto)
 * @author ArnoLagrange
 * @author Tlustulimu
 * @author Yekrats
 */
$messages['eo'] = array(
	'renameuser' => 'Alinomigi uzanton',
	'renameuser-linkoncontribs' => 'renomigi uzanton',
	'renameuser-linkoncontribs-text' => 'Renomigi ĉi tiun uzanton',
	'renameuser-desc' => "Aldonas [[Special:Renameuser|specialan paĝon]] por alinomigi uzanton (bezonas rajton ''renameuser'')",
	'renameuserold' => 'Aktuala salutnomo:',
	'renameusernew' => 'Nova salutnomo:',
	'renameuserreason' => 'Kialo:',
	'renameusermove' => 'Movu uzantan kaj diskutan paĝojn (kaj ties subpaĝojn) al la nova nomo',
	'renameusersuppress' => 'Ne krei alidirektilojn al la nova nomo',
	'renameuserreserve' => 'Teni la malnovan salutnomon de plua uzo',
	'renameuserwarnings' => 'Avertoj:',
	'renameuserconfirm' => 'Jes, renomigu la uzanton',
	'renameusersubmit' => 'Ek',
	'renameuser-submit-blocklog' => 'Montru forbarprotokolon de la uzulo',
	'renameusererrordoesnotexist' => 'La uzanto "<nowiki>$1</nowiki>" ne ekzistas',
	'renameusererrorexists' => 'La uzanto "<nowiki>$1</nowiki>" jam ekzistas',
	'renameusererrorinvalid' => 'La salutnomo "<nowiki>$1</nowiki>" estas malvalida',
	'renameuser-error-request' => 'Estis problemo recivante la peton.
Bonvolu retroigi kaj reprovi.',
	'renameuser-error-same-user' => 'Vi ne povas alinomigi uzanton al la sama nomo.',
	'renameusersuccess' => 'La uzanto "<nowiki>$1</nowiki>" estas alinomita al "<nowiki>$2</nowiki>"',
	'renameuser-page-exists' => 'La paĝo $1 jam ekzistas kaj ne povas esti aŭtomate anstataŭata.',
	'renameuser-page-moved' => 'La paĝo $1 estis movita al $2.',
	'renameuser-page-unmoved' => 'La paĝo $1 ne povis esti movita al $2.',
	'log-name-renameuser' => 'Protokolo pri alinomigoj de uzantoj',
	'log-description-renameuser' => 'Jen protokolo pri ŝanĝoj de salutnomoj.',
	'logentry-renameuser-renameuser' => '$1 {{GENDER:$2|alinomiĝis}} uzanton $4 ({{PLURAL:$6|$6 redakto|$6 redaktoj}}) al $5',
	'logentry-renameuser-renameuser-legacier' => '$1 alinomigis uzanton $4 al $5',
	'renameuser-move-log' => 'Aŭtomate movis paĝon dum alinomigo de la uzanto "[[User:$1|$1]]" al "[[User:$2|$2]]"',
	'action-renameuser' => 'Alinomigi uzantojn',
	'right-renameuser' => 'Alinomigi uzantojn',
	'renameuser-renamed-notice' => 'Ĉi tiu uzanto estis renomigita.
Jen la protokolo pri renomigado por via referenco.',
);

/** Spanish (español)
 * @author Alhen
 * @author Armando-Martin
 * @author Dferg
 * @author Diego Grez
 * @author Icvav
 * @author Jatrobat
 * @author Lin linao
 * @author Locos epraix
 * @author MarcoAurelio
 * @author Ralgis
 * @author Remember the dot
 * @author Sanbec
 * @author Spacebirdy
 * @author Translationista
 * @author Vivaelcelta
 */
$messages['es'] = array(
	'renameuser' => 'Cambiar el nombre de usuario',
	'renameuser-linkoncontribs' => 'cambiar el nombre de este usuario',
	'renameuser-linkoncontribs-text' => 'Cambiar el nombre de este usuario',
	'renameuser-desc' => "Añade una [[Special:Renameuser|página especial]] para cambiar de nombre a un usuario (necesita el derecho ''renameuser'')",
	'renameuserold' => 'Nombre actual:',
	'renameusernew' => 'Nuevo nombre de usuario:',
	'renameuserreason' => 'Motivo:',
	'renameusermove' => 'Trasladar las páginas de usuario y de discusión (y sus subpáginas) al nuevo nombre',
	'renameusersuppress' => 'No crear redirecciones al nuevo nombre',
	'renameuserreserve' => 'Bloquear el antiguo nombre de usuario para evitar que sea usado en el futuro',
	'renameuserwarnings' => 'Avisos:',
	'renameuserconfirm' => 'Sí, cambiar el nombre del usuario',
	'renameusersubmit' => 'Enviar',
	'renameuser-submit-blocklog' => 'Mostrar el registro de bloqueo para el usuario',
	'renameusererrordoesnotexist' => 'El usuario «<nowiki>$1</nowiki>» no existe',
	'renameusererrorexists' => 'El usuario «<nowiki>$1</nowiki>» ya existe',
	'renameusererrorinvalid' => 'El nombre de usuario «<nowiki>$1</nowiki>» no es válido',
	'renameuser-error-request' => 'Hubo un problema al recibir la solicitud.
Por favor, vuelve atrás e inténtalo de nuevo.',
	'renameuser-error-same-user' => 'No puedes renombrar a un usuario con el nombre que ya tenía.',
	'renameusersuccess' => 'El nombre de usuario «<nowiki>$1</nowiki>» ha sido modificado a «<nowiki>$2</nowiki>»',
	'renameuser-page-exists' => 'La página $1 ya existe y no puede ser reemplazada automáticamente.',
	'renameuser-page-moved' => 'La página $1 ha sido trasladada a $2.',
	'renameuser-page-unmoved' => 'La página $1 no pudo ser trasladada a $2.',
	'log-name-renameuser' => 'Registro de cambios de nombre de usuario',
	'log-description-renameuser' => 'Este es un registro de cambios en los nombres de usuario.',
	'logentry-renameuser-renameuser' => '$1 {{GENDER:$2|modificó el nombre}} del usuario $4 ({{PLURAL:$6|$6 edición|$6 ediciones}}) a $5',
	'logentry-renameuser-renameuser-legacier' => '$1 ha cambiado el nombre del usuario $4 a $5',
	'renameuser-move-log' => 'Página trasladada automáticamente al cambiar el nombre de usuario de «[[User:$1|$1]]» a «[[User:$2|$2]]»',
	'action-renameuser' => 'Cambiar el nombre de los usuarios',
	'right-renameuser' => 'Cambiar el nombre de los usuarios',
	'renameuser-renamed-notice' => 'El nombre de este usuario ha sido modificado.
El registro de cambios de nombre de usuario se provee debajo para mayor referencia.',
);

/** Estonian (eesti)
 * @author Avjoska
 * @author Jaan513
 * @author Pikne
 * @author Silvar
 * @author WikedKentaur
 */
$messages['et'] = array(
	'renameuser' => 'Kasutajanime muutmine',
	'renameuser-linkoncontribs' => 'kasutaja ümbernimetamine',
	'renameuser-linkoncontribs-text' => 'Nimeta see kasutaja ümber',
	'renameuser-desc' => "Lisab kasutajanime muutmise [[Special:Renameuser|erilehekülje]] (vajab ''renameuser''-õigust).",
	'renameuserold' => 'Praegune kasutajanimi:',
	'renameusernew' => 'Uus kasutajanimi:',
	'renameuserreason' => 'Põhjus:',
	'renameusermove' => 'Nimeta ümber kasutajaleht, aruteluleht ja nende alamlehed.',
	'renameusersuppress' => 'Ära loo ümbersuunamisi uuele nimele',
	'renameuserreserve' => 'Ära luba vana kasutajanime edaspidi kasutada',
	'renameuserwarnings' => 'Hoiatused:',
	'renameuserconfirm' => 'Jah, nimeta kasutaja ümber',
	'renameusersubmit' => 'Muuda',
	'renameuser-submit-blocklog' => 'Näita blokeerimislogi sissekandeid',
	'renameusererrordoesnotexist' => 'Kasutajat "<nowiki>$1</nowiki>" ei ole olemas.',
	'renameusererrorexists' => 'Kasutaja "<nowiki>$1</nowiki>" on juba olemas.',
	'renameusererrorinvalid' => 'Kasutajanimi "<nowiki>$1</nowiki>" on vigane.',
	'renameuser-error-request' => 'Palvet ei õnnestunud kätte saada.
Palun ürita uuesti.',
	'renameuser-error-same-user' => 'Vana ja uus nimi on samased.',
	'renameusersuccess' => 'Kasutaja "<nowiki>$1</nowiki>" uus nimi on nüüd "<nowiki>$2</nowiki>".',
	'renameuser-page-exists' => 'Lehekülg $1 on juba olemas ja seda ei saa automaatselt üle kirjutada.',
	'renameuser-page-moved' => 'Lehekülg $1 on teisaldatud pealkirja $2 alla.',
	'renameuser-page-unmoved' => 'Lehekülje $1 teisaldamine nime $2 alla ei õnnestunud.',
	'log-name-renameuser' => 'Kasutajanime muutmise logi',
	'log-description-renameuser' => 'See on kasutajanimede muutmise logi.',
	'logentry-renameuser-renameuser' => '$1 {{GENDER:$2|nimetas}} kasutaja ({{PLURAL:$6|üks redigeerimine|$6 redigeerimist}}) $4 ümber kasutajaks $5',
	'logentry-renameuser-renameuser-legacier' => '$1 nimetas kasutaja $4 ümber kasutajaks $5',
	'renameuser-move-log' => 'Teisaldatud automaatselt, kui kasutaja "[[User:$1|$1]]" nimetati ümber kasutajaks "[[User:$2|$2]]"',
	'action-renameuser' => 'kasutajaid ümber nimetadata',
	'right-renameuser' => 'Muuta kasutajanimesid',
	'renameuser-renamed-notice' => 'Kasutaja on ümbernimetatud.
Allpool on toodud ümbernimetamislogi.',
);

/** Basque (euskara)
 * @author An13sa
 * @author Theklan
 * @author Xabier Armendaritz
 */
$messages['eu'] = array(
	'renameuser' => 'Erabiltzaile bati izena aldatu',
	'renameuserold' => 'Oraingo erabiltzaile izena:',
	'renameusernew' => 'Erabiltzaile izen berria:',
	'renameuserreason' => 'Izena aldatzeko arrazoia:', # Fuzzy
	'renameuserwarnings' => 'Oharrak:',
	'renameuserconfirm' => 'Bai, lankidearen izena aldatu',
	'renameusersubmit' => 'Bidali',
	'renameusererrorexists' => '"<nowiki>$1</nowiki>" lankidea existitzen da',
	'renameusererrorinvalid' => '"<nowiki>$1</nowiki>" erabiltzaile izena okerra da',
	'renameusersuccess' => '"<nowiki>$1</nowiki>" lankidearen izen berria "<nowiki>$2</nowiki>" da',
	'renameuser-page-exists' => 'Badago $1 orrialdea, eta ezin da automatikoki gainidatzi.',
	'renameuser-page-moved' => '«$1» orria «$2» izenera aldatu da.',
	'renameuser-page-unmoved' => 'Ezin izan da $1 orrialdea $2(e)ra mugitu.',
	'log-name-renameuser' => 'Erabiltzaileen izen aldaketa erregistroa',
	'right-renameuser' => 'Lankideak berrizendatu',
);

/** Extremaduran (estremeñu)
 * @author Better
 */
$messages['ext'] = array(
	'renameuser-page-moved' => 'S´á moviu la páhina $1 a $2.',
);

/** Persian (فارسی)
 * @author Ebraminio
 * @author Huji
 * @author Reza1615
 * @author Wayiran
 */
$messages['fa'] = array(
	'renameuser' => 'تغییر نام کاربر',
	'renameuser-linkoncontribs' => 'تغییر نام کاربر',
	'renameuser-linkoncontribs-text' => 'تغییر نام کاربر',
	'renameuser-desc' => "نام یک کاربر را تغییر می‌دهد (نیازمند برخورداری از اختیارات ''تغییرنام'' است)",
	'renameuserold' => 'نام کاربری کنونی:',
	'renameusernew' => 'نام کاربری نو:',
	'renameuserreason' => 'دلیل:',
	'renameusermove' => 'صفحه‌های کاربری و بحث (به همراه زیر صفحه‌هایشان) به نام جدید منتقل کن',
	'renameusersuppress' => 'تغییرمسیر به نام جدید ایجاد نکن',
	'renameuserreserve' => 'نام کاربری قبلی را در مقابل استفادهٔ مجدد حفظ کن',
	'renameuserwarnings' => 'هشدار:',
	'renameuserconfirm' => 'بله، نام کاربر را تغییر بده',
	'renameusersubmit' => 'ارسال',
	'renameuser-submit-blocklog' => 'نمایش سیاههٔ بستن کاربر',
	'renameusererrordoesnotexist' => 'نام کاربری «<nowiki>$1</nowiki>» وجود ندارد',
	'renameusererrorexists' => 'نام کاربری «<nowiki>$1</nowiki>» استفاده شده‌است',
	'renameusererrorinvalid' => 'نام کاربری «<nowiki>$1</nowiki>» نامجاز است.',
	'renameuser-error-request' => 'در دریافت درخواست مشکلی پیش آمد. لطفاً به صفحهٔ قبل بازگردید و دوباره تلاش کنید.',
	'renameuser-error-same-user' => 'شما نمی‌توانید نام یک کاربر را به همان نام قبلی‌اش تغییر دهید.',
	'renameusersuccess' => 'نام کاربر «<nowiki>$1</nowiki>» به «<nowiki>$2</nowiki>» تغییر یافت.',
	'renameuser-page-exists' => 'صفحهٔ $1 از قبل وجود داشته و به طور خودکار قابل بازنویسی نیست.',
	'renameuser-page-moved' => 'صفحهٔ $1 به $2 انتقال داده شد.',
	'renameuser-page-unmoved' => 'امکان انتقال صفحهٔ $1 به $2 وجود ندارد.',
	'log-name-renameuser' => 'سیاهه تغییر نام کاربر',
	'log-description-renameuser' => 'این سیاههٔ تغییر نام کاربران است.',
	'logentry-renameuser-renameuser' => '$1 نام $4 ({{PLURAL:$6|$6 ویرایش|}}) را به $5 {{GENDER:$2|تغییر داد}}',
	'logentry-renameuser-renameuser-legacier' => '$1 نام کاربری $4 را به $5 تغییر داد',
	'renameuser-move-log' => 'صفحه در ضمن تغییر نام «[[User:$1|$1]]» به «[[User:$2|$2]]» به طور خودکار انتقال داده شد.',
	'action-renameuser' => 'تغییر نام کاربران',
	'right-renameuser' => 'تغییر نام کاربران',
	'renameuser-renamed-notice' => 'این کاربر تغییر نام داده‌است.
سیاهه تغییر نام در ادامه آمده است.',
);

/** Finnish (suomi)
 * @author Agony
 * @author Centerlink
 * @author Crt
 * @author Linnea
 * @author Nike
 * @author Pxos
 * @author Str4nd
 */
$messages['fi'] = array(
	'renameuser' => 'Käyttäjätunnuksen vaihto',
	'renameuser-linkoncontribs' => 'nimeä käyttäjä uudelleen',
	'renameuser-linkoncontribs-text' => 'Nimeä tämä käyttäjä uudelleen',
	'renameuser-desc' => "Mahdollistaa käyttäjän uudelleennimeämisen (vaatii ''renameuser''-oikeudet).",
	'renameuserold' => 'Nykyinen tunnus',
	'renameusernew' => 'Uusi tunnus',
	'renameuserreason' => 'Syy',
	'renameusermove' => 'Siirrä käyttäjä- ja keskustelusivut alasivuineen uudelle nimelle',
	'renameusersuppress' => 'Älä luo ohjauksia uuteen nimeen',
	'renameuserreserve' => 'Estä entinen käyttäjänimi tulevalta käytöltä',
	'renameuserwarnings' => 'Varoitukset:',
	'renameuserconfirm' => 'Kyllä, uudelleennimeä käyttäjä',
	'renameusersubmit' => 'Nimeä',
	'renameuser-submit-blocklog' => 'Näytä käyttäjän estoloki',
	'renameusererrordoesnotexist' => 'Tunnusta ”<nowiki>$1</nowiki>” ei ole',
	'renameusererrorexists' => 'Tunnus ”<nowiki>$1</nowiki>” on jo olemassa',
	'renameusererrorinvalid' => 'Tunnus ”<nowiki>$1</nowiki>” ei ole kelvollinen',
	'renameuser-error-request' => 'Pyynnön vastaanottamisessa oli ongelma. Ole hyvä ja yritä uudelleen.',
	'renameuser-error-same-user' => 'Et voi nimetä käyttäjää uudelleen samaksi kuin hän jo on.',
	'renameusersuccess' => 'Käyttäjän ”<nowiki>$1</nowiki>” tunnus on nyt ”<nowiki>$2</nowiki>”.',
	'renameuser-page-exists' => 'Sivu $1 on jo olemassa eikä sitä korvattu.',
	'renameuser-page-moved' => 'Sivu $1 siirrettiin nimelle $2.',
	'renameuser-page-unmoved' => 'Sivun $1 siirtäminen nimelle $2 ei onnistunut.',
	'log-name-renameuser' => 'Tunnusten vaihdot',
	'log-description-renameuser' => 'Tämä on loki käyttäjätunnuksien vaihdoista.',
	'logentry-renameuser-renameuser' => '$1 {{GENDER:$2|nimesi}} käyttäjän $4 ({{PLURAL:$6|$6 muokkaus|$6 muokkausta}}) uudelle nimelle $5',
	'logentry-renameuser-renameuser-legacier' => '$1 nimesi käyttäjän $4 uudelle nimelle $5',
	'renameuser-move-log' => 'Siirretty automaattisesti tunnukselta ”[[User:$1|$1]]” tunnukselle ”[[User:$2|$2]]”',
	'action-renameuser' => 'nimetä käyttäjätunnuksia uudelleen',
	'right-renameuser' => 'Nimetä käyttäjätunnuksia uudelleen',
	'renameuser-renamed-notice' => 'Tämä käyttäjä on nimetty uudelleen.
Alla on ote tunnusten vaihtolokista.',
);

/** Faroese (føroyskt)
 * @author EileenSanda
 * @author Spacebirdy
 */
$messages['fo'] = array(
	'renameuser' => 'Umdoyp brúkara',
	'renameuser-linkoncontribs' => 'umdoyp brúkara',
	'renameuser-linkoncontribs-text' => 'Umdoyp henda brúkara',
	'renameuserold' => 'Rætta brúkaranavn:',
	'renameusernew' => 'Nýtt brúkaranavn:',
	'renameuserreason' => 'Orsøk:',
	'renameuserwarnings' => 'Ávaringar:',
	'renameuserconfirm' => 'Ja, gev hesum brúkara nýtt navn',
	'renameusersubmit' => 'Send inn',
	'renameusererrordoesnotexist' => 'Brúkarin "<nowiki>$1</nowiki>" er ikki til.',
	'renameusererrorexists' => 'Brúkarin "<nowiki>$1</nowiki>" er long til.',
	'renameusererrorinvalid' => 'Brúkaranavnið "<nowiki>$1</nowiki>" er ógyldugt.',
	'renameuser-error-request' => 'Har var ein trupulleiki við at móttaka fyrispurningin.
Vinarliga far aftur og royn enn einaferð.',
	'renameuser-page-moved' => 'Síðan $1 er blivin flutt til $2.',
	'renameuser-page-unmoved' => 'Síðan $1 kundi ikki verða flutt til $2.',
	'right-renameuser' => 'Umdoyp brúkarar',
	'renameuser-renamed-notice' => 'Hesin brúkari hevur fingið nýtt navn.
Loggurin fyri navnabroytingina er givin niðanfyri fyri keldu ávísing.',
);

/** French (français)
 * @author Cedric31
 * @author Crochet.david
 * @author DavidL
 * @author Gomoko
 * @author Grondin
 * @author Hégésippe Cormier
 * @author IAlex
 * @author Nicolas NALLET
 * @author Peter17
 * @author PieRRoMaN
 * @author Urhixidur
 * @author Verdy p
 */
$messages['fr'] = array(
	'renameuser' => 'Renommer l’utilisateur',
	'renameuser-linkoncontribs' => 'renommer l’utilisateur',
	'renameuser-linkoncontribs-text' => 'Renommer cet utilisateur',
	'renameuser-desc' => "Renomme un utilisateur (nécessite les droits de ''renameuser'')",
	'renameuserold' => 'Nom actuel de l’utilisateur :',
	'renameusernew' => 'Nouveau nom de l’utilisateur :',
	'renameuserreason' => 'Raison(s) du changement de nom :',
	'renameusermove' => 'Renommer toutes les pages de l’utilisateur vers le nouveau nom',
	'renameusersuppress' => 'Ne pas créer de redirection vers le nouveau nom',
	'renameuserreserve' => 'Réserver l’ancien nom pour un usage futur',
	'renameuserwarnings' => 'Avertissements :',
	'renameuserconfirm' => 'Oui, renommer l’utilisateur',
	'renameusersubmit' => 'Soumettre',
	'renameuser-submit-blocklog' => "Afficher le journal de blocage de l'utilisateur",
	'renameusererrordoesnotexist' => 'L’utilisateur « <nowiki>$1</nowiki> » n’existe pas',
	'renameusererrorexists' => 'L’utilisateur « <nowiki>$1</nowiki> » existe déjà',
	'renameusererrorinvalid' => 'Le nom d’utilisateur « <nowiki>$1</nowiki> » n’est pas valide',
	'renameuser-error-request' => 'Un problème existe avec la réception de la requête. Revenez en arrière et essayez à nouveau.',
	'renameuser-error-same-user' => 'Vous ne pouvez pas renommer un utilisateur du même nom qu’auparavant.',
	'renameusersuccess' => 'L’utilisateur « <nowiki>$1</nowiki> » a été renommé « <nowiki>$2</nowiki> »',
	'renameuser-page-exists' => 'La page $1 existe déjà et ne peut pas être automatiquement remplacée.',
	'renameuser-page-moved' => 'La page $1 a été déplacée vers $2.',
	'renameuser-page-unmoved' => 'La page $1 ne peut pas être renommée en $2.',
	'log-name-renameuser' => 'Journal des changements de noms d’utilisateurs',
	'log-description-renameuser' => "Ceci est l'historique des modifications des noms d'utilisateur.",
	'logentry-renameuser-renameuser' => "$1 {{GENDER:$2|a renommé}} l'utilisateur $4 ({{PLURAL:$6|$6 modification|$6 modifications}}) en $5",
	'logentry-renameuser-renameuser-legacier' => "$1 a renommé l'utilisateur $4 en $5",
	'renameuser-move-log' => 'Page déplacée automatiquement lorsque l’utilisateur « [[User:$1|$1]] » est devenu « [[User:$2|$2]] »',
	'action-renameuser' => 'renommer les utilisateurs',
	'right-renameuser' => 'Renommer les utilisateurs',
	'renameuser-renamed-notice' => 'Cet utilisateur a été renommé.
Le journal des renommages est disponible ci-dessous pour information.',
);

/** Franco-Provençal (arpetan)
 * @author ChrisPtDe
 */
$messages['frp'] = array(
	'renameuser' => 'Renomar l’usanciér',
	'renameuser-linkoncontribs' => 'renomar l’usanciér',
	'renameuser-linkoncontribs-text' => 'Renomar ceti usanciér',
	'renameuser-desc' => "Apond una [[Special:Renameuser|pâge spèciâla]] por renomar un usanciér (at fôta des drêts de ''renameuser'').",
	'renameuserold' => 'Nom d’ora a l’usanciér :',
	'renameusernew' => 'Novél nom a l’usanciér :',
	'renameuserreason' => 'Rêson du changement de nom :', # Fuzzy
	'renameusermove' => 'Renomar totes les pâges a l’usanciér vers lo novél nom',
	'renameusersuppress' => 'Pas fâre de redirèccion de vers lo novél nom',
	'renameuserreserve' => 'Resèrvar lo viely nom por un usâjo a vegnir',
	'renameuserwarnings' => 'Avèrtissements :',
	'renameuserconfirm' => 'Ouè, renomar l’usanciér',
	'renameusersubmit' => 'Sometre',
	'renameusererrordoesnotexist' => 'L’usanciér « <nowiki>$1</nowiki> » ègziste pas.',
	'renameusererrorexists' => 'L’usanciér « <nowiki>$1</nowiki> » ègziste ja.',
	'renameusererrorinvalid' => 'Lo nom d’usanciér « <nowiki>$1</nowiki> » est envalido.',
	'renameuser-error-request' => 'Un problèmo ègziste avouéc la reçua de la requéta.
Volyéd tornar arriér et pués tornar èprovar.',
	'renameuser-error-same-user' => 'Vos pouede pas renomar un usanciér du mémo nom que dês devant.',
	'renameusersuccess' => 'L’usanciér « <nowiki>$1</nowiki> » at étâ renomâ en « <nowiki>$2</nowiki> ».',
	'renameuser-page-exists' => 'La pâge $1 ègziste ja et pôt pas étre remplaciê ôtomaticament.',
	'renameuser-page-moved' => 'La pâge $1 at étâ dèplaciê vers $2.',
	'renameuser-page-unmoved' => 'La pâge $1 pôt pas étre renomâ en $2.',
	'log-name-renameuser' => 'Jornal des changements de nom d’usanciér',
	'renameuser-move-log' => 'Pâge dèplaciê ôtomaticament quand l’usanciér « [[User:$1|$1]] » est vegnu « [[User:$2|$2]] »',
	'action-renameuser' => 'renomar los utilisators',
	'right-renameuser' => 'Renomar des usanciérs',
	'renameuser-renamed-notice' => 'Ceti usanciér at étâ renomâ.
Lo jornal des changements de nom est disponiblo ce-desot por enformacion.',
);

/** Northern Frisian (Nordfriisk)
 * @author Murma174
 */
$messages['frr'] = array(
	'renameuser' => 'Brüker amnääm',
	'renameuser-linkoncontribs' => 'Brüker amnääm',
	'renameuser-linkoncontribs-text' => 'Didiar brüker amnääm',
	'renameuser-desc' => 'Diar komt en [[Special:Renameuser|spezial-sidj]] tu, am en brükernööm tu feranrin',
	'renameuserold' => 'Uugenblakelk brükernööm:',
	'renameusernew' => 'Nei brükernööm:',
	'renameuserreason' => 'Grünj:',
	'renameusermove' => 'Fersküüw brükersidj an diskusjuunssidj (mä onersidjen) tu di nei brükernööm',
	'renameusersuppress' => 'Nian widjerfeerangen üüb di nei brükernööm iinracht',
	'renameuserreserve' => 'Di ual brükernööm spere',
	'renameuserwarnings' => 'Wäärnangen:',
	'renameuserconfirm' => 'Ja, di brüker amnääm',
	'renameusersubmit' => 'Auerdreeg',
	'renameuser-submit-blocklog' => 'Sper-logbuk för didiar brüker uunwise',
	'renameusererrordoesnotexist' => 'Son brüker "<nowiki>$1</nowiki>" jaft at ei.',
	'renameusererrorexists' => 'Son brüker "<nowiki>$1</nowiki>" jaft at al.',
	'renameusererrorinvalid' => 'Di brükernööm "<nowiki>$1</nowiki>" as ferkiard.',
	'renameuser-error-request' => "Diar as wat skiaf gingen bi't aurdreegen. Ferschük det man noch ans.",
	'renameuser-error-same-user' => 'Di nei an di ual brükernööm san likedenang.',
	'renameusersuccess' => 'Di brüker "<nowiki>$1</nowiki>" as tu "<nowiki>$2</nowiki>" amnäämd wurden.',
	'renameuser-page-exists' => 'Det sidj „$1“ as al diar an koon ei automaatisk auerskrewen wurd.',
	'renameuser-page-moved' => 'Det sidj $1 as efter $2 fersköwen wurden.',
	'renameuser-page-unmoved' => 'Det sidj $1 küd ei efter $2 fersköwen wurd.',
	'log-name-renameuser' => 'Amnääm-logbuk',
	'log-description-renameuser' => 'Det as det logbuk auer feranrangen faan brükernöömer.',
	'logentry-renameuser-renameuser' => '$1 {{GENDER:$2|hää}} brüker „$4“ (mä {{PLURAL:$6|ian feranrang|$6 feranrangen}}) tu „$5“ amnäämd.',
	'logentry-renameuser-renameuser-legacier' => '$1 hää brüker $4 amnäämd tu $5',
	'renameuser-move-log' => "Det sidj as bi't amnäämen faan „[[User:$1|$1]]“ tu „[[User:$2|$2]]“ automaatisk fersköwen wurden",
	'action-renameuser' => 'brükern amnääm',
	'right-renameuser' => 'Brükern amnääm',
	'renameuser-renamed-notice' => "Didiar brüker as amnäämd wurden. Uun't amnääm-logbuk oner stäänt muar diartu.",
);

/** Friulian (furlan)
 * @author Klenje
 */
$messages['fur'] = array(
	'renameuser' => 'Cambie non par un utent',
	'renameuserold' => 'Non utent atuâl:',
	'renameusernew' => 'Gnûf non utent:',
	'renameuserwarnings' => 'Avîs:',
);

/** Western Frisian (Frysk)
 * @author SK-luuut
 * @author Snakesteuben
 */
$messages['fy'] = array(
	'renameuser' => 'Feroarje in meidochnamme',
	'renameuser-desc' => "Foeget in [[Special:Renameuser|spesiale side]] ta om in meidoggersnamme te feroarjen (jo hawwe hjirfoar it ''renameuser'' rjocht nedich)",
	'renameuserold' => 'Alde namme:',
	'renameusernew' => 'Nije namme:',
	'renameuserreason' => 'Reden foar nammewiziging:', # Fuzzy
	'renameusermove' => 'Werneam meidogger en oerlis siden (mei ûnderlizzende siden) nei de nije namme',
	'renameuserreserve' => 'Takomst brûken fan de âlde meidoggersnamme foarkomme',
	'renameuserwarnings' => 'Warskôgings:',
	'renameuserconfirm' => 'Ja, feroarje de namme fan de meidogger',
	'renameusersubmit' => 'Feroarje',
	'renameusererrordoesnotexist' => 'Der is gjin meidogger mei de namme "<nowiki>$1</nowiki>"',
	'renameusererrorexists' => 'De meidochnamme "<nowiki>$1</nowiki>" wurdt al brûkt.',
	'renameusererrorinvalid' => 'De meidochnamme "<nowiki>$1</nowiki>" mei net.',
	'renameuser-error-request' => "Der wie in probleem mei it ferwurkjen fan de oanfraach.
Gean tebek en probearje it asjebleaft op 'e nij.",
	'renameuser-error-same-user' => 'Jo kinne in meidoggersnamme net nei deselde namme feroarje.',
	'renameusersuccess' => 'Meidogger "<nowiki>$1</nowiki>" is no meidogger "<nowiki>$2</nowiki>".',
	'renameuser-page-exists' => 'De side $1 bestiet al en kin net automatysk oerskreaun wurde.',
	'renameuser-page-moved' => 'Sidenamme $1 is feroare yn $2.',
	'renameuser-page-unmoved' => 'Sidenamme $1 koe net feroare wurde yn $2.',
	'log-name-renameuser' => 'Nammeferoar-loch',
	'renameuser-move-log' => 'Sidenamme automatysk feroare by it feroarjen fan de meidoggersnamme fan  "[[User:$1|$1]]" yn "[[User:$2|$2]]"',
	'right-renameuser' => 'Feroarje meidoggersnammen',
);

/** Irish (Gaeilge)
 * @author Alison
 */
$messages['ga'] = array(
	'renameuser' => 'Athainmnigh úsáideoir',
	'renameuserold' => 'Ainm reatha úsáideora:',
	'renameusernew' => 'Ainm nua úsáideora:',
	'renameusersuccess' => 'Athainmníodh úsáideoir "<nowiki>$1</nowiki>" mar "<nowiki>$2</nowiki>"',
	'renameuser-page-exists' => 'Tá leathanach "$1" ann chean féin; ní féidir ábhar a scríobh thairis go huathoibríoch.',
);

/** Galician (galego)
 * @author Alma
 * @author Prevert
 * @author Toliño
 */
$messages['gl'] = array(
	'renameuser' => 'Mudar o nome do usuario',
	'renameuser-linkoncontribs' => 'cambiar o nome do usuario',
	'renameuser-linkoncontribs-text' => 'Cambiar o nome deste usuario',
	'renameuser-desc' => "Engade unha [[Special:Renameuser|páxina especial]] para renomear un usuario (precisa dereitos de ''renomear usuarios'')",
	'renameuserold' => 'Nome de usuario actual:',
	'renameusernew' => 'Novo nome de usuario:',
	'renameuserreason' => 'Motivo:',
	'renameusermove' => 'Mover as páxinas de usuario e de conversa (xunto coas subpáxinas) ao novo nome',
	'renameusersuppress' => 'Non crear a redirección cara ao novo nome',
	'renameuserreserve' => 'Reservar o nome de usuario vello para un uso posterior',
	'renameuserwarnings' => 'Avisos:',
	'renameuserconfirm' => 'Si, renomear este usuario',
	'renameusersubmit' => 'Enviar',
	'renameuser-submit-blocklog' => 'Mostrar o rexistro de bloqueos do usuario',
	'renameusererrordoesnotexist' => 'O usuario "<nowiki>$1</nowiki>" non existe.',
	'renameusererrorexists' => 'O usuario "<nowiki>$1</nowiki>" xa existe.',
	'renameusererrorinvalid' => 'O nome de usuario "<nowiki>$1</nowiki>" non é válido.',
	'renameuser-error-request' => 'Houbo un problema coa recepción da solicitude.
Volva atrás e inténteo de novo.',
	'renameuser-error-same-user' => 'Non pode mudar o nome dun usuario ao mesmo nome que tiña antes.',
	'renameusersuccess' => 'O nome de usuario de "<nowiki>$1</nowiki>" cambiou a "<nowiki>$2</nowiki>".',
	'renameuser-page-exists' => 'A páxina "$1" xa existe e non pode ser sobrescrita automaticamente.',
	'renameuser-page-moved' => 'A páxina "$1" foi movida a "$2".',
	'renameuser-page-unmoved' => 'A páxina "$1" non pode ser movida a "$2".',
	'log-name-renameuser' => 'Rexistro de cambios de nome de usuario',
	'log-description-renameuser' => 'Este é un rexistro dos cambios nos nomes de usuario.',
	'logentry-renameuser-renameuser' => '$1 {{GENDER:$2|mudou o nome}} do usuario $4 ({{PLURAL:$6|$6 edición|$6 edicións}}) a $5',
	'logentry-renameuser-renameuser-legacier' => '$1 mudou o nome do usuario $4 a $5',
	'renameuser-move-log' => 'A páxina moveuse automaticamente cando se mudou o nome do usuario "[[User:$1|$1]]" a "[[User:$2|$2]]"',
	'action-renameuser' => 'renomear usuarios',
	'right-renameuser' => 'Renomear usuarios',
	'renameuser-renamed-notice' => 'Este usuario foi renomeado.
Velaquí está o rexistro de cambios de nome de usuario por se quere consultalo.',
);

/** Ancient Greek (Ἀρχαία ἑλληνικὴ)
 * @author Omnipaedista
 */
$messages['grc'] = array(
	'renameusersubmit' => 'Ὑποβάλλειν',
);

/** Swiss German (Alemannisch)
 * @author Als-Chlämens
 * @author Als-Holder
 */
$messages['gsw'] = array(
	'renameuser' => 'Benutzer umnänne',
	'renameuser-linkoncontribs' => 'Benutzer umnänne',
	'renameuser-linkoncontribs-text' => 'Dää Benutzer umnänne',
	'renameuser-desc' => "Ergänzt e [[Special:Renameuser|Spezialsyte]] fir d Umnännig vun eme Benutzer (brucht s ''renameuser''-Rächt)",
	'renameuserold' => 'Bishärige Benutzername:',
	'renameusernew' => 'Neije Benutzername:',
	'renameuserreason' => 'Grund:', # Fuzzy
	'renameusermove' => 'Verschieb Benutzer-/Diskussionssyte mit Untersyte uf dr neij Benutzername',
	'renameusersuppress' => 'Kei Wyterleitig uf dr nej Benutzername aalege',
	'renameuserreserve' => 'Blockier dr alt Benutzername fir e Neijregischtrierig',
	'renameuserwarnings' => 'Warnige:',
	'renameuserconfirm' => 'Jo, Benutzer umnänne',
	'renameusersubmit' => 'Umnänne',
	'renameuser-submit-blocklog' => 'Benutzersperrlogbuech vo däm Benutzer aazeige',
	'renameusererrordoesnotexist' => 'Dr Benutzername „<nowiki>$1</nowiki>“ git s nit.',
	'renameusererrorexists' => 'Dr Benutzername „<nowiki>$1</nowiki>“ git s scho.',
	'renameusererrorinvalid' => 'Dr Benutzername „<nowiki>$1</nowiki>“ isch uugiltig.',
	'renameuser-error-request' => 'S het e Probläm bim Empfang vu dr Aafrog gee. Bitte nomol versueche.',
	'renameuser-error-same-user' => 'Dr alt und dr neij Benutzername sin identisch.',
	'renameusersuccess' => 'Dr Benutzer „<nowiki>$1</nowiki>“ isch mit Erfolg in „<nowiki>$2</nowiki>“ umgnännt wore.',
	'renameuser-page-exists' => 'D Syte $1 git s scho un cha nit automatisch iberschribe wäre.',
	'renameuser-page-moved' => 'D Syte $1 isch noch $2 verschobe wore.',
	'renameuser-page-unmoved' => 'D Syte $1 het nit chenne noch $2 verschobe wäre.',
	'log-name-renameuser' => 'Benutzernamenänderigs-Logbuech',
	'log-description-renameuser' => 'In däm Logbuech wäre d Änderige vu Benutzernäme protokolliert.',
	'renameuser-move-log' => 'dur d Umnännig vu „[[User:$1|$1]]“ noch „[[User:$2|$2]]“ automatisch verschobeni Syte',
	'action-renameuser' => 'Benutzer umznänne',
	'right-renameuser' => 'Benutzer umnänne',
	'renameuser-renamed-notice' => 'Dää Benutzer isch umgnännt wore.
S Umnännigs-Logbuech wird do unte ufgfiert as Quälle.',
);

/** Gujarati (ગુજરાતી)
 * @author Ashok modhvadia
 * @author KartikMistry
 * @author Sushant savla
 */
$messages['gu'] = array(
	'renameuser' => 'સભ્યનામ બદલો',
	'renameuser-linkoncontribs' => 'સભ્યનામ બદલો',
	'renameuser-linkoncontribs-text' => 'આ સભ્યનું નામ બદલો',
	'renameuser-desc' => "સભ્યનું નામાંતરણ કરવા માટે [[Special:Renameuser|special page]] ઉમેરે છે (''renameuser'' હક્ક જરૂરી)",
	'renameuserold' => 'હાલનું સભ્યનામ:',
	'renameusernew' => 'નવું સભ્યનામ:',
	'renameuserreason' => 'કારણ:',
	'renameusermove' => 'સભ્ય અને ગપ્પાં પાનાંઓ (અને તેમનાં ઉપપાનાંઓ) નવાં નામ પર ખસેડો',
	'renameusersuppress' => 'નવા નામ પર દિશા નિર્દેશનો ન રચશો',
	'renameuserreserve' => 'જૂના સભ્યનામને ભવિષ્યનો વપરાશ પ્રતિબંધીત કરો',
	'renameuserwarnings' => 'ચેતવણીઓ:',
	'renameuserconfirm' => 'હા, સભ્યનું નામ બદલો',
	'renameusersubmit' => 'જમા કરો',
	'renameuser-submit-blocklog' => 'સભ્ય માટે પ્રતિબંધ લૉગ બતાવો',
	'renameusererrordoesnotexist' => 'આ સભ્ય  "<nowiki>$1</nowiki>" મોજૂદ નથી.',
	'renameusererrorexists' => 'આ સભ્ય  "<nowiki>$1</nowiki>" પહેલેથી હાજર છે.',
	'renameusererrorinvalid' => 'સભ્યનામ "<nowiki>$1</nowiki>" અયોગ્ય છે.',
	'renameuser-error-request' => 'તમારી અરજી પ્રાપ્ત કરતાં કાંઈ ત્રુટી થઈ
મહેરબાની કરી ફરી પ્રયત્ન કરશો',
	'renameuser-error-same-user' => 'તમે સભ્યને ફરીથી પહેલાનું નામ આપી શકશો નહી.',
	'renameusersuccess' => 'સભ્ય "<nowiki>$1</nowiki>" નું નામ બદલીને "<nowiki>$2</nowiki>" કરાયું છે.',
	'renameuser-page-exists' => 'પાનું  $1 પહેલેથી અસ્તિત્વમાં છે તેના પર સ્વયંચલિત નવું લેખન ન થાય.',
	'renameuser-page-moved' => 'પાના $1 ને $2 પર ખસેડાયું',
	'renameuser-page-unmoved' => 'પાના $1ને $2 પર ન લઈ જઈ શકાયું',
	'log-name-renameuser' => 'સભ્ય નામફેરનો લોગ',
	'renameuser-move-log' => 'સભ્ય "[[User:$1|$1]]" થી "[[User:$2|$2]]" નામ બદલતી વખતે આપમેળે પાનું ખસેડ્યું',
	'action-renameuser' => 'સભ્યોનાં નામ બદલો',
	'right-renameuser' => 'સભ્યોના નામ બદલો',
	'renameuser-renamed-notice' => 'આ સભ્યનું નામ પરિવર્તન થયું છે.
નામ પરિવર્તન લોગ તમારા સંદર્ભ માટે અહીં આપેલ છે',
);

/** Hebrew (עברית)
 * @author Amire80
 * @author Ofekalef
 * @author Rotem Liss
 * @author Rotemliss
 * @author YaronSh
 * @author ערן
 */
$messages['he'] = array(
	'renameuser' => 'שינוי שם משתמש',
	'renameuser-linkoncontribs' => 'שינוי שם משתמש',
	'renameuser-linkoncontribs-text' => 'שינוי שם המשתמש הזה',
	'renameuser-desc' => "הוספת [[Special:Renameuser|דף מיוחד]] לשינוי שם משתמש (דרושה הרשאת ''renameuser'')",
	'renameuserold' => 'שם משתמש נוכחי:',
	'renameusernew' => 'שם משתמש חדש:',
	'renameuserreason' => 'סיבה:',
	'renameusermove' => 'העברת דפי המשתמש והשיחה (כולל דפי המשנה שלהם) לשם החדש',
	'renameusersuppress' => 'לא ליצור הפניות לשם החדש',
	'renameuserreserve' => 'חסימת שם המשתמש הישן לשימוש נוסף',
	'renameuserwarnings' => 'אזהרות:',
	'renameuserconfirm' => 'כן, לשנות את שם המשתמש',
	'renameusersubmit' => 'שינוי שם משתמש',
	'renameuser-submit-blocklog' => 'הצגת יומן החסימות של המשתמש',
	'renameusererrordoesnotexist' => 'המשתמש "<nowiki>$1</nowiki>" אינו קיים.',
	'renameusererrorexists' => 'המשתמש "<nowiki>$1</nowiki>" כבר קיים.',
	'renameusererrorinvalid' => 'שם המשתמש "<nowiki>$1</nowiki>" אינו תקין.',
	'renameuser-error-request' => 'הייתה בעיה בקבלת הבקשה. אנא חזרו לדף הקודם ונסו שנית.',
	'renameuser-error-same-user' => 'אינכם יכולים לשנות את שם המשתמש לשם זהה לשמו הישן.',
	'renameusersuccess' => 'שם המשתמש של "<nowiki>$1</nowiki>" שונה ל"<nowiki>$2</nowiki>".',
	'renameuser-page-exists' => 'הדף $1 כבר קיים ולא ניתן לדרוס אותו אוטומטית.',
	'renameuser-page-moved' => 'הדף $1 הועבר לשם $2.',
	'renameuser-page-unmoved' => 'לא ניתן היה להעביר את הדף $1 ל$2.',
	'log-name-renameuser' => 'יומן שינויי שמות משתמש',
	'log-description-renameuser' => 'זהו יומן השינויים בשמות המשתמשים.',
	'logentry-renameuser-renameuser' => '$1 {{GENDER:$2|שינה|שינתה}} את שם המשתמש $4 &rlm;({{PLURAL:$6|עריכה אחת|$6 עריכות}}) אל $5',
	'logentry-renameuser-renameuser-legacier' => '$1 {{GENDER:$2|שינה|שינתה}} את שם המשתמש $4 ל{{GRAMMAR:תחילית|$5}}',
	'renameuser-move-log' => 'העברה אוטומטית בעקבות שינוי שם המשתמש "[[User:$1|$1]]" ל־"[[User:$2|$2]]"',
	'action-renameuser' => 'לשנות שמות משתמש',
	'right-renameuser' => 'שינוי שמות משתמש',
	'renameuser-renamed-notice' => 'שם המשתמש הזה שונה.
יומן שינויי שמות המשתמש מוצג להלן.',
);

/** Hindi (हिन्दी)
 * @author Ansumang
 * @author Kaustubh
 * @author Siddhartha Ghai
 */
$messages['hi'] = array(
	'renameuser' => 'सदस्यनाम बदलें',
	'renameuser-linkoncontribs' => 'सदस्यनाम बदलें',
	'renameuser-linkoncontribs-text' => 'इस सदस्य का नाम बदलें',
	'renameuser-desc' => "सदस्यनाम बदलने के लिए एक [[Special:Renameuser|विशेष पृष्ठ]] जोड़ता है (''renameuser'' अधिकार आवश्यक)",
	'renameuserold' => 'सद्य सदस्यनाम:',
	'renameusernew' => 'नया सदस्यनाम:',
	'renameuserreason' => 'कारण:',
	'renameusermove' => 'सदस्य पृष्ठ और वार्ता पृष्ठ (और उनके उपपृष्ठ) नये नाम पर स्थानांतरित करें',
	'renameusersuppress' => 'नए नाम को अनुप्रेषित ना करें',
	'renameuserreserve' => 'पुरान सदस्यनाम भविष्य में प्रयोग से अवरोधित करें',
	'renameuserwarnings' => 'चेतावनी:',
	'renameuserconfirm' => 'हाँ, सदस्य का नाम बदलें',
	'renameusersubmit' => 'जमा करें',
	'renameuser-submit-blocklog' => 'सदस्य का ब्लॉक लॉग दिखाएँ',
	'renameusererrordoesnotexist' => 'सदस्य "<nowiki>$1</nowiki>" मौजूद नहीं है।',
	'renameusererrorexists' => 'सदस्य "<nowiki>$1</nowiki>" पहले से मौजूद है।',
	'renameusererrorinvalid' => 'सदस्यनाम "<nowiki>$1</nowiki>" अमान्य है।',
	'renameuser-error-request' => 'अनुरोध पाने में समस्या आई है।
कृपया वापिस जाकर पुनः यत्न करें।',
	'renameuser-error-same-user' => 'आप सदस्यनाम को उसी नाम से नहीं बदल सकते हैं।',
	'renameusersuccess' => '"<nowiki>$1</nowiki>" का सदस्यनाम "<nowiki>$2</nowiki>" कर दिया गया है।',
	'renameuser-page-exists' => '$1 पृष्ठ पहले से मौजूद है और स्वचालित रूप से पुनर्लेखित नहीं किया जा सकता।',
	'renameuser-page-moved' => '$1 का नाम बदलकर $2 कर दिया गया है।',
	'renameuser-page-unmoved' => '$1 का नाम बदलकर $2 नहीं किया जा सका।',
	'log-name-renameuser' => 'सदस्यनाम बदलाव लॉग',
	'log-description-renameuser' => 'यह सदस्य नाम में बदलावों का लॉग है।',
	'logentry-renameuser-renameuser' => '$1 ने सदस्य $4 ({{PLURAL:$6|$6 सम्पादन}}) का नाम {{GENDER:$2|बदल}} कर $5 कर दिया',
	'logentry-renameuser-renameuser-legacier' => '$1 ने सदस्य $4 का नाम बदल कर $5 कर दिया',
	'renameuser-move-log' => 'सदस्य "[[User:$1|$1]]" का नाम "[[User:$2|$2]]" करते समय पृष्ठ स्वचालित रूप से स्थानांतरित कर दिया गया',
	'action-renameuser' => 'सदस्यों के नाम बदलने',
	'right-renameuser' => 'सदस्यों के नाम बदलें',
	'renameuser-renamed-notice' => 'इस सदस्य का नाम बदल दिया गया है।
संदर्भ के लिए नीचे नाम बदलने का लॉग है।',
);

/** Fiji Hindi (Latin script) (Fiji Hindi)
 * @author Thakurji
 */
$messages['hif-latn'] = array(
	'renameuser' => 'Sadasya ke naam badlo',
	'renameuser-desc' => "[[Special:Renameuser|special panna]] ke jorro ek sadasya  ke naam badle ke khatir (''renameuser'' ke hak maange hai)",
	'renameuserold' => 'Abhi ke username:',
	'renameusernew' => 'Nawaa username:',
	'renameuserreason' => 'Naam badle ke kaaran:', # Fuzzy
	'renameusermove' => 'Sadasya aur salah waala panna (aur uske sub-panna) ke naam badlo',
	'renameuserreserve' => 'Purana username ke aage use kare se roko',
	'renameuserwarnings' => 'Chetauni:',
	'renameuserconfirm' => 'Haan, sadasya ke naam badlo',
	'renameusersubmit' => 'Submit karo',
	'renameusererrordoesnotexist' => '"<nowiki>$1</nowiki>" naam ke koi sadasya nai hai.',
	'renameusererrorexists' => '"<nowiki>$1</nowiki>" naam ke ek sadasya abhi hai.',
	'renameusererrorinvalid' => 'Username "<nowiki>$1</nowiki>" kharaab hai.',
	'renameuser-error-request' => 'Request ke le me kuchh karrbarr bhais hai.
Meharbani kar ke laut ke fir kosis karo.',
	'renameuser-error-same-user' => 'Aap sadasya ke naam ke badal ke pahile waala naam nai kare sakta hai.',
	'renameusersuccess' => 'Sadasya "<nowiki>$1</nowiki>" ke naam badal ke "<nowiki>$2</nowiki>" kar dewa gais hai.',
	'renameuser-page-exists' => 'Panna $1 abhi hai aur iske apne se overwrite nai karaa jaae sake hai.',
	'renameuser-page-moved' => 'Panna $1 ke naam badal ke $2 kar dewa gais hai.',
	'renameuser-page-unmoved' => 'Panna $1 ke naam badal ke $2 nai kare sakaa hai.',
	'log-name-renameuser' => 'Sadasya ke naam badle ke log',
	'renameuser-move-log' => 'Automatically panna ke move kar diya hai jab ki sadasya ke naam  "[[User:$1|$1]]" se badal ke "[[User:$2|$2]]" kar dewa gais hai',
	'right-renameuser' => 'Sadasya log ke naam badlo',
);

/** Croatian (hrvatski)
 * @author Dalibor Bosits
 * @author Dnik
 * @author Ex13
 * @author Roberta F.
 * @author SpeedyGonsales
 * @author Tivek
 */
$messages['hr'] = array(
	'renameuser' => 'Preimenuj suradnika',
	'renameuser-linkoncontribs' => 'preimenuj suradnika',
	'renameuser-linkoncontribs-text' => 'Preimenuj ovog suradnika',
	'renameuser-desc' => "Dodaje [[Special:Renameuser|posebnu stranicu]] za preimenovanje suradnika (potrebno je ''renameuser'' pravo)",
	'renameuserold' => 'Trenutačno suradničko ime:',
	'renameusernew' => 'Novo suradničko ime:',
	'renameuserreason' => 'Razlog za preimenovanje:', # Fuzzy
	'renameusermove' => 'Premjesti suradnikove stranice (glavnu, stranicu za razgovor i podstranice, ako postoje) na novo ime',
	'renameusersuppress' => 'Ne kreiraj preusmjeravanja na novo ime',
	'renameuserreserve' => 'Zadrži staro suradničko ime od daljnje upotrebe',
	'renameuserwarnings' => 'Upozorenja:',
	'renameuserconfirm' => 'Da, preimenuj suradnika',
	'renameusersubmit' => 'Potvrdi',
	'renameuser-submit-blocklog' => 'Prikaži suradnikovu ili suradničinu evidenciju blokiranja',
	'renameusererrordoesnotexist' => 'Suradnik "<nowiki>$1</nowiki>" ne postoji (suradničko ime nije zauzeto).',
	'renameusererrorexists' => 'Suradničko ime "<nowiki>$1</nowiki>" već postoji',
	'renameusererrorinvalid' => 'Suradničko ime "<nowiki>$1</nowiki>" nije valjano',
	'renameuser-error-request' => 'Pojavio se problem sa zaprimanjem zahtjeva. Molimo, vratite se i probajte ponovo.',
	'renameuser-error-same-user' => 'Ne možete preimenovati suradnika u isto kao prethodno.',
	'renameusersuccess' => 'Suradnik "<nowiki>$1</nowiki>" je preimenovan u "<nowiki>$2</nowiki>"',
	'renameuser-page-exists' => 'Stranica $1 već postoji i ne može biti prepisana.',
	'renameuser-page-moved' => 'Suradnikova stranica $1 je premještena, sad se zove: $2.',
	'renameuser-page-unmoved' => 'Stranica $1 ne može biti preimenovana u $2.',
	'log-name-renameuser' => 'Evidencija preimenovanja suradnika',
	'log-description-renameuser' => 'Ovo je evidencija preimenovanja suradničkih imena',
	'renameuser-move-log' => 'Stranica suradnika je premještena prilikom preimenovanja iz "[[User:$1|$1]]" u "[[User:$2|$2]]"',
	'right-renameuser' => 'Preimenovati suradnike',
	'renameuser-renamed-notice' => 'Ovaj suradnik je preimenovan.
Evidencija preimenovanja suradnika je prikazana ispod kao obavijest.',
);

/** Upper Sorbian (hornjoserbsce)
 * @author Dundak
 * @author Michawiki
 */
$messages['hsb'] = array(
	'renameuser' => 'Wužiwarja přemjenować',
	'renameuser-linkoncontribs' => 'wužiwarja přemjenować',
	'renameuser-linkoncontribs-text' => 'Tutoho wužiwarja přemjenować',
	'renameuser-desc' => "Wužiwarja přemjenować (požada prawo ''renameuser'')",
	'renameuserold' => 'Tuchwilne wužiwarske mjeno:',
	'renameusernew' => 'Nowe wužiwarske mjeno:',
	'renameuserreason' => 'Přičina:',
	'renameusermove' => 'Wužiwarsku stronu a wužiwarsku diskusiju (a jeju podstrony) na nowe mjeno přesunyć',
	'renameusersuppress' => 'Dalesposrědkowanja k nowemu mjenu njewutworić',
	'renameuserreserve' => 'Stare wužiwarske mjeno za přichodne wužiwanje blokować',
	'renameuserwarnings' => 'Warnowanja:',
	'renameuserconfirm' => 'Haj, wužiwarja přemjenować',
	'renameusersubmit' => 'Składować',
	'renameuser-submit-blocklog' => 'Blokowanski protokol za wužiwarja pokazać',
	'renameusererrordoesnotexist' => 'Wužiwarske mjeno „<nowiki>$1</nowiki>“ njeeksistuje.',
	'renameusererrorexists' => 'Wužiwarske mjeno „<nowiki>$1</nowiki>“ hižo eksistuje.',
	'renameusererrorinvalid' => 'Wužiwarske mjeno „<nowiki>$1</nowiki>“ njeje płaćiwe.',
	'renameuser-error-request' => 'Problem je při přijimanju požadanja wustupił. Prošu dźi wróćo a spytaj hišće raz.',
	'renameuser-error-same-user' => 'Njemóžeš wužiwarja do samsneje wěcy kaž prjedy přemjenować.',
	'renameusersuccess' => 'Wužiwar „<nowiki>$1</nowiki>“ bu wuspěšnje na „<nowiki>$2</nowiki>“ přemjenowany.',
	'renameuser-page-exists' => 'Strona $1 hižo eksistuje a njemóže so awtomatisce přepisować.',
	'renameuser-page-moved' => 'Strona $1 bu pod nowy titul $2 přesunjena.',
	'renameuser-page-unmoved' => 'Njemóžno stronu $1 pod titul $2 přesunyć.',
	'log-name-renameuser' => 'Protokol přemjenowanja wužiwarjow',
	'log-description-renameuser' => 'To je protokol změnow wužiwarskich mjenow.',
	'logentry-renameuser-renameuser' => '$1 je wužiwarja $4 ({{PLURAL:$6|$6 změna|$6 změnje|$6 změny|$6 změnow}}) do $5 {{GENDER:$2|přemjenował|přemjenował}}',
	'logentry-renameuser-renameuser-legacier' => '$1 je wužiwarja $4 do $5 přemjenował',
	'renameuser-move-log' => 'Přez přemjenowanje wužiwarja „[[User:$1|$1]]“ na „[[User:$2|$2]]“ awtomatisce přesunjena strona.',
	'action-renameuser' => 'wužiwarjow přemjenować',
	'right-renameuser' => 'Wužiwarjow přemjenować',
	'renameuser-renamed-notice' => 'Tutón wužiwar je so přemjenował.
Protokol přemjenowanjow je deleka jako referenca podaty.',
);

/** Hungarian (magyar)
 * @author Adam78
 * @author Dani
 * @author Dj
 * @author Hunyadym
 * @author Tgr
 */
$messages['hu'] = array(
	'renameuser' => 'Szerkesztő átnevezése',
	'renameuser-linkoncontribs' => 'felhasználó átnevezése',
	'renameuser-linkoncontribs-text' => 'Felhasználó átnevezése',
	'renameuser-desc' => "Lehetővé teszi egy felhasználó átnevezését (''renameuser'' jog szükséges)",
	'renameuserold' => 'Jelenlegi felhasználónév:',
	'renameusernew' => 'Új felhasználónév:',
	'renameuserreason' => 'Ok:',
	'renameusermove' => 'Felhasználói- és vitalapok (és azok allapjainak) áthelyezése az új név alá',
	'renameusersuppress' => 'Ne készüljön átirányítás az új névre',
	'renameuserreserve' => 'Régi név blokkolása a jövőbeli használat megakadályozására',
	'renameuserwarnings' => 'Figyelmeztetések:',
	'renameuserconfirm' => 'Igen, nevezd át a szerkesztőt',
	'renameusersubmit' => 'Elküld',
	'renameusererrordoesnotexist' => 'Nem létezik „<nowiki>$1</nowiki>” nevű felhasználó',
	'renameusererrorexists' => 'Már létezik „<nowiki>$1</nowiki>” nevű felhasználó',
	'renameusererrorinvalid' => 'A felhasználónév („<nowiki>$1</nowiki>”) érvénytelen',
	'renameuser-error-request' => 'Hiba történt a lekérdezés küldése közben.  Menj vissza az előző oldalra és próbáld újra.',
	'renameuser-error-same-user' => 'Nem nevezhetsz át egy felhasználót a meglévő nevére.',
	'renameusersuccess' => '„<nowiki>$1</nowiki>” sikeresen át lett nevezve „<nowiki>$2</nowiki>” névre.',
	'renameuser-page-exists' => '$1 már létezik, és nem lehet automatikusan felülírni.',
	'renameuser-page-moved' => '$1 át lett nevezve $2 névre',
	'renameuser-page-unmoved' => '$1-t nem sikerült $2 névre nevezi',
	'log-name-renameuser' => 'Felhasználóátnevezési napló',
	'logentry-renameuser-renameuser' => '$1 {{GENDER:$2|átnevezte}} $4 szerkesztőt ({{PLURAL:$6|egy|$6}} szerkesztés) erre: $5', # Fuzzy
	'logentry-renameuser-renameuser-legacier' => '$1 átnevezte $4 szerkesztőt erre: $5',
	'renameuser-move-log' => '„[[User:$1|$1]]” „[[User:$2|$2]]” névre való átnevezése közben automatikusan átnevezett oldal',
	'action-renameuser' => 'felhasználó átnevezése',
	'right-renameuser' => 'felhasználók átnevezése',
	'renameuser-renamed-notice' => 'Ezt a szerkesztőt átnevezték.
Alább látható a szerkesztőátnevezési napló tájékoztatásként.',
);

/** Interlingua (interlingua)
 * @author McDutchie
 */
$messages['ia'] = array(
	'renameuser' => 'Renominar usator',
	'renameuser-linkoncontribs' => 'renominar usator',
	'renameuser-linkoncontribs-text' => 'Renominar iste usator',
	'renameuser-desc' => "Adde un [[Special:Renameuser|pagina special]] pro renominar un usator (require le privilegio ''renameuser'')",
	'renameuserold' => 'Nomine de usator actual:',
	'renameusernew' => 'Nove nomine de usator:',
	'renameuserreason' => 'Motivo:',
	'renameusermove' => 'Renominar etiam le paginas de usator e de discussion (e lor subpaginas) verso le nove nomine',
	'renameusersuppress' => 'Non crear redirectiones al nove nomine',
	'renameuserreserve' => 'Blocar le ancian nomine de usator de esser usate in le futuro',
	'renameuserwarnings' => 'Advertimentos:',
	'renameuserconfirm' => 'Si, renomina le usator',
	'renameusersubmit' => 'Submitter',
	'renameuser-submit-blocklog' => 'Monstrar registro de blocadas pro le usator',
	'renameusererrordoesnotexist' => 'Le usator "<nowiki>$1</nowiki>" non existe.',
	'renameusererrorexists' => 'Le usator ""<nowiki>$1</nowiki>"" existe ja.',
	'renameusererrorinvalid' => 'Le nomine de usator "<nowiki>$1</nowiki>" es invalide.',
	'renameuser-error-request' => 'Il habeva un problema con le reception del requesta.
Per favor retorna e reproba.',
	'renameuser-error-same-user' => 'Tu non pote renominar un usator al mesme nomine.',
	'renameusersuccess' => 'Le usator "<nowiki>$1</nowiki>" ha essite renominate a "<nowiki>$2</nowiki>".',
	'renameuser-page-exists' => 'Le pagina $1 existe ja e non pote esser automaticamente superscribite.',
	'renameuser-page-moved' => 'Le pagina $1 ha essite renominate a $2.',
	'renameuser-page-unmoved' => 'Le pagina $1 non poteva esser renominate a $2.',
	'log-name-renameuser' => 'Registro de renominationes de usatores',
	'renameuser-move-log' => 'Le pagina ha essite automaticamente renominate con le renomination del usator "[[User:$1|$1]]" a "[[User:$2|$2]]"',
	'action-renameuser' => 'renominar usatores',
	'right-renameuser' => 'Renominar usatores',
	'renameuser-renamed-notice' => 'Iste usator ha essite renominate.
Le registro de renominationes es providite ci infra pro referentia.',
);

/** Indonesian (Bahasa Indonesia)
 * @author Bennylin
 * @author Farras
 * @author Irwangatot
 * @author IvanLanin
 * @author Rex
 */
$messages['id'] = array(
	'renameuser' => 'Penggantian nama pengguna',
	'renameuser-linkoncontribs' => 'mengubah nama pengguna',
	'renameuser-linkoncontribs-text' => 'Ubah nama pengguna ini',
	'renameuser-desc' => "Mengganti nama pengguna (perlu hak akses ''renameuser'')",
	'renameuserold' => 'Nama sekarang:',
	'renameusernew' => 'Nama baru:',
	'renameuserreason' => 'Alasan penggantian nama:', # Fuzzy
	'renameusermove' => 'Pindahkan halaman pengguna dan pembicaraannya (berikut subhalamannya) ke nama baru',
	'renameusersuppress' => 'Jangan membuat pengalihan untuk nama baru',
	'renameuserreserve' => 'Cadangkan nama pengguna lama sehingga tidak dapat digunakan lagi',
	'renameuserwarnings' => 'Peringatan:',
	'renameuserconfirm' => 'Ya, ganti nama pengguna tersebut',
	'renameusersubmit' => 'Kirim',
	'renameuser-submit-blocklog' => 'Tampilkan log pemblokiran pengguna',
	'renameusererrordoesnotexist' => 'Pengguna "<nowiki>$1</nowiki>" tidak ada',
	'renameusererrorexists' => 'Pengguna "<nowiki>$1</nowiki>" telah ada',
	'renameusererrorinvalid' => 'Nama pengguna "<nowiki>$1</nowiki>" tidak sah',
	'renameuser-error-request' => 'Ada masalah dalam pemrosesan permintaan. Silakan kembali dan coba lagi.',
	'renameuser-error-same-user' => 'Anda tak dapat mengganti nama pengguna sama seperti asalnya.',
	'renameusersuccess' => 'Pengguna "<nowiki>$1</nowiki>" telah diganti namanya menjadi "<nowiki>$2</nowiki>"',
	'renameuser-page-exists' => 'Halaman $1 telah ada dan tidak dapat ditimpa secara otomatis.',
	'renameuser-page-moved' => 'Halaman $1 telah dipindah ke $2.',
	'renameuser-page-unmoved' => 'Halaman $1 tidak dapat dipindah ke $2.',
	'log-name-renameuser' => 'Log penggantian nama pengguna',
	'log-description-renameuser' => 'Di bawah ini adalah log penggantian nama pengguna',
	'renameuser-move-log' => 'Secara otomatis memindahkan halaman sewaktu mengganti nama pengguna "[[User:$1|$1]]" menjadi "[[User:$2|$2]]"',
	'action-renameuser' => 'ganti nama pengguna',
	'right-renameuser' => 'Mengganti nama pengguna',
	'renameuser-renamed-notice' => 'Penguna ini telah berganti nama.
Log pergantian nama disediakan di bawah untuk referensi.',
);

/** Igbo (Igbo)
 * @author Ukabia
 */
$messages['ig'] = array(
	'renameuserwarnings' => 'Ngéntị:',
	'renameusersubmit' => 'Dànyé',
	'renameuser-page-moved' => 'Ihü $1 a páfùrù gá $2.',
	'renameuser-page-unmoved' => 'Ihü $1 énweghịkị páfù gá $2.',
);

/** Iloko (Ilokano)
 * @author Lam-ang
 */
$messages['ilo'] = array(
	'renameuser' => 'Inaganan manen ti agar-aramat',
	'renameuser-linkoncontribs' => 'inaganan manen ti agar-aramat',
	'renameuser-linkoncontribs-text' => 'Inaganan manen daytoy nga agar-aramat',
	'renameuser-desc' => "Agnayon ti [[Special:Renameuser|espesial a panid]] tapno inaganan manen ti agar-aramat (masapul ti ''inaganan manen ti agar-aramat'' a karbengan)",
	'renameuserold' => 'Agdama a nagan ti agar-aramat:',
	'renameusernew' => 'Baro a nagan ti agar-aramat:',
	'renameuserreason' => 'Rason:',
	'renameusermove' => 'Iyalis ti agar-aramat ket tungtungan a pampanid (ken dagiti ap-apo a panid) iti baro a nagan',
	'renameusersuppress' => 'Saan nga agpartuat kadagiti baw-ing idiay baro a nagan',
	'renameuserreserve' => 'Serraan ti daan a nagan ti agar-aramat manipud ti masakbayan a panag-usar.',
	'renameuserwarnings' => 'Dagiti ballaag:',
	'renameuserconfirm' => 'Wen, inaganan manen ti agar-aramat',
	'renameusersubmit' => 'Ited',
	'renameuser-submit-blocklog' => 'Ipakita ti panakaserra a listaan para iti agar-aramat',
	'renameusererrordoesnotexist' => 'Ti agar-aramat "<nowiki>$1</nowiki>" ket awan.',
	'renameusererrorexists' => 'Ti agar-aramat "<nowiki>$1</nowiki>" ket addan.',
	'renameusererrorinvalid' => 'Ti nagan ti agar-aramat "<nowiki>$1</nowiki>" ket imbalido.',
	'renameuser-error-request' => 'Adda pakirut ti panakaawat ti kiddaw.
Pangngaasi nga agsubli ken padasen manen.',
	'renameuser-error-same-user' => 'Saanmo a mainaganan manen ti agar-aramat a kas idi.',
	'renameusersuccess' => 'Ti agar-aramat "<nowiki>$1</nowiki>" ket nainaganan manen ti "<nowiki>$2</nowiki>".',
	'renameuser-page-exists' => 'Ti panid a $1 ket addaan ken saan a mautomatiko a suratan manen.',
	'renameuser-page-moved' => 'Ti panid $1 ket naiyalisen idiay $2.',
	'renameuser-page-unmoved' => 'Ti panid  $1 ket saan a maiyalis idiay $2.',
	'log-name-renameuser' => 'Listaan ti panaginaganan manen ti agar-aramat',
	'log-description-renameuser' => 'Daytoy ket listaan kadagiti panagbalbaliw kadagiti nagan ti agar-aramat.',
	'logentry-renameuser-renameuser' => 'Ni $1 ket {{GENDER:$2|ninagananna}} ti agar-aramat a ni $4 ({{PLURAL:$6|$6 nga inurnos|$6 kadagiti inurnos}}) iti $5',
	'logentry-renameuser-renameuser-legacier' => 'Ni $1 ket ninagananna ti agar-aramat a ni $4 iti $5',
	'renameuser-move-log' => 'Automatiko nga iyalis ti panid bayat a nagnaganan manen ti agar-aramat "[[User:$1|$1]]" iti "[[User:$2|$2]]"',
	'action-renameuser' => 'inaganan manen dagiti agar-aramat',
	'right-renameuser' => 'Inaganan manen dagiti agar-aramat',
	'renameuser-renamed-notice' => 'Nanaganen manen daytoy nga agar-aramat.
Ti listaan ti panaginaganan manen ket naited dita baba para iti reperensia.',
);

/** Ido (Ido)
 * @author Malafaya
 * @author Wyvernoid
 */
$messages['io'] = array(
	'renameuser' => 'Rinomar uzanto',
	'renameuserold' => 'Aktuala uzantonomo:',
	'renameusernew' => 'Nova uzantonomo:',
	'renameuserwarnings' => 'Averti:',
	'renameuserconfirm' => "Yes, rinomez l'uzanto",
	'renameusererrordoesnotexist' => 'L\'uzanto "<nowiki>$1</nowiki>" ne existas.',
	'renameusererrorexists' => 'L\'uzanto "<nowiki>$1</nowiki>" ja existas.',
	'renameusererrorinvalid' => 'L\'uzantonomo "<nowiki>$1</nowiki>" esas ne-valida.',
	'renameuser-error-same-user' => 'Vu ne povas renomar uzanto ad la sama nomo.',
	'renameusersuccess' => 'La uzanto "<nowiki>$1</nowiki>" rinomesis "<nowiki>$2</nowiki>".',
	'renameuser-page-moved' => 'La pagino $1 movesis a $2.',
	'renameuser-page-unmoved' => 'On ne povis movar la pagino $1 a $2.',
	'log-name-renameuser' => 'Registro di uzanto-rinomizuri',
	'right-renameuser' => 'Rinomar uzanti',
);

/** Icelandic (íslenska)
 * @author Cessator
 * @author S.Örvarr.S
 * @author Snævar
 * @author Spacebirdy
 * @author Ævar Arnfjörð Bjarmason
 * @author לערי ריינהארט
 */
$messages['is'] = array(
	'renameuser' => 'Breyta notandanafni',
	'renameuser-linkoncontribs' => 'breyta notendanafni',
	'renameuser-linkoncontribs-text' => 'breyta notendanafni notandans',
	'renameuser-desc' => "Bætir við [[Special:Renameuser|kerfissíðu]] til að breyta notendanafni (þarfnast ''renameuser'' réttinda)",
	'renameuserold' => 'Núverandi notandanafn:',
	'renameusernew' => 'Nýja notandanafnið:',
	'renameuserreason' => 'Ástæða:',
	'renameusermove' => 'Færa notendasíðu og notendaspjallsíðu (og undirsíður þeirra) á nýja nafnið',
	'renameusersuppress' => 'Ekki skilja eftir tilvísun',
	'renameuserreserve' => 'Banna notkun á gamla notendanafninu',
	'renameuserwarnings' => 'Viðvaranir:',
	'renameuserconfirm' => 'Já, breyta nafni notandans',
	'renameusersubmit' => 'Senda',
	'renameuser-submit-blocklog' => 'Sýna bönnunar skrá notandans',
	'renameusererrordoesnotexist' => 'Notandinn „<nowiki>$1</nowiki>“ er ekki til',
	'renameusererrorexists' => 'Notandinn „<nowiki>$1</nowiki>“ er nú þegar til',
	'renameusererrorinvalid' => 'Notandanafnið „<nowiki>$1</nowiki>“ er ógilt',
	'renameuser-error-request' => 'Mistókst að sækja beiðnina um breytingu notendanafnsins.
Vinsamlegast farðu til baka og reyndu aftur.',
	'renameuser-error-same-user' => 'Óheimilt er að breyta nafni notanda aftur á það notendanafn sem hann hafði áður.',
	'renameusersuccess' => 'Nafn notandans "<nowiki>$1</nowiki>" hefur verið breytt í "<nowiki>$2</nowiki>".',
	'renameuser-page-exists' => 'Síða sem heitir $1 er nú þegar til og það er ekki hægt að búa til nýja grein með sama heiti.',
	'renameuser-page-moved' => 'Síðan $1 hefur verið færð á $2.',
	'renameuser-page-unmoved' => 'Ekki var hægt að færa síðuna $1 á $2.',
	'log-name-renameuser' => 'Skrá yfir nafnabreytingar notenda',
	'log-description-renameuser' => 'Þetta er skrá yfir breytingar á notendanöfnum.',
	'logentry-renameuser-renameuser' => '$1 breytti {{GENDER:$2|notendanafni}} $4 ({{PLURAL:$6|$6 breyting|$6 breytingar}}) í $5',
	'renameuser-move-log' => 'Færði síðuna sjálfvirkt þegar notendanafni "[[User:$1|$1]]" var breytt í "[[User:$2|$2]]"',
	'action-renameuser' => 'endurnefna notendur',
	'right-renameuser' => 'Breyta notandanafni notenda',
	'renameuser-renamed-notice' => 'Nafni notandans hefur verið breytt. 
Síðasta færsla notandans úr skrá yfir nafnabreytingar notenda er sýnd hér fyrir neðan til skýringar:',
);

/** Italian (italiano)
 * @author .anaconda
 * @author Beta16
 * @author BrokenArrow
 * @author Darth Kule
 * @author Gianfranco
 * @author HalphaZ
 * @author Melos
 * @author Nemo bis
 */
$messages['it'] = array(
	'renameuser' => 'Rinomina utente',
	'renameuser-linkoncontribs' => 'rinomina utente',
	'renameuser-linkoncontribs-text' => 'Rinomina questo utente',
	'renameuser-desc' => "Aggiunge una [[Special:Renameuser|pagina speciale]] per rinominare un utente (richiede i diritti di ''renameuser'')",
	'renameuserold' => 'Nome utente attuale:',
	'renameusernew' => 'Nuovo nome utente:',
	'renameuserreason' => 'Motivo:',
	'renameusermove' => 'Rinomina anche la pagina utente, la pagina di discussione e le relative sottopagine',
	'renameusersuppress' => 'Non creare redirect al nuovo nome',
	'renameuserreserve' => "Impedisci l'utilizzo del vecchio nome in futuro",
	'renameuserwarnings' => 'Avvisi:',
	'renameuserconfirm' => 'Sì, rinomina questo utente',
	'renameusersubmit' => 'Invia',
	'renameuser-submit-blocklog' => "Mostra registro dei blocchi per l'utente",
	'renameusererrordoesnotexist' => 'L\'utente "<nowiki>$1</nowiki>" non esiste.',
	'renameusererrorexists' => 'L\'utente "<nowiki>$1</nowiki>" esiste già.',
	'renameusererrorinvalid' => 'Il nome utente "<nowiki>$1</nowiki>" non è valido',
	'renameuser-error-request' => 'Si è verificato un problema nella ricezione della richiesta. Tornare indietro e riprovare.',
	'renameuser-error-same-user' => 'Non è possibile rinominare un utente allo stesso nome che aveva già.',
	'renameusersuccess' => 'L\'utente "<nowiki>$1</nowiki>" è stato rinominato in "<nowiki>$2</nowiki>"',
	'renameuser-page-exists' => 'La pagina $1 esiste già; impossibile sovrascriverla automaticamente.',
	'renameuser-page-moved' => 'La pagina $1 è stata spostata a $2.',
	'renameuser-page-unmoved' => 'La pagina $1 non può essere spostata a $2.',
	'log-name-renameuser' => 'Utenti rinominati',
	'log-description-renameuser' => 'Di seguito sono elencate le modifiche ai nomi utente.',
	'logentry-renameuser-renameuser' => "$1 {{GENDER:$2|ha rinominato}} l'utente $4 (con {{PLURAL:$6|$6 contributo|$6 contributi}}) in $5",
	'logentry-renameuser-renameuser-legacier' => "$1 ha rinominato l'utente $4 in $5",
	'renameuser-move-log' => 'Pagina spostata automaticamente durante la rinomina dell\'utente "[[User:$1|$1]]" a "[[User:$2|$2]]"',
	'action-renameuser' => 'rinominare gli utenti',
	'right-renameuser' => 'Rinomina gli utenti',
	'renameuser-renamed-notice' => 'Questo utente è stato rinominato.
Il registro delle rinomine è riportato di seguito per informazione.',
);

/** Japanese (日本語)
 * @author Aotake
 * @author Broad-Sky
 * @author Fryed-peach
 * @author Hosiryuhosi
 * @author Marine-Blue
 * @author Ohgi
 * @author Penn Station
 * @author Shirayuki
 * @author Suisui
 * @author 青子守歌
 */
$messages['ja'] = array(
	'renameuser' => '利用者名の変更',
	'renameuser-linkoncontribs' => '利用者名変更',
	'renameuser-linkoncontribs-text' => 'この利用者の名前を変更',
	'renameuser-desc' => "利用者名変更のための[[Special:Renameuser|特別ページ]]を追加する (「{{int:right-renameuser}}」できる権限 ''renameuser'' が必要)",
	'renameuserold' => '現在の利用者名:',
	'renameusernew' => '新しい利用者名:',
	'renameuserreason' => '理由:',
	'renameusermove' => '利用者ページと会話ページ (およびそれらの下位ページ) を新しい名前に移動',
	'renameusersuppress' => '新しい名前へのリダイレクトを作成しない',
	'renameuserreserve' => '旧利用者名の今後の使用をブロック',
	'renameuserwarnings' => '警告:',
	'renameuserconfirm' => 'はい、利用者名を変更します',
	'renameusersubmit' => '変更',
	'renameuser-submit-blocklog' => '利用者のブロック記録を表示',
	'renameusererrordoesnotexist' => '利用者「<nowiki>$1</nowiki>」は存在しません。',
	'renameusererrorexists' => '利用者「<nowiki>$1</nowiki>」は既に存在しています。',
	'renameusererrorinvalid' => '利用者名「<nowiki>$1</nowiki>」は無効な値です。',
	'renameuser-error-request' => '要求を正常に受け付けることができませんでした。
戻ってから再度試してください。',
	'renameuser-error-same-user' => '現在と同じ利用者名には変更できません。',
	'renameusersuccess' => '利用者名を「<nowiki>$1</nowiki>」から「<nowiki>$2</nowiki>」に変更しました。',
	'renameuser-page-exists' => '$1 が既に存在するため、自動での上書きはできませんでした。',
	'renameuser-page-moved' => '$1 を $2 に移動しました。',
	'renameuser-page-unmoved' => '$1 を $2 に移動できませんでした。',
	'log-name-renameuser' => '利用者名変更記録',
	'log-description-renameuser' => 'これは、利用者名変更の記録です。',
	'logentry-renameuser-renameuser' => '$1 が $4 ({{PLURAL:$6|$6 編集}}) の利用者名を $5 に{{GENDER:$2|変更しました}}',
	'logentry-renameuser-renameuser-legacier' => '$1 が $4 の利用者名を $5 に変更しました',
	'renameuser-move-log' => 'ページを自動的に移動しました (利用者名変更のため:「[[User:$1|$1]]」から「[[User:$2|$2]]」)',
	'action-renameuser' => '利用者名の変更',
	'right-renameuser' => '利用者名を変更',
	'renameuser-renamed-notice' => 'この利用者は利用者名を変更しました。
参考のため、利用者名変更記録を以下に示します。',
);

/** Jutish (jysk)
 * @author Huslåke
 * @author Ælsån
 */
$messages['jut'] = array(
	'renameuser' => 'Gæf æ bruger en ny navn',
	'renameuser-desc' => "Gæf en bruger en ny navn (''renameuser'' regt er nøteg)",
	'renameuserold' => 'Nuværende brugernavn:',
	'renameusernew' => 'Ny brugernavn:',
	'renameuserreason' => "Før hvat dett'er dun:", # Fuzzy
	'renameusermove' => 'Flyt bruger og diskusje sider (og deres substrøk) til ny navn',
	'renameusersubmit' => 'Gå til',
	'renameusererrordoesnotexist' => 'Æ bruger "<nowiki>$1</nowiki>" bestä ekke.',
	'renameusererrorexists' => 'Æ bruger "<nowiki>$1</nowiki>" er ål.',
	'renameusererrorinvalid' => 'Æ brugernavn "<nowiki>$1</nowiki>" er ogyldegt.',
	'renameuser-error-request' => 'Her har en pråblæm ve enkriige der anfråge. Gå hen og pråbær nurmål.',
	'renameuser-error-same-user' => 'Du kenst ekke hernåm æ bruger til æselbste nåm als dafør.',
	'renameusersuccess' => 'Æ bruger "<nowiki>$1</nowiki>" er hernåmt til "<nowiki>$2</nowiki>".',
	'renameuser-page-exists' => 'Æ pæge $1 er ål og ken ekke åtåmatisk åverflyttet være.',
	'renameuser-page-moved' => 'Æ pæge $1 er flyttet til $2.',
	'renameuser-page-unmoved' => 'Æ pæge $1 kon ekke flyttet være til $2.',
	'log-name-renameuser' => 'Bruger hernåm log',
	'renameuser-move-log' => 'Åtåmatisk flyttet pæge hviil hernåm der bruger "[[User:$1|$1]]" til "[[User:$2|$2]]"',
);

/** Javanese (Basa Jawa)
 * @author Meursault2004
 * @author NoiX180
 * @author Pras
 */
$messages['jv'] = array(
	'renameuser' => 'Ngganti jeneng panganggo',
	'renameuser-linkoncontribs' => 'ganti jeneng panganggo',
	'renameuser-linkoncontribs-text' => 'Ganti jenengé panganggo iki',
	'renameuser-desc' => "Ngganti jeneng panganggo (perlu hak aksès ''renameuser'')",
	'renameuserold' => 'Jeneng panganggo saiki:',
	'renameusernew' => 'Jeneng panganggo anyar:',
	'renameuserreason' => 'Alesan ganti jeneng:', # Fuzzy
	'renameusermove' => 'Mindhah kaca panganggo lan kaca dhiskusiné (sarta subkaca-kacané) menyang jeneng anyar',
	'renameusersuppress' => 'Aja gawé pangalihan kanggo jeneng anyar',
	'renameuserreserve' => 'Blokir utawa cadhangaké jeneng panganggo lawas supaya ora bisa dianggo manèh',
	'renameuserwarnings' => 'Pènget:',
	'renameuserconfirm' => 'Ya, ganti jeneng panganggo kasebut',
	'renameusersubmit' => 'Kirim',
	'renameuser-submit-blocklog' => 'Tuduhaké log blokir kanggo panganggo',
	'renameusererrordoesnotexist' => 'Panganggo "<nowiki>$1</nowiki>" ora ana.',
	'renameusererrorexists' => 'Panganggo "<nowiki>$1</nowiki>" wis ana.',
	'renameusererrorinvalid' => 'Jeneng panganggo "<nowiki>$1</nowiki>" ora absah',
	'renameuser-error-request' => 'Ana masalah nalika nampa panyuwunan panjenengan.
Mangga balènana lan nyoba manèh.',
	'renameuser-error-same-user' => 'Panjenengan ora bisa ngganti jeneng panganggo dadi kaya jeneng asalé.',
	'renameusersuccess' => 'Panganggo "<nowiki>$1</nowiki>" wis diganti jenengé dadi "<nowiki>$2</nowiki>".',
	'renameuser-page-exists' => 'Kaca $1 wis ana lan ora bisa ditimpa sacara otomatis.',
	'renameuser-page-moved' => 'Kaca $1 wis dialihaké menyang $2.',
	'renameuser-page-unmoved' => 'Kaca $1 ora bisa dialihaké menyang $2.',
	'log-name-renameuser' => 'Log ganti jeneng panganggo',
	'log-description-renameuser' => 'Iki log owah-owahan jeneng panganggo',
	'renameuser-move-log' => 'Sacara otomatis mindhah kaca nalika ngganti jeneng panganggo "[[User:$1|$1]]" dadi "[[User:$2|$2]]"',
	'action-renameuser' => 'ganti jeneng panganggo',
	'right-renameuser' => 'Ganti jeneng panganggo-panganggo',
	'renameuser-renamed-notice' => 'Panganggo iki wis diganti jenengé.
Log panggantèn jeneng sumadhiya ngisor iki kanggo rujukan.',
);

/** Georgian (ქართული)
 * @author BRUTE
 * @author David1010
 * @author Dawid Deutschland
 * @author Malafaya
 * @author Nodar Kherkheulidze
 * @author Sopho
 */
$messages['ka'] = array(
	'renameuser' => 'მომხმარებლის სახელის გამოცვლა',
	'renameuser-linkoncontribs' => 'მომხმარებლის სახელის გადარქმევა',
	'renameuser-linkoncontribs-text' => 'ამ მომხმარებლის სახელის გადარქმევა',
	'renameuser-desc' => 'ამატებს მომხმარებლების სახელის გადარქმევის [[Special:Renameuser|შესაძლებლობას]] (საჭიროა უფლება <code>renameuser</code>)',
	'renameuserold' => 'ამჟამინდელი მომხმარებლის სახელი:',
	'renameusernew' => 'ახალი მომხმარებლის სახელი:',
	'renameuserreason' => 'მიზეზი:',
	'renameusermove' => 'მომხმარებლისა და განხილვის გვერდების (და მათი დაქვემდებარებული გვერდების) გადატანა ახალ დასახელებაზე',
	'renameusersuppress' => 'არ გადაამისამართოთ ახალ სახელზე',
	'renameuserreserve' => 'ძველი მომხმარებლის სახელის სამომავლო გამოყენების აკრძალვა',
	'renameuserwarnings' => 'გაფრთხილებები:',
	'renameuserconfirm' => 'დიახ, მსურს სახელის გადარქმევა',
	'renameusersubmit' => 'გაგზავნა',
	'renameuser-submit-blocklog' => 'მომხმარებლის დაბლოკვის ჟურნალის ჩვენება',
	'renameusererrordoesnotexist' => 'მომხმარებელი „<nowiki>$1</nowiki>“ არ არსებობს',
	'renameusererrorexists' => 'მომხმარებელი "<nowiki>$1</nowiki>" უკვე არსებობს',
	'renameusererrorinvalid' => 'მომხმარებლის სახელი „<nowiki>$1</nowiki>“ არასწორია',
	'renameuser-error-request' => 'მოთხოვნის მიღებასთან დაკავშირებით რაღაც პრობლემაა. გთხოვთ, ხელახლა სცადეთ.',
	'renameuser-error-same-user' => 'თქვენ არ შეგიძლიათ დაარქვათ მომხმარებელს იგივე სახელი, რაც ერქვა წინათ.',
	'renameusersuccess' => 'მომხმარებლის სახელი — „<nowiki>$1</nowiki>“, შეიცვალა „<nowiki>$2</nowiki>-ით“',
	'renameuser-page-exists' => 'გვერდი $1 უკვე არსებობს და მისი ავტომატურად შენაცვლება შეუძლებელია.',
	'renameuser-page-moved' => 'გვერდი $1 გადატანილია $2-ზე.',
	'renameuser-page-unmoved' => 'არ მოხერხდა გვერდის $1 გადატანა $2-ზე.',
	'log-name-renameuser' => 'მომხმარებლის სახელის გადარქმევის რეგისტრაციის ჟურნალი',
	'log-description-renameuser' => 'ეს არის ჟურნალი, სადაც აღრიცხულია მომხმარებლის სახელთა ცვლილებები.',
	'logentry-renameuser-renameuser' => 'მომხმარებელმა $1 {{GENDER:$2|შეუცვალა სახელი}} მომხმარებელს $4 ({{PLURAL:$6|$6 რედაქტირება|$6 რედაქტირება}}) სახელით $5',
	'logentry-renameuser-renameuser-legacier' => 'მომხმარებელმა $1 შეუცვალა სახელი მომხმარებელს $4 სახელით $5',
	'renameuser-move-log' => 'ავტომატურად იქნა გადატანილი გვერდი მომხმარებლის „[[User:$1|$1]]“ სახელის შეცვლისას „[[User:$2|$2]]-ით“',
	'action-renameuser' => 'მომხმარებლების სახელის გადარქმევა',
	'right-renameuser' => 'მომხმარებლების სახელის გადარქმევა',
	'renameuser-renamed-notice' => 'ამ მომხმარებელს სახელი გადაერქვა.
ქვემოთ მოყვანილია სახელის გადარქმევის ჟურნალი.',
);

/** Kazakh (Arabic script) (قازاقشا (تٴوتە)‏)
 */
$messages['kk-arab'] = array(
	'renameuser' => 'قاتىسۋشىنى قايتا اتاۋ',
	'renameuserold' => 'اعىمداعى قاتىسۋشى اتى:',
	'renameusernew' => 'جاڭا قاتىسۋشى اتى:',
	'renameuserreason' => 'قايتا اتاۋ سەبەبى:', # Fuzzy
	'renameusermove' => 'قاتىسۋشىنىڭ جەكە جانە تالقىلاۋ بەتتەرىن (جانە دە ولاردىڭ تومەنگى بەتتەرىن) جاڭا اتاۋعا جىلجىتۋ',
	'renameusersubmit' => 'جىبەرۋ',
	'renameusererrordoesnotexist' => '«<nowiki>$1» دەگەن قاتىسۋشى جوق',
	'renameusererrorexists' => '«$1» دەگەن قاتىسۋشى بار تۇگە',
	'renameusererrorinvalid' => '«$1» قاتىسۋشى اتى جارامسىز',
	'renameusersuccess' => '«$1» دەگەن قاتىسۋشى اتى «$2» دەگەنگە اۋىستىرىلدى',
	'renameuser-page-exists' => '$1 دەگەن بەت بار تۇگە, جانە وزدىك تۇردە ونىڭ ۇستىنە ەشتەڭە جازىلمايدى.',
	'renameuser-page-moved' => '$1 دەگەن بەت $2 دەگەن بەتكە جىلجىتىلدى.',
	'renameuser-page-unmoved' => '$1 دەگەن بەت $2 دەگەن بەتكە جىلجىتىلمادى.',
	'log-name-renameuser' => 'قاتىسۋشىنى قايتا اتاۋ جۋرنالى',
	'renameuser-move-log' => '«[[User:$1|$1]]» دەگەن قاتىسۋشى اتىن «[[User:$2|$2]]» دەگەنگە اۋىسقاندا بەت وزدىك تۇردە جىلجىتىلدى',
);

/** Kazakh (Cyrillic script) (қазақша (кирил)‎)
 * @author Arystanbek
 */
$messages['kk-cyrl'] = array(
	'renameuser' => 'Қатысушыны қайта атау',
	'renameuserold' => 'Ағымдағы қатысушы аты:',
	'renameusernew' => 'Жаңа қатысушы аты:',
	'renameuserreason' => 'Қайта атау себебі:', # Fuzzy
	'renameusermove' => 'Қатысушының жеке және талқылау беттерін (және де олардың төменгі беттерін) жаңа атауға жылжыту',
	'renameusersubmit' => 'Жіберу',
	'renameusererrordoesnotexist' => '«<nowiki>$1</nowiki>» деген қатысушы жоқ',
	'renameusererrorexists' => '«<nowiki>$1</nowiki>» деген қатысушы бар түге',
	'renameusererrorinvalid' => '«<nowiki>$1</nowiki>» қатысушы аты жарамсыз',
	'renameusersuccess' => '«<nowiki>$1</nowiki>» деген қатысушы аты «<nowiki>$2</nowiki>» дегенге ауыстырылды',
	'renameuser-page-exists' => '$1 деген бет бар түге, және өздік түрде оның үстіне ештеңе жазылмайды.',
	'renameuser-page-moved' => '$1 деген бет $2 деген бетке жылжытылды.',
	'renameuser-page-unmoved' => '$1 деген бет $2 деген бетке жылжытылмады.',
	'log-name-renameuser' => 'Қатысушыны есімін өзгеру журналы',
	'renameuser-move-log' => '«[[User:$1|$1]]» деген қатысушы атын «[[User:$2|$2]]» дегенге ауысқанда бет өздік түрде жылжытылды',
);

/** Kazakh (Latin script) (qazaqşa (latın)‎)
 */
$messages['kk-latn'] = array(
	'renameuser' => 'Qatıswşını qaýta ataw',
	'renameuserold' => 'Ağımdağı qatıswşı atı:',
	'renameusernew' => 'Jaña qatıswşı atı:',
	'renameuserreason' => 'Qaýta ataw sebebi:', # Fuzzy
	'renameusermove' => 'Qatıswşınıñ jeke jäne talqılaw betterin (jäne de olardıñ tömengi betterin) jaña atawğa jıljıtw',
	'renameusersubmit' => 'Jiberw',
	'renameusererrordoesnotexist' => '«<nowiki>$1</nowiki>» degen qatıswşı joq',
	'renameusererrorexists' => '«<nowiki>$1</nowiki>» degen qatıswşı bar tüge',
	'renameusererrorinvalid' => '«<nowiki>$1</nowiki>» qatıswşı atı jaramsız',
	'renameusersuccess' => '«<nowiki>$1</nowiki>» degen qatıswşı atı «<nowiki>$2</nowiki>» degenge awıstırıldı',
	'renameuser-page-exists' => '$1 degen bet bar tüge, jäne özdik türde onıñ üstine eşteñe jazılmaýdı.',
	'renameuser-page-moved' => '$1 degen bet $2 degen betke jıljıtıldı.',
	'renameuser-page-unmoved' => '$1 degen bet $2 degen betke jıljıtılmadı.',
	'log-name-renameuser' => 'Qatıswşını qaýta ataw jwrnalı',
	'renameuser-move-log' => '«[[User:$1|$1]]» degen qatıswşı atın «[[User:$2|$2]]» degenge awısqanda bet özdik türde jıljıtıldı',
);

/** Khmer (ភាសាខ្មែរ)
 * @author Chhorran
 * @author Lovekhmer
 * @author Thearith
 * @author គីមស៊្រុន
 */
$messages['km'] = array(
	'renameuser' => 'ប្តូរអត្តនាម',
	'renameuser-linkoncontribs' => 'ប្តូរឈ្មោះអ្នកប្រើប្រាស់',
	'renameuser-linkoncontribs-text' => 'ប្ដូរឈ្មោះអ្នកប្រើប្រាស់នេះ',
	'renameuser-desc' => "ប្តូរឈ្មោះអ្នកប្រើប្រាស់(ត្រូវការសិទ្ធិ ''ប្តូរឈ្មោះអ្នកប្រើប្រាស់'')",
	'renameuserold' => 'ឈ្មោះអ្នកប្រើប្រាស់បច្ចុប្បន្ន ៖',
	'renameusernew' => 'ឈ្មោះអ្នកប្រើប្រាស់ថ្មី៖',
	'renameuserreason' => 'មូលហេតុ៖',
	'renameusermove' => 'ប្តូរទីតាំងទំព័រអ្នកប្រើប្រាស់និងទំព័រពិភាក្សា(រួមទាំងទំព័ររងផងដែរ)ទៅឈ្មោះថ្មី',
	'renameusersuppress' => 'កុំបង្កើតការបញ្ជូនបន្តទៅឈ្មោះថ្មី',
	'renameuserreserve' => 'ហាមឃាត់គណនីចាស់ពីការប្រើប្រាស់នាពេលអនាគត',
	'renameuserwarnings' => 'បម្រាម​៖',
	'renameuserconfirm' => 'បាទ/ចាស៎ សូមប្តូរឈ្មោះអ្នកប្រើប្រាស់នេះ',
	'renameusersubmit' => 'ដាក់ស្នើ',
	'renameusererrordoesnotexist' => 'អ្នកប្រើប្រាស់ "<nowiki>$1</nowiki>" មិនមាន ។',
	'renameusererrorexists' => 'អ្នកប្រើប្រាស់ "<nowiki>$1</nowiki>" មានហើយ ។',
	'renameusererrorinvalid' => 'ឈ្មោះអ្នកប្រើប្រាស់ "<nowiki>$1</nowiki>" មិនត្រឹមត្រូវ ។',
	'renameuser-error-request' => 'មានបញ្ហា​ចំពោះការទទួលសំណើ​។ សូមត្រឡប់ក្រោយ ហើយព្យាយាមម្តងទៀត​។',
	'renameuser-error-same-user' => 'អ្នកមិនអាចប្តូរឈ្មោះអ្នកប្រើប្រាស់ទៅជាឈ្មោះដូចមុនបានទេ។',
	'renameusersuccess' => 'អ្នកប្រើប្រាស់ "<nowiki>$1</nowiki>" ត្រូវបានប្តូរឈ្មោះទៅ "<nowiki>$2</nowiki>"។',
	'renameuser-page-exists' => 'ទំព័រ $1 មានហើយ មិនអាចសរសេរជាន់ពីលើដោយស្វ័យប្រវត្តិទេ។',
	'renameuser-page-moved' => 'ទំព័រ$1ត្រូវបានប្តូរទីតាំងទៅ$2ហើយ។',
	'renameuser-page-unmoved' => 'ទំព័រ$1មិនអាចប្តូរទីតាំងទៅ$2បានទេ។',
	'log-name-renameuser' => 'កំនត់ហេតុនៃការប្តូរឈ្មោះអ្នកប្រើប្រាស់',
	'renameuser-move-log' => 'បានប្តូរទីតាំងទំព័រដោយស្វ័យប្រវត្តិក្នុងខណៈពេលប្តូរឈ្មោះអ្នកប្រើប្រាស់ "[[User:$1|$1]]" ទៅ "[[User:$2|$2]]"',
	'right-renameuser' => 'ប្ដូរឈ្មោះអ្នកប្រើប្រាស់នានា',
	'renameuser-renamed-notice' => 'ឈ្មោះរបស់អ្នកប្រើប្រាស់នេះត្រូវបានប្ដូររួចហើយ។

ខាងក្រោមនេះជាកំណត់ហេតុនៃការប្ដូរឈ្មោះ។',
);

/** Kannada (ಕನ್ನಡ)
 * @author Nayvik
 * @author Shushruth
 */
$messages['kn'] = array(
	'renameuser' => 'ಸದಸ್ಯರನ್ನು ಮರುನಾಮಕರಣ ಮಾಡಿ',
	'renameuserwarnings' => 'ಎಚ್ಚರಿಕೆಗಳು:',
);

/** Korean (한국어)
 * @author Albamhandae
 * @author Ficell
 * @author Klutzy
 * @author Kwj2772
 * @author ToePeu
 * @author 아라
 */
$messages['ko'] = array(
	'renameuser' => '사용자 이름 바꾸기',
	'renameuser-linkoncontribs' => '이름 바꾸기',
	'renameuser-linkoncontribs-text' => '이 사용자의 계정 이름을 바꿉니다.',
	'renameuser-desc' => "사용자 이름을 바꾸기를 위한 [[Special:Renameuser|특수 문서]]를 추가합니다 ('''renameuser''' 권한 필요)",
	'renameuserold' => '기존 사용자 이름:',
	'renameusernew' => '새 사용자 이름:',
	'renameuserreason' => '이유:',
	'renameusermove' => '사용자 문서와 토론 문서, 하위 문서를 새 사용자 이름으로 이동하기',
	'renameusersuppress' => '새 이름으로 넘겨주기를 만들지 않기',
	'renameuserreserve' => '나중에 이전의 이름이 사용되지 않도록 차단하기',
	'renameuserwarnings' => '경고:',
	'renameuserconfirm' => '예, 이름을 바꿉니다.',
	'renameusersubmit' => '바꾸기',
	'renameuser-submit-blocklog' => '사용자 차단 기록 보이기',
	'renameusererrordoesnotexist' => '"<nowiki>$1</nowiki>" 사용자가 존재하지 않습니다.',
	'renameusererrorexists' => '"<nowiki>$1</nowiki>" 사용자가 이미 존재합니다.',
	'renameusererrorinvalid' => '"<nowiki>$1</nowiki>" 사용자 이름이 잘못되었습니다.',
	'renameuser-error-request' => '요청을 정상적으로 전송하지 못했습니다.
뒤로 가서 다시 시도하세요.',
	'renameuser-error-same-user' => '이전의 이름과 같은 이름으로는 바꿀 수 없습니다.',
	'renameusersuccess' => '"<nowiki>$1</nowiki>" 사용자를 "<nowiki>$2</nowiki>" 사용자로 이름을 바꾸었습니다.',
	'renameuser-page-exists' => '$1 문서가 이미 존재하여 자동으로 이동하지 못했습니다.',
	'renameuser-page-moved' => '$1 문서를 $2 문서로 옮겼습니다.',
	'renameuser-page-unmoved' => '$1 문서를 $2 문서로 이동하지 못했습니다.',
	'log-name-renameuser' => '사용자 이름 바꾸기 기록',
	'log-description-renameuser' => '사용자 이름을 바꾼 기록입니다.',
	'logentry-renameuser-renameuser' => '$1 사용자가 $4 사용자({{PLURAL:$6|편집 $6회}})의 이름을 $5(으)로 {{GENDER:$2|바꾸었습니다}}',
	'logentry-renameuser-renameuser-legacier' => '$1 사용자가 $4 사용자의 이름을 $5(으)로 바꾸었습니다',
	'renameuser-move-log' => '"[[User:$1|$1]]" 사용자를 "[[User:$2|$2]]" 사용자로 바꾸면서 문서를 자동으로 옮겼습니다',
	'action-renameuser' => '사용자 이름을 바꿀',
	'right-renameuser' => '사용자 이름 바꾸기',
	'renameuser-renamed-notice' => '이 사용자의 이름을 바꾸었습니다.
아래의 이름 바꾸기 기록을 참고하십시오.',
);

/** Colognian (Ripoarisch)
 * @author Purodha
 */
$messages['ksh'] = array(
	'renameuser' => 'Metmaacher ömdäufe',
	'renameuser-linkoncontribs' => 'Metmaacher ömnänne',
	'renameuser-linkoncontribs-text' => 'Heh dä Metmaacher ömnänne',
	'renameuser-desc' => '[[Special:Renameuser|Metmaacher ömdäufe]] — ävver do bruch mer et Rääsch „<i lang=en">renameuser</i>“ för.',
	'renameuserold' => 'Dä ahle Metmaacher-Name',
	'renameusernew' => 'Dä neue Metmaacher-Name',
	'renameuserreason' => 'Jrund för et Ömdäufe:', # Fuzzy
	'renameusermove' => 'De Metmaachersigg met Klaaf- un Ungersigge op dä neue Metmaacher-Name ömstelle',
	'renameusersuppress' => 'Donn kein Ömleidung op dä neue Name aanlääje',
	'renameuserreserve' => 'Donn dä Name fun dämm Metmaacher dobei sperre, dat_e nit norrens neu aanjemelldt weed.',
	'renameuserwarnings' => 'Warnunge:',
	'renameuserconfirm' => 'Jo, dunn dä Metmaacher ömbenenne un em singe Name ändere',
	'renameusersubmit' => 'Ömdäufe!',
	'renameuser-submit-blocklog' => 'Logbooch met Spärre för dä Metmaacher',
	'renameusererrordoesnotexist' => 'Ene Metmaacher „<nowiki>$1</nowiki>“ kenne mer nit.',
	'renameusererrorexists' => 'Ene Metmaacher met däm Name „<nowiki>$1</nowiki>“ jit et ald.',
	'renameusererrorinvalid' => 'Ene Metmaacher-Name eß „<nowiki>$1</nowiki>“ ävver nit, dä wöhr nit richtich.',
	'renameuser-error-request' => 'Mer hatte e Problem met Dingem Opdrach.
Bes esu joot un versöök et noch ens.',
	'renameuser-error-same-user' => 'Do Tuppes! Der ahle un der neue Name es dersellve. Do bengk et Ömdäufe jaanix.',
	'renameusersuccess' => 'Dä Metmaacher „<nowiki>$1</nowiki>“ es jetz op „<nowiki>$2</nowiki>“ ömjedäuf.',
	'renameuser-page-exists' => 'De Sigg $1 es ald doh, un mer könne se nit automatesch övverschrieve',
	'renameuser-page-moved' => 'De Sigg wood vun „$1“ op „$2“ ömjenannt.',
	'renameuser-page-unmoved' => 'Di Sigg „$1“ kunnt nit op „$2“ ömjenannt wääde.',
	'log-name-renameuser' => 'Logboch vum Metmaacher-Ömdäufe',
	'log-description-renameuser' => 'Dat es et Logboch vun de ömjedäufte Metmaachere',
	'renameuser-move-log' => 'Di Sigg weet automatesch ömjenannt weil mer dä Metmaacher „[[User:$1|$1]]“ op „[[User:$2|$2]]“ öm am däufe sin.',
	'action-renameuser' => 'Metmaacher ömdäufe',
	'right-renameuser' => 'Metmaacher ömdäufe',
	'renameuser-renamed-notice' => 'Dä Metmaacher es ömjenannt woode.
Dat kanns De unge en däm Ußzoch uss_em Logbooch vum Metmacher Ömnänne fenge.',
);

/** Kurdish (Latin script) (Kurdî (latînî)‎)
 * @author George Animal
 * @author Ghybu
 * @author Gomada
 */
$messages['ku-latn'] = array(
	'renameuser' => 'Navê bikarhêner biguherîne',
	'renameuser-linkoncontribs' => 'navê bikarhêner biguherîne',
	'renameuser-linkoncontribs-text' => 'Navê vî bikarhênerî biguherîne',
	'renameuserold' => 'Navê niha:',
	'renameusernew' => 'Navê nû:',
	'renameuserreason' => 'Sedema navguherandinê:', # Fuzzy
	'renameusermove' => 'Rûpelên bikarhêner û gotûbêjê xwe (û binrûpelên xwe) bigerîne berve navê nû',
	'renameuserwarnings' => 'Hişyarî:',
	'renameuserconfirm' => 'Erê, navê vî bikarhênerî biguherîne',
	'renameusersubmit' => 'Nav biguherîne',
	'renameusererrordoesnotexist' => 'Bikarhêner "<nowiki>$1</nowiki>" tune ye.',
	'renameusererrorexists' => 'Bikarhêner "<nowiki>$1</nowiki>" berê heye.',
	'renameusererrorinvalid' => 'Navê "<nowiki>$1</nowiki>" ji bikarhêneran re nayê qebûlkirin.',
	'renameusersuccess' => 'Navê bikarhênerê "<nowiki>$1</nowiki>" bû "<nowiki>$2</nowiki>"',
	'renameuser-page-exists' => 'Rûpelê $1 berê heye û nikane otomatîk were guherandin.',
	'renameuser-page-moved' => 'Navê $1 weke $2 hate guhertin.',
	'renameuser-page-unmoved' => 'Rûpela $1 nikanî çûba ciha $2.',
	'log-name-renameuser' => 'Guhertina navê bikarhêner',
	'renameuser-move-log' => 'Otomatîk hate guherandin, ji ber ku "[[User:$1|$1]]" navê xwe guherand û niha bû "[[User:$2|$2]]"',
	'right-renameuser' => 'Navê bikarhêneran biguherîne:',
);

/** Kyrgyz (Кыргызча)
 * @author Chorobek
 */
$messages['ky'] = array(
	'renameuser' => 'Колдонуучунун атын өзгөрт',
	'renameuser-linkoncontribs' => 'колдонуучунун атын өзгөрт',
	'renameuser-linkoncontribs-text' => 'Колдонуучунун атын өзгөрт',
	'renameuser-desc' => "Колдонуучуну атын өзгөртүү үчүн (''renameuser'' укугу талап кылынат) [[Special:Renameuser|special page]] кошулат",
	'renameuserold' => 'Азыркы аты:',
	'renameusernew' => 'Жаңы аты',
	'renameuserreason' => 'Атты өзгөртүүнүн себеби:', # Fuzzy
	'renameusermove' => 'Колдонуучу жана анын талкуу баракчаларын (ички баракчалары менен чогуу) жаңы атка өткөз',
	'renameusersuppress' => 'Жаңы атка багыттама койбо',
	'renameuserreserve' => 'Колдонуучунун эски атын кийин колдонуу үчүн ээлеп кой',
	'renameuserwarnings' => 'Эскертүүлөр:',
	'renameuserconfirm' => 'Ооба, колдонуучунун атын өзгөрт',
	'renameusersubmit' => 'Аткар',
);

/** Latin (Latina)
 * @author MF-Warburg
 * @author SPQRobin
 * @author UV
 */
$messages['la'] = array(
	'renameuser' => 'Usorem renominare',
	'renameuserold' => 'Praesente nomen usoris:',
	'renameusernew' => 'Novum nomen usoris:',
	'renameuserreason' => 'Causa:',
	'renameusermove' => 'Movere paginas usoris et disputationis (et subpaginae) in nomen novum',
	'renameusersubmit' => 'Renominare',
	'renameusererrordoesnotexist' => 'Usor "<nowiki>$1</nowiki>" non existit',
	'renameusererrorexists' => 'Usor "<nowiki>$1</nowiki>" iam existit',
	'renameusererrorinvalid' => 'Nomen usoris "<nowiki>$1</nowiki>" irritum est',
	'renameusersuccess' => 'Usor "<nowiki>$1</nowiki>" renominatus est in "<nowiki>$2</nowiki>"',
	'renameuser-page-exists' => 'Pagina $1 iam existit et non potest automatice deleri.',
	'renameuser-page-moved' => 'Pagina $1 mota est ad $2.',
	'renameuser-page-unmoved' => 'Pagina $1 ad $2 moveri non potuit.',
	'log-name-renameuser' => 'Index renominationum usorum',
	'renameuser-move-log' => 'movit paginam automatice in renominando usorem "[[User:$1|$1]]" in "[[User:$2|$2]]"',
	'right-renameuser' => 'Usores renominare',
	'renameuser-renamed-notice' => 'Hic usor renominatus est.
Commodule notatio renominationum usoris subter datur.',
);

/** Luxembourgish (Lëtzebuergesch)
 * @author Les Meloures
 * @author Robby
 * @author Soued031
 */
$messages['lb'] = array(
	'renameuser' => 'Benotzernumm änneren',
	'renameuser-linkoncontribs' => 'Benotzer ëmbenennen',
	'renameuser-linkoncontribs-text' => 'Dëse Benotzer ëmbenennen',
	'renameuser-desc' => "Benotzernumm änneren (Dir braucht dofir  ''renameuser''-Rechter)",
	'renameuserold' => 'Aktuelle Benotzernumm:',
	'renameusernew' => 'Neie Benotzernumm:',
	'renameuserreason' => 'Grond:',
	'renameusermove' => 'Benotzer- an Diskussiounssäiten (an déi jeweileg Ënnersäiten) op den neie Benotzernumm réckelen',
	'renameusersuppress' => 'Maacht keng Viruleedungen op den neien Numm',
	'renameuserreserve' => 'Den ale Benotzernumm fir de weitere Gebrauch spären',
	'renameuserwarnings' => 'Warnungen:',
	'renameuserconfirm' => 'Jo, Benotzer ëmbenennen',
	'renameusersubmit' => 'Ëmbenennen',
	'renameuser-submit-blocklog' => 'Lëscht vun de Späre fir de Benotzer weisen',
	'renameusererrordoesnotexist' => 'De Benotzer "<nowiki>$1</nowiki>" gëtt et net.',
	'renameusererrorexists' => 'De Benotzer "<nowiki>$1</nowiki>" gët et schonn.',
	'renameusererrorinvalid' => 'De Benotzernumm "<nowiki>$1</nowiki>" kann net benotzt ginn.',
	'renameuser-error-request' => 'Et gouf e Problem mat ärer Ufro.
Gitt w.e.g. zréck a versicht et nach eng Kéier.',
	'renameuser-error-same-user' => 'Dir kënnt kee Benotzernumm änneren, an him deselwechten Numm erëmginn.',
	'renameusersuccess' => 'De Benotzer "<nowiki>$1</nowiki>" gouf "<nowiki>$2</nowiki>" ëmbenannt.',
	'renameuser-page-exists' => "D'Säit $1 gëtt et schonn a kann net automatesch iwwerschriwwe ginn.",
	'renameuser-page-moved' => "D'Säit $1 gouf op $2 geréckelt.",
	'renameuser-page-unmoved' => "D'Säit $1 konnt net op $2 geréckelt ginn.",
	'log-name-renameuser' => 'Logbuch vun den Ännerunge vum Benotzernumm',
	'log-description-renameuser' => "Dëst ass d'Logbuch vun den Ännerunge vun de Benotzernimm.",
	'logentry-renameuser-renameuser' => '$1 {{GENDER:$2|huet}} de Benotzer $4 ({{PLURAL:$6|$6 Ännerung|$6 Ännerungen}}) op $5 ëmbenannt',
	'logentry-renameuser-renameuser-legacier' => '£1 huet de Benotzer $4 op $5 ëmbenannt',
	'renameuser-move-log' => 'Duerch d\'Réckele vum Benotzer "[[User:$1|$1]]" op "[[User:$2|$2]]" goufen déi folgend Säiten automatesch matgeréckelt:',
	'action-renameuser' => 'Benotzer ëmbenennen',
	'right-renameuser' => 'Benotzer ëmbenennen',
	'renameuser-renamed-notice' => "Dëse Benotzer gouf ëmbenannt.
D'Logbuch mat den Ëmbenunngen ass hei ënnendrënner.",
);

/** Limburgish (Limburgs)
 * @author Matthias
 * @author Ooswesthoesbes
 * @author Pahles
 * @author Tibor
 */
$messages['li'] = array(
	'renameuser' => 'Herneum gebroeker',
	'renameuser-linkoncontribs' => 'herneum gebroeker',
	'renameuser-linkoncontribs-text' => 'Hernöm deze broeker',
	'renameuser-desc' => "Voog 'n [[Special:Renameuser|speciaal pazjwna]] toe óm 'ne gebroeker te hernömme (doe höbs hiej ''renameuser''-rech veur neudig)",
	'renameuserold' => 'Hujige gebroekersnaam:',
	'renameusernew' => 'Nuje gebroekersnaam:',
	'renameuserreason' => 'Ree veur hernömme:', # Fuzzy
	'renameusermove' => "De gebroekerspazjena en euverlèkpazjena (en eventueel subpazjena's) hernömmme nao de nuje gebroekersnaam",
	'renameusersuppress' => 'Maak gein redireks nao de nuje naam',
	'renameuserreserve' => 'Veurkómme det de aaje gebroeker opnuuj wörd geregistreerd',
	'renameuserwarnings' => 'Waarschuwinge:',
	'renameuserconfirm' => 'Jao, hernaam gebroeker',
	'renameusersubmit' => 'Herneum',
	'renameuser-submit-blocklog' => 'Tuin bloklogbook veure gebroeker',
	'renameusererrordoesnotexist' => 'De gebroeker "<nowiki>$1</nowiki>" besteit neet.',
	'renameusererrorexists' => 'De gebroeker "<nowiki>$1</nowiki>" besteit al.',
	'renameusererrorinvalid' => 'De gebroekersnaam "<nowiki>$1</nowiki>" is óngeljig.',
	'renameuser-error-request' => "d'r Woor 'n perbleem bie 't óntvange vanne aanvraog. Lèvver trök te gaon en opnuuj te perbere/",
	'renameuser-error-same-user' => 'De kèns gein gebroekers herneume nao dezelfde naam.',
	'renameusersuccess' => 'De gebroeker "<nowiki>$1</nowiki>" is hernömp nao "<nowiki>$2</nowiki>".',
	'renameuser-page-exists' => 'De pazjena $1 besteit al en kan neet automatisch euversjreve waere,',
	'renameuser-page-moved' => 'De pagina $1 is hernömp nao $2.',
	'renameuser-page-unmoved' => 'De pagina $1 kon neet hernömp waere nao $2.',
	'log-name-renameuser' => 'Logbook gebroekersnaamwieziginge',
	'renameuser-move-log' => 'Automatisch hernömp bie \'t wiezige van gebroeker "[[User:$1|$1]]" nao "[[User:$2|$2]]"',
	'action-renameuser' => 'gebroekers van naam te verangere',
	'right-renameuser' => 'Gebroekers hernaome',
	'renameuser-renamed-notice' => "Deze gebroeker is herneump.
Relevante regels oet 't logbook staon hieónger.",
);

/** Lithuanian (lietuvių)
 * @author Eitvys200
 * @author Homo
 * @author Hugo.arg
 * @author Matasg
 */
$messages['lt'] = array(
	'renameuser' => 'Pervadinti naudotoją',
	'renameuser-linkoncontribs' => 'Pervadinti naudotoją',
	'renameuser-linkoncontribs-text' => 'Pervardyti šį vartotoją',
	'renameuser-desc' => "Pervadinti naudotoją (reikia ''pervadintojo'' teisių)",
	'renameuserold' => 'Esamas naudotojo vardas:',
	'renameusernew' => 'Naujas naudotojo vardas:',
	'renameuserreason' => 'Pervadinimo priežastis:', # Fuzzy
	'renameusermove' => 'Perkelti naudotojo ir aptarimo puslapius (bei jo subpuslapius) prie naujo vardo',
	'renameuserreserve' => 'Užblokuoti senąjį naudotojo vardą nuo galimybių naudoti ateityje',
	'renameuserwarnings' => 'Įspėjimai:',
	'renameuserconfirm' => 'Taip, pervadinti naudotoją',
	'renameusersubmit' => 'Patvirtinti',
	'renameusererrordoesnotexist' => 'Naudotojas "<nowiki>$1</nowiki>" neegzistuoja.',
	'renameusererrorexists' => 'Naudotojas "<nowiki>$1</nowiki>" jau egzistuoja.',
	'renameusererrorinvalid' => 'Naudotojo vardas "<nowiki>$1</nowiki>" netinkamas.',
	'renameuser-error-request' => 'Iškilo prašymo gavimo problema.
Prašome eiti atgal ir bandyti iš naujo.',
	'renameuser-error-same-user' => 'Jūs negalite pervadinti naudotojo į tokį pat vardą, kaip pirmiau.',
	'renameusersuccess' => 'Naudotojas "<nowiki>$1</nowiki>" buvo pervadintas į "<nowiki>$2</nowiki>".',
	'renameuser-page-exists' => 'Puslapis $1 jau egzistuoja ir negali būti automatiškai perrašytas.',
	'renameuser-page-moved' => 'Puslapis $1 buvo perkeltas į $2.',
	'renameuser-page-unmoved' => 'Puslapis $1 negali būti perkeltas į $2.',
	'log-name-renameuser' => 'Naudotojų pervadinimo sąrašas',
	'renameuser-move-log' => 'Puslapis automatiškai perkeltas, kai buvo pervadinamas naudotojas "[[User:$1|$1]]" į "[[User:$2|$2]]"',
	'action-renameuser' => 'pervadinti naudotojus',
	'right-renameuser' => 'Pervadinti naudotojus',
);

/** Latvian (latviešu)
 * @author Papuass
 * @author Xil
 */
$messages['lv'] = array(
	'renameuser' => 'Pārsaukt lietotāju',
	'renameuser-linkoncontribs' => 'pārsaukt lietotāju',
	'renameuser-linkoncontribs-text' => 'Pārsaukt šo lietotāju',
	'renameuserold' => 'Pašreizējais lietotāja vārds:',
	'renameusernew' => 'Jaunais lietotāja vārds:',
	'renameuserreason' => 'Iemesls:',
	'renameuserreserve' => 'Bloķēt veco lietotājvārdu no turpmākas izmantošanas',
	'renameuserwarnings' => 'Brīdinājumi:',
	'renameuserconfirm' => 'Jā, pārdēvēt lietotāju',
	'renameusersubmit' => 'Iesniegt',
	'renameusererrorexists' => 'Lietotājs "<nowiki>$1</nowiki>" jau ir.',
	'renameusersuccess' => 'Lietotājs "<nowiki>$1</nowiki>" pārdēvēts par "<nowiki>$2</nowiki>".',
	'log-name-renameuser' => 'Lietotāju pārdēvēšanas reģistrs',
	'log-description-renameuser' => 'Lietotājvārdu maiņas reģistrs',
	'action-renameuser' => 'pārsaukt lietotājus',
	'right-renameuser' => 'Pārsaukt lietotājus',
);

/** Malagasy (Malagasy)
 * @author Jagwar
 */
$messages['mg'] = array(
	'renameuser' => "Hanova ny anaran'ny mpikambana",
	'renameuser-linkoncontribs' => "Manova ny anaran'ny mpikambana",
	'renameuser-linkoncontribs-text' => "Hanova ny anaran'ity mpikambana ity",
	'renameuserold' => 'Anaram-pikambana ankehitriny :',
	'renameusernew' => 'Anaram-pikambana vaovao :',
	'renameuserreason' => "Anton'ny fanovana anarana :", # Fuzzy
	'renameusermove' => "Afindrany pejim-pikambana any amin'ny anarana vaovao",
	'renameuserwarnings' => 'Fampitandremana :',
	'renameuserconfirm' => 'Eny, soloy anarana ilay mpikambana',
	'renameusersubmit' => 'Alefa',
	'log-name-renameuser' => 'Laogim-panovana anaram-pikambana',
	'right-renameuser' => "Manova ny anaran'ny mpikambana",
);

/** Minangkabau (Baso Minangkabau)
 * @author Iwan Novirion
 */
$messages['min'] = array(
	'log-name-renameuser' => 'Log panggantian namo pangguno',
	'log-description-renameuser' => 'Di bawah ko log panggantian namo pangguno',
	'renameuser-move-log' => 'Sacaro otomatih mamindahan laman wakatu mangganti namo pangguno "[[User:$1|$1]]" manjadi "[[User:$2|$2]]"',
	'right-renameuser' => 'Mangganti namo pangguno',
);

/** Macedonian (македонски)
 * @author Bjankuloski06
 * @author Brest
 * @author Misos
 */
$messages['mk'] = array(
	'renameuser' => 'Преименувај корисник',
	'renameuser-linkoncontribs' => 'преименувај корисник',
	'renameuser-linkoncontribs-text' => 'Преименувај го корисников',
	'renameuser-desc' => "Додава [[Special:Renameuser|специјална страница]] за преименување на корисник (бара право на ''renameuser'')",
	'renameuserold' => 'Сегашно корисничко име:',
	'renameusernew' => 'Ново корисничко име:',
	'renameuserreason' => 'Причина:',
	'renameusermove' => 'Премести корисничка страница и страници за разговор (и нивните потстраници) под новото име',
	'renameusersuppress' => 'Не создавај пренасочувања кон новото име',
	'renameuserreserve' => 'Блокирање на старото корисничко име, да не може да се користи во иднина',
	'renameuserwarnings' => 'Предупредувања:',
	'renameuserconfirm' => 'Да, преименувај го корисникот',
	'renameusersubmit' => 'Внеси',
	'renameuser-submit-blocklog' => 'Дневник на блокирања за корисникот',
	'renameusererrordoesnotexist' => 'Корисникот „<nowiki>$1</nowiki>“ не постои',
	'renameusererrorexists' => 'Корисникот „<nowiki>$1</nowiki>“ веќе постои',
	'renameusererrorinvalid' => 'Корисничкото име „<nowiki>$1</nowiki>“ не е важечко.',
	'renameuser-error-request' => 'Се јави проблем при примањето на барањето.
Вратете се и обидете се повторно.',
	'renameuser-error-same-user' => 'Не можете да го преименувате корисникот во име кое е исто како претходното.',
	'renameusersuccess' => 'Корисникот „<nowiki>$1</nowiki>“ е преименуван во „<nowiki>$2</nowiki>“',
	'renameuser-page-exists' => 'Страницата $1 веќе постои и не може автоматски да се замени со друга содржина.',
	'renameuser-page-moved' => 'Страницата $1 е преместена на $2.',
	'renameuser-page-unmoved' => 'Страницата $1 неможеше да се премести на $2.',
	'log-name-renameuser' => 'Дневник на преименувања на корисници',
	'log-description-renameuser' => 'Ово е дневник на преименувања на корисници',
	'logentry-renameuser-renameuser' => '$1 го {{GENDER:$2|преименуваше}} корисникот $4 ({{PLURAL:$6|$6 уредување|$6 уредувања}}) во $5',
	'logentry-renameuser-renameuser-legacier' => '$1 го преименуваше корисникот $4 во $5',
	'renameuser-move-log' => 'Автоматски преместена страница при преименување на корисникот „[[User:$1|$1]]“ во „[[User:$2|$2]]“',
	'action-renameuser' => 'преименување на корисници',
	'right-renameuser' => 'Преименување корисници',
	'renameuser-renamed-notice' => 'Овој корисник е преименуван.
Подолу е приложен дневникот на преименување за споредба.',
);

/** Malayalam (മലയാളം)
 * @author Praveenp
 * @author Shijualex
 */
$messages['ml'] = array(
	'renameuser' => 'ഉപയോക്താവിനെ പുനർനാമകരണം ചെയ്യുക',
	'renameuser-linkoncontribs' => 'ഉപയോക്തൃ പുനർനാമകരണം',
	'renameuser-linkoncontribs-text' => 'ഈ ഉപയോക്താവിന്റെ പേരു മാറ്റുക',
	'renameuser-desc' => "ഉപയോക്താവിനെ പുനർനാമകരണം ചെയ്യുവാനുള്ള (''പുനർനാമകരണ'' അവകാശം വേണം) ഒരു [[Special:Renameuser|പ്രത്യേക താൾ]] ചേർക്കുന്നു",
	'renameuserold' => 'ഇപ്പോഴത്തെ ഉപയോക്തൃനാമം:',
	'renameusernew' => 'പുതിയ ഉപയോക്തൃനാമം:',
	'renameuserreason' => 'കാരണം:',
	'renameusermove' => 'നിലവിലുള്ള ഉപയോക്തൃതാളും, ഉപയോക്താവിന്റെ സം‌വാദം താളും (ഉപതാളുകൾ അടക്കം) പുതിയ നാമത്തിലേക്കു മാറ്റുക.',
	'renameusersuppress' => 'പുതിയ നാമത്തിലേയ്ക്ക് തിരിച്ചുവിടലുകളൊന്നും സൃഷ്ടിക്കരുത്',
	'renameuserreserve' => 'പഴയ ഉപയോക്തൃനാമം ഭാവിയിൽ ഉപയോഗിക്കുന്നതു തടയുക',
	'renameuserwarnings' => 'മുന്നറിയിപ്പുകൾ:',
	'renameuserconfirm' => 'അതെ, ഉപയോക്താവിനെ പുനർനാമകരണം ചെയ്യുക',
	'renameusersubmit' => 'സമർപ്പിക്കുക',
	'renameuser-submit-blocklog' => 'ഉപയോക്താവിനെക്കുറിച്ചുള്ള തടയൽ രേഖ പ്രദർശിപ്പിക്കുക',
	'renameusererrordoesnotexist' => '"<nowiki>$1</nowiki>"  എന്ന ഉപയോക്താവ് നിലവിലില്ല.',
	'renameusererrorexists' => '"<nowiki>$1</nowiki>" എന്ന ഉപയോക്താവ് നിലവിലുണ്ട്.',
	'renameusererrorinvalid' => '"<nowiki>$1</nowiki>" എന്ന ഉപയോക്തൃനാമം അസാധുവാണ്‌.',
	'renameuser-error-request' => 'അപേക്ഷ സ്വീകരിക്കുമ്പോൾ പിഴവ് സം‌ഭവിച്ചു. ദയവായി തിരിച്ചു പോയി വീണ്ടും പരിശ്രമിക്കുക.',
	'renameuser-error-same-user' => 'നിലവിലുള്ള ഒരു ഉപയോക്തൃനാമത്തിലേക്കു വേറൊരു ഉപയോക്തൃനാമം പുനർനാമകരണം നടത്തുവാൻ സാധിക്കില്ല.',
	'renameusersuccess' => '"<nowiki>$1</nowiki>" എന്ന ഉപയോക്താവിനെ "<nowiki>$2</nowiki>" എന്ന നാമത്തിലേക്കു പുനർനാമകരണം ചെയ്തിരിക്കുന്നു.',
	'renameuser-page-exists' => '$1 എന്ന താൾ നിലവിലുള്ളതിനാൽ അതിനെ യാന്ത്രികമായി മാറ്റാൻ കഴിയില്ല.',
	'renameuser-page-moved' => '$1 എന്ന താൾ $2 എന്നാക്കിയിരിക്കുന്നു.',
	'renameuser-page-unmoved' => '$1 എന്ന താൾ $2 എന്നാക്കാൻ സാദ്ധ്യമല്ല.',
	'log-name-renameuser' => 'ഉപയോക്തൃ പുനർനാമകരണ രേഖ',
	'log-description-renameuser' => 'ഈ പ്രവർത്തനരേഖ ഉപയോക്തൃനാമം പുനർനാമകരണം നടത്തിയതിന്റേതാണ്‌.',
	'logentry-renameuser-renameuser' => '$4 ({{PLURAL:$6|$6 തിരുത്ത്|$6 തിരുത്തുകൾ}}) എന്ന ഉപയോക്താവിനെ $1, $5 എന്ന്  {{GENDER:$2|പുനർനാമകരണം ചെയ്തിരിക്കുന്നു}}',
	'logentry-renameuser-renameuser-legacier' => '$4 എന്ന ഉപയോക്താവിനെ $5 എന്ന് $1 പുനർനാമകരണം ചെയ്തു',
	'renameuser-move-log' => '"[[User:$1|$1]]" എന്ന ഉപയോക്താവിനെ "[[User:$2|$2]]" എന്നു പുനർനാമകരണം ചെയ്തപ്പോൾ താൾ യാന്ത്രികമായി മാറ്റി.',
	'action-renameuser' => 'ഉപയോക്താക്കളുടെ പുനർനാമകരണം',
	'right-renameuser' => 'ഉപയോക്തൃ പുനർനാമകരണം',
	'renameuser-renamed-notice' => 'ഈ ഉപയോക്താവിനെ പുനർനാമകരണം ചെയ്തിരിക്കുന്നു.
പുനർനാമകരണ രേഖ അവലംബമായി പരിശോധിക്കാനായി താഴെ കൊടുത്തിരിക്കുന്നു.',
);

/** Mongolian (монгол)
 * @author Chinneeb
 */
$messages['mn'] = array(
	'renameusersubmit' => 'Явуулах',
);

/** Marathi (मराठी)
 * @author Kaajawa
 * @author Kaustubh
 * @author Rahuldeshmukh101
 * @author V.narsikar
 */
$messages['mr'] = array(
	'renameuser' => 'सदस्यनाम बदला',
	'renameuser-linkoncontribs' => 'सदस्यनाम बदला',
	'renameuser-linkoncontribs-text' => 'ह्या सदस्याचे नाव बदला',
	'renameuser-desc' => "सदस्यनाम बदला (यासाठी तुम्हाला ''सदस्यनाम बदलण्याचे अधिकार'' असणे आवश्यक आहे)",
	'renameuserold' => 'सध्याचे सदस्यनाम:',
	'renameusernew' => 'नवीन सदस्यनाम:',
	'renameuserreason' => 'नाम बदलण्याचे कारण:', # Fuzzy
	'renameusermove' => 'सदस्य तसेच सदस्य चर्चापान (तसेच त्यांची उपपाने) नवीन सदस्यनामाकडे स्थानांतरीत करा',
	'renameusersuppress' => 'नवीन नावाकडे पुर्ननिर्देशने तयार करू नका',
	'renameuserreserve' => 'जुने सदस्य खाते पुढील वापरासाठी अवरुद्ध करा',
	'renameuserwarnings' => 'ताकीद:',
	'renameuserconfirm' => 'होय, सदस्याचे नाव बदला',
	'renameusersubmit' => 'पाठवा',
	'renameusererrordoesnotexist' => '"<nowiki>$1</nowiki>" नावाचा सदस्य अस्तित्वात नाही.',
	'renameusererrorexists' => '"<nowiki>$1</nowiki>" नावाचा सदस्य अगोदरच अस्तित्वात आहे',
	'renameusererrorinvalid' => '"<nowiki>$1</nowiki>" हे नाव चुकीचे आहे.',
	'renameuser-error-request' => 'हे काम करताना त्रुटी आढळलेली आहे. कृपया मागे जाऊन परत प्रयत्न करा.',
	'renameuser-error-same-user' => 'तुम्ही एखाद्या सदस्याला परत पूर्वीच्या नावाकडे बदलू शकत नाही',
	'renameusersuccess' => '"<nowiki>$1</nowiki>" या सदस्याचे नाव "<nowiki>$2</nowiki>" ला बदललेले आहे.',
	'renameuser-page-exists' => '$1 हे पान अगोदरच अस्तित्वात आहे व आपोआप पुनर्लेखन करता येत नाही.',
	'renameuser-page-moved' => '$1 हे पान $2 मथळ्याखाली स्थानांतरीत केले.',
	'renameuser-page-unmoved' => '$1 हे पान $2 मथळ्याखाली स्थानांतरीत करू शकत नाही.',
	'log-name-renameuser' => 'सदस्यनाम बदल यादी',
	'renameuser-move-log' => '"[[User:$1|$1]]" ला "[[User:$2|$2]]" बदलताना आपोआप सदस्य पान स्थानांतरीत केलेले आहे.',
	'right-renameuser' => 'सदस्यांची नावे बदला',
	'renameuser-renamed-notice' => 'या सदस्यास पुनर्नामित करण्यात आले आहे.
पुनर्नामाचा क्रमलेख संदर्भासाठी खाली दिलेला आहे.',
);

/** Malay (Bahasa Melayu)
 * @author Anakmalaysia
 * @author Aurora
 * @author Aviator
 */
$messages['ms'] = array(
	'renameuser' => 'Tukar nama pengguna',
	'renameuser-linkoncontribs' => 'tukar nama pengguna',
	'renameuser-linkoncontribs-text' => 'Tukar nama pengguna ini',
	'renameuser-desc' => "Menukar nama pengguna (memerlukan hak ''renameuser'')",
	'renameuserold' => 'Nama semasa:',
	'renameusernew' => 'Nama baru:',
	'renameuserreason' => 'Sebab:',
	'renameusermove' => 'Pindahkan laman pengguna dan laman perbincangannya (berserta semua sublaman yang ada) ke nama baru',
	'renameusersuppress' => 'Jangan buat lencongan ke nama baru',
	'renameuserreserve' => 'Pelihara nama pengguna lama supaya tidak digunakan lagi',
	'renameuserwarnings' => 'Amaran:',
	'renameuserconfirm' => 'Ya, tukar nama pengguna ini',
	'renameusersubmit' => 'Hantar',
	'renameuser-submit-blocklog' => 'Tunjukkan log sekatan pengguna',
	'renameusererrordoesnotexist' => 'Pengguna "<nowiki>$1</nowiki>" tidak wujud.',
	'renameusererrorexists' => 'Pengguna "<nowiki>$1</nowiki>" telah pun wujud.',
	'renameusererrorinvalid' => 'Nama pengguna "<nowiki>$1</nowiki>" tidak sah.',
	'renameuser-error-request' => 'Berlaku masalah ketika menerima permintaan anda.
Sila undur dan cuba lagi.',
	'renameuser-error-same-user' => 'Anda tidak boleh menukar nama pengguna kepada nama yang sama.',
	'renameusersuccess' => 'Nama "<nowiki>$1</nowiki>" telah ditukar menjadi "<nowiki>$2</nowiki>".',
	'renameuser-page-exists' => 'Laman $1 telah pun wujud dan tidak boleh ditulis ganti secara automatik.',
	'renameuser-page-moved' => 'Laman $1 telah dipindahkan ke $2.',
	'renameuser-page-unmoved' => 'Laman $1 tidak dapat dipindahkan ke $2.',
	'log-name-renameuser' => 'Log penukaran nama pengguna',
	'log-description-renameuser' => 'Ini ialah log penukaran nama pengguna.',
	'logentry-renameuser-renameuser' => '$1 {{GENDER:$2|menukar nama}} pengguna $4 ($6 suntingan) kepada $5',
	'logentry-renameuser-renameuser-legacier' => '$1 menamakan pengguna $4 kepada $5',
	'renameuser-move-log' => 'Memindahkan laman secara automatik ketika menukar nama "[[User:$1|$1]]" menjadi "[[User:$2|$2]]"',
	'action-renameuser' => 'menukar nama pengguna',
	'right-renameuser' => 'Menukar nama pengguna',
	'renameuser-renamed-notice' => 'Pengguna ini telah dinamakan semula.
Log penukaran nama ditunjukkan di bawah sebagai rujukan.',
);

/** Maltese (Malti)
 * @author Chrisportelli
 * @author Roderick Mallia
 */
$messages['mt'] = array(
	'renameuser' => 'Semmi utent mill-ġdid',
	'renameuser-linkoncontribs' => 'semmi l-utent mill-ġdid',
	'renameuser-linkoncontribs-text' => "Erġa' semmi lil dan l-utent",
	'renameuser-desc' => "Iżżid [[Special:Renameuser|paġna speċjali]] sabiex issemmi utent mill-ġdid (huwa neċessarju li tħaddan id-dritt ''renameuser'')",
	'renameuserold' => 'Isem tal-utent attwali:',
	'renameusernew' => 'Isem tal-utent il-ġdid:',
	'renameuserreason' => 'Raġuni għall-bidla fl-isem:', # Fuzzy
	'renameusermove' => "Mexxi l-paġna tal-utent, il-paġna ta' diskussjoni u s-sottopaġni taħt l-isem il-ġdid",
	'renameusersuppress' => 'Toħloqx rindirizzi lejn l-isem il-ġdid',
	'renameuserreserve' => 'Imblokka l-użu tal-isem il-qadim fil-futur',
	'renameuserwarnings' => 'Twissijiet:',
	'renameuserconfirm' => 'Iva, semmi mill-ġdid dan l-utent',
	'renameusersubmit' => 'Ibgħat',
	'renameuser-submit-blocklog' => 'Uri r-reġistru tal-imblukkar għall-utent',
	'renameusererrordoesnotexist' => 'L-utent "<nowiki>$1</nowiki>" ma jeżistix.',
	'renameusererrorexists' => 'L-utent "<nowiki>$1</nowiki>" diġà jeżisti.',
	'renameusererrorinvalid' => 'L-isem tal-utent "<nowiki>$1</nowiki>" hu invalidu.',
	'renameuser-error-request' => "Kien hemm problema fl-ilqugħ tar-rikjesta tiegħek. Jekk jogħġbok mur lura u erġa' pprova.",
	'renameuser-error-same-user' => 'Ma tistax issemmi utent l-istess isem li kellu qabel.',
	'renameusersuccess' => 'L-utent "<nowiki>$1</nowiki>" issemma mill-ġdid għal "<nowiki>$2</nowiki>".',
	'renameuser-page-exists' => 'Il-paġna $1 diġà teżisti u ma tistax tiġi miktuba fuqha awtomatikament.',
	'renameuser-page-moved' => 'Il-paġna $1 tmexxiet lejn $2.',
	'renameuser-page-unmoved' => 'Il-paġna $1 ma setgħetx titmexxa lejn $2.',
	'log-name-renameuser' => 'Reġistru tal-utenti msemmijin mill-ġdid',
	'renameuser-move-log' => 'Paġna mmexxiha matul il-bidla tal-utent "[[User:$1|$1]]" għal "[[User:$2|$2]]"',
	'action-renameuser' => 'tbiddel l-ismijiet tal-utenti',
	'right-renameuser' => 'Ibiddel l-isem tal-utenti',
	'renameuser-renamed-notice' => "Dan l-utent reġa' ssemma mill-ġdid. Ir-reġistru tal-ismijiet ġodda huwa mogħti bħala referenza.",
);

/** Erzya (эрзянь)
 * @author Botuzhaleny-sodamo
 */
$messages['myv'] = array(
	'renameusernew' => 'Од лемесь:',
	'renameuserreserve' => 'Озавтомс ташто совицянь лементь саймес, тевс илязо нолдаво седе тов',
	'renameuserconfirm' => 'Истя, макст совицянтень од лем',
	'renameusersubmit' => 'Максомс',
	'renameusererrordoesnotexist' => '"<nowiki>$1</nowiki>" совицясь арась.',
);

/** Nahuatl (Nāhuatl)
 * @author Fluence
 */
$messages['nah'] = array(
	'renameusersubmit' => 'Tiquihuāz',
);

/** Min Nan Chinese (Bân-lâm-gú)
 */
$messages['nan'] = array(
	'renameuser' => 'Kái iōng-chiá ê miâ',
	'renameuser-page-moved' => '$1 í-keng sóa khì tī $2.',
);

/** Norwegian Bokmål (norsk bokmål)
 * @author Danmichaelo
 * @author Event
 * @author Nghtwlkr
 */
$messages['nb'] = array(
	'renameuser' => 'Døp om bruker',
	'renameuser-linkoncontribs' => 'døp om bruker',
	'renameuser-linkoncontribs-text' => 'Døp om denne brukeren',
	'renameuser-desc' => "Legger til en [[Special:Renameuser|spesialside]] for å døpe om en bruker (krever ''renameuser''-rettigheter)",
	'renameuserold' => 'Nåværende brukernavn:',
	'renameusernew' => 'Nytt brukernavn:',
	'renameuserreason' => 'Årsak for omdøping:', # Fuzzy
	'renameusermove' => 'Flytt bruker- og brukerdiskusjonssider (og deres undersider) til nytt navn',
	'renameusersuppress' => 'Ikke opprett omdirigeringer til det nye navnet',
	'renameuserreserve' => 'Blokker det gamle brukernavnet fra framtidig bruk',
	'renameuserwarnings' => 'Advarsler:',
	'renameuserconfirm' => 'Ja, døp om brukeren',
	'renameusersubmit' => 'Utfør',
	'renameuser-submit-blocklog' => 'Vis blokkeringslogg for bruker',
	'renameusererrordoesnotexist' => 'Brukeren «<nowiki>$1</nowiki>» finnes ikke.',
	'renameusererrorexists' => 'Brukeren «<nowiki>$1</nowiki>» finnes allerede.',
	'renameusererrorinvalid' => 'Brukernavnet «<nowiki>$1</nowiki>» er ugyldig.',
	'renameuser-error-request' => 'Det var et problem med å motta forespørselen.
Gå tilbake og prøv igjen.',
	'renameuser-error-same-user' => 'Du kan ikke gi en bruker samme navn som han/hun allerede har.',
	'renameusersuccess' => 'Brukeren «<nowiki>$1</nowiki>» har blitt omdøpt til «<nowiki>$2</nowiki>».',
	'renameuser-page-exists' => 'Siden $1 finnes allerede, og kunne ikke erstattes automatisk.',
	'renameuser-page-moved' => 'Siden $1 har blitt flyttet til $2.',
	'renameuser-page-unmoved' => 'Siden $1 kunne ikke flyttes til $2.',
	'log-name-renameuser' => 'Omdøpingslogg',
	'renameuser-move-log' => 'Flyttet side automatisk under omdøping av brukeren «[[User:$1|$1]]» til «[[User:$2|$2]]»',
	'action-renameuser' => 'endre navn på brukere',
	'right-renameuser' => 'Endre navn på brukere',
	'renameuser-renamed-notice' => 'Denne brukeren har fått endret navn.
Til informasjon er navnendringsloggen vist nedenfor.',
);

/** Low German (Plattdüütsch)
 * @author Slomox
 */
$messages['nds'] = array(
	'renameuser' => 'Brukernaam ännern',
	'renameuser-desc' => "Föögt en [[Special:Renameuser|Spezialsied]] to för dat Ne’en-Naam-Geven för Brukers (''renameuser''-Recht nödig)",
	'renameuserold' => 'Brukernaam nu:',
	'renameusernew' => 'Nee Brukernaam:',
	'renameuserreason' => 'Gründ för den ne’en Naam:', # Fuzzy
	'renameusermove' => 'Brukersieden op’n ne’en Naam schuven',
	'renameuserreserve' => 'Den olen Brukernaam dor vör schulen, dat he noch wedder nee anmellt warrt',
	'renameuserwarnings' => 'Wohrschauels:',
	'renameuserconfirm' => 'Jo, den Bruker en ne’en Naam geven',
	'renameusersubmit' => 'Ännern',
	'renameusererrordoesnotexist' => "Bruker ''<nowiki>$1</nowiki>'' gifft dat nich",
	'renameusererrorexists' => "Bruker ''<nowiki>$1</nowiki>'' gifft dat al",
	'renameusererrorinvalid' => "Brukernaam ''<nowiki>$1</nowiki>'' geiht nich",
	'renameuser-error-request' => 'Dat geev en Problem bi’t Överdragen vun de Anfraag. Gah trüch un versöök dat noch wedder.',
	'renameuser-error-same-user' => 'De ole un ne’e Brukernaam sünd gliek.',
	'renameusersuccess' => "Brukernaam ''<nowiki>$1</nowiki>'' op ''<nowiki>$2</nowiki>'' ännert",
	'renameuser-page-exists' => 'Siet $1 gifft dat al un kann nichautomaatsch överschreven warrn.',
	'renameuser-page-moved' => 'Siet $1 schaven na $2.',
	'renameuser-page-unmoved' => 'Siet $1 kunn nich na $2 schaven warrn.',
	'log-name-renameuser' => 'Ännerte-Brukernaams-Logbook',
	'renameuser-move-log' => "Siet bi dat Ännern vun’n Brukernaam ''[[User:$1|$1]]'' na ''[[User:$2|$2]]'' automaatsch schaven",
	'right-renameuser' => 'Brukers ne’en Naam geven',
);

/** Low Saxon (Netherlands) (Nedersaksies)
 * @author Servien
 */
$messages['nds-nl'] = array(
	'renameuser' => 'Gebruker herneumen',
	'renameuser-linkoncontribs' => 'gebruker herneumen',
	'renameuser-linkoncontribs-text' => 'Disse gebruker herneumen',
	'renameuser-desc' => "Der kömp n [[Special:Renameuser|spesiale zied]] bie um n gebruker te herneumen (je hebben hierveur t recht ''renameuser'' neudig)",
	'renameuserold' => 'Gebrukersnaam noen',
	'renameusernew' => 'Nieje gebrukersnaam:',
	'renameuserreason' => 'Reden:',
	'renameusermove' => 'Herneum gebruker en gebrukersziejen (en ziejen die deronder vallen) naor de nieje naam.',
	'renameusersuppress' => 'Gien deurverwiezingen maken naor de nieje naam',
	'renameuserreserve' => 'Veurkoemen dat de ouwe gebruker opniej eregistreerd wörden',
	'renameuserwarnings' => 'Waorschuwingen:',
	'renameuserconfirm' => 'Ja, herneum disse gebruker',
	'renameusersubmit' => 'Herneumen',
	'renameuser-submit-blocklog' => 'Blokkeerlogboek veur gebruker laoten zien',
	'renameusererrordoesnotexist' => 'De gebruker "<nowiki>$1</nowiki>" besteet niet.',
	'renameusererrorexists' => 'De gebrukersnaam "<nowiki>$1</nowiki>" is al in gebruuk.',
	'renameusererrorinvalid' => 'De gebrukersnaam "<nowiki>$1</nowiki>" is ongeldig.',
	'renameuser-error-request' => 'Der was n probleem bie t ontvangen van de anvraag.
Gao weerumme en probeer t nog es.',
	'renameuser-error-same-user' => 'Je kunnen gien gebruker herneumen naor dezelfde naam.',
	'renameusersuccess' => 'Gebruker "<nowiki>$1</nowiki>" is herneumd naor "<nowiki>$2</nowiki>".',
	'renameuser-page-exists' => 'De zied $1 besteet al en kan niet automaties overschreven wörden.',
	'renameuser-page-moved' => 'De zied $1 is herneumd naor $2.',
	'renameuser-page-unmoved' => 'De zied $1 kon niet herneumd wörden naor $2.',
	'log-name-renameuser' => 'Logboek gebrukersnaamwiezigingen',
	'log-description-renameuser' => 'Dit is n logboek mit wiezigingen van gebrukersnamen',
	'logentry-renameuser-renameuser' => '$1 {{GENDER:$2|hef}} gebruker $4 ($6 {{PLURAL:$6|bewarking|bewarkingen}}) herneumd naor $5',
	'logentry-renameuser-renameuser-legacier' => '$1 hef de gebruker $4 herneumd naor $5',
	'renameuser-move-log' => 'Zied is automaties verplaotst bie t herneumen van de gebruker "[[User:$1|$1]]" naor "[[User:$2|$2]]"',
	'action-renameuser' => 'gebrukers herneumen',
	'right-renameuser' => 'Gebrukers herneumen',
	'renameuser-renamed-notice' => 'Disse gebrukersnaam is herneumd.
Hieronder vie-j t herneumlogboek as referensie.',
);

/** Nepali (नेपाली)
 */
$messages['ne'] = array(
	'renameuserold' => 'अहिलेको प्रयोगकर्ता नाम:',
	'renameusernew' => 'नयाँ प्रयोगकर्ता नाम:',
	'renameusersubmit' => 'बुझाउने',
	'renameuser-page-exists' => '$1 पृष्ठ पहिले देखि नै रहेको छ र स्वत: अधिलेखन गर्न सकिएन ।',
	'renameuser-page-moved' => ' $1 पृष्ठलाई $2 मा सारियो ।',
	'renameuser-page-unmoved' => '$1 पृष्ठलाई $2 मा सार्न सकिएन ।',
);

/** Dutch (Nederlands)
 * @author Effeietsanders
 * @author SPQRobin
 * @author Siebrand
 */
$messages['nl'] = array(
	'renameuser' => 'Gebruiker hernoemen',
	'renameuser-linkoncontribs' => 'gebruiker hernoemen',
	'renameuser-linkoncontribs-text' => 'Deze gebruiker hernoemen',
	'renameuser-desc' => "Voegt een [[Special:Renameuser|speciale pagina]] toe om een gebruiker te hernoemen (u hebt hiervoor het recht ''renameuser'' nodig)",
	'renameuserold' => 'Huidige gebruikersnaam:',
	'renameusernew' => 'Nieuwe gebruikersnaam:',
	'renameuserreason' => 'Reden:',
	'renameusermove' => "De gebruikerspagina en overlegpagina (en eventuele subpagina's) hernoemen naar de nieuwe gebruikersnaam",
	'renameusersuppress' => 'Geen doorverwijzingen maken naar de nieuwe naam',
	'renameuserreserve' => 'Voorkomen dat de oude gebruiker opnieuw wordt geregistreerd',
	'renameuserwarnings' => 'Waarschuwingen:',
	'renameuserconfirm' => 'Ja, de gebruiker hernoemen',
	'renameusersubmit' => 'Opslaan',
	'renameuser-submit-blocklog' => 'Blokkeerlogboek voor gebruiker weergeven',
	'renameusererrordoesnotexist' => 'De gebruiker "<nowiki>$1</nowiki>" bestaat niet.',
	'renameusererrorexists' => 'De gebruiker "<nowiki>$1</nowiki>" bestaat al.',
	'renameusererrorinvalid' => 'De gebruikersnaam "<nowiki>$1</nowiki>" is ongeldig.',
	'renameuser-error-request' => 'Er was een probleem bij het ontvangen van de aanvraag.
Ga terug en probeer het opnieuw.',
	'renameuser-error-same-user' => 'U kunt geen gebruiker hernoemen naar dezelfde naam.',
	'renameusersuccess' => 'De gebruiker "<nowiki>$1</nowiki>" is hernoemd naar "<nowiki>$2</nowiki>".',
	'renameuser-page-exists' => 'De pagina $1 bestaat al en kan niet automatisch overschreven worden.',
	'renameuser-page-moved' => 'De pagina $1 is hernoemd naar $2.',
	'renameuser-page-unmoved' => 'De pagina $1 kon niet hernoemd worden naar $2.',
	'log-name-renameuser' => 'Logboek gebruikersnaamwijzigingen',
	'log-description-renameuser' => 'Hieronder staan gebruikersnamen die gewijzigd zijn.',
	'logentry-renameuser-renameuser' => '$1 {{GENDER:$2|heeft}} gebruiker $4 ($6 {{PLURAL:$6|bewerking|bewerkingen}}) hernoemd naar $5',
	'logentry-renameuser-renameuser-legacier' => '$1 heeft de gebruiker $4 hernoemd naar $5',
	'renameuser-move-log' => 'Automatisch hernoemd bij het wijzigen van gebruiker "[[User:$1|$1]]" naar "[[User:$2|$2]]"',
	'action-renameuser' => 'gebruikers te hernoemen',
	'right-renameuser' => 'Gebruikers hernoemen',
	'renameuser-renamed-notice' => 'Deze gebruiker is hernoemd.
Relevante regels uit het logboek gebruikersnaamwijzigingen worden hieronder ter referentie weergegeven.',
);

/** Nederlands (informeel)‎ (Nederlands (informeel)‎)
 * @author Siebrand
 */
$messages['nl-informal'] = array(
	'renameuser-error-same-user' => 'Je kunt geen gebruiker hernoemen naar dezelfde naam.',
);

/** Norwegian Nynorsk (norsk nynorsk)
 * @author Dittaeva
 * @author Gunnernett
 * @author Harald Khan
 * @author Njardarlogar
 * @author Ranveig
 */
$messages['nn'] = array(
	'renameuser' => 'Døyp om brukar',
	'renameuser-linkoncontribs' => 'døyp om brukar',
	'renameuser-desc' => "Legg til ei [[Special:Renameuser|spesialsida]] for å døypa om ein brukar (krev ''renameuser''-rettar)",
	'renameuserold' => 'Brukarnamn no:',
	'renameusernew' => 'Nytt brukarnamn:',
	'renameuserreason' => 'Årsak for omdøyping:', # Fuzzy
	'renameusermove' => 'Flytt brukar- og brukardiskusjonssider (og undersidene deira) til nytt namn',
	'renameusersuppress' => 'Ikkje opprett omdirigeringar til det nye namnet',
	'renameuserreserve' => 'Blokker det gamle brukarnamnet for framtidig bruk',
	'renameuserwarnings' => 'Åtvaringar:',
	'renameuserconfirm' => 'Ja, endra namn på brukaren',
	'renameusersubmit' => 'Utfør',
	'renameusererrordoesnotexist' => 'Brukaren «<nowiki>$1</nowiki>» finst ikkje.',
	'renameusererrorexists' => 'Brukaren «<nowiki>$1</nowiki>» finst allereie.',
	'renameusererrorinvalid' => 'Brukarnamnet «<nowiki>$1</nowiki>» er ikkje gyldig.',
	'renameuser-error-request' => 'Det var eit problem med å motta førespurnaden.
Gå attende og prøv på nytt.',
	'renameuser-error-same-user' => 'Du kan ikkje gje ein brukar same namn som han/ho har frå før.',
	'renameusersuccess' => 'Brukaren «<nowiki>$1</nowiki>» har fått brukarnamnet endra til «<nowiki>$2</nowiki>»',
	'renameuser-page-exists' => 'Sida $1 finst allereie og kan ikkje automatisk verta skrive over.',
	'renameuser-page-moved' => 'Sida $1 har vorte flytta til $2.',
	'renameuser-page-unmoved' => 'Sida $1 kunne ikkje verta flytta til $2.',
	'log-name-renameuser' => 'Logg over brukarnamnendringar',
	'renameuser-move-log' => 'Flytta sida automatisk under omdøyping av brukaren «[[User:$1|$1]]» til «[[User:$2|$2]]»',
	'right-renameuser' => 'Døypa om brukarar',
	'renameuser-renamed-notice' => 'Denne brukaren har fått nytt namn.
Til informasjon er omdøpingsloggen synt nedanfor.',
);

/** Northern Sotho (Sesotho sa Leboa)
 * @author Mohau
 */
$messages['nso'] = array(
	'renameuser' => 'Fetola leina la mošomiši',
	'renameuserold' => 'Leina la bjale la mošomiši:',
	'renameusernew' => 'Leina le lempsha la mošomiši:',
	'renameuserreason' => 'Lebaka lago fetola leina:', # Fuzzy
	'renameuser-page-moved' => 'Letlakala $1 le hudušitšwe go $2',
);

/** Occitan (occitan)
 * @author Boulaur
 * @author Cedric31
 */
$messages['oc'] = array(
	'renameuser' => "Tornar nomenar l'utilizaire",
	'renameuser-linkoncontribs' => "tornar nomenar l'utilizaire",
	'renameuser-linkoncontribs-text' => "Tornar nomenar l'utilizaire",
	'renameuser-desc' => "Torna nomenar un utilizaire (necessita los dreches de ''renameuser'')",
	'renameuserold' => "Nom actual de l'utilizaire :",
	'renameusernew' => "Nom novèl de l'utilizaire :",
	'renameuserreason' => 'Rason(s) del cambiament de nom :',
	'renameusermove' => 'Desplaçar totas las paginas de l’utilizaire cap al nom novèl',
	'renameuserreserve' => 'Reservar lo nom ancian per un usatge futur',
	'renameuserwarnings' => 'Avertiments :',
	'renameuserconfirm' => 'Òc, tornar nomenar l’utilizaire',
	'renameusersubmit' => 'Sometre',
	'renameusererrordoesnotexist' => "Lo nom d'utilizaire « <nowiki>$1</nowiki> » es pas valid",
	'renameusererrorexists' => "Lo nom d'utilizaire « <nowiki>$1</nowiki> » existís ja",
	'renameusererrorinvalid' => "Lo nom d'utilizaire « <nowiki>$1</nowiki> » existís pas",
	'renameuser-error-request' => 'Un problèma existís amb la recepcion de la requèsta. Tornatz en rèire e ensajatz tornamai.',
	'renameuser-error-same-user' => 'Podètz pas tornar nomenar un utilizaire amb la meteissa causa deperabans.',
	'renameusersuccess' => "L'utilizaire « <nowiki>$1</nowiki> » es plan estat renomenat en « <nowiki>$2</nowiki> »",
	'renameuser-page-exists' => 'La pagina $1 existís ja e pòt pas èsser remplaçada automaticament.',
	'renameuser-page-moved' => 'La pagina $1 es estada desplaçada cap a $2.',
	'renameuser-page-unmoved' => 'La pagina $1 pòt pas èsser renomenada en $2.',
	'log-name-renameuser' => "Istoric dels cambiaments de nom d'utilizaire",
	'log-description-renameuser' => "Aquò es l'istoric dels cambiaments de nom dels utilizaires",
	'renameuser-move-log' => 'Pagina desplaçada automaticament al moment del cambiament de nom de l’utilizaire "[[User:$1|$1]]" en "[[User:$2|$2]]"',
	'right-renameuser' => "Tornar nomenar d'utilizaires",
	'renameuser-renamed-notice' => 'Aqueste utilizaire es estat renomenat.
Lo jornal dels cambiaments de noms es disponible çaijós per informacion.',
);

/** Oriya (ଓଡ଼ିଆ)
 * @author Jnanaranjan Sahu
 * @author Odisha1
 * @author Psubhashish
 */
$messages['or'] = array(
	'renameuser' => 'ସଭ୍ୟଙ୍କ ନାମଟି ବଦଳାଇବେ',
	'renameuser-linkoncontribs' => 'ସଭ୍ୟଙ୍କ ନାମଟି ବଦଳାଇବେ',
	'renameuser-linkoncontribs-text' => 'ଏହି ସଭ୍ୟଙ୍କର ନାମ ବଦଳାଇବେ',
	'renameuser-desc' => "ଜଣେ ସଭ୍ୟଙ୍କର ନାମ ବଦଳାଇବା ପାଇଁ ଏକ [[Special:Renameuser|ବିଶେଷ ପୃଷ୍ଠା]] ଯୋଡ଼ିଥାଏ ।(''ନୂଆ ନାମକରଣ'' ଅଧିକାର ଲୋଡ଼ା)",
	'renameuserold' => 'ଏବେକାର ଇଉଜର ନାମ:',
	'renameusernew' => 'ନୂଆ ଇଉଜର ନାମ:',
	'renameuserreason' => 'କାରଣ:',
	'renameusermove' => 'ସଭ୍ୟ, ତାହାଙ୍କର ଆଲୋଚନା ପୃଷ୍ଠାମାନଙ୍କୁ (ତଥା ସାନପୃଷ୍ଠାମାନଙ୍କୁ)ନୂଆ ନାମକୁ ଘୁଞ୍ଚାଇବେ',
	'renameusersuppress' => 'ନୂଆ ନାମକୁ ପୁନପ୍ରେରଣ କରନ୍ତୁ ନାହିଁ',
	'renameuserreserve' => 'ଭବିଷ୍ୟତ ବ୍ୟବହାରରେ ପୁରୁଣା ଇଉଜର ନାମକୁ ଅଟକାଇ ଦିଅନ୍ତୁ',
	'renameuserwarnings' => 'ଚେତାବନୀ:',
	'renameuserconfirm' => 'ହଁ, ସଭ୍ୟଙ୍କ ନାମ ବଦଳାଇ ଦେବେ',
	'renameusersubmit' => 'ଦାଖଲକରିବା',
	'renameuser-submit-blocklog' => 'ବ୍ୟବହାରକାରୀଙ୍କ ପାଇଁ କିଳାଯାଇଥିବା ତାଲିକା ଦେଖିବେ',
	'renameusererrordoesnotexist' => '"<nowiki>$1</nowiki>" ନାମକ ସଭ୍ୟଜଣକ ଏଠାରେ ନାହାନ୍ତି ।',
	'renameusererrorexists' => '"<nowiki>$1</nowiki>" ନାମକ ସଭ୍ୟଜଣକ ଆଗରୁ ଅଛନ୍ତି ।',
	'renameusererrorinvalid' => '"<nowiki>$1</nowiki>" ଇଉଜର ନାମଟି ଅଚଳ ଅଟେ ।',
	'renameuser-error-request' => 'ଅନୁରୋଧ ଗ୍ରହଣ କରିବାରେ ଏକ ଅସୁବିଧା ହେଲା ।
ଦୟାକରି ପଛକୁ ଫେରି ଆଉଥରେ ଚେଷ୍ଟା କରନ୍ତୁ ।',
	'renameuser-error-same-user' => 'ଆଗ ଭଳି ଆପଣ ଜଣେ ସଭ୍ୟଙ୍କର ନାମ ବଦଳାଇପାରିବେ ନାହିଁ ।',
	'renameusersuccess' => '"<nowiki>$1</nowiki>" ସଭ୍ୟଙ୍କ ନାମ "<nowiki>$2</nowiki>"କୁ ବଦଳାଗଲା ।',
	'renameuser-page-exists' => '$1 ପୃଷ୍ଠାଟି ଆଗରୁ ଅଛି ଓ ଆଉଥରେ ଲେଖାଯାଇପାରିବ ନାହିଁ ।',
	'renameuser-page-moved' => '$1 ପୃଷ୍ଠାଟିକୁ $2କୁ ଘୁଞ୍ଚାଇ ଦିଆଗଲା ।',
	'renameuser-page-unmoved' => '$1 ପୃଷ୍ଠାଟି $2କୁ ଘୁଞ୍ଚାଯାଇ ପାରିବ ନାହିଁ ।',
	'log-name-renameuser' => 'ସଭ୍ୟ ନାମବଦଳ ଇତିହାସ',
	'log-description-renameuser' => 'ସଭ୍ୟଙ୍କ ନାମ ବଦଳର ଏହା ଏକ ଇତିହାସ ।',
	'logentry-renameuser-renameuser' => '$1 {{GENDER:$2|renamed}} user $4 ({{PLURAL:$6|$6 edit|$6 edits}}) to $5',
	'logentry-renameuser-renameuser-legacier' => '$1 $4ଙ୍କ ନାମ $5କୁ ବଦଳାଇଲେ',
	'renameuser-move-log' => 'ସଭ୍ୟ "[[User:$1|$1]]"ରୁ "[[User:$2|$2]]"କୁ ନାମ ବଦଳ କଲାବେଳେ ବେଳେ ଛାଏଁ ଛାଏଁ ପୃଷ୍ଠାଟି ଘୁଞ୍ଚାଇ ଦିଆଗଲା',
	'action-renameuser' => 'ବ୍ୟବହାରକାରୀଙ୍କ ନାମବଦଳା',
	'right-renameuser' => 'ସଭ୍ୟମାନଙ୍କ ନାମଟି ବଦଳାଇବେ',
	'renameuser-renamed-notice' => 'ଏହି ସଭ୍ୟଙ୍କ ନାମ ବଦଳାଯାଇଅଛି ।
ତଳେ ଅବଗତି ନିମନ୍ତେ ନାମ ବଦଳ ଇତିହାସ ଦିଆଗଲା ।',
);

/** Ossetic (Ирон)
 * @author Amikeco
 */
$messages['os'] = array(
	'renameuser' => 'Архайæджы ном баив',
	'renameuserold' => 'Ныры ном:',
	'renameusernew' => 'Ног ном:',
	'renameuserreason' => 'Ном ивыны аххос:', # Fuzzy
	'renameusersubmit' => 'Афтæ уæд',
	'log-name-renameuser' => 'Архайджыты нæмттæ ивыны лог',
);

/** Picard (Picard)
 * @author Geoleplubo
 */
$messages['pcd'] = array(
	'renameuser' => "Canger ch'nom d'uzeu",
	'renameusernew' => 'Nouvieu nom dechl uzeu',
	'renameuserreason' => "Motif dech canjemint d'nom",
	'renameuserwarnings' => 'Afute ! :',
	'renameuserconfirm' => 'Oui, érlonmer echl uzeu',
	'renameusererrorinvalid' => 'Ech nom  "<nowiki>$1</nowiki>" est non-val.',
	'renameusersuccess' => 'Echl uzeu "<nowiki>$1</nowiki>" o té érlonmé "<nowiki>$2</nowiki>".',
	'renameuser-page-moved' => "L'pache $1 o té déplachée dsus $2.",
	'renameuser-page-unmoved' => "L'pache $1 ale n'put poin éte déplachée su $2.",
	'log-name-renameuser' => "Jornal d'chés canjemints éd chés noms d'uzeus",
	'right-renameuser' => 'Érlonmer chés uzeus',
);

/** Deitsch (Deitsch)
 * @author Xqt
 */
$messages['pdc'] = array(
	'renameuser' => 'Naame vum Yuuser ennere',
	'renameuserold' => 'Current Yuusernaame:',
	'renameusernew' => 'Nei Yuuser-Naame',
	'renameuserreason' => 'Grund:', # Fuzzy
	'renameuserwarnings' => 'Warninge:',
);

/** Pälzisch (Pälzisch)
 * @author SPS
 */
$messages['pfl'] = array(
	'renameusersubmit' => 'Benutzer umbenenne',
);

/** Polish (polski)
 * @author BeginaFelicysym
 * @author Derbeth
 * @author Leinad
 * @author Maikking
 * @author Matma Rex
 * @author Nux
 * @author Odie2
 * @author Remedios44
 * @author Sovq
 * @author Sp5uhe
 * @author WarX
 * @author Wpedzich
 */
$messages['pl'] = array(
	'renameuser' => 'Zmiana nazwy użytkownika',
	'renameuser-linkoncontribs' => 'zmień nazwę użytkownika',
	'renameuser-linkoncontribs-text' => 'Zmień nazwę tego użytkownika',
	'renameuser-desc' => "Zmiana nazwy użytkownika (wymaga posiadania uprawnień ''renameuser'')",
	'renameuserold' => 'Obecna nazwa użytkownika:',
	'renameusernew' => 'Nowa nazwa użytkownika:',
	'renameuserreason' => 'Powód:',
	'renameusermove' => 'Przeniesienie strony osobistej i strony dyskusji użytkownika (oraz ich podstron) pod nową nazwę użytkownika',
	'renameusersuppress' => 'Nie twórz przekierowania do nowej nazwy',
	'renameuserreserve' => 'Zablokuj starą nazwę użytkownika przed możliwością użycia jej',
	'renameuserwarnings' => 'Ostrzeżenia:',
	'renameuserconfirm' => 'Zmień nazwę użytkownika',
	'renameusersubmit' => 'Zmień',
	'renameuser-submit-blocklog' => 'Pokaż rejestr blokad użytkownika',
	'renameusererrordoesnotexist' => 'Użytkownik „<nowiki>$1</nowiki>” nie istnieje',
	'renameusererrorexists' => 'Użytkownik „<nowiki>$1</nowiki>” już istnieje',
	'renameusererrorinvalid' => 'Niepoprawna nazwa użytkownika „<nowiki>$1</nowiki>”',
	'renameuser-error-request' => 'Wystąpił problem z odbiorem żądania.
Cofnij się i spróbuj jeszcze raz.',
	'renameuser-error-same-user' => 'Nie możesz zmienić nazwy użytkownika na taką samą jaka była wcześniej.',
	'renameusersuccess' => 'Nazwa użytkownika „<nowiki>$1</nowiki>” została zmieniona na „<nowiki>$2</nowiki>”',
	'renameuser-page-exists' => 'Strona „$1” już istnieje i nie może być automatycznie nadpisana.',
	'renameuser-page-moved' => 'Strona „$1” została przeniesiona pod nazwę „$2”.',
	'renameuser-page-unmoved' => 'Strona „$1” nie mogła zostać przeniesiona pod nazwę „$2”.',
	'log-name-renameuser' => 'Zmiany nazw użytkowników',
	'log-description-renameuser' => 'To jest rejestr zmian nazw użytkowników',
	'logentry-renameuser-renameuser' => '$1 {{GENDER:$2|zmienił|zmieniła}} nazwę użytkownika $4 ({{PLURAL:$6|$6 edycja|$6 edycje|$6 edycji}}) na $5',
	'logentry-renameuser-renameuser-legacier' => '$1 zmienił(a) nazwę użytkownika $4 na $5',
	'renameuser-move-log' => 'Automatyczne przeniesienie stron użytkownika po zmianie nazwy konta z „[[User:$1|$1]]” na „[[User:$2|$2]]”',
	'action-renameuser' => 'zmiana nazw użytkowników',
	'right-renameuser' => 'Zmiana nazw kont użytkowników',
	'renameuser-renamed-notice' => 'Nazwa konta {{GENDER:$1|tego użytkownika|tej użytkowniczki|użytkownika(‐czki)}} została zmieniona.
Rejestr zmian nazw kont użytkowników znajduje się poniżej.',
);

/** Piedmontese (Piemontèis)
 * @author Borichèt
 * @author Bèrto 'd Sèra
 * @author Dragonòt
 */
$messages['pms'] = array(
	'renameuser' => "Arbatié n'utent",
	'renameuser-linkoncontribs' => "arbatié n'utent",
	'renameuser-linkoncontribs-text' => "Arbatié st'utent-sì",
	'renameuser-desc' => "A gionta na [[Special:Renameuser|pàgina special]] për arnominé n'utent (a-i é dabzògn dël drit ''renameuser'')",
	'renameuserold' => 'Stranòm corent:',
	'renameusernew' => 'Stranòm neuv:',
	'renameuserreason' => 'Rason:',
	'renameusermove' => 'Tramuda ëdcò la pàgina utent e cola dle ciaciarade (con tute soe sotapàgine) a lë stranòm neuv',
	'renameusersuppress' => 'Creé nen na ridiression al nòm neuv',
	'renameuserreserve' => 'Blòca lë stanòm vej da future utilisassion',
	'renameuserwarnings' => 'Atension:',
	'renameuserconfirm' => "É!, arnòmina l'utent",
	'renameusersubmit' => 'Falo',
	'renameuser-submit-blocklog' => "Smon-e ël registr dij blocage për l'utent",
	'renameusererrordoesnotexist' => 'A-i é pa gnun utent ch\'as ës-ciama "<nowiki>$1</nowiki>"',
	'renameusererrorexists' => 'N\'utent ch\'as ës-ciama "<nowiki>$1</nowiki>" a-i é già',
	'renameusererrorinvalid' => 'Lë stranòm "<nowiki>$1</nowiki>" a l\'é nen bon',
	'renameuser-error-request' => "A l'é stàit-ie un problema con l'esecussion ëd l'arcesta.
Për piasì torna andré e preuva torna.",
	'renameuser-error-same-user' => "It peule pa arnominé n'utent con ël midem nòm ëd prima.",
	'renameusersuccess' => 'L\'utent "<nowiki>$1</nowiki>" a l\'é stait arbatià an "<nowiki>$2</nowiki>"',
	'renameuser-page-exists' => "La pàgina $1 a-i é già e as peul nen passe-ie dzora n'aotomàtich.",
	'renameuser-page-moved' => "La pàgina $1 a l'ha fait San Martin a $2.",
	'renameuser-page-unmoved' => "La pàgina $1 a l'é pa podusse tramudé a $2.",
	'log-name-renameuser' => "Registr dj'arbatiagi",
	'log-description-renameuser' => "Sossì a l'é un registr dle modìfiche djë stranòm dj'utent",
	'logentry-renameuser-renameuser' => "$1 {{GENDER:$2|a l'ha arbatià}} l'utent $4 ({{PLURAL:$6|$6 modìfica|$6 modìfiche}}) an $5",
	'logentry-renameuser-renameuser-legacier' => "$1 a l'ha arbatià l'utent $4 an $5",
	'renameuser-move-log' => 'Pàgina utent tramudà n\'aotomàtich damëntrè ch\'as arbatiava "[[User:$1|$1]]" an "[[User:$2|$2]]"',
	'action-renameuser' => "arbatié j'utent",
	'right-renameuser' => "Arnòmina j'utent",
	'renameuser-renamed-notice' => "St'utent-sì a l'é stàit arnominà.
Ël registr ëd l'arnòmina a l'é dàit sota për arferiment.",
);

/** Western Punjabi (پنجابی)
 * @author Khalid Mahmood
 */
$messages['pnb'] = array(
	'renameuser' => 'ورتن والے دا ہور ناں',
	'renameuser-linkoncontribs' => 'ورتن والے دا ہور ناں',
	'renameuser-linkoncontribs-text' => 'ایس ورتن والے دا ہور ناں رکھو',
	'renameuser-desc' => "جوڑدا اے اک [[Special:Renameuser|خاص صفہ]] اک ورتن والے نوں ہور ناں دین لئی ( ''renameuser'' حق دی لوڑ اے۔)",
	'renameuserold' => 'ہن والا ورتن والا ناں:',
	'renameusernew' => 'نواں ورتن والا ناں:',
	'renameuserreason' => 'ہور ناں رکھن دی وجہ:', # Fuzzy
	'renameusermove' => 'ورتن تے گل بات صفے نوں تے نال دے نکیاں صفیاں نوں نویں ناں ول لے چلو۔',
	'renameusersuppress' => 'ایس نویں ناں نال ریڈائرکٹ ناں بناؤ۔',
	'renameuserreserve' => 'پرانے ورتن والے ناں نوں اگے ورتے جان توں روکو',
	'renameuserwarnings' => 'خبردار',
	'renameuserconfirm' => 'ہاں، ورتن والے دا فیر ناں رکھو',
	'renameusersubmit' => 'پیش کرو',
	'renameusererrordoesnotexist' => 'ورتنوالا "<nowiki>$1</nowiki>" ہے ای نئیں۔',
	'renameusererrorexists' => 'ورتنوالا "<nowiki>$1</nowiki>" پہلے ای ہیگا اے۔',
	'renameusererrorinvalid' => 'ورتن ناں "<nowiki>$1</nowiki>" نئیں چل سکدا۔',
	'renameuser-error-request' => 'گل منن چ مسلہ اے۔ مہربانی کرکے پچھے جاؤ تے فیر کوشش کرو۔',
	'renameuser-error-same-user' => 'تسیں فیر پہلے وانگوں اک ورتن والے دا ناں فیر نئیں رکھ سکدے۔',
	'renameusersuccess' => 'ورتن والا "<nowiki>$1</nowiki>" دا ناں بدل کے "<nowiki>$1</nowiki>" رکھ دتا گیا اے۔', # Fuzzy
	'renameuser-page-exists' => 'صفہ $1 پہلے ای ہیگا اے تے ایدے تے اپنے آپ نئیں لکھیا جاسکدا۔',
	'renameuser-page-moved' => 'صفہ $1 نوں $2 ول لجایا گیا اے۔',
	'renameuser-page-unmoved' => 'صفہ $1 ، $2 ول نئیں لجایا جاسکدا۔',
	'log-name-renameuser' => 'ورتن ہور ناں لاگ',
	'renameuser-move-log' => 'اپنے آپ صفے ٹرے "[[User:$1|$1]]" دا ناں "[[User:$2|$2]]" پلٹدیاں ہویاں',
	'right-renameuser' => 'ورتن والے دا ہور ناں',
	'renameuser-renamed-notice' => 'ایس ورتن والے دا ناں بدلیا گیا اے۔
ناں بدلن والی لاگ اتے پتے لئی تھلے دتی گئی اے۔',
);

/** Pashto (پښتو)
 * @author Ahmed-Najib-Biabani-Ibrahimkhel
 */
$messages['ps'] = array(
	'renameuser' => 'کارن-نوم بدلول',
	'renameuser-linkoncontribs' => 'د کارن نوم بدلول',
	'renameuser-linkoncontribs-text' => 'د دې کارن نوم بدلول',
	'renameuserold' => 'اوسنی کارن-نوم:',
	'renameusernew' => 'نوی کارن-نوم:',
	'renameuserreason' => 'سبب:',
	'renameuserwarnings' => 'ګواښنې:',
	'renameuserconfirm' => 'هو، کارن-نوم بدلوم',
	'renameusersubmit' => 'سپارل',
	'renameusererrordoesnotexist' => 'د "<nowiki>$1</nowiki>" په نامه کوم کارن نه شته.',
	'renameusererrorexists' => 'د "<nowiki>$1</nowiki>" په نامه يو کارن له پخوا نه شته.',
	'renameusererrorinvalid' => 'د "<nowiki>$1</nowiki>" کارن نوم سم نه دی.',
	'renameuser-error-request' => 'د غوښتنې په ترلاسه کولو کې يوه ستونزه راپېښه شوه.
مهرباني وکړی بېرته پرشا ولاړ شی او يو ځل بيا پرې کوښښ وکړی.',
	'renameuser-page-moved' => 'د $1 مخ $2 ته ولېږدل شو.',
	'log-name-renameuser' => 'د کارن-نوم يادښت',
	'action-renameuser' => 'کارن-نومونه بدلول',
	'right-renameuser' => 'کارن-نومونه بدلول',
);

/** Portuguese (português)
 * @author Giro720
 * @author Hamilton Abreu
 * @author Luckas
 * @author Malafaya
 * @author Opraco
 * @author Waldir
 * @author 555
 */
$messages['pt'] = array(
	'renameuser' => 'Alterar o nome do utilizador',
	'renameuser-linkoncontribs' => 'alterar nome do utilizador',
	'renameuser-linkoncontribs-text' => 'Alterar o nome deste utilizador',
	'renameuser-desc' => "[[Special:Renameuser|Página especial]] para alterar o nome de um utilizador (requer o privilégio ''renameuser'')",
	'renameuserold' => 'Nome de utilizador atual:',
	'renameusernew' => 'Novo nome de utilizador:',
	'renameuserreason' => 'Motivo:',
	'renameusermove' => 'Mover as páginas e subpáginas de utilizador e as respectivas discussões para o novo nome',
	'renameusersuppress' => 'Não criar redirecionamentos para o novo nome',
	'renameuserreserve' => 'Impedir novos usos do antigo nome de utilizador',
	'renameuserwarnings' => 'Alertas:',
	'renameuserconfirm' => 'Sim, alterar o nome do utilizador',
	'renameusersubmit' => 'Enviar',
	'renameuser-submit-blocklog' => 'Mostrar o registo de bloqueios do utilizador',
	'renameusererrordoesnotexist' => 'O utilizador "<nowiki>$1</nowiki>" não existe.',
	'renameusererrorexists' => 'Já existe um utilizador "<nowiki>$1</nowiki>".',
	'renameusererrorinvalid' => 'O nome de utilizador "<nowiki>$1</nowiki>" é inválido.',
	'renameuser-error-request' => 'Houve um problema ao receber este pedido.
Volte atrás e tente de novo, por favor.',
	'renameuser-error-same-user' => 'Não é possível alterar o nome de um utilizador para o nome anterior.',
	'renameusersuccess' => 'O nome do utilizador "<nowiki>$1</nowiki>" foi alterado para "<nowiki>$2</nowiki>".',
	'renameuser-page-exists' => 'Já existe a página $1. Não é possível sobrescrever automaticamente.',
	'renameuser-page-moved' => 'A página $1 foi movida para $2.',
	'renameuser-page-unmoved' => 'Não foi possível mover a página $1 para $2.',
	'log-name-renameuser' => 'Registo de alteração do nome de utilizadores',
	'log-description-renameuser' => 'Este é um registro de alterações efetuadas a nomes de utilizadores.',
	'logentry-renameuser-renameuser' => '$1 renomeou $4 (com $6 edições) para $5', # Fuzzy
	'logentry-renameuser-renameuser-legacier' => '$1 renomeou $4 para $5',
	'renameuser-move-log' => 'Página movida automaticamente ao alterar o nome do utilizador "[[User:$1|$1]]" para "[[User:$2|$2]]"',
	'action-renameuser' => 'alterar nomes de utilizadores',
	'right-renameuser' => 'Alterar nomes de utilizadores',
	'renameuser-renamed-notice' => 'Este nome de utilizador foi alterado.
É apresentado abaixo o registo de alteração do nome de utilizadores.',
);

/** Brazilian Portuguese (português do Brasil)
 * @author Cainamarques
 * @author Giro720
 * @author Opraco
 * @author 555
 */
$messages['pt-br'] = array(
	'renameuser' => 'Renomear usuário',
	'renameuser-linkoncontribs' => 'renomear usuário',
	'renameuser-linkoncontribs-text' => 'Renomear este usuário',
	'renameuser-desc' => "Adiciona uma [[Special:Renameuser|página especial]] para renomear um usuário (requer privilégio ''renameuser'')",
	'renameuserold' => 'Nome de usuário atual:',
	'renameusernew' => 'Novo nome de usuário:',
	'renameuserreason' => 'Motivo:',
	'renameusermove' => 'Mover as páginas de usuário, páginas de discussão de usuário (e suas sub-páginas) para o novo nome',
	'renameusersuppress' => 'Não criar redirecionamentos para o novo nome',
	'renameuserreserve' => 'Impedir novos usos do antigo nome de usuário',
	'renameuserwarnings' => 'Alertas:',
	'renameuserconfirm' => 'Sim, renomeie o usuário',
	'renameusersubmit' => 'Enviar',
	'renameuser-submit-blocklog' => 'Mostrar registro de bloqueios do usuário',
	'renameusererrordoesnotexist' => 'Não existe um usuário "<nowiki>$1</nowiki>".',
	'renameusererrorexists' => 'Já existe um usuário "<nowiki>$1</nowiki>".',
	'renameusererrorinvalid' => 'O nome de usuário "<nowiki>$1</nowiki>" é inválido.',
	'renameuser-error-request' => 'Houve um problema ao receber este pedido.
Retorne e tente novamente.',
	'renameuser-error-same-user' => 'Não é possível renomear um usuário para o nome anterior.',
	'renameusersuccess' => 'O usuário "<nowiki>$1</nowiki>" foi renomeado para "<nowiki>$2</nowiki>".',
	'renameuser-page-exists' => 'A página $1 já existe. Não foi possível sobrescreve-la automaticamente.',
	'renameuser-page-moved' => 'A página $1 foi movida com sucesso para $2.',
	'renameuser-page-unmoved' => 'Não foi possível mover a página $1 para $2.',
	'log-name-renameuser' => 'Registro de renomeação de usuários',
	'log-description-renameuser' => 'Este é um registro de alterações de nomes de usuários.',
	'logentry-renameuser-renameuser' => '$1 {{GENDER:$2|renomeou}} $4 (com $6 ediç{{PLURAL:$6|ão|ões}}) para $5',
	'logentry-renameuser-renameuser-legacier' => '$1 renomeou $4 para $5',
	'renameuser-move-log' => 'Páginas movidas automaticamente ao renomear o usuário "[[User:$1|$1]]" para "[[User:$2|$2]]"',
	'action-renameuser' => 'renomear usuários',
	'right-renameuser' => 'Renomear usuários',
	'renameuser-renamed-notice' => 'Este usuário foi renomeado.
O registro de renomeação é fornecido abaixo, para referência.',
);

/** Quechua (Runa Simi)
 * @author AlimanRuna
 */
$messages['qu'] = array(
	'renameuser' => 'Ruraqpa sutinta hukchay',
	'renameuser-linkoncontribs' => 'ruraqpa sutinta hukchay',
	'renameuser-linkoncontribs-text' => 'Kay ruraqpa sutinta hukchay',
	'renameuser-desc' => "[[Special:Renameuser|Sapaq p'anqatam]] yapan ruraqpa sutinta hukchanapaq (''renameuser'' hayñi kana tiyan)",
	'renameuserold' => 'Kunan ruraqpa sutin:',
	'renameusernew' => 'Musuq ruraqpa sutin:',
	'renameuserreason' => 'Kayrayku:',
	'renameusermove' => "Ruraqpa p'anqanta, rimachinanta (urin p'anqankunatapas) musuq sutinman astay",
	'renameusersuppress' => 'Musuq sutiman ama pusapunata kamariychu',
	'renameuserreserve' => "Ruraqpa mawk'a sutinta qhipaq pacha suti kanamanta hark'ay",
	'renameuserwarnings' => 'Yuyampaykuna:',
	'renameuserconfirm' => 'Arí, ruraqpa sutinta hukchay',
	'renameusersubmit' => 'Kachay',
	'renameusererrordoesnotexist' => '"<nowiki>$1</nowiki>" sutiyuq ruraqqa manam kanchu.',
	'renameusererrorexists' => '"<nowiki>$1</nowiki>" sutiyuq ruraqqa kachkanñam.',
	'renameusererrorinvalid' => '"<nowiki>$1</nowiki>" nisqa sutiqa manam allinchu.',
	'renameuser-error-request' => 'Manam atinichu mañasqaykita chaskiyta.  Ama hina kaspa, ñawpaqman kutimuspa musuqmanta ruraykachay.',
	'renameuser-error-same-user' => 'Manam atinkichu ruraqpa sutinta ñawpaq suti hinalla sutinman hukchayta.',
	'renameusersuccess' => 'Ruraqpa "<nowiki>$1</nowiki>" nisqa sutinqa "<nowiki>$2</nowiki>" nisqa sutinman hukchasqañam.',
	'renameuser-page-exists' => '"<nowiki>$1</nowiki>" sutiyuq p\'anqaqa kachkanñam. Manam atinallachu kikinmanta huknachay.',
	'renameuser-page-moved' => '"<nowiki>$1</nowiki>" ñawpa sutiyuq ruraqpa p\'anqanqa "<nowiki>$2</nowiki>" nisqa musuq p\'anqanman astasqañam.',
	'renameuser-page-unmoved' => 'Manam atinichu "<nowiki>$1</nowiki>" ñawpa sutiyuq ruraqpa p\'anqanta "<nowiki>$2</nowiki>" nisqa musuq p\'anqanman astayta.',
	'log-name-renameuser' => "Ruraqpa sutin hukchay hallch'a",
	'renameuser-move-log' => '"[[User:$1|$1]]" ruraqpa sutinta "[[User:$2|$2]]" sutiman hukchaspa kikinmanta ruraqpa p\'anqatapas astan',
	'right-renameuser' => 'Ruraqpa sutinkunata hukchay',
	'renameuser-renamed-notice' => "Kay ruraqpa sutinqa hukchasqañam.
Kay qatiqpiqa hukchay hallch'atam rikunki.",
);

/** Romani (Romani)
 * @author Desiphral
 */
$messages['rmy'] = array(
	'renameusersubmit' => 'De le jeneske aver nav',
);

/** Romanian (română)
 * @author Cin
 * @author Emily
 * @author Firilacroco
 * @author KlaudiuMihaila
 * @author Memo18
 * @author Minisarm
 * @author Stelistcristi
 */
$messages['ro'] = array(
	'renameuser' => 'Redenumire utilizator',
	'renameuser-linkoncontribs' => 'redenumirea utilizatorului',
	'renameuser-linkoncontribs-text' => 'Redenumeşte acest utilizator',
	'renameuser-desc' => "Adaugă o [[Special:Renameuser|pagină specială]] pentru a redenumi un utilizator (necesită drept de ''renameuser'')",
	'renameuserold' => 'Numele de utilizator existent:',
	'renameusernew' => 'Noul nume de utilizator:',
	'renameuserreason' => 'Motiv:',
	'renameusermove' => 'Redenumește pagina de utilizator și pagina de discuții (și subpaginile lor) la noul nume',
	'renameusersuppress' => 'Nu crea redirecționări către noul nume',
	'renameuserreserve' => 'Blochează vechiul nume de utilizator pentru utilizări viitoare',
	'renameuserwarnings' => 'Avertizări:',
	'renameuserconfirm' => 'Da, redenumește utilizatorul',
	'renameusersubmit' => 'Trimite',
	'renameuser-submit-blocklog' => 'Arată jurnalul blocărilor utilizatorului',
	'renameusererrordoesnotexist' => 'Utilizatorul „<nowiki>$1</nowiki>” nu există.',
	'renameusererrorexists' => 'Utilizatorul „<nowiki>$1</nowiki>” există deja.',
	'renameusererrorinvalid' => 'Numele de utilizator „<nowiki>$1</nowiki>” este invalid.',
	'renameuser-error-request' => 'Am întâmpinat o problemă în procesul de recepționare a cererii.
Vă rugăm să vă întoarceți și să reîncercați.',
	'renameuser-error-same-user' => 'Nu puteți redenumi un utilizator la același nume ca și înainte.',
	'renameusersuccess' => 'Utilizatorul „$1” a fost redenumit în „$2”',
	'renameuser-page-exists' => 'Pagina $1 există deja și nu poate fi suprascrisă automat.',
	'renameuser-page-moved' => 'Pagina $1 a fost redenumită în $2.',
	'renameuser-page-unmoved' => 'Pagina $1 nu poate fi redenumită în $2.',
	'log-name-renameuser' => 'Jurnal redenumiri utilizatori',
	'log-description-renameuser' => 'Acesta este un jurnal al modificărilor de nume de utilizator',
	'renameuser-move-log' => 'Pagină mutată automat la redenumirea utilizatorului de la „[[User:$1|$1]]” la „[[User:$2|$2]]”',
	'action-renameuser' => 'redenumește utilizatori',
	'right-renameuser' => 'Redenumește utilizatori',
	'renameuser-renamed-notice' => 'Acestui utilizator i-a fost schimbat numele.
Jurnalul redenumirilor este furnizat mai jos pentru referință.',
);

/** tarandíne (tarandíne)
 * @author Joetaras
 */
$messages['roa-tara'] = array(
	'renameuser' => "Renomene l'utende",
	'renameuser-linkoncontribs' => "renomene l'utende",
	'renameuser-linkoncontribs-text' => 'Renomene quiste utende',
	'renameuser-desc' => "Aggiunge 'na [[Special:Renameuser|pàgena speciale]] pe renomena 'n'utende (abbesogne de le deritte ''renameuser'')",
	'renameuserold' => "Nome de l'utende de mò:",
	'renameusernew' => "Nome de l'utende nuève:",
	'renameuserreason' => 'Mutive:',
	'renameusermove' => "Spuèste utende e pàgene de le 'ngazzaminde (e le sottopàggene) a 'u nome nuève",
	'renameusersuppress' => "Nò ccrejà ridirezionaminde sus a 'u nome nuève",
	'renameuserreserve' => "Blocche 'u nome utende vicchije da le ause future",
	'renameuserwarnings' => 'Avvise:',
	'renameuserconfirm' => "Sine, cange 'u nome a l'utende",
	'renameusersubmit' => 'Conferme',
	'renameuser-submit-blocklog' => "Fà vedè l'archivije de le blocche pe l'utende",
	'renameusererrordoesnotexist' => 'L\'utende "<nowiki>$1</nowiki>" non g\'esiste.',
	'renameusererrorexists' => 'L\'utende "<nowiki>$1</nowiki>" esiste ggià.',
	'renameusererrorinvalid' => '\'U nome utende "<nowiki>$1</nowiki>" non è valide.',
	'renameuser-error-request' => "Stave 'nu probbleme cu 'a ricezione d'a richieste.<br />
Pe piacere tuèrne rrete e pruève 'n'otra vote.",
	'renameuser-error-same-user' => "Tu non ge puè renomenà 'n'utende cu 'u stesse nome d'apprime.",
	'renameusersuccess' => 'L\'utende "<nowiki>$1</nowiki>" ha cangiate \'u nome jndr\'à "<nowiki>$2</nowiki>".',
	'renameuser-page-exists' => "'A pàgene $1 già esiste e non ge se pò automaticamende sovrascrivere.",
	'renameuser-page-moved' => "'A pàgene $1 ha state spustate sus a $2.",
	'renameuser-page-unmoved' => "'A pàgene $1 non ge pò essere spustate sus a $2.",
	'log-name-renameuser' => 'Archivije de le renomenaminde de le utinde',
	'log-description-renameuser' => "Quiste jè l'archivije de le cangiaminde de le nome de l'utinde.",
	'logentry-renameuser-renameuser' => "$1 ave {{GENDER:$2|renomenate}} l'utende $4 ({{PLURAL:$6|$6 cangiamende|$6 cangiaminde}}) jndr'à $5",
	'logentry-renameuser-renameuser-legacier' => "$1 ave renomenate l'utende $4 jndr'à $5",
	'renameuser-move-log' => 'Pàgena spustate automaticamende quanne è renomenate l\'utende "[[User:$1|$1]]" jndr\'à "[[User:$2|$2]]"',
	'action-renameuser' => "renomene l'utinde",
	'right-renameuser' => "Rennomene l'utinde",
	'renameuser-renamed-notice' => "Stu utende ha state renomenate.
L'archivije de le renomenaziune 'u iacchie aqquà sotte cumme referimende.",
);

/** Russian (русский)
 * @author Ahonc
 * @author Anonim.one
 * @author DCamer
 * @author DR
 * @author EugeneZelenko
 * @author Innv
 * @author KPu3uC B Poccuu
 * @author Kaganer
 * @author Александр Сигачёв
 */
$messages['ru'] = array(
	'renameuser' => 'Переименовать участника',
	'renameuser-linkoncontribs' => 'переименовать участника',
	'renameuser-linkoncontribs-text' => 'Переименовать этого участника',
	'renameuser-desc' => 'Добавляет [[Special:Renameuser|возможность]] переименования пользователей (требуется право <code>renameuser</code>)',
	'renameuserold' => 'Имя в настоящий момент:',
	'renameusernew' => 'Новое имя:',
	'renameuserreason' => 'Причина:',
	'renameusermove' => 'Переименовать также страницу участника, личное обсуждение и их подстраницы',
	'renameusersuppress' => 'Не создавать перенаправлений на новое имя',
	'renameuserreserve' => 'Зарезервировать старое имя участника для использования в будущем',
	'renameuserwarnings' => 'Предупреждения:',
	'renameuserconfirm' => 'Да, переименовать участника',
	'renameusersubmit' => 'Выполнить',
	'renameuser-submit-blocklog' => 'Показать журнал блокировок участника',
	'renameusererrordoesnotexist' => 'Участник с именем «<nowiki>$1</nowiki>» не зарегистрирован.',
	'renameusererrorexists' => 'Участник с именем «<nowiki>$1</nowiki>» уже зарегистрирован.',
	'renameusererrorinvalid' => 'Недопустимое имя участника «<nowiki>$1</nowiki>»',
	'renameuser-error-request' => 'Возникли затруднения с получением запроса. Пожалуйста, вернитесь назад и повторите ещё раз.',
	'renameuser-error-same-user' => 'Вы не можете переименовать участника в тоже имя, что и было раньше.',
	'renameusersuccess' => 'Участник «<nowiki>$1</nowiki>» был переименован в «<nowiki>$2</nowiki>».',
	'renameuser-page-exists' => 'Страница $1 уже существует и не может быть перезаписана автоматически.',
	'renameuser-page-moved' => 'Страница $1 была переименована в $2.',
	'renameuser-page-unmoved' => 'Страница $1 не может быть переименована в $2.',
	'log-name-renameuser' => 'Журнал переименований участников',
	'log-description-renameuser' => 'Это журнал произведённых переименований зарегистрированных участников.',
	'logentry-renameuser-renameuser' => '$1 {{GENDER:$2|переименовал}} участника $4 ({{PLURAL:$6|$6 правка|$6 правки|$6 правок}}) в $5',
	'logentry-renameuser-renameuser-legacier' => '$1 переименовал пользователя $4 в $5',
	'renameuser-move-log' => 'Автоматически в связи с переименованием учётной записи «[[User:$1|$1]]» в «[[User:$2|$2]]»',
	'action-renameuser' => 'переименование участников',
	'right-renameuser' => 'переименование участников',
	'renameuser-renamed-notice' => 'Этот участник был переименован.
Ниже для справки приведён журнал переименований.',
);

/** Rusyn (русиньскый)
 * @author Gazeb
 */
$messages['rue'] = array(
	'renameuser' => 'Переменоватихоснователя',
	'renameuser-linkoncontribs' => 'переменовати хоснователя',
	'renameuser-linkoncontribs-text' => 'Переменовати того хоснователя',
	'renameuser-desc' => 'Придасть [[Special:Renameuser|шпеціалну сторінку]] про переменованя хоснователя (треба права "renameuser")',
	'renameuserold' => 'Актуалне мено:',
	'renameusernew' => 'Нове мено:',
	'renameuserreason' => 'Причіна переменованя:', # Fuzzy
	'renameusermove' => 'Переменовати тыж сторінкы хоснователя, сторінкы діскузії і їх підсторінкы',
	'renameusersuppress' => 'Не створюйте напрямлїня на нову назву',
	'renameuserreserve' => 'Блоковати нову реґістрацію старого мена хоснователя',
	'renameuserwarnings' => 'Варованя:',
	'renameuserconfirm' => 'Гей, переменовати хоснователя',
	'renameusersubmit' => 'Выконати',
	'renameuser-submit-blocklog' => 'Вказати книгу заблокованя того хоснователя',
	'renameusererrordoesnotexist' => 'Хоснователь з іменом „<nowiki>$1</nowiki>“ не єствує',
	'renameusererrorexists' => 'Хоснователь з іменом „<nowiki>$1</nowiki>“ уж єствує',
	'renameusererrorinvalid' => 'Хоснователське імя „<nowiki>$1</nowiki>“ ся не дасть хосновати',
	'renameuser-error-request' => 'Почас приїманя пожадавкы дішло ку хыбі. Вернийте ся і спробуйте то знову.',
	'renameuser-error-same-user' => 'Нове імя хоснователя є тото саме як дотеперїшнє.',
	'renameusersuccess' => 'Хоснователь „<nowiki>$1</nowiki>“ быв успішно переменованый на „<nowiki>$2</nowiki>“',
	'renameuser-page-exists' => 'Сторінка $1 уж екзістує і не може быти автоматічно переписана.',
	'renameuser-page-moved' => 'Сторінка $1 была переменована на $2.',
	'renameuser-page-unmoved' => 'Сторінка $1 не може быти переменована на $2.',
	'log-name-renameuser' => 'Лоґ переменовань хоснователїв',
	'renameuser-move-log' => 'Автоматічне переменованя сторінкы почас переменованя хоснователя „[[User:$1|$1]]“ на „[[User:$2|$2]]“',
	'action-renameuser' => 'переменовати хоснователїв',
	'right-renameuser' => 'Переменованя хоснователїв',
	'renameuser-renamed-notice' => 'Тот хоснователь быв переменованый.
Про перегляд є ниже указаный выпис з лоґу переменовань хоснователїв.',
);

/** Sanskrit (संस्कृतम्)
 * @author Ansumang
 * @author Shubha
 */
$messages['sa'] = array(
	'renameuser' => 'यॊजकस्य पुनर्नामकरणं क्रियताम्',
	'renameuser-linkoncontribs' => 'यॊजकनाम परिवर्त्यताम्',
	'renameuser-linkoncontribs-text' => 'अस्य योजकस्य नाम परिवर्त्यताम्',
	'renameuser-desc' => "योजकस्य पुनर्नामकरणं कर्तुं (''योजकपुनर्नाम''अधिकारः अपेक्षितः)  [[Special:Renameuser|विशेषपृष्ठम्]] योजयति",
	'renameuserold' => 'प्रस्तुतयोजकनाम :',
	'renameusernew' => 'नूतनयोजकनाम :',
	'renameuserreason' => 'नामपरिवर्तनस्य कारणम् :', # Fuzzy
	'renameusermove' => 'योजकः सम्भाषणपृष्ठं (तेषाम् उपपृष्ठानि) च नूतननाम प्रति चाल्यताम्',
	'renameusersuppress' => 'नूतननाम्नः पुनर्निदेशनं न सृज्यताम्',
	'renameuserreserve' => 'भविष्ये उपयोगाय पुरातनं योजकनाम अवरुद्ध्यताम्',
	'renameuserwarnings' => 'चेतावनी:',
	'renameuserconfirm' => 'आम्, योजकस्य पुनर्नाम दीयताम्',
	'renameusersubmit' => 'उपस्थाप्यताम्',
	'renameuser-submit-blocklog' => 'योजकस्य अवरोधवृत्तं दर्श्यताम्',
	'renameusererrordoesnotexist' => 'सदस्यः "<nowiki>$1</nowiki>" न विद्यते ।',
	'renameusererrorexists' => 'योजकः  "<nowiki>$1</nowiki>" पूर्वमेव विद्यते ।',
	'renameusererrorinvalid' => 'योजकनाम "<nowiki>$1</nowiki>" दोषयुक्तं विद्यते ।',
	'renameuser-error-request' => 'निवेदनस्य प्राप्तौ कश्चन क्लेशः आसीत् ।
कृपया प्रतिगत्य प्रयतताम् ।',
	'renameuser-error-same-user' => 'योजकस्य पूर्वनाम दत्त्वा पुनः नामकरणं न शक्यते ।',
	'renameusersuccess' => '"<nowiki>$1</nowiki>" इत्यस्य योजकनाम "<nowiki>$2</nowiki>" कृतमस्ति ।',
	'renameuser-page-exists' => '$1 इत्येतत् पुटं पूर्वमेव विद्यते । तदुपरि लेखनम् अशक्यम् ।',
	'renameuser-page-moved' => '$1 पृष्ठं $2 प्रति चालितम् अस्ति ।',
	'renameuser-page-unmoved' => '$1 पृष्ठं $2 प्रति चालनम् अशक्यम् ।',
	'log-name-renameuser' => 'परिवर्तितयोजकनाम्नां वृत्तम्',
	'renameuser-move-log' => '"[[User:$1|$1]]" तः "[[User:$2|$2]]" प्रति योजकनाम्नः परिवर्तनावसरे एव योजकपृष्ठं स्वयं चालितम् ।',
	'action-renameuser' => 'यॊजकस्य पुनर्नामकरणं क्रियताम्',
	'right-renameuser' => 'यॊजकस्य पुनर्नामकरणं क्रियताम्',
	'renameuser-renamed-notice' => 'अस्य योजकस्य पुनर्नामकरणं कृतमस्ति ।
परिवर्तनवृत्तम् अधः आधाररूपेण दत्तमस्ति ।',
);

/** Sakha (саха тыла)
 * @author HalanTul
 */
$messages['sah'] = array(
	'renameuser' => 'Кыттааччы аатын уларыт',
	'renameuser-linkoncontribs' => 'кыттааччы аатын уларытыы',
	'renameuser-linkoncontribs-text' => 'Бу кыттааччы аатын уларыт',
	'renameuser-desc' => "Кыттааччы аатын уларытыы (''renameuser'' бырааба наада)",
	'renameuserold' => 'Билиҥҥи аата:',
	'renameusernew' => 'Саҥа аата:',
	'renameuserreason' => 'Аатын уларыппыт төрүөтэ:', # Fuzzy
	'renameusermove' => 'Кыттааччы аатын кытта кэпсэтэр сирин, уонна атын сирэйдэрин ааттарын уларыт',
	'renameusersuppress' => 'Саҥа аакка утаарыылары оҥорума',
	'renameuserreserve' => 'Кыттааччы урукку аатын кэлин туттарга анаан хааллар',
	'renameuserwarnings' => 'Сэрэтиилэр:',
	'renameuserconfirm' => 'Сөп, аатын уларыт',
	'renameusersubmit' => 'Толор',
	'renameusererrordoesnotexist' => 'Маннык ааттаах кыттааччы «<nowiki>$1</nowiki>» бэлиэтэммэтэх.',
	'renameusererrorexists' => 'Маннык ааттаах кыттааччы "<nowiki>$1</nowiki>" номнуо баар.',
	'renameusererrorinvalid' => 'Маннык аат "<nowiki>$1</nowiki>" көҥуллэммэт.',
	'renameuser-error-request' => 'Запрос тутуута моһуоктанна. Бука диэн төнүн уонна хатылаа.',
	'renameuser-error-same-user' => 'Кыттааччы аатын урукку аатыгар уларытар табыллыбат.',
	'renameusersuccess' => '"<nowiki>$1</nowiki>" кыттааччы мантан ыла "<nowiki>$2</nowiki>" диэн ааттанна.',
	'renameuser-page-exists' => '$1 сирэй номнуо баар онон аптамаатынан хат суруллар кыаҕа суох.',
	'renameuser-page-moved' => '$1 сирэй маннык ааттаммыт $2.',
	'renameuser-page-unmoved' => '$1 сирэй маннык $2 ааттанар кыаҕа суох.',
	'log-name-renameuser' => 'Кыттааччылар ааттарын уларытыыларын сурунаала',
	'renameuser-move-log' => '«[[User:$1|$1]]» аата «[[User:$2|$2]]» буолбутунан аптамаатынан',
	'right-renameuser' => 'Кыттааччылар ааттарын уларытыы',
	'renameuser-renamed-notice' => 'Бу кыттааччы аата уларыйбыт.
Аллара аат уларыйыытын сурунаала көстөр.',
);

/** ꢱꣃꢬꢵꢯ꣄ꢡ꣄ꢬꢵ (ꢱꣃꢬꢵꢯ꣄ꢡ꣄ꢬꢵ)
 * @author MooRePrabu
 */
$messages['saz'] = array(
	'renameuser' => 'ꢮꢮ꣄ꢬꢸꢥꢵꢬ꣄ ꢥꢵꢮ꣄ ꢪꢬ꣄ꢗꢶ',
	'renameusernew' => 'ꢥꣁꢮ꣄ꢮꣁ  ꢮꢮ꣄ꢬꢸꢥꢵꢬ꣄ ꢥꢵꢮ꣄',
);

/** Sardinian (sardu)
 * @author Andria
 * @author Marzedu
 */
$messages['sc'] = array(
	'renameusernew' => 'Nou nùmene usuàriu:',
);

/** Sicilian (sicilianu)
 * @author Gmelfi
 * @author Santu
 */
$messages['scn'] = array(
	'renameuser' => 'Rinòmina utenti',
	'renameuser-linkoncontribs' => "Rinòmina l'utenti",
	'renameuser-desc' => "Funzioni pi rinuminari n'utenti (addumanna li diritti di ''renameuser'')",
	'renameuserold' => 'Nomu utenti dô prisenti:',
	'renameusernew' => 'Novu nomu utenti:',
	'renameuserreason' => 'Mutivu dû caciu di nomu', # Fuzzy
	'renameusermove' => 'Rinòmina macari la pàggina utenti, la pàggina di discussioni e li suttapàggini',
	'renameuserreserve' => 'Sarva lu vecchiu utenti pi futuri usi',
	'renameuserwarnings' => 'Avvisi:',
	'renameuserconfirm' => "Si, rinòmina st'utenti",
	'renameusersubmit' => 'Manna',
	'renameusererrordoesnotexist' => 'L\'utenti "<nowiki>$1</nowiki>" nun esisti',
	'renameusererrorexists' => 'L\'utenti "<nowiki>$1</nowiki>" c\'è già',
	'renameusererrorinvalid' => 'Lu nomu utenti "<nowiki>$1</nowiki>" nun è vàlidu',
	'renameuser-error-request' => "Si virificau nu prubbrema nnô ricivimentu dâ dumanna. Turnari arredi e pruvari n'àutra vota.",
	'renameuser-error-same-user' => "Nun si pò ri-numinari n'utenti cô stissu nomu c'avìa già.",
	'renameusersuccess' => 'L\'utenti "<nowiki>$1</nowiki>" vinni ri-numinatu \'n "<nowiki>$2</nowiki>"',
	'renameuser-page-exists' => "La pàggina $1 c'è già; mpussìbbili suprascrivìrila autumaticamenti.",
	'renameuser-page-moved' => 'La pàggina $1 vinni spustata a $2.',
	'renameuser-page-unmoved' => 'Mpussìbbili mòviri la pàggina $1 a $2.',
	'log-name-renameuser' => 'Utenti ri-numinati',
	'renameuser-move-log' => 'Spustamentu autumàticu dâ pàggina - utenti ri-numinatu di "[[User:$1|$1]]" a "[[User:$2|$2]]"',
	'right-renameuser' => "Ri-nòmina l'utenti",
);

/** Samogitian (žemaitėška)
 * @author Hugo.arg
 */
$messages['sgs'] = array(
	'renameuserold' => 'Esams nauduotuojė vards:',
	'renameusernew' => 'Naus nauduotuojė vards:',
	'renameusersuccess' => 'Nauduotuos "<nowiki>$1</nowiki>" bova parvadėnts i "<nowiki>$2</nowiki>".',
);

/** Serbo-Croatian (srpskohrvatski / српскохрватски)
 * @author OC Ripper
 */
$messages['sh'] = array(
	'renameusersubmit' => 'Unesi',
);

/** Sinhala (සිංහල)
 * @author Budhajeewa
 * @author තඹරු විජේසේකර
 * @author නන්දිමිතුරු
 * @author පසිඳු කාවින්ද
 * @author ශ්වෙත
 */
$messages['si'] = array(
	'renameuser' => 'පරිශීලකයා යළි-නම්කරන්න',
	'renameuser-linkoncontribs' => 'පරිශීලකයා යළි-නම්කරන්න',
	'renameuser-linkoncontribs-text' => 'මෙම පරිශීලකයා ප්‍රති-නම් කරන්න',
	'renameuser-desc' => "පරිශීලකයෙක් යළි-නම්කරනු වස් [[Special:Renameuser|විශේෂ පිටුවක්]] එක් කරන්න (''renameuser'' අයිතිය අවශ්‍යයි)",
	'renameuserold' => 'වත්මන් පරිශීලක නාමය:',
	'renameusernew' => 'නව පරිශීලක නාමය:',
	'renameuserreason' => 'හේතුව:',
	'renameusermove' => 'පරිශීලක හා සාකච්ඡා පිටු   (හා  ඒවායේ උපපිටු) නව නම වෙතට ගෙන යන්න',
	'renameusersuppress' => 'යළි යොමුවන් නම නාමයේ සැකසීමෙන් වළකින්න.',
	'renameuserreserve' => 'පැරණි පරිශීලක නම අනාගත භාවිතයෙන් වාරණය කරන්න',
	'renameuserwarnings' => 'අවවාදයන්:',
	'renameuserconfirm' => 'ඔව්, පරිශීලකයා යළි-නම්කරන්න',
	'renameusersubmit' => 'යොමන්න',
	'renameuser-submit-blocklog' => 'පරිශීලක සඳහා වාරණ ලඝු සටහන පෙන්වන්න',
	'renameusererrordoesnotexist' => '"<nowiki>$1</nowiki>" පරිශීලකයා නොපවතී.',
	'renameusererrorexists' => '"<nowiki>$1</nowiki>" පරිශීලකයා දැනටමත් පවතියි.',
	'renameusererrorinvalid' => '"<nowiki>$1</nowiki>" පරිශීලක නාමය අනීතිකයි.',
	'renameuser-error-request' => 'ඉල්ලීම ලැබීමේ දෝෂයක් හට ගැනිනි.
කරුණාකර ආපසු ගොස් නැවත උත්සාහ කරන්න.',
	'renameuser-error-same-user' => 'ඔබට පරිශීලකයෙක් පෙර තිබූ නමටම ප්‍රතිනම්කළ නොහැක.',
	'renameusersuccess' => '"<nowiki>$1</nowiki>" පරිශීලකයා "<nowiki>$2</nowiki>" වෙත ප්‍රතිනම් කෙරිනි.',
	'renameuser-page-exists' => '$1 පිටුව දැනටමත් පවතින අතර, එය ස්වයංක්‍රීයව අධිලිවීමකට භාජනය කල නොහැක.',
	'renameuser-page-moved' => ' $1 පිටුව $2 වෙත ගෙනයන ලදි.',
	'renameuser-page-unmoved' => ' $1 පිටුව  $2 වෙත ගෙනයා නොහැක.',
	'log-name-renameuser' => 'පරිශීලක ප්‍රතිනම්කෙරුම් ලොගය',
	'log-description-renameuser' => 'මෙය පරිශීලක නාම වෙනස්වීම් පිළිබඳ ලඝු-සටහනකි.',
	'renameuser-move-log' => 'පරිශීලක "[[User:$1|$1]]", "[[User:$2|$2]]" වෙත ප්‍රතිනම්කරන අතරතුර පිටුව ස්‍වයංක්‍රීයව ගෙනයන ලදී',
	'action-renameuser' => 'පරිශීලකයන් ප්‍රතිනම් කරන්න',
	'right-renameuser' => 'පරිශීලකයන් ප්‍රතිනම් කරන්න',
	'renameuser-renamed-notice' => 'මෙම පරිශීලකයා ප්‍රතිනම්කර ඇත.
ප්‍රතිනම්කෙරුම් ලඝු-සටහන පහත දක්වා ඇත.',
);

/** Slovak (slovenčina)
 * @author Helix84
 * @author Jkjk
 * @author KuboF
 */
$messages['sk'] = array(
	'renameuser' => 'Premenovať používateľa',
	'renameuser-linkoncontribs' => 'premenovať používateľa',
	'renameuser-linkoncontribs-text' => 'Premenovať tohto používateľa',
	'renameuser-desc' => "Premenovať používateľa (vyžaduje právo ''renameuser'')",
	'renameuserold' => 'Súčasné používateľské meno:',
	'renameusernew' => 'Nové používateľské meno:',
	'renameuserreason' => 'Dôvod:',
	'renameusermove' => 'Presunúť používateľské a diskusné stránky (a ich podstránky) na nový názov',
	'renameusersuppress' => 'Nevytvárať presmerovania na nový názov',
	'renameuserreserve' => 'Vyhradiť staré používateľské meno (zabrániť ďalšiemu použitiu)',
	'renameuserwarnings' => 'Upozornenia:',
	'renameuserconfirm' => 'Áno, premenovať používateľa',
	'renameusersubmit' => 'Odoslať',
	'renameuser-submit-blocklog' => 'Zobraziť záznam blokovaní používateľa',
	'renameusererrordoesnotexist' => 'Používateľ „<nowiki>$1</nowiki>“  neexistuje',
	'renameusererrorexists' => 'Používateľ „<nowiki>$1</nowiki>“ už existuje',
	'renameusererrorinvalid' => 'Používateľské meno „<nowiki>$1</nowiki>“ je neplatné',
	'renameuser-error-request' => 'Pri prijímaní vašej požiadavky nastal problém. Prosím, vráťte sa a skúste to znova.',
	'renameuser-error-same-user' => 'Nemôžete premenovať používateľa na rovnaké meno ako mal predtým.',
	'renameusersuccess' => 'Používateľ „<nowiki>$1</nowiki>“ bol premenovaný na „<nowiki>$2</nowiki>“',
	'renameuser-page-exists' => 'Stránka $1 už existuje a nie je možné ju automaticky prepísať.',
	'renameuser-page-moved' => 'Stránka $1 bola presunutá na $2.',
	'renameuser-page-unmoved' => 'Stránku $1 nebolo možné presunúť na $2.',
	'log-name-renameuser' => 'Záznam premenovaní používateľov',
	'log-description-renameuser' => 'Toto je záznam premenovaní používateľov',
	'logentry-renameuser-renameuser' => '$1 {{GENDER:$2|premenoval|premenovala}} používateľa $4 ({{PLURAL:$6|$6 úprava|$6 úpravy|$6 úprav}}) na $5',
	'logentry-renameuser-renameuser-legacier' => '$1 premenoval používateľa $4 na $5',
	'renameuser-move-log' => 'Automaticky presunutá stránka počas premenovania používateľa „[[User:$1|$1]]“ na „[[User:$2|$2]]“',
	'action-renameuser' => 'premenovať používateľov',
	'right-renameuser' => 'Premenovávať používateľov',
	'renameuser-renamed-notice' => 'Tento používateľ bol premenovaný.
Dolu nájdete záznam premenovaní.',
);

/** Slovenian (slovenščina)
 * @author Dbc334
 */
$messages['sl'] = array(
	'renameuser' => 'Preimenovanje uporabnika',
	'renameuser-linkoncontribs' => 'preimenuj uporabnika',
	'renameuser-linkoncontribs-text' => 'Preimenuj tega uporabnika',
	'renameuser-desc' => "Doda [[Special:Renameuser|posebno stran]] za preimenovanje uporabnika (potrebna je pravica ''renameuser'')",
	'renameuserold' => 'Trenutno uporabniško ime:',
	'renameusernew' => 'Novo uporabniško ime:',
	'renameuserreason' => 'Razlog:',
	'renameusermove' => 'Prestavi uporabniške in pogovorne strani (ter njihove podstrani) na novo ime',
	'renameusersuppress' => 'Ne ustvari preusmeritev na novo ime',
	'renameuserreserve' => 'Blokiraj staro uporabniško ime pred nadaljnjo uporabo',
	'renameuserwarnings' => 'Opozorila:',
	'renameuserconfirm' => 'Da, preimenuj uporabnika',
	'renameusersubmit' => 'Potrdi',
	'renameuser-submit-blocklog' => 'Pokaži dnevnik blokiranja uporabnika',
	'renameusererrordoesnotexist' => 'Uporabnik »<nowiki>$1</nowiki>« ne obstaja.',
	'renameusererrorexists' => 'Uporabnik »<nowiki>$1</nowiki>« že obstaja.',
	'renameusererrorinvalid' => 'Uporabniško ime »<nowiki>$1</nowiki>« ni veljavno.',
	'renameuser-error-request' => 'Pri prejemanju zahteve je prišlo do težave.
Prosimo, pojdite nazaj in poskusite znova.',
	'renameuser-error-same-user' => 'Uporabnika ne morete preimenovati v isto stvar kot prej.',
	'renameusersuccess' => 'Uporabnik »<nowiki>$1</nowiki>« je bil preimenovan v »<nowiki>$2</nowiki>«.',
	'renameuser-page-exists' => 'Stran $1 že obstaja in je ni mogoče samodejno prepisati.',
	'renameuser-page-moved' => 'Stran $1 je bila prestavljena na $2.',
	'renameuser-page-unmoved' => 'Strani $1 ni mogoče prestaviti na $2.',
	'log-name-renameuser' => 'Dnevnik preimenovanj uporabnikov',
	'log-description-renameuser' => 'Prikazan je dnevnik sprememb uporabniških imen.',
	'logentry-renameuser-renameuser' => '$1 je {{GENDER:$2|preimenoval|preimenovala|preimenoval(-a)}} uporabnika $4 ({{PLURAL:$6|$6 urejanje|$6 urejanji|$6 urejanja|$6 urejanj}}) v $5',
	'logentry-renameuser-renameuser-legacier' => '$1 je preimenoval(-a) uporabnika $4 v $5',
	'renameuser-move-log' => 'Samodejno prestavljanje strani pri preimenovanju uporabnika »[[User:$1|$1]]« v »[[User:$2|$2]]«',
	'action-renameuser' => 'preimenovanje uporabnikov',
	'right-renameuser' => 'Preimenovanje uporabnikov',
	'renameuser-renamed-notice' => 'Ta uporabnik je bil preimenovan.
Dnevnik preimenovanja je naveden spodaj.',
);

/** Lower Silesian (Schläsch)
 * @author Schläsinger
 */
$messages['sli'] = array(
	'renameuserold' => 'Bisheriger Benutzernoame:',
	'renameusernew' => 'Neuer Benutzernoame:',
	'renameuserreason' => 'Grund:', # Fuzzy
);

/** Albanian (shqip)
 * @author Dori
 * @author FatosMorina
 * @author Mikullovci11
 * @author Olsi
 */
$messages['sq'] = array(
	'renameuser' => 'Riemëroje përdoruesin',
	'renameuser-linkoncontribs' => 'Riemëroje përdoruesin',
	'renameuser-linkoncontribs-text' => 'Riemëroje këtë përdoruesin',
	'renameuser-desc' => "Shton një [[Special:Renameuser|faqe speciale]] për të riemëruar një përdorues (duhet e drejta ''renameuser'')",
	'renameuserold' => 'Emri i tanishëm',
	'renameusernew' => 'Emri i ri',
	'renameuserreason' => 'Arsyeja për riemërim:', # Fuzzy
	'renameusermove' => 'Zhvendos faqet e përdoruesit dhe të diskutimit (dhe nën-faqet e tyre) tek emri i ri',
	'renameusersuppress' => 'Mos krijoni përcjellime tek emri i ri',
	'renameuserreserve' => 'Bllokoni emrin e vjetër të përdoruesit të përdorim në të ardhmen',
	'renameuserwarnings' => 'Paralajmërimet:',
	'renameuserconfirm' => 'Po, ndërrojë emrin e përdoruesit',
	'renameusersubmit' => 'Ndryshoje',
	'renameuser-submit-blocklog' => 'Shfaq shënimet e bllokimit për përdoruesin',
	'renameusererrordoesnotexist' => 'Përdoruesi me emër "<nowiki>$1</nowiki>" nuk ekziston',
	'renameusererrorexists' => 'Përdoruesi me emër "<nowiki>$1</nowiki>" ekziston',
	'renameusererrorinvalid' => 'Emri "<nowiki>$1</nowiki>" nuk është i lejuar',
	'renameuser-error-request' => 'Kishte një problem me marrjen e kërkesës.
Ju lutemi kthehuni prapa dhe provoni përsëri.',
	'renameuser-error-same-user' => 'Ju nuk mund të riemëroni një përdorues tek e njëjta gjë si më parë.',
	'renameusersuccess' => 'Përdoruesi "<nowiki>$1</nowiki>" u riemërua në "<nowiki>$2</nowiki>"',
	'renameuser-page-exists' => 'Faqja $1 ekziston dhe nuk mund të mbivendoset automatikisht.',
	'renameuser-page-moved' => 'Faqja $1 është zhvendosur tek $2.',
	'renameuser-page-unmoved' => "Faqja $1 s'mund të zhvendosej tek $2.",
	'log-name-renameuser' => 'Regjistri i emër-ndryshimeve',
	'renameuser-move-log' => 'Lëvizi faqen automatikisht kur riemëroi përdoruesin "[[User:$1|$1]]" në "[[User:$2|$2]]"',
	'action-renameuser' => 'riemëro përdoruesit',
	'right-renameuser' => 'Riemëroni përdorueset',
	'renameuser-renamed-notice' => 'Ky përdorues është riemëruar.
Regjistri i riemërimit është poshtë për referencë.',
);

/** Serbian (Cyrillic script) (српски (ћирилица)‎)
 * @author FriedrickMILBarbarossa
 * @author Milicevic01
 * @author Millosh
 * @author Rancher
 * @author Sasa Stefanovic
 * @author Жељко Тодоровић
 * @author Михајло Анђелковић
 */
$messages['sr-ec'] = array(
	'renameuser' => 'Преименуј корисника',
	'renameuser-linkoncontribs' => 'преименуј корисника',
	'renameuser-linkoncontribs-text' => 'Преименуј овог корисника',
	'renameuser-desc' => "Додаје [[Special:Renameuser|посебну страницу]] за преименовање корисника (потребно право ''renameuser'')",
	'renameuserold' => 'Тренутно корисничко име:',
	'renameusernew' => 'Ново корисничко име:',
	'renameuserreason' => 'Разлог:',
	'renameusermove' => 'Премести корисничку страницу и страницу за разговор (и њихове подстранице) на нови назив',
	'renameusersuppress' => 'Не правите преусмерења на нови назив',
	'renameuserreserve' => 'Блокирај старо корисничко име за даљу употребу',
	'renameuserwarnings' => 'Упозорења:',
	'renameuserconfirm' => 'Да, преименуј корисника',
	'renameusersubmit' => 'Прихвати',
	'renameuser-submit-blocklog' => 'Дневник блокирања за корисника',
	'renameusererrordoesnotexist' => 'Корисник „<nowiki>$1</nowiki>“ не постоји.',
	'renameusererrorexists' => 'Корисник „<nowiki>$1</nowiki>“ већ постоји.',
	'renameusererrorinvalid' => 'Погрешно корисничко име: "<nowiki>$1</nowiki>"',
	'renameuser-error-request' => 'Дошло је до проблема при примању захтева.
Вратите се назад и покушајте поново.',
	'renameuser-error-same-user' => 'Не можете преименовати корисника у исто име.',
	'renameusersuccess' => 'Корисник "<nowiki>$1</nowiki>" је преименован на "<nowiki>$2</nowiki>"',
	'renameuser-page-exists' => 'Страница $1 већ постоји и не може се заменити.',
	'renameuser-page-moved' => 'Страница $1 је премештена у $2.',
	'renameuser-page-unmoved' => 'Страница $1 не може да се премести на $2.',
	'log-name-renameuser' => 'Дневник преименовања корисника',
	'renameuser-move-log' => 'Премештене странице приликом преименовања корисника: „[[User:$1|$1]]“ у „[[User:$2|$2]]“.',
	'action-renameuser' => 'преименовање корисника',
	'right-renameuser' => 'преименовање корисничких имена',
	'renameuser-renamed-notice' => 'Овом кориснику је промењено име.
Историја промена имена је приложена испод, као информација.',
);

/** Serbian (Latin script) (srpski (latinica)‎)
 * @author FriedrickMILBarbarossa
 * @author Liangent
 * @author Michaello
 * @author Milicevic01
 * @author Жељко Тодоровић
 */
$messages['sr-el'] = array(
	'renameuser' => 'Preimenuj korisnika',
	'renameuser-linkoncontribs' => 'preimenuj korisnika',
	'renameuser-linkoncontribs-text' => 'Preimenuj ovog korisnika',
	'renameuser-desc' => "Dodaje [[Special:Renameuser|posebnu stranicu]] za preimenovanje korisnika (potrebno pravo ''renameuser'').",
	'renameuserold' => 'Trenutno korisničko ime:',
	'renameusernew' => 'Novo korisničko ime:',
	'renameuserreason' => 'Razlog:',
	'renameusermove' => 'Premesti korisničku stranicu i stranicu za razgovor (i njihove podstranice) na novo ime',
	'renameusersuppress' => 'Ne pravite preusmerenja na novi naziv',
	'renameuserreserve' => 'Blokiraj staro korisničko ime za dalju upotrebu',
	'renameuserwarnings' => 'Upozorenja:',
	'renameuserconfirm' => 'Da, preimenuj korisničko ime.',
	'renameusersubmit' => 'Prihvati',
	'renameuser-submit-blocklog' => 'Dnevnik blokiranja za korisnika',
	'renameusererrordoesnotexist' => 'Korisnik "<nowiki>$1</nowiki>" ne postoji',
	'renameusererrorexists' => 'Korisnik "<nowiki>$1</nowiki>" već postoji',
	'renameusererrorinvalid' => 'Pogrešno korisničko ime: "<nowiki>$1</nowiki>"',
	'renameuser-error-request' => 'Javio se problem prilikom prihvatanja zahteva. Idi nazad i pokušaj ponovo.',
	'renameuser-error-same-user' => 'Ne možeš preimenovati korisničko ime u isto kao i prethodno.',
	'renameusersuccess' => 'Korisnik "<nowiki>$1</nowiki>" je preimenovan na "<nowiki>$2</nowiki>"',
	'renameuser-page-exists' => 'Stranica $1 već postoji i ne može biti automatski presnimljena.',
	'renameuser-page-moved' => 'Stranica $1 je premeštena na $2.',
	'renameuser-page-unmoved' => 'Stranica $1 ne može biti premeštena na $2.',
	'log-name-renameuser' => 'Dnevnik preimenovanja korisnika',
	'renameuser-move-log' => 'Automatski pomerene stranice prilikom preimenovanja korisničkog imena: „[[User:$1|$1]]“ u „[[User:$2|$2]]“.',
	'action-renameuser' => 'preimenovanje korisnika',
	'right-renameuser' => 'preimenovanje korisničkih imena',
	'renameuser-renamed-notice' => 'Ovom korisniku je promenjeno ime.
Istorija promena imena je priložena ispod, kao informacija.',
);

/** Seeltersk (Seeltersk)
 * @author Maartenvdbent
 * @author Pyt
 */
$messages['stq'] = array(
	'renameuser' => 'Benutsernoome annerje',
	'renameuser-desc' => "Föiget ne [[Special:Renameuser|Spezioalsiede]] bietou tou Uumbenaamenge fon n Benutser (fräiget dät ''renameuser''-Gjucht)",
	'renameuserold' => 'Benutsernoomer bithäär:',
	'renameusernew' => 'Näie Benutsernoome:',
	'renameuserreason' => 'Gruund foar Uumenaame:', # Fuzzy
	'renameusermove' => 'Ferskuuwe Benutser-/Diskussionssiede inkl. Unnersieden ap dän näie Benutsernoome',
	'renameuserreserve' => 'Blokkierje dän oolde Benutsernoome foar ne näie Registrierenge',
	'renameuserwarnings' => 'Woarskauengen:',
	'renameuserconfirm' => 'Jee, Benutser uumbenaame',
	'renameusersubmit' => 'Uumbenaame',
	'renameusererrordoesnotexist' => 'Die Benutsernoome "<nowiki>$1</nowiki>" bestoant nit',
	'renameusererrorexists' => 'Die Benutsernoome "<nowiki>$1</nowiki>" bestoant al',
	'renameusererrorinvalid' => 'Die Benutsernoome "<nowiki>$1</nowiki>" is uungultich',
	'renameuser-error-request' => 'Dät roat n Problem bie dän Ämpfang fon ju Anfroage. Fersäik jädden nochmoal.',
	'renameuser-error-same-user' => 'Oolde un näie Benutsernoome sunt identisk.',
	'renameusersuccess' => 'Die Benutser "<nowiki>$1</nowiki>" wuude mäd Ärfoulch uumenaamd in "<nowiki>$2</nowiki>"',
	'renameuser-page-exists' => 'Ju Siede $1 bestoant al un kon nit automatisk uurskrieuwen wäide.',
	'renameuser-page-moved' => 'Ju Siede $1 wuude ätter $2 ferskäuwen.',
	'renameuser-page-unmoved' => 'Ju Siede $1 kuude nit ätter $2 ferskäuwen wäide.',
	'log-name-renameuser' => 'Benutsernoomenannerengs-Logbouk',
	'renameuser-move-log' => 'truch ju Uumbenaamenge fon „[[User:$1|$1]]“ ätter „[[User:$2|$2]]“ automatisk ferskäuwene Siede.',
	'right-renameuser' => 'Benutser uumenaame',
);

/** Sundanese (Basa Sunda)
 * @author Irwangatot
 * @author Kandar
 */
$messages['su'] = array(
	'renameuser' => 'Ganti ngaran pamaké',
	'renameuser-desc' => "Ganti ngaran pamaké (perlu kawenangan ''renameuser'')",
	'renameuserold' => 'Ngaran pamaké ayeuna:',
	'renameusernew' => 'Ngaran pamaké anyar:',
	'renameuserreason' => 'Alesan ganti ngaran:', # Fuzzy
	'renameusermove' => 'Pindahkeun kaca pamaké jeung obrolanna (jeung sub-kacanna) ka ngaran anyar',
	'renameusersubmit' => 'Kirim',
	'renameusererrordoesnotexist' => 'Euweuh pamaké nu ngaranna "<nowiki>$1</nowiki>"',
	'renameusererrorexists' => 'Pamaké "<nowiki>$1</nowiki>" geus aya',
	'renameusererrorinvalid' => 'Ngaran pamaké "<nowiki>$1</nowiki>" teu sah',
	'renameuser-error-request' => 'Aya gangguan nalika nampa paménta. Coba balik deui, terus cobaan deui.',
	'renameuser-error-same-user' => 'Anjeun teu bisa ngaganti ngaran pamaké ka ngaran nu éta-éta kénéh.',
	'renameusersuccess' => 'Pamaké "<nowiki>$1</nowiki>" geus diganti ngaranna jadi "<nowiki>$2</nowiki>"',
	'renameuser-page-exists' => 'Kaca $1 geus aya sarta teu bisa ditimpah kitu baé.',
	'renameuser-page-moved' => 'Kaca $1 geus dipindahkeun ka $2.',
	'renameuser-page-unmoved' => 'Kaca $1 teu bisa dipindahkeun ka $2.',
	'log-name-renameuser' => 'Log ganti ngaran',
	'renameuser-move-log' => 'Otomatis mindahkeun kaca nalika ngaganti ngaran "[[User:$1|$1]]" jadi "[[User:$2|$2]]"',
);

/** Swedish (svenska)
 * @author Ainali
 * @author Boivie
 * @author Cohan
 * @author Cybjit
 * @author Dafer45
 * @author Habj
 * @author Jopparn
 * @author Lejonel
 * @author Lokal Profil
 * @author M.M.S.
 * @author MagnusA
 * @author Najami
 * @author Per
 */
$messages['sv'] = array(
	'renameuser' => 'Byt användarnamn',
	'renameuser-linkoncontribs' => 'byt användarnamn',
	'renameuser-linkoncontribs-text' => 'byt namn på denna användare',
	'renameuser-desc' => "Lägger till en [[Special:Renameuser|specialsida]] för att byta namn på en användare (kräver behörigheten ''renameuser'')",
	'renameuserold' => 'Nuvarande användarnamn:',
	'renameusernew' => 'Nytt användarnamn:',
	'renameuserreason' => 'Anledning:',
	'renameusermove' => 'Flytta användarsidan och användardiskussionen (och deras undersidor) till det nya namnet',
	'renameusersuppress' => 'Skapa inte omdirigeringar till det nya namnet',
	'renameuserreserve' => 'Reservera det gamla användarnamnet från framtida användning',
	'renameuserwarnings' => 'Varningar:',
	'renameuserconfirm' => 'Ja, byt namn på användaren',
	'renameusersubmit' => 'Verkställ',
	'renameuser-submit-blocklog' => 'Visa blockeringslogg för användare',
	'renameusererrordoesnotexist' => 'Användaren "<nowiki>$1</nowiki>" finns inte',
	'renameusererrorexists' => 'Användaren "<nowiki>$1</nowiki>" finns redan.',
	'renameusererrorinvalid' => 'Användarnamnet "<nowiki>$1</nowiki>" är ogiltigt.',
	'renameuser-error-request' => 'Ett problem inträffade i hanteringen av begäran. Gå tillbaks och försök igen.',
	'renameuser-error-same-user' => 'Du kan inte byta namn på en användare till samma som tidigare.',
	'renameusersuccess' => 'Användaren "<nowiki>$1</nowiki>" har fått sitt namn bytt till "<nowiki>$2</nowiki>"',
	'renameuser-page-exists' => 'Sidan $1 finns redan och kan inte skrivas över automatiskt.',
	'renameuser-page-moved' => 'Sidan $1 har flyttats till $2.',
	'renameuser-page-unmoved' => 'Sidan $1 kunde inte flyttas till $2.',
	'log-name-renameuser' => 'Logg över användarnamnsbyten',
	'log-description-renameuser' => 'Detta är en logg över ändringar av användarnamn',
	'logentry-renameuser-renameuser' => '$1 {{GENDER:$2|bytte namn på}} användare $4 ({{PLURAL:$6|$6 redigering|$6  redigeringar}}) till $5',
	'logentry-renameuser-renameuser-legacier' => '$1 bytte namn på användare $4 till $5',
	'renameuser-move-log' => 'Flyttade automatiskt sidan när namnet byttes på användaren "[[User:$1|$1]]" till "[[User:$2|$2]]"',
	'action-renameuser' => 'ändra namn på användaren',
	'right-renameuser' => 'Ändra användares namn',
	'renameuser-renamed-notice' => 'Användaren har fått ett nytt namn.
Som referens återfinns omdöpningsloggen nedan.',
);

/** Swahili (Kiswahili)
 * @author Kwisha
 * @author Stephenwanjau
 */
$messages['sw'] = array(
	'renameuser' => 'Badili jina la mtumiaji',
	'renameuser-linkoncontribs' => 'badili jina la mtumiaji',
	'renameuser-linkoncontribs-text' => 'Badili jina la mtumiaji huyu',
	'renameuserold' => 'Jina la sasa la mtumiaji:',
	'renameusernew' => 'Jina lipya la mtumiaji:',
	'renameuserreason' => 'Sababu ya kubadili jina:', # Fuzzy
	'renameuserwarnings' => 'Ilani:',
	'renameuserconfirm' => 'Ndiyo, badili jina la mtumiaji',
	'renameusersubmit' => 'Wasilisha',
	'renameuser-page-moved' => 'Ukurasa wa $1 umehamishwa hadi $2.',
	'renameuser-page-unmoved' => 'Ukurasa $1 haungesongezwa hadi $2.',
	'action-renameuser' => 'badili jina la mtumiaji',
	'right-renameuser' => 'Badili jina la watumiaji',
);

/** Tamil (தமிழ்)
 * @author Balajijagadesh
 * @author Karthi.dr
 * @author Shanmugamp7
 * @author TRYPPN
 * @author மதனாஹரன்
 */
$messages['ta'] = array(
	'renameuser' => 'பயனரை பெயர்மாற்று',
	'renameuser-linkoncontribs' => 'பயனரை பெயர்மாற்று',
	'renameuser-linkoncontribs-text' => 'இந்த பயனரை பெயர்மாற்று',
	'renameuserold' => 'தற்போதைய பயனர் பெயர்:',
	'renameusernew' => 'புதிய பயனர் பெயர்:',
	'renameuserreason' => 'மறுபெயருக்கான காரணம்:', # Fuzzy
	'renameusermove' => 'பயனர் பக்கம் மற்றும் பேச்சுப் பக்கங்களை (அவற்றின் துணைப்பக்கங்களுடன்) புதிய பெயருக்கு நகர்த்து',
	'renameusersuppress' => 'புதுப் பெயருக்கு வழிமாற்றுகளை உருவாக்க வேண்டாம்',
	'renameuserreserve' => 'எதிர்காலப் பயன்பாட்டிலிருந்து பழைய பயனர் பெயரைத் தடை செய்யவும்',
	'renameuserwarnings' => 'எச்சரிக்கை:',
	'renameuserconfirm' => 'சரி, பயனருக்கு மாற்றுப்பெயர் கொடுக்கவும்',
	'renameusersubmit' => 'சமர்ப்பி',
	'renameuser-submit-blocklog' => 'பயனாளரின் தடை உள்ளீட்டை காட்டு',
	'renameusererrordoesnotexist' => '"<nowiki>$1</nowiki>" என்ற பெயரிலான பயனர் இல்லை.',
	'renameusererrorexists' => '"<nowiki>$1</nowiki>" என்ற பெயரில் ஏற்கனவே பயனர் ஒருவர் உள்ளார்.',
	'renameusererrorinvalid' => '"<nowiki>$1</nowiki>" என்ற பயனர் பெயர் செல்லாது.',
	'renameuser-error-request' => 'வேண்டுகோளைப் பெறுவதில் ஒரு சிக்கல்.
தயவு செய்து பின்சென்று மீண்டும் முயலவும்.',
	'renameuser-error-same-user' => 'பயனர் பெயரை மாற்றும் போது அதே பெயரை நீங்கள் தரமுடியாது.',
	'renameuser-page-exists' => 'பக்கம் $1 ஏற்கனவே  உள்ளது. தானாக மேலெழுத இயலாது.',
	'renameuser-page-moved' => 'பக்கம் $1 $2 எனுந்தலைப்புக்கு நகர்த்தப்பட்டுள்ளது.',
	'renameuser-page-unmoved' => 'பக்கம் $1 என்பதை $2 என்பதற்கு நகர்த்த முடியவில்லை.',
	'log-name-renameuser' => 'பயனரை பெயர்மாற்றுதல் குறிப்பேடு',
	'log-description-renameuser' => 'இது பயனர் பெயர் மாற்றத்திற்கான குறிப்பேடு',
	'action-renameuser' => 'பயனரை பெயர்மாற்று',
	'right-renameuser' => 'பயனர்களை மாற்று பெயரிடு',
	'renameuser-renamed-notice' => 'இந்த பயனர் பெயர் மாற்றப்பட்டது.
மாற்றுப்பெயரிடுதல் குறிப்பேடு குறிப்புதவிக்காக கீழே வழங்கப்பட்டுள்ளது',
);

/** Telugu (తెలుగు)
 * @author Chaduvari
 * @author Mpradeep
 * @author Veeven
 */
$messages['te'] = array(
	'renameuser' => 'వాడుకరి పేరుమార్చు',
	'renameuser-linkoncontribs' => 'వాడుకరి పేరుమార్చు',
	'renameuser-linkoncontribs-text' => 'ఈ వాడుకరి పేరుని మార్చండి',
	'renameuser-desc' => "వాడుకరి పేరు మార్చండి (''renameuser'' అన్న అధికారం కావాలి)",
	'renameuserold' => 'ప్రస్తుత వాడుకరి పేరు:',
	'renameusernew' => 'కొత్త వాడుకరి పేరు:',
	'renameuserreason' => 'పేరు మార్చడానికి కారణం:', # Fuzzy
	'renameusermove' => 'వాడుకరి పేజీ, చర్చాపేజీలను (వాటి ఉపపేజీలతో సహా) కొత్త పేరుకు తరలించండి',
	'renameusersuppress' => 'కొత్త పేరుకి దారిమార్పులు సృష్టించకు',
	'renameuserreserve' => 'పాత వాడుకరిపేరుని భవిష్యత్తులో వాడకుండా నిరోధించు',
	'renameuserwarnings' => 'హెచ్చరికలు:',
	'renameuserconfirm' => 'అవును, వాడుకరి పేరు మార్చు',
	'renameusersubmit' => 'పంపించు',
	'renameusererrordoesnotexist' => '"<nowiki>$1</nowiki>" పేరుగల వాడుకరి లేరు.',
	'renameusererrorexists' => '"<nowiki>$1</nowiki>" పేరుతో వాడుకరి ఇప్పటికే ఉన్నారు.',
	'renameusererrorinvalid' => '"<nowiki>$1</nowiki>" అనే వాడుకరిపేరు సరైనది కాదు.',
	'renameuser-error-request' => 'మీ అభ్యర్థనను స్వీకరించేటప్పుడు ఒక సమస్య తలెత్తింది. దయచేసి వెనక్కు వెళ్లి ఇంకోసారి ప్రయత్నించండి.',
	'renameuser-error-same-user' => 'సభ్యనామాన్ని ఇంతకు ముందు ఉన్న సభ్యనామంతోనే మార్చడం కుదరదు.',
	'renameusersuccess' => '"<nowiki>$1</nowiki>" అనే సభ్యనామాన్ని "<nowiki>$2</nowiki>"గా మార్చేసాం.',
	'renameuser-page-exists' => '$1 పేజీ ఇప్పటికే ఉంది, కాబట్టి ఆటోమాటిగ్గా దానిపై కొత్తపేజీని రుద్దడం కుదరదు.',
	'renameuser-page-moved' => '$1 పేజీని $2 పేజీకి తరలించాం.',
	'renameuser-page-unmoved' => '$1 పేజీని $2 పేజీకి తరలించలేక పోయాం.',
	'log-name-renameuser' => 'వాడుకరి పేరుమార్పుల చిట్టా',
	'renameuser-move-log' => '"[[User:$1|$1]]" పేరును "[[User:$2|$2]]"కు మార్చడంతో పేజీని ఆటోమాటిగ్గా తరలించాం',
	'right-renameuser' => 'వాడుకరుల పేరు మార్చడం',
	'renameuser-renamed-notice' => 'ఈ వాడుకరి పేరు మారింది.
మీ సమాచారం కోసం పేరుమార్పుల చిట్టాని క్రింద ఇచ్చాం.',
);

/** Tetum (tetun)
 * @author MF-Warburg
 */
$messages['tet'] = array(
	'renameuser' => "Fó naran foun ba uza-na'in sira",
	'renameuser-desc' => "Fó naran foun ba uza-na'in sira (presiza priviléjiu ''renameuser'')",
	'renameuserold' => "Naran uza-na'in atuál:",
	'renameusernew' => "Naran uza-na'in foun:",
	'renameuserreason' => 'Motivu:',
	'renameusermove' => "Book pájina uza-na'in no diskusaun (no sub-pájina) ba naran foun",
	'renameuserconfirm' => 'Sin, fó naran foun',
	'renameusersubmit' => 'Fó naran foun',
	'renameusererrordoesnotexist' => 'Uza-na\'in "<nowiki>$1</nowiki>" la iha.',
	'renameuser-page-moved' => 'Book tiha pájina $1 ba $2.',
	'renameuser-page-unmoved' => 'La bele book pájina $1 ba $2.',
	'logentry-renameuser-renameuser-legacier' => '$1 muda naran uza-na\'in "$4" nian. Naran foun: "$5"',
	'right-renameuser' => "Fó naran foun ba uza-na'in sira",
);

/** Tajik (Cyrillic script) (тоҷикӣ)
 * @author Ibrahim
 */
$messages['tg-cyrl'] = array(
	'renameuser' => 'Тағйири номи корбарӣ',
	'renameuser-desc' => "Номи як корбарро тағйир медиҳад (ниёзманд ба ихтиёроти ''тағйирином'' аст)",
	'renameuserold' => 'Номи корбари феълӣ:',
	'renameusernew' => 'Номи корбари ҷадид:',
	'renameuserreason' => 'Сабаб:',
	'renameusermove' => 'Саҳифаи корбарӣ ва саҳифаи баҳси корбар (ва зерсаҳифаҳои он)ро интиқол бидеҳ',
	'renameuserreserve' => 'Бастани номи корбарии кӯҳна аз истифодаи оянда',
	'renameuserwarnings' => 'Ҳушдорҳо:',
	'renameuserconfirm' => 'Бале, номи корбариро тағйир бидеҳ',
	'renameusersubmit' => 'Сабт',
	'renameusererrordoesnotexist' => 'Номи корбарӣ "<nowiki>$1</nowiki>" вуҷуд надорад.',
	'renameusererrorexists' => 'Номи корбарӣ "<nowiki>$1</nowiki>" истифода шудааст.',
	'renameusererrorinvalid' => 'Номи корбарӣ "<nowiki>$1</nowiki>" ғайри миҷоз аст.',
	'renameuser-error-request' => 'Дар дарёфти дархост мушкилие пеш омад. Лутфан ба саҳифаи қаблӣ бозгардед ва дубора талош кунед.',
	'renameuser-error-same-user' => 'Шумо наметавонед номи як корбарро ба ҳамон номи қаблиаш тағйир диҳед.',
	'renameusersuccess' => 'Номи корбар "<nowiki>$1</nowiki>" ба "<nowiki>$2</nowiki>" тағйир ёфт.',
	'renameuser-page-exists' => 'Саҳифаи $1 аллакай вуҷуд дорда ва ба таври худкор қобили бознависӣ нест.',
	'renameuser-page-moved' => 'Саҳифаи $1 ба $2 кӯчонида шуд.',
	'renameuser-page-unmoved' => 'Имкони кӯчонидани саҳифаи $1 ба $2 вуҷуд надорад.',
	'log-name-renameuser' => 'Гузориши тағйири номи корбар',
	'renameuser-move-log' => 'Саҳифа дар вақти тағйири номи корбар  "[[User:$1|$1]]" ба "[[User:$2|$2]]" ба таври худкор кӯчонида шуд',
	'right-renameuser' => 'Тағйири номи корбарон',
);

/** Tajik (Latin script) (tojikī)
 * @author Liangent
 */
$messages['tg-latn'] = array(
	'renameuser' => 'Taƣjiri nomi korbarī',
	'renameuser-desc' => "Nomi jak korbarro taƣjir medihad (nijozmand ba ixtijoroti ''taƣjirinom'' ast)", # Fuzzy
	'renameuserold' => "Nomi korbari fe'lī:",
	'renameusernew' => 'Nomi korbari çadid:',
	'renameuserreason' => 'Illati taƣjiri nomi korbarī:', # Fuzzy
	'renameusermove' => 'Sahifai korbarī va sahifai bahsi korbar (va zersahifahoi on)ro intiqol bideh',
	'renameuserreserve' => 'Bastani nomi korbariji kūhna az istifodai ojanda',
	'renameuserwarnings' => 'Huşdorho:',
	'renameuserconfirm' => 'Bale, nomi korbariro taƣjir bideh',
	'renameusersubmit' => 'Sabt',
	'renameusererrordoesnotexist' => 'Nomi korbarī "<nowiki>$1</nowiki>" vuçud nadorad.',
	'renameusererrorexists' => 'Nomi korbarī "<nowiki>$1</nowiki>" istifoda şudaast.',
	'renameusererrorinvalid' => 'Nomi korbarī "<nowiki>$1</nowiki>" ƣajri miçoz ast.',
	'renameuser-error-request' => 'Dar darjofti darxost muşkilie peş omad. Lutfan ba sahifai qablī bozgarded va dubora taloş kuned.',
	'renameuser-error-same-user' => 'Şumo nametavoned nomi jak korbarro ba hamon nomi qabliaş taƣjir dihed.',
	'renameusersuccess' => 'Nomi korbar "<nowiki>$1</nowiki>" ba "<nowiki>$2</nowiki>" taƣjir joft.',
	'renameuser-page-exists' => 'Sahifai $1 allakaj vuçud dorda va ba tavri xudkor qobili boznavisī nest.',
	'renameuser-page-moved' => 'Sahifai $1 ba $2 kūconida şud.',
	'renameuser-page-unmoved' => 'Imkoni kūconidani sahifai $1 ba $2 vuçud nadorad.',
	'log-name-renameuser' => 'Guzorişi taƣjiri nomi korbar',
	'renameuser-move-log' => 'Sahifa dar vaqti taƣjiri nomi korbar  "[[User:$1|$1]]" ba "[[User:$2|$2]]" ba tavri xudkor kūconida şud',
	'right-renameuser' => 'Taƣjiri nomi korbaron',
);

/** Thai (ไทย)
 * @author Harley Hartwell
 * @author Mopza
 * @author Passawuth
 */
$messages['th'] = array(
	'renameuser' => 'เปลี่ยนชื่อผู้ใช้',
	'renameuser-desc' => "เพิ่ม[[Special:Renameuser|หน้าพิเศษ]] สำหรับเปลี่ยนชื่อผู้ใช้ (ต้องมีสิทธิ์ ''renameuser'' (เปลี่ยนชื่อผู้ใช้))",
	'renameuserold' => 'ชื่อผู้ใช้ปัจจุบัน:',
	'renameusernew' => 'ชื่อผู้ใช้ใหม่:',
	'renameuserreason' => 'เหตุผลในการเปลี่ยนชื่อ:', # Fuzzy
	'renameusermove' => 'ย้ายหน้าผู้ใช้และหน้าพูดคุย (รวมถึงหน้าย่อยด้วย) ไปยังชื่อใหม่',
	'renameuserreserve' => 'บล็อกชื่อผู้ใช้เดิมจากการใช้งานในอนาคต',
	'renameuserwarnings' => 'คำเตือน:',
	'renameuserconfirm' => 'ใช่, เปลี่ยนชื่อผู้ใช้นี้',
	'renameusersubmit' => 'ตกลง',
	'renameusererrordoesnotexist' => 'ไม่พบผู้ใช้ "<nowiki>$1</nowiki>" ในระบบ',
	'renameusererrorexists' => 'มีผู้ใช้ "<nowiki>$1</nowiki>" อยู่แล้ว',
	'renameusererrorinvalid' => 'ไม่สามารถใช้ชื่อผู้ใช้ "<nowiki>$1</nowiki>" ได้',
	'renameuser-error-request' => 'มีปัญหาเกิดขึ้นเกี่ยวกับการรับคำเรียกร้องของคุณ กรุณากลับไปที่หน้าเดิม และ พยายามอีกครั้ง',
	'renameuser-error-same-user' => 'ไม่สามารถเปลี่ยนชื่อผู้ใช้ได้เนื่องจากมีชื่อผู้ใช้นี้อยู่ก่อนแล้ว',
	'renameusersuccess' => 'ผู้ใช้:<nowiki>$1</nowiki> ถูกเปลี่ยนชื่อเป็น ผู้ใช้:<nowiki>$2</nowiki> เรียบร้อยแล้ว',
	'renameuser-page-exists' => 'หน้า $1 มีอยู่แล้ว และไม่สามารถย้ายไปแทนที่ได้โดยอัตโนมัติ',
	'renameuser-page-moved' => 'หน้า $1 ถูกย้ายไปยัง $2',
	'renameuser-page-unmoved' => 'ไม่สามารถย้ายหน้า $1 ไปยัง $2 ได้',
	'log-name-renameuser' => 'ปูมการเปลี่ยนชื่อผู้ใช้',
	'renameuser-move-log' => 'ย้ายโดยอัตโนมัติ ขณะเปลี่ยนชื่อผู้ใช้จาก "[[User:$1|$1]]" เป็น "[[User:$2|$2]]"',
	'right-renameuser' => 'เปลี่ยนชื่อผู้ใช้',
	'renameuser-renamed-notice' => 'ผู้ใช้นี้ได้ถูกเปลี่ยนชื่อ บันทึกการเปลี่ยนชื่อแสดงอยู่ด้านล่างสำหรับการอ้างอิง',
);

/** Turkmen (Türkmençe)
 * @author Hanberke
 */
$messages['tk'] = array(
	'renameuser' => 'Ulanyjy adyny üýtget',
	'renameuser-linkoncontribs' => 'ulanyjy adyny üýtget',
	'renameuser-linkoncontribs-text' => 'Bu ulanyjynyň adyny üýtget',
	'renameuser-desc' => "Ulanyjyny täzeden atlandyrmak üçin [[Special:Renameuser|ýörite sahypa]] goşýar (''ulanyjynytäzedenatlandyr'' hukugy gerek)",
	'renameuserold' => 'Häzirki ulanyjy ady:',
	'renameusernew' => 'Täze ulanyjy ady:',
	'renameuserreason' => 'At üýtgetmegiň sebäbi:', # Fuzzy
	'renameusermove' => 'Ulanyjy we pikir alyşma sahypalaryny (we kiçi sahypalaryny) täze ada geçir',
	'renameusersuppress' => 'Täze ada gönükdirmeler döretme',
	'renameuserreserve' => 'Köne ulanyjy adyny indi ulanylmakdan blokirle',
	'renameuserwarnings' => 'Duýduryşlar:',
	'renameuserconfirm' => 'Hawa, ulanyjynyň adyny üýtget',
	'renameusersubmit' => 'Tabşyr',
	'renameusererrordoesnotexist' => '"<nowiki>$1</nowiki>" atly ulanyjy ýok.',
	'renameusererrorexists' => '"<nowiki>$1</nowiki>" ulanyjysy eýýäm bar.',
	'renameusererrorinvalid' => '"<nowiki>$1</nowiki>" ulanyjy ady nädogry.',
	'renameuser-error-request' => 'Talaby almak bilen baglanyşykyly bir probleme ýüze çykdy.
Yza gaýdyp gaýtadan synanyşyp görüň.',
	'renameuser-error-same-user' => 'Ulanyja öňküsi ýaly bir ada täzeden geçirip bilmeýärsiňiz.',
	'renameusersuccess' => 'Ulanyjy "<nowiki>$1</nowiki>" täze ada geçirildi: "<nowiki>$2</nowiki>".',
	'renameuser-page-exists' => '$1 sahypasy eýýäm bar we onuň üstüne awtomatik ýazyp bolmaýar.',
	'renameuser-page-moved' => '$1 sahypasy $2 sahypasyna geçirildi.',
	'renameuser-page-unmoved' => '$1 sahypasyny $2 sahypasyna geçirip bolmaýar.',
	'log-name-renameuser' => 'Ulanyjy adyny üýtgetme gündeligi',
	'renameuser-move-log' => 'Ulanyjy "[[User:$1|$1]]" adyndan "[[User:$2|$2]]" adyna täzeden atlandyrylanda, sahypa awtomatik geçirildi',
	'right-renameuser' => 'Ulanyjylaryň adyny üýtget',
	'renameuser-renamed-notice' => 'Bu ulanyjynyň ady üýtgedilipdir.
At üýtgediş gündeligi aşakda salgylanma üçin berilýär.',
);

/** Tagalog (Tagalog)
 * @author AnakngAraw
 */
$messages['tl'] = array(
	'renameuser' => 'Muling pangalanan ang tagagamit',
	'renameuser-linkoncontribs' => 'muling pangalanan ang tagagamit',
	'renameuser-linkoncontribs-text' => 'muling pangalanan ang tagagamit na ito',
	'renameuser-desc' => "Nagdaragdag ng isang [[Special:Renameuser|natatanging pahina]] para mapangalanang muli ang isang tagagamit (kailangang ang karapatang ''pangalanangmuliangtagagamit'')",
	'renameuserold' => 'Pangkasalukuyang pangalan ng tagagamit:',
	'renameusernew' => 'Bagong pangalan ng tagagamit:',
	'renameuserreason' => 'Dahil para sa muling pagpapangalan:', # Fuzzy
	'renameusermove' => 'Ilipat ang mga pahina ng tagagamit at pangusapan (at mga kabahaging pahina nila) patungo sa bagong pangalan',
	'renameusersuppress' => 'Huwag lumikha ng mga pagpapapunta sa bagong  pangalan',
	'renameuserreserve' => 'Hadlangan ang dating pangalan ng tagagamit mula sa muling paggamit sa hinaharap',
	'renameuserwarnings' => 'Mga babala:',
	'renameuserconfirm' => 'Oo, pangalanang muli ang tagagamit',
	'renameusersubmit' => 'Ipasa',
	'renameuser-submit-blocklog' => 'Ipakita ang talaan ng pagharang para sa tagagamit',
	'renameusererrordoesnotexist' => 'Hindi pa umiiral ang tagagamit na "<nowiki>$1</nowiki>".',
	'renameusererrorexists' => 'Umiiral na ang tagagamit na "<nowiki>$1</nowiki>".',
	'renameusererrorinvalid' => 'Hindi tanggap ang pangalan ng tagagamit na "<nowiki>$1</nowiki>".',
	'renameuser-error-request' => 'Nagkaroon ng isang suliranin sa pagtanggap ng kahilingan.
Magbalik lamang at subukan uli.',
	'renameuser-error-same-user' => 'Hindi mo maaaring pangalanang muli ang tagagamit patungo sa kaparehong bagay na katulad ng dati.',
	'renameusersuccess' => 'Ang tagagamit na "<nowiki>$1</nowiki>" ay muling napangalanan na patungong "<nowiki>$2</nowiki>".',
	'renameuser-page-exists' => 'Umiiral na ang pahinang $1 at hindi maaaring kusang mapatungan.',
	'renameuser-page-moved' => 'Ang pahinang $1 ay nailipat na patungo sa $2.',
	'renameuser-page-unmoved' => 'Hindi mailipat ang pahinang $1 patungo sa $2.',
	'log-name-renameuser' => 'Talaan ng muling pagpapangalan ng tagagamit',
	'renameuser-move-log' => 'Kusang inilipat ang pahina habang muling pinapangalanan ang tagagamit na si "[[User:$1|$1]]" patungo sa "[[User:$2|$2]]"',
	'action-renameuser' => 'muling pangalanan ang mga tagagamit',
	'right-renameuser' => 'Muling pangalanan ang mga tagagamit',
	'renameuser-renamed-notice' => 'Napangalanan nang muli ang tagagamit na ito.
Ibinigay sa ibaba ang talaan ng pagpapangalang muli para masangguni.',
);

/** Tongan (lea faka-Tonga)
 */
$messages['to'] = array(
	'renameuser' => 'Liliu hingoa ʻo e ʻetita',
	'renameuserold' => 'Hingoa motuʻa ʻo e ʻetita:',
	'renameusernew' => 'Hingoa foʻou ʻo e ʻetita:',
	'renameusersubmit' => 'Fai ā liliuhingoa',
	'renameusererrordoesnotexist' => 'Ko e ʻetita "<nowiki>$1</nowiki>" ʻoku ʻikai toka tuʻu ia',
	'renameusererrorexists' => 'Ko e ʻetita "<nowiki>$1</nowiki>" ʻoku toka tuʻu ia',
	'renameusererrorinvalid' => 'ʻOku taʻeʻaonga ʻa e hingoa fakaʻetita ko "<nowiki>$1</nowiki>"',
	'renameusersuccess' => 'Ko e ʻetita "<nowiki>$1</nowiki>" kuo liliuhingoa ia kia "<nowiki>$2</nowiki>"',
	'log-name-renameuser' => 'Tohinoa ʻo e liliu he hingoa ʻo e ʻetita',
);

/** Turkish (Türkçe)
 * @author Joseph
 * @author Karduelis
 * @author Runningfridgesrule
 * @author Uğur Başak
 * @author Vito Genovese
 */
$messages['tr'] = array(
	'renameuser' => 'Kullanıcı adı değiştir',
	'renameuser-linkoncontribs' => 'kullanıcıyı yeniden adlandır',
	'renameuser-linkoncontribs-text' => 'Bu kullanıcıyı yeniden adlandır',
	'renameuser-desc' => "Kullanıcıyı yeniden adlandırmak için bir [[Special:Renameuser|özel sayfa]] ekler (''kullanıcıyıyenidenadlandır'' hakkı gerekir)",
	'renameuserold' => 'Şu anda ki kullanıcı adı:',
	'renameusernew' => 'Yeni kullanıcı adı:',
	'renameuserreason' => 'Neden:', # Fuzzy
	'renameusermove' => 'Kullanıcı ve tartışma sayfalarını (ve alt sayfalarını) yeni isme taşı',
	'renameusersuppress' => 'Yeni ada yönlendirmeler oluşturma',
	'renameuserreserve' => 'Eski kullanıcı adını ilerdeki kullanımlar için engelle',
	'renameuserwarnings' => 'Uyarılar:',
	'renameuserconfirm' => 'Evet, kullanıcıyı yeniden adlandır',
	'renameusersubmit' => 'Gönder',
	'renameusererrordoesnotexist' => '"<nowiki>$1</nowiki>" adlı kullanıcı bulunmamaktadır.',
	'renameusererrorexists' => '"<nowiki>$1</nowiki>" kullanıcısı zaten mevcut.',
	'renameusererrorinvalid' => '"<nowiki>$1</nowiki>" kullanıcı adı geçersiz.',
	'renameuser-error-request' => 'İsteğin alımıyla ilgili bir problem var.
Lütfen geri dönüp tekrar deneyin.',
	'renameuser-error-same-user' => 'Bir kullanıcıyı eskiden olduğu isme yeniden adlandıramazsınız.',
	'renameusersuccess' => 'Daha önce "<nowiki>$1</nowiki>" olarak kayıtlı kullanıcının rumuzu "<nowiki>$2</nowiki>" olarak değiştirilmiştir.',
	'renameuser-page-exists' => '$1 sayfası zaten mevcut ve otomatik olarak üstüne yazılamaz.',
	'renameuser-page-moved' => '$1 sayfası $2 sayfasına taşındı.',
	'renameuser-page-unmoved' => '$1 sayfası $2 sayfasına taşınamıyor.',
	'log-name-renameuser' => 'Kullanıcı adı değişikliği kayıtları',
	'renameuser-move-log' => 'Kullanıcıyı "[[User:$1|$1]]" isminden "[[User:$2|$2]]" ismine yeniden adlandırırken, sayfa otomatik olarak taşındı',
	'right-renameuser' => 'Kullanıcıların adlarını değiştirir',
	'renameuser-renamed-notice' => 'Bu kullanıcının adı değiştirildi.
Referans için ad değiştirme günlüğü aşağıda sağlanmıştır.',
);

/** Uyghur (Arabic script) (ئۇيغۇرچە)
 * @author Sahran
 */
$messages['ug-arab'] = array(
	'renameuserreason' => 'سەۋەب:',
	'renameuserwarnings' => 'ئاگاھلاندۇرۇشلار:',
	'renameusersubmit' => 'تاپشۇر',
	'renameuser-page-exists' => '$1 بەت مەۋجۇد، ئۆزلۈكىدىن قاپلىۋەتكىلى بولمايدۇ.',
	'renameuser-page-moved' => '$1 بەت $2 گە يۆتكەلدى.',
	'renameuser-page-unmoved' => '$1 بەتنى $2 گە يۆتكىيەلمىدى.',
);

/** Ukrainian (українська)
 * @author A1
 * @author AS
 * @author Ahonc
 * @author Base
 * @author EugeneZelenko
 * @author Microcell
 * @author Prima klasy4na
 * @author Тест
 */
$messages['uk'] = array(
	'renameuser' => 'Перейменувати користувача',
	'renameuser-linkoncontribs' => 'перейменувати користувача',
	'renameuser-linkoncontribs-text' => 'Перейменувати цього користувача',
	'renameuser-desc' => "Перейменування користувача (потрібні права ''renameuser'')",
	'renameuserold' => "Поточне ім'я:",
	'renameusernew' => "Нове ім'я:",
	'renameuserreason' => 'Причина:',
	'renameusermove' => 'Перейменувати також сторінку користувача, сторінку обговорення та їхні підсторінки',
	'renameusersuppress' => 'Не створюйте перенаправлення на нову назву',
	'renameuserreserve' => "Зарезервувати старе ім'я користувача для подальшого використання",
	'renameuserwarnings' => 'Попередження:',
	'renameuserconfirm' => 'Так, перейменувати користувача',
	'renameusersubmit' => 'Виконати',
	'renameuser-submit-blocklog' => 'Показати журнал блокувань користувача',
	'renameusererrordoesnotexist' => 'Користувач з іменем «<nowiki>$1</nowiki>» не зареєстрований.',
	'renameusererrorexists' => 'Користувач з іменем «<nowiki>$1</nowiki>» уже зареєстрований.',
	'renameusererrorinvalid' => "Недопустиме ім'я користувача: <nowiki>$1</nowiki>.",
	'renameuser-error-request' => 'Виникли ускладнення з отриманням запиту. Будь ласка, поверніться назад і повторіть іще раз.',
	'renameuser-error-same-user' => "Ви не можете змінити ім'я користувача на те саме, що було раніше.",
	'renameusersuccess' => 'Користувач «<nowiki>$1</nowiki>» був перейменований на «<nowiki>$2</nowiki>».',
	'renameuser-page-exists' => 'Сторінка $1 вже існує і не може бути перезаписана автоматично.',
	'renameuser-page-moved' => 'Сторінка $1 була перейменована на $2.',
	'renameuser-page-unmoved' => 'Сторінка $1 не може бути перейменована на $2.',
	'log-name-renameuser' => 'Журнал перейменувань користувачів',
	'log-description-renameuser' => 'Це журнал перейменувань зареєстрованих користувачів.',
	'logentry-renameuser-renameuser' => '$1 {{GENDER:$2|перейменував|перейменувала}} $4 ({{PLURAL:$6|$6 редагування|$6 редагування|$6 редагувань}}) на $5',
	'logentry-renameuser-renameuser-legacier' => '$1 {{GENDER:$2|перейменував|перейменувала}} $4 на $5',
	'renameuser-move-log' => 'Автоматичне перейменування сторінки при перейменуванні користувача «[[User:$1|$1]]» на «[[User:$2|$2]]»',
	'action-renameuser' => 'перейменування користувачів',
	'right-renameuser' => 'Перейменування користувачів',
	'renameuser-renamed-notice' => 'Цей користувач був перейменований.
Для довідки нижче наведений журнал перейменувань.',
);

/** Urdu (اردو)
 * @author පසිඳු කාවින්ද
 */
$messages['ur'] = array(
	'renameuser' => 'صارف کا نام تبدیل کریں',
	'renameuserwarnings' => 'انتباہ:',
	'renameusersubmit' => 'جمع کرائیں',
	'action-renameuser' => 'صارفین کو نیا نام دیںکے',
	'right-renameuser' => 'صارفین کو نیا نام دیںکے',
);

/** Uzbek (oʻzbekcha)
 * @author CoderSI
 */
$messages['uz'] = array(
	'log-name-renameuser' => 'Ishtirokchilarni qayta nomlash qaydlari',
);

/** vèneto (vèneto)
 * @author Candalua
 * @author GatoSelvadego
 */
$messages['vec'] = array(
	'renameuser' => 'Rinomina utente',
	'renameuser-linkoncontribs' => 'rinomina utente',
	'renameuser-linkoncontribs-text' => 'Rinomina sto utente',
	'renameuser-desc' => "Funsion par rinominar un utente (ghe vole i diriti de ''renameuser'')",
	'renameuserold' => 'Vecio nome utente:',
	'renameusernew' => 'Novo nome utente:',
	'renameuserreason' => 'Motivo:',
	'renameusermove' => 'Rinomina anca la pagina utente, la pagina de discussion e le relative sotopagine',
	'renameusersuppress' => 'No stà crear rimandi al nome novo',
	'renameuserreserve' => "Tien da conto el vecio nome utente par inpedir che'l vegna doparà in futuro",
	'renameuserwarnings' => 'Avertimenti:',
	'renameuserconfirm' => "Sì, rinomina l'utente",
	'renameusersubmit' => 'Invia',
	'renameuser-submit-blocklog' => "Mostra registro de i blochi pa'l utente",
	'renameusererrordoesnotexist' => 'El nome utente "<nowiki>$1</nowiki>" no l\'esiste',
	'renameusererrorexists' => 'El nome utente "<nowiki>$1</nowiki>" l\'esiste de zà',
	'renameusererrorinvalid' => 'El nome utente "<nowiki>$1</nowiki>" no\'l xe mìa valido.',
	'renameuser-error-request' => 'Se gà verificà un problema ne la ricezion de la richiesta. Torna indrìo e ripróa da novo.',
	'renameuser-error-same-user' => "No se pol rinominar un utente al stesso nome che'l gavea zà.",
	'renameusersuccess' => 'El nome utente "<nowiki>$1</nowiki>" el xe stà canbià in "<nowiki>$2</nowiki>"',
	'renameuser-page-exists' => 'La pagina $1 la esiste de zà; no se pole sovrascrìvarla automaticamente.',
	'renameuser-page-moved' => 'La pagina $1 la xe stà spostà a $2.',
	'renameuser-page-unmoved' => 'No se pole spostar la pagina $1 a $2.',
	'log-name-renameuser' => 'Registro dei utenti rinominà',
	'log-description-renameuser' => 'Sto cuà el xe el registro de łe modifeghe a i nome utente.',
	'logentry-renameuser-renameuser' => "$1 {{GENDER:$2|ga rinominà}} 'l utente $4 (có {{PLURAL:$6|$6 contributo|$6 contributi}}) in $5",
	'logentry-renameuser-renameuser-legacier' => "$1 ga rinominà 'l utente $4 in $5",
	'renameuser-move-log' => 'Spostamento automatico de la pagina - utente rinominà da "[[User:$1|$1]]" a "[[User:$2|$2]]"',
	'action-renameuser' => 'rinominar i utenti',
	'right-renameuser' => 'Rinomina utenti',
	'renameuser-renamed-notice' => 'Sto utente el gà canbià nome.
Qua soto ghe xe el riferimento sul registro de rinomina.',
);

/** Veps (vepsän kel’)
 * @author Игорь Бродский
 */
$messages['vep'] = array(
	'renameuser' => 'Udesnimitada kävutajad',
	'renameuserold' => 'Nügüdläine kävutajannimi:',
	'renameusernew' => "Uz' kävutajan nimi:",
	'renameuserreason' => 'Udesnimitandan sü:', # Fuzzy
	'renameusersubmit' => 'Tehta',
	'right-renameuser' => 'Udesnimitada kävutajid',
);

/** Vietnamese (Tiếng Việt)
 * @author Minh Nguyen
 * @author Vinhtantran
 */
$messages['vi'] = array(
	'renameuser' => 'Đổi tên thành viên',
	'renameuser-linkoncontribs' => 'đổi tên thành viên',
	'renameuser-linkoncontribs-text' => 'Đổi tên thành viên này',
	'renameuser-desc' => "Đổi tên thành viên (cần có quyền ''renameuser'')",
	'renameuserold' => 'Tên hiệu hiện nay:',
	'renameusernew' => 'Tên hiệu mới:',
	'renameuserreason' => 'Lý do:',
	'renameusermove' => 'Di chuyển trang thành viên và thảo luận thành viên (cùng với trang con của nó) sang tên mới',
	'renameusersuppress' => 'Không tạo trang đổi hướng đến tên mới',
	'renameuserreserve' => 'Không cho phép ai lấy tên cũ',
	'renameuserwarnings' => 'Cảnh báo:',
	'renameuserconfirm' => 'Đổi tên người dùng',
	'renameusersubmit' => 'Thực hiện',
	'renameuser-submit-blocklog' => 'Xem nhật trình cấm người dùng',
	'renameusererrordoesnotexist' => 'Thành viên “<nowiki>$1</nowiki>” không tồn tại.',
	'renameusererrorexists' => 'Thành viên “<nowiki>$1</nowiki>” đã hiện hữu.',
	'renameusererrorinvalid' => 'Tên thành viên “<nowiki>$1</nowiki>” không hợp lệ.',
	'renameuser-error-request' => 'Có trục trặc trong tiếp nhận yêu cầu. Xin hãy quay lại và thử lần nữa.',
	'renameuser-error-same-user' => 'Bạn không thể đổi tên thành viên sang tên y hệt như vậy.',
	'renameusersuccess' => 'Thành viên “<nowiki>$1</nowiki>” đã được đổi tên thành “<nowiki>$2</nowiki>”.',
	'renameuser-page-exists' => 'Trang $1 đã tồn tại và không thể bị tự động ghi đè.',
	'renameuser-page-moved' => 'Trang $1 đã được di chuyển đến $2.',
	'renameuser-page-unmoved' => 'Trang $1 không thể di chuyển đến $2.',
	'log-name-renameuser' => 'Nhật trình đổi tên thành viên',
	'log-description-renameuser' => 'Đây là nhật trình ghi lại các thay đổi đối với tên thành viên',
	'logentry-renameuser-renameuser' => '{{GENDER:$2}}$1 đã đổi tên thành viên $4 ($6 lần sửa đổi) thành $5',
	'logentry-renameuser-renameuser-legacier' => '$1 đã đổi tên thành viên $4 thành $5',
	'renameuser-move-log' => 'Đã tự động di chuyển trang khi đổi tên thành viên “[[User:$1|$1]]” thành “[[User:$2|$2]]”',
	'action-renameuser' => 'đổi tên thành viên',
	'right-renameuser' => 'Đổi tên thành viên',
	'renameuser-renamed-notice' => 'Thành viên này đã được đổi tên.
Nhật trình đổi tên được ghi ở dưới để tiện theo dõi.',
);

/** Volapük (Volapük)
 * @author Malafaya
 * @author Smeira
 */
$messages['vo'] = array(
	'renameuser' => 'Votanemön gebani',
	'renameuser-linkoncontribs' => 'votanemön gebani',
	'renameuser-linkoncontribs-text' => 'Votanemön gebani at',
	'renameuser-desc' => "Votanemön gebani (gität: ''renameuser'' zesüdon)",
	'renameuserold' => 'Gebananem anuik:',
	'renameusernew' => 'Gebananem nulik:',
	'renameuserreason' => 'Kod votanemama:', # Fuzzy
	'renameusermove' => 'Topätükön padi e bespikapadi gebana (e donapadis onsik) ad nem nulik',
	'renameuserreserve' => 'Neletön gebananemi rigik (pos votanemam) ad pagebön ün fütür',
	'renameuserwarnings' => 'Nuneds:',
	'renameuserconfirm' => 'Si, votanemolös gebani',
	'renameusersubmit' => 'Sedön',
	'renameusererrordoesnotexist' => 'Geban: "<nowiki>$1</nowiki>" no dabinon.',
	'renameusererrorexists' => 'Geban: "<nowiki>$1</nowiki>" ya dabinon.',
	'renameusererrorinvalid' => 'Gebananem: "<nowiki>$1</nowiki>" no lonöfon.',
	'renameuser-error-request' => 'Ädabinon säkäd pö daget bega. Geikolös, begö! e steifülolös dönu.',
	'renameuser-error-same-user' => 'No kanol votanemön gebani ad nem ot.',
	'renameusersuccess' => 'Geban: "<nowiki>$1</nowiki>" pevotanemon ad "<nowiki>$2</nowiki>".',
	'renameuser-page-exists' => 'Pad: $1 ya dabinon e no kanon pamoükön itjäfidiko.',
	'renameuser-page-moved' => 'Pad: $1 petopätükon ad pad: $2.',
	'renameuser-page-unmoved' => 'No eplöpos ad topätükön padi: $1 ad pad: $2.',
	'log-name-renameuser' => 'Jenotalised votanemamas',
	'renameuser-move-log' => 'Pad petopätükon itjäfidiko dü votanemama gebana: "[[User:$1|$1]]" ad "[[User:$2|$2]]"',
	'right-renameuser' => 'Votanemön gebanis',
);

/** Walloon (walon)
 * @author Srtxg
 */
$messages['wa'] = array(
	'renameuser' => 'Rilomer èn uzeu',
	'renameuserold' => "No d' elodjaedje pol moumint:",
	'renameusernew' => "Novea no d' elodjaedje:",
	'renameuserreason' => 'Råjhon pol rilomaedje:', # Fuzzy
	'renameusermove' => "Displaecî les pådjes d' uzeu et d' copene (eyet leus dzo-pådjes) viè l' novea no",
	'renameuserwarnings' => 'Adviertixhmints:',
	'renameusersubmit' => 'Evoye',
	'renameusererrordoesnotexist' => "L' uzeu «<nowiki>$1</nowiki>» n' egzistêye nén",
	'renameusererrorexists' => "L' uzeu «<nowiki>$1</nowiki>» egzistêye dedja",
	'renameusererrorinvalid' => "Li no d' elodjaedje «<nowiki>$1</nowiki>» n' est nén on no valide",
	'renameusersuccess' => "L' uzeu «<nowiki>$1</nowiki>» a stî rlomé a «<nowiki>$2</nowiki>»",
	'renameuser-page-exists' => "Li pådje $1 egzistêye dedja et n' pout nén esse otomaticmint spotcheye.",
	'renameuser-page-moved' => 'Li pådje $1 a stî displaeceye viè $2.',
	'renameuser-page-unmoved' => 'Li pådje $1 èn pout nén esse displaeceye viè $2.',
	'log-name-renameuser' => "Djournå des candjmints d' no d' uzeus",
	'renameuser-move-log' => "Pådje displaeceye otomaticmint tot rlomant l' uzeu «[[User:$1|$1]]» viè «[[User:$2|$2]]»",
);

/** Yiddish (ייִדיש)
 * @author פוילישער
 */
$messages['yi'] = array(
	'renameuser' => 'בײַטן באַניצער נאָמען',
	'renameuser-linkoncontribs' => 'בײַטן באַניצער נאָמען',
	'renameuser-linkoncontribs-text' => 'בײַטן נאָמען פֿון דעם באַניצער',
	'renameuser-desc' => "לייגט צו א [[Special:Renameuser|באַזונדערן בלאַט]] צו בײַטן א באַניצער נאָמען (פֿאדערט ''renameuser'' רעכט)",
	'renameuserold' => 'לויפיגער באַניצער-נאָמען:',
	'renameusernew' => 'נײַער באַניצער-נאָמען:',
	'renameuserreason' => 'אורזאַך:',
	'renameusermove' => 'באַוועגן באַניצער און שמועס בלעטער (מיט זייערע אונטערבלעטער) צו נײַעם נאָמען',
	'renameusersuppress' => 'שאַפֿט נישט קיין ווייטערפֿירונגען צום נײַעם נאָמען',
	'renameuserreserve' => 'בלאקירן דעם אַלטן באַניצער־נאָמען פֿון נוץ אין צוקונפֿט',
	'renameuserwarnings' => 'ווארענונגען:',
	'renameuserconfirm' => 'יאָ, ענדער דעם באַניצער־נאָמען',
	'renameusersubmit' => 'אײַנגעבן',
	'renameuser-submit-blocklog' => 'ווײַזן בלאקירן לאג פאר באניצער',
	'renameusererrordoesnotexist' => 'דער באַניצער "<nowiki>$1</nowiki>" עקזיסטירט נישט.',
	'renameusererrorexists' => 'דער באַניצער "<nowiki>$1</nowiki>" עקזיסטירט שוין.',
	'renameusererrorinvalid' => 'דער באַניצער נאָמען  "<nowiki>$1</nowiki>" איז נישט גילטיק.',
	'renameuser-error-request' => 'געווען א פראבלעם מיט באַקומען די בקשה.
ביטע גייט צוריק און פרואווט ווידעראַמאָל.',
	'renameuser-error-same-user' => 'מען קען נישט ענדערן א באַניצער צום זעלבן נאָמען ווי פֿריער.',
	'renameusersuccess' => 'דער באַניצער־נאָמען "<nowiki>$1</nowiki>" איז געווארן געענדערט צו "<nowiki>$2</nowiki>".',
	'renameuser-page-exists' => "דער בלאַט $1 עקזיסטירט שוין און מ'קען אים נישט אויטאָמאַטיש איבערשרײַבן.",
	'renameuser-page-moved' => 'דער בלאַט $1 איז געווארן באַוועגט צו $2.',
	'renameuser-page-unmoved' => 'מען קען נישט באַוועגן דעם בלאַט $1 צו $2.',
	'log-name-renameuser' => 'באַניצער נאָמען-טויש לאָג-בוך',
	'renameuser-move-log' => 'אויטאמאַטיש באַוועגט בלאַט דורך ענדערן באַניצער־נאָמען פֿון "[[User:$1|$1]]" צו "[[User:$2|$2]]"',
	'action-renameuser' => 'בײַטן באַניצער נעמען',
	'right-renameuser' => 'בײַטן באַניצער נעמען',
	'renameuser-renamed-notice' => 'דער נאָמען פֿון דעם באַניצער איז געענדערט געווארן.
דער ענדערן נעמען לאגבוך ווערט געוויזן אונטן.',
);

/** Yoruba (Yorùbá)
 * @author Demmy
 */
$messages['yo'] = array(
	'renameuserold' => 'Orúkọ oníṣe ìsinsìnyí:',
	'renameusernew' => 'Orúkọ oníṣe tuntun:',
	'renameuserwarnings' => 'Àwọn ìkìlọ̀:',
	'renameusersubmit' => 'Fúnsílẹ̀',
	'renameusererrordoesnotexist' => 'Oníṣe "<nowiki>$1</nowiki>" kò sí.',
	'renameusererrorexists' => 'Oníṣe "<nowiki>$1</nowiki>" tilẹ̀ wà tẹ́lẹ̀.',
);

/** Cantonese (粵語)
 */
$messages['yue'] = array(
	'renameuser' => '改用戶名',
	'renameuser-desc' => "幫用戶改名 (需要 ''renameuser'' 權限)",
	'renameuserold' => '現時嘅用戶名:',
	'renameusernew' => '新嘅用戶名:',
	'renameuserreason' => '改名嘅原因:', # Fuzzy
	'renameusermove' => '搬用戶頁同埋佢嘅對話頁（同埋佢哋嘅細頁）到新名',
	'renameuserwarnings' => '警告:',
	'renameuserconfirm' => '係，改呢個用戶名',
	'renameusersubmit' => '遞交',
	'renameusererrordoesnotexist' => '用戶"<nowiki>$1</nowiki>"唔存在',
	'renameusererrorexists' => '用戶"<nowiki>$1</nowiki>"已經存在',
	'renameusererrorinvalid' => '用戶名"<nowiki>$1</nowiki>"唔正確',
	'renameuser-error-request' => '響收到請求嗰陣出咗問題。
請返去再試過。',
	'renameuser-error-same-user' => '你唔可以改一位用戶係同之前嘅嘢一樣。',
	'renameusersuccess' => '用戶"<nowiki>$1</nowiki>"已經改咗名做"<nowiki>$2</nowiki>"',
	'renameuser-page-exists' => '$1呢一版已經存在，唔可以自動重寫。',
	'renameuser-page-moved' => '$1呢一版已經搬到去$2。',
	'renameuser-page-unmoved' => '$1呢一版唔能夠搬到去$2。',
	'log-name-renameuser' => '用戶改名日誌',
	'renameuser-move-log' => '當由"[[User:$1|$1]]"改名做"[[User:$2|$2]]"嗰陣已經自動搬咗用戶頁',
	'right-renameuser' => '改用戶名',
);

/** Simplified Chinese (中文（简体）‎)
 * @author Bencmq
 * @author Gaoxuewei
 * @author Gzdavidwong
 * @author Hydra
 * @author Hzy980512
 * @author Liangent
 * @author PhiLiP
 * @author Shizhao
 * @author Xiaomingyan
 * @author Yfdyh000
 */
$messages['zh-hans'] = array(
	'renameuser' => '更改用户名',
	'renameuser-linkoncontribs' => '更改用户名',
	'renameuser-linkoncontribs-text' => '更改该用户名',
	'renameuser-desc' => "添加更改用户名的[[Special:Renameuser|特殊页面]]（需要''renameuser''权限）",
	'renameuserold' => '当前用户名：',
	'renameusernew' => '新用户名：',
	'renameuserreason' => '原因：',
	'renameusermove' => '移动用户和讨论页面（和子页面）至新用户名',
	'renameusersuppress' => '不创建至新用户名的重定向页',
	'renameuserreserve' => '封锁旧用户名，使其不能在未来使用',
	'renameuserwarnings' => '警告：',
	'renameuserconfirm' => '是，更改用户名',
	'renameusersubmit' => '提交',
	'renameuser-submit-blocklog' => '显示用户的封禁日志',
	'renameusererrordoesnotexist' => '用户“<nowiki>$1</nowiki>”不存在。',
	'renameusererrorexists' => '用户“<nowiki>$1</nowiki>”已经存在。',
	'renameusererrorinvalid' => '用户名“<nowiki>$1</nowiki>”无效。',
	'renameuser-error-request' => '接收申请出错。请返回重试。',
	'renameuser-error-same-user' => '你不可以填写原来的用户名。',
	'renameusersuccess' => '用户“<nowiki>$1</nowiki>”已更名为“<nowiki>$2</nowiki>”。',
	'renameuser-page-exists' => '页面$1己经存在，不能被自动覆盖。',
	'renameuser-page-moved' => '页面$1已移动至$2。',
	'renameuser-page-unmoved' => '页面$1不能移动至$2。',
	'log-name-renameuser' => '用户更名日志',
	'log-description-renameuser' => '这是对用户名改动的日志。',
	'logentry-renameuser-renameuser' => '$1{{GENDER:$2|将}}用户$4（$6次编辑）重命名成$5',
	'logentry-renameuser-renameuser-legacier' => '$1将用户$4重命名成$5',
	'renameuser-move-log' => '当更改用户名“[[User:$1|$1]]”为“[[User:$2|$2]]”时自动移动页面',
	'action-renameuser' => '重命名用户',
	'right-renameuser' => '更改用户名',
	'renameuser-renamed-notice' => '本用户已更名。下面提供更名日志以供参考。',
);

/** Traditional Chinese (中文（繁體）‎)
 * @author Gaoxuewei
 * @author Horacewai2
 * @author Liangent
 * @author Mark85296341
 * @author Simon Shek
 * @author Waihorace
 * @author Wrightbus
 */
$messages['zh-hant'] = array(
	'renameuser' => '用戶重新命名',
	'renameuser-linkoncontribs' => '用戶重新命名',
	'renameuser-linkoncontribs-text' => '重命名此用戶',
	'renameuser-desc' => "新增一個[[Special:Renameuser|特殊頁面]]來重命名用戶（需要''renameuser''權限）",
	'renameuserold' => '現時用戶名：',
	'renameusernew' => '新的使用者名稱：',
	'renameuserreason' => '原因：',
	'renameusermove' => '移動用戶頁及其對話頁（包括各子頁）到新的名字',
	'renameusersuppress' => '不要建立重定向到新的名稱',
	'renameuserreserve' => '封禁舊使用者名稱，使之不能在日後使用',
	'renameuserwarnings' => '警告：',
	'renameuserconfirm' => '是，為用戶重新命名',
	'renameusersubmit' => '提交',
	'renameuser-submit-blocklog' => '顯示用戶的封禁日誌',
	'renameusererrordoesnotexist' => '用戶「<nowiki>$1</nowiki>」不存在',
	'renameusererrorexists' => '用戶「<nowiki>$1</nowiki>」已存在',
	'renameusererrorinvalid' => '用戶名「<nowiki>$1</nowiki>」不可用',
	'renameuser-error-request' => '在收到請求時出現問題。
請回去重試。',
	'renameuser-error-same-user' => '您不可以更改一位用戶是跟之前的東西一樣。',
	'renameusersuccess' => '用戶「<nowiki>$1</nowiki>」已經更名為「<nowiki>$2</nowiki>」',
	'renameuser-page-exists' => '$1 這一頁己經存在，不能自動覆寫。',
	'renameuser-page-moved' => '$1 這一頁已經移動到 $2。',
	'renameuser-page-unmoved' => '$1 這一頁不能移動到 $2。',
	'log-name-renameuser' => '用戶名變更日誌',
	'log-description-renameuser' => '這是用戶名更改的日誌',
	'logentry-renameuser-renameuser' => '$1{{GENDER:$2|重命名}}用戶$4（{{PLURAL:$6|$6次|$6次}}編輯）成$5',
	'logentry-renameuser-renameuser-legacier' => '$1重命名用戶$4成$5',
	'renameuser-move-log' => '當由「[[User:$1|$1]]」重新命名作「[[User:$2|$2]]」時已經自動移動用戶頁',
	'action-renameuser' => '重命名用戶',
	'right-renameuser' => '重新命名用戶',
	'renameuser-renamed-notice' => '該用戶已被重新命名。
以下列出更改用戶名日誌以供參考。',
);

/** Zulu (isiZulu)
 */
$messages['zu'] = array(
	'renameusersubmit' => 'Yisa',
);
