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

require_once _DIR_RESTREINT.'typographie/en.php';

function typographie_en($letexte) {

	# version core
	if (!$GLOBALS['tw']) {
		return typographie_en_dist($letexte);
	}

	$debug = _request('var_debug_wheel');

	static $trans;

	if (!isset($trans)) {
		$trans = array(
		"&nbsp;" => '~',
		"'" => '&#8217;'
		);
		switch ($GLOBALS['meta']['charset']) {
			case 'utf-8':
				$trans["\xc2\xa0"] = '~';
				break;
			default:
				$trans["\xa0"] = '~';
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

	/* 2 */
	$letexte = preg_replace('/ --?,|(?: %)(?:\W|$)/S', '~$0', $letexte);

	/* 4 */
	$letexte = preg_replace('/Mr\.? /S', '$0~', $letexte);

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

	$letexte = str_replace('~', '&nbsp;', $letexte);


	if($debug) $GLOBALS['totaux']['expanser_liens:']['corriger_typo:']['cherche2'] += spip_timer('cherche2', true);

	return $letexte;
}
