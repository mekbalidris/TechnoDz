<?php
/**
 * NEXUS SHOP — API AJAX : Recherche dynamique
 * Endpoint : /api/search.php?q=rtx&categorie=cartes-graphiques&tri=prix_asc
 * Réponse  : JSON
 * Utilisé par : script.js (fetch API)
 */
declare(strict_types=1);

// Headers CORS et type de réponse
header('Content-Type: application/json; charset=UTF-8');
header('X-Content-Type-Options: nosniff');

require_once __DIR__ . '/../includes/config.php';

// Uniquement les requêtes GET
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['erreur' => 'Méthode non autorisée.']);
    exit;
}

$pdo = getPDO();

// Paramètres
$q         = trim($_GET['q']         ?? '');
$categorie = trim($_GET['categorie'] ?? '');
$tri       = $_GET['tri']             ?? 'nom';
$minPrix   = (float)($_GET['min_prix'] ?? 0);
$maxPrix   = (float)($_GET['max_prix'] ?? 9999);

// Whitelist tri
$trisOK = ['nom', 'prix_asc', 'prix_desc', 'nouveau'];
if (!in_array($tri, $trisOK, true)) $tri = 'nom';

$orderClause = match($tri) {
    'prix_asc'  => 'p.prix ASC',
    'prix_desc' => 'p.prix DESC',
    'nouveau'   => 'p.cree_le DESC',
    default     => 'p.nom ASC',
};

// Construction requête
$conditions = ['p.stock > 0'];
$params     = [];

if ($q !== '') {
    // Recherche partielle sur le nom (fallback si FULLTEXT pas disponible)
    $conditions[] = '(p.nom LIKE :q OR p.description LIKE :q2 OR p.marque LIKE :q3)';
    $params[':q']  = '%' . $q . '%';
    $params[':q2'] = '%' . $q . '%';
    $params[':q3'] = '%' . $q . '%';
}

if ($categorie !== '') {
    $conditions[] = 'c.slug = :slug';
    $params[':slug'] = $categorie;
}

if ($minPrix > 0) {
    $conditions[] = 'p.prix >= :min';
    $params[':min'] = $minPrix;
}
if ($maxPrix < 9999) {
    $conditions[] = 'p.prix <= :max';
    $params[':max'] = $maxPrix;
}

$whereSQL = 'WHERE ' . implode(' AND ', $conditions);

try {
    $stmt = $pdo->prepare("
        SELECT
            p.id, p.nom, p.slug, p.prix, p.image, p.est_vedette, p.stock,
            SUBSTRING(p.description, 1, 100) AS description_courte,
            c.nom  AS categorie_nom,
            c.slug AS categorie_slug
        FROM produits p
        JOIN categories c ON c.id = p.categorie_id
        $whereSQL
        ORDER BY $orderClause
        LIMIT 24
    ");

    foreach ($params as $key => $val) {
        $stmt->bindValue($key, $val);
    }
    $stmt->execute();
    $produits = $stmt->fetchAll();

    // Formatage de la réponse
    $resultat = array_map(fn($p) => [
        'id'             => (int)$p['id'],
        'nom'            => $p['nom'],
        'description'    => $p['description_courte'],
        'prix'           => (float)$p['prix'],
        'prix_formate'   => number_format((float)$p['prix'], 2, ',', ' ') . ' €',
        'image'          => BASE_URL . '/assets/images/products/' . $p['image'],
        'image_fallback' => BASE_URL . '/assets/images/products/' . DEFAULT_IMG,
        'url'            => BASE_URL . '/product.php?id=' . $p['id'],
        'categorie'      => $p['categorie_nom'],
        'est_vedette'    => (bool)$p['est_vedette'],
        'stock'          => (int)$p['stock'],
        'stock_bas'      => (int)$p['stock'] <= 5,
    ], $produits);

    echo json_encode([
        'succes'   => true,
        'total'    => count($resultat),
        'produits' => $resultat,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['erreur' => 'Erreur serveur. Réessayez plus tard.']);
}
