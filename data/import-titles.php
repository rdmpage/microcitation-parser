<?php

// Create a SQLite database from list of a BHL titles

require_once(dirname(__FILE__) . '/sqlite.php');

$pdo 		= new PDO($config['database']); 	// name of SQLite database (a file on disk)

$csv_path 	= "title.tsv"; 			// name of csv file to import

import_csv_to_sqlite($pdo, $csv_path, $options = array('delimiter' => "\t"));

?>
