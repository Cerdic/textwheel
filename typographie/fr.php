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

	$debug = _request('var_debug_wheel');

	static $trans;

	// Nettoyer 160 = nbsp ; 187 = raquo ; 171 = laquo ; 176 = deg ;
	// 147 = ldquo; 148 = rdquo; ' = zouli apostrophe
	if (!$trans) {
		$trans = array(
			"'" => "&#8217;",
			"&nbsp;" => "~",
			"&raquo;" => "&#187;",
			"&laquo;" => "&#171;",
			"&rdquo;" => "&#8221;",
			"&ldquo;" => "&#8220;",
			"&deg;" => "&#176;"
		);
		$chars = array(160 => '~', 187 => '&#187;', 171 => '&#171;', 148 => '&#8221;', 147 => '&#8220;', 176 => '&#176;');
		$chars_trans = array_keys($chars);
		$chars = array_values($chars);
		$chars_trans = implode(' ',array_map('chr',$chars_trans));
		$chars_trans = unicode2charset(charset2unicode($chars_trans, 'iso-8859-1', 'forcer'));
		$chars_trans = explode(" ",$chars_trans);
		foreach($chars as $k=>$r)
			$trans[$chars_trans[$k]] = $r;
	}

	if($debug) spip_timer('trans');
	$letexte = strtr($letexte, $trans);
	if($debug) $GLOBALS['totaux']['trans'] += spip_timer('trans', true);

	if($debug) spip_timer('cherche1');
	# la typo du ; risque de clasher avec les entites &xxx;
	if (strpos($letexte, ';') !== false) {
		$letexte = str_replace(';', '~;', $letexte);
		$letexte = preg_replace(',(&#?[0-9a-z]+)~;,iS', '\1;', $letexte);
	}

	$cherche1 = array(
		/* 2 */		'/&#187;| --?,|(?::| %)(?:\W|$)/S',
		/* 3 */		'/([^[<(])([!?][!?\.]*)/S',
		/* 4 */		'/&#171;|(?:M(?:M?\.|mes?|r\.?)|[MnN]&#176;) /S'
	);
	$remplace1 = array(
		/* 2 */		'~\0',
		/* 3 */		'\1~\2',
		/* 4 */		'\0~'
	);
	$letexte = preg_replace($cherche1, $remplace1, $letexte);
	if($debug) $GLOBALS['totaux']['cherche1'] += spip_timer('cherche1', true);
	if($debug) spip_timer('chercheespaces');
	if (strpos($letexte, '~') !== false)
		$letexte = preg_replace("/ *~+ */S", "~", $letexte);
	if($debug) $GLOBALS['totaux']['chercheespaces'] += spip_timer('chercheespaces', true);

	if($debug) spip_timer('cherche2');
	$cherche2 = array(
		'/([^-\n]|^)--([^-]|$)/S',
		',(http|https|ftp|mailto)~((://[^"\'\s\[\]\}\)<>]+)~([?]))?,S',
		'/~/'
	);
	$remplace2 = array(
		'\1&mdash;\2',
		'\1\3\4',
		'&nbsp;'
	);
	$letexte = preg_replace($cherche2, $remplace2, $letexte);
	if($debug) $GLOBALS['totaux']['cherche2'] += spip_timer('cherche2', true);

	return $letexte;
}
