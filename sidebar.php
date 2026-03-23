<!-- Sidebar mainpart-->

<div class="sidebar" id="sidebar">

    <div class="sidebar-header">

        <div class="sidebar-title" id="marvin-title" style="cursor: pointer;">eDO Sys</div>

    </div>

    <div class="menu-items">

        <div class="menu-links">
            <?php 
            // Normalize role for comparison
            $current_role_normalized = isset($_SESSION['role']) ? strtolower(trim($_SESSION['role'])) : '';
            if ($current_role_normalized !== 'poradce'): 
            ?>
            <a href="#edo" class="menu-item" data-page="edo">

                <span class="menu-icon">🏫</span>

                <span>eDO Start</span>

            </a>



            <a href="#test" class="menu-item" data-page="test">



                <span class="menu-icon">📄</span>



                <span>Odborná zkouška</span>



            </a>



            <a href="#list" class="menu-item" data-page="list">



                <span class="menu-icon">📜</span>



                <span>Seznam oblastí</span>



            </a>



            <a href="#agreements" class="menu-item" data-page="agreements">



                <span class="menu-icon">📑</span>



                <span>Smlouvy</span>



            </a>



            <a href="#marketing" class="menu-item" data-page="marketing">



                <span class="menu-icon">📺</span>



                <span>Marketing</span>



            </a>



            <a href="#programs" class="menu-item" data-page="programs">



                <span class="menu-icon">🤖</span>



                <span>AI</span>



            </a>



            <a href="#courses" class="menu-item" data-page="courses">



                <span class="menu-icon">🎓</span>



                <span>Kurzy a školení</span>



            </a>



            <a href="#links" class="menu-item" data-page="links">



                <span class="menu-icon">📎</span>



                <span>Odkazy</span>



            </a>



            <a href="#pdfs" class="menu-item" data-page="pdfs">



                <span class="menu-icon">🗞️</span>



                <span>Magazíny</span>



            </a>



            <a href="#other" class="menu-item" data-page="other">



                <span class="menu-icon">📂</span>



                <span>Ostatní</span>



            </a>



            <a href="#contacts" class="menu-item" data-page="contacts">



                <span class="menu-icon">📞</span>



                <span>Kontakty</span>



            </a>
            <?php endif; ?>



            <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>

                <a href="#admin" class="menu-item" data-page="admin">

                    <span class="menu-icon">⚙️</span>

                    <span>Správa uživatelů</span>

                </a>

            <?php endif; ?>

        </div>



        <div class="user-info">
            <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
                <div class="view-switcher-label" style="font-size: 0.75rem; color: #888; margin-bottom: 5px; text-transform: uppercase; letter-spacing: 0.5px;">Zobrazit jako:</div>
                <div class="view-switcher" style="display: flex; gap: 5px; margin-bottom: 15px;">
                    <a href="index.php?action=switch_view&target=user" class="btn-switch" title="Zobrazit jako Makléř">
                        <i class="fas fa-user-tie"></i> Makléř
                    </a>
                    <a href="index.php?action=switch_view&target=poradce" class="btn-switch" title="Zobrazit jako Poradce">
                        <i class="fas fa-user-graduate"></i> Poradce
                    </a>
                </div>
            <?php endif; ?>

            <?php if (isset($_SESSION['original_role']) && $_SESSION['original_role'] === 'admin'): ?>
                <div class="view-switcher" style="margin-bottom: 15px;">
                    <a href="index.php?action=switch_view&target=admin" class="btn-switch btn-restore">
                        <i class="fas fa-undo-alt"></i> Zpět na Admina
                    </a>
                </div>
            <?php endif; ?>
            <div class="username"><?php echo htmlspecialchars($_SESSION['username']); ?></div>
            <div class="user-role"><span>Role:</span><?php echo htmlspecialchars($_SESSION['role'] === 'user' ? 'makler' : $_SESSION['role']); ?></div>
            <a href="logout.php" class="logout-link">Odhlásit se</a>
        </div>

    </div>

</div>

<style>

    .btn-switch {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 6px;
        padding: 8px 10px;
        background: rgba(255, 255, 255, 0.1);
        color: #ecf0f1;
        text-align: center;
        border-radius: 6px;
        text-decoration: none;
        font-size: 0.85rem;
        transition: all 0.2s ease;
        flex: 1;
        border: 1px solid rgba(255, 255, 255, 0.1);
    }
    
    .btn-switch:hover {
        background: rgba(52, 152, 219, 0.2);
        color: #3498db;
        border-color: #3498db;
        text-decoration: none;
        transform: translateY(-1px);
    }
    
    .btn-switch i {
        font-size: 0.9em;
    }

    .btn-restore {
        width: 100%;
        background: rgba(231, 76, 60, 0.15);
        color: #e74c3c;
        border-color: rgba(231, 76, 60, 0.3);
    }
    
    .btn-restore:hover {
        background: rgba(231, 76, 60, 0.25);
        color: #c0392b;
        border-color: #c0392b;
    }

    .menu-item.active {



        background-color: #3498db !important;



        color: white !important;



        border-radius: 8px;



        transform: translateX(5px);



        text-decoration: none; /* Prevent blue underline */



    }







    .menu-item {



        color: inherit; /* Ensure default color */



        text-decoration: none; /* Prevent underline */



    }







    .menu-item.active .menu-icon {



        filter: brightness(1.2);



    }







    .menu-item {



        transition: all 0.3s ease;



    }



</style>







<script>



    function updateActiveMenuItem() {



        // Get current hash or URL parameter



        const hash = window.location.hash.replace('#', '');



        const urlParams = new URLSearchParams(window.location.search);



        const page = urlParams.get('page');







        // Determine which page is active - prioritize hash over URL parameter



        let activePage;



        if (hash) {



            activePage = hash;



        } else if (page) {


            activePage = page;



        } else {



            activePage = 'dashboard';



        }



        



        console.log('Updating active menu item for:', activePage);







        // Remove active class from all menu items



        document.querySelectorAll('.menu-item').forEach(item => {



            item.classList.remove('active');



        });







        // Add active class to current menu item



        const activeMenuItem = document.querySelector(`[data-page="${activePage}"]`);



        if (activeMenuItem) {



            activeMenuItem.classList.add('active');



            console.log('Active menu item set for:', activePage);



        } else {



            console.log('No menu item found for:', activePage);



        }



    }







    // Update on page load



    document.addEventListener('DOMContentLoaded', function() {



        console.log('Sidebar script loaded');







        // Add click listener to Marvin title



        const marvinTitle = document.getElementById('marvin-title');



        if (marvinTitle) {



            marvinTitle.addEventListener('click', function() {



                window.location.href = 'index.php';



            });



        }







        // Wait a bit for the main page script to load



        setTimeout(function() {



            updateActiveMenuItem();



        }, 100);







        // Add click listeners to menu items



        document.querySelectorAll('.menu-item').forEach(item => {



            item.addEventListener('click', function(e) {



                const href = this.getAttribute('href');



                console.log('Menu item clicked:', href);



                



                // Handle hash links



                if (href.includes('#')) {



                    e.preventDefault();



                    const pageId = href.split('#')[1];



                    console.log('Setting hash to:', pageId);



                    



                    // Update URL hash



                    window.location.hash = pageId;



                    



                    // Show the page immediately



                    if (window.showActivePage) {



                        window.showActivePage(pageId);



                    } else {


                        console.log('showActivePage function not available yet');



                    }



                    



                    // Update active menu item



                    updateActiveMenuItem();



                }



                // For regular page links (like dashboard), let them navigate normally



            });



        });



    });







    // Handle hash changes



    window.addEventListener('hashchange', function() {



        console.log('Hash change detected');



        const hash = window.location.hash.replace('#', '');



        if (hash && window.showActivePage) {


            window.showActivePage(hash);



        }



        updateActiveMenuItem();



    });



</script>