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
	$api_url="https://api.dfi.dk/v1/film/$nr";
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
	format_film($filmdata_ind);
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

	$result = $connection->query($query);
	if($result===false)
		echo "$query\n";

	while ($row = $result->fetch_object()) {
		$til_link["$row->pl_title"] = 0;
		if($verbose)
			echo "til $row->pl_title 0\n";
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
	
	$result = $connection->query($query);
	if($result===false)
		echo "$query\n";

	while ($row = $result->fetch_object()) {
		$til_link["$row->rd_title"] = 1;
		if($verbose)
			echo "til $row->rd_title 1 " . $row->rd_title . " " . $row->pl_title . "\n";
	}	

	$query="select page_title
from page, pagelinks
where pl_title='" . addslashes($link) . "'
and page_id=pl_from
and page_namespace=0
and pl_namespace=0";

	$result = $connection->query($query);
	if($result===false)
		echo "$query\n";

	while ($row = $result->fetch_object()) {
		$fra_link["$row->page_title"] = 0;
		if($verbose)
			echo "fra $row->page_title 0\n";
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

function tjk_person($navn, $nr)
{
global $connection, $fra_link, $til_link, $linkstatus, $falsk_positiv_person;

	$query="select page_title, el_to_path
from page, externallinks
where page_id=el_from
   and page_namespace=0
   and el_to_domain_index = \"https://dk.dfi.www.\"
   and el_to_path=\"/viden-om-film/filmdatabasen/person/$nr\"";

	$result = $connection->query($query);
	if($result===false)
		echo "$query\n";

	$antal=0;
	$last_navn="xdfdfsd";
	while ($row = $result->fetch_object()) {
		if($last_navn==$row->page_title) {} # håndere samme titel forskel url til dfi
		else if(!isset($falsk_positiv_person["$nr"]["$row->page_title"])) {
			$note_titel["$antal"]=$row->page_title;
			$dfi_url=$row->el_to_path;
			$antal++;
			$last_navn=$row->page_title;
		}
	}

	if($antal>1) {
		echo "<li>duplet";
		foreach($note_titel as $cur_person) {
			$wiki_url="https://da.wikipedia.org/wiki/" . urlencode(strtr($cur_person, ' ', '_'));
			echo " - \$falsk_positiv_person[\"$nr\"][\"$cur_person\"] = 0; <a href=\"$wiki_url\">$cur_person</a>";	
		}
		echo " - <a href=\"https://www.dfi.dk" . $dfi_url . "\">" . $nr . "</a>\n";
		return 0;
	}

	if($antal==1) {
		$link=$note_titel[0];
		$wiki_url="https://da.wikipedia.org/wiki/" . urlencode(strtr($link, ' ', '_'));
		echo "<a href=\"$wiki_url\">" . htmlentities(strtr($navn, '_', ' '), ENT_COMPAT, "UTF-8") . "</a>";
		if(strtr($link, '_', ' ') != $navn)
			echo " (D)";
		if(isset($til_link["$link"]) && isset($fra_link["$link"]))
			$linkstatus=" - link ok";
		else if(isset($til_link["$link"]))
			$linkstatus=" - kun link til person findes";
		else if(isset($fra_link["$link"]))
			$linkstatus=" - kun link fra person findes";
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
		$wiki_url="https://da.wikipedia.org/wiki/" . urlencode($link);
		echo "<a href=\"$wiki_url\">" . htmlentities($navn, ENT_COMPAT, "UTF-8") . "$tillaeg</a>";
		if(isset($til_link["$link"]) && isset($fra_link["$link"]))
			$linkstatus=" - link ok";
		else if(isset($til_link["$link"]))
			$linkstatus=" - kun link til person findes";
		else if(isset($fra_link["$link"]))
			$linkstatus=" - kun link fra person findes";
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

function format_film($filmdata_ind)
{
global $konv_rolletype, $connection, $linkstatus, $falsk_positiv_titel, $verbose;

	$filmdata=json_decode($filmdata_ind);

	$query="select page_title, page_id
from page, externallinks
where page_id=el_from
   and page_namespace=0
   and el_to_domain_index = \"https://dk.dfi.www.\"
   and el_to_path=\"https://www.dfi.dk/viden-om-film/filmdatabasen/film/" . $filmdata->Id . "\"";

	$result = $connection->query($query);
	if($result===false)
		echo "$query\n";

	while ($row = $result->fetch_object()) {
		if(isset($falsk_positiv_titel["$filmdata->Id"]["$row->page_title"])) {
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
where page_title = '" . addslashes(strtr($filmdata->DanishTitle, ' ', '_')) . "'
and page_namespace=0";

		$result = $connection->query($query);
		if($result===false)
			echo "$query\n";

		if ($row = $result->fetch_object()) {
			$wiki_url="https://da.wikipedia.org/wiki/" . urlencode(strtr($filmdata->DanishTitle, ' ', '_'));
			$slut="&ndash; Wikipedia:  <a href=\"$wiki_url\">" . htmlentities($filmdata->DanishTitle, ENT_COMPAT, "UTF-8") . "</a> &ndash; {{Danmark Nationalfilmografi titel|" . $filmdata->Id . "}}";
			$link=$row->page_title;
			$id=$row->page_id;
		} else {
			$id=0;
			$slut="  &mdash; {{Danmark Nationalfilmografi titel|" . $filmdata->Id . "}}";
		}
	}

	if($id)
		note_links($link, $id);

	# print_r($filmdata);
	vis_header($filmdata->DanishTitle);
	if(isset($filmdata->Description))
		echo htmlentities($filmdata->Description, ENT_COMPAT, "UTF-8") . "\n";
	echo "<ol>\n";
	foreach($filmdata->PersonCredits as $cur_credit) {
		$linkstatus="";
		echo "<li>";
		$vis_skabelon=tjk_person($cur_credit->Name, $cur_credit->Id);
		if(isset($cur_credit->Description))
			echo ", " . htmlentities($cur_credit->Description, ENT_COMPAT, "UTF-8");
		$cur_type=$cur_credit->Type;
		if(isset($konv_rolletype["$cur_type"]))
			echo ", " . $konv_rolletype["$cur_type"];
		else
			echo ", \$konv_rolletype[\"" . $cur_credit->Type . "\"] = \"xx\";";
		echo " (<a href=\"https://www.dfi.dk/viden-om-film/filmdatabasen/person/" . $cur_credit->Id . "\">filmografi</a>, <a href=\"vis_navn.php?nr=" . $cur_credit->Id . "\">navn</a>)";
		if($vis_skabelon)
			echo " &mdash; {{Danmark Nationalfilmografi navn|" . $cur_credit->Id . "}}";
		echo "$linkstatus</li>\n";
	}
	echo "</ol>\n";
	echo "<p>Kilde <a href=\"https://www.dfi.dk/viden-om-film/filmdatabasen/film/" . $filmdata->Id . "\">DFI filmdata</a>\n";
	echo "$slut" . handtere_links($id) . "</p>\n";
}

###########################################################################

	$cur_database="dawiki";

	$opts = getopt("d:n:j:Dv");
	$cur_nr=49694;
	$jsonfil=null;
	$dumpmode=0;
	$verbose=0;

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
			format_film($testdata);
		}
		fclose($handle);
	} else hand_film($cur_nr);

	# format_film($testdata);

	$connection->close();
	if($dumpmode==0) { ?>
</div>
</body>
</html>
<?php }
?>
