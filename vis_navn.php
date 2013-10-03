<?php
// create a new cURL resource

include "password.php";
include "tab/wiki_database_opsaet.php";
#include "testdata.php";

###########################################################################

function vis_header($tekst)
{
?>
<!DOCTYPE HTML>
<html>
<header>
<title><? echo htmlentities($tekst, ENT_COMPAT, "UTF-8") ?></title>
    <meta name="language" content="DA" />
    <meta http-equiv="Content-Language" content="DA" />
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
    <link href="dfi_soeg.css" rel="stylesheet" type="text/css">
</header>
<body>
<div class="wrapper">
<?
	echo "<h1>" . htmlentities($tekst, ENT_COMPAT, "UTF-8") . "</h1>\n";
}

###########################################################################

function hand_film($nr)
{
global $dumpmode;

	$ch = curl_init();

	// set URL and other appropriate options
	curl_setopt($ch, CURLOPT_URL, "http://nationalfilmografien.service.dfi.dk/person.svc/json/$nr");
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_HEADER, 0);

	// grab URL and pass it to the browser
	$filmdata_ind=curl_exec($ch);

	// close cURL resource, and free up system resources
	curl_close($ch);
	if($dumpmode) {
		echo "$filmdata_ind\n";
		return;
	}

	# echo "xx\n$filmdata_ind\nxx\n";
	format_person($filmdata_ind);
}

###########################################################################

function tjk_titel($navn, $nr)
{
global $connection;

	$query="select page_title
from page, externallinks
where page_id=el_from
   and page_namespace=0
   and ( el_to=\"http://www.dfi.dk/faktaomfilm/nationalfilmografien/nffilm.aspx?id=$nr\"
   or el_to=\"http://www.dfi.dk/FaktaOmFilm/Nationalfilmografien/nffilm.aspx?id=$nr\")";

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
$konv_rolletype["Medvirkende"] = "medvirkende";
$konv_rolletype["Stemme"] = "stemme";

###########################################################################

function format_person($filmdata_ind)
{
global $konv_rolletype, $connection;

	$filmdata=json_decode($filmdata_ind);
	# print_r($filmdata);
	vis_header($filmdata->Name);
	if(isset($filmdata->Description)) {
		if($filmdata->Description!="")
			echo htmlentities($filmdata->Description, ENT_COMPAT, "UTF-8") . "\n";
	}
	echo "<ol>\n";
	foreach($filmdata->Movies as $cur_movie) {
		echo "<li>";
		$vis_skabelon=tjk_titel($cur_movie->Name, $cur_movie->ID);
		if(isset($cur_movie->Description))
			if($filmdata->Description!="")
				echo ", " . htmlentities($cur_movie->Description, ENT_COMPAT, "UTF-8");
		$cur_type=$cur_movie->Type;
		if(isset($konv_rolletype["$cur_type"]))
			echo ", " . $konv_rolletype["$cur_type"];
		else
			echo ", " . htmlentities($cur_movie->Type, ENT_COMPAT, "UTF-8") . "";
		echo " (<a href=\"http://www.dfi.dk/faktaomfilm/nationalfilmografien/nffilm.aspx?id=" . $cur_movie->ID . "\">filmografi</a>, <a href=\"vis_titel.php?nr=" . $cur_movie->ID . "\">filmtitel</a>)";
		if($vis_skabelon)
			echo " &mdash; {{Danmark Nationalfilmografi titel|" . $cur_movie->ID . "}}";
		echo "</li>\n";
	}
	echo "</ol>\n";
	echo "Kilde <a href=\"http://www.dfi.dk/faktaomfilm/nationalfilmografien/nfperson.aspx?id=" . $filmdata->ID . "\">DFI filmdata</a>\n";

	$query="select page_title
from page, externallinks
where page_id=el_from
   and page_namespace=0
   and ( el_to=\"http://www.dfi.dk/faktaomfilm/nationalfilmografien/nfperson.aspx?id=" . $filmdata->ID . "\"
   or el_to=\"http://www.dfi.dk/FaktaOmFilm/Nationalfilmografien/nfperson.aspx?id=" . $filmdata->ID . "\")";

	$result = mysql_query($query, $connection);
	if($result===false)
		echo "$query\n";

	if ($row = mysql_fetch_row($result)) {
		$link=$row[0];
		$wikiurl="https://da.wikipedia.org/wiki/" . urlencode(strtr($link, ' ', '_'));
		echo "  Wikipedia: <a href=\"$wikiurl\">" . htmlentities(strtr($link, '_', ' '), ENT_COMPAT, "UTF-8") . "</a>";
		return;
	}

	$query="select page_title
from page
where page_title = '" . addslashes(strtr($filmdata->Name, ' ', '_')) . "'
and page_namespace=0";

	$result = mysql_query($query, $connection);
	if($result===false)
		echo "$query\n";

	if ($row = mysql_fetch_row($result)) {
		$wikiurl="https://da.wikipedia.org/wiki/" . urlencode(strtr($filmdata->Name, ' ', '_'));
		echo "&ndash; Wikipedia:  <a href=\"$wikiurl\">" . htmlentities($filmdata->Name, ENT_COMPAT, "UTF-8") . "</a> &ndash; {{Danmark Nationalfilmografi navn|" . $filmdata->ID . "}}";
		return;
	}
	echo "  &mdash; {{Danmark Nationalfilmografi navn|" . $filmdata->ID . "}}";
}

###########################################################################

	$cur_database="dawiki";

	$opts = getopt("d:n:j:D");
	$cur_nr=49694;
	$jsonfil=null;
	$dumpmode=0;

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
	case 'n': $cur_nr = $opts['n']; break;
	case 'j': $jsonfil = $opts['j']; break;
	case 'D': $dumpmode=1; break;
	default:
		echo "fejl: optfejl $opt\n";
		exit(1);
	}

	if(isset($_GET["nr"])) {
		$cur_nr=$_GET["nr"];
		if(!preg_match("/^[0-9]*$/", $cur_nr)) {
			die("$nr ikke et nummer");
		}
	}


	$connection = mysql_connect($wiki_db_opsaet[$cur_database]['host'], $db_user, $db_passwd);
	mysql_select_db($wiki_db_opsaet[$cur_database]['database'], $connection);

	if($jsonfil) {
		$handle = fopen("$jsonfil", "r");
		while(!feof($handle))
		{
			$testdata = fgets($handle);
			if($testdata=="") break;
			format_person($testdata);
		}
		fclose($handle);
	} else hand_film($cur_nr);

	# format_person($testdata);

	mysql_close($connection);
	if($dumpmode==0) { ?>
</div>
</body>
</html>
<? }
?>
