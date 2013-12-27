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
	curl_setopt($ch, CURLOPT_URL, "http://nationalfilmografien.service.dfi.dk/movie.svc/json/$nr");
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
	format_film($filmdata_ind);
}

###########################################################################

function note_links($link, $id)
{
global $connection, $fra_link, $til_link;

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
		# echo "$fundet_link 0\n";
	}	

	# indirekte link til film-titel
        $query = "select rd_title
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
		# echo "$fundet_link 1\n";
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
		#echo "$fundet_link 0\n";
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
		# echo "$fundet_link 1 " . $row[0] . " " . $row[1] . "\n";
	}	
}

###########################################################################

function tjk_person($navn, $nr)
{
global $connection, $fra_link, $til_link, $linkstatus;

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
		if(isset($til_link["$link"]) && isset($fra_link["$link"]))
			$linkstatus=" - link ok";
		else if(isset($til_link["$link"]))
			$linkstatus=" - kun link til person findes";
		else if(isset($fra_link["$link"]))
			$linkstatus=" - kun link fra person findes";
		else
			$linkstatus=" - ingen link imellem titel og person";
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
		$link=$row[0];
		echo "<a href=\"$wikiurl\">" . htmlentities($navn, ENT_COMPAT, "UTF-8") . "</a>";
		if(isset($til_link["$link"]) && isset($fra_link["$link"]))
			$linkstatus=" - link ok";
		else if(isset($til_link["$link"]))
			$linkstatus=" - kun link til person findes";
		else if(isset($fra_link["$link"]))
			$linkstatus=" - kun link fra person findes";
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
$konv_rolletype["Voice"] = "stemme";
$konv_rolletype["Production"] = "produktion";
$konv_rolletype["Electrical dept."] = "lys";
$konv_rolletype["Wardrobe"] = "kostumer"; 
$konv_rolletype["Makeup"] = "makeup"; 
$konv_rolletype["Sound"] = "lyd";
$konv_rolletype["Stunt"] = "Stunt";

###########################################################################

function format_film($filmdata_ind)
{
global $konv_rolletype, $connection, $linkstatus;

	$filmdata=json_decode($filmdata_ind);

	$query="select page_title, page_id
from page, externallinks
where page_id=el_from
   and page_namespace=0
   and ( el_to=\"http://www.dfi.dk/faktaomfilm/nationalfilmografien/nffilm.aspx?id=" . $filmdata->ID . "\"
   or el_to=\"http://www.dfi.dk/FaktaOmFilm/Nationalfilmografien/nffilm.aspx?id=" . $filmdata->ID . "\")";

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
where page_title = '" . addslashes(strtr($filmdata->Title, ' ', '_')) . "'
and page_namespace=0";

		$result = mysql_query($query, $connection);
		if($result===false)
			echo "$query\n";

		if ($row = mysql_fetch_row($result)) {
			$wikiurl="https://da.wikipedia.org/wiki/" . urlencode(strtr($filmdata->Title, ' ', '_'));
			$slut="&ndash; Wikipedia:  <a href=\"$wikiurl\">" . htmlentities($filmdata->Title, ENT_COMPAT, "UTF-8") . "</a> &ndash; {{Danmark Nationalfilmografi titel|" . $filmdata->ID . "}}";
			$link=$row[0];
			$id=$row[1];
		} else {
			$id=0;
			$slut="  &mdash; {{Danmark Nationalfilmografi titel|" . $filmdata->ID . "}}";
		}
	}

	if($id)
		note_links($link, $id);

	# print_r($filmdata);
	vis_header($filmdata->Title);
	if(isset($filmdata->Description))
		echo htmlentities($filmdata->Description, ENT_COMPAT, "UTF-8") . "\n";
	echo "<ol>\n";
	foreach($filmdata->Credits as $cur_credit) {
		$linkstatus="";
		echo "<li>";
		$vis_skabelon=tjk_person($cur_credit->Name, $cur_credit->ID);
		if(isset($cur_credit->Description))
			echo ", " . htmlentities($cur_credit->Description, ENT_COMPAT, "UTF-8");
		$cur_type=$cur_credit->Type;
		if(isset($konv_rolletype["$cur_type"]))
			echo ", " . $konv_rolletype["$cur_type"];
		else
			echo ", \$konv_rolletype[\"" . $cur_credit->Type . "\"] = \"xx\";";
		echo " (<a href=\"http://www.dfi.dk/faktaomfilm/nationalfilmografien/nfperson.aspx?id=" . $cur_credit->ID . "\">filmografi</a>, <a href=\"vis_navn.php?nr=" . $cur_credit->ID . "\">navn</a>)";
		if($vis_skabelon)
			echo " &mdash; {{Danmark Nationalfilmografi navn|" . $cur_credit->ID . "}}";
		echo "$linkstatus</li>\n";
	}
	echo "</ol>\n";
	echo "<p>Kilde <a href=\"http://www.dfi.dk/faktaomfilm/nationalfilmografien/nffilm.aspx?id=" . $filmdata->ID . "\">DFI filmdata</a>\n";
	echo "$slut</p>\n";
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
			format_film($testdata);
		}
		fclose($handle);
	} else hand_film($cur_nr);

	# format_film($testdata);

	mysql_close($connection);
	if($dumpmode==0) { ?>
</div>
</body>
</html>
<? }
?>
