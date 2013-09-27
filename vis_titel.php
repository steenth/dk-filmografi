<html>
<?php
// create a new cURL resource

include "password.php";
include "tab/wiki_database_opsaet.php";
#include "testdata.php";

###########################################################################

function vis_header($tekst)
{
?>
<header>
<title><? echo htmlentities($tekst, ENT_COMPAT, "UTF-8") ?></title>
</header>
<body>
<?
	echo "<h1>" . htmlentities($tekst, ENT_COMPAT, "UTF-8") . "</h1>\n";
}

###########################################################################

function hand_film($nr)
{
	$ch = curl_init();

	// set URL and other appropriate options
	curl_setopt($ch, CURLOPT_URL, "http://nationalfilmografien.service.dfi.dk/movie.svc/json/$nr");
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_HEADER, 0);

	// grab URL and pass it to the browser
	$filmdata_ind=curl_exec($ch);

	// close cURL resource, and free up system resources
	curl_close($ch);

	# echo "xx\n$filmdata_ind\nxx\n";
	format_film($filmdata_ind);
}

###########################################################################

function tjk_person($navn, $nr)
{
global $connection;

	$query="select page_title
from page, externallinks
where page_id=el_from
   and page_namespace=0
   and ( el_to=\"http://www.dfi.dk/faktaomfilm/nationalfilmografien/nfperson.aspx?id=$nr\"
   or el_to=\"http://www.dfi.dk/FaktaOmFilm/Nationalfilmografien/nfperson.aspx?id=$nr\")";

	$result = mysql_query($query, $connection);
	if($result===false)
		echo "$query\n";

	if ($row = mysql_fetch_row($result)) {
		$link=$row[0];
		$wikiurl="https://da.wikipedia.org/wiki/" . urlencode(strtr($link, ' ', '_'));
		echo "<a href=\"$wikiurl\">" . htmlentities($link, ENT_COMPAT, "UTF-8") . "</a>";
		return 0;
	}

	$query="select page_title
from page
where page_title = '" . addslashes(strtr($navn, ' ', '_')) . "'
and page_namespace=0";

	$result = mysql_query($query, $connection);
	if($result===false)
		echo "$query\n";

	if ($row = mysql_fetch_row($result)) {
		$wikiurl="https://da.wikipedia.org/wiki/" . urlencode(strtr($navn, ' ', '_'));
		echo "<a href=\"$wikiurl\">" . htmlentities($navn, ENT_COMPAT, "UTF-8") . "</a>";
		return 1;
	}
	echo htmlentities($navn, ENT_COMPAT, "UTF-8");
	return 0;
}

###########################################################################

$konv_rolletype["Script"] = "manuscript";
$konv_rolletype["Direction"] = "instruktion";
$konv_rolletype["Cinematography"] = "foto";
$konv_rolletype["Editing"] = "klipning";
$konv_rolletype["Music"] = "musik";
$konv_rolletype["Production design"] = "produktionsdesign";
$konv_rolletype["Actors"] = "skuespiller";
$konv_rolletype["Stills"] = "stills";

###########################################################################

function format_film($filmdata_ind)
{
global $konv_rolletype;

	$filmdata=json_decode($filmdata_ind);
	# print_r($filmdata);
	vis_header($filmdata->Title);
	if(isset($filmdata->Description))
		echo htmlentities($filmdata->Description, ENT_COMPAT, "UTF-8") . "\n";
	echo "<ol>\n";
	foreach($filmdata->Credits as $cur_credit) {
		echo "<li>";
		$vis_skabelon=tjk_person($cur_credit->Name, $cur_credit->ID);
		if(isset($cur_credit->Description))
			echo ", " . htmlentities($cur_credit->Description, ENT_COMPAT, "UTF-8");
		$cur_type=$cur_credit->Type;
		if(isset($konv_rolletype["$cur_type"]))
			echo ", " . $konv_rolletype["$cur_type"];
		else
			echo ", \$konv_rolletype[\"" . $cur_credit->Type . "\"] = \"xx\";";
		echo " (<a href=\"http://www.dfi.dk/faktaomfilm/nationalfilmografien/nfperson.aspx?id=" . $cur_credit->ID . "\">filmografi</a>)";
		if($vis_skabelon)
			echo " &mdash; {{Danmark Nationalfilmografi navn|" . $cur_credit->ID . "}}";
		echo "</li>\n";
	}
	echo "</ol>\n";
	echo "Kilde nr\n";
}

###########################################################################

	$cur_database="dawiki";

	$opts = getopt("d:tn:");
	$testmode=1;
	$cur_nr=49694;

	if(isset($opts))
	foreach (array_keys($opts) as $opt) switch ($opt) {
	case 'd':
	    // Do something with s parameter
	    $cur_database = $opts['d'];
	    if(!isset($wiki_db_opsaet[$cur_database])) {
		echo "database $cur_database findes ikke\n";
		exit(1);
	    }
	    break;
	case 't': $testmode=1; break;
	case 'n': $cur_nr = $opts['n']; $testmode=1; break;
	default:
		echo "fejl: optfejl $opt\n";
		exit(1);
	}

	if(isset($_GET["nr"])) {
		$cur_nr=$_GET["nr"];
		#if(preg_match("/^[0-9]*$/", $cur_nr)) {
		#	die("$nr ikke et nummer");
		#}
	}


	$connection = mysql_connect($wiki_db_opsaet[$cur_database]['host'], $db_user, $db_passwd);
	mysql_select_db($wiki_db_opsaet[$cur_database]['database'], $connection);

	hand_film($cur_nr);

	# format_film($testdata);

	mysql_close($connection);
?>
</body>
</html>
