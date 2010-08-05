<?php

/*
 * TextWheelTest 0.1
 *
 * let's test the Wheel
 *
 * This library of code is meant to be a fast and universal replacement
 * for any and all text-processing systems written in PHP
 *
 * It is dual-licensed for any use under the GNU/GPL2 and MIT licenses,
 * as suits you best
 *
 * (c) 2010 Fil - fil@rezo.net
 * (c) 2010 Cedric - cedric@yterium.com
 * Documentation & http://zzz.rezo.net/-TextWheel-
 *
 */

require_once dirname(dirname(__FILE__))."/textwheel.php";

class TextWheelTest {
	var $input = "";
	var $ruleset;
	var $output = "";

	var $name; # test's name
	var $author; # test's author
	var $url; # test's homepage
	var $package; # test belongs to package
	var $version; # test version
	var $disabled=false; # true if test is disabled

	public function TextWheelTest($args) {
		if (!is_array($args))
			return;
		foreach($args as $k=>$v)
			if (property_exists($this, $k))
				$this->$k = $args[$k];
	}
}

class TextWheelTestSet extends TextWheelDataSet {
	/**
	 * Constructor
	 *
	 * @param array/string $testset
	 */
	public function TextWheelTestSet($testset = array(), $filepath='') {
		if ($testset)
			$this->addTests($testset, $filepath);
	}

	/**
	 * get Tests
	 * @return array
	 */
	public function &getTests(){
		return $this->data;
	}

	/**
	 * add a test
	 *
	 * @param TextWheelTest $test
	 */
	public function addTest($test) {
		# cast array-test to object
		if (is_array($test))
			$test = new TextWheelTest($test);
		if (is_array($test->ruleset))
			$test->ruleset = new TextWheelRuleSet($test->ruleset);
		$this->data[] = $$test;
	}

	/**
	 * add an list of tests
	 * can be an array or a string filename
	 *
	 * @param array/string $tests
	 */
	public function addTests($tests, $filepath='') {
		if (!$filepath) $filepath = dirname(__FILE__).'/';
		if (is_string($tests)) {
			$file = $tests; // keep the real filename
			$tests = $this->loadFile($file,$filepath);
			$filepath = dirname($file).'/';
		}

		if (is_array($tests) AND count($tests)){
			# cast array-tests to objects
			foreach ($tests as $i => $test){
				if (is_array($test))
					$tests[$i] = new TextWheelTest($test);
				if (is_array($tests[$i]->ruleset))
					$tests[$i]->ruleset = new TextWheelRuleSet($tests[$i]->ruleset,$filepath);
			}
			$this->data = array_merge($this->data, $tests);
		}
	}

}

class TextWheelTester {
	protected $testset;

	/**
	 * Constructor
	 * @param TextWheelTestSet $testset
	 */
	public function TextWheelTester($testset = null) {
		$this->setTestSet($testset);
	}

	/**
	 * Set RuleSet
	 * @param TextWheelTestSet $testset
	 */
	public function setTestSet($testset){
		if (!is_object($testset))
			$testset = new TextWheelTestSet ();
		$this->testset = $testset;
	}

	/**
	 * Apply all rules of RuleSet to a text
	 *
	 * @param string $t
	 * @return string
	 */
	public function &test() {

		$tests = & $this->testset->getTests();
		$results = array();
		## apply each in order
		foreach ($tests as $i=>$test){ #php4+php5
			if (!$test->disabled) {
				$results[$i] = array('test' => &$tests[$i]);

				$w = new TextWheel($test->ruleset);
				$output = $w->text($test->input);
				if ($output == $test->output) {
					$results[$i]['result'] = true;
				}
				else {
					$results[$i]['result'] = false;
					$results[$i]['output'] = $output;
				}
			}
		}
		return $results;
	}

}