<?php

// BHL

require_once (dirname(__FILE__) . '/api_utilities.php');
require_once (dirname(dirname(__FILE__)) . '/data/sqlite.php');
require_once (dirname(__FILE__) . '/compare.php');


//----------------------------------------------------------------------------------------
// Given a structured citation with container-title, volume, and page, can we match to
// one or more BHL pages using BHL's OpenURL query?
function find_bhl_page($obj, $debug = false)
{
	if (isset($obj->data))
	{

		if (
			isset($obj->data->BHLTITLEID)
			&& isset($obj->data->volume)
			&& isset($obj->data->page)
		)
		{

			$parameters = array(	
				'volume' 	=> $obj->data->volume,
				'spage'		=> $obj->data->page,
				'pid'		=> 'title:' . $obj->data->BHLTITLEID[0],
				'format' 	=> 'json'
			);
	
			$url = 'https://www.biodiversitylibrary.org/openurl?';
		
			$url .= http_build_query($parameters);
			
			if ($debug)
			{
				$obj->openurl = $url;
			}
		
			$json = get($url);
		
			$response = json_decode($json);
		
			if ($response)
			{
				foreach ($response->citations as $citation)
				{			
					if (!isset($obj->data->BHLPAGEID))
					{
						$obj->data->BHLPAGEID = array();
					}
					$obj->data->BHLPAGEID[] = str_replace('https://www.biodiversitylibrary.org/page/', '', $citation->Url);
				}
			}
		}
	}
	
	return $obj;
}

//----------------------------------------------------------------------------------------
// get BHL text from BHL
function get_bhl_page_text($pageid)
{	
	global $config;
	
	$text = '';

	$parameters = array(
		'op' 		=> 'GetPageMetadata',
		'pageid'	=> $pageid,
		'ocr'		=> 't',
		'names'		=> 't',
		'apikey'	=> $config['BHL_API_KEY'],
		'format'	=> 'json'
	);

	$url = 'https://www.biodiversitylibrary.org/api2/httpquery.ashx?' . http_build_query($parameters);	

	$json = get($url);
	
	$obj = json_decode($json);
	
	if (isset($obj->Result->OcrText))
	{
		$text = $obj->Result->OcrText;
		
		// remove double lines
		$text = preg_replace('/\n\n/', "\n", $text);
	}

	return $text;
}

//----------------------------------------------------------------------------------------
// Do we have this BHL title match in our local file?
function match_title_direct($text)
{
	global $config;
	
	$result = array();
	
	$json = file_get_contents($config['bhl_title_cache']);
	
	$obj = json_decode($json);
	if ($obj)
	{
		if (isset($obj->{$text}))
		{
			$result = $obj->{$text};
		}
	}

	return $result;
}

//----------------------------------------------------------------------------------------
// Store new title match in local file
function update_match_title_file($text, $titles)
{
	global $config;
	
	// only write to this file if we are developing on local machine
	if ($config['local'])
	{	
		$json = file_get_contents($config['bhl_title_cache']);
	
		$obj = json_decode($json);
		if ($obj)
		{
			if (!isset($obj->{$text}))
			{
				$obj->{$text} = $titles;
			}
		
			file_put_contents($config['bhl_title_cache'], json_encode($obj, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
		}
	}
}

	
//----------------------------------------------------------------------------------------
function match_bhl_title($text, $debug = false)
{
	$TitleIDs = array();
	
	// try direct lookup using our local file
	$TitleIDs = match_title_direct($text);
	
	if (count($TitleIDs) == 0)
	{
		// try database
		
		// try approx match
		
		//$like_title = preg_replace('/(\.\s+)/', '% ', $text);
		if (0)
		{
			$like_title = preg_replace('/\./', '% ', $text);
			$like_title = preg_replace('/\.([A-Z])/', '% $1', $like_title);
			$like_title = preg_replace('/\.\)/', '%)', $like_title);
			$like_title = preg_replace('/\.$/', '%', $like_title);
		}
		else
		{
			$like_title = preg_replace('/,/', ' ', $text);
			$like_title = preg_replace('/\./', ' ', $like_title);
			$like_title = trim($like_title);
			$like_title = preg_replace('/\s+/', '% ', $like_title);	
			$like_title .= '%'; 	
		}
		$like_title = preg_replace('/’/u', "'", $like_title);
		$like_title = '%' . $like_title; 
		
		// fix some abbreviations (can we do this more cleverly?)
		$like_title = preg_replace('/Annls/i', "Annals", $like_title);
		$like_title = preg_replace('/natn/i', "National", $like_title);
		$like_title = preg_replace('/Ztg/i', "Zeitung", $like_title);
		
		// echo $title;
		
		$sql = 'SELECT DISTINCT titleid, fulltitle as title FROM title WHERE fulltitle LIKE "' . addcslashes($like_title, '"') . '" COLLATE NOCASE;';
		$sql = 'SELECT DISTINCT titleid, shorttitle as title FROM title WHERE shorttitle LIKE "' . addcslashes($like_title, '"') . '" COLLATE NOCASE;';

		if ($debug)
		{
			echo "\n$sql\n";
		}
	
		$query_result = do_query($sql);
		
		// get best match...
		$max_score = 0;
				
		foreach ($query_result as $row)
		{		
			$result = compare_common_subsequence(
				$text, 
				$row->title,
				false);

			if ($result->normalised[1] > 0.95)
			{
				// one string is almost an exact substring of the other
				if ($result->normalised[1] > $max_score)
				{
					$max_score = $result->normalised[1];
					$TitleIDs = array((Integer)$row->titleid);
				}
			}
		}
		
		if (count($TitleIDs) > 0)
		{
			update_match_title_file($text, $TitleIDs);
		}

	}
	
	
	return $TitleIDs;
}

//----------------------------------------------------------------------------------------

if (0)
{
$strings = array(
'Tijdschr. Ent.',
'Proc. zool. Soc. Lond.',
'Novit. zool.',
'Sarawak Mus. J.',
'Cat. Lepid. Phalaenae Br. Mus.',
'Gross-Schmett. Erde',
'Cat. lepid. Phalaenae Br. Mus. (Suppl.)',
'J. Linn. Soc. (Zool.)',
'Entomologist',
'Amoenitates Academicae',
'List Specimens lepid. Insects Colln Br. Mus.',
'Cat. Lepid. Insects Mus. E. Ind. Co.',
'Ann. Mag. nat. Hist.',
'Arch. Naturgesch.',
//'J. Coll. Agric. Hokkaido Imp. Univ.',
'Transactions of the Entomological Society of London',
'Schmett. Eur.',
//'Schmett. Dtl. Schweiz',
'Mitt. schweiz. ent. Ges.',
'Lepid. Cat.',
'Trans. Am. ent. Soc.',
'Canadian Entomologist',
'Notul. ent.',
"Entomologist’s Monthly Magazine",
'Mitt. zool. Mus. Berl.',
'Journal of the Bombay Natural History Society',
'Dt. ent. Z. Iris',
'Proc. ent. Soc. Philad.',
'Proc. Linn. Soc. N.S.W.',
'Wien. ent. Mschr.',
'Entomologica am.',
'Isis, Leipzig',
'Mem. sth. Calif. Acad. Sci.',
'Bull. sth. Calif. Acad. Sci.',
'Horae Soc. ent. ross.',
'Ann. S. Afr. Mus.',
'J. Dep. Agric. P. Rico',
'Bull. Soc. ent. Fr.',
'Cat. Lepid. palaearct. Faunengeb.',
'Samml. eur. Schmett.',
'Biologia cent.-am. (Zool.) Lepid.-Heterocera',
'Trudy russk. ent. Obshch.',
'Transactions of the Royal Society of South Australia',
'Lepid. Cat.',
'Anz. Akad. Wiss. Wien',
'Hist. nat. Lépid. Papillons Fr. (Suppl.)',
'Verz. bekannter Schmett',
//'Zoogeographica',
//'Märk. Ent. Nachr.',
'Ent. Tidskr.',
'Annls Soc. ent. Fr.',
//'Bull. Dep. Agric. Calif.',
'Verz. bekannter Schmett.',
'Stettin. ent. Ztg',
//'Bull. Soc. Fouad I. Ent.',
//'Ent. Obozr.',
//'Trans. R. ent. Soc. Lond.',
'Bull. U.S. natn. Mus.',
//'Acta ent. bohemoslovaca',
'Genera Insect.',
'Berl. ent. Z.',
//'Suppl. Butterfl. Moths N. Z.',
'Trans. Proc. N.Z. Inst.',
//'Lepid. Microptera quae J.A. Wahlberg in Caffrorum Terra collegit',
//'Nasekom. Mongol.',
//'Reichenbachia',
'Bull. U.S. geol. geogr. Surv. Territ.',
'Proc. U.S. natn. Mus.',
'Reise öst. Fregatte Novara (Zool.)',
'Samml. eur. Schmett.',
'List Specimens lepid. Insects Colln Br. Mus.',
'Cincinn. Q. Jl Sci.',
'Proc. ent. Soc. Wash.',
'Ent. News',
'Biologia cent.-am. (Zool.) Lepid.-Heterocera',
//'Trudy vses. ent. Obshch.',
'Lepid. Br.',
'Zool. Rec.',
'Isis, Leipzig',
'Fragm. ent.',
//'Z. öst. EntVer',
'Zoologist synonymic List Br. Butterfl. Moths',
'Encycl. Hist. nat. (Papillons nocturnes)',
'Rec. Indian Mus.',
'Verh. K. ned. Akad. Wet.',
'Exotic Microlepidoptera',
'Ark. Zool.',
'Br. Ent.',
'Physis, B. Aires',

// if
'Syll. fung. (Abellini)',
'Anal. Soc. cient. argent.',
'Malpighia',
'Revista Chilena Hist. Nat.',
'Unters. Gesammtgeb. Mykol. (Liepzig)',

// ipni
'Abh. Königl. Ges. Wiss. Göttingen',
'Adansonia',
'Agric. Prat. Pays Chauds',
'Anales Mus. Nac. Montevideo',
'Ann. Carnegie Mus.',
'Ann. Inst. Bot.-Geol. Colon. Marseille',
'Ann. Jard. Bot. Buitenzorg',
'Ann. K. K. Naturhist. Hofmus.',
'Ann. Mus. Bot. Lugduno-Batavi',
'Ann. Nat. Hofmus. Wien.',
'Ann. Sci. Nat., Bot.',
'Ark. Bot.',
'Arkiv Bot., Stockh.',
'Arnaldoa',
'Austral. Orchid Rev.',
'Beih. Bot. Centralbl.',
'Bot. Jahrb. Syst.',
'Bot. Mag.',
'Bot. Mag. (Tokyo)',
'Bot. Zeitung (Berlin)',
'Bothalia',
'Brit. Fern Gaz.',
'Bull. Brit. Mus. (Nat. Hist.), Bot.',
'Bull. Herb. Boissier',
'Bull. Jard. Bot. Buitenzorg',
'Bull. Mens. Soc. Linn. Paris',
'Bull. Mus. Hist. Nat. (Paris)',
'Bull. Mus. Natl. Hist. Nat.',
'Bull. Mus. Natl. Hist. Nat., B, Adansonia',
'Bull. Nat. Hist. Mus. London, Bot.',
'Bull. New York Bot. Gard.',
'Bull. Soc. Bot. France',
'Bull. Soc. Bot. Genève',
'Bull. Soc. Imp. Naturalistes Moscou',
'Contr. Gray Herb.',
'Contr. W. Bot.',
'Edinburgh Philos. J.',
'Enum. Pl. Zeyl. [Thwaites]',
'Fern Gaz. (U.K.)',
'Fl. Aegypt.-Arab.',
'Fl. Bras. (Martius)',
'Fl. Bras. Merid. (A. St.-Hil.).',
'Fl. Madagasc.',
'Fl. Males., Ser. 1, Spermat.',
'Fl. Trop. Afr. [Oliver et al.]',
'Fl. Yunnan.',
'Gard. Bull. Singapore',
'Hooker\'s Icon. Pl.',
'Icon. Pl. Formosan.',
'J. Arnold Arbor.',
'J. Asiat. Soc. Bengal, Pt. 2, Nat. Hist.',
'J. Bot.',
'J. Roy. Soc. Western Australia',
'J. S. African Bot.',
'J. Straits Branch Roy. Asiat. Soc.',
'J. Wash. Acad. Sci.',
'Kongl. Vetensk.-Akad. Handl.',
'Leafl. Bot. Observ. Crit.',
'Leafl. Philipp. Bot.',
'Les Bambusees',
'Linnaea',
'Mem. New York Bot. Gard.',
'Methodus (Moench)',
'Moscosoa',
'N. Amer. Fl.',
'Nachtr. Fl. Schutzgeb. Südsee [Schumann & Lauterbach]',
'Notul. Syst. (Paris)',
'Nova Acta Acad. Caes. Leop.-Carol. German. Nat. Cur.',
'Nova Guinea',
'Novon',
'Oesterr. Bot. Z.',
'Orchidaceae (Ames)',
'Pflanzenr. (Engler)',
'Philipp. J. Sci.',
'Philipp. J. Sci., C',
'Phytogeogr. & Fl. Arfak Mts.',
'Phytologia',
'Proc. Biol. Soc. Washington',
'Proc. Linn. Soc.',
'Proc. Roy. Soc. Queensland',
'Prodr. (DC.)',
'Repert. Spec. Nov. Regni Veg. Beih.',
'Revis. Gen. Pl.',
'Rhodora',
'Richardiana',
'Sendtnera',
'Sida',
'Telopea',
'Tent. Fl. Abyss.',
'The Victorian Naturalist',
'Traité Gén. Conif.',
'Trans. & Proc. Roy. Soc. S. Austral.',
'Trans. & Proc. Roy. Soc. South Australia',
'Trans. Phil. Inst. Vict.',
'Trans. S. African Philos. Soc.',
'Trudy Imp. S.-Peterburgsk. Bot. Sada',
'Utafiti',
'Verh. Bot. Vereins Prov. Brandenburg',
'Verh. Zool.-Bot. Ges. Wien',
'Wiss. Ergebn. Schwed. Rhodesia-Kongo-Exped. 1911-1912',
'Wiss. Ergebn. Zweit. Deut. Zentr.-Afr. Exped. (1910-11), Bot.',
'Wrightia',
//'Bol. Mus. Paraense "Emilio Goeldi," N.S., Bot.',
'in Denkschr. Akad. Wien, Math. Nat.',
'in Engl. Pflanzenw. Afr.',

);

$strings=array(

'Ann S Afr Mus',
'Ann. Mag. nat. Hist., (4)',
'Ann. Mag. nat. Hist., (8)',
'Ann. Mus. Stor. nat. Genova',
'Ann. S. Afr. Mus.',
'Ann. S. Afric. Mus.',
'Ann. S. African Mus.',
'Ann. S.-Afr. Mus.',
'Ann. Soc. ent. Belg.',
'Ann. Soc. ent. Belgique',
'Ann. Soc. ent. France',
'Ann. Soc. ent., Belgique',
'Ann.S.Afr,Mus.',
'Ann.S.Afr.Mus.',
'Boll. Soc. Portug. Sci. nat.',
'Bull. Soc. Portugaise Sci. nat.',
'Bull. Soc. ent. France',
'Dtsch. ent. NatBibl.',
'Dtsch. ent. Nation. Bibl.',
'Dtsch. ent. Nation.-Bibl.',
'Dtsch. ent. Nationbibl.',
'Dtsch. ent. Z., Lep.',
'Ent. Mon. Mag.',
'Ent. mon. Mag.',
'Entomologist',
'Exot. Micr.',
'Exot. Microlep',
'Exot. Microlep.',
'Exot. microlep.',
'Exot., Microlep.',
'Gen. Ins.',
'Gen. Insect.',
'Genera Insect.',
'Gross-Schmett. Erde',
'Gross-Schmett. Erde, Noctuidae',
'Gross-Schmett. Erde, Suppl.',
'Gross-Schmett.Erde',
'Gross-schmett. Erde',
'Gross-schmett. der Erde (German Edn.) Stuttgart. Fauna Palaearct. Suppl.',
'Gross-schmett. der Erde (German edn.) Stutt-gart. Fauna Palaearct. Suppl.',
'Gross-schmett. der Erde (German edn.) Stuttgart. Fauna Palaearct. Suppl.',
'Gross-schmett. der Erde (German edn.) Stuttgart. Fauna Palaearct., Suppl.',
'Gross-schmett. der Erde, (German edn.) Stuttgart. Fauna Palaearct. Suppl.',
'Gross-schmett. derErde (German edn.) Stuttgart. Fauna Palaearct. Suppl.',
'Grosschmett der Erde',
'J Bombay Nat Hist Soc',
'J. Bombay Soc. nat. Hist.',
'J. Bombay nat. Hist. Soc.',
'J. Bombay nat. Hist., Soc.',
'J. Bombay nat.Hist.Soc.',
'J. Linn. Soc. London, Zool.',
'J. Sarawak Mus.',
'J.Bombay nat.Hist.Soc.',
'Jahrb. Ver. Nassau',
'Jahrb. Ver. Nat. Nassau',
'Jahrb. Ver. Naturk. Nassau',
'Journal Bombay nat. Hist. Soc.',
'Journal of the Bombay Natural History Society',
'Novit zool.',
'Novit, zool.',
'Novit. Zool.',
'Novit. zool',
'Novit. zool.',
'Philipp. J. Sci., (D)',
'Philippine J. Sci.',
'Proc Biol Soc Wash',
'Proc Linn Soc N S W',
'Proc. Acad. nat. Sci. Philad.',
'Proc. Acad. nat. Sci. Philadelphia',
'Proc. Biol. Soc. Wash.',
'Proc. Linn, Soc. N.S. Wales',
'Proc. Linn. Soc, N.S. Wales',
'Proc. Linn. Soc. N. S. W.',
'Proc. Linn. Soc. N. S. Wales',
'Proc. Linn. Soc. N. S. Wales, (2)',
'Proc. Linn. Soc. N.S. Wales',
'Proc. Linn. Soc. N.S. Wales, (1)',
'Proc. Linn. Soc. N.S. Wales, (2)',
'Proc. Linn. Soc. N.S.W.',
'Proc. Linn. Soc. N.S.Wales',
'Proc. Linn. Soc. W.S. Wales',
'Proc. Zool. Soc. London',
'Proc. biol. Soc. Wash.',
'Proc. biol. Soc. Washington',
'Proc. biol. Soc. Washington.',
'Proc. biol. Soc., Wash',
'Proc. biol. soc. Wash.',
'Proc. linn. Soc. N.S.W.',
'Proc. zool Soc. London',
'Proc. zool. Soc, London',
'Proc. zool. Soc. Lond.',
'Proc. zool. Soc. Lond., pt.',
'Proc. zool. Soc. London',
'Proc. zool. soc. London',
'Proc. zool., Soc. London',
'Proc.Linn.Soc.N.S.W.',
'Proc.Linn.Soc.N.S.Wales',
'Proc.Linn.Soc.N.S.w.',
'Proc.biol.Soc,Wash.',
'Proc.biol.Soc.Wash',
'Proc.biol.Soc.Wash.',
'Quaest Entomol',
'Quaest.ent.',
'Quaestiones ent.',
'Revue Suisse de Zoologie',
'Revue suisse Zool.',
'Trans. ent. Soc. London',
);



$failed = array();


foreach ($strings as $string)
{
	$result = match_bhl_title($string);
	
	echo $string . ' ' . json_encode($result) . "\n";
	
	if (count($result) == 0)
	{
		$failed[] = $string;
	}
}

print_r($failed);

}




?>
