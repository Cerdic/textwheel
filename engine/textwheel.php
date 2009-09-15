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
	var $is_callback=false; # $replace is a callback function
	# optional
	# language specific
	var $require; # file to require_once
	var $create_replace; # do create_function('$m', %) on $this->replace, $m is the matched array

}

class TextWheel {
	var $rules = array();
	private $sorted = false;

	/*
	 * @param array $ruleset
	 */
	public function TextWheel($ruleset = array()) {
		if ($ruleset)
			$this->addrules($ruleset);
	}

	/*
	 * @param object $rule
	 * @param string $t
	 * @param int $count
	 * @return bool
	 */
	private function apply(&$rule, &$t, &$count=null) {
		if (
			!$rule->disabled
			AND
			(!isset($rule->if_chars)
				OR ((strlen($rule->if_chars) == 1)
					? (stripos($t, $rule->if_chars) !== false)
					: (strtr($t, $rule->if_chars, chr(0)) !== $t)
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
			# /begin optimization needed
			# language specific
			if ($rule->require)
				require_once $rule->require;
			if ($rule->create_replace)
				$rule->replace = create_function('$m', $rule->replace);
			# /end
			if (isset($rule->replace))
			switch($rule->type) {
				case 'all':
					if ($rule->is_callback) {
						$func = $rule->replace;
						$t = $func($t);
					}
					else {
						# special case: replace \0 with $t
						#   replace: "A\0B" will surround the string with A..B
						#   replace: "\0\0" will repeat the string
						$t = str_replace('\\0', $t, $rule->replace);
					}
					break;
				case 'str':
					if ($rule->is_callback) {
						if (count($b = explode($rule->match, $t)) > 1)
							$t = join($rule->replace(), $b);
					} else {
						$t = str_replace($rule->match, $rule->replace, $t, $count);
					}
					break;
				case 'preg':
				default:
					if ($rule->is_callback) {
						$t = preg_replace_callback($rule->match, $rule->replace, $t, -1, $count);
					} else {
						$t = preg_replace($rule->match, $rule->replace, $t, -1, $count);
					}
					break;
			}
		}
	}

	/*
	 * Apply all rules to a text
	 * @param string $t
	 * @return string
	 */
	public function text($t) {
		$this->sort();
		## apply each in order
		foreach ($this->rules as $i=>$rule) #php4+php5
			$this->apply($this->rules[$i], $t);
		#foreach ($this->rules as &$rule) #smarter &reference, but php5 only
		#	$this->apply($rule, $t);
		return $t;
	}

	/*
	 * add a rule
	 */
	public function addRule($rule) {
		# cast array-rule to object
		if (is_array($rule))
			$rule = (object) $rule;
		$this->rules[] = $rule;
		$this->sorted = false;
	}
	/*
	 * add an array of rules
	 */
	public function addRules(array $rules) {
		# cast array-rules to objects
		foreach ($rules as $i => $rule)
			if (is_array($rule))
				$rules[$i] = (object) $rule;
		$this->rules = array_merge($this->rules, $rules);
		$this->sorted = false;
	}


	/*
	 * Sort rules according to priority
	 */
	private function sort() {
		if (!$this->sorted) {
			$rulz = array();
			foreach($this->rules as $index => $rule)
				$rulz[intval($rule->priority)][$index] = $rule;
			ksort($rulz);
			$this->rules = array();
			foreach($rulz as $rules)
				$this->rules += $rules;

			$this->sorted = true;
		}
	}

}


/* stripos for php4 */
if (!function_exists('stripos')) {
	function stripos($haystack, $needle) {
		return strpos($haystack, stristr( $haystack, $needle ));
	}
}

?>
