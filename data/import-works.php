<?php

// Create a SQLite database from a sets of titles and ISSNs

require_once(dirname(__FILE__) . '/sqlite.php');

$pdo     = new PDO($config['database']); 	// name of SQLite database (a file on disk)
$basedir = dirname(__FILE__) . '/works';

$files = scandir($basedir);

$files=array('extra.tsv');

foreach ($files as $filename)
{
	if (preg_match('/\.tsv$/', $filename))
	{	
		import_csv_to_sqlite($pdo, $basedir . '/' . $filename, $options = array('delimiter' => "\t", 'table' => 'works'));
	}
}


/*

BioNames

SELECT DISTINCT 
IFNULL(issn,'') AS issn,
IFNULL(series,'') AS series,
IFNULL(`volume`,'') AS `volume`,
IFNULL(`issue`,'') AS `issue`,
IFNULL(spage,'') AS spage,
IFNULL(epage,'') AS epage,
IFNULL(year,'') AS year,
IFNULL(LOWER(doi),'') AS doi
FROM names 
WHERE issn IS NOT NULL 
AND volume IS NOT NULL 
AND spage IS NOT NULL 
AND doi IS NOT NULL 
AND issn="0004-959X";

===============

Microcitations mysql

SELECT DISTINCT 
IFNULL(issn,'') AS issn,
IFNULL(series,'') AS series,
IFNULL(`volume`,'') AS `volume`,
IFNULL(`issue`,'') AS `issue`,
IFNULL(spage,'') AS spage,
IFNULL(epage,'') AS epage,
IFNULL(year,'') AS year,
IFNULL(LOWER(doi),'') AS doi
FROM publications 
WHERE issn IS NOT NULL 
AND volume IS NOT NULL 
AND spage IS NOT NULL 
AND doi IS NOT NULL;

SELECT DISTINCT 
IFNULL(issn,'') AS issn,
IFNULL(series,'') AS series,
IFNULL(`volume`,'') AS `volume`,
IFNULL(`issue`,'') AS `issue`,
IFNULL(spage,'') AS spage,
IFNULL(epage,'') AS epage,
IFNULL(year,'') AS year,
IFNULL(LOWER(doi),'') AS doi
FROM publications_eperiodica 
WHERE issn IS NOT NULL 
AND volume IS NOT NULL 
AND spage IS NOT NULL 
AND doi IS NOT NULL;

SELECT DISTINCT 
IFNULL(issn,'') AS issn,
IFNULL(series,'') AS series,
IFNULL(`volume`,'') AS `volume`,
IFNULL(`issue`,'') AS `issue`,
IFNULL(spage,'') AS spage,
IFNULL(epage,'') AS epage,
IFNULL(year,'') AS year,
IFNULL(LOWER(doi),'') AS doi
FROM publications_tmp 
WHERE issn IS NOT NULL 
AND volume IS NOT NULL 
AND spage IS NOT NULL 
AND doi IS NOT NULL;


===============

SQLite publications, publications_doi

SELECT DISTINCT 
IFNULL(issn,'') AS issn,
IFNULL(series,'') AS series,
IFNULL(`volume`,'') AS `volume`,
IFNULL(`issue`,'') AS `issue`,
IFNULL(spage,'') AS spage,
IFNULL(epage,'') AS epage,
IFNULL(year,'') AS year,
IFNULL(LOWER(doi),'') AS doi
FROM publications_doi 
WHERE issn IS NOT NULL 
AND volume IS NOT NULL 
AND spage IS NOT NULL 
AND doi IS NOT NULL;

SELECT DISTINCT 
IFNULL(issn,'') AS issn,
IFNULL(series,'') AS series,
IFNULL(`volume`,'') AS `volume`,
IFNULL(`issue`,'') AS `issue`,
IFNULL(spage,'') AS spage,
IFNULL(epage,'') AS epage,
IFNULL(year,'') AS year,
IFNULL(LOWER(doi),'') AS doi
FROM publications 
WHERE issn IS NOT NULL 
AND volume IS NOT NULL 
AND spage IS NOT NULL 
AND doi IS NOT NULL;



*/


?>
