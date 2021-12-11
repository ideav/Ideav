<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title id="title">Вход в Интеграл</title>
<link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" integrity="sha384-JcKb8q3iqJ61gNV9KGb8thSsNjpSL0n8PARn9HuZOnIxN0hoP+VmmDGMN5t9UJ0Z" crossorigin="anonymous">
<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.1.1/jquery.min.js"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js" integrity="sha384-B4gt1jrGC7Jh4AgTPSdUtOBvfO8shuf57BaghqFfPlYxofvL8/KUEfYiJOMMV+rV" crossorigin="anonymous"></script>
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="viewport" content="width=device-width, initial-scale=1">
</head>
<body style="color:#eeeeee; background-color:#3b8dbd; font-size: 16px;">
<style>
    input, select { margin-bottom:5px; }
    a { cursor: pointer; }
</style>
<input type="hidden" id="tzone" name="tzone">
<div class="container">
<div class="row">
    <div class="col-xs-12 col-sm-3 col-lg-3 col-xl-4"> 
    </div>
    <div id="result" class="col-xs-12 col-sm-6 col-lg-5 col-xl-4">
    </div>
</div>
<div class="row no-gutters">
    <div class="col-xs-12 col-sm-1 col-lg-3 col-xl-4"> 
    </div>
    <div class="col-xs-12 col-sm-10 col-lg-6 col-xl-4">
    <div class="row no-gutters">
        <div class="col-12">
        <table width="100%">
            <tr>
                <td align="left">
                    <a href="/"><img height="32px" style="margin-top: -5px;" src="/i/ld.png?v=3"></a>
                </td>
                <td align="right">
                    <select id="locale" name="locale" class="form-control input-sm" style="width:auto; padding:0;" tabindex="99" onchange="changeLocale(this.value)">
                		<option id="RU" value="RU">RU</option>
                		<option id="EN" value="EN">EN</option>
                    </select>
                </td>
            </tr>
        </table>
        <FORM id="form" method="post" onsubmit="if(isEmpty(db.value,u.value,p.value))event.preventDefault?event.preventDefault():(event.returnValue=false);">
        <INPUT id="loginlocale" TYPE="hidden" NAME="locale" value="RU">
        <center><font style="color:#ffffff; font-size:24px;"><span id="name">ИНТЕГРАЛ</span></font>
        </center>
        <span id="dbname">База:</span><br />
        <INPUT class="form-control" TYPE="text" id="db" NAME="db" SIZE="16" MAXLENGTH="16" autofocus oninput="checkLocale(this.value)">
        <div id="dbs"></div>
        <span id="username">Пользователь:</span><br />
        <INPUT class="form-control" TYPE="text" id="u" NAME="u" SIZE="16" MAXLENGTH="16">
        <span id="password">Пароль:</span><br />
        <INPUT class="form-control" TYPE="password" id="p" NAME="p" SIZE="16" MAXLENGTH="16">
        <center>
        <label style="margin:20px"><INPUT TYPE="checkbox" NAME="save" VALUE="checked" ><span id="remember"> запомнить пароль</span></label>
        <br />
        <INPUT class="btn btn-light btn-secondary-secondary" style="margin-bottom:5px;" TYPE="submit" id="btnsubmit" NAME="submit" VALUE="Войти">
        <INPUT class="btn btn-light btn-secondary-secondary" style="margin-bottom:5px;" TYPE="submit" id="btnreset" NAME="reset" VALUE="Сгенерировать пароль" onclick="p.value=' '">
        <label style="margin:20px"><INPUT TYPE="checkbox" NAME="change" VALUE="checked"  onclick="if(byId('change_pass').style.display=='none') byId('change_pass').style.display='block'; else byId('change_pass').style.display='none';">
        <span id="change">сменить пароль после входа</span></label>
        <div id="change_pass" style="display:none;">
        <span id="newpwd">Новый пароль:</span><br />
        <INPUT class="form-control" TYPE="password" NAME="npw1" SIZE="16" MAXLENGTH="16" id="phnpwd" placeholder="Не менее 6 символов">
        <span id="newpwd2">еще раз:</span><br />
        <INPUT class="form-control" TYPE="password" NAME="npw2" SIZE="16" MAXLENGTH="16" id="phnpwd2" placeholder="Введите пароль ещё раз">
        </div>
        <br />
        <!--
        <script src="//ulogin.ru/js/ulogin.js"></script>
        <div id="uLogin" data-ulogin="display=panel;theme=classic;fields=first_name,last_name;providers=vkontakte,mailru,facebook,yandex,odnoklassniki,google,instagram;hidden=;redirect_uri=https%3A%2F%2Ftryjob.ru%2Fregister.php;mobilebuttons=0;"></div>
        <br />
        -->
        <a href="#phName" onclick="hideUnHide('form');hideUnHide('regform')"><font color="#00FFFF"><span id="register">Регистрация</span></font></a>
        </center>
        </FORM>
        
        <form class="form form-horizontal d-none" method="POST" id="regform" action="/register.php" onsubmit="event.preventDefault?event.preventDefault():(event.returnValue=false);validateReg();">
        <INPUT id="reglocale" TYPE="hidden" NAME="locale" value="RU">
        <center><font style="color:#ffffff; font-size:24px;"><span id="reghead">2&nbsp;минуты&nbsp;&mdash; и&nbsp;у&nbsp;вас база и&nbsp;бесплатный урок</span></font>
        </center>
        <span id="regname">Ваше имя:</span>
        <input type="text" class="form-control" name="name" id="phname" placeholder="Представьтесь, пожалуйста">
        <span id="reglogin">Login (имя базы данных):</span><span id="phlogin_warning" class="form-text text-warning m-0"></span>
        <input type="login" class="form-control required" name="db" id="phlogin" placeholder="От 3 до 15 символов латиницей">
        <span id="regpwd">Пароль:</span><span id="phpwd_warning" class="form-text text-warning m-0"></span>
        <input type="password" class="form-control required" name="inputPassword" id="phpwd" placeholder="Не менее 6 символов">
        <span id="regpwd2">Подтвердите&nbsp;пароль:</span><span id="phpwd2_warning" class="form-text text-warning m-0"></span>
        <input type="password" class="form-control required" name="confirmPassword" id="phpwd2" placeholder="Введите пароль ещё раз">
        <span id="regmail">Email:</span><span id="phmail_warning" class="form-text text-warning m-0"></span>
        <input type="email" class="form-control required" name="inputEmail" id="phmail" placeholder="На случай, если забудете пароль">
        <span id="regtel">Телефон:</span>
        <input type="Phone" class="form-control" name="phone" id="phtel" placeholder="Введите номер телефона">
        <span id="regtemplate">Шаблон:</span>
    	<select class="form-control" id="template" name="template">
    	    <option value="RU">Русский</option>
    	    <option value="EN">English</option>
    	    <option value="full">Русский с html</option>
    	    <option value="mista">Mista</option>
    	</select>
        <br />
        <center>
        <span id="regoffer_warning" class="form-text text-warning m-0"></span>
        <label><font size="+1" color="salmon">*</font>
        <input type="checkbox" name="agree" id="agree"><span id="regoffer">  Я соглашаюсь с <a href="offer.html" target="_blank"><font color="#00FFFF">условиями</font></a> предоставления сервиса Интеграл</span>
        </label>
        <br />
        <input type="submit" class="btn btn-light btn-secondary-secondary" id="btndoreg" style="margin-top:10px;" value="Зарегистрироваться">
        <br />
        <br />
        <a href="#db" onclick="hideUnHide('regform');hideUnHide('form')"><font color="#00FFFF"><span id="login">Я уже зарегистрирован</span></font></a>
        <br />
        </center>
        </form>
        </div>
    </div>
    </div>
</div>
</div>
<center>
    <br/>
    <!--<a href="https://accounts.google.com/o/oauth2/auth?client_id=315358679657-nh0svik9str529clch2p4n23mnmh61fu.apps.googleusercontent.com&redirect_uri=https://ideav.pro/auth.asp&response_type=code&scope=https://www.googleapis.com/auth/userinfo.email%20https://www.googleapis.com/auth/userinfo.profile&state=1">-->
    <a href="https://accounts.google.com/o/oauth2/auth?client_id=693299131937-fe5ffl05r7mnig2e4q0fdctgeiibnjif.apps.googleusercontent.com&redirect_uri=https://dev.forthcrm.ru/auth.asp&response_type=code&scope=https://www.googleapis.com/auth/userinfo.email%20https://www.googleapis.com/auth/userinfo.profile&state=g343">
            <font color="#00FFFF"><span id="googel">Авторизация через</span> Google</font>
    </a>
</center>

<script type="text/javascript">
if(document.location.hash=='#phName'){
    hideUnHide('regform');hideUnHide('form');
    byId('template').value=locale;
}
function byId(i){
    return document.getElementById(i);
}
var autoLocale=true,l=[];
l['EN']={title:'I d e a V'
    ,name:'I d e a V'
    ,dbname:'Database:'
    ,isempty:'Enter DB name, user name, and password'
    ,username:'User name:'
    ,password:'Password:'
    ,remember:' remember me'
    ,change:'change password upon login'
    ,btnsubmit:'Log in'
    ,btnreset:'Reset password'
    ,newpwd:'New password:'
    ,newpwd2:'repeat the password:'
    ,register:'Register'
    ,reghead:'Get your&nbsp;own&nbsp;database and&nbsp;a&nbsp;free&nbsp;lesson'
    ,regname:'Your name:'
    ,phname:'Enter your name'
    ,reglogin:'Login:'
    ,phlogin:'3 to 15 latin characters'
    ,regpwd:'Password:'
    ,phpwd:'6 characters at least'
    ,regpwd2:'Confirm the password:'
    ,phnpwd:'6 characters at least'
    ,phnpwd2:'Enter the password again'
    ,phpwd2:'Enter the password again'
    ,regmail:'Email:'
    ,phmail:'In case you forgot the password'
    ,regtel:'Phone:'
    ,phtel:'Enter your phone number'
    ,regtemplate:'Template:'
    ,regoffer:'&nbsp; I accept <a href="https://ideav.pro/offer_en.html"><font color="#00FFFF">the offer</font></a> of IdeaV'
    ,btndoreg:'Register'
    ,login:'I\'m already registered'
    ,eqpwd:'The passwords must be equal'
    ,mailcheck:'Please enter a valid email'
    ,offercheck:'Please confirm you have read and accepted the papers to protect you'
    ,exists:'This user name already taken'
    ,googel:'Sign-in with'
};
l['RU']={title:'ИНТЕГРАЛ'
    ,name:'ИНТЕГРАЛ'
    ,dbname:'База:'
    ,isempty:'Введите имя базы, пользователя и пароль'
    ,username:'Пользователь:'
    ,password:'Пароль:'
    ,remember:' запомнить пароль'
    ,change:'сменить пароль после входа'
    ,btnsubmit:'Войти'
    ,btnreset:'Сгенерировать пароль'
    ,newpwd:'Новый пароль:'
    ,newpwd2:'еще раз:'
    ,register:'Регистрация'
    ,reghead:'Своя база и&nbsp;бесплатный урок за&nbsp;2&nbsp;минуты'
    ,regname:'Ваше имя:'
    ,phname:'Представьтесь, пожалуйста'
    ,reglogin:'Login (имя базы данных):'
    ,phlogin:'От 3 до 15 символов латиницей'
    ,regpwd:'Пароль:'
    ,phpwd:'Не менее 6 символов'
    ,regpwd2:'Подтвердите&nbsp;пароль:'
    ,phpwd2:'Введите пароль ещё раз'
    ,phnpwd:'Не менее 6 символов'
    ,phnpwd2:'Введите пароль ещё раз'
    ,regmail:'Email:'
    ,phmail:'На случай, если забудете пароль'
    ,regtel:'Телефон:'
    ,phtel:'Введите номер телефона'
    ,regtemplate:'Шаблон:'
    ,regoffer:'&nbsp; Я соглашаюсь с <a href="offer_ru.html"><font color="#00FFFF">условиями</font></a> предоставления сервиса Интеграл'
    ,btndoreg:'Зарегистрироваться'
    ,login:'Я уже зарегистрирован'
    ,eqpwd:'Введите пароль два раза одинаково'
    ,mailcheck:'Введите корректный email'
    ,offercheck:'Примите условия обслуживания и политику конфиденциальности'
    ,exists:'Это имя пользователя уже занято'
    ,googel:'Вход через'
};
var i,checkReg,match=document.cookie.match(new RegExp('(^| )_locale=([^;]+)'));
// Gather all the cookies and url params into an array
var cookies=document.cookie.split(';').reduce(function(p,e){var a=e.trimStart().split('=');p[decodeURIComponent(a[0])]=decodeURIComponent(a[1]);return p;},{})
    ,search=document.location.search.substr(1).split('&').reduce(function(p,e){var a=e.trimStart().split('=');p[decodeURIComponent(a[0])]=decodeURIComponent(a[1]);return p;},{});
if(byId('db').value==='')
    for(i in cookies)
        if(cookies[i].length==32)
            byId('dbs').innerHTML+='<a class="btn" href="'+i+'"><button type="button" class="btn btn-light btn-sm" onclick="byId(\'db\').value=this.innerHTML">'+i+'</button></a>';
if(document.location.search.toUpperCase().indexOf('LOCALE=EN')!=-1)
    lang='EN';
else if(match)
    lang=match[2];  // The locale was found in cookie
else if(navigator.languages && navigator.languages.length)
    lang = navigator.languages[0];
else if (navigator.userLanguage)
    lang = navigator.userLanguage;
else
    lang = navigator.language;
if(lang.toUpperCase().indexOf('RU')==-1)
    byId('locale').value=locale='EN';
else
    byId('locale').value=locale='RU';
byId('template').value=byId('reglocale').value=byId('loginlocale').value=locale;
function localize(){
    for(i in l[locale])
        if(byId(i))
            if('ph'==i.substring(0,2))
                byId(i).placeholder=l[locale][i];
            else if('btn'==i.substring(0,3))
                byId(i).value=l[locale][i];
            else
                byId(i).innerHTML=l[locale][i];
}
localize();
function alertReg(check,id,message){
    message=message||id;
    if(check){
        $('#'+id).removeClass('is-invalid');
        $('#'+id+'_warning').html('');
    }
    else{
        checkReg++;
        $('#'+id).addClass('is-invalid');
        $('#'+id+'_warning').html(l[locale][message]);
    }
    return check;
}
function validateReg(){
    checkReg=0;
    var db=byId('phlogin').value,obj=new XMLHttpRequest();
    alertReg(/^[a-z0-9_]{3,15}$/gi.test(db),'phlogin');
    alertReg(byId('phpwd').value.length>=5,'phpwd');
    if(alertReg(byId('phpwd2').value.length>=5,'phpwd2','phpwd'))
        alertReg(byId('phpwd').value===byId('phpwd2').value,'phpwd2','eqpwd');
    alertReg(/.+@.+\..+/i.test(byId('phmail').value),'phmail','mailcheck');
    alertReg(byId('agree').checked,'regoffer','offercheck');
    if(checkReg===0){
        obj.open('GET','checkuser.php?db='+db,true);
        obj.onload=function(e){ // Когда запрос вернет результат - сработает эта функция
            try{ // в this.responseText лежит ответ от сервера
              json=JSON.parse(this.responseText); // Пытаемся разобрать ответ как JSON
            }
            catch(e){ // Если произошла ошибка при разборе JSON
                console.log(e); // то выводим детали этой ошибки в консоль браузера
                console.log(json=this.responseText);
            }
            obj.abort(); // Закрываем соединение
            if(json.result==='Ok'){
                byId('regform').setAttribute('onSubmit','');
                byId('regform').submit();
            }
            else
                alertReg(false,'phlogin','exists');
        }
        obj.send();
    }
}
function alertInfo(t){
    byId('result').innerHTML='<div class="alert alert-danger" role="alert">'+t+'</div>';
}
function changeLocale(loc){
    autoLocale=false;
    byId('template').value=locale=byId('reglocale').value=byId('loginlocale').value=loc;
    document.cookie = '_locale='+loc+'; max-age=31622400';
    localize();
}
function isEmpty(db,u,p){
	if(db.length*u.length*p.length==0){
		alert(l[locale]['isempty']);
		return true;
	}
	byId('form').action = '/'+byId('db').value+'/auth';
	byId('tzone').value = Math.round(new Date().getTime()/1000)-new Date().getTimezoneOffset()*60;
}
function checkLocale(k){
    if(!autoLocale)
        return;
    match=document.cookie.match(new RegExp('(^| )'+k+'_locale=([^;]+)'));
    if(match){
        byId('locale').value=locale=match[2];  // The locale was found
        byId('reglocale').value=byId('loginlocale').value=locale;
    }
}
function hideUnHide(i){
    if(byId(i).classList.contains('d-none'))
        byId(i).classList.remove('d-none');
    else
        byId(i).className+=' d-none';
}
function intApi(m,u,b,vars,index){ // Параметры: метод, адрес, действие - ветка switch, параметры, ID целевого элемента
    vars=vars||''; // По умолчанию список параметров пуст
    var h='',i,j,json,obj=new XMLHttpRequest(); // Объявляем переменную под JSON и API по HTTPS
    // Открываем асинхронное соединение заданным методом по нужному адресу
    if(u.substring(0,4)=='http')
        obj.open(m,u,true);
    else
        obj.open(m,'/'+db+'/'+u,true);
    if(m=='POST'){ // Если это POST запрос, то передаем заданные параметры
        obj.setRequestHeader('Content-Type','application/x-www-form-urlencoded');
        vars='_xsrf='+xsrf+'&'+vars; // добавляем токен xsrf, необходимый для POST-запроса
    }
    obj.onload=function(e){ // Когда запрос вернет результат - сработает эта функция
        try{ // в this.responseText лежит ответ от сервера
          json=JSON.parse(this.responseText); // Пытаемся разобрать ответ как JSON
        }
        catch(e){ // Если произошла ошибка при разборе JSON
            console.log(e); // то выводим детали этой ошибки в консоль браузера
            console.log(json=this.responseText);
        }
        obj.abort(); // Закрываем соединение
        switch(b){ // Отрабатываем заданную команду - переходим в соответствующую ветку case
            
            // Сведения о текущем прицеливании
            case 'drawAim':
                //alert(json.html);
                $('#frame').html(json.html);
                intApi('GET','report/350?JSON&FR_ID='+id,'drawComm');
                break;

        }
    }
    obj.send(vars); // отправили запрос и теперь будем ждать ответ, а пока - выходим
}
</script>
<!-- Yandex.Metrika counter --> <script type="text/javascript" > (function(m,e,t,r,i,k,a){m[i]=m[i]||function(){(m[i].a=m[i].a||[]).push(arguments)}; m[i].l=1*new Date();k=e.createElement(t),a=e.getElementsByTagName(t)[0],k.async=1,k.src=r,a.parentNode.insertBefore(k,a)}) (window, document, "script", "https://mc.yandex.ru/metrika/tag.js", "ym"); ym(55530634, "init", { clickmap:true, trackLinks:true, accurateTrackBounce:true, webvisor:true }); </script> <noscript><div><img src="https://mc.yandex.ru/watch/55530634" style="position:absolute; left:-9999px;" alt="" /></div></noscript> <!-- /Yandex.Metrika counter -->
</body>
</html>
