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
	var $type; # 'preg' (default), 'str', 'all'...
	var $match; # matching string or expression
	# optional
	# var $limit; # limit number of applications (unused)

	## rule effectors, replacing
	# mandatory
	var $replace; # replace match with this expression

	# optional
	var $is_wheel; # flag to create a sub-wheel from rules given as replace
	var $is_callback=false; # $replace is a callback function

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
	# list of datas
	protected $datas = array();
	
	/**
	 * Load a yaml file describing datas
	 * @param string $file
	 * @return array
	 */
	protected function loadFile($file, $default_path='') {
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

		$rules = false;
		// yaml caching
		if (defined('_TW_DIR_CACHE_YAML')
			AND $hash = substr(md5($file),0,8)."-".substr(md5_file($file),0,8)
			AND $fcache = _TW_DIR_CACHE_YAML."yaml-".basename($file,'.yaml')."-".$hash.".txt"
			AND file_exists($fcache)
			AND $c = file_get_contents($fcache)
			)
			$rules = unserialize($c);

		if (!$rules){
			require_once dirname(__FILE__).'/../lib/yaml/sfYaml.php';
			$rules = sfYaml::load($file);
		}

		if (!$rules)
			return array();

		// if a php file with same name exists
		// include it as it contains callback functions
		if ($f = preg_replace(',[.]yaml$,i','.php',$file)
		  AND file_exists($f))
			include_once $f;

		if ($fcache AND !$c)
		 file_put_contents ($fcache, serialize($rules));
		
		return $rules;
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
	public function TextWheelRuleSet($ruleset = array()) {
		if ($ruleset)
			$this->addRules($ruleset);
	}

	/**
	 * get sorted Rules
	 * @return array
	 */
	public function &getRules(){
		$this->sort();
		return $this->datas;
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
		$this->datas[] = $rule;
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
	public function addRules($rules) {
		// rules can be an array of filename
		if (is_array($rules) AND is_string(reset($rules))) {
			foreach($rules as $i=>$filename)
				$this->addRules($filename);
			return;
		}

		// rules can be a string : yaml filename
		if (is_string($rules))
			$rules = $this->loadFile($rules);

		// rules can be an array of rules
		if (is_array($rules) AND count($rules)){
			# cast array-rules to objects
			foreach ($rules as $i => $rule)
				if (is_array($rule))
					$rules[$i] = new TextWheelRule($rule);
			$this->datas = array_merge($this->datas, $rules);
			$this->sorted = false;
		}
	}

	/**
	 * Sort rules according to priority and
	 */
	protected function sort() {
		if (!$this->sorted) {
			$rulz = array();
			foreach($this->datas as $index => $rule)
				$rulz[intval($rule->priority)][$index] = $rule;
			ksort($rulz);
			$this->datas = array();
			foreach($rulz as $rules)
				$this->datas += $rules;

			$this->sorted = true;
		}
	}
}

class TextWheel {
	protected $ruleset;

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
		foreach ($rules as $i=>$rule) #php4+php5
			$this->apply($rules[$i], $t);
		#foreach ($this->rules as &$rule) #smarter &reference, but php5 only
		#	$this->apply($rule, $t);
		return $t;
	}

	/**
	 * Initializing a rule a first call
	 * including file, creating function or wheel
	 * optimizing tests
	 *
	 * @param TextWheelRule $rule
	 */
	protected function initRule(&$rule){

		# /begin optimization needed
		# language specific
		if ($rule->require)
			require_once $rule->require;
		if ($rule->create_replace){
			$rule->replace = create_function('$m', $rule->replace);
			$rule->create_replace = false;
			$rule->is_callback = true;
		}
		elseif ($rule->is_wheel){
			$var = '$m[0]'; $arg = '$m';
			if ($rule->type=='all' OR $rule->type=='str')
				$var = $arg = '$t';
			$code = 'static $w=null; if (!isset($w)) $w=new TextWheel(new TextWheelRuleSet('
			. var_export($rule->replace,true) . '));
			return $w->text('.$var.');';
			$rule->replace = create_function($arg, $code);
			$rule->is_wheel = false;
			$rule->is_callback = true;
		}
		# /end

		# optimization
		$rule->func_replace = '';
		if (isset($rule->replace)) {
			switch($rule->type) {
				case 'all':
					$rule->func_replace = 'replace_all';
					break;
				case 'str':
					$rule->func_replace = 'replace_str';
					break;
				case 'preg':
				default:
					$rule->func_replace = 'replace_preg';
					break;
			}
			if ($rule->is_callback)
				$rule->func_replace .= '_cb';
		}
		if (!method_exists($this, $rule->func_replace)){
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
		if (
			!$rule->disabled
			AND
			(!isset($rule->if_chars)
				OR ((strlen($rule->if_chars) == 1)
					? (strpos($t, $rule->if_chars) !== false)
					: (strtr($t, $rule->if_chars, str_pad(chr(0), strlen($rule->if_chars), chr(0))) !== $t)
				)
			)
			AND
			(!isset($rule->if_str)
				OR (stripos($t, $rule->if_str) !== false)
			)
			AND
			(!isset($rule->if_match)
				OR preg_match($rule->if_match, $t)
			)
		) {
			if (!isset($rule->func_replace))
				$this->initRule($rule);

			$func = $rule->func_replace;
			$this->$func($rule->match,$rule->replace,$t,$count);
		}
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
	protected function replace_identity(&$match,&$replace,&$t,&$count){
	}

	/**
	 * Static replacement of All text
	 * @param mixed $match
	 * @param mixed $replace
	 * @param string $t
	 * @param int $count
	 */
	protected function replace_all(&$match,&$replace,&$t,&$count){
		# special case: replace \0 with $t
		#   replace: "A\0B" will surround the string with A..B
		#   replace: "\0\0" will repeat the string
		$t = str_replace('\\0', $t, $replace);
	}

	/**
	 * Call back replacement of All text
	 * @param mixed $match
	 * @param mixed $replace
	 * @param string $t
	 * @param int $count
	 */
	protected function replace_all_cb(&$match,&$replace,&$t,&$count){
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
	protected function replace_str(&$match,&$replace,&$t,&$count){
		$t = str_replace($match, $replace, $t, $count);
	}

	/**
	 * Callback string replacement
	 *
	 * @param mixed $match
	 * @param mixed $replace
	 * @param string $t
	 * @param int $count
	 */
	protected function replace_str_cb(&$match,&$replace,&$t,&$count){
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
	protected function replace_preg(&$match,&$replace,&$t,&$count){
		$t = preg_replace($match, $replace, $t, -1, $count);
	}

	/**
	 * Callback Preg replacement
	 * @param mixed $match
	 * @param mixed $replace
	 * @param string $t
	 * @param int $count
	 */
	protected function replace_preg_cb(&$match,&$replace,&$t,&$count){
		$t = preg_replace_callback($match, $replace, $t, -1, $count);
	}
}


/* stripos for php4 */
if (!function_exists('stripos')) {
	function stripos($haystack, $needle) {
		return strpos($haystack, stristr( $haystack, $needle ));
	}
}

