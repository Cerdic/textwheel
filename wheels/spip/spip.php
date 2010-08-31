<?php
include_spip('inc/texte');

# not usefull as this file is include by the engine itself
# require_once 'engine/textwheel.php';

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
 * callback fermer-para-mano
 * on refait le preg, a la main
 */
function fermer_para_mano(&$t) {
	# match: ",<p (.*)<(/?)(STOP P|div|pre|ul|ol|li|blockquote|h[1-6r]|t(able|[rdh]|body|foot|extarea)|form|object|center|marquee|address|d[ltd]|script|noscript|map|button|fieldset|style)\b,UimsS"
	# replace: "\n<p "+trim($1)+"</p>\n<$2$3"

	foreach (explode('<p ', $t) as $c => $p) {
		if ($c == 0)
			$t = $p;
		else {
			$pi = strtolower($p);
			if (preg_match(
			",</?(?:stop p|div|pre|ul|ol|li|blockquote|h[1-6r]|t(able|[rdh]|body|foot|extarea)|form|object|center|marquee|address|d[ltd]|script|noscript|map|button|fieldset|style)\b,S",
			$pi, $r)) {
				$pos = strpos($pi, $r[0]);
				$t .= "\n<p ".rtrim(substr($p,0,$pos))."</p>\n".substr($p,$pos);
			} else {
				$t .= '<p '.$p;
			}
		}
	}

	return $t;
}
