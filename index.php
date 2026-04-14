<?php
require 'connexion.php';

// Top 5 pays par production totale (année la plus récente)
$sql_top5 = "
    SELECT pa.nom, SUM(pr.quantite_t) as total
    FROM PRODUCTION pr
    JOIN PAYS pa ON pr.id_pays = pa.id_pays
    WHERE pr.annee = (SELECT MAX(annee) FROM PRODUCTION)
    GROUP BY pa.nom
    ORDER BY total DESC
    LIMIT 5
";
$top5 = $pdo->query($sql_top5)->fetchAll(PDO::FETCH_ASSOC);
$max_prod = !empty($top5) ? $top5[0]['total'] : 1;

// Cultures par catégorie
$sql_cultures = "
    SELECT cc.nom as categorie, c.nom as culture
    FROM CULTURE c
    JOIN CATEGORIE_CULTURE cc ON c.id_categorie = cc.id_categorie
    ORDER BY cc.nom, c.nom
";
$cultures_raw = $pdo->query($sql_cultures)->fetchAll(PDO::FETCH_ASSOC);

// Grouper par catégorie
$cultures_by_cat = [];
foreach ($cultures_raw as $row) {
  $cultures_by_cat[$row['categorie']][] = $row['culture'];
}

// Stats générales
$annee_max = $pdo->query("SELECT MAX(annee) FROM PRODUCTION")->fetchColumn();
$nb_pays = $pdo->query("SELECT COUNT(*) FROM PAYS")->fetchColumn();
$nb_cultures = $pdo->query("SELECT COUNT(*) FROM CULTURE")->fetchColumn();
$nb_lignes = $pdo->query("SELECT COUNT(*) FROM PRODUCTION")->fetchColumn();

// Couleur par catégorie
$dot_class = [
  'Céréales' => 'dot-cereales',
  'Oléagineux' => 'dot-oleagineux',
  'Racines' => 'dot-racines',
  'Sucre' => 'dot-sucre',
  'Légumineuses' => 'dot-legumineuses',
];
?>
<!DOCTYPE html>
<html lang="fr">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Tableau de bord — FAOSTAT</title>
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
      <li><a href="index.php" class="active">Tableau de bord</a></li>
      <li><a href="pays.php">Pays</a></li>
      <li><a href="culture.php">Cultures</a></li>
      <li><a href="comparaison.php">Comparaison</a></li>
      <li><a href="stats.php">Statistiques</a></li>
    </ul>
  </nav>

  <!-- HERO -->
  <section class="hero">
    <div class="hero-content">
      <div class="hero-badge"> Source : FAOSTAT — Production QCL</div>
      <h1>Production Agricole<br><span>Mondiale</span></h1>
      <p>Explorez les dynamiques mondiales de production agricole, comparez les pays et identifiez les cultures en
        progression.</p>
    </div>
  </section>

  <!-- STATS BAR -->
  <div class="stats-bar">
    <div class="stats-bar-inner">
      <div class="stat-pill">
        <div class="icon">🗓️</div>
        <div class="info">
          <div class="val"><?= $annee_max ?></div>
          <div class="lbl">Année la plus récente</div>
        </div>
      </div>
      <div class="stat-pill">
        <div class="icon">🌍</div>
        <div class="info">
          <div class="val"><?= $nb_pays ?> pays</div>
          <div class="lbl">Pays couverts</div>
        </div>
      </div>
      <div class="stat-pill">
        <div class="icon">🌱</div>
        <div class="info">
          <div class="val"><?= $nb_cultures ?> cultures</div>
          <div class="lbl">Cultures suivies</div>
        </div>
      </div>
      <div class="stat-pill">
        <div class="icon">📈</div>
        <div class="info">
          <div class="val"><?= number_format($nb_lignes, 0, ',', ' ') ?></div>
          <div class="lbl">Entrées de production</div>
        </div>
      </div>
    </div>
  </div>

  <!-- MAIN CONTENT -->
  <main class="main">

    <!-- COLONNE GAUCHE -->
    <div>
      <!-- TOP 5 -->
      <div class="card">
        <div class="card-header">
          <h2>🏆 Top 5 pays producteurs</h2>
          <span class="badge">Année <?= $annee_max ?></span>
        </div>
        <table class="top5-table">
          <thead>
            <tr>
              <th>#</th>
              <th>Pays</th>
              <th>Production totale</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($top5 as $i => $row):
              $rank = $i + 1;
              $pct = round(($row['total'] / $max_prod) * 100);
              $val = number_format($row['total'] / 1000000, 2, ',', ' ') . ' Mt';
              ?>
              <tr>
                <td><span class="rank-badge rank-<?= $rank ?>"><?= $rank ?></span></td>
                <td><span class="country-name"><?= htmlspecialchars($row['nom']) ?></span></td>
                <td>
                  <div class="production-bar-wrap">
                    <div class="production-bar">
                      <div class="production-bar-fill" style="width:<?= $pct ?>%"></div>
                    </div>
                    <span class="production-val"><?= $val ?></span>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <!-- LIENS RAPIDES -->
      <div class="quick-links">
        <a href="pays.php" class="quick-card">
          <div class="qc-icon">🌍</div>
          <div class="qc-title">Fiche Pays</div>
          <div class="qc-desc">Historique complet des productions par pays et par culture.</div>
        </a>
        <a href="culture.php" class="quick-card">
          <div class="qc-icon">🌱</div>
          <div class="qc-title">Fiche Culture</div>
          <div class="qc-desc">Top producteurs et évolution mondiale d'une culture.</div>
        </a>
        <a href="comparaison.php" class="quick-card">
          <div class="qc-icon">⚖️</div>
          <div class="qc-title">Comparaison</div>
          <div class="qc-desc">Comparer deux pays sur une culture et une plage d'années.</div>
        </a>
        <a href="stats.php" class="quick-card">
          <div class="qc-icon">📊</div>
          <div class="qc-title">Statistiques</div>
          <div class="qc-desc">Les 8 requêtes analytiques interactives du projet.</div>
        </a>
      </div>
    </div>

    <!-- COLONNE DROITE -->
    <div>
      <div class="card">
        <div class="card-header">
          <h2>🌿 Cultures par catégorie</h2>
        </div>
        <div class="culture-list">
          <?php foreach ($cultures_by_cat as $categorie => $items): ?>
            <div class="culture-category">
              <div class="category-label"><?= htmlspecialchars($categorie) ?></div>
              <?php foreach ($items as $culture):
                $dc = $dot_class[$categorie] ?? 'dot-cereales';
                ?>
                <div class="culture-item">
                  <span class="culture-dot <?= $dc ?>"></span>
                  <?= htmlspecialchars($culture) ?>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>

  </main>

  <!-- FOOTER -->
  <footer class="footer">
    <p>Données : <a href="https://www.fao.org/faostat" target="_blank">FAOSTAT — FAO</a> &nbsp;|&nbsp; Projet BD M1
      Informatique &amp; Big Data &nbsp;|&nbsp; <?= date('Y') ?></p>
  </footer>

</body>

</html>