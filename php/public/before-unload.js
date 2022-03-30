window.addEventListener("beforeunload", function() {
    document.getElementById('overlay').classList.add('loading')
});