<!DOCTYPE html PUBLIC "-//W3C//DTD HTML 4.01//EN"
   "http://www.w3.org/TR/html4/strict.dtd">

<html lang="en">
<head>
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
	<title></title>
	<meta name="generator" content="TextMate http://macromates.com/">
	<meta name="author" content="Stephan Müller">
</head>
<body>
<?php

error_reporting(E_ALL|E_WARNING);
ini_set('display_errors', '1');

require_once "SJMForm.php";

if (isset($_POST['form'])) {
	$in = $_POST['form'];
	
	try {
		$form = new SJMForm($in);
	} catch (Exception $e) {
		echo "<pre>Form parser error:\n".$e->getMessage();
		exit;
	}

	$data = $form->preprocess($_POST);

	if (count($_POST)>1) {
		$form->set($data);
	}
	
	echo "<a href=\"demo.php\">back</a>\n";
	echo "<h2>Generated Form</h2>\n";
	echo '<form action="demo.php" method="post" accept-charset="utf-8">'."\n";
	echo '<input type="hidden" name="form" value="'.htmlentities($in).'">'."\n";
	echo $form;
	echo '<p><input type="submit" value="Submit"></p></form>'."\n";
	echo "<h2>Received Form Data</h2>\n";
	echo "<pre>".print_r($data,true)."</pre>\n";
} else {
	$in = file_get_contents('form.txt');
	
	echo "<h2>Form Generator Demo</h2>\n";
	echo '<form action="demo.php" method="post" accept-charset="utf-8">'."\n";
	echo '<dt><label for="form">Form Markup Language</label></dt>'."\n";
	echo '<dd><textarea name="form" rows="32" cols="80" id="form">'.htmlentities($in).'</textarea></dd>'."\n";
	echo '<p><input type="submit" value="Submit"></p></form>'."\n";
}



?>
</body>
</html>
