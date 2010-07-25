<?php

# usage: php wheels/spip.php

require_once 'engine/textwheel.php';

function ruleset($file) {
	require_once 'lib/yaml/sfYaml.php';
	return sfYaml::load('wheels/spip/'.$file);
}

function raccourcis($t) {
	static $wheel;
	if (!isset($wheel))
		$wheel = new TextWheel(ruleset('spip.yaml'));
	return $wheel->text($t);
}
function paragrapher($t) {
	static $wheel;
	if (!isset($wheel))
		$wheel = new TextWheel(ruleset('spip-paragrapher.yaml'));
	return $wheel->text($t);
}

// callback pour poesie
// (a remplacer par une sous-liste = indentation de yaml ?)
function replace_poesie($m) {
	static $wheel;
	if (!isset($wheel))
		$wheel = new TextWheel(ruleset('spip-poesie.yaml'));
	return $wheel->text($m[2]);
}

/*
function propre($t) {
	static $wheel;
	if (!isset($wheel)) {
		$wheel = new TextWheel(ruleset('spip.yaml'));
		$wheel->addRules(ruleset('spip-paragrapher.yaml'));
	}
	return $wheel->text($t);
}
*/

function propre($t) {
	return yaml_wheel($t, array('spip.yaml', 'spip-paragrapher.yaml'));
}

function yaml_wheel($t, $yaml=array()) {
	static $wheels;
	$co = crc32(serialize($yaml));
	if (!isset($wheels[$co])) {
		$wheels[$co] = new TextWheel();
		foreach ($yaml as $f)
			$wheels[$co]->addRules(ruleset($f));
	}
	return $wheels[$co]->text($t);
}


$texte = 	'


joli :{{{o}}}
	<poetry>
	I love
	you
	my love
	</poetry>

----

- puce
_ br
	'
;

echo propre($texte);

#echo paragrapher(raccourcis($texte));


#var_dump(ruleset('spip-paragrapher.yaml'));