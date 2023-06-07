<?php

// Microparser that matches to BHL

require_once (dirname(__FILE__) . '/lib/api_utilities.php');
require_once (dirname(__FILE__) . '/lib/pid_utilities.php');
require_once (dirname(__FILE__) . '/lib/microparser.php');
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
			
			//var pages = document.getElementById("pages");
			//pages.style.display = "none";

			var q = document.getElementById("q").value;
			
			var doc = {};
			doc.q = q;
			
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
						
						/*
						if (data.data.BHLPAGEID) {
							
							var html = "<h2>Page images</h2>";
						
							for (var i in data.data.BHLPAGEID) {
								html += "<figure class=\"pagethumbnail\">";
								html += "<a href=\"https://biodiversitylibrary.org/page/" + data.data.BHLPAGEID[i] + "\" target=\"_new\">";
								html += "<image src=\"https://biodiversitylibrary.org/pagethumb/" + data.data.BHLPAGEID[i] + "\">";
								html += "</a>";
								html += "<figcaption>" + data.data.BHLPAGEID[i] + "</figcaption>";
								html += "</figure>";
							}
							pages.innerHTML = html;
							pages.style.display = "block";
						}
						*/
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
			echo '<h1>Parse microcitation and get PIDs</h1>';
			
			$example = "Aust. J. Zool. 6: 280 [key], 281, figs 2G, 2H, 3D, pl. 1, fig. 2.";
			
			echo '<p>This tool takes a "microcitation" such as "' . $example . '",
			attempts to convert it into structured data and adds persistent identifiers
			such as ISSNs and DOIs.
			</p>';

			echo '<p>To use as an API simply GET the site URL with the citation as the parameter "q", 
			e.g.</p>
			<p> <a href="' . $_SERVER['PHP_SELF'] . '?q=' . urlencode($example) . '">?q=' . $example . '</a></p>
			<p>
			or POST a JSON document of the form <code>{ "q": "microcitation" }</code>. The API will return results as JSON.
			</p>';
			
			
		echo '
		<div class="flexbox" >
				<div class="stretch">
					<input type="input" id="q" name="q" placeholder="Citation..." value="' . $example . '" autofocus/>
				</div>
				<div class="normal">
					<button onclick="go()">Go</button>
				</div>
		</div>	
		';	
			
			echo '<div id="output" style="display:none;" class="data"></div>';
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
	$site = new MySite("Parse microcitation and get PIDs");
	$site->default();
}
else
{
	// API
	$doc = null;

	if ($_SERVER['REQUEST_METHOD'] == 'GET')
	{
		$doc = http_get_endpoint(["q"]);
	}
	else
	{
		$doc = http_post_endpoint(["q"]);
	}

	if ($doc->status == 200)
	{
		$parse_result = parse($doc->q, false);

		// we want to add results to existing doc
		foreach ($parse_result as $k => $v)
		{
			$doc->{$k} = $v;
		}
		
		// BHL specific stuff
		if (isset($doc->data))
		{
			if (isset($doc->data->{'container-title'}))
			{
				$doc = find_issn($doc);
				$doc = find_pid($doc);
				
				/*
				if (isset($doc->data->DOI))
				{
					$doc->style = 'apa';
					
					$doc = format_citation($doc);				
				}
				*/
				
				/*
				$bhl_titles = match_bhl_title($doc->data->{'container-title'});
				if (count($bhl_titles) > 0)
				{
					$doc->data->BHLTITLEID = $bhl_titles;
				}	
		
				if (isset($doc->data->BHLTITLEID))
				{
					// BHL page
					$doc = find_bhl_page($doc);
				}
				*/		
			}
		}
		
	}
	
	send_doc($doc);	
}

?>
