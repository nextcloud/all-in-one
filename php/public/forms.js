"use strict";

(function (){
  let lastError;

  // Forms rendered inside a same-origin iframe (e.g. the deSEC modal) show their
  // errors on the top-level page so the toast is not clipped by the modal.
  function toastDocument() {
    try {
      return window.top.document
    } catch {
      return document
    }
  }

  function showError(message) {
    const doc = toastDocument()
    const body = doc.body
    const toast = doc.createElement("div")
    toast.className = "toast error"
    toast.prepend(message)
    if (lastError) {
      lastError.remove()
    }
    lastError = toast
    body.prepend(toast)
    // Scheduled on the toast's window so the timer survives the iframe navigating away.
    doc.defaultView.setTimeout(toast.remove.bind(toast), 10000)
  }

  function handleEvent(e) {
    const xhr = e.target;
    if (xhr.status === 201) {
      window.location.replace(xhr.getResponseHeader('Location'));
    } else if (xhr.status === 422) {
      disableSpinner()
      showError(xhr.response);
    } else if (xhr.status === 500) {
      showError("Server error. Please check the mastercontainer logs for details. This page will reload after 10s automatically. Then you can check the mastercontainer logs.");
      // Reload after 10s since it is expected that the updated view is shown (e.g. after starting containers)
      setTimeout(function(){
        window.location.reload(true);
      }, 10000);
    } else {
      // If the responose is not one of the above, we should reload to show the latest content
      window.location.reload(true);
    }
  }

  function enableSpinner() {
    document.getElementById('overlay').classList.add('loading');
  }

  function disableSpinner() {
    document.getElementById('overlay').classList.remove('loading');
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
  }

  function initForms() {
    const forms = document.querySelectorAll('form.xhr')
    for (const form of forms) {
      initForm(form);
    }
    const overlayLogForms = document.querySelectorAll('form[target="overlay-log"]')
    for (const form of overlayLogForms) {
        form.onsubmit = function() {
            enableSpinner();
            document.getElementById('overlay-log')?.classList.add('visible');
            // Reload the page after the response was fully loaded into the iframe.
            document.querySelector('iframe[name="overlay-log"]').addEventListener('load', () => {
                location.reload(true);
            });
        };
    }
  }

  if (document.readyState === 'loading') {
    // Loading hasn't finished yet
    document.addEventListener('DOMContentLoaded', initForms);
  } else {  // `DOMContentLoaded` has already fired
    initForms();
  }
})()
