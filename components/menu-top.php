<!-- Menu Top -->



<div class="menu-top">



    <div class="menu-top-item">

        <a href="#documents">

            <i class="fas fa-book"></i>

            <span class="menu-text">Knihovna</span>

        </a>

    </div>



    <div class="menu-top-item">

        <a href="#videos">

            <i class="fas fa-video"></i>

            <span class="menu-text">Videotéka</span>

        </a>

    </div>



    <div class="menu-top-item">

        <a href="#podcasts">

            <i class="fas fa-podcast"></i>

            <span class="menu-text">Podcasty</span>

        </a>

    </div>



    <div class="menu-top-item">

        <a href="#chatbot">

            <i class="fas fa-robot"></i>

            <span class="menu-text">Chatbot</span>

        </a>

    </div>



    <div class="menu-top-item">

        <a href="#bulletin">

            <i class="fas fa-clipboard"></i>

            <span class="menu-text">Nástěnka</span>

        </a>

    </div>



    <div class="menu-top-item">

        <a href="#search">

            <i class="fas fa-search"></i>

            <span class="menu-text">Vyhledat</span>

        </a>

    </div>



    <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>

        <div class="menu-top-item">

            <a href="#synara">

                <i class="fas fa-cogs"></i>

                <span class="menu-text">SYNARA</span>

            </a>

        </div>

    <?php endif; ?>



</div>



<!-- Main Content -->



<div class="main-content">



    <style>

        .menu-top-item i {

            margin-right: 8px;

            font-size: 16px;

        }



        @media (max-width: 450px) {

            .menu-text {

                display: none;

            }

            .menu-top-item i {

                margin-right: 0;

            }

            .menu-top-item a {

                padding: 8px !important;

                min-width: 36px;

                justify-content: center;

            }

        }

    </style>