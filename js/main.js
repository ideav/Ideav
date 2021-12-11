var state = false;
var navbar = document.getElementById("navbarSupportedContent");
var rightBlock = document.getElementById("right_block");

var navListItem=byId('navlist').innerHTML
    ,navList=byId('dropdown-list').innerHTML
    ,extraListItem=byId('dropdown-list').innerHTML
    ,extraListTemplate=byId('extralist').innerHTML;

function resizeMutations(){
    var extraList='',listLength=byId('brand').offsetWidth; // The nav's length in pixels
    byId('navbar-list').innerHTML='';
    for(var i in menu){ // Add a menu item and calc the space left for the rest of items
        if(listLength+byId('right_block').offsetWidth+200<document.documentElement.clientWidth
                || (i==menu.length-1 && extraList==='') // Do not shrink the lone last item
                || document.documentElement.clientWidth<=562){ 
            byId('navbar-list').innerHTML+=navListItem.replace(':href:',menu[i].href)
                                                    .replace(':name:',menu[i].name)
                                                    .replace(':id:',i);
            listLength+=byId('list'+i).offsetWidth; // Update the total space occupied
        }
        else // No space left - fill in the Extra dropdown menu
            extraList+=extraListItem.replace(':href:',menu[i].href)
                                    .replace(':name:',menu[i].name);
    }
    if(extraList!=='') // Put the extra menu in, if any
        byId('navbar-list').innerHTML+=extraListTemplate.replace(extraListItem,extraList);
}
// Put the burger's menu after the nav pane to see the proper dropdown list
var observer = new MutationObserver((e) => {
    e.forEach(mutation => {
        if (mutation.target.classList.contains('show')) {
                navbar.parentNode.insertBefore(rightBlock, navbar)
        } else {
            rightBlock.parentNode.insertBefore(navbar, rightBlock);
        }
    })
});
observer.observe(navbar, { // No idea what this hell is about
    attributes: true
})

var burgerClick = function(target) {
    var collapsable = target.attributes.getNamedItem('data-target');
    var collapseElem = document.getElementById(collapsable.value.replace('#', ''));
    if (collapseElem.classList.contains('show')) {
        collapseElem.classList.remove('show')
    } else {
        collapseElem.classList.add('show')
    }
    //resizeMutations();
}

window.addEventListener('load',function(){
    resizeMutations();
});

window.onresize = resizeMutations