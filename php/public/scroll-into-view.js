const observer = new MutationObserver((records) => {
    const node = records[0]?.addedNodes[0];
    // Text nodes also appear here but can't be scrolled to, so we have to check for the
    // function being present.
    if (node && typeof(node.scrollIntoView) === 'function') {
        node.scrollIntoView();
    }
});
observer.observe(document, {childList: true, subtree: true});
