<?php

###########################################################################

include "password.php";
include "tab/wiki_database_opsaet.php";
include "include/vislinks.php";
include "include/falsk_positiv.php";
include "include/rolle.php";

###########################################################################

function vis_header($tekst)
{
?>
<!DOCTYPE HTML>
<html>
<header>
<title><?php echo htmlentities($tekst, ENT_COMPAT, "UTF-8") ?></title>
    <meta name="language" content="DA" />
    <meta http-equiv="Content-Language" content="DA" />
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
    <link href="dfi_soeg.css" rel="stylesheet" type="text/css">
</header>
<body>
<div class="wrapper">
<?php
	echo "<h1>" . htmlentities($tekst, ENT_COMPAT, "UTF-8") . "</h1>\n";
}

###########################################################################

function hand_film($nr)
{
global $dumpmode, $dfi_user, $dfi_passwd;

	$ch = curl_init();

	// set URL and other appropriate options
	$api_url="https://api.dfi.dk/v1/person/$nr";
	curl_setopt($ch, CURLOPT_URL, $api_url);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_HEADER, 0);
	curl_setopt($ch, CURLOPT_USERPWD, "$dfi_user:$dfi_passwd");

	// grab URL and pass it to the browser
	$filmdata_ind=curl_exec($ch);
	if($errno = curl_errno($ch)) {
		$error_message = curl_strerror($errno);
    		echo "cURL error ({$errno}):\n {$error_message}";
	}

	if($filmdata_ind===false) {
		echo "Ingen data fra api'et\n";
		curl_close($ch);
		return;
	} else if($filmdata_ind=="") {
		echo "Tom data fra api'et\n";
		curl_close($ch);
		return;
	}

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
	$query="select lt_title
from pagelinks, linktarget, page
where pl_from=$id
and page_title=lt_title
and page_namespace = lt_namespace
and pl_target_id = lt_id
and page_is_redirect=0
and lt_namespace = 0";

	$result = $connection->query($query);
	if($result===false)
		echo "$query\n";

	while ($row = $result->fetch_object()) {
		$til_link["$row->lt_title"] = 0;
		if($verbose)
			echo "til $row->lt_title 0\n";
	}	

	# indirekte link til film-titel
        $query = "select rd_title, lt_title
from page, linktarget, pagelinks, redirect
where pl_from = $id
and page_title = lt_title
and page_namespace = lt_namespace
and pl_target_id = lt_id
and page_is_redirect!=0
and lt_namespace = 0
and rd_from = page_id
and rd_namespace = 0";
	
	$result = $connection->query($query);
	if($result===false)
		echo "$query\n";

	while ($row = $result->fetch_object()) {
		$til_link["$row->rd_title"] = 1;
		if($verbose)
			echo "til $row->rd_title 1 " . $row->rd_title . " " . $row->lt_title . "\n";
	}	

	$query="select page_title
from page, linktarget, pagelinks
where lt_title='" . addslashes($link) . "'
and page_id=pl_from
and pl_target_id = lt_id
and page_namespace=0
and lt_namespace=0";

	$result = $connection->query($query);
	if($result===false)
		echo "$query\n";

	while ($row = $result->fetch_object()) {
		$fra_link["$row->page_title"] = 0;
		if($verbose)
			echo "fra $row->page_title 0\n";
	}	

	$query="select p1.page_title, p2.page_title
from page p1, redirect, linktarget, pagelinks, page p2
where rd_title='" . addslashes($link) . "'
and p1.page_id=rd_from
and rd_namespace=0
and p1.page_namespace=0
and lt_title=p1.page_title
and lt_namespace=0
and pl_from=p2.page_id
and p2.page_namespace=0";

	$result = $connection->query($query);
	if($result===false)
		echo "$query\n";

	while ($row = $result->fetch_row()) {
		$fundet_link=$row[1];
		$fra_link["$fundet_link"] = 1;
		if($verbose)
			echo "fra $fundet_link 1 " . $row[0] . " " . $row[1] . "\n";
	}	
}

###########################################################################

function tjk_titel($navn, $nr)
{
global $connection, $fra_link, $til_link, $til_link_brugt, $linkstatus, $res, $falsk_positiv_titel, $mangler_match;

	$query="select page_title, el_to_path
from page, externallinks
where page_id=el_from
   and page_namespace=0
   and el_to_domain_index = \"https://dk.dfi.www.\"
   and el_to_path=\"/viden-om-film/filmdatabasen/film/$nr\"";

	$result = $connection->query($query);
	if($result===false)
		echo "$query\n";

	$antal=0;
	$last_titel="xdfdfsd";
	while ($row = $result->fetch_object()) {
		if($last_titel==$row->page_title) {} # håndere samme titel forskel url til dfi
		else if(!isset($falsk_positiv_titel["$nr"]["$row->page_title"])) {
			$note_titel["$antal"]=$row->page_title;
			$ind_url="https://www.dfi.dk" . $row->el_to_path;
			$antal++;
			$last_titel=$row->page_title;
		}
	}

	if($antal>1) {
		echo "<li>duplet";
		foreach($note_titel as $cur_titel) {
			$wiki_url="https://da.wikipedia.org/wiki/" . urlencode(strtr($cur_titel, ' ', '_'));
			echo " - \$falsk_positiv_titel[\"$nr\"][\"$cur_titel\"] = 0; <a href=\"$wiki_url\">$cur_titel</a>";	
		}
		echo " - <a href=\"" . $ind_url . "\">" . $nr . "</a>\n";
		return 0;
	}

	if($antal==1) {
		$link=$note_titel[0];
		$wiki_url="https://da.wikipedia.org/wiki/" . urlencode(strtr($link, ' ', '_'));
		if($res)
			echo htmlentities(strtr($link, '_', ' '), ENT_COMPAT, "UTF-8");
		else
			echo "<a href=\"$wiki_url\">" . htmlentities(strtr($link, '_', ' '), ENT_COMPAT, "UTF-8") . "</a>";

		if(strtr($link, '_', ' ') != $navn) {
			echo " (D)";
			$vis_link=strtr($link, '_', ' ') . "|" . strtr($navn, '_', ' ');
		} else
			$vis_link=strtr($link, '_', ' ');

		if(isset($til_link["$link"]))
			$til_link_brugt["$link"]=1;

		if(isset($til_link["$link"]) && isset($fra_link["$link"]))
			$linkstatus=" - link ok";
		else if(isset($til_link["$link"]))
			$linkstatus=" - kun link til titel findes";
		else if(isset($fra_link["$link"])) {
			$linkstatus=" - kun link fra titel findes";
			$mangler_match .= "* [[$vis_link]]\n";
		}
		else if(!isset($fra_link) && !isset($til_link))
			$linkstatus="";
		else {
			$linkstatus=" - ingen link imellem titel og person";
			$mangler_match .= "* [[$vis_link]]\n";
		}
		return 0;
	}

	$query="select page_title, page_is_redirect, page_id
from page
where page_title = '" . addslashes(strtr($navn, ' ', '_')) . "'
and page_namespace=0";

	$result = $connection->query($query);
	if($result===false)
		echo "$query\n";

	if ($row = $result->fetch_object()) {
		$link=$row->page_title;
		if($row->page_is_redirect) {
			$rd_id=$row->page_id;

			$query="select rd_title
from redirect
where rd_from = $rd_id";

			$result = $connection->query($query);
			if($result===false)
				echo "$query\n";
			if ($row = $result->fetch_object())
				$link=$row->rd_title;
			else
				die("redirect $rd_id mis\n");

			$tillaeg=" (R)";
		} else
			$tillaeg="";

		if(isset($til_link["$link"]))
			$til_link_brugt["$link"]=1;

		$wiki_url="https://da.wikipedia.org/wiki/" . urlencode($link);
		echo "<a href=\"$wiki_url\">" . htmlentities($navn, ENT_COMPAT, "UTF-8") . "$tillaeg</a>";
		if(isset($til_link["$link"]) && isset($fra_link["$link"]))
			$linkstatus=" - link ok";
		else if(isset($til_link["$link"]))
			$linkstatus=" - kun link til titel findes";
		else if(isset($fra_link["$link"])) {
			$linkstatus=" - kun link fra titel findes";
			$mangler_match .= "* [[" . strtr($link, '_', ' ') . "]]\n";
		}
		else if(!isset($fra_link) && !isset($til_link))
			$linkstatus="";
		else {
			$linkstatus=" - ingen link imellem titel og person";
			$mangler_match .= "* [[" . strtr($link, '_', ' ') . "]]\n";
		}
		return 1;
	}
	echo htmlentities($navn, ENT_COMPAT, "UTF-8");
	return 0;
}

###########################################################################

function tjk_ikkebrugt()
{
global $til_link, $til_link_brugt, $connection;

	$query="select lt_title
from pagelinks, linktarget, page
where page_id=pl_from
   and page_namespace=2
   and lt_namespace=0
   and page_title='Steenth/Danske_film_tjek'";

	$result = $connection->query($query);
	if($result===false)
		echo "$query\n";

	$liste="";

	while ($row = $result->fetch_object()) {
		$tmp = $row->lt_title;
		if(isset($til_link["$tmp"]) && !isset($til_link_brugt["$tmp"])) {
			$wiki_url="https://da.wikipedia.org/wiki/" . urlencode(strtr($tmp, ' ', '_'));
			$xx_url="<a href=\"$wiki_url\">" . htmlentities(strtr($tmp, '_', ' '), ENT_COMPAT, "UTF-8") . "</a>";
			if($liste=="")
				$liste=$xx_url;
			else
				$liste.=", " . $xx_url;
		}
		#else if(isset($til_link["$tmp"]))
			#$liste .= "- $tmp";
	}	
	if($liste!="")
		echo "<p>Titler som mangler: $liste</p>\n";

}

###########################################################################

function format_person($filmdata_ind)
{
global $konv_rolletype, $connection, $linkstatus, $falsk_positiv_person, $verbose, $mangler_match;

	$filmdata=json_decode($filmdata_ind);

	if(!isset($filmdata->Id)) {
		echo "Kan ikke finde Id i data\n";
		return;
	}

	$query="select page_title, page_id
from page, externallinks
where page_id=el_from
   and page_namespace=0
   and el_to_domain_index = \"https://dk.dfi.www.\"
   and el_to_path=\"/viden-om-film/filmdatabasen/person/" . $filmdata->Id . "\"";

	$result = $connection->query($query);
	if($result===false)
		echo "$query\n";

	while ($row = $result->fetch_object()) {
		if(isset($falsk_positiv_person["$filmdata->Id"]["$row->page_title"])) {
			if($verbose)
				echo "skip $filmdata->Id $row->page_title\n";
		}
		else {
			if($verbose)
				echo "følg: $filmdata->Id $row->page_title\n";
			$link=$row->page_title;
			$id=$row->page_id;
			$wiki_url="https://da.wikipedia.org/wiki/" . urlencode(strtr($link, ' ', '_'));
			$slut="  Wikipedia: <a href=\"$wiki_url\">" . htmlentities(strtr($link, '_', ' '), ENT_COMPAT, "UTF-8") . "</a>";
		}
	}

	if(!isset($id)) {

		$query="select page_title, page_id
from page
where page_title = '" . addslashes(strtr($filmdata->Name, ' ', '_')) . "'
and page_namespace=0";

		$result = $connection->query($query);
		if($result===false)
			echo "$query\n";

		if ($row = $result->fetch_object()) {
			$wiki_url="https://da.wikipedia.org/wiki/" . urlencode(strtr($filmdata->Name, ' ', '_'));
			$slut="&ndash; Wikipedia:  <a href=\"$wiki_url\">" . htmlentities($filmdata->Name, ENT_COMPAT, "UTF-8") . "</a> &ndash; {{Danmark Nationalfilmografi navn|" . $filmdata->Id . "}}";
			$link=$row->page_title;
			$id=$row->page_id;
		} else {
			$id=0;
			$slut="  &mdash; {{Danmark Nationalfilmografi navn|" . $filmdata->Id . "}}";
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
	foreach($filmdata->FilmCredits as $cur_movie) {
		$linkstatus="";
		echo "<li>";
		$vis_skabelon=tjk_titel($cur_movie->Title, $cur_movie->Id);
		if(isset($cur_movie->Description))
			if($filmdata->Description!="")
				echo ", " . htmlentities($cur_movie->Description, ENT_COMPAT, "UTF-8");
		$cur_type=$cur_movie->Type;
		if(isset($konv_rolletype["$cur_type"]))
			echo ", " . $konv_rolletype["$cur_type"];
		else
			echo ", " . htmlentities($cur_movie->Type, ENT_COMPAT, "UTF-8") . "";
		echo " (<a href=\"https://www.dfi.dk/viden-om-film/filmdatabasen/film/" . $cur_movie->Id . "\">filmografi</a>, <a href=\"vis_titel.php?nr=" . $cur_movie->Id . "\">tjek titel</a>)";
		if($vis_skabelon)
			echo " &mdash; {{Danmark Nationalfilmografi titel|" . $cur_movie->Id . "}}";
		echo "$linkstatus</li>\n";
	}
	echo "</ol>\n";

	tjk_ikkebrugt();

	echo "<p>Kilde <a href=\"https://www.dfi.dk/viden-om-film/filmdatabasen/person/" . $filmdata->Id . "\">DFI filmdata</a>\n";
	echo "$slut" . handtere_links($id) . "</p>\n";
	if($mangler_match)
		echo "<h3>Links som mangler</h3><pre>\n$mangler_match</pre>\n";
}

###########################################################################

	$cur_database="dawiki";

	$opts = getopt("d:n:j:Dv");
	$cur_nr=49694;
	$jsonfil=null;
	$dumpmode=0;
	$verbose=0;
	$res = 0;
	$mangler_match = "";

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


	$connection = new mysqli($wiki_db_opsaet[$cur_database]['host'], $db_user, $db_passwd, $wiki_db_opsaet[$cur_database]['database']);
	if($connection===false) {
		die("ingen forbindelse til database\n");
	}

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

	$connection->close();
	if($dumpmode==0) { ?>
</div>
</body>
</html>
<?php }
?>
