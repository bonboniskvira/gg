<?php
/** * LOGIKA PRO PROGRESS TRACKER - Opravená verze
 */
$todoProgress = 0;
if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
    // $_SERVER['DOCUMENT_ROOT'] zajistí, že najdeme todo.md v hlavním adresáři
    $todoFile = $_SERVER['DOCUMENT_ROOT'] . '/scratch.md';

    if (file_exists($todoFile)) {
        $todoContent = file_get_contents($todoFile);

        // Spočítá všechny [x] nebo [X]
        $done = preg_match_all('/\[x\]/i', $todoContent);

        // Spočítá [] nebo [ ] (s mezerou i bez)
        $pending = preg_match_all('/\[\s?\]/', $todoContent);

        $total = $done + $pending;

        if ($total > 0) {
            $todoProgress = round(($done / $total) * 100);
        }
    }
}
?>

<div class="sidebar" id="sidebar">
    <div class="sidebar-header" style="display: flex; align-items: center; justify-content: space-between; padding-right: 15px;">
        <div class="sidebar-title" id="marvin-title" style="cursor: pointer;">eDO Sys</div>

        <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
            <div class="admin-implementation-tracker" title="Progres systému: <?= $todoProgress ?>%">
                <div class="progress-pie" style="--p: <?= $todoProgress ?>; --c: #2ecc71;">
                    <span class="progress-value"><?= $todoProgress ?>%</span>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <div class="menu-items">
        <?php if (isset($_SESSION['role']) && strtolower(trim($_SESSION['role'])) === 'poradce'): ?>
            <div style="padding: 10px; color: yellow; background: #333; text-align: center; font-size: 0.8rem;">
                Poradce View Active
            </div>
        <?php endif; ?>

        <div class="menu-links">
            <?php
            $current_role_normalized = isset($_SESSION['role']) ? strtolower(trim($_SESSION['role'])) : '';
            if ($current_role_normalized !== 'poradce'):
                ?>
                <a href="#edo" class="menu-item" data-page="edo">
                    <span class="menu-icon"><i class="fas fa-graduation-cap"></i></span>
                    <span>eDO Start</span>
                </a>

                <a href="#list" class="menu-item" data-page="list">
                    <span class="menu-icon"><i class="fas fa-scroll"></i></span>
                    <span>Odborná zkouška</span>
                </a>

                <a href="#test" class="menu-item" data-page="test">
                    <span class="menu-icon"><i class="fas fa-file-alt"></i></span>
                    <span>Okruhy</span>
                </a>

                <a href="#courses" class="menu-item" data-page="courses">
                    <span class="menu-icon"><i class="fas fa-chalkboard-teacher"></i></span>
                    <span>Kurzy a školení</span>
                </a>

                <a href="#agreements" class="menu-item" data-page="agreements">
                    <span class="menu-icon"><i class="fas fa-file-contract"></i></span>
                    <span>Smlouvy</span>
                </a>

                <a href="#marketing" class="menu-item" data-page="marketing">
                    <span class="menu-icon"><i class="fas fa-bullhorn"></i></span>
                    <span>Marketing</span>
                </a>

                <a href="#pdfs" class="menu-item" data-page="pdfs">
                    <span class="menu-icon"><i class="fas fa-newspaper"></i></span>
                    <span>Magazíny</span>
                </a>

                <a href="#programs" class="menu-item" data-page="programs">
                    <span class="menu-icon"><i class="fas fa-robot"></i></span>
                    <span>AI</span>
                </a>

                <a href="#links" class="menu-item" data-page="links">
                    <span class="menu-icon"><i class="fas fa-link"></i></span>
                    <span>Odkazy</span>
                </a>

                <a href="#other" class="menu-item" data-page="other">
                    <span class="menu-icon"><i class="fas fa-folder"></i></span>
                    <span>Ostatní</span>
                </a>

                <a href="#contacts" class="menu-item" data-page="contacts">
                    <span class="menu-icon"><i class="fas fa-phone"></i></span>
                    <span>Kontakty</span>
                </a>
            <?php endif; ?>

            <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
                <a href="#contest" class="menu-item" data-page="contest">
                    <span class="menu-icon"><i class="fas fa-trophy"></i></span>
                    <span>Soutěž</span>
                </a>

                <a href="#admin" class="menu-item" data-page="admin">
                    <span class="menu-icon"><i class="fas fa-cogs"></i></span>
                    <span>Správa uživatelů</span>
                </a>
            <?php endif; ?>
        </div>

        <div class="user-info">
            <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
                <div class="view-switcher-label">Zobrazit jako:</div>
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
            <div class="user-role">Role: <?php echo htmlspecialchars($_SESSION['role'] === 'user' ? 'makler' : $_SESSION['role']); ?></div>
            <a href="logout.php" class="logout-link">Odhlásit se</a>
        </div>
    </div>
</div>

<style>
    /* TRACKER STYLES */
    .admin-implementation-tracker {
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .progress-pie {
        --w: 32px;
        width: var(--w);
        height: var(--w);
        position: relative;
        display: inline-grid;
        place-content: center;
        font-family: sans-serif;
    }

    .progress-pie:before {
        content: "";
        position: absolute;
        border-radius: 50%;
        inset: 0;
        background: conic-gradient(var(--c) calc(var(--p) * 1%), rgba(255,255,255,0.1) 0);
        -webkit-mask: radial-gradient(farthest-side, #0000 80%, #000 0);
        mask: radial-gradient(farthest-side, #0000 80%, #000 0);
    }

    .progress-value {
        font-size: 8px;
        font-weight: 800;
        color: #ecf0f1;
        z-index: 1;
    }

    /* PŮVODNÍ SIDEBAR STYLY */
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

    .btn-restore {
        width: 100%;
        background: rgba(231, 76, 60, 0.15);
        color: #e74c3c;
        border-color: rgba(231, 76, 60, 0.3);
    }

    .view-switcher-label {
        font-size: 0.75rem;
        color: #888;
        margin-bottom: 5px;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .menu-item.active {
        background-color: #3498db !important;
        color: white !important;
        border-radius: 8px;
        transform: translateX(5px);
        text-decoration: none;
    }

    .menu-item {
        color: inherit;
        text-decoration: none;
        transition: all 0.3s ease;
    }

    .menu-icon i {
        width: 20px;
        text-align: center;
        font-size: 16px;
    }
</style>

<script>
    // Tvůj původní JS script pro sidebar (ponechán beze změn)
    function updateActiveMenuItem() {
        const hash = window.location.hash.replace('#', '');
        const urlParams = new URLSearchParams(window.location.search);
        const page = urlParams.get('page');
        let activePage = hash || page || 'dashboard';

        document.querySelectorAll('.menu-item').forEach(item => {
            item.classList.remove('active');
        });

        const activeMenuItem = document.querySelector(`[data-page="${activePage}"]`);
        if (activeMenuItem) {
            activeMenuItem.classList.add('active');
        }
    }

    document.addEventListener('DOMContentLoaded', function() {
        const marvinTitle = document.getElementById('marvin-title');
        if (marvinTitle) {
            marvinTitle.addEventListener('click', function() {
                window.location.href = '#dashboard';
            });
        }

        setTimeout(updateActiveMenuItem, 100);

        document.querySelectorAll('.menu-item').forEach(item => {
            item.addEventListener('click', function(e) {
                const href = this.getAttribute('href');
                if (href.includes('#')) {
                    e.preventDefault();
                    const pageId = href.split('#')[1];
                    window.location.hash = pageId;
                    if (window.showActivePage) window.showActivePage(pageId);
                    updateActiveMenuItem();
                }
            });
        });
    });

    window.addEventListener('hashchange', updateActiveMenuItem);
</script>