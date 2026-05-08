<?php
/**
 * NEXUS SHOP — Panneau d'administration
 * Accès : réservé au rôle 'admin'
 * Fonctions : CRUD produits, liste commandes
 */
declare(strict_types=1);
require_once __DIR__ . '/includes/config.php';

// ── Vérification des droits ──────────────────────────────────────────────────
if (!estAdmin()) {
    setFlash('error', 'Accès refusé. Vous devez être administrateur.');
    redirect(BASE_URL . '/auth.php');
}

$pdo    = getPDO();
$action = $_GET['action'] ?? 'dashboard';
$errors = [];

// ── TRAITEMENT FORMULAIRE : AJOUT / ÉDITION PRODUIT ─────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_product'])) {

    $editId      = filter_input(INPUT_POST, 'edit_id',      FILTER_VALIDATE_INT);
    $nom         = trim($_POST['nom']         ?? '');
    $description = trim($_POST['description'] ?? '');
    $prix        = filter_input(INPUT_POST, 'prix',   FILTER_VALIDATE_FLOAT);
    $stock       = filter_input(INPUT_POST, 'stock',  FILTER_VALIDATE_INT);
    $categorieId = filter_input(INPUT_POST, 'categorie_id', FILTER_VALIDATE_INT);
    $marque      = trim($_POST['marque']      ?? '');
    $estVedette  = isset($_POST['est_vedette']) ? 1 : 0;

    // Spécifications JSON
    $specsRaw = trim($_POST['specifications'] ?? '');
    $specsJSON = null;
    if ($specsRaw) {
        $decoded = json_decode($specsRaw);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $errors[] = 'Les spécifications doivent être au format JSON valide.';
        } else {
            $specsJSON = $specsRaw;
        }
    }

    // Validation
    if (empty($nom))                        $errors[] = 'Le nom du produit est requis.';
    if (empty($description))                $errors[] = 'La description est requise.';
    if (!$prix || $prix <= 0)               $errors[] = 'Le prix doit être un nombre positif.';
    if ($stock === false || $stock < 0)     $errors[] = 'Le stock doit être un entier positif ou nul.';
    if (!$categorieId)                      $errors[] = 'Veuillez choisir une catégorie.';

    // Gestion de l'image uploadée
    $imageName = $_POST['image_actuelle'] ?? DEFAULT_IMG;

    if (!empty($_FILES['image']['name'])) {
        $ext      = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
        $allowed  = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
        $maxSize  = 5 * 1024 * 1024; // 5 Mo

        if (!in_array($ext, $allowed)) {
            $errors[] = 'Format image non autorisé. Utilisez : JPG, PNG, WEBP.';
        } elseif ($_FILES['image']['size'] > $maxSize) {
            $errors[] = 'L\'image ne doit pas dépasser 5 Mo.';
        } elseif ($_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $imageName = uniqid('prod_', true) . '.' . $ext;
            $dest      = UPLOAD_DIR . $imageName;

            if (!is_dir(UPLOAD_DIR)) mkdir(UPLOAD_DIR, 0755, true);

            if (!move_uploaded_file($_FILES['image']['tmp_name'], $dest)) {
                $errors[] = 'Erreur lors de l\'upload de l\'image.';
            }
        }
    }

    if (empty($errors)) {
        // Générer un slug unique
        $slug = slugify($nom);
        $slugUnique = $slug;
        $i = 1;
        while (true) {
            $check = $pdo->prepare('SELECT id FROM produits WHERE slug = ? AND id != ?');
            $check->execute([$slugUnique, $editId ?? 0]);
            if (!$check->fetch()) break;
            $slugUnique = $slug . '-' . $i++;
        }

        if ($editId) {
            // Mise à jour
            $stmt = $pdo->prepare('
                UPDATE produits
                SET nom=?, slug=?, description=?, prix=?, stock=?, categorie_id=?,
                    marque=?, image=?, specifications=?, est_vedette=?
                WHERE id=?
            ');
            $stmt->execute([$nom, $slugUnique, $description, $prix, $stock, $categorieId,
                            $marque, $imageName, $specsJSON, $estVedette, $editId]);
            setFlash('success', 'Produit «' . $nom . '» mis à jour avec succès.');
        } else {
            // Insertion
            $stmt = $pdo->prepare('
                INSERT INTO produits (nom, slug, description, prix, stock, categorie_id, marque, image, specifications, est_vedette)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ');
            $stmt->execute([$nom, $slugUnique, $description, $prix, $stock, $categorieId,
                            $marque, $imageName, $specsJSON, $estVedette]);
            setFlash('success', 'Produit «' . $nom . '» ajouté avec succès !');
        }
        redirect(BASE_URL . '/admin.php');
    }
}

// ── SUPPRESSION PRODUIT ──────────────────────────────────────────────────────
if ($action === 'delete' && isset($_GET['id'])) {
    $idDel = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
    if ($idDel) {
        $stmt = $pdo->prepare('DELETE FROM produits WHERE id = ?');
        $stmt->execute([$idDel]);
        setFlash('success', 'Produit supprimé.');
    }
    redirect(BASE_URL . '/admin.php');
}

// ── RÉCUPÉRATION DES DONNÉES ─────────────────────────────────────────────────
$categories = $pdo->query('SELECT * FROM categories ORDER BY nom')->fetchAll();

// Produit à éditer
$editProduit = null;
if ($action === 'edit' && isset($_GET['id'])) {
    $editId      = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
    $stmtEdit    = $pdo->prepare('SELECT * FROM produits WHERE id = ?');
    $stmtEdit->execute([$editId]);
    $editProduit = $stmtEdit->fetch();
}

// Stats dashboard
$stats = $pdo->query('
    SELECT
        (SELECT COUNT(*) FROM produits)      AS nb_produits,
        (SELECT COUNT(*) FROM utilisateurs)  AS nb_users,
        (SELECT COUNT(*) FROM panier)        AS nb_paniers,
        (SELECT SUM(stock) FROM produits)    AS stock_total
')->fetch();

// Liste des produits
$produits = $pdo->query('
    SELECT p.*, c.nom AS cat_nom
    FROM produits p
    JOIN categories c ON c.id = p.categorie_id
    ORDER BY p.cree_le DESC
')->fetchAll();

// ── Helper slug ──────────────────────────────────────────────────────────────
function slugify(string $str): string {
    $str = mb_strtolower(trim($str));
    $str = iconv('UTF-8', 'ASCII//TRANSLIT', $str);
    $str = preg_replace('/[^a-z0-9\s-]/', '', $str);
    $str = preg_replace('/[\s-]+/', '-', $str);
    return trim($str, '-');
}

$pageTitle = 'Administration — ' . APP_NAME;
require_once __DIR__ . '/includes/header.php';
?>

<div class="admin-page container">

    <!-- Header Admin -->
    <div class="admin-header">
        <h1>⚙ Administration</h1>
        <p>Connecté en tant qu'<strong><?= e($_SESSION['user_nom']) ?></strong> · <a href="<?= BASE_URL ?>/index.php">← Voir la boutique</a></p>
    </div>

    <!-- Erreurs -->
    <?php if (!empty($errors)): ?>
    <div class="alert alert--error" role="alert">
        <ul><?php foreach ($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?></ul>
    </div>
    <?php endif; ?>

    <!-- Statistiques rapides -->
    <div class="admin-stats">
        <div class="stat-card">
            <span class="stat-val"><?= $stats['nb_produits'] ?></span>
            <span class="stat-label">Produits</span>
        </div>
        <div class="stat-card">
            <span class="stat-val"><?= $stats['nb_users'] ?></span>
            <span class="stat-label">Utilisateurs</span>
        </div>
        <div class="stat-card">
            <span class="stat-val"><?= $stats['nb_paniers'] ?></span>
            <span class="stat-label">Articles en panier</span>
        </div>
        <div class="stat-card">
            <span class="stat-val"><?= $stats['stock_total'] ?></span>
            <span class="stat-label">Stock total</span>
        </div>
    </div>

    <!-- Formulaire Ajout / Édition produit -->
    <section class="admin-form-section">
        <h2><?= $editProduit ? '✏ Modifier le produit' : '+ Ajouter un produit' ?></h2>

        <form method="POST" action="<?= BASE_URL ?>/admin.php" enctype="multipart/form-data" class="admin-form" novalidate>
            <?php if ($editProduit): ?>
            <input type="hidden" name="edit_id"        value="<?= $editProduit['id'] ?>">
            <input type="hidden" name="image_actuelle" value="<?= e($editProduit['image']) ?>">
            <?php endif; ?>

            <div class="form-grid">
                <div class="form-group">
                    <label for="p-nom">Nom du produit *</label>
                    <input type="text" id="p-nom" name="nom" required
                           value="<?= e($editProduit['nom'] ?? $_POST['nom'] ?? '') ?>"
                           placeholder="Ex: NVIDIA GeForce RTX 5090">
                </div>

                <div class="form-group">
                    <label for="p-marque">Marque</label>
                    <input type="text" id="p-marque" name="marque"
                           value="<?= e($editProduit['marque'] ?? $_POST['marque'] ?? '') ?>"
                           placeholder="NVIDIA, AMD, Corsair…">
                </div>

                <div class="form-group">
                    <label for="p-cat">Catégorie *</label>
                    <select id="p-cat" name="categorie_id" required>
                        <option value="">— Choisir —</option>
                        <?php foreach ($categories as $cat): ?>
                        <option value="<?= $cat['id'] ?>"
                            <?= ($editProduit['categorie_id'] ?? $_POST['categorie_id'] ?? '') == $cat['id'] ? 'selected' : '' ?>>
                            <?= e($cat['nom']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="p-prix">Prix (€) *</label>
                    <input type="number" id="p-prix" name="prix" step="0.01" min="0" required
                           value="<?= e($editProduit['prix'] ?? $_POST['prix'] ?? '') ?>"
                           placeholder="199.99">
                </div>

                <div class="form-group">
                    <label for="p-stock">Stock *</label>
                    <input type="number" id="p-stock" name="stock" min="0" required
                           value="<?= e($editProduit['stock'] ?? $_POST['stock'] ?? '') ?>"
                           placeholder="50">
                </div>

                <div class="form-group form-check-group">
                    <label class="checkbox-label">
                        <input type="checkbox" name="est_vedette" value="1"
                               <?= ($editProduit['est_vedette'] ?? 0) ? 'checked' : '' ?>>
                        <span>Produit vedette ⭐</span>
                    </label>
                </div>
            </div>

            <div class="form-group">
                <label for="p-desc">Description *</label>
                <textarea id="p-desc" name="description" rows="4" required
                          placeholder="Description complète du produit…"><?= e($editProduit['description'] ?? $_POST['description'] ?? '') ?></textarea>
            </div>

            <div class="form-group">
                <label for="p-specs">Spécifications techniques (JSON)</label>
                <textarea id="p-specs" name="specifications" rows="5"
                          placeholder='{"VRAM":"16 Go","TDP":"304W","Interface":"PCIe 4.0"}'><?= e($editProduit['specifications'] ?? $_POST['specifications'] ?? '') ?></textarea>
                <p class="field-hint">Format JSON — ex: <code>{"Cœurs":"16","Socket":"AM5"}</code></p>
            </div>

            <div class="form-group">
                <label for="p-image">Image produit</label>
                <?php if (!empty($editProduit['image']) && $editProduit['image'] !== DEFAULT_IMG): ?>
                <div class="current-image">
                    <img src="<?= BASE_URL ?>/assets/images/products/<?= e($editProduit['image']) ?>"
                         alt="Image actuelle" width="120" height="90">
                    <span>Image actuelle</span>
                </div>
                <?php endif; ?>
                <input type="file" id="p-image" name="image" accept="image/*">
                <p class="field-hint">Formats acceptés : JPG, PNG, WEBP · Max 5 Mo</p>
            </div>

            <div class="form-actions">
                <button type="submit" name="save_product" class="btn btn-primary btn-lg">
                    <?= $editProduit ? '💾 Enregistrer les modifications' : '+ Ajouter le produit' ?>
                </button>
                <?php if ($editProduit): ?>
                <a href="<?= BASE_URL ?>/admin.php" class="btn btn-ghost btn-lg">Annuler</a>
                <?php endif; ?>
            </div>
        </form>
    </section>

    <!-- Liste des produits -->
    <section class="admin-products-section">
        <h2>📦 Catalogue (<?= count($produits) ?> produits)</h2>

        <div class="admin-table-wrapper">
            <table class="admin-table" role="grid">
                <thead>
                    <tr>
                        <th scope="col">#</th>
                        <th scope="col">Image</th>
                        <th scope="col">Nom</th>
                        <th scope="col">Catégorie</th>
                        <th scope="col">Prix</th>
                        <th scope="col">Stock</th>
                        <th scope="col">Vedette</th>
                        <th scope="col">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($produits as $p): ?>
                    <tr class="<?= $p['stock'] === 0 ? 'row--rupture' : '' ?>">
                        <td><?= $p['id'] ?></td>
                        <td>
                            <img src="<?= BASE_URL ?>/assets/images/products/<?= e($p['image']) ?>"
                                 alt="" width="50" height="40" loading="lazy"
                                 onerror="this.src='<?= BASE_URL ?>/assets/images/products/default.png'">
                        </td>
                        <td>
                            <a href="<?= BASE_URL ?>/product.php?id=<?= $p['id'] ?>" target="_blank">
                                <?= e($p['nom']) ?>
                            </a>
                        </td>
                        <td><?= e($p['cat_nom']) ?></td>
                        <td><?= formatPrix($p['prix']) ?></td>
                        <td class="<?= $p['stock'] <= 5 ? 'stock--low' : '' ?>"><?= $p['stock'] ?></td>
                        <td><?= $p['est_vedette'] ? '⭐' : '—' ?></td>
                        <td class="table-actions">
                            <a href="<?= BASE_URL ?>/admin.php?action=edit&id=<?= $p['id'] ?>"
                               class="btn btn-outline btn-xs" aria-label="Modifier <?= e($p['nom']) ?>">
                                ✏ Éditer
                            </a>
                            <a href="<?= BASE_URL ?>/admin.php?action=delete&id=<?= $p['id'] ?>"
                               class="btn btn-danger btn-xs"
                               onclick="return confirm('Supprimer définitivement «<?= e(addslashes($p['nom'])) ?>» ?')"
                               aria-label="Supprimer <?= e($p['nom']) ?>">
                                🗑 Suppr.
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>

</div><!-- /.admin-page -->

<?php require_once __DIR__ . '/includes/footer.php'; ?>
