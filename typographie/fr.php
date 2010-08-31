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

// Correction typographique francaise

require_once _DIR_RESTREINT.'typographie/fr.php';

function typographie_fr($letexte) {

	# version core
	if (!$GLOBALS['tw']) {
		return typographie_fr_dist($letexte);
	}

	$debug = _request('var_debug_wheel');

	static $trans;

	if (!isset($trans)) {
		$trans = array(
		"&nbsp;" => '~',
		"&raquo;" => '&#187;',
		"&laquo;" => '&#171;',
		"&rdquo;" => '&#8221;',
		"&ldquo;" => '&#8220;',
		"&deg" => '&#176;',
		"'" => '&#8217;'
		);
		switch ($GLOBALS['meta']['charset']) {
			case 'utf-8':
				$trans["\xc2\xa0"] = '~';
				$trans["\xc2\xbb"] = '&#187;';
				$trans["\xc2\xab"] = '&#171;';
				$trans["\xe2\x80\x9d"] = '&#8221;';
				$trans["\xe2\x80\x9c"] = '&#8220;';
				$trans["\xc2\xb0"] = '&#176;';
				break;
			default:
				$trans["\xa0"] = '~';
				$trans["\xab"] = '&#171;';
				$trans["\xbb"] = '&#187;';
				$trans["\xb0"] = '&#176;';
				break;
		}
	}

	# cette chaine ne peut pas exister,
	# cf. TYPO_PROTECTEUR dans inc/texte
	$pro = "-\x2-";

	if($debug) spip_timer('trans');
	$letexte = str_replace(array_keys($trans), array_values($trans), $letexte);
	if($debug) $GLOBALS['totaux']['expanser_liens:']['corriger_typo:']['trans'] += spip_timer('trans', true);

	if($debug) spip_timer('cherche1');
	# la typo du ; risque de clasher avec les entites &xxx;
	if (strpos($letexte, ';') !== false) {
		$letexte = str_replace(';', '~;', $letexte);
		$letexte = preg_replace(',(&#?[0-9a-z]+)~;,iS', '$1;', $letexte);
	}

	/* 2 */
	$letexte = preg_replace('/&#187;| --?,|(?::| %)(?:\W|$)/S', '~$0', $letexte);

	/* 3 */
	$letexte = preg_replace('/[!?][!?\.]*/S', "$pro~$0", $letexte, -1, $c);
	if ($c) {
		$letexte = preg_replace("/([\[<\(!\?\.])$pro~/S", '$1', $letexte);
		$letexte = str_replace("$pro", '', $letexte);
	}

	/* 4 */
	$letexte = preg_replace('/&#171;|M(?:M?\.|mes?|r\.?|&#176;) |[nN]&#176; /S', '$0~', $letexte);

	if($debug) $GLOBALS['totaux']['expanser_liens:']['corriger_typo:']['cherche1'] += spip_timer('cherche1', true);


	if($debug) spip_timer('chercheespaces');
	if (strpos($letexte, '~') !== false)
		$letexte = preg_replace("/ *~+ */S", "~", $letexte);
	if($debug) $GLOBALS['totaux']['expanser_liens:']['corriger_typo:']['chercheespaces'] += spip_timer('chercheespaces', true);

	if($debug) spip_timer('cherche2');
	$letexte = preg_replace("/--([^-]|$)/S", "$pro&mdash;$1", $letexte, -1, $c);
	if ($c) {
		$letexte = preg_replace("/([-\n])$pro&mdash;/S", "$1--", $letexte);
		$letexte = str_replace($pro, '', $letexte);
	}

	$letexte = preg_replace(',(https?|ftp|mailto)~((://[^"\'\s\[\]\}\)<>]+)~([?]))?,S', '$1$3$4', $letexte);
	$letexte = str_replace('~', '&nbsp;', $letexte);


	if($debug) $GLOBALS['totaux']['expanser_liens:']['corriger_typo:']['cherche2'] += spip_timer('cherche2', true);

	return $letexte;
}
