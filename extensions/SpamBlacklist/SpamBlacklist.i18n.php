<?php
/**
 * Internationalisation file for extension SpamBlacklist.
 *
 * @file
 * @ingroup Extensions
 */

$messages = array();

$messages['en'] = array(
	'spam-blacklist' => ' #<!-- leave this line exactly as it is --> <pre>
# External URLs matching this list will be blocked when added to a page.
# This list affects only this wiki; refer also to the global blacklist.
# For documentation see https://www.mediawiki.org/wiki/Extension:SpamBlacklist
#
# Syntax is as follows:
#   * Everything from a "#" character to the end of the line is a comment
#   * Every non-blank line is a regex fragment which will only match hosts inside URLs

 #</pre> <!-- leave this line exactly as it is -->',
	'spam-whitelist' => ' #<!-- leave this line exactly as it is --> <pre>
# External URLs matching this list will *not* be blocked even if they would
# have been blocked by blacklist entries.
#
# Syntax is as follows:
#   * Everything from a "#" character to the end of the line is a comment
#   * Every non-blank line is a regex fragment which will only match hosts inside URLs

 #</pre> <!-- leave this line exactly as it is -->',
	'email-blacklist' => ' #<!-- leave this line exactly as it is --> <pre>
# Email addresses matching this list will be blocked from registering or sending emails
# This list affects only this wiki; refer also to the global blacklist.
# For documentation see https://www.mediawiki.org/wiki/Extension:SpamBlacklist
#
# Syntax is as follows:
#   * Everything from a "#" character to the end of the line is a comment
#   * Every non-blank line is a regex fragment which will only match hosts inside email addresses

 #</pre> <!-- leave this line exactly as it is -->',
	'email-whitelist' => ' #<!-- leave this line exactly as it is --> <pre>
# Email addresses matching this list will *not* be blocked even if they would
# have been blocked by blacklist entries.
#
# Syntax is as follows:
#   * Everything from a "#" character to the end of the line is a comment
#   * Every non-blank line is a regex fragment which will only match hosts inside email addresses

 #</pre> <!-- leave this line exactly as it is -->',

	'spam-blacklisted-email' => 'Blacklisted email address',
	'spam-blacklisted-email-text' => 'Your email address is currently blacklisted from sending emails to other users.',
	'spam-blacklisted-email-signup' => 'The given email address is currently blacklisted from use.',

	'spam-invalid-lines' =>	"The following spam blacklist {{PLURAL:$1|line is an|lines are}} invalid regular {{PLURAL:$1|expression|expressions}} and {{PLURAL:$1|needs|need}} to be corrected before saving the page:",
	'spam-blacklist-desc' => 'Regex-based anti-spam tool allowing to blacklist URLs in pages and email addresses for registered users',
	'log-name-spamblacklist' => 'Spam blacklist log',
	'log-description-spamblacklist' => 'These events track spam blacklist hits.',
	'logentry-spamblacklist-hit' => '$1 caused a spam blacklist hit on $3 by attempting to add $4.',
	'right-spamblacklistlog' => 'View spam blacklist log',
	'action-spamblacklistlog' => 'view the spam blacklist log',
);

/** Message documentation (Message documentation)
 * @author Fryed-peach
 * @author Purodha
 * @author SPQRobin
 * @author Shirayuki
 * @author Siebrand
 * @author The Evil IP address
 */
$messages['qqq'] = array(
	'spam-blacklist' => "See also: [[MediaWiki:spam-whitelist]] and [[MediaWiki:captcha-addurl-whitelist]]. You can translate the text, including 'Leave this line exactly as it is'. Some lines of this messages have one (1) leading space.",
	'spam-whitelist' => "See also: [[MediaWiki:spam-blacklist]] and [[MediaWiki:captcha-addurl-whitelist]]. You can translate the text, including 'Leave this line exactly as it is'. Some lines of this messages have one (1) leading space.",
	'email-blacklist' => "See also: [[MediaWiki:email-whitelist]] and [[MediaWiki:Spam-blacklist]]. You can translate the text, including 'Leave this line exactly as it is'. Some lines of this messages have one (1) leading space.",
	'email-whitelist' => "See also: [[MediaWiki:email-blacklist]] and [[MediaWiki:Spam-whitelist]]. You can translate the text, including 'Leave this line exactly as it is'. Some lines of this messages have one (1) leading space.",
	'spam-blacklisted-email' => 'Title of errorpage when trying to send an email with a blacklisted e-mail address',
	'spam-blacklisted-email-text' => 'Text of errorpage when trying to send an e-mail with a blacklisted e-mail address',
	'spam-blacklisted-email-signup' => 'Error when trying to create an account with an invalid e-mail address',
	'spam-invalid-lines' => 'Used as an error message.

This message is followed by a list of bad lines.

Parameters:
* $1 - the number of bad lines',
	'spam-blacklist-desc' => '{{desc|name=Spam Blacklist|url=http://www.mediawiki.org/wiki/Extension:SpamBlacklist}}',
	'log-name-spamblacklist' => 'Name of log that appears on [[Special:Log]].',
	'log-description-spamblacklist' => 'Description of spam blacklist log',
	'logentry-spamblacklist-hit' => 'Log entry that is created when a user adds a link that is blacklisted on the spam blacklist.

Parameters:
* $1 - a user link, for example "Jane Doe (Talk | contribs)"
* $2 - (Optional) a username. Can be used for GENDER
* $3 - the page the user attempted to edit
* $4 - the URL the user tried to add',
	'right-spamblacklistlog' => '{{doc-right|spamblacklistlog}}',
	'action-spamblacklistlog' => '{{doc-action|spamblacklistlog}}',
);

/** Aragonese (aragonés)
 * @author Juanpabl
 */
$messages['an'] = array(
	'spam-blacklist' => " # As URLs externas que concuerden con ista lista serán bloqueyatas quan s'encluyan en una pachina.
 # Ista lista afecta sólo ta ista wiki; mire-se tamién a lista negra global.
 # Más decumentación en https://www.mediawiki.org/wiki/Extension:SpamBlacklist
 #<!-- leave this line exactly as it is --> <pre>
#
# A sintaxi ye asinas:
#  * Tot o que bi ha dende un carácter \"#\" dica a fin d'a linia ye un comentario
#  * As linias no buedas son fragmentos d'expresions regulars que sólo concordarán con hosts adintro d'as URLs

 #</pre> <!-- leave this line exactly as it is -->",
	'spam-whitelist' => " #<!-- leave this line exactly as it is --> <pre>
# As URLs externas que concuerden con ista lista *no* serán bloqueyatas
# mesmo si han estato bloqueyatas por dentradas d'a lista negra.
#
#  A sintaxi ye asinas:
#  * Tot o que bi ha dende o carácter \"#\" dica a fin d'a linia ye un comentario
#  * As linias no buedas ye un fragmento d'expresión regular que sólo concordarán con hosts adintro d'as URLs

 #</pre> <!-- leave this line exactly as it is -->",
	'email-blacklist' => '# As adrezas de correu electronico que coincidan con ista lista se bloqueyarán ta o rechistro u ninviamiento de correus!
# Ista lista no afecta que a iste wiki; Mire-se tamién a lista negra global.
# Ta la documentación, mire-se https://www.mediawiki.org/wiki/Extension:SpamBlacklist
#<!-- Deixe ista linia exactament como ye --> <pre>
#
# A sintaxi ye a siguient:
 #   * Tot texto a la dreita d\'o caracter "#" dica la fin d\'a linia ye un comentario
 #   * Toda linia que no sía en blanco ye un fragmento de codigo que compararán os servidors con as adrezas de correu electronico
#</pre> <!-- Deixe ista linia como ye-->',
	'email-whitelist' => " #<!-- Deixe ista linia como ye --> <pre>
# As adrezas de correu electronico que amaneixen en ista lista *no* serán bloqueyadas mesmo si s'hesen habiu de bloquiar por amaneixer en a lista negra.
#
 #</pre> <!-- Deixe ista linia como ye-->
# A sintaxi ye a siguient:
#  * Tot texto a la dreita d'o caracter \"#\" dica a fin d'a linia ye un comentario
#  * Toda linia que no sía en blanco ye un fragmento de codigo que os servidors compararán con as adrezas de correu electronico",
	'spam-blacklisted-email' => 'Adreza de correu electronico en a lista negra',
	'spam-blacklisted-email-text' => 'A suya adreza de correu-e ye agora en a lista negra, y no puede ninviar correu ta atros usuarios.',
	'spam-blacklisted-email-signup' => "L'adreza de correu-e que ha dau ye actualment en a lista negra, y no se puede fer servir.",
	'spam-invalid-lines' => "{{PLURAL:$1|A linia siguient ye una|As linias siguients son}} {{PLURAL:$1|expresión regular|expresions regulars}} y {{PLURAL:$1|ha|han}} d'estar correchitas antes d'alzar a pachina:",
	'spam-blacklist-desc' => 'Ferramienta anti-spam basata en expresions regulars (regex): [[MediaWiki:Spam-blacklist]] y [[MediaWiki:Spam-whitelist]]', # Fuzzy
);

/** Arabic (العربية)
 * @author Meno25
 * @author OsamaK
 */
$messages['ar'] = array(
	'spam-blacklist' => ' # الوصلات الخارجية التي تطابق هذه القائمة سيتم منعها عند إضافتها لصفحة.
 # هذه القائمة تؤثر فقط على هذه الويكي؛ ارجع أيضا للقائمة السوداء العامة.
 # للوثائق انظر https://www.mediawiki.org/wiki/Extension:SpamBlacklist
 #<!-- اترك هذا السطر تماما كما هو --> <pre>
#
# الصيغة كالتالي:
#   * كل شيء من علامة "#" إلى آخر السطر هو تعليق
#   * كل سطر غير فارغ هو تعبير منتظم يوافق فقط المضيفين داخل الوصلات الخارجية

 #</pre> <!-- اترك هذا السطر تماما كما هو -->',
	'spam-whitelist' => ' #<!-- اترك هذا السطر تماما كما هو --> <pre>
# الوصلات الخارجية التي تطابق هذه القائمة *لن* يتم منعها حتى لو
# كانت ممنوعة بواسطة مدخلات القائمة السوداء.
#
# الصيغة كالتالي:
#   * كل شيء من علامة "#" إلى آخر السطر هو تعليق
#   * كل سطر غير فارغ هو تعبير منتظم يطابق فقط المضيفين داخل الوصلات الخارجية

 #</pre> <!-- اترك هذا السطر تماما كما هو -->',
	'spam-invalid-lines' => '{{PLURAL:$1||السطر التالي|السطران التاليان|السطور التالية}} في قائمة السبام السوداء {{PLURAL:$1|ليس تعبيرًا منتظمًا صحيحًا|ليسا تعبيرين منتظمين صحيحين|ليست تعبيرات منتظمة صحيحة}}  و{{PLURAL:$1||يحتاج|يحتاجان|تحتاج}} إلى أن {{PLURAL:$1||يصحح|يصححان|تصحح}} قبل حفظ الصفحة:',
	'spam-blacklist-desc' => 'أداة ضد السبام تعتمد على التعبيرات المنتظمة: [[MediaWiki:Spam-blacklist]] و [[MediaWiki:Spam-whitelist]]', # Fuzzy
);

/** Egyptian Spoken Arabic (مصرى)
 * @author Meno25
 * @author Ramsis II
 */
$messages['arz'] = array(
	'spam-blacklist' => ' # اللينكات الخارجية اللى بتطابق الليستة دى ح تتمنع لما تضاف لصفحة.
 # اللستة دى بتأثر بس على الويكى دى؛ ارجع كمان للبلاك ليست العامة.
 # للوثايق شوف https://www.mediawiki.org/wiki/Extension:SpamBlacklist
 #<!-- سيب السطر دا زى ما هو كدا بالظبط--> <pre>
#
# الصيغة كدا:
#  * كل حاجة من علامة "#" لحد آخر السطر هو تعليق
#  * كل سطر مش فاضى هو تعبير منتظم بيوافق بس المضيفين جوه الوصلات الخارجية

 #</pre> <!-- سيب السطر دا زى ما هو كدا بالظبط-->',
	'spam-whitelist' => ' #<!-- سيب السطر دا زى ما هو كدا بالظبط --> <pre>
# اللينكات الخارجية اللى بتطابق اللستة دى *مش* ح تتمنع حتى لو
# كانت ممنوعة بواسطة مدخلات البلاك ليست.
#
# الصيغة كدا:
#  * كل حاجة من علامة "#" لحد آخر السطر هو تعليق
#  * كل سطر مش فاضى هو تعبير منتظم بيطابق بس المضيفين جوه الوصلات الخارجية

 #</pre> <!-- سيب السطر دا زى ما هو كدا بالظبط-->',
	'spam-invalid-lines' => '{{PLURAL:$1|السطر دا|السطور دول}} اللى فى السبام بلاك ليست {{PLURAL:$1|هو تعبير منتظم |هى تعبيرات منتظمة}} مش صح و {{PLURAL:$1|محتاج|محتاجين}} تصليح قبل حفظ الصفحة:',
	'spam-blacklist-desc' => 'اداة انتي-سبام مبنية على اساس ريجيكس: [[MediaWiki:Spam-blacklist]] و [[MediaWiki:Spam-whitelist]]', # Fuzzy
);

/** Assamese (অসমীয়া)
 * @author Gitartha.bordoloi
 */
$messages['as'] = array(
	'spam-blacklist' => '# এই তালিকাৰ লগত মিলা বাহিৰা URLবোৰ পৃষ্ঠাত যোগ কৰোঁতে অৱৰোধ কৰা হ\'ব।
# এই তালিকাই কেৱল এই ৱিকিত প্ৰভাৱ পেলায়; গোলকীয় ব্লেকলিষ্টখনো চাওক।
# নথিকৰণৰ বাবে https://www.mediawiki.org/wiki/Extension:SpamBlacklist চাওক।
# <!-- leave this line exactly as it is --> <pre>
#
# বিন্যাস তলত দিয়া ধৰণৰ:
# * "#" চিহ্নৰ পৰা শাৰীৰ শেষলৈকে সকলোখিনিয়েই মন্তব্য।
# * প্ৰতিটো অশূন্য শাৰী একোটা ৰেজেক্স খণ্ডাংশ যি কেৱল URLৰ ভিতৰৰ hostৰ লগত মিলিব

#</pre> <!-- leave this line exactly as it is -->',
	'spam-whitelist' => " #<!-- leave this line exactly as it is --> <pre>
# এই তালিকাৰ লগত মিলা বাহিৰা URLসমূহ অৱৰোধ কৰা *নহ'ব* যদিও সেইবোৰ
# ব্লেকলিষ্ট ভুক্তিৰ দ্বাৰা অৱৰোধ হ'ব।
#
 #</pre> <!-- leave this line exactly as it is -->",
	'email-blacklist' => '# এই তালিকাৰ লগত মিলা ই-মেইল ঠিকনাৰ পৰা পঞ্জীয়ন বা ই-মেইল পঠিওৱা অৱৰোধ কৰা হ\'ব।
# এই তালিকাই কেৱল ঐ ৱিকিত প্ৰভাৱ পেলায়; গোলকীয় ব্লেকলিষ্টখনো চাওক।
# নথিকৰণৰ বাবে https://www.mediawiki.org/wiki/Extension:SpamBlacklist চাওক।
#<!-- leave this line exactly as it is --> <pre>
#
# বিন্যাস তলত দিয়া ধৰণৰ:
# * "#" চিহ্নৰ পৰা শাৰীৰ শেষলৈকে সকলোখিনিয়েই মন্তব্য।
# * প্ৰতিটো অশূন্য শাৰী এটা ৰেজেক্স খণ্ডাংশ যি কেৱল ই-মেইল ঠিকনাবোৰৰ ভিতৰৰ হ\'ষ্টৰ লগত মিলিব।

#</pre> <!-- leave this line exactly as it is -->',
	'email-whitelist' => " #<!-- leave this line exactly as it is --> <pre>
# এই তালিকাৰ লগত মিলা বাহিৰা ই-মেইলসমূহ অৱৰোধ কৰা *নহ'ব* যদিও সেইবোৰ
# ব্লেকলিষ্ট ভুক্তিৰ দ্বাৰা অৱৰোধ হ'ব পাৰে।
#
 #</pre> <!-- leave this line exactly as it is -->
# বিন্যাস তলত দিয়া ধৰণৰ:
# * \"#\" চিহ্নৰ পৰা শাৰীৰ শেষলৈকে সকলোখিনি মন্তব্য।
3 * প্ৰতিটো অশূন্য শাৰী এটা ৰেজেক্স খণ্ডাংশ যি কেৱল ই-মেইল ঠিকনাৰ ভিতৰৰ হ'ষ্টৰ লগত মিলিব।",
	'spam-blacklisted-email' => 'ব্লেকলিষ্টেড ই-মেইল ঠিকনা',
	'spam-blacklisted-email-text' => 'আন সদস্যলৈ ই-মেইল পঠিয়াব নোৱাৰাকৈ আপোনাৰ ই-মেইল ঠিকনা ব্লেকলিষ্টেড কৰা হৈছে।',
	'spam-blacklisted-email-signup' => 'ই-মেইল ঠিকনাটো ব্যৱহাৰৰ পৰা ব্লেকলিষ্টেড কৰা হৈছে।',
	'spam-invalid-lines' => 'তলৰ স্পাম ব্লেকলিষ্টৰ {{PLURAL:$1|শাৰীটোত|শাৰীসমূহত}} অবৈধ নিয়মিত {{PLURAL:$1|এক্সপ্ৰেছন|এক্সপ্ৰেছন}} আছে আৰু সেইবোৰ পৃষ্ঠা সাঁচি থোৱাৰ আগতেই ঠিক কৰাটো {{PLURAL:$1|প্ৰয়োজন|প্ৰয়োজন}}:',
	'spam-blacklist-desc' => 'ৰেজেক্স-ভিত্তিক স্পামবিৰোধী সঁজুলি: [[MediaWiki:Spam-blacklist]] আৰু [[MediaWiki:Spam-whitelist]]', # Fuzzy
);

/** Asturian (asturianu)
 * @author Esbardu
 * @author Xuacu
 */
$messages['ast'] = array(
	'spam-blacklist' => ' # Les URLs que casen con esta llista se bloquiarán cuando s\'añadan a una páxina.
 # Esta llista afeuta namái a esta wiki; mira tamién la llista negra global.
 # Pa ver la documentación visita https://www.mediawiki.org/wiki/Extension:SpamBlacklist
 #<!-- dexa esta llinia exautamente como ta --> <pre>
#
# La sintaxis ye ésta:
#  * Tol testu dende un caráuter "#" hasta lo cabero la llinia ye un comentariu
#  * Toa llinia non balera ye un fragmentu regex qu\'afeuta namái a los sirvidores de les URLs

 #</pre> <!-- dexa esta llinia exautamente como ta -->',
	'spam-whitelist' => ' #<!-- dexa esta llinia exautamente como ta --> <pre>
# Les URLs esternes d\'esta llista *nun* se bloquiarán inda si quedaríen bloquiaes
# por una entrada na llista negra.
#
# La sintaxis ye esta:
#  * Tol testu dende un caráuter "#" hasta lo cabero la llinia ye un comentariu
#  * Toa llinia non balera ye un fragmentu regex qu\'afeuta namái a les URLs especificaes
 #</pre> <!-- dexa esta llinia exautamente como ta -->',
	'email-blacklist' => ' # Los correos que casen con esta llista tendrán torgao rexistrase o unviar corréu.
 # Esta llista afeuta namái a esta wiki; mira tamién la llista negra global.
 # Pa ver la documentación visita https://www.mediawiki.org/wiki/Extension:SpamBlacklist
 #<!-- dexa esta llinia exautamente como ta --> <pre>
#
# La sintaxis ye esta:
#  * Tol testu dende un caráuter "#" hasta lo cabero la llinia ye un comentariu
#  * Toa llinia non balera ye un fragmentu regex qu\'afeuta namái a los sirvidores de corréu

 #</pre> <!-- dexa esta llinia exautamente como ta -->',
	'email-whitelist' => '#<!-- Dexa esta llinia tal y como ta --> <pre>
# Los correos que casen con esta llista *nun* se bloquiarán, incluío si
# los hubieren bloquiao entraes de la llista negra.
#
 #</pre> <!-- Dexa esta llinia tal y como ta -->
# La sintaxis ye esta:
#  * Tol testu dende un caráuter "#" hasta lo cabero la llinia ye un comentariu
#  * Toa llinia non balera ye un fragmentu regex qu\'afeuta namái a los sirvidores de corréu',
	'spam-blacklisted-email' => 'Corréu electrónicu de la llista negra',
	'spam-blacklisted-email-text' => 'El to corréu electrónicu anguaño ta na llista negra y nun pue unviar correos electrónicos a otros usuarios.',
	'spam-blacklisted-email-signup' => "La direición de corréu electrónicu que se dio tien torgáu l'usu por tar anguaño na llista negra.",
	'spam-invalid-lines' => '{{PLURAL:$1|La siguiente llinia|Les siguientes llinies}} de la llista negra de spam {{PLURAL:$1|ye una espresión regular non válida|son espresiones regulares non válides}} y {{PLURAL:$1|necesita ser correxida|necesiten ser correxíes}} enantes de guardar la páxina:',
	'spam-blacklist-desc' => "Ferramienta antispam basada n'espresiones regulares que permite a los usuarios rexistraos poner nuna llista prieta URLs de páxines y direiciones de corréu electrónicu",
	'log-name-spamblacklist' => 'Rexistru de la llista prieta de spam',
	'log-description-spamblacklist' => 'Estos socesos rexistren les coincidencies cola llista prieta de spam.',
	'logentry-spamblacklist-hit' => '$1 provocó una activación de la llista prieta de spam en $3 al intentar amestar $4.',
	'right-spamblacklistlog' => 'Ver el rexistru de la llista prieta de spam',
	'action-spamblacklistlog' => 'ver el rexistru de la llista prieta de spam',
);

/** Bashkir (башҡортса)
 * @author Assele
 */
$messages['ba'] = array(
	'spam-blacklist' => ' # Был исемлеккә тап килгән тышҡы һылтанмаларҙы биттәргә өҫтәү тыйыласаҡ.
 # Был исемлек ошо вики өсөн генә ғәмәлгә эйә, шулай уҡ дөйөм ҡара исемлек бар.
 # Тулыраҡ мәғлүмәт өсөн https://www.mediawiki.org/wiki/Extension:SpamBlacklist ҡарағыҙ
 #<!-- был юлды үҙгәртмәгеҙ --><pre>
#
# Синтаксис:
# * # хәрефенән башлап юл аҙағына тиклем барыһы ла иҫкәрмә тип иҫәпләнә
# * Һәр буш булмаған юл URL эсендәге төйөнгә генә ҡулланылған регуляр аңлатманың өлөшө булып тора

 #</pre><!-- был юлды үҙгәртмәгеҙ -->',
	'spam-whitelist' => '#<!-- был юлды нисек бар, шулай ҡалдырығыҙ --> <pre>
# Был исемлеккә тап килгән тышҡы һылтанмаларҙы биттәргә өҫтәү, хатта улар ҡара исемлектә булһалар ҙа, *тыйылмаясаҡ*.
#
# Синтаксис:
# * # хәрефенән башлап юл аҙағына тиклем барыһы ла иҫкәрмә тип иҫәпләнә
# * Һәр буш булмаған юл URL эсендәге төйөнгә генә ҡулланылған регуляр аңлатманың өлөшө булып тора
#</pre> <!-- был юлды нисек бар, шулай ҡалдырығыҙ -->',
	'spam-invalid-lines' => 'Түбәндәге ҡара исемлек {{PLURAL:$1|юлында|юлдарында}} хаталы регуляр {{PLURAL:$1|аңлатма|аңлатмалар}} бар һәм {{PLURAL:$1|ул|улар}} битте һаҡлар алдынан төҙәтелергә тейеш:',
	'spam-blacklist-desc' => 'Регуляр аңлатмаларға нигеҙләнгән спамға ҡаршы ҡорал: [[MediaWiki:Spam-blacklist]] һәм [[MediaWiki:Spam-whitelist]]', # Fuzzy
);

/** Southern Balochi (بلوچی مکرانی)
 * @author Mostafadaneshvar
 */
$messages['bcc'] = array(
	'spam-blacklist-desc' => 'وسیله په ضد اسپم په اساس عبارات منظم:  [[MediaWiki:Spam-blacklist]] و [[MediaWiki:Spam-whitelist]]', # Fuzzy
);

/** Bikol Central (Bikol Central)
 * @author Geopoet
 */
$messages['bcl'] = array(
	'spam-blacklist' => '#</pre><!-- pakiwalat ining linya na eksaktong siring kaiyan -->
# Mga panluwas na pangilyaw na minatampad kaining listahan ipagkukubkob kunsoarin na ipagdugang ini sa sarong pahina.
# Ining listahan mina-apekto sana sa wiking ini; pakihiling man sa pankinabanong pinagbaraduhan.
# Para sa dokumentasyon hilngon tabi sa https://www.mediawiki.org/wiki/Extension:SpamBlacklist
# 
#An sintaks iyo an mga minasunod:
# *An gabos magpoon sa "#" na karakter sagkod sa tapos kan linya iyo an komento
# *An lambang bako na blankong linya iyo an sarong kapedasohan kan regex na makakapagtampad sana kan mga parabunsod na yaon sa laog kan mga pangilyaw

#</pre><!-- pakiwalat ining linya na eksaktong siring kaiyan -->',
	'spam-whitelist' => '#<!-- pakiwalat ining linya na eksaktong siring kaiyan  --> <pre>
#An panluwas na mga pangilyaw na nagtatampad kaining listahn *dae* ipagkukubkob dawa ngani na sinda #ipinagkubkob kan mga pinagbarahang entrada.
#
#An sintaks iyo an mga minasunod:
# *An gabos magpoon sa "#" na karakter sagkod sa tapos kan linya iyo an komento
# *An lambang bako na blankong linya iyo an sarong kapedasohan kan regex na makakapagtampad sana kan mga parabunsod na yaon sa laog kan mga pangilyaw

#</pre><!-- pakiwalat ining linya na eksaktong siring kaiyan -->',
	'email-blacklist' => '#<!-- pakiwalat ining linya na eksaktong siring kaiyan  --> <pre>
#An mga e-surat na nagtatampad kaining listahan ipagkukubkob sa pagpaparehistro o sa pagpapadara kan me e-surat
#Ining listahan mina-apekto sana kaining wiki; pakihiling man sa pankinabanong pinagbarahan.
#Para sa dokumentasyon pakihiling sa https://www.mediawiki.org/wiki/Extension:SpamBlacklist
#
#An sintaks iyo an mga minasunod:
# *An gabos magpoon sa "#" na karakter sagkod sa tapos kan linya iyo an komento
# *An lambang bako na blankong linya iyo an sarong kapedasohan kan regex na makakapagtampad sana kan mga parabunsod na yaon sa laog kan mga estada kan e-surat

#</pre><!-- pakiwalat ining linya na eksaktong siring kaiyan -->',
	'email-whitelist' => '#<!-- pakiwalat ining linya na eksaktong siring kaiyan  --> <pre>
#An mga e-surat na nagtatampad kaining listahan *dae* ipagkukubkob dawa ngani sinda
#pinagkubkob kan mga pingbarahang entrada.
#
#An sintaks iyo an mga minasunod:
# *An gabos magpoon sa "#" na karakter sagkod sa tapos kan linya iyo an komento
# *An lambang bako na blankong linya iyo an sarong kapedasohan kan regex na makakapagtampad sana kan mga parabunsod na yaon sa laog kan mga estada kan e-surat

#</pre><!-- pakiwalat ining linya na eksaktong siring kaiyan -->',
	'spam-blacklisted-email' => 'Pinagbaraduhang estada kan e-surat',
	'spam-blacklisted-email-text' => 'An saimong estada kan e-surat sa ngunyan pinagbaraduhan sa pagpapadara nin mga e-surat pasiring sa ibang mga paragamit.',
	'spam-blacklisted-email-signup' => 'An ipinagtaong estada kan e-surat sa ngunyan pinagbaraduhan na magamit.',
	'spam-invalid-lines' => 'An minasunod na pinagbarahang listahan kan espam {{PLURAL:$1|hilira iyo an|hilira iyo an mga}} imbalidong pirmihan na {{PLURAL:$1|ekspresyon|mga ekspresyon}} asin {{PLURAL:$1|kinakaipuhan|kaipuhan}} na pagkokorihiran bago tabi itatagama an pahina:',
	'spam-blacklist-desc' => 'Nakabase sa Regex na gamit sa anti-espam:[[MediaWiki:Spam-blacklist]] asin [[MediaWiki:Spam-whitelist]]', # Fuzzy
);

/** Belarusian (Taraškievica orthography) (беларуская (тарашкевіца)‎)
 * @author EugeneZelenko
 * @author Jim-by
 */
$messages['be-tarask'] = array(
	'spam-blacklist' => ' # Вонкавыя спасылкі, якія будуць адпавядаць гэтаму сьпісу, будуць блякавацца пры 
 # спробе даданьня на старонку.
 # Гэты сьпіс будзе дзейнічаць толькі ў гэтай вікі; існуе таксама і глябальны чорны сьпіс.
 # Дакумэнтацыю гэтай функцыі глядзіце на https://www.mediawiki.org/wiki/Extension:SpamBlacklist
 #<!-- пакіньце гэты радок такім, які ён ёсьць --> <pre>
#
# Сынтаксіс наступны:
#  * Усё, што пачынаецца з «#» і да канца радку, зьяўляецца камэнтарам
#  * Усе непустыя радкі зьяўляюцца часткамі рэгулярнага выразу, які будзе выкарыстоўвацца толькі
# ў дачыненьні да назваў сэрвэраў у вонкавых спасылках

 #</pre> <!-- пакіньце гэты радок такім, які ён ёсьць -->',
	'spam-whitelist' => ' #<!-- пакіньце гэты радок такім, які ён ёсьць --> <pre>
# Вонкавыя спасылкі, якія будуць адпавядаць гэтаму сьпісу, *ня* будуць блякавацца, нават калі яны 
# будуць адпавядаць чорнаму сьпісу
#
# Сынтаксіс наступны:
#  * Усё, што пачынаецца з «#» і да канца радка, зьяўляецца камэнтарам
#  * Усе непустыя радкі зьяўляюцца часткамі рэгулярнага выразу, які будзе выкарыстоўвацца толькі
# ў дачыненьні да назваў сэрвэраў у вонкавых спасылках

 #</pre> <!-- пакіньце гэты радок такім, які ён ёсьць -->',
	'email-blacklist' => ' # Электронныя лісты, якія будуць адпавядаць гэтаму сьпісу, будуць блякавацца пры 
 # спробе адпраўкі.
 # Гэты сьпіс будзе дзейнічаць толькі ў гэтай вікі; існуе таксама і глябальны чорны сьпіс.
 # Дакумэнтацыю гэтай функцыі глядзіце на https://www.mediawiki.org/wiki/Extension:SpamBlacklist
 #<!-- пакіньце гэты радок такім, які ён ёсьць --> <pre>
#
# Сынтаксіс наступны:
#  * Усё, што пачынаецца з «#» і да канца радку, зьяўляецца камэнтарам
#  * Усе непустыя радкі зьяўляюцца часткамі рэгулярнага выразу, які будзе выкарыстоўвацца толькі
# ў дачыненьні да назваў сэрвэраў у электронных лістах

 #</pre> <!-- пакіньце гэты радок такім, які ён ёсьць -->',
	'email-whitelist' => ' #<!-- пакіньце гэты радок такім, які ён ёсьць --> <pre>
 # Электронныя лісты, якія будуць адпавядаць гэтаму сьпісу, ня будуць блякавацца, нават  
 # калі яны будуць у чорным сьпісе. 
 #
 #</pre> <!-- пакіньце гэты радок такім, які ён ёсьць -->
# Сынтаксіс наступны:
#  * Усё, што пачынаецца з «#» і да канца радку, зьяўляецца камэнтарам
#  * Усе непустыя радкі зьяўляюцца часткамі рэгулярнага выразу, які будзе выкарыстоўвацца толькі
# ў дачыненьні да назваў сэрвэраў у электронных лістах',
	'spam-blacklisted-email' => 'Адрасы электроннай пошты з чорнага сьпісу',
	'spam-blacklisted-email-text' => 'З Вашага адрасу электроннай пошты ў цяперашні момант забаронена дасылаць электронныя лісты іншым удзельнікам.',
	'spam-blacklisted-email-signup' => 'Пададзены Вамі адрас электроннай пошты ў цяперашні момант знаходзіцца ў чорным сьпісе.',
	'spam-invalid-lines' => '{{PLURAL:$1|Наступны радок чорнага сьпісу ўтрымлівае няслушны рэгулярны выраз|Наступныя радкі чорнага сьпісу ўтрымліваюць няслушныя рэгулярныя выразы}} і {{PLURAL:$1|павінен быць|павінныя быць}} выпраўлены перад захаваньнем старонкі:',
	'spam-blacklist-desc' => 'Антыспамавы інструмэнт, які базуецца на рэгулярных выразах: [[MediaWiki:Spam-blacklist]] і [[MediaWiki:Spam-whitelist]]', # Fuzzy
);

/** Bulgarian (български)
 * @author Spiritia
 */
$messages['bg'] = array(
	'spam-invalid-lines' => '{{PLURAL:$1|Следният запис|Следните записи}} от черния списък на спама {{PLURAL:$1|е невалиден регулярен израз|са невалидни регулярни изрази}} и  трябва да {{PLURAL:$1|бъде коригиран|бъдат коригирани}} преди съхраняване на страницата:',
	'spam-blacklist-desc' => 'Инструмент за защита от спам, използващ регулярни изрази: [[МедияУики:Spam-blacklist]] и [[МедияУики:Spam-whitelist]]', # Fuzzy
);

/** Banjar (Bahasa Banjar)
 * @author Alamnirvana
 */
$messages['bjn'] = array(
	'spam-invalid-lines' => 'Baris-baris nang maumpati ini manggunaakan ungkapan nalar nang kahada sah. Silakan dibaiki daptar hirang ini sabalum manyimpannya:', # Fuzzy
);

/** Bengali (বাংলা)
 * @author Bellayet
 * @author Nasir8891
 * @author Zaheen
 */
$messages['bn'] = array(
	'spam-blacklist' => '
 # এই তালিকার সাথে মিলে যায় এমন বহিঃসংযোগ URLগুলি পাতায় যোগ করতে বাধা দেয়া হবে।
 # এই তালিকাটি কেবল এই উইকির ক্ষেত্রে প্রযোজ্য; সামগ্রিক কালোতালিকাও দেখতে পারেন।
 # ডকুমেন্টেশনের জন্য https://www.mediawiki.org/wiki/Extension:SpamBlacklist দেখুন
 #<!-- leave this line exactly as it is --> <pre>
#
# সিনট্যাক্স নিচের মত:
#  * "#" ক্যারেক্টার থেকে শুরু করে লাইনের শেষ পর্যন্ত সবকিছু একটি মন্তব্য
#  * প্রতিটি অশূন্য লাইন একটি রেজেক্স খণ্ডাংশ যেটি কেবল URLগুলির ভেতরের hostগুলির সাথে মিলে যাবে

 #</pre> <!-- leave this line exactly as it is -->',
	'spam-whitelist' => ' #<!-- এই লাইন যেমন আছে ঠিক তেমনই ছেড়ে দিন --> <pre>
# External URLs matching this list will *not* be blocked even if they would
# have been blocked by blacklist entries.
#
# Syntax is as follows:
#  * Everything from a "#" character to the end of the line is a comment
#  * Every non-blank line is a regex fragment which will only match hosts inside URLs

 #</pre> <!-- এই লাইন যেমন আছে ঠিক তেমনই ছেড়ে দিন -->',
	'spam-blacklisted-email' => 'কালোতালিকাভুক্ত ইমেইল ঠিকানা',
	'spam-blacklisted-email-text' => 'অন্যদের ইমেইল পাঠানো থেকে বিরত রাখতে আপনাকে কালোতালিকাভুক্ত করা হয়েছে।',
	'spam-blacklisted-email-signup' => 'আপনার লেখা ইমেইল ঠিকানাটি কালোতালিকাভুক্ত।',
	'spam-invalid-lines' => 'নিচের স্প্যাম কালোতালিকার {{PLURAL:$1|লাইন|লাইনগুলি}} অবৈধ রেগুলার {{PLURAL:$1|এক্সপ্রেশন|এক্সপ্রেশন}} ধারণ করছে এবং পাতাটি সংরক্ষণের আগে এগুলি ঠিক করা {{PLURAL:$1|প্রয়োজন|প্রয়োজন}}:',
	'spam-blacklist-desc' => 'রেজেক্স-ভিত্তিক স্প্যামরোধী সরঞ্জাম: [[MediaWiki:Spam-blacklist]] এবং [[MediaWiki:Spam-whitelist]]', # Fuzzy
);

/** Breton (brezhoneg)
 * @author Fulup
 */
$messages['br'] = array(
	'spam-blacklist' => '  # Stanket e vo an URLoù diavaez a glot gant ar roll-mañ ma vezont ouzhpennet en ur bajenn.
  # Ne sell ar roll-mañ nemet ouzh ar wiki-mañ ; sellit ivez ouzh al listenn zu hollek.
  # Aze emañ an titouroù https://www.mediawiki.org/wiki/Extension:SpamBlacklist
  #<!-- leave this line exactly as it is --> <pre>
#
# Setu doare an ereadur :
#  * Pep tra adalek un arouezenn "#" betek dibenn al linenn zo un evezhiadenn
#  * Kement linenn anc\'houllo zo un darnad lavarenn reoliek na gloto nemet gant an ostizien el liammoù gourskrid

  #</pre> <!-- lezel al linenn-mañ tre evel m\'emañ -->',
	'spam-whitelist' => "  #<!-- lezel al linenn-mañ tre evel m'emañ --> <pre>
# *Ne vo ket* stanket al liammoù gourskrid a glot gant al listenn-mañ
# ha pa vijent bet stanket gant monedoù ar listenn zu.
#
# Setu an eredur :
#  * Pep tra adalek un arouezenn \"#\" betek dibenn al linenn zo un ev evezhiadenn
#  * Kement linenn anc'houllo zo un darnad skrid poellek na zielfennno nemet an ostizien el liammoù gourskrid

  #</pre> <!-- lezel al linenn-mañ tre evel m'emañ -->",
	'email-blacklist' => "  # Miret e vo ouzh ar chomlec'hioù postel a glot gant ar roll-mañ da enrollañ pe da gas posteloù
  # Ne sell ar roll-mañ nemet ouzh ar wiki-mañ ; sellit ivez ouzh al listenn zu hollek.
  # Aze emañ an titouroù http://www.mediawiki.org/wiki/Extension:SpamBlacklist
  #<!-- lezel al linenn-mañ tre evel m'emañ  --> <pre>
#
# Setu doare an ereadur :
#  * Kement testenn zo war-lerc'h un arouezenn \"#\" betek dibenn al linenn a vez sellet outi evel un evezhiadenn
#  * Kement linenn n'eo ket goullo zo un tamm eus ul lavarenn reoliek na gloto nemet gant an ostizien el liammoù gourskrid

  #</pre> <!-- lezel al linenn-mañ tre evel m'emañ -->",
	'email-whitelist' => " #<!-- lezel al linenn-mañ tre evel m'emañ --> <pre>
# *Ne vo ket* stanket ar chomlec'hioù postel zo er roll-mañ ha pa oant da vezañ
# diouzh enmontoù al listenn zu.
#
 #</pre> <!-- lezel al linenn-mañ tre evel m'emañ -->
# Setu an ereadur :
#   * Kement tra zo war-lerc'h un arouezenn \"#\" betek dibenn al linenn zo un evezhiadenn
#   * Kement linenn n'eo ket goullo zo un tamm regex (lavarenn reoliek) a vo lakaet a-geñver gant al lodenn \"ostiz\" e diabarzh ar chomlec'hioù postel",
	'spam-blacklisted-email' => "Chomlec'hioù postel ha listenn zu",
	'spam-blacklisted-email-text' => "Evit ar mare emañ ho chomlec'h postel war ul listenn zu ha n'haller ket kas posteloù drezañ d'an implijerien all.",
	'spam-blacklisted-email-signup' => "War ul listenn zu emañ ar chomlec'h postel pourchaset. N'hall ket bezañ implijet.",
	'spam-invalid-lines' => '{{PLURAL:$1|Ul lavarenn|Lavarennoù}} reoliek direizh eo {{PLURAL:$1|al linenn|al linennoù}} da-heul eus roll du ar stroboù ha ret eo {{PLURAL:$1|he reizhañ|o reizhañ}} a-raok enrollañ ar bajenn :',
	'spam-blacklist-desc' => 'Ostilh enep-strob diazezet war lavarennoù reoliek (Regex) : [[MediaWiki:Spam-blacklist]] ha [[MediaWiki:Spam-whitelist]]', # Fuzzy
);

/** Bosnian (bosanski)
 * @author CERminator
 */
$messages['bs'] = array(
	'spam-blacklist' => '# Vanjski URLovi koji odgovaraju ovom spisku će biti blokirani ako se dodaju na stranicu.
 # Ovaj spisak će biti aktivan samo na ovoj wiki; a poziva se i na globalni zabranjeni spisak.
 # Za objašenjenja i dokumentaciju pogledajte https://www.mediawiki.org/wiki/Extension:SpamBlacklist
 #<!-- ostavite ovaj red tačno onako kakav je --> <pre>
#
# Sintaksa je slijedeća:
#  * Sve od znaka "#" do kraja reda je komentar
#  * Svi neprazni redovi su fragmenti regexa koji će odgovarati samo domaćinima unutar URLova

 #</pre> <!-- ostavite ovaj red tačno onako kakav je -->',
	'spam-whitelist' => '#<!-- ostavite ovaj red onakav kakav je --> <pre>
# Vanjski URLovi koji odgovaraju nekoj od stavki na ovom spisku *neće* biti blokirani čak iako
# budu blokirani preko spisak nepoželjnih stavki.
#
# Sintaksa je slijedeća:
#  * Sve od znaka "#" do kraja reda je komentar
#  * Svaki neprazni red je fragment regexa koji će odgovarati samo domaćinima unutar URLa

 #</pre> <!-- ostavite ovaj red onakav kakav je -->',
	'spam-invalid-lines' => 'Slijedeći {{PLURAL:$1|red|redovi}} u spisku spam nepoželjnih stavki {{PLURAL:$1|je nevalidan izraz|su nevalidni izrazi}} i {{PLURAL:$1|treba|trebaju}} se ispraviti prije spremanja stranice:',
	'spam-blacklist-desc' => 'Alati protiv spama zasnovani na regexu: [[MediaWiki:Spam-blacklist]] i [[MediaWiki:Spam-whitelist]]', # Fuzzy
);

/** Catalan (català)
 * @author Aleator
 * @author Arnaugir
 * @author Jordi Roqué
 * @author SMP
 * @author Vriullop
 */
$messages['ca'] = array(
	'spam-blacklist' => ' # Les URLs externes coincidents amb aquesta llista seran bloquejades en ser afegides a una pàgina.
 # Aquesta llista afecta només a aquesta wiki; vegeu també la llista negra global.
 # Per a més informació vegeu https://www.mediawiki.org/wiki/Extension:SpamBlacklist
 #<!-- deixeu aquesta línia exactament com està --> <pre>
#
# La sintaxi és com segueix:
#  * Tot allò des d\'un caràcter "#" fins al final de la línia és un comentari
#  * Cada línia que no estigui en blanc és un fragment regex que només coincidirà amb amfitrions dintre d\'URLs

 #</pre> <!-- deixeu aquesta línia exactament com està -->',
	'spam-whitelist' => " #<!-- deixeu aquesta línia tal com està --> <pre>
# Les adreces URL externes que apareguin en aquesta llista no seran blocades
# fins i tot si haurien estat blocades per aparèixer a la llista negra.
#
# La sintaxi és la següent:
#   * Tot allò que hi hagi des d'un símbol '#' fins a la fi de línia és un comentari
#   * Cada línia no buida és un fragment d'expressió regular (regex) que només marcarà hosts dins les URL

 #</pre> <!-- deixeu aquesta línia tal com està -->",
	'spam-blacklisted-email' => 'Adreces de correu electrònic a la llista negra',
	'spam-blacklisted-email-text' => "La vostra adreça de correu electrònic està actualment en la llista negra d'enviament de correus a altres usuaris.",
	'spam-blacklisted-email-signup' => "L'adreça de correu electrònic proporcionada està actualment en la llista negra d'ús.",
	'spam-invalid-lines' => "{{PLURAL:$1|La línia següent no es considera una expressió correcta|Les línies següents no es consideren expressions correctes}} {{PLURAL:$1|perquè recull|perquè recullen}} SPAM que està vetat. Heu d'esmenar-ho abans de salvar la pàgina:",
	'spam-blacklist-desc' => 'Eina anti-spam basada en regexp: [[MediaWiki:Spam-blacklist]] i [[MediaWiki:Spam-whitelist]]', # Fuzzy
);

/** Sorani Kurdish (کوردی)
 * @author Calak
 */
$messages['ckb'] = array(
	'right-spamblacklistlog' => 'دیتنی لۆگی پێرستی ڕەشی ڕیکلام',
);

/** Czech (česky)
 * @author Li-sung
 * @author Matěj Grabovský
 * @author Mormegil
 */
$messages['cs'] = array(
	'spam-blacklist' => ' # Externí URL odpovídající tomuto seznamu budou zablokovány při pokusu přidat je na stránku.
 # Tento seznam ovlivňuje jen tuto wiki; podívejte se také na globální černou listinu.
 # Dokumentaci najdete na https://www.mediawiki.org/wiki/Extension:SpamBlacklist
 #<!-- Nechte tento řádek přesně tak jak je --> <pre>
#
# Syntaxe je následující:
#  * Všechno od znaku „#“ do konce řádku je komentář
#  * Každý neprázdný řádek je část regulárního výrazu, kterému budou odpovídat pouze domény z URL

 #</pre> <!-- Nechte tento řádek přesně tak jak je -->',
	'spam-whitelist' => ' #<!-- nechejte tento řádek přesně tak jak je --> <pre>
# Externí URL odpovídající výrazům v tomto seznamu *nebudou* zablokovány, ani kdyby
# je zablokovaly položky z černé listiny.
#
# Syntaxe je následující:
#  * Všechno od znaku „#“ do konce řádku je komentář
#  * Každý neprázdný řádek je část regulárního výrazu, kterému budou odpovídat pouze domény z URL

 #</pre> <!-- nechejte tento řádek přesně tak jak je -->',
	'email-blacklist' => ' # Z e-mailů vyhovujících tomuto seznamu nebude možno zaregistrovat účet ani odesílat e-mail.
 # Tento seznam ovlivňuje jen tuto wiki; vizte také globální černou listinu.
 # Dokumentaci najdete na https://www.mediawiki.org/wiki/Extension:SpamBlacklist
 #<!-- tuto řádku ponechte přesně tak, jak je --> <pre>
#
# Syntaxe je následující:
#  * Všechno od znaku „#“ do konce řádku je komentář
#  * Každý neprázdný řádek je část regulárního výrazu, kterému budou odpovídat pouze domény v e-mailových adresách

 #</pre> <!-- tuto řádku ponechte přesně tak, jak je -->',
	'email-whitelist' => ' #<!-- tuto řádku ponechte přesně tak, jak je --> <pre>
# E-maily vyhovující tomuto seznamu *nebudou* blokovány, i kdyby
# odpovídaly záznamům v černé listině.
#
# Syntaxe je následující:
#  * Všechno od znaku „#“ do konce řádku je komentář
#  * Každý neprázdný řádek je část regulárního výrazu, kterému budou odpovídat pouze domény v e-mailových adresách
 #</pre> <!-- tuto řádku ponechte přesně tak, jak je -->',
	'spam-blacklisted-email' => 'E-mail na černé listině',
	'spam-blacklisted-email-text' => 'Vaše e-mailová adresa je momentálně uvedena na černé listině, takže ostatním uživatelům nemůžete posílat e-maily.',
	'spam-blacklisted-email-signup' => 'Uvedená e-mailová adresa je v současné době na černé listině.',
	'spam-invalid-lines' => 'Na černé listině spamu {{PLURAL:$1|je následující řádka neplatný regulární výraz|jsou následující řádky neplatné regulární výrazy|jsou následující řádky regulární výrazy}} a je nutné {{PLURAL:$1|ji|je|je}} před uložením stránky opravit :',
	'spam-blacklist-desc' => 'Antispamový nástroj na základě regulárních výrazů: [[MediaWiki:Spam-blacklist]] a [[MediaWiki:Spam-whitelist]]', # Fuzzy
);

/** Welsh (Cymraeg)
 * @author Lloffiwr
 * @author Xxglennxx
 */
$messages['cy'] = array(
	'spam-blacklist' => "# Dyma restr o gyfeiriadau URL allanol; os osodir un o'r rhain ar dudalen fe gaiff ei flocio.
 # Ar gyfer y wici hwn yn unig mae'r rhestr hon; mae rhestr waharddedig led-led yr holl wicïau i'w gael.
 # Gweler https://www.mediawiki.org/wiki/Extension:SpamBlacklist am ragor o wybodaeth.
 #<!-- leave this line exactly as it is --> <pre>
#
# Dyma'r gystrawen:
#   * Mae popeth o nod \"#\" hyd at ddiwedd y llinell yn sylwad
#   * Mae pob llinell nad yw'n wag yn ddarn regex sydd ddim ond yn cydweddu 
#   * gwesteiwyr tu mewn i gyfeiriadau URL

 #</pre> <!-- leave this line exactly as it is -->",
	'spam-whitelist' => ' #<!-- leave this line exactly as it is --> <pre>
# *Ni fydd* cyfeiriadau URL allanol sydd ar y rhestr hon yn cael eu blocio
# hyd yn oed pan ydynt ar restr arall o gyfeiriadau URL gwaharaddedig.
#
# Dyma\'r gystrawen:
#   * Mae popeth o nod "#" hyd at ddiwedd y llinell yn sylwad
#   * Mae pob llinell nad yw\'n wag yn ddarn regex sydd ddim ond yn cydweddu 
#   * gwesteiwyr tu mewn i gyfeiriadau URL

 #</pre> <!-- leave this line exactly as it is -->',
	'email-blacklist' => "#<!-- leave this line exactly as it is --> <pre>
# Fe gaiff cyfeiriadau ebost sydd yn cyfateb i'r rhestr hon eu blocio rhag iddynt gofrestru neu anfon ebyst
# Ar gyfer y wici hwn yn unig mae'r rhestr hon; mae rhestr waharddedig led-led yr holl wicïau i'w gael.
# Gweler https://www.mediawiki.org/wiki/Extension:SpamBlacklist am ragor o wybodaeth.
#
# Dyma'r gystrawen:
#   * Mae popeth o nod \"#\" hyd at ddiwedd y llinell yn sylwad
#   * Mae pob llinell nad yw'n wag yn ddarn regex sydd ddim ond yn cydweddu gwesteiwyr tu mewn i gyfeiriadau ebost

 #</pre> <!-- leave this line exactly as it is -->",
	'email-whitelist' => ' #<!-- leave this line exactly as it is --> <pre>
# *Ni fydd* cyfeiriadau ebost sydd ar y rhestr hon yn cael eu blocio
# hyd yn oed pan ydynt ar restr arall o gyfeiriadau ebost gwaharaddedig.
#
# Dyma\'r gystrawen:
#   * Mae popeth o nod "#" hyd at ddiwedd y llinell yn sylwad
#   * Mae pob llinell nad yw\'n wag yn ddarn regex sydd ddim ond yn cydweddu 
#   * gwesteiwyr tu mewn i gyfeiriadau ebost

 #</pre> <!-- leave this line exactly as it is -->',
	'spam-blacklisted-email' => 'Cyfeiriad ebost ar y rhestr waharddedig',
	'spam-blacklisted-email-text' => 'Mae eich cyfeiriad ebost wedi ei wahardd rhag anfon ebyst at ddefnyddwyr eraill ar hyn o bryd.',
	'spam-blacklisted-email-signup' => "Mae'r cyfeiriad ebost a roddwyd wedi ei wahardd rhag ei ddefnyddio ar hyn o bryd.",
	'spam-invalid-lines' => "Mae'r {{PLURAL:$1|llinell|llinell|llinellau}} canlynol ar y rhestr spam gwaharddedig yn {{PLURAL:$1|fynegiad|fynegiad|fynegiadau}} rheolaidd annilys; rhaid {{PLURAL:ei gywiro|ei gywiro|eu cywiro}} cyn rhoi'r dudalen ar gadw:",
	'spam-blacklist-desc' => "Teclyn gwrth-sbam yn seiliedig ar regex, sy'n galluogi gwahardd y canlynol - URLs o fewn tudalennau a chyfeiriadau ebost defnyddwyr cofrestredig",
	'log-name-spamblacklist' => 'Lòg y rhestr sbam waharddedig',
	'log-description-spamblacklist' => "Mae'r digwyddiadau hyn yn cofnodi trawiadau ar y rhestr sbam waharddedig.",
	'logentry-spamblacklist-hit' => 'Fe geisiodd $1 ychwanegu $4 sydd ar y rhestr waharddedig $3.',
	'right-spamblacklistlog' => 'Gallu gweld lòg y rhestr sbam waharddedig',
);

/** Danish (dansk)
 * @author Christian List
 * @author HenrikKbh
 * @author Hylle
 */
$messages['da'] = array(
	'spam-blacklist' => '#<!-- lad denne linje være nøjagtig som den er --> <pre>
 # Denne liste blokerer matchende eksterne URL\'er matching fra at blive tilføjet siden.
 # denne liste berører kun denne wiki; henviser også til den globale sortliste.
 # For dokumentation se https://www.mediawiki.org/wiki/Extension:SpamBlacklist
#
# syntaksen er som følger:
 #  * alt fra et "#" tegn til slutningen af linjen er en kommentar
 #  * hver ikke-tomme linjer anvendes som regulære udtryk for at matcha domænenavne i webadresser
 #</pre> <!-- lad denne linje være nøjagtig som den er -->',
	'spam-whitelist' => '#<!-- lad denne linje være nøjagtig som den er --> <pre>
# Eksterne URL\'er på denne liste bliver ikke blokeret, selvom de ville være blevet det gennem den globale sortliste.
# For dokumentation se https://www.mediawiki.org/wiki/Extension:SpamBlacklist
#
# Syntaksen er som følger:
#  * alt fra et "#" tegn til slutningen af linjen er en kommentar
#  * hver ikke-tomme linjer anvendes som regulære udtryk for at matcha domænenavne i webadresser
#</pre> <!-- lad denne linje være nøjagtig som den er -->',
	'email-blacklist' => '#<!-- lad denne linje være nøjagtig som den er --> <pre>
# E-mail adresser der er på denne liste vil blive blokeret fra at registreres eller fra at sende e-mails
# Denne liste vedrører kun denne wiki; se også den globale sortliste
# For dokumentation se https://www.mediawiki.org/wiki/Extension:SpamBlacklist
#
# Syntaksen er som følger:
#  * alt fra et "#" tegn til slutningen af linjen er en kommentar
#  * hver ikke-tomme linjer anvendes som regulære udtryk for at matche domænenavne i webadresser
#</pre> <!-- lad denne linje være nøjagtig som den er -->',
	'email-whitelist' => '#<!-- lad denne linje være nøjagtig som den er --> <pre>
# E-mail adresser på denne liste bliver ikke blokeret, selvom de ville være blevet det gennem den globale sortliste.
#
# Syntaksen er som følger:
#  * alt fra et "#" tegn til slutningen af linjen er en kommentar
#  * hver ikke-tomme linjer anvendes som regulære udtryk for at matcha domænenavne i webadresser
#</pre> <!-- lad denne linje være nøjagtig som den er -->',
	'spam-blacklisted-email' => 'Sortlistet e-mailadresse',
	'spam-blacklisted-email-text' => 'Din e-mailadresse er i øjeblikket blokeret for at sende e-mails til andre brugere.',
	'spam-blacklisted-email-signup' => 'Den angivne e-mailadresse er i øjeblikket blokeret for brug.',
	'spam-invalid-lines' => 'Følgende {{PLURAL:$1|linje|linjer}} i spamsortelisten er {{PLURAL:$1|et ugyldigt regulært udtryk|ugyldige regulære udtryk}} og må rettes før lagring af siden:',
	'spam-blacklist-desc' => 'Antispamværktøj baseret på regulære udtryk der giver mulighed for at sortliste URLs i sider og e-mailadresser for registrerede brugere',
	'log-name-spamblacklist' => 'Spamsortlistningslog',
	'log-description-spamblacklist' => 'Disse begivenheder er træfferer i spamsortlistningen.',
	'logentry-spamblacklist-hit' => '$1 ramte en regel i spamsortlisten på $3 ved at forsøge at tilføje $4.',
	'right-spamblacklistlog' => 'Vis spamsortlisteloggen',
	'action-spamblacklistlog' => 'se spamsortlisteloggen',
);

/** German (Deutsch)
 * @author Geitost
 * @author Kghbln
 * @author Metalhead64
 * @author Raimond Spekking
 * @author S2cchst
 * @author Umherirrender
 * @author Wnme
 */
$messages['de'] = array(
	'spam-blacklist' => ' # Externe URLs, die in dieser Liste enthalten sind, blockieren das Speichern einer Seite.
 # Diese Liste hat nur Auswirkungen auf dieses Wiki. Siehe ggf. auch die globale Blockierliste.
 # Siehe auch https://www.mediawiki.org/wiki/Extension:SpamBlacklist für die Dokumentation dieser Funktion. 
 #<!-- Diese Zeile darf nicht verändert werden! --> <pre>
#
# Syntax:
#   * Alles ab dem „#“-Zeichen bis zum Ende der Zeile ist ein Kommentar
#   * Jede nicht-leere Zeile ist ein regulärer Ausdruck, der gegen die Host-Namen in den URLs geprüft wird.

 #</pre> <!-- Diese Zeile darf nicht verändert werden! -->',
	'spam-whitelist' => ' #<!-- Diese Zeile darf nicht verändert werden! --> <pre>
# Externe URLs, die in dieser Liste enthalten sind, blockieren das Speichern einer Seite nicht, 
# auch wenn sie in der lokalen oder ggf. globalen Blockierliste enthalten sind.
#
# Syntax:
#   * Alles ab dem „#“-Zeichen bis zum Ende der Zeile ist ein Kommentar
#   * Jede nicht-leere Zeile ist ein regulärer Ausdruck, der gegen die Host-Namen in den URLs geprüft wird.

 #</pre> <!-- Diese Zeile darf nicht verändert werden! -->',
	'email-blacklist' => ' #<!-- Diese Zeile darf nicht verändert werden! --> <pre>
 # E-Mail-Adressen, die in dieser Liste enthalten sind, werden bei der Registrierung sowie beim Senden von E-Mail-Nachrichten geblockt.
 # Diese Liste hat nur Auswirkungen auf dieses Wiki. Siehe gegebenenfalls auch die globale Blockierliste.
 # Zur Dokumentation dieser Funktion siehe auch https://www.mediawiki.org/wiki/Extension:SpamBlacklist.
#
# Syntax wie folgt:
#   * Alles ab dem „#“-Zeichen bis zum Ende der Zeile ist ein Kommentar.
#   * Jede nicht-leere Zeile ist ein regulärer Ausdruck, der gegen die Host-Namen in den E-Mail-Adressen abgeglichen wird.

 #</pre> <!-- Diese Zeile darf nicht verändert werden! -->',
	'email-whitelist' => ' #<!-- Diese Zeile darf nicht verändert werden! --> <pre>
# E-Mail-Adressen, die sich in dieser Liste befinden, blockieren die Registrierung sowie
# das Senden von E-Mail-Nachrichten *nicht*, auch wenn sie in der 
# lokalen oder ggf. globalen Blockierliste enthalten sind.
#
 #</pre> <!-- Diese Zeile darf nicht verändert werden! -->',
	'spam-blacklisted-email' => 'Blockierte E-Mail-Adressen',
	'spam-blacklisted-email-text' => 'Deine E-Mail-Adresse ist derzeit für das Senden von E-Mail-Nachrichten an andere Benutzer blockiert.',
	'spam-blacklisted-email-signup' => 'Die angegebene E-Mail-Adresse ist derzeit für das Senden von E-Mail-Nachrichten an andere Benutzer blockiert.',
	'spam-invalid-lines' => 'Die {{PLURAL:$1|folgende Zeile|folgenden Zeilen}} in der Blockierliste {{PLURAL:$1|ist ein ungültiger regulärer Ausdruck|sind ungültige reguläre Ausdrücke}}. Sie {{PLURAL:$1|muss|müssen}} vor dem Speichern der Seite korrigiert werden:',
	'spam-blacklist-desc' => 'Ein auf regulären Ausdrücken basiertes Anti-Spam-Werkzeug, um URLs in Seiten und E-Mail-Adressen für registrierte Benutzer auf die schwarze Liste zu setzen',
	'log-name-spamblacklist' => 'Spam-Blacklist-Logbuch',
	'log-description-spamblacklist' => 'Es folgt ein Logbuch von Spam-Blacklist-Treffern.',
	'logentry-spamblacklist-hit' => '$1 verursachte einen Spam-Blacklist-Treffer auf „$3“ durch das versuchte Hinzufügen von $4.',
	'right-spamblacklistlog' => 'Spam-Blacklist-Logbuch ansehen',
	'action-spamblacklistlog' => 'dieses Logbuch einzusehen',
);

/** Swiss High German (Schweizer Hochdeutsch)
 * @author Geitost
 */
$messages['de-ch'] = array(
	'email-blacklist' => ' #<!-- Diese Zeile darf nicht verändert werden! --> <pre>
 # E-Mail-Adressen, die in dieser Liste enthalten sind, werden bei der Registrierung sowie beim Senden von E-Mail-Nachrichten geblockt.
 # Diese Liste hat nur Auswirkungen auf dieses Wiki. Siehe gegebenenfalls auch die globale Blockierliste.
 # Zur Dokumentation dieser Funktion siehe auch https://www.mediawiki.org/wiki/Extension:SpamBlacklist.
#
# Syntax wie folgt:
#   * Alles ab dem «#»-Zeichen bis zum Ende der Zeile ist ein Kommentar.
#   * Jede nicht-leere Zeile ist ein regulärer Ausdruck, der gegen die Host-Namen in den E-Mail-Adressen abgeglichen wird.

 #</pre> <!-- Diese Zeile darf nicht verändert werden! -->',
);

/** German (formal address) (Deutsch (Sie-Form)‎)
 * @author Kghbln
 */
$messages['de-formal'] = array(
	'spam-blacklisted-email-text' => 'Ihre E-Mail-Adresse ist derzeit für das Senden von E-Mail-Nachrichten an andere Benutzer blockiert.',
);

/** Zazaki (Zazaki)
 * @author Aspar
 * @author Erdemaslancan
 * @author Olvörg
 */
$messages['diq'] = array(
	'spam-blacklist' => '  #gıreyê teber ê ke na liste de zêpi bi bloke beni.
  # na liste tena no wiki re tesir beno.
  # Dokümantasyon: https://www.mediawiki.org/wiki/Extension:SpamBlacklist
  #<!-- no satır zey xo verdê --> <pre>
#
# rêzvateyê ey zey cêr o.:
#  * "#" karakteri ra heta satıro peyin her çi mışoreyo
#  * Her satıro dekerde, pêşkeşwan ê ke zerreyê URLlyi de tena parçeyê regexê .

  #</pre> <!-- no satır zey xo verdê -->',
	'spam-whitelist' => '  #<!-- no satır zey xo verdê --> <pre>
# gıreyê teber ê ke na liste de zêpi yê *bloke nêbeni*,
# wazeno pê listeya siya zi bloke bıbo.
#
# rêzvate zey cêr o:
#  * "#" karakteri raheta satıro peyin her çi mışoreyo
#  * Her satıro dekerde, pêşkeşwan ê ke zerreyê URLlyi de tena parçeyê regexê .

  #</pre> <!--no satır zey xo verdê -->',
	'email-blacklist' => '#Adresê e-postay ke eno liste de esto qandê starkerdış ya zi rusnayış rê blokeyo.
# eno liste tenya aidê eno wikiyo.Siyalisteyê globali rê bıwane.
#Qandê dokumentasyon  https://www.mediawiki.org/wiki/Extension:SpamBlacklist rê bıwane.
#
#Syntax zey cerêni;Hame yew karakterê "#" ra qediyeno u pêyê kommenti izahato. 
#Her satırê ke veng niyo yew fragmano nızamiyo u tenya qandê e-postayo.
 #</pre> <!-- Ena satıri bınuse -->',
	'email-whitelist' => "#<!-- pêroyi en satır bınuse --> <pre>
#Adresê e-postay ke eno liste de dero bloke ''nêbeno'' eke ravêr i biyê.
# Cı kewtışi terefê siyaliste biyo bloke.
#
 #</pre> <!-- pêroyî in satır bınuse ,satır ino-->
# Syntax zey ino:
#  * Hame ke be yew karakterê a \"#\" qediyeno kommentê peyêni yew izahato:
#  * Her satırê ke veng niyo yew fragmano nızamiyo u tenya qandê e-postayo.",
	'spam-blacklisted-email' => 'E-posta deyayo teni liste',
	'spam-blacklisted-email-text' => 'Nıka adresa e-postayê to qande karberê bini ra mesac riştene listeyê siya dero.',
	'spam-blacklisted-email-signup' => 'E-posta adresiyo ke deyayo karkerdışe cı newke groto siyaliste.',
	'spam-invalid-lines' => 'na qerelisteya spami {{PLURAL:$1|satır|satıran}}  {{PLURAL:$1|nemeqbulo|nemeqbuli}};',
	'spam-blacklist-desc' => 'Regex-tabanlı anti-spam aracı: [[MediaWiki:Spam-blacklist]] ve [[MediaWiki:Spam-whitelist]]', # Fuzzy
);

/** Lower Sorbian (dolnoserbski)
 * @author Michawiki
 */
$messages['dsb'] = array(
	'spam-blacklist' => ' # Eksterne URL, kótarež su w toś tej lisćinje, blokěruju se, gaž pśidawaju se bokoju.
 # Toś ta lisćina nastupa jano toś ten wiki; glědaj teke globalnu cornu lisćinu.
 # Za dokumentaciju glědaj https://www.mediawiki.org/wiki/Extension:SpamBlacklist
 #<!-- Wóstaj toś tu smužka rowno tak ako jo --><pre>
#
# Syntaksa jo ako slědujo:
#  * Wšykno wót znamuška "#" až ku kóńcoju smužki jo komentar
# Kužda smužka, kótaraž njejo prozna, jo fragment regularnego wuraza, kótaryž wótpowědujo hostam w URL

 #</pre> <!-- wóstaj toś tu smužku rowno ako jo -->',
	'spam-whitelist' => ' #<!-- wóstaj toś tu smužka rowno tak ako jo --> <pre>
 # Eksterne URL, kótarež sw toś tej lisćinje se *nje*blokěruju, samo jolic wone by
 # se blokěrowali pśez zapiski corneje lisćiny.
 #
 # Syntaksa jo ako slědujo:
 #  * Wšykno wót znamuška "#" ku kóńcoju smužki jo komentar
 #  * Kužda smužka, kótaraž njejo prozna, jo fragment regularanego wuraza, kótaryž wótpowědujo jano hostam w URL

 #</pre> <!-- wóstaj toś tu smužku rowno tak ako jo -->',
	'spam-blacklisted-email' => 'Blokěrowana e-mailowa adresa',
	'spam-invalid-lines' => '{{PLURAL:$1|Slědujuca smužka|Slědujucej smužce|Slědujuce smužki|Slědujuce smužki}} corneje lisćiny spama {{PLURAL:$1|jo njepłaśiwy regularny wuraz|stej njepłaśiwej regularnej wuraza|su njepłaśiwe regularne wuraze|su njepłaśiwe regularne wuraze}} a {{PLURAL:$1|musy|musytej|muse|muse}} se korigěrowaś, pjerwjej až składujoš bok:',
	'spam-blacklist-desc' => 'Antispamowy rěd na zakłaźe regularnych wurazow: [[MediaWiki:Spam-blacklist]] a [[MediaWiki:Spam-whitelist]]', # Fuzzy
);

/** Greek (Ελληνικά)
 * @author Dead3y3
 */
$messages['el'] = array(
	'spam-blacklist' => ' # Εξωτερικά URLs που ταιριάζουν σε αυτή τη λίστα θα φραγούν όταν προστίθενται σε μία σελίδα.
 # Αυτή η λίστα επηρεάζει μόνο αυτό το wiki· αναφερθείτε επίσης στην καθολική μαύρη λίστα.
 # Για τεκμηρίωση δείτε τον σύνδεσμο https://www.mediawiki.org/wiki/Extension:SpamBlacklist
 #<!-- leave this line exactly as it is --> <pre>
#
# Η σύνταξη είναι ως ακολούθως:
#  * Οτιδήποτε από τον χαρακτήρα «#» μέχρι το τέλος της γραμμής είναι ένα σχόλιο
#  * Οποιαδήποτε μη κενή γραμμή είναι ένα κομμάτι κανονικής έκφρασης το οποίο θα ταιριάξει μόνο hosts
#    μέσα σε URLs

 #</pre> <!-- leave this line exactly as it is -->',
	'spam-whitelist' => ' #<!-- leave this line exactly as it is --> <pre>
# Εξωτερικά URLs που ταιριάζουν σε αυτή τη λίστα _δεν_ θα φραγούν ακόμα και αν είχαν
# φραγεί από εγγραφές της μαύρης λίστας.
#
# Η σύνταξη είναι ως ακολούθως:
#  * Οτιδήποτε από τον χαρακτήρα «#» μέχρι το τέλος της γραμμής είναι ένα σχόλιο
#  * Οποιαδήποτε μη κενή γραμμή είναι ένα κομμάτι κανονικής έκφρασης το οποίο θα ταιριάξει μόνο hosts
#    μέσα σε URLs

 #</pre> <!-- leave this line exactly as it is -->',
	'spam-invalid-lines' => '{{PLURAL:$1|Η ακόλουθη γραμμή|Οι ακόλουθες γραμμές}} της μαύρης λίστας spam είναι {{PLURAL:$1|άκυρη κανονική έκφραση|άκυρες κανονικές εκφράσεις}} και {{PLURAL:$1|χρειάζεται|χρειάζονται}} διόρθωση πριν την αποθήκευση της σελίδας:',
	'spam-blacklist-desc' => 'Εργαλείο anti-spam βασισμένο σε κανονικές εκφράσεις: [[MediaWiki:Spam-blacklist]] και [[MediaWiki:Spam-whitelist]]', # Fuzzy
);

/** Esperanto (Esperanto)
 * @author Yekrats
 */
$messages['eo'] = array(
	'spam-blacklist' => '
 #<!-- ne ŝanĝu ĉi tiun linion iel ajn --> <pre>
# Eksteraj URL-oj kongruante al ĉi tiuj listanoj estos forbarita kiam aldonita al paĝo.
# Ĉi tiu listo nur regnas ĉi tiun vikion; ankaux aktivas la ĝenerala nigralisto.
 # Por dokumentaro, rigardu https://www.mediawiki.org/wiki/Extension:SpamBlacklist
#
# Jen la sintakso:
#  * Ĉio ekde "#" signo al la fino de linio estas komento
#  * Ĉiu ne-malplena linio estas regex kodero kiu nur kongruas retnodojn ene de URL-oj

 #</pre> <!-- ne ŝanĝu ĉi tiun linion iel ajn -->',
	'spam-whitelist' => ' #<!-- ne ŝanĝu ĉi tiun linion iel ajn --> <pre>
# Eksteraj URL-oj kongruante al ĉi tiuj listanoj *NE* estos forbarita eĉ se ili estus
# forbarita de nigralisto
#
# Jen la sintakso:
#  * Ĉio ekde "#" signo al la fino de linio estas komento
#  * Ĉiu nemalplena linio estas regex kodero kiu nur kongruas retnodojn ene de URL-oj
 #</pre> <!-- ne ŝanĝu ĉi tiun linion iel ajn -->',
	'email-blacklist' => ' # Retadresoj kongruante de ĉi tiu listo estos forbarita de reĝistrado aŭ sendado de retpoŝtoj
 # Ĉi tiu listo nur funkciigas ĉi tiun vikion; ankaŭ rigardu la ĝeneralan nigraliston.
 # Por dokumentado, vidu https://www.mediawiki.org/wiki/Extension:SpamBlacklist
 #<!-- leave this line exactly as it is --> <pre>
# Jen la sintakso:
#  * Ĉio ekde "#" signo al la fino de linio estas komento
#  * Ĉiu ne-malplena linio estas regex kodero kiu nur kongruas retnodojn ene de URL-oj

 #</pre> <!-- ne ŝanĝu ĉi tiun linion iel ajn -->',
	'email-whitelist' => ' #<!-- leave this line exactly as it is --> <pre>
# Retadresoj kongruante de ĉi tiu listo *ne* estos forbarita se ili estus forbarita de nigralisto.
# 
 #</pre> <!-- leave this line exactly as it is -->
# Jen la sintakso:
#  * Ĉio ekde "#" signo al la fino de linio estas komento
#  * Ĉiu ne-malplena linio estas regex kodero kiu nur kongruas retnodojn ene de URL-oj',
	'spam-blacklisted-email' => 'Retpoŝtadreso en nigra listo',
	'spam-blacklisted-email-text' => 'Via retpoŝtadreso estas nune membro de nigralisto forbarita de sendante retpoŝtojn al aliaj uzantoj.',
	'spam-blacklisted-email-signup' => 'Tiu retpoŝtadreso estas nune forbarita de uzado.',
	'spam-invalid-lines' => 'La {{PLURAL:$1|jena linio|jenaj linioj}} de spama nigralisto estas {{PLURAL:$1|nevlidaj regularaj esprimoj|nevlidaj regularaj esprimoj}} kaj devas esti {{PLURAL:$1|korektigita|korektigitaj}} antaŭ savante la paĝon:',
	'spam-blacklist-desc' => 'Regex-bazita kontraŭspamilo: [[MediaWiki:Spam-blacklist]] kaj [[MediaWiki:Spam-whitelist]]', # Fuzzy
);

/** Spanish (español)
 * @author Armando-Martin
 * @author Dferg
 * @author Drini
 * @author MarcoAurelio
 * @author Sanbec
 * @author Vivaelcelta
 */
$messages['es'] = array(
	'spam-blacklist' => ' # Enlaces externos que coincidan con esta lista serán bloqueados al añadirse a una página
 # Esta lista afecta sólo a esta wiki. Existe asímismo una lista global en Meta para todos los proyectos. 
 # Para documentación mire: https://www.mediawiki.org/wiki/Extension:SpamBlacklist
 #<!-- Deje esta línea exactamente como está --> <pre>
#
# La sintaxis es:
#  * Todo lo que aparezca desde un caracter "#" hasta el fin de la línea es un comentario
#  * Toda línea que no esté en blanco es una expresión regular que sólo se cotejará con URLs

 #</pre> <!-- Deje esta línea exactamente como está -->',
	'spam-whitelist' => ' #<!-- Deje esta línea exactamente como está --> <pre>
# URLs externas que coincidan con esta lista *no* serán bloqueadas incluso si coincidiesen
# con una entrada en la lista negra.
#
## La sintaxis es:
#  * Todo lo que aparezca desde un caracter "#" hasta el fin de la línea es un comentario
#  * Toda línea que no esté en blanco es una expresión regular que sólo se cotejará con URLs

 #</pre> <!-- Deje esta línea exactamente como está -->',
	'email-blacklist' => ' # Las direcciones de correo electrónico que coincidan con las de esta lista no podrán registrar cuentas ni enviar correos electrónicos
 # Esta lista sólo afecta a este proyecto aunque existe una lista global para todos los proyectos.
 # Documentación: https://www.mediawiki.org/wiki/Extension:SpamBlacklist
 #<!-- leave this line exactly as it is --> <pre>
#
# Sintaxis como sigue:
#   * Todo lo que sigue a un "#" se interpreta como un comentario
#   * Toda línea no en blanco es un fragmento de expresión regular (regex) que sólo coincidirá con los "host" de la dirección de correo electrónico.

 #</pre> <!-- leave this line exactly as it is -->',
	'email-whitelist' => ' #<!-- Deje esta línea exactamente como está --> <pre>
# Las direcciones de correo electrónico que aparecen en esta lista*no* serán bloqueadas incluso si hubieran
# debido ser bloqueadas por aparecer en la lista negra.
#
 #</pre> <!-- Deje esta línea exactamente como está-->
# La sintaxis es la siguiente:
#  * Todo texto a la derecha del carácter "#" hasta el final de la línea es un comentario
#  * Cada línea que no esté en blanco es un fragmento de código que será cotejada por los servidores (hosts) con las direcciones de correo electrónico',
	'spam-blacklisted-email' => 'Dirección de correo electrónico de la lista negra',
	'spam-blacklisted-email-text' => 'Su dirección de correo electrónico está actualmente en la lista negra y no puede enviar correos electrónicos a otros usuarios.',
	'spam-blacklisted-email-signup' => 'La dirección de correo electrónico dada está actualmente en la lista negra de uso.',
	'spam-invalid-lines' => '{{PLURAL:$1|La siguiente línea|Las siguientes líneas}} de la lista negra de spam {{PLURAL:$1|es una expresión regular inválida|son expresiones regulares inválidas}} y es necesario {{PLURAL:$1|corregirla|corregirlas}} antes de guardar la página:',
	'spam-blacklist-desc' => 'Herramienta anti-spam basada en expresiones regulares [[MediaWiki:Spam-blacklist]] y [[MediaWiki:Spam-whitelist]]', # Fuzzy
);

/** Estonian (eesti)
 * @author Pikne
 */
$messages['et'] = array(
	'spam-blacklist' => ' # Sellele nimekirjale vastavad internetiaadressid blokeeritakse.
 # See nimekiri puudutab ainult seda vikit; uuri ka globaalse musta nimekirja kohta.
 # Dokumentatsioon on asukohas https://www.mediawiki.org/wiki/Extension:SpamBlacklist
 #<!-- Jäta see rida muutmata kujule. --> <pre>
#
# Süntaks on järgmine:
#   * Kõik alates märgist "#" kuni rea lõpuni on kommentaar
#   * Iga rida, mis ei ole tühi, on regulaaravaldise osa, milleks sobib internetiaadressi osadest ainult hostinimi

 #</pre> <!-- Jäta see rida muutmata kujule. -->',
	'spam-whitelist' => ' #<!-- Jäta see rida muutmata kujule. --> <pre>
# Sellele nimekirjale vastavaid internetiaadresse *ei* blokeerita isegi mitte siis
# kui musta nimekirja sissekande järgi võiks nad olla blokeeritud.
#
# Süntaks on järgmine:
#   * Kõik alates märgist "#" kuni rea lõpuni on kommentaar
#   * Iga rida, mis ei ole tühi, on regulaaravaldise osa, milleks sobib internetiaadressi osadest ainult hostinimi

 #</pre> <!-- Jäta see rida muutmata kujule. -->',
	'email-blacklist' => ' # Sellele nimekirjale vastavatel e-posti aadressidel blokeeritakse registreerumine ja e-kirjade saatmine.
 # See nimekiri puudutab ainult seda vikit; uuri ka globaalse musta nimekirja kohta.
 # Dokumentatsioon on asukohas https://www.mediawiki.org/wiki/Extension:SpamBlacklist.
 #<!-- Jäta see rida muutmata kujule. --> <pre>
#
# Süntaks on järgmine:
#   * Kõik alates märgist "#" kuni rea lõpuni on kommentaar.
#   * Iga rida, mis ei ole tühi, on regulaaravaldise osa, mis vastab ainult e-posti aadressides sisalduvatele hostinimedele.

 #</pre> <!-- Jäta see rida muutmata kujule. -->',
	'email-whitelist' => ' #<!-- Jäta see rida muutmata kujule. --> <pre>
# Sellele nimekirjale vastavaid e-posti aadresse *ei* blokeerita isegi mitte siis,
# kui musta nimekirja sissekande järgi võiks nad olla blokeeritud.
#
 #</pre> <!-- Jäta see rida muutmata kujule. -->
# Süntaks on järgmine:
#   * Kõik alates märgist "#" kuni rea lõpuni on kommentaar.
#   * Iga rida, mis ei ole tühi, on regulaaravaldise osa, mis vastab ainult e-posti aadressides sisalduvatele hostinimedele.',
	'spam-blacklisted-email' => 'Musta nimekirja kantud e-posti aadress',
	'spam-blacklisted-email-text' => 'Musta nimekirja sissekande tõttu on sinu e-posti aadressilt teistele kasutajatele e-kirjade saatmine praegu keelatud.',
	'spam-blacklisted-email-signup' => 'Selle e-posti aadressi kasutamine praegu musta nimekirja sissekandega keelatud.',
	'spam-invalid-lines' => '{{PLURAL:$1|Järgmine rida|Järgmised read}} rämpspostituste mustas nimekirjas on {{PLURAL:$1|vigane regulaaravaldis|vigased regulaaravaldised}} ja {{PLURAL:$1|see|need}} tuleb enne lehekülje salvestamist parandada:',
	'spam-blacklist-desc' => 'Regulaaravaldisel põhinev tööriist, mis võimaldab lisada musta nimekirja lehekülgedel toodud internetiaadresse ning registreeritud kasutajate e-posti aadresse.',
);

/** Persian (فارسی)
 * @author Ebraminio
 * @author Huji
 * @author Meisam
 */
$messages['fa'] = array(
	'spam-blacklist' => ' # از درج پیوندهای بیرونی که با این فهرست مطابقت کنند جلوگیری می‌شود.
 # این فهرست فقط روی همین ویکی اثر دارد؛ به فهرست سیاه سراسری نیز مراجعه کنید.
 # برای مستندات به https://www.mediawiki.org/wiki/Extension:SpamBlacklist مراجعه کنید
 #<!-- این سطر را همان‌گونه که هست رها کنید --> <pre>
# دستورات به این شکل هستند:
#  * همه چیز از «#» تا پایان سطر به عنوان توضیح در نظر گرفته می‌شود
#  * هر سطر از متن به عنوان یک دستور از نوع عبارت باقاعده در نظر گرفته می‌شود که فقط  با نام میزبان در نشانی اینترنتی مطابقت داده می‌شود

 #</pre> <!-- این سطر را همان‌گونه که هست رها کنید -->',
	'spam-whitelist' => ' #<!-- این سطر را همان‌گونه که هست رها کنید --> <pre>
# از درج پیوندهای بیرونی که با این فهرست مطابقت کنند جلوگیری *نمی‌شود* حتی اگر
# در فهرست سیاه قرار داشته باشند.
#
 #</pre> <!-- این سطر را همان‌گونه که هست رها کنید -->',
	'email-blacklist' => ' # از ثبت نام یا ارسال نامه توسط نشانی‌های پست الکترونیکی که با این فهرست مطابقت کنند جلوگیری می‌شود.
 # این فهرست فقط روی همین ویکی اثر دارد؛ به فهرست سیاه سراسری نیز مراجعه کنید.
 # برای مستندات به https://www.mediawiki.org/wiki/Extension:SpamBlacklist مراجعه کنید
 #<!-- این سطر را همان‌گونه که هست رها کنید --> <pre>
# دستورات به این شکل هستند:
#  * همه چیز از «#» تا پایان سطر به عنوان توضیح در نظر گرفته می‌شود
#  * هر سطر از متن به عنوان یک دستور از نوع عبارت باقاعده در نظر گرفته می‌شود که فقط با نام میزبان در نشانی پست الکترونیکی مطابقت داده می‌شود

 #</pre> <!-- این سطر را همان‌گونه که هست رها کنید -->',
	'email-whitelist' => ' #<!-- این سطر را همان‌گونه که هست رها کنید --> <pre>
# نشانی‌های پست الکترونیکی که با این فهرست مطابقت کنند محدود *نمی‌شوند* حتی اگر
# با فهرست سیاه مطابقت داشته باشند.
#
 #</pre> <!-- این سطر را همان‌گونه که هست رها کنید -->
# دستورات به این شکل هستند:
#  * همه چیز از «#» تا پایان سطر به عنوان توضیح در نظر گرفته می‌شود
#  * هر سطر از متن به عنوان یک دستور از نوع عبارت باقاعده در نظر گرفته می‌شود که فقط با نام میزبان در نشانی پست الکترونیکی مطابقت داده می‌شود',
	'spam-blacklisted-email' => 'نشانی پست الکترونیکی موجود در لیست سیاه',
	'spam-blacklisted-email-text' => 'نشانی پست الکترونیکی شما در حال حاضر در فهرست سیاه قرار دارد و نمی‌توانید به دیگر کاربران نامه بفرستید.',
	'spam-blacklisted-email-signup' => 'نشانی پست الکترونیکی داده شده در حال حاضر در فهرست سیاه است و قابل استفاده نیست.',
	'spam-invalid-lines' => '{{PLURAL:$1|سطر|سطرهای}} زیر در فهرست سیاه هرزنگاری، عبارات باقاعدهٔ نامجاز {{PLURAL:$1|است|هستند}} و قبل از ذخیره کردن صفحه باید اصلاح {{PLURAL:$1|شود|شوند}}:',
	'spam-blacklist-desc' => 'ابزار ضد هرزنویسی مبتنی بر regular expressions: [[MediaWiki:Spam-blacklist]] و [[MediaWiki:Spam-whitelist]]', # Fuzzy
);

/** Finnish (suomi)
 * @author Cimon Avaro
 * @author Crt
 * @author Linnea
 * @author Nike
 * @author Olli
 * @author Pxos
 */
$messages['fi'] = array(
	'spam-blacklist' => '  #<!-- leave this line exactly as it is --> <pre>
 # Tämän listan säännöillä voi estää ulkopuolisiin sivustoihin viittaavien osoitteiden lisäämisen.
 # Tämä lista koskee vain tätä wikiä. Tutustu myös järjestelmänlaajuiseen mustaan listaan.
 # Lisätietoja on osoitteessa http://www.mediawiki.org/wiki/Extension:SpamBlacklist
 #<!-- Älä koske tähän riviin lainkaan --> <pre>
#
# Syntaksi on seuraavankaltainen:
#   * Kaikki #-merkistä lähtien rivin loppuun asti on kommenttia
#   * Jokainen ei-tyhjä rivi on säännöllisen lausekkeen osa, joka tunnistaa vain osoitteissa olevat verkkotunnukset.

 #</pre> <!-- Älä koske tähän riviin lainkaan -->',
	'spam-whitelist' => '  #<!-- älä koske tähän riviin --> <pre>
 # Tällä sivulla on säännöt, joihin osuvia ulkoisia osoitteita ei estetä, vaikka ne olisivat mustalla listalla.
#
# Syntaksi on seuraava:
#  * Kommentti alkaa #-merkistä ja jatkuu rivin loppuun
#  * Muut ei-tyhjät rivit tulkitaan säännöllisen lausekkeen osaksi, joka tutkii vain osoitteissa olevia verkko-osoitteita.

 #</pre> <!-- älä koske tähän riviin -->',
	'email-blacklist' => '  #<!-- leave this line exactly as it is --> <pre> 
# Tällä listalla olevia sähköpostiosoitteita estetään rekisteröitymästä tai lähettämästä sähköpostia
# Tämä lista koskee vain tätä wikiä. Tutustu myös järjestelmänlaajuiseen mustaan listaan.
# Lisätietoja on osoitteessa https://www.mediawiki.org/wiki/Extension:SpamBlacklist
#
# Syntaksi on seuraavankaltainen:
#   * Kaikki #-merkistä lähtien rivin loppuun asti on kommenttia
#   * Jokainen ei-tyhjä rivi on säännöllisen lausekkeen osa, joka tunnistaa vain sähköpostiosoitteissa olevat verkkotunnukset.

 #</pre> <!-- Älä koske tähän riviin lainkaan -->',
	'spam-blacklisted-email' => 'Mustalla listalla oleva sähköpostiosoite',
	'spam-blacklisted-email-text' => 'Sähköpostisi on tällä hetkellä mustalla listalla, etkä voi lähettää sähköpostia muille käyttäjille.',
	'spam-blacklisted-email-signup' => 'Annettu sähköpostiosoite on tällä hetkellä mustalla listalla.',
	'spam-invalid-lines' => 'Listalla on {{PLURAL:$1|seuraava virheellinen säännöllinen lauseke, joka|seuraavat virheelliset säännölliset lausekkeet, jotka}} on korjattava ennen tallentamista:',
	'spam-blacklist-desc' => 'Säännöllisiä lausekkeita (reg-ex) tukeva roskalinkkejä torjuva työkalu, jonka avulla internet-osoitteita (URL) sivuilla ja sähköpostiosoitteissa voidaan asettaa mustalle listalle. Tarkoitettu kirjautuneille käyttäjille.',
	'log-name-spamblacklist' => 'Roskalinkkien torjuntalistan loki',
	'log-description-spamblacklist' => 'Nämä tapahtumat ovat osumia roskalinkkien torjuntalistalla.',
	'logentry-spamblacklist-hit' => '$1 sai aikaan osuman roskalinkkien torjuntalistalla kohteessa $3 yrittäessään lisätä $4',
	'right-spamblacklistlog' => 'Tarkastella roskalinkkien torjuntalistan lokia',
	'action-spamblacklistlog' => 'nähdä roskalinkkien torjuntalistan lokia',
);

/** French (français)
 * @author Gomoko
 * @author Sherbrooke
 * @author Urhixidur
 * @author Verdy p
 */
$messages['fr'] = array(
	'spam-blacklist' => ' # Les liens externes faisant partie de cette liste seront bloqués lors de leur insertion dans une page.
 # Cette liste n’affecte que ce wiki ; référez-vous aussi à la liste noire globale.
 # La documentation se trouve à l’adresse suivante : https://www.mediawiki.org/wiki/Extension:SpamBlacklist
 #<!-- Laissez cette ligne telle quelle --><pre>
#
# La syntaxe est la suivante :
#  * tout texte qui suit un « # » est considéré comme un commentaire ;
#  * toute ligne non vide est un fragment d’expression rationnelle qui n’analysera que les hôtes dans les liens hypertextes.

 #</pre><!-- Laissez cette ligne telle quelle -->',
	'spam-whitelist' => ' #<!-- Laissez cette ligne telle quelle--><pre>
# Les liens hypertextes externes correspondant à cette liste ne seront *pas* bloqués
# même s’ils auraient été bloqués par les entrées de la liste noire.
#
# La syntaxe est la suivante :
#  * tout texte qui suit un « # » est considéré comme un commentaire ;
#  * toute ligne non vide est un fragment d’expression rationnelle qui n’analysera que les hôtes dans les liens hypertextes.

 #</pre> <!--Laissez cette ligne telle quelle -->',
	'email-blacklist' => "# Les adresses de courriel correspondant à cette liste seront bloquées lors l'enregistrement ou de l'envoi d'un courriel
 # Cette liste n’affecte que ce wiki ; référez-vous aussi à la liste noire globale.
 # La documentation se trouve à l’adresse suivante : https://www.mediawiki.org/wiki/Extension:SpamBlacklist
 #<!-- Laissez cette ligne telle quelle --><pre>
#
# La syntaxe est la suivante :
#  * tout texte qui suit un \"#\" est considéré comme un commentaire
#  * toute ligne non vide est un fragment d’expression rationnelle qui n’analysera que les hôtes correspondant dans les URLs.

 #</pre><!-- Laissez cette ligne telle quelle -->",
	'email-whitelist' => "<!-- laissez cette ligne telle quelle --> <pre>
# Les adresses de courriels correspondant à cette liste ne seront *pas* bloqués même s'ils auraient
# dû l'être par les entrées de la liste noire.
#
 #</pre> <!-- laissez cette ligne telle quelle -->
# La syntaxe est comme suit :
#  * Tout texte à partir du caractère « # » jusqu'à la fin de la ligne est un commentaire.
#  * Chaque ligne non vide est un morceau de regex (expression rationnelle) qui sera mis en correspondance avec la partie « hosts » des adresses de courriels",
	'spam-blacklisted-email' => 'Adresses courriel et liste noire',
	'spam-blacklisted-email-text' => "Votre adresse de courriel est actuellement sur une liste noire d'envoi de courriel aux autres utilisateurs.",
	'spam-blacklisted-email-signup' => "L'adresse de courriel fournie est actuellement sur une liste noire d'utilisation.",
	'spam-invalid-lines' => '{{PLURAL:$1|La ligne suivante|Les lignes suivantes}} de la liste noire des polluriels {{PLURAL:$1|est une expression rationnelle invalide|sont des expressions rationnelles invalides}} et doi{{PLURAL:$1||ven}}t être corrigée{{PLURAL:$1||s}} avant d’enregistrer la page :',
	'spam-blacklist-desc' => 'Outil anti-pourriel basé sur des expressions rationnelles permettant de mettre en liste noire des URLs dans les pages et des adresses de courriel pour les utilisateurs enregistrés',
	'log-name-spamblacklist' => 'Journal de liste noire des pourriels',
	'log-description-spamblacklist' => 'Ces événements tracent les correspondances avec la liste noire des pourriels.',
	'logentry-spamblacklist-hit' => '$1 a provoqué un correspondance avec la liste noire des pourriels sur $3 en essayant d’ajouter $4.',
	'right-spamblacklistlog' => 'Afficher le journal de la liste noire des pourriels',
	'action-spamblacklistlog' => 'afficher le journal de la liste noir des pourriels',
);

/** Franco-Provençal (arpetan)
 * @author ChrisPtDe
 */
$messages['frp'] = array(
	'spam-blacklist' => ' # Los lims hipèrtèxtos de defôr que sont dens ceta lista seront blocâs pendent lor entrebetâ dens una pâge.
 # Ceta lista afècte ren que ceti vouiqui ; refèrâd-vos asse-ben a la lista nêre globâla.
 # La documentacion sè trove a ceta adrèce : https://www.mediawiki.org/wiki/Extension:SpamBlacklist
 #<!-- lèssiéd ceta legne justo d’ense --> <pre>
#
# La sintaxa est ceta :
#  * Tot tèxto que siut un « # » est considèrâ coment un comentèro.
#  * Tota legne pas voueda est un bocon d’èxprèssion racionèla (*RegEx*) qu’analiserat ren que los hôtos dedens los lims hipèrtèxtos.

  #</pre> <!-- lèssiéd ceta legne justo d’ense -->',
	'spam-whitelist' => ' #<!-- lèssiéd ceta legne justo d’ense --> <pre>
# Los lims hipèrtèxtos de defôr que sont dens ceta lista seront *pas* blocâs mémo
# s’ils ariant étâ blocâs per les entrâs de la lista nêre.
#
# La sintaxa est ceta :
#  * Tot tèxto que siut un « # » est considèrâ coment un comentèro.
#  * Tota legne pas voueda est un bocon d’èxprèssion racionèla (*RegEx*) qu’analiserat ren que los hôtos dedens los lims hipèrtèxtos.

  #</pre> <!-- lèssiéd ceta legne justo d’ense -->',
	'spam-invalid-lines' => '{{PLURAL:$1|Ceta legne|Cetes legnes}} de la lista nêre des spames {{PLURAL:$1|est una èxprèssion racionèla envalida|sont des èxprèssions racionèles envalides}} et dê{{PLURAL:$1||von}}t étre corregiê{{PLURAL:$1||s}} devant que sôvar la pâge :',
	'spam-blacklist-desc' => "Outil anti-spame basâ sur des èxprèssions racionèles (''RegEx'') : ''[[MediaWiki:Spam-blacklist]]'' et ''[[MediaWiki:Spam-whitelist]]''.", # Fuzzy
);

/** Galician (galego)
 * @author Alma
 * @author Toliño
 * @author Xosé
 */
$messages['gl'] = array(
	'spam-blacklist' => ' #<!-- Deixe esta liña tal e como está --> <pre>
# As ligazóns externas que coincidan na súa totalidade ou en parte con algún rexistro desta lista serán bloqueadas cando se intenten engadir a unha páxina.
# Esta lista afecta unicamente a este wiki; tamén existe unha lista global.
# Para obter máis documentación vaia a https://www.mediawiki.org/wiki/Extension:SpamBlacklist
#
# A sintaxe é a seguinte:
#   * Todo o que vaia despois dun carácter "#" ata o final da liña é un comentario
#   * Toda liña que non estea en branco é un fragmento de expresión regular que só coincide con dominios dentro de enderezos URL

 #</pre> <!-- Deixe esta liña tal e como está -->',
	'spam-whitelist' => ' #<!-- Deixe esta liña tal e como está --> <pre>
# As ligazóns externas que coincidan con esta lista *non* serán bloqueadas mesmo se
# fosen bloqueadas mediante entradas na lista negra.
#
# A sintaxe é a seguinte:
#   * Todo o que vaia despois dun carácter "#" ata o final da liña é un comentario
#   * Toda liña que non estea en branco é un fragmento de expresión regular que só coincide con dominios dentro de enderezos URL

 #</pre> <!-- Deixe esta liña tal e como está -->',
	'email-blacklist' => ' #<!-- Deixe esta liña tal e como está --> <pre>
# Os enderezos de correo electrónico que coincidan na súa totalidade ou en parte con algún rexistro desta lista serán bloqueadas cando se intenten rexistrar ou se intente enviar un correo desde eles.
# Esta lista afecta unicamente a este wiki; tamén existe unha lista global.
# Para obter máis documentación vaia a https://www.mediawiki.org/wiki/Extension:SpamBlacklist
#
# A sintaxe é a seguinte:
#   * Todo o que vaia despois dun carácter "#" ata o final da liña é un comentario
#   * Toda liña que non estea en branco é un fragmento de expresión regular que só coincide con dominios dentro de enderezos de correo electrónico

 #</pre> <!-- Deixe esta liña tal e como está -->',
	'email-whitelist' => ' #<!-- Deixe esta liña tal e como está --> <pre>
# Os enderezos de correo electrónico que coincidan con algún desta lista *non* serán bloqueados,
# mesmo se foron bloqueados por entradas da lista negra.
#
# A sintaxe é a seguinte:
#   * Todo o que vaia despois dun carácter "#" ata o final da liña é un comentario
#   * Toda liña que non estea en branco é un fragmento de expresión regular que só coincide con dominios dentro de enderezos de correo electrónico

 #</pre> <!-- Deixe esta liña tal e como está -->',
	'spam-blacklisted-email' => 'Enderezo de correo electrónico presente na lista negra',
	'spam-blacklisted-email-text' => 'O seu enderezo de correo electrónico atópase na lista negra e non pode enviar correos electrónicos aos outros usuarios.',
	'spam-blacklisted-email-signup' => 'O enderezo de correo electrónico especificado está na lista negra e non se pode empregar.',
	'spam-invalid-lines' => '{{PLURAL:$1|A seguinte liña|As seguintes}} da lista negra de spam {{PLURAL:$1|é unha expresión regular inválida|son expresións regulares inválidas}} e {{PLURAL:$1|haina|hainas}} que corrixir antes de gardar a páxina:',
	'spam-blacklist-desc' => 'Ferramenta antispam baseada en expresións regulares que permite incluír enderezos URL e enderezos de correo electrónico nunha lista negra para os usuarios rexistrados',
	'log-name-spamblacklist' => 'Rexistro da lista negra de spam',
	'log-description-spamblacklist' => 'Este rexistro fai un seguimento das coincidencias coa lista negra de spam.',
	'logentry-spamblacklist-hit' => '$1 provocou a activación da lista negra de spam en "$3" ao intentar engadir $4.',
	'right-spamblacklistlog' => 'Ver o rexistro da lista negra de spam',
	'action-spamblacklistlog' => 'ver o rexistro da lista negra de spam',
);

/** Swiss German (Alemannisch)
 * @author Als-Holder
 */
$messages['gsw'] = array(
	'spam-blacklist' => ' # Externi URL, wu in däre Lischt sin, blockiere s Spychere vu dr Syte.
 # Die Lischt giltet nume fir des Wiki; lueg au di wältwyt Blacklist.
 # Fir d Dokumentation lueg https://www.mediawiki.org/wiki/Extension:SpamBlacklist
 #<!-- Die Zyylete derf nit gänderet wäre! --> <pre>
#
# Syntax:
#  * Alles ab em "#"-Zeiche bis zum Änd vu dr Zyylete isch e Kommentar
#  * Jede Zyylete, wu nit läär isch, isch e reguläre Usdruck, wu gege d Host-Näme in dr URL prieft wird.

 #</pre> <!-- Die Zyylete derf nit gänderet wäre! -->',
	'spam-whitelist' => ' #</pre> <!-- Die Zyylete derf nit gänderet wäre! -->
# Externi URL, wu in däre Lischt sin, blockiere s Spychere vu dr Syte nit, au wänn si in dr wältwyte oder lokale Schwarze Lischt din sin.
#
# Syntax:
#  * Alles ab em "#"-Zeiche bis zum Änd vu dr Zyylete isch e Kommentar
#  * Jede Zyylete, wu nit läär isch, isch e reguläre Usdruck, wu gege d Host-Näme in dr URL prieft wird.

 #</pre> <!-- Die Zyylete derf nit gänderet wäre! -->',
	'email-blacklist' => ' # E-Mail-Adrässe, wu s nume in däre Lischt het, blockiere d Regischtrierig un s Sände vu E-Mail-Nochrichte.
 # Die Lischt giltet nume fir des Wiki; lueg au di wältwyt Blacklist.
 # Fir d Dokumentation lueg https://www.mediawiki.org/wiki/Extension:SpamBlacklist
 #<!-- Die Zyylete derf nit gänderet wäre! --> <pre>
#
# Syntax:
#  * Alles ab em "#"-Zeiche bis zum Änd vu dr Zyylete isch e Kommentar
#  * Jede Zyylete, wu nit läär isch, isch e reguläre Usdruck, wu gege d Host-Näme in dr URL prieft wird.

 #</pre> <!-- Die Zyylete derf nit gänderet wäre! -->',
	'email-whitelist' => ' #<!-- Die Zyylete derf nit gänderet wäre! -->
# E-Mail-Adrässe, wu s nume in däre Lischt het, blockiere d Regischtrierig un 
# s Sände vu E-Mail-Nochrichte *nit*, au wänn si in dr 
# lokale oder villicht au globale Blockierlischt din sin.
# 
 #<!-- Die Zyylete derf nit gänderet wäre! --> <pre>
# Syntax:
#  * Alles ab em "#"-Zeiche bis zum Änd vu dr Zyylete isch e Kommentar
#  * Jede Zyylete, wu nit läär isch, isch e reguläre Usdruck, wu gege d Host-Näme in dr URL prieft wird.',
	'spam-blacklisted-email' => 'Blockierti E-Mail-Adrässe',
	'spam-blacklisted-email-text' => 'Dyy E-Mail-Adräss isch zurzyt fir s Sände vu E-Mail-Nochrichte an anderi Benutzer blockiert.',
	'spam-blacklisted-email-signup' => 'Di aagee E-Mail-Adräss isch zurzyt fir s Sände vu E-Mail-Nochrichte an anderi Benutzer blockiert.',
	'spam-invalid-lines' => 'Die {{PLURAL:$1|Zyylete|Zyylete}} in dr Spam-Blacklist {{PLURAL:$1|isch e nit giltige reguläre Usdruck|sin nit giltigi reguläri Usdrick}}. Si {{PLURAL:$1|muess|mien}} vor em Spychere vu dr Syte korrigiert wäre:',
	'spam-blacklist-desc' => 'Regex-basiert Anti-Spam-Wärchzyyg: [[MediaWiki:Spam-blacklist]] un [[MediaWiki:Spam-whitelist]]', # Fuzzy
);

/** Gujarati (ગુજરાતી)
 * @author Ashok modhvadia
 * @author KartikMistry
 * @author Sushant savla
 */
$messages['gu'] = array(
	'spam-blacklist' => ' # જ્યારે કોઈ પાનામાં આ યાદીને મળતા બાહ્ય URLs ઉમેરાશે ત્યારે તેમને રોકી દેવાશે. 
 # આ યાદી માત્ર આ વિકિ પરજ કાર્યાન્વીત છે.; વૈશ્વીક પ્રતિબંધ યાદી જોવા પણ વિનંતી. 
 # દસ્તાવજ માટે  https://www.mediawiki.org/wiki/Extension:SpamBlacklist જુઓ.
 #<!-- leave this line exactly as it is --> <pre>
#
# સૂત્ર લેખન (સિન્ટેક્સ) આ પ્રમાણે છે:
#   * Everything from a "#" character to the end of the line is a comment
#   * Every non-blank line is a regex fragment which will only match hosts inside URLs

 #</pre> <!-- leave this line exactly as it is -->',
	'spam-whitelist' => ' #<!-- આ લાઈનને એમની એમ જ રહેવા દેશો --> <pre>
# યાદીને મળતા અવતા બાહ્ય URLs નેપ્રતિબંધિત  *નહીં* કરાય  પછી ભલે તેમના
# પ્રતિબંધીત યાદીને ઍંટ્રીમાં રોક લગાડેલી હોય.
#
 #</pre> <!-- આ લાઈનને એમની એમ જ રહેવા દેશો  -->',
	'email-blacklist' => ' # આ યાદીને મળતા ઈ-મેલની નોંધણી કે તેમના દ્વારા મેલ આવાગમનને રોકી દેવાશે. 
 # આ યાદી માત્ર આ વિકિ પરજ કાર્યાન્વીત છે.; વૈશ્વીક પ્રતિબંધ યાદી જોવા પણ વિનંતી. 
 # દસ્તાવજ માટે  https://www.mediawiki.org/wiki/Extension:SpamBlacklist જુઓ.
 #<!-- leave this line exactly as it is --> <pre>
#
# સૂત્ર લેખન (સિન્ટેક્સ) આ પ્રમાણે છે:
#   * Everything from a "#" character to the end of the line is a comment
#   * Every non-blank line is a regex fragment which will only match hosts inside URLs

 #</pre> <!-- leave this line exactly as it is -->',
	'email-whitelist' => ' #<!-- leave this line exactly as it is --> <pre>
# આ યાદીને મળતાં ઈ-મેલ ને પ્રતિબંધિત *નહીં* કરી શકાય પછી ભલે તેમના પર
# પ્રતિબંધીત સૂચિ દ્વારા રોક લગાવાઈ હોય. 
#
 #</pre> <!-- leave this line exactly as it is -->
# સૂત્ર રચના આમુજબ હશે.:
#   * Everything from a "#" character to the end of the line is a comment
#   * Every non-blank line is a regex fragment which will only match hosts inside e-mail addresses',
	'spam-blacklisted-email' => 'પ્રતિબંધિત ઈ-મેલ સરનામું',
	'spam-blacklisted-email-text' => 'તમારા ઈ-મેલ સરનામાં પર હાલમાં પ્રતિબંધ લગાડેલો છે આથી તમે ઈ-મેલ મોકલી  નહીં શકો.',
	'spam-blacklisted-email-signup' => 'આ ઈ-મેલ પર હાલમાં વપરાશ પ્રતિબંધ લાગેલો છે.',
	'spam-invalid-lines' => 'નીચેને સ્પૅમ બ્લેકલીસ્ટમાં {{PLURAL:$1| લાઈન|લાઈનો}} અમાન્ય છે. નિયમીત {{PLURAL:$1|expression|expressions}} અને પાનુમ્ સાચવ્યાં પહેલા તેને સુધારી લેશો.',
	'spam-blacklist-desc' => 'Regex-આધારિત ઍન્ટી સ્પૅમ સાધનો પાનાઓ અને નોંધાયેલા સભ્યોનાં ઇમેલ સરનામાઓમાં URLs ને બ્લેકલિસ્ટ કરવા દે છે.',
);

/** Hebrew (עברית)
 * @author Amire80
 * @author Ofekalef
 * @author Rotem Liss
 */
$messages['he'] = array(
	'spam-blacklist' => ' # כתובות URL חיצוניות התואמות לרשימה זו ייחסמו בעת הוספתן לדף.
 # רשימה זו משפיעה על אתר זה בלבד; שימו לב גם לרשימה הכללית.
 # לתיעוד ראו https://www.mediawiki.org/wiki/Extension:SpamBlacklist
 #<!-- נא להשאיר שורה זו בדיוק כפי שהיא --> <pre>
#
# התחביר הוא כדלקמן:
#   * כל דבר מתו "#" לסוף השורה הוא הערה
#   * כל שורה לא ריקה היא קטע מביטוי רגולרי שיתאים לשמות המתחם של כתובות URL

 #</pre> <!-- נא להשאיר שורה זו בדיוק כפי שהיא -->',
	'spam-whitelist' => ' #<!-- נא להשאיר שורה זו בדיוק כפי שהיא --> <pre>
# כתובות URL חיצוניות המופיעות ברשימה זו *לא* ייחסמו אפילו אם יש להן ערך ברשימת הכתובות האסורות.
#
# התחביר הוא כדלקמן:
#   * כל דבר מתו "#" לסוף השורה הוא הערה
#   * כל שורה לא ריקה היא קטע מביטוי רגולרי שיתאים לשמות המתחם של כתובות URL

 #</pre> <!-- נא להשאיר שורה זו בדיוק כפי שהיא -->',
	'email-blacklist' => ' # עבור כתובות הדואר האלקטרוני המתאימות לרשימה זו תיחסם האפשרות להירשם ולשלוח דואר אלקטרוני
 # רשימה זו משפיעה רק על ויקי זה; שימו לב גם לרשימה הגלובלית.
 # לתיעוד ראו https://www.mediawiki.org/wiki/Extension:SpamBlacklist
 #<!-- leave this line exactly as it is --> <pre>
#
# התחביר הוא כדלקמן:
# * הכול החל מהתו "#" עד סוף השורה הוא הערה
# * כל שורה לא ריקה היא ביטוי רגולרי חלקי שתתאים רק לשרתים בתוך הדואר האלקטרוני

 #</pre> <!-- leave this line exactly as it is -->',
	'email-whitelist' => ' #<!-- leave this line exactly as it is --> <pre>
# כתובות הדואר האלקטרוני המתאימות לרשימה זו *לא* תיחסמנה אף אם הן מתאימות לרשימה השחורה.
#
 #</pre> <!-- leave this line exactly as it is -->
# התחביר הוא כדלקמן:
# * הכול החל מהתו "#" עד סוף השורה הוא הערה
# * כל שורה לא ריקה היא ביטוי רגולרי חלקי שתתאים רק לשרתים בתוך הדואר האלקטרוני',
	'spam-blacklisted-email' => 'כתובות דוא"ל ברשימה השחורה',
	'spam-blacklisted-email-text' => 'כתובת הדוא"ל שלך נמצאת כרגע ברשימה השחורה של כתובות שלא ניתן לשלוח מהן הודעות למשמתמשים אחרים.',
	'spam-blacklisted-email-signup' => 'כתובת הדוא"ל הזאת נמצאת כרגע ברשימה השחורה של כתובות אסורות לשימוש.',
	'spam-invalid-lines' => '{{PLURAL:$1|השורה הבאה|השורות הבאות}} ברשימת כתובות ה־URL האסורות
	{{PLURAL:$1|היא ביטוי רגולרי בלתי תקין ויש לתקנה|הן ביטויים רגולריים בלתי תקינים ויש לתקנן}} לפני שמירת הדף:',
	'spam-blacklist-desc' => 'כלי נגד זבל מבוסס ביטויים רגולריים ליצירת רשימה שחורה של URL־ים בדפים וכתובות דוא"ל למשתמשים רשומים',
	'log-name-spamblacklist' => 'יומן רשימה שחורה של ספאם',
	'log-description-spamblacklist' => 'האירועים האלה עוקבים אחרי הפעלות של רשימה שחורה של ספאם.',
	'logentry-spamblacklist-hit' => '$1 {{GENDER:$1|גרם|גרמה}} לפעולת רשימה שחורה בדף $3 תוך כדי ניסיון להוסיף את הכתובת $4.',
	'right-spamblacklistlog' => 'תצוגת יומן רשימה שחורה של ספאם',
	'action-spamblacklistlog' => 'תצוגת יומן רשימה שחורה של ספאם',
);

/** Hindi (हिन्दी)
 * @author Kaustubh
 * @author Shyam
 */
$messages['hi'] = array(
	'spam-blacklist' => ' #इस सूची में मौजूद कडियाँ जब एक पृष्ठ में जोड़ी गई बाहरी URLs से मेल खाती है तब वह पृष्ठ संपादन से बाधित हो जायेगा।
 #यह सूची केवल इस विकी पर ही प्रभावी है, विश्वव्यापी ब्लैकलिस्ट को भी उद्धृत करें।
 #प्रलेखन के लिए https://www.mediawiki.org/wiki/Extension:SpamBlacklist देखें
 #<!-- इस पंक्तीं को ऐसे के ऐसे ही रहने दें --> <pre>
#
#वाक्य विश्लेषण निम्नांकित है:
#  * हर जगह "#" संकेत से लेकर पंक्ति के अंत तक एक ही टिपण्णी है
#  * प्रत्येक अरिक्त पंक्ति एक टुकडा है जो कि URLs के अंतर्गत केवल आयोजकों से मेल खाता है

 #</pre> <!-- इस पंक्तीं को ऐसे के ऐसे ही रहने दें -->',
	'spam-whitelist' => ' #<!-- इस पंक्तीं को ऐसे के ऐसे ही रहने दें --> <pre>
# बाहरी कडियाँ जो इस सूची से मेल खाती है, वह कभी भी बाधित *नहीं* होंगी
# ब्लैकलिस्ट प्रवेशिका द्वारा बाधित कि गई हैं।
#
# वाक्य विश्लेषण निम्नांकित है:
#  * हर जगह "#" संकेत से लेकर पंक्ति के अंत तक एक ही टिपण्णी है
#  * प्रत्येक अरिक्त पंक्ति एक टुकडा है जो कि URLs के अंतर्गत केवल आयोजकों से मेल खाता है

 #</pre> <!-- इस पंक्तीं को ऐसे के ऐसे ही रहने दें -->',
	'spam-invalid-lines' => 'निम्नांकित अवांछित ब्लैकलिस्ट {{PLURAL:$1|पंक्ति|पंक्तियाँ}} अमान्य नियमित {{PLURAL:$1|अभिव्यक्ति है|अभिव्यक्तियाँ हैं}} और पृष्ठ को जमा कराने से पहले ठीक करना चाहिए:',
	'spam-blacklist-desc' => 'रेजएक्स पर आधारित स्पॅम रोकनेवाला उपकरण:[[MediaWiki:Spam-blacklist]] और [[MediaWiki:Spam-whitelist]]', # Fuzzy
);

/** Croatian (hrvatski)
 * @author Dnik
 * @author Roberta F.
 * @author SpeedyGonsales
 */
$messages['hr'] = array(
	'spam-blacklist' => ' # Vanjske URLovi koji budu pronađeni pomoću ovog popisa nije moguće snimiti na stranicu wikija.
 # Ovaj popis utječe samo na ovaj wiki; provjerite globalnu "crnu listu".
 # Za dokumentaciju pogledajte https://www.mediawiki.org/wiki/Extension:SpamBlacklist
 #<!-- leave this line exactly as it is --> <pre>
#
# Rabi se sljedeća sintaksa:
#   * Sve poslije "#" znaka do kraja linije je komentar
#   * svaki redak koji nije prazan dio je regularnog izraza (\'\'regex fragment\'\') koji odgovara imenu poslužitelja u URL-u

 #</pre> <!-- leave this line exactly as it is -->',
	'spam-whitelist' => ' #<!-- leave this line exactly as it is --> <pre>
# Vanjski URLovi koji budu pronađeni pomoću ovog popisa nisu blokirani
# čak iako se nalaze na "crnom popisu".
#
# Rabi se slijedeća sintaksa:
#   * Sve poslije "#" znaka do kraja linije je komentar
#   * svaki neprazni redak je dio regularnog izraza (\'\'regex fragment\'\') koji odgovara imenu poslužitelja u URL-u

 #</pre> <!-- leave this line exactly as it is -->',
	'spam-invalid-lines' => '{{PLURAL:$1|Slijedeći redak|Slijedeći redovi|Slijedeći redovi}} "crnog popisa" spama {{PLURAL:$1|je|su}} nevaljani {{PLURAL:$1|regularan izraz|regularni izrazi|regularni izrazi}} i {{PLURAL:$1|mora|moraju|moraju}} biti ispravljeni prije snimanja ove stranice:',
	'spam-blacklist-desc' => 'Anti-spam alat zasnovan na reg. izrazima: [[MediaWiki:Spam-blacklist]] i [[MediaWiki:Spam-whitelist]]', # Fuzzy
);

/** Upper Sorbian (hornjoserbsce)
 * @author Michawiki
 */
$messages['hsb'] = array(
	'spam-blacklist' => ' # Eksterne URL, kotrež su w lisćinje wobsahowane, blokuja składowanje strony.
 # Tuta lisćina nastupa jenož tutón Wiki; hlej tež globalnu čornu lisćinu.
 # Za dokumentaciju hlej https://www.mediawiki.org/wiki/Extension:SpamBlacklist
 #<!-- Tuta linka njesmě so změnić! --> <pre>
#
# Syntaksa:
#   * Wšitko wot znamjenja "#" hač ke kóncej linki je komentar
#   * Kóžda njeprózdna linka je regularny wuraz, kotryž so přećiwo mjenu hosta w URL pruwuje.

 #</pre> <!-- Tuta linka njesmě so změnić! -->',
	'spam-whitelist' => ' #<!-- Tuta linka njesmě so změnić! --> <pre>
# Eksterne URL, kotrež su w tutej lisćinje wobsahowane, njeblokuja składowanje strony, byrnjež
# w globalnej abo lokalnej čornej lisćinje wobsahowane byli.
#
# Syntaksa:
#   * Wšitko wot znamjenja "#" hač ke kóncej linki je komentar
#   * Kóžda njeprózdna linka je regularny wuraz, kotryž so přećiwo mjenu hosta w URL pruwuje.

 #</pre> <!-- Tuta linka njesmě so změnić! -->',
	'email-blacklist' => '# E-mejlowe adresy, kotrež su w lisćinje wobsahowane, blokuja registrowanje a słanje e-mejlkow.
 # Tuta lisćina nastupa jenož tutón Wiki; hlej tež globalnu čornu lisćinu.
 # Za dokumentaciju hlej https://www.mediawiki.org/wiki/Extension:SpamBlacklist
 #<!-- Tuta linka njesmě so změnić! --> <pre>
#
# Syntaksa:
#   * Wšitko wot znamjenja "#" hač ke kóncej linki je komentar
#   * Kóžda njeprózdna linka je regularny wuraz, kotryž so přećiwo mjenu hosta w e-mejlach pruwuje.

 #</pre> <!-- Tuta linka njesmě so změnić! -->',
	'email-whitelist' => '#<!-- Tuta linka njesmě so změnić! --> <pre>
# E-mejlowe adresy, kotrež su w tutej lisćinje, *nje*blokuja so, byrnjež so 
# přez zapiski čornje lisćiny blokowali.
#
 #</pre> <!-- Tuta linka njesmě so změnić! -->
# Syntaksa je slědowaca:
# * Wšitko wot znamješka "#" ke kóncej linki je komentar
# * Kóžda njeprózdna linka je regularny wuraz, kotryž jenož hostam znutřka e-mejlow wotpowěduje',
	'spam-blacklisted-email' => 'E-mejlowe adresy w čornej lisćinje',
	'spam-blacklisted-email-text' => 'Twoja e-mejlowa adresa je tuchwilu w čornej lisćinje a tohodla za słanje e-mejlow do druhich wužiwarjow zablokowana.',
	'spam-blacklisted-email-signup' => 'Podata e-mejlowa adresa je tuchwilu přećiwo wužiwanju zablokowana.',
	'spam-invalid-lines' => '{{PLURAL:$1|slědowaca linka je njepłaćiwy regularny wuraz|slědowacych linkow je regularny wuraz|slědowace linki su regularne wurazy|slědowacej lince stej regularnej wurazaj}} a {{PLURAL:$1|dyrbi|dyrbi|dyrbja|dyrbjetej}} so korigować, prjedy hač so strona składuje:',
	'spam-blacklist-desc' => 'Přećiwospamowy nastroj na zakładźe Regex: [[MediaWiki:Spam-blacklist]] a [[MediaWiki:Spam-whitelist]]', # Fuzzy
);

/** Hungarian (magyar)
 * @author Dani
 * @author Dj
 * @author TK-999
 */
$messages['hu'] = array(
	'spam-blacklist' => ' # A lista elemeire illeszkedő külső hivatkozások blokkolva lesznek
 # A lista csak erre a wikire vonatkozik; a globális feketelistába is tedd bele.
 # Dokumentációhoz lásd a https://www.mediawiki.org/wiki/Extension:SpamBlacklist oldalt (angolul)
 #<!-- ezen a soron ne változtass --> <pre>
#
# A szintaktika a következő:
#  * Minden a „#” karaktertől a sor végéig megjegyzésnek számít
#  * Minden nem üres sor egy reguláris kifejezés darabja, amely csak az URL-ekben található kiszolgálókra illeszkedik',
	'spam-whitelist' => ' #<!-- ezen a soron ne változtass --> <pre>
# A lista elemeire illeszkedő külső hivatkozások *nem* lesznek blokkolva, még
# akkor sem, ha illeszkedik egy feketelistás elemre.
#
# A szintaktika a következő:
#  * Minden a „#” karaktertől a sor végéig megjegyzésnek számít
#  * Minden nem üres sor egy reguláris kifejezés darabja, amely csak az URL-ekben található kiszolgálókra illeszkedik

 #</pre> <!-- ezen a soron ne változtass -->',
	'spam-blacklisted-email' => 'Feketelistás e-mail cím',
	'spam-blacklisted-email-signup' => 'A megadott email cím jelenleg feketelistán van, és nem lehet használni.',
	'spam-invalid-lines' => 'Az alábbi {{PLURAL:$1|sor hibás|sorok hibásak}} a spam elleni feketelistában; {{PLURAL:$1|javítsd|javítsd őket}} mentés előtt:',
	'spam-blacklist-desc' => 'Regex-alapú spamellenes eszköz: [[MediaWiki:Spam-blacklist]] és [[MediaWiki:Spam-whitelist]]', # Fuzzy
);

/** Interlingua (interlingua)
 * @author McDutchie
 */
$messages['ia'] = array(
	'spam-blacklist' => ' # Le adresses URL externe correspondente a iste lista es blocate de esser addite a un pagina.
 # Iste lista ha effecto solmente in iste wiki; refere te etiam al lista nigre global.
 # Pro documentation vide https://www.mediawiki.org/wiki/Extension:SpamBlacklist
 #<!-- non modificar in alcun modo iste linea --> <pre>
#
# Le syntaxe es lo sequente:
#  * Toto a partir de un character "#" usque al fin del linea es un commento
#  * Cata linea non vacue es un fragmento de regex que se applica solmente al nomines de host intra adresses URL

 #</pre> <!-- non modificar in alcun modo iste linea -->',
	'spam-whitelist' => ' #<!-- non modificar in alcun modo iste linea --> <pre>
# Le adresses URL correspondente a iste lista *non* essera blocate mesmo si illos
# haberea essite blocate per entratas in le lista nigre.
#
# Le syntaxe es lo sequente:
#  * Toto a partir de un character "#" usque al fin del linea es un commento
#  * Omne linea non vacue es un fragmento de regex que se applica solmente al nomines de host intra adresses URL

 #</pre> <!-- non modificar in alcun modo iste linea -->',
	'email-blacklist' => ' # Le adresses de e-mail correspondente a iste lista es blocate de crear contos o inviar e-mail.
 # Iste lista ha effecto solmente in iste wiki; refere te etiam al lista nigre global.
 # Pro documentation vide https://www.mediawiki.org/wiki/Extension:SpamBlacklist
 #<!-- non modificar in alcun modo iste linea --> <pre>
#
# Le syntaxe es lo sequente:
#  * Toto a partir de un character "#" usque al fin del linea es un commento
#  * Cata linea non vacue es un fragmento de regex que se applica solmente al nomines de host in adresses de e-mail

 #</pre> <!-- non modificar in alcun modo iste linea -->',
	'email-whitelist' => ' #<!-- non modificar in alcun modo iste linea --> <pre>
# Le adresses de e-mail correspondente a iste lista *non* essera blocate
# mesmo si illos haberea essite blocate per entratas de lista nigre.
#
# Le syntaxe es lo sequente:
#  * Toto a partir de un character "#" usque al fin del linea es un commento
#  * Cata linea non vacue es un fragmento de regex que se applica solmente al nomines de host in adresses de e-mail
 #</pre> <!-- non modificar in alcun modo iste linea -->',
	'spam-blacklisted-email' => 'Adresse de e-mail in lista nigre',
	'spam-blacklisted-email-text' => 'Tu adresse de e-mail es actualmente blocate de inviar messages a altere usatores.',
	'spam-blacklisted-email-signup' => 'Le adresse de e-mail specificate es actualmente blocate per le lista nigre.',
	'spam-invalid-lines' => 'Le sequente {{PLURAL:$1|linea|lineas}} del lista nigre antispam es {{PLURAL:$1|un expression|expressiones}} regular invalide e debe esser corrigite ante que tu immagazina le pagina:',
	'spam-blacklist-desc' => 'Instrumento antispam a base de regex: [[MediaWiki:Spam-blacklist]] e [[MediaWiki:Spam-whitelist]]', # Fuzzy
);

/** Indonesian (Bahasa Indonesia)
 * @author Farras
 * @author IvanLanin
 * @author Meursault2004
 */
$messages['id'] = array(
	'spam-blacklist' => '
 # URL eksternal yang cocok dengan daftar berikut akan diblokir jika ditambahkan pada suatu halaman.
 # Daftar ini hanya berpengaruh pada wiki ini; rujuklah juga daftar hitam global.
 # Untuk dokumentasi, lihat https://www.mediawiki.org/wiki/Extension:SpamBlacklist
 #<!-- biarkan baris ini seperti adanya --> <pre>
#
# Sintaksnya adalah sebagai berikut:
#   * Semua yang diawali dengan karakter "#" hingga akhir baris adalah komentar
#   * Semua baris yang tidak kosong adalah fragmen regex yang hanya akan dicocokkan dengan nama host di dalam URL

 #</pre> <!-- biarkan baris ini seperti adanya -->',
	'spam-whitelist' => ' #<!-- biarkan baris ini seperti adanya --> <pre>
 # URL eksternal yang cocok dengan daftar berikut *tidak* akan diblokir walaupun
# pasti akan diblokir oleh entri pada daftar hitam
#
# Sintaksnya adalah sebagai berikut:
#   * Semua yang diawali dengan karakter "#" hingga akhir baris adalah komentar
#   * Semua baris yang tidak kosong adalah fragmen regex yang hanya akan dicocokkan dengan nama host di dalam URL

 #</pre> <!-- biarkan baris ini seperti adanya -->',
	'spam-blacklisted-email' => 'Alamat surel yang masuk daftar hitam',
	'spam-blacklisted-email-signup' => 'Alamat surel yang dimasukkan saat ini sedang tidak boleh digunakan.',
	'spam-invalid-lines' => '{{PLURAL:$1|Baris|Baris-baris}} daftar hitam spam berikut adalah {{PLURAL:$1|ekspresi|ekspresi}} regular yang tak valid dan {{PLURAL:$1|perlu|perlu}} dikoreksi sebelum disimpan:',
	'spam-blacklist-desc' => 'Perkakas anti-spam berbasis regex: [[MediaWiki:Spam-blacklist]] dan [[MediaWiki:Spam-whitelist]]', # Fuzzy
);

/** Iloko (Ilokano)
 * @author Lam-ang
 */
$messages['ilo'] = array(
	'spam-blacklist' => ' # Dagiti akinruar a URL a maipada iti daytoy a listaan ket maserraan to no mainayon ditoy a panid.
 # Daytoy a listaan ket apektaranna laeng daytoy a wiki; kitaen pay ti sangalubongan a naiparit.
 # Para iti dokumentasion kitaen ti https://www.mediawiki.org/wiki/Extension:SpamBlacklist
 #<!-- baybayan daytoy a linia --> <pre>
#
# Ti gramatika ket kasla dagiti sumaganad:
#   * Amin manipud iti "#" a karakter iti gibus ti linia ket komentario
#   * Amin a saan a blanko a linia ket regex a pedaso a maipada laeng ti nagsangaili ti uneg dagiti URL

 #</pre> <!-- baybayan daytoy a linia -->',
	'spam-whitelist' => ' #<!-- baybayan daytoy a linia --> <pre>
# Dagiti akinruar a panilpo a maipada iti daytoy a listaan ket *saan* a maserraan urayno
# naseraanen babaen ti naikabil kadagiti panagiparitan a listaan.
#
 #</pre> <!-- baybayan daytoy a linia -->',
	'email-blacklist' => ' # Dagiti e-surat a pagtaengan a maipada iti daytoy a listaan ket maseraanto manipud ti panagrehistro wenno panagitulod kadagiti e-surat
 # Daytoy a listaan ket apektarannna laeng daytoy a wiki; mangiturong pay ti sangalubongan a naiparit.
 # Para iti dokumentasion kitaen ti https://www.mediawiki.org/wiki/Extension:SpamBlacklist
 #<!-- baybayan daytoy a linia --> <pre>
#
# Ti gramatika ket kasla dagiti sumaganad:
#   * Amin manipud iti "#" a karakter iti gibus ti linia ket komentario
#   * Amin ti saan a blanko a linia ket regex a pedaso a maipada laeng ti nagsangaili iti uneg dagiti e-surat a pagtaengan

 #</pre> <!--baybayan daytoy a linia-->',
	'email-whitelist' => ' #<!-- baybayan daytoy a linia --> <pre>
# Dagiti e-surat a pagtaengan a maipada iti daytoy a listaan ket *saant* a maserraan urayno 
# naserraanda babaen dagiti naikabil a naiparit.
 #</pre> <!-- baybayan daytoy a linia -->
# Ti gramatika ket kasla dagiti sumaganad:
#   * Amin manipud ti "#" a karakter aginggana ti gibus iti linia ket maysa a komentario
#   * Amin a saan a blanko a linia ket regex a pedaso a mangipada laeng ti nagsangaili ti uneg dagiti e-surat a pagtaengan',
	'spam-blacklisted-email' => 'Dagiti naiparit nga e-surat a pagtaengan',
	'spam-blacklisted-email-text' => 'Ti e-suratmo a pagtaengan ket agdama a naiparit manipud ti panagipatulod kadagiti e-surat kadagiti sabsabali nga agar-aramat.',
	'spam-blacklisted-email-signup' => 'Ti naited nga e-surat a pagatengan ket agdama a naiparit manipud ti panagusar.',
	'spam-invalid-lines' => 'Ti sumaganad a spam blacklist {{PLURAL:$1| a linia ket|kadagiti linia ket}} imbalido a kadawyan {{PLURAL:$1|a nangisao|kadagiti panangisao}} ken {{PLURAL:$1|masapsapol|masapol}} a mapudnuan sakbay nga idulin ti panid:',
	'spam-blacklist-desc' => 'Naibantay ti regex kontra-spam a ramit: [[MediaWiki:Spam-blacklist]] ken [[MediaWiki:Spam-whitelist]]', # Fuzzy
);

/** Icelandic (íslenska)
 * @author Snævar
 */
$messages['is'] = array(
	'spam-blacklist' => ' # Ytri tenglar sem passa við þennan lista er ekki hægt að bæta við á síður.
 # Þessi bannlisti hefur aðeins áhrif á þennan wiki. 
 # Einnig er til altækur bannlisti sem hefur áhrif á öll wiki verkefni Wikimedia. Hann er að finna á http://meta.wikimedia.org/wiki/Spam_blacklist
 # Leiðbeiningar eru á https://www.mediawiki.org/wiki/Extension:SpamBlacklist
 #<!-- ekki breyta þessari línu --> <pre>
#
# Málskipan listans er eftirfarandi:
#   * Allar línur sem byrja á "#" eru athugasemdir
#   * Allar síður sem eru ekki tómar eru reglulegar segðir sem verða aðeins bornar saman við vefsvæði tengilsins

 #</pre> <!-- ekki breyta þessari línu -->',
	'spam-whitelist' => ' #<!-- ekki breyta þessari línu --> <pre>
# Ytri tenglar sem passa við þennan lista verður *hægt* að bæta við á síður, þrátt fyrir að
# þeir séu á bannlistanum.
#
 #</pre> <!-- ekki breyta þessari línu -->',
	'email-blacklist' => ' # Netföng á þessum lista verður ekki hægt að nota til þess að skrá notenda eða senda tölvupost á notendur
 # Þessi bannlisti hefur eingöngu áhrif á þennan wiki, en einning er til altækur bannlisti sem hefur áhrif á öll wiki verkefni Wikimedia.
 # Leiðbeiningar eru á https://www.mediawiki.org/wiki/Extension:SpamBlacklist
 #<!-- ekki breyta þessari línu --> <pre>
#
# Málskipan listans er eftirfarandi:
#  * Allar línur sem byrja á "#" eru athugasemdir
#  * Allar síður sem eru ekki tómar eru reglulegar segðir sem verða aðeins bornar saman við vefsvæði netfangsins

 #</pre> <!-- ekki breyta þessari línu -->',
	'email-whitelist' => ' #<!-- leave this line exactly as it is --> <pre>
# Netföng sem passa við þennan lista verður *hægt* að bæta við á síður, þrátt fyrir að
# þau séu á bannlistanum.
#
 #</pre> <!-- leave this line exactly as it is -->
# Málskipan listans er eftirfarandi:
#  * Allar línur sem byrja á "#" eru athugasemdir
#  * Allar síður sem eru ekki tómar eru reglulegar segðir sem verða aðeins bornar saman við vefsvæði netfangsins',
	'spam-blacklisted-email' => 'Netfangið er á bannlista',
	'spam-blacklisted-email-text' => 'Netfangið þitt er skráð á bannlista og ekki er hægt að senda tölfupóst frá því til annara notenda.',
	'spam-blacklisted-email-signup' => 'Netfangið sem þú tilgreindir er á bannlista og er ekki hægt að nota.',
	'spam-invalid-lines' => 'Eftirfarandi bannlista {{PLURAL:$1|færsla er ógild regluleg segð|færslur eru ógildar reglulegar segðir}} og leiðrétta þarf {{PLURAL:$1|hana|þær}} áður en síðan er vistuð:',
	'spam-blacklist-desc' => 'Kæfuvörn byggð á reglulegum segðum: [[MediaWiki:Spam-blacklist]] og [[MediaWiki:Spam-whitelist]]', # Fuzzy
);

/** Italian (italiano)
 * @author Beta16
 * @author BrokenArrow
 * @author Ximo17
 */
$messages['it'] = array(
	'spam-blacklist' => ' #<!-- non modificare in alcun modo questa riga --> <pre>
# Le URL esterne al sito che corrispondono alla lista seguente verranno bloccate.
# La lista è valida solo per questo sito; fare riferimento anche alla blacklist globale.
# Per la documentazione si veda https://www.mediawiki.org/wiki/Extension:SpamBlacklist
#
# La sintassi è la seguente:
#  * Tutto ciò che segue un carattere "#" è un commento, fino al termine della riga
#  * Tutte le righe non vuote sono frammenti di espressioni regolari che si applicano al solo nome dell\'host nelle URL
 #</pre> <!-- non modificare in alcun modo questa riga -->',
	'spam-whitelist' => ' #<!-- non modificare in alcun modo questa riga --> <pre>
# Le URL esterne al sito che corrispondono alla lista seguente *non* verranno
# bloccate, anche nel caso corrispondano a delle voci della blacklist
#
# La sintassi è la seguente:
#  * Tutto ciò che segue un carattere "#" è un commento, fino al termine della riga
#  * Tutte le righe non vuote sono frammenti di espressioni regolari che si applicano al solo nome dell\'host nelle URL
 #</pre> <!-- non modificare in alcun modo questa riga -->',
	'email-blacklist' => ' #<!-- non modificare in alcun modo questa riga --> <pre>
# Gli indirizzi email che corrispondono alla lista seguente saranno bloccati, non sarà possibile salvare o inviare email.
# La lista è valida solo per questo sito; fare riferimento anche alla blacklist globale.
# Per la documentazione si veda https://www.mediawiki.org/wiki/Extension:SpamBlacklist
#
# La sintassi è la seguente:
#  * Tutto ciò che segue un carattere "#" è un commento, fino al termine della riga
#  * Tutte le righe non vuote sono frammenti di espressioni regolari che si applicano al solo nome dell\'host degli indirizzi email
 #</pre> <!-- non modificare in alcun modo questa riga -->',
	'email-whitelist' => ' #<!-- non modificare in alcun modo questa riga --> <pre>
# Gli indirizzi email che corrispondono alla lista seguente *non* verranno
# bloccati, anche nel caso corrispondano a delle voci della blacklist
#
# La sintassi è la seguente:
#  * Tutto ciò che segue un carattere "#" è un commento, fino al termine della riga
#  * Tutte le righe non vuote sono frammenti di espressioni regolari che si applicano al solo nome dell\'host degli indirizzi email
 #</pre> <!-- non modificare in alcun modo questa riga -->',
	'spam-blacklisted-email' => 'Indirizzo di posta elettronica bloccato',
	'spam-blacklisted-email-text' => "Il tuo indirizzo di posta elettronica è attualmente nella lista nera per l'invio di email verso altri utenti.",
	'spam-blacklisted-email-signup' => "L'indirizzo di posta elettronica indicato è attualmente nella lista nera.",
	'spam-invalid-lines' => "{{PLURAL:$1|La seguente riga|Le seguenti righe}} della blacklist dello spam {{PLURAL:$1|non è un'espressione regolare valida|non sono espressioni regolari valide}}; si prega di correggere {{PLURAL:$1|l'errore|gli errori}} prima di salvare la pagina.",
	'spam-blacklist-desc' => 'Strumento antispam basato sulle espressioni regolari per bloccare URL e indirizzi email di utenti registrati',
	'log-name-spamblacklist' => 'Spam blacklist',
	'log-description-spamblacklist' => 'Questi eventi tengono traccia delle attivazioni della lista nera dello spam.',
	'logentry-spamblacklist-hit' => "$1 ha causato l'attivazione della spam blacklist su $3 tentando di aggiungere $4.",
	'right-spamblacklistlog' => 'Visualizza registro della spam blacklist',
	'action-spamblacklistlog' => 'vedere il registro della spam blacklist',
);

/** Japanese (日本語)
 * @author Aotake
 * @author Fryed-peach
 * @author JtFuruhata
 * @author Marine-Blue
 * @author Shirayuki
 * @author Whym
 */
$messages['ja'] = array(
	'spam-blacklist' => ' #<!-- この行は変更しないでください --> <pre>
# この一覧に掲載されている外部URLをページに追加すると編集をブロックします。
# この一覧はこのウィキでのみ有効です。グローバル ブラックリストも参照してください。
# 利用方法は https://www.mediawiki.org/wiki/Extension:SpamBlacklist/ja をご覧ください。
#
# 構文は以下の通りです:
#  * 「#」以降行末まではコメントです
#  * 空でない行は、URLに含まれるホスト名との一致を検出する正規表現です

 #</pre> <!-- この行は変更しないでください -->',
	'spam-whitelist' => ' #<!-- この行は変更しないでください --> <pre>
# この一覧に掲載されている外部URLに一致する送信元からのページ編集は、
# たとえブラックリストに掲載されていたとしても、ブロック*されません*。
#
# 構文は以下の通りです:
#  * 「#」文字から行末まではコメントとして扱われます
#  * 空でない行は、URLに含まれるホスト名との一致を検出する正規表現です

 #</pre> <!-- この行は変更しないでください -->',
	'email-blacklist' => ' #<!-- この行は変更しないでください --> <pre>
# この一覧と一致するメールアドレスはその登録とそこからのメール送信がブロックされます。
# この一覧はこのウィキでのみ有効です。グローバル ブラックリストも参照してください。
# 利用方法は https://www.mediawiki.org/wiki/Extension:SpamBlacklist/ja をご覧ください。
#
# 構文は以下の通りです:
#  * 「#」以降行末まではコメントです
#  * 空でない行は、URLに含まれるホスト名との一致を検出する正規表現です

 #</pre> <!-- この行は変更しないでください -->',
	'email-whitelist' => ' #<!-- この行は変更しないでください --> <pre>
# この一覧と一致するメールアドレスはたとえブラックリストに
# 掲載されていたとしても、ブロック*されません*。
#
# 構文は以下の通りです:
#  * 「#」文字から行末まではコメントとして扱われます
#  * 空でない行は、URLに含まれるホスト名との一致を検出する正規表現です

 #</pre> <!-- この行は変更しないでください -->',
	'spam-blacklisted-email' => '拒否リストにあるメールアドレス',
	'spam-blacklisted-email-text' => 'メールアドレスが拒否リストに入っているため、他の利用者にメールを送信できません。',
	'spam-blacklisted-email-signup' => '指定されたメールアドレスは現在拒否リストに入っており、使用できません。',
	'spam-invalid-lines' => 'このスパムブラックリストには、無効な{{PLURAL:$1|正規表現}}を含む{{PLURAL:$1|行}}があります。保存する前に問題部分を修正してください:',
	'spam-blacklist-desc' => 'ページ内の URL や登録利用者のメールアドレスをブラックリスト化できるようにする、正規表現に基づいたスパム対策ツール',
	'log-name-spamblacklist' => 'スパムブラックリスト記録',
	'log-description-spamblacklist' => 'これらのイベントはスパムブラックリストとの一致を追跡します。',
	'logentry-spamblacklist-hit' => '$1 が $3 に $4 を追加しようとした際にスパムブラックリストが発動しました。',
	'right-spamblacklistlog' => 'スパムブラックリストを閲覧',
	'action-spamblacklistlog' => 'スパムブラックリスト記録の閲覧',
);

/** Jutish (jysk)
 * @author Ælsån
 */
$messages['jut'] = array(
	'spam-blacklist-desc' => 'Regex-basærn anti-spem tø: [[MediaWiki:Spam-blacklist]] og [[MediaWiki:Spam-whitelist]]', # Fuzzy
);

/** Javanese (Basa Jawa)
 * @author Meursault2004
 * @author NoiX180
 */
$messages['jv'] = array(
	'spam-blacklist' => ' # URL eksternal sing cocog karo daftar iki bakal diblokir yèn ditambahaké ing sawijining kaca.
 # Daftar iki namung nduwé pangaruh ing wiki iki; ngrujuka uga daftar ireng global.
 # Kanggo dokumentasi, delengen https://www.mediawiki.org/wiki/Extension:SpamBlacklist
 #<!-- lirwakna baris iki apa anané --> <pre>
#
# Sintaksisé kaya mengkéné:
#  * Kabèh sing diawali mawa karakter "#" nganti tekaning akir baris iku komentar
#  * Kabèh baris sing ora kosong iku fragmèn regex sing namung bakal dicocogaké karo jeneng host sajroning URL-URL

 #</pre> <!-- lirwakna baris iki apa anané -->',
	'spam-whitelist' => ' #<!-- lirwakna baris iki apa anané --> <pre>
 # URL èksternal sing cocog karo daftar iki *ora* bakal diblokir senadyan
# bakal diblokir déning èntri ing daftar ireng
#
# Sintaksisé kaya mengkéné:
#  * Kabèh sing diawali mawa karakter "#" nganti tekaning akir baris iku komentar
#  * Kabèh baris sing ora kosong iku fragmèn regex sing namung bakal dicocogaké karo jeneng host sajroning URL-URL

 #</pre> <!-- lirwakna baris iki apa anané -->',
	'spam-blacklisted-email' => 'Alamat layang èlèktronik kalebu nèng daptar ireng',
	'spam-blacklisted-email-text' => 'Alamat layang èlèktronik Sampéyan saiki didaptarirengaké saka ngirim layang èlèktronik nèng panganggi liya.',
	'spam-blacklisted-email-signup' => 'Alamat layang èlèktronik sing diawèhaké saiki ora dililakaké.',
	'spam-invalid-lines' => '{{PLURAL:$1|Baris|Baris-baris}} daftar ireng spam ing ngisor iki yaiku {{PLURAL:$1|èksprèsi|èksprèsi}} regulèr sing ora absah lan {{PLURAL:$1|perlu|perlu}} dikorèksi sadurungé disimpen:',
	'spam-blacklist-desc' => 'Piranti anti-spam adhedhasar regex: [[MediaWiki:Spam-blacklist]] lan [[MediaWiki:Spam-whitelist]]', # Fuzzy
);

/** Georgian (ქართული)
 * @author David1010
 * @author გიორგიმელა
 */
$messages['ka'] = array(
	'spam-blacklist' => '  # ამ სიის შესაბამისი გარე ბმულები აიკრძალება გვერდებში შესატანად.
  # ეს სია მოქმედებს მარტო ამ ვიკისთვის, თუმცა არსებობს ასევე საერთო შავი სია.
  # დამატებით ინფორმაცია გვერდზე https://www.mediawiki.org/wiki/Extension:SpamBlacklist
  #<!-- არ შეასწოროთ ეს ხაზი --> <pre>
#
# სინტაქსისი:
#   * ყველაფერი დაწყებული სიმბოლოთი "#" ხაზის ბოლომდე კომენტარად ითვლება
#   * ყველა არაცარიელი ხაზო აროს რეგულარული გამოთქმის ფრაგმენტი, რომელიც მხოლოდ URL-თან ერთად გამოიყენება

  #</pre> <!-- არ შეასწოროთ ეს ხაზი -->',
	'spam-whitelist' => '  #<!-- არ შეასწოროთ ეს ხაზი --> <pre>
# ის გარე ბმულები, რომლებიც ამ სიაშია შეტანილი *არ დაიბლოკება* მაშინაც კი, თუ შავ სიაში მოხვდება
#
# სინტაქსი:
#  * ყველაფერი სიმბოლ "#" иდაწყებული ბოლომდე კომენტარად ითვლება
#  * ყველა არაცარიელი ხაზი არის რეგულარული გამოთქმის ნაწილი, რომელიც მხოლოდ URL-თან ერთად გამოიყენება

  #</pre> <!--არ შეასწოროთ ეს ხაზი-->',
	'email-blacklist' => ' # ამ სიის შესაბამისი ელ.ფოსტის მისამართები დაიბლოკება რეგისტრაციისაგან, ან ელ.ფოსტის გაგზავნისაგან
 # ეს სია მოქმედებს მარტო ამ ვიკისთვის, თუმცა არსებობს ასევე საერთო შავი სია.
 # დამატებითი ინფორმაციისათვის იხილეთ https://www.mediawiki.org/wiki/Extension:SpamBlacklist
 #<!-- არ შეასწოროთ ეს ხაზი --> <pre>
#
# სინტაქსი:
#   * ყველაფერი დაწყებული სიმბოლოთი "#" ხაზის ბოლომდე კომენტარად ითვლება
#   * ყველა არაცარიელი ხაზი არის რეგულარული გამოთქმის ფრაგმენტი, რომელიც გამოიყენება, მხოლოდ ელ.ფოსტის შიდა მისამართების კვანძებთან

 #</pre> <!-- არ შეასწოროთ ეს ხაზი -->',
	'email-whitelist' => ' #<!-- ეს ხაზი არ შეცვალოთ --> <pre>
# ამ სიის შესაბამისი ელ.ფოსტის მისამართები *არ* დაიბლოკება
# იმ შემთხვევაშიც კი, თუ ისინი შავ სიაშია შეტანილი.
#
 #</pre> <!-- ეს ხაზი არ შეცვალოთ --> 
# სინტაქსი:
#   * ყველა, დაწყებული სიმბლოთი "#" და ხაზის ბოლომდე ითვლება კომენტარად
#   * ყველა არაცარიელი ხაზი წარმოადგენს რეგულარული გამოხატვის ფრაგმენტს, რომელიც გამოიყენება მხოლოდ ელ.ფოსტის მისამართების შიდა კვანძებისათვის',
	'spam-blacklisted-email' => 'შავ სიაში შეტანილი ელ.ფოსტის მისამართები',
	'spam-blacklisted-email-text' => 'ამჟამად თქვენი ელ.ფოსტის მისამართი შეტანილია შავ სიაში, ამიტომ თქვენ არ შეგიძლიათ სხვა მომხმარებლებისათვის შეტყობინებების გაგზავნა.',
	'spam-blacklisted-email-signup' => 'მითითებული ელ.ფოსტის მისამართი შეტანილია შავ სიაში და მისი გამოყენება შეუძლებელია.',
	'spam-invalid-lines' => '{{PLURAL:$1|შავი სიის შემდეგმა ხაზმა შესაძლოა შეიცავდეს არასწორი რეგულარუსლი გამოთქმა და უნდა გასწორდეს|შავი სიის შემდეგმა ხაზებმა შესაძლოა შეიცავდეს არასწორი რეგულარუსლი გამოთქმები და უნდა გასწორდეს}} შენახვამდე:',
	'spam-blacklist-desc' => 'რეგულარულ გამოთქმებზე დაფუძნებული ანტი-სპამ ინსტრუმენტი[[MediaWiki:Spam-blacklist]] და [[MediaWiki:Spam-whitelist]]', # Fuzzy
);

/** Kazakh (Arabic script) (قازاقشا (تٴوتە)‏)
 */
$messages['kk-arab'] = array(
	'spam-blacklist' => ' # وسى تىزىمگە سايكەس سىرتقى URL جايلار بەتكە ۇستەۋدەن بۇعاتتالادى.
 # بۇل ٴتىزىم تەك مىنداعى ۋىيكىيگە اسەر ەتەدى; تاعى دا عالامدىق قارا ٴتىزىمدى قاراپ شىعىڭىز.
 # قۇجاتتاما ٴۇشىن https://www.mediawiki.org/wiki/Extension:SpamBlacklist بەتىن قاراڭىز
 #<!-- بۇل جولدى بولعان جاعدايىمەن قالدىرىڭىز --> <pre>
#
# سىينتاكسىيسى كەلەسىدەي:
#  * «#» نىشانىنان باستاپ جول اياعىنا دەيىنگىلەرىنىڭ بۇكىلى ماندەمە دەپ سانالادى
#  * بوس ەمەس ٴار جول تەك URL جايلاردىڭ ىشىندەگى حوستتارعا سايكەس جۇيەلى ايتىلىمدىڭ (regex) بولىگى دەپ سانالادى

 #</pre> <!-- بۇل جولدى بولعان جاعدايىمەن قالدىرىڭىز -->',
	'spam-whitelist' => ' #<!-- بۇل جولدى بولعان جاعدايىمەن قالدىرىڭىز --> <pre>
# وسى تىزىمگە سايكەس سىرتقى URL جايلار *بۇعاتتالمايدى*,
# (قارا تىزىمدەگى جازبامەن بۇعاتتالعان بولسا دا).
#
# سىينتاكسىيسى كەلەسىدەي:
#  * «#» نىشانىنان باستاپ جول اياعىنا دەيىنگىلەرىنىڭ بۇكىلى ماندەمە دەپ سانالادى
#  * بوس ەمەس ٴار جول تەك URL جايلاردىڭ ىشىندەگى حوستتارعا سايكەس جۇيەلى ايتىلىمدىڭ (regex) بولىگى دەپ سانالادى

 #</pre> <!-- بۇل جولدى بولعان جاعدايىمەن قالدىرىڭىز -->',
	'spam-invalid-lines' => 'سپام قارا تىزىمىندەگى كەلەسى {{PLURAL:$1|جولدا|جولداردا}} جارامسىز جۇيەلى {{PLURAL:$1|ايتىلىم|ايتىلىمدار}} بار, جانە بەتتى ساقتاۋدىڭ {{PLURAL:$1|بۇنى|بۇلاردى}}  دۇرىستاۋ كەرەك.',
);

/** Kazakh (Cyrillic script) (қазақша (кирил)‎)
 * @author AlefZet
 */
$messages['kk-cyrl'] = array(
	'spam-blacklist' => ' # Осы тізімге сәйкес сыртқы URL жайлар бетке үстеуден бұғатталады.
 # Бұл тізім тек мындағы уикиге әсер етеді; тағы да ғаламдық қара тізімді қарап шығыңыз.
 # Құжаттама үшін https://www.mediawiki.org/wiki/Extension:SpamBlacklist бетін қараңыз
 #<!-- бұл жолды болған жағдайымен қалдырыңыз --> <pre>
#
# Синтаксисі келесідей:
#  * «#» нышанынан бастап жол аяғына дейінгілерінің бүкілі мәндеме деп саналады
#  * Бос емес әр жол тек URL жайлардың ішіндегі хосттарға сәйкес жүйелі айтылымдың (regex) бөлігі деп саналады

 #</pre> <!-- бұл жолды болған жағдайымен қалдырыңыз -->',
	'spam-whitelist' => ' #<!-- бұл жолды болған жағдайымен қалдырыңыз --> <pre>
# Осы тізімге сәйкес сыртқы URL жайлар *бұғатталмайды*,
# (қара тізімдегі жазбамен бұғатталған болса да).
#
# Синтаксисі келесідей:
#  * «#» нышанынан бастап жол аяғына дейінгілерінің бүкілі мәндеме деп саналады
#  * Бос емес әр жол тек URL жайлардың ішіндегі хосттарға сәйкес жүйелі айтылымдың (regex) бөлігі деп саналады

 #</pre> <!-- бұл жолды болған жағдайымен қалдырыңыз -->',
	'spam-invalid-lines' => 'Спам қара тізіміндегі келесі {{PLURAL:$1|жолда|жолдарда}} жарамсыз жүйелі {{PLURAL:$1|айтылым|айтылымдар}} бар, және бетті сақтаудың {{PLURAL:$1|бұны|бұларды}}  дұрыстау керек.',
);

/** Kazakh (Latin script) (qazaqşa (latın)‎)
 */
$messages['kk-latn'] = array(
	'spam-blacklist' => ' # Osı tizimge säýkes sırtqı URL jaýlar betke üstewden buğattaladı.
 # Bul tizim tek mındağı wïkïge äser etedi; tağı da ğalamdıq qara tizimdi qarap şığıñız.
 # Qujattama üşin https://www.mediawiki.org/wiki/Extension:SpamBlacklist betin qarañız
 #<!-- bul joldı bolğan jağdaýımen qaldırıñız --> <pre>
#
# Sïntaksïsi kelesideý:
#  * «#» nışanınan bastap jol ayağına deýingileriniñ bükili mändeme dep sanaladı
#  * Bos emes är jol tek URL jaýlardıñ işindegi xosttarğa säýkes jüýeli aýtılımdıñ (regex) böligi dep sanaladı

 #</pre> <!-- bul joldı bolğan jağdaýımen qaldırıñız -->',
	'spam-whitelist' => ' #<!-- bul joldı bolğan jağdaýımen qaldırıñız --> <pre>
# Osı tizimge säýkes sırtqı URL jaýlar *buğattalmaýdı*,
# (qara tizimdegi jazbamen buğattalğan bolsa da).
#
# Sïntaksïsi kelesideý:
#  * «#» nışanınan bastap jol ayağına deýingileriniñ bükili mändeme dep sanaladı
#  * Bos emes är jol tek URL jaýlardıñ işindegi xosttarğa säýkes jüýeli aýtılımdıñ (regex) böligi dep sanaladı

 #</pre> <!-- bul joldı bolğan jağdaýımen qaldırıñız -->',
	'spam-invalid-lines' => 'Spam qara tizimindegi kelesi {{PLURAL:$1|jolda|joldarda}} jaramsız jüýeli {{PLURAL:$1|aýtılım|aýtılımdar}} bar, jäne betti saqtawdıñ {{PLURAL:$1|bunı|bulardı}}  durıstaw kerek.',
);

/** Korean (한국어)
 * @author Albamhandae
 * @author Klutzy
 * @author Kwj2772
 * @author 아라
 */
$messages['ko'] = array(
	'spam-blacklist' => ' #<!-- 이 줄은 그대로 두십시오 --> <pre>
# 이 필터에 해당하는 URL을 문서에 넣을 경우 해당 편집의 저장을 자동으로 막습니다.
# 이 필터는 여기 위키 내에서만 적용됩니다. 광역 블랙리스트 기능이 있을 경우 해당 목록도 작동합니다.
# 설명서에 대해서는 https://www.mediawiki.org/wiki/Extension:SpamBlacklist 문서를 참고하세요
# 
# 문법은 다음과 같습니다:
#   * ""#" 문자에서 줄의 끝까지는 주석입니다
#   * 각 줄은 정규 표현식으로, URL 문장을 검사하는 데에 사용됩니다

 #</pre> <!-- 이 줄은 그대로 두십시오 -->',
	'spam-whitelist' => ' # <!-- 이 줄은 그대로 두십시오 --> <pre>
# 이 목록에 포함되는 바깥 URL은 블랙리스트에 의해 차단되어
# 있더라도 문서 편집이 제한되지 않습니다.
#
# 문법은 다음과 같습니다:
#   * "#" 문자에서 줄의 끝까지는 주석입니다
#   * 모든 줄은 URL의 호스트와 일치하는 정규 표현식의 일부분입니다
 #</pre> <!-- 이 줄은 그대로 두십시오 -->',
	'email-blacklist' => ' #<!-- 이 줄은 그대로 두십시오 --> <pre>
# 이 리스트와 일치하는 이메일 주소는 등록과 이메일 발송이 금지됩니다.
# 이 리스트는 이 위키에만 적용됩니다; 전역 블랙리스트도 함께 참조하십시오.
# 설명서에 대해서는 https://www.mediawiki.org/wiki/Extension:SpamBlacklist 를 참조하십시오
#
# 문법은 다음과 같습니다:
#   * "#" 문자에서 줄의 끝까지는 주석입니다
#   * 빈 줄이 아닌 모든 줄은 이메일 주소의 호스트만 검사하는 정규 표현식입니다

 #</pre> <!-- 이 줄은 그대로 두십시오 -->',
	'email-whitelist' => ' #<!-- 이 줄은 그대로 두십시오 --> <pre>
# 이 리스트와 일치하는 이메일 주소는 블랙리스트에 올라가 있을지라도
# 사용이 차단되지 않습니다.
#
# 문법은 다음과 같습니다:
#   * "#" 문자에서 줄의 끝까지는 주석입니다
#   * 빈 줄이 아닌 모든 줄은 이메일 주소의 호스트만 검사하는 정규 표현식입니다.

 #</pre> <!-- 이 줄은 그대로 두십시오 -->',
	'spam-blacklisted-email' => '이메일 주소가 블랙리스트됨',
	'spam-blacklisted-email-text' => '이메일 주소는 다른 사용자가 이메일을 보내지 못하도록 블랙리스트에 올라와 있습니다.',
	'spam-blacklisted-email-signup' => '입력한 이메일 주소는 사용할 수 없도록 블랙리스트되어 있습니다.',
	'spam-invalid-lines' => '스팸 블랙리스트의 다음 {{PLURAL:$1|줄}}에 잘못된 정규 {{PLURAL:$1|표현식}}이 사용되어 문서를 저장하기 전에 바르게 고쳐져{{PLURAL:$1|야 합니다}}:',
	'spam-blacklist-desc' => '정규 표현식을 사용해 문서에 있는 URL과 등록된 사용자의 이메일 주소를 블랙리스트 처리하여 스팸을 막는 도구',
	'log-name-spamblacklist' => '스팸 블랙리스트 기록',
	'log-description-spamblacklist' => '이 사건은 스팸 블랙리스트의 일치를 추적합니다.',
	'logentry-spamblacklist-hit' => '$1 사용자가 $3 문서에 $4(을)를 추가하려고 했을 때에 스팸 블랙리스트의 일치가 발생했습니다.',
	'right-spamblacklistlog' => '스팸 블랙리스트 기록 보기',
	'action-spamblacklistlog' => '스팸 블랙리스트 기록을 볼',
);

/** Colognian (Ripoarisch)
 * @author Purodha
 */
$messages['ksh'] = array(
	'spam-blacklist' => ' # URLs noh ußerhallef uß dä Leß wäde nit zojelohße, wann se einer en en Sigg erin donn well.
 # Heh di Liß eß bloß för heh dat Wiki joot. Loor Der och de jemeinsame „schwazze Leß“ aan.
 # Dokkementeet is dat op https://www.mediawiki.org/wiki/Extension:SpamBlacklist
 #<!-- Loß di Reih hee jenou esu wi se es --> <pre>
# Dä Opbou es:
# * Alles fun enem #-Zeiche bes an et Engk fun ene Reih es ene Kommentaa för de Minsche
# * Jede Reih met jet dren es e Stöck rejolähre Ußdrok, wat alleins Domains en URLs treffe kann

 #</pre> <!-- Lohß di Reih he jenou esu wi se es -->',
	'spam-whitelist' => ' #<!-- Loß di Reih hee jenou esu wi se es --> <pre>
# URLs noh ußerhallef uß dä Leß wäde dorschjelohße,
# sellefts wann se op en „schwazze Leß“ shtonn
# Dä Opbou es:
# * Alles fun enem #-Zeiche bes an et Engk fun ene Reih es ene Kommentaa för de Minsche
# * Jede Reih met jet dren es e Stöck rejolähre Ußdrok, wat alleins Domains en URLs treffe kann
 #</pre> <!-- Lohß di Reih he jenou esu wi se es -->',
	'email-blacklist' => ' # e-mail-Addräße uß dä Leß wäde nit zojelohße beim Aanmälde un beim e-mail-Verschecke.
 # Heh di Liß eß bloß för heh dat Wiki joot. Loor Der och de jemeinsame „schwazze Leß“ aan.
 # Dokkementeet is dat op https://www.mediawiki.org/wiki/Extension:SpamBlacklist
 #<!-- Loß di Reih hee jenou esu wi se es --> <pre>
# Dä Opbou es:
# * Alles fun enem #-Zeiche bes an et Engk fun ene Reih es ene Kommentaa för de Minsche
# * Jede Reih met jet dren es ene rejolähre Ußdrok, wohmet dä Name vum Rääschner en de e-mail-Addräße jeprööf wääde kann.

 #</pre> <!-- Lohß di Reih he jenou esu wi se es -->',
	'email-whitelist' => ' #<!-- Loß di Reih hee jenou esu wi se es --> <pre>
# e-mail-Addräße uß dä Leß wäde  zojelohße beim Aanmälde un beim e-mail-Verschecke,
# och wann se op en „schwazze Leß“ schtonn.
#
 #</pre> <!-- Lohß di Reih he jenou esu wi se es -->
# Dä Opbou es:
# * Alles fun enem #-Zeiche bes an et Engk fun ene Reih es ene Kommentaa för de Minsche
# * Jede Reih met jet dren es ene rejolähre Ußdrok, wohmet dä Name vum Rääschner en de e-mail-Addräße jeprööf wääde kann.',
	'spam-blacklisted-email' => 'Di <i lang="en">e-mail</i>-Addräß es op der „schwazze Lėß“',
	'spam-blacklisted-email-text' => 'Ding <i lang="en">e-mail</i>-Addräß es em Momang op dä „schwazze Lėß“ un De kanns dermet kein <i lang="en">e-mail</i> aan ander Metmaacher verschecke.',
	'spam-blacklisted-email-signup' => 'Di aanjejovve Addräß för de <i lang="en">e-mail</i> es em Momang op dä „schwazze Lėß“ un kann nit jebruch wääde.',
	'spam-invalid-lines' => 'Mer han Fähler en rejolähre Ußdröck jefonge.
{{PLURAL:$1|De Reih onge schtemmp nit un moß|Di $1 Reije onge schtemme nit un möße|Dat sull}}
för em Afschpeischere eets en Oodenong jebraat wääde:',
	'spam-blacklist-desc' => 'Met rejolähre Ußdröck jääje der <i lang="en">SPAM<i> — övver en [[MediaWiki:Spam-blacklist|„schwazze Leß“]] un en [[MediaWiki:Spam-whitelist|Leß met Ußnahme dohfun]].', # Fuzzy
);

/** Luxembourgish (Lëtzebuergesch)
 * @author Robby
 */
$messages['lb'] = array(
	'spam-blacklisted-email' => 'Gespaart Mail-Adressen',
	'spam-blacklisted-email-text' => 'Är Mailadress ass elo gespaart fir anere Benotzer Mailen ze schécken.',
	'spam-blacklisted-email-signup' => "D'Mailadress déi Dir uginn hutt ass elo gespaart fir anere Benotzer Mailen ze schécken.",
	'spam-blacklist-desc' => 'Op regulären Ausdréck (Regex) opgebauten Tool deen et erlaabt URLe vu Säiten op eng schwaarz Lëscht ze setzen an e-Mail-Adresssen vu registréierte Benotzer',
);

/** Limburgish (Limburgs)
 * @author Matthias
 * @author Ooswesthoesbes
 */
$messages['li'] = array(
	'spam-blacklist' => " # Externe URL's die voldoen aan deze lijst waere geweigerd bie 't
 # toevoege aan 'n pagina. Deze lijst haet allein invloed op deze wiki.
 # Er bestaot ouk 'n globale zwarte lijst.
 # Documentatie: https://www.mediawiki.org/wiki/Extension:SpamBlacklist
 #<!-- laot deze lien --> <pre>
#
# De syntax is as volg:
#  * Alles vanaaf 't karakter \"#\" tot 't einde van de regel is opmerking
#  * Iedere niet-lege regel is 'n fragment van 'n reguliere oetdrukking die
#    alleen van toepassing is op hosts binne URL's.

 #</pre> <!-- laot deze lien -->",
	'spam-whitelist' => " #<!-- laot deze lien --> <pre>
# Externe URL's die voldoen aan deze lijst, waere *nooit* geweigerd, al
# zoude ze geblokkeerd motte waere door regels oet de zwarte lijst.
#
# De syntaxis is es volg:
#  * Alles vanaaf 't karakter \"#\" tot 't einde van de regel is opmerking
#  * Iddere neet-lege regel is 'n fragment van 'n reguliere oetdrukking die
#    allein van toepassing is op hosts binne URL's.

 #</pre> <!-- laot deze lien -->",
	'email-blacklist' => " # E-mailadresse die voldoon aan dees lies waere geblokkeerd bie 't registrere of 't versjikke van e-mails.
 # Dees lis haet allein invlood op deze wiki. d'r Besteit ouch 'n wikiwiej zwarte lies.
 # Documentatie: https://www.mediawiki.org/wiki/Extension:SpamBlacklist
 #<!-- laot dees lien wie zie is --> <pre>
#
# De syntax is es volg:
#   * Alles vanaaf 't karakter \"#\" toet 't ènj vanne regel is 'n opmèrking
#   * Edere neet-laege regel is e fragment van 'n regulier oetdrökking die
#     allein van toepassing is op óngerbringers binne e-mailadresse.

 #</pre> <!-- laot dees lien wie zie is -->",
	'email-whitelist' => " #<!-- laot dees lien wie zie is --> <pre>
# E-mailadresse die voldoon aan dees lies, waere *noeatj* geweigerd, al
# zówwe ze geblokkeerd mótte waere door regels oete zwarte lies.
#
# De syntaxis is es volg:
#   * Alles vanaaf 't karakter \"#\" toet 't ènj vanne regel is opmèrking
#   * Edere neet-laege regel is e fragment van 'n regulier oetdrökking die
#     allein van toepassing is op óngerbringers binne e-mailadresse.

 #</pre> <!-- laot dees lien wie zie is -->",
	'spam-blacklisted-email' => 'E-mailadres oppe zwarte lies',
	'spam-blacklisted-email-text' => 'Dien e-mailadres steit momenteel oppe zwarte lies wodoor se gein e-mails nao anger gebroekers kins versjikke.',
	'spam-blacklisted-email-signup' => "'t Opgegaeve e-mailadres steit momenteel oppe zwarte lies.",
	'spam-invalid-lines' => "De volgende {{PLURAL:$1|regel|regel}} van de zwarte lies {{PLURAL:$1|is 'n|zeen}} onzjuuste reguliere {{PLURAL:$1|oetdrukking|oetdrukkinge}}  en {{PLURAL:$1|mót|mótte}} verbaeterd waere alveures de pazjena kin waere opgeslage:",
	'spam-blacklist-desc' => 'Antispamfunctionaliteit via reguliere expressies: [[MediaWiki:Spam-blacklist]] en [[MediaWiki:Spam-whitelist]]', # Fuzzy
);

/** Minangkabau (Baso Minangkabau)
 * @author Iwan Novirion
 */
$messages['min'] = array(
	'spam-blacklist-desc' => 'Pakakeh anti-spam babasis regex: [[MediaWiki:Spam-blacklist]] jo [[MediaWiki:Spam-whitelist]]', # Fuzzy
);

/** Macedonian (македонски)
 * @author Bjankuloski06
 */
$messages['mk'] = array(
	'spam-blacklist' => '# Надворешните URL адреси кои одговараат на наведеното на овој список ќе бидат блокирани кога ќе се постават на страница.
  # Овој список важи само за ова вики; погледајте ја и глобалниот црн список.
  # За документација, видете https://www.mediawiki.org/wiki/Extension:SpamBlacklist
  #<!-- leave this line exactly as it is --> <pre>
#
# Синтаксата е следнава:
#  * Сè од знакот „#“ до крајот на редот е коментар
#  * Секој ред кој не е празен е фрагмент од регуларен израз кој се совпаѓа само со домаќини во URL адреси

  #</pre> <!-- leave this line exactly as it is -->',
	'spam-whitelist' => '  #<!-- leave this line exactly as it is --> <pre>
# Надворешните URL адреси одговараат на списокот *нема* да бидат блокирани дури и во случај да
# се блокирани од ставки на црниот список.
#
# Синтаксата е следнава:
#  * Сè од знакот „#“ до крајот на редот е коментар
#  * Секој ред кој не е празен е фрагмент од регуларен израз кој се совпаѓа само со домаќини во URL адреси

  #</pre> <!-- leave this line exactly as it is -->',
	'email-blacklist' => '# На е-поштенските адреси што ќе се совпаднат со списоков *нема* ќе им биде забрането регистрирањето и испраќањето на пошта
# Списоков важи само за ова вики; погледајте го и глобалниот црн список.
# Документација ќе најдете на https://www.mediawiki.org/wiki/Extension:SpamBlacklist
#<!-- не менувајте го овој ред --> <pre>
#
# Синтаксата е следнава:
#   * Сето она што се наоѓа по знакот „#“ (па до крајот на редот) е коментар
#   * Секој непразен ред е извадок од регуларен израз кој одговара само на домаќини во е-пошта

 #</pre> <!-- не менувајте го овој ред -->',
	'email-whitelist' => '#<!-- не менувајте го овој ред --> <pre>
# Е-поштенските адреси што ќе се совпаднат со списоков *нема* да бидат блокирани, дури и 
# ако треба да се блокираат согласно записите во црниот список.
#
 #</pre> <!-- не менувајте го овој ред -->
# Синтаксата е следнава:
#  * Сето она што стои по знакот „#“ (па до крајот на редот) е коментар
#  * Секој непразен ред е извадок од регуларен израз кој одговара само на домаќини во е-пошта',
	'spam-blacklisted-email' => 'Забранета адреса',
	'spam-blacklisted-email-text' => 'На вашата адреса моментално не ѝ е дозволено да испраќа е-пошта на други корисници.',
	'spam-blacklisted-email-signup' => 'Употребата на дадената адреса е моментално забранета.',
	'spam-invalid-lines' => '{{PLURAL:$1|Следниов ред во црниот список на спам е|Следниве редови во црниот список на спам се}} {{PLURAL:$1|погрешен регуларен израз|погрешни регуларни изрази}} и {{PLURAL:$1|треба да се поправи|треба да се поправат}} пред да се зачува страницата:',
	'spam-blacklist-desc' => 'Алатка против спам на основа на регуларни изрази што овозможува забрана на URL и е-поштенски адреси за корисници',
	'log-name-spamblacklist' => 'Дневник за спам од црниот список',
	'log-description-spamblacklist' => 'Овие настани следат обиди на спам од црниот список.',
	'logentry-spamblacklist-hit' => '$1 стави спам заведен во црниот список $3 при обидот да ја додаде адресата $4.',
	'right-spamblacklistlog' => 'Преглед на дневникот за спам од црниот список',
	'action-spamblacklistlog' => 'преглед на дневникот за спам од црниот список',
);

/** Malayalam (മലയാളം)
 * @author Praveenp
 */
$messages['ml'] = array(
	'spam-blacklist' => '# ഈ ലിസ്റ്റുമായി ഒത്തുപോകുന്ന പുറത്തേയ്ക്കുള്ള യൂ.ആർ.എല്ലുകൾ താളിൽ ചേർക്കപ്പെട്ടാൽ തടയുന്നതായിരിക്കും.
  # ഈ ലിസ്റ്റ് ഈ വിക്കിയ്ക്കു മാത്രം ബാധകമായ ഒന്നാണ്; ആഗോള കരിമ്പട്ടികയും പരിശോധിക്കുക.
  # ഉപയോഗ സഹായിയ്ക്കായി https://www.mediawiki.org/wiki/Extension:SpamBlacklist കാണുക
  #<!-- ഈ വരിയിൽ മാറ്റം വരുത്തരുത് --> <pre>
#
# എഴുതേണ്ട രീതി താഴെ കൊടുക്കുന്നു:
#  * "#" ലിപിയിൽ തുടങ്ങി വരിയുടെ അവസാനം വരെയുള്ള എന്തും കുറിപ്പ് (comment) ആയി കണക്കാക്കും
#  * Every non-blank line is a regex fragment which will only match hosts inside URLs

  #</pre> <!-- ഈ വരിയിൽ മാറ്റം വരുത്തരുത് -->',
	'spam-whitelist' => '  #<!-- ഈ വരി ഇതുപോലെ തന്നെ സൂക്ഷിക്കുക --> <pre>
# കരിമ്പട്ടികയിലെ ഉൾപ്പെടുത്തലുകളുമായി ഒത്തുപോയെങ്കിൽ കൂടി,
# ഈ ലിസ്റ്റുമായി ഒത്തുപോകുന്ന പുറത്തുനിന്നുള്ള യൂ.ആർ.എല്ലുകൾ തടയപ്പെടുക *ഇല്ല*
#
# എഴുത്തുരീതി താഴെ കൊടുക്കുന്നു:
#  * "#" അക്ഷരത്തിൽ തുടങ്ങി വരിയുടെ അവസാനം വരെയുള്ളതെന്തും കുറിപ്പായി കണക്കാക്കും
#  * റെജെക്സ് ഘടകത്തിലെ ശൂന്യമല്ലാത്ത വരികൾ എല്ലാം ആന്തരിക യൂ.ആർ.എല്ലുമായി ഒത്തു നോക്കുകയുള്ളു

  #</pre> <!-- ഈ വരി ഇതുപോലെ തന്നെ സൂക്ഷിക്കുക -->',
	'email-blacklist' => ' # ഈ പട്ടികയോട് സദൃശമായ ഇമെയിൽ വിലാസങ്ങൾ രജിസ്റ്റർ ചെയ്യുന്നതും ഇമെയിലുകൾ അയയ്ക്കുന്നതും തടയപ്പെടുന്നതാണ്
 # ഈ പട്ടിക ഈ വിക്കിയിൽ മാത്രമേ പ്രാവർത്തികമാകൂ; ആഗോള കരിമ്പട്ടികയും കാണുക.
 # വിവരണത്തിനായി https://www.mediawiki.org/wiki/Extension:SpamBlacklist കാണുക
 #<!-- ഈ വരിയിൽ മാറ്റം വരുത്താൻ പാടില്ല --> <pre>
#
# എഴുത്തുരീതി താഴെക്കൊടുക്കുന്നു:
#   * "#" അക്ഷരത്തിൽ തുടങ്ങി വരിയുടെ അവസാനം വരെയുള്ളവ കുറിപ്പായിരിക്കും
#   * എല്ലാ ശൂന്യമല്ലാത്ത വരികളും ഇമെയിൽ വിലാസത്തിലെ ഹോസ്റ്റുമായി ഒത്തുനോക്കപ്പെടുന്ന രെജെക്സ് ഘടകമായിരിക്കും

 #</pre> <!-- ഈ വരിയിൽ മാറ്റം വരുത്താൻ പാടില്ല -->',
	'email-whitelist' => ' #<!-- ഈ വരിയിൽ മാറ്റം വരുത്താൻ പാടില്ല --> <pre>
# ഈ പട്ടികയോട് സദൃശമായ ഇമെയിൽ വിലാസങ്ങൾ, അവ കരിമ്പട്ടികയിലെ ഉൾപ്പെടുത്തലുകളുമായി
# സദൃശമാണെങ്കിൽ പോലും *തടയപ്പെടില്ല*.
#
 #</pre> <!-- ഈ വരിയിൽ മാറ്റം വരുത്താൻ പാടില്ല -->
# എഴുത്തുരീതി താഴെക്കൊടുക്കുന്നു:
#   * "#" അക്ഷരത്തിൽ തുടങ്ങി വരിയുടെ അവസാനം വരെയുള്ളവ കുറിപ്പായിരിക്കും
#   * എല്ലാ ശൂന്യമല്ലാത്ത വരികളും ഇമെയിൽ വിലാസത്തിലെ ഹോസ്റ്റുമായി ഒത്തുനോക്കപ്പെടുന്ന രെജെക്സ് ഘടകമായിരിക്കും',
	'spam-blacklisted-email' => 'കരിമ്പട്ടികയിൽ പെട്ട ഇമെയിൽ',
	'spam-blacklisted-email-text' => 'താങ്കളുടെ ഇമെയിൽ വിലാസം ഇപ്പോൾ മറ്റുള്ളവർക്ക് എഴുത്തയക്കാനാവാത്ത കരിമ്പട്ടികയിൽ ഉൾപ്പെട്ടിരിക്കുന്നു.',
	'spam-blacklisted-email-signup' => 'നൽകിയ ഇമെയിൽ വിലാസം ഇപ്പോൾ കരിമ്പട്ടികയിൽ പെട്ടിരിക്കുന്ന ഒന്നാണ്.',
	'spam-invalid-lines' => 'താഴെ കൊടുത്തിരിക്കുന്ന പാഴെഴുത്ത് കരിമ്പട്ടികയിലെ {{PLURAL:$1|വരി ഒരു|വരികൾ}} അസാധുവായ റെഗുലർ {{PLURAL:$1|എക്സ്‌‌പ്രെഷൻ|എക്സ്‌‌പ്രെഷനുകൾ}} ആണ്, താൾ സേവ് ചെയ്യുന്നതിനു മുമ്പ് {{PLURAL:$1|അത്|അവ}} ശരിയാക്കേണ്ടതുണ്ട്:',
	'spam-blacklist-desc' => 'റെജെക്സ്-അധിഷ്ഠിത പാഴെഴുത്ത് തടയൽ ഉപകരണം: [[MediaWiki:Spam-blacklist]] ഒപ്പം [[MediaWiki:Spam-whitelist]]', # Fuzzy
);

/** Marathi (मराठी)
 * @author Hiteshgotarane
 * @author Kaustubh
 * @author Rahuldeshmukh101
 */
$messages['mr'] = array(
	'spam-blacklist' => ' # या यादीशी जुळणारे बाह्य दुवे एखाद्या पानावर दिल्यास ब्लॉक केले जातील.
 # ही यादी फक्त या विकिसाठी आहे, सर्व विकिंसाठीची यादी सुद्धा तपासा.
 # अधिक माहिती साठी पहा https://www.mediawiki.org/wiki/Extension:SpamBlacklist
 #<!-- leave this line exactly as it is --> <pre>
#
# रुपरेषा खालीलप्रमाणे:
#  * "#" ने सुरु होणारी ओळ शेरा आहे
#  * प्रत्येक रिकामी नसलेली ओळ अंतर्गत URL जुळविणारी regex फ्रॅगमेंट आहे

 #</pre> <!-- leave this line exactly as it is -->',
	'spam-whitelist' => ' # या यादीशी जुळणारे बाह्य दुवे एखाद्या पानावर दिल्यास ब्लॉक केले *जाणार नाहीत*.
 # ही यादी फक्त या विकिसाठी आहे, सर्व विकिंसाठीची यादी सुद्धा तपासा.
 # अधिक माहिती साठी पहा http://www.mediawiki.org/wiki/Extension:SpamBlacklist
 #<!-- leave this line exactly as it is --> <pre>
#
# रुपरेषा खालीलप्रमाणे:
#  * "#" ने सुरु होणारी ओळ शेरा आहे
#  * प्रत्येक रिकामी नसलेली ओळ अंतर्गत URL जुळविणारी regex फ्रॅगमेंट आहे

 #</pre> <!-- leave this line exactly as it is -->',
	'spam-blacklisted-email' => 'प्रतिबंधित  विपत्र पत्ता',
	'spam-blacklisted-email-text' => 'तुमचा ई-पत्ता काळ्या यादीत समाविष्ट करण्यात आला आहे. इतर सदस्यांना संपर्क करणे शक्य नाही.',
	'spam-blacklisted-email-signup' => 'दिलेला विपत्र पत्ता सद्य वापरण्यास प्रतिबंधित केलेला आहे',
	'spam-invalid-lines' => 'हे पान जतन करण्यापूर्वी खालील {{PLURAL:$1|ओळ जी चुकीची|ओळी ज्या चुकीच्या}} एक्स्प्रेशन {{PLURAL:$1|आहे|आहेत}}, दुरुस्त करणे गरजेचे आहे:',
	'spam-blacklist-desc' => 'रेजएक्स वर चालणारे स्पॅम थांबविणारे उपकरण: [[MediaWiki:Spam-blacklist]] व [[MediaWiki:Spam-whitelist]]', # Fuzzy
);

/** Malay (Bahasa Melayu)
 * @author Anakmalaysia
 * @author Aviator
 */
$messages['ms'] = array(
	'spam-blacklist' => '# URL luar yang sepadan dengan mana-mana entri dalam senarai ini akan disekat daripada ditambah ke dalam sesebuah laman.
# Senarai ini melibatkan wiki ini sahaja; sila rujuk juga senarai hitam sejagat. 
# Sila baca dokumentasi di https://www.mediawiki.org/wiki/Extension:SpamBlacklist
#<!-- jangan ubah baris ini --> <pre>
#
# Sintaks adalah seperti berikut:
#  * Semuanya mulai aksara "#" hingga akhir baris merupakan komen
#  * Setiap baris yang tidak kosong meruakan pecahan ungkapan nalar yang hanya akan berpadan dengan hos-hos dalam alamat e-mel

 #</pre> <!-- jangan ubah baris ini -->',
	'spam-whitelist' => ' #<!-- jangan ubah baris ini --> <pre>
# URL luar yang sepadan dengan mana-mana entri dalam senarai ini tidak akan
# disekat walaupun terdapat juga dalam senarai hitam.
#
# Sintaks:
#  * Aksara "#" sampai akhir baris diabaikan
#  * Ungkapan nalar dibaca daripada setiap baris dan dipadankan dengan nama hos sahaja

 #</pre> <!-- jangan ubah baris ini -->',
	'email-blacklist' => ' # Alamat-alamat e-mel yang berpadanan dengan senarai ini akan disekat daripada mendaftar atau menghantar e-mel
 # Senarai ini melibatkan wiki ini sahaja; sila rujuk juga senarai hitam sejagat.
 # Untuk dokumentasi, rujuk https://www.mediawiki.org/wiki/Extension:SpamBlacklist
 #<!-- jangan ubah baris ini --> <pre>
#
# Sintaks adalah seperti berikut:
#   * Semuanya mulai aksara "#" hingga akhir baris merupakan komen
#   * Setiap baris yang tidak kosong meruakan pecahan ungkapan nalar yang hanya akan berpadan dengan hos-hos dalam alamat e-mel

 #</pre> <!-- jangan ubah baris ini -->',
	'email-whitelist' => ' #<!-- jangan ubah baris ini --> <pre>
# Alamat-alamat e-mel yang berpadanan dengan senarai ini *tidak* akan disekat sungguhpun boleh
# disekat oleh entri senarai hitam.
#
 #</pre> <!-- jangan ubah baris ini -->
# Sintaks adalah seperti berikut:
#   * Segalanya mulai aksara "#" hingga akhir baris ialah komen
#   * Setiap baris yang tidak kosong meruakan pecahan ungkapan nalar yang hanya akan berpadan dengan hos-hos dalam alamat e-mel',
	'spam-blacklisted-email' => 'E-mel yang Disenaraihitamkan',
	'spam-blacklisted-email-text' => 'Alamat e-mel anda kini disenaraihitamkan daripada menghantar e-mel kepada pengguna lain.',
	'spam-blacklisted-email-signup' => 'Alamat e-mel yang diberikan ini kini disenaraihitamkan.',
	'spam-invalid-lines' => '{{PLURAL:$1|Baris|Baris-baris}} berikut menggunakan ungkapan nalar yang tidak sah. Sila baiki senarai hitam ini sebelum menyimpannya:',
	'spam-blacklist-desc' => 'Alat anti-spam berdasarkan ungkapan nalar: [[MediaWiki:Spam-blacklist]] dan [[MediaWiki:Spam-whitelist]]', # Fuzzy
);

/** Norwegian Bokmål (norsk bokmål)
 */
$messages['nb'] = array(
	'spam-blacklist' => ' # Eksterne URL-er som finnes på denne lista vil ikke kunne legges til på en side.
 # Denne listen gjelder kun denne wikien; se også den globale svartelistinga.
 # For dokumentasjon, se https://www.mediawiki.org/wiki/Extension:SpamBlacklist
 #<!-- La denne linja være nøyaktig som den er --> <pre>
#
# Syntaksen er som følgende:
#  * Alle linjer som begynner med «#» er kommentarer
#  * Alle ikke-blanke linjer er et regex-fragment som kun vil passe med domenenavn i URL-er

 #</pre> <!-- la denne linja være nøyaktig som den er -->',
	'spam-whitelist' => ' #<!-- la denne linja være nøyaktig som den er --> <pre>
# Eksterne URL-er på denne lista vil *ikke* blokkeres, selv om
# de ellers ville vært blokkert av svartelista.
#
# Syntaksen er som følger:
#  * Alle linjer som begynner med «#» er kommentarer
#  * Alle ikke-blanke linjer er et regex-fragment som kun vil passe med domenenavn i URL-er

 #</pre> <!-- la denne linja være nøyaktig som den er -->',
	'email-blacklist' => '# E-postadresser som matcher adresser på denne listen vil ikke kunne registrere seg eller sende e-post
# Denne listen påvirker kun denne wikien; sjekk også den globale svartelista.
# For dokumentasjon, se https://www.mediawiki.org/wiki/Extension:SpamBlacklist
#<!-- la denne linja være som den er --> <pre>
#
# Syntaksen er som følger:
# * Alt fra et «#»-tegn til sluttan av linje er kommentarer
# * Hver ikke-blank linje er et regex-fragment som kun matcher domenenavn i e-postadresser

#</pre> <!-- la denne linja være som den er -->',
	'email-whitelist' => '#<!-- la denne linja være som den er --> <pre>
# E-postadresser som matcher denne listen vil *ikke* blokkeres selv om
# de er blokkert av poster på svartelista.
#
#</pre> <!-- la denne linja være som den er -->
# Syntaksen er som følger:
# * Alt fra et «#»-tegn til slutten av linja er kommentarer
# * Hver ikke-blank linje er et regex-fragment som kun matcher domener i e-postadresser',
	'spam-blacklisted-email' => 'Svartelistede e-postadresser',
	'spam-blacklisted-email-text' => 'E-postadressen din er svartelistes, så du kan ikke sende e-post til andre brukere.',
	'spam-blacklisted-email-signup' => 'Den angitte e-postadressen er svartelistet.',
	'spam-invalid-lines' => 'Følgende {{PLURAL:$1|linje|linjer}} i spamsvartelista er {{PLURAL:$1|et ugyldig regulært uttrykk|ugyldige regulære uttrykk}} og må rettes før lagring av siden:',
	'spam-blacklist-desc' => 'Antispamverktøy basert på regulære uttrykk: [[MediaWiki:Spam-blacklist]] og [[MediaWiki:Spam-whitelist]]', # Fuzzy
);

/** Low German (Plattdüütsch)
 * @author Slomox
 */
$messages['nds'] = array(
	'spam-blacklist' => '  # URLs na buten de Websteed in disse List stoppt dat Spiekern vun de Sied.
  # Disse List gellt blot för dit Wiki; kiek ok na de globale Swartlist.
  # För mehr Infos kiek op https://www.mediawiki.org/wiki/Extension:SpamBlacklist
  #<!-- Disse Reeg dröff nich ännert warrn! --> <pre>
#
# Syntax:
#  * Allens vun dat „#“-Teken af an bet to dat Enn vun de Reeg is en Kommentar
#  * Elkeen Reeg, de nich leddig is, is en regulären Utdruck, bi den nakeken warrt, wat he op de Host-Naams in de URLs passt

  #</pre> <!-- Disse Reeg dröff nich ännert warrn! -->',
	'spam-whitelist' => '  #<!-- Disse Reeg dröff nich ännert warrn! --> <pre>
# URLs na buten de Websteed in disse List stoppt dat Spiekern vun de Sied nich, ok wenn se
# in de globale oder lokale swarte List in sünd.
#
# Syntax:
#  * Allens vun dat „#“-Teken af an bet to dat Enn vun de Reeg is en Kommentar
#  * Elkeen Reeg, de nich leddig is, is en regulären Utdruck, bi den nakeken warrt, wat he op de Host-Naams in de URLs passt

  #</pre> <!-- Disse Reeg dröff nich ännert warrn! -->',
	'spam-invalid-lines' => 'Disse {{PLURAL:$1|Reeg|Regen}} in de Spam-Swartlist {{PLURAL:$1|is en ungülligen regulären Utdruck|sünd ungüllige reguläre Utdrück}}. De {{PLURAL:$1|mutt|mööt}} utbetert warrn, ehrdat de Sied spiekert warrn kann:',
	'spam-blacklist-desc' => 'Regex-baseert Anti-Spam-Warktüüch: [[MediaWiki:Spam-blacklist]] un [[MediaWiki:Spam-whitelist]]', # Fuzzy
);

/** Dutch (Nederlands)
 * @author SPQRobin
 * @author Siebrand
 */
$messages['nl'] = array(
	'spam-blacklist' => ' # Externe URL\'s die voldoen aan deze lijst worden geweigerd bij het
 # toevoegen aan een pagina. Deze lijst heeft alleen invloed op deze wiki.
 # Er bestaat ook een globale zwarte lijst.
 # Documentatie: https://www.mediawiki.org/wiki/Extension:SpamBlacklist
 #<!-- laat deze regel zoals hij is --> <pre>
#
# De syntaxis is als volgt:
#   * Alles vanaf het karakter "#" tot het einde van de regel is opmerking
#   * Iedere niet-lege regel is een fragment van een reguliere uitdrukking die
#     alleen van toepassing is op hosts binnen URL\'s.

 #</pre> <!-- laat deze regel zoals hij is -->',
	'spam-whitelist' => ' #<!-- laat deze regel zoals hij is --> <pre>
# Externe URL\'s die voldoen aan deze lijst, worden *nooit* geweigerd, al
# zouden ze geblokkeerd moeten worden door regels uit de zwarte lijst.
#
# De syntaxis is als volgt:
#   * Alles vanaf het karakter "#" tot het einde van de regel is opmerking
#   * Iedere niet-lege regel is een fragment van een reguliere uitdrukking die
#     alleen van toepassing is op hosts binnen URL\'s.

 #</pre> <!-- laat deze regel zoals hij is -->',
	'email-blacklist' => ' # E-mailadressen die voldoen aan deze lijst worden geblokkeerd bij het registreren of het verzenden van e-mails.
 # Deze lijst heeft alleen invloed op deze wiki. Er bestaat ook een globale zwarte lijst.
 # Documentatie: https://www.mediawiki.org/wiki/Extension:SpamBlacklist
 #<!-- laat deze regel zoals hij is --> <pre>
#
# De syntaxis is als volgt:
#   * Alles vanaf het karakter "#" tot het einde van de regel is een opmerking
#   * Iedere niet-lege regel is een fragment van een reguliere uitdrukking die
#     alleen van toepassing is op hosts binnen e-mailadressen.

 #</pre> <!-- laat deze regel zoals hij is -->',
	'email-whitelist' => ' #<!-- laat deze regel zoals hij is --> <pre>
# E-mailadressen die voldoen aan deze lijst, worden *nooit* geweigerd, al
# zouden ze geblokkeerd moeten worden door regels uit de zwarte lijst.
#
# De syntaxis is als volgt:
#   * Alles vanaf het karakter "#" tot het einde van de regel is opmerking
#   * Iedere niet-lege regel is een fragment van een reguliere uitdrukking die
#     alleen van toepassing is op hosts binnen e-mailadressen.

 #</pre> <!-- laat deze regel zoals hij is -->',
	'spam-blacklisted-email' => 'E-mailadres op de zwarte lijst',
	'spam-blacklisted-email-text' => 'Uw e-mailadres staat momenteel op de zwarte lijst waardoor u geen e-mails naar andere gebruikers kunt verzenden.',
	'spam-blacklisted-email-signup' => 'Het opgegeven e-mailadres staat momenteel op de zwarte lijst.',
	'spam-invalid-lines' => 'De volgende {{PLURAL:$1|regel|regels}} van de zwarte lijst {{PLURAL:$1|is een|zijn}} onjuiste reguliere {{PLURAL:$1|expressie|expressies}}  en {{PLURAL:$1|moet|moeten}} verbeterd worden alvorens de pagina kan worden opgeslagen:',
	'spam-blacklist-desc' => "Op reguliere expressies gebaseed antispamhulpprogramma dat het mogelijk maakt URL's in pagina's te blokkeren en e-mailadressen voor geblokkeerde gebruikers",
);

/** Nederlands (informeel)‎ (Nederlands (informeel)‎)
 * @author Siebrand
 */
$messages['nl-informal'] = array(
	'spam-blacklisted-email-text' => 'Je e-mailadres staat momenteel op de zwarte lijst waardoor je geen e-mails naar andere gebruikers kunt verzenden.',
);

/** Norwegian Nynorsk (norsk nynorsk)
 * @author Frokor
 */
$messages['nn'] = array(
	'spam-blacklist' => ' # Eksterne URL-ar som finnst på denne lista vil ikkje kunne leggast til på ei side.
 # Denne lista gjeld berre denne wikien; sjå òg den globale svartelistinga.
 # For dokumentasjon, sjå https://www.mediawiki.org/wiki/Extension:SpamBlacklist
 #<!-- La denne linja vere nøyaktig som ho er --> <pre>
#
# Syntaksen er som følgjer:
#  * Alle linjer som byrjar med «#» er kommentarar
#  * Alle ikkje-blanke linjer er eit regex-fragment som berre vil passe med domenenavn i URL-ar

 #</pre> <!-- la denne linja vere nøyaktig som ho er -->',
	'spam-whitelist' => ' #<!-- la denne linja vere nøyaktig som ho er --> <pre>
# Eksterne URL-ar på denne lista vil *ikkje* blokkerast, sjølv om
# dei elles ville vorte blokkert av svartelista.
#
# Syntaksen er som følgjer:
#  * Alle linjer som byrjar med «#» er kommentarar
#  * Alle ikkje-blanke linjer er eit regex-fragment som berre vil passe med domenenamn i URL-ar

 #</pre> <!-- la denne linja vere nøyaktig som ho er -->',
	'spam-invalid-lines' => 'Følgjande {{PLURAL:$1|linje|linjer}} i spamsvartelista er {{PLURAL:$1|eit ugyldig regulært uttrykk|ugyldige regulære uttrykk}} og må rettast før lagring av sida:',
	'spam-blacklist-desc' => 'Antispamverktøy basert på regulære uttrykk: [[MediaWiki:Spam-blacklist]] og [[MediaWiki:Spam-whitelist]]', # Fuzzy
);

/** Occitan (occitan)
 * @author Cedric31
 */
$messages['oc'] = array(
	'spam-blacklist' => "# Los ligams extèrnes que fan partida d'aquesta lista seràn blocats al moment de lor insercion dins una pagina. # Aquesta lista concernís pas que Wikinews ; referissètz-vos tanben a la lista negra generala de Meta. # La documentacion se tròba a l’adreça seguenta : http://www.MediaWiki.org/wiki/Extension:SpamBlacklist # <!--Daissatz aquesta linha tala coma es --> <pre> # # La sintaxi es la seguenta # * Tot tèxte que seguís lo « # » es considerat coma un comentari. # * Tota linha pas voida es un fragment regex que concernís pas que los ligams ipertèxtes. #</pre> <!--Daissatz aquesta linha tala coma es -->",
	'spam-whitelist' => " #<!--Daissatz aquesta linha tala coma es --> <pre>
# Los ligams extèrnes que fan partida d'aquesta lista seràn blocas al moment de lor insercion dins una pagina. 
# Aquesta lista concernís pas que Wikinews ; referissetz-vos tanben a la lista negra generala de Meta. 
 # La documentacion se tròba a l’adreça seguenta : http://www.mediawiki.org/wiki/Extension:SpamBlacklist 
#
# La sintaxi es la seguenta :
# * Tot tèxte que seguís lo « # » es considerat coma un comentari.
# * Tota linha pas voida es un fragment regex que concernís pas que los ligams ipertèxtes.

 #</pre> <!--Daissatz aquesta linha tala coma es -->",
	'spam-invalid-lines' => "{{PLURAL:$1|La linha seguenta |Las linhas seguentas}} de la lista dels spams {{PLURAL:$1|es redigida|son redigidas}} d'un biais incorrècte e {{PLURAL:$1|necessita|necessitan}} las correccions necessàrias abans tot salvament de la pagina :",
	'spam-blacklist-desc' => "Aisina antispam basada sus d'expressions regularas : ''[[MediaWiki:Spam-blacklist]]'' et ''[[MediaWiki:Spam-whitelist]]''", # Fuzzy
);

/** Oriya (ଓଡ଼ିଆ)
 * @author Jnanaranjan Sahu
 * @author Psubhashish
 */
$messages['or'] = array(
	'spam-blacklist' => ' # ଏକ ଫୃଷ୍ଠାରେ ଯୋଡ଼ାଯାଉଥିବା ବାହାର URL ଏହି ତାଲିକା ସହ ମେଳ ଖାଇଲେ ତାହାକୁ ଅଟକାଇଦିଆଯିବ ।
 # ଏହି ତାଲିକା କେବଳ କେବଳ ଏହି ଉଇକିକୁ ପ୍ରଭାବିତ କରିଥାଏ; ଜଗତ ଅଟକତାଲିକା ମଧ୍ୟ ଦେଖିପାରନ୍ତି ।
 # ଦଲିଲକରଣ ନିମନ୍ତେ ଦୟାକରି https://www.mediawiki.org/wiki/Extension:SpamBlacklist ଦେଖନ୍ତୁ ।
 #<!-- ଏହି ଧାଡ଼ିଟି ଯେଉଁପରି ଅଛି ଅବିକଳ ସେହିପରି ଛାଡ଼ି ଦିଅନ୍ତୁ --> <pre>
#
# ସିଣ୍ଟାକ୍ସ:
#   * "#" ଚିହ୍ନ ଠାରୁ ଧାଡ଼ିର ଶେଷ ଯାଏଁ ଏକ ମତ
#   * ସବୁ ଅଣ-ଖାଲି ଧାଡ଼ି ଏକ regex ଖଣ୍ଡ ଯାହା କେବଳ URL ଭିତରେ ଥିବା ହୋଷ୍ଟ ସହ ମେଳନ କରିଥାଏ

 #</pre> <!-- ଏହି ଧାଡ଼ିଟି ଯେଉଁପରି ଅଛି ଅବିକଳ ସେହିପରି ଛାଡ଼ି ଦିଅନ୍ତୁ -->',
	'spam-whitelist' => ' #<!-- ଏହି ଧାଡ଼ିଟି ଯେଉଁପରି ଅଛି ଅବିକଳ ସେହିପରି ଛାଡ଼ି ଦିଅନ୍ତୁ --> <pre>
# ଯଦି ସେସବୁ ଅଟକତାଲିକାରେ ଥାଏ ତେବେ ମଧ୍ୟ
 # ଏକ ଫୃଷ୍ଠାରେ ଯୋଡ଼ାଯାଉଥିବା ବାହାର URL ଏହି ତାଲିକା ସହ ମେଳ ଖାଉଥିଲେ ତାହାକୁ ଅଟକାଇ ଦିଆଯିବ *ନାହିଁ*
#
# ସିଣ୍ଟାକ୍ସ:
#   * "#" ଚିହ୍ନ ଠାରୁ ଧାଡ଼ିର ଶେଷ ଯାଏଁ ଏକ ମତ
#   * ସବୁ ଅଣ-ଖାଲି ଧାଡ଼ି ଏକ regex ଖଣ୍ଡ ଯାହା କେବଳ URL ଭିତରେ ଥିବା ହୋଷ୍ଟ ସହ ମେଳନ କରିଥାଏ

 #</pre> <!-- ଏହି ଧାଡ଼ିଟି ଯେଉଁପରି ଅଛି ଅବିକଳ ସେହିପରି ଛାଡ଼ି ଦିଅନ୍ତୁ -->',
	'email-blacklist' => ' #<!-- ଏହି ଧାଡିଟି ଯେମିତି ଅଛି ସେମିତି ରଖନ୍ତୁ କିଛି ବଦଳାନ୍ତୁ ନାହିଁ --> <pre>
# ଏହି ତାଲିକାରେ ଥିବା ଇ-ମେଲ ଠିକଣାଗୁଡିକୁ ପଞ୍ଜୀକରଣ କିମ୍ବା ଇ-ମେଲ ପଠେଇବାରୁ ଅଟକ ରଖାଯିବ
# ଏହି ତାଲିକାଟି କେବଳ ଏହି ଉଇକିରେ କାର୍ଯ୍ୟକାରୀ ହେବ ; ଜାଗତିକ ଅଟକ ତାଲିକାକୁ ମଧ୍ୟ ଦେଖନ୍ତୁ ।
# ନଥିପତ୍ର ପାଇଁ https://www.mediawiki.org/wiki/Extension:SpamBlacklist ଦେଖନ୍ତୁ
#
# ସିନ୍ଟାକ୍ସଟି ହେଉଛି:
#   * "#"ଠାରୁ ଆରମ୍ଭ କରି ଧାଡିର ଶେଷ ପର୍ଯ୍ୟନ୍ତ ସମସ୍ତ ଲେଖାଟି ହେଉଛି ଗୋଟେ ମନ୍ତବ୍ୟ
#   * ସମସ୍ତ ଖାଲିନଥିବା ଧାଡି ହେଉଛି ଏକ ରେଜେକ୍ସ ଫ୍ରାଗମେଣ୍ଟ ଯାହାକି ଇ-ମେଲ ଠିକଣାଗୁଡିକ ଭିତରେ ଥିବା ହୋଷ୍ଟଗୁଡିକ ସହ ମିଳେଇବ ।

 #</pre> <!-- ଏହି ଧାଡିଟି ଯେମିତି ଅଛି ସେମିତି ରଖନ୍ତୁ କିଛି ବଦଳାନ୍ତୁ ନାହିଁ -->',
	'email-whitelist' => ' #<!-- ଏହି ଧାଡିଟି ଯେମିତି ଅଛି ସେମିତି ରଖନ୍ତୁ କିଛି ବଦଳାନ୍ତୁ ନାହିଁ --> <pre>
# ଏହି ତାଲିକାରେ ଥିବା ଇ-ମେଲ ଠିକଣାଗୁଡିକୁ  ଅଟକ ରଖାଯିବ *ନାହିଁ*
# ଯଦିଓ ସେଗୁଡିକ ବାସନ୍ଦ ତାଲିକାରେ ଅଟକ ରଖାଯାଇଥିବ ।
#
# ସିନ୍ଟାକ୍ସଟି ହେଉଛି:
#  * "#"ଠାରୁ ଆରମ୍ଭ କରି ଧାଡିର ଶେଷ ପର୍ଯ୍ୟନ୍ତ ସମସ୍ତ ଲେଖାଟି ହେଉଛି ଗୋଟେ ମନ୍ତବ୍ୟ
#  * ସମସ୍ତ ଖାଲିନଥିବା ଧାଡି ହେଉଛି ଏକ ରେଜେକ୍ସ ଫ୍ରାଗମେଣ୍ଟ ଯାହାକି ଇ-ମେଲ ଠିକଣାଗୁଡିକ ଭିତରେ ଥିବା ହୋଷ୍ଟଗୁଡିକ ସହ ମିଳେଇବ ।

 #</pre> <!-- ଏହି ଧାଡିଟି ଯେମିତି ଅଛି ସେମିତି ରଖନ୍ତୁ କିଛି ବଦଳାନ୍ତୁ ନାହିଁ -->',
	'spam-blacklisted-email' => 'ବନ୍ଦ କରାଯାଇଥିବା ଇ-ମେଲ ଠିକଣା',
	'spam-blacklisted-email-text' => 'ଆପଣଙ୍କ ଇ-ମେଲ ଠିକଣାଟି ଅନ୍ୟମାନଙ୍କୁ ଇ-ମେଲ ପଠାଇବାରୁ ବାସନ୍ଦ କରାଯାଇଛି ।',
	'spam-blacklisted-email-signup' => 'ଦିଆଯାଇଥିବା ଇ-ମେଲ ଠିକଣାଟି ବ୍ୟବହାରକରିବାରୁ ବାସନ୍ଦ କରାଯାଇଛି ।',
	'spam-invalid-lines' => 'ଏହି ସ୍ପାମ ଅଟକତାଲିକା {{PLURAL:$1|ଧାଡ଼ିଟି|ଧାଡ଼ିସବୁ}} ଅଚଳ ସାଧାରଣ {{PLURAL:$1|ପରିପ୍ରକାଶ|ପରିପ୍ରକାଶ}} ଓ ସାଇତିବା ଆଗରୁ  {{PLURAL:$1|ତାହାକୁ  ସୁଧାରିବା ଲୋଡ଼ା|ସେହିସବୁକୁ ସୁଧାରିବା ଲୋଡ଼ା}}:',
	'spam-blacklist-desc' => 'Regex-ଭିତ୍ତିକ ସ୍ପାମ-ବିରୋଧୀ ଉପକରଣ: [[MediaWiki:Spam-blacklist]] ଓ [[MediaWiki:Spam-whitelist]]', # Fuzzy
);

/** Polish (polski)
 * @author BeginaFelicysym
 * @author Derbeth
 * @author Sp5uhe
 */
$messages['pl'] = array(
	'spam-blacklist' => '  # Dodawanie w treści stron linków zewnętrznych pasujących do tej listy będzie blokowane.
  # Lista dotyczy wyłącznie tej wiki; istnieje też globalna czarna lista.
  # Dokumentacja znajduje się na stronie https://www.mediawiki.org/wiki/Extension:SpamBlacklist
  #<!-- zostaw tę linię dokładnie tak, jak jest --> <pre>
#
# Składnia jest następująca:
#   * Wszystko od znaku „#” do końca linii jest komentarzem
#   * Każda niepusta linia jest fragmentem wyrażenia regularnego, które będzie dopasowywane jedynie do hostów wewnątrz linków

  #</pre> <!-- zostaw tę linię dokładnie tak, jak jest -->',
	'spam-whitelist' => ' #<!-- zostaw tę linię dokładnie tak, jak jest --> <pre>
# Linki zewnętrzne pasujące do tej listy *nie będą* blokowane nawet jeśli
# zostałyby zablokowane przez czarną listę.
#
# Składnia jest następująca:
#   * Wszystko od znaku „#” do końca linii jest komentarzem
#   * Każda niepusta linia jest fragmentem wyrażenia regularnego, które będzie dopasowywane jedynie do hostów wewnątrz linków

 #</pre> <!-- zostaw tę linię dokładnie tak, jak jest -->',
	'email-blacklist' => ' # Adresy e-mail pasujące do tej listy będą blokowane przed rejestracją i wysyłaniem maili
 # Ta lista dotyczy tylko tej wiki; przejrzyj również globalną czarną listę.
 # Dokumentacja znajduje się na https://www.mediawiki.org/wiki/Extension:SpamBlacklist
 #<!-- leave this line exactly as it is --> <pre>
#
# Składnia opisana jest poniżej:
#   * Wszystko znajdujące się za znakiem "#" do końca linii jest komentarzem
#   * Każda niepusta linia jest fragmentem wyrażenia regularnego, które będzie dopasowywane do hosta z adresu e-mail

 #</pre> <!-- leave this line exactly as it is -->',
	'email-whitelist' => ' #<!-- leave this line exactly as it is --> <pre>
# Adresy e-mail pasujące do tej listy *nie* będą blokowane, nawet jeśli zostaną
# zablokowane przez wpisy z czarnej listy.
#
 #</pre> <!-- leave this line exactly as it is -->
# Składnia jest następująca:
#   * Wszystko począwszy od znaku "#" do końca linii jest komentarzem
#   * Każda niepusta linia jest fragmentem wyrażenia regularnego dopasowywanego tylko do nazw hpstów z adresów e-mail',
	'spam-blacklisted-email' => 'Niedozwolone adresy e-mail',
	'spam-blacklisted-email-text' => 'Twój adres e-mail jest obecnie umieszczony na czarnej liście i nie można z niego wysyłać wiadomości e-mail do innych użytkowników.',
	'spam-blacklisted-email-signup' => 'Podany adres e-mail jest obecnie na czarnej liście blokującej przed użyciem.',
	'spam-invalid-lines' => '{{PLURAL:$1|Następująca linia jest niepoprawnym wyrażeniem regularnym i musi być poprawiona przed zapisaniem strony:|Następujące linie są niepoprawnymi wyrażeniami regularnymi i muszą być poprawione przed zapisaniem strony:}}',
	'spam-blacklist-desc' => 'Narzędzie antyspamowe oparte o wyrażenia regularne: [[MediaWiki:Spam-blacklist|spam – lista zabronionych]] oraz [[MediaWiki:Spam-whitelist|spam – lista dozwolonych]]', # Fuzzy
);

/** Piedmontese (Piemontèis)
 * @author Borichèt
 * @author Bèrto 'd Sèra
 * @author Dragonòt
 */
$messages['pms'] = array(
	'spam-blacklist' => "# J'adrësse esterne ch'as treuva ant sta lista-sì a vniran blocà se cheidun a jë gionta ansima a na pàgina. # Sta lista a l'ha valor mach an sta wiki-sì; ch'a-j fasa arferiment ëdcò a la lista nèira global. # Për dla documentassion ch'a varda http://www.MediaWiki.org/wiki/Extension:SpamBlacklist #<!-- ch'a lassa sta riga-sì giusta 'me ch'a l'é --> <pre> # # La sintassi a l'é: # * Tut lòn ch'as anandia con na \"#\" fin a la fin dla riga as ten coma coment # * Qualsëssìa riga nen veuja a resta un tòch d'espression regolar ch'as paragon-a a ij nòm ëd servent andrinta a j'adrësse #</pre> <!-- ch'a lassa sta riga-sì giusta 'me ch'a l'é -->",
	'spam-whitelist' => "#<!-- ch'a lassa sta riga-sì giusta 'me ch'a l'é --> <pre> # J'adrësse esterne coma cole dë sta lista a vniran NEN blocà, ëdcò fin-a # s'a fusso da bloché conforma a le régole dla lista nèira. # # La sintassi a l'é: # * Tut lòn ch'as anandia con na \"#\" fin a la fin dla riga as ten coma coment # * Qualsëssìa riga nen veuja a resta un tòch d'espression regolar ch'as paragon-a a ij nòm ëd servent andrinta a j'adrësse #</pre> <!-- ch'a lassa sta riga-sì giusta 'me ch'a l'é -->",
	'email-blacklist' => "# J'adrësse ëd pòsta eletrònica ch'as treuva ant sta lista-sì a vniran blocà da registresse o mandé 'd mëssagi. 
# Sta lista a l'ha valor mach an sta wiki-sì; ch'a-j fasa arferiment ëdcò a la lista nèira global. 
# Për dla documentassion ch'a varda http://www.mediawiki.org/wiki/Extension:SpamBlacklist 
#<!-- ch'a lassa sta riga-sì giusta 'me ch'a l'é --> <pre> 
# 
# La sintassi a l'é: 
# * Tut lòn ch'as anandia con na \"#\" fin a la fin dla riga as ten coma coment # 
* Qualsëssìa riga nen veujda a resta un tòch d'espression regolar ch'as paragon-a ai nòm dij servent andrinta a j'adrësse 

#</pre> <!-- ch'a lassa sta riga-sì giusta 'me ch'a l'é -->",
	'email-whitelist' => "#<!-- ch'a lassa sta riga-sì giusta 'me ch'a l'é --> <pre> 
# J'adrësse ëd pòsta eletrònica ch'as treuvo ant sta lista-sì a saran *pa* blocà bele ch'a sarìo
# da bloché për le vos ëd la lista nèira.
# 
#</pre> <!-- ch'a lassa sta riga-sì giusta 'me ch'a l'é -->
# La sintassi a l'é: 
# * Tut lòn ch'as anandia con un «#» fin a la fin dla riga as ten coma coment 
# * Qualsëssìa riga nen veujda a resta un tòch d'espression regolar ch'as paragon-a ai nòm dij servent andrinta a j'adrësse",
	'spam-blacklisted-email' => 'Adrëssa ëd pòsta eletrònica an lista nèira',
	'spam-blacklisted-email-text' => "Soa adrëssa ëd pòsta eletrònica a l'é al moment an na lista nèira për mandé dij mëssagi a j'àutri utent.",
	'spam-blacklisted-email-signup' => "L'adrëssa ëd pòsta eletrònica dàita a l'é al moment an na lista nèira për l'utilisassion.",
	'spam-invalid-lines' => "{{PLURAL:$1|St'|Sti}} element dla lista nèira dla rumenta ëd reclam a {{PLURAL:$1|l'é|son}} {{PLURAL:$1|n'|dj'}}espression regolar nen {{PLURAL:$1|bon-a|bon-e}} e a l'{{PLURAL:$1|ha|han}} da manca d'esse coregiùe anans che salvé la pàgina:",
	'spam-blacklist-desc' => 'Strument anti-spam basà an dzora a Regex: [[MediaWiki:Spam-blacklist]] e [[MediaWiki:Spam-whitelist]]', # Fuzzy
);

/** Western Punjabi (پنجابی)
 * @author Khalid Mahmood
 */
$messages['pnb'] = array(
	'spam-blacklist' => '# بارلے یو آر ایل جیہڑے ایس لسٹ نال رلدے ہون جدوں اوناں ایس صفے نال جوڑیا جاۓ گا تے اوناں نوں روک دتا جاؤکا۔
# ایہ لسٹ صرف ایس وکی نال جڑی اے؛ جگت روکلسٹ نوں وی ویکھو۔
# ڈوکومنٹیشن ل‏ی ویکھو  https://www.mediawiki.org/wiki/Extension:SpamBlacklist
# <!-- ایس لین نوں اینج ای چھوڑ جنج اے ہے --> <pre>
#
# سینٹیکس ایہ اے:
# * ہرشے  "#" توںلے کے لین دے انت تک اک کومنٹ اے
# * ہر ناں خالی لین اک ریجیکس فریگمنٹ اے جیہڑی یو آر ایل دے اندر ہوسٹو نال رلے گی۔

#</pre> <!-- ایس لین نوں انج ای چھوڑ دیو جنج ایہ ہے -->',
	'spam-whitelist' => '# <!-- ایس لین نوں اینج ای چھوڑ جنج اے ہے --> <pre>
# بارلے یو آر ایل جیہڑے ایس لسٹ نال رلدے ہون جدوں اوناں ایس صفے نال جوڑیا جاۓ گا تے اوناں نوں نئیں روکیا جاویگا پاویں اوناں نوں بلیکلسٹ انٹریز چ روکیا گیا ہووے۔
#
# سینٹیکس ایہ اے:
# * ہرشے  "#" توںلے کے لین دے انت تک اک کومنٹ اے
# * ہر ناں خالی لین اک ریجیکس فریگمنٹ اے جیہڑی یو آر ایل دے اندر ہوسٹو نال رلے گی۔

#</pre> <!-- ایس لین نوں انج ای چھوڑ دیو جنج ایہ ہے -->',
	'spam-invalid-lines' => 'تھلے دتی گئی سپام کالیلسٹ {{PLURAL:$1|lلین|لیناں}} ناں منی جان والی ریگولر {{PLURAL:$1|ایکسپریشن|ایکسپریشناں}} تے {{PLURAL:$1|لوڑاں|لوڑ}} نوں ٹھیک کرنا ضروری صفہ بچان توں پہلے:',
	'spam-blacklist-desc' => 'ریجیکس تے بنیا سپام ویری اوزار: [[MediaWiki:Spam-blacklist]] تے [[MediaWiki:Spam-whitelist]]', # Fuzzy
);

/** Portuguese (português)
 * @author Giro720
 * @author Hamilton Abreu
 * @author Helder.wiki
 * @author Malafaya
 * @author 555
 */
$messages['pt'] = array(
	'spam-blacklist' => '  # URLs externas que coincidam com esta lista serão bloqueadas quando forem
  # adicionadas a uma página.
  # Esta lista aplica-se apenas a esta wiki. Consulte também a lista-negra global.
  # Veja a documentação em https://www.mediawiki.org/wiki/Extension:SpamBlacklist
  #<!-- mantenha esta linha exatamente assim --> <pre>
#
# A sintaxe é a seguinte:
#  * Tudo o que estiver após um "#" até o final de uma linha é um comentário
#  * Todas as linhas que não estiverem em branco são um fragmento de expressão regular
#    (regex) de busca, que só poderão coincidir com hosts na URL

  #</pre> <!-- mantenha esta linha exatamente assim -->',
	'spam-whitelist' => ' #<!-- mantenha esta linha exatamente assim --> <pre>
# URLs externas que coincidam com esta lista *não* serão bloqueadas mesmo se
# teriam sido bloqueadas por entradas presentes na lista negra.
#
# A sintaxe é a seguinte:
#  * Tudo o que estiver após um "#" até o final de uma linha é um comentário
#  * Todas as linhas que não estiverem em branco são um fragmento de expressão regular
#    (regex) de busca, que só poderão coincidir com hosts na URL

 #</pre> <!-- mantenha esta linha exatamente assim -->',
	'spam-blacklisted-email' => 'Endereço de correio electrónico da lista negra',
	'spam-blacklisted-email-text' => 'Atualmente o seu endereço de e-mail está na lista negra que impede o envio de e-mails a outros utilizadores.',
	'spam-blacklisted-email-signup' => 'O endereço de e-mail fornecido não pode ser utilizado pois está na lista negra.',
	'spam-invalid-lines' => "{{PLURAL:$1|A entrada|As entradas}} abaixo {{PLURAL:$1|é uma expressão regular|são expressões regulares}}  ''(regex)'' {{PLURAL:$1|inválida e precisa|inválidas e precisam}} de ser {{PLURAL:$1|corrigida|corrigidas}} antes de gravar a página:",
	'spam-blacklist-desc' => 'Ferramenta anti-"spam" baseada em Regex: [[MediaWiki:Spam-blacklist]] e [[MediaWiki:Spam-whitelist]]', # Fuzzy
	'log-name-spamblacklist' => 'Registros da lista negra de spam',
	'log-description-spamblacklist' => 'Estes eventos acompanham as ocorrências da lista negra de spam.',
	'logentry-spamblacklist-hit' => '$1 ativou a lista negra de spam em $3 ao tentar inserir $4.',
	'right-spamblacklistlog' => 'Ver registros da lista negra de spam',
	'action-spamblacklistlog' => 'ver os registos da lista negra de spam',
);

/** Brazilian Portuguese (português do Brasil)
 * @author Cainamarques
 * @author Eduardo.mps
 * @author Helder.wiki
 * @author Tuliouel
 * @author 555
 */
$messages['pt-br'] = array(
	'spam-blacklist' => ' #<!-- mantenha esta linha exatamente assim --> <pre>
# URLs externas que coincidam com esta lista serão bloqueadas quando forem
# adicionadas a uma página.
# Esta lista refere-se apenas a este wiki. Consulte também a lista-negra global.
# Veja a documentação em https://www.mediawiki.org/wiki/Extension:SpamBlacklist
#
# A sintaxe é a seguinte:
#  * Tudo o que estiver após um "#" até o final de uma linha será tido como um comentário
#  * Todas as linhas que não estiverem em branco são um fragmento de expressão 
# regular (regex) que abrangem apenas a URL especificada

 #</pre> <!-- mantenha esta linha exatamente assim -->',
	'spam-whitelist' => ' #<!-- mantenha esta linha exatamente assim --> <pre>
 # URLs externas que coincidam com esta lista *não* serão
 # bloqueadas mesmo se tiverem sido bloqueadas por entradas
 # presentes nas listas negras.
 #
 # A sintaxe é a seguinte:
 #  * Tudo o que estiver após um "#" até o final de uma linha
 # será tido como um comentário
 #  * Todas as linhas que não estiverem em branco são um
 # fragmento de expressão regular (regex) que abrangem apenas
 # a URL especificada

  #</pre> <!-- mantenha esta linha exatamente assim -->',
	'email-blacklist' => ' #<!-- mantenha esta linha exatamente assim --> <pre>
 # Endereços de e-mail que coincidam com esta lista serão
 # impedidos de se registrar, bem como de enviar mensagens
 # Esta lista refere-se apenas a este wiki. Consulte também a lista negra global.
 # Veja a documentação em https://www.mediawiki.org/wiki/Extension:SpamBlacklist
 #<!-- mantenha esta linha exatamente assim --> <pre>
 #
 # A sintaxe é a seguinte:
 #  * Tudo o que estiver após um "#" até o final de uma 
 # linha será tido como um comentário
 #  * Todas as linhas que não estiverem em branco são um
 # fragmento de expressão regular (regex) que abrangem apenas
 # o domínio do endereço de e-mail

  #</pre> <!-- mantenha esta linha exatamente assim -->',
	'email-whitelist' => ' #<!-- mantenha esta linha exatamente assim --> <pre>
 # Endereços de e-mail que coincidam com esta lista *não*
 # serão bloqueados mesmo que tenham sofrido bloqueio
 # por instruções presentes nas listas negras.
 #
 # A sintaxe é a seguinte:
 #  * Tudo o que estiver após um "#" até o final de uma linha
 # será tido como um comentário
 #  * Todas as linhas que não estiverem em branco são um
 # fragmento de expressão regular (regex) que abrangem apenas
 # os domínios dos endereços de e-mail

  #</pre> <!-- mantenha esta linha exatamente assim -->',
	'spam-blacklisted-email' => 'Endereço eletrônico na lista negra',
	'spam-blacklisted-email-text' => 'O seu endereço de correio eletrônico está proibido de enviar mensagens para outros usuários.',
	'spam-blacklisted-email-signup' => 'O endereço fornecido encontra-se na lista negra.',
	'spam-invalid-lines' => '{{PLURAL:$1|A linha|As linhas}} a seguir {{PLURAL:$1|é uma expressão regular|são expressões regulares}} (regex) {{PLURAL:$1|inválida e precisa|inválidas e precisam}} ser {{PLURAL:$1|corrigida|corrigidas}} antes de salvar a página:',
	'spam-blacklist-desc' => 'Ferramenta anti-spam baseada em expressões regulares que permite adicionar URLs numa lista negra, barrando-os em páginas e também em emails enviados a usuários registrados',
	'log-name-spamblacklist' => 'Registro da lista negra de spam',
	'action-spamblacklistlog' => 'ver os registros da lista negra de spam',
);

/** Romanian (română)
 * @author Firilacroco
 * @author Minisarm
 */
$messages['ro'] = array(
	'spam-blacklisted-email' => 'Adresă de e-mail inclusă în lista neagră',
	'spam-blacklisted-email-text' => 'Adresa dumneavoastră de e-mail este actualmente inclusă în lista neagră, neputând expedia e-mailuri altor utilizatori.',
	'spam-blacklisted-email-signup' => 'Adresa de e-mail specificată este actualmente inclusă în lista neagră, neputând fi utilizată.',
	'spam-invalid-lines' => '{{PLURAL:$1|Următorul rând|Următoarele rânduri}} din lista neagră de spam {{PLURAL:$1|este|sunt}} {{PLURAL:$1|o expresie regulată invalidă|expresii regulate invalide}} și trebuie {{PLURAL:$1|corectat|corectate}} înainte de a salva pagina:',
	'spam-blacklist-desc' => 'Unealtă antispam bazată pe regex care permite includerea adreselor URL introduse în pagini și a adreselor de e-mail ale utilizatorilor înregistrați în lista neagră',
	'log-name-spamblacklist' => 'Jurnal listă neagră spam',
	'log-description-spamblacklist' => 'Aceste evenimente urmăresc declanșarea listei negre de spam.',
	'logentry-spamblacklist-hit' => '$1 a provocat declanșarea listei negre de spam în pagina $3, încercând să adauge $4.',
	'right-spamblacklistlog' => 'Vizualizează jurnalul listei negre de spam',
	'action-spamblacklistlog' => 'vizualizați jurnalul listei negre de spam',
);

/** tarandíne (tarandíne)
 * @author Joetaras
 */
$messages['roa-tara'] = array(
	'spam-blacklist' => " # Le URL esterne ca se iacchiane jndr'à st'elenghe avènene bloccate quanne avènene aggiunde jndr'à 'na pàgene.
  # St'elenghe tène effette sulamende sus a sta Uicchi; se pò refèrì pure a 'a lista gnore globale.
  # Pe documendazione vide https://www.mediawiki.org/wiki/Extension:SpamBlacklist
  #<!-- leave this line exactly as it is --> <pre>
#
# 'A sindasse jè a cumme segue:
#  * Ognecose ca tène 'u carattere \"#\" 'mbonde a fine d'a linèe jè 'nu commende
#  * Ogne linèe ca non g'è vacande jè 'nu frammende de regex ca vè face le combronde cu le host jndr'à l'URL

  #</pre> <!-- leave this line exactly as it is -->",
	'spam-whitelist' => "  #<!-- leave this line exactly as it is --> <pre>
 # Le URL esterne ca se iacchiane jndr'à st'elenghe *non* g'avènene bloccate pure ca lore sonde mise 
 # jndr'à l'elenghe d'a lista gnore.
 #

#
# 'A sindasse jè a cumme segue:
#  * Ognecose ca tène 'u carattere \"#\" 'mbonde a fine d'a linèe jè 'nu commende
#  * Ogne linèe ca non g'è vacande jè 'nu frammende de regex ca vè face le combronde cu le host jndr'à l'URL

  #</pre> <!-- leave this line exactly as it is -->",
	'email-blacklist' => " #<!-- leave this line exactly as it is --> <pre>
# Le indirizze email ca iessene jndr'à ste elenghe onna essere bloccate da 'a reggistrazzione e da mannà le email
# Ste elenghe tène cunde sole de sta uicchi; referite pure a 'a lista gnore globbale.
# Pe documendazione 'ndruche https://www.mediawiki.org/wiki/Extension:SpamBlacklist
#
# 'A sindasse jè 'a seguende:
#   * Ognecose ca tène 'u carattere \"#\" 'mbonde a fine d'a linèe jè 'nu commende
#   * Ogne linèe ca non g'è vacande jè 'nu frammende de regex ca vè face le combronde cu le host jndr'à le indirizze email

 #</pre> <!-- leave this line exactly as it is -->",
	'email-whitelist' => " #<!-- leave this line exactly as it is --> <pre>
# Le indirizze email ca iessene jndr'à ste elenghe *NON* ge onna essere bloccate pure ce lore ponne sta jndr'à le vôsce d'a lista gnore
#
# 'A sindasse jè 'a seguende:
#   * Ognecose ca tène 'u carattere \"#\" 'mbonde a fine d'a linèe jè 'nu commende
#   * Ogne linèe ca non g'è vacande jè 'nu frammende de regex ca vè face le combronde cu le host jndr'à le indirizze email

 #</pre> <!-- leave this line exactly as it is -->",
	'spam-blacklisted-email' => 'Indirizze email da ignorà',
	'spam-blacklisted-email-text' => "L'indirizze email tune jè mo jndr'à lista gnore pe mannà email a otre utinde.",
	'spam-blacklisted-email-signup' => "L'indirizze email ca è date pe mò ste jndr'à lista gnore.",
	'spam-invalid-lines' => "{{PLURAL:$1|'A seguende linèe d'a blacklist de spam jè|Le seguende linèe d'a blacklist de spam sonde}} {{PLURAL:$1|espressione|espressiune}} regolare invalide e {{PLURAL:$1|abbesogne|abbesognane}} de avenè corrette apprime de reggistrà 'a pàgene:",
	'spam-blacklist-desc' => "'U strumende andi-spam basate sus a le regex ca dèje le URL de le pàggene de l'elenghe gnure e le indirizze email de le utinde reggistrate",
	'log-name-spamblacklist' => "Archivije de l'elenghe gnure de le rummate",
	'log-description-spamblacklist' => "Ste evende tracciane le trasute jndr'à l'elenghe gnure de le rummate.",
	'logentry-spamblacklist-hit' => "$1 ave fatte 'na trasute jndr'à l'elenghe gnure de le rummate sus a $3 pruvanne a aggiungere $4.",
	'right-spamblacklistlog' => "'Ndruche l'archivije de l'elenghe gnure de le rummate",
	'action-spamblacklistlog' => "'ndruche l'archivije de l'elenghe gnure d'u rummate",
);

/** Russian (русский)
 * @author Ahonc
 * @author Express2000
 * @author HalanTul
 * @author Kaganer
 * @author Okras
 * @author Александр Сигачёв
 */
$messages['ru'] = array(
	'spam-blacklist' => ' # Внешние ссылки, соответствующие этому списку, будут запрещены для внесения на страницы.
 # Этот список действует только для данной вики, существует также общий чёрный список.
 # Подробнее на странице https://www.mediawiki.org/wiki/Extension:SpamBlacklist
 #<!-- не изменяйте эту строку --> <pre>
#
# Синтаксис:
#   * Всё, начиная с символа "#" и до конца строки, считается комментарием
#   * Каждая непустая строка является фрагментом регулярного выражения, применяемого только к узлу в URL

 #</pre> <!-- не изменяйте эту строку -->',
	'spam-whitelist' => ' #<!-- не изменяйте эту строку --> <pre>
# Внешние ссылки, соответствующие этому списку, *не* будут блокироваться, даже если они попали в чёрный список.
#
# Синтаксис:
#   * Всё, начиная с символа "#" и до конца строки, считается комментарием
#   * Каждая непуская строка является фрагментом регулярного выражения, применяемого только к узлу в URL

 #</pre> <!-- не изменяйте эту строку -->',
	'email-blacklist' => ' # Адреса электронной почты, соответствующие этому списку, будут заблокированы от регистрации или посылки эл. почты.
 # Этот список действует только для данной вики, существует также общий чёрный список.
 # Подробнее на странице https://www.mediawiki.org/wiki/Extension:SpamBlacklist
 #<!-- не изменяйте эту строку --> <pre>
#
# Синтаксис:
#   * Всё, начиная с символа "#" и до конца строки, считается комментарием
#   * Каждая непустая строка является фрагментом регулярного выражения, применяемого только к узлам внутри адресов эл. почты

 #</pre> <!-- не изменяйте эту строку -->',
	'email-whitelist' => ' #<!-- не изменяйте эту строку --> <pre>
# Адреса электронной почты, соответствующие этому списку, НЕ БУДУТ заблокированы,
# даже если они внесены в черный список.
#
 #</pre> <!-- не изменяйте эту строку --> 
# Синтаксис:
#   * Всё, начиная с символа "#" и до конца строки, считается комментарием
#   * Каждая непустая строка является фрагментом регулярного выражения, применяемого только к узлам внутри адресов эл. почты',
	'spam-blacklisted-email' => 'Адреса электронной почты, занесённые в чёрный список',
	'spam-blacklisted-email-text' => 'Ваш адрес электронной почты в настоящее время находится в чёрном списке, поэтому вы не можете отправлять сообщения другим пользователям.',
	'spam-blacklisted-email-signup' => 'Указанный адрес электронной почты в настоящее время занесён в чёрный список и не может быть использован.',
	'spam-invalid-lines' => '{{PLURAL:$1|Следующая строка чёрного списка ссылок содержит ошибочное регулярное выражение и должна быть исправлена|Следующие строки чёрного списка ссылок содержат ошибочные регулярные выражения и должны быть исправлены}} перед сохранением:',
	'spam-blacklist-desc' => 'Основанный на регулярных выражениях анти-спам инструмент позволяет добавлять в чёрный список URL на страницах и адреса электронной почты для зарегистрированных пользователей',
);

/** Rusyn (русиньскый)
 * @author Gazeb
 */
$messages['rue'] = array(
	'spam-blacklist' => ' # Екстерны URL одповідаючі тому списку будуть заблокованы при пробі придати їх на сторінку.
 # Тот список овпливнює лем тоту вікі; посмотьте ся тыж на ґлоналну чорну листину.
 # Документацію найдете на https://www.mediawiki.org/wiki/Extension:SpamBlacklist
 #<!-- Охабте тот рядок точно як є --> <pre>
#
# Сінтаксіс є наступный:
#  * Вшытко од знаку „#“ до кінце рядку є коментарь
#  * Каждый непорожній рядок є часть реґуларного выразу, котрому будуть одповідати лем домены з URL

 #</pre> <!-- Охабте тот рядок точно як є -->',
	'spam-whitelist' => ' #<!-- Охабте тот рядок точно як є --> <pre>
# Екстерны URL одповідаючі выразам у тім списку *не будуть* заблокованы, ані кобы
# їх заблоковали положкы з чорной листины.
#
# Сінтаксіс є наступна:
#  * Вшытко од знаку „#“ до кінце рядку є коментарь
#  * каждый непорожній рядок є часть реґуларного выразу, котрому будурь одповідати лем домены з URL

 #</pre> <!-- Охабте тот рядок точно як є -->',
	'email-blacklist' => ' # З імейлів одповідных гевсёму списку не буде годен зареґістровати ни конто ни послати імейл.
 # Гевсесь список мать вплыв лем на гевсю вікі; посмотьте тыж ґлобалных чорный список.
 # Документацію найдете на https://www.mediawiki.org/wiki/Extension:SpamBlacklist
 #<!-- тот рядок охабте актуално так як він є теперь --> <pre>
#
# Сінтакс є в наступных рядках:
#  * Вшытко од сімвола „#“ до кінце рядка є коментарь
#  * Каждый непорожнїй рядок є часть реґуларного выразу, котрому будуть одповідати лем домены в імейловых адресах

 #</pre> <!-- тот рядок охабте актуално так як він є теперь -->',
	'email-whitelist' => ' #<!-- тот рядок охабте актуално так як він є теперь --> <pre>
# Імейлы одповідны тому списку *не будуть* заблокованы, хоць бы
# одповідали записам в чорнім списку.
#
# Сінтакс є в наступныха рядках:
#  * Вшытко од сімвола „#“ до кінце рядка є коментарь
#  * Каждый непорожнїй рядок є часть реґуларного выразу, котрому будуть одповідати лем домены в імейловых адресах
 #</pre> <!-- тот рядок охабте актуално так як він є теперь -->',
	'spam-blacklisted-email' => 'Імейл на чорнім списку',
	'spam-blacklisted-email-text' => 'Ваша імейлова адреса є моментално уведжена на чорнім списку, та же другым хоснователям не можете послати імейл.',
	'spam-blacklisted-email-signup' => 'Уведжена імейлова адреса є моментално на чорнім списку.',
	'spam-invalid-lines' => 'На чорній листинї спаму {{PLURAL:$1|є наступный рядок неправилный реґуларный выраз|суть наступны рядкы неправилны реґуларны выразы|суть наступны рядкы неправилны реґуларны выразы}} і є треба {{PLURAL:$1|го|їх|їх}} перед уложінём сторінкы справити:',
	'spam-blacklist-desc' => 'Антіспамовый інштрумент на базї реґуларных выразів: [[MediaWiki:Spam-blacklist]] і [[MediaWiki:Spam-whitelist]]', # Fuzzy
);

/** Sanskrit (संस्कृतम्)
 * @author Shubha
 */
$messages['sa'] = array(
	'spam-blacklist' => ' #सूच्यां विद्यमानानां सदृशानि बाह्य URLs अवरुद्धानि भवन्ति यदा पृष्ठं योज्यते ।
 #एषा सूची अस्यां वीक्यां प्रभावकारिणी अस्ति; वैश्विकदुरुपयुक्तावल्याः कृते अपि आन्वितं भवति ।
 #प्रलेखनाय दृश्यताम् https://www.mediawiki.org/wiki/Extension:SpamBlacklist 
 #<!-- leave this line exactly as it is --> <pre>
#
#विन्यासः एवं विद्यते :
#  * "#" तः आरभ्यमाणाः पङ्क्तेः अन्त्यपर्यन्तं विद्यमानः अभिप्रायः भवति ।
#  * प्रत्येकं रिक्तरहिता पंक्तिः regex fragment भवति यत् URLs  अन्तर्गतैः आयोजकैः तुल्यते
 #</pre> <!-- इयं पङ्क्ती यथावत् त्यज्यताम् -->',
	'spam-whitelist' => ' #<!-- leave this line exactly as it is --> <pre>
#अस्यां सूच्यां विद्यमानानां सदृशानि URLs  *न* अवरुद्ध्यन्ते यद्यपि शक्यम्
# दुरुपयुक्तप्रवेशैः अवरुद्धमस्ति ।
#
 #</pre> <!-- leave this line exactly as it is -->',
	'email-blacklist' => ' #सूच्यां विद्यमानानां सदृशाः बाह्य ईपत्रसङ्केताः पञ्जीकरणात् ईपत्रप्रेषणात् च अवरुद्धाः भवन्ति 
 #एषा सूची अस्यां वीक्यां प्रभावकारिणी अस्ति; वैश्विकदुरुपयुक्तावल्याः कृते अपि आन्वितं भवति ।
 #प्रलेखनाय दृश्यताम् https://www.mediawiki.org/wiki/Extension:SpamBlacklist 
 #<!-- leave this line exactly as it is --> <pre>
#
#विन्यासः एवं विद्यते :
#  * "#" तः आरभ्यमाणाः पङ्क्तेः अन्त्यपर्यन्तं विद्यमानः अभिप्रायः भवति ।
#  * प्रत्येकं रिक्तरहिता पंक्तिः regex fragment भवति यत् URLs  अन्तर्गतैः आयोजकैः तुल्यते
 #</pre> <!-- इयं पङ्क्ती यथावत् त्यज्यताम् -->',
	'email-whitelist' => ' #<!-- leave this line exactly as it is --> <pre>
#अस्यां सूच्यां विद्यमानानां सदृशाः ईपत्रसङ्केताः *न* अवरुद्ध्यन्ते यद्यपि शक्यम्
# दुरुपयुक्तप्रवेशैः अवरुद्धमस्ति ।
#
 #</pre> <!-- leave this line exactly as it is -->
# विन्यासः एवं भवेत्:
#   *  "#" तः आरभ्यमाणं वाक्यान्तपर्यन्तं विद्यमानम् अभिप्रायः मन्यते 
#  * सर्वाः रिक्तरहिताः पङ्क्तयः  regex fragment भवति ये ईपत्रसङ्केतान्तर्गतेन अंशेन तुल्यन्ते',
	'spam-blacklisted-email' => 'निन्द्यः ईपत्रसङ्केतः',
	'spam-blacklisted-email-text' => 'भवतः ईपत्रसङ्केतः सम्प्रति निन्द्यसङ्केतानाम् आवल्यां प्रवेशितः । अतः अन्येभ्यः योजकेभ्यः ईपत्रप्रेषणं नानुमन्यते ।',
	'spam-blacklisted-email-signup' => 'प्रदत्तः निन्द्यः ईपत्रसङ्केतः सम्प्रति उपयोगे नास्ति ।',
	'spam-invalid-lines' => 'अधोनिर्दिष्टाः अनिष्टसन्देशदुर्वृत्तयः {{PLURAL:$1|पंक्तिः|पंक्तियः}} अमान्याः नियताः {{PLURAL:$1|अभिव्यक्तिः अस्ति|अभिव्यक्तयः सन्ति}} अतः पृष्ठरक्षणात् पूर्वं तेषां परिष्कारः अवश्यं कर्तव्याः :',
	'spam-blacklist-desc' => 'रेजेक्स्-आधारितम् अनिष्टसन्देशविरोधि उपकरणम्: [[MediaWiki:Spam-blacklist]]  [[MediaWiki:Spam-whitelist]] च', # Fuzzy
);

/** Sakha (саха тыла)
 * @author HalanTul
 */
$messages['sah'] = array(
	'spam-blacklist' => " # Бу испииһэккэ баар тас сигэлэр бобуллуохтара.
 # Бу испииһэк бу эрэ бырайыакка үлэлиир, уопсай ''хара испииһэк'' эмиэ баарын умнума.
 # Сиһилии манна көр https://www.mediawiki.org/wiki/Extension:SpamBlacklist
 #<!-- бу строканы уларытыма --> <pre>
#
# Синтаксис:
#  * Бу \"#\" бэлиэттэн саҕалаан строка бүтүөр дылы барыта хос быһаарыыннан ааҕыллар
#  * Каждая непустая строка является фрагментом регулярного выражения, применяемого только к узлу в URL

 #</pre> <!-- бу строканы уларытыма -->",
	'spam-whitelist' => ' #<!-- бу строканы уларытыма --> <pre>
# Манна киирбит тас сигэлэр хара испииһэккэ киирбит да буоллахтарына син биир *бобуллуохтара суоҕа*.
#
# Синтаксис:
#  * Бу "#" бэлиэттэн саҕалаан строка бүтүөр дылы барыта хос быһаарыыннан ааҕыллар
#  * Каждая непустая строка является фрагментом регулярного выражения, применяемого только к узлу в URL

 #</pre> <!-- бу строканы уларытыма -->',
	'spam-invalid-lines' => 'Хара испииһэк манна көрдөрүллүбүт {{PLURAL:$1|строкаата сыыһалаах|строкаалара сыыһалаахтар}}, уларытыах иннинэ ол көннөрүллүөхтээх:',
	'spam-blacklist-desc' => 'Анти-спам үстүрүмүөнэ: [[MediaWiki:Spam-blacklist]] уонна [[MediaWiki:Spam-whitelist]]', # Fuzzy
);

/** Sicilian (sicilianu)
 * @author Santu
 */
$messages['scn'] = array(
	'spam-blacklist' => ' # Li URL fora dû sito ca currispùnnunu a la lista di sècutu vènunu bluccati.
 # La lista vali sulu pi stu situ; fari rifirimentu macari a la blacklist glubbali.
 # Pâ ducumentazzioni talìa https://www.mediawiki.org/wiki/Extension:SpamBlacklist
 #<!-- nun mudificari pi nenti chista riga --> <pre>
# La sintassi è  chista:
#  * Tuttu chiddu ca veni doppu nu caràttiri "#" è nu cummentu, nzinu ca finisci la riga
#  * Tutti li righi non vacanti sunnu frammenti di sprissioni riulari ca s\'àpplicanu sulu ô nomu di l\'host nti li URL
 #</pre> <!-- non mudificari nenti di sta riga -->',
	'spam-whitelist' => ' #<!-- non mudificari nta nudda manera sta riga --> <pre>
# Li URL fora ô situ ca currispùnninu a la lista ccà di sècutu *non* vèninu
# bluccati, macari ntô casu avìssiru a currispùnniri a arcuni vuci di la blacklist
#
# La sintassi è chista:
#  * Tuttu chiddu ca veni doppu un caràttiri "#" è nu cummentu, nzinu a la fini dâ riga
#  * Tutti li righi non vacanti sunnu frammenti di sprissioni riulari ca s\'applìcanu sulu  ô nomu di l\'host ntê URL

 #</pre> <!-- non mudificari nta nudda manera sta riga -->',
	'spam-invalid-lines' => '{{PLURAL:$1|La riga di sècutu|Li righi di sècutu}} di la blacklist dô spam {{PLURAL:$1|nun è na sprissioni riulari boni|nun sunnu sprissioni riulari boni}}; currèggiri {{PLURAL:$1|lu sbagghiu|li sbagghi}} prima di sarvari la pàggina.',
	'spam-blacklist-desc' => 'Strumentu antispam basatu supra li sprissioni riulari [[MediaWiki:Spam-blacklist]] e [[MediaWiki:Spam-whitelist]]', # Fuzzy
);

/** Sinhala (සිංහල)
 * @author Budhajeewa
 * @author පසිඳු කාවින්ද
 */
$messages['si'] = array(
	'spam-blacklist' => ' # External URLs matching this list will be blocked when added to a page.
 # This list affects only this wiki; refer also to the global blacklist.
 # For documentation see https://www.mediawiki.org/wiki/Extension:SpamBlacklist
 #<!-- leave this line exactly as it is --> <pre>
#
# Syntax is as follows:
#   * Everything from a "#" character to the end of the line is a comment
#   * Every non-blank line is a regex fragment which will only match hosts inside URLs

 #</pre> <!-- leave this line exactly as it is -->',
	'spam-whitelist' => ' #<!-- leave this line exactly as it is --> <pre>
# External URLs matching this list will *not* be blocked even if they would
# have been blocked by blacklist entries.
#
# Syntax is as follows:
#   * Everything from a "#" character to the end of the line is a comment
#   * Every non-blank line is a regex fragment which will only match hosts inside URLs

 #</pre> <!-- leave this line exactly as it is -->',
	'spam-blacklisted-email' => 'අපලේඛනගත විද්‍යුත්-තැපැල් ලිපින',
	'spam-invalid-lines' => 'පහත දැක්වෙන කළු ලයිස්තු {{PLURAL:$1|පේලිය|පේලි}} වැරදි regular {{PLURAL:$1|expression|expressions}} වන අතර, පිටුව සුරැකීමට පෙර නිවැරදි කළ යුතුය:',
	'spam-blacklist-desc' => 'Regex-පාදක ප්‍රති-ස්පෑම ආවුදය: [[MediaWiki:Spam-blacklist]] සහ [[MediaWiki:Spam-whitelist]]', # Fuzzy
);

/** Slovak (slovenčina)
 * @author Helix84
 */
$messages['sk'] = array(
	'spam-blacklist' => '# Externé URLs zodpovedajúce tomuto zoznamu budú zablokované pri pokuse pridať ich na stránku.
# Tento zoznam ovplyvňuje iba túto wiki; pozrite sa tiež na globálnu čiernu listinu.
 # Dokumentáciu nájdete na  https://www.mediawiki.org/wiki/Extension:SpamBlacklist
#<!-- nechajte tento riadok presne ako je --> <pre>
#
# Syntax je nasledovná:
#  * Všetko od znaku „#“ do konca riadka je komentár
#  * Každý neprázdny riadok je časť regulárneho výrazu, ktorému budú zodpovedať iba domény z URL

#</pre> <!-- nechajte tento riadok presne ako je -->',
	'spam-whitelist' => ' #<!-- leave this line exactly as it is --> <pre> 
# Externé URL zodpovedajúce výrazom v tomto zozname *nebudú* zablokované, ani keby
# ich zablokovali položky z čiernej listiny.
#
# Syntax je nasledovná:
#   * Všetko od znaku "#" do konca riadka je komentár
#   * Každý neprázdny riadok je regulárny výraz, podľa ktorého sa budú kontrolovať názvy domén

 #</pre> <!-- leave this line exactly as it is -->',
	'spam-invalid-lines' => '{{PLURAL:$1|Nasledovný riadok|Nasledovné riadky}} čiernej listiny spamu {{PLURAL:$1|je neplatný regulárny výraz|sú neplatné regulárne výrazy}} a je potrebné {{PLURAL:$1|ho|ich}} opraviť pred uložením stránky:',
	'spam-blacklist-desc' => 'Antispamový nástroj na základe regulárnych výrazov: [[MediaWiki:Spam-blacklist|Čierna listina]] a [[MediaWiki:Spam-whitelist|Biela listina]]', # Fuzzy
);

/** Slovenian (slovenščina)
 * @author Dbc334
 * @author Eleassar
 * @author Yerpo
 */
$messages['sl'] = array(
	'spam-blacklist' => ' # Zunanji URL-ji, ki se ujemajo s tem seznamom, bodo blokirani, ko bodo dodani na stran.
 # Seznam vpliva samo na ta wiki; oglejte si tudi globalni črni seznam.
 # Za dokumentacijo si oglejte https://www.mediawiki.org/wiki/Extension:SpamBlacklist
 #<!-- pustite to vrstico takšno, kot je --> <pre>
#
# Skladnja je sledeča:
#   * Vse od znaka »#« do konca vrstice je pripomba
#   * Vsaka neprazna vrstica je delec regularnega izraza, ki se bo ujemal samo z gostitelji v URL-jih

 #</pre> <!-- pustite to vrstico takšno, kot je -->',
	'spam-whitelist' => ' #<!-- pustite to vrstico takšno, kot je --> <pre>
# Zunanji URL-ji, ki se ujemajo s tem seznamom, *ne* bodo blokirani,
# četudi bi bili blokirani z vnosi črnega seznama.
#
# Skladnja je sledeča:
#   * Vse od znaka »#« do konca vrstice je pripomba
#   * Vsaka neprazna vrstica je delec regularnega izraza, ki se bo ujemal samo z gostitelji v URL-jih

 #</pre> <!-- pustite to vrstico takšno, kot je -->',
	'email-blacklist' => '# Registracija in pošiljanje z e-poštnih naslovov, ki se ujemajo s spodnjim seznamom, bosta preprečena
 # Seznam vpliva samo na ta wiki; glejte tudi globalni črni seznam.
 # Za dokumentacijo glejte https://www.mediawiki.org/wiki/Extension:SpamBlacklist
 #<!-- to vrstico pustite natančno takšno, kakršna je --> <pre>
#
# Opis skladnje:
#  * Vse od znaka "#" do konca vrstice je komentar  *
# Vsaka neprazna vrstica je regularni izraz, ki se lahko ujema le z imeni gostiteljev v e-poštnih naslovih

 #</pre> <!-- to vrstico pustite natančno takšno, kakršna je -->',
	'email-whitelist' => '#<!-- to vrstico pustite natančno takšno, kakršna je --> <pre>
# E-poštni naslovi, ki se ujemajo s tem seznamom, *ne* bodo blokirani, tudi če bi
# bili blokirani z vnosi na črnem seznamu.
#
 #</pre> <!-- to vrstico pustite natančno takšno, kakršna je -->
# Opis skladnje:
#  * Vse od znaka "#" do konca vrstice je komentar
#  * Vsaka neprazna vrstica je regularni izraz, ki se lahko ujema le z imenom gostitelja v e-poštnem naslovu',
	'spam-blacklisted-email' => 'E-poštni naslov na črnem seznamu',
	'spam-blacklisted-email-text' => 'Vaš e-poštni naslov je trenutno na črnem seznamu, zato ne morete pošiljati pošte drugim uporabnikom.',
	'spam-blacklisted-email-signup' => 'E-poštni naslov je trenutno na črnem seznamu.',
	'spam-invalid-lines' => '{{PLURAL:$1|Naslednja vrstica|Naslednji vrstici|Naslednje vrstice}} črnega seznama smetja {{PLURAL:$1|je neveljavni regularni izraz in ga|sta neveljavna regularna izraza in ju|so neveljavni regularni izrazi in jih}} je pred shranjevanjem strani potrebno popraviti:',
	'spam-blacklist-desc' => 'Orodje proti smetju, temelječe na regularnih izrazih, ki omogoča navedbo spletnih naslovov na straneh in v e-poštnih naslovih za registrirane uporabnike na črnem seznamu',
	'log-name-spamblacklist' => 'Dnevnik črnega seznama smetja',
	'log-description-spamblacklist' => 'Ti dogodki sledijo zadetke črnega seznama smetja.',
	'logentry-spamblacklist-hit' => '$1 je povzročil zadetek na črnem seznamu smetja na strani $3 ob dodajanju povezave $4',
	'right-spamblacklistlog' => 'Prikaz dnevnika črnega seznama smetja',
	'action-spamblacklistlog' => 'ogled dnevnika s črnim seznamom smetja',
);

/** Albanian (shqip)
 * @author FatosMorina
 * @author Olsi
 */
$messages['sq'] = array(
	'spam-blacklist' => ' # URL-të e jashtme që përputhen me këtë listë do të bllokohen kur shtohen tek një faqe.
 # Kjo listë ndikon vetëm në këtë wiki; referojuni gjithashtu listës së zezë globale.
 # Për dokumentacionin shiko https://www.mediawiki.org/wiki/Extension:SpamBlacklist
 #<!-- leave this line exactly as it is --> <pre>
#
# Sintaksa është si më poshtë:
#  * Çdo gjë nga një karakter "#" në fund të rreshtit është një koment
#  * Çdo rresht jobosh është një fragment që do të përputhë vetëm hostet brenda URL-ve

 #</pre> <!-- leave this line exactly as it is -->',
	'spam-whitelist' => ' #<!-- leave this line exactly as it is --> <pre>
# URL-të e jashtme që përputhen më këtë listë *nuk* nuk do të bllokohen edhe nëse ato do të
# kishin qenë të bllokuara nga shënimet e listës së zezë.
#
# Sintaksa është si më poshtë:
#   * Çdo gjë nga një karakter "#" në fund të rreshtit është një koment
#   * Çdo rresht jobosh është një fragment që vetëm do të përputhë hostet brenda URL-ve

 #</pre> <!-- leave this line exactly as it is -->',
	'spam-blacklisted-email' => 'E-mail adresa e vendosur në listën e zezë',
	'spam-blacklisted-email-text' => 'E-mail adresa juaj është për momentin në penguar nga dërgimi i e-mailave tek përdoruesit e tjerë.',
	'spam-blacklisted-email-signup' => 'E-mail adresa e dhënë për momentin është ndaluuar nga përdorimi',
	'spam-invalid-lines' => 'Lista e zezë e mëposhtme spam {{PLURAL:$1|rreshti është një|rreshtat janë}} {{PLURAL:$1|shprehje|shprehje}} të rregullta të pavlefshme dhe {{PLURAL:$1|nevojitet|nevojitet}} të korrigjohen përpara ruajtjes së faqes:',
	'spam-blacklist-desc' => 'Mjeti anti-spam regex i bazuar: [[MediaWiki:Spam-blacklist]] dhe [[MediaWiki:Spam-whitelist]]', # Fuzzy
);

/** Serbian (Cyrillic script) (српски (ћирилица)‎)
 * @author Millosh
 */
$messages['sr-ec'] = array(
	'spam-blacklist-desc' => 'Антиспам оруђе засновано на регуларним изразима: [[MediaWiki:Spam-blacklist]] и [[MediaWiki:Spam-whitelist]]', # Fuzzy
);

/** Serbian (Latin script) (srpski (latinica)‎)
 * @author Michaello
 */
$messages['sr-el'] = array(
	'spam-blacklist-desc' => 'Antispam oruđe zasnovano na regularnim izrazima: [[MediaWiki:Spam-blacklist]] i [[MediaWiki:Spam-whitelist]]', # Fuzzy
);

/** Seeltersk (Seeltersk)
 * @author Pyt
 */
$messages['stq'] = array(
	'spam-blacklist' => ' # Externe URLs, do der in disse Lieste äntheelden sunt, blokkierje dät Spiekerjen fon ju Siede.
 # Disse Lieste beträft bloot dit Wiki; sjuch uk ju globoale Blacklist.
 # Tou ju Dokumenation sjuch https://www.mediawiki.org/wiki/Extension:SpamBlacklist
 #<!-- Disse Riege duur nit ferannerd wäide! --> <pre>
#
# Syntax:
#   * Alles fon dät "#"-Teeken ou bit tou Eende fon ju Riege is n Kommentoar
#   * Älke nit-loose Riege is n regulären Uutdruk, ju der juun do Host-Noomen in do URLs wröiged wäd.

 #</pre> <!-- Disse Riege duur nit ferannerd wäide! -->',
	'spam-whitelist' => ' #<!-- Disse Riege duur nit ferannerd wäide! --> <pre>
# Externe URLs, do der in disse Lieste äntheelden sunt, blokkierje dät Spiekerjen fon ju Siede nit,
# uk wan jo in ju globoale of lokoale swotte Lieste äntheelden sunt.
#
# Syntax:
#  * Alles fon dät "#"-Teeken ou bit tou Eende fon ju Riege is n Kommentoar
#  * Älke nit-loose Riege is n regulären Uutdruk, die der juun do Host-Noomen in do URLs wröided wäd.

 #</pre> <!-- Disse Riege duur nit ferannerd wäide! -->',
	'spam-invalid-lines' => '{{PLURAL:$1
	| Ju foulgjende Siede in ju Spam-Blacklist is n uungultigen regulären Uutdruk. Ju mout foar dät Spiekerjen fon ju Siede korrigierd wäide
	| Do foulgjende Sieden in ju Spam-Blacklist sunt uungultige reguläre Uutdrukke. Do mouten foar dät Spiekerjen fon ju Siede korrigierd wäide}}:',
	'spam-blacklist-desc' => 'Regex-basierde Anti-Spam-Reewe: [[MediaWiki:Spam-blacklist]] un [[MediaWiki:Spam-whitelist]]', # Fuzzy
);

/** Swedish (svenska)
 * @author Lejonel
 * @author Skalman
 * @author WikiPhoenix
 */
$messages['sv'] = array(
	'spam-blacklist' => ' #<!-- ändra inte den här raden --> <pre>
# Den här listan stoppar matchande externa URL:er från att läggas till på sidor.
# Listan påverkar bara den här wikin; se även den globala svartlistan.
# För dokumentation se https://www.mediawiki.org/wiki/Extension:SpamBlacklist
#
# Syntaxen är följande:
#   * All text från ett #-tecken till radens slut är en kommentar
#   * Alla icke-tomma rader används som reguljära uttryck för att matcha domännamn i URL:er

 #</pre> <!-- ändra inte den här raden -->',
	'spam-whitelist' => '
 #<!-- ändra inte den här raden --> <pre>
# Externa URL:er som matchar den här listan blockeras *inte*,
# inte ens om de är blockerade genom den svarta listan för spam.
#
# Syntaxen är följande:
#   * All text från ett #-tecken till radens slut är en kommentar
#   * Alla icke-tomma rader används som reguljära uttryck för att matcha domännamn i URL:er

 #</pre> <!-- ändra inte den här raden -->',
	'spam-blacklisted-email' => 'Svartlistad e-postadress',
	'spam-invalid-lines' => 'Följande {{PLURAL:$1|rad|rader}} i svarta listan för spam innehåller inte något giltigt reguljärt uttryck  och måste rättas innan sidan sparas:',
	'spam-blacklist-desc' => 'Antispamverktyg baserat på reguljära uttryck som gör det möjligt att svartlista webbadresser på sidor och e-postadresser för registrerade användare',
);

/** Tamil (தமிழ்)
 * @author Karthi.dr
 * @author மதனாஹரன்
 */
$messages['ta'] = array(
	'spam-blacklisted-email' => 'தடை செய்யப்பட்டுள்ள மின்னஞ்சல் முகவரிகள்',
	'spam-blacklisted-email-text' => 'மற்ற பயனர்களுக்கு மின்னஞ்சல் செய்ய இயலாதபடி உங்கள் மின்னஞ்சல் முகவரி தடை செய்யப்பட்டுள்ளது.',
	'spam-blacklisted-email-signup' => 'வழங்கப்பட்ட மின்னஞ்சல் முகவரியானது இப்போது பயன்பாட்டிலிருநது விலக்கப்பட்டுக் கறுப்புப் பட்டியலிலுள்ளது.',
);

/** Telugu (తెలుగు)
 * @author Veeven
 */
$messages['te'] = array(
	'spam-blacklist' => '
 # ఓ పేజీకి చేర్చిన బయటి లింకులు గనక ఈ జాబితాతో సరిపోలితే వాటిని నిరోధిస్తాం.
 # ఈ జాబితా ఈ వికీకి మాత్రమే సంబంధించినది; మహా నిరోధపు జాబితాని కూడా చూడండి.
 # పత్రావళి కొరకు ఇక్కడ చూడండి: https://www.mediawiki.org/wiki/Extension:SpamBlacklist
 #<!-- leave this line exactly as it is --> <pre>
#
# Syntax is as follows:
#  * "#" అన్న అక్షరం నుండి లైను చివరివరకూ ఉన్నదంతా వ్యాఖ్య
#  * ఖాళీగా లేని ప్రతీలైనూ URLలలోని హోస్ట్ పేరుని మాత్రమే సరిపోల్చే ఒక regex తునక

 #</pre> <!-- leave this line exactly as it is -->',
	'spam-whitelist' => '
 #<!-- leave this line exactly as it is --> <pre>
# ఈ జాబితాకి సరిపోలిన బయటి లింకులని *నిరోధించము*,
# అవి నిరోధపు జాబితాలోని పద్దులతో సరిపోలినా గానీ.
#
# ఛందస్సు ఇదీ:
#  * "#" అక్షరం నుండి లైను చివరివరకూ ప్రతీదీ ఓ వ్యాఖ్యే
#  * ఖాళీగా లేని ప్రతీ లైనూ URLలలో హోస్ట్ పేరుని సరిపోల్చే regex తునక

 #</pre> <!-- leave this line exactly as it is -->',
	'spam-invalid-lines' => 'స్పామ్ నిరోధపు జాబితాలోని క్రింద పేర్కొన్న {{PLURAL:$1|లైను|లైన్లు}} తప్పుగా {{PLURAL:$1|ఉంది|ఉన్నాయి}}, పేజీని భద్రపరిచేముందు {{PLURAL:$1|దాన్ని|వాటిని}} సరిదిద్దండి:',
	'spam-blacklist-desc' => 'Regex-ఆధారిత స్పామ్ నిరోధక పనిముట్టు: [[MediaWiki:Spam-blacklist]] మరియు [[MediaWiki:Spam-whitelist]]', # Fuzzy
);

/** Tajik (Cyrillic script) (тоҷикӣ)
 * @author Ibrahim
 */
$messages['tg-cyrl'] = array(
	'spam-blacklist' => ' # Нишониҳои URL берунаи ба ин феҳрист мутобиқатшуда вақте, ки ба саҳифае илова мешаванд, 
 # баста хоҳанд шуд.
 # Ин феҳрист фақат рӯи ҳамин вики таъсир мекунад; ба феҳристи сиёҳи саросар низ муроҷиат кунед.
 # Барои мустанадот, нигаред ба https://www.mediawiki.org/wiki/Extension:SpamBlacklist
 #<!--  ин сатрро ҳамонгуна, ки ҳаст раҳо кунед --> <pre>
#
 # Дастурот ба ин шакл ҳастанд:
 #  * Ҳама чиз аз аломати "#" то поёни сатр ба унвони тавзеҳ ба назар гирифта мешавад
 #  * Ҳар сатр аз матн ба унвони як дастур regex ба назар гирифта мешавад, 
 #  ки фақат бо номи мизбон дар нишонии интернетии URL мутобиқат дода мешавад

 #</pre> <!-- ин сатрро ҳамонгуна, ки ҳаст раҳо кунед -->',
	'spam-whitelist' => ' #<!-- ин сатрро ҳамонгуна, ки ҳаст раҳо кунед --> <pre>
# Нишониҳои URL берунаи ба ин феҳрист мутобиқатбуда, баста нахоҳанд шуд, 
# ҳатто агар дар феҳристи сиёҳ қарор дошта бошад.
#
# Дастурот ба ин шакл ҳастанд:
#  * Ҳама чиз аз аломати "#" то поёни сатр ба унвони тавзеҳ ба назар гирифта мешавад
#  * Ҳар сатр аз матн ба унвони як дастур regex ба назар гирифта мешавад, ки фақат бо номи мизбон дар 
# нишонии интернетии URL мутобиқат дода мешавад
 #</pre> <!-- ин сатрро ҳамонгуна, ки ҳаст раҳо кунед -->',
	'spam-invalid-lines' => '{{PLURAL:$1|Сатри|Сатрҳои}} зерин дар феҳристи сиёҳи ҳарзнигорӣ дастуроти ғайри миҷозе regular expressions  {{PLURAL:$1|аст|ҳастанд}} ва қабл аз захира кардани саҳифа ба ислоҳ кардан ниёз {{PLURAL:$1|дорад|доранд}}:',
	'spam-blacklist-desc' => 'Абзори зидди ҳарзнигорӣ дар асоси Regex: [[MediaWiki:Spam-blacklist]] ва [[MediaWiki:Spam-whitelist]]', # Fuzzy
);

/** Tajik (Latin script) (tojikī)
 * @author Liangent
 */
$messages['tg-latn'] = array(
	'spam-blacklist' => ' # Nişonihoi URL berunai ba in fehrist mutobiqatşuda vaqte, ki ba sahifae ilova meşavand, 
 # basta xohand şud.
 # In fehrist faqat rūi hamin viki ta\'sir mekunad; ba fehristi sijohi sarosar niz muroçiat kuned.
 # Baroi mustanadot, nigared ba https://www.mediawiki.org/wiki/Extension:SpamBlacklist
 #<!--  in satrro hamonguna, ki hast raho kuned --> <pre>
#
 # Dasturot ba in şakl hastand:
 #  * Hama ciz az alomati "#" to pojoni satr ba unvoni tavzeh ba nazar girifta meşavad
 #  * Har satr az matn ba unvoni jak dastur regex ba nazar girifta meşavad, 
 #  ki faqat bo nomi mizbon dar nişoniji internetiji URL mutobiqat doda meşavad

 #</pre> <!-- in satrro hamonguna, ki hast raho kuned -->',
	'spam-whitelist' => ' #<!-- in satrro hamonguna, ki hast raho kuned --> <pre>
# Nişonihoi URL berunai ba in fehrist mutobiqatbuda, basta naxohand şud, 
# hatto agar dar fehristi sijoh qaror doşta boşad.
#
# Dasturot ba in şakl hastand:
#  * Hama ciz az alomati "#" to pojoni satr ba unvoni tavzeh ba nazar girifta meşavad
#  * Har satr az matn ba unvoni jak dastur regex ba nazar girifta meşavad, ki faqat bo nomi mizbon dar 
# nişoniji internetiji URL mutobiqat doda meşavad
 #</pre> <!-- in satrro hamonguna, ki hast raho kuned -->',
	'spam-invalid-lines' => '{{PLURAL:$1|Satri|Satrhoi}} zerin dar fehristi sijohi harznigorī dasturoti ƣajri miçoze regular expressions  {{PLURAL:$1|ast|hastand}} va qabl az zaxira kardani sahifa ba isloh kardan nijoz {{PLURAL:$1|dorad|dorand}}:',
	'spam-blacklist-desc' => 'Abzori ziddi harznigorī dar asosi Regex: [[MediaWiki:Spam-blacklist]] va [[MediaWiki:Spam-whitelist]]', # Fuzzy
);

/** Turkmen (Türkmençe)
 * @author Hanberke
 */
$messages['tk'] = array(
	'spam-invalid-lines' => 'Aşakdaky spam gara sanawynyň {{PLURAL:$1|setiri|setiri}} nädogry regulýar {{PLURAL:$1|aňlatmadyr|aňlatmadyr}} we sahypa ýazdyrylmanka düzedilmelidir:',
	'spam-blacklist-desc' => 'Regulýar aňlatmalar esasynda anti-spam guraly: [[MediaWiki:Spam-blacklist]] we [[MediaWiki:Spam-whitelist]]', # Fuzzy
);

/** Tagalog (Tagalog)
 * @author AnakngAraw
 */
$messages['tl'] = array(
	'spam-blacklist' => " # Ang panlabas na mga URL na tumutugma sa talaang ito ay hahadlangan/haharangin kapag idinagdag sa isang pahina.
 # Nakakaapekto lamang ang talaang ito sa wiking ito; sumangguni rin sa pandaigdigang talaan ng pinagbabawalan.
 # Para sa kasulatan tingnan ang https://www.mediawiki.org/wiki/Extension:SpamBlacklist
 #<!-- leave this line exactly as it is --> <pre>
#
# Ang palaugnayan ay ayon sa mga sumusunod:
#  * Lahat ng bagay mula sa isang \"#\" na panitik hanggang sa wakas ng isang guhit/hanay ay isang puna (kumento)
#  * Bawat hindi/walang patlang na guhit/hanay ay isang piraso ng karaniwang pagsasaad (''regex'') na tutugma lamang sa mga tagapagpasinaya sa loob ng mga URL

 #</pre> <!-- leave this line exactly as it is -->",
	'spam-whitelist' => " #<!-- leave this line exactly as it is --> <pre>
# Ang panlabas na mga URL na tumutugma sa talaang ito ay *hindi* hahadlangan kahit na sila ay
# hinarang ng mga ipinasok (entrada) sa talaan ng pinagbabawalan.
#
# Ang palaugnayan ay ayon sa mga sumusunod:
#  * Lahat ng bagay mula sa isang \"#\" na panitik hanggang sa wakas ng isang guhit/hanay ay isang puna (kumento)
#  * Bawat hindi/walang patlang na guhit/hanay ay isang piraso ng karaniwang pagsasaad (''regex'') na tutugma lamang sa mga tagapagpasinaya sa loob ng mga URL

 #</pre> <!-- leave this line exactly as it is -->",
	'email-blacklist' => " # Ang mga tirahan ng e-liham na tumutugma sa talaang ito ay hahadlangan mula sa pagpaparehistro o pagpapadala ng mga e-liham.
 # Nakakaapekto lamang ang talaang ito sa wiking ito; sumangguni rin sa pandaigdigang talaan ng pinagbabawalan.
 # Para sa kasulatan tingnan ang https://www.mediawiki.org/wiki/Extension:SpamBlacklist
 #<!-- leave this line exactly as it is --> <pre>
#
# Ang palaugnayan ay ayon sa mga sumusunod:
#  * Lahat ng bagay mula sa isang panitik na \"#\" magpahanggang sa wakas ng isang guhit ay isang puna
#  * Bawat guhit na mayroong laman ay isang piraso ng karaniwang pagsasaad (''regex'') na tutugma lamang sa mga tagapagpasinaya sa loob ng mga tirahan ng e-liham

 #</pre> <!-- leave this line exactly as it is -->",
	'email-whitelist' => " #<!-- leave this line exactly as it is --> <pre>
# Ang mga tirahan ng e-liham na tumutugma sa listahang ito ay *hindi* haharangin kahit na gawin nila ito
# ay naharang ng mga lahot sa talaan ng pinagbabawalan.
#
 #</pre> <!-- leave this line exactly as it is -->
# Ang palaugnayan ay ang mga sumusunod:
#   * Ang lahat ng mga bagay magmula sa isang panitik na \"#\" magpahanggang sa wakas ng guhit ay isang puna
#   * Bawat linya na mayroong laman ay isang piraso ng karaniwang pagsasaad (''regex'') na tutugma lamang sa mga tagapagpasinayang nasa loob ng mga tirahan ng e-liham",
	'spam-blacklisted-email' => 'Pinagbabawalang mga tirahan ng e-liham',
	'spam-blacklisted-email-text' => 'Kasalukuyang pinagbabawalan ang iyong tirahan ng e-liham na makapagpadala ng mga e-liham papunta sa ibang mga tagagamit.',
	'spam-blacklisted-email-signup' => 'Kasalukuyang ipinagbabawal ang paggamit ng ibinigay na tirahan ng e-liham.',
	'spam-invalid-lines' => 'Ang sumusunod na {{PLURAL:$1|isang hanay/guhit|mga hanay/guhit}} ng talaan ng pinagbabawalang "manlulusob" (\'\'spam\'\') ay hindi tanggap na karaniwang {{PLURAL:$1|pagsasaad|mga pagsasaad}} at {{PLURAL:$1|kinakailangang|kinakailangang}} maitama muna bago sagipin ang pahina:',
	'spam-blacklist-desc' => "Kasangkapang panlaban sa \"manlulusob\" (''spam'') na nakabatay sa karaniwang pagsasaad (''regex''): [[MediaWiki:Spam-blacklist]] at [[MediaWiki:Spam-whitelist]]", # Fuzzy
);

/** Turkish (Türkçe)
 * @author Joseph
 */
$messages['tr'] = array(
	'spam-blacklist' => ' # Bu listeyle eşleşen dış bağlantılar, bir sayfaya eklendiğinde engellenecektir. 
 # Bu liste sadece bu vikiyi etkiler; ayrıca küresel karalisteye de bakın.
 # Dokümantasyon için https://www.mediawiki.org/wiki/Extension:SpamBlacklist
 #<!-- bu satırı olduğu gibi bırakın --> <pre>
#
# Sözdizimi aşağıdaki gibidir:
#  * "#" karakterinden satır sonuna kadar her şey bir yorumdur
#  * Her boş olmayan satır, sadece URLlerin içindeki sunucularla eşleşen regex parçasıdır

 #</pre> <!-- bu satırı olduğu gibi bırakın -->',
	'spam-whitelist' => ' #<!-- bu satırı olduğu gibi bırakın --> <pre>
# Bu listeyle eşlenen dış bağlantılar *engellenmeyecektir*,
# karaliste girdileriyle engellenmiş olsalar bile.
#
# Sözdizimi aşağıdaki gibidir:
#  * "#" karakterinden satır sonuna kadar her şey bir yorumdur
#  * Her boş olmayan satır, sadece URLlerin içindeki sunucularla eşleşen regex parçasıdır

 #</pre> <!--bu satırı olduğu gibi bırakın -->',
	'spam-invalid-lines' => 'Şu spam karaliste {{PLURAL:$1|satırı|satırları}} geçersiz düzenli {{PLURAL:$1|tanımdır|tanımlardır}} ve sayfayı kaydetmeden düzeltilmesi gerekmektedir:',
	'spam-blacklist-desc' => 'Regex-tabanlı anti-spam aracı: [[MediaWiki:Spam-blacklist]] ve [[MediaWiki:Spam-whitelist]]', # Fuzzy
);

/** Ukrainian (українська)
 * @author AS
 * @author Ahonc
 * @author Andriykopanytsia
 * @author AtUkr
 * @author Base
 * @author Ата
 */
$messages['uk'] = array(
	'spam-blacklist' => '# Зовнішні посилання, що відповідають цьому списку, будуть заборонені для внесення на стоірнки.
 # Цей список діє лише для цієї вікі, існує також загальний чорний список.
 # Докладніше на сторінці https://www.mediawiki.org/wiki/Extension:SpamBlacklist
 #<!-- не змінюйте цей рядок --> <pre>
#
# Синтаксис:
#  * Все, починаючи із символу "#" і до кінця рядка, вважається коментарем
#  * Кожен непорожній рядок є фрагментом регулярного виразу, який застосовується тільки до вузла в URL

 #</pre> <!-- не змінюйте цей рядок -->',
	'spam-whitelist' => ' #<!-- не змінюйте це рядок --> <pre>
# Зовнішні посилання, що відповідають цьому списку, *не* будуть блокуватися, навіть якщо вони потрапили до чорного списку.
#
# Синтаксис:
#  * Усе, починаючи з символу "#" і до кінця рядка, вважається коментарем
#  * Кожен непорожній рядок є фрагментом регулярного виразу, який застосовується тільки до вузла в URL

 #</pre> <!-- не изменяйте эту строку -->',
	'email-blacklist' => '#<!-- не змінюйте цей рядок --> <pre>
# Адреси електронної пошти, що відповідають цьому списку, будуть заблоковані від реєстрації або надсилання ел. пошти.
# Цей список діє тільки для даної вікі, існує також загальний чорний список.
# Докладніше на сторінці https://www.mediawiki.org/wiki/Extension:SpamBlacklist
#
# Синтаксис:
# * Все, починаючи з символу "#" і до кінця рядка, вважається коментарем
# * Кожен непорожній рядок є фрагментом регулярного виразу, вживаного тільки до вузлів усередині адреси ел. пошти

#</pre> <!-- не змінюйте цей рядок -->',
	'email-whitelist' => '#<!-- не змінюйте цей рядок --> <pre>
# Адреси електронної пошти, що відповідають цьому списку, НЕ БУДУТЬ заблоковані
# навіть якщо вони занесені до чорного списку.
#
#</pre> <!-- не змінюйте цей рядок --> 
# Синтаксис:
# * Все, починаючи з символу "#" і до кінця рядка, вважається коментарем
# * Кожен непорожній рядок є фрагментом регулярного виразу, вживаного тільки до вузлів усередині адреси ел. пошти',
	'spam-blacklisted-email' => 'Адреса електронної пошти з чорного списку',
	'spam-blacklisted-email-text' => 'Ваша адреса електронної пошти в даний час знаходиться в чорному списку, тому ви не можете надсилати повідомлення іншим користувачам.',
	'spam-blacklisted-email-signup' => 'Вказана Вами адреса електронної пошти наразі занесена до чорного списку і не може бути використаною.',
	'spam-invalid-lines' => '{{PLURAL:$1|Наступний рядок із чорного списку посилань містить помилковий регулярний вираз і його треба виправити|Наступні рядки із чорного списку посилань містять помилкові регулярні вирази і їх треба виправити}} перед збереженням:',
	'spam-blacklist-desc' => 'Засновану на регулярних виразах антиспам інструмент, який дозволяє кидати у чорний список URL сторінки і адреси електронної пошти для зареєстрованих користувачів',
	'log-name-spamblacklist' => 'Журнал чорного списку спамерів',
	'log-description-spamblacklist' => 'Ці події відстежують потрапляння у чорний список спамерів.',
	'logentry-spamblacklist-hit' => '$1 спричинив потрапляння у чорний список спамерів на $3, намагаючись додати $4.',
	'right-spamblacklistlog' => 'Перегляд журналу чорного списку спамерів',
	'action-spamblacklistlog' => 'перегляд журналу "чорний список" спамерів',
);

/** vèneto (vèneto)
 * @author Candalua
 * @author GatoSelvadego
 */
$messages['vec'] = array(
	'spam-blacklist' => ' # Le URL esterne al sito che corisponde a la lista seguente le vegnarà blocà.
 # La lista la xe valida solo par sto sito qua; far riferimento anca a la blacklist globale.
 # Par la documentazion vardar https://www.mediawiki.org/wiki/Extension:SpamBlacklist
 #<!-- no sta modificar in alcun modo sta riga --> <pre>
# La sintassi la xe la seguente:
#  * Tuto quel che segue un caràtere "#" el xe un comento, fin a la fine de la riga
#  * Tute le righe mìa vode le xe framenti de espressioni regolari che se àplica al solo nome de l\'host ne le URL
 #</pre> <!-- no sta modificar in alcun modo sta riga -->',
	'spam-whitelist' => ' #<!-- no sta modificar in alcun modo sta riga --> <pre>
# Le URL esterne al sito che corisponde a la lista seguente *no* le vegnarà
# mìa blocà, anca nel caso che le corisponda a de le voçi de la lista nera
#
# La sintassi la xe la seguente:
#  * Tuto quel che segue un caràtere "#" el xe un comento, fin a la fine de la riga
#  * Tute le righe mìa vode le xe framenti de espressioni regolari che se àplica al solo nome de l\'host ne le URL

 #</pre> <!-- no sta modificar in alcun modo sta riga -->',
	'email-blacklist' => ' # I indirisi e-mail che corisponde a ła lista seguente i sarà blocai, nó sarà posibiłe salvar o inviar e-mail.
 # Ła lista ła xe vałida soło che pa\' sta wiki; far riferimento anca a ła blacklist globałe.
 # Pa\' ła documentasion se varde https://www.mediawiki.org/wiki/Extension:SpamBlacklist
 # <!-- nó modifegar sta linea --> <pre>
# Ła sintasi ła xe ła seguente:
#  * Tuto chel che xe conprexo intrà un caratere "#" e ła fine de ła riga el xe un comento
#  * Tute łe righe nó vode i xe tochi de espresion regołari che se aplica soło che al nome del host de i indirisi e-mail
 #</pre> <!-- nó modifegar sta linea -->',
	'email-whitelist' => ' #<!-- nó modifegar sta linea --> <pre>
# I indirisi e-mail conprexi in sta lista *nó* i sarà blocai anca se i dovaria
# eser stai blocai da i elementi prexenti inte ła lista nera.
#
 #</pre> <!-- nó modifegar sta linea -->
# Ła sintasi ła xe ła seguente:
#  * Tuto chel che xe conprexo intrà un caratere "#" e ła fine de ła riga el xe un comento
#  * Tute łe righe nó vode i xe tochi de espresion regołari che se aplica soło che al nome del host de i indirisi e-mail',
	'spam-blacklisted-email' => 'Indiriso de posta eletronega blocà',
	'spam-blacklisted-email-text' => "El to indiriso de posta eletronega el xe atualmente inte ła lista nera par 'l invio de e-mail verso altri utenti.",
	'spam-blacklisted-email-signup' => 'El indiriso de posta eletronega indicà el xe atualmente inte ła lista nera.',
	'spam-invalid-lines' => "{{PLURAL:$1|La seguente riga|Le seguenti righe}} de la lista nera del spam {{PLURAL:$1|no la xe na espression regolare valida|no le xe espressioni regolari valide}}; se prega de corègiar {{PLURAL:$1|l'eror|i erori}} prima de salvar la pagina.",
	'spam-blacklist-desc' => 'Strumento antispam basà su le espressioni regolari [[MediaWiki:Spam-blacklist]] e [[MediaWiki:Spam-whitelist]]', # Fuzzy
);

/** Vietnamese (Tiếng Việt)
 * @author Minh Nguyen
 */
$messages['vi'] = array(
	'spam-blacklist' => ' # Các địa chỉ URL ngoài trùng với một khoản trong danh sách này bị cấm không được thêm vào trang nào.
 # Danh sách này chỉ có hiệu lực ở wiki này; hãy xem thêm “danh sách đen toàn cầu”.
 # Có tài liệu hướng dẫn tại https://www.mediawiki.org/wiki/Extension:SpamBlacklist
 #<!-- leave this line exactly as it is --> <pre>
#
# Cú pháp:
#  * Các lời ghi chú bắt đầu với ký tự “#” và tiếp tục cho đến cuối dòng.
#  * Các dòng không để trống là một mảnh biểu thức chính quy, nó chỉ trùng với tên máy chủ trong địa chỉ URL.

 #</pre> <!-- leave this line exactly as it is -->',
	'spam-whitelist' => ' #<!-- leave this line exactly as it is --> <pre>
# Các địa chỉ URL ngoài trùng với một khoản trong danh sách này *không* bị cấm, dù có nó trong danh sách đen.
#
# Cú pháp:
#  * Các lời ghi chú bắt đầu với ký tự “#” và tiếp tục cho đến cuối dòng.
#  * Các dòng không để trống là một mảnh biểu thức chính quy, nó chỉ trùng với tên máy chủ trong địa chỉ URL.

 #</pre> <!-- leave this line exactly as it is -->',
	'email-blacklist' => ' # Các địa chỉ thư điện tử trùng với danh sách này bị cấm không được mở tài khoản hoặc gửi thư điện tử.
 # Danh sách này chỉ có hiệu lực ở wiki này; hãy xem thêm “danh sách đen toàn cầu”.
 # Có tài liệu hướng dẫn tại https://www.mediawiki.org/wiki/Extension:SpamBlacklist
 #<!-- leave this line exactly as it is --> <pre>
#
# Cú pháp:
#   * Các lời ghi chú bắt đầu với ký tự “#” và tiếp tục cho đến cuối dòng.
#   * Các dòng không để trống là một mảnh biểu thức chính quy, nó chỉ trùng với tên máy chủ trong địa chỉ thư điện tử.

 #</pre> <!-- leave this line exactly as it is -->',
	'email-whitelist' => ' #<!-- leave this line exactly as it is --> <pre>
# Các địa chỉ thư điện tử trùng với danh sách này *không* bị cấm, dù có nó trong danh sách đen.
#
 #</pre> <!-- leave this line exactly as it is -->
# Cú pháp:
#   * Các lời ghi chú bắt đầu với ký tự “#” và tiếp tục cho đến cuối dòng.
#   * Các dòng không để trống là một mảnh biểu thức chính quy, nó chỉ trùng với tên máy chủ trong địa chỉ thư điện tử.',
	'spam-blacklisted-email' => 'Địa chỉ thư điện tử bị đưa vào danh sách đen',
	'spam-blacklisted-email-text' => 'Địa chỉ thư điện tử của bạn đã được đưa vào danh sách đen nên bị cấm không được gửi thư điện tử cho người dùng khác.',
	'spam-blacklisted-email-signup' => 'Địa chỉ thư điện tử được cung cấp đã được đưa vào danh sách đen nên bị cấm không được sử dụng.',
	'spam-invalid-lines' => '{{PLURAL:$1|Dòng|Những dòng}} sau đây trong danh sách đen về spam không hợp lệ; xin hãy sửa chữa {{PLURAL:$1|nó|chúng}} để tuân theo cú pháp biểu thức chính quy trước khi lưu trang:',
	'spam-blacklist-desc' => 'Công cụ cho phép chống spam bằng cách cấm những URL trong trang và địa chỉ thư điện tử của thành viên đăng ký khớp với các biểu thức chính quy trong danh sách đen',
	'log-name-spamblacklist' => 'Nhật trình chặn spam vì danh sách đen',
	'log-description-spamblacklist' => 'Nhật trình này ghi các lần chặn spam vì nằm vào danh sách đen.',
	'logentry-spamblacklist-hit' => '$1 bị danh sách đen chống spam ngăn không được thêm $4 vào $3',
	'right-spamblacklistlog' => 'Xem nhật trình chặn spam vì danh sách đen',
	'action-spamblacklistlog' => 'xem nhật trình chặn spam vì danh sách đen',
);

/** Wu (吴语)
 * @author 十弌
 */
$messages['wuu'] = array(
	'log-name-spamblacklist' => '垃圾電郵黑名單日誌',
);

/** Cantonese (粵語)
 */
$messages['yue'] = array(
	'spam-blacklist' => ' # 同呢個表合符嘅外部 URL 當加入嗰陣會被封鎖。
 # 呢個表只係會影響到呢個wiki；請同時參閱全域黑名單。
 # 要睇註解請睇 https://www.mediawiki.org/wiki/Extension:SpamBlacklist
 #<!-- 請完全噉留番呢行 --> <pre>
#
# 語法好似下面噉:
#   * 每一個由 "#" 字元開頭嘅行，到最尾係一個註解
#   * 每個非空白行係一個標準表示式碎片，只係會同入面嘅URL端核對

 #</pre> <!-- 請完全噉留番呢行 -->',
	'spam-whitelist' => ' #<!-- 請完全噉留番呢行 --> <pre>
# 同呢個表合符嘅外部 URL ，即使響黑名單項目度封鎖，
# 都*唔會*被封鎖。
#
# 語法好似下面噉:
#   * 每一個由 "#" 字元開頭嘅行，到最尾係一個註解
#   * 每個非空白行係一個標準表示式碎片，只係會同入面嘅URL端核對

 #</pre> <!-- 請完全噉留番呢行 -->',
	'spam-invalid-lines' => '下面響灌水黑名單嘅{{PLURAL:$1|一行|多行}}有無效嘅表示式，請響保存呢版之前先將{{PLURAL:$1|佢|佢哋}}修正:',
	'spam-blacklist-desc' => '以正規表達式為本嘅防灌水工具: [[MediaWiki:Spam-blacklist]] 同 [[MediaWiki:Spam-whitelist]]', # Fuzzy
);

/** Simplified Chinese (中文（简体）‎)
 * @author Hzy980512
 * @author Liangent
 * @author Linforest
 * @author Liuxinyu970226
 * @author Mys 721tx
 * @author PhiLiP
 * @author Supaiku
 */
$messages['zh-hans'] = array(
	'spam-blacklist' => ' # 跟这个表合符的外部 URL 当加入时会被封锁。
 # 这个表只是会影响到这个wiki；请同时参阅全域黑名单。
 # 要参看注解请看 https://www.mediawiki.org/wiki/Extension:SpamBlacklist
 #<!-- 请完全地留下这行 --> <pre>
#
# 语法像下面这样:
#   * 每一个由 "#" 字元开头的行，到结尾是一个注解
#   * 每个非空白行是一个标准表示式碎片，只是跟里面的URL端核对

 #</pre> <!-- 请完全地留下这行 -->',
	'spam-whitelist' => ' #<!-- 请完整地保留此行 --> <pre>
# 与本列表匹配的外部链接，即使已被黑名单的规则禁止
# 也*不会*被封锁。
#
# 语法如下:
#  * 由“#”字符开头的每行均为注释
#  * 非空白的每行则是正则表达式片段，将只与内含该URL的链接相匹配

 #</pre> <!-- 请完整地保留此行 -->',
	'email-blacklist' => ' # 将会把那些与该列表相匹配的电子邮件地址从注册或发生电子邮件地址当中屏蔽掉。
 # 该列表仅仅影响本维基站点；请另见全局黑名单。
 # 有关文档请参见 https://www.mediawiki.org/wiki/Extension:SpamBlacklist
 #<!-- leave this line exactly as it is --> <pre>
#
# 语法如下：
#   * 从字符"#"开始直至行尾的所有内容称为一条注释
#   * 每个非空白行都是一个regex片段，它将仅仅匹配电子邮件地址当中的主机

 #</pre> <!-- leave this line exactly as it is -->',
	'email-whitelist' => ' #<!-- leave this line exactly as it is --> <pre>
# 在此页面中列出的电子邮件地址即便匹配黑名单中条目也不会被封锁。
#
 #</pre> <!-- leave this line exactly as it is -->
# 格式如下：
#   *注释以#开头并延续到一行末位。
#   *非空白行都是一个匹配电子邮箱地址中主机地址的正则表达式片段。',
	'spam-blacklisted-email' => '黑名单中的电邮地址',
	'spam-blacklisted-email-text' => '您的电子邮件地址目前已被列入黑名单以防止您发送邮件。',
	'spam-blacklisted-email-signup' => '所给电邮地址已被列入黑名单。',
	'spam-invalid-lines' => '下列垃圾链接黑名单有{{PLURAL:$1|一行|多行}}含有无效的正则表示式，请在保存该页前修正之：',
	'spam-blacklist-desc' => '基于正则表达式的反垃圾邮件工具，允许列入黑名单的网页的URL和电子邮件地址注册用户',
	'log-name-spamblacklist' => '垃圾链接黑名单日志',
	'log-description-spamblacklist' => '这个列表跟踪垃圾链接黑名单的触发。',
	'logentry-spamblacklist-hit' => '$1在$3上试图加入$4，触发了垃圾链接黑名单',
	'right-spamblacklistlog' => '查看垃圾邮件黑名单日志',
	'action-spamblacklistlog' => '查看垃圾邮件黑名单日志',
);

/** Traditional Chinese (中文（繁體）‎)
 * @author Liangent
 * @author Liuxinyu970226
 * @author Mark85296341
 * @author Oapbtommy
 * @author Waihorace
 */
$messages['zh-hant'] = array(
	'spam-blacklist' => ' # 跟這個表符合的外部 URL 當加入時會被封鎖。
 # 這個表只是會影響到這個 wiki；請同時參閱全域黑名單。
 # 要參看註解請看 https://www.mediawiki.org/wiki/Extension:SpamBlacklist
 #<!-- 請完全地留下這行 --> <pre>
#
# 語法像下面這樣:
#   * 每一個由「#」字元開頭的行，到結尾是一個註解
#   * 每個非空白行是一個標準表示式碎片，只是跟裡面的 URL 端核對

 #</pre> <!-- 請完全地留下這行 -->',
	'spam-whitelist' => ' #<!-- 請完全地留下這行 --> <pre>
# 跟這個表符合的外部 URL ，即使在黑名單項目中封鎖，
# 都*不會*被封鎖。
#
# 語法像下面這樣:
#   * 每一個由「#」字元開頭的行，到結尾是一個註解
#   * 每個非空白行是一個標準表示式碎片，只是跟裡面的 URL 端核對

 #</pre> <!-- 請完全地留下這行 -->',
	'email-blacklist' => ' # 與本列表匹配的電郵地址將被禁止註冊或發送電郵
 # 本列表只影響本站；另見全域黑名單。
 # 說明文檔在 https://www.mediawiki.org/wiki/Extension:SpamBlacklist
 #<!-- leave this line exactly as it is --> <pre>
#
# 語法如下：
#   * 以字符"#"開始直至行尾的所有内容稱為一條註腳
#   * 每個非空白行都是一個regex片段，它將只匹配電子郵件的主機

 #</pre> <!-- leave this line exactly as it is -->',
	'email-whitelist' => '#<!-- leave this line exactly as it is --> <pre>
 # 和此列表相配的Email 的地址*不會*被阻止，即使它被列入黑名單
 #
  #</pre> <!-- leave this line exactly as it is -->
 # 代號如下所示：
 # ＊ 一切從"#"字符到行末尾是註解
 # ＊ 每個非空白行是一個 regex 部份，將只匹配電郵地址的主機部份',
	'spam-blacklisted-email' => '被列入黑名單的電子郵件地址',
	'spam-blacklisted-email-text' => '您的電郵地址目前已列入黑名單以防止您發送電郵予其他用戶。',
	'spam-blacklisted-email-signup' => '此電郵地址目前被禁止使用。',
	'spam-invalid-lines' => '以下在灌水黑名單的{{PLURAL:$1|一行|多行}}有無效的表示式，請在儲存這頁前先將{{PLURAL:$1|它|它們}}修正：',
	'spam-blacklist-desc' => '以正則表達式為本的防灌水工具：[[MediaWiki:Spam-blacklist]] 與 [[MediaWiki:Spam-whitelist]]', # Fuzzy
	'log-name-spamblacklist' => '垃圾連結黑名單日誌',
	'right-spamblacklistlog' => '查閱垃圾電郵黑名單日誌',
	'action-spamblacklistlog' => '查閱垃圾電郵黑名單日誌',
);
