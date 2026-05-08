<?php
/**
 * NEXUS SHOP — Gestion du Panier
 * Actions : add, update, remove, clear
 * Stockage : $_SESSION['panier'] + table `panier` (si connecté)
 * Format session : ['panier'][produit_id] => ['nom'=>..., 'prix'=>..., 'quantite'=>..., 'image'=>...]
 */
declare(strict_types=1);
require_once __DIR__ . '/includes/config.php';

$pdo    = getPDO();
$action = $_POST['action'] ?? $_GET['action'] ?? '';

// ── TRAITEMENT DES ACTIONS ───────────────────────────────────────────────────
if ($action && $_SERVER['REQUEST_METHOD'] === 'POST') {

    $produitId = filter_input(INPUT_POST, 'produit_id', FILTER_VALIDATE_INT);
    $quantite  = max(1, (int)($_POST['quantite'] ?? 1));
    $redirect  = $_POST['redirect'] ?? BASE_URL . '/cart.php';

    switch ($action) {

        // ── Ajouter au panier ──────────────────────────────────────────────
        case 'add':
            if (!estConnecte()) {
                setFlash('warning', 'Connectez-vous pour ajouter au panier.');
                redirect(BASE_URL . '/auth.php?redirect=' . urlencode($redirect));
            }

            if (!$produitId) break;

            // Vérifier que le produit existe et est en stock
            $stmt = $pdo->prepare('SELECT id, nom, prix, image, stock FROM produits WHERE id = ? AND stock > 0');
            $stmt->execute([$produitId]);
            $produit = $stmt->fetch();

            if (!$produit) {
                setFlash('error', 'Produit introuvable ou en rupture de stock.');
                break;
            }

            // Quantité max = stock disponible
            $qteSession = $_SESSION['panier'][$produitId]['quantite'] ?? 0;
            $qteFinal   = min($qteSession + $quantite, $produit['stock']);

            // Session
            $_SESSION['panier'][$produitId] = [
                'nom'      => $produit['nom'],
                'prix'     => (float)$produit['prix'],
                'quantite' => $qteFinal,
                'image'    => $produit['image'],
                'stock'    => $produit['stock'],
            ];

            // BDD (si connecté)
            $stmtBDD = $pdo->prepare('
                INSERT INTO panier (utilisateur_id, produit_id, quantite)
                VALUES (?, ?, ?)
                ON DUPLICATE KEY UPDATE quantite = ?
            ');
            $stmtBDD->execute([$_SESSION['user_id'], $produitId, $qteFinal, $qteFinal]);

            setFlash('success', '✓ «' . $produit['nom'] . '» ajouté au panier.');
            break;

        // ── Mettre à jour la quantité ──────────────────────────────────────
        case 'update':
            if (!$produitId || !isset($_SESSION['panier'][$produitId])) break;

            if ($quantite <= 0) {
                // Retirer si quantité = 0
                unset($_SESSION['panier'][$produitId]);
                if (estConnecte()) {
                    $stmt = $pdo->prepare('DELETE FROM panier WHERE utilisateur_id=? AND produit_id=?');
                    $stmt->execute([$_SESSION['user_id'], $produitId]);
                }
            } else {
                $maxStock = $_SESSION['panier'][$produitId]['stock'] ?? 99;
                $qteFinal = min($quantite, $maxStock);
                $_SESSION['panier'][$produitId]['quantite'] = $qteFinal;

                if (estConnecte()) {
                    $stmt = $pdo->prepare('UPDATE panier SET quantite=? WHERE utilisateur_id=? AND produit_id=?');
                    $stmt->execute([$qteFinal, $_SESSION['user_id'], $produitId]);
                }
            }
            $redirect = BASE_URL . '/cart.php';
            break;

        // ── Retirer un article ─────────────────────────────────────────────
        case 'remove':
            if ($produitId && isset($_SESSION['panier'][$produitId])) {
                $nomProduit = $_SESSION['panier'][$produitId]['nom'];
                unset($_SESSION['panier'][$produitId]);

                if (estConnecte()) {
                    $stmt = $pdo->prepare('DELETE FROM panier WHERE utilisateur_id=? AND produit_id=?');
                    $stmt->execute([$_SESSION['user_id'], $produitId]);
                }
                setFlash('success', '«' . $nomProduit . '» retiré du panier.');
            }
            $redirect = BASE_URL . '/cart.php';
            break;

        // ── Vider le panier ────────────────────────────────────────────────
        case 'clear':
            $_SESSION['panier'] = [];
            if (estConnecte()) {
                $stmt = $pdo->prepare('DELETE FROM panier WHERE utilisateur_id = ?');
                $stmt->execute([$_SESSION['user_id']]);
            }
            setFlash('success', 'Panier vidé.');
            $redirect = BASE_URL . '/cart.php';
            break;
    }

    redirect(filter_var($redirect, FILTER_VALIDATE_URL) ? $redirect : BASE_URL . '/cart.php');
}

// ── Charger le panier depuis la BDD si connecté (synchronisation) ────────────
if (estConnecte()) {
    $stmt = $pdo->prepare('SELECT * FROM vue_panier WHERE utilisateur_id = ?');
    $stmt->execute([$_SESSION['user_id']]);
    $lignesBDD = $stmt->fetchAll();

    // Reconstruire le panier session depuis la BDD (source de vérité)
    $_SESSION['panier'] = [];
    foreach ($lignesBDD as $ligne) {
        $_SESSION['panier'][$ligne['produit_id']] = [
            'nom'      => $ligne['produit_nom'],
            'prix'     => (float)$ligne['prix_unitaire'],
            'quantite' => (int)$ligne['quantite'],
            'image'    => $ligne['image'],
            'stock'    => (int)$ligne['stock'],
        ];
    }
}

// ── Calcul des totaux ────────────────────────────────────────────────────────
$panier     = $_SESSION['panier'] ?? [];
$totalHT    = 0.0;
$totalQte   = 0;
foreach ($panier as $item) {
    $totalHT  += $item['prix'] * $item['quantite'];
    $totalQte += $item['quantite'];
}
$livraison = $totalHT >= 99 ? 0.0 : 9.99;
$totalTTC  = $totalHT + $livraison;

// ── Page ─────────────────────────────────────────────────────────────────────
$pageTitle = 'Mon Panier — ' . APP_NAME;
require_once __DIR__ . '/includes/header.php';
?>

<div class="cart-page container">
    <h1>🛒 Mon Panier <span class="cart-count-title">(<?= $totalQte ?> article<?= $totalQte > 1 ? 's' : '' ?>)</span></h1>

    <?php if (empty($panier)): ?>
    <!-- Panier vide -->
    <div class="cart-empty">
        <div class="cart-empty-icon" aria-hidden="true">🛒</div>
        <h2>Votre panier est vide</h2>
        <p>Découvrez notre catalogue et ajoutez des produits pour commencer votre commande.</p>
        <a href="<?= BASE_URL ?>/index.php" class="btn btn-primary btn-lg">Explorer la boutique</a>
    </div>

    <?php else: ?>
    <!-- Panier avec articles -->
    <div class="cart-layout">

        <!-- Liste des articles -->
        <section class="cart-items" aria-label="Articles dans le panier">

            <form method="POST" action="<?= BASE_URL ?>/cart.php" id="cart-form">
                <input type="hidden" name="action" value="clear">
                <div class="cart-items-header">
                    <span>Produit</span>
                    <span>Prix unit.</span>
                    <span>Quantité</span>
                    <span>Sous-total</span>
                    <span></span>
                </div>

                <?php foreach ($panier as $produitId => $item): ?>
                <div class="cart-item" data-product-id="<?= (int)$produitId ?>">
                    <div class="cart-item-product">
                        <img src="<?= BASE_URL ?>/assets/images/products/<?= e($item['image']) ?>"
                             alt="<?= e($item['nom']) ?>"
                             width="80" height="70" loading="lazy"
                             onerror="this.src='<?= BASE_URL ?>/assets/images/products/default.png'">
                        <div class="cart-item-details">
                            <p class="cart-item-name">
                                <a href="<?= BASE_URL ?>/product.php?id=<?= (int)$produitId ?>"><?= e($item['nom']) ?></a>
                            </p>
                            <p class="cart-item-stock <?= $item['stock'] <= 5 ? 'stock--low' : '' ?>">
                                <?= $item['stock'] > 0 ? 'En stock' : 'Rupture' ?>
                            </p>
                        </div>
                    </div>

                    <span class="cart-item-price"><?= formatPrix($item['prix']) ?></span>

                    <!-- Contrôle quantité inline -->
                    <form method="POST" action="<?= BASE_URL ?>/cart.php" class="cart-qty-form">
                        <input type="hidden" name="action"     value="update">
                        <input type="hidden" name="produit_id" value="<?= (int)$produitId ?>">
                        <div class="qty-control qty-control--sm">
                            <button type="submit" name="quantite" value="<?= $item['quantite'] - 1 ?>"
                                    class="qty-btn" aria-label="Retirer un">−</button>
                            <span class="qty-display" aria-live="polite"><?= $item['quantite'] ?></span>
                            <button type="submit" name="quantite" value="<?= min($item['quantite'] + 1, $item['stock']) ?>"
                                    class="qty-btn" aria-label="Ajouter un"
                                    <?= $item['quantite'] >= $item['stock'] ? 'disabled' : '' ?>>+</button>
                        </div>
                    </form>

                    <span class="cart-item-subtotal">
                        <?= formatPrix($item['prix'] * $item['quantite']) ?>
                    </span>

                    <!-- Retirer -->
                    <form method="POST" action="<?= BASE_URL ?>/cart.php">
                        <input type="hidden" name="action"     value="remove">
                        <input type="hidden" name="produit_id" value="<?= (int)$produitId ?>">
                        <button type="submit" class="btn-remove" aria-label="Retirer <?= e($item['nom']) ?> du panier">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6"/><path d="M14 11v6"/><path d="M9 6V4h6v2"/></svg>
                        </button>
                    </form>
                </div>
                <?php endforeach; ?>
            </form>

            <!-- Vider tout le panier -->
            <div class="cart-actions-bottom">
                <form method="POST" action="<?= BASE_URL ?>/cart.php">
                    <input type="hidden" name="action" value="clear">
                    <button type="submit" class="btn btn-ghost btn-sm"
                            onclick="return confirm('Vider tout le panier ?')">
                        🗑 Vider le panier
                    </button>
                </form>
                <a href="<?= BASE_URL ?>/index.php" class="btn btn-outline btn-sm">← Continuer mes achats</a>
            </div>
        </section>

        <!-- Récapitulatif commande -->
        <aside class="cart-summary" aria-label="Récapitulatif de commande">
            <h2>Récapitulatif</h2>

            <div class="summary-line">
                <span>Sous-total (<?= $totalQte ?> article<?= $totalQte > 1 ? 's' : '' ?>)</span>
                <span><?= formatPrix($totalHT) ?></span>
            </div>
            <div class="summary-line">
                <span>Livraison</span>
                <span class="<?= $livraison === 0.0 ? 'free-shipping' : '' ?>">
                    <?= $livraison === 0.0 ? '✓ Gratuite' : formatPrix($livraison) ?>
                </span>
            </div>
            <?php if ($livraison > 0): ?>
            <p class="shipping-tip">
                Ajoutez <?= formatPrix(99 - $totalHT) ?> pour bénéficier de la livraison gratuite !
            </p>
            <?php endif; ?>

            <div class="summary-divider"></div>

            <div class="summary-total">
                <span>Total TTC</span>
                <span><?= formatPrix($totalTTC) ?></span>
            </div>

            <?php if (estConnecte()): ?>
            <button class="btn btn-primary btn-lg btn-full" onclick="alert('Fonctionnalité paiement à intégrer (Stripe, PayPal…)')">
                ✓ Passer la commande
            </button>
            <?php else: ?>
            <a href="<?= BASE_URL ?>/auth.php?redirect=<?= urlencode(BASE_URL . '/cart.php') ?>"
               class="btn btn-primary btn-lg btn-full">
                Connexion pour commander
            </a>
            <?php endif; ?>

            <div class="secure-badges">
                <span>🔒 Paiement sécurisé SSL</span>
                <span>📦 Livraison 48h/72h</span>
                <span>↩ Retour 30 jours</span>
            </div>
        </aside>

    </div><!-- /.cart-layout -->
    <?php endif; ?>
</div><!-- /.cart-page -->

<?php require_once __DIR__ . '/includes/footer.php'; ?>
