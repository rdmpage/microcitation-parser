<?php

// Take BHL PageID and check the text

require_once (dirname(__FILE__) . '/lib/api_utilities.php');
require_once (dirname(__FILE__) . '/lib/bhl_utilities.php');
require_once (dirname(__FILE__) . '/lib/microparser.php');
require_once (dirname(__FILE__) . '/lib/textsearch.php');
require_once (dirname(__FILE__) . '/site.php');

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
			//output.style.display = "none";
			output.innerHTML = "<progress></progress>";
			
			var pages = document.getElementById("pages");
			pages.style.display = "none";			

			var pageid = document.getElementById("pageid").value;
			var target = document.getElementById("target").value;
			
			var doc = {};
			doc.pageid = pageid;
			
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
									html += data.data.hits[i].html;
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
			echo '<h1>BHL PageID and text match to BHL</h1>';
			
			$example = "30155815";
			$example_target = "Anarsia sagittaria";
			
			echo '<p>This tool takes a <a href="https://www.biodiversitylibrary.org">Biodiversity Heritage Library</a> (BHL).
			PageID such as "' . $example . ' and a string (such as a taxonomic name) and 
			checks whether that string occurs on the BHL page.
			</p>';

			echo '<p>To use as an API simply GET the site URL with the BHL PageID (just the number) as the parameter "pageid",
			and the string to match as "target", 
			e.g.</p>
			<p> <a href="' . $_SERVER['PHP_SELF'] . '?pageid=' . urlencode($example) . '&target=' . urlencode($example_target) . '">?q=' . $example . '&target=' . $example_target . '</a></p>
			<p>
			or POST a JSON document of the form <code>{ "pageid": integer, "target": "string to match" }</code>. The API will return results as JSON.
			</p>';
			
			
		echo '
		<div class="flexbox" >
				<div class="stretch">
					<input type="number" id="pageid" name="pageid" placeholder="PageID..." value="' . $example . '" autofocus/>
					<p>Enter target text to match, such as taxon name.</p>
					<input type="input" id="target" name="target" placeholder="Target..." value="' . $example_target . '" autofocus/>
				</div>
				<div class="normal">
					<button onclick="go()">Go</button>
				</div>
		</div>	
		';	
			echo '<div id="output" class="data"></div>';
			
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
	$site = new MySite("Parse microcitation and text match to BHL");
	$site->default();
}
else
{
	// API
	$doc = null;

	if ($_SERVER['REQUEST_METHOD'] == 'GET')
	{
		$doc = http_get_endpoint(["pageid", "target"]);
	}
	else
	{
		$doc = http_post_endpoint(["pageid", "target"]);
	}

	if ($doc->status == 200)
	{
		if (isset($doc->pageid))
		{
			$doc->data = new stdclass;
			$doc->data->BHLPAGEID[] = (Integer)$doc->pageid;
			unset($doc->pageid);
			
		
			// text
			if (isset($doc->data->BHLPAGEID))
			{
				$doc->data->text = array();
				$doc->data->hits = array();
				
				foreach ($doc->data->BHLPAGEID as $pageid)
				{
					$text = get_bhl_page_text($pageid);
					if ($text != '')
					{
						if (!isset($doc->data->text))
						{
							$doc->data->text = array();
						}
	
						$text = mb_convert_encoding($text, 'UTF-8', mb_detect_encoding($text));
	
						$doc->data->text[$pageid] = $text;
					}
					
					foreach ($doc->data->text as $id => $text)
					{
						if (1)
						{
							$hits = find_in_text(
								$doc->target, 
								$text, 
								isset($doc->ignorecase) ? $doc->ignorecase : true,
								isset($doc->maxerror) ? $doc->maxerror : 3	
								);
						}
						else
						{
							$hits = find_in_text_simple(
								$doc->target, 
								$text, 
								isset($doc->ignorecase) ? $doc->ignorecase : true,
								isset($doc->maxerror) ? $doc->maxerror : 2	
								);								
						}
						
						if ($hits->total > 0)
						{
							$doc->data->hits[$id] = $hits;
						}
					}	
				}		
			}
		}
		
	}
	
	send_doc($doc);	
}

?>
