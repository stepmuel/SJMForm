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

$in = file_get_contents('form.txt');

$form = new SJMForm($in);

$data = $form->preprocess($_POST);

if (count($_POST)) {
	$form->set($data);
}

echo '<form action="index.php" method="post" accept-charset="utf-8">'."\n";
echo $form;
echo '<p><input type="submit" value="Submit"></p></form>'."\n";

echo "<pre>".print_r($data,true)."</pre>\n"; 

?>
</body>
</html>
