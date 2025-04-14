<?php

// Microparser converts BHL page link to PageID and checks text

require_once (dirname(__FILE__) . '/lib/api_utilities.php');
require_once (dirname(__FILE__) . '/lib/textsearch.php');
require_once (dirname(__FILE__) . '/site.php');

// Link stuff

//----------------------------------------------------------------------------------------
function get_item($ItemID, $force = false)
{
	global $config;
	
	$filename = $config['bhl_download_cache'] . '/item-' . $ItemID . '.json';

	if (!file_exists($filename) || $force)
	{
		$parameters = array(
			'op' 		=> 'GetItemMetadata',
			'itemid'	=> $ItemID,
			'parts'		=> 't',
			'pages'		=> 't',
			'apikey'	=> $config['BHL_API_KEY'],
			'format'	=> 'json'
		);
	
		$url = 'https://www.biodiversitylibrary.org/api2/httpquery.ashx?' . http_build_query($parameters);
		$json = get($url);
		file_put_contents($filename, $json);
	}

	$json = file_get_contents($filename);
	$item_data = json_decode($json);
	
	return $item_data;
}

//----------------------------------------------------------------------------------------
function get_page($PageID, $force = false)
{
	global $config;
	
	$filename = $config['bhl_download_cache'] . '/page-' . $PageID . '.json';

	if (!file_exists($filename) || $force)
	{
		$parameters = array(
			'op' 		=> 'GetPageMetadata',
			'pageid'	=> $PageID,
			'names'		=> 't',
			'ocr'		=> 't',
			'apikey'	=> $config['BHL_API_KEY'],
			'format'	=> 'json'
		);
	
		$url = 'https://www.biodiversitylibrary.org/api2/httpquery.ashx?' . http_build_query($parameters);

		$json = get($url);
		file_put_contents($filename, $json);
	}

	$json = file_get_contents($filename);
	$page_data = json_decode($json);
	
	return $page_data;
}

//----------------------------------------------------------------------------------------
// Get BHL ItemID that corresponds to Internet Archive item (if found)
function bhl_item_from_ia($ia)
{
	global $config;
	
	$ItemID = 0;
	
	$parameters = array(
		'op' => 'GetItemByIdentifier',
		'type' => 'ia',
		'value' => $ia,
		'format' => 'json',
		'apikey' => $config['BHL_API_KEY']
	);

	$url = 'https://www.biodiversitylibrary.org/api2/httpquery.ashx?' . http_build_query($parameters);
	
	// echo $url . "\n";
	
	$json = get($url);
	
	if ($json != '')
	{
		$obj = json_decode($json);

		if ($obj->Status == 'ok')
		{
			$ItemID = $obj->Result->ItemID;
		}
	}	
	
	//echo "ItemID=$ItemID\n";

	return $ItemID;
}

//----------------------------------------------------------------------------------------
function get_bhl_page_ids($item, $item_order, $mode = 1)
{
	$pages = array();
	
	$page_offset = $item_order - 1;
	
	if (isset($item->Result->Pages) && isset($item->Result->Pages[$page_offset]))
	{
		// single page
		$pages[] = $item->Result->Pages[$page_offset]->PageID;
	}
	
	// second page?	
	if ($mode == 2 && isset($item->Result->Pages[$page_offset + 1]))
	{
		// second page
		$pages[] = $item->Result->Pages[$page_offset + 1]->PageID;
	}

	return $pages;
}

//----------------------------------------------------------------------------------------
function match_target(&$target, $string = '')
{
	$target->BHLPAGEID = array();
	
	//print_r($target);
	
	if (isset($target->internetarchive))
	{
	
	
	}
	else
	{
		if (isset($target->ItemID))
		{
			// Location is relative to other pages in an item
			$item = get_item($target->ItemID);
			$target->BHLPAGEID = get_bhl_page_ids($item, $target->item_order, $target->mode);			
		}
		elseif (isset($target->PageID))
		{
			// PageID 			
			if (isset($target->item_order))
			{
				// get item with page				
				$page = get_page($target->PageID);

				if (isset($page->Result->ItemID))
				{
					$item = get_item($page->Result->ItemID);
					$target->BHLPAGEID = get_bhl_page_ids($item, $target->item_order, $target->mode);
				}
			}
			else
			{
				// simple direct link to PageID
				$target->BHLPAGEID[] = $target->PageID;
			}
		}
		else
		{
			// badness, shouldn't get here
		}
	
	}
	
	// option to check whether match makes sense
	if (count($target->BHLPAGEID) > 0)
	{
		$target->text = array();
		$target->hits = array();
		
		foreach ($target->BHLPAGEID as $pageid)
		{
			$text = '';
			
			$page = get_page($pageid);
			
			if (isset($page->Result->OcrText))
			{
				$text = $page->Result->OcrText;
			}
			if ($text != '')
			{
				if (!isset($target->text))
				{
					$target->text = array();
				}

				$text = mb_convert_encoding($text, 'UTF-8', mb_detect_encoding($text));

				$target->text[$pageid] = $text;
			}
			
			foreach ($target->text as $id => $text)
			{	
				if (1)
				{
					$hits = find_in_text(
						$string, 
						$text, 
						isset($doc->ignorecase) ? $doc->ignorecase : true,
						isset($doc->maxerror) ? $doc->maxerror : 3	
						);
				}
				else
				{
					$hits = find_in_text_simple(
						$string, 
						$text, 
						isset($doc->ignorecase) ? $doc->ignorecase : true,
						isset($doc->maxerror) ? $doc->maxerror : 2	
						);								
				}
				
				//if ($hits->total > 0)
				{
					$target->hits[$id] = $hits;
				}
			
			}
		}
	}
}




//----------------------------------------------------------------------------------------
// Parse BHL URL and extract various identifiers
// e.g. http://www.biodiversitylibrary.org/item/96891#page/697/mode/1up
function parse_bhl_url($url)
{
	$target = new stdclass;
	$target->url = $url;

	// Link to BHL item with page offset
	// http://www.biodiversitylibrary.org/item/96891#page/697/mode/1up
	if (preg_match('/https?:\/\/(www\.)?biodiversitylibrary.org\/item\/(?<item>\d+)#page\/(?<page>\d+)(\/mode\/(?<mode>\d+)up)?/', $url, $m))
	{		
		$target->ItemID     = $m['item'];
		$target->item_order = $m['page'];		
		$target->mode       = 1;
		
		if (isset($m['mode']))
		{
			$target->mode = $m['mode'];
		}
	}

	// Link to BHL page with page offset
	// https://www.biodiversitylibrary.org/page/498171#page/33/mode/1up
	if (preg_match('/https?:\/\/(www\.)?biodiversitylibrary.org\/page\/(?<pageid>\d+)#page\/(?<page>\d+)(\/mode\/(?<mode>\d+)up)?/', $url, $m))
	{
		$target->PageID     = $m['pageid'];
		$target->item_order = $m['page'];
		$target->mode       = 1;
		
		if (isset($m['mode']))
		{
			$target->mode = $m['mode'];
		}
	}
	
	// Link to BHL page using PageID, just extract the PageID
	if (preg_match('/http[s]?:\/\/(www\.)?biodiversitylibrary.org\/page\/(?<pageid>\d+)$/', $url, $m))
	{
		$target->PageID = $m['pageid'];
	}
		
	return $target;
}

//----------------------------------------------------------------------------------------
// Parse Internet Archive URL and extract IA and offset info
function parse_ia_url($url)
{
	$target = new stdclass;
	$target->url = $url;
	
	if (preg_match('/https?:\/\/(www\.)?archive.org\/(details|stream)\/(?<ia>[A-Za-z0-9]+)#page\/(?<page>\d+)(\/mode\/(?<mode>\d+)up)?/', $url, $m))
	{
		$target->internetarchive = $m['ia'];
		$target->page_number     = $m['page'];		
		$target->mode            = 1;
		
		if (isset($m['mode']))
		{
			$target->mode = $m['mode'];
		}		
	}
	
	// 'https://archive.org/details/entomologistsrec101898tutt/page/n7/mode/1up?view=theater
	if (preg_match('/https?:\/\/(www\.)?archive.org\/details\/(?<ia>[A-Za-z0-9]+)\/page\/n(?<page>\d+)(\/mode\/(?<mode>\d+)up)?/', $url, $m))
	{
		$target->internetarchive = $m['ia'];
		$target->offset          = $m['page'];		
		$target->mode            = 1;
		
		if (isset($m['mode']))
		{
			$target->mode = $m['mode'];
		}		
	}
	
	return $target;
}

//----------------------------------------------------------------------------------------
function parse_source_url($url)
{
	$target = null;
	
	if (preg_match('/archive.org/', $url))
	{
		$target =  parse_ia_url($url);
	}

	if (preg_match('/biodiversitylibrary.org/', $url))
	{
		$target =  parse_bhl_url($url);
	}
	
	return $target;
}





// Web site to document API

class MySite extends Site
{
	// Styles specific to this site
	function style_custom()
	{
		echo '.data {
			margin-top:1em;
			padding:1em;
			border:1px solid rgb(222,222,222);
			border-radius: 0.2em;
			font-family:monospace;
			white-space:pre-wrap;	
			height:300px;	
			overflow-y:auto;	
		}
		
		.text {
			padding:1em;
			border:1px solid rgb(222,222,222);
			border-radius: 0.2em;
			font-family:monospace;
			white-space:pre-wrap;					
		}
				
		';	
	}	
	
	// Javascript specific to <HEAD> for this site
	function javascript_head()
	{
	
	}	
	
	// Javascript specific to <BODY> for this site
	function javascript_body_start()
	{
		echo '<script>
		
		function go() {
			var output = document.getElementById("output");
			output.style.display = "none";
			
			var pages = document.getElementById("pages");
			pages.style.display = "none";			

			var url = document.getElementById("url").value;
			var target = document.getElementById("target").value;
			
			var doc = {};
			doc.url = url;
			
			if (target) {
				doc.target = target;
			}
			
			var url = "' . $_SERVER['PHP_SELF'] . '";
			
			fetch(url, {
				method: "post",
				body: JSON.stringify(doc)
			}).then(
					function(response){
						if (response.status != 200) {
							console.log("Looks like there was a problem. Status Code: " + response.status);
        					return;
						}
				
						response.json().then(function(data) {
						
						output.innerHTML = JSON.stringify(data, null, 2);							
						output.style.display = "block";
						
						if (Object.keys(data.data.hits).length > 0) {
							
							var html = "<h2>Page text with hits</h2>";
						
							for (var i in data.data.hits) {
								html += "<h3><a href=\"https://biodiversitylibrary.org/page/" + i + "\" target=\"_new\">" + i + "</a></h3>";
								
								if (data.data.hits[i].html) {
									html += "<div class=\"text\">";
									
									var page_html = data.data.hits[i].html;
									
									page_html = page_html.replace(/<mark>/g, "OPENMARKTAG");
									page_html = page_html.replace(/<\/mark>/g, "CLOSEMARKTAG");
									
									page_html = page_html.replace(/</g, "&lt;");
									page_html = page_html.replace(/>/g, "&gt;");
									
									page_html = page_html.replace(/OPENMARKTAG/g, "<mark>");
									page_html = page_html.replace(/CLOSEMARKTAG/g, "</mark>");
									
									
									html += page_html;
									html += "</div>";
								}
							}

							pages.innerHTML = html;
							pages.style.display = "block";
						}
						
							
					});
				}
			);
		}	
	</script>';
	}	
	
	// Display home page, or error message if badness happened
	function default($error_msg = "")
	{	
		$this->start();
		$this->header();
		$this->main_start();
		
		if ($error_msg != '')
		{
			echo '<div><strong>Error!</strong> ' . $error_msg . '</div>';
		}
		else
		{
			// main content	
			echo '<h1>Match BHL link to PageID</h1>';
			
			$example = "http://www.biodiversitylibrary.org/item/36660#page/306/mode/2up";
			$example_target = "Acharana";
			
			echo '<p>The book reader used by BHL to display pages rewrites 
			the URL your web browser displays as you navigate through the book. Hence two
			people looking at the same page may have different URLs for that page. If we 
			take a page link of the form <b>https://www.biodiversitylibrary.org/page/nnn</b>
			as the definitive link for a BHL page, then this tools aims to convert browser URLs
			to this definitive link.</p>';
			
			echo '<p>A further complication is that the book reader can display one or more
			pages. Typically the book reader displays a single page, but some people 
			prefer two pages side-by-side. The BHL URL stores this information in a <b>mode</b>
			parameter, e.g. <b>mode/1up</b> is one page. However, if we have more than one page
			on screen, which page did the user intend to link too? By default this tool will
			return the PageIDs of the two pages. If you also supply a "target string", 
			such as a taxonomic name, then it will attempt to find that string in the two pages.
			If it is only present on one page, the PageID for that page is returned.</p>';
			
			echo '<p>A good example is <b>http://www.biodiversitylibrary.org/item/36660#page/306/mode/2up</b>
			(from NHM Butterflies and Moths of the World)
			and <b>https://www.biodiversitylibrary.org/page/9340414</b> (from Afromoths.net) 
			which are two URLs given for the same genus
			<i>Acharana</i> Moore, 1885 on the same page in the same book.</p>';
			
			
			echo '<p>To use as an API simply GET the site URL with the citation as the parameter "url",
			and the optional string to match as "target", 
			e.g.</p>
			<p> <a href="' . $_SERVER['PHP_SELF'] . '?url=' . urlencode($example) . '&target=' . urlencode($example_target) . '">?q=' . $example . '&target=' . $example_target . '</a></p>
			<p>
			or POST a JSON document of the form <code>{ "url": "browser link to BHL page", "target": "string to match" }</code>. The API will return results as JSON.
			</p>';
			
			
		echo '
		<div class="flexbox" >
				<div class="stretch">
					<input type="input" id="url" name="url" placeholder="BHL link..." value="' . $example . '" autofocus/>
					<p>Enter target text to match, such as taxon name (optional).</p>
					<input type="input" id="target" name="target" placeholder="Optional string to match..." value="' . $example_target . '" autofocus/>
				</div>
				<div class="normal">
					<button onclick="go()">Go</button>
				</div>
		</div>	
		';	
			echo '<div id="output" style="display:none;" class="data"></div>';
			
			echo '<div id="pages" style="display:none;" ></div>';
		}
		
		$this->main_end();
		$this->footer();
		$this->end();	
	}
}



// Web or API?
if (count($_GET) == 0 && $_SERVER['REQUEST_METHOD'] != 'POST')
{
	// Web
	$site = new MySite("Parse BHL link");
	$site->default();
}
else
{
	// API
	$doc = null;

	if ($_SERVER['REQUEST_METHOD'] == 'GET')
	{
		$doc = http_get_endpoint(["url"]);
	}
	else
	{
		$doc = http_post_endpoint(["url"]);
	}

	if ($doc->status == 200)
	{
		// interpret the BHL link
		$doc->data = parse_source_url($doc->url);
		
		// match
		$string = isset($doc->target) ? $doc->target : '';
		match_target($doc->data, $string);
		
	
	}
	
	send_doc($doc);	
}

?>
