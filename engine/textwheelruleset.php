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

require_once dirname(__FILE__)."/textwheelrule.php";

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

		require_once dirname(__FILE__).'/../lib/yaml/sfYaml.php';
		$dataset = sfYaml::load($file);

		if (is_null($dataset))
			$dataset = array();
#			throw new DomainException('yaml file is empty, unreadable or badly formed: '.$file.var_export($dataset,true));

		// if a php file with same name exists
		// include it as it contains callback functions
		if ($f = preg_replace(',[.]yaml$,i','.php',$file)
		AND file_exists($f)) {
			$dataset[] = array('require' => $f, 'priority' => -1000);
}
		return $dataset;
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
