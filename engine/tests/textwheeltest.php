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
}

class TextWheelTestSet {
	# list of tests
	private $tests = array();

	/**
	 * Constructor
	 *
	 * @param array/string $testset
	 */
	public function TextWheelTestSet($testset = array()) {
		if ($testset)
			$this->addTests($testset);
	}

	/**
	 * get Tests
	 * @return array
	 */
	public function &getTests(){
		return $this->tests;
	}

	/**
	 * add a test
	 *
	 * @param TextWheelTest $test
	 */
	public function addTest($test) {
		# cast array-test to object
		if (is_array($test))
			$test = (object) $test;
		if (is_array($test->ruleset))
			$test->ruleset = new TextWheelRuleSet($test->ruleset);
		$this->tests[] = $$test;
	}

	/**
	 * add an list of tests
	 * can be an array or a string filename
	 *
	 * @param array/string $tests
	 */
	public function addTests($tests) {
		if (is_string($tests))
			$tests = $this->loadTests($tests);
		if (is_array($tests) AND count($tests)){
			# cast array-tests to objects
			foreach ($tests as $i => $test){
				if (is_array($test))
					$tests[$i] = (object) $test;
				if (is_array($tests[$i]->ruleset))
					$tests[$i]->ruleset = new TextWheelRuleSet($tests[$i]->ruleset);
			}
			$this->tests = array_merge($this->tests, $tests);
		}
	}

	/**
	 * Load a yaml file describing tests
	 * @param string $file
	 * @return array
	 */
	private function loadTests($file) {
		if (!preg_match(',[.]yaml$,i',$file)
			// external tests
			OR 
				(!file_exists($file)
				// tests embed with texwheelstests
				AND !file_exists($file = dirname(__FILE__).'/'.$file)
				)
			)
			return array();
		require_once dirname(__FILE__).'/../../lib/yaml/sfYaml.php';
		$tests = sfYaml::load($file);

		if (!$tests)
			return array();

		// if a php file with same name exists
		// include it as it contains callback functions
		if ($f = preg_replace(',[.]yaml$,i','.php',$file)
		  AND file_exists($f))
			include_once $f;

		return $tests;
	}

}

class TextWheelTester {
	private $testset;

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
			$results[$i] = array('test' => &$tests[$i]);
			$w = new TextWheel(new TextWheelRuleSet($test->ruleset));
			$output = $w->text($test->input);
			if ($output == $test->output) {
				$results[$i]['result'] = true;
			}
			else {
				$results[$i]['result'] = false;
				$results[$i]['output'] = $output;
			}
		}
		return $results;
	}

}