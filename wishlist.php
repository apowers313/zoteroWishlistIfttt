<? 

$url = "https://api.zotero.org/users/702972/items?start=0&limit=1&format=atom&tag=wishlist&v=1";
$incl_path = "/home3/apowers/php/";
require_once ($incl_path . "Net/URL2.php");
libxml_use_internal_errors (true);

$asin_cache = [];
$asin_cache_file = "asin_cache.json";
if (file_exists ($asin_cache_file)) {
  $asin_cache = json_decode(file_get_contents($asin_cache_file), true);
} 

function get_asin ($base_url)
{
  global $asin_cache;

  //print_r ($asin_cache);
  if ($asin_cache[$base_url]) {
    return $asin_cache[$base_url];
  }
  //print ("URL is: " . $base_url . "\n");
  $url = new Net_URL2($base_url);

  $doc = new DOMDocument();
  @$doc->loadHTMLFile ($base_url, LIBXML_NOWARNING | LIBXML_NOERROR);
  $xpath = new DOMXpath($doc);
  $results = $xpath->query ("//ul[@id=\"notes-and-attachments\"]//a/@href");
  foreach ($results as $result) {
    //print "Found Next: " . $result->nodeValue . "\n";
    $url->setPath ($result->nodeValue);
    //print "Next URL: " . $url->getURL() . "\n";
  }

  $doc->loadHTMLFile ($url->getURL(), LIBXML_NOWARNING | LIBXML_NOERROR);
  $xpath = new DOMXpath($doc);
  $results = $xpath->query ("//td[@class=\"url\"]//a/@href");
  foreach ($results as $result) {
    //print "Amazon URL: " . $result->nodeValue . "\n";
    $amazon_url = new Net_URL2(urldecode ($result->nodeValue));
    //print "Path is: " . $amazon_url->getPath() . "\n";
    //print "ASIN is: " . end (explode ("/", $amazon_url->getPath())) . "\n";
    $url_parts = explode ("/",$amazon_url->getPath());
    for ($i = 0; $i < count($url_parts); $i++) {
      //print $url_parts[$i] . "\n";
      if ($url_parts[$i] == "dp") {
	//print ("found!\n");
	break;
      }
    }

    $asin = $url_parts[++$i];
    //print "ASIN: " . $asin . "\n";
    //$asin = end (explode ("/", $amazon_url->getPath()));
  }
  $asin_cache[$base_url] = $asin;

  return $asin;
}


//$now = "2014-10-20T05:59:00Z";
$now = date("Y-m-d\\TH:i:s\\Z", time());

$str = file_get_contents($url);

//print $str

$xml = new DOMDocument();
//$xml->preserveWhiteSpace = false;
$xml->loadXML ($str);

// feed
$xmlDoc = $xml->documentElement;
foreach ($xmlDoc->childNodes AS $docItem) {
  //print $item->nodeName . " = " . $item->nodeValue . "\n";

  // process <entry> tag
  if ($docItem->nodeName == "entry") {
    $publishedItem = null;
    $updatedItem = null;

    // find and save the nodes for <published>, <updated>, <id> and <content> so that we can change them later
    foreach ($docItem->childNodes AS $elementItem) {
      if ($elementItem->nodeName == "published") $publishedItem = $elementItem;
      if ($elementItem->nodeName == "updated") $updatedItem = $elementItem;
      if ($elementItem->nodeName == "id") $idItem = $elementItem;
      if ($elementItem->nodeName == "content") $contentItem = $elementItem;
      if ($elementItem->nodeName == "title") $titleItem = $elementItem;
    }

    if ($publishedItem != null && $updatedItem != null) {
      //$publishedItem->nodeValue = $updatedItem->nodeValue;
      // published = updated = now
      $publishedItem->nodeValue = $now;
      $updatedItem->nodeValue = $now;

      // reformat the RSS <content> tag to be what we want
      $contentItem->nodeValue = get_asin ($idItem->nodeValue);

      // reformat the RSS <title> tag to be what we want
      $title = $details['title'];
      if ($details['date'] != null and $details['date'] != "") $title = $title . " - " . $details['date'];
      if (count ($details['author']) > 0) $title = $title . " - ";
      for ($i = 0; $i < count ($details['author']); $i++) {
	$title = $title . $details['author'][$i];
	if (($i + 1) < count ($details['author'])) $title = $title . ", ";
      }
      $titleItem->nodeValue = $title;
      //print $contentItem->nodeValue;
      //$contentItem->nodeValue = $idItem->nodeValue;
    }
  }

  // set the overall feed update time to "now
  if ($docItem->nodeName == "updated") {
    $docItem->nodeValue = $now;
  }
}

$finalXml = $xml->saveXML();
print $finalXml;
file_put_contents($asin_cache_file, json_encode($asin_cache));

?>
