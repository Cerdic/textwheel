<?php

# usage: php wheels/spip.php
require_once _DIR_PLUGIN_TW.'engine/textwheel.php';

function tw_raccourcis($t) {
	static $wheel;
	if (!isset($wheel))
		$wheel = new TextWheel(new TextWheelRuleSet('spip/spip.yaml'));
	return $wheel->text($t);
}
function tw_paragrapher($t) {
	static $wheel;
	if (!isset($wheel))
		$wheel = new TextWheel(new TextWheelRuleSet('spip/spip-paragrapher.yaml'));
	return $wheel->text($t);
}

function tw_propre($t) {
	static $wheel;
	if (!isset($wheel)){
		$ruleset = new TextWheelRuleSet('spip/spip.yaml');
		$ruleset->addRules('spip/spip-paragrapher.yaml');
		$wheel = new TextWheel($ruleset);
	}
	return $wheel->test($t);
}

