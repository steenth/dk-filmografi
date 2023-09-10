<?php

$konv_ekstern_host["http://com.imdb.www."] = "http://www.imdb.com";
$konv_ekstern_host["http://com.imdb.akas."] = "http://akas.imdb.com";
$konv_ekstern_host["https://com.imdb.www."] = "https://www.imdb.com";
$konv_ekstern_host["https://com.imdb.akas."] = "https://akas.imdb.com";
$konv_ekstern_host["https://org.toolforge.wikidata-externalid-url."] = "https://wikidata-externalid-url.toolforge.org";
$konv_ekstern_host["http://dk.danskefilm.www."] = "http://www.danskefilm.dk";
$konv_ekstern_host["https://dk.danskefilm.www."] = "https://www.danskefilm.dk";
$konv_ekstern_host["http://dk.dfi.www."] = "http://www.dfi.dk";
$konv_ekstern_host["https://dk.dfi.www."] = "https://www.dfi.dk";

function handtere_links($id)
{
global $connection, $konv_ekstern_host;

	if($id==0)
		return;
	$query = "select distinct el_to_domain_index, el_to_path from externallinks where el_from=$id order by el_to_domain_index, el_to_path";

	$result = $connection->query($query);
	$sum_links="";

	if($result===false) {
		echo "$query\n";
		exit(1);
	}

	$imdb_link=0;
	while ($row = $result->fetch_object()) {
		$tmp_host=$row->el_to_domain_index;
		if(isset($konv_ekstern_host["$tmp_host"])) {
			$sti=$konv_ekstern_host["$tmp_host"] . $row->el_to_path;

			if(preg_match("#http://akas.imdb.com/(name|title)/(nm|tt)([0-9]+)/?#", $sti, $opdel)) {
		    		if($imdb_link==0)
					$sum_links .= " - <a href=\"" . $sti . "\" class=\"imdblink\" >imdb</a>";
		    		$imdb_link++;
			}
			else if(preg_match("#https://www.danskefilm.dk/(film|skuespiller|tvserie|stumfilm|julekalender|tegnefilm).php\?id=([0-9]*)#", $sti, $opdel)) {
				$sum_links .= " - <a href=\"" . $sti . "\" class=\"danskefilmlink\" >danskefilm</a>";
			}
			else if(preg_match("#https?://danskfilmogtv.dk/content.php\?page=(media|persons|main)(&sort=lastname)?&value=([0-9]*)#", $sti, $opdel)) {
				$sum_links .= " - <a href=\"" . $sti . "\" class=\"danskfilmogtvlink\" >danskfilmogtv</a>";
			}
			else if(preg_match("#https://(wikidata-externalid-url.toolforge.org|tools.wmflabs.org/wikidata-externalid-url)/\?p=345&url_prefix=https?://www.imdb.com/&id=(nm|tt|ch|co|ev)([0-9]{2,7})/?#", $sti, $opdel)) {
				$sum_links .= " - <a href=\"" . $sti . "\" class=\"imdblink\" >imdb</a>";
			}
		}
	}
	return $sum_links;

}
?>
