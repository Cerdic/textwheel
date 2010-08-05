<?php
include_spip('inc/texte');

# not usefull as this file is include by the engine itself
# require_once 'engine/textwheel.php';

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
		$wheel = new TextWheel(new TextWheelRuleSet('spip/spip-poesie.yaml'));
	return $wheel->text($m[2]); # 2 : correspond au deuxieme element du match...
}

/**
 * callback pour les listes
 */
function replace_listes(&$t){
	return traiter_listes($t);
}


/**
 * callback pour la puce qui est definissable/surchargeable
 */
function replace_puce(){
	static $puce;
	if (!isset($puce))
		$puce = "\n<br />".definir_puce()."&nbsp;";
	return $puce;
}


/**
 * callback pour gerer les tableaux
 * (a remplacer par une inclusion de spip-tableaux.yaml ?)
 *
 * @staticvar TextWheel $wheel
 * @param string $texte
 * @return string
 */
function spip_tableaux($texte) {
	static $wheel;
	if (!isset($wheel))
		$wheel = new TextWheel(new TextWheelRuleSet('spip/spip-tableaux.yaml'));

	return $wheel->text($texte);
}
