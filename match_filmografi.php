<?
	include "password.php";
	include "tab/wiki_database_opsaet.php";
	include "include/falsk_positiv.php";

	$cur_database = "dawiki";

	$opts = getopt("d:S:");
	$sektion = "film";

	foreach (array_keys($opts) as $opt) switch ($opt) {
	case 'd':
	    // Do something with s parameter
	    $cur_database = $opts['d'];
	    if(!isset($wiki_db_opsaet[$cur_database])) {
		echo "database $cur_database findes ikke\n";
		exit(1);
	    }
	    break;
	case 'S':
	    $sektion = $opts['S'];
	    break;
	default:
		echo "fejl: optfejl $opt\n";
		exit(1);
	}

	$connection = mysql_connect($wiki_db_opsaet[$cur_database]['host'], $db_user, $db_passwd);
	mysql_select_db($wiki_db_opsaet[$cur_database]['database'], $connection);

	$query = "select page_title, el_to from page, externallinks where page_namespace=0 and page_id=el_from and el_to like 'http://www.dfi.dk/%'";
	$result = mysql_query($query, $connection);

	if($result===false) {
		echo "$query\n";
		exit(1);
	}

	while ($row = mysql_fetch_row($result))
	{
		$titel=$row[0];
		$link=$row[1];
		# echo "xx $titel $link\n";
		if(preg_match("#http://www.dfi.dk/faktaomfilm/nationalfilmografien/nffilm.aspx\?id=([0-9]*)#", $row[1], $opdel)) {
			$cur_nr=$opdel[1];
			if(isset($falsk_positiv_titel["$cur_nr"]["$titel"])) {}
			else if(isset($film_nr["$cur_nr"])) {
				echo "* dobbel [[" .strtr($film_nr["$cur_nr"], '_', ' '). "]] og [[" . strtr($titel, '_', ' ') . "]] for [$link $cur_nr]\n"; 
				echo "    \$falsk_positiv_titel[\"$cur_nr\"][\"$titel\"] = 0;\n";
				echo "    \$falsk_positiv_titel[\"$cur_nr\"][\"" . $film_nr["$cur_nr"] . "\"] = 0;\n";
			} else
				$film_nr["$cur_nr"] = $titel;
		}
		else if(preg_match("#http://www.dfi.dk/FaktaOmFilm/Nationalfilmografien/nffilm.aspx\?id=([0-9]*)#", $row[1], $opdel)) {
			$cur_nr=$opdel[1];
			if(isset($falsk_positiv_titel["$cur_nr"]["$titel"])) {}
			else if(isset($film_nr["$cur_nr"])) {
				echo "* dobbel [[" . strtr($film_nr["$cur_nr"], '_', ' '). "]] og [[" . strtr($titel, '_', ' ') . "]] for [$link $cur_nr]\n";
				echo "    \$falsk_positiv_titel[\"$cur_nr\"][\"$titel\"] = 0;\n";
				echo "    \$falsk_positiv_titel[\"$cur_nr\"][\"" . $film_nr["$cur_nr"] . "\"] = 0;\n";
			} else
				$film_nr["$cur_nr"] = $titel;
		}
		else if(preg_match("#http://www.dfi.dk/faktaomfilm/nationalfilmografien/nfperson.aspx\?id=([0-9]*)#", $row[1], $opdel)) {
			$cur_nr=$opdel[1];
			if(isset($falsk_positiv_person["$cur_nr"]["$link"])) {}
			else if(isset($person_nr["$cur_nr"]))
				echo "* dobbel [[" . strtr($person_nr["$cur_nr"], '_', ' '). "]] og [[" . strtr($titel, '_', ' ') . "]] for [$link $cur_nr]\n";
			else
				$person_nr["$cur_nr"] = $titel;
		}
	}

	mysql_close($connection);
?>