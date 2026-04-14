<?php
require 'connexion.php';

// Liste de tous les pays pour le menu déroulant
$pays_list = $pdo->query("SELECT id_pays, nom FROM PAYS ORDER BY nom")->fetchAll(PDO::FETCH_ASSOC);

// Pays sélectionné
$id_pays_sel = isset($_GET['id_pays']) ? (int)$_GET['id_pays'] : ($pays_list[0]['id_pays'] ?? null);
$cat_sel     = isset($_GET['categorie']) ? $_GET['categorie'] : '';

// Infos du pays sélectionné
$stmt = $pdo->prepare("SELECT pa.nom, pa.continent, r.nom as region FROM PAYS pa JOIN REGION_FAO r ON pa.id_region = r.id_region WHERE pa.id_pays = ?");
$stmt->execute([$id_pays_sel]);
$pays_info = $stmt->fetch(PDO::FETCH_ASSOC);

// Liste des catégories
$categories = $pdo->query("SELECT * FROM CATEGORIE_CULTURE ORDER BY nom")->fetchAll(PDO::FETCH_ASSOC);

// Historique productions avec filtre catégorie
$sql_prod = "
    SELECT c.nom as culture, cc.nom as categorie, pr.annee, pr.quantite_t, pr.superficie_ha, pr.rendement_kg_ha
    FROM PRODUCTION pr
    JOIN CULTURE c ON pr.id_culture = c.id_culture
    JOIN CATEGORIE_CULTURE cc ON c.id_categorie = cc.id_categorie
    WHERE pr.id_pays = ?
    " . ($cat_sel ? "AND cc.nom = ?" : "") . "
    ORDER BY c.nom, pr.annee
";
$stmt2 = $pdo->prepare($sql_prod);
if ($cat_sel) {
    $stmt2->execute([$id_pays_sel, $cat_sel]);
} else {
    $stmt2->execute([$id_pays_sel]);
}
$productions = $stmt2->fetchAll(PDO::FETCH_ASSOC);

// Grouper par culture
$by_culture = [];
foreach ($productions as $row) {
    $by_culture[$row['culture']][] = $row;
}

// Total productions pour ce pays
$total_pays = $pdo->prepare("SELECT SUM(quantite_t) FROM PRODUCTION WHERE id_pays = ?");
$total_pays->execute([$id_pays_sel]);
$total_val = $total_pays->fetchColumn();

// Pagination
$page     = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$per_page = 20;
$total_rows = count($productions);
$total_pages = ceil($total_rows / $per_page);
$productions_page = array_slice($productions, ($page - 1) * $per_page, $per_page);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Fiche Pays — AgriStat FAO</title>
  <link rel="stylesheet" href="css/style.css">
</head>
<body>

<!-- NAVBAR -->
<nav class="navbar">
  <a class="navbar-brand" href="index.php">
    <span class="logo-icon">🌾</span>
    <span>Agri<em>Stat</em> FAO</span>
  </a>
  <ul class="nav-links">
    <li><a href="index.php">Tableau de bord</a></li>
    <li><a href="pays.php" class="active">Pays</a></li>
    <li><a href="culture.php">Cultures</a></li>
    <li><a href="comparaison.php">Comparaison</a></li>
    <li><a href="stats.php">Statistiques</a></li>
  </ul>
</nav>

<!-- HERO -->
<section class="hero">
  <div class="hero-content">
    <div class="hero-badge">🌍 Fiche Pays</div>
    <h1><?= htmlspecialchars($pays_info['nom'] ?? 'Pays') ?></h1>
    <p><?= htmlspecialchars($pays_info['region'] ?? '') ?> — <?= htmlspecialchars($pays_info['continent'] ?? '') ?></p>
  </div>
</section>

<!-- STATS BAR -->
<div class="stats-bar">
  <div class="stats-bar-inner">
    <div class="stat-pill">
      <div class="icon">📦</div>
      <div class="info">
        <div class="val"><?= number_format($total_val / 1000000, 2, ',', ' ') ?> Mt</div>
        <div class="lbl">Production totale</div>
      </div>
    </div>
    <div class="stat-pill">
      <div class="icon">📋</div>
      <div class="info">
        <div class="val"><?= $total_rows ?> entrées</div>
        <div class="lbl">Données disponibles</div>
      </div>
    </div>
    <div class="stat-pill">
      <div class="icon">🌱</div>
      <div class="info">
        <div class="val"><?= count($by_culture) ?> cultures</div>
        <div class="lbl">Cultures produites</div>
      </div>
    </div>
  </div>
</div>

<!-- FORMULAIRE FILTRES -->
<div style="max-width:1200px;margin:2rem auto;padding:0 2rem;">
  <form method="GET" style="display:flex;gap:1rem;flex-wrap:wrap;margin-bottom:1.5rem;">
    <div style="display:flex;flex-direction:column;gap:4px;">
      <label style="font-size:0.75rem;font-weight:600;color:var(--gray-600);text-transform:uppercase;letter-spacing:0.05em;">Pays</label>
      <select name="id_pays" onchange="this.form.submit()" class="form-select">
        <?php foreach ($pays_list as $p): ?>
          <option value="<?= $p['id_pays'] ?>" <?= $p['id_pays'] == $id_pays_sel ? 'selected' : '' ?>>
            <?= htmlspecialchars($p['nom']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div style="display:flex;flex-direction:column;gap:4px;">
      <label style="font-size:0.75rem;font-weight:600;color:var(--gray-600);text-transform:uppercase;letter-spacing:0.05em;">Catégorie</label>
      <select name="categorie" onchange="this.form.submit()" class="form-select">
        <option value="">Toutes les catégories</option>
        <?php foreach ($categories as $cat): ?>
          <option value="<?= htmlspecialchars($cat['nom']) ?>" <?= $cat['nom'] == $cat_sel ? 'selected' : '' ?>>
            <?= htmlspecialchars($cat['nom']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <input type="hidden" name="page" value="1">
  </form>

  <!-- TABLEAU PRODUCTIONS -->
  <div class="card">
    <div class="card-header">
      <h2>📊 Historique des productions</h2>
      <span class="badge"><?= $total_rows ?> lignes</span>
    </div>
    <?php if (empty($productions_page)): ?>
      <p style="padding:2rem;color:var(--gray-600);text-align:center;">Aucune donnée disponible pour ce pays.</p>
    <?php else: ?>
    <div style="overflow-x:auto;">
      <table class="top5-table" style="min-width:600px;">
        <thead>
          <tr>
            <th>Culture</th>
            <th>Catégorie</th>
            <th>Année</th>
            <th>Production (t)</th>
            <th>Superficie (ha)</th>
            <th>Rendement (kg/ha)</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($productions_page as $row): ?>
          <tr>
            <td><strong><?= htmlspecialchars($row['culture']) ?></strong></td>
            <td><span style="font-size:0.78rem;color:var(--green-light);font-weight:600;"><?= htmlspecialchars($row['categorie']) ?></span></td>
            <td><?= $row['annee'] ?></td>
            <td><?= $row['quantite_t'] ? number_format($row['quantite_t'], 0, ',', ' ') : '—' ?></td>
            <td><?= $row['superficie_ha'] ? number_format($row['superficie_ha'], 0, ',', ' ') : '—' ?></td>
            <td><?= $row['rendement_kg_ha'] ? number_format($row['rendement_kg_ha'], 2, ',', ' ') : '—' ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <!-- PAGINATION -->
    <?php if ($total_pages > 1): ?>
    <div class="pagination">
      <?php for ($i = 1; $i <= $total_pages; $i++): ?>
        <a href="?id_pays=<?= $id_pays_sel ?>&categorie=<?= urlencode($cat_sel) ?>&page=<?= $i ?>"
           class="page-btn <?= $i == $page ? 'active' : '' ?>">
          <?= $i ?>
        </a>
      <?php endfor; ?>
    </div>
    <?php endif; ?>
    <?php endif; ?>
  </div>
</div>

<footer class="footer">
  <p>Données : <a href="https://www.fao.org/faostat" target="_blank">FAOSTAT — FAO</a> &nbsp;|&nbsp; Projet BD M1 Informatique &amp; Big Data &nbsp;|&nbsp; <?= date('Y') ?></p>
</footer>

</body>
</html>