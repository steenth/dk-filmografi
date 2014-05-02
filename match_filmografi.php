<?
	include "password.php";
	include "tab/wiki_database_opsaet.php";
	include "include/falsk_positiv.php";

	$cur_database = "dawiki";

	$opts = getopt("d:S:i");
	$sektion = "film";
	$ikke_match=0;

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
	case 'i': $ikke_match=1; break;
	default:
		echo "fejl: optfejl $opt\n";
		exit(1);
	}

	$connection = new mysqli($wiki_db_opsaet[$cur_database]['host'], $db_user, $db_passwd, $wiki_db_opsaet[$cur_database]['database']);
	if($connection===false) {
		die("ingen forbindelse til database\n");
	}

	$query = "select page_title, el_to from page, externallinks where page_namespace=0 and page_id=el_from and el_to like 'http://www.dfi.dk/%'";
	$result = $connection->query($query);

	if($result===false) {
		echo "$query\n";
		exit(1);
	}

	while ($row = $result->fetch_object())
	{
		# echo "xx $row->page_title $link\n";
		if(preg_match("#http://www.dfi.dk/[Ff]akta[Oo]m[Ff]ilm/[Nn]ationalfilmografien/nffilm.aspx\?id=([0-9]*)#", $row->el_to, $opdel)) {
			$cur_nr=$opdel[1];
			if(isset($falsk_positiv_titel["$cur_nr"]["$row->page_title"])) {}
			else if(isset($film_nr["$cur_nr"])) {
				echo "* dobbel [[" .strtr($film_nr["$cur_nr"], '_', ' '). "]] og [[" . strtr($row->page_title, '_', ' ') . "]] for [$row->el_to $cur_nr]\n"; 
				echo "    \$falsk_positiv_titel[\"$cur_nr\"][\"$row->page_title\"] = 0;\n";
				echo "    \$falsk_positiv_titel[\"$cur_nr\"][\"" . $film_nr["$cur_nr"] . "\"] = 0;\n";
			} else
				$film_nr["$cur_nr"] = $row->page_title;
		}
		else if(preg_match("#http://www.dfi.dk/[Ff]akta[Oo]m[Ff]ilm/[Nn]ationalfilmografien/nfperson.aspx\?id=([0-9]*)#", $row->el_to, $opdel)) {
			$cur_nr=$opdel[1];
			if(isset($falsk_positiv_person["$cur_nr"]["$row->el_to"])) {}
			else if(isset($person_nr["$cur_nr"]))
				echo "* dobbel [[" . strtr($person_nr["$cur_nr"], '_', ' '). "]] og [[" . strtr($row->page_title, '_', ' ') . "]] for [$row->el_to $cur_nr]\n";
			else
				$person_nr["$cur_nr"] = $row->page_title;
		}
		else if($ikke_match==0) {}
		else if(preg_match("#http://www.dfi.dk/faktaomfilm/[Dd]anish[Ff]ilms/(dffilm|dfperson).aspx\?id=([0-9]*)#", $row->el_to, $opdel))
		# http://www.dfi.dk/faktaomfilm/DanishFilms/dfperson.aspx?id=4677#
		# http://www.dfi.dk/faktaomfilm/DanishFilms/dfperson.aspx?id=23297
			echo "konv $row->page_title $row->el_to\n";
		else if(preg_match("#http://www.dfi.dk/faktaomfilm/(film|person)/(da|en)/([0-9]*).aspx\?id=([0-9]*)#", $row->el_to, $opdel))
			echo "konv $row->page_title $row->el_to\n";
		# http://www.dfi.dk/faktaomfilm/film/da/71920.aspx?id=71920
		# http://www.dfi.dk/faktaomfilm/person/da/88547.aspx?id=88547
		else
			echo "rest $row->page_title $row->el_to\n";
	}

	$connection->close();
?>
