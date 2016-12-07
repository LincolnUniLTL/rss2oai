<?php
/* Configurable parameters */
$feed = ""; // url of source RSS feed
$adminEmail = ""; // contact email for OAI feed
$rights = array(
	"© Your University",
);
$useBuildDate = true; // TRUE: if any item has been edited since $from, returns all items, otherwise nothing. FALSE: returns only items first published since $from
$serveSSL = true; // TRUE if you'll be serving the *OAI feed* (ie this page) over https


/* Hard-coded parameters */
$protocol = $serveSSL?"https":"http";
$baseURL = $protocol."://".$_SERVER['HTTP_HOST'].preg_replace('/(.*)\?.*/','$1',$_SERVER['REQUEST_URI']);
if(isset($_GET['verb'])) $param['verb'] = $_GET['verb'];
if(isset($_GET['metadataPrefix'])) $param['metadataPrefix'] = $_GET['metadataPrefix'];
if(isset($_GET['from'])) $param['from'] = $_GET['from'];


/* Core functions */
function fetchRSS($feed,$p) {
	if ($p>1) $feed = $feed . '?paged=' . $p;
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, $feed);
	curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
	curl_setopt($ch, CURLOPT_HEADER, FALSE);
	$response = curl_exec($ch);
	curl_close($ch);
	$response = html_entity_decode($response);
	$response = str_replace('&','&amp;',$response); // XML hates the ampersand with a burning passion
	$rss = simplexml_load_string($response);
	return $rss;
}

function fetchFull($feed,$from) {
	global $useBuildDate;
	$end = 0;
	$p = 1;
	$full = fetchRSS($feed,$p);
	if ($useBuildDate) {
		if (strtotime($full->channel->lastBuildDate) < strtotime($from)) {
			$full = "";
		} else {
			while ($end==0) { // get the next page and if no 404 or item before $from, add items to full feed
				$p++;
				$temp = fetchRSS($feed,$p);
				if (substr($temp->channel->title, 0, 15) === 'Page not found ' || !$temp->channel->item) {
					$end = 1;
				} else {
					foreach($temp->channel->item as $node) {
						$appendage = dom_import_simplexml($full->channel);
						$new_node = dom_import_simplexml($node);
						$new_node = $appendage->ownerDocument->importNode($new_node, TRUE);
						$appendage->appendChild($new_node);
					}
				}
			}
		}
	} else {
		$n = count($full->channel->item)-1;
		for ($n; $n>=0; $n--) { // working backwards through page 1 remove items before $from
			if (strtotime($full->channel->item[$n]->pubDate) < strtotime($from)) {
				unset($full->channel->item[$n]);
				$end = 1;
			}
		}
		while ($end==0) { // get the next page and if no 404 or item before $from, add items to full feed
			$p++;
			$temp = fetchRSS($feed,$p);
				if (substr($temp->channel->title, 0, 15) === 'Page not found ' || !$temp->channel->item) {
				$end = 1;
			} else {
				foreach($temp->channel->item as $node) {
					if (strtotime($node->pubDate) < strtotime($from)) {
						$end = 1;
					} else {
						$appendage = dom_import_simplexml($full->channel);
						$new_node = dom_import_simplexml($node);
						$new_node = $appendage->ownerDocument->importNode($new_node, TRUE);
						$appendage->appendChild($new_node);
					}
				}
			}
		}
	}
	return $full;
}

function assocArrayToXML($root,$arr,$attr) {
	$xml = new SimpleXMLElement("<?xml version=\"1.0\" encoding=\"UTF-8\" ?><{$root}></{$root}>");
	$f = create_function('$f,$c,$a','
        foreach($a as $v) {
            if(isset($v["@text"])) {
                $ch = $c->addChild($v["@tag"],$v["@text"]);
            } else {
                $ch = $c->addChild($v["@tag"]);
                if(isset($v["@items"])) {
                    $f($f,$ch,$v["@items"]);
                }
            }
            if(isset($v["@attr"])) {
                foreach($v["@attr"] as $attr => $val) {
					if (preg_match("/(.*):(.*)/",$attr,$m)) {
						$schemata = array (
							"xsi" => "http://www.w3.org/2001/XMLSchema-instance",
						);
						$n = $schemata[$m[1]];
						$ch->addAttribute($attr,$val,$n);
					} else {
						$ch->addAttribute($attr,$val);
					}
                }
            }
        }');
	$f($f,$xml,$arr);
	foreach($attr as $k=>$v) {
		if (preg_match("/(.*):(.*)/",$k,$m)) {
			$schemata = array (
				"xsi" => "http://www.w3.org/2001/XMLSchema-instance",
			);
			$n = $schemata[$m[1]];
			$xml->addAttribute($k,$v,$n);
		} else {
			$xml->addAttribute($k,$v);
		}
	}
	return $xml->asXML();
}


/* Start building the OAI data */
$attributes = array(
	'xmlns' => 'http://www.openarchives.org/OAI/2.0/',
	'xsi:schemaLocation' => 'http://www.openarchives.org/OAI/2.0/ http://www.openarchives.org/OAI/2.0/OAI-PMH.xsd',
	);
$oai[] = array(
	"@tag" => "responseDate",
	"@text" => gmdate('Y-m-d\TH:i:s\Z'),
);

if($param['verb']=='Identify') {
	$oai[] = array(
		"@tag" => "request",
		"@text" => $baseURL,
		"@attr" => $param,
	);
	$rss = fetchRSS($feed,1);
	$repo = $rss->channel->link;
	$repo = preg_replace("/https*:\/\//","",$repo);
	$oai[] = array(
		"@tag" => "Identify",
		"@items" => array(
			array(
				"@tag" => 'repositoryName',
				"@text" => $rss->channel->title,
			),
			array(
				"@tag" => 'baseURL',
				"@text" => $baseURL,
			),
			array(
				"@tag" => 'protocolVersion',
				"@text" => '2.0',
			),
			array(
				"@tag" => 'adminEmail',
				"@text" => $adminEmail,
			),
			array(
				"@tag" => 'earliestDatestamp',
				"@text" => '',
			),
			array(
				"@tag" => 'deletedRecord',
				"@text" => 'no',
			),
			array(
				"@tag" => 'granularity',
				"@text" => 'YYYY-MM-DDThh:mm:ssZ',
			),
			array(
				"@tag" => 'description',
				"@items" => array(array(
					"@tag" => 'oai-identifier',
					"@attr" => $attributes,
					"@items" => array(
						array(
							"@tag" => 'scheme',
							"@text" => 'oai',
						),
						array(
							"@tag" => 'repositoryIdentifier',
							"@text" => $repo,
						),
						array(
							"@tag" => 'delimiter',
							"@text" => ':',
						),
						array(
							"@tag" => 'sampleIdentifier',
							"@text" => 'oai:' . $repo . ':1234',
						),
					),
				)),
			),
		),
	);
} else if($param['verb']=='ListMetadataFormats') {
	$oai[] = array(
		"@tag" => "request",
		"@text" => $baseURL,
		"@attr" => $param,
	);
	$oai[] = array(
		"@tag" => 'ListMetadataFormats',
		"@items" => array(
			array(
				"@tag" => 'metadataFormat',
				"@items" => array(
					array(
						"@tag" => 'metadataPrefix',
						"@text" => 'oai_dc',
					),
					array(
						"@tag" => 'schema',
						"@text" => 'http://www.openarchives.org/OAI/2.0/oai_dc.xsd',
					),
					array(
						"@tag" => 'metadataNamespace',
						"@text" => 'http://www.openarchives.org/OAI/2.0/oai_dc/',
					),
				),
			),
		),
	);
} else if($param['verb']=='ListRecords') {
	if(!($param['from']) || is_numeric(strtotime($param['from']))) {
		$oai[] = array(
			"@tag" => "request",
			"@text" => $baseURL,
			"@attr" => $param,
		);
		if($param['metadataPrefix']=='oai_dc') {
			$rss = fetchFull($feed,$param['from']);
			if ($rss && count($rss->channel->item)>0) {
				$repo = $rss->channel->link;
				foreach($rss->channel->item as $i) {
/* Here begins oai_dc metadata for each record */
					$dc = array(
						array(
							"@tag" => 'dc:dc:type',
							"@text" => 'Text',
						),
						array(
							"@tag" => 'dc:dc:identifier',
							"@text" => $repo . '?p=' . preg_replace('/^.*=/','',$i->guid),
						),
						array(
							"@tag" => 'dc:dc:date',
							"@text" => gmdate('Y-m-d\TH:i:s\Z', strtotime($i->pubDate)),
						),
					);
					foreach($i->title as $t=>$title) {
						$dc[] = array(
							"@tag" => 'dc:dc:title',
							"@text" => str_replace('&','and',$title), // XML continues to hate the ampersand with a burning passion
						);
					}
					$creator = $i->children('http://purl.org/dc/elements/1.1/')->creator;
					foreach($creator as $c=>$creat) {
						$dc[] = array(
							"@tag" => 'dc:dc:creator',
							"@text" => strip_tags((string)$creat),
						);
					}
					foreach($i->category as $c=>$category) {
						$dc[] = array(
							"@tag" => 'dc:dc:subject',
							"@text" => strip_tags((string)$category),
						);
					}
					$description = strip_tags((string)$i->description);
					$description = preg_replace('/Read More\n/','',$description); // Implementation-specific?
					$description = preg_replace('/The post .*\n/','',$description); // Implementation-specific?
					if(trim($description) != "") {
						$dc[] = array(
							"@tag" => 'dc:dc:description',
							"@text" => trim($description),
						);
					}
					$content = $i->children('http://purl.org/rss/1.0/modules/content/');
					$content = strip_tags((string)$content->encoded);
					$content = preg_replace('/The post .*/','',$content); // Implementation-specific?
					if(trim($content) != "") {
						$dc[] = array(
							"@tag" => 'dc:dc:description',
							"@text" => trim($content),
						);
					}
					$dc[] = array(
						"@tag" => 'dc:dc:publisher',
						"@text" => preg_replace('/Page \d* – /','',$rss->channel->title), // Wordpress-specific
					);
					foreach($rights as $r=>$right) {
						$dc[] = array(
							"@tag" => 'dc:dc:rights',
							"@text" => $right,
						);
					}
					$records[] = array(
						"@tag" => 'record',
						"@items" => array(
							array(
								"@tag" => 'header',
								"@items" => array(
									array(
										"@tag" => 'identifier',
										"@text" => 'oai:' . preg_replace("/https*:\/\//","",$rss->channel->link) . ':' . preg_replace('/^.*=/','',$i->guid),
									),
									array(
										"@tag" => 'datestamp',
										"@text" => gmdate('Y-m-d\TH:i:s\Z', strtotime($rss->channel->lastBuildDate)),
									),
								),
							),
							array(
								"@tag" => 'metadata',
								"@items" => array(
									array(
										"@tag" => 'oai_dc:oai_dc:dc',
										"@attr" => array(
											"xmlns:xmlns:oai_dc" => "http://www.openarchives.org/OAI/2.0/oai_dc/",
											"xmlns:xmlns:doc" => "http://www.lyncode.com/xoai",
											"xmlns:xmlns:dc" => "http://purl.org/dc/elements/1.1/",
											"xsi:schemaLocation" => "http://www.openarchives.org/OAI/2.0/oai_dc/ http://www.openarchives.org/OAI/2.0/oai_dc.xsd",
										),
										"@items" => $dc,
									),
								),
							),
						),
					);
/* Here ends the oai_dc metadata for each record */
				}
				$oai[] = array(
					"@tag" => 'ListRecords',
					"@items" => $records,
				);
			} else {
				$oai[] = array(
					"@tag" => 'error',
					"@text" => 'The combination of the values of the from, until, and metadataPrefix arguments results in an empty list',
					"@attr" => array('code' => 'noRecordsMatch'),
				);
			}
		} else {
			$oai[] = array(
				"@tag" => 'error',
				"@text" => 'Unknown metadata format',
				"@attr" => array('code' => 'cannotDisseminateFormat'),
			);
		}
	} else {
		$oai[] = array(
			"@tag" => "request",
			"@text" => $baseURL,
		);
		$oai[] = array(
			"@tag" => 'error',
			"@text" => 'The request includes illegal arguments, is missing required arguments, includes a repeated argument, or values for arguments have an illegal syntax: probably the date is incorrectly formatted',
			"@attr" => array('code' => 'badArgument'),
		);		
	}
} else {
	$oai[] = array(
		"@tag" => "request",
		"@text" => $baseURL,
	);
	$oai[] = array(
		"@tag" => 'error',
		"@text" => 'Illegal OAI verb, or a legal verb not supported by this minimal implementation. Try: Identify, ListMetadataFormats, ListRecords',
		"@attr" => array('code' => 'badVerb'),
	);
}

/* And now we convert that data into XML and dump it to output */
echo assocArrayToXML('OAI-PMH',$oai,$attributes);
?>