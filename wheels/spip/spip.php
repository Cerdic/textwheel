<?php

# not usefull as this file is include by the engine itself
# require_once 'engine/textwheel.php';

// 
//
/**
 * callback pour poesie
 * (a remplacer par une sous-liste = indentation de yaml ?)
 *
 * @staticvar TextWheel $wheel
 * @param array $m
 * @return string
 */
function replace_poesie($m) {
	static $wheel;
	if (!isset($wheel))
		$wheel = new TextWheel(new TextWheelRuleSet('spip-poesie.yaml'));
	return $wheel->text($m[2]);
}

function replace_puce(){
	static $puce;
	if (!isset($puce))
		$puce = "\n<br />".definir_puce()."&nbsp;";
	return $puce;
}