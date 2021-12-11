<?php
global $com, $dumpAPI;
if(isset($dumpAPI))
	api_dump(json_encode(array("failed"=>$reason, "db"=>$z, "u"=>$u)), "login.json");
$GLOBALS["GLOBAL_VARS"]["action"] = "login";
$GLOBALS["GLOBAL_VARS"]["user"] = strtolower(htmlspecialchars($u==""?(isset($_REQUEST["u"])?$_REQUEST["u"]:""):$u));
$GLOBALS["GLOBAL_VARS"]["z"] = $z;
$GLOBALS["GLOBAL_VARS"]["xsrf"] = xsrf($_SERVER["REMOTE_ADDR"], "guest");
if(!isset($GLOBALS["GLOBAL_VARS"]["uri"]))
	$GLOBALS["GLOBAL_VARS"]["uri"] = "/$z";
if(strlen($reason))
	$GLOBALS["GLOBAL_VARS"]["message"] = strtoupper($reason);
$text = Get_file("login.html");
wlog(" @".$_SERVER["REMOTE_ADDR"], "log");
include "maketree.php";
Make_tree($text, "&main");
include "construct_where.php";
include "get_block_data.php";
die(Parse_block("&main"));
?>
