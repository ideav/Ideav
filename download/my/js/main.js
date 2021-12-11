function byId(i){
    return document.getElementById(i);
}
function getCookie(name){
    var matches=document.cookie.match(new RegExp("(?:^|; )"+name.replace(/([\.$?*|{}\(\)\[\]\\\/\+^])/g,'\\$1')+"=([^;]*)"));
    return matches?decodeURIComponent(matches[1]):undefined;
}
function t9n(text){
    var l=text.indexOf(locale);
    if(l==-1)
        return text;
    l = l + locale.length;
    var n = text.substring(l).search(/\[[A-Z]{2}\]/);
    if(n==-1)
        return text.substring(l);
    return text.substring(l,n+l);
}
// Fetch the locale from cookie
function replacer(match,a,b,c,d){
    return t9n(d);
}
function localize(){
    document.title=t9n(document.title);
    // Translate the content of the t9n class
    $('.t9n').each(function(index){
        // Get inner HTML of all tags with t9n, capture <t9n> tags, and process those with t9n()
        this.innerHTML=this.innerHTML.replace(/((&lt;t9n&gt;)|(<t9n>))(.*?)((&lt;\/t9n&gt;)|(<\/t9n>))/gm, replacer);
        $(this).removeClass('t9n'); // remove the t9n class to show this element
    });
    // Translate the content of the t9n tags
    $('t9n').each(function(index){
        $(this).replaceWith(t9n($(this).html()));
    });
}
function post(a){
	byId('post').action+=a;
	byId('post').submit();
	event.preventDefault?event.preventDefault():(event.returnValue=false);
}
var locale=getCookie(db+'_locale');
if(locale)
    locale='['+locale+']';  // The locale was found
else
    locale='[RU]'; // Use RU by default

var search=window.location.search.substr(1).split('&').reduce(function(result,item){
    var parts=item.split('=');
    result[parts[0]]=parts[1];
    return result;
}, {});