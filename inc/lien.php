<?php

/***************************************************************************\
 *  SPIP, Systeme de publication pour l'internet                           *
 *                                                                         *
 *  Copyright (c) 2001-2010                                                *
 *  Arnaud Martin, Antoine Pitrou, Philippe Riviere, Emmanuel Saint-James  *
 *                                                                         *
 *  Ce programme est un logiciel libre distribue sous licence GNU/GPL.     *
 *  Pour plus de details voir le fichier COPYING.txt ou l'aide en ligne.   *
\***************************************************************************/

if (!defined("_ECRIRE_INC_VERSION")) return;

# Tests TW
require_once _DIR_RESTREINT.'inc/lien.php';

include_spip('base/abstract_sql');

//
// Production de la balise A+href a partir des raccourcis [xxx->url] etc.
// Note : complique car c'est ici qu'on applique typo(),
// et en plus on veut pouvoir les passer en pipeline
//

function inc_lien($lien, $texte='', $class='', $title='', $hlang='', $rel='', $connect=''){
	# Tests TW
	if (!$GLOBALS['tw']) {
		return inc_lien_dist($lien, $texte, $class, $title, $hlang, $rel, $connect);
	}

	static $u=null;
	if (!$u) $u=url_de_base();
	$typo = false;

	// Si une langue est demandee sur un raccourci d'article, chercher
	// la traduction ;
	// - [{en}->art2] => traduction anglaise de l'article 2, sinon art 2
	// - [{}->art2] => traduction en langue courante de l'art 2, sinon art 2
	if ($hlang
	AND $match = typer_raccourci($lien)) { 
		@list($type,,$id,,$args,,$ancre) = $match; 
		if ($id_trad = sql_getfetsel('id_trad', 'spip_articles', "id_article=$id")
		AND $id_dest = sql_getfetsel('id_article', 'spip_articles',
			"id_trad=$id_trad AND lang=" . sql_quote($hlang))
		)
			$lien = "$type$id_dest";
		else
			$hlang = '';
	}

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

// Regexp des raccourcis, aussi utilisee pour la fusion de sauvegarde Spip
// Laisser passer des paires de crochets pour la balise multi
// mais refuser plus d'imbrications ou de mauvaises imbrications
// sinon les crochets ne peuvent plus servir qu'a ce type de raccourci

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


function tw_expanser_un_lien($reg, $quoi='echappe'){
	static $pile = array();
	static $inserts;
	static $sources;
	static $regs;
	static $k = 0;
	static $lien;
	static $connect='';

	switch ($quoi){
		case 'init':
			if (!$lien) $lien = charger_fonction('lien', 'inc');
			array_push($pile,array($inserts,$sources,$regs,$connect,$k));
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
			if (count($inserts))
				$reg = str_replace($inserts, $regs, $reg);
			list($inserts,$sources,$regs,$connect,$k) = array_pop($pile);
			return $reg;
			break;
		case 'sources':
			return array($inserts, $sources);
			break;
	}
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


?>
