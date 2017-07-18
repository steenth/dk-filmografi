<!DOCTYPE HTML>
<html>
<header>
<title>S&oslash;geresultat af s&oslash;gning af titler</title>
    <meta name="language" content="DA" />
    <meta http-equiv="Content-Language" content="DA" />
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
    <link href="dfi_soeg.css" rel="stylesheet" type="text/css">
</header>
<body>
<div class="wrapper">
<?php

include "password.php";

	$opts = getopt("s:d:D");

	if(isset($opts) && is_array($opts))
	foreach (array_keys($opts) as $opt) switch ($opt) {
	case 's': $soeg = $opts['s']; break;
	default:
		echo "fejl: optfejl $opt\n";
		exit(1);
	}

	if(isset($_GET["soeg"])) {
		$soeg=$_GET["soeg"];
		#if(!preg_match("/^[0-9]*$/", $cur_nr)) {
		#	die("$nr ikke et nummer");
		#}
	}

	$ch = curl_init();

	// set URL and other appropriate options
	$url="https://api.dfi.dk/v1/film?title=" . urlencode($soeg);
	curl_setopt($ch, CURLOPT_URL, $url);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_HEADER, 0);
	curl_setopt($ch, CURLOPT_USERPWD, "$dfi_user:$dfi_passwd");

	echo "<h1>Resultat på s&oslash;gning</h1>";
	// grab URL and pass it to the browser
	$soegedata_ind=curl_exec($ch);
	$soegedata=json_decode($soegedata_ind);

	// close cURL resource, and free up system resources
	curl_close($ch);
	# print_r($soegedata);
	echo "<ul>\n";
	foreach($soegedata->FilmList as $cur_titel) {
		echo "<li><a href=\"vis_titel.php?nr=" . $cur_titel->Id . "\">" . htmlentities($cur_titel->Title, ENT_COMPAT, "UTF-8") . "</a></li>\n";
	}
	echo "</ul>\n";
?>
</div>
</body>
</html>
