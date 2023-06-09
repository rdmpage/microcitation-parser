<?php

// BHL

require_once (dirname(__FILE__) . '/api_utilities.php');
require_once (dirname(dirname(__FILE__)) . '/data/sqlite.php');
require_once (dirname(__FILE__) . '/compare.php');


//----------------------------------------------------------------------------------------
// Given a structured citation with container-title, find one or more ISSNs
function find_issn($doc, $debug = false)
{
	if (isset($doc->data))
	{
		if (isset($doc->data->{'container-title'}))
		{
			$issns = array();
			
			$title = $doc->data->{'container-title'};
	
			$sql = 'SELECT DISTINCT issn FROM issn WHERE title="' . addcslashes($title, '"') . '" COLLATE NOCASE;';

			$result = do_query($sql);

			foreach ($result as $row)
			{
				$issns[] = $row->issn;
			}
	
			if (count($issns) == 0)
			{
				// try approx match
				$like_title = preg_replace('/(\.\s+)/', '% ', $title);
				$like_title = trim($like_title);
		
				$sql = 'SELECT DISTINCT title, issn FROM issn WHERE title LIKE "' . addcslashes($like_title, '"') . '" COLLATE NOCASE;';
	
				$result = do_query($sql);
		
				// get best match...
				$max_score = 0;
				
				foreach ($result as $row)
				{		
					$result = compare_common_subsequence(
						$title, 
						$row->title,
						false);

					if ($result->normalised[1] > 0.95)
					{
						// one string is almost an exact substring of the other
						if ($result->normalised[1] > $max_score)
						{
							$max_score = $result->normalised[1];
							$issns = array($row->issn);
						}
					}
				}
	
	
			}
			
			if (count($issns) > 0)
			{
				$doc->data->ISSN = $issns;
			}
		}
	}
	
	return $doc;
}		


//----------------------------------------------------------------------------------------
// Given a structured citation with ISSN, volume, and page(s) find a PID (by default a DOI)
function find_pid($doc, $debug = false)
{
	if (isset($doc->data))
	{
		if (
			isset($doc->data->ISSN)
			&& isset($doc->data->{'volume'})
			&& isset($doc->data->{'page'})			
			)
		{
			$dois = array();
			
			$parts = preg_split('/[-|—]/u', $doc->data->page);
		
			foreach ($doc->data->ISSN as $issn)
			{
				$sql = 'SELECT * FROM works WHERE issn="' . $issn . '" AND volume="' . $doc->data->volume . '"';
				
				// page range, does it match?
				if (count($parts) == 2)
				{
					$sql .= ' AND spage="' . $parts[0] . '" AND epage="' . $parts[1] . '"';
				}
				else
				{
					$sql .= ' AND (spage="' . $doc->data->page . '" OR "' . $doc->data->page . '" BETWEEN CAST(spage AS INT) AND CAST(epage AS INT))';
				}
				
				// series if we have it
				if (isset($doc->data->{'collection-title'}))
				{
					$sql .= ' AND series="' . $doc->data->{'collection-title'} . '"';
				}
				
				$sql .= ' AND doi IS NOT NULL';
				
				if ($debug)
				{
					echo $sql;
				}

				$result = do_query($sql);

				foreach ($result as $row)
				{
					$dois[] = $row->doi;
				}
			}
			
			if ($debug)
			{
				print_r($dois);
			}
			
			$dois = array_unique($dois);
	
			if (count($dois) == 1)
			{
				$doc->data->DOI = $dois[0];
			}
		}
	}
	
	return $doc;
}		


//----------------------------------------------------------------------------------------
// Generate a citation
function format_citation($doc, $debug = false)
{
	if (isset($doc->data) && isset($doc->style))
	{
		if (isset($doc->data->DOI))
		{
			$mode = 0; // use content negotiation with style sheet

			$parts = explode('/', $doc->data->DOI);
			$prefix = $parts[0];
	
			switch ($prefix)
			{
				case '10.18942':
					$mode = 1;
					break;
	
				default:
					$mode = 0;
					break;
			}	

			if ($mode)
			{
				// get CSL-JSON and format locally
				$json = get('https://doi.org/' . $doc->data->DOI, 'application/vnd.citationstyles.csl+json');
				$csl = json_decode($json);
				if ($csl)
				{
					// fix
					if (!isset($csl->type))
					{
						$csl->type = 'article-journal';
					}
		
					$style_sheet = StyleSheet::loadStyleSheet($doc->style);
					$citeProc = new CiteProc($style_sheet);
					$doc->data->citation = $citeProc->render(array($csl), "bibliography");
					$doc->data->citation = strip_tags($doc->data->citation);
					$doc->data->citation = trim(html_entity_decode($doc->data->citation, ENT_QUOTES | ENT_HTML5, 'UTF-8'));	
				}		
			}
			else
			{
				$doc->data->citation = get('https://doi.org/' . $doc->data->DOI, 'text/x-bibliography; style=' . $doc->style);
				if (preg_match('/^<!DOCTYPE html/', $doc->data->citation))
				{
					unset ($doc->data->citation);
				}
				else
				{
					$doc->data->citation = trim($doc->data->citation);
					$doc->data->citation = preg_replace('/\R/u', ' ', $doc->data->citation);
					$doc->data->citation = preg_replace('/\s\s+/u', ' ', $doc->data->citation);		
				}
			}

		}
	}
	
	return $doc;
}		



?>
