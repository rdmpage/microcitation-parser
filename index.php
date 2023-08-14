<?php

// Web site to document API

require_once (dirname(__FILE__) . '/site.php');


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
		}
		
		input {
			font-size:2em;
		}
		
		
		button {
			font-size:2em;
		}	
		';	
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
			echo '<h1>Microcitation parsing</h1>';
			
			echo '<p>This web site brings together tools to parse "microcitations" 
			to literature in the
			<a href="https://www.biodiversitylibrary.org">Biodiversity Heritage Library</a> (BHL)
			and elsewhere.</p>';
			
			echo '<p>Each tool has its own web page, which is also the endpoint for the API 
			for that service.</p>';
			
			echo '<h2>Microcitation parser</h2><p>A tool to convert a microcitation to 
			structured data. <a href="parse.php">Try it!</a></p>';

			echo '<h2>Parse microcitation and match to BHL</h2><p>Parses a microcitation 
			and tries to locate the corresponding page(s) in BHL. <a href="bhl.php">Try it!</a></p>';

			echo '<h2>Parse microcitation and text match to BHL</h2><p>Parses a microcitation, 
			tries to locate the corresponding page(s) in BHL, then checks whether a give string
			(such as a taxonomic name) appears on that page. <a href="bhltext.php">Try it!</a></p>';
			
			echo '<h2>BHL PageID and text match to BHL</h2><p>Takes a BHL PageID, then checks 
			whether a give string (such as a taxonomic name) appears on that page. 
			<a href="bhlpagetext.php">Try it!</a></p>';

			echo '<h2>Parse microcitation and get PIDs (DOIs)</h2><p>Parses a microcitation, 
			and adds persistent identifiers such as ISSNs and DOIs. <a href="pid.php">Try it!</a></p>';

			echo '<h2>Parse microcitation and get formatted citation</h2><p>Parses a microcitation, 
		    finds DOI, then creates formated citation. <a href="citation.php">Try it!</a></p>';
			
		}
		
		$this->main_end();
		$this->footer();
		$this->end();	
	}
}

$site = new MySite("Microcitation parsing");
$site->default();


?>
