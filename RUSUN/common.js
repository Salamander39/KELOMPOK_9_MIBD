// common.js
document.addEventListener('DOMContentLoaded', function() {
    const backButtons = document.querySelectorAll('.exit-button');
    
    backButtons.forEach(button => {
        button.addEventListener('click', function(e) {
            e.preventDefault();
            
            const currentPage = window.location.pathname.split('/').pop().toLowerCase();
            const isHomePage = ['home.html', 'home_admin.html', 'home_pengelola.html'].includes(currentPage);
            
            if (isHomePage) {
                // For home pages, always go to login
                window.location.href = 'LOGIN.html';
            } else {
                // For other pages, go back in history or to appropriate home
                window.history.back();
            }
        });
    });
});