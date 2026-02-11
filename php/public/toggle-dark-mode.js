// Function to toggle theme
function toggleTheme() {
    const currentTheme = document.documentElement.getAttribute('data-theme');
    const newTheme = (currentTheme === 'dark') ? '' : 'dark'; // Toggle between no theme and dark theme
    setThemeToDOM(newTheme);
    localStorage.setItem('theme', newTheme);

    // Change the icon based on the current theme
    const themeIcon = document.getElementById('theme-icon');
    themeIcon.textContent = newTheme === 'dark' ? '☀️' : '🌙'; // Switch between moon and sun icons
}

function setThemeToDOM(value) {
    // Set the theme to the root document and all possible iframe documents (so they can adapt their styling, too).
    const documents = [document, Array.from(document.querySelectorAll('iframe')).map((iframe) => iframe.contentDocument)].flat()
    documents.forEach((doc) => doc.documentElement.setAttribute('data-theme', value));
}

// Function to immediately apply saved theme without icon update
function applySavedThemeImmediately() {
    // Default to light theme
    setThemeToDOM(localStorage.getItem('theme') ?? '');
}

// Function to apply theme-icon update
function setThemeIcon() {
    const savedTheme = localStorage.getItem('theme');
    if (savedTheme === 'dark') {
        document.getElementById('theme-icon').textContent = '☀️'; // Sun icon for dark mode
    } else {
        document.getElementById('theme-icon').textContent = '🌙'; // Moon icon for light mode
    }
}

// Immediately apply the saved theme to avoid flickering
applySavedThemeImmediately();

// Apply theme when the page loads
document.addEventListener('DOMContentLoaded', setThemeIcon);
