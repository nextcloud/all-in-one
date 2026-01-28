document.addEventListener("DOMContentLoaded", function(event) {
    function displayOverlayLogMessage(message) {
        const overlayLogElement = document.getElementById('overlay-log');
        if (!overlayLogElement) {
            return;
        }
        overlayLogElement.textContent = message;
    }

    // Attempt to connect to Server-Sent Events at /events/containers and listen for 'container-start' events
    if (typeof EventSource !== 'undefined') {
        try {
            const serverSentEventSource = new EventSource('events/containers');
            serverSentEventSource.addEventListener('container-start', function(serverSentEvent) {
                try {
                    let parsedPayload = JSON.parse(serverSentEvent.data);
                    displayOverlayLogMessage(parsedPayload.name || serverSentEvent.data);
                } catch (parseError) {
                    displayOverlayLogMessage(serverSentEvent.data);
                }
            });
            serverSentEventSource.onerror = function() { serverSentEventSource.close(); };
        } catch (connectionError) {
            /* ignore if Server-Sent Events are not available */
        }
    }
});
