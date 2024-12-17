// Function to toggle theme
function toggleTheme() {
    const currentTheme = document.documentElement.getAttribute('data-theme');
    const newTheme = (currentTheme === 'dark') ? '' : 'dark'; // Toggle between no theme and dark theme
    document.documentElement.setAttribute('data-theme', newTheme);
    localStorage.setItem('theme', newTheme);

    // Change the icon based on the current theme
    const themeIcon = document.getElementById('theme-icon');
    themeIcon.textContent = newTheme === 'dark' ? '☀️' : '🌙'; // Switch between moon and sun icons
}

// Function to immediately apply saved theme without icon update
function applySavedThemeImmediately() {
    const savedTheme = localStorage.getItem('theme');
    if (savedTheme === 'dark') {
        document.documentElement.setAttribute('data-theme', 'dark');
    } else {
        document.documentElement.removeAttribute('data-theme'); // Default to light theme
    }
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
