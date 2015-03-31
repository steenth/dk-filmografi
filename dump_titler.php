<?
	include "password.php";
	include "tab/wiki_database_opsaet.php";
	include "include/falsk_positiv.php";

	$cur_database = "dawiki";

	$opts = getopt("d:");

	foreach (array_keys($opts) as $opt) switch ($opt) {
	case 'd':
	    // Do something with s parameter
	    $cur_database = $opts['d'];
	    if(!isset($wiki_db_opsaet[$cur_database])) {
		echo "database $cur_database findes ikke\n";
		exit(1);
	    }
	    break;
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

	while ($row = $result->fetch_object()) {
		# echo "xx $row->page_title $link\n";
		if(preg_match("#http://www.dfi.dk/[Ff]akta[Oo]m[Ff]ilm/([Nn]ationalfilmografien/nffilm.aspx|film/(da|en)/[0-9]+.aspx)\?id=(?<id>[0-9]+)#", $row->el_to, $opdel)) {
			$cur_nr=$opdel['id'];
			if(isset($falsk_positiv_titel["$cur_nr"]["$row->page_title"])) {}
			else if(isset($film_nr["$cur_nr"]) && $row->page_title!= $film_nr["$cur_nr"]) {}
			else {
				$film_nr["$cur_nr"] = $row->page_title;
				echo "$cur_nr $row->page_title\n";
			}
		}
	}

	$connection->close();
?>
