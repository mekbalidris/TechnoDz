<?php
/**
 * NEXUS SHOP — Footer HTML commun
 */
?>
</main><!-- /#main-content -->

<!-- ═══════════════════════════════ FOOTER ═══════════════════════════════════ -->
<footer class="site-footer" role="contentinfo">
    <div class="container footer-grid">
        <div class="footer-brand">
            <span class="logo-text">NEXUS<em>SHOP</em></span>
            <p>Votre destination ultime pour le matériel informatique et gaming haut de gamme.</p>
            <div class="footer-socials">
                <a href="#" aria-label="Twitter / X">𝕏</a>
                <a href="#" aria-label="Discord">⌨</a>
                <a href="#" aria-label="YouTube">▶</a>
            </div>
        </div>

        <nav aria-label="Liens boutique">
            <h3>Boutique</h3>
            <ul role="list">
                <li><a href="<?= BASE_URL ?>/index.php?categorie=processeurs">Processeurs</a></li>
                <li><a href="<?= BASE_URL ?>/index.php?categorie=cartes-graphiques">Cartes Graphiques</a></li>
                <li><a href="<?= BASE_URL ?>/index.php?categorie=ram-memoire">RAM & Mémoire</a></li>
                <li><a href="<?= BASE_URL ?>/index.php?categorie=stockage">Stockage</a></li>
                <li><a href="<?= BASE_URL ?>/index.php?categorie=peripheriques">Périphériques</a></li>
            </ul>
        </nav>

        <nav aria-label="Liens compte">
            <h3>Mon Compte</h3>
            <ul role="list">
                <?php if (estConnecte()): ?>
                <li><a href="<?= BASE_URL ?>/cart.php">Mon Panier</a></li>
                <li><a href="<?= BASE_URL ?>/auth.php?action=logout">Déconnexion</a></li>
                <?php else: ?>
                <li><a href="<?= BASE_URL ?>/auth.php">Connexion</a></li>
                <li><a href="<?= BASE_URL ?>/auth.php?mode=register">Créer un compte</a></li>
                <?php endif; ?>
            </ul>
        </nav>

        <div class="footer-info">
            <h3>Infos</h3>
            <address>
                <p>📍 123 Rue de la Tech, 75001 Paris</p>
                <p>📧 <a href="mailto:contact@nexus.shop">contact@nexus.shop</a></p>
            </address>
            <p class="footer-payment">💳 Paiements sécurisés</p>
        </div>
    </div>

    <div class="footer-bottom container">
        <p>&copy; <?= date('Y') ?> <?= APP_NAME ?>. Tous droits réservés.</p>
        <p>Fait avec ♥ en PHP 8 · MySQL · Vanilla JS</p>
    </div>
</footer>

<script src="<?= BASE_URL ?>/assets/js/script.js"></script>
</body>
</html>
