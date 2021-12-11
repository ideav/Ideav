<?php
function pwd_reset($u)
{
	global $z;
	# Redirect to another host in case we have one
	if(isset($_POST["host"]) || isset($_GET["host"]))
		$host = isset($_POST["host"]) ? $_POST["host"] : $_GET["host"];
	$data_set = Exec_sql("SELECT u.id, email.val, pwd.id pwd, phone.val phone, pwd.val old
						FROM $z u LEFT JOIN $z email ON email.up=u.id AND email.t=".EMAIL
						." LEFT JOIN $z pwd ON pwd.up=u.id AND pwd.t=".PASSWORD
						." LEFT JOIN $z phone ON phone.up=u.id AND phone.t=".PHONE
						." WHERE u.val='$u' AND u.t=".USER
					, "Get user's reqs");
#print_r($GLOBALS); die();
	if($row = mysqli_fetch_array($data_set))
	{
		$pwd = substr(md5(mt_rand()), 0, 6);	# Create the password
		$sha = sha1(Salt($u, $pwd));	# Make the password hash
		if(preg_match(MAIL_MASK, $row["val"]))
		{
			if($row["pwd"])  # There is a password already
			{
				mysendmail($row["val"], t9n("[RU]Сброс пароля[EN]Password reset")
					, t9n("[RU]Ваш новый пароль [EN]Your new password ")."$pwd\r\n"
					    .t9n("[RU]Для подтверждения его перейдите по ссылке:[EN]Confirm it here before use")
					    .": https://".$_SERVER["SERVER_NAME"]."/$z/confirm?u=".urlencode($u)."&p=$sha&o=".$row["old"]
				        .(isset($host) ? "&host=".rawurlencode($host) : "")
					, "Noreply@".$_SERVER["SERVER_NAME"]);
				$reason = "MAIL";
			}
			else	# No password yet, thus no confirmation required
			{
				mysendmail($row["val"], t9n("[RU]Ваш новый пароль[EN]Your new password")
					, t9n("[RU]Ваш новый пароль [EN]Your new password ")."$pwd\r\n".(isset($host) ? $host : "https://".$_SERVER["SERVER_NAME"]."/$z/?u=".urlencode($u))
					, "Noreply@".$_SERVER["SERVER_NAME"]);
				Insert($row["id"], 1, PASSWORD, $sha, "Create the password record");
				$reason = "NEW_PWD";
			}
		}
		elseif(strlen($row["phone"]))
		{
			$phone = preg_replace('![^0-9]+!', '', $row["phone"]);
			if(strlen($phone) == 11)
				if((substr($phone, 0, 2) == '89') || (substr($phone, 0, 2) == '79'))
				{
					if($row["pwd"])  # There is a password already
						Update_Val($row["pwd"], $sha, FALSE);
					else
						Insert($row["id"], 1, PASSWORD, $sha, "Create the password record");
#							$rep = file_get_contents(SMS_OP."&sadr=".SMS_SADR."&dadr=$phone&text=Пароль:%20$pwd");
					$GLOBALS["GLOBAL_VARS"]["code"] = file_get_contents(SMS_OP."&sadr=".SMS_SADR."&dadr=$phone&text="
					       .t9n("[RU]Пароль[EN]Password").":%20$pwd"
					       .t9n("[RU]%20Рекомендуем%20сменить%20пароль%20после%20авторизации[EN]%20We%20recommend%20to%20change%20it%20upon%20first%20login"));
#							$rep = file_get_contents(SMS_OP."&sadr=".SMS_SADR."&dadr=$phone&text=$pwd");
					$GLOBALS["GLOBAL_VARS"]["sms"] = substr($phone, 7);
                	$reason = "SMS";
				}
		}
	}
	else
	    $reason = "WRONG_CONT";
	if(isset($host))
		header("Location: $host#$reason");
	else
    	login($z, $u, $reason);
}
$GLOBALS["GLOBAL_VARS"]["uri"] = isset($_REQUEST["uri"]) ? htmlentities($_REQUEST["uri"]) : "/$z";
$u = addslashes(strtolower($_REQUEST["u"]));
if(isset($_REQUEST["reset"]))  # A new user wants to get the password
	pwd_reset($u);
if(isset($_POST["tzone"]))
{
	$GLOBALS["tzone"] = round(((int)$_POST["tzone"] - time() - date("Z"))/1800)*1800; # Round the time zone shift to 30 min
	setcookie("tzone", $GLOBALS["tzone"], time() + COOKIES_EXPIRE, "/"); # 30 days
}
if(isset($_POST["locale"]))
	setcookie($z."_locale", strtoupper($_POST["locale"]) == "EN" ? "EN" : "RU", 0, "/");

$p = $_REQUEST["p"];
$pwd = sha1(Salt($u, $p)); # Add some salt
$data_set = Exec_sql("SELECT u.id, u.val, pwd.id pwd_id, pwd.val pwd, tok.id tok, act.id act, xsrf.id xsrf
						FROM $z pwd, $z u LEFT JOIN $z act ON act.up=u.id AND act.t=".ACTIVITY
							." LEFT JOIN $z tok ON tok.up=u.id AND tok.t=".TOKEN
							." LEFT JOIN $z xsrf ON xsrf.up=u.id AND xsrf.t=".XSRF
						." WHERE u.t=".USER." AND u.val='$u' AND pwd.up=u.id AND pwd.val='$pwd'"
				, "Authenticate user");
if($row = mysqli_fetch_array($data_set))
{
	$GLOBALS["GLOBAL_VARS"]["user"] = $row["val"];
	$GLOBALS["cur_user_id"] = $row["id"];
	if(isset($_REQUEST["change"]))
	{
		$npw1 = isset($_REQUEST["npw1"]) ? $_REQUEST["npw1"] : "";
		$npw2 = isset($_REQUEST["npw2"]) ? $_REQUEST["npw2"] : "";
		
		if(mb_strlen($npw1) < 6)
			$msg .= "<p>".t9n("[RU]Новый пароль должен быть не короче 6 символов[EN]Password must be at lest 6 symbols long")."</p>";
		elseif(sha1($npw1) == $row["pwd"])
			$msg .= "<p>".t9n("[RU]Новый пароль должен отличаться от старого[EN]The new password must differ from the old one")."</p>";
		elseif($npw1 != $npw2)
			$msg .= "<p>".t9n("[RU]Введите новый пароль дважды одинаково[EN]Please input the same password twice")."</p>";
		else
		{
			$npw1 = sha1(Salt($u, $npw1));
			Update_Val($row["pwd_id"], $npw1);
			$msg = t9n("[RU]Пароль успешно изменен[EN]The password has been changed").LOGIN_PAGE;
		}
	}
	$token = md5(microtime(TRUE));
	$xsrf = xsrf($token, $u);
	if($row["tok"])
		Update_Val($row["tok"], $token);
	else
		Insert($row["id"], 1, TOKEN, $token, "Save token");
	if($row["xsrf"])
		Update_Val($row["xsrf"], $xsrf);
	else
		Insert($row["id"], 1, XSRF, $xsrf, "Save xsrf");
	if($row["act"])
		Update_Val($row["act"], microtime(TRUE), FALSE);
	else
		Insert($row["id"], 1, ACTIVITY, microtime(TRUE), "Save activity time");
	if(isset($_REQUEST["save"]))
		setcookie($z, $token, 0, "/");  # The cookie to be deleted upon the session close
	else
		setcookie($z, $token, time() + COOKIES_EXPIRE, "/"); # 30 days
}
elseif((strtolower($u) == "admin") && (sha1($p) == ADMINHASH))
{
	$GLOBALS["GLOBAL_VARS"]["user"] = "admin";
	$xsrf = xsrf(ADMINHASH, "admin");
	$token = adminHash();
	setcookie($z, $token, 0, "/");
}
else
	login($z, $_REQUEST["u"], "wrong");
$GLOBALS["GLOBAL_VARS"]["xsrf"] = $xsrf;
if(isset($dumpAPI))
	api_dump(json_encode(array("_xsrf"=>$xsrf,"token"=>$token,"msg"=>$msg)), "login.json");
if(isset($msg))
	die($msg.BACK_LINK);
if(substr($GLOBALS["GLOBAL_VARS"]["uri"],0,strlen($z)+1) != "/$z")
    $GLOBALS["GLOBAL_VARS"]["uri"] = "/$z";
header("Location: ".$GLOBALS["GLOBAL_VARS"]["uri"]);
die();
?>
