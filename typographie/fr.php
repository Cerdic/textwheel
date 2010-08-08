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

function typographie_fr($letexte) {

	# version core
	if (!$GLOBALS['tw']) {
		require_once _DIR_RESTREINT.'typographie/fr.php';
		return typographie_fr_dist($letexte);
	}

	#$debug = _request('var_debug_wheel');

	static $trans;

	if (!isset($trans)) {
		switch ($GLOBALS['meta']['charset'] == 'utf-8') {
			case 'utf-8':
				$trans = array(
				"\x26\x6e\x62\x73\x70\x3b" => '~',
				"\x26\x72\x61\x71\x75\x6f\x3b" => '&#187;',
				"\x26\x6c\x61\x71\x75\x6f\x3b" => '&#171;',
				"\x26\x72\x64\x71\x75\x6f\x3b" => '&#8221;',
				"\x26\x6c\x64\x71\x75\x6f\x3b" => '&#8220;',
				"\x26\x64\x65\x67\x3b" => '&#176;',
				"\xc2\xa0" => '~',
				"\xc2\xbb" => '&#187;',
				"\xc2\xab" => '&#171;',
				"\xe2\x80\x9d" => '&#8221;',
				"\xe2\x80\x9c" => '&#8220;',
				"\xc2\xb0" => '&#176;'
				);
				break;
			case 'iso-8859-1':
				$trans = array(
				"\x26\x6e\x62\x73\x70\x3b" => '~',
				"\x26\x72\x61\x71\x75\x6f\x3b" => '&#187;',
				"\x26\x6c\x61\x71\x75\x6f\x3b" => '&#171;',
				"\x26\x72\x64\x71\x75\x6f\x3b" => '&#8221;',
				"\x26\x6c\x64\x71\x75\x6f\x3b" => '&#8220;',
				"\x26\x64\x65\x67\x3b" => '&#176;',
				"\x20" => '~',
				"\xbb" => '&#187;',
				"\xab" => '&#171;',
				"\xb0" => '&#176;'
				);
				break;
			default:
				$trans = array();
		}
		$trans["'"] = '&#8217;'; # joulie apostrophe
	}

	if($debug) spip_timer('trans');
	$letexte = str_replace(array_keys($trans), array_values($trans), $letexte);
	if($debug) $GLOBALS['totaux']['expanser_liens:']['corriger_typo:']['trans'] += spip_timer('trans', true);

	if($debug) spip_timer('cherche1');
	# la typo du ; risque de clasher avec les entites &xxx;
	if (strpos($letexte, ';') !== false) {
		$letexte = str_replace(';', '~;', $letexte);
		$letexte = preg_replace(',(&#?[0-9a-z]+)~;,iS', '\1;', $letexte);
	}

	/* 2 */
	$letexte = preg_replace('/&#187;| --?,|(?::| %)(?:\W|$)/S', '~\0', $letexte);

	/* 3 */
	$letexte = preg_replace('/[!?][!?\.]*/S', "\x2\x2~\\0", $letexte, -1, $c);
	if ($c) {
		$letexte = preg_replace("/([\[<\(!\?\.])\x2\x2~/S", '\1', $letexte);
		$letexte = str_replace("\x2\x2", '', $letexte);
	}

	/* 4 */
	$letexte = preg_replace('/&#171;|M(?:M?\.|mes?|r\.?|&#176;) |[nN]&#176; /S', '\0~', $letexte);

	if($debug) $GLOBALS['totaux']['expanser_liens:']['corriger_typo:']['cherche1'] += spip_timer('cherche1', true);


	if($debug) spip_timer('chercheespaces');
	if (strpos($letexte, '~') !== false)
		$letexte = preg_replace("/ *~+ */S", "~", $letexte);
	if($debug) $GLOBALS['totaux']['expanser_liens:']['corriger_typo:']['chercheespaces'] += spip_timer('chercheespaces', true);

	if($debug) spip_timer('cherche2');
	$letexte = preg_replace("/--([^-]|$)/S", "\x2\x2&mdash;\\1", $letexte, -1, $c);
	if ($c) {
		$letexte = preg_replace("/([-\n])\x2\x2&mdash;/S", "\\1--", $letexte);
		$letexte = str_replace("\x2\x2", '', $letexte);
	}

	$letexte = preg_replace(',(https?|ftp|mailto)~((://[^"\'\s\[\]\}\)<>]+)~([?]))?,S', '\1\3\4', $letexte);
	$letexte = str_replace('~', '&nbsp;', $letexte);


	if($debug) $GLOBALS['totaux']['expanser_liens:']['corriger_typo:']['cherche2'] += spip_timer('cherche2', true);

	return $letexte;
}
