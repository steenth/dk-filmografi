<html>
<header>
<title>Søgeresultat af søgning af personer</title>
</header>
<body>
<?
	$opts = getopt("s:d:D");

	if(isset($opts))
	foreach (array_keys($opts) as $opt) switch ($opt) {
	case 's': $soeg = $opts['s']; break;
	default:
		echo "fejl: optfejl $opt\n";
		exit(1);
	}

	if(isset($_POST["soeg"])) {
		$cur_nr=$_POST["soeg"];
		#if(!preg_match("/^[0-9]*$/", $cur_nr)) {
		#	die("$nr ikke et nummer");
		#}
	}

	$ch = curl_init();

	// set URL and other appropriate options
	curl_setopt($ch, CURLOPT_URL, "http://nationalfilmografien.service.dfi.dk/person.svc/json/list?namecontains=$soeg");
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_HEADER, 0);

	// grab URL and pass it to the browser
	$soegedata_ind=curl_exec($ch);
	$soegedata=json_decode($soegedata_ind);

	// close cURL resource, and free up system resources
	curl_close($ch);
	# print_r($soegedata);
	echo "<ol>\n";
	foreach(array_keys($soegedata) as $cur_person) {
		echo "<li><a href=\"vis_navn.php?nr=" . $soegedata["$cur_person"]->ID . "\">" . htmlentities($soegedata["$cur_person"]->Name, ENT_COMPAT, "UTF-8") . "</a></li>\n";
	}
	echo "</ol>\n";
?>
</body>
</html>
