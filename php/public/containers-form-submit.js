document.addEventListener("DOMContentLoaded", function () {
    // Don't run if the expected form isn't present.
    if (document.getElementById('options-form') === null) {
        return;
    }

    // Hide submit button initially
    const optionsFormSubmit = document.querySelectorAll(".options-form-submit");
    optionsFormSubmit.forEach(element => {
        element.style.display = 'none';
    });

    const communityFormSubmit = document.getElementById("community-form-submit");
    communityFormSubmit.style.display = 'none';

    // Store initial states for all checkboxes
    const initialStateOptionsContainers = {};
    const initialStateCommunityContainers = {};
    const optionsContainersCheckboxes = document.querySelectorAll("#options-form input[type='checkbox']");
    const communityContainersCheckboxes = document.querySelectorAll("#community-form input[type='checkbox']");

    // Office suite radio buttons
    const collaboraRadio = document.getElementById('office-collabora');
    const onlyofficeRadio = document.getElementById('office-onlyoffice');
    const noneRadio = document.getElementById('office-none');
    const collaboraHidden = document.getElementById('collabora');
    const onlyofficeHidden = document.getElementById('onlyoffice');
    let initialOfficeSelection = null;

    optionsContainersCheckboxes.forEach(checkbox => {
        initialStateOptionsContainers[checkbox.id] = checkbox.checked;  // Use checked property to capture actual initial state
    });

    communityContainersCheckboxes.forEach(checkbox => {
        initialStateCommunityContainers[checkbox.id] = checkbox.checked;  // Use checked property to capture actual initial state
    });

    // Store initial office suite selection
    if (collaboraRadio && onlyofficeRadio && noneRadio) {
        if (collaboraRadio.checked) {
            initialOfficeSelection = 'collabora';
        } else if (onlyofficeRadio.checked) {
            initialOfficeSelection = 'onlyoffice';
        } else {
            initialOfficeSelection = 'none';
        }
    }

    // Function to compare current states to initial states
    function checkForOptionContainerChanges() {
        let hasChanges = false;

        optionsContainersCheckboxes.forEach(checkbox => {
            if (checkbox.checked !== initialStateOptionsContainers[checkbox.id]) {
                hasChanges = true;
            }
        });

        // Check office suite changes and sync to hidden inputs
        if (collaboraRadio && onlyofficeRadio && noneRadio && collaboraHidden && onlyofficeHidden) {
            let currentOfficeSelection = null;
            if (collaboraRadio.checked) {
                currentOfficeSelection = 'collabora';
                collaboraHidden.value = 'on';
                onlyofficeHidden.value = '';
            } else if (onlyofficeRadio.checked) {
                currentOfficeSelection = 'onlyoffice';
                collaboraHidden.value = '';
                onlyofficeHidden.value = 'on';
            } else {
                currentOfficeSelection = 'none';
                collaboraHidden.value = '';
                onlyofficeHidden.value = '';
            }

            if (currentOfficeSelection !== initialOfficeSelection) {
                hasChanges = true;
            }
        }

        // Show or hide submit button based on changes
        optionsFormSubmit.forEach(element => {
            element.style.display = hasChanges ? 'block' : 'none';
        });
    }

    // Function to compare current states to initial states
    function checkForCommunityContainerChanges() {
        let hasChanges = false;

        communityContainersCheckboxes.forEach(checkbox => {
            if (checkbox.checked !== initialStateCommunityContainers[checkbox.id]) {
                hasChanges = true;
            }
        });

        // Show or hide submit button based on changes
        communityFormSubmit.style.display = hasChanges ? 'block' : 'none';
    }

    // Event listener to trigger visibility check on each change
    optionsContainersCheckboxes.forEach(checkbox => {
        checkbox.addEventListener("change", checkForOptionContainerChanges);
    });

    communityContainersCheckboxes.forEach(checkbox => {
        checkbox.addEventListener("change", checkForCommunityContainerChanges);
    });

    // Custom behaviors for specific options
    function handleTalkVisibility() {
        const talkRecording = document.getElementById("talk-recording");
        if (document.getElementById("talk").checked) {
            talkRecording.disabled = false;
        } else {
            talkRecording.checked = false;
            talkRecording.disabled = true;
        }
        checkForOptionContainerChanges();  // Check changes after toggling Talk Recording
    }

    function handleDockerSocketProxyWarning() {
        if (document.getElementById("docker-socket-proxy").checked) {
            alert('⚠️ The docker socket proxy container is deprecated. Please use the HaRP (High-availability Reverse Proxy for Nextcloud ExApps) instead!');
            document.getElementById("docker-socket-proxy").checked = false
        }
    }

    function handleHarpWarning() {
        if (document.getElementById("harp").checked) {
            alert('⚠️ Warning! Enabling this container comes with possible Security problems since you are exposing the docker socket and all its privileges to the HaRP container. Enable this only if you are sure what you are doing!');
            document.getElementById("docker-socket-proxy").checked = false
        }
    }

    // Initialize event listeners for specific behaviors
    document.getElementById("talk").addEventListener('change', handleTalkVisibility);
    document.getElementById("docker-socket-proxy").addEventListener('change', handleDockerSocketProxyWarning);
    document.getElementById("harp").addEventListener('change', handleHarpWarning);

    // Initialize talk-recording visibility on page load
    handleTalkVisibility();  // Ensure talk-recording is correctly initialized

    // Add event listeners for office suite radio buttons
    if (collaboraRadio && onlyofficeRadio && noneRadio) {
        collaboraRadio.addEventListener('change', checkForOptionContainerChanges);
        onlyofficeRadio.addEventListener('change', checkForOptionContainerChanges);
        noneRadio.addEventListener('change', checkForOptionContainerChanges);
    }

    // Initial call to check for changes
    checkForOptionContainerChanges();
    checkForCommunityContainerChanges();
});
