<?php

###########################################################################

include "password.php";
include "tab/wiki_database_opsaet.php";
include "include/vislinks.php";
include "include/falsk_positiv.php";

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

function note_links($link, $id)
{
global $connection, $fra_link, $til_link, $verbose;

	# direkte link til film-titel
	$query="select pl_title
from pagelinks, page
where pl_from=$id
and page_title=pl_title
and page_namespace = pl_namespace
and page_is_redirect=0
and pl_namespace = 0";

	$result = mysql_query($query, $connection);
	if($result===false)
		echo "$query\n";

	while ($row = mysql_fetch_row($result)) {
		$fundet_link=$row[0];
		$til_link["$fundet_link"] = 0;
		if($verbose)
			echo "til $fundet_link 0\n";
	}	

	# indirekte link til film-titel
        $query = "select rd_title, pl_title
from page, pagelinks, redirect
where pl_from = $id
and page_title = pl_title
and page_namespace = pl_namespace
and page_is_redirect!=0
and pl_namespace = 0
and rd_from = page_id
and rd_namespace = 0";
	
	$result = mysql_query($query, $connection);
	if($result===false)
		echo "$query\n";

	while ($row = mysql_fetch_row($result)) {
		$fundet_link=$row[0];
		$til_link["$fundet_link"] = 1;
		if($verbose)
			echo "til $fundet_link 1 " . $row[0] . " " . $row[1] . "\n";
	}	

	$query="select page_title
from page, pagelinks
where pl_title='" . addslashes($link) . "'
and page_id=pl_from
and page_namespace=0
and pl_namespace=0";

	$result = mysql_query($query, $connection);
	if($result===false)
		echo "$query\n";

	while ($row = mysql_fetch_row($result)) {
		$fundet_link=$row[0];
		$fra_link["$fundet_link"] = 0;
		if($verbose)
			echo "fra $fundet_link 0\n";
	}	

	$query="select p1.page_title, p2.page_title
from page p1, redirect, pagelinks, page p2
where rd_title='" . addslashes($link) . "'
and p1.page_id=rd_from
and rd_namespace=0
and p1.page_namespace=0
and pl_title=p1.page_title
and pl_namespace=0
and pl_from=p2.page_id
and p2.page_namespace=0";

	$result = mysql_query($query, $connection);
	if($result===false)
		echo "$query\n";

	while ($row = mysql_fetch_row($result)) {
		$fundet_link=$row[1];
		$fra_link["$fundet_link"] = 1;
		if($verbose)
			echo "fra $fundet_link 1 " . $row[0] . " " . $row[1] . "\n";
	}	
}

###########################################################################

function tjk_titel($navn, $nr)
{
global $connection, $fra_link, $til_link, $linkstatus, $res, $falsk_positiv_titel;

	$query="select page_title, el_to
from page, externallinks
where page_id=el_from
   and page_namespace=0
   and ( el_to=\"http://www.dfi.dk/faktaomfilm/nationalfilmografien/nffilm.aspx?id=$nr\"
   or el_to=\"http://www.dfi.dk/FaktaOmFilm/Nationalfilmografien/nffilm.aspx?id=$nr\")";

	$result = mysql_query($query, $connection);
	if($result===false)
		echo "$query\n";

	$antal=0;
	while ($row = mysql_fetch_row($result)) {
		$wlink=$row[0];
		if(!isset($falsk_positiv_titel["$nr"]["$wlink"])) {
			$note_titel["$antal"]=$row[0];
			$url=$row[1];
			$antal++;
		}
	}

	if($antal>1) {
		echo "<li>duplet";
		foreach($note_titel as $cur_titel) {
			$wikiurl="https://da.wikipedia.org/wiki/" . urlencode(strtr($cur_titel, ' ', '_'));
			echo " - \$falsk_positiv_titel[\"$nr\"][\"$cur_titel\"] = 0; <a href=\"$wikiurl\">$cur_titel</a>";	
		}
		echo " - <a href=\"" . $url . "\">" . $nr . "</a>\n";
		return 0;
	}

	if ($antal == 1) {
		$link=$note_titel[0];
		$wikiurl="https://da.wikipedia.org/wiki/" . urlencode(strtr($link, ' ', '_'));
		if($res)
			echo htmlentities(strtr($link, '_', ' '), ENT_COMPAT, "UTF-8");
		else
			echo "<a href=\"$wikiurl\">" . htmlentities(strtr($link, '_', ' '), ENT_COMPAT, "UTF-8") . "</a>";

		if(strtr($link, '_', ' ') != $navn)
			echo " (D)";

		if(isset($til_link["$link"]) && isset($fra_link["$link"]))
			$linkstatus=" - link ok";
		else if(isset($til_link["$link"]))
			$linkstatus=" - kun link til titel findes";
		else if(isset($fra_link["$link"]))
			$linkstatus=" - kun link fra titel findes";
		else if(!isset($fra_link) && !isset($til_link))
			$linkstatus="";
		else
			$linkstatus=" - ingen link imellem titel og person";
		return 0;
	}

	$query="select page_title, page_is_redirect, page_id
from page
where page_title = '" . addslashes(strtr($navn, ' ', '_')) . "'
and page_namespace=0";

	$result = mysql_query($query, $connection);
	if($result===false)
		echo "$query\n";

	if ($row = mysql_fetch_row($result)) {
		$link=$row[0];
		if($row[1]) {
			$rd_id=$row[2];
			$query="select rd_title
from redirect
where rd_from = $rd_id";

			$result = mysql_query($query, $connection);
			if($result===false)
				echo "$query\n";
			if ($row = mysql_fetch_row($result))
				$link=$row[0];
			else
				die("redirect $rd_id mis\n");

			$tillaeg=" (R)";
		} else
			$tillaeg="";
		$wikiurl="https://da.wikipedia.org/wiki/" . urlencode($link);
		echo "<a href=\"$wikiurl\">" . htmlentities($navn, ENT_COMPAT, "UTF-8") . "$tillaeg</a>";
		if(isset($til_link["$link"]) && isset($fra_link["$link"]))
			$linkstatus=" - link ok";
		else if(isset($til_link["$link"]))
			$linkstatus=" - kun link til titel findes";
		else if(isset($fra_link["$link"]))
			$linkstatus=" - kun link fra titel findes";
		else if(!isset($fra_link) && !isset($til_link))
			$linkstatus="";
		else
			$linkstatus=" - ingen link imellem titel og person";
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
global $konv_rolletype, $connection, $linkstatus;

	$filmdata=json_decode($filmdata_ind);

	$query="select page_title, page_id
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
		$id=$row[1];
		$wikiurl="https://da.wikipedia.org/wiki/" . urlencode(strtr($link, ' ', '_'));
		$slut="  Wikipedia: <a href=\"$wikiurl\">" . htmlentities(strtr($link, '_', ' '), ENT_COMPAT, "UTF-8") . "</a>";
	} else {

		$query="select page_title, page_id
from page
where page_title = '" . addslashes(strtr($filmdata->Name, ' ', '_')) . "'
and page_namespace=0";

		$result = mysql_query($query, $connection);
		if($result===false)
			echo "$query\n";

		if ($row = mysql_fetch_row($result)) {
			$wikiurl="https://da.wikipedia.org/wiki/" . urlencode(strtr($filmdata->Name, ' ', '_'));
			$slut="&ndash; Wikipedia:  <a href=\"$wikiurl\">" . htmlentities($filmdata->Name, ENT_COMPAT, "UTF-8") . "</a> &ndash; {{Danmark Nationalfilmografi navn|" . $filmdata->ID . "}}";
			$link=$row[0];
			$id=$row[1];
		} else {
			$id=0;
			$slut="  &mdash; {{Danmark Nationalfilmografi navn|" . $filmdata->ID . "}}";
		}
	}
	if($id)
		note_links($link, $id);

	# print_r($filmdata);
	vis_header($filmdata->Name);
	if(isset($filmdata->Description)) {
		if($filmdata->Description!="")
			echo htmlentities($filmdata->Description, ENT_COMPAT, "UTF-8") . "\n";
	}
	echo "<h2>Filmografi</h2>";
	echo "<ol>\n";
	foreach($filmdata->Movies as $cur_movie) {
		$linkstatus="";
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
		echo " (<a href=\"http://www.dfi.dk/faktaomfilm/nationalfilmografien/nffilm.aspx?id=" . $cur_movie->ID . "\">filmografi</a>, <a href=\"vis_titel.php?nr=" . $cur_movie->ID . "\">tjek titel</a>)";
		if($vis_skabelon)
			echo " &mdash; {{Danmark Nationalfilmografi titel|" . $cur_movie->ID . "}}";
		echo "$linkstatus</li>\n";
	}
	echo "</ol>\n";
	echo "<p>Kilde <a href=\"http://www.dfi.dk/faktaomfilm/nationalfilmografien/nfperson.aspx?id=" . $filmdata->ID . "\">DFI filmdata</a>\n";
	echo "$slut" . handtere_links($id) . "</p>\n";
}

###########################################################################

	$cur_database="dawiki";

	$opts = getopt("d:n:j:Dv");
	$cur_nr=49694;
	$jsonfil=null;
	$dumpmode=0;
	$verbose=0;
	$res = 0;

	if(isset($opts) && is_array($opts))
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
	case 'v': $verbose++; break;
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
	if(isset($_GET["res"]))
		$res = 1;


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
