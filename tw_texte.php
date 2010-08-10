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


// http://doc.spip.org/@traiter_raccourci_lien_lang
function tw_inc_lien_dist($lien, $texte='', $class='', $title='', $hlang='', $rel='', $connect='')
{
	static $u=null;
	if (!$u) $u=url_de_base();
	$typo = false;

	$mode = ($texte AND $class) ? 'url' : 'tout';
	$lien = calculer_url($lien, $texte, $mode, $connect);
	if ($mode === 'tout') {
		$texte = $lien['titre'];
		if (!$class AND isset($lien['class'])) $class = $lien['class'];
		$lang = isset($lien['lang']) ?$lien['lang'] : '';
		$mime = isset($lien['mime']) ? " type='".$lien['mime']."'" : "";
		$lien = $lien['url'];
	}
	
	$lien = trim($lien);
	if (strncmp($lien,"#",1) == 0)  # ancres pures (internes a la page)
		$class = 'spip_ancre';
	elseif (strncasecmp($lien,'mailto:',7)==0) # pseudo URL de mail
		$class = "spip_mail";
	elseif (strncmp($texte,'<html>',6)==0) # cf traiter_lien_explicite
		$class = "spip_url spip_out";
	elseif (!$class) $class = "spip_out"; # si pas spip_in|spip_glossaire

	// Si l'objet n'est pas de la langue courante, on ajoute hreflang
	if (!$hlang AND $lang!==$GLOBALS['spip_lang'])
		$hlang = $lang;

	$lang = ($hlang ? " hreflang='$hlang'" : '');

	if ($title) $title = ' title="'.attribut_html($title).'"';

	// rel=external pour les liens externes
	if ((strncmp($lien,'http://',7)==0 OR strncmp($lien,'https://',8)==0)
	  AND strncmp("$lien/", $u ,strlen($u))!=0)
		$rel = trim("$rel external");
	if ($rel) $rel = " rel='$rel'";

	if (traiter_modeles($texte, false, $echapper ? 'TYPO' : '', $connect)==$texte){
		$texte = typo($texte, true, $connect);
		$lien = "<a href=\"".str_replace('"', '&quot;', $lien)."\" class='$class'$lang$title$rel$mime>$texte</a>";
		return $lien;
	}
	# ceci s'execute heureusement avant les tableaux et leur "|".
	# Attention, le texte initial est deja echappe mais pas forcement
	# celui retourne par calculer_url.
	# Penser au cas [<imgXX|right>->URL], qui exige typo('<a>...</a>')
	$lien = "<a href=\"".str_replace('"', '&quot;', $lien)."\" class='$class'$lang$title$rel$mime>$texte</a>";
	return typo($lien, true, $connect);
}


// http://doc.spip.org/@traiter_raccourci_lien_atts
function tw_traiter_raccourci_lien_atts($texte) {

	$bulle = $hlang = '';
	// title et hreflang donnes par le raccourci ?
	if (strpbrk($texte, "|{") !== false AND
					preg_match(_RACCOURCI_ATTRIBUTS, $texte, $m)) {

		$n =count($m);
		// |infobulle ?
		if ($n > 2) {
			$bulle = $m[3];
			// {hreflang} ?
			if ($n > 4) {
			// si c'est un code de langue connu, on met un hreflang
				if (traduire_nom_langue($m[5]) <> $m[5]) {
					$hlang = $m[5];
				} elseif (!$m[5]) {
					$hlang = test_espace_prive() ?
					  $GLOBALS['lang_objet'] : $GLOBALS['spip_lang'];
				// sinon c'est un italique
				} else {
					$m[1] .= $m[4];
				}

	// S'il n'y a pas de hreflang sous la forme {}, ce qui suit le |
	// est peut-etre une langue
			} else if (preg_match('/^[a-z_]+$/', $m[3])) {
			// si c'est un code de langue connu, on met un hreflang
			// mais on laisse le title (c'est arbitraire tout ca...)
				if (traduire_nom_langue($m[3]) <> $m[3]) {
				  $hlang = $m[3];
				}
			}
		}
		$texte = $m[1];
	}

	return array(trim($texte), $bulle, $hlang);
}

function tw_expanser_un_lien($reg, $quoi='echappe'){
	static $inserts;
	static $sources;
	static $regs;
	static $k = 0;
	static $lien;
	static $connect='';

	switch ($quoi){
		case 'init':
			if (!$lien) $lien = "tw_inc_lien_dist";//charger_fonction('lien', 'inc');
			$inserts = $sources = $regs = array();
			$connect = $reg; // stocker le $connect pour les appels a inc_lien_dist
			$k=0;
			return;
			break;
		case 'echappe':
			$inserts[$k] = '@@SPIP_ECHAPPE_LIEN_' . $k . '@@';
			$sources[$k] = $reg[0];

			#$titre=$reg[1];
			list($titre, $bulle, $hlang) = tw_traiter_raccourci_lien_atts($reg[1]);
			$r = end($reg);
			// la mise en lien automatique est passee par la a tort !
			// corrigeons pour eviter d'avoir un <a...> dans un href...
			if (strncmp($r,'<a',2)==0){
				$href = extraire_attribut($r, 'href');
				// remplacons dans la source qui peut etre reinjectee dans les arguments
				// d'un modele
				$sources[$k] = str_replace($r,$href,$sources[$k]);
				// et prenons le href comme la vraie url a linker
				$r = $href;
			}
			$regs[$k] = $lien($r, $titre, '', $bulle, $hlang, '', $connect);
			return $inserts[$k++];
			break;
		case 'reinsert':
			if (!count($inserts)) return $reg;
			return str_replace($inserts, $regs, $reg);
			break;
		case 'sources':
			return array($inserts, $sources);
			break;
	}
}


define('_RACCOURCI_LIEN_TW', "/\[([^][]*?([[]\w*[]][^][]*)*)->(>?)([^]]*)\]/msS");

// http://doc.spip.org/@expanser_liens
function expanser_liens_tw($texte, $connect='')
{
	$debug = _request('var_debug_wheel');

	$texte = pipeline('pre_liens', $texte);


	if($debug) spip_timer('liensmatch');
	tw_expanser_un_lien($connect,'init');

	if (strpos($texte, '->') !== false)
		$texte = preg_replace_callback (_RACCOURCI_LIEN_TW, 'tw_expanser_un_lien',$texte);

	if($debug) $GLOBALS['totaux']['expanser_liens:']['liensmatch'] += spip_timer('liensmatch', true);

	// on passe a traiter_modeles la liste des liens reperes pour lui permettre
	// de remettre le texte d'origine dans les parametres du modele
	if($debug) spip_timer('traiter_modeles');
	$texte = traiter_modeles($texte, false, false, $connect, tw_expanser_un_lien('','sources'));
	if($debug) $GLOBALS['totaux']['expanser_liens:']['traiter_modeles'] += spip_timer('traiter_modeles', true);

	if($debug) spip_timer('corriger_typo');
 	$texte = corriger_typo($texte);
	if($debug) $GLOBALS['totaux']['expanser_liens:']['corriger_typo'] += spip_timer('corriger_typo', true);

	if($debug) spip_timer('reinserts');
	$texte = tw_expanser_un_lien($texte,'reinsert');
	if($debug) $GLOBALS['totaux']['expanser_liens:']['reinserts'] += spip_timer('reinserts', true);

	return $texte;
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
