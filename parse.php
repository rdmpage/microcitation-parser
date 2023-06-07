<?php

// Microparser

require_once (dirname(__FILE__) . '/lib/api_utilities.php');
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
							
						if (data.xml) {
							displayXml(data.xml);
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
			echo '<h1>Microcitation parser</h1>';
			
			$example = "Proc. zool. Soc. Lond. 1882: 781";
			
			echo '<p>This tool takes a "microcitation" such as "' . $example . '"
			and attempts to convert it into structured data. 
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
			
			
			//echo '<input id="q" name="q" size="30" value="Proc. zool. Soc. Lond. 1882: 781"><button onclick="go()">Parse</button>';
			
			
			
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
	$site = new MySite("Microparser");
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
	}
	
	send_doc($doc);	
}

?>
