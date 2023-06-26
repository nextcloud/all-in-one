"use strict";
(function (){
  let lastError;

  function showError(message) {
    const body = document.getElementsByTagName('body')[0]
    const toast = document.createElement("div")
    toast.className = "toast error"
    toast.prepend(message)
    if (lastError) {
      lastError.remove()
    }
    lastError = toast
    body.prepend(toast)
    setTimeout(toast.remove.bind(toast), 10000)
  }

  function handleEvent(e) {
    const xhr = e.target;
    if (xhr.status === 201) {
      window.location.replace(xhr.getResponseHeader('Location'));
    } else if (xhr.status === 422) {
      disableSpinner()
      showError(xhr.response);
    } else if (xhr.status === 500) {
      disableSpinner()
      showError("Server error. Please check the mastercontainer logs for details.");
    } else {
      // If the responose is not one of the above, we should reload to show the latest content
      window.location.reload(1);
    }
  }

  function enableSpinner() {
    document.getElementById('overlay').classList.add('loading');
  }

  function disableSpinner() {
    document.getElementById('overlay').classList.remove('loading');
  }

  function showPassword(id) {
    let passwordField = document.getElementById(id);
    if (passwordField.type === "password" && passwordField.value !== "") {
      passwordField.type = "text";
    } else if (passwordField.type === "text" && passwordField.value === "") {
      passwordField.type = "password";
    }
  }

  function initForm(form) {
    function submit(event)
    {
      if (lastError) {
        lastError.remove()
      }
      let xhr = new XMLHttpRequest();
      xhr.addEventListener('load', handleEvent);
      xhr.addEventListener('error', () => showError("Failed to talk to server."));
      xhr.addEventListener('error', () => disableSpinner());
      xhr.open(form.method, form.getAttribute("action"));
      xhr.setRequestHeader('Content-type', 'application/x-www-form-urlencoded');
      enableSpinner();
      xhr.send(new URLSearchParams(new FormData(form)));
      event.preventDefault();
    }

    form.onsubmit = submit;
    console.info(form);
  }

  function initForms() {
    const forms = document.querySelectorAll('form.xhr')
    console.info("Making " + forms.length + " form(s) use XHR.");
    for (const form of forms) {
      initForm(form);
    }
  }

  if (document.readyState === 'loading') {
    // Loading hasn't finished yet
    document.addEventListener('DOMContentLoaded', initForms);
  } else {  // `DOMContentLoaded` has already fired
    initForms();
  }
})()
