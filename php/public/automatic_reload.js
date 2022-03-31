if (document.hasFocus()) {
    // hide reload button if the site reloads automatically
    var list = document.getElementsByClassName("reload button");
    for (var i = 0; i < list.length; i++) {
        // list[i] is a node with the desired class name
        list[i].style.display = 'none';
    }

    // set timeout for reload
    setTimeout(function(){
    window.location.reload(1);
    }, 5000);
} else {
    window.addEventListener("beforeunload", function() {
        document.getElementById('overlay').classList.add('loading')
    });
}