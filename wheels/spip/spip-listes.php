<?php
# not usefull as this file is include by the engine itself
# require_once 'engine/textwheel.php';

function tw_liste_init($t){
	return tw_liste_item($t,'init');
}

function tw_liste_close($t){
	return tw_liste_item($t,'close');
}

function tw_liste_item_ul($t){
	return tw_liste_item($t,'ul');
}

function tw_liste_item_ol($t){
	return tw_liste_item($t,'ol');
}

function tw_liste_item($t,$quoi='ul'){
	global $class_spip, $class_spip_plus;
	static $niveau;
	static $pile_li;
	static $pile_type;
	static $type;

	switch ($quoi){
		case 'init':
			$niveau = 0;
			$pile_li = array();
			$pile_type = array();
			$type = '';
			break;
		case 'close':
			// retour sur terre
			$ajout = '';
			while ($niveau > 0) {
				$ajout .= $pile_li[$niveau];
				$ajout .= $pile_type[$niveau];
				$niveau --;
			}
			$t .= $ajout;
			break;

		
		case 'ul':
		case 'ol':
		default:
			$profond = strlen($t[2]);

			if ($profond > 0) {
				$ajout='';

				// changement de type de liste au meme niveau : il faut
				// descendre un niveau plus bas, fermer ce niveau, et
				// remonter
				$nouv_type = ($quoi=='ol') ? 'ol' : 'ul';
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
					if ($niveau == 0)
						$ajout .= "\n\n";
					elseif (!isset($pile_li[$niveau])) {
						$ajout .= "<li$class_spip>";
						$pile_li[$niveau] = "</li>";
					}
					$niveau ++;
					$ajout .= "<$type$class_spip_plus>";
					$pile_type[$niveau] = "</$type>";
				}

				$ajout .= "<li$class_spip>";
				$pile_li[$profond] = "</li>";
			}
			else {
				$ajout = $t[1];	// puce normale ou <hr>
			}

			$t = $ajout . $t[3];
			break;
	}

	return $t;
}