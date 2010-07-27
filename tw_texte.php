<?php

# usage: php wheels/spip.php
require_once _DIR_PLUGIN_TW.'engine/textwheel.php';
$GLOBALS['spip_wheels'] = array('spip/spip.yaml','spip/spip-paragrapher.yaml');
if (test_espace_prive ())
	$GLOBALS['spip_wheels'][] = 'spip/ecrire.yaml';

function tw_traiter_raccourcis($letexte) {
	static $wheel;
	// Appeler les fonctions de pre_traitement
	$letexte = pipeline('pre_propre', $letexte);

	if (!isset($wheel)){
		$ruleset = new TextWheelRuleSet();
		foreach($GLOBALS['spip_wheels'] as $wheel)
			$ruleset->addRules($wheel);
		$wheel = new TextWheel($ruleset);
	}

	// Gerer les notes (ne passe pas dans le pipeline)
	$notes = charger_fonction('notes', 'inc');
	list($letexte, $mes_notes) = $notes($letexte);

	//
	// Tableaux
	//

	// ne pas oublier les tableaux au debut ou a la fin du texte
	$letexte = preg_replace(",^\n?[|],S", "\n\n|", $letexte);
	$letexte = preg_replace(",\n\n+[|],S", "\n\n\n\n|", $letexte);
	$letexte = preg_replace(",[|](\n\n+|\n?$),S", "|\n\n\n\n", $letexte);

	if (preg_match_all(',[^|](\n[|].*[|]\n)[^|],UmsS', $letexte,
	$regs, PREG_SET_ORDER))
	foreach ($regs as $t) {
		$letexte = str_replace($t[1], traiter_tableau($t[1]), $letexte);
	}

	$letexte = $wheel->text($letexte);

	// Appeler les fonctions de post-traitement
	$letexte = pipeline('post_propre', $letexte);

	if ($mes_notes) $notes($mes_notes);

	return $letexte;
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
		echappe_retour_modeles(
			tw_traiter_raccourcis(
				expanser_liens(echappe_html($t),$connect)),$interdire_script);
}
