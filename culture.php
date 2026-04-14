<?php
require 'connexion.php';

// Liste des cultures
$cultures_list = $pdo->query("SELECT id_culture, nom FROM CULTURE ORDER BY nom")->fetchAll(PDO::FETCH_ASSOC);

// Culture sélectionnée
$id_culture_sel = isset($_GET['id_culture']) ? (int) $_GET['id_culture'] : ($cultures_list[0]['id_culture'] ?? null);
$annee_sel = isset($_GET['annee']) ? (int) $_GET['annee'] : 2022;

// Infos culture
$stmt = $pdo->prepare("SELECT c.nom, cc.nom as categorie FROM CULTURE c JOIN CATEGORIE_CULTURE cc ON c.id_categorie = cc.id_categorie WHERE c.id_culture = ?");
$stmt->execute([$id_culture_sel]);
$culture_info = $stmt->fetch(PDO::FETCH_ASSOC);

// Top 10 producteurs pour l'année choisie
$stmt2 = $pdo->prepare("
    SELECT pa.nom, pr.quantite_t, pr.rendement_kg_ha
    FROM PRODUCTION pr
    JOIN PAYS pa ON pr.id_pays = pa.id_pays
    WHERE pr.id_culture = ? AND pr.annee = ?
    ORDER BY pr.quantite_t DESC
    LIMIT 10
");
$stmt2->execute([$id_culture_sel, $annee_sel]);
$top10 = $stmt2->fetchAll(PDO::FETCH_ASSOC);
$max_prod = !empty($top10) ? $top10[0]['quantite_t'] : 1;

// Évolution mondiale
$stmt3 = $pdo->prepare("
    SELECT annee, SUM(quantite_t) as total
    FROM PRODUCTION
    WHERE id_culture = ?
    GROUP BY annee
    ORDER BY annee
");
$stmt3->execute([$id_culture_sel]);
$evolution = $stmt3->fetchAll(PDO::FETCH_ASSOC);

// Coordonnées des pays (fallback sans colonne lat/lng en BDD)
$coords = [
  'Algeria' => [28.03, 1.66],
  'Argentina' => [-38.42, -63.62],
  'Brazil' => [-14.24, -51.93],
  'China' => [35.86, 104.20],
  'Egypt' => [26.82, 30.80],
  'France' => [46.23, 2.21],
  'Germany' => [51.17, 10.45],
  'India' => [20.59, 78.96],
  'Indonesia' => [-0.79, 113.92],
  'Italy' => [41.87, 12.57],
  'Morocco' => [31.79, -7.09],
  'Netherlands (Kingdom of the)' => [52.13, 5.29],
  'Poland' => [51.92, 19.15],
  'Romania' => [45.94, 24.97],
  'Spain' => [40.46, -3.75],
  'Tunisia' => [33.89, 9.54],
  'turkiye' => [38.96, 35.24],
  'Ukraine' => [48.38, 31.17],
  'United States of America' => [37.09, -95.71],
  'Viet Nam' => [14.06, 108.28],
];

$stmt4 = $pdo->prepare("
    SELECT pa.nom, pr.quantite_t,
           RANK() OVER (ORDER BY pr.quantite_t DESC) as rang
    FROM PRODUCTION pr
    JOIN PAYS pa ON pr.id_pays = pa.id_pays
    WHERE pr.id_culture = ? AND pr.annee = ?
    ORDER BY pr.quantite_t DESC
    LIMIT 10
");
$stmt4->execute([$id_culture_sel, $annee_sel]);
$carte_raw = $stmt4->fetchAll(PDO::FETCH_ASSOC);
$max_carte = !empty($carte_raw) ? $carte_raw[0]['quantite_t'] : 1;

// Injecte les coordonnées
$carte_data = [];
foreach ($carte_raw as $row) {
  $nom = $row['nom'];
  if (isset($coords[$nom])) {
    $row['latitude'] = $coords[$nom][0];
    $row['longitude'] = $coords[$nom][1];
    $carte_data[] = $row;
  }
}
// Années disponibles
$annees = $pdo->query("SELECT DISTINCT annee FROM PRODUCTION ORDER BY annee ASC")->fetchAll(PDO::FETCH_COLUMN);
?>
<!DOCTYPE html>
<html lang="fr">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Fiche Culture — AgriStat FAO</title>
  <link rel="stylesheet" href="css/style.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.css" />
</head>

<body>

  <nav class="navbar">
    <a class="navbar-brand" href="index.php">
      <span class="logo-icon">🌾</span>
      <span>Agri<em>Stat</em> FAO</span>
    </a>
    <ul class="nav-links">
      <li><a href="index.php">Tableau de bord</a></li>
      <li><a href="pays.php">Pays</a></li>
      <li><a href="culture.php" class="active">Cultures</a></li>
      <li><a href="comparaison.php">Comparaison</a></li>
      <li><a href="stats.php">Statistiques</a></li>
    </ul>
  </nav>

  <section class="hero">
    <div class="hero-content">
      <div class="hero-badge">🌱 Fiche Culture</div>
      <h1><?= htmlspecialchars($culture_info['nom'] ?? 'Culture') ?></h1>
      <p>Catégorie : <?= htmlspecialchars($culture_info['categorie'] ?? '') ?></p>
    </div>
  </section>

  <div style="max-width:1200px;margin:2rem auto;padding:0 2rem;">

    <!-- FILTRES -->
    <form method="GET" style="display:flex;gap:1rem;flex-wrap:wrap;margin-bottom:1.5rem;">
      <div style="display:flex;flex-direction:column;gap:4px;">
        <label
          style="font-size:0.75rem;font-weight:600;color:var(--gray-600);text-transform:uppercase;letter-spacing:0.05em;">Culture</label>
        <select name="id_culture" onchange="this.form.submit()" class="form-select">
          <?php foreach ($cultures_list as $c): ?>
            <option value="<?= $c['id_culture'] ?>" <?= $c['id_culture'] == $id_culture_sel ? 'selected' : '' ?>>
              <?= htmlspecialchars($c['nom']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div style="display:flex;flex-direction:column;gap:4px;">
        <label
          style="font-size:0.75rem;font-weight:600;color:var(--gray-600);text-transform:uppercase;letter-spacing:0.05em;">Année</label>
        <select name="annee" onchange="this.form.submit()" class="form-select">
          <?php foreach ($annees as $a): ?>
            <option value="<?= $a ?>" <?= $a == $annee_sel ? 'selected' : '' ?>><?= $a ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </form>

    <!-- GRILLE TOP 10 + ÉVOLUTION -->
    <div style="display:grid;grid-template-columns:1fr 380px;gap:1.5rem;">

      <!-- TOP 10 -->
      <div class="card">
        <div class="card-header">
          <h2>🏆 Top 10 producteurs</h2>
          <span class="badge">Année <?= $annee_sel ?></span>
        </div>
        <?php if (empty($top10)): ?>
          <p style="padding:2rem;color:var(--gray-600);text-align:center;">Aucune donnée pour cette année.</p>
        <?php else: ?>
          <table class="top5-table">
            <thead>
              <tr>
                <th>#</th>
                <th>Pays</th>
                <th>Production (t)</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($top10 as $i => $row): ?>
                <tr>
                  <td><span class="rank-badge rank-<?= min($i + 1, 5) ?>"><?= $i + 1 ?></span></td>
                  <td><span class="country-name"><?= htmlspecialchars($row['nom']) ?></span></td>
                  <td>
                    <div class="production-bar-wrap">
                      <div class="production-bar">
                        <div class="production-bar-fill"
                          style="width:<?= round(($row['quantite_t'] / $max_prod) * 100) ?>%">
                        </div>
                      </div>
                      <span class="production-val"><?= number_format($row['quantite_t'] / 1000000, 2, ',', ' ') ?> Mt</span>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        <?php endif; ?>
      </div>

      <!-- ÉVOLUTION MONDIALE -->
      <?php
      $max_evolution = !empty($evolution) ? max(array_column($evolution, 'total')) : 1;
      ?>
      <div class="card">
        <div class="card-header">
          <h2>📈 Évolution mondiale</h2>
        </div>
        <div style="padding:1rem 1.5rem;overflow-y:auto;max-height:420px;">
          <?php foreach ($evolution as $ev): ?>
            <div style="display:flex;align-items:center;gap:10px;margin-bottom:8px;">
              <span style="font-size:0.78rem;color:var(--gray-600);width:36px;flex-shrink:0;"><?= $ev['annee'] ?></span>
              <div style="flex:1;height:8px;background:var(--gray-200);border-radius:99px;overflow:hidden;">
                <div
                  style="height:100%;width:<?= $max_evolution > 0 ? round(($ev['total'] / $max_evolution) * 100) : 0 ?>%;background:linear-gradient(90deg,var(--green-mid),var(--green-accent));border-radius:99px;">
                </div>
              </div>
              <span style="font-size:0.78rem;color:var(--gray-600);white-space:nowrap;width:60px;text-align:right;">
                <?= number_format($ev['total'] / 1000000, 1, ',', ' ') ?> Mt
              </span>
            </div>
          <?php endforeach; ?>
        </div>
      </div>

    </div>

    <!-- CARTE PLEINE LARGEUR -->
    <div class="card" style="margin-top:1.5rem;">
      <div class="card-header">
        <h2>🗺️ Carte des producteurs</h2>
        <span class="badge">Top 10 — <?= $annee_sel ?></span>
      </div>
      <div id="map" style="height:420px;width:100%;"></div>

    </div>

  </div>

  <script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.js"></script>
  <script>
    const carteData = <?= json_encode($carte_data) ?>;
    const maxProd = <?= $max_carte ?>;
    const colors = ['#1a3a2a', '#2d5a3d', '#2d5a3d', '#4a8c5c', '#4a8c5c', '#4a8c5c', '#6abf7b', '#6abf7b', '#6abf7b', '#6abf7b'];

    const map = L.map('map', { scrollWheelZoom: false }).setView([20, 10], 2);
    L.tileLayer('https://{s}.basemaps.cartocdn.com/light_all/{z}/{x}/{y}{r}.png', {
      attribution: '&copy; <a href="https://carto.com/">CARTO</a>',
      subdomains: 'abcd', maxZoom: 18
    }).addTo(map);

    carteData.forEach((d, i) => {
      if (!d.latitude || !d.longitude) return;
      const r = 10 + (d.quantite_t / maxProd) * 28;
      const circle = L.circleMarker([d.latitude, d.longitude], {
        radius: r,
        fillColor: colors[i] ?? '#6abf7b',
        color: '#fff', weight: 1.5,
        opacity: 1, fillOpacity: 0.85
      }).addTo(map);
      circle.bindPopup(`
      <strong style="color:#1a3a2a;">${i + 1}. ${d.nom}</strong><br>
      <span style="color:#6b6560;">${(d.quantite_t / 1e6).toFixed(2).replace('.', ',')} Mt</span>
    `, { closeButton: false, offset: [0, -4] });
      circle.on('mouseover', function () { this.openPopup(); });
      circle.on('mouseout', function () { this.closePopup(); });
    });
  </script>

  <footer class="footer">
    <p>Données : <a href="https://www.fao.org/faostat" target="_blank">FAOSTAT — FAO</a> &nbsp;|&nbsp; Projet BD M1
      Informatique &amp; Big Data &nbsp;|&nbsp; <?= date('Y') ?></p>
  </footer>
</body>

</html>