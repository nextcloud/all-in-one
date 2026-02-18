// Function to toggle theme
function toggleTheme() {
    const currentTheme = document.documentElement.getAttribute('data-theme');
    const newTheme = (currentTheme === 'dark') ? '' : 'dark'; // Toggle between no theme and dark theme
    setThemeToDOM(newTheme);
    localStorage.setItem('theme', newTheme);

    // Change the icon based on the current theme
    setThemeIcon(newTheme);
}

function setThemeToDOM(value) {
    // Set the theme to the root document and all possible iframe documents (so they can adapt their styling, too).
    const documents = [document, Array.from(document.querySelectorAll('iframe')).map((iframe) => iframe.contentDocument)].flat()
    documents.forEach((doc) => doc.documentElement.setAttribute('data-theme', value));
}

function getSavedTheme() {
    return localStorage.getItem('theme') ?? '';
}

// Function to apply theme-icon update
function setThemeIcon(theme) {
    if (theme === 'dark') {
        document.getElementById('theme-icon').textContent = '☀️'; // Sun icon for dark mode
    } else {
        document.getElementById('theme-icon').textContent = '🌙'; // Moon icon for light mode
    }
}

// Immediately apply the saved theme to avoid flickering
setThemeToDOM(getSavedTheme());

// Apply theme when the page loads
document.addEventListener('DOMContentLoaded', () => setThemeIcon(getSavedTheme()));
