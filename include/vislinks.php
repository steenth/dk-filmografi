<?
function handtere_links($id)
{
global $connection;

	if($id==0)
		return;
	$query = "select distinct el_to from externallinks where el_from=$id order by el_to";

	$result = $connection->query($query);
	$sum_links="";

	if($result===false) {
		echo "$query\n";
		exit(1);
	}

	$imdb_link=0;
	while ($row = $result->fetch_row()) {
		if(preg_match("#http://akas.imdb.com/(name|title)/(nm|tt)([0-9]+)/?#", $row[0], $opdel)) {
		    if($imdb_link==0)
			$sum_links .= " - <a href=\"" . $row[0] . "\" class=\"imdblink\" >imdb</a>";
		    $imdb_link++;
                } else if(preg_match("#http://www.danskefilm.dk/(film|skuespiller|tvserie)/([0-9]*).html#", $row[0], $opdel)) {
                        $sum_links .= " - <a href=\"" . $row[0] . "\" class=\"danskefilmlink\" >danskefilm</a>";
                } else if(preg_match("#http://danskfilmogtv.dk/content.php\?page=(media|persons|main)&value=([0-9]*)#", $row[0], $opdel)) {
                        $sum_links .= " - <a href=\"" . $row[0] . "\" class=\"danskfilmogtvlink\" >danskfilmogtv</a>";
                }
	}
	return $sum_links;

}
?>
