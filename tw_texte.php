<?php

define('_TW_DIR_CACHE_YAML',  sous_repertoire(_DIR_CACHE,"yaml"));

# accepter un mode debug
if (_request('var_debug_wheel'))
	$GLOBALS['textWheel'] = 'TextWheelDebug';
else
	$GLOBALS['textWheel'] = 'TextWheel';
	
# usage: php wheels/spip.php
require_once _DIR_PLUGIN_TW.'engine/textwheel.php';
$GLOBALS['spip_wheels']['raccourcis'] = array('spip/spip.yaml','spip/spip-paragrapher.yaml');
if (test_espace_prive ())
	$GLOBALS['spip_wheels']['raccourcis'][] = 'spip/ecrire.yaml';

$GLOBALS['spip_wheels']['interdire_scripts'] = array('spip/interdire-scripts.yaml');
$GLOBALS['spip_wheels']['echappe_js'] = array('spip/echappe-js.yaml');


class SPIPTextWheelRuleset extends TextWheelRuleSet {
	protected function findFile(&$file, $path=''){
		static $default_path;

		// absolute file path ?
		if (file_exists($file))
			return $file;

		// file embed with texwheels, relative to calling ruleset
		if ($path AND file_exists($f = $path.$file))
			return $f;

		return find_in_path($file,'wheels/');
	}

	public static function &loader($ruleset, $callback = '', $class = 'SPIPTextWheelRuleset') {
		# memoization
		if (!_request('var_mode')
		  AND function_exists('cache_get')
		  AND $key = md5(serialize($ruleset).$callback.$class)
		  AND $ruleset = cache_get($key))
			return $ruleset;

		$ruleset = parent::loader($ruleset, $callback, $class);

		if ($key AND function_exists('cache_set'))
			cache_set($key, $ruleset, $ttl = 3600);
		
		return $ruleset;
	}
}

function tw_personaliser_raccourcis(&$ruleset){
	if (isset($GLOBALS['debut_intertitre']) AND $rule=$ruleset->getRule('intertitres')){
		$rule->replace[0] = preg_replace(',<[^>]*>,Uims',$GLOBALS['debut_intertitre'],$rule->replace[0]);
		$rule->replace[1] = preg_replace(',<[^>]*>,Uims',$GLOBALS['fin_intertitre'],$rule->replace[1]);
		$ruleset->addRules(array('intertitres'=>$rule));
	}
	if (isset($GLOBALS['debut_gras']) AND $rule=$ruleset->getRule('gras')){
		$rule->replace[0] = preg_replace(',<[^>]*>,Uims',$GLOBALS['debut_gras'],$rule->replace[0]);
		$rule->replace[1] = preg_replace(',<[^>]*>,Uims',$GLOBALS['fin_gras'],$rule->replace[1]);
		$ruleset->addRules(array('gras'=>$rule));
	}
	if (isset($GLOBALS['debut_italique']) AND $rule=$ruleset->getRule('italiques')){
		$rule->replace[0] = preg_replace(',<[^>]*>,Uims',$GLOBALS['debut_italique'],$rule->replace[0]);
		$rule->replace[1] = preg_replace(',<[^>]*>,Uims',$GLOBALS['fin_italique'],$rule->replace[1]);
		$ruleset->addRules(array('italiques'=>$rule));
	}
	if (isset($GLOBALS['ligne_horizontale']) AND $rule=$ruleset->getRule('ligne-horizontale')){
		$rule->replace = preg_replace(',<[^>]*>,Uims',$GLOBALS['ligne_horizontale'],$rule->replace);
		$ruleset->addRules(array('ligne-horizontale'=>$rule));
	}
	if (isset($GLOBALS['toujours_paragrapher']) AND !$GLOBALS['toujours_paragrapher']
	  AND $rule=$ruleset->getRule('toujours-paragrapher')) {
		$rule->disabled = true;
		$ruleset->addRules(array('toujours-paragrapher'=>$rule));
	}
}

function tw_traiter_raccourcis($letexte) {
	static $wheel;
	// Appeler les fonctions de pre_traitement
	#$letexte = pipeline('pre_propre', $letexte);

	$debug = _request('var_debug_wheel');


	if (!isset($wheel)) {
		if($debug) spip_timer('init');
		$ruleset = SPIPTextWheelRuleset::loader($GLOBALS['spip_wheels']['raccourcis'],'tw_personaliser_raccourcis');
		$wheel = new $GLOBALS['textWheel']($ruleset);

		if (_request('var_mode') == 'compile') {
			echo "<pre>";
			echo htmlspecialchars($wheel->compile());
			echo "</pre>\n";
			;
		}

		if($debug) $GLOBALS['totaux']['tw_traiter_raccourcis:']['init'] += spip_timer('init', true);
	}

	// Gerer les notes (ne passe pas dans le pipeline)
	if($debug) spip_timer('notes');
	$notes = charger_fonction('notes', 'inc');
	list($letexte, $mes_notes) = $notes($letexte);
	if($debug) $GLOBALS['totaux']['tw_traiter_raccourcis:']['notes'] += spip_timer('notes', true);

	if($debug) spip_timer('text');
	$letexte = $wheel->text($letexte);
	if($debug) $GLOBALS['totaux']['tw_traiter_raccourcis:']['text'] += spip_timer('text', true);

	// Appeler les fonctions de post-traitement
	if($debug) spip_timer('post_propre');
	$letexte = pipeline('post_propre', $letexte);
	if($debug) $GLOBALS['totaux']['tw_traiter_raccourcis:']['post_propre'] += spip_timer('post_propre', true);

	if($debug) spip_timer('mesnotes');
	if ($mes_notes) {
		$notes($mes_notes);
	}
	if($debug) $GLOBALS['totaux']['tw_traiter_raccourcis:']['mesnotes'] += spip_timer('mesnotes', true);

	return $letexte;
}


function tw_echappe_js($t) {
	static $wheel = null;
	if (!isset($wheel))
		$wheel = new $GLOBALS['textWheel'](
			SPIPTextWheelRuleset::loader($GLOBALS['spip_wheels']['echappe_js'])
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
		$ruleset = SPIPTextWheelRuleset::loader($GLOBALS['spip_wheels']['interdire_scripts']);
		// Pour le js, trois modes : parano (-1), prive (0), ok (1)
		// desactiver la regle echappe-js si besoin
		if ($GLOBALS['filtrer_javascript']==1
			OR ($GLOBALS['filtrer_javascript']==0 AND !test_espace_prive()))
			$ruleset->addRules (array('echappe-js'=>array('disabled'=>true)));
		$wheel = new $GLOBALS['textWheel']($ruleset);
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

	$GLOBALS['tw'] = true;

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

	if (!$t) return strval($t);

	$debug = _request('var_debug_wheel');

	if($debug) spip_timer('echappe_html');
	$t = echappe_html($t);
	if($debug) $GLOBALS['totaux']['echappe_html'] += spip_timer('echappe_html', true);

	if($debug) spip_timer('expanser_liens');
	$t = expanser_liens_tw($t,$connect);
	if($debug) $GLOBALS['totaux']['expanser_liens'] += spip_timer('expanser_liens', true);

	if($debug) spip_timer('tw_traiter_raccourcis');
	$t = tw_traiter_raccourcis($t);
	if($debug) $GLOBALS['totaux']['tw_traiter_raccourcis'] += spip_timer('tw_traiter_raccourcis', true);

	if($debug) spip_timer('tw_echappe_retour_modeles');
	$t = tw_echappe_retour_modeles($t, $interdire_script);
	if($debug) $GLOBALS['totaux']['tw_echappe_retour_modeles'] += spip_timer('tw_echappe_retour_modeles', true);


	$GLOBALS['tw'] = false;

	return $t;
}
