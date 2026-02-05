class ContainerEventsLogClient {
    overlayElem;
    overlayLogElem;
    pollingFrequencySec = 5;
    pollingIntervalId = null;
    etag = '';
    debugLogging = false;

    constructor() {
        this.overlayElem = document.getElementById('overlay');
        this.fetchAndShow();
        this.pollingIntervalId = setInterval(() => this.fetchAndShow(), this.pollingFrequencySec * 1000);
    }

    #debug(message) {
        if (this.debugLogging) {
            console.debug(message);
        }
    }

    stopPolling() {
        if (this.pollingIntervalId) {
            clearInterval(this.pollingIntervalId);
        }
    }

    async storeEtag(response) {
        const newEtag = response.headers.get('etag');
        if (newEtag) {
            this.etag = newEtag;
        }
        return response;
    }

    async getTextFromResponse(response) {
        if (response.status >= 200 && response.status < 300) {
            return response.text();
        } else if (response.status === 304) {
            this.#debug('Cache hit, nothing to do');
            return Promise.reject();
            // Cache hit, nothing to do.
        } else {
            console.error(`Got response status ${response.status}, cannot continue`);
            return Promise.reject();
        }
    }

    showLoggedEventsInOverlay(loggedEvents) {
        this.overlayLogElem ||= document.getElementById('overlay-log');
        this.overlayLogElem.classList.add('visible');
        loggedEvents.forEach((loggedEvent) => {
            const elem = this.overlayLogElem.querySelector(`.${loggedEvent.id}`);
            if (elem) {
                elem.lastElementChild.textContent = loggedEvent.message;
            } else {
                const capitalizedContainerName = loggedEvent.id.replace('nextcloud-aio-', '').replace('-', ' ').replace(/(^|\s)[a-z]/gi, (letter) => letter.toUpperCase());
                const newElem = document.createElement('div');
                newElem.className = loggedEvent.id;
                const nameElem = document.createElement('span');
                nameElem.textContent = `${capitalizedContainerName}:`;
                const messageElem = document.createElement('span');
                messageElem.textContent = loggedEvent.message;
                newElem.append(nameElem, messageElem);
                this.overlayLogElem.append(newElem);
            }
        });
    }

    showLoggedEventsInContainerList(loggedEvents) {
        this.containerElems ||= new Map(Array.from(document.getElementsByClassName('container-elem')).map((elem) => [elem.dataset.containerId, elem.querySelector('.events-log')]));
        loggedEvents.forEach((loggedEvent) => {
            const textElem = this.containerElems.get(loggedEvent.id);
            // Check if the element exists, the event list might contain events for containers that are
            // not contained in our list.
            if (textElem) {
                textElem.textContent = loggedEvent.message;
            }
        });
    }
    
    async showLoggedEvents(text) {
        const loggedEvents = new Map();
        this.#debug({ text });
        // Split text into logged-events and filter out empty lines.
        const lines = text.split('\n').filter((line) => line);
        // Reduce the list of events to the last of each container.
        lines.forEach((line) => {
            const loggedEvent = JSON.parse(line);
            loggedEvents.set(loggedEvent.id, loggedEvent);
        });
        if (this.overlayElem && this.overlayElem.checkVisibility()) {
            this.showLoggedEventsInOverlay(loggedEvents);
        } else {
            this.showLoggedEventsInContainerList(loggedEvents);
        }
    }

    fetchAndShow(args = { forceReloading: false}) {
        if (args.forceReloading) {
            this.etag = '';
        }
        this.#debug('Fetching logged events from server');
        fetch('/api/events/containers', {
                cache: 'no-cache',
                headers: {
                    'If-None-Match': this.etag,
                },
            })
            .then((response) => this.storeEtag(response))
            .then((response) => this.getTextFromResponse(response))
            .then((text) => this.showLoggedEvents(text))
            .catch((error) => {
                if (error instanceof Error) {
                    throw error;
                }
            });
    };
}

document.addEventListener('DOMContentLoaded', () => {
    window.containerEventsLogClient = new ContainerEventsLogClient();
});
