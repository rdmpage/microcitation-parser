<?php

//----------------------------------------------------------------------------------------

$filename = 'title.txt';

$headings = array();

$row_count = 0;

$keys = array('TitleID', 'FullTitle', 'ShortTitle');

echo join("\t", $keys) . "\n";

$file = @fopen($filename, "r") or die("couldn't open $filename");
		
$file_handle = fopen($filename, "r");
while (!feof($file_handle)) 
{
	$row = fgetcsv(
		$file_handle, 
		0, 
		"\t" 
		);
		
	$go = is_array($row);
	
	if ($go)
	{
		if ($row_count == 0)
		{
			$headings = $row;	
			// Fix BOM
			$headings[0] = preg_replace('/\xEF\xBB\xBF/', '', $headings[0]);
		}
		else
		{
			$obj = new stdclass;
		
			foreach ($row as $k => $v)
			{
				if ($v != '')
				{
					$obj->{$headings[$k]} = $v;
				}
			}
			
			
			
			$output = array();
			
			foreach ($keys as $k)
			{
				if (isset($obj->{$k}))
				{
					$output[] = $obj->{$k};
				}
				else
				{
					$output[] = "";
				}
			}
			
			echo join("\t", $output) . "\n";
			
		}
	}	
	$row_count++;
}
?>
