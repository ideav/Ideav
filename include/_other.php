<?php
switch ($a)
{
	case "xsrf":
		api_dump(json_encode(array("_xsrf"=>$GLOBALS["GLOBAL_VARS"]["xsrf"],"token"=>$GLOBALS["GLOBAL_VARS"]["token"],"user"=>$GLOBALS["GLOBAL_VARS"]["user"],"msg"=>"")), "login.json");
	    break;
	    
	case "connect":
		if($id == 0)
			my_die(t9n("[RU]Неверный id ($id) [EN]Invalid id ($id)"));
		$sql = "SELECT val FROM $z WHERE up=$id AND t=".CONNECT;
		if($row = mysqli_fetch_array(Exec_sql($sql, "Get the connector")))
		{
		    trace("Got connector: ".$row["val"]);
			foreach($_GET as $k => $v)
				$url .= "&$k=$v";
			$url = $row["val"] . (strpos($row["val"],"?") ? "&" : "?") . substr($url, 1);
		    trace("url: $url");
			$ch = curl_init();
			curl_setopt($ch, CURLOPT_HEADER, 0);
			curl_setopt($ch, CURLOPT_HTTPHEADER, array("User-Agent: Integral"));
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
			curl_setopt($ch, CURLOPT_URL, $url);
			$val = curl_exec($ch);
			if(curl_errno($ch)){
				$val = curl_errno($ch).": $val";
				$file_failed = true;
			}
			curl_close($ch);
			die("$val");
		}
	    break;

	default:
		$user = $GLOBALS["GLOBAL_VARS"]["user"];
		$f_u = isset($_REQUEST["F_U"]) ? (int)$_REQUEST["F_U"] : "1"; # Filter for associated (linked) objects
		if(isset($_GET["warning"]))
			$GLOBALS["warning"] = $_REQUEST["warning"];
		
        include_once "maketree.php";
        include_once "construct_where.php";
        include_once "get_block_data.php";
        
		if($a == "report")
		{
			unset($blocks); # We might get some stuff there already
			$text = Get_file("report.html"); # Avoid UI header and styles
			if(isset($_REQUEST["obj"]))
				if($_REQUEST["obj"] != 0)
					$obj = (int)$_REQUEST["obj"];  # Set the exact object for calculatables
		}
		elseif($a == "dir_admin") # Admin should be able to fix the files anyway,
		{
			Make_tree(Get_file("dir_admin.html"), ""); # thus, avoid UI header and styles
			die(Parse_block(""));
		}
		else
			$text = Get_file("main.html");
#    		wlog("$user@".$_SERVER["REMOTE_ADDR"], "log");
    	$GLOBALS["GLOBAL_VARS"]["user_id"] = isset($GLOBALS["cur_user_id"]) ? $GLOBALS["cur_user_id"] : 0; # This would be 0 for illegal Admin
    
    	if(isset($_REQUEST["TIME"]))
    		set_time_limit(3600);
    	Make_tree($text, "&main");
    	$html = Parse_block("&main");

    	$time = substr(microtime(TRUE) - $time_start, 0, 6);
    	$stime = round($GLOBALS["sql_time"], 4);
    	$scount = $GLOBALS["sqls"];
    	$tzone = $GLOBALS["tzone"];
		wlog("$user@".$_SERVER["REMOTE_ADDR"]."[$scount/$time/$stime]", "log");
		if(isApi())
			die(json_encode($GLOBALS["GLOBAL_VARS"]["api"], JSON_HEX_QUOT));
#				print_r($GLOBALS["GLOBAL_VARS"]["api"]);
    	if(($z == $GLOBALS["GLOBAL_VARS"]["user"]) || ($GLOBALS["GLOBAL_VARS"]["user"] == "admin"))
    		echo str_replace("<!--Elapsed-->"
    		                , "<font  size=\"-1\"><a href=\"/$z/dir_admin\">[$user]</a> $scount / $stime / $time ($tzone)</font>", $html);
    	else
    		echo str_replace("<!--Elapsed-->", "<font size=\"-1\">[$user] $scount / $stime / $time ($tzone)</font>", $html);
    	myexit();
	}
?>
