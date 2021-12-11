<?php
$connection = mysqli_connect(null, "<>", "<>", "ideav", 0, "/cloudsql/idea-v:us-central1:<>") or die("Couldn't connect. ".mysqli_connect_error());
$connection->set_charset("utf8"); 
mysqli_select_db($connection, "ideav") or die("Couldn't select database.");
$result = mysqli_query($connection, "show tables where Tables_in_Ideav='$z'") or die("Неверное имя базы данных $z");

define("ADMINHASH", "<>");
define("SALT", "<>");
define("SMS_SADR", "CoffeeClick");
define("SMS_OP", "http://gateway.api.sc/get/?user=bkintru&pwd=xxx");

function mysendmail($to,$subj,$msg,$from='Integral',$replyto='support@ideav.pro'){
    
	include_once "include/sendmail.php";
    
    global $config;
    $config['smtp_username'] = ''; //$replyto;  // Default reply address
    $config['smtp_port'] = '465'; // Порт работы.
    $config['smtp_host'] =  'ssl://smtp.yandex.ru';  //сервер для отправки почты
    $config['smtp_password'] = '';  //Измените пароль
    $config['smtp_debug'] = true;  //Если Вы хотите видеть сообщения ошибок, укажите true вместо false
    $config['smtp_charset'] = 'utf-8';	//кодировка сообщений. (windows-1251 или utf-8, итд)
    $config['smtp_from'] = $from; // "From" by default

    wlog("===== ".date("d.m.y H:m:s ")."\nTo$to\nSubj:$subj\nMsg:msg");
    $res = "id:".smtpmail($to, $to, $subj, $msg);
    wlog("\nResult: ".($res ? " Ok" : " Failed")."\n=====\n");
    return $res;
}
#mysendmail('drynny@mail.ru','Hi','Test','Intergal','abc@tryjob.ru')
function wlog($text, $mode)
{
    $file = fopen(LOGS_DIR.$GLOBALS["z"]."_$mode.txt", "a+");
    fwrite ($file, date("d/m/Y H:i:s")." $text\n");
    fclose($file);
}

?>
