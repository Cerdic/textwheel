<?php

/*
 * TextWheel 0.1
 *
 * let's reinvent the wheel one last time
 *
 * This library of code is meant to be a fast and universal replacement
 * for any and all text-processing systems written in PHP
 *
 * It is dual-licensed for any use under the GNU/GPL2 and MIT licenses,
 * as suits you best
 *
 * (c) 2009 Fil - fil@rezo.net
 * Documentation & http://zzz.rezo.net/-TextWheel-
 *
 * Usage: $wheel = new TextWheel(); echo $wheel->text($text);
 *
 */

class TextWheelRule {

	## rule description
	# optional
	var $priority = 0; # rule priority (rules are applied in ascending order)
		# -100 = application escape, +100 = application unescape
	var $name; # rule's name
	var $author; # rule's author
	var $url; # rule's homepage
	var $package; # rule belongs to package
	var $version; # rule version
	var $test; # rule test function
	var $disabled=false; # true if rule is disabled

	## rule init checks
	## the rule will be applied if the text...
	# optional
	var $if_chars; # ...contains one of these chars
	var $if_str; # ...contains this string (case insensitive)
	var $if_match; # ...matches this simple expr


	## rule effectors, matching
	# mandatory
	var $type; # 'preg' (default), 'str', 'all', 'split'...
	var $match; # matching string or expression
	# optional
	# var $limit; # limit number of applications (unused)

	## rule effectors, replacing
	# mandatory
	var $replace; # replace match with this expression

	# optional
	var $is_callback=false; # $replace is a callback function
	var $is_wheel; # flag to create a sub-wheel from rules given as replace
	var $pick_match = 0; # item to pick for sub-wheel replace

	# optional
	# language specific
	var $require; # file to require_once
	var $create_replace; # do create_function('$m', %) on $this->replace, $m is the matched array

	# optimizations
	var $func_replace;
	
	public function TextWheelRule($args) {
		if (!is_array($args))
			return;
		foreach($args as $k=>$v)
			if (property_exists($this, $k))
				$this->$k = $args[$k];
	}
}

abstract class TextWheelDataSet {
	# list of data
	protected $data = array();
	
	/**
	 * Load a yaml file describing data
	 * @param string $file
	 * @return array
	 */
	protected function loadFile(&$file, $default_path='') {
		if (!$default_path)
			$default_path = dirname(__FILE__).'/../wheels/';
		if (!preg_match(',[.]yaml$,i',$file)
			// external rules
			OR
				(!file_exists($file)
				// rules embed with texwheels
				AND !file_exists($file = $default_path.$file)
				)
			)
			return array();

		$data = false;
		// yaml caching
		if (defined('_TW_DIR_CACHE_YAML')
			AND $hash = substr(md5($file),0,8)."-".substr(md5_file($file),0,8)
			AND $fcache = _TW_DIR_CACHE_YAML."yaml-".basename($file,'.yaml')."-".$hash.".txt"
			AND file_exists($fcache)
			AND $c = file_get_contents($fcache)
			)
			$data = unserialize($c);

		if (!$data){
			require_once dirname(__FILE__).'/../lib/yaml/sfYaml.php';
			$data = sfYaml::load($file);
		}

		if (!$data)
			return array();

		// if a php file with same name exists
		// include it as it contains callback functions
		if ($f = preg_replace(',[.]yaml$,i','.php',$file)
		  AND file_exists($f))
			include_once $f;

		if ($fcache AND !$c)
		 file_put_contents ($fcache, serialize($data));
		
		return $data;
	}

}

class TextWheelRuleSet extends TextWheelDataSet {
	# sort flag
	protected $sorted = true;

	/**
	 * Constructor
	 *
	 * @param array/string $ruleset
	 */
	public function TextWheelRuleSet($ruleset = array(), $filepath='') {
		if ($ruleset)
			$this->addRules($ruleset, $filepath);
	}

	/**
	 * Get an existing named rule in order to override it
	 *
	 * @param string $name
	 * @return string
	 */
	public function &getRule($name){
		if (isset($this->data[$name]))
			return $this->data[$name];
		$result = null;
		return $result;
	}
	
	/**
	 * get sorted Rules
	 * @return array
	 */
	public function &getRules(){
		$this->sort();
		return $this->data;
	}

	/**
	 * add a rule
	 *
	 * @param TextWheelRule $rule
	 */
	public function addRule($rule) {
		# cast array-rule to object
		if (is_array($rule))
			$rule = new TextWheelRule($rule);
		$this->data[] = $rule;
		$this->sorted = false;
	}

	/**
	 * add an list of rules
	 * can be
	 * - an array of rules
	 * - a string filename
	 * - an array of string filename
	 *
	 * @param array/string $rules
	 */
	public function addRules($rules, $filepath='') {
		// rules can be an array of filename
		if (is_array($rules) AND is_string(reset($rules))) {
			foreach($rules as $i=>$filename)
				$this->addRules($filename);
			return;
		}

		// rules can be a string : yaml filename
		if (is_string($rules)) {
			$file = $rules; // keep the real filename
			$rules = $this->loadFile($file, $filepath);
			$filepath = dirname($file).'/';
		}

		// rules can be an array of rules
		if (is_array($rules) AND count($rules)){
			# cast array-rules to objects
			foreach ($rules as $i => $rule) {
				if (is_array($rule))
					$rules[$i] = new TextWheelRule($rule);
				// load subwheels when necessary
				if ($rules[$i]->is_wheel){
					$rules[$i]->replace = new TextWheelRuleSet($rules[$i]->replace, $filepath);
				}
			}
			$this->data = array_merge($this->data, $rules);
			$this->sorted = false;
		}
	}

	/**
	 * Sort rules according to priority and
	 * purge disabled rules
	 *
	 */
	protected function sort() {
		if (!$this->sorted) {
			$rulz = array();
			foreach($this->data as $index => $rule)
				if (!$rule->disabled)
					$rulz[intval($rule->priority)][$index] = $rule;
			ksort($rulz);
			$this->data = array();
			foreach($rulz as $rules)
				$this->data += $rules;

			$this->sorted = true;
		}
	}
}

class TextWheel {
	protected $ruleset;
	protected static $subwheel = array();

	/**
	 * Constructor
	 * @param TextWheelRuleSet $ruleset
	 */
	public function TextWheel($ruleset = null) {
		$this->setRuleSet($ruleset);
	}

	/**
	 * Set RuleSet
	 * @param TextWheelRuleSet $ruleset
	 */
	public function setRuleSet($ruleset){
		if (!is_object($ruleset))
			$ruleset = new TextWheelRuleSet ();
		$this->ruleset = $ruleset;
	}

	/**
	 * Apply all rules of RuleSet to a text
	 *
	 * @param string $t
	 * @return string
	 */
	public function text($t) {
		$rules = & $this->ruleset->getRules();
		## apply each in order
		foreach ($rules as $name => $rule) #php4+php5
		{
			$this->apply($rules[$name], $t);
		}
		#foreach ($this->rules as &$rule) #smarter &reference, but php5 only
		#	$this->apply($rule, $t);
		return $t;
	}

	public function compile() {
		$rules = & $this->ruleset->getRules();

		## apply each in order
		$comp = array();

		foreach ($rules as $name => $rule)
		{
			$this->initRule($rule);
			$r = "/* $name */\n";

			if ($rule->if_str)
				$r .= 'if_str('.var_export($rule->if_str, true).', $t)'."\n";
			if ($rule->if_stri)
				$r .= 'if_stri('.var_export($rule->if_stri, true).', $t)'."\n";
			if ($rule->if_match)
				$r .= 'if_match('.var_export($rule->if_match, true).', $t)'."\n";

			$fun = 'TextWheel::'.$rule->func_replace;
			$r .= '$t = '.$fun.'('.var_export($rule->match, true).', '.var_export($rule->replace, true).', $t);'."\n";
			$comp[] = $r;
		}
		return join ("\n", $funcs) . join ("\n", $comp);
	}


	/**
	 * Get an internal global subwheel
	 * read acces for annymous function only
	 *
	 * @param int $n
	 * @return TextWheel
	 */
	public static function &getSubWheel($n){
		return TextWheel::$subwheel[$n];
	}

	/**
	 * Create SubWheel (can be overriden in debug class)
	 * @param TextWheelRuleset $rules
	 * @return TextWheel
	 */
	protected function &createSubWheel(&$rules){
		return new TextWheel($rules);
	}
	
	/**
	 * Initializing a rule a first call
	 * including file, creating function or wheel
	 * optimizing tests
	 *
	 * @param TextWheelRule $rule
	 */
	protected function initRule(&$rule){

		# language specific
		if ($rule->require)
			require_once $rule->require;

		# optimization: strpos or stripos?
		if (isset($rule->strpos)) {
			if (strtolower($rule->if_str) !== strtoupper($rule->if_str)) {
				$rule->if_stri = $rule->if_str;
				unset($rule->if_str);
			}
		}

		if ($rule->create_replace){
			$rule->replace = create_function('$m', $rule->replace);
			$rule->create_replace = false;
			$rule->is_callback = true;
		}
		elseif ($rule->is_wheel){
			$n = count(TextWheel::$subwheel);
			TextWheel::$subwheel[] = $this->createSubWheel($rule->replace);
			$var = '$m['.intval($rule->pick_match).']';
			if ($rule->type=='all' OR $rule->type=='str' OR $rule->type=='split' OR !isset($rule->match))
				$var = '$m';
			$code = 'return TextWheel::getSubWheel('.$n.')->text('.$var.');';
			$rule->replace = create_function('$m', $code);
			$rule->is_wheel = false;
			$rule->is_callback = true;
		}

		# optimization
		$rule->func_replace = '';
		if (isset($rule->replace)) {
			switch($rule->type) {
				case 'all':
					$rule->func_replace = 'replace_all';
					break;
				case 'str':
					$rule->func_replace = 'replace_str';
					// test if quicker strtr usable
					if (!$rule->is_callback
						AND is_array($rule->match) AND is_array($rule->replace)
						AND $c = array_map('strlen',$rule->match) 
						AND $c = array_unique($c)
						AND count($c)==1
						AND reset($c)==1
						AND $c = array_map('strlen',$rule->replace)
						AND $c = array_unique($c)
						AND count($c)==1
						AND reset($c)==1
						){
						$rule->match = implode('',$rule->match);
						$rule->replace = implode('',$rule->replace);
						$rule->func_replace = 'replace_strtr';
					}
					break;
				case 'split':
					$rule->func_replace = 'replace_split';
					break;
				case 'preg':
				default:
					$rule->func_replace = 'replace_preg';
					break;
			}
			if ($rule->is_callback)
				$rule->func_replace .= '_cb';
		}
		if (!method_exists("TextWheel", $rule->func_replace)){
			$rule->disabled = true;
			$rule->func_replace = 'replace_identity';
		}
		# /end
	}

	/**
	 * Apply a rule to a text
	 *
	 * @param TextWheelRule $rule
	 * @param string $t
	 * @param int $count
	 */
	protected function apply(&$rule, &$t, &$count=null) {

		if ($rule->disabled)
			return;

		if (isset($rule->if_chars) AND (strpbrk($t, $rule->if_chars) === false))
			return;

		if (isset($rule->if_str) AND strpos($t, $rule->if_str) === false)
			return;

		if (isset($rule->if_stri) AND stripos($t, $rule->if_str) === false)
			return;

		if (isset($rule->if_match) AND !preg_match($rule->if_match, $t))
			return;

		if (!isset($rule->func_replace))
			$this->initRule($rule);

		$func = $rule->func_replace;
		TextWheel::$func($rule->match,$rule->replace,$t,$count);
	}

	/**
	 * No Replacement function
	 * fall back in case of unknown method for replacing
	 * should be called max once per rule
	 * 
	 * @param mixed $match
	 * @param mixed $replace
	 * @param string $t
	 * @param int $count
	 */
	protected static function replace_identity(&$match,&$replace,&$t,&$count){
	}

	/**
	 * Static replacement of All text
	 * @param mixed $match
	 * @param mixed $replace
	 * @param string $t
	 * @param int $count
	 */
	protected static function replace_all(&$match,&$replace,&$t,&$count){
		# special case: replace \0 with $t
		#   replace: "A\0B" will surround the string with A..B
		#   replace: "\0\0" will repeat the string
		if (strpos($replace, '\\0')!==FALSE)
			$t = str_replace('\\0', $t, $replace);
		else
			$t = $replace;
	}

	/**
	 * Call back replacement of All text
	 * @param mixed $match
	 * @param mixed $replace
	 * @param string $t
	 * @param int $count
	 */
	protected static function replace_all_cb(&$match,&$replace,&$t,&$count){
		$t = $replace($t);
	}

	/**
	 * Static string replacement
	 *
	 * @param mixed $match
	 * @param mixed $replace
	 * @param string $t
	 * @param int $count
	 */
	protected static function replace_str(&$match,&$replace,&$t,&$count){
		if (!is_string($match) OR strpos($t,$match)!==FALSE)
			$t = str_replace($match, $replace, $t, $count);
	}

	/**
	 * Fast Static string replacement one char to one char
	 *
	 * @param mixed $match
	 * @param mixed $replace
	 * @param string $t
	 * @param int $count
	 */
	protected static function replace_strtr(&$match,&$replace,&$t,&$count){
		$t = strtr( $t, $match, $replace);
	}

	/**
	 * Callback string replacement
	 *
	 * @param mixed $match
	 * @param mixed $replace
	 * @param string $t
	 * @param int $count
	 */
	protected static function replace_str_cb(&$match,&$replace,&$t,&$count){
		if (strpos($t,$match)!==FALSE)
			if (count($b = explode($match, $t)) > 1)
				$t = join($replace($match), $b);
	}

	/**
	 * Static Preg replacement
	 *
	 * @param mixed $match
	 * @param mixed $replace
	 * @param string $t
	 * @param int $count
	 */
	protected static function replace_preg(&$match,&$replace,&$t,&$count){
		$t = preg_replace($match, $replace, $t, -1, $count);
	}

	/**
	 * Callback Preg replacement
	 * @param mixed $match
	 * @param mixed $replace
	 * @param string $t
	 * @param int $count
	 */
	protected static function replace_preg_cb(&$match,&$replace,&$t,&$count){
		$t = preg_replace_callback($match, $replace, $t, -1, $count);
	}

	/**
	 * Callback split replacement
	 * @param mixed $match
	 * @param mixed $replace
	 * @param string $t
	 * @param int $count
	 */
	protected static function replace_split_cb(&$match,&$replace,&$t,&$count){
		$a = explode($match, $t);
		foreach ($a as $i=>$b)
			$a[$i] = $replace($b);
		$t = join($match, $a);
	}
}

class TextWheelDebug extends TextWheel {
	static protected $t; #tableaux des temps
	static protected $tu; #tableaux des temps (rules utilises)
	static protected $tnu; #tableaux des temps (rules non utilises)
	static protected $u; #compteur des rules utiles
	static protected $w; #compteur des rules appliques
	static $total;

	/**
	 * Timer for profiling
	 * 
	 * @staticvar int $time
	 * @param string $t
	 * @param bool $raw
	 * @return int/strinf
	 */
	protected function timer($t='rien', $raw = false) {
		static $time;
		$a=time(); $b=microtime();
		// microtime peut contenir les microsecondes et le temps
		$b=explode(' ',$b);
		if (count($b)==2) $a = end($b); // plus precis !
		$b = reset($b);
		if (!isset($time[$t])) {
			$time[$t] = $a + $b;
		} else {
			$p = ($a + $b - $time[$t]) * 1000;
			unset($time[$t]);
			if ($raw) return $p;
			if ($p < 1000)
				$s = '';
			else {
				$s = sprintf("%d ", $x = floor($p/1000));
				$p -= ($x*1000);
			}
			return $s . sprintf("%.3f ms", $p);
		}
	}

	/**
	 * Apply all rules of RuleSet to a text
	 *
	 * @param string $t
	 * @return string
	 */
	public function text($t) {
		$rules = & $this->ruleset->getRules();
		## apply each in order
		foreach ($rules as $name => $rule) #php4+php5
		{
			if (is_int($name))
				$name .= ' '.$rule->match;
			$this->timer($name);
			$b = $t;
			$this->apply($rule, $t);
			TextWheelDebug::$w[$name] ++; # nombre de fois appliquee
			$v = $this->timer($name, true); # timer
			TextWheelDebug::$t[$name] += $v;
			if ($t !== $b) {
				TextWheelDebug::$u[$name] ++; # nombre de fois utile
				TextWheelDebug::$tu[$name] += $v;
			} else {
				TextWheelDebug::$tnu[$name] += $v;
			}
			
		}
		#foreach ($this->rules as &$rule) #smarter &reference, but php5 only
		#	$this->apply($rule, $t);
		return $t;
	}

	/**
	 * Ouputs data stored for profiling/debuging purposes
	 */
	public static function outputDebug(){
		if (isset(TextWheelDebug::$t)) {
			$time = array_flip(array_map('strval', TextWheelDebug::$t));
			krsort($time);
			echo "
			<div class='textwheeldebug'>
			<style type='text/css'>
				.textwheeldebug table { margin:1em 0; }
				.textwheeldebug th,.textwheeldebug td { padding-left: 15px }
				.textwheeldebug .prof-0 .number { padding-right: 60px }
				.textwheeldebug .prof-1 .number { padding-right: 30px }
				.textwheeldebug .prof-1 .name { padding-left: 30px }
				.textwheeldebug .prof-2 .name { padding-left: 60px }
				.textwheeldebug .zero { color:orange; }
				.textwheeldebug .number { text-align:right; }
				.textwheeldebug .strong { font-weight:bold; }
			</style>
			<table class='sortable'>
			<caption>Temps par rule</caption>
			<thead><tr><th>temps&nbsp;(ms)</th><th>rule</th><th>application</th><th>t/u&nbsp;(ms)</th><th>t/n-u&nbsp;(ms)</th></tr></thead>\n";
			foreach($time as $t => $r) {
				$applications = intval(TextWheelDebug::$u[$r]);
				$total += $t;
				if(intval($t*10))
					echo "<tr>
					<td class='number strong'>".number_format(round($t*10)/10,1)."</td><td> ".htmlspecialchars($r)."</td>
					<td"
					. (!$applications ? " class='zero'" : "")
					.">".$applications."/".intval(TextWheelDebug::$w[$r])."</td>
					<td class='number'>".($applications?number_format(round(TextWheelDebug::$tu[$r]/$applications*100)/100,2):"") ."</td>
					<td class='number'>".(($nu = intval(TextWheelDebug::$w[$r])-$applications)?number_format(round(TextWheelDebug::$tnu[$r]/$nu*100)/100,2):"") ."</td>
					</tr>";
			}
			echo "</table>\n";

			echo "
			<table>
			<caption>Temps total par rule</caption>
			<thead><tr><th>temps</th><th>rule</th></tr></thead>\n";
			ksort($GLOBALS['totaux']);
			TextWheelDebug::outputTotal($GLOBALS['totaux']);
			echo "</table>";
			# somme des temps des rules, ne tient pas compte des subwheels
			echo "<p>temps total rules: ".round($total)."&nbsp;ms</p>\n";
			echo "</div>\n";
		}
	}

	public static function outputTotal($liste, $profondeur=0) {
		ksort($liste);
		foreach ($liste as $cause => $duree) {
			if (is_array($duree)) {
				TextWheelDebug::outputTotal($duree, $profondeur+1);
			} else {
				echo "<tr class='prof-$profondeur'>
					<td class='number'><b>".intval($duree)."</b>&nbsp;ms</td>
					<td class='name'>".htmlspecialchars($cause)."</td>
					</tr>\n";
			}
		}
	}
	
	/**
	 * Create SubWheel (can be overriden in debug class)
	 * @param TextWheelRuleset $rules
	 * @return TextWheel
	 */
	protected function &createSubWheel(&$rules){
		return new TextWheelDebug($rules);
	}

}



/* stripos for php4 */
if (!function_exists('stripos')) {
	function stripos($haystack, $needle) {
		return strpos($haystack, stristr( $haystack, $needle ));
	}
}

if (!function_exists('strpbrk')) {
	function strpbrk($haystack, $char_list) {
    $result = strcspn($haystack, $char_list);
    if ($result != strlen($haystack)) {
        return $result;
    }
    return false;
	}
}
