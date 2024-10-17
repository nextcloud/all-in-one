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

// Function to apply saved theme from localStorage
function applySavedTheme() {
    const savedTheme = localStorage.getItem('theme');
    if (savedTheme === 'dark') {
        document.documentElement.setAttribute('data-theme', 'dark');
        document.getElementById('theme-icon').textContent = '☀️'; // Sun icon for dark mode
    } else {
        document.documentElement.removeAttribute('data-theme'); // Default to light theme (no data-theme)
        document.getElementById('theme-icon').textContent = '🌙'; // Moon icon for light mode
    }
}

// Immediately apply the saved theme
applySavedTheme();
