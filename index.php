<?php
header("Cache-Control: no-store, no-cache, must-revalidate");
header("Content-Type: text/html; charset=UTF-8");
header("Expires: " . date("r"));
header('Access-Control-Allow-Headers: X-Authorization, x-authorization,Content-Type,content-type,Origin');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Origin: *');
mb_internal_encoding("UTF-8");
error_reporting(0);
define("DB_MASK", "/^[a-z]\w{1,14}$/i");
define("USER_DB_MASK", "/^[a-z]\w{2,14}$/i");
define("DIR_MASK", "/^[a-z0-9_]+$/i");
define("FILE_MASK", "/^[a-z0-9_.]+$/i");
define("LOGS_DIR", "logs/");
define("USER", 18);
define("DATABASE", 271);
define("PHONE", 30);
define("XSRF", 40);
define("EMAIL", 41);
define("ROLE", 42);
define("ACTIVITY", 124);
define("PASSWORD", 20);
define("TOKEN", 125);
define("SECRET", 130);
define("VAL_LIM", 127);
$com = explode("?", $_SERVER["REQUEST_URI"]);
$com = explode("/", $com[0]);
if (isset($com[1]))
	$z = strtolower($com[1]);
else
	$z = "ideav";
if (($z == "api") || ($_SERVER["REQUEST_METHOD"] == "OPTIONS")) {
	if ($_SERVER["REQUEST_METHOD"] == "OPTIONS") {
		header("Allow: GET,POST,OPTIONS");
		header("Content-Length: 0");
		die();
	}
	$dumpAPI                       = true;
	$GLOBALS["GLOBAL_VARS"]["api"] = array();
	array_shift($com);
	if (isset($com[1]))
		$z = $com[1];
	else
		die("[{\"error\":\"No DB provided\"}]");
}
$locale = isset($_COOKIE[$z . "_locale"]) ? $_COOKIE[$z . "_locale"] : (isset($_COOKIE["my_locale"]) ? $_COOKIE["my_locale"] : "EN");
if (isset($_REQUEST["TRACE_IT"])) {
	$GLOBALS["TRACE"] = "****" . $_SERVER["REQUEST_URI"] . "<br/>\n";
	if (isset($_GET["TRACE_IT"]))
		setcookie("TRACE_IT", 1, 0, "/");
}
$params = "";
foreach ($_POST as $key => $value)
	if (is_array($value))
		$params .= "\n $key " . print_r($value, true) . "\n";
	else if (strlen($value) && ($key != "pwd"))
		$params .= " $key=$value;";
if (!is_dir(LOGS_DIR))
	mkdir(LOGS_DIR);
if (!checkDbName(DB_MASK, $z))
	login("", "", "InvalidDB");
foreach ($com as $k => $v)
	if ($k > 2) {
		$GLOBALS["GLOBAL_VARS"][$k] = $v;
		$args[strtolower($v)]       = 1;
	}
include "include/connection.php";
$GLOBALS["GLOBAL_VARS"]["z"] = $z;
$GLOBALS["sqls"]             = $GLOBALS["sql_time"] = 0;
$GLOBALS["basics"]           = array(
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
	17 => "PATH"
);
$GLOBALS["REV_BT"]           = $GLOBALS["basics"];
$GLOBALS["BT"]               = array_flip($GLOBALS["basics"]);
define("MAIL_MASK", "/.+@.+\..+/i");
define("LOGIN_PAGE", " <A HREF=\"/$z\">Продолжить</A>.");
define("BACK_LINK", " <A href=\"#\" onclick=\"history.back();\">Go back</A>");
define("REPORT", 22);
define("LEVEL", 47);
define("MASK", 49);
define("CONNECT", 226);
define("NOT_NULL_MASK", ":!NULL:");
define("ALIAS_MASK", "/:ALIAS=(.+):/u");
define("ALIAS_DEF", ":ALIAS=");
define("DEFAULT_LIMIT", 20);
define("UPLOAD_DIR", "download/$z/");
define("DDLIST_ITEMS", 50);
define("COOKIES_EXPIRE", 2592000);
define("REP_COLS", 28);
define("REP_JOIN", 44);
define("REP_HREFS", 95);
define("REP_URL", 97);
define("REP_LIMIT", 134);
define("REP_IFNULL", 113);
define("REP_WHERE", 262);
define("REP_ALIAS", 265);
define("REP_JOIN_ON", 266);
define("REP_COL_FORMAT", 29);
define("REP_COL_ALIAS", 58);
define("REP_COL_FUNC", 63);
define("REP_COL_TOTAL", 65);
define("REP_COL_NAME", 100);
define("REP_COL_FORMULA", 101);
define("REP_COL_FROM", 102);
define("REP_COL_TO", 103);
define("REP_COL_HAV_FR", 105);
define("REP_COL_HAV_TO", 106);
define("REP_COL_HIDE", 107);
define("REP_COL_SORT", 109);
define("REP_COL_SET", 132);
define("CUSTOM_REP_COL", t9n("[RU]Вычисляемое[EN]Calculatable"));
define("TYPE_EDITOR", "*** Type editor ***");
define("ALL_OBJECTS", "*** All objects ***");
define("FILES", "*** Files ***");
function updateTokens($row)
{
	global $z;
	$token = $GLOBALS["GLOBAL_VARS"]["token"] = md5(microtime(TRUE));
	$xsrf  = $GLOBALS["GLOBAL_VARS"]["xsrf"] = xsrf($token, $z);
	setcookie($z, $token, time() + 2592000 * 12, "/");
	if ($row["tok"])
		Update_Val($row["tok"], $token);
	else
		Insert($row["uid"], 1, TOKEN, $token, "Save token");
	if ($row["xsrf"])
		Update_Val($row["xsrf"], $xsrf);
	else
		Insert($row["uid"], 1, XSRF, $xsrf, "Save xsrf");
	if ($row["act"])
		Update_Val($row["act"], microtime(TRUE), FALSE);
	else
		Insert($row["uid"], 1, ACTIVITY, microtime(TRUE), "Save activity time");
}
function checkDbName($mask, $db)
{
	return preg_match($mask, $db);
}
function isApi()
{
	global $dumpAPI;
	return (isset($dumpAPI) || isset($_POST["JSON"]) || isset($_GET["JSON"]) || isset($_POST["JSON_DATA"]) || isset($_GET["JSON_DATA"]) || isset($_POST["JSON_KV"]) || isset($_GET["JSON_KV"]));
}
function xsrf($a, $b)
{
	return substr(sha1(Salt($a, $b)), 0, 22);
}
function login($z = "", $u = "", $message = "", $details = "")
{
	wlog(" @" . $_SERVER["REMOTE_ADDR"], "log");
	if (isApi())
		api_dump(json_encode(array(
			"message" => $message,
			"db" => $z,
			"login" => $u,
			"details" => $details
		)), "login.json");
	$p = "?";
	if (strlen($z))
		$p .= "db=$z&";
	if (strlen($u))
		$p .= "u=$u&";
	if (strlen($message))
		$p .= "r=$message&";
	if (strlen($details))
		$p .= "d=" . urlencode($details) . "&";
	header("Location: /" . substr($p, 0, -1));
	die();
}
function wlog($text, $mode)
{
	$file = fopen(LOGS_DIR . $GLOBALS["z"] . "_$mode.txt", "a+");
	fwrite($file, date("d/m/Y H:i:s") . " $text\n");
	fclose($file);
}
function trace($text)
{
	if (isset($GLOBALS["TRACE"]))
		$GLOBALS["TRACE"] .= "$text <br>\n";
}
function Exec_sql($sql, $err_msg, $log = TRUE)
{
	global $connection, $z;
	$time_start = microtime(TRUE);
	if (!$result = mysqli_query($connection, $sql)) {
		if (mysqli_errno($connection) === 1146)
			login("", "", "dBNotExists", t9n("[RU]База $z не существует[EN]The $z DB does not exist") . " [$err_msg]");
		die_info("Couldn't execute query [$err_msg] " . mysqli_error($connection) . " ($sql; )");
	}
	$time = microtime(TRUE) - $time_start;
	$GLOBALS["sqls"]++;
	$GLOBALS["sql_time"] = $GLOBALS["sql_time"] + $time;
	return $result;
}
function HintNeeded($k, $id)
{
	if (isset($GLOBALS["STORED_REPS"][$id][REP_COL_NAME][$k])) {
		$str = str_replace(" ", "_", $GLOBALS["STORED_REPS"][$id][REP_COL_NAME][$k]);
		if (isset($_REQUEST["FR_$str"]))
			$c = $_REQUEST["FR_$str"];
		elseif (isset($_REQUEST["TO_$str"]))
			$c = $_REQUEST["TO_$str"];
	}
	if (isset($GLOBALS["STORED_REPS"][$id][REP_COL_FROM][$k]))
		$c = $GLOBALS["STORED_REPS"][$id][REP_COL_FROM][$k];
	elseif (isset($GLOBALS["STORED_REPS"][$id][REP_COL_TO][$k]))
		$c = $GLOBALS["STORED_REPS"][$id][REP_COL_TO][$k];
	if (isset($c))
		if (strlen($c))
			if ((substr($c, 0, 1) != "!") && (substr($c, 0, 1) != "%")) {
				trace("Hint NOT needed for $k: $c");
				return false;
			}
	trace("HintNeeded for $k");
	return true;
}
function t9n($msg)
{
	global $locale;
	$l = mb_stripos($msg, "[" . $locale . "]");
	if ($l === false)
		return $msg;
	$msg = mb_substr($msg, $l + 4);
	preg_match("/(.*?)\[[A-Z]{2}\]/ms", $msg, $tmp);
	if (isset($tmp[1]))
		return $tmp[1];
	return $msg;
}
function BlackList($ext)
{
	if (stripos(". php cgi pl fcgi fpl phtml shtml php2 php3 php4 php5 asp jsp ", " $ext "))
		my_die(t9n("[RU]Недопустимый тип файла![EN]Wrong file extension!"));
}
function GetSha($i)
{
	global $z;
	return sha1(Salt($z, $i));
}
function GetSubdir($id)
{
	return UPLOAD_DIR . floor($id / 1000) . substr(GetSha(floor($id / 1000)), 0, 8);
}
function GetFilename($id)
{
	return substr("00$id", -3) . substr(GetSha($id), 0, 8);
}
function RemoveDir($path)
{
	if (is_dir($path)) {
		$dirHandle = opendir($path);
		while (false !== ($file = readdir($dirHandle)))
			if ($file != '.' && $file != '..') {
				$tmpPath = $path . '/' . $file;
				if (is_dir($tmpPath))
					RemoveDir($tmpPath);
				elseif (!unlink($tmpPath))
					my_die(t9n("[RU]Не удалось удалить файл[EN]Cannot delete the file") . " '$tmpPath'.");
			}
		closedir($dirHandle);
		if (!rmdir($path))
			die(t9n("[RU]Не удалось удалить директорию '[EN]Couldn't drop folder '") . $path . "'.");
	} elseif (!unlink($path))
		my_die(t9n("[RU]Не удалось удалить файл '[EN]Couldn't drop file '") . $path . "'.");
}
function Construct_WHERE($key, $filter, $cur_typ, $join_req = 0, $ignore_tailed = FALSE)
{
	trace("Construct_WHERE for $key, filter: " . print_r($filter, true) . ", cur_typ: $cur_typ");
	global $z;
	$join = $join_req != 0;
	foreach ($filter as $f => $value) {
		$is_date = FALSE;
		if (substr($value, 0, 1) == "!") {
			$NOT      = "NOT";
			$NOT_EQ   = "!";
			$value    = substr($value, 1);
			$NOT_flag = TRUE;
		} else {
			$NOT      = $NOT_EQ = "";
			$NOT_flag = FALSE;
		}
		if (strpos($value, ".")) {
			$block = "." . strtolower(substr($value, 0, strpos($value, ".")));
			$len   = strlen($block);
			foreach ($GLOBALS["blocks"] as $block_id => $val)
				if (substr($block_id, -$len) == $block)
					if (isset($val["CUR_VARS"][strtolower(substr($value, strpos($value, ".") + 1))])) {
						$value               = $val["CUR_VARS"][strtolower(substr($value, strpos($value, ".") + 1))];
						$GLOBALS["NO_CACHE"] = "";
						break;
					}
		}
		$value = BuiltIn($value);
		if ($value == "%")
			$search_val = "IS " . ($NOT_flag ? "" : "NOT") . " NULL";
		elseif ((substr(trim(strtoupper($value)), 0, 3) == "IN(") && substr(trim($value), -1) == ")") {
			$in         = true;
			$value      = substr(trim($value), 3, -1);
			$search_val = "$NOT IN($value)";
		} else {
			$v = preg_match("/\[([^\[\]]+)\]/", $value) ? $value : "'" . addslashes($value) . "'";
			if (strpos($value, "%") === FALSE)
				$search_val = "$NOT_EQ=$v";
			else
				$search_val = "$NOT LIKE $v";
		}
		if (substr($value, 0, 1) == "@") {
			$value = (int) str_replace(" ", "", substr($value, 1));
			if ($key == $cur_typ)
				$GLOBALS["where"] .= " AND vals.id$NOT_EQ=$value ";
			else {
				if ($GLOBALS["REV_BT"][$key] == "ARRAY")
					$GLOBALS["distinct"] = "DISTINCT";
				if ($NOT_flag) {
					if ($GLOBALS["REV_BT"][$key] == "REFERENCE") {
						if ($join)
							$GLOBALS["join"] .= " LEFT JOIN ($z r$key CROSS JOIN $z a$key) ON r$key.up=vals.id AND a$key.t=" . $GLOBALS["REF_typs"][$key] . " AND r$key.t=a$key.id AND r$key.val='$join_req'";
						$GLOBALS["where"] .= " AND (a$key.id!=$value OR a$key.id IS NULL)";
					} else {
						if ($join)
							$GLOBALS["join"] .= " LEFT JOIN $z a$key ON a$key.up=vals.id AND a$key.t=$key";
						$GLOBALS["where"] .= " AND (a$key.id!=$value OR a$key.id IS NULL)";
					}
				} else {
					trace(" Check if GLOBALS[REV_BT][$key] " . $GLOBALS["REV_BT"][$key] . " == REFERENCE");
					if (isset($GLOBALS["REF_typs"][$key])) {
						if ($join)
							$GLOBALS["join"] .= " JOIN ($z r$key CROSS JOIN $z a$key) ON r$key.up=vals.id AND r$key.t=a$key.id AND r$key.val='$join_req' AND r$key.t=$value";
						$GLOBALS["where"] .= " AND a$key.id=$value";
					} else {
						if ($join)
							$GLOBALS["join"] .= " JOIN $z a$key ON a$key.up=vals.id AND a$key.id=$value";
						$GLOBALS["where"] .= " AND a$key.id=$value";
					}
				}
			}
			break;
		}
		trace("_ REV_BT: " . (isset($GLOBALS["REV_BT"][$key]) ? $GLOBALS["REV_BT"][$key] : "No [REV_BT][$key]"));
		if (isset($GLOBALS["REF_typs"][$key])) {
			if ($join)
				$GLOBALS["join"] .= " LEFT JOIN ($z r$key CROSS JOIN $z a$key) ON r$key.up=vals.id AND r$key.t=a$key.id AND r$key.val='$join_req' AND a$key.t=" . $GLOBALS["REF_typs"][$key];
			if ($NOT_flag)
				$GLOBALS["where"] .= " AND (a$key.val $search_val OR a$key.val IS NULL)";
			else
				$GLOBALS["where"] .= " AND a$key.val $search_val ";
		} else
			switch ($GLOBALS["REV_BT"][$key]) {
				case "CHARS":
				case "FILE":
				case "MEMO":
				case "HTML":
					if ($value == "%") {
						if ($join)
							$GLOBALS["join"] .= ($NOT_flag ? " LEFT" : "") . " JOIN $z a$key ON a$key.up=vals.id AND a$key.t=$key ";
						$GLOBALS["where"] .= " AND a$key.val $search_val ";
					} elseif (((mb_strlen($value) <= VAL_LIM) && (strpos($value, "%") === FALSE)) || $ignore_tailed) {
						if ($key == $cur_typ)
							$GLOBALS["where"] .= " AND vals.val $search_val ";
						else {
							if ($join)
								$GLOBALS["join"] .= " LEFT JOIN $z a$key ON a$key.up=vals.id AND a$key.t=$key ";
							if ($NOT_flag)
								$GLOBALS["where"] .= " AND (a$key.val $search_val OR a$key.val IS NULL)";
							else
								$GLOBALS["where"] .= " AND a$key.val $search_val ";
						}
					} elseif ((substr($value, 0, 1) != "%") && ((substr($value, 0, 1) != "_") || (strpos($value, "%") === FALSE))) {
						$GLOBALS["distinct"] = "DISTINCT";
						if (strpos($value, "%") === FALSE)
							$short_search_val = "$NOT_EQ='" . mb_substr($value, 0, VAL_LIM) . "'";
						else
							$short_search_val = "$NOT LIKE '" . mb_substr($value, 0, min(mb_strpos($value, '%', 0), mb_strpos($value, '%', 0))) . "%'";
						if ($key == $cur_typ) {
							if ($join)
								$GLOBALS["join"] .= " LEFT JOIN $z t$key ON t$key.up=vals.id AND t$key.t=0 " . " LEFT JOIN $z tp$key ON tp$key.up=t$key.up AND tp$key.t=0 AND tp$key.ord=t$key.ord+1 ";
							$GLOBALS["where"] .= " AND vals.val $short_search_val
  AND concat(CASE WHEN t$key.ord!=0 THEN '' ELSE vals.val END
	, COALESCE(t$key.val, ''), COALESCE(tp$key.val,'')) $search_val ";
						} else {
							if ($join)
								$GLOBALS["join"] .= " LEFT JOIN $z a$key ON a$key.up=vals.id AND a$key.t=$key
	LEFT JOIN $z t$key ON t$key.up=a$key.id AND t$key.t=0 " . " LEFT JOIN $z tp$key ON tp$key.up=t$key.up AND tp$key.t=0 AND tp$key.ord=t$key.ord+1 ";
							if ($NOT_flag)
								$GLOBALS["where"] .= " AND ((a$key.val $short_search_val 
	 AND concat(CASE WHEN t$key.ord!=0 THEN '' ELSE a$key.val END
	  , COALESCE(t$key.val, ''), COALESCE(tp$key.val,'')) $search_val)
	OR a$key.val IS NULL) ";
							elseif (strpos($value, "%") == strlen($value) - 1)
								$GLOBALS["where"] .= " AND a$key.val $short_search_val ";
							else
								$GLOBALS["where"] .= " AND a$key.val $short_search_val " . " AND concat(CASE WHEN t$key.ord!=0 THEN '' ELSE a$key.val END
	, COALESCE(t$key.val, ''), COALESCE(tp$key.val,'')) $search_val ";
						}
					} elseif ($key == $cur_typ) {
						if ($join) {
							$GLOBALS["distinct"] = "DISTINCT";
							$GLOBALS["join"] .= "LEFT JOIN $z t$key ON t$key.up=vals.id AND t$key.t=0 " . " LEFT JOIN $z tp$key ON tp$key.up=t$key.up AND tp$key.t=0 AND tp$key.ord=t$key.ord+1 ";
						}
						$GLOBALS["where"] .= " AND concat(vals.val, COALESCE(t$key.val, ''), COALESCE(tp$key.val,'')) $search_val ";
					} else {
						if ($join) {
							$GLOBALS["distinct"] = "DISTINCT";
							$GLOBALS["join"] .= " LEFT JOIN $z a$key ON a$key.up=vals.id AND a$key.t=$key
	LEFT JOIN $z t$key ON t$key.up=a$key.id AND t$key.t=0 " . " LEFT JOIN $z tp$key ON tp$key.up=t$key.up AND tp$key.t=0 AND tp$key.ord=t$key.ord+1 ";
						}
						if ($NOT_flag)
							$GLOBALS["where"] .= " AND (CONCAT(a$key.val, COALESCE(t$key.val, ''), COALESCE(tp$key.val,'')) $search_val 
   OR a$key.val IS NULL)";
						else
							$GLOBALS["where"] .= " AND CONCAT(a$key.val, COALESCE(t$key.val, ''), COALESCE(tp$key.val,'')) $search_val ";
					}
					break;
				case "ARRAY":
					$GLOBALS["distinct"] = "DISTINCT";
					if ($f == "F") {
						if ($join)
							$GLOBALS["join"] .= " LEFT JOIN $z a$key ON a$key.up=vals.id AND a$key.t=$key";
						if ($NOT_flag)
							$GLOBALS["where"] .= " AND (a$key.val $search_val OR a$key.val IS NULL)";
						else
							$GLOBALS["where"] .= " AND a$key.val $search_val ";
					} else if ((!isset($filter["TO"])) || (!isset($filter["FR"]))) {
						if ($join)
							$GLOBALS["join"] .= " LEFT JOIN $z a$key ON a$key.up=vals.id AND a$key.t=$key ";
						if ($value != "%") {
							if ($NOT_flag)
								$GLOBALS["where"] .= " AND (a$key.val$NOT_EQ='$value' OR a$key.val IS NULL)";
							else
								$GLOBALS["where"] .= " AND a$key.val='$value'";
						} else
							$GLOBALS["where"] .= " AND a$key.val $search_val ";
					} elseif (isset($filter["TO"]) && isset($filter["FR"])) {
						if ($value == 0)
							$value = "'$value'";
						else
							$value = (float) $value;
						if ($f == "FR") {
							if ($join)
								$GLOBALS["join"] .= " JOIN $z a$key ON a$key.up=vals.id AND a$key.t=$key ";
							$GLOBALS["where"] .= " AND a$key.val>=$value ";
						} elseif ($f == "TO")
							$GLOBALS["where"] .= " AND a$key.val<=$value ";
					}
					break;
				case "DATE":
					$is_date = TRUE;
				case "DATETIME":
					if ($value != "%")
						$value = Format_Val($GLOBALS["BT"][$GLOBALS["REV_BT"][$key]], $value);
				case "NUMBER":
				case "SIGNED":
					if ((float) str_replace(" ", "", $value) != 0)
						$value = str_replace(" ", "", $value);
					if ((!isset($filter["TO"])) || (!isset($filter["FR"]))) {
						if ($key == $cur_typ) {
							if (isset($in))
								$GLOBALS["where"] .= " AND vals.val $search_val ";
							elseif (strpos($value, "%") === FALSE)
								$GLOBALS["where"] .= " AND vals.val$NOT_EQ='$value' ";
							else
								$GLOBALS["where"] .= " AND vals.val $NOT LIKE '$value' ";
						} else {
							if ($join)
								$GLOBALS["join"] .= " LEFT JOIN $z a$key ON a$key.up=vals.id AND a$key.t=$key ";
							if ($value == "%")
								$GLOBALS["where"] .= " AND a$key.val $search_val ";
							elseif (strpos($value, "%") === FALSE) {
								if ($NOT_flag) {
									if (isset($in))
										$GLOBALS["where"] .= " AND (a$key.val $search_val OR a$key.val IS NULL)";
									else
										$GLOBALS["where"] .= " AND (a$key.val!='$value' OR a$key.val IS NULL) ";
								} else if (isset($in))
									$GLOBALS["where"] .= " AND a$key.val $search_val ";
								else
									$GLOBALS["where"] .= " AND a$key.val='$value' ";
							} elseif ($NOT_flag)
								$GLOBALS["where"] .= " AND (a$key.val NOT LIKE '$value' OR a$key.val IS NULL) ";
							else
								$GLOBALS["where"] .= " AND a$key.val LIKE '$value' ";
						}
					} else {
						if ($is_date)
							$value = "'$value'";
						elseif ((strpos($value, "[") === FALSE) && (strpos($value, "_") === FALSE))
							$value = (float) str_replace(" ", "", $value);
						if ($f == "FR") {
							if ($key == $cur_typ)
								$GLOBALS["where"] .= " AND vals.val>=$value ";
							else {
								if ($join)
									$GLOBALS["join"] .= " JOIN $z a$key ON a$key.up=vals.id AND a$key.t=$key ";
								$GLOBALS["where"] .= " AND a$key.val>=$value ";
							}
						} elseif ($f == "TO") {
							if ($key == $cur_typ)
								$GLOBALS["where"] .= " AND vals.val<=$value ";
							else
								$GLOBALS["where"] .= " AND a$key.val<=$value ";
						}
					}
					break;
				default:
					if ($key == $cur_typ)
						$GLOBALS["where"] .= " AND vals.val $search_val ";
					else {
						if ($join)
							$GLOBALS["join"] .= " LEFT JOIN $z a$key ON a$key.up=vals.id AND a$key.t=$key ";
						if ($NOT_flag)
							$GLOBALS["where"] .= " AND (a$key.val $search_val OR a$key.val IS NULL)";
						else
							$GLOBALS["where"] .= " AND a$key.val $search_val ";
					}
					break;
			}
	}
}
function Fetch_WHERE_for_mask($t, $val, $mask)
{
	if (isset($GLOBALS["where"]))
		unset($GLOBALS["where"]);
	Construct_WHERE($t, array(
		"F" => $mask
	), 1, FALSE, TRUE);
	return str_replace("a$t.val", is_null($val) ? "NULL" : "'" . addslashes($val) . "'", substr($GLOBALS["where"], 5));
}
function Val_barred_by_mask($t, $val)
{
	if (isset($GLOBALS["GRANTS"]["masklevel"][$t])) {
		trace("Mask set for $t");
		foreach ($GLOBALS["GRANTS"]["masklevel"][$t] as $grant => $mask) {
			trace(" Mask: $grant => $mask");
			$mask = Fetch_WHERE_for_mask($t, $val, $mask);
			if ($row = mysqli_fetch_array(Exec_sql("SELECT $mask", "Apply mask")))
				if ($row[0])
					return ($grant != "WRITE");
		}
		if ($grant == "WRITE")
			return TRUE;
	}
	return FALSE;
}
function Check_Val_granted($t, $val)
{
	if (isset($GLOBALS["GRANTS"]["mask"][$t])) {
		foreach ($GLOBALS["GRANTS"]["mask"][$t] as $mask) {
			if (!strlen($val))
				if ($mask == "!%")
					return;
				else
					continue;
			$mask = Fetch_WHERE_for_mask($t, $val, $mask);
			if ($row = mysqli_fetch_array(Exec_sql("SELECT $mask", "Apply granted mask")))
				if ($row[0])
					return;
		}
		my_die(t9n("[RU]У вас нет доступа к этому объекту ($val))![EN]You do not have this object granted ($val))") . " ($t)");
	}
}
function Check_Types_Grant($fatal = TRUE)
{
	if ($GLOBALS["GLOBAL_VARS"]["user"] == "admin")
		return "WRITE";
	elseif (isset($GLOBALS["GRANTS"][0]))
		if (($GLOBALS["GRANTS"][0] == "READ") || ($GLOBALS["GRANTS"][0] == "WRITE"))
			return $GLOBALS["GRANTS"][0];
	if ($fatal)
		die(t9n("[RU]У вас нет прав на редактирование и просмотр типов ([EN]You do not have the grant to view and edit the metadata (") . (isset($GLOBALS["GRANTS"][0]) ? isset($GLOBALS["GRANTS"][0]) : "") . ").");
	return "READ";
}
function IsOccupied($id)
{
	global $z;
	if ($row = mysqli_fetch_array(Exec_sql("SELECT 1 FROM $z WHERE id=$id", "Check if ID is occupied")))
		return true;
	return false;
}
function my_die($msg)
{
	if (isset($GLOBALS["TRACE"])) {
		print_r($GLOBALS);
		print($GLOBALS["TRACE"]);
	}
	if (isApi())
		die("[{\"error\":\"$msg\"}]");
	else
		die($msg);
}
function Check_Grant($id, $t = 0, $grant = "WRITE", $fatal = TRUE)
{
	global $z;
	if ($GLOBALS["GLOBAL_VARS"]["user"] == "admin")
		return TRUE;
	elseif (isset($GLOBALS["GRANTS"][$t]) && ($t != 0)) {
		trace("  Explicit grant to the Object $t: " . $GLOBALS["GRANTS"][$t]);
		if (($GLOBALS["GRANTS"][$t] == $grant) || ($GLOBALS["GRANTS"][$t] == "WRITE"))
			return TRUE;
		if (!$fatal)
			return FALSE;
		my_die(t9n("[RU]У вас нет доступа к реквизиту объекта $id, $t (" . $GLOBALS["GRANTS"][$t] . ") или его родителю " . $id . " (" . $GLOBALS["GRANTS"][$id] . "). Ваш глобальный доступ: '" . "[EN]The object is not granted  $id, $t (" . $GLOBALS["GRANTS"][$t] . ") neither its parent " . $id . " (" . $GLOBALS["GRANTS"][$id] . "). The access level is: '") . $GLOBALS["GRANTS"][1] . "'");
	} elseif (isset($GLOBALS["GRANTS"][$id])) {
		if (($GLOBALS["GRANTS"][$id] == $grant) || ($GLOBALS["GRANTS"][$id] == "WRITE"))
			return TRUE;
		if (!$fatal)
			return FALSE;
		my_die(t9n("[RU]У вас нет доступа к реквизиту объекта $id, $t (" . $GLOBALS["GRANTS"][$t] . ") или его родителю " . $id . " (" . $GLOBALS["GRANTS"][$id] . "). Ваш глобальный доступ: '" . "[EN]The object is not granted  $id, $t (" . $GLOBALS["GRANTS"][$t] . ") neither its parent " . $id . " (" . $GLOBALS["GRANTS"][$id] . "). The access level is: '") . $GLOBALS["GRANTS"][1] . "'");
	} elseif ($t == 0)
		$data_set = Exec_sql("SELECT obj.t, COALESCE(par.t, 1) par_typ, COALESCE(par.id, 1) par_id, COALESCE(arr.id, -1) arr
FROM $z obj LEFT JOIN $z par ON obj.up>1 AND par.id=obj.up 
 LEFT JOIN $z arr ON arr.up=par.t AND arr.t=obj.t
WHERE obj.id=$id LIMIT 1", "Get Object info by ID");
	elseif ($id != 1)
		$data_set = Exec_sql("SELECT obj.t, COALESCE(par.t, 1) par_typ, COALESCE(par.id, 1) par_id, COALESCE(arr.id, -1) arr
FROM $z obj JOIN $z par ON obj.up>1 AND (par.t=obj.up OR par.id=obj.up)
 LEFT JOIN $z arr ON arr.up=par.t AND arr.t=obj.t
WHERE par.id=$id AND (obj.t=$t OR obj.id=$t) LIMIT 1", "Get Object info by Parent&Type");
	else
		$data_set = Exec_sql("SELECT $t t, 1 par_typ, 1 par_id, -1 arr", "Get 1st level Object");
	if ($row = mysqli_fetch_array($data_set)) {
		if (isset($GLOBALS["GRANTS"][$row["t"]])) {
			if (($GLOBALS["GRANTS"][$row["t"]] == $grant) or ($GLOBALS["GRANTS"][$row["t"]] == "WRITE"))
				return TRUE;
		} elseif (isset($GLOBALS["GRANTS"][$row["arr"]])) {
			if (($GLOBALS["GRANTS"][$row["arr"]] == $grant) or ($GLOBALS["GRANTS"][$row["arr"]] == "WRITE"))
				return TRUE;
		} elseif (isset($GLOBALS["GRANTS"][$row["par_typ"]])) {
			if (($GLOBALS["GRANTS"][$row["par_typ"]] == $grant) or ($GLOBALS["GRANTS"][$row["par_typ"]] == "WRITE"))
				return TRUE;
		} elseif (isset($GLOBALS["GRANTS"][$row["par_id"]])) {
			if (($GLOBALS["GRANTS"][$row["par_id"]] == $grant) or ($GLOBALS["GRANTS"][$row["par_id"]] == "WRITE"))
				return TRUE;
		} elseif ($row["par_id"] > 1)
			if (Check_Grant($row["par_id"], 0, $grant, FALSE))
				return TRUE;
	}
	if ($fatal)
		my_die(t9n("[RU]У вас нет доступа к реквизиту объекта $id, $t (" . $GLOBALS["GRANTS"][$row["t"]] . ") или его родителю " . $row["par_id"] . " (" . $GLOBALS["GRANTS"][$row["par_typ"]] . ")! Ваш глобальный доступ: '" . $GLOBALS["GRANTS"][1] . "'.[EN]The object is not granted $id, $t (" . $GLOBALS["GRANTS"][$row["t"]] . ")neither its parent " . $row["par_id"] . " (" . $GLOBALS["GRANTS"][$row["par_typ"]] . ")! The access level is: '" . $GLOBALS["GRANTS"][1] . "'"));
	return FALSE;
}
function Grant_1level($id)
{
	if ($GLOBALS["GLOBAL_VARS"]["user"] == "admin")
		return TRUE;
	elseif (isset($GLOBALS["GRANTS"][$id])) {
		if (($GLOBALS["GRANTS"][$id] == "READ") || ($GLOBALS["GRANTS"][$id] == "WRITE"))
			return $GLOBALS["GRANTS"][$id];
	} elseif (isset($GLOBALS["GRANTS"][1]))
		if (($GLOBALS["GRANTS"][1] == "READ") || ($GLOBALS["GRANTS"][1] == "WRITE"))
			return $GLOBALS["GRANTS"][1];
	return FALSE;
}
function Validate_Token()
{
	global $z, $blocks, $dumpAPI;
	$GLOBALS["GRANTS"] = array(); {
		$data_set = Exec_sql("SELECT u.id, u.val, role_def.id r, role_def.val role, xsrf.val xsrf FROM $z tok, $z u
LEFT JOIN ($z r CROSS JOIN $z role_def) ON r.up=u.id AND role_def.id=r.t AND role_def.t=" . ROLE . " LEFT JOIN $z xsrf ON xsrf.up=u.id AND xsrf.t=" . XSRF . " WHERE u.t=" . USER . " AND tok.up=u.id AND u.val='$z' AND tok.t=" . TOKEN, "Validate token");
		if ($row = mysqli_fetch_array($data_set)) {
			$GLOBALS["GLOBAL_VARS"]["user"]    = strtolower($row["val"]);
			$GLOBALS["GLOBAL_VARS"]["role"]    = strtolower($row["role"]);
			$GLOBALS["GLOBAL_VARS"]["user_id"] = $row["id"];
			$xsrf                              = $row["xsrf"];
			if (!$row["r"])
				my_die(t9n("[RU]Пользователю " . $GLOBALS["GLOBAL_VARS"]["user"] . " не задана роль" . "[EN]No role assigned to user " . $GLOBALS["GLOBAL_VARS"]["user"]));
			Exec_sql("UPDATE $z SET val=" . microtime(TRUE) . " WHERE up=" . $row["id"] . " AND t=" . ACTIVITY, "Update activity time", FALSE);
			$data_set = Exec_sql("SELECT gr.val object, CASE WHEN mask.t=" . MASK . " THEN mask.val ELSE '' END mask
  , CASE WHEN lev.t=" . LEVEL . " THEN lev.val ELSE '' END level
  , CASE WHEN lev.t!=" . LEVEL . " THEN lev_def.val ELSE '' END mass
 FROM $z gr JOIN $z mask ON mask.up=gr.id
  LEFT JOIN ($z lev CROSS JOIN $z lev_def) ON lev.id=mask.t AND lev_def.id=lev.t AND mask.t!=" . MASK . " WHERE gr.up=" . $row["r"], "Get grants");
			while ($row = mysqli_fetch_array($data_set)) {
				if (substr($row["mask"], 0, 1) == "[") {
					$v = BuiltIn($row["mask"]);
					if ($v == $row["mask"]) {
						$attrs = substr($v, 1, strlen($v) - 2);
						Get_block_data($attrs);
						if (isset($blocks[$attrs][strtolower($attrs)]))
							if (count($blocks[$attrs][strtolower($attrs)]))
								$v = array_shift($blocks[$attrs][strtolower($attrs)]);
					}
				} else
					$v = "" . $row["mask"];
				if (strlen($row["mask"]) && strlen($row["level"]))
					$GLOBALS["GRANTS"]["masklevel"][$row["object"]][$row["level"]] = $v;
				elseif (strlen($row["level"]))
					$GLOBALS["GRANTS"][$row["object"]] = $row["level"];
				elseif (strlen($row["mask"]))
					$GLOBALS["GRANTS"]["mask"][$row["object"]][] = $v;
				elseif (strlen($row["mass"]))
					$GLOBALS["GRANTS"][$row["mass"]][$row["object"]] = "";
			}
		} else
			die("No ideav user found - restore the user in the DB");
		$GLOBALS["tzone"] = isset($_COOKIE["tzone"]) ? $_COOKIE["tzone"] : 0;
	}
	$GLOBALS["GLOBAL_VARS"]["xsrf"]  = isset($xsrf) ? $xsrf : xsrf($_SERVER["REMOTE_ADDR"], "guest");
	$GLOBALS["GLOBAL_VARS"]["token"] = 'ideav';
	return isset($GLOBALS["GLOBAL_VARS"]["user"]);
}
function Get_Align($typ)
{
	switch ($GLOBALS["REV_BT"][$typ]) {
		case "PWD":
		case "DATE":
		case "BOOLEAN":
			return "CENTER";
		case "NUMBER":
		case "SIGNED":
			return "RIGHT";
	}
	return "LEFT";
}
function Format_Val($typ, $val)
{
	global $z;
	if ($val != "NULL") {
		if (!isset($GLOBALS["REV_BT"][$typ]))
			if ($typ != 0)
				if ($row = mysqli_fetch_array(Exec_sql("SELECT t FROM $z WHERE id=$typ", "Get Typ for Format")))
					if (isset($GLOBALS["REV_BT"][$row["t"]]))
						$GLOBALS["REV_BT"][$typ] = $GLOBALS["REV_BT"][$row["t"]];
		if (isset($GLOBALS["REV_BT"][$typ]))
			switch ($GLOBALS["REV_BT"][$typ]) {
				case "DATE":
					if (($val != "") && (substr($val, 0, 1) != "[") && (substr($val, 0, 10) != "_request_.")) {
						if (preg_match("/^([0-9]{4})[-\/\.]?([0-9]{2})[-\/\.]?([0-9]{2})$/", $val, $date))
							$val = $date[1] . $date[2] . $date[3];
						else {
							$v  = explode("/", str_replace(".", "/", str_replace(",", "/", $val)));
							$dy = (isset($v[2])) ? (int) ((strlen($v[2]) == 4) ? $v[2] : 2000 + $v[2]) : date("Y");
							$dm = isset($v[1]) ? (int) $v[1] : date("m");
							$dd = (int) $v[0];
							if (!checkdate($dm, $dd, $dy))
								$GLOBALS["warning"] .= t9n("[RU]Неверная дата[EN]Wrong date") . " $val!<br>";
							$val = $dy . substr("0" . $dm, -2) . substr("0" . $dd, -2);
						}
					}
					break;
				case "NUMBER":
					$v = (int) str_replace(",", ".", str_replace(" ", "", $val));
					if ($v != 0)
						$val = $v;
					break;
				case "SIGNED":
					$v = (float) str_replace(",", ".", str_replace(array(
						" ",
						chr(0xC2) . chr(0xA0)
					), "", $val));
					if ($v != 0)
						$val = $v;
					break;
				case "DATETIME":
					if (($val != "") && (substr($val, 0, 1) != "[")) {
						if ($val > 10000)
							$val = $val - $GLOBALS["tzone"];
						elseif (strtotime($val) < 10000)
							$val = strtotime(Format_Val($GLOBALS["BT"]["DATE"], $val)) - $GLOBALS["tzone"];
						else
							$val = strtotime($val) - $GLOBALS["tzone"];
					}
					break;
			}
	}
	return $val;
}
function Format_Val_View($typ, $val, $id = 0)
{
	global $z;
	if ($val != "NULL" && isset($GLOBALS["REV_BT"][$typ]))
		switch ($GLOBALS["REV_BT"][$typ]) {
			case "DATE":
				if ($val != "") {
					if (strlen($val) > 8)
						$val = date("d.m.Y", $val + $GLOBALS["tzone"]);
					else
						$val = substr($val, 6, 2) . "." . substr($val, 4, 2) . "." . substr($val, 0, 4);
				}
				break;
			case "DATETIME":
				$val = date("d.m.Y H:i:s", (int) $val + $GLOBALS["tzone"]);
				break;
			case "BOOLEAN":
				if ($val != "")
					$val = "X";
				break;
			case "NUMBER":
				if ($val != 0)
					$val = number_format($val, 0, "", "");
				break;
			case "FILE":
				$val = "<a target=\"_blank\" href=\"/" . GetSubdir($id) . "/" . GetFilename($id) . "." . substr(strrchr($val, '.'), 1) . "\">$val</a>";
				break;
			case "SIGNED":
				if ($val == "")
					break;
				$v   = explode(".", trim($val));
				$val = trim(number_format($v[0], 0, ".", "") . "." . substr((isset($v[1]) ? $v[1] : "") . "00", 0, max(2, strlen((isset($v[1]) ? $v[1] : 0)))));
				break;
			case "PATH":
				$id  = substr($val, 0, strpos($val, ":"));
				$val = "/" . GetSubdir($id) . "/" . GetFilename($id) . "." . substr(strrchr($val, '.'), 1);
				break;
			case "GRANT":
				if ($val == 0)
					return TYPE_EDITOR;
				if ($val == 1)
					return ALL_OBJECTS;
				if ($val == 10)
					return FILES;
			case "REPORT_COLUMN":
				if ($val == "0")
					$GLOBALS["REP_COLS"][$val] = CUSTOM_REP_COL;
				elseif ($val == 0)
					$GLOBALS["REP_COLS"][$val] = $val;
				elseif (!isset($GLOBALS["REP_COLS"][$val])) {
					$sql      = "SELECT a.id, a.val, reqs.id req_id, refs.val req_val, reqs.val attr, ref_vals.val ref_val
FROM $z a LEFT JOIN ($z reqs CROSS JOIN $z refs) ON refs.id=reqs.t AND reqs.up=a.id
LEFT JOIN $z ref_vals ON ref_vals.id=refs.t AND ref_vals.id!=ref_vals.t
WHERE a.id=COALESCE((SELECT up FROM $z WHERE id=$val AND up!=0), $val)";
					$data_set = Exec_sql($sql, "Get Report Columns for View");
					while ($row = mysqli_fetch_array($data_set)) {
						if (!isset($GLOBALS["REP_COLS"][$row["id"]]))
							$GLOBALS["REP_COLS"][$row["id"]] = $row["val"];
						if (!isset($GLOBALS["REP_COLS"][$row["req_id"]]))
							if (strlen($row["ref_val"])) {
								$alias = FetchAlias($row["attr"], $row["ref_val"]);
								if ($alias == $row["ref_val"])
									$GLOBALS["REP_COLS"][$row["req_id"]] = $row["val"] . " -> " . $row["ref_val"];
								else
									$GLOBALS["REP_COLS"][$row["req_id"]] = $row["val"] . " -> $alias (" . $row["ref_val"] . ")";
							} else
								$GLOBALS["REP_COLS"][$row["req_id"]] = $row["val"] . " -> " . $row["req_val"];
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
function Get_file($file, $fatal = TRUE)
{
	global $z;
	if (!isset($file))
		die("Set file name!");
	if (is_file($_SERVER['DOCUMENT_ROOT'] . "/templates/custom/$z/$file"))
		$file = $_SERVER['DOCUMENT_ROOT'] . "/templates/custom/$z/$file";
	elseif (is_file($_SERVER['DOCUMENT_ROOT'] . "/templates/$file"))
		$file = $_SERVER['DOCUMENT_ROOT'] . "/templates/$file";
	elseif ($fatal)
		die("File [$file] does not exist!");
	else
		return "";
	if (!($fh = fopen($file, "r")))
		die(t9n("[RU]Не удается открыть файл:[$file][EN]Cannot open file: [$file]"));
	$file_text = fread($fh, filesize($file));
	fclose($fh);
	return $file_text;
}
function Get_tail($id, $v)
{
	global $z;
	if ($v == "")
		return "";
	$data_set = Exec_sql("SELECT id, val FROM $z WHERE up=$id AND t=0 ORDER BY ord", "Get Tail");
	while ($row = mysqli_fetch_array($data_set)) {
		$val_length = mb_strlen($v) % VAL_LIM;
		if ($val_length)
			$v .= str_repeat(" ", VAL_LIM - $val_length);
		$v .= $row["val"];
	}
	return $v;
}
function Delete($id, $root = TRUE)
{
	global $z;
	$children = exec_sql("SELECT id FROM $z WHERE up=$id", "Get children");
	if ($child = mysqli_fetch_array($children)) {
		do {
			Delete($child["id"], FALSE);
		} while ($child = mysqli_fetch_array($children));
		Exec_sql("DELETE FROM $z WHERE up=$id", "Delete reqs");
	}
	if ($root)
		Exec_sql("DELETE FROM $z WHERE id=$id", "Delete obj");
}
function BatchDelete($id, $root = TRUE)
{
	global $z;
	if ($id === "") {
		if (isset($GLOBALS["BatchUps"])) {
			Exec_sql("DELETE FROM $z WHERE up IN(" . $GLOBALS["BatchUps"] . ")", "Flush ups");
			unset($GLOBALS["BatchUps"]);
		}
		if (isset($GLOBALS["BatchIDs"])) {
			Exec_sql("DELETE FROM $z WHERE id IN(" . $GLOBALS["BatchIDs"] . ")", "Flush objs");
			unset($GLOBALS["BatchIDs"]);
		}
		return;
	}
	trace(" get children for $id");
	$children = exec_sql("SELECT del.id, MIN(child.up) child FROM $z del LEFT JOIN $z child ON child.up=del.id WHERE del.up=$id GROUP BY del.id", "Get children for batch");
	if ($child = mysqli_fetch_array($children)) {
		trace(" $id has " . mysqli_num_rows($children) . " children");
		do {
			if ($child["child"] > 0) {
				BatchDelete($child["id"], false);
				$GLOBALS["BatchUps"] = isset($GLOBALS["BatchUps"]) ? $GLOBALS["BatchUps"] . "," . $child["id"] : $child["id"];
				if (strlen($GLOBALS["BatchUps"]) > 10000)
					BatchDelete("");
			}
		} while ($child = mysqli_fetch_array($children));
		$GLOBALS["BatchUps"] = isset($GLOBALS["BatchUps"]) ? $GLOBALS["BatchUps"] . ",$id" : $id;
		if (strlen($GLOBALS["BatchUps"]) > 10000)
			BatchDelete("");
	}
	if ($root) {
		$GLOBALS["BatchIDs"] = isset($GLOBALS["BatchIDs"]) ? $GLOBALS["BatchIDs"] . ",$id" : $id;
		if (strlen($GLOBALS["BatchIDs"]) > 10000)
			BatchDelete("");
	}
}
function BuiltIn($par)
{
	switch ($par) {
		case "[TODAY]":
			return date("d.m.Y", time() + $GLOBALS["tzone"]);
		case "[NOW]":
			return date("d.m.Y H:i:s", time() + $GLOBALS["tzone"]);
		case "[YESTERDAY]":
			return date("d.m.Y", time() - 86400 + $GLOBALS["tzone"]);
		case "[TOMORROW]":
			return date("d.m.Y", time() + 86400 + $GLOBALS["tzone"]);
		case "[MONTH_AGO]":
			return date("d.m.Y", strtotime("-1 months") + $GLOBALS["tzone"]);
		case "[USER]":
			return $GLOBALS["GLOBAL_VARS"]["user"];
		case "[USER_ID]":
			return $GLOBALS["GLOBAL_VARS"]["user_id"];
		case "[TSHIFT]":
			return $GLOBALS["tzone"];
		case "[REMOTE_ADDR]":
			return $_SERVER["REMOTE_ADDR"];
		case "[HTTP_USER_AGENT]":
			return $_SERVER["HTTP_USER_AGENT"];
		case "[HTTP_REFERER]":
			return $_SERVER["HTTP_REFERER"];
		case "[HTTP_HOST]":
			return $_SERVER["HTTP_HOST"];
	}
	return $par;
}
function MaskDelimiters($v)
{
	return str_replace(";", "\;", str_replace(":", "\:", str_replace("\\", "\\\\", $v)));
}
function UnMaskDelimiters($v)
{
	return str_replace("\;", ";", str_replace("\:", ":", str_replace("\\\\", "\\", UnHideDelimiters($v))));
}
function HideDelimiters($v)
{
	return str_replace("\;", "%3B", str_replace("\:", "%3A", str_replace("\\\\", "%5C", $v)));
}
function UnHideDelimiters($v)
{
	return str_replace("%3B", "\;", str_replace("%3A", "\:", str_replace("%5C", "\\\\", $v)));
}
function Export_header($id, $parent = 0)
{
	global $z;
	if (!isset($GLOBALS["local_struct"][$id])) {
		$GLOBALS["parents"][$id] = $parent;
		$data_set                = Exec_sql("SELECT CASE WHEN length(obj.val)=0 THEN obj.id ELSE obj.t END t, CASE WHEN length(obj.val)=0 THEN obj.t ELSE obj.val END val
					  , req.id, req.t req_t, refr.val req, refr.t ref_t, req.val attr, base.t base_t, arr.id arr, linx.i, obj.ord uniq
FROM $z obj LEFT JOIN ($z req CROSS JOIN $z refr CROSS JOIN $z base) ON req.up=obj.id AND refr.id=req.t AND base.id=refr.t
 LEFT JOIN $z arr ON arr.up=req.t AND arr.t!=0 AND arr.ord=1
 CROSS JOIN (SELECT count(1) i FROM $z WHERE up=0 AND t=$id) linx
WHERE obj.id=$id ORDER BY req.ord", "Get Obj structure");
		while ($row = mysqli_fetch_array($data_set)) {
			if (!isset($GLOBALS["local_struct"][$id])) {
				$GLOBALS["local_struct"][$id][0] = "$id:" . MaskDelimiters($row["val"]) . (isset($GLOBALS["REV_BT"][$row["t"]]) ? ":" . $GLOBALS["REV_BT"][$row["t"]] : "") . ($row["uniq"] == "1" ? ":unique" : "");
				$GLOBALS["base"][$id]            = $row["t"];
				if ($row["i"])
					$GLOBALS["linx"][$id] = "";
			}
			if ($row["req_t"]) {
				if ($row["ref_t"] != $row["base_t"]) {
					trace("add link: " . $row["id"] . " -> " . $row["req_t"]);
					$GLOBALS["local_struct"][$id][$row["id"]] = "ref:" . $row["id"] . ":" . $row["req_t"] . ($row["attr"] ? ":" . MaskDelimiters($row["attr"]) : "");
					if (!isset($GLOBALS["local_struct"][$row["req_t"]]))
						Export_header($row["req_t"], $id);
					if (!isset($GLOBALS["local_struct"][$row["ref_t"]]))
						Export_header($row["ref_t"], $id);
					$GLOBALS["refs"][$row["id"]] = $row["ref_t"];
				} elseif ($row["arr"]) {
					$GLOBALS["local_struct"][$id][$row["id"]] = "arr:" . $row["req_t"] . ($row["attr"] ? ":" . MaskDelimiters($row["attr"]) : "");
					if (!isset($GLOBALS["local_struct"][$row["req_t"]])) {
						Export_header($row["req_t"], $id);
						$GLOBALS["arrays"][$row["req_t"]] = "";
					}
				} else {
					$GLOBALS["local_struct"][$id][$row["id"]] = MaskDelimiters($row["req"]) . ":" . $GLOBALS["REV_BT"][$row["ref_t"]] . ($row["attr"] ? ":" . MaskDelimiters($row["attr"]) : "");
					if ($GLOBALS["REV_BT"][$row["ref_t"]] == "PWD")
						$GLOBALS["pwds"][$row["id"]] = "";
					$GLOBALS["base"][$row["id"]] = $row["base_t"];
				}
			}
		}
	}
	$head_str = "";
	if (is_array($GLOBALS["local_struct"]))
		foreach ($GLOBALS["local_struct"] as $value)
			$head_str .= implode(";", $value) . ";\r\n";
	return $head_str;
}
function Export_reqs($id, $obj, $val, $ref = "")
{
	global $z;
	$str = $children = $refs = "";
	if (!isset($GLOBALS["data"][$obj])) {
		$reqs     = array();
		$data_set = Exec_sql("SELECT DISTINCT obj.id, obj.t, obj.val, obj.ord, req.t req_t, req.val req_val, req.up rup, tail.up, par.up ref
FROM $z obj LEFT JOIN $z tail ON tail.t=0 AND tail.up=obj.id AND tail.ord=0
 LEFT JOIN $z req ON req.id=obj.t LEFT JOIN $z par ON par.id=req.up
WHERE obj.up=$obj ORDER BY obj.ord", "Get Obj data $id");
		while ($row = mysqli_fetch_array($data_set))
			if (($row["rup"] != $id) && ($row["rup"] != 0)) {
				$reqs[$row["val"]] = $row["t"];
				$refs .= Export_reqs($row["req_t"], $row["t"], MaskDelimiters($row["req_val"]), $row["ref"] == 1 ? $row["val"] . ":" : 0);
			} elseif (isset($GLOBALS["arrays"][$row["t"]]))
				$children .= Export_reqs($row["t"], $row["id"], MaskDelimiters($row["val"]));
			elseif (!isset($GLOBALS["pwds"][$row["t"]]))
				$reqs[$row["t"]] = MaskDelimiters($row["up"] ? Get_tail($row["up"], $row["val"]) : $row["val"]);
		foreach ($GLOBALS["local_struct"][$id] as $key => $value)
			if ($key == 0)
				$str = MaskDelimiters($val) . ";";
			else
				$str .= isset($reqs[$key]) ? $reqs[$key] . ";" : ";";
		if (isset($GLOBALS["arrays"][$id]) || !isset($GLOBALS["linx"][$id]) || (($GLOBALS["id"] == $id) && ($_REQUEST["F_U"] > 1)))
			$str = "$id::$str\r\n";
		else {
			$str                   = "$ref$id:$obj:$str\r\n";
			$GLOBALS["data"][$obj] = "";
		}
	}
	return $refs . $str . $children;
}
function isRef($id, $par, $typ)
{
	if (isset($GLOBALS["STORED_REPS"][$id]["ref_typ"][$typ]))
		return $GLOBALS["STORED_REPS"][$id]["ref_typ"][$typ];
	return false;
}
function Compile_Report($id, $exe = TRUE, $check = FALSE)
{
	global $blocks, $obj, $z, $args;
	if (!isset($GLOBALS["STORED_REPS"][$id]["sql"])) {
		$GLOBALS["STORED_REPS"][$id]["params"] = $GLOBALS["STORED_REPS"][$id] = array();
		if ($row = mysqli_fetch_array(Exec_sql("SELECT val FROM $z WHERE id=$id", "Get Report Header")))
			$GLOBALS["STORED_REPS"][$id]["header"] = $row["val"];
		else
			die_info("Report #$id was not found");
		if ($check)
			Check_Val_granted(REPORT, $row["val"]);
		$tables     = $conds = $field_names = $joinedOn = $GLOBALS["CONDS"] = $GLOBALS["STORED_REPS"][$id][REP_JOIN] = array();
		$s          = "_";
		$tailed     = " CHARS MEMO FILE HTML";
		$aggr_funcs = array(
			"AVG",
			"COUNT",
			"MAX",
			"MIN",
			"SUM"
		);
		$distinct   = "";
		$fieldsAll  = $displayVal = $fieldsName = $displayName = $groupBy = array();
		$joined     = array();
		if ($exe) {
			$p  = "a";
			$pi = "i";
			$pr = "r";
			$pv = "v";
			$pu = "u";
		} else {
			$p  = "a$id" . "_";
			$pi = "i$id" . "_";
			$pr = "r$id" . "_";
			$pv = "v$id" . "_";
			$pu = "u$id" . "_";
		}
		$data_set = Exec_sql("SELECT rep.id up, rep.ord, col_def.up par, col_def.id typ, def_typ.id refr, COALESCE(def_typ.t, def.t, col_def.t) base
, CASE WHEN cols.t=0 THEN rep.t ELSE COALESCE(col_typ.t, cols.t, rep.t) END col
, CASE WHEN rep.t=" . REP_COLS . " THEN cols.id ELSE '' END id
, CASE WHEN cols.t=0 THEN (CASE WHEN cols.ord=0 THEN CONCAT(rep.val, cols.val) ELSE cols.val END)
ELSE COALESCE(col_typ.val, cols.val, rep.val) END val
, CASE WHEN cols.t IS NULL AND col_def.id IS NULL THEN NULL WHEN col_def.val IS NULL THEN rep.ord WHEN req_def.val IS NULL THEN col_def.val
WHEN def_typ.id=def_typ.t THEN CONCAT(req_def.val, ' -> ', def.val) ELSE req_def.val END name
, CASE WHEN def_typ.id!=def_typ.t THEN col_def.val END mask, def_typ.val ref_name
, rep.t jn, COALESCE(cols.val,'') jnon
FROM $z rep LEFT JOIN $z cols ON cols.up=rep.id 
LEFT JOIN $z col_typ ON col_typ.id=cols.t AND rep.t=" . REP_COLS . " AND col_typ.up!=" . REP_COLS . " LEFT JOIN $z col_def ON col_def.id=rep.val AND (rep.t=" . REP_COLS . " OR rep.t=" . REP_JOIN . ")" . " LEFT JOIN $z req_def ON col_def.up!=0 AND req_def.id=col_def.up
LEFT JOIN $z def ON col_def.up!=0 AND def.id=col_def.t
LEFT JOIN $z def_typ ON def.id!=def.t AND def_typ.id=def.t
WHERE rep.up=$id ORDER BY rep.ord", "Get the Report Params & Columns");
		while ($row = mysqli_fetch_array($data_set))
			if ($row["jn"] == REP_JOIN)
				$GLOBALS["STORED_REPS"][$id][REP_JOIN][$row["par"] > 0 ? $row["par"] : ($row["typ"] > 0 ? $row["typ"] : $row["up"])][$row["col"]] = strlen($row["jnon"]) >= VAL_LIM ? Get_tail($row["id"], $row["jnon"]) : $row["jnon"];
			elseif ($row["base"] || $row["id"]) {
				if (isset($row["mask"])) {
					$alias = FetchAlias($row["mask"], $row["ref_name"]);
					if ($alias == $row["ref_name"])
						$GLOBALS["STORED_REPS"][$id]["head"][$row["ord"]] = $row["name"] . " -> $alias";
					else
						$GLOBALS["STORED_REPS"][$id]["head"][$row["ord"]] = $row["name"] . " -> $alias (" . $row["ref_name"] . ")";
				} else
					$GLOBALS["STORED_REPS"][$id]["head"][$row["ord"]] = $row["name"];
				$GLOBALS["STORED_REPS"][$id]["types"][$row["ord"]]   = isset($row["typ"]) ? $row["typ"] : "";
				$GLOBALS["STORED_REPS"][$id]["columns"][$row["ord"]] = $row["up"];
				if ($row["par"])
					$GLOBALS["STORED_REPS"]["parents"][$row["typ"]] = $row["par"];
				if ($row["refr"])
					$GLOBALS["STORED_REPS"][$id]["refs"][$row["ord"]] = $row["refr"];
				$GLOBALS["STORED_REPS"][$id][$row["col"]][$row["ord"]] = mb_strlen($row["val"]) >= VAL_LIM ? Get_tail($row["id"], $row["val"]) : trim($row["val"]);
				if (!isset($GLOBALS["REV_BT"][$row["typ"]]) && $row["typ"])
					$GLOBALS["REV_BT"][$row["typ"]] = $GLOBALS["basics"][$row["base"]];
			} elseif (isset($GLOBALS["STORED_REPS"][$id]["params"][$row["col"]]))
				$GLOBALS["STORED_REPS"][$id]["params"][$row["col"]] .= $row["val"];
			else
				$GLOBALS["STORED_REPS"][$id]["params"][$row["col"]] = $row["val"];
		$GLOBALS["STORED_REPS"][$id]["columns_flip"] = array_flip($GLOBALS["STORED_REPS"][$id]["columns"]);
		if (isset($_REQUEST["SELECT"]) && $exe) {
			$i      = count($GLOBALS["STORED_REPS"][$id]["columns"]);
			$select = explode(",", str_replace("\,", "%2c", $_REQUEST["SELECT"]));
			trace("Dynamic select: " . print_r($select, TRUE));
			foreach ($select as $k => $v) {
				$f = explode(":", str_replace("\:", "%3a", $v));
				if (!isset($GLOBALS["STORED_REPS"][$id]["columns_flip"][$f[0]])) {
					$i++;
					if (strlen($f[0]))
						$f[0] = str_replace("%2c", ",", str_replace("%3a", ":", $f[0]));
					else
						$f[0] = "''";
					$GLOBALS["STORED_REPS"][$id]["types"][$i]           = "";
					$GLOBALS["STORED_REPS"][$id]["columns"][$i]         = $f[0];
					$GLOBALS["STORED_REPS"][$id]["head"][$i]            = $f[0];
					$GLOBALS["STORED_REPS"][$id][REP_COL_FORMULA][$i]   = $f[0];
					$GLOBALS["STORED_REPS"][$id]["columns_flip"][$f[0]] = $i;
					trace("_ check filter for FR_$i");
					if (isset($_REQUEST["FR_$k"]))
						$GLOBALS["STORED_REPS"][$id][REP_COL_FROM][$i] = $_REQUEST["FR_$k"];
				}
			}
			trace("Dynamic columns: " . print_r($GLOBALS["STORED_REPS"][$id], TRUE));
		}
		if (isset($_REQUEST["TOTALS"])) {
			$select = explode(",", $_REQUEST["TOTALS"]);
			$tmp    = array();
			trace("custom totals: " . print_r($select, TRUE));
			foreach ($select as $k => $v) {
				trace("_ field: $k => $v");
				$f = explode(":", $v);
				if (isset($GLOBALS["STORED_REPS"][$id]["columns_flip"][$f[0]]) && in_array($f[1], $aggr_funcs)) {
					trace("__ add total: " . $f[0] . "=>" . $f[1]);
					$tmp[$GLOBALS["STORED_REPS"][$id]["columns_flip"][$f[0]]] = $f[1];
				}
			}
			if (count($tmp) > 0)
				$GLOBALS["STORED_REPS"][$id][REP_COL_TOTAL] = $tmp;
		}
		if (!is_array($GLOBALS["STORED_REPS"][$id]["types"]))
			my_die(t9n("[RU]Пустой отчет[EN]Empty report") . " " . $GLOBALS["STORED_REPS"][$id]["header"]);
		trace(print_r($GLOBALS["STORED_REPS"][$id], TRUE));
		foreach ($GLOBALS["STORED_REPS"][$id]["types"] as $key => $typ) {
			if (isset($GLOBALS["STORED_REPS"][$id][REP_COL_NAME][$key]))
				$GLOBALS["STORED_REPS"][$id]["head"][$key] = $GLOBALS["STORED_REPS"][$id][REP_COL_NAME][$key];
			if (isset($GLOBALS["STORED_REPS"][$id][REP_COL_FORMAT][$key]))
				if ($GLOBALS["STORED_REPS"][$id][REP_COL_FORMAT][$key] != "")
					$GLOBALS["STORED_REPS"][$id]["base_out"][$key] = $GLOBALS["STORED_REPS"][$id]["base_in"][$key] = $GLOBALS["STORED_REPS"][$id][REP_COL_FORMAT][$key];
			if (!isset($GLOBALS["STORED_REPS"][$id]["base_out"][$key]))
				$GLOBALS["STORED_REPS"][$id]["base_out"][$key] = $GLOBALS["STORED_REPS"][$id]["base_in"][$key] = isset($GLOBALS["REV_BT"][$typ]) ? $GLOBALS["REV_BT"][$typ] : "SHORT";
		}
		if (isset($GLOBALS["STORED_REPS"][$id][REP_COL_SET])) {
			if (isset($_REQUEST["confirmed"]))
				$GLOBALS["STORED_REPS"][$id]["head"][] = t9n("[RU]Выполнено[EN]Done");
			else
				$GLOBALS["STORED_REPS"][$id]["head"][] = "<a href=\"#\" onclick=\"byId('report').action+='?confirmed';byId('report').submit();event.stopPropagation();\">" . t9n("[RU]Выполнить[EN]Commit changes") . "</a>";
		}
		if (!isset($GLOBALS["STORED_REPS"][$id]["sql"])) {
			$sql      = "SELECT distinct CASE WHEN col_def.up=0 THEN col_def.id ELSE col_def.up END typ, reqs.id req, req_refs.t refr, arr_vals.up arr
FROM $z rep LEFT JOIN $z col_def ON col_def.id=rep.val LEFT JOIN $z reqs ON reqs.up=CASE WHEN col_def.up=0 THEN col_def.id ELSE col_def.up END LEFT JOIN $z req_refs ON req_refs.id=reqs.t AND length(req_refs.val)=0 LEFT JOIN $z arr_vals ON arr_vals.up=reqs.t AND arr_vals.ord=1 WHERE rep.up=$id AND rep.t=" . REP_COLS . " AND (req_refs.id IS NOT NULL OR arr_vals.id IS NOT NULL) ORDER BY rep.ord";
			$data_set = Exec_sql($sql, "Get all Objects involved in Report along with their Refs");
			while ($row = mysqli_fetch_array($data_set))
				if ($row["refr"]) {
					$GLOBALS["STORED_REPS"][$id]["references"][$row["typ"]][$row["refr"]] = $row["req"];
					$GLOBALS["STORED_REPS"][$id]["ref_typ"][$row["req"]]                  = $row["refr"];
				} else
					$GLOBALS["STORED_REPS"][$id]["arrays"][$row["typ"]][$row["arr"]] = $row["req"];
			foreach ($GLOBALS["STORED_REPS"][$id]["types"] as $key => $typ) {
				trace(" Replace the report params with the gotten ones from REQUEST: $key => $typ");
				if (isset($GLOBALS["STORED_REPS"][$id][REP_COL_NAME][$key])) {
					$str = str_replace(" ", "_", $GLOBALS["STORED_REPS"][$id][REP_COL_NAME][$key]);
					if (isset($_REQUEST["FR_$str"]))
						if (strlen($_REQUEST["FR_$str"]))
							$GLOBALS["CONDS"][$key]["FR"] = $_REQUEST["FR_$str"];
					if (isset($_REQUEST["TO_$str"]))
						if (strlen($_REQUEST["TO_$str"]))
							$GLOBALS["CONDS"][$key]["TO"] = $_REQUEST["TO_$str"];
				}
				if (!isset($GLOBALS["CONDS"][$key]["FR"]) && isset($GLOBALS["STORED_REPS"][$id][REP_COL_FROM][$key]))
					$GLOBALS["CONDS"][$key]["FR"] = $GLOBALS["STORED_REPS"][$id][REP_COL_FROM][$key];
				if (!isset($GLOBALS["CONDS"][$key]["TO"]) && isset($GLOBALS["STORED_REPS"][$id][REP_COL_TO][$key]))
					$GLOBALS["CONDS"][$key]["TO"] = $GLOBALS["STORED_REPS"][$id][REP_COL_TO][$key];
			}
			if (isset($_REQUEST["SELECT"])) {
				$new_funcs = array();
				$select    = explode(",", str_replace("\,", "%2c", $_REQUEST["SELECT"]));
				trace("Functions: " . print_r($select, TRUE));
				foreach ($select as $k => $v) {
					$f = explode(":", str_replace("\:", "%3a", $v));
					if (isset($GLOBALS["STORED_REPS"][$id]["columns_flip"][$f[0]]))
						$new_funcs[$GLOBALS["STORED_REPS"][$id]["columns_flip"][$f[0]]] = strtoupper($f[1]);
				}
				if (count($new_funcs) > 0)
					$GLOBALS["STORED_REPS"][$id][REP_COL_FUNC] = $new_funcs;
				trace("New Functions: " . print_r($new_funcs, TRUE));
			}
			$not_all_joined = TRUE;
			while ($not_all_joined) {
				$not_all_joined = FALSE;
				$no_progress    = TRUE;
				foreach ($GLOBALS["STORED_REPS"][$id]["types"] as $key => $typ) {
					if (strlen($typ)) {
						$par   = $par_alias = isset($GLOBALS["STORED_REPS"]["parents"][$typ]) ? $GLOBALS["STORED_REPS"]["parents"][$typ] : $typ;
						$alias = $typ;
						unset($field);
						if (isset($GLOBALS["STORED_REPS"][$id][REP_COL_FUNC][$key]))
							if (substr($GLOBALS["STORED_REPS"][$id][REP_COL_FUNC][$key], 0, 4) == "abn_") {
								switch ($GLOBALS["STORED_REPS"][$id][REP_COL_FUNC][$key]) {
									case "abn_ID":
										$field = "$p$alias.id";
										break;
									case "abn_UP":
										$field = "$p$alias.up";
										break;
									case "abn_TYP":
										$field = "$p$alias.t";
										break;
									case "abn_ORD":
										$field = "$p$alias.ord";
										break;
									case "abn_REQ":
										$field = "$alias";
										break;
									case "abn_BT":
										$field = $GLOBALS["BT"][$GLOBALS["STORED_REPS"][$id]["types"][$key]];
										break;
								}
								if (isset($field)) {
									$GLOBALS["STORED_REPS"][$id]["base_in"][$key] = "NUMBER";
									unset($GLOBALS["STORED_REPS"][$id][REP_COL_FUNC][$key]);
									$GLOBALS["STORED_REPS"][$id]["abn_"][$key] = "";
								}
							}
						if (!isset($field))
							$field = "$p$alias.val";
						if (!isset($master)) {
							$master          = $par_alias;
							$tables[$master] = "$z $p$master";
							if (isset($_REQUEST["i$master"]))
								$conds[$master] = "$p$master.id=%$master" . "_OBJ_ID%";
							else
								$conds[$master] = "$p$master.up!=0 AND length($p$master.val)!=0 AND $p$master.t=$par";
						}
						if (!isset($tables[$alias])) {
							trace("$alias not joined yet");
							if (!isset($tables[$par_alias])) {
								trace("_ parent $par_alias of $alias not joined yet");
								$on = "AND $p$par_alias.t=$par";
								if (isset($GLOBALS["STORED_REPS"][$id][REP_JOIN][$par_alias])) {
									trace("__ REP_JOIN for $par_alias");
									$rf   = $GLOBALS["STORED_REPS"][$id][REP_JOIN][$par_alias];
									$join = "";
									if (isset($rf[REP_JOIN_ON])) {
										preg_match_all("/:(\d+):/", $rf[REP_JOIN_ON], $matches);
										foreach ($matches[1] as $j)
											if (($j != $par_alias) && !isset($tables[$j])) {
												trace("___ $j required, not joined");
												continue (2);
											}
										$join = $rf[REP_JOIN_ON];
									}
									$par_alias          = isset($rf[REP_ALIAS]) ? $rf[REP_ALIAS] : $par_alias;
									$tables[$par_alias] = "LEFT JOIN $z $p$par_alias ON $p$par_alias.t=$par_alias AND $join";
									preg_match_all("/($p$par_alias\.\w+)/", $join, $matches);
									if (count($matches[1]))
										foreach ($matches[1] as $j)
											$joined["$p$par_alias"][$j] = $j;
									else
										$joined["$p$par_alias"][$par_alias] = "$p$par_alias.*";
									$joinedFrom["$p$par_alias"]   = "FROM $z $p$par_alias";
									$joinedClause["$p$par_alias"] = " WHERE $p$par_alias.t=$par_alias";
									$joinedOn["$p$par_alias"]     = ") $p$par_alias ON $join";
									trace("__ " . $tables[$par_alias]);
								} else
									foreach ($tables as $t => $j) {
										trace("__ Look through joined tables");
										if (substr($t, strpos($t, "_")) == (isset($suffix) ? $suffix : ""))
											$orig = substr($t, 0, strpos($t, "_"));
										else
											$orig = $t;
										if ($t == $master) {
											$ptid = "$p$t.id";
											$ptup = "$p$t.up";
										} else {
											$ptid = "$p$t" . "_id";
											$ptup = "$p$t" . "_up";
										}
										if (isset($GLOBALS["STORED_REPS"][$id]["references"][$orig][$par]) && ($t . "_alias" !== $par_alias)) {
											trace("___ first suitable link [$orig]->[$par]");
											if (!isset($joined["$p$t"]) || (strpos(implode(" ", $joined["$p$t"]), $ptid) === false))
												$joined["$p$t"][$ptid] = "$p$t.id $ptid";
											if (HintNeeded($key, $id)) {
												$tables[$par_alias]                 = "LEFT JOIN ($z $pr$par_alias CROSS JOIN $z $p$par_alias USE INDEX (PRIMARY)) ON $pr$par_alias.up=$p$t.id AND $p$par_alias.id=$pr$par_alias.t $on";
												$joined["$p$par_alias"][$par_alias] = "$pr$par_alias.up";
												$joinedFrom["$p$par_alias"]         = "FROM $z $pr$par_alias,$z $p$par_alias USE INDEX (PRIMARY)";
												$joinedClause["$p$par_alias"]       = " WHERE $p$par_alias.id=$pr$par_alias.t $on";
												$joinedOn["$p$par_alias"]           = ") $p$par_alias ON $p$par_alias.up=$ptid";
											} else {
												$tables[$par_alias]                 = "LEFT JOIN ($z $pr$par_alias CROSS JOIN $z $p$par_alias) ON $pr$par_alias.val='" . $GLOBALS["STORED_REPS"][$id]["references"][$orig][$par] . "'" . " AND $pr$par_alias.up=$p$t.id AND $p$par_alias.id=$pr$par_alias.t $on";
												$joined["$p$par_alias"][$par_alias] = "$pr$par_alias.up,$pr$par_alias.val";
												$joinedFrom["$p$par_alias"]         = "FROM $z $pr$par_alias,$z $p$par_alias";
												$joinedClause["$p$par_alias"]       = " WHERE $p$par_alias.id=$pr$par_alias.t $on";
												$joinedOn["$p$par_alias"]           = ") $p$par_alias ON $p$par_alias.val='" . $GLOBALS["STORED_REPS"][$id]["references"][$orig][$par] . "'" . " AND $p$par_alias.up=$ptid";
											}
										} elseif (isset($GLOBALS["STORED_REPS"][$id]["arrays"][$orig][$par])) {
											trace("___ We are an Array [$par]->[$orig]");
											$tables[$par_alias]                 = "LEFT JOIN $z $p$par_alias ON $p$par_alias.up=$p$t.id $on";
											$joined["$p$par_alias"][$par_alias] = "$p$par_alias.up";
											$joinedFrom["$p$par_alias"]         = "FROM $z $p$par_alias /* We are an Array */";
											$joinedClause["$p$par_alias"]       = " WHERE $p$par_alias.t=$par";
											if (strpos(implode(",", $joined["$p$t"]), $ptid) === false)
												$joined["$p$t"][$ptid] = "$p$t.id $ptid";
											$joinedOn["$p$par_alias"]                    = " ) $p$par_alias ON $p$par_alias.up=$ptid";
											$GLOBALS["STORED_REPS"][$id]["PARENT"][$par] = $orig;
										} elseif (isset($GLOBALS["STORED_REPS"][$id]["references"][$par][$orig])) {
											trace("___ We have a Reference [$par]->[$orig]");
											if (HintNeeded($key, $id)) {
												$tables[$par_alias]                 = "LEFT JOIN ($z $pr$par_alias CROSS JOIN $z $p$par_alias USE INDEX (PRIMARY)) ON $pr$par_alias.up=$p$par_alias.id AND $p$t.id=$pr$par_alias.t AND $pr$par_alias.val='" . $GLOBALS["STORED_REPS"][$id]["references"][$par][$orig] . "' $on";
												$joined["$p$par_alias"][$par_alias] = "$pr$par_alias.t";
												$joinedFrom["$p$par_alias"]         = "FROM $z $pr$par_alias,$z $p$par_alias /*USE INDEX (PRIMARY)*/";
												$joinedClause["$p$par_alias"]       = " WHERE $pr$par_alias.up=$p$par_alias.id AND $pr$par_alias.val='" . $GLOBALS["STORED_REPS"][$id]["references"][$par][$orig] . "' $on";
												$joinedOn["$p$par_alias"]           = ") $p$par_alias ON $ptid=$p$par_alias.t";
											} else {
												$joined["$p$par_alias"][$par_alias] = "$pr$par_alias.t,$pr$par_alias.val";
												$tables[$par_alias]                 = "LEFT JOIN ($z $pr$par_alias CROSS JOIN $z $p$par_alias) ON $pr$par_alias.val='" . $GLOBALS["STORED_REPS"][$id]["references"][$par][$orig] . "'" . " AND $pr$par_alias.up=$p$par_alias.id AND $p$t.id=$pr$par_alias.t $on";
												$joinedFrom["$p$par_alias"]         = "FROM $z $pr$par_alias,$z $p$par_alias";
												$joinedClause["$p$par_alias"]       = " WHERE $pr$par_alias.up=$p$par_alias.id $on";
												$joinedOn["$p$par_alias"]           = " ) $p$par_alias ON $p$par_alias.val='" . $GLOBALS["STORED_REPS"][$id]["references"][$par][$orig] . "'" . " AND $ptid=$p$par_alias.t";
											}
										} elseif (isset($GLOBALS["STORED_REPS"][$id]["arrays"][$par][$orig])) {
											trace("___ We got an Array [$par]->[$orig]");
											$tables[$par_alias]           = "LEFT JOIN $z $p$par_alias ON $p$t.up=$p$par_alias.id $on";
											$joinedFrom["$p$par_alias"]   = "FROM $z $p$par_alias /* We got an Array */";
											$joinedClause["$p$par_alias"] = " WHERE $p$par_alias.t=$par";
											if (strpos(implode(",", $joined["$p$t"]), $ptup) === false)
												$joined["$p$t"][$ptup] = "$p$t.up $ptup";
											$joined["$p$par_alias"][$par_alias]           = "$p$par_alias.id";
											$joinedOn["$p$par_alias"]                     = " ) $p$par_alias ON $ptup=$p$par_alias.id";
											$GLOBALS["STORED_REPS"][$id]["PARENT"][$orig] = $par;
										} else
											continue;
										trace("____ " . $tables[$par_alias]);
										break;
									}
							}
							if (!isset($tables[$par_alias])) {
								trace("__ Failed to join the parent $par_alias, better luck next round");
								$not_all_joined = TRUE;
								continue;
							}
							if ($typ != $par) {
								if (!isset($tables[$alias]))
									$tables[$alias] = "";
								if ($l = isRef($id, $par, $typ)) {
									if (HintNeeded($key, $id)) {
										$tables[$alias] .= "LEFT JOIN ($z $pr$alias CROSS JOIN $z $p$alias USE INDEX (PRIMARY)) ON $pr$alias.up=$p$par_alias.id AND $p$alias.id=$pr$alias.t AND $p$alias.t=$l AND $pr$alias.val='$typ' ";
										$joinedJoin["$p$par_alias"][] = "LEFT JOIN ($z $pr$alias CROSS JOIN $z $p$alias USE INDEX (PRIMARY)) ON $pr$alias.up=$p$par_alias.id AND $p$alias.id=$pr$alias.t AND $p$alias.t=$l AND $pr$alias.val='$typ' ";
									} else {
										$tables[$alias] .= "LEFT JOIN ($z $pr$alias CROSS JOIN $z $p$alias) ON $pr$alias.up=$p$par_alias.id AND $pr$alias.val='$typ' AND $p$alias.id=$pr$alias.t AND $p$alias.t=$l";
										$joinedJoin["$p$par_alias"][] = "LEFT JOIN ($z $pr$alias CROSS JOIN $z $p$alias) ON $pr$alias.up=$p$par_alias.id AND $pr$alias.val='$typ' AND $p$alias.id=$pr$alias.t AND $p$alias.t=$l";
									}
								} else {
									$tables[$alias] .= "LEFT JOIN $z $p$alias ON $p$alias.up=$p$par_alias.id AND $p$alias.t=$typ";
									$joinedJoin["$p$par_alias"][] = "LEFT JOIN $z $p$alias ON $p$alias.up=$p$par_alias.id AND $p$alias.t=$typ";
								}
							}
						}
						if (isset($fields[$key]))
							continue;
						$no_progress = FALSE;
						if (isset($GLOBALS["STORED_REPS"][$id][REP_COL_NAME][$key]))
							$name = "'" . str_replace("'", "\\'", $GLOBALS["STORED_REPS"][$id][REP_COL_NAME][$key]) . "'";
						else
							$name = "$pv$key$s$par";
						$fieldsOrig[$key] = $master == $par_alias ? $field : str_replace(".", "_", $field);
						if (isset($GLOBALS["STORED_REPS"][$id][REP_COL_FORMULA][$key]))
							if (mb_strpos($GLOBALS["STORED_REPS"][$id][REP_COL_FORMULA][$key], "[THIS]") !== FALSE)
								$field = str_replace("[THIS]", $field, $GLOBALS["STORED_REPS"][$id][REP_COL_FORMULA][$key]);
						$fields[$key] = $field;
						$names[$key]  = $name;
						if (!isset($GLOBALS["STORED_REPS"][$id][REP_COL_HIDE][$key])) {
							$displayName[$key] = $name;
							if (isset($GLOBALS["STORED_REPS"][$id][REP_COL_FUNC][$key])) {
								if (in_array($GLOBALS["STORED_REPS"][$id][REP_COL_FUNC][$key], $aggr_funcs)) {
									trace("# Needs grouping " . $GLOBALS["REV_BT"][$typ]);
									if (($GLOBALS["REV_BT"][$typ] == "NUMBER") || ($GLOBALS["REV_BT"][$typ] == "DATETIME")) {
										$field_names[$key]                               = $GLOBALS["STORED_REPS"][$id][REP_COL_FUNC][$key] . "(CAST($field AS SIGNED)) $name";
										$displayVal[$key]                                = $GLOBALS["STORED_REPS"][$id][REP_COL_FUNC][$key] . "(CAST(" . $fieldsOrig[$key] . " AS SIGNED))";
										$GLOBALS["STORED_REPS"][$id]["aggrs2sort"][$key] = $GLOBALS["STORED_REPS"][$id][REP_COL_FUNC][$key] . "(CAST($field AS SIGNED))";
									} elseif ($GLOBALS["REV_BT"][$typ] == "SIGNED") {
										$field_names[$key]                               = $GLOBALS["STORED_REPS"][$id][REP_COL_FUNC][$key] . "(CAST($field AS DOUBLE)) $name";
										$displayVal[$key]                                = $GLOBALS["STORED_REPS"][$id][REP_COL_FUNC][$key] . "(CAST(" . $fieldsOrig[$key] . " AS DOUBLE))";
										$GLOBALS["STORED_REPS"][$id]["aggrs2sort"][$key] = $GLOBALS["STORED_REPS"][$id][REP_COL_FUNC][$key] . "(CAST($field AS DOUBLE))";
									} else {
										$field_names[$key]                               = $GLOBALS["STORED_REPS"][$id][REP_COL_FUNC][$key] . "($field) $name";
										$displayVal[$key]                                = $GLOBALS["STORED_REPS"][$id][REP_COL_FUNC][$key] . "(" . $fieldsOrig[$key] . ")";
										$GLOBALS["STORED_REPS"][$id]["aggrs2sort"][$key] = $GLOBALS["STORED_REPS"][$id][REP_COL_FUNC][$key] . "($field)";
									}
									if (array_search("$field " . $fieldsOrig[$key], $joined["$p$par_alias"]) === false)
										$joined["$p$par_alias"][$key] = "$field " . $fieldsOrig[$key];
									$GLOBALS["STORED_REPS"][$id]["aggrs"][$key] = "";
								} elseif (substr($GLOBALS["STORED_REPS"][$id][REP_COL_FUNC][$key], 0, 4) == "abn_") {
									$field_names[$key]                         = "$field $name";
									$displayVal[$key]                          = $field;
									$GLOBALS["STORED_REPS"][$id]["abn_"][$key] = "";
								} elseif (strlen($GLOBALS["STORED_REPS"][$id][REP_COL_FUNC][$key])) {
									$field_names[$key]            = $GLOBALS["STORED_REPS"][$id][REP_COL_FUNC][$key] . "($field) $name";
									$displayVal[$key]             = $master == $par_alias ? $GLOBALS["STORED_REPS"][$id][REP_COL_FUNC][$key] . "($field)" : $fieldsOrig[$key];
									$joined["$p$par_alias"][$key] = $GLOBALS["STORED_REPS"][$id][REP_COL_FUNC][$key] . "($field)" . " " . $fieldsOrig[$key];
									unset($GLOBALS["STORED_REPS"][$id][REP_COL_FUNC][$key]);
								}
							}
							if (!isset($field_names[$key])) {
								$field_names[$key] = "$field $name";
								if ($master == $par_alias) {
									$joined["$p$par_alias"][$key] = "";
									$displayVal[$key]             = $field;
								} else {
									$displayVal[$key] = $fieldsOrig[$key];
									if (!isset($joined["$p$par_alias"]) || (strpos(implode(" ", $joined["$p$par_alias"]), $fieldsOrig[$key]) === false))
										$joined["$p$par_alias"][$key] = $field . " " . $fieldsOrig[$key];
								}
								if (strpos($tailed, $GLOBALS["STORED_REPS"][$id]["base_in"][$key]) && $exe) {
									$GLOBALS["REP_COLS"][$name] = "t$alias";
									if (!isset($tails_fetched[$alias])) {
										$tables[$alias] .= " LEFT JOIN $z t$alias ON t$alias.t=0 AND t$alias.ord=0 AND t$alias.up=$p$alias.id";
										if ($master !== $alias)
											$joinedJoin["$p$par_alias"][] = " LEFT JOIN $z t$alias ON t$alias.t=0 AND t$alias.ord=0 AND t$alias.up=$p$alias.id";
										$field_names["t$alias"]            = "t$alias.up t$alias";
										$displayVal["t$key"]               = $master == $par_alias ? "t$alias.up" : "t$alias" . "_up";
										$displayName["t$key"]              = "t$alias";
										$joined["$p$par_alias"]["t$alias"] = "t$alias.up t$alias" . "_up";
										$tails_fetched[$alias]             = "";
									}
								}
							}
							if ((($par == $typ) && isset($GLOBALS["STORED_REPS"][$id]["params"][REP_HREFS]) && !isset($GLOBALS["STORED_REPS"][$id]["abn_"][$key]) && !isset($GLOBALS["STORED_REPS"][$id]["aggrs"][$key]))) {
								$field_names["$pi$key"]            = "$p$alias.id $pi$key";
								$joined["$p$par_alias"]["$pi$key"] = "$p$alias.id $pi$key";
								$displayVal["$pi$key"]             = $master == $par_alias ? "$p$alias.id " : "";
								$displayName["$pi$key"]            = "$pi$key";
								$fields["$pi$key"]                 = "$p$alias.id";
								$names["$pi$key"]                  = "$pi$key";
							} elseif (($GLOBALS["STORED_REPS"][$id]["base_in"][$key] == "FILE") || ($GLOBALS["STORED_REPS"][$id]["base_in"][$key] == "PATH")) {
								if ($master == $par_alias) {
									$field_names[$key]  = "CONCAT($p$alias.id,':',$field) $name";
									$displayVal["$key"] = "CONCAT($p$alias.id,':',$field)";
								} else {
									$field_names[$key]                 = "CONCAT($p$alias" . "_id,':',$p$alias" . "_val) $name";
									$displayVal["$key"]                = "CONCAT($p$alias" . "_id,':',$p$alias" . "_val)";
									$joined["$p$par_alias"]["$pi$key"] = "$p$alias.id $p$alias" . "_id";
								}
							}
							if (isset($GLOBALS["STORED_REPS"][$id][REP_COL_SET][$key])) {
								trace("REP_COL_SET for $key - " . $GLOBALS["STORED_REPS"][$id][REP_COL_SET][$key]);
								$displayName["$pi$key"] = "$pi$key";
								if (isRef($id, $par, $typ)) {
									$field_names["$pi$key"] = "$pr$alias.id $pi$key";
									if ($master == $par_alias) {
										$joined["$p$par_alias"]["$pi$key"] = "";
										$displayVal["$pi$key"]             = "$pr$alias.id";
									} else {
										$joined["$p$par_alias"]["$pi$key"] = "$pr$alias.id $pi$key";
										$displayVal["$pi$key"]             = "";
									}
								} else {
									$field_names["$pi$key"] = "$p$alias.id $pi$key";
									if ($master == $par_alias) {
										$joined["$p$par_alias"]["$pi$key"] = "";
										$displayVal["$pi$key"]             = "$p$alias.id";
									} else {
										$joined["$p$par_alias"]["$pi$key"] = "$p$alias.id $pi$key";
										$displayVal["$pi$key"]             = "";
									}
								}
								$fields["$pi$key"] = "$p$alias.id";
								$names["$pi$key"]  = "$pi$key";
								$update_val        = BuiltIn($GLOBALS["STORED_REPS"][$id][REP_COL_SET][$key]);
								if ($GLOBALS["STORED_REPS"][$id][REP_COL_SET][$key] != $update_val)
									$update_val = "'$update_val'";
								elseif (mb_strpos($update_val, "[THIS]") !== FALSE)
									$update_val = str_replace("[THIS]", $field, $update_val);
								$field_names["$pu$key"] = "$update_val $pu$key";
								$displayVal["$pu$key"]  = $update_val;
								$displayName["$pu$key"] = "$pu$key";
								$fields["$pu$key"]      = "$pu$alias.id";
								$names["$pu$key"]       = "$pu$key";
								if (!isset($field_names["$pi$par"]) && ($par != $typ)) {
									$field_names["$pi$par"] = "$p$par.id /*0*/ $pi$par /*1*/";
									$displayName["$pi$par"] = "$pi$par /**/";
									$displayVal["$pi$par"]  = "$p$par" . ($par == $master ? "." : "_") . "id /*2*/";
									if ($master == $par_alias)
										$joined["$p$par_alias"]["$pi$par"] = "";
									elseif (strpos(implode(" ", $joined["$p$par_alias"]), "$p$par" . ($par == $master ? "." : "_") . "id") === false)
										$joined["$p$par_alias"]["$pi$par"] = "$p$par.id /*3*/ $p$par" . ($par == $master ? "." : "_") . "id";
									$fields["$pi$par"] = "$p$par.id /*4*/";
									$names["$pi$par"]  = "$pi$par /*5*/";
								}
							}
						} else {
							$fieldsAll[$key]  = $fieldsOrig[$key];
							$fieldsName[$key] = $name;
							if (($master !== $par_alias) && (isset($GLOBALS["STORED_REPS"][$id][REP_COL_FORMULA][$key]) || isset($GLOBALS["STORED_REPS"][$id][REP_COL_FROM][$key]) || isset($GLOBALS["STORED_REPS"][$id][REP_COL_TO][$key]) || isset($GLOBALS["STORED_REPS"][$id][REP_COL_HAV_FR][$key]) || isset($GLOBALS["STORED_REPS"][$id][REP_COL_HAV_TO][$key])))
								if (strpos(implode(" ", $joined["$p$par_alias"]), $fieldsOrig[$key]) === false)
									$joined["$p$par_alias"][$key] = "$field " . $fieldsOrig[$key];
						}
					} else {
						$no_progress = FALSE;
						if (isset($GLOBALS["STORED_REPS"][$id][REP_COL_FORMULA][$key])) {
							$field = $GLOBALS["STORED_REPS"][$id][REP_COL_FORMULA][$key];
							if (isset($GLOBALS["STORED_REPS"][$id][REP_COL_FUNC][$key]))
								if ($GLOBALS["STORED_REPS"][$id][REP_COL_FUNC][$key] == 'abn_URL')
									$field = "'abn_URL($key)'";
						} elseif (isset($GLOBALS["STORED_REPS"][$id][REP_COL_FUNC][$key]))
							$field = "'" . $GLOBALS["STORED_REPS"][$id][REP_COL_FUNC][$key] . " $key - $typ'";
						else
							$field = t9n("[RU]'Пустая или неверная формула в вычисляемой колонке (№$key)'" . "[EN]Empty or incorrect formula in column #$key'");
						if (isset($GLOBALS["STORED_REPS"][$id][REP_COL_NAME][$key]))
							$name = "'" . str_replace("'", "\\'", $GLOBALS["STORED_REPS"][$id][REP_COL_NAME][$key]) . "'";
						else
							$name = "$pv$key";
						$fields[$key] = $field;
						$names[$key]  = $name;
						if (!isset($GLOBALS["STORED_REPS"][$id][REP_COL_HIDE][$key])) {
							$field_names[$key] = "$field $name";
							$displayVal[$key]  = $field;
							$displayName[$key] = $name;
							if (isset($GLOBALS["STORED_REPS"][$id][REP_COL_FUNC][$key])) {
								if (in_array($GLOBALS["STORED_REPS"][$id][REP_COL_FUNC][$key], $aggr_funcs)) {
									$field_names[$key]                          = $GLOBALS["STORED_REPS"][$id][REP_COL_FUNC][$key] . "($field) $name";
									$fields[$key]                               = $GLOBALS["STORED_REPS"][$id][REP_COL_FUNC][$key] . "($field)";
									$displayVal[$key]                           = $GLOBALS["STORED_REPS"][$id][REP_COL_FUNC][$key] . "($field)";
									$GLOBALS["STORED_REPS"][$id]["aggrs"][$key] = "";
								} elseif (strlen($GLOBALS["STORED_REPS"][$id][REP_COL_FUNC][$key])) {
									if (substr($GLOBALS["STORED_REPS"][$id][REP_COL_FUNC][$key], 0, 4) == "abn_")
										$field_names[$key] = "$field $name";
									else {
										$field_names[$key] = $GLOBALS["STORED_REPS"][$id][REP_COL_FUNC][$key] . "($field) $name";
										$displayVal[$key]  = $GLOBALS["STORED_REPS"][$id][REP_COL_FUNC][$key] . "($field)";
										$fields[$key]      = $GLOBALS["STORED_REPS"][$id][REP_COL_FUNC][$key] . "($field)";
										unset($GLOBALS["STORED_REPS"][$id][REP_COL_FUNC][$key]);
									}
								}
							}
						} else {
							$fieldsAll[$key]  = $field;
							$fieldsName[$key] = $name;
						}
					}
					if (isset($GLOBALS["STORED_REPS"][$id][REP_COL_SORT][$key])) {
						if (isset($GLOBALS["STORED_REPS"][$id][REP_COL_HIDE][$key]) && ($master !== $par_alias))
							if (strpos(implode(" ", $joined["$p$par_alias"]), $fieldsOrig[$key]) === false)
								$joined["$p$par_alias"][$key] = $field . " " . $fieldsOrig[$key];
						if ($GLOBALS["STORED_REPS"][$id]["base_out"][$key] == "NUMBER" || $GLOBALS["STORED_REPS"][$id]["base_out"][$key] == "SIGNED")
							$tmp = "CAST(" . (isset($GLOBALS["STORED_REPS"][$id]["aggrs2sort"][$key]) ? $GLOBALS["STORED_REPS"][$id]["aggrs2sort"][$key] : $fields[$key]) . " AS SIGNED)";
						else
							$tmp = $fields[$key];
						if ($GLOBALS["STORED_REPS"][$id][REP_COL_SORT][$key] < 0)
							$sortByArr[-$GLOBALS["STORED_REPS"][$id][REP_COL_SORT][$key]] = ($master == $par_alias ? $tmp : str_replace(".", "_", $tmp)) . " DESC";
						else
							$sortByArr[$GLOBALS["STORED_REPS"][$id][REP_COL_SORT][$key]] = $master == $par_alias ? $tmp : str_replace(".", "_", $tmp);
					}
					if (isset($filters[$key]))
						continue;
					if (isset($GLOBALS["CONDS"][$key])) {
						$GLOBALS["REV_BT"][$alias] = $GLOBALS["STORED_REPS"][$id]["base_in"][$key];
						$GLOBALS["where"]          = "";
						Construct_WHERE($alias, $GLOBALS["CONDS"][$key], 1, FALSE);
						$filters[$key] = str_replace("a$alias.val", $field, $GLOBALS["where"]);
						if (($master == $par_alias) || !isset($fieldsOrig[$key]))
							$masterFilters[$key] = $filters[$key];
						else {
							if (strpos(implode(" ", $joined["$p$par_alias"]), $fieldsOrig[$key]) === false)
								$joined["$p$par_alias"]["f$key"] = $field . " " . $fieldsOrig[$key];
							$masterFilters[$key] = str_replace($field, $fieldsOrig[$key], $filters[$key]);
						}
						if (strpos($filters[$key], "tp$alias.val")) {
							$filters[$key]       = str_replace("t$alias.val", "t$field", $filters[$key]);
							$filters[$key]       = str_replace("tp$alias.val", "tp$field", $filters[$key]);
							$masterFilters[$key] = str_replace("t$alias.val", "t$field", $masterFilters[$key]);
							$masterFilters[$key] = str_replace("tp$alias.val", "tp$field", $masterFilters[$key]);
							if ($master != $par_alias)
								$masterFilters[$key] = str_replace(".val", "_val", $masterFilters[$key]);
							$field    = $typ;
							$distinct = "DISTINCT";
							if (!isset($field_names[$field])) {
								$field_names[$field]               = "a$field.id";
								$joined["$p$par_alias"]["t$field"] = "a$field.id";
							}
							if (!isset($tables["t$field"])) {
								$tables["t$field"] = " LEFT JOIN $z ta$field ON ta$field.up=a$field.id AND ta$field.t=0
 LEFT JOIN $z tpa$field ON tpa$field.up=ta$field.up AND tpa$field.t=0 AND tpa$field.ord=ta$field.ord+1";
								$joined["$p$par_alias"]["t$field"] .= ",t$field.up t$field" . "_up,ta$field.val ta$field" . "_val,tpa$field.val tpa$field" . "_val";
								$joinedJoin["$p$par_alias"][] = " LEFT JOIN $z ta$field ON ta$field.up=a$field.id AND ta$field.t=0
 LEFT JOIN $z tpa$field ON tpa$field.up=ta$field.up AND tpa$field.t=0 AND tpa$field.ord=ta$field.ord+1";
							}
						} elseif (isset($GLOBALS["STORED_REPS"][$id][REP_COL_SET][$key]) && ($par != $master)) {
							$tables[$typ] .= $filters[$key];
							$joinedOn["$p$par_alias"] .= $masterFilters[$key];
							unset($filters[$key]);
							unset($masterFilters[$key]);
							if (isset($GLOBALS["STORED_REPS"][$id]["PARENT"][$typ])) {
								$par_id = $GLOBALS["STORED_REPS"][$id]["PARENT"][$typ];
								if (!strpos($field_names[$key], "a$par_id.id i$par_id")) {
									$field_names[$key] .= ", a$par_id.id i$par_id";
									$displayVal["i$par_id"]            = $par_id == $master ? "a$par_id.id" : "i$par_id";
									$joined["a$par_id"]["a$par_id.id"] = "a$par_id.id i$par_id /* joined */";
								}
							}
						}
					} elseif (isset($GLOBALS["STORED_REPS"][$id][REP_COL_SET][$key]) && ($par != $master))
						if (isset($GLOBALS["STORED_REPS"][$id]["PARENT"][$typ])) {
							$par_id = $GLOBALS["STORED_REPS"][$id]["PARENT"][$typ];
							if (!strpos($field_names[$key], "a$par_id.id i$par_id")) {
								$field_names[$key] .= ", a$par_id.id i$par_id";
								$displayName["i$par_id"]           = "i$par_id";
								$displayVal["i$par_id"]            = "a$par_id.id";
								$joined["a$par_id"]["a$par_id.id"] = "a$par_id.id i$par_id";
							}
						}
				}
				if ($not_all_joined && $no_progress) {
					die_info($GLOBALS["STORED_REPS"][$id]["header"] . ": " . t9n("[RU]Невозможно связать колонки отчета.[EN]It is impossible to link the columns of the report."));
				}
				if (isset($_REQUEST["i$typ"]) && ($typ != $master))
					$conds[$typ] = "AND $p$typ.id=%$typ" . "_OBJ_ID%";
			}
			$fieldsAll  = $fieldsAll + $displayVal;
			$fieldsName = $fieldsName + $displayName;
			trace("Globals: " . print_r($GLOBALS["STORED_REPS"][$id], TRUE));
			trace("field_names: " . print_r($field_names, TRUE));
			trace("names: " . print_r($names, TRUE));
			trace("fields: " . print_r($fields, TRUE));
			if (isset($_REQUEST["SELECT"])) {
				$new_field_names = $new_head = $new_fields = array();
				$select          = explode(",", str_replace("\,", "%2c", $_REQUEST["SELECT"]));
				trace("select: " . print_r($select, TRUE));
				trace("fields: " . print_r($fields, TRUE));
				foreach ($select as $k => $v) {
					$v = array_shift(explode(":", str_replace("\:", "%3a", $v)));
					$v = str_replace("%2c", ",", str_replace("%3a", ":", $v));
					if (isset($GLOBALS["STORED_REPS"][$id]["columns_flip"][$v])) {
						trace("_ found in columns_flip: $k => $v");
						$new_field_names[$GLOBALS["STORED_REPS"][$id]["columns_flip"][$v]] = $field_names[$GLOBALS["STORED_REPS"][$id]["columns_flip"][$v]];
						$new_head[$GLOBALS["STORED_REPS"][$id]["columns_flip"][$v]]        = $GLOBALS["STORED_REPS"][$id]["head"][$GLOBALS["STORED_REPS"][$id]["columns_flip"][$v]];
					}
				}
				if (count($new_field_names) > 0) {
					$field_names                         = $new_field_names;
					$GLOBALS["STORED_REPS"][$id]["head"] = $new_head;
				}
				trace("columns: " . print_r($GLOBALS["STORED_REPS"][$id]["columns_flip"], TRUE));
				trace("new field_names: " . print_r($field_names, TRUE));
			}
			foreach ($GLOBALS["STORED_REPS"][$id]["head"] as $key => $value)
				if (isset($GLOBALS["STORED_REPS"][$id][REP_COL_FORMAT][$key]))
					$GLOBALS["STORED_REPS"][$id]["base_out"][$key] = $GLOBALS["STORED_REPS"][$id][REP_COL_FORMAT][$key];
				else
					$GLOBALS["STORED_REPS"][$id]["base_out"][$key] = $GLOBALS["STORED_REPS"][$id]["base_in"][$key];
			if (isset($GLOBALS["STORED_REPS"][$id]["aggrs"]))
				foreach ($fields as $key => $value)
					if (isset($field_names[$key]))
						if ((!isset($GLOBALS["STORED_REPS"][$id]["aggrs"][$key])) && (!isset($GLOBALS["STORED_REPS"][$id][REP_COL_HIDE][$key])) && !(($GLOBALS["STORED_REPS"][$id]["types"][$key] == "") && (preg_match("/\b(sum|avg|count|min|max)\b\(/i", $GLOBALS["STORED_REPS"][$id][REP_COL_FORMULA][$key]))) && !((substr($key, 0, 1) == "u") && preg_match("/\b(sum|avg|count|min|max)\b\(/i", $GLOBALS["STORED_REPS"][$id][REP_COL_SET][substr($key, 1)]))) {
							if (isset($group)) {
								$group .= ", ";
								$groupBy[$master] .= ", ";
							} else
								$group = $groupBy[$master] = "GROUP BY ";
							$group .= substr($names[$key], 0, 1) == "'" ? $fields[$key] : $names[$key];
							$groupBy[$master] .= substr($displayName[$key], 0, 1) == "'" ? $fieldsAll[$key] : $fieldsName[$key];
						}
			$GLOBALS["CONDS"] = array();
			foreach ($GLOBALS["STORED_REPS"][$id]["head"] as $key => $value) {
				if (isset($GLOBALS["STORED_REPS"][$id][REP_COL_HAV_FR][$key]))
					$GLOBALS["CONDS"][$key]["FR"] = $GLOBALS["STORED_REPS"][$id][REP_COL_HAV_FR][$key];
				if (isset($GLOBALS["STORED_REPS"][$id][REP_COL_HAV_TO][$key]))
					$GLOBALS["CONDS"][$key]["TO"] = $GLOBALS["STORED_REPS"][$id][REP_COL_HAV_TO][$key];
				if (isset($GLOBALS["CONDS"][$key])) {
					$typ                                                            = $GLOBALS["STORED_REPS"][$id]["types"][$key];
					$GLOBALS["REV_BT"][$GLOBALS["STORED_REPS"][$id]["types"][$key]] = $GLOBALS["STORED_REPS"][$id]["base_out"][$key];
					$GLOBALS["where"]                                               = "";
					Construct_WHERE($typ, $GLOBALS["CONDS"][$key], 1, FALSE, FALSE);
					if (isset($GLOBALS["STORED_REPS"][$id][REP_COL_HIDE][$key]))
						$names[$key] = $fields[$key];
					if (isset($having)) {
						$having .= str_replace("a$typ.val", substr($displayName[$key], 0, 1) == "'" ? $fieldsAll[$key] : $fieldsName[$key], $GLOBALS["where"]);
					} else {
						$having = " HAVING " . substr(str_replace("a$typ.val", substr($displayName[$key], 0, 1) == "'" ? $fieldsAll[$key] : $fieldsName[$key], $GLOBALS["where"]), 4);
					}
				}
			}
			if (isset($_REQUEST["ORDER"])) {
				$select = explode(",", $_REQUEST["ORDER"]);
				trace("Order get: " . print_r($select, TRUE));
				foreach ($select as $k => $v) {
					if (substr($v, 0, 1) == "-") {
						$v    = substr($v, 1);
						$desc = " DESC";
					} else
						$desc = "";
					if (isset($GLOBALS["STORED_REPS"][$id]["columns_flip"][$v])) {
						$col = $GLOBALS["STORED_REPS"][$id]["columns_flip"][$v];
						$key = $fields[$col];
						trace("_ field $v ($col) found: $key type: " . $GLOBALS["STORED_REPS"][$id]["base_out"][$col]);
						if ($GLOBALS["STORED_REPS"][$id]["base_out"][$col] == "NUMBER" || $GLOBALS["STORED_REPS"][$id]["base_out"][$col] == "SIGNED")
							$key = "CAST(" . $key . " AS SIGNED)";
						if (isset($order))
							$order .= ", $key $desc";
						else
							$order = "ORDER BY $key $desc";
					}
				}
				trace("order set: $order");
			} elseif (isset($GLOBALS["STORED_REPS"][$id][REP_COL_SORT])) {
				foreach ($GLOBALS["STORED_REPS"][$id][REP_COL_SORT] as $key => $value) {
					unset($GLOBALS["STORED_REPS"][$id][REP_COL_SORT][$key]);
					if (strlen($value)) {
						if ($GLOBALS["STORED_REPS"][$id]["base_out"][$key] == "NUMBER" || $GLOBALS["STORED_REPS"][$id]["base_out"][$key] == "SIGNED")
							$key = "CAST(" . (isset($GLOBALS["STORED_REPS"][$id]["aggrs2sort"][$key]) ? $GLOBALS["STORED_REPS"][$id]["aggrs2sort"][$key] : $fields[$key]) . " AS SIGNED)";
						else
							$key = $fields[$key];
						trace("order: $key");
						if ($value < 0)
							$GLOBALS["STORED_REPS"][$id][REP_COL_SORT][$key . " DESC"] = -$value;
						else
							$GLOBALS["STORED_REPS"][$id][REP_COL_SORT][$key] = $value;
					}
				}
				array_multisort($GLOBALS["STORED_REPS"][$id][REP_COL_SORT]);
				foreach ($GLOBALS["STORED_REPS"][$id][REP_COL_SORT] as $key => $value) {
					if (isset($order))
						$order .= ", $key";
					else
						$order = " ORDER BY $key";
				}
				ksort($sortByArr);
			}
			ksort($names);
			$GLOBALS["STORED_REPS"][$id]["names"] = $names;
			$filter                               = isset($filters) ? implode(" ", $filters) : "";
			$masterFilter                         = isset($masterFilters) ? implode(" ", $masterFilters) : "";
			$field                                = implode(",", $field_names);
			$cond                                 = implode(" ", $conds);
			$sql                                  = implode(" ", $tables);
			if ($exe && (isset($_REQUEST["WHERE"]) || isset($GLOBALS["STORED_REPS"][$id]["params"][REP_WHERE])))
				if (strlen(trim($where = isset($_REQUEST["WHERE"]) ? $_REQUEST["WHERE"] : $GLOBALS["STORED_REPS"][$id]["params"][REP_WHERE])))
					$filter .= strtoupper(substr($where, 0, 3)) == "AND" ? " $where" : " AND $where";
			preg_match_all("/(\[[0-9a-zA-Z\_]+\])/ims", $field, $builtins);
			foreach ($builtins[0] as $builtin)
				if (BuiltIn($builtin) != $builtin)
					$field = str_replace($builtin, BuiltIn($builtin), $field);
			preg_match_all("/_request_\.([0-9a-zA-Z\_]+)/ims", $field, $builtins);
			foreach ($builtins[1] as $k => $_req)
				if (isset($_REQUEST[$_req]))
					$field = str_replace($builtins[0][$k], $_REQUEST[$_req], $field);
			preg_match_all("/_global_\.([0-9a-zA-Z\_]+)/ims", $field, $builtins);
			foreach ($builtins[1] as $k => $_req)
				if (isset($GLOBALS["GLOBAL_VARS"][$_req]))
					$field = str_replace($builtins[0][$k], $GLOBALS["GLOBAL_VARS"][$_req], $field);
			preg_match_all("/(\[[0-9a-zA-Z\_]+\])/ims", $filter, $builtins);
			foreach ($builtins[0] as $builtin)
				if (BuiltIn($builtin) != $builtin)
					$filter = str_replace($builtin, BuiltIn($builtin), $filter);
			preg_match_all("/_request_\.([0-9a-zA-Z\_]+)/ims", $filter, $builtins);
			foreach ($builtins[1] as $k => $_req)
				if (isset($_REQUEST[$_req]))
					$filter = str_replace($builtins[0][$k], $_REQUEST[$_req], $filter);
			preg_match_all("/_global_\.([0-9a-zA-Z\_]+)/ims", $filter, $builtins);
			foreach ($builtins[1] as $k => $_req)
				if (isset($GLOBALS["GLOBAL_VARS"][$_req]))
					$filter = str_replace($builtins[0][$k], $GLOBALS["GLOBAL_VARS"][$_req], $filter);
			preg_match_all("/(\[[0-9a-zA-Z\_]+\])/ims", implode(" ", $fieldsAll), $builtins);
			foreach ($builtins[0] as $builtin)
				if (BuiltIn($builtin) != $builtin)
					$fieldsAll = str_replace($builtin, BuiltIn($builtin), $fieldsAll);
			preg_match_all("/_request_\.([0-9a-zA-Z\_]+)/ims", implode(" ", $fieldsAll), $builtins);
			foreach ($builtins[1] as $k => $_req)
				if (isset($_REQUEST[$_req]))
					$fieldsAll = str_replace($builtins[0][$k], $_REQUEST[$_req], $fieldsAll);
			preg_match_all("/_global_\.([0-9a-zA-Z\_]+)/ims", implode(" ", $fieldsAll), $builtins);
			foreach ($builtins[1] as $k => $_req)
				if (isset($GLOBALS["GLOBAL_VARS"][$_req]))
					$fieldsAll = str_replace($builtins[0][$k], $GLOBALS["GLOBAL_VARS"][$_req], $fieldsAll);
			preg_match_all("/(\[[0-9a-zA-Z\_]+\])/ims", implode(" ", $joinedOn), $builtins);
			foreach ($builtins[0] as $builtin)
				if (BuiltIn($builtin) != $builtin)
					$joinedOn = str_replace($builtin, BuiltIn($builtin), $joinedOn);
			preg_match_all("/_request_\.([0-9a-zA-Z\_]+)/ims", implode(" ", $joinedOn), $builtins);
			foreach ($builtins[1] as $k => $_req)
				if (isset($_REQUEST[$_req]))
					$joinedOn = str_replace($builtins[0][$k], $_REQUEST[$_req], $joinedOn);
			preg_match_all("/_global_\.([0-9a-zA-Z\_]+)/ims", implode(" ", $joinedOn), $builtins);
			foreach ($builtins[1] as $k => $_req)
				if (isset($GLOBALS["GLOBAL_VARS"][$_req]))
					$joinedOn = str_replace($builtins[0][$k], $GLOBALS["GLOBAL_VARS"][$_req], $joinedOn);
			$tmp = "";
			foreach ($joined as $k => $v)
				$tmp .= implode(" ", $v);
			preg_match_all("/(\[[0-9a-zA-Z\_]+\])/ims", $tmp, $builtins);
			foreach ($builtins[0] as $builtin)
				if (BuiltIn($builtin) != $builtin)
					foreach ($joined as $k => $v)
						$joined[$k] = str_replace($builtin, BuiltIn($builtin), $joined[$k]);
			preg_match_all("/_request_\.([0-9a-zA-Z\_]+)/ims", $tmp, $builtins);
			foreach ($builtins[1] as $k => $_req)
				if (isset($_REQUEST[$_req]))
					foreach ($joined as $kk => $v)
						$joined[$kk] = str_replace($builtins[0][$k], $_REQUEST[$_req], $joined[$kk]);
			preg_match_all("/_global_\.([0-9a-zA-Z\_]+)/ims", $tmp, $builtins);
			foreach ($builtins[1] as $k => $_req)
				if (isset($GLOBALS["GLOBAL_VARS"][$_req]))
					foreach ($joined as $kk => $v)
						$joined[$kk] = str_replace($builtins[0][$k], $GLOBALS["GLOBAL_VARS"][$_req], $joined[$kk]);
			preg_match_all("/(\[[0-9a-zA-Z\_]+\])/ims", $masterFilter, $builtins);
			foreach ($builtins[0] as $builtin)
				if (BuiltIn($builtin) != $builtin)
					$masterFilter = str_replace($builtin, BuiltIn($builtin), $masterFilter);
			preg_match_all("/_request_\.([0-9a-zA-Z\_]+)/ims", $masterFilter, $builtins);
			foreach ($builtins[1] as $k => $_req)
				if (isset($_REQUEST[$_req]))
					$masterFilter = str_replace($builtins[0][$k], $_REQUEST[$_req], $masterFilter);
			preg_match_all("/_global_\.([0-9a-zA-Z\_]+)/ims", $masterFilter, $builtins);
			foreach ($builtins[1] as $k => $_req)
				if (isset($GLOBALS["GLOBAL_VARS"][$_req]))
					$masterFilter = str_replace($builtins[0][$k], $GLOBALS["GLOBAL_VARS"][$_req], $masterFilter);
			if (isset($order)) {
				preg_match_all("/_request_\.([0-9a-zA-Z\_]+)/ims", $order, $builtins);
				foreach ($builtins[1] as $k => $_req)
					if (isset($_REQUEST[$_req]))
						$order = str_replace($builtins[0][$k], $_REQUEST[$_req], $order);
				preg_match_all("/_global_\.([0-9a-zA-Z\_]+)/ims", $order, $builtins);
				foreach ($builtins[1] as $k => $_req)
					if (isset($GLOBALS["GLOBAL_VARS"][$_req]))
						$order = str_replace($builtins[0][$k], $GLOBALS["GLOBAL_VARS"][$_req], $order);
			}
			if (isset($group)) {
				preg_match_all("/_request_\.([0-9a-zA-Z\_]+)/ims", $group, $builtins);
				foreach ($builtins[1] as $k => $_req)
					if (isset($_REQUEST[$_req]))
						$group = str_replace($builtins[0][$k], $_REQUEST[$_req], $group);
				preg_match_all("/_global_\.([0-9a-zA-Z\_]+)/ims", $group, $builtins);
				foreach ($builtins[1] as $k => $_req)
					if (isset($GLOBALS["GLOBAL_VARS"][$_req]))
						$group = str_replace($builtins[0][$k], $GLOBALS["GLOBAL_VARS"][$_req], $group);
				preg_match_all("/_request_\.([0-9a-zA-Z\_]+)/ims", implode(",", $groupBy), $builtins);
				foreach ($builtins[1] as $k => $_req)
					if (isset($_REQUEST[$_req]))
						$groupBy = str_replace($builtins[0][$k], $_REQUEST[$_req], $groupBy);
				preg_match_all("/_global_\.([0-9a-zA-Z\_]+)/ims", implode(",", $groupBy), $builtins);
				foreach ($builtins[1] as $k => $_req)
					if (isset($GLOBALS["GLOBAL_VARS"][$_req]))
						$groupBy = str_replace($builtins[0][$k], $GLOBALS["GLOBAL_VARS"][$_req], $groupBy);
			}
			if (preg_match("/(\b(from|select|table)\b)/i", $field . $filter, $match))
				die_info(t9n("[RU]Недопустимое значение вычисляемого поля: нельзя использовать служебные слова SQL. Найдено: " . "[EN]No SQL clause allowed in calculatable fields. Found: ") . $match[0]);
			trace("Fields: " . print_r($fields, true));
			if (isset($_REQUEST["SELECT"])) {
				trace("Check if we got field IDs to replace with field values");
				foreach ($fields as $k => $v) {
					preg_match_all("/:(\d+):/", $v, $cols);
					if (count($cols[1])) {
						trace("IDs to replace: " . print_r($cols, TRUE));
						foreach ($cols[1] as $f)
							if (isset($GLOBALS["STORED_REPS"][$id]["columns_flip"][$f])) {
								trace("replace $f with " . $fields[$GLOBALS["STORED_REPS"][$id]["columns_flip"][$f]]);
								$field  = str_replace(":$f:", $fields[$GLOBALS["STORED_REPS"][$id]["columns_flip"][$f]], $field);
								$filter = str_replace(":$f:", $fields[$GLOBALS["STORED_REPS"][$id]["columns_flip"][$f]], $filter);
								if (isset($group))
									$group = str_replace(":$f:", $fields[$GLOBALS["STORED_REPS"][$id]["columns_flip"][$f]], $group);
								if (isset($order))
									$order = str_replace(":$f:", $fields[$GLOBALS["STORED_REPS"][$id]["columns_flip"][$f]], $order);
								if (isset($having))
									$having = str_replace(":$f:", $fields[$GLOBALS["STORED_REPS"][$id]["columns_flip"][$f]], $having);
							}
					}
				}
			}
			trace("New fields: " . print_r($fields, true));
			trace("New field_names: " . print_r($field_names, true));
			trace("Check if we got some subqueries: " . print_r($GLOBALS["STORED_REPS"][$id], true));
			$reps = array_unique(explode("[", $sql . $field . $filter . (isset($having) ? $having : " ") . (isset($order) ? $order : " ")));
			array_shift($reps);
			if (count($reps))
				foreach ($reps as $value) {
					trace("_ subquery: $value");
					$tmp       = explode("]", $value);
					$sub_query = array_shift($tmp);
					$bak_id    = $id;
					Get_block_data($sub_query, FALSE);
					$id = $bak_id;
					if (isset($GLOBALS["STORED_REPS"][$GLOBALS["STORED_REPS"][$sub_query]["_rep_id"]]["sql"])) {
						$field        = str_replace('[' . $sub_query . ']', "(" . $GLOBALS["STORED_REPS"][$GLOBALS["STORED_REPS"][$sub_query]["_rep_id"]]["sql"] . ")", $field);
						$filter       = str_replace('\'[' . $sub_query . ']\'', "(" . $GLOBALS["STORED_REPS"][$GLOBALS["STORED_REPS"][$sub_query]["_rep_id"]]["sql"] . ")", $filter);
						$filter       = str_replace('[' . $sub_query . ']', "(" . $GLOBALS["STORED_REPS"][$GLOBALS["STORED_REPS"][$sub_query]["_rep_id"]]["sql"] . ")", $filter);
						$masterFilter = str_replace('\'[' . $sub_query . ']\'', "(" . $GLOBALS["STORED_REPS"][$GLOBALS["STORED_REPS"][$sub_query]["_rep_id"]]["sql"] . ")", $masterFilter);
						$masterFilter = str_replace('[' . $sub_query . ']', "(" . $GLOBALS["STORED_REPS"][$GLOBALS["STORED_REPS"][$sub_query]["_rep_id"]]["sql"] . ")", $masterFilter);
						$sql          = str_replace('[' . $sub_query . ']', "(" . $GLOBALS["STORED_REPS"][$GLOBALS["STORED_REPS"][$sub_query]["_rep_id"]]["sql"] . ")", $sql);
						if (isset($order))
							$order = str_replace('[' . $sub_query . ']', "(" . $GLOBALS["STORED_REPS"][$GLOBALS["STORED_REPS"][$sub_query]["_rep_id"]]["sql"] . ")", $order);
						if (isset($group)) {
							$group   = str_replace('[' . $sub_query . ']', "(" . $GLOBALS["STORED_REPS"][$GLOBALS["STORED_REPS"][$sub_query]["_rep_id"]]["sql"] . ")", $group);
							$groupBy = str_replace('[' . $sub_query . ']', "(" . $GLOBALS["STORED_REPS"][$GLOBALS["STORED_REPS"][$sub_query]["_rep_id"]]["sql"] . ")", $groupBy);
						}
						if (isset($having)) {
							$having = str_replace('\'[' . $sub_query . ']\'', "(" . $GLOBALS["STORED_REPS"][$GLOBALS["STORED_REPS"][$sub_query]["_rep_id"]]["sql"] . ")", $having);
							$having = str_replace('[' . $sub_query . ']', "(" . $GLOBALS["STORED_REPS"][$GLOBALS["STORED_REPS"][$sub_query]["_rep_id"]]["sql"] . ")", $having);
						}
						$fieldsAll = str_replace('[' . $sub_query . ']', "(" . $GLOBALS["STORED_REPS"][$GLOBALS["STORED_REPS"][$sub_query]["_rep_id"]]["sql"] . ")", $fieldsAll);
						$fieldsAll = str_replace('\'[' . $sub_query . ']\'', "(" . $GLOBALS["STORED_REPS"][$GLOBALS["STORED_REPS"][$sub_query]["_rep_id"]]["sql"] . ")", $fieldsAll);
						foreach ($joined as $k => $v) {
							$joined[$k] = str_replace('[' . $sub_query . ']', "(" . $GLOBALS["STORED_REPS"][$GLOBALS["STORED_REPS"][$sub_query]["_rep_id"]]["sql"] . ")", $joined[$k]);
							$joined[$k] = str_replace('\'[' . $sub_query . ']\'', "(" . $GLOBALS["STORED_REPS"][$GLOBALS["STORED_REPS"][$sub_query]["_rep_id"]]["sql"] . ")", $joined[$k]);
						}
					}
				}
			if (isset($GLOBALS["STORED_REPS"][$id][REP_COL_FORMULA]))
				foreach ($GLOBALS["STORED_REPS"][$id][REP_COL_NAME] as $key => $value)
					if (strlen($value) && isset($GLOBALS["STORED_REPS"][$id][REP_COL_FORMULA][$key])) {
						$key = $GLOBALS["STORED_REPS"][$id][REP_COL_FORMULA][$key];
						trace("field formula $value to be replaced with $key in $field ");
						$field        = str_replace('\'[' . $value . ']\'', $key, $field);
						$field        = str_replace('[' . $value . ']', $key, $field);
						$filter       = str_replace('\'[' . $value . ']\'', $key, $filter);
						$filter       = str_replace('[' . $value . ']', $key, $filter);
						$filter       = str_replace($value, $key, $filter);
						$masterFilter = str_replace('\'[' . $value . ']\'', $key, $masterFilter);
						$masterFilter = str_replace('[' . $value . ']', $key, $masterFilter);
						$masterFilter = str_replace($value, $key, $masterFilter);
						if (isset($group))
							$group = str_replace('[' . $value . ']', $key, $group);
						if (isset($groupBy))
							$groupBy = str_replace('[' . $value . ']', $key, $groupBy);
						if (isset($order))
							$order = str_replace('[' . $value . ']', $key, $order);
						if (isset($having))
							$having = str_replace('[' . $value . ']', $key, $having);
						$sql = str_replace('\'[' . $value . ']\'', $key, $sql);
						$sql = str_replace('[' . $value . ']', $key, $sql);
					}
			if (isset($GLOBALS["STORED_REPS"][$id][REP_COL_FORMULA]))
				foreach ($GLOBALS["STORED_REPS"][$id][REP_COL_FORMULA] as $key => $value)
					if (strlen($value) && ($GLOBALS["STORED_REPS"][$id]["types"][$key] != "")) {
						trace("formula $key => $value - to be replaced with " . $fields[$key] . " in $field ");
						$field        = str_replace('\'[' . $value . ']\'', $fields[$key], $field);
						$field        = str_replace('[' . $value . ']', $fields[$key], $field);
						$field        = str_replace($value, $fields[$key], $field);
						$filter       = str_replace('\'[' . $value . ']\'', $fields[$key], $filter);
						$filter       = str_replace('[' . $value . ']', $fields[$key], $filter);
						$filter       = str_replace($value, $fields[$key], $filter);
						$masterFilter = str_replace('\'[' . $value . ']\'', $fieldsOrig[$key], $masterFilter);
						$masterFilter = str_replace('[' . $value . ']', $fieldsOrig[$key], $masterFilter);
						$masterFilter = str_replace($value, $fieldsOrig[$key], $masterFilter);
						if (isset($group)) {
							$group   = str_replace('\'[' . $value . ']\'', $fields[$key], $group);
							$group   = str_replace('[' . $value . ']', $fields[$key], $group);
							$group   = str_replace($value, $fields[$key], $group);
							$groupBy = str_replace('\'[' . $value . ']\'', $fieldsOrig[$key], $groupBy);
							$groupBy = str_replace('[' . $value . ']', $fieldsOrig[$key], $groupBy);
							$groupBy = str_replace($value, $fieldsOrig[$key], $groupBy);
						}
						if (isset($order)) {
							$order = str_replace('\'[' . $value . ']\'', $fields[$key], $order);
							$order = str_replace('[' . $value . ']', $fields[$key], $order);
							$order = str_replace($value, $fields[$key], $order);
						}
						if (isset($having))
							$having = str_replace('\'[' . $value . ']\'', $fields[$key], $having);
						$sql          = str_replace('\'[' . $value . ']\'', $fields[$key], $sql);
						$sql          = str_replace('[' . $value . ']', $fields[$key], $sql);
						$fieldsAll    = str_replace('\'[' . $value . ']\'', $fieldsOrig[$key], $fieldsAll);
						$fieldsAll    = str_replace('[' . $value . ']', $fieldsOrig[$key], $fieldsAll);
						$fieldsAll    = preg_replace("/\b$value\b/u", $fieldsOrig[$key], $fieldsAll);
						$joinedClause = str_replace('\'[' . $value . ']\'', $fieldsOrig[$key], $joinedClause);
						$joinedClause = str_replace('[' . $value . ']', $fieldsOrig[$key], $joinedClause);
						$joinedClause = preg_replace("/\b$value\b/u", $fieldsOrig[$key], $joinedClause);
						$joinedOn     = str_replace('\'[' . $value . ']\'', $fieldsOrig[$key], $joinedOn);
						$joinedOn     = str_replace('[' . $value . ']', $fieldsOrig[$key], $joinedOn);
						$joinedOn     = preg_replace("/\b$value\b/u", $fieldsOrig[$key], $joinedOn);
					}
			if (isset($_REQUEST["RECORD_COUNT"]))
				$limit = "";
			elseif (isset($GLOBALS["STORED_REPS"][$id]["params"][REP_LIMIT])) {
				$limit  = "LIMIT ";
				$limits = explode(",", $GLOBALS["STORED_REPS"][$id]["params"][REP_LIMIT]);
				if (isset($_REQUEST["LIMIT"])) {
					$req_limits = explode(",", $_REQUEST["LIMIT"]);
					if (isset($limits[1])) {
						if (isset($req_limits[1]))
							$limit .= (int) $req_limits[0] . "," . min((int) $req_limits[1], (int) $limits[1]);
						else
							$limit .= min((int) $req_limits[0], (int) $limits[1]);
					} else {
						if (isset($req_limits[1]))
							$limit .= (int) $req_limits[0] . "," . min((int) $req_limits[1], (int) $limits[0]);
						else
							$limit .= min((int) $req_limits[0], (int) $limits[0]);
					}
				} else
					$limit = "LIMIT " . (int) $limits[0] . (isset($limits[1]) ? "," . (int) $limits[1] : "");
			} elseif (isset($_REQUEST["LIMIT"])) {
				$limits = explode(",", $_REQUEST["LIMIT"]);
				if ((int) $limits[0] >= 0)
					$limit = "LIMIT " . (int) $limits[0] . (isset($limits[1]) ? "," . (int) $limits[1] : "");
				else
					$limit = "";
			} else
				$limit = "";
			if (strlen($sql))
				$sql = "SELECT $distinct $field FROM $sql WHERE $cond $filter " . (isset($group) ? $group : " ") . (isset($having) ? $having : " ") . (isset($order) ? $order : " ") . " $limit";
			else
				$sql = "SELECT $field " . (strlen($filter) ? " FROM dual WHERE " . substr($filter, 4) : "") . $having;
			if (strlen($sql)) {
				$sql = "";
				foreach ($displayVal as $k => $v)
					$sql .= "," . $fieldsAll[$k] . " " . $displayName[$k];
				$sql = "\r\nSELECT " . substr($sql, 1);
				$sql .= "\r\nFROM " . $tables[$master];
				if (isset($joinedJoin["$p$master"]))
					foreach ($joinedJoin["$p$master"] as $j)
						$sql .= "\r\n   $j";
				foreach ($joined as $k => $v) {
					if ($k != "$p$master")
						$sql .= "\r\n  LEFT JOIN (SELECT " . implode(",", $joined[$k]);
					if (isset($joinedFrom[$k]))
						$sql .= "\r\n  " . $joinedFrom[$k];
					if (($k != "$p$master") && isset($joinedJoin[$k]))
						foreach ($joinedJoin[$k] as $j)
							$sql .= "\r\n   $j";
					if (isset($joinedClause[$k])) {
						$sql .= "\r\n   " . $joinedClause[$k];
						if (preg_match("/\b(sum|avg|count|min|max)\b\(/i", implode(",", $joined[$k])))
							foreach ($joined[$k] as $gr)
								if (!preg_match("/\b(sum|avg|count|min|max)\b\(/i", $gr)) {
									$tmp = explode(" ", $gr);
									$gr  = array_pop($tmp);
									if (isset($groupBy[$k]))
										$groupBy[$k] .= ",$gr";
									else
										$groupBy[$k] .= "GROUP BY $gr";
								}
						if (isset($groupBy[$k]))
							$sql .= "\r\n   " . $groupBy[$k];
					}
					if (isset($joinedOn[$k]))
						$sql .= "\r\n   " . $joinedOn[$k];
				}
				$sql .= "\r\nWHERE $cond $masterFilter " . (isset($groupBy[$master]) ? "\r\n" . $groupBy[$master] : " ") . (isset($having) ? $having : " ") . (isset($sortByArr) ? "\r\nORDER BY " . implode(",", $sortByArr) : " ") . " $limit";
			} else
				$sql = "SELECT $field " . (strlen($filter) ? " FROM dual WHERE " . substr($filter, 4) : "") . $having;
			if (isset($_REQUEST["SELECT"])) {
				preg_match_all("/:(\d+):/", $sql, $cols);
				foreach ($cols[1] as $k => $v)
					if (isset($GLOBALS["STORED_REPS"][$id]["columns_flip"][$v]))
						$sql = str_replace(":$v:", $fields[$GLOBALS["STORED_REPS"][$id]["columns_flip"][$v]], $sql);
				trace("IDs to replace: " . print_r($cols, TRUE));
			}
			$GLOBALS["STORED_REPS"][$id]["sql"] = $sql;
		}
	}
	if (!$exe) {
		if (isset($GLOBALS["STORED_REPS"][$id]["params"][REP_IFNULL]))
			$GLOBALS["STORED_REPS"][$id]["sql"] = "COALESCE((" . $GLOBALS["STORED_REPS"][$id]["sql"] . ")," . $GLOBALS["STORED_REPS"][$id]["params"][REP_IFNULL] . ")";
		return;
	}
	foreach ($_REQUEST as $key => $value)
		if ((substr($key, 0, 1) == "i") && ((int) $value != 0))
			$GLOBALS["STORED_REPS"][$id]["sql"] = str_replace("%" . substr($key, 1) . "_OBJ_ID%", (int) $value, $GLOBALS["STORED_REPS"][$id]["sql"]);
	$sql = $GLOBALS["STORED_REPS"][$id]["sql"];
	if (isset($GLOBALS["NO_CACHE"]))
		unset($GLOBALS["STORED_REPS"][$id]["sql"]);
	elseif ($sql == $GLOBALS["STORED_REPS"][$id]["sql"]) {
		if (isset($GLOBALS["STORED_REPS"][$id]["last_res"])) {
			$blocks["_data_col"][$id] = $GLOBALS["STORED_REPS"][$id]["last_res"];
			if (isset($GLOBALS["STORED_REPS"][$id]["last_totals"]))
				$blocks["col_totals"][$id] = $GLOBALS["STORED_REPS"][$id]["last_totals"];
			return;
		} elseif (isset($GLOBALS["STORED_REPS"][$id]["last_res_empty"]))
			return;
	}
	if (isset($_REQUEST["RECORD_COUNT"])) {
		trace("RECORD_COUNT set");
		$data_set = Exec_sql("SELECT COUNT(1) FROM ($sql) temp", "Request report data");
		if ($row = mysqli_fetch_array($data_set))
			if (isset($_REQUEST["JSON"]) || isApi() || isset($args["json"]))
				die('{"count":"' . $row[0] . '"}');
			else
				die($row[0]);
	}
	$data_set                                      = Exec_sql($sql, "Request report data");
	$rownum                                        = 1;
	$GLOBALS["STORED_REPS"][$id]["last_res_empty"] = 1;
	$GLOBALS["STORED_REPS"][$id]["rownum"]         = mysqli_num_rows($data_set);
	foreach ($GLOBALS["STORED_REPS"][$id]["names"] as $key => $value)
		if (substr($value, 0, 1) == "'") {
			$names[$key] = $GLOBALS["STORED_REPS"][$id]["names"][$key] = str_replace("\\'", "'", substr($value, 1, strlen($value) - 2));
		}
	if ((mysqli_num_rows($data_set) == 0) && isset($GLOBALS["STORED_REPS"][$id]["params"][REP_IFNULL])) {
		$GLOBALS["STORED_REPS"][$id]["rownum"] = 1;
		foreach ($GLOBALS["STORED_REPS"][$id]["head"] as $key => $value)
			if (!isset($GLOBALS["STORED_REPS"][$id][REP_COL_HIDE][$key]))
				$blocks["_data_col"][$id][$GLOBALS["STORED_REPS"][$id]["names"][$key]][] = $GLOBALS["STORED_REPS"][$id]["params"][REP_IFNULL];
	} elseif (mysqli_num_rows($data_set) == 0) {
		foreach ($GLOBALS["STORED_REPS"][$id]["head"] as $key => $value)
			if (!isset($GLOBALS["STORED_REPS"][$id][REP_COL_HIDE][$key]))
				$blocks["_data_col"][$id][$GLOBALS["STORED_REPS"][$id]["names"][$key]] = array();
	} else
		while ($row = mysqli_fetch_array($data_set)) {
			foreach ($GLOBALS["STORED_REPS"][$id]["head"] as $key => $value) {
				if (!isset($names[$key]))
					$names[$key] = $GLOBALS["STORED_REPS"][$id]["names"][$key] = "update";
				$value = $names[$key];
				if (isset($GLOBALS["STORED_REPS"][$id][REP_COL_HIDE][$key])) {
					unset($GLOBALS["STORED_REPS"][$id]["head"][$key]);
					if (!isset($GLOBALS["STORED_REPS"][$id][REP_COL_SET][$key]))
						continue;
				}
				$typ      = isset($GLOBALS["STORED_REPS"][$id]["types"][$key]) ? $GLOBALS["STORED_REPS"][$id]["types"][$key] : NULL;
				$base_str = isset($GLOBALS["STORED_REPS"][$id]["base_out"][$key]) ? $GLOBALS["STORED_REPS"][$id]["base_out"][$key] : NULL;
				$base     = isset($GLOBALS["BT"][$base_str]) ? $GLOBALS["BT"][$base_str] : NULL;
				$val      = isset($row[$value]) ? $row[$value] : "";
				if (isset($row["t$typ"]))
					if ($row["t$typ"])
						$val = Get_tail($row["t$typ"], $val);
				if (isset($GLOBALS["STORED_REPS"][$id][REP_COL_FUNC][$key]))
					switch ($GLOBALS["STORED_REPS"][$id][REP_COL_FUNC][$key]) {
						case "abn_DATE2STR":
							include_once "include/funcs.php";
							$val  = abn_DATE2STR($val);
							$base = $GLOBALS["BT"]["SHORT"];
							break;
						case "abn_NUM2STR":
							include_once "include/funcs.php";
							$val  = abn_NUM2STR($val);
							$base = $GLOBALS["BT"]["SHORT"];
							break;
						case "abn_RUB2STR":
							include_once "include/funcs.php";
							$val  = abn_RUB2STR($val);
							$base = $GLOBALS["BT"]["SHORT"];
							break;
						case "abn_Translit":
							include_once "include/funcs.php";
							$val  = abn_Translit($val);
							$base = $GLOBALS["BT"]["SHORT"];
							break;
						case "abn_ROWNUM":
							$val = $rownum++;
							break;
						case "abn_URL":
							$val = $GLOBALS["STORED_REPS"][$id]["params"][REP_URL];
							if (strlen($val) && !isset($file_failed)) {
								$host = strtolower(parse_url($val, 1));
								if ((strtolower($_SERVER["HTTP_HOST"]) === $host) || ($host === "localhost") || ((false !== filter_var($host, FILTER_VALIDATE_IP)) && (false === filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE))))
									$val = $file_failed = "You cannot access your own server!";
								elseif (substr(parse_url($val, 0), 0, 4) == 'http') {
									if (is_array($GLOBALS["STORED_REPS"][$id][REP_COL_NAME]))
										foreach ($GLOBALS["STORED_REPS"][$id][REP_COL_NAME] as $i => $a)
											if (strpos($val, "[$a]"))
												$val = str_replace("[$a]", rawurlencode($cur_line[$i]), $val);
									$ch = curl_init();
									curl_setopt($ch, CURLOPT_HEADER, 0);
									curl_setopt($ch, CURLOPT_HTTPHEADER, array(
										"User-Agent: Integral"
									));
									curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
									curl_setopt($ch, CURLOPT_URL, $val);
									$val = curl_exec($ch);
									if (curl_errno($ch)) {
										$val         = curl_errno($ch) . ": $val";
										$file_failed = true;
									}
									curl_close($ch);
								} else
									$val = $file_failed = "URL must use https or http";
							}
							break;
					}
				if (isset($GLOBALS["STORED_REPS"][$id]["params"][REP_HREFS]) && isset($names["i$key"]) && !isset($GLOBALS["STORED_REPS"]["parents"][$typ])) {
					if (($base_str == "PATH") || ($row["i$key"] < 2))
						$val = "<span>" . Format_Val_View($base, $val, $row["i$key"]) . "</span>";
					else
						$val = "<a target=\"$key\" href=\"/$z/edit_obj/" . $row["i$key"] . "\">" . Format_Val_View($base, $val, $row["i$key"]) . "</a>";
				} elseif ($base_str == "PATH")
					$val = strlen($val) ? Format_Val_View($base, $val, $row["i$key"]) : "";
				elseif ($base_str == "HTML")
					$val = strlen($val) ? str_ireplace("{_global_.z}", $z, $val) : "";
				elseif ($base_str == "FILE")
					$val = strlen($val) ? Format_Val_View($base, $val, $row["i$key"]) : "";
				else
					$val = strlen($val) ? htmlspecialchars(Format_Val_View($base, $val)) : "";
				$blocks["_data_col"][$id][$value][] = $val;
				if (isset($GLOBALS["STORED_REPS"][$id]["params"][REP_URL]))
					if (strlen($GLOBALS["STORED_REPS"][$id]["params"][REP_URL]))
						$cur_line[$key] = $val;
				if (isset($GLOBALS["STORED_REPS"][$id][REP_COL_TOTAL])) {
					if (isset($GLOBALS["STORED_REPS"][$id][REP_COL_TOTAL][$key])) {
						switch (strtoupper($GLOBALS["STORED_REPS"][$id][REP_COL_TOTAL][$key])) {
							case "COUNT":
								if (isset($blocks["col_totals"][$id][$value]))
									$blocks["col_totals"][$id][$value] = $blocks["col_totals"][$id][$value] + 1;
								else
									$blocks["col_totals"][$id][$value] = 1;
								break;
							case "AVG":
							case "SUM":
								if (isset($blocks["col_totals"][$id][$value]))
									$blocks["col_totals"][$id][$value] = (float) $blocks["col_totals"][$id][$value] + (float) $row[$value];
								break;
							case "MIN":
								if (isset($blocks["col_totals"][$id][$value]))
									if ($blocks["col_totals"][$id][$value] > $row[$value])
										$blocks["col_totals"][$id][$value] = $row[$value];
								break;
							case "MAX":
								if (isset($blocks["col_totals"][$id][$value]))
									if ($blocks["col_totals"][$id][$value] < $row[$value])
										$blocks["col_totals"][$id][$value] = $row[$value];
								break;
							default:
								$blocks["col_totals"][$id][$value] = "" . $GLOBALS["STORED_REPS"][$id][REP_COL_TOTAL][$key];
								break;
						}
						if (!isset($blocks["col_totals"][$id][$value]))
							$blocks["col_totals"][$id][$value] = $row[$value];
					}
					if (!isset($blocks["col_totals"][$id][$value]))
						$blocks["col_totals"][$id][$value] = "";
				}
			}
			if (isset($GLOBALS["STORED_REPS"][$id][REP_COL_SET])) {
				while (!isset($ready)) {
					$ready    = 0;
					$progress = FALSE;
					$rec      = count($blocks["_data_col"][$id]["update"]) - 1;
					foreach ($GLOBALS["STORED_REPS"][$id][REP_COL_SET] as $key => $val) {
						trace("REP_COL_SET $key => $val");
						$typ    = $GLOBALS["STORED_REPS"][$id]["types"][$key];
						$parent = isset($GLOBALS["STORED_REPS"]["parents"][$typ]) ? $GLOBALS["STORED_REPS"]["parents"][$typ] : 0;
						if ($new = ($row["i$key"] == 0)) {
							$o = 1;
							if ($parent == 0) {
								trace("_ We are parent");
								if (isset($GLOBALS["STORED_REPS"][$id]["PARENT"][$typ])) {
									$u = $row["i" . $GLOBALS["STORED_REPS"][$id]["PARENT"][$typ]];
									$o = 0;
								} else
									$u = 1;
							} else
								$u = $row["i$parent"];
							if ($u == 0) {
								trace("_ Our parent doesn't exist yet");
								foreach ($blocks["_update"] as $upd => $col)
									if (isset($col["t"]))
										if (isset($col["t"][$rec]))
											if (($col["t"][$rec] == $parent) && isset($col["new_id"][$rec])) {
												trace("__ Our parent will be");
												$u = $upd . "_" . $col["new_id"][$rec];
												trace("__ Our parent will be $u");
												$new_id_needed[$u] = "";
												break;
											}
								if ($u == 0) {
									trace("__ no parent to be created, though we need a parent ID for this Req");
									unset($ready);
									continue;
								}
							}
							$i = $u;
							if (!isset($blocks["_update"][$key]["id"][$i]))
								$blocks["_update"][$key]["new_id"][$rec] = $i;
						} else
							$i = $row["i$key"];
						if (isset($blocks["progress"][$key]["id"][$i]))
							continue;
						trace("_ The value to set is row[u" . $key . "]=" . $row["u$key"]);
						$progress                           = TRUE;
						$blocks["progress"][$key]["id"][$i] = true;
						$blocks["_update"][$key]["id"][$i]  = $rec;
						if ($row["u$key"] == "") {
							if ($new)
								unset($blocks["_update"][$key]["id"][$i]);
							else {
								$blocks["_update"][$key]["delete"][$rec] = "";
								$blocks["_data_col"][$id]["update"][$rec] .= "<s>" . $GLOBALS["STORED_REPS"][$id]["head"][$key] . "</s> (удалить)<br>";
							}
							continue;
						}
						if ($new) {
							$blocks["_update"][$key]["up"][$rec]  = $u;
							$blocks["_update"][$key]["ord"][$rec] = $o;
						}
						if (isRef($id, $par, $typ)) {
							$blocks["_update"][$key]["t"][$rec] = $row["u$key"];
							trace("Our type is $typ");
							if ($new)
								$blocks["_update"][$key]["val"][$rec] = $typ;
							$blocks["_data_col"][$id]["update"][$rec] .= $GLOBALS["STORED_REPS"][$id]["head"][$key] . ($new ? ": #" : " => #") . $row["u$key"] . "<br>";
						} else {
							$blocks["_update"][$key]["val"][$rec] = Format_Val($GLOBALS["BT"][$GLOBALS["STORED_REPS"][$id]["base_in"][$key]], $row["u$key"]);
							trace("__ formatted value is " . $blocks["_update"][$key]["val"][$rec]);
							trace("__ base type: " . $GLOBALS["BT"][$GLOBALS["STORED_REPS"][$id]["base_in"][$key]]);
							trace("__ formula applied, value is " . $blocks["_update"][$key]["val"][$rec]);
							$blocks["_data_col"][$id]["update"][$rec] .= $GLOBALS["STORED_REPS"][$id]["head"][$key] . ": ";
							if ($new)
								$blocks["_update"][$key]["t"][$rec] = $GLOBALS["STORED_REPS"][$id]["types"][$key];
							else {
								$blocks["_data_col"][$id]["update"][$rec] .= $blocks["_data_col"][$id][$GLOBALS["STORED_REPS"][$id]["names"][$key]][$rec] . " => ";
								if (strlen($blocks["_data_col"][$id][$GLOBALS["STORED_REPS"][$id]["names"][$key]][$rec]) > VAL_LIM)
									$blocks["_update"][$key]["tail"][$rec] = "";
							}
							$blocks["_data_col"][$id]["update"][$rec] .= Format_Val_View($GLOBALS["BT"][$GLOBALS["STORED_REPS"][$id]["base_out"][$key]], $blocks["_update"][$key]["val"][$rec]) . "<br>";
						}
					}
					if (!isset($ready) && !$progress)
						$ready = 0;
				}
				unset($ready);
			}
		}
	if (isset($blocks["_update"]) && isset($_REQUEST["confirmed"])) {
		check();
		foreach ($blocks["_update"] as $key => $value)
			foreach ($value["id"] as $i => $n)
				if (isset($value["ord"][$n])) {
					trace("A new record under " . $value["up"][$n]);
					if (isset($new_id_needed[$value["up"][$n]]))
						$value["up"][$n] = $new_id_needed[$value["up"][$n]];
					if (isset($new_id_needed[$key . "_" . $value["new_id"][$n]]))
						$new_id_needed[$key . "_" . $value["new_id"][$n]] = Insert($value["up"][$n], $value["ord"][$n] == 0 ? Calc_Order($value["up"][$n], $value["t"][$n]) : $value["ord"][$n], $value["t"][$n], $value["val"][$n], "INSERT new rec, get ID");
					else
						Insert($value["up"][$n], $value["ord"][$n] == 0 ? Calc_Order($value["up"][$n], $value["t"][$n]) : $value["ord"][$n], $value["t"][$n], $value["val"][$n], "INSERT new rec");
				} elseif (isset($value["delete"][$n]))
					Delete($i);
				elseif (!isset($value["val"][$n]))
					Exec_sql("UPDATE $z SET t=" . $value["t"][$n] . " WHERE id=$i", "UPDATE Ref");
				else
					Update_Val($i, Format_Val($GLOBALS["BT"][$GLOBALS["STORED_REPS"][$id]["base_out"][$key]], $value["val"][$n]), !isset($value["tail"][$n]));
		unset($blocks["_update"]);
	}
	if (isset($blocks["col_totals"][$id]))
		foreach ($blocks["col_totals"][$id] as $key => $value) {
			$k = array_search($key, $GLOBALS["STORED_REPS"][$id]["names"]);
			if (isset($GLOBALS["STORED_REPS"][$id][REP_COL_TOTAL][$k]))
				if (strtoupper($GLOBALS["STORED_REPS"][$id][REP_COL_TOTAL][$k]) == "AVG")
					$value = $value / $GLOBALS["STORED_REPS"][$id]["rownum"];
			if (isset($GLOBALS["STORED_REPS"][$id]["base_out"][$k]))
				$blocks["col_totals"][$id][$key] = Format_Val_View($GLOBALS["BT"][$GLOBALS["STORED_REPS"][$id]["base_out"][$k]], $value);
			else
				$blocks["col_totals"][$id][$key] = $value;
			if (isset($GLOBALS["STORED_REPS"][$id][REP_COL_FUNC][$k]))
				if ($GLOBALS["STORED_REPS"][$id][REP_COL_FUNC][$k] == "COUNT")
					$blocks["col_totals"][$id][$key] = Format_Val_View($GLOBALS["BT"]["NUMBER"], $value);
		}
	if ((isApi() || isset($args["json"])) && ($GLOBALS["GLOBAL_VARS"]["action"] == "report")) {
		if (isset($_REQUEST["JSON_DATA"])) {
			foreach ($GLOBALS["STORED_REPS"][$id]["head"] as $key => $value)
				if (isset($field_names[$key]))
					$json[$value] = count($blocks["_data_col"][$id]) ? array_shift($blocks["_data_col"][$id]) : "";
		} elseif (isset($_REQUEST["JSON_KV"])) {
			$json = "[ ";
			if (count($blocks["_data_col"][$id]))
				reset($blocks["_data_col"][$id]);
			$i = key($blocks["_data_col"][$id]);
			foreach ($blocks["_data_col"][$id][$i] as $key => $value) {
				foreach ($GLOBALS["STORED_REPS"][$id]["head"] as $k => $v)
					if (isset($field_names[$k]))
						$temp[$v] = $blocks["_data_col"][$id][$v][$key];
				$json .= json_encode($temp) . ",";
			}
			api_dump(substr($json, 0, -1) . "]", $GLOBALS["STORED_REPS"][$id]["header"] . ".json");
		} else {
			$i = 0;
			foreach ($GLOBALS["STORED_REPS"][$id]["head"] as $key => $value)
				if (isset($field_names[$key])) {
					$GLOBALS["STORED_REPS"][$id]["last_res"][$value] = count($blocks["_data_col"][$id]) ? array_shift($blocks["_data_col"][$id]) : "";
					$json["columns"][$i]["id"]                       = $GLOBALS["STORED_REPS"][$id]["columns"][$key];
					$json["columns"][$i]["format"]                   = $GLOBALS["STORED_REPS"][$id]["base_out"][$key];
					$json["columns"][$i]["name"]                     = $value;
					$i++;
				}
			$i = 0;
			foreach ($GLOBALS["STORED_REPS"][$id]["last_res"] as $rs)
				$json["data"][$i++] = $rs;
			$i = 0;
			if (isset($blocks["col_totals"][$id]))
				foreach ($blocks["col_totals"][$id] as $v)
					$json["columns"][$i++]["totals"] = $v;
		}
		api_dump(json_encode($json), $GLOBALS["STORED_REPS"][$id]["header"] . ".json");
	}
	$GLOBALS["STORED_REPS"][$id]["last_res"] = $blocks["_data_col"][$id];
	if (isset($blocks["col_totals"][$id]))
		$GLOBALS["STORED_REPS"][$id]["last_totals"] = $blocks["col_totals"][$id];
}
function Slash_semi($str)
{
	return str_replace("\;", "\$L3sH", $str);
}
function UnSlash_semi($str)
{
	return str_replace("\$L3sH", ";", $str);
}
function Download_send_headers($filename)
{
	header("Content-Type: application/force-download");
	header("Content-Type: application/octet-stream");
	header("Content-Type: application/download");
	header("Content-Disposition: attachment;filename={$filename}");
	header("Content-Transfer-Encoding: binary");
}
function FetchAlias($attr, $orig)
{
	preg_match(ALIAS_MASK, $attr, $alias);
	return isset($alias[1]) ? $alias[1] : $orig;
}
function ResolveType($typ)
{
	global $z;
	$data_set = Exec_sql("SELECT id FROM $z WHERE val='" . addslashes($typ[1]) . "' AND up=0 AND t=" . $GLOBALS["BT"][$typ[2]], "Seek Typ");
	if ($row = mysqli_fetch_array($data_set)) {
		$id = $row["id"];
		Export_header($id);
	} else {
		$id                              = Insert(0, (isset($typ[3]) ? "1" : "0"), $GLOBALS["BT"][$typ[2]], $typ[1], "Insert type substitute");
		$GLOBALS["local_struct"][$id][0] = "$id:" . MaskDelimiters($typ[1]) . ":" . $GLOBALS["BT"][$typ[2]] . (isset($typ[3]) ? ":unique" : "");
	}
	if ($id != $typ[0]) {
		$GLOBALS["local_struct"]["subst"][$typ[0]] = $id;
		trace("Substitute for " . $typ[0] . " - " . $typ[1] . " is $id");
	}
	return $id;
}
function CheckSubst($i)
{
	if (isset($GLOBALS["local_struct"]["subst"]))
		if (isset($GLOBALS["local_struct"]["subst"][$i]))
			return $GLOBALS["local_struct"]["subst"][$i];
	return $i;
}
function CheckObjSubst($i)
{
	if (isset($GLOBALS["obj_subst"]))
		if (isset($GLOBALS["obj_subst"][$i]))
			return $GLOBALS["obj_subst"][$i];
	return $i;
}
function maskCsvDelimiters($v)
{
	if (strpos($v, "\"") !== false)
		return "\"" . str_replace("\"", "\"\"", $v) . "\"";
	elseif (strpos($v, ";") !== false)
		return "\"" . $v . "\"";
	return $v;
}
function Get_block_data($block, $exe = TRUE)
{
	$tmp        = explode(".", $block);
	$block_name = array_pop($tmp);
	if (!strlen($block_name) || (substr($block_name, 0, 1) == "_"))
		return;
	global $blocks, $id, $f_u, $a, $obj, $z, $com, $args;
	switch ($block_name) {
		case "&top_menu":
			$blocks[$block]["top_menu"][]      = t9n("[RU]Таблицы[EN]Tables");
			$blocks[$block]["top_menu_href"][] = "dict";
			if (in_array(Check_Types_Grant(FALSE), array(
				"READ",
				"WRITE"
			))) {
				$blocks[$block]["top_menu"][]      = t9n("[RU]Структура[EN]Structure");
				$blocks[$block]["top_menu_href"][] = "edit_types";
			}
			if (RepoGrant() != "BARRED") {
				$blocks[$block]["top_menu"][]      = t9n("[RU]Файлы[EN]Files");
				$blocks[$block]["top_menu_href"][] = "dir_admin";
			}
			break;
		case "&main":
			$blocks[$block]["z"][] = $z;
			switch ($GLOBALS["GLOBAL_VARS"]["action"]) {
				case "object":
					if ($id == 0)
						die(t9n("[RU]Ошибка: id=0 или не задан[EN]Object id is empty or 0"));
					$data_set = Exec_sql("SELECT obj.val, obj.t, par.id FROM $z obj
  LEFT JOIN ($z par CROSS JOIN $z req USE INDEX (up_t)) ON par.up=0 AND req.up=par.id AND req.t=obj.id
  WHERE obj.id=$id AND (obj.up=0 OR par.up=0)", "Get Object type name");
					if ($row = mysqli_fetch_array($data_set)) {
						$blocks[$block]["title"][]      = $row[0];
						$blocks[$block]["typ"][]        = $row[1];
						$blocks[$block]["parent_obj"][] = $row[2];
					} else
						die(t9n("[RU]Тип $id не найден[EN]Type $id not found"));
					break;
				case "edit_obj":
					if ($id == 0)
						die(t9n("[RU]Ошибка: id=0 или не задан[EN]Object id is empty or 0"));
					$data_set = Exec_sql("SELECT typs.val, typs.t, a.val, typs.id
   FROM $z a, $z typs WHERE a.id=$id AND a.up!=0 AND typs.id=a.t AND typs.up=0", "Get Object & type name");
					if ($row = mysqli_fetch_array($data_set)) {
						$blocks[$block]["title"][]     = $row[0] . " " . Format_Val_View($row[1], $row[2], $id);
						$GLOBALS["REV_BT"][$row["id"]] = $GLOBALS["REV_BT"][$row["t"]];
					} else
						die(t9n("[RU]Объект $id не найден, вероятно, он был удален[EN]Object $id not found (it might be deleted)"));
					break;
				case "csv_all":
					set_time_limit(300);
					if (!isset($GLOBALS["GRANTS"]["EXPORT"][1]) && ($GLOBALS["GLOBAL_VARS"]["user"] != "admin") && ($GLOBALS["GLOBAL_VARS"]["user"] != $z))
						die("У вас нет прав на выгрузку базы");
					$sql      = "SELECT a.id, a.val, IF(base.t=base.id,0,1) ref, IF(base.t=base.id,defs.val,base.val) req, count(def_reqs.id) req_req, reqs.id req_id, defs.id req_t, defs.t req_base, a.t base
			   FROM $z a LEFT JOIN $z reqs ON reqs.up=a.id LEFT JOIN $z defs ON defs.id=reqs.t LEFT JOIN $z def_reqs ON def_reqs.up=defs.id LEFT JOIN $z base ON base.id=defs.t 
						WHERE a.up=0 AND a.id!=a.t AND a.val!='' AND a.t!=0
						GROUP BY reqs.id ORDER BY a.id, reqs.ord";
					$data_set = Exec_sql($sql, "Get Typs for backup");
					if (!is_dir($path = "templates/custom/$z/backups"))
						mkdir($path);
					$name = $z . "_" . date("Ymd_His") . ".csv";
					$file = fopen("$path/$name", "a+");
					fwrite($file, pack('CCC', 0xef, 0xbb, 0xbf));
					while ($row = mysqli_fetch_array($data_set))
						if (($GLOBALS["REV_BT"][$row["t"]] != "CALCULATABLE") && ($GLOBALS["REV_BT"][$row["t"]] != "BUTTON")) {
							$i = $row["id"];
							if (!isset($req[$i]) && !isset($typ[$i])) {
								$typ[$i]    = maskCsvDelimiters($row["val"]);
								$select[$i] = $join[$i] = "";
								$reqs[$i]   = array();
								$base[$i]   = $row["base"];
								if (in_array($GLOBALS["REV_BT"][$base[$i]], array(
									"CHARS",
									"MEMO",
									"FILE",
									"HTML"
								))) {
									$join[$i] .= " LEFT JOIN $z t ON t.up=obj.id AND t.t=0 AND t.ord=0";
									$select[$i] .= ", IF(t.id IS NULL, 0, 1) tail";
								}
							}
							if ($row["req"]) {
								$reqs[$i][] = $rid = $row["req_id"];
								$base[$rid] = $r = $row["req_base"];
								unset($typ[$row["req"]]);
								$req[$row["req"]] = "";
								$typ[$i] .= ";" . maskCsvDelimiters($row["req"]);
								if ($row["req_req"] > 0)
									$arr[$row["req_t"]] = "";
								else {
									if ($row["ref"] === "1")
										$join[$i] .= " LEFT JOIN ($z l$rid CROSS JOIN $z r$rid USE INDEX (PRIMARY)) ON l$rid.up=obj.id AND r$rid.id=l$rid.t AND r$rid.t=$r";
									elseif (in_array($GLOBALS["REV_BT"][$r], array(
										"CHARS",
										"MEMO",
										"FILE",
										"HTML"
									))) {
										$join[$i] .= " LEFT JOIN $z r$rid ON r$rid.up=obj.id AND r$rid.t=$rid LEFT JOIN $z t$rid ON t$rid.up=r$rid.id AND t$rid.t=0 AND t$rid.ord=0";
										$select[$i] .= ", IF(t$rid.id IS NULL, 0, r$rid.id) t$rid";
									} else
										$join[$i] .= " LEFT JOIN $z r$rid ON r$rid.up=obj.id AND r$rid.t=$rid";
									$select[$i] .= ", r$rid.val v$rid";
								}
							}
						}
					foreach ($typ as $i => $v) {
						fwrite($file, $v);
						$limit = round(500000 / (count($reqs[$i]) + 1));
						$last  = 0;
						do {
							$h = "";
							if (isset($arr[$i]))
								$data_set = Exec_sql("SELECT obj.id FROM $z obj, $z up WHERE obj.t=$i AND obj.up!=0 AND up.id=obj.up AND up.up!=0 AND obj.id>$last ORDER BY obj.id LIMIT $limit", "Get arr objects for CSV");
							else
								$data_set = Exec_sql("SELECT id FROM $z obj WHERE t=$i AND up!=0 AND id>$last ORDER BY id LIMIT $limit", "Get objects for CSV");
							if ($row = mysqli_fetch_array($data_set)) {
								$first = $row["id"];
								do {
									$last = $row["id"];
								} while ($row = mysqli_fetch_array($data_set));
								$data_set    = Exec_sql("SELECT obj.id, obj.val" . $select[$i] . " FROM $z obj" . $join[$i] . " WHERE obj.t=$i AND obj.up!=0 AND obj.id>=$first AND obj.id<=$last", "Get reqs for CSV");
								$rows_number = mysqli_num_rows($data_set);
								$prev        = 0;
								while ($row = mysqli_fetch_array($data_set)) {
									if ($prev !== $row["id"]) {
										$v = $row["val"];
										if (isset($row["tail"]))
											if ($row["tail"] == 1)
												$v = Get_tail($row["id"], $row["val"]);
										$h .= "\n" . maskCsvDelimiters(Format_Val_View($base[$i], $v));
										$prev = $row["id"];
									}
									foreach ($reqs[$i] as $rid) {
										$v = $row["v$rid"];
										if (isset($row["t$rid"]))
											if ($row["t$rid"] > 0)
												$v = Get_tail($row["t$rid"], $row["v$rid"]);
										$h .= ";" . maskCsvDelimiters(Format_Val_View($base[$rid], $v));
									}
								}
								fwrite($file, "$h");
							}
						} while ($rows_number == $limit);
						fwrite($file, "\n\n");
					}
					fclose($file);
					$zip = new ZipArchive();
					$zip->open("$path/$name.zip", ZipArchive::CREATE);
					$zip->addFile("$path/$name", $name);
					$zip->close();
					unlink("$path/$name");
					header("Location: /$z/dir_admin/?templates=1&add_path=/backups&gf=$name.zip");
					die();
					break;
				case "backup":
					set_time_limit(300);
					$limit = 500000;
					if (!isset($GLOBALS["GRANTS"]["EXPORT"][1]) && ($GLOBALS["GLOBAL_VARS"]["user"] != "admin") && ($GLOBALS["GLOBAL_VARS"]["user"] != $z))
						die("У вас нет прав на выгрузку базы");
					if (!is_dir($path = "templates/custom/$z/backups"))
						mkdir($path);
					$name = $z . "_" . date("Ymd_His") . ".dmp";
					$file = fopen("$path/$name", "a+");
					fwrite($file, pack('CCC', 0xef, 0xbb, 0xbf));
					$last   = 0;
					$lastup = $lastt = "-";
					do {
						$h           = "";
						$data_set    = Exec_sql("SELECT id,up,t,ord,val FROM $z WHERE id>$last ORDER BY id LIMIT $limit", "Get Objects list for export");
						$rows_number = mysqli_num_rows($data_set);
						while ($row = mysqli_fetch_assoc($data_set)) {
							if (++$last == $row["id"])
								$t = "";
							else
								$t = base_convert($last = $row["id"], 10, 36);
							if ($lastup == $row["up"])
								$t = $t . "/";
							else
								$t = $t . ";" . base_convert($lastup = $row["up"], 10, 36) . ";";
							if ($lastt == $row["t"]) {
								if (substr($t, -1) === ";")
									$t = substr($t, 0, -1) . "/";
								else
									$t .= ";";
							} else
								$t .= base_convert($lastt = $row["t"], 10, 36) . ";";
							if ($row["ord"] == "1") {
								if (substr($t, -1) === ";")
									$h .= substr($t, 0, -1) . "/" . MaskDelimiters($row["val"]) . "\n";
								else
									$h .= "$t;" . MaskDelimiters($row["val"]) . "\n";
							} else
								$h .= $t . $row["ord"] . ";" . MaskDelimiters($row["val"]) . "\n";
						}
						fwrite($file, $h);
					} while ($rows_number == $limit);
					fclose($file);
					$zip = new ZipArchive();
					$zip->open("$path/$name.zip", ZipArchive::CREATE);
					$zip->addFile("$path/$name", $name);
					$zip->close();
					unlink("$path/$name");
					header("Location: /$z/dir_admin/?templates=1&add_path=/backups&gf=$name.zip");
					die();
					break;
				default:
					$blocks[$block]["title"][] = "Integral";
			}
			break;
		case "&edit_typs":
			$data_set = Exec_sql("SELECT typs.id, typs.t, refs.id ref_val, typs.ord uniq
, CASE WHEN refs.id!=refs.t THEN refs.val ELSE typs.val END val
, reqs.id req_id, reqs.t req_t, reqs.ord, reqs.val attrs, ref_typs.t reft
FROM $z typs LEFT JOIN $z refs ON refs.id=typs.t AND refs.id!=refs.t
LEFT JOIN $z reqs ON reqs.up=typs.id
LEFT JOIN $z req_typs ON req_typs.id=reqs.t AND req_typs.id!=req_typs.t
LEFT JOIN $z ref_typs ON ref_typs.id=req_typs.t AND ref_typs.id!=ref_typs.t
WHERE typs.up=0 AND typs.id!=typs.t
ORDER BY ISNULL(reqs.id), CASE WHEN refs.id!=refs.t THEN refs.val ELSE typs.val END, refs.id DESC, reqs.ord", "Get Typs & Reqs");
			while ($row = mysqli_fetch_array($data_set))
				foreach ($row as $key => $value)
					$blocks[$block][$key][] = str_replace("\\", "\\\\", "$value");
			if (isApi()) {
				$GLOBALS["GLOBAL_VARS"]["api"]["edit_types"] = $blocks[$block];
				$GLOBALS["GLOBAL_VARS"]["api"]["types"]      = $GLOBALS["basics"];
				if (Check_Types_Grant() == "WRITE")
					$GLOBALS["GLOBAL_VARS"]["api"]["editable"] = 1;
				die(json_encode($GLOBALS["GLOBAL_VARS"]["api"], JSON_HEX_QUOT));
			}
			break;
		case "&editables":
			if (Check_Types_Grant() == "WRITE")
				$blocks[$block]["ok"][] = "";
			break;
		case "&types":
			foreach ($GLOBALS["basics"] as $key => $value) {
				$blocks[$block]["typ"][] = "$key";
				$blocks[$block]["val"][] = $value;
			}
			break;
		case "&object":
			if ($id == 0)
				die(t9n("[RU]Ошибка: id=0 или не задан[EN]Object id is empty or 0"));
			$data_set = Exec_sql("SELECT a.*, typs.val typ_name, typs.t base_typ FROM $z a, $z typs WHERE a.id=$id AND typs.id=a.t AND typs.up=0", "Get Object");
			if ($row = mysqli_fetch_array($data_set)) {
				Check_Val_Granted($row["t"], is_null($row["val"]) ? NULL : $row["val"]);
				$GLOBALS["parent_val"]        = $row["val"];
				$blocks[$block]["id"][]       = $GLOBALS["cur_id"] = $row["id"];
				$blocks[$block]["up"][]       = $GLOBALS["parent_id"] = $row["up"];
				$blocks[$block]["typ"][]      = $GLOBALS["parent_typ"] = $row["t"];
				$blocks[$block]["typ_name"][] = $row["typ_name"];
				$blocks[$block]["base_typ"][] = $GLOBALS["parent_base"] = $row["base_typ"];
				trace("Check_Grant for " . $row["id"]);
				if (Check_Grant($row["id"], 0, "WRITE", FALSE))
					$blocks[$block]["disabled"][] = $GLOBALS["parent_disabled"] = "";
				else
					$blocks[$block]["disabled"][] = $GLOBALS["parent_disabled"] = "DISABLED";
				trace("_Grant for " . $row["id"] . " is " . $GLOBALS["parent_disabled"]);
				$v = $row["val"];
				if (in_array($GLOBALS["REV_BT"][$row["base_typ"]], array(
					"CHARS",
					"MEMO",
					"FILE",
					"HTML"
				)))
					$v = Get_tail($row["id"], $v);
				if ($GLOBALS["REV_BT"][$row["base_typ"]] != "SIGNED")
					$v = Format_Val_View($row["base_typ"], $v, $id);
				$blocks[$block]["val"][] = htmlspecialchars($v);
				GetObjectReqs($GLOBALS["parent_typ"], $id);
			}
			foreach ($_REQUEST as $key => $value)
				if (substr($key, 0, 7) == "SEARCH_")
					if (strlen($value))
						$GLOBALS["search"][substr($key, 7)] = $value;
			break;
		case "&new_req":
			$base = $GLOBALS["REV_BT"][$blocks["&main"]["CUR_VARS"]["typ"]];
			if (($base != "REPORT_COLUMN") && ($base != "GRANT")) {
				$blocks[$block]["new_req"][] = "";
				$blocks[$block]["type"][]    = ($base == "DATE" ? "date" : "text");
			}
			break;
		case "&new_req_report_column":
			if ($GLOBALS["REV_BT"][$blocks["&main"]["CUR_VARS"]["typ"]] == "REPORT_COLUMN")
				$blocks[$block]["new_req"][] = "";
			break;
		case "&new_req_grant":
			if ($GLOBALS["REV_BT"][$blocks["&main"]["CUR_VARS"]["typ"]] == "GRANT")
				$blocks[$block]["new_req"][] = "";
			break;
		case "&grant_list":
			$existing   = $req = array();
			$parent_id  = $GLOBALS["parent_id"];
			$parent_val = $GLOBALS["parent_val"];
			$data_set   = Exec_sql("SELECT gr.id, gr.val, reqs.id req_id, reqs.t req_t, req_typ.val req_val, ref_reqs.val ref_val
 FROM $z gr LEFT JOIN ($z reqs CROSS JOIN $z req_typ) ON gr.id!=1 AND reqs.up=gr.id AND req_typ.id=reqs.t
  LEFT JOIN $z ref_reqs ON ref_reqs.id!=ref_reqs.t AND ref_reqs.id=req_typ.t
 WHERE gr.up=0 AND gr.t!=gr.id AND gr.val!='' AND !COALESCE(gr.t=0 OR req_typ.t=0, false)
 ORDER BY gr.val, reqs.ord", "Get available Grants");
			while ($row = mysqli_fetch_array($data_set)) {
				$i = $row["id"];
				if (!isset($existing[$i]) && !isset($req[$i])) {
					$existing[$i]              = "";
					$blocks[$block]["id"][$i]  = $i;
					$blocks[$block]["val"][$i] = $row["val"];
					if ($GLOBALS["parent_val"] == $i)
						$blocks[$block]["selected"][$i] = "SELECTED";
					else
						$blocks[$block]["selected"][$i] = "";
				}
				if (($row["req_id"] != 0) && !isset($existing[$row["req_id"]])) {
					$req[$row["req_t"]] = "";
					if (isset($existing[$row["req_t"]]))
						unset($blocks[$block]["id"][$row["req_t"]], $blocks[$block]["val"][$row["req_t"]], $blocks[$block]["selected"][$row["req_t"]]);
					$blocks[$block]["id"][$row["req_id"]]  = $row["req_id"];
					$blocks[$block]["val"][$row["req_id"]] = $row["val"] . " -> " . $row["req_val"] . $row["ref_val"];
					if ($GLOBALS["parent_val"] == $row["req_id"])
						$blocks[$block]["selected"][$row["req_id"]] = "SELECTED";
					else
						$blocks[$block]["selected"][$row["req_id"]] = "";
				}
			}
			foreach (
				array(
					0,
					1,
					10
				) as $key
			)
				if (($GLOBALS["GLOBAL_VARS"]["action"] != "object") || !isset($existing[$key])) {
					$blocks[$block]["id"][]  = $key;
					$blocks[$block]["val"][] = Format_Val_View($GLOBALS["BT"]["GRANT"], "$key");
					if ((string) $GLOBALS["parent_val"] == "$key")
						$blocks[$block]["selected"][] = "SELECTED";
					else
						$blocks[$block]["selected"][] = "";
				}
			break;
		case "&editreq_grant":
			if ($GLOBALS["REV_BT"][$GLOBALS["parent_base"]] == "GRANT")
				$blocks[$block]["typ"][] = $GLOBALS["parent_typ"];
			break;
		case "&editreq_report_column":
			if ($GLOBALS["REV_BT"][$GLOBALS["parent_base"]] == "REPORT_COLUMN")
				$blocks[$block]["typ"][] = $blocks[$block]["val"][] = $GLOBALS["parent_typ"];
			break;
		case "&edit_req":
			$base = $GLOBALS["REV_BT"][$GLOBALS["parent_base"]];
			if (($base != "REPORT_COLUMN") && ($base != "GRANT")) {
				$blocks[$block]["typ"][]  = $GLOBALS["parent_typ"];
				$blocks[$block]["type"][] = ($base == "DATE" ? "date" : "text");
			}
			break;
		case "&rep_col_list":
			$existing   = $in_list = array();
			$parent_id  = $GLOBALS["parent_id"];
			$parent_val = $GLOBALS["parent_val"];
			$data_set   = Exec_sql("SELECT a.val col_id, CASE WHEN pars.id IS NULL THEN a.val ELSE pars.id END par_id
FROM $z typs, $z a LEFT JOIN ($z reqs CROSS JOIN $z pars) ON pars.id=reqs.up AND reqs.id=a.val
WHERE $parent_id!=0 AND a.up=$parent_id AND a.val!=0 AND a.t=typs.id AND typs.t=" . $GLOBALS["BT"]["REPORT_COLUMN"] . " ORDER BY a.ord", "Get Existing Report Columns");
			if ($row = mysqli_fetch_array($data_set)) {
				do {
					$v = $row["par_id"];
					if (!isset($in))
						$in = ":$v:";
					elseif (strpos($in, ":$v:") === false)
						$in .= ",:$v:";
				} while ($row = mysqli_fetch_array($data_set));
				if (strlen($in))
					$in = str_replace(":", "", $in);
				else
					$in = 0;
				$data_set = Exec_sql("SELECT refs.t, links.up FROM $z refs, $z links, $z typs
   WHERE refs.t IN ($in) AND typs.up=0 AND links.t=refs.id AND typs.id=links.up AND typs.val!=''
 UNION SELECT linx.up, refs.t FROM $z refs, $z linx 
   WHERE linx.up IN ($in) AND linx.t=refs.id
 UNION SELECT arr_refs.up, arrs.id FROM $z arrs, $z reqs, $z arr_refs
   WHERE arrs.val!='' AND arrs.up=0 AND reqs.up=arrs.id 
	AND arr_refs.t=arrs.id AND arr_refs.up IN ($in) AND reqs.ord=1
 UNION SELECT arrs.id, arr_refs.up FROM $z arrs, $z reqs, $z arr_refs USE INDEX (up_t), $z objs 
   WHERE arrs.up=0 AND reqs.up=arrs.id AND arr_refs.t=arrs.id AND objs.up=0
	AND objs.id=arr_refs.up AND arrs.id+0 IN ($in) AND reqs.ord=1", "Get all referenced Objects");
				$refs     = "";
				while ($row = mysqli_fetch_array($data_set)) {
					if (!isset($GLOBALS["basics"][$row[0]]))
						if (strpos($refs, ":" . $row[0] . ":") === false)
							$refs .= ",:" . $row[0] . ":";
					if (!isset($GLOBALS["basics"][$row[1]]))
						if (strpos($refs, ":" . $row[1] . ":") === false)
							$refs .= ",:" . $row[1] . ":";
				}
				if (strlen($refs))
					$in = str_replace(":", "", substr($refs, 1));
				$data_set = Exec_sql("SELECT pars.id par_id, reqs.id req_id, pars.val par_name, pars.t par_base
, req_typs.id req_typ, CASE WHEN req_typs.val='' THEN ref_reqs.val ELSE req_typs.val END req_name
, ref_reqs.id ref_typ, reqs.val ref_name, cols.val cols, arr.id arr
, CASE WHEN req_typs.val='' THEN ref_reqs.t ELSE req_typs.t END base
FROM $z pars LEFT JOIN $z reqs ON reqs.up=pars.id 
   LEFT JOIN $z req_typs ON req_typs.id=reqs.t
LEFT JOIN $z ref_reqs ON ref_reqs.id=req_typs.t AND ref_reqs.id!=ref_reqs.t
LEFT JOIN $z arr ON ref_reqs.id IS NULL AND arr.up=req_typs.id AND arr.ord=1
LEFT JOIN (SELECT val FROM $z WHERE up=$parent_id AND val!='$parent_val' LIMIT 1) cols ON cols.val=reqs.id
WHERE pars.id IN ($in) AND pars.id!=pars.t ORDER BY pars.val, reqs.ord", "Get Available Report Columns");
			} else
				$data_set = Exec_sql("SELECT pars.id par_id, reqs.id req_id, pars.val par_name, reqs.val ref_name, NULL cols
, CASE WHEN req_typs.val='' THEN ref_reqs.val ELSE req_typs.val END req_name, arr.id arr
, CASE WHEN req_typs.val='' THEN ref_reqs.t ELSE req_typs.t END base
FROM $z pars, $z reqs
LEFT JOIN $z req_typs ON req_typs.id=reqs.t
LEFT JOIN $z ref_reqs ON ref_reqs.id=req_typs.t AND ref_reqs.id!=ref_reqs.t
LEFT JOIN $z arr ON ref_reqs.id IS NULL AND arr.up=req_typs.id AND arr.ord=1
WHERE pars.up=0 AND pars.val!='' AND reqs.up=pars.id AND req_typs.t!=0 ORDER BY pars.val, reqs.ord", "Get All Report Columns");
			while ($row = mysqli_fetch_array($data_set)) {
				$pid = $row["par_id"];
				if (isset($blocks["&main..&uni_obj.&new_req_report_column"]) && !Grant_1level($pid))
					continue;
				if (!isset($in_list[$pid]) || (!isset($parent_listed) && ($pid == $GLOBALS["parent_val"]))) {
					if ((!isset($parent_listed) && ($pid == $GLOBALS["parent_val"])))
						$parent_listed = TRUE;
					$in_list[$pid]           = "";
					$blocks[$block]["id"][]  = $pid;
					$blocks[$block]["val"][] = $row["par_name"];
					if ($GLOBALS["parent_val"] == $pid)
						$blocks[$block]["selected"][] = "SELECTED";
					else
						$blocks[$block]["selected"][] = "";
					if (isApi() && $row["base"]) {
						$GLOBALS["GLOBAL_VARS"]["api"]["rep_col_list"][$pid]["id"]   = $row["par_id"];
						$GLOBALS["GLOBAL_VARS"]["api"]["rep_col_list"][$pid]["name"] = $row["par_name"];
						$GLOBALS["GLOBAL_VARS"]["api"]["rep_col_list"][$pid]["type"] = $GLOBALS["REV_BT"][$row["par_base"]];
					}
				}
				if (strlen($row["arr"]) || !isset($row["req_id"]))
					continue;
				if (!Check_Grant($pid, $row["req_id"], "READ", FALSE))
					continue;
				$alias = $row["par_name"] . " -> " . $row["req_name"];
				if (isset($row["ref_typ"]) && strlen($row["ref_name"])) {
					$tmp = FetchAlias($row["ref_name"], $row["req_name"]);
					if ($tmp != $row["req_name"])
						$alias = $row["par_name"] . " -> $tmp (" . $row["req_name"] . ")";
				}
				$blocks[$block]["val"][] = $alias;
				$blocks[$block]["id"][]  = $row["req_id"];
				if ($GLOBALS["parent_val"] == $row["req_id"])
					$blocks[$block]["selected"][] = "SELECTED";
				else
					$blocks[$block]["selected"][] = "";
				if (isset($existing[$pid]) && isset($existing[$row["ref_typ"]]))
					if (isset($existing[$pid . "_" . $row["ref_typ"]])) {
						if ($existing[$pid . "_" . $row["ref_typ"]] == 0)
							$GLOBALS["warning"] .= t9n("[RU]Тип <b>" . $row["req_name"] . "</b> используется более 1 раза как реквизит типа " . "[EN]Type <b>" . $row["req_name"] . "</b> is used more than once as attribute of type ") . "<b>" . $row["par_name"] . "</b><br/>";
						$existing[$pid . "_" . $row["ref_typ"]]++;
					} else
						$existing[$pid . "_" . $row["ref_typ"]] = 0;
				if (isApi() && $row["base"]) {
					$GLOBALS["GLOBAL_VARS"]["api"]["rep_col_list"][$row["req_id"]]["id"]   = $row["req_typ"];
					$GLOBALS["GLOBAL_VARS"]["api"]["rep_col_list"][$row["req_id"]]["name"] = $alias;
					$GLOBALS["GLOBAL_VARS"]["api"]["rep_col_list"][$row["req_id"]]["type"] = $GLOBALS["REV_BT"][$row["base"]];
				}
			}
			$blocks[$block]["id"][]  = "0";
			$blocks[$block]["val"][] = CUSTOM_REP_COL;
			if ($GLOBALS["parent_val"] == "0")
				$blocks[$block]["selected"][] = "SELECTED";
			else
				$blocks[$block]["selected"][] = "";
			break;
		case "&warnings":
			if (isset($GLOBALS["warning"]))
				$blocks[$block]["warning"][] = $GLOBALS["warning"];
			break;
		case "&tabs":
			if (!isset($GLOBALS["TABS"]))
				break;
			$tab = isset($_REQUEST["tab"]) ? $_REQUEST["tab"] : 0;
			foreach ($GLOBALS["TABS"] as $key => $value) {
				$blocks[$block]["tab"][] = "$key";
				$blocks[$block]["val"][] = "$value";
				if ($tab == $key || ($tab == 0 && !count($blocks[$block]["class"]))) {
					$has_active                = true;
					$blocks[$block]["class"][] = "class=\"tab-link active\"";
				} else
					$blocks[$block]["class"][] = "class=\"tab-link\"";
			}
			$blocks[$block]["tab"][]   = "1";
			$blocks[$block]["val"][]   = t9n("[RU]Все[EN]All");
			$blocks[$block]["class"][] = $has_active ? "class=\"tab-link\"" : "class=\"tab-link active\"";
			break;
		case "&object_reqs":
			$rows = isset($GLOBALS["ObjectReqs"]) ? $GLOBALS["ObjectReqs"] : array();
			foreach ($GLOBALS["REQS"] as $key => $value) {
				if (isset($rows[$key]))
					$row = $rows[$key];
				elseif (isset($GLOBALS["REF_typs"][$key]))
					$row = isset($rows[$GLOBALS["REF_typs"][$key]]) ? $rows[$GLOBALS["REF_typs"][$key]] : NULL;
				elseif (isset($GLOBALS["ARR_typs"][$key]))
					$row = array(
						"arr_num" => isset($rows[$GLOBALS["ARR_typs"][$key]]["arr_num"]) ? (int) $rows[$GLOBALS["ARR_typs"][$key]]["arr_num"] : NULL
					);
				else
					$row = array();
				$row["attrs"]            = $GLOBALS["REQS"][$key]["attrs"];
				$base_typ                = $GLOBALS["REQS"][$key]["base_typ"];
				$GLOBALS["REV_BT"][$key] = $GLOBALS["REV_BT"][$base_typ];
				if (isset($GLOBALS["GRANTS"][$key]))
					if ($GLOBALS["GRANTS"][$key] == "BARRED")
						continue;
				$v = isset($row["val"]) ? $row["val"] : "";
				if ($GLOBALS["REV_BT"][$base_typ] == "BUTTON") {
					$blocks["BUTTONS"][$GLOBALS["REQS"][$key]["val"]] = $GLOBALS["REQS"][$key]["attrs"];
					continue;
				}
				if ((isset($row["id"]) ? $row["id"] : 0) > 0) {
					if (($GLOBALS["REV_BT"][$base_typ] != "SIGNED") && !isset($GLOBALS["REF_typs"][$key]))
						$v = Format_Val_View($base_typ, $v, $row["id"]);
				} else {
					if (strlen($row["attrs"])) {
						$attrs = str_replace(NOT_NULL_MASK, "", $row["attrs"]);
						$attrs = preg_replace(ALIAS_MASK, "", $attrs);
						$v     = BuiltIn($attrs);
						if ($v == $attrs) {
							$id_bak    = $id;
							$block_bak = $block;
							Get_block_data($attrs);
							$id    = $id_bak;
							$block = $block_bak;
							if (isset($blocks[$attrs][strtolower($attrs)])) {
								if (count($blocks[$attrs][strtolower($attrs)]))
									$v = array_shift($blocks[$attrs][strtolower($attrs)]);
							} elseif (isset($blocks[$attrs]))
								foreach ($blocks[$attrs] as $tmp) {
									$v = array_shift($tmp);
									break;
								}
						}
					} else
						$v = "";
				}
				if ($GLOBALS["REV_BT"][$base_typ] != "FILE")
					$blocks[$block]["val"][] = htmlspecialchars($v);
				else
					$blocks[$block]["val"][] = $v;
				if (isApi()) {
					$GLOBALS["GLOBAL_VARS"]["api"]["reqs"][$key]["type"]  = $GLOBALS["REQS"][$key]["val"];
					$GLOBALS["GLOBAL_VARS"]["api"]["reqs"][$key]["value"] = $v;
				}
				$blocks[$block]["reqid"][]    = isset($row["id"]) ? $row["id"] : "";
				$blocks[$block]["typ"][]      = $key;
				$blocks[$block]["up"][]       = $id;
				$blocks[$block]["typ_name"][] = $GLOBALS["REQS"][$key]["val"];
				$blocks[$block]["not_null"][] = strpos($row["attrs"], NOT_NULL_MASK) === false ? 0 : 1;
				$blocks[$block]["arr_num"][]  = isset($row["arr_num"]) ? $row["arr_num"] : 0;
				$blocks[$block]["arr"][]      = isset($GLOBALS["ARR_typs"][$key]) ? $GLOBALS["ARR_typs"][$key] : 0;
				$blocks[$block]["attrs"][]    = $row["attrs"];
				if (isset($GLOBALS["ARR_typs"][$key]))
					$GLOBALS["REV_BT"][$key] = "ARRAY";
				trace("Check GRANTS for $key");
				if (Val_barred_by_mask($key, isset($row["val"]) ? $v : NULL))
					$blocks[$block]["disabled"][] = "DISABLED";
				elseif (isset($GLOBALS["GRANTS"][$key])) {
					trace("GRANTS for $key: " . $GLOBALS["GRANTS"][$key]);
					if ($GLOBALS["GRANTS"][$key] == "WRITE")
						$blocks[$block]["disabled"][] = $GLOBALS["enable_save"] = "";
					else
						$blocks[$block]["disabled"][] = "DISABLED";
				} else
					$blocks[$block]["disabled"][] = $GLOBALS["parent_disabled"];
				if (isset($GLOBALS["enable_save"])) {
					$blocks[$block]["enable_save"][] = "<script>enable_save=1;</script>";
					unset($GLOBALS["enable_save"]);
				} else
					$blocks[$block]["enable_save"][] = "";
				if (isset($GLOBALS["REF_typs"][$key])) {
					if (strlen($v))
						Check_Val_granted($key, $row["ref_val"]);
					$GLOBALS["REV_BT"][$key]      = "REFERENCE";
					$blocks[$block]["ref"][]      = $GLOBALS["REF_typs"][$key];
					$blocks[$block]["base_typ"][] = $base_typ;
				} else {
					if (strlen($v))
						Check_Val_granted($key, $v);
					$blocks[$block]["ref"][]      = "";
					$blocks[$block]["base_typ"][] = $base_typ;
				}
			}
			break;
		case "&editreq_array":
			if ($blocks[$blocks[$block]["PARENT"]]["CUR_VARS"]["arr"] != 0) {
				$blocks[$block]["typ"][] = $blocks[$blocks[$block]["PARENT"]]["CUR_VARS"]["arr"];
				$blocks[$block]["val"][] = $blocks[$blocks[$block]["PARENT"]]["CUR_VARS"]["val"];
			}
			break;
		case "&editreq_pwd":
			if ($GLOBALS["REV_BT"][$blocks[$blocks[$block]["PARENT"]]["CUR_VARS"]["typ"]] == "PWD")
				$blocks[$block]["val"][] = "******";
			break;
		case "&editreq_boolean":
			if ($GLOBALS["REV_BT"][$blocks[$blocks[$block]["PARENT"]]["CUR_VARS"]["typ"]] == strtoupper(substr($block_name, 9)))
				if ($GLOBALS["REV_BT"][$blocks[$blocks[$block]["PARENT"]]["CUR_VARS"]["base_typ"]] == "BOOLEAN")
					$blocks[$block]["checked"][] = ($blocks[$blocks[$block]["PARENT"]]["CUR_VARS"]["val"] != "" ? "CHECKED" : "");
		case "&editreq_file":
			$blocks[$block]["reqid"][] = $blocks[$blocks[$block]["PARENT"]]["CUR_VARS"]["reqid"];
		case "&editreq_short":
		case "&editreq_chars":
		case "&editreq_html":
		case "&editreq_memo":
		case "&editreq_date":
		case "&editreq_datetime":
		case "&editreq_reference":
		case "&editreq_signed":
		case "&editreq_number":
		case "&editreq_calculatable":
			if ($GLOBALS["REV_BT"][$blocks[$blocks[$block]["PARENT"]]["CUR_VARS"]["typ"]] == strtoupper(substr($block_name, 9))) {
				$blocks[$block]["typ"][]      = $blocks[$blocks[$block]["PARENT"]]["CUR_VARS"]["typ"];
				$blocks[$block]["ref"][]      = $blocks[$blocks[$block]["PARENT"]]["CUR_VARS"]["ref"];
				$blocks[$block]["base_typ"][] = $blocks[$blocks[$block]["PARENT"]]["CUR_VARS"]["base_typ"];
				$blocks[$block]["disabled"][] = $blocks[$blocks[$block]["PARENT"]]["CUR_VARS"]["disabled"];
				if (($blocks[$blocks[$block]["PARENT"]]["CUR_VARS"]["val"] == "") && isset($_REQUEST["t" . $blocks[$blocks[$block]["PARENT"]]["CUR_VARS"]["typ"]]))
					$blocks[$block]["val"][] = $_REQUEST["t" . $blocks[$blocks[$block]["PARENT"]]["CUR_VARS"]["typ"]];
				else
					$blocks[$block]["val"][] = $blocks[$blocks[$block]["PARENT"]]["CUR_VARS"]["val"];
			}
			break;
		case "&array_val":
			if (isset($blocks[$blocks[$block]["PARENT"]]["CUR_VARS"]["arr"]))
				if (($blocks[$blocks[$block]["PARENT"]]["CUR_VARS"]["arr"] != 0) && strlen($blocks[$blocks[$block]["PARENT"]]["CUR_VARS"]["val"]))
					$blocks[$block]["val"][] = $blocks[$blocks[$block]["PARENT"]]["CUR_VARS"]["val"];
			break;
		case "&nullable_req":
		case "&nullable_req_close":
			if ($blocks[$blocks[$block]["PARENT"]]["CUR_VARS"]["not_null"] != 0)
				if (($blocks[$blocks[$block]["PARENT"]]["CUR_VARS"]["val"] == "") && ($blocks[$blocks[$block]["PARENT"]]["CUR_VARS"]["arr_num"] == 0))
					$blocks[$block]["not_null"][] = "*";
			break;
		case "&ref_create_granted":
			if (Grant_1level(($blocks[$blocks[$block]["PARENT"]]["CUR_VARS"]["ref"])) == "WRITE")
				$blocks[$block]["typ"][] = $blocks[$blocks[$block]["PARENT"]]["CUR_VARS"]["typ"];
			$blocks[$block]["orig"][] = $blocks[$blocks[$block]["PARENT"]]["CUR_VARS"]["ref"];
			break;
		case "&add_obj_ref_reqs":
			$cur_ref_req = $blocks[$blocks[$block]["PARENT"]]["CUR_VARS"]["ref"];
			$cur_ref_typ = $blocks[$blocks[$block]["PARENT"]]["CUR_VARS"]["typ"];
			$search_val  = "";
			if (isset($GLOBALS["search"][$cur_ref_typ]))
				$search_arr = explode("/", addslashes($GLOBALS["search"][$cur_ref_typ]));
			$data_set  = Exec_sql("SELECT def_reqs.id, ref_reqs.id ref_req, base.t base, is_ref.val ref_name
   , CASE WHEN length(base.val)!=0 THEN 0 ELSE base.t END is_ref
 FROM $z r JOIN $z def_reqs ON def_reqs.up=r.t
 JOIN $z base ON base.id=def_reqs.t JOIN $z is_ref ON base.t=is_ref.id
 LEFT JOIN $z ref_reqs ON ref_reqs.up=r.id AND ref_reqs.t=def_reqs.t
WHERE r.t=$cur_ref_req and r.up=0 ORDER BY ref_reqs.ord", "Get ref's reqs");
			$joins     = $reqs = $reqs_granted = $sub_reqs = $join_granted = $search_req = "";
			$req_count = 0;
			if (isset($search_arr[0]))
				if (strlen($search_arr[0])) {
					$GLOBALS["where"] = "";
					Construct_WHERE($blocks[$blocks[$block]["PARENT"]]["CUR_VARS"]["base_typ"], array(
						"F" => $search_arr[$req_count]
					), $blocks[$blocks[$block]["PARENT"]]["CUR_VARS"]["base_typ"], FALSE, FALSE);
					$search_req = str_replace("a" . $blocks[$blocks[$block]["PARENT"]]["CUR_VARS"]["base_typ"] . ".val", "vals.val", $GLOBALS["where"]);
				}
			while ($row = mysqli_fetch_array($data_set)) {
				$req = $row["id"];
				if (isset($row["ref_req"])) {
					$req_count++;
					if ($row["is_ref"])
						$joins .= " LEFT JOIN ($z r$req CROSS JOIN $z a$req) ON r$req.up=vals.id AND a$req.id=r$req.t AND a$req.t=" . $row["is_ref"];
					else
						$joins .= " LEFT JOIN $z a$req ON a$req.up=vals.id AND a$req.t=$req";
					$reqs .= ", $req" . "val";
					$sub_reqs .= ", a$req.val $req" . "val";
					if (isset($search_arr[$req_count]))
						if (strlen($search_arr[$req_count])) {
							$GLOBALS["REV_BT"][$req] = $GLOBALS["REV_BT"][$row["base"]];
							$GLOBALS["where"]        = "";
							Construct_WHERE($req, array(
								"F" => $search_arr[$req_count]
							), 1, FALSE, FALSE);
							$search_req .= $GLOBALS["where"];
						}
				}
				if (isset($GLOBALS["GRANTS"]["mask"][$req])) {
					unset($granted);
					foreach ($GLOBALS["GRANTS"]["mask"][$req] as $mask) {
						$GLOBALS["where"] = $GLOBALS["join"] = "";
						if ($GLOBALS["REV_BT"][$row["base"]])
							$GLOBALS["REV_BT"][$req] = $GLOBALS["REV_BT"][$row["base"]];
						else {
							$GLOBALS["REV_BT"][$req]   = "REFERENCE";
							$GLOBALS["REF_typs"][$req] = $row["base"];
						}
						$GLOBALS["where"] = "";
						Construct_WHERE($req, array(
							"F" => $mask
						), $cur_ref_req, $cur_ref_typ);
						if (isset($granted))
							$granted .= " OR " . substr($GLOBALS["where"], 4);
						else
							$granted = substr($GLOBALS["where"], 4);
						if (strpos($join_granted . $joins, "$z a$req") === FALSE)
							$join_granted .= $GLOBALS["join"];
					}
					$reqs_granted .= " AND ($granted) ";
				}
			}
			if (isset($GLOBALS["GRANTS"]["mask"][$cur_ref_req])) {
				unset($granted);
				foreach ($GLOBALS["GRANTS"]["mask"][$cur_ref_req] as $mask) {
					$GLOBALS["where"] = "";
					Construct_WHERE($blocks[$blocks[$block]["PARENT"]]["CUR_VARS"]["base_typ"], array(
						"F" => $mask
					), 1, FALSE, TRUE);
					if (isset($granted))
						$granted .= " OR " . substr($GLOBALS["where"], 4);
					else
						$granted = substr($GLOBALS["where"], 4);
				}
				$reqs_granted .= str_replace("a" . $blocks[$blocks[$block]["PARENT"]]["CUR_VARS"]["base_typ"] . ".val", "vals.val", " AND ($granted) ");
			}
			if ($blocks[$blocks[$block]["PARENT"]]["CUR_VARS"]["val"])
				$cur_val = " UNION (SELECT vals.id, vals.val $sub_reqs FROM $z vals $joins WHERE vals.id=" . $blocks[$blocks[$block]["PARENT"]]["CUR_VARS"]["val"] . ") ";
			elseif (isset($_REQUEST["t" . $blocks[$blocks[$block]["PARENT"]]["CUR_VARS"]["typ"]]))
				$cur_val = " UNION (SELECT vals.id, vals.val $sub_reqs FROM $z vals $joins WHERE vals.id=" . addslashes($_REQUEST["t" . $blocks[$blocks[$block]["PARENT"]]["CUR_VARS"]["typ"]]) . ") ";
			else
				$cur_val = "";
			$sql                 = "SELECT vals.id, vals.val ref_val $reqs 
FROM (SELECT vals.id, vals.val $sub_reqs FROM $z vals  $join_granted $joins, $z pars
WHERE pars.id=vals.up AND pars.up!=0 AND vals.t=$cur_ref_req $search_val $reqs_granted $search_req LIMIT " . DDLIST_ITEMS . ") vals
$cur_val ORDER BY ref_val";
			$data_set            = Exec_sql($sql, "Get Object ref reqs");
			$blocks["ref_count"] = mysqli_num_rows($data_set);
			while ($row = mysqli_fetch_array($data_set)) {
				$i    = 1;
				$reqs = "";
				while ($i <= $req_count) {
					$i++;
					if (strlen($row[$i]))
						$reqs .= " / " . $row[$i];
					else
						$reqs .= " / --";
				}
				$blocks[$block]["r"][]   = $cur_ref_typ;
				$blocks[$block]["id"][]  = $row["id"];
				$blocks[$block]["val"][] = Format_Val_View($blocks[$blocks[$block]["PARENT"]]["CUR_VARS"]["base_typ"], htmlspecialchars($row["ref_val"])) . $reqs;
				if (($blocks[$blocks[$block]["PARENT"]]["CUR_VARS"]["val"] == 0) && isset($_REQUEST["t" . $blocks[$blocks[$block]["PARENT"]]["CUR_VARS"]["typ"]])) {
					if ($_REQUEST["t" . $blocks[$blocks[$block]["PARENT"]]["CUR_VARS"]["typ"]] == $row["id"])
						$blocks[$block]["selected"][] = " SELECTED";
					else
						$blocks[$block]["selected"][] = "";
				} elseif ($blocks[$blocks[$block]["PARENT"]]["CUR_VARS"]["val"] == $row["id"])
					$blocks[$block]["selected"][] = " SELECTED";
				elseif (($blocks["ref_count"] == 1) && (isset($_REQUEST["t" . $blocks[$blocks[$block]["PARENT"]]["CUR_VARS"]["typ"]]) || isset($_REQUEST["SEARCH_" . $blocks[$blocks[$block]["PARENT"]]["CUR_VARS"]["typ"]])))
					$blocks[$block]["selected"][] = " SELECTED";
				else
					$blocks[$block]["selected"][] = "";
			}
			break;
		case "&seek_refs":
			if ($blocks[$blocks[$block]["PARENT"]]["CUR_VARS"]["disabled"] == "")
				if (($blocks["ref_count"] >= DDLIST_ITEMS) || isset($GLOBALS["search"][$blocks[$blocks[$block]["PARENT"]]["CUR_VARS"]["typ"]]))
					$blocks[$block]["search"][] = "" . $GLOBALS["search"][$blocks[$blocks[$block]["PARENT"]]["CUR_VARS"]["typ"]];
			break;
		case "&uni_obj_list":
			if (isset($_GET["val"]))
				$cond = "AND a.val LIKE '" . addslashes($_GET["val"]) . "'";
			else
				$cond = "";
			$sql      = "SELECT a.id, a.val, a.t, reqs.t reqs_t FROM $z a LEFT JOIN $z reqs ON reqs.up=a.id
WHERE a.up=0 AND a.id!=a.t AND a.val!='' AND a.t!=0 $cond ORDER BY a.val";
			$data_set = Exec_sql($sql, "Get all independent Typs");
			while ($row = mysqli_fetch_array($data_set))
				if (($GLOBALS["REV_BT"][$row["t"]] != "CALCULATABLE") && ($GLOBALS["REV_BT"][$row["t"]] != "BUTTON")) {
					if (!isset($req[$row["id"]]))
						$typ[$row["id"]] = $row["val"];
					if ($row["reqs_t"]) {
						unset($typ[$row["reqs_t"]]);
						$req[$row["reqs_t"]] = "";
					}
				}
			if (count($typ))
				foreach ($typ as $id => $val)
					if (Grant_1level($id)) {
						$blocks[$block]["id"][]  = $id;
						$blocks[$block]["val"][] = htmlspecialchars($val);
					}
			if (isApi()) {
				$json = array();
				foreach ($blocks[$block]["id"] as $key => $value)
					$json[$value] = $blocks[$block]["val"][$key];
				api_dump(json_encode($json, JSON_HEX_QUOT));
			}
			break;
		case "&uni_obj":
			if ($f_u > 1) {
				if (isset($_REQUEST["_m_del_select"])) {
					check();
					Check_Grant($f_u, $id);
				} elseif (Check_Grant($f_u, $id, "READ", FALSE) === FALSE)
					break;
			} elseif (isset($_REQUEST["_m_del_select"])) {
				check();
				if (Grant_1level($id) != "WRITE")
					die(t9n("[RU]У вас нет доступа на изменение этих данных[EN]You have no grant to delete this data"));
			} elseif (Grant_1level($id) === FALSE)
				if ($blocks["&main"]["CUR_VARS"]["parent_obj"])
					Check_Grant($blocks["&main"]["CUR_VARS"]["parent_obj"], $id, "READ");
				else
					break;
			if ((Grant_1level($id) == "WRITE") || Check_Grant($f_u, $id, "WRITE", FALSE))
				$blocks[$block]["create_granted"][] = "block";
			else
				$blocks[$block]["create_granted"][] = "none";
			if (isset($_REQUEST["order_val"]))
				$GLOBALS["ORDER_VAL"] = $_REQUEST["order_val"] == "val" ? "val" : (int) $_REQUEST["order_val"];
			else
				$GLOBALS["ORDER_VAL"] = 0;
			$f = "";
			foreach ($_REQUEST as $key => $value)
				if (($value != "") && (preg_match("/(F\_|FR\_|TO\_)/", $key)))
					$f .= "&" . $key . "=" . str_replace("\"", "&#34;", $value);
			if (isset($_REQUEST["f_show_all"]))
				$f .= "&f_show_all=1";
			if (isset($_REQUEST["full"]))
				$f .= "&full=0";
			if (isset($_REQUEST["lnx"]))
				if ($_REQUEST["lnx"] == 1)
					$f .= "&lnx=1";
			$GLOBALS["FILTER"] = $f;
			if (!isset($_REQUEST["desc"]) && ($GLOBALS["ORDER_VAL"] === "val"))
				$blocks[$block]["filter"][] = "$f&desc=0";
			else
				$blocks[$block]["filter"][] = $f;
			if (isset($blocks["&main"]["CUR_VARS"]["title"])) {
				$GLOBALS["parent_id"]         = $f_u;
				$GLOBALS["parent_val"]        = 0;
				$blocks[$block]["id"][]       = $GLOBALS["GLOBAL_VARS"]["api"]["type"]["id"] = $id;
				$blocks[$block]["up"][]       = $GLOBALS["GLOBAL_VARS"]["api"]["type"]["up"] = ($f_u > 1) ? $f_u : 1;
				$blocks[$block]["typ"][]      = $id;
				$blocks[$block]["val"][]      = $GLOBALS["GLOBAL_VARS"]["api"]["type"]["val"] = $blocks["&main"]["CUR_VARS"]["title"];
				$blocks[$block]["base_typ"][] = $GLOBALS["GLOBAL_VARS"]["api"]["base"]["id"] = $blocks["&main"]["CUR_VARS"]["typ"];
				$GLOBALS["REV_BT"][$id]       = $GLOBALS["GLOBAL_VARS"]["api"]["type"]["base"] = $GLOBALS["REV_BT"][$blocks["&main"]["CUR_VARS"]["typ"]];
				$blocks[$block]["f_i"][]      = isset($_REQUEST["F_I"]) ? (int) $_REQUEST["F_I"] : "";
				$blocks[$block]["f_u"][]      = isset($_REQUEST["F_U"]) ? (int) $_REQUEST["F_U"] : "";
				if (isset($_REQUEST["switch_links"]))
					$GLOBALS["lnx"] = ($_REQUEST["lnx"] == 1) ? 0 : 1;
				else
					$GLOBALS["lnx"] = isset($_REQUEST["lnx"]) ? (int) $_REQUEST["lnx"] : 0;
				$blocks[$block]["lnx"][] = $GLOBALS["lnx"];
				if ($GLOBALS["lnx"] == 1) {
					$data_set         = Exec_sql("SELECT typs.id, typs.up, objs.val, refs.val refr, typs.val attr
 FROM $z a, $z typs, $z objs, $z refs
 WHERE a.t=$id AND a.up=0 AND typs.t=a.id AND objs.id=typs.up AND refs.id=a.t", "Get Links to this object");
					$GLOBALS["links"] = $GLOBALS["links_val"] = array();
					while ($row = mysqli_fetch_array($data_set)) {
						$GLOBALS["links"][$row["id"]]     = $row["up"];
						$GLOBALS["links_val"][$row["id"]] = $row["val"] . "." . FetchAlias($row["attr"], $row["refr"]);
					}
				}
			}
			break;
		case "&uni_obj_parent":
			if ($f_u > 1) {
				$data_set = Exec_sql("SELECT typs.id, typs.val typ, objs.val name, objs.up, base.t base FROM $z objs, $z typs, $z base
 WHERE typs.id=objs.t AND objs.id=$f_u AND base.id=typs.t", "Get Typ name and type");
				if ($row = mysqli_fetch_array($data_set)) {
					$blocks[$block]["tid"][]  = $row["id"];
					$blocks[$block]["typ"][]  = $row["typ"];
					$blocks[$block]["name"][] = Format_Val_View($row["base"], $row["name"]);
					$blocks[$block]["up"][]   = $row["up"];
					if (isApi()) {
						$GLOBALS["GLOBAL_VARS"]["api"]["parent"]["id"]   = $row["id"];
						$GLOBALS["GLOBAL_VARS"]["api"]["parent"]["name"] = $row["name"];
						$GLOBALS["GLOBAL_VARS"]["api"]["parent"]["type"] = $row["typ"];
						$GLOBALS["GLOBAL_VARS"]["api"]["parent"]["up"]   = $row["up"];
					}
					Check_Val_granted($row["id"], $blocks[$block]["name"][0]);
				}
			} else if (isset($GLOBALS["GRANTS"]["mask"][$blocks["&main"]["CUR_VARS"]["parent_obj"]]))
				die(t9n("[RU]К объекту задан доступ по маске родителя - укажите ID родителя[EN]A mask is defined for the parent - please provide parent ID"));
			break;
		case "&uni_obj_head":
			if (isset($_POST["import"])) {
				check();
				Export_header($id);
				$max_size = 4194304;
				if (!is_file($_FILES["bki_file"]["tmp_name"]))
					die(t9n("[RU]Выберите файл (максимальный размер: $max_size Б)[EN]Please select a file (max size is $max_size Bytes)"));
				if ($_FILES["bki_file"]["size"] > $max_size)
					die(t9n("[RU]Ошибка. Максимальный размер файла: $max_size Б[EN]The maximum file size is $max_size B)"));
				$up     = ($GLOBALS["parent_id"] > 1) ? $GLOBALS["parent_id"] : 1;
				$handle = fopen($_FILES["bki_file"]["tmp_name"], "r");
				$buffer = fgets($handle);
				if (substr($buffer, 0, 3) == pack('CCC', 0xef, 0xbb, 0xbf))
					$buffer = substr($buffer, 3);
				if (substr($buffer, 0, 4) === "DATA") {
					$plain_data = true;
					trace("Plain DATA");
					$i = $id;
				} else
					$i = (int) substr($buffer, 0, strpos($buffer, ":"));
				if (!isset($GLOBALS["GRANTS"]["EXPORT"][$i]) && ($GLOBALS["GLOBAL_VARS"]["user"] != "admin") && ($GLOBALS["GLOBAL_VARS"]["user"] != $z))
					die(t9n("[RU]У вас нет прав на загрузку объектов этого типа ($i)[EN]You are not granted to upload this type of objects ($i)"));
				if ($i > 1)
					Export_header($i);
				else
					die(t9n("[RU]Недопустимый тип метаданных $i [EN]Invalid metadata type $i"));
				$count = 1;
				if (!$plain_data) {
					if ($i != $GLOBALS["GLOBAL_VARS"]["id"]) {
						$sql      = "SELECT a.val FROM $z a LEFT JOIN $z refs ON refs.id=a.t AND refs.t!=refs.id 
	LEFT JOIN ($z obj CROSS JOIN $z req) ON obj.up=0 AND req.up=obj.id AND req.t=a.id
   WHERE a.id=$i";
						$data_set = Exec_sql($sql, "Check Typ's independence");
						if ($row = mysqli_fetch_array($data_set)) {
							if (($up == 1) && ($row["up"] != 0))
								die(t9n("[RU]Реквизит типа \"" . $row[0] . "\" (id=$i) необходимо загружать в его родительской записи" . "[EN]The object \"" . $row[0] . "\" (id=$i) should be uploaded under its parent"));
						} else if ($up != 1)
							die(t9n("[RU]Несуществующий реквизит типа $i (реквизиты можно импортировать только в составе типа)" . "[EN]Non-exiting attribute of $i (attributes are uploaded within its parent definition)"));
					}
					if ($up != 1) {
						$data_set = Exec_sql("SELECT reqs.t req, a.t par FROM $z a LEFT JOIN $z reqs ON reqs.up=a.t AND reqs.t=$i WHERE a.id=$up", "Validate Parent ID");
						if ($row = mysqli_fetch_array($data_set)) {
							if ($row["req"] != $i)
								die(t9n("[RU]Реквизит типа $i отсутствует у родителя $up типа[EN]The $i type is missing from the $up type parent" . $row["par"]));
						} else
							die(t9n("[RU]Родительская запись с id=$i не найдена[EN]Parent record with id=$i not found"));
					}
					while (true) {
						if ($buffer == "DATA\r\n") {
							trace(" Types end, data begins");
							break;
						}
						$object = explode(";", HideDelimiters($buffer));
						array_pop($object);
						$order = 0;
						$typ   = explode(":", $object[0]);
						$obj   = $typ[0];
						foreach ($object as $value)
							$GLOBALS["imported"][$obj][$order++] = UnHideDelimiters($value);
						trace("(" . $count++ . ") check $obj");
						if (count($typ) > 2)
							if (IsOccupied($obj)) {
								trace(" $obj is occupied");
								Export_header($obj);
								if ($GLOBALS["local_struct"][$obj][0] != $GLOBALS["imported"][$obj][0])
									ResolveType($typ);
							} else {
								trace(" create the Object " . $obj);
								exec_sql("INSERT INTO $z (id, up, ord, t, val) VALUES ($obj, 0, " . (isset($typ[3]) ? "1" : "0") . ", " . $GLOBALS["BT"][$typ[2]] . ", '" . addslashes($typ[1]) . "')", "Import Obj with ID");
								$GLOBALS["local_struct"][$obj][0] = $GLOBALS["imported"][$obj][0];
							}
						if (feof($handle))
							break;
						$buffer = fgets($handle);
					}
					trace("Start reconciling the objects");
					$GLOBALS["local_types"] = array();
					foreach ($GLOBALS["imported"] as $par => $reqs) {
						$parent = CheckSubst($par);
						foreach ($reqs as $order => $req) {
							if ($order == 0)
								continue;
							trace(" Imported req $order " . $reqs[$order]);
							$typ   = UnHideDelimiters(explode(":", HideDelimiters($req)));
							$value = $typ[0] . ":" . CheckSubst($typ[1]);
							if ($typ[0] == "ref") {
								trace($typ[1] . " is a ref");
								$value .= $typ[2];
							}
							$found = false;
							foreach ($GLOBALS["local_struct"][$parent] as $local_type => $local_value)
								if ($found = ($value == substr($local_value, 0, strlen($value))))
									break;
							if ($found) {
								trace("  match found for $value");
								if ($req == $local_value)
									trace("   this is a full match $req => $local_value");
								else {
									trace("   adjust $req => $local_value");
									$local = UnHideDelimiters(explode(":", HideDelimiters($local_value)));
								}
								if ($typ[0] == "ref")
									$GLOBALS["local_types"][$par][$order] = $typ[2];
								else
									$GLOBALS["local_types"][$par][$order] = $local_type;
							} elseif ($typ[0] == "ref") {
								trace(" Define ref req $order " . $typ[1] . " as $req. Ref ID is " . $typ[2]);
								$reqID = $typ[1];
								$refID = $typ[2];
								$obj   = explode(":", $GLOBALS["imported"][$typ[2]][0]);
								trace("  ref Obj is " . $obj[1] . " substituted by " . CheckSubst($obj[1]));
								$obj = CheckSubst($obj[1]);
								if (IsOccupied($refID)) {
									$row = mysqli_fetch_array(Exec_sql("SELECT id FROM $z WHERE up=0 AND t=$obj AND val=''", "Seek Ref"));
									if ($row["id"]) {
										$refID = $row["id"];
										trace("  the ref $refID to $obj exists");
									} else
										$refID = $GLOBALS["local_struct"]["subst"][$refID] = Insert(0, 0, $obj, "", "Import Ref without ID");
								} else
									exec_sql("INSERT INTO $z (id, up, ord, t, val) VALUES ($refID, 0, 0, $obj, '')", "Import Ref with ID");
								$GLOBALS["refs"][$refID] = "";
								if (IsOccupied($reqID)) {
									$row = mysqli_fetch_array(Exec_sql("SELECT id FROM $z WHERE t=$refID AND up=$parent", "Seek ref Req"));
									if ($row["id"]) {
										$reqID = $row["id"];
										trace("  the ref req $reqID to $obj exists");
									} else
										$reqID = $GLOBALS["local_struct"]["subst"][$reqID] = Insert($parent, $order, $refID, isset($typ[3]) ? UnMaskDelimiters($typ[3]) : "", "Import Ref without ID");
								} else
									exec_sql("INSERT INTO $z (id, up, ord, t, val) VALUES ($reqID, $parent, $order, $refID, '')", "Import ref Req with ID");
								$GLOBALS["refs"][$reqID]              = $refID;
								$GLOBALS["local_types"][$par][$order] = CheckSubst($reqID);
							} elseif ($typ[0] == "arr") {
								$i = CheckSubst($typ[1]);
								trace("   Define array req " . $typ[1] . " $reqs[$order] as " . $GLOBALS["imported"][$typ[1]][0]);
								$reqID                                    = Insert($parent, $order, $i, isset($typ[2]) ? UnMaskDelimiters($typ[2]) : "", "Import arr Req");
								$GLOBALS["local_struct"][$parent][$reqID] = $reqs[$order];
								$GLOBALS["local_types"][$par][$order]     = $reqID;
								$GLOBALS["parents"][$i]                   = $parent;
							} else {
								trace("   $req is a plain req - find an analogue or register the new one");
								$data_set = Exec_sql("SELECT id FROM $z WHERE val='" . addslashes($typ[0]) . "' AND up=0 AND t=" . $GLOBALS["BT"][$typ[1]], "Seek Req Typ");
								if ($row = mysqli_fetch_array($data_set))
									$i = $row["id"];
								else
									$i = Insert(0, 0, $GLOBALS["BT"][$typ[1]], $typ[0], "Import new Type for Req");
								$i                                    = Insert($parent, Get_Ord($parent), $i, isset($typ[2]) ? UnMaskDelimiters($typ[2]) : "", "Import new Req");
								$GLOBALS["local_struct"][$parent][$i] = $req;
								$GLOBALS["local_types"][$par][$order] = $i;
							}
						}
					}
				}
				trace("Data");
				$GLOBALS["cur_parent"][0] = $up;
				if ($plain_data) {
					if (isset($GLOBALS["cur_parent"][$GLOBALS["parents"][$id]])) {
						$parent = $GLOBALS["cur_parent"][$GLOBALS["parents"][$id]];
						if ($parent != 1)
							$cur_order = Get_ord($parent, $id);
					} else
						$parent = 1;
					$typesCount = isset($GLOBALS["local_types"][$id]) ? count($GLOBALS["local_types"][$id]) : count($GLOBALS["local_struct"][$id]);
					while (!feof($handle)) {
						$buffer = fgets($handle);
						if (strlen($buffer) == 0)
							continue;
						$object = UnHideDelimiters(explode(";", HideDelimiters($buffer)));
						if ($object[0] == "")
							my_die(t9n("[RU]Пустой объект типа $id (строка $count)[EN]Empty object of type $id (string $count)"));
						while (count($object) <= $typesCount) {
							if (feof($handle))
								my_die(t9n("[RU]Неожиданный конец файла [EN]Unexpected end of file"));
							$buffer .= fgets($handle);
							$object = UnHideDelimiters(explode(";", UnHideDelimiters($buffer)));
							$count++;
						}
						end($object);
						$object[key($object)] = rtrim(current($object), "\t\n\r\0\x0B");
						trace("(" . $count++ . ") Buffer: $buffer");
						$object[0] = Format_Val($GLOBALS["base"][$id], UnMaskDelimiters($object[0]));
						$new_id    = Insert($parent, (isset($cur_order) ? $cur_order++ : 1), $id, $object[0], "Plain import");
						$order     = 0;
						foreach ($GLOBALS["local_struct"][$id] as $key => $value) {
							if ($key == 0)
								continue;
							$order++;
							trace(" Parse $key " . $object[$order] . " of $value");
							if (strlen($object[$order]))
								if (!isset($GLOBALS["refs"][$key]))
									Insert_batch($new_id, 1, $key, Format_Val($GLOBALS["base"][$key], UnMaskDelimiters($object[$order])), "Import plain req");
								else {
									$refType = $GLOBALS["refs"][$key];
									if (isset($GLOBALS["refs"][$refType]))
										if (isset($GLOBALS["refs"][$refType][$object[$order]])) {
											Insert_batch($new_id, 1, $GLOBALS["refs"][$refType][$object[$order]], $key, "Import cached plain ref");
											continue;
										}
									if ($row = mysqli_fetch_array(Exec_sql("SELECT id FROM $z WHERE t=$refType AND val='" . str_replace("'", "\'", $object[$order]) . "'", "Check plain ref Obj Value")))
										$refObjID = $row["id"];
									else
										$refObjID = Insert(1, 1, $refType, $object[$order], "Import plain ref Object");
									Insert_batch($new_id, 1, $refObjID, $key, "Import plain ref");
									$GLOBALS["refs"][$refType][$object[$order]] = $refObjID;
								}
						}
					}
				} else
					while (!feof($handle)) {
						$buffer = fgets($handle);
						if (strlen($buffer) == 0)
							continue;
						$object = UnHideDelimiters(explode(";", HideDelimiters($buffer)));
						trace("");
						$typ = UnHideDelimiters(explode(":", HideDelimiters($object[0])));
						trace("Object: " . $object[0] . ", typ: " . $typ[0]);
						if (count($typ) == 4) {
							trace("Reference attribute " . $object[0]);
							$isref = CheckSubst((int) array_shift($typ));
						}
						$orig = (int) $typ[0];
						while (count($object) <= count($GLOBALS["imported"][$orig])) {
							if (feof($handle))
								my_die(t9n("[RU]Неожиданный конец файла[EN]Unexpected end of file"));
							$buffer .= fgets($handle);
							$object = UnHideDelimiters(explode(";", UnHideDelimiters($buffer)));
							$count++;
						}
						$t = CheckSubst($orig);
						if (!isset($GLOBALS["local_struct"][$t])) {
							print_r($GLOBALS);
							my_die(t9n("[RU]Недопустимый тип $t, остутствующий в мета-данных[EN]Invalid type $t that is not present in the metadata"));
						}
						trace("(" . $count++ . ") Buffer: $buffer");
						array_pop($object);
						$new_id = $order = 0;
						foreach ($object as $value) {
							trace(" Parse  $value, t:$t, orig:$orig");
							if ($new_id) {
								$order++;
								$key = $GLOBALS["local_types"][$orig][$order];
								trace(" order:$order: key:$key of t:$orig ($t)");
								if ($key == "")
									my_die(t9n("[RU]Тип $orig ($t) не имеет реквизита №$order [EN]The type $orig ($t) does not have the $order attribute"));
								if (strlen($value))
									if (!isset($GLOBALS["refs"][$key]))
										Insert_batch($new_id, 1, $key, UnMaskDelimiters($value), "Import req");
									elseif ((strpos($value, ":") !== FALSE) || ((int) $value == 0)) {
										if ((strpos($value, ":") === FALSE)) {
											$refObjID  = 0;
											$refObjVal = $value;
										} else {
											$tmp       = UnHideDelimiters(explode(":", HideDelimiters($value)));
											$refObjID  = (int) $tmp[0];
											$refObjVal = $tmp[1];
										}
										if (!isset($GLOBALS["local_types"][$key])) {
											$tmp                          = explode(":", $GLOBALS["local_struct"][$key][0]);
											$GLOBALS["local_types"][$key] = $tmp[1];
										}
										$refType = $GLOBALS["local_types"][$key];
										trace("   ref type: $refType");
										$refObjVal = addslashes(UnMaskDelimiters($refObjVal));
										if ($refObjID > 0) {
											if ($row = mysqli_fetch_array(Exec_sql("SELECT t, val FROM $z WHERE id=$refObjID", "Check ref Obj ID"))) {
												trace("The object exists t=" . $row["t"] . " value=" . $row["val"]);
												if ($row["t"] != $refType) {
													trace(" the type is wrong " . $row["t"] . " != $refType");
													$refObjID = $GLOBALS["obj_subst"][$refObjID] = Insert(1, 1, $refType, $refObjVal, "Import new ID");
												}
											} elseif (strlen($refObjVal))
												exec_sql("INSERT INTO $z (id, up, ord, t, val) VALUES ($refObjID, 1, 1, $refType, '$refObjVal')", "Import ref Obj with ID");
										} elseif (strlen($refObjVal)) {
											if ($row = mysqli_fetch_array(Exec_sql("SELECT id FROM $z WHERE t=$refType AND val='$refObjVal'", "Check ref Obj Value")))
												$refObjID = $row["id"];
											else
												$refObjID = Insert(1, 1, $refType, $refObjVal, "Import direct ref Object");
										}
										Insert_batch($new_id, 1, $refObjID, $key, "Import direct ref");
									} elseif ((int) $value != 0)
										Insert_batch($new_id, 1, CheckObjSubst((int) $value), $key, "Import ref");
							} else {
								if ($typ[2] == "")
									my_die(t9n("[RU]Пустой объект типа $t (строка $count)[EN]Empty object of type $t (string $count)"));
								if (isset($GLOBALS["cur_parent"][$GLOBALS["parents"][$t]])) {
									$parent = $GLOBALS["cur_parent"][$GLOBALS["parents"][$t]];
									if (isset($GLOBALS["cur_order"][$parent]))
										$ord = ++$GLOBALS["cur_order"][$parent];
									else
										$ord = $GLOBALS["cur_order"][$parent] = Get_ord($parent, $t);
								} else
									$parent = $ord = 1;
								$typ[2] = UnMaskDelimiters($typ[2]);
								if ($typ[1] == "")
									$new_id = Insert($parent, $ord, $t, $typ[2], "Import no ID");
								else {
									$new_id = $typ[1];
									if ($row = mysqli_fetch_array(Exec_sql("SELECT t, val FROM $z WHERE id=$new_id", "Check ID presence"))) {
										if (($row["t"] == $t) && ($row["val"] == $typ[2]))
											break;
										$new_id = $GLOBALS["obj_subst"][$new_id] = Insert($parent, $ord, $t, $typ[2], "Import new ID");
									} elseif ($isref)
										exec_sql("INSERT INTO $z (id, up, ord, t, val) VALUES ($new_id, 1, 1, $t, '" . addslashes($typ[2]) . "')", "Import with ID");
									else
										exec_sql("INSERT INTO $z (id, up, ord, t, val) VALUES ($new_id, $parent, $ord, $t, '" . addslashes(substr($typ[2], 0, VAL_LIM)) . "')", "Import with ID");
								}
								$GLOBALS["cur_parent"][$t] = $new_id;
							}
						}
					}
				Insert_batch("", "", "", "", "Import");
				fclose($handle);
			}
			$GLOBALS["BT"]["REFERENCE"] = 0;
			$GLOBALS["REV_BT"][0]       = "REFERENCE";
			$GLOBALS["DESC"]            = isset($_REQUEST["desc"]) ? "DESC" : "";
			$GLOBALS["PG"]              = isset($_REQUEST["pg"]) ? max($_REQUEST["pg"], 1) : 1;
			$sql                        = "SELECT CASE WHEN arrs.id IS NULL THEN a.id ELSE typs.id END t, CASE WHEN refs.id IS NULL THEN typs.t ELSE refs.t END base_typ
, CASE WHEN refs.id IS NULL THEN typs.val ELSE refs.val END val, refs.id ref_id, arrs.id arr_id, a.val attrs, a.id
FROM $z a, $z typs LEFT JOIN $z refs ON refs.id=typs.t AND refs.t!=refs.id
LEFT JOIN $z arrs ON refs.id IS NULL AND arrs.up=typs.id AND arrs.ord=1
WHERE a.up=$id AND typs.id=a.t ORDER BY a.ord";
			$data_set                   = Exec_sql($sql, "Get all Names of Reqs of the Typ");
			$GLOBALS["no_reqs"]         = mysqli_num_rows($data_set) == 0;
			$ord                        = 0;
			while ($row = mysqli_fetch_array($data_set)) {
				if (isset($GLOBALS["GRANTS"][$row["id"]]))
					if ($GLOBALS["GRANTS"][$row["id"]] == "BARRED")
						continue;
				$val                          = isset($row["ref_id"]) ? FetchAlias($row["attrs"], $row["val"]) : $row["val"];
				$blocks[$block]["val"][]      = $val;
				$blocks[$block]["typ"][]      = $row["t"];
				$blocks[$block]["base_typ"][] = $row["base_typ"];
				$blocks[$block]["id"][]       = $id;
				$GLOBALS["attrs"][$row["t"]]  = $row["attrs"];
				$GLOBALS["REV_BT"][$row["t"]] = $GLOBALS["REV_BT"][$row["base_typ"]];
				if (isApi()) {
					$GLOBALS["GLOBAL_VARS"]["api"]["req_base"][$row["t"]]    = $GLOBALS["REV_BT"][$row["base_typ"]];
					$GLOBALS["GLOBAL_VARS"]["api"]["req_base_id"][$row["t"]] = $row["base_typ"];
					$GLOBALS["GLOBAL_VARS"]["api"]["req_type"][$row["t"]]    = $val;
					$GLOBALS["GLOBAL_VARS"]["api"]["req_order"][$ord++]      = $row["t"];
				}
				if ($row["arr_id"] != 0) {
					$GLOBALS["REV_BT"][$row["t"]]   = "ARRAY";
					$GLOBALS["HAVE_ARR"]            = "";
					$GLOBALS["ARR_typs"][$row["t"]] = $GLOBALS["REV_BT"][$row["base_typ"]];
				}
				if ($row["ref_id"] != 0) {
					$GLOBALS["HAVE_REF"]            = "";
					$GLOBALS["REF_typs"][$row["t"]] = $row["ref_id"];
				} else
					$GLOBALS["NonREF_typs"][$row["t"]] = "";
				$GLOBALS["REQS"][$row["t"]]     = $row["base_typ"];
				$GLOBALS["REQNAMES"][$row["t"]] = $row["val"];
				$f                              = $GLOBALS["FILTER"];
				if (!isset($_REQUEST["desc"]) && ($GLOBALS["ORDER_VAL"] == $row["t"]))
					$blocks[$block]["filter"][] = "$f&desc=0";
				else
					$blocks[$block]["filter"][] = $f;
			}
			if (isset($_REQUEST["csv"])) {
				if (!isset($GLOBALS["GRANTS"]["EXPORT"][$id]) && ($GLOBALS["GLOBAL_VARS"]["user"] != "admin") && ($GLOBALS["GLOBAL_VARS"]["user"] != $z))
					die(t9n("[RU]У вас нет прав на выгрузку объектов этого типа[EN]You do not have access to upload this type of object"));
				if (is_array($blocks[$block]["val"]))
					array_unshift($blocks[$block]["val"], $blocks[$blocks[$block]["PARENT"]]["CUR_VARS"]["val"]);
				else
					$blocks[$block]["val"][] = $blocks[$blocks[$block]["PARENT"]]["CUR_VARS"]["val"];
				foreach ($blocks[$block]["val"] as $key => $value)
					$blocks[$block]["val"][$key] = iconv("utf-8", "windows-1251", $value);
				download_send_headers("data_export.csv");
				ob_start();
				$GLOBALS["CSV_handler"] = fopen("php://output", 'w');
				fputcsv($GLOBALS["CSV_handler"], $blocks[$block]["val"], ';');
			}
			if (isset($_REQUEST["bki"])) {
				if (!isset($GLOBALS["GRANTS"]["EXPORT"][$id]) && ($GLOBALS["GLOBAL_VARS"]["user"] != "admin") && ($GLOBALS["GLOBAL_VARS"]["user"] != $z))
					die(t9n("[RU]У вас нет прав на выгрузку объектов этого типа[EN]You do not have access to upload this type of object"));
				$header = Export_header($id);
				download_send_headers("data_export.bki");
				ob_start();
				$GLOBALS["CSV_handler"] = fopen("php://output", 'w');
				fwrite($GLOBALS["CSV_handler"], $header . "DATA\r\n");
			}
			break;
		case "&delete":
		case "&export":
			if (isset($GLOBALS["GRANTS"][strtoupper(substr($block_name, 1))][$id]) || ($GLOBALS["GLOBAL_VARS"]["user"] == "admin") || ($GLOBALS["GLOBAL_VARS"]["user"] == $z))
				$blocks[$block]["ok"][] = "";
			break;
		case "&uni_obj_head_links":
		case "&uni_obj_head_filter_links":
		case "&uni_object_view_reqs_links":
			if ($GLOBALS["lnx"] == 1)
				$blocks[$block]["val"][] = "";
			break;
		case "&uni_obj_head_filter":
			if (isset($GLOBALS["REQS"]))
				foreach ($GLOBALS["REQS"] as $key => $value) {
					$blocks[$block]["typ"][]      = $key;
					$blocks[$block]["base_typ"][] = $value;
					$blocks[$block]["dd"][]       = isset($GLOBALS["REF_typs"]) ? (isset($GLOBALS["REF_typs"][$key]) ? "dropdown-toggle" : "") : "";
					$blocks[$block]["ref"][]      = isset($GLOBALS["REF_typs"]) ? (isset($GLOBALS["REF_typs"][$key]) ? $GLOBALS["REF_typs"][$key] : $key) : $key;
				}
			break;
		case "&filter_val_rcm":
		case "&filter_val_dns":
		case "&filter_req_rcm":
		case "&filter_req_dns":
			$cur_typ = $blocks[$blocks[$block]["PARENT"]]["CUR_VARS"]["typ"];
			if (in_array($GLOBALS["REV_BT"][$blocks[$blocks[$block]["PARENT"]]["CUR_VARS"]["base_typ"]], array(
				"DATE",
				"NUMBER",
				"SIGNED",
				"DATETIME"
			))) {
				$blocks[$block]["f_typ_fr"][]  = "FR_" . $cur_typ;
				$blocks[$block]["filter_fr"][] = isset($_REQUEST["FR_" . $cur_typ]) ? str_replace(" ", "", $_REQUEST["FR_" . $cur_typ]) : "";
				$blocks[$block]["f_typ_to"][]  = "TO_" . $cur_typ;
				$blocks[$block]["filter_to"][] = isset($_REQUEST["TO_" . $cur_typ]) ? str_replace(" ", "", $_REQUEST["TO_" . $cur_typ]) : "";
			} else {
				$blocks[$block]["f_typ"][]  = "F_$cur_typ";
				$blocks[$block]["filter"][] = isset($_REQUEST["F_$cur_typ"]) ? str_replace("\"", "&#34;", $_REQUEST["F_$cur_typ"]) : "";
			}
			break;
		case "&uni_obj_all":
			if (isset($GLOBALS["HAVE_ARR"])) {
				$data_set = Exec_sql("SELECT arr_reqs.t req_typ, base_typs.t base_typ
  FROM $z reqs, $z arr_reqs, $z base_typs
  WHERE reqs.up=$id AND arr_reqs.up=reqs.t AND base_typs.id=arr_reqs.t", "Get base types for array Reqs");
				$ref_list = "";
				while ($row = mysqli_fetch_array($data_set)) {
					if ($row["req_typ"] == "") {
						if ($ref_list == "")
							$ref_list = $row["req_typ"];
						else
							$ref_list .= "," . $row["req_typ"];
					}
					$GLOBALS["REV_BT"][$row["req_typ"]] = isset($GLOBALS["REV_BT"][$row["base_typ"]]) ? $GLOBALS["REV_BT"][$row["base_typ"]] : "SHORT";
				}
			}
			$joins            = $filter_tables = $filter_cond = $GLOBALS["distinct"] = $filter_by_id = $parent_cond = "";
			$cur_typ          = $id;
			$cur_base_typ     = $blocks["&main"]["CUR_VARS"]["typ"];
			$filter_by_id_off = FALSE;
			$GLOBALS["where"] = $GLOBALS["join"] = $GLOBALS["join_cond"] = "";
			if (isset($_REQUEST["F_U"]) && $blocks["&main"]["CUR_VARS"]["parent_obj"])
				$filter_cond .= " AND vals.up=" . (int) $_REQUEST["F_U"] . " ";
			else
				$parent_cond = " AND vals.up!=0 ";
			foreach ($_REQUEST as $key => $value)
				if (($value != "") && (preg_match("/(F\_|FR\_|TO\_)/", $key)))
					$GLOBALS["CONDS"][substr($key, strpos($key, "_") + 1)][substr($key, 0, strpos($key, "_"))] = $value;
			if (isset($GLOBALS["CONDS"]))
				foreach ($GLOBALS["CONDS"] as $key => $value) {
					if ($key == "U")
						continue;
					elseif (($key == "I") && ($value["F"] != 0))
						$filter_by_id = " AND vals.id=" . (int) $value["F"] . " ";
					elseif ($key != 0) {
						$filter_by_id_off = TRUE;
						Construct_WHERE($key, $value, $cur_typ, $key, FALSE);
					}
				}
			$filter_tables = $GLOBALS["join"];
			$filter_cond .= $GLOBALS["where"];
			if ($blocks["&main"]["CUR_VARS"]["parent_obj"] && !$f_u)
				$filter_tables = "JOIN $z par ON par.id=vals.up AND par.up!=0 $filter_tables";
			$GLOBALS["REQS"][$cur_typ] = $cur_base_typ;
			foreach ($GLOBALS["REQS"] as $req => $base)
				if (isset($GLOBALS["GRANTS"]["mask"][$req])) {
					foreach ($GLOBALS["GRANTS"]["mask"][$req] as $mask) {
						$GLOBALS["where"] = $GLOBALS["join"] = $GLOBALS["CONDS"] = "";
						Construct_WHERE($req, array(
							"F" => $mask
						), $cur_typ, $req, FALSE);
						if (isset($reqs_granted))
							$reqs_granted .= " OR " . substr($GLOBALS["where"], 4);
						else
							$reqs_granted = substr($GLOBALS["where"], 4);
						if (strpos($filter_tables, "$z a$req") === FALSE)
							$filter_tables .= $GLOBALS["join"];
					}
					$filter_cond .= " AND ($reqs_granted) ";
					unset($reqs_granted);
				}
			if (!strlen($filter_cond) && isset($_REQUEST["f_show_all"]))
				$filter_cond = " ";
			$tmp_base_typ = $cur_base_typ;
			if ($GLOBALS["ORDER_VAL"] === "val")
				$order = "vals.val";
			elseif (($GLOBALS["ORDER_VAL"] != 0) && ($GLOBALS["REV_BT"][$GLOBALS["ORDER_VAL"]] != "ARRAY")) {
				$tmp_base_typ = $GLOBALS["ORDER_VAL"];
				$order        = "a$tmp_base_typ.val";
				if (strpos($filter_tables, "a$tmp_base_typ") === FALSE) {
					if (isset($GLOBALS["REF_typs"][$tmp_base_typ]))
						$filter_tables = " LEFT JOIN ($z r$tmp_base_typ JOIN $z a$tmp_base_typ) " . "ON r$tmp_base_typ.up=vals.id AND r$tmp_base_typ.t=a$tmp_base_typ.id AND r$tmp_base_typ.val='$tmp_base_typ' " . "AND a$tmp_base_typ.t=" . $GLOBALS["REF_typs"][$tmp_base_typ] . $filter_tables;
					else
						$filter_tables = " LEFT JOIN $z a$tmp_base_typ ON a$tmp_base_typ.up=vals.id
		 AND a$tmp_base_typ.t=$tmp_base_typ" . $filter_tables;
				}
			} else
				$order = "";
			if (!$filter_by_id_off)
				$filter_cond .= $filter_by_id;
			if (strlen($order))
				if (($GLOBALS["REV_BT"][$tmp_base_typ] == "NUMBER") || ($GLOBALS["REV_BT"][$tmp_base_typ] == "SIGNED"))
					$order = "$order + 0.0";
			$desc     = $GLOBALS["DESC"];
			$pg       = (DEFAULT_LIMIT * ($GLOBALS["PG"] - 1)) . ",";
			$vals_ord = "";
			if ($GLOBALS["parent_id"] > 1)
				$vals_ord = ", vals.ord val_ord";
			if (($GLOBALS["parent_id"] > 1) && ($GLOBALS["ORDER_VAL"] === 0))
				$order = " ORDER BY vals.ord";
			elseif (strlen($order))
				$order = " ORDER BY $order $desc";
			if (!isset($_REQUEST["csv"]) && !isset($_REQUEST["bki"]))
				$order .= " LIMIT $pg " . DEFAULT_LIMIT;
			if (in_array($GLOBALS["REV_BT"][$cur_base_typ], array(
				"CHARS",
				"MEMO",
				"FILE",
				"HTML"
			)))
				$tails = ", (SELECT COUNT(*) FROM $z tails WHERE tails.up=vals.id AND tails.t=0) tails";
			$distinct = $GLOBALS["distinct"];
			if (isset($_REQUEST["_m_del_select"])) {
				if (!isset($GLOBALS["GRANTS"]["DELETE"][$cur_typ]) && ($GLOBALS["GLOBAL_VARS"]["user"] != "admin") && ($GLOBALS["GLOBAL_VARS"]["user"] != $z))
					die(t9n("[RU]У вас нет прав на массовое удаление объектов этого типа[EN]You do not have access to delete this type of object in bulk"));
				$data_set = Exec_sql("SELECT $distinct vals.id FROM $z vals LEFT JOIN $z refr ON refr.t=vals.id /*AND !length(refr.val)*/ $filter_tables
  WHERE vals.t=$cur_typ $parent_cond $filter_cond AND refr.id IS NULL" . ($cur_typ == 18 ? " AND vals.id!=" . $GLOBALS["GLOBAL_VARS"]["user_id"] : ""), "Get filtered Objs set to delete");
				while ($row = mysqli_fetch_array($data_set))
					BatchDelete($row["id"]);
				BatchDelete("");
				header("Location: /$z/object/$id/?" . $GLOBALS["FILTER"]);
				myexit();
			}
			$data_set               = Exec_sql("SELECT $distinct vals.id, vals.t, vals.val $vals_ord " . (isset($tails) ? $tails : "") . " FROM $z vals $filter_tables WHERE vals.t=$cur_typ $parent_cond $filter_cond $order", "Get filtered Objs set");
			$blocks["object_count"] = mysqli_num_rows($data_set);
			$i                      = 0;
			while ($row = mysqli_fetch_array($data_set)) {
				if (isset($_REQUEST["bki"])) {
					$str = Export_reqs($id, $row["id"], $row["val"]);
					fwrite($GLOBALS["CSV_handler"], $str);
					continue;
				}
				if ((isset($row["tails"]) ? $row["tails"] : 0) > 0) {
					if (isset($_REQUEST["full"]))
						$v = htmlspecialchars(Get_tail($row["id"], $row["val"]));
					else
						$v = htmlspecialchars($row["val"] . "...");
				} else
					$v = htmlspecialchars($row["val"]);
				if (isApi()) {
					if ($f_u > 1)
						$GLOBALS["GLOBAL_VARS"]["api"]["object"][$i]["ord"] = $row["val_ord"];
					$GLOBALS["GLOBAL_VARS"]["api"]["object"][$i]["id"]  = $row["id"];
					$GLOBALS["GLOBAL_VARS"]["api"]["object"][$i]["val"] = Format_Val_View($cur_base_typ, $v, $row["id"]);
					if (in_array($GLOBALS["REV_BT"][$cur_base_typ], array(
						"REPORT_COLUMN",
						"GRANT"
					)))
						$GLOBALS["GLOBAL_VARS"]["api"]["object"][$i]["ref"] = $v;
					$GLOBALS["GLOBAL_VARS"]["api"]["object"][$i]["base"] = $row["t"];
					$i++;
				}
				$blocks[$block]["id"][]    = $row["id"];
				$blocks[$block]["ord"][]   = $i;
				$blocks[$block]["align"][] = Get_Align($cur_base_typ);
				if (trim($v) == "")
					$v = "&nbsp;";
				if (isset($_REQUEST["bki"]))
					$blocks[$block]["val"][] = $row["val"];
				else
					$blocks[$block]["val"][] = Format_Val_View($cur_base_typ, $v, $row["id"]);
				if ($f_u > 1)
					$blocks[$block]["val_ord"][] = $row["val_ord"];
			}
			if (isset($_REQUEST["bki"]))
				break;
			if ((($blocks["object_count"] == DEFAULT_LIMIT) || ($GLOBALS["PG"] > 1)) && strlen($filter_cond)) {
				if (strlen($filter_tables)) {
					if ($row = mysqli_fetch_array(Exec_sql("SELECT COUNT($distinct vals.id) cnt FROM $z vals $filter_tables
 WHERE vals.t=$cur_typ $parent_cond $filter_cond", "Get number of filtered Objs")))
						$blocks["object_count_total"] = $row["cnt"];
				} else {
					$row                          = mysqli_fetch_array(Exec_sql("SELECT COUNT(1) cnt FROM $z vals WHERE t=$cur_typ $filter_cond AND up!=0", "Get number of Objs"));
					$blocks["object_count_total"] = $row["cnt"];
				}
			} elseif (strlen($filter_cond))
				$blocks["object_count_total"] = $blocks["object_count"];
			break;
		case "&head_ord":
		case "&head_ord_n":
		case "&head_move_n_delete":
			if ($f_u > 1)
				$blocks[$block]["filler"][] = "";
			break;
		case "&move_n_delete":
			if ($f_u > 1)
				$blocks[$block]["id"][] = $blocks[$blocks[$block]["PARENT"]]["CUR_VARS"]["id"];
			break;
		case "&ord":
			if ($f_u > 1)
				$blocks[$block]["ord"][] = $blocks[$blocks[$block]["PARENT"]]["CUR_VARS"]["val_ord"];
			break;
		case "&move":
			if (($f_u > 1) && ($GLOBALS["ORDER_VAL"] == 0))
				$blocks[$block]["id"][] = $blocks[$blocks[$block]["PARENT"]]["CUR_VARS"]["id"];
			break;
		case "&no_page":
			if (!isset($blocks["object_count_total"]) && ($blocks["object_count"] == DEFAULT_LIMIT)) {
				$blocks[$block]["limit"][] = DEFAULT_LIMIT;
				$blocks[$block]["id"][]    = $id;
				$blocks[$block]["f_u"][]   = $f_u;
				$blocks[$block]["lnx"][]   = (isset($_REQUEST["lnx"]) ? "&lnx=" . (int) $_REQUEST["lnx"] : "") . ($GLOBALS["ORDER_VAL"] === 0 ? "" : "&order_val=" . $GLOBALS["ORDER_VAL"]);
			}
			break;
		case "&uni_obj_pages":
			if (isset($_REQUEST["csv"]) || isset($_REQUEST["bki"])) {
				fclose($GLOBALS["CSV_handler"]);
				echo ob_get_clean();
				die();
			}
			if (isset($_GET["saved1"]))
				$blocks[$block]["ending"][] = t9n("[RU]Запись сохранена[EN]Record saved");
			elseif (isset($_GET["copied1"]))
				$blocks[$block]["ending"][] = t9n("[RU]Запись скопирована[EN]Record copied");
			elseif (isset($_GET["canc1"]))
				$blocks[$block]["ending"][] = t9n("[RU]Запись не изменена[EN]Record not changed");
			elseif (isset($blocks["object_count_total"])) {
				$pages                     = ceil($blocks["object_count_total"] / DEFAULT_LIMIT);
				$blocks[$block]["val"][]   = $blocks["object_count_total"];
				$blocks[$block]["pages"][] = $pages;
				$last_dig                  = $blocks["object_count_total"] % 10;
				if (($last_dig >= 5) || ($last_dig == 0))
					$records = t9n("[RU]записей[EN]records");
				elseif ($blocks["object_count_total"] == 1)
					$records = t9n("[RU]запись[EN]record");
				elseif (($blocks["object_count_total"] % 100 > 14) || ($blocks["object_count_total"] % 100 < 5)) {
					if ($last_dig == 1)
						$records = t9n("[RU]запись[EN]records");
					else
						$records = t9n("[RU]записи[EN]records");
				} else
					$records = t9n("[RU]записей[EN]records");
				$blocks[$block]["ending"][] = t9n("[RU]Всего [EN]Total ") . $blocks["object_count_total"] . " $records";
			}
			break;
		case "&page":
			if ($blocks[$blocks[$block]["PARENT"]]["CUR_VARS"]["pages"] > 1) {
				if ($GLOBALS["PG"] != 1) {
					$blocks[$block]["page"][]  = $GLOBALS["PG"] - 1;
					$blocks[$block]["val"][]   = " < ";
					$blocks[$block]["class"][] = "";
				}
				$pages = $blocks[$blocks[$block]["PARENT"]]["CUR_VARS"]["pages"];
				$i     = 1;
				while ($i <= $pages) {
					if ($i == $GLOBALS["PG"])
						$blocks[$block]["class"][] = "active";
					else
						$blocks[$block]["class"][] = "";
					$blocks[$block]["page"][] = $i;
					$blocks[$block]["val"][]  = $i++;
					if (($i > 3) && ($i < $pages - 2))
						if (abs($i - $GLOBALS["PG"]) > 1) {
							$blocks[$block]["val"][]   = ".&nbsp;.&nbsp;.";
							$blocks[$block]["class"][] = "";
							if ($i < $GLOBALS["PG"]) {
								$i                        = $GLOBALS["PG"] - 1;
								$blocks[$block]["page"][] = round((3 + $i) / 2);
							} else {
								$blocks[$block]["page"][] = round(($pages - 2 + $i) / 2);
								$i                        = $pages - 2;
							}
						}
				}
				if ($GLOBALS["PG"] != $pages) {
					$blocks[$block]["page"][]  = $GLOBALS["PG"] + 1;
					$blocks[$block]["val"][]   = " > ";
					$blocks[$block]["class"][] = "";
				}
				if (isApi())
					$GLOBALS["GLOBAL_VARS"]["api"]["pages"] = $blocks[$block]["val"];
			}
			break;
		case "&page_href":
			if ($blocks[$blocks[$block]["PARENT"]]["CUR_VARS"]["val"] != $GLOBALS["PG"]) {
				$blocks[$block]["filter"][] = $GLOBALS["FILTER"] . (isset($_REQUEST["lnx"]) ? "&lnx=" . (int) $_REQUEST["lnx"] : "") . ($GLOBALS["ORDER_VAL"] === 0 ? "" : "&order_val=" . $GLOBALS["ORDER_VAL"] . (isset($_REQUEST["desc"]) ? "&desc=1" : ""));
				$blocks[$block]["id"][]     = $id;
			}
			break;
		case "&uni_object_view_reqs":
			$parent_id = $blocks[$blocks[$block]["PARENT"]]["CUR_VARS"]["id"];
			if ($GLOBALS["no_reqs"]) {
				if (isset($_REQUEST["csv"]))
					fputcsv($GLOBALS["CSV_handler"], array(
						iconv("utf-8", "windows-1251", $blocks[$blocks[$block]["PARENT"]]["CUR_VARS"]["val"])
					), ';');
				break;
			}
			if ($GLOBALS["lnx"] == 1)
				$flags = "&lnx=1";
			else
				$flags = "";
			if (isset($GLOBALS["HAVE_ARR"]))
				$sql = "SELECT CASE WHEN typs.up=0 THEN 0 ELSE reqs.id END id, CASE WHEN typs.up=0 THEN 0 ELSE reqs.val END val
, typs.id t, typs.up, typs.val refr, count(1) arr_num";
			else
				$sql = "SELECT reqs.id, reqs.val, typs.id t, typs.up, typs.val refr";
			$sql .= " FROM $z reqs JOIN $z typs ON typs.id=reqs.t WHERE reqs.up=$parent_id";
			if (isset($GLOBALS["HAVE_ARR"]))
				$sql .= " GROUP BY val, id, t, refr";
			$data_set = Exec_sql($sql, "Get all Object reqs");
			while ($row = mysqli_fetch_array($data_set))
				if (isset($GLOBALS["NonREF_typs"][$row["t"]])) {
					$rows[$row["t"]]["id"]      = $row["id"];
					$rows[$row["t"]]["val"]     = $row["val"];
					$rows[$row["t"]]["arr_num"] = isset($row["arr_num"]) ? $row["arr_num"] : "";
				} else {
					$rows[$row["val"]]["id"]     = $row["id"];
					$rows[$row["val"]]["val"]    = $row["refr"];
					$rows[$row["val"]]["ref_id"] = $row["t"];
				}
			foreach ($GLOBALS["REQS"] as $key => $value) {
				if ($key == $id)
					break;
				elseif (isset($rows[$key]))
					$row = $rows[$key];
				else
					$row = array(
						"t" => $key
					);
				if (isset($GLOBALS["GRANTS"][$key]))
					if ($GLOBALS["GRANTS"][$key] == "BARRED")
						continue;
				$val         = isset($row["val"]) ? $row["val"] : "";
				$literal_typ = $GLOBALS["REV_BT"][$key];
				$base_typ    = isset($GLOBALS["BT"][$literal_typ]) ? $GLOBALS["BT"][$literal_typ] : $GLOBALS["BT"]["SHORT"];
				$req_id      = isset($row["id"]) ? $row["id"] : 0;
				if (isApi()) {
					if (isset($GLOBALS["ARR_typs"][$key]))
						$v = isset($row["arr_num"]) ? (int) $row["arr_num"] : "";
					elseif (isset($row["ref_id"])) {
						$v                                                             = $val;
						$GLOBALS["GLOBAL_VARS"]["api"]["reqs"][$parent_id]["ref_$key"] = $GLOBALS["REF_typs"][$key] . ":" . $row["ref_id"];
					} elseif ($req_id > 0) {
						if (in_array($literal_typ, array(
							"CHARS",
							"MEMO",
							"HTML"
						)))
							$v = Get_tail($req_id, $val);
						elseif ($GLOBALS["REV_BT"][$base_typ] == "FILE")
							$v = Format_Val_View($base_typ, $req_id . ":" . Get_tail($req_id, $val), $req_id);
						else
							$v = Format_Val_View($base_typ, $val);
					} elseif ($literal_typ == "BUTTON")
						$v = "***";
					else
						$v = $val;
					if ($v != "")
						$GLOBALS["GLOBAL_VARS"]["api"]["reqs"][$parent_id][$key] = $v;
				}
				if (isset($_REQUEST["csv"])) {
					if (isset($GLOBALS["ARR_typs"][$key]))
						$blocks[$block]["val"][] = isset($row["arr_num"]) ? (int) $row["arr_num"] : "";
					elseif (isset($row["ref_id"]))
						$blocks[$block]["val"][] = $val;
					elseif ($req_id > 0) {
						if (in_array($literal_typ, array(
							"CHARS",
							"MEMO",
							"HTML"
						)))
							$blocks[$block]["val"][] = str_replace("\n", " ", Get_tail($req_id, $val));
						elseif ($GLOBALS["REV_BT"][$base_typ] == "FILE")
							$blocks[$block]["val"][] = Format_Val_View($base_typ, Get_tail($req_id, $val), $req_id);
						else
							$blocks[$block]["val"][] = Format_Val_View($base_typ, $val);
					} elseif ($literal_typ == "BUTTON")
						$blocks[$block]["val"][] = "***";
					else
						$blocks[$block]["val"][] = "";
				} else {
					$blocks[$block]["align"][] = Get_Align($base_typ);
					if (isset($GLOBALS["ARR_typs"][$key]))
						$blocks[$block]["val"][] = "<A HREF=\"/$z/object/$key/?F_U=$parent_id$flags\">(" . (isset($row["arr_num"]) ? (int) $row["arr_num"] : 0) . ")</A>";
					elseif (isset($row["ref_id"])) {
						if (Grant_1level($key))
							$blocks[$block]["val"][] = "<A HREF=\"/$z/object/" . $GLOBALS["REF_typs"][$key] . "/?F_I=" . $row["ref_id"] . "$flags\">" . Format_Val_View($base_typ, htmlspecialchars($val)) . "</A>";
						else
							$blocks[$block]["val"][] = Format_Val_View($base_typ, htmlspecialchars($val));
					} elseif ($req_id > 0) {
						if (in_array($literal_typ, array(
							"CHARS",
							"MEMO",
							"HTML"
						))) {
							if (isset($_REQUEST["full"]) && (mb_strlen($val) == VAL_LIM))
								$blocks[$block]["val"][] = htmlspecialchars(Get_tail($req_id, $val));
							elseif (mb_strlen($val) == VAL_LIM)
								$blocks[$block]["val"][] = str_replace("\n", " ", htmlspecialchars("$val ..."));
							else
								$blocks[$block]["val"][] = str_replace("\n", " ", htmlspecialchars($val));
						} elseif ($GLOBALS["REV_BT"][$base_typ] == "FILE")
							$blocks[$block]["val"][] = Format_Val_View($base_typ, Get_tail($req_id, $val), $req_id);
						else
							$blocks[$block]["val"][] = Format_Val_View($base_typ, htmlspecialchars($val));
					} elseif ($literal_typ == "BUTTON")
						$blocks[$block]["val"][] = " <A HREF=\"/$z/" . str_replace("[ID]", $blocks[$blocks[$block]["PARENT"]]["CUR_VARS"]["id"], str_replace("[VAL]", $blocks[$blocks[$block]["PARENT"]]["CUR_VARS"]["val"], $GLOBALS["attrs"][$key])) . "\">***</A>";
					else
						$blocks[$block]["val"][] = "";
				}
			}
			if (isApi())
				$GLOBALS["GLOBAL_VARS"]["api"]["&object_reqs"][$parent_id] = $blocks[$block]["val"];
			if (isset($_REQUEST["csv"])) {
				array_unshift($blocks[$block]["val"], $blocks[$blocks[$block]["PARENT"]]["CUR_VARS"]["val"]);
				foreach ($blocks[$block]["val"] as $key => $value)
					$blocks[$block]["val"][$key] = iconv("utf-8", "windows-1251", $value);
				fputcsv($GLOBALS["CSV_handler"], $blocks[$block]["val"], ';');
				unset($blocks[$block]["val"]);
			}
			break;
		case "&reqs_links":
			if ($GLOBALS["lnx"] == 1)
				foreach ($GLOBALS["links"] as $key => $value)
					if (Check_Grant($value, $key, "READ", FALSE)) {
						$blocks[$block]["value"][]     = $value;
						$blocks[$block]["links_typ"][] = $key;
						$blocks[$block]["key"][]       = $GLOBALS["links_val"][$key];
					}
			break;
		case "&buttons":
			if (isset($blocks["BUTTONS"]))
				foreach ($blocks["BUTTONS"] as $key => $value) {
					$blocks[$block]["val"][]   = $key;
					$blocks[$block]["attrs"][] = str_replace("[ID]", $GLOBALS["cur_id"], str_replace("[VAL]", $blocks[$blocks[$block]["PARENT"]]["CUR_VARS"]["val"], $value));
				}
			break;
		case "&uni_report":
			if (!isset($GLOBALS["STORED_REPS"][$id]["header"]))
				if (Check_Grant($id, 0, "READ"))
					Compile_Report($id, TRUE, TRUE);
			$blocks[$block]["val"][] = $GLOBALS["STORED_REPS"][$id]["header"];
			break;
		case "&uni_report_head":
			if (isset($GLOBALS["STORED_REPS"][$id]["head"]))
				foreach ($GLOBALS["STORED_REPS"][$id]["head"] as $key => $value)
					if (!isset($GLOBALS["STORED_REPS"][$id][REP_COL_HIDE][$key]))
						$blocks[$block]["val"][] = $GLOBALS["STORED_REPS"][$id]["head"][$key];
			break;
		case "&uni_report_filter":
			if (isset($GLOBALS["STORED_REPS"][$id]["head"])) {
				foreach ($GLOBALS["STORED_REPS"][$id]["head"] as $key => $value)
					if (!isset($GLOBALS["STORED_REPS"][$id][REP_COL_HIDE][$key]) && isset($GLOBALS["STORED_REPS"][$id]["types"][$key])) {
						$value                      = str_replace(" ", "_", $value);
						$blocks[$block]["col"][]    = $value;
						$blocks[$block]["fr_val"][] = isset($_REQUEST["FR_$value"]) ? (strlen($_REQUEST["FR_$value"]) ? $_REQUEST["FR_$value"] : (isset($GLOBALS["STORED_REPS"][$id][REP_COL_FROM][$key]) ? $GLOBALS["STORED_REPS"][$id][REP_COL_FROM][$key] : "")) : "";
						$blocks[$block]["to_val"][] = isset($_REQUEST["TO_$value"]) ? (strlen($_REQUEST["TO_$value"]) ? $_REQUEST["TO_$value"] : (isset($GLOBALS["STORED_REPS"][$id][REP_COL_TO][$key]) ? $GLOBALS["STORED_REPS"][$id][REP_COL_TO][$key] : "")) : "";
					}
			}
			break;
		case "&uni_report_data":
			if ($GLOBALS["STORED_REPS"][$id]["rownum"])
				$blocks[$block]["data"] = array_fill(0, $GLOBALS["STORED_REPS"][$id]["rownum"], "");
			break;
		case "&uni_report_column":
			foreach ($GLOBALS["STORED_REPS"][$id]["head"] as $key => $value) {
				if (isset($GLOBALS["STORED_REPS"][$id]["base_out"][$key]))
					$blocks[$block]["align"][] = Get_Align($GLOBALS["BT"][$GLOBALS["STORED_REPS"][$id]["base_out"][$key]]);
				else
					$blocks[$block]["align"][] = "LEFT";
				$blocks[$block]["val"][] = array_shift($blocks["_data_col"][$id][$GLOBALS["STORED_REPS"][$id]["names"][$key]]);
			}
			break;
		case "&uni_report_totals":
			if (isset($GLOBALS["STORED_REPS"][$id][REP_COL_TOTAL]))
				if (strlen(implode(",", $GLOBALS["STORED_REPS"][$id][REP_COL_TOTAL])))
					$blocks[$block]["totals"][] = "";
			break;
		case "&uni_report_column_total":
			if (isset($blocks["col_totals"][$id]))
				foreach ($blocks["col_totals"][$id] as $key => $value)
					$blocks[$block]["val"][] = $value . "&nbsp";
			break;
		case "&login":
			$blocks[$block]["change"][] = isset($_POST["change"]) ? "CHECKED" : "";
			break;
		case "&dir_admin":
			$grant = RepoGrant();
			if ($grant == "BARRED")
				die(t9n("[RU]Недостаточно прав для доступа к этому рабочему месту[EN]Insufficient permissions to access this workplace"));
			$blocks[$block]["folder"][]  = isset($_REQUEST["download"]) ? "download" : "templates";
			$blocks[$block]["another"][] = isset($_REQUEST["download"]) ? "templates" : "download";
			if ($blocks[$block]["folder"][0] == "download")
				$path = "download/$z";
			else
				$path = "templates/custom/$z";
			if (!file_exists($path))
				mkdir($path);
			$add_path = isset($_REQUEST["add_path"]) ? $_REQUEST["add_path"] : "";
			if (strpos($add_path, "..") !== false)
				$add_path = "";
			if (isset($_REQUEST["gf"]))
				if ((strpos($_REQUEST["gf"], "..") === false) && file_exists($path . $add_path . "/" . $_REQUEST["gf"])) {
					$file = $path . $add_path . "/" . $_REQUEST["gf"];
					if (ob_get_level())
						ob_end_clean();
					header('Content-Description: File Transfer');
					header('Content-Type: application/octet-stream');
					header('Content-Disposition: attachment; filename=' . basename($file));
					header('Content-Transfer-Encoding: binary');
					header('Expires: 0');
					header('Cache-Control: must-revalidate');
					header('Pragma: public');
					header('Content-Length: ' . filesize($file));
					readfile($file);
					exit;
				} else
					die(t9n("[RU]Файл не найден[EN]File not found"));
			if (is_dir($path . $add_path))
				$path .= $add_path;
			else
				$add_path = "";
			$blocks[$block]["path"][]     = $path;
			$blocks[$block]["add_path"][] = $add_path;
			$fname                        = isset($_REQUEST["dir_name"]) ? strtolower(trim($_REQUEST["dir_name"])) : "";
			if (isset($_REQUEST["mkdir"])) {
				if ($grant != "WRITE")
					die(t9n("[RU]Недостаточно прав для создания каталогов[EN]Insufficient permissions to create directories"));
				check();
				if (preg_match(DIR_MASK, $fname)) {
					if (is_dir($path . "/" . $fname))
						die(t9n("[RU]Такой каталог уже существует![EN]This directory already exists!" . BACK_LINK));
					mkdir($path . "/" . $fname);
					header("Location: /$z/dir_admin/?" . $blocks[$block]["folder"][0] . "=1&add_path=$add_path");
					myexit();
				} else
					die(t9n("[RU]Недопустимое имя каталога.[EN]The directory name is invalid" . BACK_LINK));
			}
			if (isset($_REQUEST["touch"])) {
				if ($grant != "WRITE")
					die(t9n("[RU]Недостаточно прав для создания файлов[EN]Insufficient permissions to create files"));
				check();
				if (preg_match(FILE_MASK, $fname)) {
					BlackList(substr(strrchr($fname, '.'), 1));
					if (strpos($fname, ".") === false)
						$fname .= ".html";
					if (is_file($path . "/" . $fname))
						die(t9n("[RU]Такой файл ($fname) уже существует![EN]File ($fname) already exists!" . BACK_LINK));
					touch($path . "/" . $fname);
					header("Location: /$z/dir_admin/?" . $blocks[$block]["folder"][0] . "=1&add_path=$add_path");
					myexit();
				} else
					die(t9n("[RU]Недопустимое имя файла.[EN]Invalid file name" . BACK_LINK));
			}
			$warning = "";
			if (isset($_POST["upload"]))
				if ($grant != "WRITE")
					die(t9n("[RU]Недостаточно прав для загрузки файлов[EN]Not grants to upload files"));
				else {
					check();
					foreach ($_FILES as $value)
						if (strlen($value["name"]) > 0) {
							BlackList(substr(strrchr($value["name"], '.'), 1));
							if (file_exists($path . "/" . $value["name"]))
								if (isset($_REQUEST["rewrite"]))
									$warning = t9n("[RU] (перезаписан)[EN] (rewritten)");
								else
									die(t9n("[RU]Такой файл (" . $value["name"] . ") уже существует![EN]File (" . $value["name"] . ") already exists!" . BACK_LINK));
							if (!move_uploaded_file($value['tmp_name'], $path . "/" . $value["name"]))
								die(t9n("[RU]Не удалось загрузить файл[EN]File uploading failed"));
							$warning = t9n("[RU]Файл [EN]File ") . $value["name"] . t9n("[RU] загружен[EN] uploaded") . $warning;
							header("Location: /$z/dir_admin/?" . $blocks[$block]["folder"][0] . "=1&add_path=$add_path&warning=$warning");
							myexit();
						}
				}
			if (isset($_POST["delete"])) {
				if ($grant != "WRITE")
					die(t9n("[RU]Недостаточно прав для удаления файлов[EN]Insufficient permissions to delete files"));
				check();
				if (is_array($_POST["del"]))
					foreach ($_POST["del"] as $value)
						if (strlen($value))
							RemoveDir($path . "/" . $value);
				header("Location: /$z/dir_admin/?" . $blocks[$block]["folder"][0] . "=1&add_path=$add_path");
				myexit();
			}
			if ($dir = @opendir($path)) {
				$GLOBALS["dir_list"] = $GLOBALS["file_list"] = $GLOBALS["file_size"] = $GLOBALS["file_time"] = array();
				while (($file = readdir($dir)) !== false)
					if ($file != '..' && $file != '.') {
						if (is_dir($path . "/" . $file))
							$GLOBALS["dir_list"][] = $file;
						else
							$GLOBALS["file_list"][] = $file;
					}
				closedir($dir);
				sort($GLOBALS["dir_list"]);
				sort($GLOBALS["file_list"]);
				$blocks[$block]["files"][]   = count($GLOBALS["file_list"]);
				$blocks[$block]["folders"][] = count($GLOBALS["dir_list"]);
				foreach ($GLOBALS["file_list"] as $value) {
					$GLOBALS["file_size"][] = NormalSize(filesize($path . "/" . $value));
					$GLOBALS["file_time"][] = date("d.m.Y H:i:s", filemtime($path . "/" . $value));
				}
			}
			break;
		case "&pattern":
			$add_path = "";
			foreach (explode("/", substr($blocks[$blocks[$block]["PARENT"]]["CUR_VARS"]["add_path"], 1)) as $val) {
				$add_path .= "/$val";
				$blocks[$block]["path"][] = $add_path;
				$blocks[$block]["name"][] = $val;
			}
			break;
		case "&file_list":
			$blocks[$block]["size"] = $GLOBALS["file_size"];
			$blocks[$block]["time"] = $GLOBALS["file_time"];
			$blocks[$block]["name"] = $GLOBALS["file_list"];
			break;
		case "&dir_list":
			$blocks[$block]["name"] = $GLOBALS["dir_list"];
			break;
		default:
			$rep_id = 0;
			if (isset($GLOBALS["STORED_REPS"][$block]["_rep_id"]))
				$rep_id = $GLOBALS["STORED_REPS"][$block]["_rep_id"];
			elseif ($row = mysqli_fetch_array(Exec_sql("SELECT id FROM $z WHERE val='" . addslashes($block_name) . "' AND t=" . REPORT, "Get Report's ID")))
				$rep_id = $row[0];
			elseif (is_numeric($block_name) && ($row = mysqli_fetch_array(Exec_sql("SELECT id FROM $z WHERE id='$block_name' AND t=" . REPORT, "Check Report's ID"))))
				$rep_id = $block_name;
			$GLOBALS["STORED_REPS"][$block]["_rep_id"] = $rep_id;
			if ($rep_id) {
				Compile_Report($rep_id, $exe);
				if (!$exe)
					return;
				$bak_id = $id;
				$id     = $rep_id;
				if (isset($_REQUEST["obj"]) && ($_REQUEST["obj"] != 0))
					$obj = $_REQUEST["obj"];
				$id = $rep_id;
				Get_block_data("&uni_report");
				Get_block_data("&uni_report_head");
				Get_block_data("&uni_report_data");
				if (isset($blocks["_data_col"][$id]) && isset($GLOBALS["STORED_REPS"][$id]["head"]))
					foreach ($GLOBALS["STORED_REPS"][$id]["head"] as $key => $value)
						if (!isset($GLOBALS["STORED_REPS"][$id][REP_COL_HIDE][$key]))
							if ($GLOBALS["STORED_REPS"][$id]["base_out"][$key] == "HTML")
								$blocks[$block][strtolower($value)] = array_shift($blocks["_data_col"][$id]);
							else
								$blocks[$block][strtolower($value)] = str_replace("\n", "<BR/>", array_shift($blocks["_data_col"][$id]));
				$id = $bak_id;
			}
			break;
	}
}
function RepoGrant()
{
	global $z;
	if (isset($GLOBALS["GRANTS"][$GLOBALS["BT"]["FILE"]]))
		return $GLOBALS["GRANTS"][$GLOBALS["BT"]["FILE"]];
	elseif (($z == $GLOBALS["GLOBAL_VARS"]["user"]) || ($GLOBALS["GLOBAL_VARS"]["user"] == "admin"))
		return "WRITE";
	return "BARRED";
}
function GetObjectReqs($typ, $id)
{
	global $z;
	$GLOBALS["REQS"] = array();
	$sql             = "SELECT a.id t, refs.id ref_id, a.val attrs, a.ord
, CASE WHEN refs.id IS NULL THEN typs.t ELSE refs.t END base_typ
, CASE WHEN refs.id IS NULL THEN typs.val ELSE refs.val END val
, CASE WHEN arrs.id IS NULL THEN NULL ELSE typs.id END arr_id
FROM $z a, $z typs LEFT JOIN $z refs ON refs.id=typs.t AND refs.t!=refs.id
LEFT JOIN $z arrs ON refs.id IS NULL AND arrs.up=typs.id AND arrs.ord=1
WHERE a.up=$typ AND typs.id=a.t ORDER BY a.ord";
	$data_set        = Exec_sql($sql, "Get the Reqs meta");
	while ($row = mysqli_fetch_array($data_set)) {
		if ($row["ref_id"])
			$GLOBALS["REF_typs"][$row["t"]] = $row["ref_id"];
		elseif ($row["arr_id"])
			$GLOBALS["ARR_typs"][$row["t"]] = $row["arr_id"];
		if (($row["base_typ"] == 0) && !isset($_REQUEST["copybtn"])) {
			if (count($GLOBALS["REQS"]) && !(isset($GLOBALS["TABS"]))) {
				$GLOBALS["TABS"][0] = t9n("[RU]Реквизиты[EN]Attributes");
				if (!isset($_REQUEST["tab"]) || ($_REQUEST["tab"] == 0)) {
					$tab_from                   = 0;
					$tab_to                     = $row["ord"];
					$GLOBALS["TABS"][$row["t"]] = $row["val"];
					continue;
				}
			}
			$GLOBALS["TABS"][$row["t"]] = $row["val"];
			if (isset($_REQUEST["tab"])) {
				if ($_REQUEST["tab"] == $row["t"]) {
					$GLOBALS["REQS"] = array();
					$tab_from        = $row["ord"];
				} elseif (isset($tab_from))
					$tab_to = $row["ord"];
			} elseif (isset($tab_from))
				$tab_to = $row["ord"];
			else
				$tab_from = $row["ord"];
			continue;
		}
		if (isset($tab_to) && ($GLOBALS["REV_BT"][$row["base_typ"]] != "BUTTON"))
			continue;
		$GLOBALS["REQS"][$row["t"]]["base_typ"] = $row["base_typ"];
		$GLOBALS["REQS"][$row["t"]]["val"]      = isset($row["ref_id"]) ? FetchAlias($row["attrs"], $row["val"]) : $row["val"];
		$GLOBALS["REQS"][$row["t"]]["ref_id"]   = $row["ref_id"];
		$GLOBALS["REQS"][$row["t"]]["arr_id"]   = $row["arr_id"];
		$GLOBALS["REQS"][$row["t"]]["attrs"]    = $row["attrs"];
	}
	if (isset($GLOBALS["ARR_typs"]))
		$sql = "SELECT CASE WHEN typs.up=0 THEN 0 ELSE reqs.id END id, CASE WHEN typs.up=0 THEN 0 ELSE reqs.val END val
, typs.id t, count(1) arr_num";
	else
		$sql = "SELECT reqs.id, reqs.val, typs.id t, origs.t base_typ";
	$sql .= ", origs.t bt, typs.val ref_val FROM $z reqs JOIN $z typs ON typs.id=reqs.t LEFT JOIN $z origs ON origs.id=typs.t WHERE reqs.up=$id";
	if (isset($GLOBALS["ARR_typs"]))
		$sql .= " GROUP BY val, id, t";
	$data_set = Exec_sql($sql, "GetObjectReqs");
	while ($row = mysqli_fetch_array($data_set))
		if (isset($GLOBALS["REF_typs"][$row["val"]])) {
			$rows[$row["val"]]["id"]      = $row["id"];
			$rows[$row["val"]]["val"]     = $row["t"];
			$rows[$row["val"]]["ref_val"] = $row["ref_val"];
		} else {
			$rows[$row["t"]]["id"] = $row["id"];
			if (in_array($GLOBALS["REV_BT"][$row["bt"]], array(
				"CHARS",
				"MEMO",
				"FILE",
				"HTML"
			)) && (mb_strlen($row["val"]) == VAL_LIM))
				$rows[$row["t"]]["val"] = Get_tail($row["id"], $row["val"]);
			else
				$rows[$row["t"]]["val"] = $row["val"];
			$rows[$row["t"]]["arr_num"] = isset($row["arr_num"]) ? $row["arr_num"] : 0;
		}
	if (isset($rows))
		$GLOBALS["ObjectReqs"] = $rows;
}
function Calc_Order($up, $t)
{
	global $z;
	$data_set = Exec_sql("SELECT COALESCE(MAX(ord)+1, 1) FROM $z WHERE t=$t AND up=$up", "Get the Ord for new Array Object");
	if ($row = mysqli_fetch_array($data_set))
		return $row[0];
	die(t9n("[RU]Не удается вычислить порядок[EN]Cannot Calc the Order"));
}
function Populate_Reqs($i, $new_id)
{
	global $z;
	$chil = exec_sql("SELECT $z.*, base.t base, (SELECT 1 FROM $z ch WHERE up=$z.id LIMIT 1) ch
FROM $z LEFT JOIN $z typ ON typ.id=$z.t LEFT JOIN $z base ON base.id=typ.t WHERE $z.up=$i", "Get children");
	while ($ch = mysqli_fetch_array($chil))
		if (!isset($_REQUEST["t" . $ch["t"]])) {
			if ($GLOBALS["REV_BT"][$ch["base"]] == "FILE") {
				$id        = Insert($new_id, $ch["ord"], $ch["t"], $ch["val"], "Copy file");
				$orig_path = GetSubdir($ch["id"]) . "/" . GetFilename($ch["id"]) . "." . substr(strrchr($ch["val"], '.'), 1);
				$new_dir   = GetSubdir($id);
				@mkdir($new_dir);
				if (is_file($orig_path))
					if (copy($orig_path, $new_dir . "/" . GetFilename($id) . "." . substr(strrchr($ch["val"], '.'), 1)))
						continue;
				$GLOBALS["warning"] .= t9n("[RU]Не удалось скопировать файл $orig_path в $new_dir" . "[EN]Couldn't copy file $orig_path to $new_dir") . "<br>";
			} elseif ($ch["ch"] == 1) {
				$id = Insert($new_id, $ch["ord"], $ch["t"], $ch["val"], "Copy child");
				Populate_Reqs($ch["id"], $id);
			} else
				Insert_batch($new_id, $ch["ord"], $ch["t"], $ch["val"], "Copy req to batch");
		}
}
function Get_Current_Values($id, $typ)
{
	GetObjectReqs($typ, $id);
	$rows = isset($GLOBALS["ObjectReqs"]) ? $GLOBALS["ObjectReqs"] : array();
	foreach ($GLOBALS["REQS"] as $key => $value) {
		if (!is_array($value))
			continue;
		if (!(strpos($GLOBALS["REQS"][$key]["attrs"], NOT_NULL_MASK) === FALSE))
			$GLOBALS["NOT_NULL"][$key] = "";
		$GLOBALS["REV_BT"][$key] = $GLOBALS["REV_BT"][$GLOBALS["REQS"][$key]["base_typ"]];
		if (isset($rows[$key])) {
			$GLOBALS["REQS"][$key]     = $rows[$key]["val"];
			$GLOBALS["REQ_TYPS"][$key] = $rows[$key]["id"];
		} elseif (isset($GLOBALS["REF_typs"][$key])) {
			$GLOBALS["REQS"][$key]     = isset($rows[$GLOBALS["REF_typs"][$key]]["val"]) ? $rows[$GLOBALS["REF_typs"][$key]]["val"] : NULL;
			$GLOBALS["REQ_TYPS"][$key] = isset($rows[$GLOBALS["REF_typs"][$key]]["id"]) ? $rows[$GLOBALS["REF_typs"][$key]]["id"] : NULL;
			$GLOBALS["REV_BT"][$key]   = "REFERENCE";
		} elseif (isset($GLOBALS["ARR_typs"][$key]))
			$GLOBALS["REQS"][$key] = isset($rows[$GLOBALS["ARR_typs"][$key]]["arr_num"]) ? $rows[$GLOBALS["ARR_typs"][$key]]["arr_num"] : NULL;
		elseif ($key != $typ)
			$GLOBALS["REQS"][$key] = $GLOBALS["REQ_TYPS"][$key] = "";
		if ($GLOBALS["REV_BT"][$key] == "BOOLEAN")
			if ($GLOBALS["REQS"][$key] == 1)
				$GLOBALS["BOOLEANS"][$key] = 1;
	}
}
function Get_Ord($parent, $typ = 0)
{
	global $z;
	$result = Exec_sql("SELECT max(ord) ord FROM $z WHERE up=$parent" . ($typ == 0 ? "" : " AND t=$typ"), "Get Ord");
	$row    = mysqli_fetch_array($result);
	return $row["ord"] + 1;
}
function Salt($u, $val)
{
	global $z;
	$u = strtoupper($u);
	return SALT;
}
function Insert_batch($up, $ord, $t, $val, $message)
{
	if (mb_strlen($val) > VAL_LIM)
		return Insert($up, $ord, $t, $val, $message);
	global $connection, $z;
	if (($up === "") && isset($GLOBALS["SQLbatch"])) {
		exec_sql("INSERT INTO $z (up, ord, t, val) VALUES " . $GLOBALS["SQLbatch"], "Close batch: $message");
		unset($GLOBALS["SQLbatch"]);
		return;
	}
	if (isset($GLOBALS["SQLbatch"]))
		$GLOBALS["SQLbatch"] .= ",($up,$ord,$t,'" . addslashes($val) . "')";
	else
		$GLOBALS["SQLbatch"] = "($up,$ord,$t,'" . addslashes($val) . "')";
	if (strlen($GLOBALS["SQLbatch"]) > 31000) {
		exec_sql("INSERT INTO $z (up, ord, t, val) VALUES " . $GLOBALS["SQLbatch"], "Flush batch: $message");
		unset($GLOBALS["SQLbatch"]);
	}
}
function Insert($up, $ord, $t, $val, $message)
{
	global $connection, $z;
	exec_sql("INSERT INTO $z (up, ord, t, val) VALUES ($up, $ord, $t, '" . addslashes(mb_substr($val, 0, VAL_LIM)) . "')", "Insert: $message");
	$id  = mysqli_insert_id($connection);
	$ord = 0;
	while (mb_strlen($val) > VAL_LIM) {
		$val = mb_substr($val, VAL_LIM);
		exec_sql("INSERT INTO $z (up, ord, t, val) VALUES ($id, " . ($ord++) . ", 0, '" . addslashes(mb_substr($val, 0, VAL_LIM)) . "')", 'Save tail $id ($order)');
	}
	return $id;
}
function Update_Val($id, $val, $no_tail = FALSE)
{
	global $z;
	if ($no_tail && (mb_strlen($val) <= VAL_LIM))
		return Exec_sql("UPDATE $z SET val='" . addslashes($val) . "' WHERE id=$id", "Update Val with no tails");
	$tails = exec_sql("SELECT id, ord, val FROM $z WHERE up=$id AND t=0 ORDER BY ord", "Get tails");
	$v     = addslashes(mb_substr($val, 0, VAL_LIM));
	Exec_sql("UPDATE $z SET val='$v' WHERE id=$id", "Update Val");
	$val = mb_substr($val, VAL_LIM);
	$ord = 0;
	while ($tail = mysqli_fetch_array($tails)) {
		$ord = $tail[1];
		if (mb_strlen($val) == 0) {
			Exec_sql("DELETE FROM $z WHERE up=$id AND t=0 AND ord>=$ord", "Kill Tails");
			return;
		} else {
			$v = addslashes(mb_substr($val, 0, VAL_LIM));
			if ($tail[2] != $v)
				Exec_sql("UPDATE $z SET val='$v' WHERE id=" . $tail[0], "Update Val tail");
			$val = mb_substr($val, VAL_LIM);
			$ord++;
		}
	}
	while (mb_strlen($val) > 0) {
		$v = mb_substr($val, 0, VAL_LIM);
		Insert($id, $ord, 0, $v, "Insert Tail");
		$val = mb_substr($val, VAL_LIM);
		$ord++;
	}
}
function die_info($msg)
{
	if (isset($GLOBALS["TRACE"]))
		echo $GLOBALS["TRACE"];
	if (($GLOBALS["GLOBAL_VARS"]["z"] == $GLOBALS["GLOBAL_VARS"]["user"]) || ($GLOBALS["GLOBAL_VARS"]["user"] == "admin"))
		die("$msg<br /><font color=\"lightgray\"><a href=\"/" . $GLOBALS["GLOBAL_VARS"]["z"] . "/dir_admin\">Файлы</a></font>");
	die($msg);
}
function Make_tree($text, $cur_block)
{
	global $blocks;
	if (substr($text, 0, 3) == pack('CCC', 0xef, 0xbb, 0xbf))
		$text = substr($text, 3);
	$begin                         = "begin:";
	$end                           = "end:";
	$file                          = "file:";
	$begin_delimiter               = "<!-- ";
	$end_delimiter                 = " -->";
	$exp                           = explode($begin_delimiter, $text);
	$patt                          = "/($begin|$end|$file)[[:blank:]]*(&?[A-ZА-Я0-9_ ]+)[[:blank:]]*$end_delimiter(.*)/uims";
	$blocks[$cur_block]["CONTENT"] = "";
	foreach ($exp as $key => $a)
		if (preg_match($patt, $a, $res)) {
			$res[1] = strtolower($res[1]);
			$res[2] = strtolower($res[2]);
			if (strcasecmp($res[1], $begin) == 0) {
				$blocks[$cur_block . "." . $res[2]]["PARENT"] = $cur_block;
				$cur_block                                    = $cur_block . "." . $res[2];
				$blocks[$cur_block]["CONTENT"]                = $res[3];
			} elseif (strcasecmp($res[1], $end) == 0) {
				if ($blocks[$cur_block]["PARENT"] . "." . $res[2] != $cur_block)
					die_info("Invalid blocks nesting (" . $blocks[$cur_block]["PARENT"] . "." . $res[2] . " - $cur_block)!");
				$insertion_point = "{_block_.$cur_block}";
				$cur_block       = $blocks[$cur_block]["PARENT"];
				$blocks[$cur_block]["CONTENT"] .= $insertion_point . $res[3];
			} elseif (strcasecmp($res[1], $file) == 0) {
				if ($res[2] == "a")
					$text = Get_file($GLOBALS["GLOBAL_VARS"]["action"] . ".html", FALSE);
				elseif (isset($_GET[$res[2]]))
					$text = Get_file($_GET[$res[2]] . ".html", FALSE);
				else
					$text = Get_file($res[2] . ".html", FALSE);
				if (strlen($text) == 0)
					$text = Get_file("info.html");
				$file_block                    = "$cur_block." . (isset($_REQUEST[$res[2]]) ? $_REQUEST[$res[2]] : "");
				$insertion_point               = "{_block_.$file_block}";
				$blocks[$file_block]["PARENT"] = $cur_block;
				Make_tree($text, $file_block);
				$blocks[$cur_block]["CONTENT"] .= $insertion_point . $res[3];
			}
		} elseif ($a) {
			if ($key != 0)
				$blocks[$cur_block]["CONTENT"] .= $begin_delimiter . $a;
			else
				$blocks[$cur_block]["CONTENT"] = $a;
		}
}
function Parse_block($block)
{
	global $blocks;
	$i = count($blocks[$block], 1);
	Get_block_data($block);
	if (preg_match("/\{([A-ZА-Я0-9_ \-]*?[^ ;\r\n])}/ui", $blocks[$block]["CONTENT"]) && ($i == count($blocks[$block], 1)))
		return "";
	preg_match_all("/\{([A-ZА-Я0-9\.&_ \-]+?)}/ui", $blocks[$block]["CONTENT"], $temp);
	$points = $sub = array();
	foreach (array_unique($temp[1]) as $key => $value)
		if (substr($value, 0, 7) != "_block_")
			$points[] = $value;
		else
			$sub[] = $value;
	$points  = array_merge($points, $sub);
	$content = "";
	unset($end);
	while (!isset($end)) {
		$end         = 1;
		$cur_content = $blocks[$block]["CONTENT"];
		foreach ($blocks[$block] as $key => $value)
			if (($key != "CUR_VARS") && is_array($value))
				$blocks[$block]["CUR_VARS"][$key] = array_shift($blocks[$block][$key]);
		foreach ($points as $key => $point) {
			unset($item);
			$point = strtolower($point);
			$sub   = explode(".", $point);
			if ($sub[0] == "_block_") {
				trace("Got sub-block: $point");
				unset($sub[0]);
				$sub_block = implode(".", $sub);
				trace("Parse sub-block: $sub_block");
				$item        = Parse_block($sub_block);
				$cur_content = str_ireplace("{" . $point . "}", $item, $cur_content);
			} else {
				if (isset($blocks[$block]["CUR_VARS"][$point]))
					$item = $blocks[$block]["CUR_VARS"][$point];
				elseif ($sub[0] == "_parent_") {
					$parent = $blocks[$block]["PARENT"];
					while (!isset($item))
						if (isset($blocks[$parent]["CUR_VARS"][$sub[1]]))
							$item = $blocks[$parent]["CUR_VARS"][$sub[1]];
						elseif (isset($blocks[$parent]["PARENT"]))
							$parent = $blocks[$parent]["PARENT"];
						else
							break;
				} elseif ($sub[0] == "_global_")
					$item = isset($GLOBALS["GLOBAL_VARS"][$sub[1]]) ? $GLOBALS["GLOBAL_VARS"][$sub[1]] : (BuiltIn("[" . strtoupper($sub[1]) . "]") === "[" . strtoupper($sub[1]) . "]" ? "" : BuiltIn("[" . strtoupper($sub[1]) . "]"));
				elseif ($sub[0] == "_request_") {
					foreach ($_GET as $k => $v)
						if (strtolower($k) == $sub[1]) {
							$item = $v;
							break;
						}
					foreach ($_POST as $k => $v)
						if (strtolower($k) == $sub[1]) {
							$item = $v;
							break;
						}
				}
				if (isset($item))
					$cur_content = str_ireplace("{" . $point . "}", str_replace("{", "&#123;", $item), $cur_content);
				else
					break;
				if (isApi() && ($sub[0] != "_global_"))
					$GLOBALS["GLOBAL_VARS"]["api"][$block][$point][] = str_replace("{", "&#123;", $item);
				if (isset($blocks[$block][$point]))
					if (count($blocks[$block][$point]))
						unset($end);
			}
		}
		if (!preg_match("/\{([A-ZА-Я0-9\.&_ \-]*?[^ ;\r\n])}/ui", $cur_content) || isset($_REQUEST["debug"]))
			$content .= $cur_content;
	}
	if (($block == "&main") || ($block == ""))
		return str_replace("&#123;", "{", $content);
	else
		return $content;
}
function NormalSize($size)
{
	if ($size < 1024)
		return $size . " B";
	elseif ($size < 1048576)
		return round($size / 1024, 2) . " KB";
	elseif ($size < 1073741824)
		return round($size / 1048576, 2) . " MB";
	elseif ($size < 1099511627776)
		return round($size / 1073741824, 2) . " GB";
	else
		return round($size / 1099511627776, 2) . " TB";
}
function check()
{
	if (isset($_POST["_xsrf"]))
		if ($GLOBALS["GLOBAL_VARS"]["xsrf"] == $_POST["_xsrf"])
			return true;
	my_die(t9n("[RU]Неверный или устаревший токен CSRF<br/>[EN]Invalid or expired CSRF token <br/>" . BACK_LINK));
}
function api_dump($json, $name = "api.json")
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
	if (isset($GLOBALS["TRACE"])) {
		if (!is_dir($path = "templates/custom/$z/logs"))
			mkdir($path);
		$file = fopen("$path/trace" . date("YmdHis") . ".log", "a+");
		fwrite($file, $GLOBALS["TRACE"] . print_r($GLOBALS, TRUE));
		fclose($file);
	}
	exit();
}
function dumptrace()
{
	global $z;
	if (!is_dir($path = "templates/custom/$z/logs"))
		mkdir($path);
	$file = fopen("$path/trace" . date("YmdHis") . ".log", "a+");
	fwrite($file, $GLOBALS["TRACE"] . print_r($GLOBALS, TRUE));
	fclose($file);
}
function CheckRepColGranted($id, $level = 0)
{
	global $z;
	$row = mysqli_fetch_array(Exec_sql("SELECT up FROM $z WHERE id=$id", "Check the new ref"));
	if ($level !== 0) {
		if ($row["up"] == 0) {
			if (!Grant_1level($id))
				my_die(t9n("[RU]Нет доступа на запись к объекту с типом $id [EN]Object type #$id is not granted for changes"));
		} else
			Check_Grant($row["up"], $id);
	} elseif (!Grant_1level($row["up"] == 0 ? $id : $row["up"]))
		my_die(t9n("[RU]Нет доступа к объекту с типом " . ($row["up"] == 0 ? $id : $id . ":" . $row["up"]) . ".[EN]Object type #" . ($row["up"] == 0 ? $id : $id . ":" . $row["up"]) . " is not granted"));
}
function isDbVacant($db)
{
	global $z;
	if ($row = mysqli_fetch_array(Exec_sql("SELECT 1 FROM $z WHERE val='$db' AND t=" . DATABASE, "Check DB name uniquity")))
		return false;
	return true;
}
function checkDuplicatedReqs($id, $t)
{
	global $z;
	$data_set = Exec_sql("SELECT id FROM $z WHERE up=$id AND t=$t ORDER BY id DESC", "Check Duplicated Reqs");
	$row      = mysqli_fetch_array($data_set);
	while ($row = mysqli_fetch_array($data_set))
		Delete($row["id"]);
}
$time_start = microtime(TRUE);
$blocks     = array();
$a          = $GLOBALS["GLOBAL_VARS"]["action"] = isset($com[2]) ? $com[2] : "";
$id         = $GLOBALS["GLOBAL_VARS"]["id"] = isset($com[3]) ? (int) $com[3] : "";
$next_act   = isset($_REQUEST["next_act"]) ? addslashes($_REQUEST["next_act"]) : "";
Exec_sql("SET SESSION optimizer_search_depth = 9", "Search depth");
$GLOBALS["GLOBAL_VARS"]["uri"] = htmlentities($_SERVER["REQUEST_URI"]);
if (Validate_Token()) {
	$up     = isset($_REQUEST["up"]) ? (int) $_REQUEST["up"] : 0;
	$t      = isset($_REQUEST["t"]) ? (int) $_REQUEST["t"] : 0;
	$val    = isset($_REQUEST["val"]) ? $_REQUEST["val"] : "";
	$unique = isset($_REQUEST["unique"]) ? 1 : 0;
	$arg    = "";
	if (substr($a, 0, 3) == "_m_")
		check();
	elseif (substr($a, 0, 3) == "_d_")
		if ((Check_Types_Grant() == "WRITE") && check())
			$next_act = $next_act == "" ? "edit_types" : $next_act;
		else
			die(t9n("[RU]У вас нет прав на редактирование типов(" . $GLOBALS["GRANTS"][0] . ").[EN]You don't have permission to edit types (" . $GLOBALS["GRANTS"][0] . ")."));
	switch ($a) {
		case "_m_up":
			Check_Grant($id);
			$result = Exec_sql("SELECT obj.t, obj.up, obj.ord, max(peers.ord) new_ord FROM $z obj LEFT JOIN $z peers ON peers.up=obj.up AND peers.t=obj.t AND peers.ord<obj.ord WHERE obj.id=$id", "Get new Order and other Reqs");
			if ($row = mysqli_fetch_array($result)) {
				$up = $row["up"];
				$id = $row["t"];
				if ($row["new_ord"] > 0)
					Exec_sql("UPDATE $z SET ord=(CASE WHEN ord=" . $row["ord"] . " THEN " . $row["new_ord"] . " WHEN ord=" . $row["new_ord"] . " THEN " . $row["ord"] . " END) WHERE up=$up AND (ord=" . $row["ord"] . " OR ord=" . $row["new_ord"] . ")", "Change Req order");
			} else
				exit("No arr recs");
			$arg = "F_U=$up";
			$a   = "object";
			break;
		case "_m_set":
			$t = 0;
			foreach ($_REQUEST as $key => $val)
				if ((substr($key, 0, 1) == "t") && ((int) substr($key, 1) != 0)) {
					$t = (int) substr($key, 1);
					Check_Grant($id, $t);
					$result = Exec_sql("SELECT a.id, a.val, def.t FROM $z obj JOIN $z req ON req.up=obj.t AND req.id=$t JOIN $z def ON def.id=req.t LEFT JOIN $z a ON a.up=$id AND (a.t=$t OR a.val='$t') WHERE obj.id=$id", "Get Attr Type");
					if ($row = mysqli_fetch_array($result)) {
						$cur_val = $row["val"];
						$cur_id  = $row["id"];
						if (!isset($GLOBALS["basics"][$row["t"]])) {
							$val = (int) $val;
							if ($val)
								if (!($row = mysqli_fetch_array(Exec_sql("SELECT 1 FROM $z WHERE id=$val AND t=" . $row["t"], "Check new Ref"))))
									die(t9n("[RU]Неверная ссылка $val :[EN]Wrong Reference $val : " . $row["t"]));
							if ($cur_id) {
								if ($val) {
									if ($val != $cur_val)
										Exec_sql("UPDATE $z SET t=$val WHERE id=$cur_id", "Update Reference Attr");
								} else
									Delete($row["id"]);
							} elseif ($val) {
								Insert($id, 1, $val, "$t", "Insert new Ref Attr");
								checkDuplicatedReqs($id, $t);
							}
						} else {
							if (($GLOBALS["REV_BT"][$row["t"]] == "NUMBER") && ($val != 0))
								$val = (int) $val;
							elseif (($GLOBALS["REV_BT"][$row["t"]] == "SIGNED") && ($val != 0))
								$val = (float) str_replace(",", ".", $val);
							else
								$val = Format_Val($row["t"], $val);
							if ($cur_id) {
								if ($val == "") {
									if ($GLOBALS["REV_BT"][$row["t"]] === "FILE")
										RemoveDir(GetSubdir($cur_id) . "/" . GetFilename($cur_id) . "." . substr(strrchr($cur_val, '.'), 1));
									Delete($cur_id);
								} else {
									if (in_array($GLOBALS["REV_BT"][$row["t"]], array(
										"CHARS",
										"MEMO",
										"FILE",
										"HTML"
									)))
										$cur_val = Get_tail($cur_id, $cur_val);
									else
										$val = Format_Val($row["t"], $val);
									if ($val != $cur_val)
										Update_Val($cur_id, $val);
								}
							} elseif ($val != "") {
								Insert($id, 1, $t, $val, "Insert new non-empty rec");
								checkDuplicatedReqs($id, $t);
							}
						}
					}
				}
			foreach ($_FILES as $key => $value)
				if (strlen($value["name"]) > 0) {
					$t = substr($key, 1);
					if ((substr($key, 0, 1) != "t") || ($t == 0))
						continue;
					if (Check_Grant($id, $t)) {
						BlackList(substr(strrchr($value["name"], '.'), 1));
						if (!file_exists(UPLOAD_DIR))
							mkdir(UPLOAD_DIR);
						$result = Exec_sql("SELECT req.id FROM $z req, $z def, $z base WHERE req.up=$id AND base.id=def.t AND def.id=req.t AND base.t=" . $GLOBALS["BT"]["FILE"], "Get File attr ID");
						if ($row = mysqli_fetch_array($result)) {
							$req_id = $row["id"];
							Update_Val($req_id, $value["name"]);
						} else
							$req_id = Insert($id, 1, $t, $value["name"], "Insert new Filename");
						$subdir = GetSubdir($req_id);
						if (!file_exists($subdir))
							@mkdir($subdir);
						if (!move_uploaded_file($value['tmp_name'], $subdir . "/" . GetFilename($req_id) . "." . substr(strrchr($value["name"], '.'), 1)))
							die(t9n("[RU]Не удалось загрузить файл[EN]File uploading failed"));
						$arg .= $subdir . "/" . GetFilename($req_id) . "." . substr(strrchr($value["name"], '.'), 1);
					}
				}
			if ($t == 0)
				die(t9n("[RU]Нет набора атрибутов ($key) или пустое значение ($val) [EN]No attribute set ($key) or empty value ($val)"));
			$a = "nul";
			break;
		case "_m_save":
			$result = Exec_sql("SELECT a.val, a.t typ, a.up, a.ord, typs.t, tail.up tail FROM $z typs, $z a LEFT JOIN $z tail ON tail.up=a.id AND tail.t=0 WHERE typs.id=a.t AND a.id=$id", "Get current Val and Type");
			if ($row = mysqli_fetch_array($result))
				$cur_val = $row["val"];
			else
				exit("No such record");
			if ($row["up"] == 0)
				exit("Cannot update meta-data");
			$typ      = $row["typ"];
			$base_typ = $GLOBALS["basics"][$row["t"]];
			$up       = $row["up"];
			if ($up > 1)
				$arg = "F_U=$up";
			if ($row["tail"])
				$cur_val = Get_tail($id, $cur_val);
			$GLOBALS["REV_BT"][$typ] = $GLOBALS["REV_BT"][$row["t"]];
			$search_str              = "";
			foreach ($_REQUEST as $key => $value)
				if ((substr($key, 0, 7) == "SEARCH_") && (strlen($value)))
					if ($value != $_REQUEST["PREV_$key"]) {
						$search[substr($key, 7)] = $value;
						$search_str .= "&$key=$value";
					}
			if (isset($_REQUEST["copybtn"])) {
				Check_Grant($id);
				$copy = TRUE;
				if ($up > 1)
					$ord = Calc_Order($up, $typ);
				else
					$ord = 1;
				$old_id = $id;
				if (strlen($_REQUEST["t$typ"]))
					$GLOBALS["REQS"][$typ] = $cur_val = $_REQUEST["t$typ"];
				$id = Insert($up, $ord, $typ, $cur_val, "Copy the object");
				Populate_Reqs($old_id, $id);
				Insert_batch("", "", "", "", "Flush Copy");
				if (isset($GLOBALS["BOOLEANS"]))
					unset($GLOBALS["BOOLEANS"]);
				$arg = "copied1&";
			} else {
				$arg  = "saved1&";
				$copy = FALSE;
			}
			$GLOBALS["REQ_TYPS"][$typ] = $id;
			Get_Current_Values($id, $typ);
			$GLOBALS["REQS"][$typ] = $cur_val;
			foreach ($_REQUEST as $key => $value) {
				$t = substr($key, 1);
				if ((substr($key, 0, 1) != "t") || ($t == 0))
					continue;
				$req_id = isset($GLOBALS["REQ_TYPS"][$t]) ? $GLOBALS["REQ_TYPS"][$t] : 0;
				if (!isset($GLOBALS["REF_typs"][$t]))
					$v = Format_Val($t, $value);
				else
					$v = $value;
				if (isset($_REQUEST["NEW_$t"]) && isset($GLOBALS["REF_typs"][$t]))
					if (strlen($_REQUEST["NEW_$t"])) {
						$value = $_REQUEST["NEW_$t"];
						if ($row = mysqli_fetch_array(Exec_sql("SELECT id FROM $z WHERE val='" . addslashes($_REQUEST["NEW_$t"]) . "' AND t=" . $GLOBALS["REF_typs"][$t], "Check if the Ref exists")))
							$v = $row["id"];
						elseif (Grant_1level($GLOBALS["REF_typs"][$t]) == "WRITE")
							$v = Insert(1, 1, $GLOBALS["REF_typs"][$t], addslashes($_REQUEST["NEW_$t"]), "Insert the new Ref Obj");
						else
							die(t9n("[RU]У вас нет прав на создание объектов этого типа (" . $GLOBALS["REF_typs"][$t] . ").[EN]You don't have permission to create (" . $GLOBALS["REF_typs"][$t] . ") type of object."));
					}
				if ($base_typ == "REPORT_COLUMN")
					if (($t == REP_COL_SET) || ($t == $typ))
						if ((strlen($_REQUEST["t" . REP_COL_SET]) != 0))
							if (($_REQUEST["t$typ"] !== $GLOBALS["REQS"][$typ]) || ($_REQUEST["t" . REP_COL_SET] != $GLOBALS["REQS"][REP_COL_SET]))
								CheckRepColGranted($_REQUEST["t$typ"], "WRITE");
				if ($v != $GLOBALS["REQS"][$t]) {
					if ($t == $typ) {
						Check_Grant($id);
						if ($base_typ == "REPORT_COLUMN")
							CheckRepColGranted((int) $v);
					} elseif ($t == REP_COL_SET)
						CheckRepColGranted($cur_val, "WRITE");
					else
						Check_Grant($id, $t);
					if (strlen($value) != 0) {
						if (isset($GLOBALS["REF_typs"][$t])) {
							if ($row = mysqli_fetch_array(Exec_sql("SELECT val FROM $z WHERE id=" . (int) $v . " AND t=" . $GLOBALS["REF_typs"][$t], "Check Ref's Type"))) {
								Check_Val_granted($GLOBALS["REF_typs"][$t], $row["val"]);
								if ($req_id == 0) {
									Insert($id, 1, $v, "$t", "Insert new Ref");
									checkDuplicatedReqs($id, $t);
								} else
									Exec_sql("UPDATE $z SET t=$v WHERE id=$req_id", "Update Reference");
								if (isset($search[$t]))
									unset($search[$t]);
							} else
								my_die(t9n("[RU]Неверный тип объекта с ID=$v или объект не найден[EN]Invalid object type with ID=$v or the object was not found"));
						} else {
							if ($req_id)
								Update_Val($req_id, $v);
							else {
								Insert($id, 1, $t, $v, "Insert new non-empty rec");
								checkDuplicatedReqs($id, $t);
							}
						}
					} else {
						if ($req_id == 0)
							$GLOBALS["warning"] .= t9n("[RU]Пустой тип реквизита![EN]Empty attribute type") . "<br>";
						elseif ($t != $typ)
							Exec_sql("DELETE FROM $z WHERE id=$req_id OR up=$req_id", "Delete Empty Obj");
						else
							$GLOBALS["warning"] .= t9n("[RU]Нельзя оставить пустым имя объекта![EN]Object name cannot be blank!") . "<br>";
					}
				}
				if ($GLOBALS["REV_BT"][$t] == "BOOLEAN")
					unset($GLOBALS["BOOLEANS"][$t]);
			}
			if (isset($GLOBALS["BOOLEANS"]))
				foreach ($GLOBALS["BOOLEANS"] as $key => $value)
					if (isset($_REQUEST["b$key"]))
						if (Check_Grant($id, $key, "WRITE", FALSE))
							Exec_sql("DELETE FROM $z WHERE id=" . $GLOBALS["REQ_TYPS"][$key], "Clear empty boolean Reqs");
			foreach ($_FILES as $key => $value)
				if (strlen($value["name"]) > 0) {
					$t = substr($key, 1);
					if ((substr($key, 0, 1) != "t") || ($t == 0))
						continue;
					if (Check_Grant($id, $t)) {
						BlackList(substr(strrchr($value["name"], '.'), 1));
						if (!file_exists(UPLOAD_DIR))
							mkdir(UPLOAD_DIR);
						$req_id = $GLOBALS["REQ_TYPS"][$t];
						if ($req_id == 0)
							$req_id = Insert($id, 1, $t, $value["name"], "Insert new Filename");
						else
							Update_Val($req_id, $value["name"]);
						$subdir = GetSubdir($req_id);
						if (!file_exists($subdir))
							@mkdir($subdir);
						if (!move_uploaded_file($value['tmp_name'], $subdir . "/" . GetFilename($req_id) . "." . substr(strrchr($value["name"], '.'), 1)))
							die(t9n("[RU]Не удалось загрузить файл[EN]File uploading failed"));
					}
				}
			if (isset($search)) {
				$a   = "edit_obj";
				$arg = str_replace("%", "%25", $search_str);
				break;
			}
			if (isset($GLOBALS["NOT_NULL"]))
				foreach ($GLOBALS["NOT_NULL"] as $key => $value)
					if (Check_Grant($typ, $key, "WRITE", FALSE)) {
						if ((isset($_REQUEST["t$key"]) ? strlen($_REQUEST["t$key"]) : FALSE) || (isset($_REQUEST["NEW_$key"]) ? strlen($_REQUEST["NEW_$key"]) : FALSE) || (isset($GLOBALS["ARR_typs"][$key]) && ($GLOBALS["REQS"][$key] != 0)) || isset($_REQUEST["copybtn"]))
							continue;
						if (!isset($GLOBALS["warning"]))
							$GLOBALS["warning"] = "";
						$GLOBALS["warning"] .= t9n("[RU]Необходимо заполнить реквизиты, выделенные красным[EN]The attributes marked red are mandatory") . "!<br>";
						$next_act = "";
						break;
					}
			if (isset($GLOBALS["warning"])) {
				$a = "edit_obj";
				$arg .= (isset($_REQUEST["tab"]) ? "tab=" . (int) $_REQUEST["tab"] : "") . "&warning=" . $GLOBALS["warning"];
				$obj = $id;
			} else {
				$arg .= "F_U=$up&F_I=$id";
				$a   = "object";
				$obj = $id;
				$id  = $typ;
			}
			break;
		case "_m_move":
			if ($id == 0)
				die(t9n("[RU]Неверный id: $id[EN]Wrong id: $id"));
			Check_Grant($id);
			$result = Exec_sql("SELECT a.up, a.t, up.t ut, a.ord, target.t tt, COALESCE(MAX(reqs.ord)+1,1) new_ord FROM $z a, $z up, $z target, $z reqs WHERE up.id=a.up AND a.id=$id AND target.id=$up AND reqs.up=$up", "Get Obj to move");
			if ($row = mysqli_fetch_array($result)) {
				$arg = "moved&";
				if ($up != 1) {
					Check_Grant($up, $row["t"]);
					$arg .= "&F_U=$up";
					$ord = $row["new_ord"];
				} elseif (Grant_1level($row["t"]) != "WRITE")
					die(t9n("[RU]У вас нет прав на создание объектов этого типа.[EN]You don't have permission to create this type of object."));
				else
					$ord = 1;
				if ($row["up"] == 0)
					exit("Cannot update meta-data");
				if ($row["ut"] != $row["tt"])
					exit("Types mismatch " . $row["t"] . "!=" . $row["tt"]);
				if ($row["up"] != $up) {
					Exec_sql("UPDATE $z SET ord=$ord, up=$up WHERE id=$id", "Move Obj");
					Exec_sql("UPDATE $z SET ord=ord-1 WHERE up=" . $row["up"] . " AND t=" . $row["t"] . " AND ord>" . $row["ord"], "Move peers up");
				}
			} else
				exit("No such record");
			$a = "object";
			break;
		case "_m_del":
			if ($id == 0)
				my_die(t9n("[RU]Неверный id: $id[EN]Wrong id: $id"));
			Check_Grant($id);
			if ($id == $GLOBALS["GLOBAL_VARS"]["user_id"])
				my_die(t9n("[RU]Нельзя удалить себя как пользователя[EN]The user is not able to delete himself, sorry"));
			$refs = exec_sql("SELECT count(r.id), obj.up, obj.ord, obj.t, par.up pup FROM $z obj " . "LEFT JOIN $z r ON r.t=obj.id JOIN $z par ON par.id=obj.up WHERE obj.id=$id", "Get Refs to the Object");
			if ($row = mysqli_fetch_array($refs)) {
				if ($row["pup"] == 0)
					my_die(t9n("[RU]Нельзя удалить метаданные (реквизит $id типа [EN]You can't delete metadata (the $id type" . $row["up"] . ")!"));
				if ($row[0] > 0)
					my_die(t9n("[RU]Нельзя удалить объект, на который существует ссылки (всего: [EN]You can't delete an object that has links to it (total:") . $row[0] . ")!");
				if ($row["up"] > 1) {
					$arg = "F_U=" . $row["up"];
					Exec_sql("UPDATE $z SET ord=ord-1 WHERE up=" . $row["up"] . " AND t=" . $row["t"] . " AND ord>" . $row["ord"], "Move peers");
				}
				BatchDelete($id);
				$obj = $id;
				$id  = $row["t"];
				$a   = "object";
			} else
				die(t9n("[RU]Объект не найден[EN]Object not found"));
			BatchDelete("");
			$a = "object";
			break;
		case "_m_new":
			if ($up == 0)
				my_die(t9n("[RU]Недопустимые данные: up=0. Установите значение=1 для независимых объектов.[EN]Data is invalid: up=0. Set up=1 for independent objects."));
			$data_set = Exec_sql("SELECT obj.t, obj.ord, req.id FROM $z obj LEFT JOIN $z req ON req.up=$id WHERE obj.id=$id AND obj.up=0", "Check Obj type&reqs");
			if ($row = mysqli_fetch_array($data_set)) {
				$val = isset($_REQUEST["t$id"]) ? $_REQUEST["t$id"] : "";
				if (($GLOBALS["basics"][$row["t"]] == "REPORT_COLUMN") && ((int) $val != 0))
					CheckRepColGranted($val);
				$base_typ = $row["t"];
				$has_reqs = strlen($row["id"]);
				$unique   = $row["ord"];
				$ord      = 1;
				if ($up != 1) {
					if ($row = mysqli_fetch_array(Exec_sql("SELECT up FROM $z WHERE id=$up", "Check the object "))) {
						if ($row[0] == 0)
							my_die(t9n("[RU]Родительский объект $up - метаданные.[EN] The parent object $up is metadata."));
					} else
						my_die(t9n("[RU]Родительский объект $up не найден.[EN]The parent object $up not found."));
					Check_Grant($up, $id);
					$ord = Calc_Order($up, $id);
				} elseif (Grant_1level($id) != "WRITE")
					die(t9n("[RU]У вас нет прав на создание объектов этого типа.[EN]You don't have permission to create this type of object"));
				if ($val == "") {
					if ($GLOBALS["REV_BT"][$base_typ] == "NUMBER") {
						$data_set = Exec_sql("SELECT MAX(CAST(val AS UNSIGNED)) val FROM $z WHERE t=$id AND up=$up", "Get max Val of numeric Obj");
						$max_val  = 0;
						if ($row = mysqli_fetch_array($data_set))
							if ($row[0] > 0)
								$max_val = $row[0];
						$data_set = Exec_sql("SELECT id FROM $z obj WHERE t=$id AND val=$max_val AND up=$up AND NOT EXISTS(SELECT * FROM $z reqs WHERE up=obj.id)", "Get 'empty' numeric Obj");
						if ($row = mysqli_fetch_array($data_set)) {
							$id = $row[0];
							$a  = "edit_obj";
							break;
						} else
							$val = $max_val + 1;
					} elseif ($GLOBALS["REV_BT"][$base_typ] == "DATE")
						$val = Format_Val($base_typ, date("d", time() + $GLOBALS["tzone"]));
					elseif ($GLOBALS["REV_BT"][$base_typ] == "DATETIME")
						$val = time();
					elseif ($GLOBALS["REV_BT"][$base_typ] == "SIGNED")
						$val = 1;
					else
						$val = $ord;
				} else
					$val = Format_Val($base_typ, $val);
				if ($unique && !isset($max_val))
					if ($row = mysqli_fetch_array(Exec_sql("SELECT id FROM $z WHERE t=$id AND val='" . addslashes($val) . "' AND up=$up LIMIT 1", "Check Obj's uniquity")))
						if (strlen($row[0])) {
							$msg = "<b>" . Format_Val_View($base_typ, $val) . "</b> " . t9n("[RU]уже существует! Перейти к[EN]already exists. Go to") . " <a href=\"/$z/edit_obj/" . $row[0] . "\">" . Format_Val_View($base_typ, $val) . "</>";
							if (isset($dumpAPI))
								api_dump(json_encode(array(
									"error" => $msg
								)), "_m_new.json");
							else
								die($msg);
						}
			} else
				die(t9n("[RU]Проверка типа неуспешна[EN]Type check failed"));
			$i = Insert($up, $ord, $id, $val, "Add Object");
			foreach ($_REQUEST as $key => $value)
				if ($key != "t$id") {
					$t = substr($key, 1);
					if ((substr($key, 0, 1) != "t") || ($t == 0)) {
						if (substr($key, 0, 7) == "SEARCH_")
							$arg .= "$key=$value&";
						continue;
					}
					if (!isset($GLOBALS["REQ_TYPS"][$t]))
						Get_Current_Values($i, $id);
					$v = Format_Val($t, BuiltIn($value));
					Check_Grant($i, $t);
					if (strlen($value) != 0) {
						if (isset($GLOBALS["REF_typs"][$t])) {
							if ((int) $v == 0)
								my_die(t9n("[RU]Неверный тип объекта ID=$v [EN]Invalid object type ID=$v"));
							$v = (int) $v;
							if ($row = mysqli_fetch_array(Exec_sql("SELECT val FROM $z WHERE id=$v AND t=" . $GLOBALS["REF_typs"][$t], "Check Ref's req Type"))) {
								Check_Val_granted($GLOBALS["REF_typs"][$t], $row["val"]);
								Insert($i, 1, $v, "$t", "Insert new Ref req");
							} else
								my_die(t9n("[RU]Неверный тип объекта с ID=$v или объект не найден [EN]Invalid object type with ID=$v or the object was not found"));
						} elseif ($t == PASSWORD)
							Insert($i, 1, $t, sha1(Salt($val, $v)), "Insert a first time password");
						else
							Insert($i, 1, $t, $v, "Insert new non-empty req");
					}
				}
			foreach ($_FILES as $key => $value)
				if (strlen($value["name"]) > 0) {
					$t = substr($key, 1);
					if ((substr($key, 0, 1) != "t") || ($t == 0))
						continue;
					if (Check_Grant($i, $t)) {
						BlackList(substr(strrchr($value["name"], '.'), 1));
						$req_id = Insert($i, 1, $t, $value["name"], "Insert new Filename");
						if (!file_exists(UPLOAD_DIR))
							mkdir(UPLOAD_DIR);
						$subdir = GetSubdir($req_id);
						if (!file_exists($subdir))
							@mkdir($subdir);
						if (!move_uploaded_file($value['tmp_name'], $subdir . "/" . GetFilename($req_id) . "." . substr(strrchr($value["name"], '.'), 1)))
							die(t9n("[RU]Не удалось загрузить файл[EN]File uploading failed"));
					}
				}
			if ($has_reqs) {
				$a   = "edit_obj";
				$id  = $i;
				$arg = "new1&$arg";
			} else {
				$a = "object";
				if ($up != 1)
					$arg = "F_U=$up";
			}
			$obj = $i;
			break;
		case "_d_req":
			if (($id == 0) || ($t == 0))
				my_die(t9n("[RU]Неверный реквизит ($t) или ID ($id)[EN] Invalid requisite($t) or ID ($id)"));
			if ($row = mysqli_fetch_array(Exec_sql("SELECT $z.up objup, new.t, $z.val, new.id, new.up FROM $z LEFT JOIN $z new ON new.id=$t WHERE $z.id=$id", "Check the new req"))) {
				if (($row["id"] == 0) || ($row["up"] != 0))
					my_die(t9n("[RU]Неверный реквизит $t [EN]Invalid requisite($t)"));
				if ($row["objup"] != 0)
					my_die(t9n("[RU]Некорректный тип $id - " . $row["val"] . " (это не метаданные)[EN]"));
				if ($row["t"] == $t)
					my_die(t9n("[RU]Некорректный тип $t - это базовый тип[EN]Invalid type $t is the base type"));
			} else
				my_die(t9n("[RU]Не найден тип $id [EN]$id type not found"));
			Insert($id, Get_Ord($id), $t, "", "Add Req");
			$obj = $id;
			break;
		case "_d_save":
			if ($val == "")
				my_die(t9n("[RU]Неверный тип ($val) [EN]Invalid type ($val)"));
			if ($row = mysqli_fetch_array(Exec_sql("SELECT $z.t, $z.val, $z.ord FROM $z LEFT JOIN $z dup ON dup.id!=$id AND dup.val='" . addslashes($val) . "' AND dup.t=$t WHERE $z.id=$id AND dup.id IS NULL", "Get Object and check duplicates"))) {
				if (($row["t"] != 0) && ($t == 0))
					my_die(t9n("[RU]Неверный базовый тип ($t) [EN]Invalid base type ($t)"));
				if (($row["t"] != $t) || ($row["val"] != $val) || ($row["ord"] != $unique))
					Exec_sql("UPDATE $z SET t=$t, val='" . addslashes($val) . "', ord='$unique' WHERE id=$id", "Change typ");
			} else
				my_die(t9n("[RU]Тип $val с базовым типом " . $GLOBALS["REV_BT"][$t] . " уже существует. [EN]The $val type with the base type " . $GLOBALS["REV_BT"][$t] . " already exists."));
			$obj = $id;
			break;
		case "_d_alias":
			if (strpos($val, ":") !== false)
				my_die(t9n("[RU]Недопустимый символ &laquo;:&raquo; в псевдониме $val [EN] Invalid character &laquo;:&raquo; in the alias $val"));
			if ($row = mysqli_fetch_array(Exec_sql("SELECT $z.val, par.up, $z.up myup FROM $z, $z par WHERE $z.id=$id AND par.id=$z.t", "Get Ref alias"))) {
				if ($row["up"] != 0)
					my_die(t9n("[RU]Ошибка подчиненности объекта ссылки [EN]Error in subordination of the link object"));
				$up    = $row["myup"];
				$alias = explode(ALIAS_DEF, $row["val"]);
				if (isset($alias[1])) {
					if (mb_strlen($alias[1]) > mb_strpos($alias[1], ":") + 1)
						$alias[1] = mb_substr($alias[1], mb_strpos($alias[1], ":") + 1);
					else
						$alias[1] = "";
					if ($val != "")
						$alias[1] = ALIAS_DEF . $val . ":" . $alias[1];
					Exec_sql("UPDATE $z SET val='" . implode("", $alias) . "' WHERE id=$id", "Update alias");
				} elseif ($val != "")
					Exec_sql("UPDATE $z SET val=CONCAT(val,'" . ALIAS_DEF . addslashes($val) . ":') WHERE id=$id", "Set alias");
			} else
				my_die(t9n("[RU]Тип $id не найден [EN]Type $id not found"));
			$id = $obj = $up;
			break;
		case "_d_new":
			if ($val == "")
				my_die(t9n("[RU]Пустой тип[EN]Empty type"));
			if (!isset($_REQUEST["t"]))
				my_die(t9n("[RU]Не задан базовый тип[EN]Base type is not set"));
			if ($_REQUEST["t"] == "")
				my_die(t9n("[RU]Не задан базовый тип[EN]Base type is not set"));
			if (!$row = mysqli_fetch_array(Exec_sql("SELECT id FROM $z WHERE val='" . addslashes($val) . "' AND t=$t AND id!=t", "Check Typ presence")))
				$obj = Insert(0, $unique, $t, $val, "Create Typ");
			else
				my_die(t9n("[RU]Тип $val уже существует![EN]The Type $val already exists!"));
			break;
		case "_d_ref":
			if ($id == 0)
				my_die(t9n("[RU]Неверная ссылка ($id) [EN]Invalid link ($id)"));
			if ($row = mysqli_fetch_array(Exec_sql("SELECT up, t FROM $z WHERE id=$id", "Check the new ref"))) {
				if (($row["up"] != 0) || ($row["t"] == $id))
					my_die(t9n("[RU]Неверный тип $id - [EN]Invalid $id type -" . $row["val"]));
			} else
				my_die(t9n("[RU]Не найден тип $id [EN]$Id type not found"));
			$obj = Insert(0, 0, $id, "", "Create Ref");
			break;
		case "_d_null":
		case "_d_not_null":
			$result = Exec_sql("SELECT obj.id FROM $z req LEFT JOIN $z obj ON obj.id=req.up WHERE req.id=$id and obj.up=0", "Check the req and obj");
			if ($row = mysqli_fetch_array($result))
				Exec_sql("UPDATE $z SET val=CASE WHEN val LIKE '%" . NOT_NULL_MASK . "%' THEN REPLACE(val, '" . NOT_NULL_MASK . "', '') ELSE CONCAT(val, '" . NOT_NULL_MASK . "') END WHERE id=$id", "Switch NULL-able");
			else
				my_die(t9n("[RU]Неверный реквизит $id [EN]Invalid requisite $id "));
			$id = $obj = $row["id"];
			break;
		case "_d_attrs":
			if (isset($_REQUEST["alias"]))
				if (strlen($_REQUEST["alias"]))
					$val = ALIAS_DEF . $_REQUEST["alias"] . ":$val";
			if (isset($_REQUEST["set_null"]))
				$val = NOT_NULL_MASK . $val;
			Update_Val($id, $val);
			$id = $obj = $up;
			break;
		case "_d_up":
			$result = Exec_sql("SELECT obj.up, obj.ord, max(peers.ord) new_ord FROM $z obj LEFT JOIN $z peers ON peers.up=obj.up AND peers.ord<obj.ord WHERE obj.id=$id", "Get new Order");
			if ($row = mysqli_fetch_array($result)) {
				$id = $row["up"];
				if ($row["new_ord"] > 0)
					Exec_sql("UPDATE $z SET ord=(CASE WHEN ord=" . $row["ord"] . " THEN " . $row["new_ord"] . " WHEN ord=" . $row["new_ord"] . " THEN " . $row["ord"] . " END) WHERE up=$id AND (ord=" . $row["ord"] . " OR ord=" . $row["new_ord"] . ")", "Change order");
				$obj = $id;
			} else
				my_die(t9n("[RU]Не найден id=$id [EN] Id=$id not found"));
			break;
		case "_d_del":
			$data_set = Exec_sql("SELECT COUNT(id) FROM $z WHERE t=$id", "Check, if the Typ is being used");
			if ($row = mysqli_fetch_array($data_set))
				if ($row[0] > 0)
					die(t9n("[RU]Нельзя удалить тип при наличии его экземпляров (всего: " . "[EN]Cannot delete the Type in case there are objects of this type (total objects: ") . $row[0] . ")!");
			$sql = "SELECT reqs.id FROM $z, $z reqs WHERE $z.t=" . REP_COLS . " AND $z.val=reqs.id AND (reqs.up=$id OR reqs.id=$id) LIMIT 1";
			if ($row = mysqli_fetch_array(Exec_sql($sql, "Check, if the Reqs are being used in Reports")))
				my_die(t9n("[RU]Тип или его реквизиты используются в <a href=\"/$z/object/" . REPORT . "/?F_" . REP_COLS . "=" . $row["id"] . "\">отчетах</a>" . "[EN]The type or its requisites are used in <a href=\"/$z/object/" . REPORT . "/?F_" . REP_COLS . "=" . $row["id"] . "\">reports</a>"));
			$sql = "SELECT objs.t, objs.val FROM $z, $z r, $z objs WHERE r.t=" . ROLE . " AND r.up=1 AND objs.up=r.id AND objs.val=$z.id AND ($z.up=$id OR $z.id=$id) LIMIT 1";
			if ($row = mysqli_fetch_array(Exec_sql($sql, "Check, if the Reqs are being used in Roles")))
				die(t9n("[RU]Тип или его реквизиты используются в <a href=\"/$z/object/" . ROLE . "/?F_" . $row["t"] . "=" . $row["val"] . "\">ролях</a>!).[EN]The type or its requisites are used in <a href=\"/$z/object/" . ROLE . "/?F_" . $row["t"] . "=" . $row["val"] . "\">roles</a>!"));
			Delete($id);
			break;
		case "_d_del_req":
			$data_set = Exec_sql("SELECT def.up, def.t typ, def.ord, r.t, r.val FROM $z def, $z r WHERE def.id=$id AND r.id=def.t", "Get Req's typ");
			if ($row = mysqli_fetch_array($data_set)) {
				$myord = $row["ord"];
				$myup  = $row["up"];
				if (isset($GLOBALS["basics"][$row["t"]]))
					$sql = "SELECT count(1) FROM $z obj, $z req WHERE obj.t=$myup AND (req.t=" . $row["typ"] . " OR req.t=$id) AND req.up=obj.id";
				else
					$sql = "SELECT count(1) FROM $z obj, $z req WHERE obj.t=$myup AND req.up=obj.id AND req.val='$id'";
				if ($row = mysqli_fetch_array(Exec_sql($sql, "Check, if the Req is being used"))) {
					if ($row[0] > 0)
						my_die(t9n("[RU]Нельзя удалить реквизит у типа при наличии этого реквизита у экземпляров (всего: " . "[EN]Cannot delete a requisite if there are records of this type (total records: ") . $row[0] . ")!");
					$sql = "SELECT " . REP_COLS . " t FROM $z WHERE t=" . REP_COLS . " AND val='$id' " . "UNION SELECT reqs.t FROM $z, $z reqs WHERE $z.t=" . ROLE . " AND $z.up=1 AND reqs.up=$z.id AND reqs.val='$id' LIMIT 1";
					if ($row = mysqli_fetch_array(Exec_sql($sql, "Check, if the Req is being used in Reports or Roles")))
						my_die(t9n("[RU]Этот реквизит используется в <a href=\"/$z/object/" . REPORT . "/?F_" . REP_COLS . "=$id\">отчетах</a> или <a href=\"/$z/object/" . ROLE . "/?F_116=$id\">ролях</a>!" . "[EN]The requisite is used in <a href=\"/$z/object/" . REPORT . "/?F_" . REP_COLS . "=$id\">reports</a> or <a href=\"/$z/object/" . ROLE . "/?F_116=$id\">roles</a>!"));
					Delete($id);
					Exec_sql("UPDATE $z SET ord=ord-1 WHERE up=$myup AND ord>$myord", "Move up other Reqs");
					$id = $obj = $myup;
				}
			}
			break;
		case "xsrf":
			api_dump(json_encode(array(
				"_xsrf" => $GLOBALS["GLOBAL_VARS"]["xsrf"],
				"token" => $GLOBALS["GLOBAL_VARS"]["token"],
				"user" => $GLOBALS["GLOBAL_VARS"]["user"],
				"role" => $GLOBALS["GLOBAL_VARS"]["role"],
				"id" => $GLOBALS["GLOBAL_VARS"]["user_id"],
				"msg" => ""
			)), "login.json");
			break;
		case "connect":
			if ($id == 0)
				my_die(t9n("[RU]Неверный id ($id) [EN]Invalid id ($id)"));
			$sql = "SELECT val FROM $z WHERE up=$id AND t=" . CONNECT;
			if ($row = mysqli_fetch_array(Exec_sql($sql, "Get the connector"))) {
				trace("Got connector: " . $row["val"]);
				foreach ($_GET as $k => $v)
					$url .= "&$k=$v";
				$url = $row["val"] . (strpos($row["val"], "?") ? "&" : "?") . substr($url, 1);
				trace("url: $url");
				$ch = curl_init();
				curl_setopt($ch, CURLOPT_HEADER, 0);
				curl_setopt($ch, CURLOPT_HTTPHEADER, array(
					"User-Agent: Integral"
				));
				curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
				curl_setopt($ch, CURLOPT_URL, $url);
				$val = curl_exec($ch);
				if (curl_errno($ch)) {
					$val         = curl_errno($ch) . ": $val";
					$file_failed = true;
				}
				curl_close($ch);
				die("$val");
			}
			break;
		default:
			$user = $GLOBALS["GLOBAL_VARS"]["user"];
			$f_u  = isset($_REQUEST["F_U"]) ? (int) $_REQUEST["F_U"] : "1";
			if (isset($_GET["warning"]))
				$GLOBALS["warning"] = $_REQUEST["warning"];
			if ($a == "report") {
				unset($blocks);
				$text = Get_file("report.html");
				if (isset($_REQUEST["obj"]))
					if ($_REQUEST["obj"] != 0)
						$obj = (int) $_REQUEST["obj"];
			} elseif ($a == "dir_admin") {
				Make_tree(Get_file("dir_admin.html"), "");
				die(Parse_block(""));
			} else
				$text = Get_file("main.html");
			if (isset($_REQUEST["TIME"]))
				set_time_limit(3600);
			Make_tree($text, "&main");
			$html   = Parse_block("&main");
			$time   = substr(microtime(TRUE) - $time_start, 0, 6);
			$stime  = round($GLOBALS["sql_time"], 4);
			$scount = $GLOBALS["sqls"];
			$tzone  = $GLOBALS["tzone"];
			if (isApi())
				die(json_encode($GLOBALS["GLOBAL_VARS"]["api"], JSON_HEX_QUOT));
			if (($z == $GLOBALS["GLOBAL_VARS"]["user"]) || ($GLOBALS["GLOBAL_VARS"]["user"] == "admin"))
				echo str_replace("<!--Elapsed-->", "<font  size=\"-1\"><a href=\"/$z/dir_admin\">[$user]</a> $scount / $stime / $time ($tzone)</font>", $html);
			else
				echo str_replace("<!--Elapsed-->", "<font size=\"-1\">[$user] $scount / $stime / $time ($tzone)</font>", $html);
			myexit();
	}
} elseif (isApi())
	my_die(t9n("[RU]Ошибка проверки токена $z[EN]Not logged into $z"));
else
	die('Seems, no such DB exists - $z');
if (isset($_REQUEST["message"]))
	die("<h3>" . $_REQUEST["message"] . "</h3>");
if ($next_act == "nul")
	die('{"id":"' . $id . '", "obj":"' . $obj . '", "a":"' . $a . '", "args":"' . $arg . '"}');
elseif ($next_act == "")
	$next_act = $a;
else
	$next_act = str_replace("[id]", isset($obj) ? $obj : "", $next_act);
if (substr($a, 0, 3) == "_d_")
	$arg .= "ext";
if (isApi())
	api_dump(json_encode(array(
		"id" => $id,
		"obj" => $obj,
		"next_act" => "$next_act",
		"args" => $arg,
		"warnings" => (isset($GLOBALS["warning"]) ? $GLOBALS["warning"] : "")
	), JSON_HEX_QUOT));
header("Location: /$z/$next_act/$id" . (strlen($arg) ? "/?$arg" : "") . (isset($obj) ? "#$obj" : ""));
