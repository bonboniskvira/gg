<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">



    <meta name="viewport" content="width=device-width, initial-scale=1.0">



    <title>eDO Sys</title>



    <!-- Favicon -->

    <link rel="icon" type="image/x-icon" href="favicon/pokuss.ico">



    <!-- Google Fonts - Montserrat -->

    <link rel="preconnect" href="https://fonts.googleapis.com">

    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

    <link href="https://fonts.googleapis.com/css2?family=Montserrat:ital,wght@0,100..900;1,100..900&display=swap" rel="stylesheet">



    <link rel="stylesheet" href="styles.css">



    <script src="script.js" defer></script>



    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">



    <style>



        /* Apply Montserrat font globally */

        * {

            font-family: 'Montserrat', sans-serif;

        }



        /* Ensure all pages are hidden by default */



        .page-content {



            display: none;



        }







        /* Dashboard will be shown by default via JavaScript */



        #dashboard {



            display: block;



        }



    </style>



</head>







<body>

<div class="container">

    <script>
        function handleNavigation() {
            const urlParams = new URLSearchParams(window.location.search);
            const page = urlParams.get('page');
            const hash = window.location.hash.replace('#', '');

            let pageIdToShow = 'dashboard'; // Default page

            if (page) {
                pageIdToShow = page;
            } else if (hash) {
                pageIdToShow = hash;
            }

            showActivePage(pageIdToShow);
        }

        // Global function to show active page
        window.showActivePage = function(pageId) {
            // Hide all page contents
            document.querySelectorAll('.page-content').forEach(page => {
                page.style.display = 'none';
            });

            // Handle special case for search
            let targetPageId = pageId;
            if (pageId === 'file:search.php') {
                targetPageId = 'search';
            }

            // Show the selected page
            const targetPage = document.getElementById(targetPageId);
            if (targetPage) {
                targetPage.style.display = 'block';
                console.log('Showing page:', targetPageId);
            } else {
                // Fallback to dashboard if page not found
                const dashboard = document.getElementById('dashboard');
                if (dashboard) {
                    dashboard.style.display = 'block';
                    console.log('Fallback to dashboard');
                }
            }
        };

        // Initialize page display on load
        document.addEventListener('DOMContentLoaded', handleNavigation);

        // Handle hash changes for navigation
        window.addEventListener('hashchange', handleNavigation);
    </script>