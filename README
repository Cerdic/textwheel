##summary TextWheel overview

This is a preliminary proposal for an interoperability layer for all the different text engines that have been floating for years now around the Web.

It is *not* a proposal for a unified syntax (though it might help it happen at some point).


= Rationale =

Most text engines seem keen on reinventing the wheel, each time with a different flavor and style. But they all basically do the same stuff: take a text as an input, and output formatted text according to rules that are applied in sequence.

For example while Textile does this:
<code>
  @define('txt_registered', '&#174;');
  @define('txt_copyright', '&#169;');
../..
        $this->glyph = array(
../..
  'registered' => txt_registered,
  'copyright' => txt_copyright,
);
</code>

PHP-Typography does this:
<code>
  $this->chr["copyright"] = $this->uchr(169);
  $this->chr["registeredMark"] = $this->uchr(174);
</code>



Again, when Drupal does "prepare paragraphs" with:
<code>
  $chunk = preg_replace('|\n*$|', '', $chunk) ."\n\n"; // just to make things a little easier, pad the end
  $chunk = preg_replace('|<br />\s*<br />|', "\n\n", $chunk);
  $chunk = preg_replace('!(<'. $block .'[^>]*>)!', "\n$1", $chunk); // Space things out a little
  $chunk = preg_replace('!(</'. $block .'>)!', "$1\n\n", $chunk); // Space things out a little
  $chunk = preg_replace("/\n\n+/", "\n\n", $chunk); // take care of duplicates
  $chunk = preg_replace('/\n?(.+?)(?:\n\s*\n|\z)/s', "<p>$1</p>\n", $chunk); // make paragraphs, including one at the end
  $chunk = preg_replace('|<p>\s*</p>\n|', '', $chunk); // under certain strange conditions it could create a P of entirely whitespace
  $chunk = preg_replace("|<p>(<li.+?)</p>|", "$1", $chunk); // problem with nested lists
  $chunk = preg_replace('|<p><blockquote([^>]*)>|i', "<blockquote$1><p>", $chunk);
</code>

SPIP does it with:
<code>
  $letexte = preg_replace(',(<p\b.*>)\s*,UiS'.$u, '\1',$letexte);
  $letexte = preg_replace(',\s*(</p\b.*>),UiS'.$u, '\1',$letexte);
  $letexte = preg_replace(',<p\b[^<>]*></p>\s*,iS'.$u, '', $letexte);
  $letexte = str_replace('<p >', "<p$class_spip>", $letexte);
</code>


OK, you got it... and this is the same over and over, _ad nauseam._ Paragraph formatting, XSS security clearance, emphasis, links, images, and so on. Every project seems to be writing its own lists of sometimes less-than-optimal code to do the same stuff.

~

I propose everyone does this:

<code>
require_once 'path/to/textwheel.php';
$ruleset[] = array(
  'match' => '(C)',
  'replace' => '&#169;'
);
$ruleset[] = array(
  'match' => '(R)',
  'replace' => '&#174;'
);

$textwheel = new TextWheel($ruleset);
$text = $textwheel->text($text);
</code>

OK now, what's going to be boring is that, at some point, someone will have to rewrite all those engines into lists of rules. But then lists of rules will be _conventional_ and _description-based_. And this will mean a lot in terms of simplicity and interoperability.



= Rules & Regulations =


Regulation *#1. TextWheel is agnostic*

By itself TextWheel does not contain {any} rule and does not favor any "shortcut syntax" over the other. The engine ships with no base ruleset (i.e., does nothing). Give it a ruleset, and it does apply all rules in sequence, as fast as possible.


Regulation *#2. A good ruleset is autonomous*

If you want to port an existing text engine to TextWheel, please try and make sure it can run independently of your application. If the ruleset needs a library, include the library. If it needs so many libraries that you need to include your whole application, then... maybe it's time to rethink your dependency model.

Rulesets can be distributed alongside TextWheel when they are autonomous.


= How to start =

The simplest way to integrate your engine with TextWheel is the following:
<code>
require_once 'path/to/textwheel.php';
$ruleset[] = array(
  'match' => '/.*/',
  'replace' => 'return myengine($m[0]);',
  'is_callback' => true,
  'create_replace' => true
);
$textwheel = new TextWheel($ruleset);
$text = $textwheel->text($text);
</code>

which means "just call my function on the whole text". This might be a first step.


= Code examples =

*0. my ruleset =

<code>
$ruleset = array(
  array(
    'type' => 'str',
    'match' => 'aa',
    'replace' => 'bb',
    'if_chars' => 'a'
  ),
  array(
    'match' => '/\s+/S',
    'replace' => 'return strlen($m[0]);',
    'if_chars' => ' ',
    'create_replace' => true,
    'is_callback' => true
  )
);
</code>

This ruleset has two rules.

The first one does a <code>str_replace</code> transforming all <code>'aa'</code> substrings to <code>'bb'</code>. It is applied only if the character <code>'a'</code> is present in the text.

The second one will apply only if the text contains a space character. If so, a replacement function will be created on the fly, then there will be a <code>preg_replace_callback()</code> on the <code>'/\s+/S</code> expression (any sequence of spaces), call the newliy created function (which replaces a the matched substring by its character length).


*1. procedural call*

<code>
function verynice($text) {
  static $wheel;
  if (!isset($wheel)) {
    $wheel = new TextWheel(ruleset());
  }
  return $wheel->text($text);
}
var_dump(verynice('ab   baa z '));
</code>

As you might have guessed, both calls will output: <code>string(9) "ab3bbb1z1"</code>.

*2. object call*

<code>
class VeryNice extends TextWheel {
  function VeryNice() {
    $this->addRules(ruleset());
  }
}
$wheel = new VeryNice();
var_dump($wheel->text('ab   baa z '));
</code>


= API and Options =

- Before compiling and applying a complex regular expression, a rule can check if it's needed, with a (much) simpler expression check.
- Pattern matching can be done by <code>str_replace()</code> or <code>preg_match()</code>.
-  Replacements can be done with string expressions or with callback functions.
- Callback functions can be created on the fly as they are needed.

OK let's have a look at the API:

A rule can be defined by a <code>TextWheelRule</code> object instance (but an array with the same named properties is fine):

<code>
class TextWheelRule {

  ## rule description
  # optional
  var $priority = 0; # rule priority (rules are applied in ascending order)
    # -100 = application escape, +100 = application unescape
  var $disabled=false; # true if rule is disabled

  ## rule init checks
  ## the rule will be applied if the text...
  # optional
  var $if_chars; # ...contains one of these chars
  var $if_str; # ...contains this string
  var $if_match; # ...matches this simple expr

  ## rule effectors, matching
  # mandatory
  var $type='str'; # str or preg match
  var $match; # matching string or expression

  ## rule effectors, replacing
  # mandatory
  var $replace; # replace match with this expression
  var $is_callback=false; # $replace is a callback function
  # optional
  var $require; # file to require_once
  var $create_replace; # do create_function('$m', %) on $this->replace, $m is the matched array
}
</code>

See the actual code for the full version. An open question is what to do with the <code>author</code>, <code>package</code>, <code>version</code>... fields of the rule object. For the moment I have no fixed idea on this issue.


~

The <code>TextWheel</code> class offer the following API:

<code>
class TextWheel {
  var $rules = array();
  public function TextWheel($ruleset = array()) {}
  public function text($t) {}
  public function addRule($rule) {}
  public function addRules(array $rules) {}
}
</code>

