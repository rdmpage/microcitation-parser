<?php

// Encapsulate a Very simple web site, for example to wrap an API

class Site 
{
	var $title 			= "[Untitled]";
	var $hero			= "A web site";
	var $meta			= [];
		
	function __construct($title = "[Untitled]") 
	{
		$this->title = $title;
		
		// meta tags
		$meta = new stdclass;
		$meta->charset = "utf-8";
		$this->meta[] = $meta;

		$meta = new stdclass;
		$meta->name = "theme-color";
		$meta->content = "#1976d2";
		$this->meta[] = $meta;		
	}
	
	// CSS--------------------------------------------------------------------------------
	function style_body()
	{
		echo 	
		'body {
		padding:0;
		margin:0;
		font-family: sans-serif;
		font-size:1em;
		
		/* gives us a sticky footer */
		display: flex;
    	min-height: 100vh;
    	flex-direction: column;
    	}
		';
	}
	
	function style_main()
	{
		echo 
	'main {	
		margin: 0 auto;
		width:80%;	
		
		/* gives us a sticky footer */
		flex: 1 0 auto;	
	}';
	}
	
	function style_paragraph()
	{
		echo 	'p {
		color: #666666;
		line-height:1.3em;
		}';	
	}
	
	
	function style_header()
	{
		echo 'nav {
		position: sticky; 
		top: 0;
		
		margin: 0 auto;
		width:80%;			
		
		/*background:#1a5d8d;*/
		padding:1em;
		z-index:1000;
	}';
	}
	
	function style_footer()
	{
		echo 	'footer {
		/* background:rgb(231,224,185); */
		padding:1em;
		border-top:1px solid rgb(222,222,222);
		
		margin: 0 auto;
		width:80%;	
		}';	
	}
	
	function style_search()
	{
		echo '	/* searchbox */
    .flexbox { display: flex; }
    .flexbox .stretch { flex: 1; }
    .flexbox .normal { flex: 0; margin: 0 0 0 1rem; }
    .flexbox div input { 
    	width: 95%; 
    	font-size:1em; 
    	border:1px solid rgb(222,222,222); 
    	font-weight:bold;
    	padding: 0.5em 1em;
    	border-radius: 0.2em;
     }
    .flexbox div button { 
    	font-size:1em;
    	 
    	background: #1976d2; 
    	color:white;

    	border:1px solid #1976d2;
    	
    	padding: 0.5em 1em;
    	border-radius: 0.2em;
    	
    	-webkit-appearance: none;
    	display: inline-block;
        /* border: none;    */
    }
 ';
	
	}
	
	function style_links()
	{
		echo 	
	'a {
		text-decoration: none;
		color:#1a0dab;
	}
	
	a:hover {
		text-decoration: underline;		
	}';

	}
	
	function style_custom()
	{
	}

	
	// style the key elements of the page
	function css()
	{
		echo '<style type="text/css">';
		$this->style_body();
		$this->style_main();	
		$this->style_paragraph();
		$this->style_header();	
		$this->style_footer();	
		$this->style_search();	
		$this->style_links();
		$this->style_custom();
		echo '</style>';
	}
	
	// Web page components----------------------------------------------------------------
	
	function home()
	{
		// https://stackoverflow.com/a/6768831
		//echo '<a href="' . (empty($_SERVER['HTTPS']) ? 'http' : 'https') . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]" . '">Home</a>';
		echo '<a href=".">Home</a>';
	}	
	
	// Header at top of page
	function header($q = '')
	{
		echo '<nav>' . "\n";
		$this->home();
		//$this->search_bar($q);	
		echo '</nav>' . "\n";	
	}
	
	
	// Search bar, displayed within header
	function search_bar($q = "")
	{
		echo '
		<form class="flexbox" method="GET" action=".">
				<div class="stretch">
					<input type="input" id="q" name="q" placeholder="Search..." value="' . $q . '" autofocus/>
				</div>
				<div class="normal">
					<button>Search</button>
				</div>
		</form>	
		';	
	}

	// Footer at bottom of page
	function footer()
	{
		echo '<footer>' . "\n";
		$this->footer_content();
		echo '</footer>' . "\n";	
	}
	
	function footer_content()
	{
		//echo '[Footer goes here]';
	}
	
	function main_start()
	{
		echo "<main>\n<div class=\"container\">\n";	
	}

	function main_end()
	{
		echo "</div>\n</main>\n";
	}
	
	// Display meta tags
	function metatags()
	{
		foreach ($this->meta as $meta)
		{
			echo '<meta';
			
			foreach ($meta as $k => $v)
			{
				echo ' ' . $k . '="' . htmlentities($v, ENT_HTML5) . '"';
			}
			
			echo '>' . "\n";
		}	
	}
	
	// Javascript specific to <HEAD> for this site
	function javascript_head()
	{
		echo '<script></script>' . "\n";	
	}	
	
	// Javascript specific to top of <BODY> for this site
	function javascript_body_start()
	{
		echo '<script></script>' . "\n";
	}	

	// Javascript specific to bottom of <BODY> for this site
	function javascript_body_end()
	{
		echo '<script></script>' . "\n";
	}	
	
	// Start the HTML document up to and including <body>
	function start($meta = '')
	{
		echo "<!DOCTYPE html>\n<html>\n<head>\n";
		
		$this->metatags();
		
		echo '<title>' . $this->title . '</title>';

		$this->css();
		
		$this->javascript_head();
		
		echo "</head>\n";
		echo "<body>\n";
		
		$this->javascript_body_start();
	}
	
	// End the HTML document from </body>
	function end()
	{
		$this->javascript_body_end();
	
		echo "</body>\n";
		echo "</html>\n";

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
			echo '<h1>Hello world!</h1>';
			echo '<p>' . $this->hero . '</p>';
		}
		
		$this->main_end();
		$this->footer();
		$this->end();	
	}
	
}

?>

