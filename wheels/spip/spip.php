<?php
include_spip('inc/texte');

# not usefull as this file is include by the engine itself
# require_once 'engine/textwheel.php';

function tw_traiter_listes2($para){
	global $class_spip, $class_spip_plus;
	$niveau = 0;
	$lignes = explode("\n-", "\n" . $para);

	// ne pas toucher a la premiere ligne
	list(,$debut) = each($lignes);
	$texte .= $debut;

	// chaque item a sa profondeur = nb d'etoiles
	$type ='';
	while (list(,$item) = each($lignes)) {
		preg_match(",^([*]*|[#]*)([^*#].*)$,sS", $item, $regs);
		$profond = strlen($regs[1]);

		if ($profond > 0) {
			$ajout='';

			// changement de type de liste au meme niveau : il faut
			// descendre un niveau plus bas, fermer ce niveau, et
			// remonter
			$nouv_type = (substr($item,0,1) == '*') ? 'ul' : 'ol';
			$change_type = ($type AND ($type <> $nouv_type) AND ($profond == $niveau)) ? 1 : 0;
			$type = $nouv_type;

			// d'abord traiter les descentes
			while ($niveau > $profond - $change_type) {
				$ajout .= $pile_li[$niveau];
				$ajout .= $pile_type[$niveau];
				if (!$change_type)
					unset ($pile_li[$niveau]);
				$niveau --;
			}

			// puis les identites (y compris en fin de descente)
			if ($niveau == $profond && !$change_type) {
				$ajout .= $pile_li[$niveau];
			}

			// puis les montees (y compris apres une descente un cran trop bas)
			while ($niveau < $profond) {
				if ($niveau == 0) $ajout .= "\n\n";
				$niveau ++;
				$ajout .= "<$type$class_spip_plus>";
				$pile_type[$niveau] = "</$type>";
			}

			$ajout .= "<li$class_spip>";
			$pile_li[$profond] = "</li>";
		}
		else {
			$ajout = "\n-";	// puce normale ou <hr>
		}

		$texte .= $ajout . $regs[2];
	}

	// retour sur terre
	$ajout = '';
	while ($niveau > 0) {
		$ajout .= $pile_li[$niveau];
		$ajout .= $pile_type[$niveau];
		$niveau --;
	}
	$texte .= $ajout;

	return $texte;
}

/**
 * callback pour la puce qui est definissable/surchargeable
 */
function replace_puce(){
	static $puce;
	if (!isset($puce))
		$puce = "\n<br />".definir_puce()."&nbsp;";
	return $puce;
}
