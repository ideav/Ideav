<?php
header("Cache-Control: no-store, no-cache, must-revalidate");
header("Content-Type: text/html; charset=UTF-8");
header("Expires: ".date("r"));
header('Access-Control-Allow-Headers: X-Authorization, x-authorization,Content-Type,content-type,Origin');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Origin: *');
mb_internal_encoding("UTF-8");

define("USER_MASK", "|^\w{3,15}$|i");  # Mask for the user name validation
define("DIR_MASK", "/^[a-z0-9_]+$/i");  # Mask for the dir name validation
define("FILE_MASK", "/^[a-z0-9_.]+$/i");  # Mask for the dir name validation
define("LOGS_DIR", "logs/");  # Logs files folder
define("VAL_LIM", 127);  # Maximum length of the value (val) field in the DB

define("MAIL_MASK", "/.+@.+\..+/i");  # Mask for the email validation
define("LOGIN_PAGE", " <A HREF=\"/$z\">Продолжить</A>.");  # Path to the login page
define("BACK_LINK" , " <A href=\"#\" onclick=\"history.back();\">Go back</A>");

define("USER", 18);
define("PASSWORD", 20);
define("REPORT", 22);
define("PHONE", 30);
define("XSRF", 40);
define("EMAIL", 41);
define("ROLE", 42);
define("LEVEL", 47);
define("MASK", 49);
define("ACTIVITY", 124);
define("TOKEN", 125);
define("SECRET", 130);
define("CONNECT", 226);

$com = explode("?", $_SERVER["REQUEST_URI"]);
$com = explode("/", $com[0]);
if($com[1] == "checkuser.php") # Check if the user exists
{
    $db = strtolower(htmlspecialchars($_GET["db"]));
	if(!checkUserName($db))
		die("{\"result\":\"Incorrect name\"}");
    $z = "my";
    include "include/connection.php";
    if($row = mysqli_fetch_array(Exec_sql("SELECT 1 FROM $z WHERE val='$db' AND t=".USER, "Check user name uniquity")))
    	if($row[0])	# Inform of the errors, if any, and let him try again
    		die("{\"result\":\"Exists\"}");
	die("{\"result\":\"Ok\"}");
}
elseif($com[1] == "register.php") # Register the user
    include "include/register.php";
elseif(($com[1] == "auth.asp") && !empty($_GET['code']))
    include "include/google.php";
elseif(($com[1] == "api") || ($_SERVER["REQUEST_METHOD"] == "OPTIONS"))
{
    if($_SERVER["REQUEST_METHOD"] == "OPTIONS")
    {
    	header("Allow: GET,POST,OPTIONS");
    	header("Content-Length: 0");
    	die();
    }
    global $dumpAPI;
    $dumpAPI = true;
	$GLOBALS["GLOBAL_VARS"]["api"] = array();
	array_shift($com);
}
if(!is_dir(LOGS_DIR))
	mkdir(LOGS_DIR);
if(strlen($com[1]) && ($_SERVER["SCRIPT_NAME"] != $_SERVER["REQUEST_URI"]))
	$z = strtolower($com[1]); # Get the DB name
else
	login();
$locale = isset($_COOKIE[$z."_locale"]) ? "[".$_COOKIE[$z."_locale"]."]" : "[RU]"; // Default locale
# Store the arguments
foreach($com as $k => $v)
    if($k > 2)
    {
        $GLOBALS["GLOBAL_VARS"][$k]=$v;
        $args[strtolower($v)] = 1;
    }
# Check the DB existence
include "include/connection.php";
$GLOBALS["GLOBAL_VARS"]["z"] = $z;
$GLOBALS["sqls"] = $GLOBALS["sql_time"] = 0;
$params = "";
# The trace cookie to be deleted upon the session close
if(isset($_REQUEST["TRACE_IT"]))
	setcookie("TRACE_IT", 1, 0, "/");
if(isset($_COOKIE["TRACE_IT"]))
	$GLOBALS["TRACE"] = "****".$_SERVER["REQUEST_URI"]."<br/>\n";
# Fetch all the parameters and log them
foreach($_POST AS $key => $value)
	if(is_array($value))
		$params .= "\n $key " . print_r($value, true) . "\n";
	else
		if(strlen($value) && ($key != "p"))	# Do not log passwords
			$params .= " $key=$value;";
wlog($_SERVER["REMOTE_ADDR"]." ".$_SERVER["REQUEST_URI"]." $params", "log");
# Fill in the global array of the basic data types
$GLOBALS["basics"] = array(
	3 => "SHORT",
	8 => "CHARS",
	9 => "DATE",
	13 => "NUMBER",
	14 => "SIGNED",
	11 => "BOOLEAN",
	12 => "MEMO",
	4 => "DATETIME",
	10 => "FILE",
	2 => "HTML",
	7 => "BUTTON",
	6 => "PWD",
	5 => "GRANT",
	15 => "CALCULATABLE",
	16 => "REPORT_COLUMN",
	17 => "PATH");
$GLOBALS["REV_BT"] = $GLOBALS["basics"];
$GLOBALS["BT"] = array_flip($GLOBALS["basics"]);

# Define ALIASES

define("NOT_NULL_MASK", ":!NULL:");
define("ALIAS_MASK", "/:ALIAS=(.+):/u");
define("ALIAS_DEF", ":ALIAS=");
define("DEFAULT_LIMIT", 20);  # Default LIMIT parameter for queries with no filter
define("UPLOAD_DIR", "download/$z/");  # Uploaded files folder
define("DDLIST_ITEMS", 50);  # Default length of dropdown lists
define("COOKIES_EXPIRE", 2592000);  # Cookie expiration time (2592000 = 30 days)

define("REP_COLS", 28);

define("CUSTOM_REP_COL", t9n("[RU]Вычисляемое[EN]Calculatable"));
define("TYPE_EDITOR", "*** Type editor ***");
define("ALL_OBJECTS", "*** All objects ***");
define("FILES", "*** Files ***");

# ################# FUNCTIONS #################
function dieReg($msg, $isOk="Fail")
{
    die("{result:$isOk,msg:\"$msg\"}");
}
function checkUserName($u)
{
    return preg_match(USER_MASK, $u);
}
function isApi()
{
    global $dumpAPI;
    return (isset($dumpAPI) || isset($_POST["JSON"]) || isset($_GET["JSON"]));
}
function xsrf($a, $b)
{
	return substr(sha1(Salt($a, $b)), 0, 22);
}
function login($z="", $u="", $reason="")
{
    include "login.php";
}
function trace($text)
{
	if(isset($GLOBALS["TRACE"]))
		$GLOBALS["TRACE"] .= "$text <br>\n";
}
# Execute SQL and measure the time it needs to be processed
function Exec_sql($sql, $err_msg, $log=TRUE)
{
	global $connection, $z;
	$time_start = microtime(TRUE);
	if(!$result = mysqli_query($connection, $sql))
	{
		if(!isset($GLOBALS["TRACE"]))
			die_info("Couldn't execute query [$err_msg] ".mysqli_error($connection)." ($sql; )");
		print($GLOBALS);
		die_info("Couldn't execute query [$err_msg] ".mysqli_error($connection)." ($sql; )");
	}
	$time = microtime(TRUE) - $time_start;
	if($log && (((strtoupper(substr($sql, 0, 6)) != "SELECT") && (strtoupper(substr($sql, 0, 4)) != "SET "))
	            || isset($GLOBALS["TRACE_IT"])))
	    wlog($GLOBALS["GLOBAL_VARS"]["user"]."@".$_SERVER["REMOTE_ADDR"]."[".round($time, 4)."]$sql;[$err_msg]","sql");
	if(isset($GLOBALS["TRACE"]))
		$GLOBALS["TRACE"] .= "[".round($time, 4)."] $sql; [$err_msg]<br>\n";
	$GLOBALS["sqls"]++;
	$GLOBALS["sql_time"] = $GLOBALS["sql_time"] + $time;

	return $result;
}
# Translation $msg format: "[RU]Текст[EN]Text[DE]Texte"
function t9n($msg)
{
	global $locale;
    $l = mb_stripos($msg, $locale);
    if($l === false)
        return $msg;
    $msg = mb_substr($msg, $l + mb_strlen($locale));
    // Grab the text till the next language starts
    preg_match("/(.*?)\[[A-Z]{2}\]/ms", $msg, $tmp);
    if(isset($tmp[1]))
        return $tmp[1];
    return $msg;
}
# Check file extension
function BlackList($ext)
{
	if(stripos(". php cgi pl fcgi fpl phtml shtml php2 php3 php4 php5 asp jsp ", " $ext "))
		my_die(t9n("[RU]Недопустимый тип файла![EN]Wrong file extension!"));
}
# Get a hashed string
function GetSha($i)
{
	global $z;
	return sha1(Salt($z, $i));
}
# Get the Subdirectory name to securely store files on the server
function GetSubdir($id)
{
	return UPLOAD_DIR.floor($id / 1000).substr(GetSha(floor($id / 1000)), 0, 8);
}
# Get the Filename to securely store files on the server
function GetFilename($id)
{
	return substr("00$id", -3).substr(GetSha($id), 0, 8);
}
# Construct WHERE to apply mask
function Fetch_WHERE_for_mask($t, $val, $mask)
{
	if(isset($GLOBALS["where"]))
		unset($GLOBALS["where"]);
	include_once "include/construct_where.php";
	Construct_WHERE($t, array("F" => $mask), 1, FALSE, TRUE); # Fake parent table =1 to make field look like "a$t.val"
	# Remove beginning " AND " from the condition built and replace the field name
	return str_replace("a$t.val", is_null($val) ? "NULL" : "'".addslashes($val)."'", substr($GLOBALS["where"], 5));
}
# Check Req val granted
function Check_Val_granted($t, $val)
{
	if(isset($GLOBALS["GRANTS"]["mask"][$t]))
	{
		foreach($GLOBALS["GRANTS"]["mask"][$t] as $mask)
		{
			if(!strlen($val))
				if($mask == "!%")	# Empty val granted
					return;
				else
					continue;
			if($row = mysqli_fetch_array(Exec_sql("SELECT ".Fetch_WHERE_for_mask($t, $val, $mask), "Apply granted mask")))
				if($row[0])
					return;
		}
		my_die(t9n("[RU]У вас нет доступа к этому объекту![EN]You do not have this object granted")." ($t)");
	}
}
function Check_Types_Grant($fatal=TRUE)	# $fatal stops the script on no access
{
	if($GLOBALS["GLOBAL_VARS"]["user"] == "admin")
		return "WRITE";
	elseif(($GLOBALS["GRANTS"][0] == "READ") || ($GLOBALS["GRANTS"][0] == "WRITE"))
		return $GLOBALS["GRANTS"][0];
	elseif($fatal)
		die(t9n("[RU]У вас нет прав на редактирование и просмотр типов ([EN]You do not have the grant to view and edit the metadata (").$GLOBALS["GRANTS"][0].").");
	return "READ";
}
function my_die($msg)
{
	if(isset($GLOBALS["TRACE"]))
	{
		print_r($GLOBALS);
		print($GLOBALS["TRACE"]);
	}
	die($msg);
}
# Check grants to the object
function Check_Grant($id, $t=0, $grant="WRITE", $fatal=TRUE)	# $fatal stops the script on no access
{
	global $z;
	if($GLOBALS["GLOBAL_VARS"]["user"] == "admin")
		return TRUE;
	elseif(isset($GLOBALS["GRANTS"][$t])&&($t!=0))
	{
	    trace("  Explicit grant to the Object $t: ".$GLOBALS["GRANTS"][$t]);
		if(($GLOBALS["GRANTS"][$t] == $grant) # Explicit grant to the Object
					|| ($GLOBALS["GRANTS"][$t] == "WRITE"))  # Requested or WRITE (higher)
			return TRUE;
		if(!$fatal)
			return FALSE;
		my_die(t9n("[RU]У вас нет доступа к реквизиту объекта $id, $t (".$GLOBALS["GRANTS"][$t]
			            .") или его родителю ".$id." (".$GLOBALS["GRANTS"][$id]."). Ваш глобальный доступ: '"
			        ."[EN]The object is not granted  $id, $t (".$GLOBALS["GRANTS"][$t]
			            .") neither its parent ".$id." (".$GLOBALS["GRANTS"][$id]."). The access level is: '")
			.$GLOBALS["GRANTS"][1]."'");
	}
	elseif(isset($GLOBALS["GRANTS"][$id]))
	{
		if(($GLOBALS["GRANTS"][$id] == $grant) # Explicit grant to the Parent
					|| ($GLOBALS["GRANTS"][$id] == "WRITE"))  # Requested or WRITE (higher)
			return TRUE;
		if(!$fatal)
			return FALSE;
		my_die(t9n("[RU]У вас нет доступа к реквизиту объекта $id, $t (".$GLOBALS["GRANTS"][$t]
			            .") или его родителю ".$id." (".$GLOBALS["GRANTS"][$id]."). Ваш глобальный доступ: '"
			        ."[EN]The object is not granted  $id, $t (".$GLOBALS["GRANTS"][$t]
			            .") neither its parent ".$id." (".$GLOBALS["GRANTS"][$id]."). The access level is: '")
			.$GLOBALS["GRANTS"][1]."'");
	}
	elseif($t == 0)
		$data_set = Exec_sql("SELECT obj.t, COALESCE(par.t, 1) par_typ, COALESCE(par.id, 1) par_id, COALESCE(arr.id, -1) arr
								FROM $z obj LEFT JOIN $z par ON obj.up>1 AND par.id=obj.up 
									LEFT JOIN $z arr ON arr.up=par.t AND arr.t=obj.t
								WHERE obj.id=$id LIMIT 1", "Get Object info by ID");
	elseif($id != 1)
		$data_set = Exec_sql("SELECT obj.t, COALESCE(par.t, 1) par_typ, COALESCE(par.id, 1) par_id, COALESCE(arr.id, -1) arr
								FROM $z obj JOIN $z par ON obj.up>1 AND (par.t=obj.up OR par.id=obj.up)
									LEFT JOIN $z arr ON arr.up=par.t AND arr.t=obj.t
								WHERE par.id=$id AND (obj.t=$t OR obj.id=$t) LIMIT 1", "Get Object info by Parent&Type");
	else
		$data_set = Exec_sql("SELECT $t t, 1 par_typ, 1 par_id, -1 arr", "Get 1st level Object");
#print_r($GLOBALS); die();

	if($row = mysqli_fetch_array($data_set))
	{
		if(isset($GLOBALS["GRANTS"][$row["t"]]))  # Explicit something for the Object
		{
			if(($GLOBALS["GRANTS"][$row["t"]] == $grant) # Explicit grant to the Object
					OR ($GLOBALS["GRANTS"][$row["t"]] == "WRITE"))  # Requested or WRITE (higher)
				return TRUE;
		}
		elseif(isset($GLOBALS["GRANTS"][$row["arr"]]))  # This is an array member
		{
			if(($GLOBALS["GRANTS"][$row["arr"]] == $grant) # Explicit grant to the Array
					OR ($GLOBALS["GRANTS"][$row["arr"]] == "WRITE"))  # Requested or WRITE (higher)
				return TRUE;
		}
		elseif(isset($GLOBALS["GRANTS"][$row["par_typ"]]))  # Explicit something for the Parent
		{
			if(($GLOBALS["GRANTS"][$row["par_typ"]] == $grant) # Explicit grant to the Parent
					OR ($GLOBALS["GRANTS"][$row["par_typ"]] == "WRITE"))  # Requested or WRITE (higher)
				return TRUE;
		}
		elseif(isset($GLOBALS["GRANTS"][$row["par_id"]]))  # Explicit something for the Parent's type
		{
			if(($GLOBALS["GRANTS"][$row["par_id"]] == $grant) # Explicit grant to the Parent's type
					OR ($GLOBALS["GRANTS"][$row["par_id"]] == "WRITE"))  # Requested or WRITE (higher)
				return TRUE;
		}
		elseif($row["par_id"] > 1)  # Until we get to the ROOT
			if(Check_Grant($row["par_id"], 0, $grant, FALSE)) # Dig further recursively
				return TRUE;
	}
	if($fatal)
		my_die(t9n("[RU]У вас нет доступа к реквизиту объекта $id, $t (".$GLOBALS["GRANTS"][$row["t"]]
			    .") или его родителю ".$row["par_id"]." (".$GLOBALS["GRANTS"][$row["par_typ"]]
			    .")! Ваш глобальный доступ: '".$GLOBALS["GRANTS"][1]
			."'.[EN]The object is not granted $id, $t (".$GLOBALS["GRANTS"][$row["t"]].")neither its parent "
			    .$row["par_id"]." (".$GLOBALS["GRANTS"][$row["par_typ"]].")! The access level is: '".$GLOBALS["GRANTS"][1]."'"));
	return FALSE;
}
# Check Grants for ROOT's children
function Grant_1level($id)  # Check READ grant on the object
{
	if($GLOBALS["GLOBAL_VARS"]["user"] == "admin")
		return TRUE;
	elseif(isset($GLOBALS["GRANTS"][$id]))  # Explicit rights
	{
		if(($GLOBALS["GRANTS"][$id] == "READ") || ($GLOBALS["GRANTS"][$id] == "WRITE"))
			return $GLOBALS["GRANTS"][$id];  # Granted
	}
	elseif(isset($GLOBALS["GRANTS"][1]))  # ROOT rights defined
		if(($GLOBALS["GRANTS"][1] == "READ") || ($GLOBALS["GRANTS"][1] == "WRITE"))
			return $GLOBALS["GRANTS"][1];  # ROOT rights granted
		
	return FALSE;
}
function Validate_Token() # Validates the cookie token and gathers the user permissions
{
	global $z, $blocks, $dumpAPI;
	$GLOBALS["GRANTS"] = array();
	if(isset($dumpAPI))
	{
		foreach(getallheaders() as $key => $value)
    		if(strtolower($key) == "x-authorization")
    		{
        		$tok = $value;
        		break;
    		}
		$typ = TOKEN;
	}
	elseif(isset($_REQUEST["secret"]))
	{
		$tok = addslashes($_REQUEST["secret"]);
		$typ = SECRET;
		setcookie("secret", $tok, 0, "/");  # The cookie to be deleted upon the session close
	}
	elseif(isset($_COOKIE[$z]))
	{
		$tok = addslashes($_COOKIE[$z]);
		$typ = TOKEN;
	}
	elseif(isset($_COOKIE["secret"]))
	{
		$tok = addslashes($_COOKIE["secret"]);
		$typ = SECRET;
	}
    if(isset($tok))
	{
		$data_set = Exec_sql("SELECT u.id, u.val, role_def.id r, role_def.val role, xsrf.val xsrf, act.id aid, act.val act FROM $z tok, $z u
						LEFT JOIN ($z r CROSS JOIN $z role_def) ON r.up=u.id AND role_def.id=r.t AND role_def.t=".ROLE
						." LEFT JOIN $z xsrf ON xsrf.up=u.id AND xsrf.t=".XSRF
						." LEFT JOIN $z act ON act.up=u.id AND act.t=".ACTIVITY
						." WHERE u.t=".USER." AND tok.up=u.id AND tok.val='$tok' AND tok.t=$typ", "Validate token");
		if($row = mysqli_fetch_array($data_set))
		{
            if($row["aid"])
            {
                if($row["act"] != time(TRUE))
    				Update_Val($row["aid"], time(TRUE), FALSE);
            }
    		else
    			Insert($row["id"], 1, ACTIVITY, time(TRUE), "Set activity time");
            
			$GLOBALS["GLOBAL_VARS"]["user"] = strtolower($row["val"]);
			$GLOBALS["GLOBAL_VARS"]["role"] = strtolower($row["role"]);
			$GLOBALS["cur_user_id"] = strtolower($row["id"]);
			$xsrf = $row["xsrf"];
			if(!$row["r"])
				my_die(t9n("[RU]Пользователю ".$GLOBALS["GLOBAL_VARS"]["user"]." не задана роль"
				        ."[EN]No role assigned to user ".$GLOBALS["GLOBAL_VARS"]["user"]));

			$data_set = Exec_sql("SELECT gr.val object, CASE WHEN mask.t=".MASK." THEN mask.val ELSE '' END mask
										, CASE WHEN lev.t=".LEVEL." THEN lev.val ELSE '' END level
										, CASE WHEN lev.t!=".LEVEL." THEN lev_def.val ELSE '' END mass
									FROM $z gr JOIN $z mask ON mask.up=gr.id
										LEFT JOIN ($z lev CROSS JOIN $z lev_def) ON lev.id=mask.t AND lev_def.id=lev.t AND mask.t!=".MASK
								." WHERE gr.up=".$row["r"], "Get grants");
			while($row = mysqli_fetch_array($data_set))
			{
				if(substr($row["mask"], 0, 1) == "[")	# An expression given
				{
					$v = BuiltIn($row["mask"]);
					if($v == $row["mask"])	# No Built in for this
					{
						$attrs = substr($v, 1, strlen($v) - 2);
						include_once "include/construct_where.php";
						include_once "include/get_block_data.php";
						Get_block_data($attrs);
						if(isset($blocks[$attrs][strtolower($attrs)]))
							if(count($blocks[$attrs][strtolower($attrs)]))
								$v = array_shift($blocks[$attrs][strtolower($attrs)]);
					}
				}
				else
					$v = "".$row["mask"];
				if(strlen($row["mask"]) && strlen($row["level"]))	# This means the mask affects this Req only
					$GLOBALS["GRANTS"]["masklevel"][$row["object"]][$row["level"]] = $v;
				elseif(strlen($row["level"]))
					$GLOBALS["GRANTS"][$row["object"]] = $row["level"];
				elseif(strlen($row["mask"]))
					$GLOBALS["GRANTS"]["mask"][$row["object"]][] = $v;
				elseif(strlen($row["mass"]))
					$GLOBALS["GRANTS"][$row["mass"]][$row["object"]] = "";
			}
#print_r($GLOBALS); die($v." ".$attrs." ".count($blocks[$attrs][strtolower($attrs)]));
		}
		elseif(isset($_COOKIE[$z]) || (strlen($tok) == 32))
		{
		    $hash = adminHash();
		    if(($_COOKIE[$z] == $hash) || ($tok == $hash))
    		{
    			$GLOBALS["GLOBAL_VARS"]["user"] = "admin";
    			$xsrf = xsrf($z, $hash);
    		}
		}
		elseif(strlen($tok) == 32)
		$GLOBALS["tzone"] = isset($_COOKIE["tzone"]) ? $_COOKIE["tzone"] : 0;
	}
	if(!isset($GLOBALS["GLOBAL_VARS"]["user"]))
	{ # Check, if we got a Guest user defined
		$data_set = Exec_sql("SELECT u.id u, tok.id tok, xsrf.id xsrf FROM $z u LEFT JOIN $z tok ON tok.up=u.id AND tok.t=".TOKEN
								." LEFT JOIN $z xsrf ON xsrf.up=u.id AND xsrf.t=".XSRF
    		                    ." WHERE u.t=".USER." AND u.val='guest'"
		                    , "Get Guest credentials");
#        print_r($GLOBALS);print_r($GLOBALS["TRACE"]);my_die("ok");
		if($row = mysqli_fetch_array($data_set))
		{
    		if(!$row["tok"])
    			Insert($row["u"], 1, TOKEN, "gtuoeksetn", "Save guest token");
			$xsrf = xsrf("gtuoeksetn", "guest");
    		if(!$row["xsrf"])
    			Insert($row["u"], 1, XSRF, $xsrf, "Save guest xsrf");
    		setcookie($z, "gtuoeksetn", time() + COOKIES_EXPIRE, "/"); # 30 days
    		header("Location: ".$_SERVER["REQUEST_URI"]);
		}
		else
		{
    		setcookie($z, "", 0, "/"); # clear the token
    		login($z, "", "InvalidToken");
		}
        die();
	}
	$GLOBALS["GLOBAL_VARS"]["xsrf"] = isset($xsrf) ? $xsrf : xsrf($_SERVER["REMOTE_ADDR"], "guest");
    $GLOBALS["GLOBAL_VARS"]["token"] = $tok;
	return isset($GLOBALS["GLOBAL_VARS"]["user"]);  # Return TRUE in case the token is OK
}
function adminHash()
{
    return md5(salt(ADMINHASH,$_SERVER["REMOTE_ADDR"]));
}

function Format_Val($typ, $val)
{
	global $z;
	if($val != "NULL")
	{
		if(!isset($GLOBALS["REV_BT"][$typ]))
			if($typ != 0)
				if($row = mysqli_fetch_array(Exec_sql("SELECT t FROM $z WHERE id=$typ", "Get Typ for Format")))
				    if(isset($GLOBALS["REV_BT"][$row["t"]]))
    					$GLOBALS["REV_BT"][$typ] = $GLOBALS["REV_BT"][$row["t"]];
        if(isset($GLOBALS["REV_BT"][$typ]))
            switch($GLOBALS["REV_BT"][$typ])
    		{
    			case "DATE":
    				if(($val != "") && (substr($val, 0, 1) != "[") && (substr($val, 0, 10) != "_request_."))
    				{
    				    if(preg_match("/^([0-9]{4})[-\/\.]?([0-9]{2})[-\/\.]?([0-9]{2})$/", $val, $date))
        					$val = $date[1].$date[2].$date[3]; // ISO YYYY[/-.]MM[/-.]DD
    				    else
    				    {
        					$v = explode("/", str_replace(".", "/", str_replace(",", "/", $val)));
        					$dy = (isset($v[2])) ? (int)((strlen($v[2])==4) ? $v[2] : 2000+$v[2]) : date("Y");
        					$dm = isset($v[1]) ? (int)$v[1] : date("m");
        					$dd = (int)$v[0];
        					if(!checkdate($dm, $dd, $dy))
        						$GLOBALS["warning"] .= t9n("[RU]Неверная дата[EN]Wrong date")." $val!<br>";
        					$val = $dy.substr("0". $dm, -2).substr("0".$dd, -2);
    				    }
    				}
    				break;
    			case "NUMBER":
    				$v = (int)str_replace(",", ".", str_replace(" ", "", $val));
    				if($v != 0)
    					$val = $v;
    				break;
    			case "SIGNED":
    				$v = (double)str_replace(",", ".", str_replace(array(" ", chr(0xC2).chr(0xA0)), "", $val));
    				if($v != 0)
    					$val = $v;
    				break;
    			case "DATETIME":
    				if(($val != "") && (substr($val, 0, 1) != "["))
    				{
    					if($val > 10000)	# Timestamp is OK
    						$val = $val - $GLOBALS["tzone"];
    					elseif(strtotime($val) < 10000)	# An inadequate Timestamp & non-valid string time
    						$val = strtotime(Format_Val($GLOBALS["BT"]["DATE"], $val)) - $GLOBALS["tzone"];	# Try to apply DATE validation
    					else
    						$val = strtotime($val) - $GLOBALS["tzone"];
    				}
    				break;
    		}
	}
	return $val;
}
function Format_Val_View($typ, $val, $id=0)
{
    #trace("format val $val ($typ) of ".$GLOBALS["REV_BT"][$typ]);
	global $z;
	if($val != "NULL" && isset($GLOBALS["REV_BT"][$typ]))
		switch($GLOBALS["REV_BT"][$typ])
		{
			case "DATE":
				if($val != "")
				{
					if(strlen($val) > 8)	# This might be DATETIME
						$val = date("d.m.Y", $val + $GLOBALS["tzone"]);	# Microtime
					else
						$val = substr($val, 6, 2).".".substr($val, 4, 2).".".substr($val, 0, 4);
				}
				break;
			case "DATETIME":
				$val = date("d.m.Y H:i:s", (int)$val + $GLOBALS["tzone"]);	# Microtime
				break;
			case "BOOLEAN":
				if($val != "")
					$val = "X";
				break;
			case "NUMBER":
				if($val != 0)
					$val = number_format($val, 0, "", "");
				break;
			case "FILE":
				$val = "<a target=\"_blank\" href=\"/".GetSubdir($id)."/".GetFilename($id).".".substr(strrchr($val,'.'),1)."\">$val</a>";
				break;
			case "SIGNED":
				if($val == "")
					break;
				$v = explode(".",trim($val));
				$val = trim(number_format($v[0], 0, ".", "") . "." . substr((isset($v[1])?$v[1]:"")."00", 0, max(2,strlen((isset($v[1])?$v[1]:0)))));
				break;
			case "PATH":
				$val = "/".GetSubdir($id)."/".GetFilename($id).".".substr(strrchr($val,'.'),1);
				break;
			case "GRANT":
				if($val == 0)
					return TYPE_EDITOR;
				if($val == 1)
					return ALL_OBJECTS;
				if($val == 10)
					return FILES;
	# Attention: no break here!		
			case "REPORT_COLUMN":
				if($val == "0") # A synthetic field
					$GLOBALS["REP_COLS"][$val] = CUSTOM_REP_COL;
				elseif($val == 0) # Symbolic non-standard (grants for ROOT, EDIT_TYPES, etc)
					$GLOBALS["REP_COLS"][$val] = $val;
				elseif(!isset($GLOBALS["REP_COLS"][$val]))
				{
					$sql = "SELECT a.id, a.val, reqs.id req_id, refs.val req_val, reqs.val attr, ref_vals.val ref_val
							FROM $z a LEFT JOIN ($z reqs CROSS JOIN $z refs) ON refs.id=reqs.t AND reqs.up=a.id
								LEFT JOIN $z ref_vals ON ref_vals.id=refs.t AND ref_vals.id!=ref_vals.t
							WHERE a.id=COALESCE((SELECT up FROM $z WHERE id=$val AND up!=0), $val)";
					$data_set = Exec_sql($sql, "Get Report Columns for View");
					while($row = mysqli_fetch_array($data_set))
					{
						if(!isset($GLOBALS["REP_COLS"][$row["id"]]))
							$GLOBALS["REP_COLS"][$row["id"]] = $row["val"];
						if(!isset($GLOBALS["REP_COLS"][$row["req_id"]]))
							if(strlen($row["ref_val"]))
							{
								$alias = FetchAlias($row["attr"], $row["ref_val"]);
								if($alias == $row["ref_val"])
									$GLOBALS["REP_COLS"][$row["req_id"]] = $row["val"]." -> ".$row["ref_val"];
								else
									$GLOBALS["REP_COLS"][$row["req_id"]] = $row["val"]." -> $alias (".$row["ref_val"].")";
							}
							else
								$GLOBALS["REP_COLS"][$row["req_id"]] = $row["val"]." -> ".$row["req_val"];
					}
				}
				$val = $GLOBALS["REP_COLS"][$val];
				break;
			case "PWD":
				$val = "******";
				break;
		}
	return $val;
}

function Get_file($file, $fatal=TRUE) # $fatal - Die if not found
{
	global $z;
	if(!isset($file))
		die ("Set file name!");

	if(is_file($_SERVER['DOCUMENT_ROOT']."/templates/custom/$z/$file"))  # Search DB folder
		$file = $_SERVER['DOCUMENT_ROOT']."/templates/custom/$z/$file";
	elseif(is_file($_SERVER['DOCUMENT_ROOT']."/templates/$file"))  # Search common folder
		$file = $_SERVER['DOCUMENT_ROOT']."/templates/$file";
	elseif($fatal)
		die ("File [$file] does not exist!");
	else
		return "";

	if(!($fh = fopen($file, "r")))
		die(t9n("[RU]Не удается открыть файл:[$file][EN]Cannot open file: [$file]"));

	$file_text = fread($fh, filesize($file));
	fclose($fh);
	return $file_text;
}

function Get_tail($id, $v)
{
	global $z;
	if($v == "")
		return "";
	$data_set = Exec_sql("SELECT id, val FROM $z WHERE up=$id AND t=0 ORDER BY ord", "Get Tail");
	while($row = mysqli_fetch_array($data_set))
	{
#    add trailing spaces, which where cut by MySQL server, to every object
		$val_length = mb_strlen($v) % VAL_LIM;
		if($val_length)
			$v .= str_repeat(" ", VAL_LIM - $val_length);
		$v .= $row["val"];
	}
	return $v;
}

function Delete($id, $root=TRUE)  # Delete Obj and its children recursively
{
	global $z;
	$children = exec_sql("SELECT id FROM $z WHERE up=$id", "Get children");
	if($child=mysqli_fetch_array($children))
	{
		do
		{
			Delete($child["id"], FALSE);  # FALSE mean don't drop the object itself, just kill Reqs
		} while($child=mysqli_fetch_array($children));
		Exec_sql("DELETE FROM $z WHERE up=$id", "Delete reqs");
	}
	if($root) # Delete the object in case it's the initially requested one
		Exec_sql("DELETE FROM $z WHERE id=$id", "Delete obj");
}
# Replace the built-in Definitions with exact values
# Built-ins look like [VALUE]
function BuiltIn($par)
{
	switch($par)
	{
		case "[TODAY]":  # The current date
			return date("d.m.Y", time() + $GLOBALS["tzone"]);
		case "[NOW]":  # The current datetime
			return date("d.m.Y H:i:s", time() + $GLOBALS["tzone"]);
		case "[YESTERDAY]":  # Yesterday
			return date("d.m.Y", time() - 86400 + $GLOBALS["tzone"]);
		case "[TOMORROW]":  # Tomorrow
			return date("d.m.Y", time() + 86400 + $GLOBALS["tzone"]);
		case "[MONTH_AGO]":		# Month ago
			return date("d.m.Y", strtotime("-1 months") + $GLOBALS["tzone"]);
		case "[USER]":  # Current user
			return $GLOBALS["GLOBAL_VARS"]["user"];
		case "[USER_ID]":  # Current user ID
			return isset($GLOBALS["cur_user_id"]) ? $GLOBALS["cur_user_id"] : "";
		case "[TSHIFT]":  # User's time zone shift
			return $GLOBALS["tzone"];
		case "[REMOTE_ADDR]":  # User's IP-address
			return $_SERVER["REMOTE_ADDR"];
		case "[HTTP_USER_AGENT]":  # User's IP-address
			return $_SERVER["HTTP_USER_AGENT"];
		case "[HTTP_REFERER]":  # User's IP-address
			return $_SERVER["HTTP_REFERER"];
	}
	return $par;  # No matches found - return as is
}
function Download_send_headers($filename)
{ 
# force download
	header("Content-Type: application/force-download");
	header("Content-Type: application/octet-stream");
	header("Content-Type: application/download");
# disposition / encoding on response body
	header("Content-Disposition: attachment;filename={$filename}");
	header("Content-Transfer-Encoding: binary");
}
function FetchAlias($attr, $orig){
	preg_match(ALIAS_MASK, $attr, $alias); # Check if we got an alias
	return isset($alias[1]) ? $alias[1] : $orig;
}
# Fetch the object's meta data and Reqs
function GetObjectReqs($typ, $id)
{
	global $z;
	$GLOBALS["REQS"] = Array();
	$sql = "SELECT a.id t, refs.id ref_id, a.val attrs, a.ord
				, CASE WHEN refs.id IS NULL THEN typs.t ELSE refs.t END base_typ
				, CASE WHEN refs.id IS NULL THEN typs.val ELSE refs.val END val
				, CASE WHEN arrs.id IS NULL THEN NULL ELSE typs.id END arr_id
			FROM $z a, $z typs LEFT JOIN $z refs ON refs.id=typs.t AND refs.t!=refs.id
					LEFT JOIN $z arrs ON refs.id IS NULL AND arrs.up=typs.id AND arrs.ord=1
			WHERE a.up=$typ AND typs.id=a.t ORDER BY a.ord";
	$data_set = Exec_sql($sql, "Get the Reqs meta");
	while($row = mysqli_fetch_array($data_set))
	{
		if($row["ref_id"])
			$GLOBALS["REF_typs"][$row["t"]] = $row["ref_id"];
		elseif($row["arr_id"])
			$GLOBALS["ARR_typs"][$row["t"]] = $row["arr_id"];
		if(($row["base_typ"] == 0) && !isset($_REQUEST["copybtn"]))	# Tab met and we're not copying the Object
		{
			if(count($GLOBALS["REQS"]) && !(isset($GLOBALS["TABS"])))	# And there were Reqs already - tab them as 'Reqs'
			{
				$GLOBALS["TABS"][0] = t9n("[RU]Реквизиты[EN]Attributes");
				if(!isset($_REQUEST["tab"]) || ($_REQUEST["tab"] == 0))	# This was the Tab we need
				{
					$tab_from = 0;
					$tab_to = $row["ord"];
					$GLOBALS["TABS"][$row["t"]] = $row["val"];
					continue;
				}
			}
			$GLOBALS["TABS"][$row["t"]] = $row["val"];
			if(isset($_REQUEST["tab"]))
			{
				if($_REQUEST["tab"] == $row["t"])
				{
					$GLOBALS["REQS"] = Array();
					$tab_from = $row["ord"];
				}
				elseif(isset($tab_from))
					$tab_to = $row["ord"];
			}
			elseif(isset($tab_from))
				$tab_to = $row["ord"];
			else
				$tab_from = $row["ord"];
			continue;
		}
		if(isset($tab_to) # We don't need the rest of the Reqs, except Buttons; just keep collecting Tabs
				&& ($GLOBALS["REV_BT"][$row["base_typ"]] != "BUTTON"))
			continue;
		$GLOBALS["REQS"][$row["t"]]["base_typ"] = $row["base_typ"];
		$GLOBALS["REQS"][$row["t"]]["val"] = isset($row["ref_id"]) ? FetchAlias($row["attrs"], $row["val"]) : $row["val"];
		$GLOBALS["REQS"][$row["t"]]["ref_id"] = $row["ref_id"];
		$GLOBALS["REQS"][$row["t"]]["arr_id"] = $row["arr_id"];
		$GLOBALS["REQS"][$row["t"]]["attrs"] = $row["attrs"];
	}
	if(isset($GLOBALS["ARR_typs"]))
		$sql = "SELECT CASE WHEN typs.up=0 THEN 0 ELSE reqs.id END id, CASE WHEN typs.up=0 THEN 0 ELSE reqs.val END val
					, typs.id t, count(1) arr_num";
	else
		$sql = "SELECT reqs.id, reqs.val, typs.id t, origs.t base_typ";
	$sql .= ", origs.t bt, typs.val ref_val FROM $z reqs JOIN $z typs ON typs.id=reqs.t LEFT JOIN $z origs ON origs.id=typs.t WHERE reqs.up=$id";
	if(isset($GLOBALS["ARR_typs"]))
		$sql .=	" GROUP BY val, id, t";
	$data_set = Exec_sql($sql, "GetObjectReqs");
	while($row = mysqli_fetch_array($data_set))
		if(isset($GLOBALS["REF_typs"][$row["val"]]))
		{
			$rows[$row["val"]]["id"] = $row["id"];
			$rows[$row["val"]]["val"] = $row["t"];
			$rows[$row["val"]]["ref_val"] = $row["ref_val"];
		}
		else
		{
			$rows[$row["t"]]["id"] = $row["id"];
			if(in_array($GLOBALS["REV_BT"][$row["bt"]], array("CHARS", "MEMO", "FILE", "HTML"))
				&& (mb_strlen($row["val"]) == VAL_LIM))
				$rows[$row["t"]]["val"] = Get_tail($row["id"], $row["val"]);
			else
				$rows[$row["t"]]["val"] = $row["val"];
			$rows[$row["t"]]["arr_num"] = isset($row["arr_num"]) ? $row["arr_num"] : 0;
		}
	if(isset($rows))
    	$GLOBALS["ObjectReqs"] = $rows;
}
# Calc the Order among the peers
function Calc_Order($up, $t)
{
	global $z;
	$data_set = Exec_sql("SELECT COALESCE(MAX(ord)+1, 1) FROM $z WHERE t=$t AND up=$up", "Get the Ord for new Array Object");
	if($row = mysqli_fetch_array($data_set))
		return $row[0];
	die(t9n("[RU]Не удается вычислить порядок[EN]Cannot Calc the Order"));
}
# Retrieve all current requisites to tell the updated ones
function Get_Current_Values($id, $typ)
{
	GetObjectReqs($typ, $id);
	$rows = isset($GLOBALS["ObjectReqs"]) ? $GLOBALS["ObjectReqs"] : array();
#print_r($GLOBALS);print_r($rows);die();
	foreach($GLOBALS["REQS"] as $key => $value)
	{
		if(!is_array($value))
			continue;
		if(!(strpos($GLOBALS["REQS"][$key]["attrs"], NOT_NULL_MASK) === FALSE))
			$GLOBALS["NOT_NULL"][$key] = "";
		# Remember the base Type
		$GLOBALS["REV_BT"][$key] = $GLOBALS["REV_BT"][$GLOBALS["REQS"][$key]["base_typ"]];
		if(isset($rows[$key]))
		{
			$GLOBALS["REQS"][$key] = $rows[$key]["val"];
			$GLOBALS["REQ_TYPS"][$key] = $rows[$key]["id"];
		}
		elseif(isset($GLOBALS["REF_typs"][$key]))
		{
			$GLOBALS["REQS"][$key] = isset($rows[$GLOBALS["REF_typs"][$key]]["val"]) ? $rows[$GLOBALS["REF_typs"][$key]]["val"] : NULL;
			$GLOBALS["REQ_TYPS"][$key] = isset($rows[$GLOBALS["REF_typs"][$key]]["id"]) ? $rows[$GLOBALS["REF_typs"][$key]]["id"] : NULL;
			$GLOBALS["REV_BT"][$key] = "REFERENCE";
		}
		elseif(isset($GLOBALS["ARR_typs"][$key]))
			$GLOBALS["REQS"][$key] = isset($rows[$GLOBALS["ARR_typs"][$key]]["arr_num"]) ? $rows[$GLOBALS["ARR_typs"][$key]]["arr_num"] : NULL;
		elseif($key != $typ)
			$GLOBALS["REQS"][$key] = $GLOBALS["REQ_TYPS"][$key] = "";
		# Remember Booleans separately
		if($GLOBALS["REV_BT"][$key] == "BOOLEAN")
			if($GLOBALS["REQS"][$key] == 1)
				$GLOBALS["BOOLEANS"][$key] = 1;
	}
}
# Mention the Type in case it's not a requisite's order
function Get_Ord($parent, $typ=0)
{
	global $z;
	$result = Exec_sql("SELECT max(ord) ord FROM $z WHERE up=$parent". ($typ==0 ? "" : " AND t=$typ"), "Get Ord");
	$row = mysqli_fetch_array($result);
	return $row["ord"] + 1;
}
# Add user name and some salt to the password value
function Salt($u, $val)
{
	global $z;
	$u = strtoupper($u);
	return SALT."$u$z$val";
}
# Inserts new values in a batch
function Insert_batch($up, $ord, $t, $val, $message)
{
    if(mb_strlen($val) > VAL_LIM)
        return Insert($up, $ord, $t, $val, $message);
	global $connection, $z;
	if(($up === "") && isset($GLOBALS["SQLbatch"])) // Close the batch
	{
    	exec_sql("INSERT INTO $z (up, ord, t, val) VALUES ".$GLOBALS["SQLbatch"], "Close batch: $message");
    	unset($GLOBALS["SQLbatch"]);
    	return;
	}
	if(isset($GLOBALS["SQLbatch"]))
    	$GLOBALS["SQLbatch"] .= ",($up,$ord,$t,'".addslashes($val)."')";
    else
        $GLOBALS["SQLbatch"] = "($up,$ord,$t,'".addslashes($val)."')";
#    trace("GLOBAL[SQLbatch] = ".$GLOBALS["SQLbatch"]);
	if(strlen($GLOBALS["SQLbatch"]) > 31000)
	{
    	exec_sql("INSERT INTO $z (up, ord, t, val) VALUES ".$GLOBALS["SQLbatch"], "Flush batch: $message");
    	unset($GLOBALS["SQLbatch"]);
	}
}
# Inserts a new value and returns the ID it got
function Insert($up, $ord, $t, $val, $message)
{
	global $connection, $z;
	exec_sql("INSERT INTO $z (up, ord, t, val) VALUES ($up, $ord, $t, '".addslashes(mb_substr($val, 0, VAL_LIM))."')", "Insert: $message");
    $id = mysqli_insert_id($connection);
	$ord = 0; # Add the tail if applicable
	while(mb_strlen($val) > VAL_LIM)
	{
    	$val = mb_substr($val, VAL_LIM); # Cut off the rest of the value
    	exec_sql("INSERT INTO $z (up, ord, t, val) VALUES ($id, ".($ord++).", 0, '".addslashes(mb_substr($val, 0, VAL_LIM))."')"
    	        , 'Save tail $id ($order)');
	}
	return $id;
}
# Update the value
function Update_Val($id, $val, $no_tail=FALSE)
{
	global $z;
	if($no_tail && (mb_strlen($val) <= VAL_LIM))	# Short values with no tail
		return Exec_sql("UPDATE $z SET val='".addslashes($val)."' WHERE id=$id", "Update Val with no tails");
# Checking presence of "Tail" children
	$tails = exec_sql("SELECT id, ord, val FROM $z WHERE up=$id AND t=0 ORDER BY ord", "Get tails");
# Write the Value of the object in VAL_LIM-char portions
	$v = addslashes(mb_substr($val, 0, VAL_LIM));
	Exec_sql("UPDATE $z SET val='$v' WHERE id=$id", "Update Val");
	$val = mb_substr($val, VAL_LIM);
# If there are Tails
	$ord = 0;
	while($tail=mysqli_fetch_array($tails))
	{
		$ord = $tail[1];
		#  and Object's Value is over - kill the rest of Tails
		if(mb_strlen($val) == 0)
		{
			Exec_sql("DELETE FROM $z WHERE up=$id AND t=0 AND ord>=$ord", "Kill Tails");
			return;
		}
		else  # if value is not over - continue Value loading
		{
			$v = addslashes(mb_substr($val, 0, VAL_LIM));
			if($tail[2] != $v)
				Exec_sql("UPDATE $z SET val='$v' WHERE id=".$tail[0], "Update Val tail");
			$val = mb_substr($val, VAL_LIM);
			$ord++;
		}
	}
	# If there are no more existing Tails and the Value isn't over - 
	#   create new records to fill with the rest of the Value
	while(mb_strlen($val)>0)
	{
		$v = mb_substr($val, 0, VAL_LIM);
		Insert($id, $ord, 0, $v, "Insert Tail");
		$val = mb_substr($val, VAL_LIM);
		$ord++;
	}
}

function die_info($msg)
{
	if(isset($GLOBALS["TRACE"]))
		echo $GLOBALS["TRACE"];
	if(($GLOBALS["GLOBAL_VARS"]["z"] == $GLOBALS["GLOBAL_VARS"]["user"]) || ($GLOBALS["GLOBAL_VARS"]["user"] == "admin"))
		die("$msg<br /><font color=\"lightgray\"><a href=\"/".$GLOBALS["GLOBAL_VARS"]["z"]."/dir_admin\">Файлы</a></font>");
	die($msg);
}
function check()
{
    if(isset($_POST["_xsrf"]))
    	if($GLOBALS["GLOBAL_VARS"]["xsrf"] == $_POST["_xsrf"]) # Check the xsrf token
	    	return true;
	if(($GLOBALS["GLOBAL_VARS"]["user"] == "admin") && ($GLOBALS["GLOBAL_VARS"]["xsrf"] == xsrf($z, adminHash())))
    	return true;
	my_die(t9n("[RU]Неверный или устаревший токен CSRF<br/>[EN]Invalid or expired CSRF token <br/>".BACK_LINK));
}
function api_dump($json, $name="api.json")
{
	download_send_headers($name);
	ob_start();
	$api = fopen("php://output", 'w');
	fwrite($api, $json);
	fclose($api);
	echo ob_get_clean();
	die();
}
function myexit()
{
    global $z;
	if(isset($GLOBALS["TRACE"]))
	{
		if(!is_dir($path="templates/custom/$z/logs"))
			mkdir($path);
        $file = fopen("$path/trace".date("YmdHis").".log", "a+");
        fwrite($file, $GLOBALS["TRACE"].print_r($GLOBALS, TRUE));
        fclose($file);
	}
	exit();
}
function dumptrace()
{
    global $z;
	if(!is_dir($path="templates/custom/$z/logs"))
		mkdir($path);
    $file = fopen("$path/trace".date("YmdHis").".log", "a+");
    fwrite($file, $GLOBALS["TRACE"].print_r($GLOBALS, TRUE));
    fclose($file);
}
function mywrite($t, $mode="a+")
{
    global $z;
	if(!is_dir($path="templates/custom/$z/logs"))
		mkdir($path);
    $file = fopen("$path/trace.log", $mode);
    fwrite($file, "$t\r\n");
    fclose($file);
}
function CheckRepColGranted($id, $level=0)
{
    global $z;
	$row = mysqli_fetch_array(Exec_sql("SELECT up FROM $z WHERE id=$id", "Check the new ref"));
	if($level !== 0)
	{
	    if($row["up"] == 0)
	    {
    	    if(!Grant_1level($id))
                my_die(t9n("[RU]Нет доступа на запись к объекту с типом $id [EN]Object type #$id is not granted for changes"));
	    }
	    else
    	    Check_Grant($row["up"], $id);
	}
    elseif(!Grant_1level($row["up"] == 0 ? $id : $row["up"]))
        my_die(t9n("[RU]Нет доступа к объекту с типом ".($row["up"] == 0 ? $id : $id.":".$row["up"]).".[EN]Object type #".($row["up"] == 0 ? $id : $id.":".$row["up"])." is not granted"));
}
# ################# Start here #################
$time_start = microtime(TRUE);
$blocks = array();
$a = $GLOBALS["GLOBAL_VARS"]["action"] = isset($com[2]) ? $com[2] : "";
$id = $GLOBALS["GLOBAL_VARS"]["id"] = isset($com[3]) ? (int)$com[3] : "";
$next_act = isset($_REQUEST["next_act"]) ? addslashes($_REQUEST["next_act"]) : "";

switch($a)  # Check actions, which don't require authentication
{
	case "auth":
        include "include/auth.php";
		break;

	case "confirm":
    	if(isset($_POST["host"]) || isset($_GET["host"]))
    		$host = isset($_POST["host"]) ? $_POST["host"] : $_GET["host"];
		if($row = mysqli_fetch_array(Exec_sql("SELECT pwd.id FROM $z pwd, $z u WHERE pwd.up=u.id AND pwd.t=".PASSWORD
						." AND u.t=".USER." AND u.val='".addslashes($_REQUEST["u"])."' AND pwd.val='".addslashes($_REQUEST["o"])."'"
						, "Get user's pwd")))
		{
			Exec_sql("UPDATE $z SET val='".addslashes($_REQUEST["p"])."' WHERE id=".$row[0], "Reset the password");
			$reason = "confirm";
		}
		else
		    $reason = "obsolete";
		if(isset($host))
    		header("Location: $host#$reason");
    	else
        	login($z, urlencode($_REQUEST["u"]), $reason);
		break;

	case "exit":
		setcookie($z, "", time() - 3600, "/");  # Remove the password and secret cookies
		setcookie("secret", "", time() - 3600, "/");
		if(strlen($next_act))
			die("<script>document.location.href='/$z/$next_act'</script>");
		login($z);
		break;

	case "login":
		login($z,htmlentities($_REQUEST["u"]));
		break;
}

$GLOBALS["GLOBAL_VARS"]["uri"] = htmlentities($_SERVER["REQUEST_URI"]);
if(Validate_Token())
{
    $up = isset($_REQUEST["up"]) ? (int)$_REQUEST["up"] : 0;
    $t = isset($_REQUEST["t"]) ? (int)$_REQUEST["t"] : 0;
    $val = isset($_REQUEST["val"]) ? $_REQUEST["val"] : "";
    $unique = isset($_REQUEST["unique"]) ? 1 : 0;
    $arg = "";

	if(substr($a, 0, 3) == "_m_") # This is a DML action
	{
		check();
        include "include/$a.php";
    }
	elseif(substr($a, 0, 3) == "_d_") # This is a DDL action
        include "include/_d.php";
	else
        include "include/_other.php";
	if(isset($GLOBALS["TRACE"]))
	    dumptrace();
}
else
	login($z);
#print_r($GLOBALS); die();
if(isset($_REQUEST["message"]))
	die("<h3>".$_REQUEST["message"]."</h3>");
if($next_act == "nul")
	die('{"id":"'.$id.'", "obj":"'.$obj.'", "a":"'.$a.'", "args":"'.$arg.'"}');
elseif($next_act == "")
    $next_act = $a;
else
    $next_act = str_replace("[id]", isset($obj)?$obj:"", $next_act);
if(substr($a, 0, 3) == "_d_")
    $arg .= "ext";
if(isApi())
	api_dump(json_encode(array("id"=>$id, "obj"=>$obj, "next_act"=>"$next_act", "args"=>$arg
	                            , "warnings"=>(isset($GLOBALS["warning"])?$GLOBALS["warning"]:"")), JSON_HEX_QUOT));
header("Location: /$z/$next_act/$id".(strlen($arg) ? "/?$arg" : "").(isset($obj)?"#$obj":""));
?>
