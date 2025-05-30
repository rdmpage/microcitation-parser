<?php

// Parse a microcitation

require_once (dirname(__FILE__) . '/collation_parser.php');

//--------------------------------------------------------------------------------------------------
// From http://snipplr.com/view/6314/roman-numerals/
// Expand subtractive notation in Roman numerals.
function roman_expand($roman)
{
	$roman = str_replace("CM", "DCCCC", $roman);
	$roman = str_replace("CD", "CCCC", $roman);
	$roman = str_replace("XC", "LXXXX", $roman);
	$roman = str_replace("XL", "XXXX", $roman);
	$roman = str_replace("IX", "VIIII", $roman);
	$roman = str_replace("IV", "IIII", $roman);
	return $roman;
}
    
//--------------------------------------------------------------------------------------------------
// From http://snipplr.com/view/6314/roman-numerals/
// Convert Roman number into Arabic
function arabic($roman)
{
	$result = 0;
	
	$roman = strtoupper($roman);

	// Remove subtractive notation.
	$roman = roman_expand($roman);

	// Calculate for each numeral.
	$result += substr_count($roman, 'M') * 1000;
	$result += substr_count($roman, 'D') * 500;
	$result += substr_count($roman, 'C') * 100;
	$result += substr_count($roman, 'L') * 50;
	$result += substr_count($roman, 'X') * 10;
	$result += substr_count($roman, 'V') * 5;
	$result += substr_count($roman, 'I');
	return $result;
} 


//----------------------------------------------------------------------------------------
// Output the regex matching using XML-style tags for debugging, and with an eye on
// generating training data.
// need to fix regexp to include series so we have a tag for that.
function match_to_tags($text, $matches, $debug = false)
{
	$terminating_character = '•';

	$xml = '';
	
	$xml .= '<sequence>';
	
	$length = mb_strlen($text);
	
	if ($debug)
	{
		// visual debugging
		$ticks = array();
	
		for ($i = 0; $i < $length; $i++)
		{
			$ticks[] = ' ';
		}
	
		foreach ($matches as $k => $match)
		{
			if (is_numeric($k))
			{
				// skip
			}
			else
			{
				$start = $match[1];	
						
				// handle problem that PREG_OFFSET_CAPTURE doesn't work with UTF-8
				$start = mb_strlen(substr($text, 0, $start), 'UTF-8');			
				$ticks[$start] = '|';						
				$match_length = mb_strlen($match[0]);			
				$ticks[$match[1] + $match_length ] = '|';			
			}
		}
		echo join('', $ticks) . "\n";
		echo $text . "\n";
	}
	
	// split text into character array
	$text_array = mb_str_split($text);

	// append a terminating character so last tag can be added
	$text_array[] = $terminating_character;
			
	$tag_starts = array();
	$tag_ends 	= array();
	
	// process labelled matches in regex
	foreach ($matches as $tag => $match)
	{
		if (is_numeric($tag))
		{
			// skip
		}
		else
		{
			switch ($tag)
			{
				// any tags we want to "eat"
				case 'page':
					break;

				// any remaining tags
				default:
					$start = $match[1];	
			
					// handle problem that PREG_OFFSET_CAPTURE doesn't work with UTF-8
					$start = mb_strlen(substr($text, 0, $start), 'UTF-8');
					
					if (!isset($tag_starts[$start]))
					{
						$tag_starts[$start] = array();
					}
					
					$tag_name = $tag;
					
					if ($tag_name == 'year')
					{
						$tag_name = 'date';
					}					
					
					$tag_starts[$start][] = $tag_name;
			
					$match_length = mb_strlen($match[0]);
					$end = $start + $match_length;
						
					if (!isset($tag_ends[$end]))
					{
						$tag_ends[$end] = array();
					}
					$tag_ends[$end][] = $tag_name;	
					break;
			}		
		}
	}
	
	//print_r($tag_starts);
	//print_r($tag_ends);
	
	foreach ($text_array as $pos => $char)
	{		
		// close any tags that end here
		if (isset($tag_ends[$pos]))
		{
			foreach ($tag_ends[$pos] as $tag)
			{
				$xml .= '</' . $tag . '>';
			}
		}
	
		// open any tags that start here
		if (isset($tag_starts[$pos]))
		{		
			foreach ($tag_starts[$pos] as $tag)
			{
				$xml .= '<' . $tag . '>';
			}
		}
		
		if ($char != $terminating_character)
		{
			$xml .= $char;	
		}
	}

	$xml .= '</sequence>';
	return $xml;
}

//----------------------------------------------------------------------------------------
// Measure how much of input string is "covered" by the regular expression
// need to handle cases where tags may be nested
function match_span($text, $matches, $debug = false)
{
	$length = mb_strlen($text);

	$coverage = array_fill(0, $length, 0);

	// process labelled matches in regex
	foreach ($matches as $tag => $match)
	{
		if (is_numeric($tag))
		{
			// skip
		}
		else
		{
			// fill any positions in the range of this tag with "1"
			
			$start = $match[1];	
	
			// handle problem that PREG_OFFSET_CAPTURE doesn't work with UTF-8
			$start = mb_strlen(substr($text, 0, $start), 'UTF-8');
			
			$match_length = mb_strlen($match[0]);
			$end = $start + $match_length;
			
			for ($i = $start; $i < $end; $i++)
			{
				$coverage[$i] = 1;
			}
		}
	}

	//echo $text . "\n";
	//echo join('', $coverage) . "\n";
	
	$count = 0;
	foreach ($coverage as $c)
	{
		if ($c == 1)
		{
			$count++;
		}
	}
	
	// percent coverage
	$score = 100 * $count / $length;
	
	return $score;
}

//----------------------------------------------------------------------------------------
// Parse citation using regex.
// likley to eventually need multiple patterns here...
function parse($text, $debug = false)
{
	$obj = new stdclass;
	$obj->status = 404;
	

	// regex expressions to match microcitations
	// note that we include anything that looks ike a delimiter as these could be 
	// useful if we ever move to an appraoch such as CRF
	
	$comment_pattern = '(\s*\[[^\]]+\])?';

	//$date_pattern 		= "(\d+\s+)?([A-Z]|[a-z]|\'|-)+(\s+\d+)?";
	
	$year_pattern = '(?<year>\(?[0-9]{4}\)?,?)';

	// include delimiter after journal, also include series in this pattern
	$journal_pattern 	= "(?<journal>.*)(\s+\(\d+\))?[,]?";
	
	$journal_pattern = "(?<journal>([\p{L}]+[\.|,]?)((\s+[\p{L}]+[\.|,]?)+)?[,]?(\s+\(\d+\))?)";
	$journal_pattern = "(?<journal>([\p{L}]+[\.|,]?)((\s+[\p{L}]+[\.|,]?)+)?[,]?(\s+\((N.S.|\d+)\))?)";
	$journal_pattern = "(?<journal>([\p{L}]+[\.|,]?)((\s+[\p{L}]+[\.|,]?)+)?[,]?(\s+\((N.S.|\d+)\))?(\([A-Z]\))?)";
	$journal_pattern = "(?<journal>([\p{L}]+\.?,?)((\s*[\p{L}]+\.?\,?)+)?[,]?(\s+\((N.S.|\d+)\))?(\([A-Z]\))?)";

	// current
	$journal_pattern = "(?<journal>([\p{L}]+\.?,?)((\s*[\p{L}]+\.?\,?)+)?[,]?(\s+\([^\)]+\))?)";
	$journal_pattern = "(?<journal>([\p{L}]+\.?,?)((\s*[\p{L}]+\.?\,?)+)?[,]?(\s*\([^\)]+\))?)";

	// include issue in "volume", and include delimiter
	$volume_pattern 	= "(?<volume>\d+[A-Z]?(-\d+)?(\s*\(\d+(-\d+)?\))?[,|:]?)";
	$volume_pattern 	= "(?<volume>\d+[A-Z]?(-\d+)?(\s*\(\d+(-\d+)?\))?(,?\s+fasc\.\s+\d+)?[,|:]?)";
	$volume_pattern 	= "(?<volume>\d+[A-Z]?(-\d+)?(\s*\(\d+(-\d+)?\))?(,?\s+(no|fasc)\.\s+\d+)?[,|:]?)";

	$volume_pattern 	= "(?<volume>([N|n]o.\s+)?\d+[A-Z]?(-\d+)?(\s*\(\d+(-\d+)?\))?(,?\s+(no|fasc)\.\s+\d+)?[,|:]?)";

	// current
	$volume_pattern 	= "\s*(?<volume>([N|n]o.\s+)?\d+[A-Z]?(-\d+)?(\s*\([^\)]+\))?(,?\s+(no|fasc)\.\s+\d+)?[,|:])";
		
	$volume_roman_pattern 	= "\s*(?<volume>([ivxlcIVXLC]+)\.?)";

	// include delimiter
	$page_pattern 		= "(p\.\s*)?(?<page>(\d+|[xvlci]+)(-(\d+|[xvlci]+))?)" . $comment_pattern . "[\.]?";

	// figures, plates, etc.
	$extra_pattern = "([,|;]\s+(.*))?";
	
	$collation_pattern = '\s*(?<collation>' . $page_pattern  . $extra_pattern . ')';
	
	
	$journal_simple = '(?<journal>(\s*[\-|\']?[\p{L}]+[\.]?[,]?)+(\s*\([^\)]+\))?)';
	$journal_simple = '(?<journal>(\s*[\-|\']?[\p{L}]+[\.]?[,]?)+(\s*\([^\)]+\)){0,})';
	$journal_simple = '(?<journal>(\s*[\-|\']?[\p{L}]+[\.]?[,]?([\'|’]s)?)+(\s*\([^\)]+\)){0,})';

	// Biologia cent.-am. (Zool.) Lepid.-Heterocera
	$journal_para = '(?<journal>(\s*[\-]?[\p{L}]+[\.]?[,]?)+(\s*\([^\)]+\))\s*(\s*[\-]?[\p{L}]+[\.]?[,]?)+)';
				
	// authorship	
	$name_pattern = '((\p{Lu}\.)+\s*)?\p{Lu}\p{L}+(-\p{Lu}\p{L}+)?';
	
	// in(,?\s+(\p{Lu}\p{L}+))+\s+&\s+(\p{Lu}\p{L}+|al\.),\s+
	
	$authors = '(,?\s*' . $name_pattern . ')+(\s+&\s+' . '(' . $name_pattern . '|al\.))?';
	
	$month_en = '(January|February|March|April|May|June|July|August|September|October|November|December)';
	
	$months = '([A-Z]\w+(-[A-Z]\w+)?)';
		
	$matched = false;	
	$matches = array();
	
	$patterns = array(
	
		// authorship prefix
		'/^(?<author>[I|i]n\s+' . $authors . ',)\s+' . $journal_simple . $volume_pattern . $collation_pattern . '/u',
		'/^(?<author>[I|i]n\s+' . $authors . ',)\s+' . $journal_simple . '[,|:]' . $collation_pattern . '/u',

		'/^(?<author>[I|i]n\s+' . $authors . ',)\s*' . $year_pattern . '\s*' . $journal_simple . $volume_pattern . $collation_pattern . '/u',

		'/^(?<author>' . $authors . ',?)\s*' . $year_pattern . '\s*' . $journal_simple . $volume_pattern . $collation_pattern . '/u',
		
		// complex journal pattern
		'/^' . $journal_para  .  $volume_pattern . $collation_pattern . '/u',
		
		// journal and date then volume
		'/^' . $journal_simple . '\s*' . $year_pattern . $volume_pattern . $collation_pattern . '/u',
		
		// Proc. Zool. Soc. London, 7 (1839), p. 144
		// journal, volume, date, page
		'/^' . $journal_simple . '\s+(?<volume>\d+)\s+(?<year>\([1|2][0-9]{3}\),?)\s+' . $collation_pattern . '/u',
		
		// Joum. f. Orn., 72, 1924, p. 449.
		'/^' . $journal_simple . '\s+(?<volume>\d+),\s+(?<year>[1|2][0-9]{3},?)\s+' . $collation_pattern . '/u',
		
		
		// IPNI
		// Lilloa xxx. 131 (1960).
		'/^' . '(?<journal>[\p{L}]+)' . $volume_roman_pattern . $collation_pattern . '\s+(?<year>\([1|2][0-9]{3}\))' . '/u',
		'/^' . '(?<journal>([\p{L}]+\.?\s*)+,)\s*(?<volume>([ivxlcIVXLC]+)\.?)\s*(?<collation>(p\.\s*)?(?<page>(\d+|[xvlci]+)(-(\d+|[xvlci]+))?)(\s*\[[^\]]+\])?[\.]?([,|;]\s+(.*))?)\s+(?<year>\([1|2][0-9]{3}\))' . '/u',
		'/^' . '(?<journal>([\p{L}]+\.?\s*)+,)\s*(?<volume>([ivxlcIVXLC]+)\.?\s+No.\s+\d+,)\s*(?<collation>(p\.\s*)?(?<page>(\d+|[xvlci]+)(-(\d+|[xvlci]+))?)(\s*\[[^\]]+\])?[\.]?([,|;]\s+(.*))?)\s+(?<year>\([1|2][0-9]{3}\))' . '/u',
		
		// Acta Phytotax. Sin. ix. 297 (1964).
		'/^' . '(?<journal>([A-Z][a-z]+\.?\s*)+)\s+(?<volume>([ivxlcIVXLC]+)\.)\s+(?<collation>(?<page>\d+))\s+(?<year>\([1|2][0-9]{3}\))' . '/u',
		
		// Bull. Herb. Boissier ser. 2, 8(5): 326 (1908).
		'/^' . '(?<journal>([A-Z][a-z]+\.?\s*)+(,\s+Bot\.)?\s+ser\.\s+\d+),\s+(?<volume>\d+(\([^\)]+\))?):\s+(?<collation>(?<page>\d+))\s+(?<year>\([1|2][0-9]{3}\))' . '/u',
		
		// Bull. Soc. Roy. Bot. Belgique 35, pt. 1: 261 (1896).
		'/^' . '(?<journal>([A-Z][a-z]+\.?\s*)+)\s+(?<volume>\d+,\s+pt.\s+\d+):\s+(?<collation>(?<page>\d+))\s+(?<year>\([1|2][0-9]{3}\))' . '/u',
		
		// Quart. J. Taiwan Mus. 34(3–4): 145 (1981)
		'/^' . '(?<journal>([A-Z][a-z]*\.?\s*)+)\s+(?<volume>[ivxlcIVXLC0-9]+(\s*\([^\)]+\))?[\.|,|:]?)\s+(?<collation>(?<page>\d+))\s*(?<year>\([1|2][0-9]{3}\))' . '/u',

		// Richardiana 12(1): 3 (fig. 1)
		'/^' . '(?<journal>([A-Z][a-z]*\.?\s*)+)\s+(?<volume>\d+(\s*\([^\)]+\))?[\.|,|:]?)\s+(?<collation>(?<page>\d+)\s*(\(fig.*\)))' . '/u',
		// Richardiana 3(2): 97 (25 March 2003)
		// Richardiana 2(2): 49 (March 2003)
		'/^' . '(?<journal>([A-Z][a-z]*\.?\s*)+)\s+(?<volume>\d+(\s*\([^\)]+\))?[\.|,|:]?)\s+(?<collation>(?<page>\d+)\s*(\((\d+\s+)?([A-Z]\w+\s+)?[0-9]{4}\)))' . '/u',

		// Richardiana 5(4): 198 (-200, 202; fig. 1...)
		'/^' . '(?<journal>([A-Z][a-z]*\.?\s*)+)\s+(?<volume>\d+(\s*\([^\)]+\))?[\.|,|:]?)\s+(?<collation>(?<page>\d+)\s*(\(-\d+.*\)))' . '/u',

		// Richardiana 6(3): 126 (123-129; fig. 3)
		'/^' . '(?<journal>([A-Z][a-z]*\.?\s*)+)\s+(?<volume>\d+(\s*\([^\)]+\))?[\.|,|:]?)\s+(?<collation>(?<page>\d+)\s*(\(\d+-.*\)))' . '/u',
		
		// Trans. Acad. Sci. St. Louis xiv. 190.
		'/^' . '(?<journal>([A-Z][a-z]+\.?\s*)+)\s+(?<volume>([ivxlcIVXLC]+)\.)\s+(?<collation>(?<page>\d+))\s+(?<year>\([1|2][0-9]{3}\))' . '/u',
		
		// articles
		'/^' . $journal_simple . $volume_pattern . $collation_pattern . '/u',
		'/^' . $journal_simple . '[,|:]' . $volume_pattern . $collation_pattern . '/u',
		'/^' . $journal_simple . $volume_pattern . '/u',

		'/^' . $journal_simple . $volume_pattern .  '\s+(?<date>' . $months . ':)' . $collation_pattern . '/u',

		
		// month prefix
		'/^' . '(?<date>\(' . $month_en . '(\s*(\d+))?\)\]?,)\s+' . $journal_simple . $volume_pattern . $collation_pattern . '/u',
		'/^' . '(?<date>\(' . $month_en . '(\s*(\d+))?\)\]?,)\s+' . '(?<author>[I|i]n\s+' . $authors . ',)\s+' . $journal_simple . $volume_pattern . $collation_pattern . '/u',

		// monographs
		'/^' . $journal_simple . '[,|:]' . $collation_pattern . '/u',
		
		// simple pattern
		'/^' . $journal_simple . '/u'
	);
	
	if (0)
	{
		$patterns = array(
			// IPNI
			// Lilloa xxx. 131 (1960).
			'/^' . '(?<journal>[\p{L}]+)' . $volume_roman_pattern . $collation_pattern . '\s+(?<year>\([1|2][0-9]{3}\))' . '/u',
	
		);
	}
	
	if (0)
	{
		print_r($patterns);	
		//exit();
	}
	
	$num_patterns = count($patterns);
	
	if (0)
	{
		// first match wins
		$i = 0;
		while ($i < $num_patterns && !$matched)
		{
			if (preg_match($patterns[$i], $text, $matches, PREG_OFFSET_CAPTURE))
			{
				// store the pattern
				$obj->pattern = $patterns[$i];
			
				// keep the matches
				$obj->matches = $matches;
			
				// score the match			
				$obj->score = round(match_span($text, $matches), 2);	

				$matched = true;
			}
			else
			{
				$i++;
			}
		}
	}
	
	if (1)
	{
		// best match wins, score both length of character match and how many different
		// kinds of tokens we have matched
		$obj->score = 0;
		$obj->pattern = '';
		$obj->match_count = 0;
		
		for ($i = 0; $i < $num_patterns; $i++)
		{
			if (preg_match($patterns[$i], $text, $matches, PREG_OFFSET_CAPTURE))
			{
				$score = round(match_span($text, $matches), 2);	
				
				$match_count = 0;
				foreach ($matches as $k => $v)
				{
					if (is_numeric($k))
					{
					}
					else
					{
						$match_count++;
					}
				}
				
				//echo "pattern $i : score=$score\n";
				
				if ($score > $obj->score && $match_count >  $obj->match_count)
				{
					// store the pattern
					$obj->pattern = $patterns[$i];
			
					// keep the matches
					$obj->matches = $matches;
								
					// score the match			
					$obj->score = round(match_span($text, $matches), 2);	
					$obj->match_count = $match_count;

					$matched = true;					
				}
			}	
		}
	}	
	
	
	/*

	if (!$matched)
	{

		$pattern = '/'
		    . '^'
     		. $journal_pattern 
			. '\s*' 
			. $volume_pattern 
			. '\s*' 
			. '(?<collation>' // "collation pattern includes pages, plates, and figures"
			. $page_pattern 
			. $extra_pattern
			. ')'
			. '$'
			. '/xu';
			
		echo $pattern;
	
		if (preg_match($pattern, $text, $matches, PREG_OFFSET_CAPTURE)) 
		{
			//print_r($matches);
		
			$matched = true;
		}
	}
	*/
		
	if ($matched)
	{		
		// populate object from matching	
		// try to mimic a complete CSL-JSON record as much as possible
		
		$obj->status = 200;	
		
		$data = new stdclass;
		
		foreach ($obj->matches as $tag => $match)
		{
			if (is_numeric($tag))
			{
				// skip
			}
			else
			{
				if ($match[0] != '')
				{
					switch ($tag)
					{
						case 'author':
							$authorstring = preg_replace('/^[I|i]n\s+/', '', $match[0]);
							$authorstring = preg_replace('/,$/', '', $authorstring);
					
							$names = preg_split('/(,\s+|\s+&\s+)/', $authorstring);
					
							foreach ($names as $name)
							{
								$author = new stdclass;
								
								if (preg_match('/[\.|,]/', $name))
								{
									$author->literal = $name;
								}
								else
								{
									$author->family = $name;
								
								}
								$data->author[] = $author;
							}
							break;
					
						case 'year':
							$year = $match[0];
							$year = preg_replace('/[,\(\)]/', '', $year);							
							if (!isset($data->issued))
							{
								$data->issued = new stdclass;
								$data->issued->{'date-parts'} = array();
							}
							$data->issued->{'date-parts'}[0][0] = (Integer)$year;
							break;	
							
							// eat for now
						case 'date':
							break;					
					
						case 'journal':
							$data->{'container-title'} = $match[0];
							break;
							
						case 'volume':
							$value =  $match[0];
							
							if (preg_match('/(\s+(No|pt)\.\s+(?<issue>\d+),?)/', $value, $m))
							{
								$data->issue = $m['issue'];
								$value = str_replace($m[1], '', $value);
							}							
							
							$value = preg_replace('/\.$/', '', $value);
						
							if (preg_match('/^[ivxlc]+$/i', $value))
							{
								$value = arabic($value);
							}
							
							$data->{$tag} = $value;
							break;
							
							// eat as we want to parse the collation
						case 'page':
							break;							
							
						case 'collation':
							$collation = collation_parser($match[0]);
							
							// if we've parsed the collation try and extract the first "main page"
							if (isset($collation->locator))
							{
								// store parsed data
								$data->{$tag} = $collation->locator;
								
								// extract first "significant" page
								$page_name = '';
								foreach ($collation->locator->page as $page)
								{
									if ($page_name == '') //  && !isset($page->comment))
									{
										$page_name = $page->name;
									}
								}
								
								if ($page_name != '')
								{
									$data->page = $page_name;
								}
							}
							else
							{
								// failed to parse, create object so we can still store the text
								$data->{$tag} = new stdclass;
							}
							
							// store 
							$data->{$tag}->value = $match[0];
							break;
							
						
						default:
							$data->{$tag} = $match[0];
							break;				
					}
				}				
			}
		}
		
		// clean object (e.g., remove delimiters)		
		foreach ($data as $k => $v)
		{
			switch ($k)
			{
			
				case 'container-title':
					$data->{$k} = preg_replace('/,$/', '', $data->{$k});
					$data->{$k} = preg_replace('/No\.$/', '', $data->{$k});
					
					// to do: this is a hack, should catch these in regex
					// series
					if (preg_match('/(?<journal>.*)\s+\((?<series>\d+)\)$/', $data->{$k}, $m))
					{
						$data->{$k} = $m['journal'];
						$data->{'collection-title'} = $m['series'];
					}
					break;
					
				case 'volume':
					$data->{$k} = preg_replace('/[,|:]$/', '', $data->{$k});
					
					$clean_matched = false;
					
					if (!$clean_matched)
					{
						if (preg_match('/(?<volume>\d+)\s*\((?<issue>[^\)]+)\)/', $data->{$k}, $m))
						{
							$data->volume = $m['volume'];
							$data->issue = $m['issue'];
							
							$clean_matched = true;
						}					
					}
					
					if (!$clean_matched)
					{
						if (preg_match('/(?<volume>\d+),?\s*no\.?\s*(?<issue>\d+)/i', $data->{$k}, $m))
						{
							$data->volume = $m['volume'];
							$data->issue = $m['issue'];
							
							$clean_matched = true;
						}					
					}
					
					break;
					
				case 'page':
					// if we have a page range we take first page, 
					// we will handle full collation later
					$parts = preg_split('/-/u', $data->{$k});
					if (count($parts) > 1)
					{
						$data->{$k} = $parts[0];
					}
					break;
			
				default:
					break;
			}
		
		}
		
		$obj->data = $data;
		
		// to do: parse the "collation" into pages, plates, figures, etc.
		
		// add tagging
		$obj->xml = match_to_tags($text, $obj->matches);
		
		// cleanup
		unset($obj->matches);
		
	}
	else
	{
		$obj->status = 404;
		$obj->message = "Unable to parse text";
		
	}
	
	if (!$debug)
	{
		if (isset($obj->score)) { unset($obj->score); }
		if (isset($obj->pattern)) { unset($obj->pattern); }
		if (isset($obj->match_count)) { unset($obj->match_count); }
		if (isset($obj->xml)) { unset($obj->xml); }	
	}
		
	return $obj;
}


//
// "tests"

if (0)
{
	$publications = array(
	/*
	'Trans. Linn. Soc. London, Zool., (2) 1, 585.',
	'J. Proc. Linn. Soc. London, Zool., 7 (1864), 61.',
	'Canad. Ent., 9, 70.',
	'Ann. Mag. nat. Hist., (8) 6, 587.',
	'Proc. malac. Soc. London 15, 20',
	'Vertebrata Palasiatica 32(3): 163.',
	'Publs Seto mar. biol. Lab. 7: 256.',
	*/
	//'Bull. Soc. zool. France, 54, 327.',
	//'Bull. Inst. océan. Monaco, No. 575, 13.',

	//'Recu. vétér., 86, fasc. 9, 337.',

	//'Science, (N.S.) 16, no. 402, 434.',
	//'Revta ibér. Parasit. 18: 315.',

	'Mém. Mus. natn. Hist. nat. Paris (N.S.)(A)37: 75, pl. 3, fig. 14.',
	'Mém. Mus. natn. Hist. nat. Paris (N.S.)(A)37: 75.',
	'Mém. Mus. natn. Hist. nat. Paris (N.S.) 37: 75.',

	'Allan. (1929). In: Genetica, 11: 505.',

	'Bull. N.Y. St. Mus. No. 265: 43.',

	'Vertebrata palasiat. 24 (1): 79.',
	'Mems Inst.Butantan 36: 209.',
	'Ann. Mag. nat. Hist., 2 (9), 188.',

	'Neues Jb. Geol. Paläont. Mh. 1959: 424.',

	'Ofvers. Finska Förh., 50, no. 7, 73.',
	);

	$publications = array(

	"S.B. Ges. Morph. Phys., Münich, 31, 37.",
	"Proc. U.S. nat. Mus., 58, 57, 74.",
	"Entomologist, 53, 126.",
	"Mem. Inst. Oswaldo Cruz, 12, 73.",
	"S.B. Ges. Morph. Phys. Münich, 31, 45.",
	"Contr. Sci. Nematology, 9, 321.",
	"Mem. Col., 9, 456.",
	"Ohio J. Sci., 21, 88.",
	"Bull. Soc. nat. Sci. Buffalo, 12, 151.",
	"Zool. Jahrb., Syst., 39, 652.",
	"Tijdschr. Nederl. dierl. Ver., (2) 12, 5.",
	"Insects Samoa 3(Lepid. 2): 80.",

	"Bull.zool.Mus.Univ.Amsterdam 4: 197.",
	"Bull.Mus.natn.Hist.nat.,Paris (Ser.2) 41: 1264.",
	"Bull.Sth.Calif.Acad.Sci. 76: 57.",


	);
	
	
	$publications = array(
	//'Ofvers. Finska Förh., 50, no. 7, 73.',
	//	'Mém. Mus. natn. Hist. nat. Paris (N.S.)(A)37: 75, pl. 3, fig. 14.',
	// 'Insecta kor. 12: 27, 28 [keys], 32, figs 26, 27, 103, pl. C, fig. 17.',
	
	'in Mey, Esperiana (Mem.)6: 202, pl. 1, fig. 6, pl. 6, fig. 32, pl. 32, fig. 4.',
	'Korean J. appl. Ent. 29: 142, figs 3, 11-13.',
	'Samml. eur. Schmett. 8: pl. 45, fig. 312.',
	'Samml. eur. Schmett. 8: 60, pl. 24, fig. 166.',
	
	'in Walsingham & Hampson, Proc. zool. Soc. Lond. 1896: 278.',
	'Mém. Mus. natn. Hist. nat. Paris (N.S.)(A)37: 75, pl. 3, fig. 14.',
	'Proc. zool. Soc. Lond. 1907: 944, pl. 51, fig. 16.',
	);
	
	$publications = array(
//	'Eur. J. Ent. 107: 249 [key], 264, figs 21, 45, 68, 86.',
	'Biol. issled. estest. kultur. ekosist. Primorsk. Kray: 208, figs 3, 16, 17.'
	);

	$publications = array(
	'Eur. J. Ent. 107: 249 [key], 264, figs 21, 45, 68, 86.',
	'Biol. issled. estest. kultur. ekosist. Primorsk. Kray: 208, figs 3, 16, 17.',
	"S.B. Ges. Morph. Phys. Münich, 31, 45.",
	'Mém. Mus. natn. Hist. nat. Paris (N.S.) 37: 75.',
	'Mém. Mus. natn. Hist. nat. Paris (N.S.)(A)37: 75, pl. 3, fig. 14.',
	'Science, (N.S.) 16, no. 402, 434.',
	"Insects Samoa 3(Lepid. 2): 80.",
	'in Walsingham & Hampson, Proc. zool. Soc. Lond. 1896: 278.',
	'Ann. Mag. nat. Hist., (8) 6, 587.',
	"Bull.Mus.natn.Hist.nat.,Paris (Ser.2) 41: 1264.",
	);
	
	
	$publications = array(
	'Science, (N.S.) 16, no. 402, 434.',
	'Ann. Mag. nat. Hist., (8) 6, 587.',
	"Bull.Mus.natn.Hist.nat.,Paris (Ser.2) 41: 1264.",
	'Biol. issled. estest. kultur. ekosist. Primorsk. Kray: 208, figs 3, 16, 17.',
	);
	
	
	$publications = array(
	'Pan-Pacific Ent. 3: 137.',
	'Verh. zool.-bot. Ges. Wien 18(Abh.): 614.',
	'Biologia cent.-am. (Zool.) Lepid.-Heterocera 4: 35.',
	
	'Entomologist\'s mon. Mag. 47: 13.',
	'Arch. Naturgesch. 85(A)(4): 63.',
	'Acta ent. bohemoslovaca 73: 175; 182 [key], figs 1, 2, 6, 8, 9.',
	'(May 9), Proc. U.S. natn. Mus. 25: 853 [key], 865.',
	'In Alluaud & Jeannel, Voyage Alluaud & Jeannel Afr. or. (Lépid.) 2: 71.',
	'Voyage Alluaud & Jeannel Afr. or. (Lépid.) 2: 71.',
	'In Caradja & Meyrick, Dt. ent. Z. Iris 52: 4.',
	'Entomologist\'s Gaz. 49: 40, figs 5, 6, 9, 10, 15, 18, 22-24.',
	'in Powell & Povolný, Holarctic Lepid. 8(Suppl. 1): 6, figs 13, 31.',
	);
	
	
	$publications = array(
	'In Wocke & Staudinger, Stettin. ent. Ztg 23: 236.',
	'In Joannis, Annls Soc. ent. Fr. 98(Suppl.): 724 [486].',
	'In Caradja & Meyrick, Mater. Microlepid. Fauna chin. Provinzen Kiangsu, Chekiang, Hunan: 75.',
	);
	
	$publications = array(
	'in Landry & Roque-Albelo, Revue suisse Zool. 117: 730, figs 23-26, 66, 67, 93.',
	'in Park & Kim, 2016, Oriental Insects 50: 172, figs 1(A-H).',
	'Zootaxa 4059(3): 406-408 [keys], 416, figs 16-18, 61, 87, 114-116.',
	'Verh. zool.-bot. Ges. Wien 57: (213).',
	'Ruwenzori Exped. 1952 2: 97, figs 23, 24, 111-114.',
	'Proc. ent. Soc. Philad. 2: 119; 120 [key].',
	'P?írodov. Pr. ?esk. Akad. V?d. Brn? (N.S.)1: 217, pl. 4, figs 25, 26, pl. 16, fig. 4.',
	'Insecta kor. 11: 2-4 [keys], 19, text-fig. 9, pl. 2, fig. 12.',
	'In Ler (ed.), Opred. Nasekom. dal\'nego Vost. Ross. 5(2): 153.',
	'In Alluaud & Jeannel, Voyage Alluaud & Jeannel Afr. or. (Lépid.) 2: 71.',
	'Comb. rev. ? P?írodov. Pr. ?esk. Akad. V?d. Brn? (N.S.)3(12): 21, pl. 28, fig. 102, pl. 31, fig. 28.',
	'Arch. Naturgesch. 85(A)(4): 63.',
	'(May 9), Proc. U.S. natn. Mus. 25: 855 [key], 871.',
	);

	$publications = array(
//	'in Landry & Roque-Albelo, Revue suisse Zool. 117: 730, figs 23-26, 66, 67, 93.',
	'in Park & Kim, 2016, Oriental Insects 50: 172, figs 1(A-H).',
//	'Breslin, P. B., & Majure. (2021). In: Taxon 70(2): 318.', // fail
	//'Zootaxa 4059(3): 406-408 [keys], 416, figs 16-18, 61, 87, 114-116.',
	
	//'In Alluaud & Jeannel, Voyage Alluaud & Jeannel Afr. or. (Lépid.) 2: 71.',
	//'Voyage Alluaud & Jeannel Afr. or. (Lépid.) 2: 71.',
	//'In Alluaud & Jeannel, Voyage Alluaud and Jeannel Afr. or. (Lépid.) 2: 71.',
	
	'Meyrick (1929) Exotic Microlepidoptera. 3: 4510',
	'Meyrick (1938) Deutsche Entomologische Zeitschrift, Iris. 52: 3003',
	'Mém. Comité Liaison Rech. ecofaun. Jura 12: 80; 109, 110, 112 [keys], pl. 24, figs 1, 2.',
	'Mém. Mus. natn. Hist. nat. Paris (N.S.)(A)37: 74, pl. 5, fig. 4.',
	'P?írodov. Pr. ?esk. Akad. V?d. Brn? (N.S.)7(2): 18.', // encoding errors, doomed
	'Reise öst. Fregatte Novara (Zool.)2(Abt. 2): pl. 138, fig. 43.',
	//'Revta Lepid. 27: 382; 384 [key], figs 5; 24 [as auroalba].',
	
	// bad
		'Ruwenzori Exped. 1952 2: 97, figs 23, 24, 111-114.',
		'Samml. eur. Schmett. 8: pl. 41, fig. 281.',
		'Trav. Mus. Hist. nat. `Gr. Antipa\' 32: 166.',
'Walker (1864) List of the specimens of lepidopterous insects in the collection of the British Museum. (29). Available from https://www.biodiversitylibrary.org/page/38948139: 6357',
'Arch. Naturgesch. 85(A)(4): 63.',
//'Acta ent. bohemoslovaca 73: 175; 182 [key], figs 1, 2, 6, 8, 9.',
'Far Eastern Entomologist (127): 4 [key], 10, figs 3, 4, 29. Acanthophila (A.).',

//'Muelleria 2(1): 21-23',
	);
	
	$publications = array(
//'Ruwenzori Exped. 1952 2: 97, figs 23, 24, 111-114.',
//'Acta ent. bohemoslovaca 73: 175; 182 [key], figs 1, 2, 6, 8, 9.',
//'Reise öst. Fregatte Novara (Zool.)2(Abt. 2): pl. 138, fig. 43.',
'(May 9), Proc. U.S. natn. Mus. 25: 855 [key], 871.',
'(May), Biologia cent.-am. (Zool.) Lepid.-Heterocera 4: 78.',
'(November), Entomologist\'s mon. Mag. 40: 268.',
'(January 13)], in Dyar, Bull. U.S. natn. Mus. 52: 499.',

	
	);
	
	
// NZ, notice the months and sometimes days on some of these, lots to do here...	
	$publications = array(

'Journal of Natural History 28(2), March-April: 480.',
'Acarologia (Paris) 34(4), Octobre: 289.',
'Atalanta (Markleuthen) 24(1-4), Juli: 231.',
'Bulletin de la Societe Entomologique de France 97(4), octobre: 333.',
'Courier Forschungsinstitut Senckenberg 155, 1 Marz: 173.',
'Canadian Journal of Earth Sciences 30(10-11), October-November: 2129.',
'Bulletin of the National Science Museum Series A (Zoology) 19(3), September 22: 118.',
'The generic names of moths of the world. Vol.1. Noctuoidea (part): Noctuidae, Agaristidae, and Nolidae. Publs Br.Mus.nat.Hist.,London No.770: 432.',
	);
	
 $publications = array( 
  'Ent. scand. 27: 129 [keys], 146, figs 9, 31, 52, 53, 64, 80.',
  );
  
  /*
 $publications = array( 
  //'Ann. Mag. nat. Hist. (6), 12: 183',
  //'Ann. Mag. nat. Hist., (6) 12, 183.',
  'Ann. Mag. nat. Hist (6), 14:138',
  'Ann. Mag. nat. Hist. (9), 4, 151.',
  );
  */
  
	foreach ($publications as $text)
	{
		$obj = parse($text);
		
		print_r($obj);
	}  
  
}

if (0)
{
  
 $publications = array( 
  //'Proc. Zool. Soc. London, 7 (1839), p. 144',
  //'Novit. Zool., 10, p. 448',
  //'Acarologia (Paris) 34(4), Octobre: 289.',
  
  //'Lilloa xxx. 131 (1960).',
  //'Mem. New York Bot. Gard., ix. 287 (1957).',
  'Mem. New York Bot. Gard., x. No. 5, 40 (1964).',
  );  

	foreach ($publications as $text)
	{
		$obj = parse($text);
		
		print_r($obj);
	}
}

if (0)
{
  
 $publications = array( 
	'Proneca fola Swinhoe, 1890, Trans. ent. Soc. London, 1890: 194.',
	'Trans. ent. Soc. London, 1890: 194.',
	'Entomotaxonomia 15: 209 [key], 214, 218, figs 7A-D.',
  );  

	foreach ($publications as $text)
	{
		$obj = parse($text);
		
		print_r($obj);
	}
}

if (0)
{
  
 $publications = array( 
	//'Ann. Mag. nat. Hist. (8). 16: 178.',
	'Cat. Eastern & Australian lepid. Heterocera colln Oxford Univ. Mus., 1: 264',
  );  

	foreach ($publications as $text)
	{
		$obj = parse($text, true);
		
		print_r($obj);
	}
}


?>
