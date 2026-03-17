window.addEventListener('beforeunload', function (event) {
    // Trigger AJAX request to log the user out
    navigator.sendBeacon('logout.php');
});
