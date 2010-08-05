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
