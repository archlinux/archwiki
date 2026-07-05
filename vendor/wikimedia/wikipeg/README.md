WikiPEG
======

WikiPEG is a recursive descent parser generator for Node.js, intended mostly
to support Parsoid's complex needs. It is a fork of PEG.js with a new backend.

Features
--------

  * Simple and expressive grammar syntax
  * Integrates both lexical and syntactical analysis
  * Parsers have excellent error reporting out of the box
  * Based on [parsing expression grammar](http://en.wikipedia.org/wiki/Parsing_expression_grammar)
    formalism — more powerful than traditional LL(*k*) and LR(*k*) parsers

Installation
------------

### Node.js

To use the `wikipeg` command, install WikiPEG globally:

    $ npm install -g wikipeg

To use the JavaScript API, install WikiPEG locally:

    $ npm install wikipeg

If you need both the `wikipeg` command and the JavaScript API, install WikiPEG both
ways.

Generating a Parser
-------------------

WikiPEG generates parser from a grammar that describes expected input and can
specify what the parser returns (using semantic actions on matched parts of the
input). Generated parser itself is a JavaScript object with a simple API.

### Command Line

To generate a parser from your grammar, use the `wikipeg` command:

    $ wikipeg arithmetics.pegjs

This writes parser source code into a file with the same name as the grammar
file but with “.js” extension. You can also specify the output file explicitly:

    $ wikipeg arithmetics.pegjs arithmetics-parser.js

If you omit both input and output file, standard input and output are used.

By default, the parser object is assigned to `module.exports`, which makes the
output a Node.js module. You can assign it to another variable by passing a
variable name using the `-e`/`--export-var` option. This may be helpful if you
want to use the parser in browser environment.

You can tweak the generated parser with several options:

  * `--cache` — makes the parser cache results, avoiding exponential parsing
    time in pathological cases but making the parser slower. See the
    `cache` option to `PEG.buildParse` and the [Caching](#caching)
    section below.
  * `--allowed-start-rules` — comma-separated list of rules the parser will be
    allowed to start parsing from (default: the first rule in the grammar)
  * `--plugin` — makes WikiPEG use a specified plugin (can be specified multiple
    times)
  * `--extra-options` — additional options (in JSON format) to pass to
    `PEG.buildParser`
  * `--extra-options-file` — file with additional options (in JSON format) to
    pass to `PEG.buildParser`
  * `--trace` — makes the parser trace its progress
  * `--header-comment-file` — file containing a well-formatted comment, used
    to customize the comment at the top of the generated file

### JavaScript API

In Node.js, require the WikiPEG parser generator module:

    var PEG = require("wikipeg");

In browser, include the WikiPEG library in your web page or application using the
`<script>` tag. The API will be available in the `PEG` global object.

To generate a parser, call the `PEG.buildParser` method and pass your grammar as
a parameter:

    var parser = PEG.buildParser("start = ('a' / 'b')+");

The method will return generated parser object or its source code as a string
(depending on the value of the `output` option — see below). It will throw an
exception if the grammar is invalid. The exception will contain `message`
property with more details about the error.

You can tweak the generated parser by passing a second parameter with an options
object to `PEG.buildParser`. The following options are supported:

  * `language` — if set to `"javascript"`, the method will generate parser
     code in JavaScript; if set to `"php"`, it will generate parser code in PHP
     (default: `"javascript"`)
  * `cache` — if `true`, makes the parser cache results, avoiding exponential
    parsing time in pathological cases but making the parser slower (default:
    `false`). See the [Caching](#caching) section below.
  * `allowLoops` — if `true`, disables "infinite loop checking", which
    looks for rules like `""*` which can match an infinite number of
    times. Disabling this check can be helpful if it uncovers false
    positives -- matches which can not be empty for reasons outside
    its analysis.
  * `allowUselessChoice` — if `true`, disables the check for rules
    which "always match" as other than the last element in a choice.
  * `caselessRestrict`  — by default, WikiPEG uses the Unicode "Simple
    Case Folding" algorithm to implement case-insensitive matching.
    If `caselessRestrict` is true, the algorithm is modified to
    prohibit case-insensitive matches between ASCII and non-ASCII
    characters, in the same way that the PCRE CASELESS_RESTRICT
    feature does.
  * `commonLang` — if `true`, performs some simple modifications to
    action clauses to make it possible to write test cases that work
    in both javascript and PHP.
  * `noAlwaysMatch` — if `true`, disables optimization of rules which
     always match.
  * `noInlining` — if `true`, disables inlining of simple character
    classes and repeated character classes. This can be useful if you
    are tracing execution or testing the parser and wish to see every
    rule entry/exit, or need to explicitly manage caching. See
    the [Caching](#caching) section below.
  * `noOptimizeFirstSet` - if `true`, disables an optimization which
    fails early if looking at the first character is sufficient to
    determine that a rule can not match.  This can affect failure
    reporting, since we might be able to fail on a parent rule before
    actually recursing into the child responsible.
  * `optimizePureActions` = if `true`, defaults to enabling a rule
    optimization which skips executing rule action blocks if the
    result of that rule will not be used.  This can be enabled or
    disabled for individual rules via the `[pure]` rule attribute
    described below.
  * `cacheInitHook` and `cacheRuleHook` — functions to generate custom cache
    control code
  * `allowedStartRules` — rules the parser will be allowed to start parsing from
    (default: the first rule in the grammar)
  * `allowedStreamRules` — rules the parser will be allowed to start parsing from
     in asynchronous mode
  * `output` — if set to `"parser"`, the method will return generated parser
    object; if set to `"source"`, it will return parser source code as a string
    (default: `"parser"`)
  * `plugins` — plugins to use

Using the Parser
----------------

Using the generated parser is simple — just call its `parse` method and pass an
input string as a parameter. The method will return a parse result (the exact
value depends on the grammar used to build the parser) or throw an exception if
the input is invalid. The exception will contain `location`, `expected`, `found`
and `message` properties with more details about the error.

    parser.parse("abba"); // returns ["a", "b", "b", "a"]

    parser.parse("abcd"); // throws an exception

You can tweak parser behavior by passing a second parameter with an options
object to the `parse` method. The following options are supported:

  * `startRule` — name of the rule to start parsing from
  * `tracer` — tracer to use

Parsers can also support their own custom options.

Grammar Syntax and Semantics
----------------------------

The grammar syntax is similar to JavaScript in that it is not line-oriented and
ignores whitespace between tokens. You can also use JavaScript-style comments
(`// ...` and `/* ... */`).

Let's look at example grammar that recognizes simple arithmetic expressions like
`2*(3+4)`. A parser generated from this grammar computes their values.

    start
      = additive

    additive
      = left:multiplicative "+" right:additive { return left + right; }
      / multiplicative

    multiplicative
      = left:primary "*" right:multiplicative { return left * right; }
      / primary

    primary
      = integer
      / "(" @additive ")"

    integer "integer"
      = digits:[0-9]+ { return parseInt(digits.join(""), 10); }

On the top level, the grammar consists of *rules* (in our example, there are
five of them). Each rule has a *name* (e.g. `integer`) that identifies the rule,
and a *parsing expression* (e.g. `digits:[0-9]+ { return
parseInt(digits.join(""), 10); }`) that defines a pattern to match against the
input text and possibly contains some JavaScript code that determines what
happens when the pattern matches successfully. A rule can also contain
*human-readable name* that is used in error messages (in our example, only the
`integer` rule has a human-readable name). The parsing starts at the first rule,
which is also called the *start rule*.

A rule name must be a JavaScript identifier. It is followed by an
equals sign (“=”) and a parsing expression. If the rule has additional
attributes, they are written between square brackets (“[” and “]”)
between the rule name and the equals sign; see the “Rule attribute
syntax” section below for more details.

Rules need to be separated only by whitespace (their beginning is easily
recognizable), but a semicolon (“;”) after the parsing expression is allowed.

The first rule can be preceded by an *initializer* — a piece of JavaScript code
in curly braces (“{” and “}”). This code is executed before the generated parser
starts parsing. All variables and functions defined in the initializer are
accessible in rule actions and semantic predicates. The code inside the
initializer can access the parser object using the `parser` variable and options
passed to the parser using the `options` variable. Curly braces in the
initializer code must be balanced. Let's look at the example grammar from above
using a simple initializer.

    {
      function makeInteger(o) {
        return parseInt(o.join(""), 10);
      }
    }

    start
      = additive

    additive
      = left:multiplicative "+" right:additive { return left + right; }
      / multiplicative

    multiplicative
      = left:primary "*" right:multiplicative { return left * right; }
      / primary

    primary
      = integer
      / "(" @additive ")"

    integer "integer"
      = digits:[0-9]+ { return makeInteger(digits); }

The parsing expressions of the rules are used to match the input text to the
grammar. There are various types of expressions — matching characters or
character classes, indicating optional parts and repetition, etc. Expressions
can also contain references to other rules. See detailed description below.

If an expression successfully matches a part of the text when running the
generated parser, it produces a *match result*, which is a JavaScript value. For
example:

  * An expression matching a literal string produces a JavaScript string
    containing matched part of the input.
  * An expression matching repeated occurrence of some subexpression produces a
    JavaScript array with all the matches.
  * An expression matching a sequence of expressions produces a
    JavaScript array with all the picked elements.
    * If no matches are picked, all elements of the sequence will be
      present in the array.
    * If the pick operator (`@`) is used, only those elements which
      are picked will be present.  If only one element is picked, it
      will be returned directly (not wrapped in a 1-element array).

The match results propagate through the rules when the rule names are used in
expressions, up to the start rule. The generated parser returns start rule's
match result when parsing is successful.

One special case of parser expression is a *parser action* — a piece of
JavaScript code inside curly braces (`{` and `}`) that takes match results of
some of the preceding expressions and returns a JavaScript value. This value
is considered match result of the preceding expression (in other words, the
parser action is a match result transformer).

In our arithmetics example, there are many parser actions. Consider the action
in expression `digits:[0-9]+ { return parseInt(digits.join(""), 10); }`. It
takes the match result of the expression `[0-9]+`, which is an array of strings
containing digits, as its parameter. It joins the digits together to form a
number and converts it to a JavaScript `number` object.

### Parsing Expression Types

There are several types of parsing expressions, some of them containing
subexpressions and thus forming a recursive structure:

#### "*literal*"<br>'*literal*'

Match exact literal string and return it. The string syntax is the same as in
JavaScript. Appending `i` right after the literal makes the match
case-insensitive.

#### .

Match exactly one character and return it as a string.

#### [*characters*]

Match one character from a set and return it as a string. The characters in the
list can be escaped in exactly the same way as in JavaScript string. The list of
characters can also contain ranges (e.g. `[a-z]` means “all lowercase letters”).
Preceding the characters with `^` inverts the matched set (e.g. `[^a-z]` means
“all character but lowercase letters”). Appending `i` right after the right
bracket makes the match case-insensitive.

#### *rule*

Match a parsing expression of a rule recursively and return its match result.

#### ( *expression* )

Match a subexpression and return its match result.

#### *expression* \*

Match zero or more repetitions of the expression and return their match results
in an array. The matching is greedy, i.e. the parser tries to match the
expression as many times as possible.

#### *expression* +

Match one or more repetitions of the expression and return their match results
in an array. The matching is greedy, i.e. the parser tries to match the
expression as many times as possible.

#### *expression* ?

Try to match the expression. If the match succeeds, return its match result,
otherwise return `null`.

#### & *expression*

Try to match the expression. If the match succeeds, just return `undefined` and
do not advance the parser position, otherwise consider the match failed.

#### ! *expression*

Try to match the expression. If the match does not succeed, just return
`undefined` and do not advance the parser position, otherwise consider the match
failed.

#### & { *predicate* }

The predicate is a piece of JavaScript code that is executed as if it was inside
a function. It gets the match results of labeled expressions in preceding
expression as its arguments. It should return some JavaScript value using the
`return` statement. If the returned value evaluates to `true` in boolean
context, just return `undefined` and do not advance the parser position;
otherwise consider the match failed.

The code inside the predicate can access all variables and functions defined in
the initializer at the beginning of the grammar.

The code inside the predicate can also access location information using the
`location` function. It returns an object like this:

    {
      start: { offset: 23, line: 5, column: 6 },
      end:   { offset: 23, line: 5, column: 6 }
    }

The `start` and `end` properties both refer to the current parse position. The
`offset` property contains an offset as a zero-based index and `line` and
`column` properties contain a line and a column as one-based indices.

The code inside the predicate can also access the parser object using the
`parser` variable and options passed to the parser using the `options` variable.

Note that curly braces in the predicate code must be balanced.

#### ! { *predicate* }

The predicate is a piece of JavaScript code that is executed as if it was inside
a function. It gets the match results of labeled expressions in preceding
expression as its arguments. It should return some JavaScript value using the
`return` statement. If the returned value evaluates to `false` in boolean
context, just return `undefined` and do not advance the parser position;
otherwise consider the match failed.

The code inside the predicate can access all variables and functions defined in
the initializer at the beginning of the grammar.

The code inside the predicate can also access location information using the
`location` function. It returns an object like this:

    {
      start: { offset: 23, line: 5, column: 6 },
      end:   { offset: 23, line: 5, column: 6 }
    }

The `start` and `end` properties both refer to the current parse position. The
`offset` property contains an offset as a zero-based index and `line` and
`column` properties contain a line and a column as one-based indices.

The code inside the predicate can also access the parser object using the
`parser` variable and options passed to the parser using the `options` variable.

Note that curly braces in the predicate code must be balanced.

#### $ *expression*

Try to match the expression. If the match succeeds, return the matched string
instead of the match result.

#### *label* : *expression*

Match the expression and remember its match result under given label. The label
must be a JavaScript identifier.

Labeled expressions are useful together with actions, where saved match results
can be accessed by action's JavaScript code.

#### *expression<sub>1</sub>* *expression<sub>2</sub>* ...  *expression<sub>n</sub>*

Match a sequence of expressions and return their match results in an array.
Elements of the sequence can be picked by preceding them with the pick
operator (`@`), and only those elements will be returned in the array.
If only one element is picked, it is returned directly (not wrapped in
an array).

#### @ *expression*

Pick the specified expression in a sequence to return.  See the
description of a sequence expression above.

Note that sequences with pick operators can be nested, for example:

    foo = @"a" @("b" @"c" "d") "e"

will return `["a", "c"]` if it matches.

#### *expression* { *action* }

Match the expression. If the match is successful, run the action, otherwise
consider the match failed.

The action is a piece of JavaScript code that is executed as if it was inside a
function. It gets the match results of labeled expressions in preceding
expression as its arguments. The action should return some JavaScript value
using the `return` statement. This value is considered match result of the
preceding expression.

To indicate an error, the code inside the action can invoke the `expected`
function, which makes the parser throw an exception. The function takes one
parameter — a description of what was expected at the current position. This
description will be used as part of a message of the thrown exception.

The code inside an action can also invoke the `error` function, which also makes
the parser throw an exception. The function takes one parameter — an error
message. This message will be used by the thrown exception.

The code inside the action can access all variables and functions defined in the
initializer at the beginning of the grammar. Curly braces in the action code
must be balanced.

The code inside the action can also access the string matched by the expression
using the `text` function.


The code inside the action can also access location information using the
`location` function. It returns an object like this:

    {
      start: { offset: 23, line: 5, column: 6 },
      end:   { offset: 25, line: 5, column: 8 }
    }

The `start` property refers to the position at the beginning of the expression,
the `end` property refers to position after the end of the expression. The
`offset` property contains an offset as a zero-based index and `line` and
`column` properties contain a line and a column as one-based indices.

The code inside the action can also access the parser object using the `parser`
variable and options passed to the parser using the `options` variable.

Note that curly braces in the action code must be balanced.

#### *expression<sub>1</sub>* / *expression<sub>2</sub>* / ... / *expression<sub>n</sub>*

Try to match the first expression, if it does not succeed, try the second one,
etc. Return the match result of the first successfully matched expression. If no
expression matches, consider the match failed.

Rule attribute syntax
---------------------
WikiPEG supports attaching attributes to rules which can affect their
behavior.  The syntax is:

    rule1 [attr1, attr2=false, attr3="string", ...] = nonterminal1 ... ;

That is, attributes are comma-separated between square brackets
between the rule name and the equals sign.  Attributes can have
boolean, string, or integer values.  An attribute without a value
is treated as shorthand for setting it to boolean `true`.

The following attributes affect parsing:

#### [name="*rule name*"]

Provide a human-readable *rule name* for this rule.  For example, this
production:

    integer [name="simple number"] = [0-9]+

will produce an error message like:

    Expected simple number but "a" found.

when parsing a non-number, referencing the human-readable name "simple
number".  Without the human-readable name, WikiPEG uses a description
of the character class that failed to match:

    Expected [0-9] but "a" found.

Aside from the content of error messages, providing a `name` attribute
also affects *where* errors are reported, preferring to report failure
at the named rule instead of inside it.

#### [inline] *or* [inline=true]

Forces inlining of the given rule, regardless of the status of the
`noInlining` option.

#### [inline=false]

Prevents inlining of the given rule.

#### [cache] *or* [cache=true]

Turns on caching for the given rule, regardless of the status of the
top-level `cache` option. This can be useful for enabling caching
only on a few rules while leaving it mostly disabled.

If caching is disabled in the top-level WikiPEG options but any rule
has this attribute set to `true`, then caching will be enabled but all
rules will default to `[cache=false]`.

If caching is enabled in the WikiPEG options, then `[cache]` is
effectively a no-op, since the default is to cache all rules.

#### [cache=false]

Turns off caching for the given rule, regardless of the status of the
top-level `cache` option. This can be useful for selectively disabling
caching on a few rules while leaving it mostly enabled.

If caching is disabled in the top-level WikiPEG options, this is
effectively a no-op.

If caching is enabled in the top-level WikiPEG options, this will
prevent the given rule from being cached.

#### [empty=false]

Marks a node as non-nullable; that is, asserts that it cannot match
the empty string -- usually because of some predicate expression in
the rule which is beyond WikiPEG's ability to analyze.  This can
prevent false positives when WikiPEG checks for infinite loops.

#### [unreachable]

Marks a rule as unreachable. If the `allowUselessChoice` option is
false, this attribute permits a reference to the rule in a choice even
if a previous option in the choice appears to always match.

#### [pure]

Marks a rule as not having side effects in its action blocks.  This
allows action blocks in rules which have this attribute set to be
skipped (not executed) when the value of the rule is not needed.

#### [pure=false]

Marks a rule as having side effects in its action blocks.  If the
`optimizePureActions` option is given to the parser, this ensures that
the action blocks for this rule are executed even if the value of
the rule is not needed.

Rule parameter syntax
---------------------

WikiPEG supports passing parameters to rules. This is an extension compared to
PEG.js.

All parameters referenced in the grammar have an initial value and can
be used before their first assignment.

Parameters have a type detected at compile time: boolean, integer,
string, reference, or labeled expression. Initial values for each type are:
  - boolean: false
  - integer: 0
  - string: ""
  - reference: null
  - labeled expression: null (or the integer or string default value)

The parameter namespace is global, but the value of a parameter reverts
to its previous value after termination of the assigning rule reference.

The syntax is as follows:

#### & < *parameter* >

Assert that the parameter "x" is true or nonzero

#### ! < *parameter* >

Assert that the parameter "x" is false or zero

#### & < *parameter* == *value* >

Assert that the parameter "x" has the specified value.

#### ! < *parameter* == *value* >
#### & < *parameter* != *value* >

Assert that the parameter "x" does not have the specified value.

#### *rule* < *parameter* = true >

Match a parsing expression of a rule recursively, and assign *parameter* to
boolean true in *rule* and its callees.

#### *rule* < *parameter* = false >

Assign parameter "x" to false.

#### *rule* < *parameter* >

Shortcut for rule<parameter=true>.

#### *rule* < *parameter* = 0 >

Integer assignment.

#### *rule* < *parameter* ++ >

Assign x = x + 1.

#### *rule* < *parameter* = "*literal*" >

String assignment.

#### *rule* < *parameter* = $*label* >

Assign the value of the given labeled expression to the parameter.

Labeled expression parameters can't be intermixed with boolean
assignments to the same parameter, but they can co-exist with
integer or string assignments to the parameter.

#### *rule* < & *parameter* = 1 >

Create a reference (in/out) parameter and give it an initial value of 1.

Note that it is illegal to assign to a reference parameter using non-reference
syntax.

#### *variable* : < *parameter* >

Expose value of parameter "x" as variable "v" in JS action or predicate code.

The value of a reference parameter can be exposed to JS as an ordinary rvalue
in this way: assigning to it will have no effect outside the action in question.

#### *variable* : < & *parameter* >

In JS this will expose the reference parameter "r" as an object with r.set(),
r.get(). In PHP it will be a native reference such that {$r = 1;} will set
the value of the reference in the declaration scope.

Caching
-------
Note that caching makes PEG grammars behave somewhat differently from
recursive descent parsers.  Consider the grammar:

    start = "a" long_complicated_thing b
          / "a" long_complicated_thing c
          / "a" long_complicated_thing
    
    // this could be any costly rule, but this is the simplest example
    // which will take time proportional to the file length
    long_complicated_thing = $[^]*
    b = "b"
    c = "c"

Without caching, the generated parser will match `"a"`, then scan the
entire length of the string matching `long_complicated_thing`, then
match the end-of-file to `"b"` and fail, return to the start of the
string and do it again (scanning the entire length of the string),
fail to match `"c"` and so on.

When caching is enabled, the second time we try to match
`long_complicated_thing` at position 2 in the string it will recognize
that it has tried exactly this parse before and return the previous
match from the cache.  This takes constant time instead of time
proportional to the input string length.  This can be quite
significant in a grammar that involves a lot of backtracking.

There are some caveats, however!

First, caching is relatively expensive, so it is only done at rule
boundaries, like `long_complicated_thing`, `b`, and `c` above.  This
is a departure from a "theoretical" packrat parser.

Second, the memoization cache stores an entry for every nonterminal at
every position is it attempted *whether the result is success or
failure*.  In our example we allocate memory for cache entries for "b"
and "c" even though they do not match. Writing rules which match
single characters can easily result in excessive memory use if care is
not taken.

Consider two alterations to our example above.  First, consider inlining the
`long_complicated_thing` rule like so:

    start = "a" $[^]* "b"
          / "a" $[^]* "c"
          / "a" $[^]*

The grammar would then match exactly the same strings as before, but
we would do no caching and each of the choice branches would scan to
the end of the string.

Alternatively, if we just moved the zero-or-more repetition operator
like so:

    start = "a" $long_complicated_thing* b
          / "a" $long_complicated_thing* c
          / "a" $long_complicated_thing*
    
    long_complicated_thing = [^]
    b = "b"
    c = "c"

Now not only have we broken caching (each choice will scan to the
end of the input string, matching long_complicated_thing as it goes)
we're also going to allocate a cache entry for every character in the
input string.  This can cause ballooning memory requirements for what
look like simple inputs.

By default wikipeg inlines "simple expressions", which are rules that
match simple literals, character classes, or repeated character
classes, possibly prefixed with the `$` operator.  This is primarily
done to manage the memory cost of excessive caching of simple matches.
For more predictable caching, you may wish to use the `noInlining`
option.

Requirements
-------------

* Node.js 6 or later

Development
-----------

Development occurs in the "wikipeg" project in [Wikimedia's Gerrit](https://www.mediawiki.org/wiki/Gerrit).

Bugs should be reported to [Wikimedia's Phabricator](https://phabricator.wikimedia.org/)

WikiPEG is a derivative of PEG.js by David Majda.
