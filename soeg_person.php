<!DOCTYPE HTML>
<html>
<header>
<title>S&oslash;geresultat af s&oslash;gning af personer</title>
    <meta name="language" content="DA" />
    <meta http-equiv="Content-Language" content="DA" />
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
    <link href="dfi_soeg.css" rel="stylesheet" type="text/css">
</header>
<body>
<div class="wrapper">
<?
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

	echo "<h1>Resultat på s&oslash;gning</h1>";
	$url="http://nationalfilmografien.service.dfi.dk/person.svc/json/list?namecontains=" . urlencode($soeg);
	// set URL and other appropriate options
	curl_setopt($ch, CURLOPT_URL, $url);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_HEADER, 0);
	# echo "<pre>$url</pre>";

	// grab URL and pass it to the browser
	$soegedata_ind=curl_exec($ch);
	$soegedata=json_decode($soegedata_ind);

	// close cURL resource, and free up system resources
	curl_close($ch);
	# print_r($soegedata);
	echo "<ul>\n";
	foreach(array_keys($soegedata) as $cur_person) {
		echo "<li><a href=\"vis_navn.php?nr=" . $soegedata["$cur_person"]->ID . "\">" . htmlentities($soegedata["$cur_person"]->Name, ENT_COMPAT, "UTF-8") . "</a></li>\n";
	}
	echo "</ul>\n";
?>
</div>
</body>
</html>
