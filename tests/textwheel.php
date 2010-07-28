<?php
/**
 * Test unitaire de TextWheel
 *
 */

	$test = 'TextWheel';
	$remonte = "../";
	while (!is_dir($remonte."ecrire"))
		$remonte = "../$remonte";
	require $remonte.'tests/test.inc';
	find_in_path("engine/tests/textwheeltest.php",'',true);

	$testeur = new TextWheelTester(new TextWheelTestSet('textwheel.yaml'));
	$results = $testeur->test();

	$err = array();
	foreach($results as $titre=>$res){
		if (!$res['result'])
			$err[] = display_error($titre,'TextWheel()->text("'.$res['test']->input.'")',$res['output'],$res['test']->output);
	}
	
	// si le tableau $err est pas vide ca va pas
	if ($err) {
		die ('<dl>' . join('', $err) . '</dl>');
	}

	echo "OK";

?>