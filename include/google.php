<?php

$params = array(
	'client_id'     => '<>',
	'client_secret' => '<>',
	'redirect_uri'  => 'https://'.$_SERVER["SERVER_NAME"].'/auth.asp',
	'grant_type'    => 'authorization_code',
	'code'          => $_GET['code']
);	

$ch = curl_init('https://accounts.google.com/o/oauth2/token');
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, $params); 
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_HEADER, false);
$data = curl_exec($ch);
curl_close($ch);	

$data = json_decode($data, true);
if (!empty($data['access_token'])) {
	// Got the token, retrieve the user data
	$params = array(
		'access_token' => $data['access_token'],
		'id_token'     => $data['id_token'],
		'token_type'   => 'Bearer',
		'expires_in'   => 3599
	);

	$info = file_get_contents('https://www.googleapis.com/oauth2/v1/userinfo?' . urldecode(http_build_query($params)));
	$info = json_decode($info, true);
	print_r($info);
	print_r("<br>\n<br>\nparams");
	print_r($params);
	die("\n<br>State:".$_GET['state']);
    $z = "my";
    include "connection.php";
    
    if($row = mysqli_fetch_array(Exec_sql("SELECT id FROM $z WHERE val='".$info["id"]."' AND t=".USER, "Check user presence")))
	{
	    $uid = $row["id"];
        $z = "g$uid";
    	if($row = mysqli_fetch_array(Exec_sql("SELECT token.id tok, xsrf.id xsrf, act.id act
                                    FROM $z user LEFT JOIN $z token ON token.up=user.id AND token.t=".TOKEN
                                            ." LEFT JOIN $z xsrf ON xsrf.up=user.id AND xsrf.t=".XSRF
                                            ." LEFT JOIN $z act ON act.up=user.id AND act.t=".ACTIVITY
                                    ." WHERE user.val='$z' AND user.t=".USER
                				, "Get google user")))
		{
			$token = md5(microtime(TRUE));
			$xsrf = xsrf($token, $z);
			setcookie($z, $token, time() + 2592000*12, "/"); # 30*12 days
			if($row["tok"])
				Update_Val($row["tok"], $token);
			else
				Insert($uid, 1, TOKEN, $token, "Save token G");
			if($row["xsrf"])
				Update_Val($row["xsrf"], $xsrf);
			else
				Insert($uid, 1, XSRF, $xsrf, "Save xsrf G");
			if($row["act"])
				Update_Val($row["act"], microtime(TRUE), FALSE);
			else
				Insert($uid, 1, ACTIVITY, microtime(TRUE), "Save activity time G");
			header("Location: /$z");
		}
	}
    else
    {
        # Insert new user and its data into CRM
        $id = Insert(1, 0, USER, $info["id"], "Insert new user G");
        Insert($id, 1, EMAIL, $info["email"], "Insert email G");
        Insert($id, 1, 164, "115", "Insert User role link G");
        # My CRM's specific data
        Insert($id, 1, 156, date("Ymd"), "Insert date G");
		if(strlen($info["name"]))
            Insert($id, 1, 33, $info["name"], "Insert name G");
        Insert($id, 1, 39, $info["locale"]." ".$info["picture"], "Insert Template G");
        
		$z = "g$id"; #	Switch the DB to the user's one - for Googel it's "g" + user Id
		if(strtolower($info["locale"])==="ru") # The template might be EN or RU
		{
			$template = "ru";
		    $locale = "[RU]";
		}
		else
        {
			$template = "en";
		    $locale = "[EN]";
        }
		Exec_sql("CREATE TABLE $z LIKE $template", "Create the initial table G");
		Exec_sql("INSERT INTO $z SELECT * FROM $template", "Fill in the table by template G");

		# Insert new user and its data into his DB
		$id = Insert(1, 0, USER, $z, "Insert new user G");
		if(strlen($info["email"]))
			Insert($id, 1, EMAIL, $info["email"], "Insert email G");
		if(strlen($info["name"]))
			Insert($id, 1, 33, $info["name"], "Insert name G");
		Insert($id, 1, 145, "115", "Insert Admin role link G");

		$token = md5(microtime(TRUE));
		$xsrf = xsrf($token, $u);
		Insert($id, 1, TOKEN, $token, "Save token G");
		Insert($id, 1, XSRF, $xsrf, "Save xsrf G");
		Insert($id, 1, ACTIVITY, microtime(TRUE), "Save activity time G");
		setcookie($z, $token, time() + 2592000*12, "/"); # 30*12 days
		
		# Create folders for files and templates
		exec("cp -r templates/custom/$template templates/custom/$z");
		exec("cp -r download/$template download/$z");
		
        mysendmail("alexey.p.semenov@gmail.com", "Registration from ".$_SERVER["SERVER_NAME"].": $z"
        	, "Name: ".$info["name"]."\nEmail: ".$info["email"]."\nhttps://".$_SERVER["SERVER_NAME"]."/$z/object/".USER);
        # Send the confirmation letter to the new user
        mysendmail($info["email"], t9n("[RU]Регистрация на сервисе [EN]Registration from ").$_SERVER["SERVER_NAME"].": $z"
        	, t9n("[RU]\r\nЗдравствуйте ".(isset($info["name"])?", ".$info["name"]:"!")."[EN]Hello ".(isset($info["name"])?" ".$info["name"]:"my friend!"))."\r\n\r\n"
        	    .t9n("[RU]Для вас создана новая база Интеграл, где вы можете обучаться и творить, поэтому не стесняйтесь перейти по ссылке и добавить её в Избранное:\r\n"
        	        ."[EN]You've got a new IdeaV database for your personal or business use, click here to access it and don't forget o add it to Favorites:\r\n")
        	    ."https://".$_SERVER["SERVER_NAME"]."/$z"
        		."\r\n\r\n".t9n("[RU]С уважением,\r\nКоманда Интеграл[EN]Best regards,\r\nIdeaV team")
        		."\r\n\r\n".t9n("[RU]Если вы не хотите получать от нас писем, связанных с регистрацией $z, вы можете отписаться от оповещений:"
                                ."\r\nhttps://".$_SERVER["SERVER_NAME"]."/register.php?locale=RU&optdb=$z&optout=".$info["email"]
        	                ."[EN]In case you do not want to receive messages regarding your registration, unsubscribe here:"
                                ."\r\nhttps://".$_SERVER["SERVER_NAME"]."/register.php?locale=EN&optdb=$z&optout=".$info["email"])
        	, "No Reply", "abc@".$_SERVER["SERVER_NAME"]);
        	
		header("Location: /$z");
    }
}
die();
?>
