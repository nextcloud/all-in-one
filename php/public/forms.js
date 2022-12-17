"use strict";
(function (){
  var lastError;

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
      showError(xhr.response);
    } else if (xhr.status === 500) {
      showError("Server error. Please check the mastercontainer logs for details.");
    } else {
      // If the responose is not one of the above, we should reload to show the latest content
      window.location.reload(1);
    }
  }

  function disable(element) {
    document.getElementById('overlay').classList.add('loading');
    element.classList.add('loading');
    element.disabled = true;
  }

  function enable(element) {
    document.getElementById('overlay').classList.remove('loading');
    element.classList.remove('loading');
    element.disabled = false;
  }

  function initForm(form) {
    function submit(event)
    {
      if (lastError) {
        lastError.remove()
      }
      var xhr = new XMLHttpRequest();
      xhr.addEventListener('load', handleEvent);
      xhr.addEventListener('error', () => showError("Failed to talk to server."));
      xhr.addEventListener('load', () => enable(event.submitter));
      xhr.addEventListener('error', () => enable(event.submitter));
      xhr.open(form.method, form.getAttribute("action"));
      xhr.setRequestHeader('Content-type', 'application/x-www-form-urlencoded');
      disable(event.submitter);
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
