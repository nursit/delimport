<?php
/**
 * 
 * Import Public bookmarks from http://delicious.com/xxxx
 * into a tmp/bookmarks.html file that can be used
 * to reimport in browser or new delicious account
 * or any bookmark web service
 *
 * Put this file in directory squelettes/action/delimport.php
 * of your SPIP installation
 * Change xxxx to the name of the delicious user to import in following line
 * $base = "http://delicious.com/xxxx";
 *
 * then launch /spip.php?action=delimport from your SPIP site
 * in case of timeout, relaunch until "End" is displayed
 *
 * The result is in the file tmp/bookmarks.html
 *
 */

include_spip('inc/distant');
include_spip('inc/filtres');

function action_delimport_dist(){

	// CHANGE xxxx to your delicious User Name
	$base = "http://delicious.com/xxxx";


	$c = recuperer_page_cache($base);
	$count = 0;
	if (preg_match(",<div class=\"left linkCount\">(\d+),ims",$c,$m))
		$count = intval($m[1]);

	echo "<h1>$count links</h1>";

	$maxiter = 200;
	$bookmarks = array();
	$url = $base;
	$page = 1;
	do {
		#var_dump($url);
		$c = recuperer_page_cache($url);
		$links = importer_links($c);
		$bookmarks = array_merge($bookmarks,$links);
		$page++;
		$url = parametre_url($base,'page',$page);
	}
	while (count($links)
	  AND count($bookmarks)<$count
		AND $maxiter--);

	var_dump(count($bookmarks));

	$out = exporter_links($bookmarks);
	ecrire_fichier(_DIR_TMP."bookmarks.html",$out);
	echo "End";
}

function exporter_links($bookmarks){
	$out = <<<html
<!DOCTYPE NETSCAPE-Bookmark-file-1>
<META HTTP-EQUIV="Content-Type" CONTENT="text/html; charset=UTF-8">
<!-- This is an automatically generated file.
It will be read and overwritten.
Do Not Edit! -->
<TITLE>Bookmarks</TITLE>
<H1>Bookmarks</H1>
<DL>
html;
	foreach($bookmarks as $b){
		$out .= '<DT><A HREF="'
		  . $b['url']
		  . '" ADD_DATE="'
		  . (strtotime($b['date'])+12*3600)
		  . '" PRIVATE="0" TAGS="'
		  . implode(',',$b['tags'])
		  . '">'
		  . $b['title']
		  . '</A>'
		  . "\n";
		$out .= "<DD>".$b['descriptif']."\n";
	}

	$out .= "</DL>";
	return $out;
}

function importer_links($page){

	$ps = explode('<li id="item-',$page);
	array_shift($ps); // le premier ne nous interesse pas
	$links = array();
	foreach ($ps as $p){
		$link = array();
		if (preg_match(',<div class="date">([^<]*)<,Uims',$p,$m)){
			$link['date'] = $m[1];
			$link['date'] = date('Y-m-d',strtotime($link['date']));
		}
		$h4 = extraire_balise($p,'h4');
		$a = extraire_balise($h4,'a');
		$link['url'] = extraire_attribut($a,'href');
		$link['title'] = trim(strip_tags($a));

		$p = explode('<ul class="tag-chain">',$p);
		$notes = reset($p);
		$p = end($p);

		if (preg_match(',<div class="notes">(.*)</div>,Uims',$notes,$m)){
			#var_dump($m[1]);
			$link['descriptif'] = nettoyer_notes($m[1]);
			#var_dump($link['descriptif']);
		}

		$p = explode("</ul>",$p);
		$p = reset($p);
		$tags = extraire_balises($p,'a');
		foreach($tags as $tag)
			$link['tags'][] = trim(strip_tags($tag));
		$links[] = $link;
	}
	return $links;
}

function nettoyer_notes($notes){
	$notes = str_replace(array("\n","\r"),"",$notes);
	$notes = str_replace("</p>","\n\n",$notes);
	$notes = str_replace("<br />","\n",$notes);
	$notes = trim(strip_tags($notes));
	return $notes;
}

function recuperer_page_cache($url){
	$dir = sous_repertoire(_DIR_CACHE,"recupererpage");
	$name = $dir . "f".md5($url)."html";

	if (!file_exists($name)){
		recuperer_page($url,$name);
	}
	lire_fichier($name,$c);
	return $c;
}