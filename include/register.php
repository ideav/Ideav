<?php
$locale = $_REQUEST["locale"] == "RU" ? "[RU]" : "[EN]"; // Default locale
$msg = "";
$z = "my";
include "connection.php";

# Check if this is a confirmation request
if(isset($_REQUEST["c"]) && isset($_REQUEST["u"]))
{
	$u = strtolower(addslashes($_REQUEST["u"]));
	if($row = mysqli_fetch_array(Exec_sql("SELECT p.id, p.val, template.val template, email.val email, t.id tid, name.val name, phone.val phone
											FROM $z t, $z p, $z u
											LEFT JOIN $z template ON template.up=u.id AND template.t=39
											LEFT JOIN $z email ON email.up=u.id AND email.t=".EMAIL
											." LEFT JOIN $z name ON name.up=u.id AND name.t=33
											LEFT JOIN $z phone ON phone.up=u.id AND phone.t=".PHONE
											." WHERE u.val='$u' AND u.t=".USER." AND p.up=u.id AND p.t=".PASSWORD
												." AND t.up=u.id AND t.t=".TOKEN." AND t.val='".addslashes($_REQUEST["c"])."'"
										, "Check user name & conf code")))
		if($row["id"]) # User found
		{
			# Create the table for this new user
			Exec_sql("DELETE FROM $z WHERE id=".$row["tid"], "Reset token");
			Exec_sql("UPDATE $z SET val='".sha1(Salt($u, $row["val"]))."' WHERE id=".$row["id"], "Update user's password");
			if(in_array($row["template"], array("EN", "RU"))) # List of available templates
				$template = strtolower($row["template"]);
			else
				$template = "en";
			Exec_sql("CREATE TABLE $u LIKE $template", "Create the initial table");
			Exec_sql("INSERT INTO $u SELECT * FROM $template", "Fill in the table by template");

			# Insert new user and its data into his DB
			$z = $u; #	Switch the DB to the user's one
			$id = Insert(1, 0, USER, $u, "Insert new user");
			Insert($id, 1, EMAIL, $row["email"], "Insert email");
			if(strlen($row["name"]))
    			Insert($id, 1, 33, $row["name"], "Insert name");
			if(strlen($row["phone"]))
    			Insert($id, 1, PHONE, $row["phone"], "Insert phone");
			Insert($id, 1, PASSWORD, sha1(Salt($u, $row["val"])), "Insert password");
			Insert($id, 1, 145, "115", "Insert Admin role link");
			
			# Create folders for files and templates
			exec("cp -r templates/custom/$template templates/custom/$u");
			exec("cp -r download/$template download/$u");
			
			header("Location: /$u/login?locale=" . ($locale == "[RU]" ? "RU" : "EN") . "&message=FIRST&u=$u");
        }
        else
			header("Location: /$u/login?locale=" . ($locale == "[RU]" ? "RU" : "EN") . "&message=EXPIRED&u=$u");
	die();
}
elseif(isset($_POST["db"]))
{
    $db = strtolower(htmlspecialchars($_POST["db"]));
    setcookie($db."_locale", ($_REQUEST["locale"] == "RU" ? "RU" : "EN"), 0, "/");
    
	if(!checkUserName($db))
		$msg .= t9n("[RU]Недопустимое имя пользователя ".$db." (от 3 до 15 латинских букв и цифр)"
		        ."[EN]Please correct the username: ".$db." 3 to 15 digits or latin letters")."<br/><br/>\n";
	if(!preg_match("/.+@.+\..+/i", $_REQUEST["inputEmail"]))
		$msg .= t9n("[RU]Вы ввели неверный email[EN]Please provide a correct email")."<br/><br/>\n";
#	if(strlen($_REQUEST["Phone"])<10)
#		$msg .= "Введенный телефон некорректен<br/><br/>\n";
#	if(strlen($_REQUEST["Phone"]) && (strlen($_REQUEST["Phone"])<10))
#		$msg .= "Введенный телефон некорректен (Вы можете оставить это поле пустым)<br/><br/>\n";
	if(!strlen($_REQUEST["inputPassword"]) || !strlen($_REQUEST["confirmPassword"]))
		$msg .= t9n("[RU]Введен пустой пароль[EN]Please input the password")."<br/><br/>\n";
	elseif(strlen($_REQUEST["inputPassword"]) < 6)
		$msg .= t9n("[RU]Введенный вами пароль слишком короток (менее 6 символов)[EN]The password must be at least 6 characters long")."<br/><br/>\n";
	elseif($_REQUEST["inputPassword"] != $_REQUEST["confirmPassword"])
		$msg .= t9n("[RU]Введенные вами пароли не совпадают[EN]Repeat the same password twice")."<br/><br/>\n";
	if(!strlen($_REQUEST["agree"]))
		$msg .= t9n("[RU]Пожалуйста, поставьте отметку, что ознакомились с&nbsp;Лицензионным соглашением"
		    ."[EN]Please confirm that you have read the&nbsp;<a href=\"offer.html\">papers to protect you")."<br/><br/>\n";
    # Stage one: Inform of the errors, if any, and let him try again
    if(strlen($msg))
    	dieReg($msg);
    
    if($row = mysqli_fetch_array(Exec_sql("SELECT 1 FROM $z WHERE val='$db' AND t=".USER, "Check user name uniquity")))
    	if($row[0])	# Inform of the errors, if any, and let him try again
    		dieReg("Сожалеем, но имя '$db' уже занято.");
    
    # Insert new user and its data into CRM
    $id = Insert(1, 0, USER, $db, "Insert new user");
    Insert($id, 1, EMAIL, $_REQUEST["inputEmail"], "Insert email");
    Insert($id, 1, PASSWORD, $_REQUEST["inputPassword"], "Insert password");
    Insert($id, 1, 164, "115", "Insert User role link");
    # My CRM's specific data
    Insert($id, 1, 156, date("Ymd"), "Insert date");
    if(strlen($_REQUEST["name"]))
        Insert($id, 1, 33, $_REQUEST["name"], "Insert name");
    if(strlen($_REQUEST["phone"]))
        Insert($id, 1, PHONE, $_REQUEST["phone"], "Insert Phone");
    $confirm = md5("xz$db");
    Insert($id, 1, TOKEN, $confirm, "Insert confirmation code");
    if(strlen($_REQUEST["template"]))
        Insert($id, 1, 39, $_REQUEST["template"], "Insert Template");
    
    mysendmail("alexey.p.semenov@gmail.com", "Registration from ".$_SERVER["SERVER_NAME"].": $db"
    	, "Name: ".$_REQUEST["Name"]."\nEmail: ".$_REQUEST["inputEmail"]."\nPhone: ".$_REQUEST["Phone"]."\nhttps://".$_SERVER["SERVER_NAME"]."/$db/object/".USER);
    # Send the confirmation letter to the new user
    mysendmail($_REQUEST["inputEmail"], t9n("[RU]Регистрация на сервисе [EN]Registration from ").$_SERVER["SERVER_NAME"].": $db"
    	, t9n("[RU]\r\nЗдравствуйте".(isset($_REQUEST["Name"])?", ".$_REQUEST["Name"]:"!")."[EN]Hello my friend,")."\r\n\r\n"
    	    .t9n("[RU]Для подтверждения регистрации пройдите по ссылке:\r\nhttps://".$_SERVER["SERVER_NAME"]."/register.php?locale=RU&u=$db&c=$confirm\r\n"
    	        ."[EN]To complete the registration click the following link:\r\nhttps://".$_SERVER["SERVER_NAME"]."/register.php?locale=EN&u=$db&c=$confirm\r\n")
    		.t9n("[RU]или скопируйте её и откройте в Вашем web-браузере.\r\nЭта ссылка действительна в течение трех дней."
    		    ."[EN]or copy its text and open it in your Internet browser.\r\nThe link will expire in 3 days.")
    		."\r\n\r\n".t9n("[RU]С уважением,\r\nКоманда Интеграл[EN]Best regards,\r\nIdeaV team")
    		."\r\n\r\n".t9n("[RU]Если вы не хотите получать от нас писем, связанных с регистрацией $db, вы можете отписаться от оповещений:"
                            ."\r\nhttps://".$_SERVER["SERVER_NAME"]."/register.php?locale=RU&optdb=$db&optout=".$_REQUEST["inputEmail"]
    	                ."[EN]In case you do not want to receive messages regarding your registration, unsubscribe here:"
                            ."\r\nhttps://".$_SERVER["SERVER_NAME"]."/register.php?locale=EN&optdb=$db&optout=".$_REQUEST["inputEmail"])
    	, "No Reply", "abc@".$_SERVER["SERVER_NAME"]);
    
	login($db, "", "toConfirm");

	header("Location: $db/login?locale=" . ($locale == "[RU]" ? "RU" : "EN") . "&message=TOCONFIRM&u=$db");
}
elseif(isset($_GET["optout"]))
    die(t9n("[RU]Вы отписались от рассылки для [EN]You have cancelled the email subscription for ").$_GET["optout"]);
else
    dieReg("Запрос не распознан");
?>
