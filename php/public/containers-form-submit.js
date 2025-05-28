document.addEventListener("DOMContentLoaded", function () {
    // Hide submit button initially
    const optionsFormSubmit = document.getElementById("options-form-submit");
    optionsFormSubmit.style.display = 'none';

    const communityFormSubmit = document.getElementById("community-form-submit");
    communityFormSubmit.style.display = 'none';

    // Store initial states for all checkboxes
    const initialStateOptionsContainers = {};
    const initialStateCommunityContainers = {};
    const optionsContainersCheckboxes = document.querySelectorAll("#options-form input[type='checkbox']");
    const communityContainersCheckboxes = document.querySelectorAll("#community-form input[type='checkbox']");

    optionsContainersCheckboxes.forEach(checkbox => {
        initialStateOptionsContainers[checkbox.id] = checkbox.checked;  // Use checked property to capture actual initial state
    });

    communityContainersCheckboxes.forEach(checkbox => {
        initialStateCommunityContainers[checkbox.id] = checkbox.checked;  // Use checked property to capture actual initial state
    });

    // Function to compare current states to initial states
    function checkForOptionContainerChanges() {
        let hasChanges = false;

        optionsContainersCheckboxes.forEach(checkbox => {
            if (checkbox.checked !== initialStateOptionsContainers[checkbox.id]) {
                hasChanges = true;
            }
        });

        // Show or hide submit button based on changes
        optionsFormSubmit.style.display = hasChanges ? 'block' : 'none';
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
            alert('⚠️ Warning! Enabling this container comes with possible Security problems since you are exposing the docker socket and all its privileges to the Nextcloud container. Enable this only if you are sure what you are doing!');
        }
    }

    // Initialize event listeners for specific behaviors
    document.getElementById("talk").addEventListener('change', handleTalkVisibility);
    document.getElementById("docker-socket-proxy").addEventListener('change', handleDockerSocketProxyWarning);

    // Initialize talk-recording visibility on page load
    handleTalkVisibility();  // Ensure talk-recording is correctly initialized

    // Initial call to check for changes
    checkForOptionContainerChanges();
    checkForCommunityContainerChanges();
});
