<?php

# usage: php wheels/spip.php
require_once _DIR_PLUGIN_TW.'engine/textwheel.php';
$GLOBALS['spip_wheels']['raccourcis'] = array('spip/spip.yaml','spip/spip-paragrapher.yaml','spip/spip-tableaux.yaml');
if (test_espace_prive ())
	$GLOBALS['spip_wheels']['raccourcis'][] = 'spip/ecrire.yaml';

$GLOBALS['spip_wheels']['interdire_scripts'] = array('spip/interdire-scripts.yaml');
$GLOBALS['spip_wheels']['echappe_js'] = array('spip/echappe-js.yaml');

function tw_traiter_raccourcis($letexte) {
	static $wheel;
	// Appeler les fonctions de pre_traitement
	$letexte = pipeline('pre_propre', $letexte);

	if (!isset($wheel))
		$wheel = new TextWheel(
			new TextWheelRuleSet($GLOBALS['spip_wheels']['raccourcis'])
		);

	// Gerer les notes (ne passe pas dans le pipeline)
	$notes = charger_fonction('notes', 'inc');
	list($letexte, $mes_notes) = $notes($letexte);

	$letexte = $wheel->text($letexte);

	// Appeler les fonctions de post-traitement
	$letexte = pipeline('post_propre', $letexte);

	if ($mes_notes) $notes($mes_notes);

	return $letexte;
}


function tw_echappe_js($t) {
	static $wheel = null;
	if (!isset($wheel))
		$wheel = new TextWheel(
			new TextWheelRuleSet($GLOBALS['spip_wheels']['echappe_js'])
		);

	return $wheel->text($t);
}

function tw_echappe_retour_modeles($letexte, $interdire_scripts=false){
	$letexte = echappe_retour($letexte);

	// Dans les appels directs hors squelette, securiser aussi ici
	if ($interdire_scripts)
		$letexte = tw_interdire_scripts($letexte,true);

	return trim($letexte);
}

// Securite : empecher l'execution de code PHP, en le transformant en joli code
// dans l'espace prive, cette fonction est aussi appelee par propre et typo
// si elles sont appelees en direct
// il ne faut pas desactiver globalement la fonction dans l'espace prive car elle protege
// aussi les balises des squelettes qui ne passent pas forcement par propre ou typo apres
// http://doc.spip.org/@interdire_scripts
function tw_interdire_scripts($arg) {
	static $dejavu = array();
	static $wheel = null;

	// Attention, si ce n'est pas une chaine, laisser intact
	if (!$arg OR !is_string($arg) OR !strstr($arg, '<')) return $arg;
	if (isset($dejavu[$GLOBALS['filtrer_javascript']][$arg])) return $dejavu[$GLOBALS['filtrer_javascript']][$arg];

	if (!isset($wheel)){
		$ruleset = new TextWheelRuleSet($GLOBALS['spip_wheels']['interdire_scripts']);
		// Pour le js, trois modes : parano (-1), prive (0), ok (1)
		// desactiver la regle echappe-js si besoin
		if ($GLOBALS['filtrer_javascript']==1
			OR ($GLOBALS['filtrer_javascript']==0 AND !test_espace_prive()))
			$ruleset->addRules (array('echappe-js'=>array('disabled'=>true)));
		$wheel = new TextWheel($ruleset);
	}

	$t = $wheel->text($arg);

	// Reinserer les echappements des modeles
	if (defined('_PROTEGE_JS_MODELES'))
		$t = echappe_retour($t,"javascript"._PROTEGE_JS_MODELES);
	if (defined('_PROTEGE_PHP_MODELES'))
		$t = echappe_retour($t,"php"._PROTEGE_PHP_MODELES);

	return $dejavu[$GLOBALS['filtrer_javascript']][$arg] = $t;
}

function tw_propre($t, $connect=null) {
	// les appels directs a cette fonction depuis le php de l'espace
	// prive etant historiquement ecrits sans argment $connect
	// on utilise la presence de celui-ci pour distinguer les cas
	// ou il faut passer interdire_script explicitement
	// les appels dans les squelettes (de l'espace prive) fournissant un $connect
	// ne seront pas perturbes
	$interdire_script = false;
	if (is_null($connect)){
		$connect = '';
		$interdire_script = true;
	}

	return !$t ? strval($t) :
		tw_echappe_retour_modeles(
			tw_traiter_raccourcis(
				expanser_liens(echappe_html($t),$connect)),$interdire_script);
}
