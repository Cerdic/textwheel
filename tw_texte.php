<?php

# usage: php wheels/spip.php
require_once 'engine/textwheel.php';

function tw_raccourcis($t) {
	static $wheel;
	if (!isset($wheel))
		$wheel = new TextWheel(new TextWheelRuleSet('spip.yaml'));
	return $wheel->text($t);
}
function tw_paragrapher($t) {
	static $wheel;
	if (!isset($wheel))
		$wheel = new TextWheel(new TextWheelRuleSet('spip-paragrapher.yaml'));
	return $wheel->text($t);
}

function tw_propre($t) {
	static $wheel;
	if (!isset($wheel)){
		$ruleset = new TextWheelRuleSet('spip.yaml');
		$ruleset->addRules('spip-paragrapher.yaml');
		$wheel = new TextWheel($ruleset);
	}
	return $wheel->test($t);
}

