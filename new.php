<?php
/*
  This license is similar to zlb license found at:
    http://www.gzip.org/zlib/zlib_license.html


  Copyright (C) The PasteBox developer - http://pastebox.sf.net/

  This software is provided 'as-is', without any express or implied
  warranty.  In no event will the authors be held liable for any damages
  arising from the use of this software.

  Permission is granted to anyone to use this software for any purpose,
  including commercial applications, and to alter it and redistribute it
  freely, subject to the following restrictions:

  1. The origin of this software must not be misrepresented; you must not
     claim that you wrote the original software. If you use this software
     in a product, an acknowledgment in the product documentation would be
     appreciated but is not required.
  2. Altered source versions must be plainly marked as such, and must not be
     misrepresented as being the original software.
  3. This notice may not be removed or altered from any source distribution.
*/
error_reporting(E_ALL);
$ver = (int)implode('',explode('.', PHP_VERSION ));
if ( $ver < 520 )
{
	die('This script is dependent on PHP >= 5.2. If you are willing to test this anyway, please comment the line '.__LINE__.' in file '.__FILE__.'.');
}
if ( get_magic_quotes_gpc() == 1 )
{
	die('Magic quotes are on. Please add \'php_flag magic_quotes_gpc off\' to your .htaccess configuration or update your php.ini.');
}
if ( ini_get("register_globals") )
{
	die('register_globals is on. Please add \'php_flag register_globals off\' to your .htaccess configuration or update your php.ini.');
}
if ( !isset($_SERVER['SCRIPT_NAME']) )
{
	die('$'.'_SERVER[\'SCRIPT_NAME\'] was not set. Assuming that not called from a web server - diying.');
}
ini_set('display_errors','On');
error_reporting(E_ALL);

ini_set('display_errors','Off');
error_reporting(E_NONE);

$config = array();
$lang = array();
$config['captcha'] = true;
$config['language'] = NULL; // Language stuffs? 
$config['language'] = 'en';
$config['geshi'] = true; // Use geshi? If not, just dump nicely into HTML
$config['idlen'] = 13;// Length of the IDs 
$config['showen'] = 0; // How many will be shown on the index page - 0 to show none
// The allowed types
$config['types'] = array('txt'=>'Plain Text','winbatch'=>'Batch File','c'=>'C','cpp'=>'C++','css'=>'CSS','diff'=>'Diff','dos'=>'DOS','html'=>'HTML','ini'=>'INI','java'=>'Java','javascript'=>'JavaScript','lua'=>'Lua','mirc'=>'mIRC','mysql'=>'MySQL','perl'=>'Perl','php' => 'PHP','python'=>'Python','ruby'=>'Ruby','xml'=>'XML');
$config['keepen'] = 20; // How many entries are kept after an update - 0 to keep every entry (not recommended for large sites)
$config['age'] = 0; // And for how long; but ignored if the above is used - 0 to keep forever; otherwise in seconds

/* LANGUAGE DATA */
function _l($text)
{
	global $lang;
	global $config;
	$ln = $config['language'];
	if (!$ln) { return $text; }
	if ( !isset($lang[$ln]) || (isset($lang[$ln]) && count($lang[$ln]) != count($lang['keys']) ) ) { return '<span style="color:red">Something doesn\'t match.</span>'; }
	$id = array_search($text,$lang['keys']);
	if ( $id === false ) { return '<span style="color:red">Key not found: \''.htmlspecialchars($text).'\'</span>'; }
	return $lang[$ln][$id];
}

$lang['keys'] = array('captcha','Post content', 'title','timeformat','PasteBox','New entry', 'Subject: ', 'Content: ', 'Submit', 'Text version', 'Type: ');
$lang['en'] = array('Press the second button to post: ','Post content', 'PasteBox','r','PasteBox','New entry', 'Subject: ', 'Content: ', 'Submit', 'Text version', 'Type: ');
$lang['de'] = array('Drücken Sie die zweite Taste, um: ','Post den Inhalt', 'PasteBox','r','PasteBox','Neuer Eintrag', 'Titel: ', 'Inhalt: ', 'Gehen', 'Nur Text', 'Typ: ');
$lang['pt'] = array('Pressione o segundo botão para enviar: ', 'Publique o conteúdo', 'PasteBox','r','PasteBox','Novada entrada', 'Assunto: ', 'Conteúdo: ', 'Enviar', 'Texto Plano', 'Tipo: ');

/* START OF FUNCTIONS */
function entry_var_get($entry,$source,$name,$required,$default = false)
{
	$d = var_get($source,$name,$required,$default);
	if ($d === false) { $entry->valid = false; }
	$entry->{$name} = $d;
	return true;
}
function var_get($source, $name, $required, $default = false)
{
	if ( isset($source[$name]) ) { return $source[$name]; }
	if ( $required) { return false; }
	else { return $default; }
}
// Here we take in the user data
function entry_input($config, $entry)
{
	entry_var_get($entry,$_POST,'content',true);
	//var_dump($entry->valid);
	entry_var_get($entry,$_POST,'subject',false,'Untitled');
	if (preg_replace('/\s/','',(string)$entry->subject) == '') { $entry->subject='Untitled'; }
	//var_dump($entry->valid);
	entry_var_get($entry,$_POST,'type',false,'txt');
	// Make sure it's available
	if ( !in_array($entry->type, array_keys($config['types'])) ) { $entry->type = 'txt'; }
	if ( !isset($entry->valid) ) { $entry->valid = true; }
	if ( !$entry->valid ) { return false; }
	return true;
}
function random_id($config)
{
	/// Could make your own function here
	return substr(uniqid(),0,$config['idlen']);
}

function sort_by_mtime($file1,$file2) {
    $time1 = filemtime($file1);
    $time2 = filemtime($file2);
    if ($time1 == $time2) {
        return 0;
    }
    return ($time1 < $time2) ? 1 : -1;
    }
// Here we generate the ID and dump the data into the necessary files
function entry_add_separate($config, $entry)
{
	$id = random_id($config);
	// In case we get the same ID again
	while (file_exists($id.".html") ) { $id = random_id();}
	file_put_contents($id.".txt",$entry->content);
	ob_start();
	$page = new stdClass();
	$entry->id = $id;
	$page->config = $config;
	$page->name='entry';
	$page->entry = $entry;
	$page->title = _l('Viewing entry');
	page_render($page);
	file_put_contents($id.".html",ob_get_contents());
	ob_end_clean();
	return $id;
}
function entries_removeold($config)
{
	if ($config['keepen'] == 0 && $config['age'] == 0) { return true; }
	$pat = str_pad("",$config['idlen'],"?").'.txt';
	if ( $config['keepen'] != 0 )
	{
	$f = glob($pat,GLOB_BRACE);
	usort($f,"sort_by_mtime");
	while(isset($f[$config['keepen']]) ) { $d = array_pop($f); unlink($d);$d2 = preg_replace('/\.txt$/','.html',$d);unlink($d2); }
	return true;
	}
	elseif ( $config['age'] != 0 )
	{
		$f = glob($pat,GLOB_BRACE);
		$t = time();
		foreach($f as $file)
		{
			if ( $t - filemtime($file) > $config['age'] )
			{
				unlink($file);
				$d2 = preg_replace('/\.txt$/','.html',$file);
				unlink($d2);
			}
		}
		return true;
	}
	else { return false; }
}
function entry_add($config,$entry)
{
	$entry->time = time();
	entry_add_separate($config,$entry);
	
	if ( $config['showen'] != 0 && file_exists('.recent') && file_get_contents('.recent')) { $recent_entries = unserialize(file_get_contents('.recent')); }
	else { $recent_entries = array(); }
	foreach($recent_entries as $i => $e ) { if (!file_exists($e->id.".txt") ) { unset($recent_entries[$i]); } }
	unset($entry->content);
	array_unshift($recent_entries, $entry);
	$page = new stdClass();
	
	while ( count($recent_entries) > $config['showen']) { array_pop($recent_entries); }
	if ( $config['showen'] != 0 ) { file_put_contents('.recent',serialize($recent_entries)); }
	$page->recent = $recent_entries;
	$page->config = $config;
	$page->name = 'index';
//	var_dump($page->recent);
	ob_start(); page_render($page); file_put_contents('index.html',ob_get_contents()); ob_end_clean();
	entries_removeold($config);
	return true;
/* // not implemented!
 * 
 * ob_start(); feed_render($page); file_put_contents('feed.xml',ob_get_contents()); ob_end_clean(); */
}
function page_render($page) { if(!isset($page) ) { die("No page set!"); } ?><!DOCTYPE html PUBLIC "-//W3C//DTD HTML 4.01//EN"
   "http://www.w3.org/TR/html4/strict.dtd">
<html>
<head>
<meta http-equiv="Content-type" content="text/html; charset=utf-8">
<link href="style.css" rel="stylesheet" type="text/css"><title><?php echo _l('title'); ?></title></head><body<?php if ( isset($page->name) ) { ?> id="<?php echo $page->name; ?>"<?php } ?>><h1><?php echo _l('PasteBox'); ?></h1>
<ul id="menu"><!-- ADD YOUR MENU ENTRIES HERE -->



<li><a href="index.html"><?php echo _l('New entry'); ?></a></li> 
<!--<li><a href="feed.xml"><?php echo _l('Feeds'); ?></a></li>-->
<?php if ( isset($page->entry) ) { ?><li><a href="<?php echo $page->entry->id; ?>.txt"><?php echo _l('Text version'); ?></a></li><?php } ?> </ul><div id="bigwrapper">
<?php if (isset($page->recent)) { ?><div id="xrecent"><?php if ( count($page->recent) > 0 ) { ?><ul id="recent"><?php foreach($page->recent as $i => $item) 
{?><li><a href="<?php echo $item->id; ?>.html"><?php echo htmlspecialchars($item->subject); ?></a> <span class="time"><?php 
echo date(_l('timeformat'),$item->time); ?></span></li><?php }?></ul><?php } ?>
<!-- You may put whatever you like here! -->
</div><?php } 
?>
<div id="center"><?php if (isset($page->entry) ) { ?><div id="t_entry"><!--<h2><?php echo htmlspecialchars($page->entry->subject); ?></h2>-->
<div id="code"><?php
if ( $page->config['geshi'] && in_array($page->entry->type,array_keys($page->config['types'])) )
{
	include_once('geshi/geshi.php');
	$language = $page->entry->type;
	$geshi =& new GeSHi($page->entry->content, $language);
	$geshi->enable_line_numbers(GESHI_FANCY_LINE_NUMBERS);
	echo $geshi->parse_code();
}
else
{
	echo '<pre><code>'.htmlspecialchars($page->entry->content).'</code></pre>';
}
?></div>
<address><?php echo date(_l('timeformat'),$page->entry->time); ?></address></div><?php } else { ?><form id="new" method="post" enctype="multipart/form-data" action="new.php"><ul id="fields">
<!--<li><label><span><?php echo _l('Subject: '); ?></span><input type="text" name="subject" value=""></label></li>-->
<li><label><span><?php echo _l('Type: '); ?></span><select name="type"><?php foreach($page->config['types'] as $type=>$name){?><option value="<?php echo $type; ?>"><?php echo $name; ?></option><?php } ?></select></label></li>
<li><label><span><?php echo _l('Content: '); ?></span><textarea name="content" rows="30" cols="150"></textarea></label></li>
<li><?php if ($config['captcha']) { /* echo _l('captcha');*/ ?><input id="ms1" type="submit" value="Submit" name="submit"><?php } ?><input id="ms2" type="submit" value="<?php echo _l('Post content'); ?>" name="xsubmit"></li>
</ul></form><?php } ?></div><div class="clear"></div><p id="footer"><a href="http://pastebox.sf.net/">PasteBox v1.0.0</a> released under the MIT license | modified by DFKT</p></div></body></html><?php }


/* END OF FUNCTIONS. */
//require_once("language.php");
//require_once("functions.php"); // Main functions

$entry = new stdClass();
entry_input($config,$entry);
// Here we check if the static index page was generated
if ( !file_exists('index.html') || ( file_exists('index.html') && filesize('index.html') == 0) ) { $page = new stdClass(); $page->name = 'index'; $page->recent = array();$page->config = $config; $page->title=_l('PasteBox');
ob_start(); $page->name = 'index';page_render($page);$c=ob_get_contents();file_put_contents('index.html',$c);ob_end_clean();echo $c; die(); }
if ( !isset($_POST['xsubmit']) ) { $entry->valid = false; }
// If the entry is invalid, then we get out.
if ( !$entry->valid )
{
	//var_dump($_POST);
	header("Location: index.html");
	echo "Invalid entry.";
	die();
}
else
{
	// Added scucessfully, redirecting!
	entry_add($config, $entry);
	header('Location: '.$entry->id.'.html');
	echo "Added: <a href=\"".$entry->id.".html\">".$entry->id."</a>";
}

?>
