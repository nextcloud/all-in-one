class LogViewer {
    // Configure the interval in seconds for autoloading log data.
    autoloadIntervalSec = 5;
    // Set to true to see some debug log statements in the browser console.
    debugLog = false;

    // Don't touch these, please.
    containerId;
    apiBaseUrl = 'api/docker/logs';
    autoloadIntervalId = null;
    logElem;
    lastLogTimestamp = '';
    autoloadingDisabledFromButton = false;
    loaderElem;
    dataLoadingLock;

    constructor() {
        const id = document.body.dataset.containerId;
        if (typeof(id) !== 'string' || !id.startsWith('nextcloud-aio-')) {
            throw new Exception('Invalid container ID');
        }
        this.containerId = id;
        this.logElem = document.querySelector('pre');
        this.loaderElem = document.querySelector('.loader');
        this.initAutoloadingControls();
        // Enable automatic log data loading.
        this.startAutoloading();
    }

    startAutoloading() {
        // Load log data immediately.
        this.loadAndAppendLogData();
        // Load new log data repeatedly.
        this.debug("Starting autoloading");
        this.autoloadIntervalId = setInterval(() => {
            if (this.isAutoloadingEnabled()) {
                this.loadAndAppendLogData();
            }
        }, 5000);
    }

    stopAutoloading() {
        this.debug("Stopping autoloading");
        clearInterval(this.autoloadIntervalId);
        this.autoloadIntervalId = null;
    }

    isAutoloadingEnabled() {
        return !!this.autoloadIntervalId;
    }

    getUrl() {
        return `${this.apiBaseUrl}?id=${this.containerId}&since=${this.lastLogTimestamp}`;
    }

    debug(...args) {
        if (this.debugLog) {
            console.debug('LogViewer:', ...args);
        }
    }

    // Load log data and append it to the DOM.
    loadAndAppendLogData() {
        if (this.dataLoadingLock) {
            this.debug("Another log data loading request is still running, cancelling this request");
            return;
        }
        this.debug("Loading new log data");
        this.dataLoadingLock = true;
        this.loaderElem.classList.remove('hidden');
        fetch(this.getUrl())
            .then((response) => {
                if (!response.ok) {
                    throw new Error("Error while fetching log data!");
                }
                return response;
            })
            .then((response) => response.text())
            .then((text) => {
                text = text.trim();
                if (text.length === 0) {
                    this.debug("Received no new log data from server");
                    return;
                }
                this.debug("Received", Math.round(text.length / 1024), "KB of new log data from server");
                this.logElem.append(text + "\n");
                this.scrollToBottom();
                this.lastLogTimestamp = text.split("\n").at(-1)?.split(' ')[0] ?? '';
            })
            .finally(() => {
                this.dataLoadingLock = false;
                this.loaderElem.classList.add('hidden');
                this.debug("Finished log data loading");
            })
            .catch((err) => console.error(err));
    }

    scrollToBottom() {
        window.scrollTo(0, document.body.scrollHeight);
    }

    initAutoloadingControls() {
        // Provide a button that allows to manually disable the autoloading.
        const button = document.getElementById('autoloading-control');
        const statusElem = document.getElementById('autoloading-status');
        if (!button) {
            return;
        }
        button.addEventListener('click', (event) => {
            event.preventDefault();
            if (this.isAutoloadingEnabled()) {
                this.stopAutoloading();
                statusElem.textContent = 'disabled';
                button.textContent = 'Enable';
                this.autoloadingDisabledFromButton = true;
            } else {
                this.startAutoloading();
                statusElem.textContent = 'enabled';
                button.textContent = 'Disable';
                this.autoloadingDisabledFromButton = false;
            }
        });

        // Load new data immediately if the window gets visible to the user again (unless autoloading has been
        // disabled).
        document.addEventListener('visibilitychange', () => {
            if (document.visibilityState === 'visible') {
                this.debug("Window became visible");
                if (!this.autoloadingDisabledFromButton) {
                    this.startAutoloading();
                }
            } else {
                this.debug("Window became hidden");
                this.stopAutoloading();
            }
        });
    }
}

document.addEventListener("DOMContentLoaded", () => {
    new LogViewer();
});
