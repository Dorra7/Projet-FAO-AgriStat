<?php
require 'connexion.php';

$cultures_list = $pdo->query("SELECT id_culture, nom FROM CULTURE ORDER BY nom")->fetchAll(PDO::FETCH_ASSOC);
$annees = $pdo->query("SELECT DISTINCT annee FROM PRODUCTION ORDER BY annee DESC")->fetchAll(PDO::FETCH_COLUMN);
$annee_max = $annees[0] ?? 2022;

// ===================== R1 =====================
$r1_culture = $_GET['r1_culture'] ?? $cultures_list[0]['nom'];
$stmt_r1 = $pdo->prepare("
    SELECT pa.nom AS pays, SUM(p.quantite_t) AS total_production
    FROM PRODUCTION p JOIN PAYS pa ON p.id_pays = pa.id_pays JOIN CULTURE c ON p.id_culture = c.id_culture
    WHERE c.nom = ? AND p.annee >= (SELECT MAX(annee) FROM PRODUCTION) - 10
    GROUP BY pa.nom ORDER BY total_production DESC LIMIT 10
");
$stmt_r1->execute([$r1_culture]);
$r1 = $stmt_r1->fetchAll(PDO::FETCH_ASSOC);

// ===================== R2 =====================
$r2_annee = $_GET['r2_annee'] ?? $annee_max;
$stmt_r2 = $pdo->prepare("
    SELECT r.nom AS region, c.nom AS culture, AVG(p.rendement_kg_ha) AS rendement_moyen
    FROM PRODUCTION p JOIN PAYS pa ON p.id_pays = pa.id_pays JOIN REGION_FAO r ON pa.id_region = r.id_region JOIN CULTURE c ON p.id_culture = c.id_culture
    WHERE p.annee = ? GROUP BY r.nom, c.nom ORDER BY r.nom, rendement_moyen DESC
");
$stmt_r2->execute([$r2_annee]);
$r2 = $stmt_r2->fetchAll(PDO::FETCH_ASSOC);

// ===================== R3 =====================
$r3_annee1 = $_GET['r3_annee1'] ?? 2015;
$r3_annee2 = $_GET['r3_annee2'] ?? 2022;
$stmt_r3 = $pdo->prepare("
    SELECT c.nom AS culture,
        (SELECT SUM(p1.quantite_t) FROM PRODUCTION p1 WHERE p1.id_culture = p.id_culture AND p1.annee = ?) AS prod_annee1,
        (SELECT SUM(p2.quantite_t) FROM PRODUCTION p2 WHERE p2.id_culture = p.id_culture AND p2.annee = ?) AS prod_annee2
    FROM PRODUCTION p JOIN CULTURE c ON p.id_culture = c.id_culture
    GROUP BY c.nom, p.id_culture
    HAVING prod_annee2 < prod_annee1
");
$stmt_r3->execute([$r3_annee1, $r3_annee2]);
$r3 = $stmt_r3->fetchAll(PDO::FETCH_ASSOC);

// ===================== R4 =====================
$r4_cultureA = $_GET['r4_cultureA'] ?? 'Wheat';
$r4_cultureB = $_GET['r4_cultureB'] ?? 'Rice';
$stmt_r4 = $pdo->prepare("
    SELECT DISTINCT pa.nom AS pays FROM PRODUCTION p JOIN PAYS pa ON p.id_pays = pa.id_pays JOIN CULTURE c ON p.id_culture = c.id_culture
    WHERE c.nom = ?
    AND pa.id_pays NOT IN (SELECT p2.id_pays FROM PRODUCTION p2 JOIN CULTURE c2 ON p2.id_culture = c2.id_culture WHERE c2.nom = ?)
");
$stmt_r4->execute([$r4_cultureA, $r4_cultureB]);
$r4 = $stmt_r4->fetchAll(PDO::FETCH_ASSOC);

// ===================== R5 =====================
$r5_culture = $_GET['r5_culture'] ?? 'Wheat';
$r5_annee = $_GET['r5_annee'] ?? $annee_max;
$stmt_r5 = $pdo->prepare("
    SELECT pa.nom AS pays, p.rendement_kg_ha,
        (SELECT AVG(p2.rendement_kg_ha) FROM PRODUCTION p2 JOIN CULTURE c2 ON p2.id_culture = c2.id_culture WHERE c2.nom = ? AND p2.annee = ?) AS moyenne_mondiale,
        p.rendement_kg_ha - (SELECT AVG(p3.rendement_kg_ha) FROM PRODUCTION p3 JOIN CULTURE c3 ON p3.id_culture = c3.id_culture WHERE c3.nom = ? AND p3.annee = ?) AS ecart
    FROM PRODUCTION p JOIN PAYS pa ON p.id_pays = pa.id_pays JOIN CULTURE c ON p.id_culture = c.id_culture
    WHERE c.nom = ? AND p.annee = ?
    AND p.rendement_kg_ha > (SELECT AVG(p4.rendement_kg_ha) FROM PRODUCTION p4 JOIN CULTURE c4 ON p4.id_culture = c4.id_culture WHERE c4.nom = ? AND p4.annee = ?)
    ORDER BY ecart DESC
");
$stmt_r5->execute([$r5_culture, $r5_annee, $r5_culture, $r5_annee, $r5_culture, $r5_annee, $r5_culture, $r5_annee]);
$r5 = $stmt_r5->fetchAll(PDO::FETCH_ASSOC);

// ===================== R6 =====================
$stmt_r6 = $pdo->query("
    SELECT pa.nom AS pays,
        SUM(CASE WHEN p.annee = 2010 THEN p.quantite_t ELSE 0 END) AS prod_2010,
        SUM(CASE WHEN p.annee = 2022 THEN p.quantite_t ELSE 0 END) AS prod_2022,
        (SUM(CASE WHEN p.annee = 2022 THEN p.quantite_t ELSE 0 END) - SUM(CASE WHEN p.annee = 2010 THEN p.quantite_t ELSE 0 END))
        / NULLIF(SUM(CASE WHEN p.annee = 2010 THEN p.quantite_t ELSE 0 END), 0) * 100 AS variation_pct
    FROM PRODUCTION p JOIN PAYS pa ON p.id_pays = pa.id_pays JOIN CULTURE c ON p.id_culture = c.id_culture
    JOIN CATEGORIE_CULTURE cat ON c.id_categorie = cat.id_categorie
    WHERE cat.nom = 'Céréales' GROUP BY pa.nom ORDER BY variation_pct DESC
");
$r6 = $stmt_r6->fetchAll(PDO::FETCH_ASSOC);

// ===================== R7 =====================
$r7_n = isset($_GET['r7_n']) ? (int) $_GET['r7_n'] : 10;
$stmt_r7 = $pdo->prepare("
    SELECT c.nom AS culture FROM CULTURE c
    LEFT JOIN PRODUCTION p ON c.id_culture = p.id_culture AND p.annee >= (SELECT MAX(annee) FROM PRODUCTION) - ?
    WHERE p.id_prod IS NULL
");
$stmt_r7->execute([$r7_n]);
$r7 = $stmt_r7->fetchAll(PDO::FETCH_ASSOC);

// ===================== R8 =====================
$stmt_r8 = $pdo->query("
   SELECT 
    cat.nom AS categorie,
    pa.nom AS pays,
    SUM(p.quantite_t) AS total_production
FROM PRODUCTION p
JOIN PAYS pa ON p.id_pays = pa.id_pays
JOIN CULTURE c ON p.id_culture = c.id_culture
JOIN CATEGORIE_CULTURE cat ON c.id_categorie = cat.id_categorie
JOIN (
    -- Sous-requête pour trouver la valeur maximale par catégorie
    SELECT id_categorie, MAX(somme_prod) as valeur_max
    FROM (
        SELECT c2.id_categorie, p2.id_pays, SUM(p2.quantite_t) AS somme_prod
        FROM PRODUCTION p2
        JOIN CULTURE c2 ON p2.id_culture = c2.id_culture
        GROUP BY c2.id_categorie, p2.id_pays
    ) AS totaux
    GROUP BY id_categorie
) AS max_par_cat ON cat.id_categorie = max_par_cat.id_categorie
GROUP BY cat.id_categorie, cat.nom, pa.nom, max_par_cat.valeur_max
-- Filtre direct pour comparer la somme calculée au max de la catégorie
HAVING SUM(p.quantite_t) = max_par_cat.valeur_max
ORDER BY cat.nom;
");
$r8 = $stmt_r8->fetchAll(PDO::FETCH_ASSOC);

// ===================== RB — Score de souveraineté alimentaire =====================
$stmt_rb = $pdo->query("
    SELECT 
        sub.continent,
        sub.pays,
        sub.diversite,
        ROUND(sub.score_rendement, 2) AS performance_regionale,
        IFNULL(ROUND(sub.progression_10ans, 2), 0) AS evolution_pct,
        ROUND((sub.diversite * 10) + (sub.score_rendement * 5) + (IFNULL(sub.progression_10ans, 0) / 10), 2) AS score_souverainete
    FROM (
        SELECT 
            r.nom AS continent,
            pa.nom AS pays,
            COUNT(DISTINCT p.id_culture) AS diversite,
            AVG(p.rendement_kg_ha) / (
                SELECT AVG(p3.rendement_kg_ha) 
                FROM PRODUCTION p3 
                JOIN PAYS pa3 ON p3.id_pays = pa3.id_pays 
                WHERE pa3.id_region = pa.id_region
            ) AS score_rendement,
            ((SUM(CASE WHEN p.annee = 2022 THEN p.quantite_t ELSE 0 END) - 
              SUM(CASE WHEN p.annee = 2010 THEN p.quantite_t ELSE 0 END)) / 
              NULLIF(SUM(CASE WHEN p.annee = 2010 THEN p.quantite_t ELSE 0 END), 0)) * 100 AS progression_10ans
        FROM PRODUCTION p
        JOIN PAYS pa ON p.id_pays = pa.id_pays
        JOIN REGION_FAO r ON pa.id_region = r.id_region
        GROUP BY r.nom, pa.nom, pa.id_region
    ) AS sub
    ORDER BY sub.continent, score_souverainete DESC
");
$rb = $stmt_rb->fetchAll(PDO::FETCH_ASSOC);

// ===================== PAGINATION =====================
$per_page = 20;

function paginate($data, $prefix, $page)
{
  $total = count($data);
  $total_pages = ceil($total / 20);
  $page = max(1, min($page, $total_pages));
  $slice = array_slice($data, ($page - 1) * 20, 20);
  return ['data' => $slice, 'total' => $total, 'page' => $page, 'total_pages' => $total_pages, 'prefix' => $prefix];
}

function render_pagination($p)
{
  if ($p['total_pages'] <= 1)
    return;
  $prefix = $p['prefix'];
  echo '<div class="pagination">';
  for ($i = 1; $i <= $p['total_pages']; $i++) {
    $params = array_merge($_GET, [$prefix . '_page' => $i]);
    $url = '?' . http_build_query($params) . '#' . $prefix;
    $active = $i == $p['page'] ? 'active' : '';
    echo "<a href='$url' class='page-btn $active'>$i</a>";
  }
  echo '</div>';
}

$p1 = paginate($r1, 'r1', $_GET['r1_page'] ?? 1);
$p2 = paginate($r2, 'r2', $_GET['r2_page'] ?? 1);
$p3 = paginate($r3, 'r3', $_GET['r3_page'] ?? 1);
$p4 = paginate($r4, 'r4', $_GET['r4_page'] ?? 1);
$p5 = paginate($r5, 'r5', $_GET['r5_page'] ?? 1);
$p6 = paginate($r6, 'r6', $_GET['r6_page'] ?? 1);
$p7 = paginate($r7, 'r7', $_GET['r7_page'] ?? 1);
$p8 = paginate($r8, 'r8', $_GET['r8_page'] ?? 1);
$pb = paginate($rb, 'rb', $_GET['rb_page'] ?? 1);
?>
<!DOCTYPE html>
<html lang="fr">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Statistiques — AgriStat FAO</title>
  <link rel="stylesheet" href="css/style.css">
  <style>
    .req-card {
      background: var(--white);
      border: 1px solid var(--gray-200);
      border-radius: var(--radius);
      margin-bottom: 2rem;
      overflow: hidden;
      box-shadow: var(--shadow-sm);
    }

    .req-header {
      background: var(--green-dark);
      color: var(--white);
      padding: 1rem 1.5rem;
      display: flex;
      align-items: center;
      gap: 12px;
    }

    .req-num {
      background: var(--gold);
      color: var(--green-dark);
      font-weight: 800;
      font-size: 0.8rem;
      width: 28px;
      height: 28px;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      flex-shrink: 0;
    }

    .req-title {
      font-family: 'Playfair Display', serif;
      font-size: 1rem;
    }

    .req-technique {
      display: none;
    }

    .req-body {
      padding: 1.25rem 1.5rem;
    }

    .req-form {
      display: flex;
      gap: 0.75rem;
      flex-wrap: wrap;
      margin-bottom: 1rem;
      align-items: flex-end;
    }

    .req-form label {
      font-size: 0.72rem;
      font-weight: 600;
      color: var(--gray-600);
      text-transform: uppercase;
      letter-spacing: 0.05em;
      display: block;
      margin-bottom: 3px;
    }

    .req-form .form-select,
    .req-form .form-input {
      min-width: 140px;
    }

    .result-table {
      width: 100%;
      border-collapse: collapse;
      font-size: 0.875rem;
    }

    .result-table th {
      padding: 0.6rem 1rem;
      text-align: left;
      font-size: 0.7rem;
      font-weight: 700;
      letter-spacing: 0.07em;
      text-transform: uppercase;
      color: var(--gray-600);
      background: var(--gray-100);
    }

    .result-table td {
      padding: 0.65rem 1rem;
      border-bottom: 1px solid var(--gray-100);
    }

    .result-table tr:last-child td {
      border-bottom: none;
    }

    .result-table tr:hover td {
      background: var(--gray-100);
    }

    .no-data {
      color: var(--gray-600);
      font-size: 0.875rem;
      padding: 0.5rem 0;
      font-style: italic;
    }
  </style>
</head>

<body>

  <nav class="navbar">
    <a class="navbar-brand" href="index.php"><span class="logo-icon">🌾</span><span>Agri<em>Stat</em> FAO</span></a>
    <ul class="nav-links">
      <li><a href="index.php">Tableau de bord</a></li>
      <li><a href="pays.php">Pays</a></li>
      <li><a href="culture.php">Cultures</a></li>
      <li><a href="comparaison.php">Comparaison</a></li>
      <li><a href="stats.php" class="active">Statistiques</a></li>
    </ul>
  </nav>

  <section class="hero">
    <div class="hero-content">
      <div class="hero-badge">📊 Requêtes analytiques</div>
      <h1>Statistiques <span>avancées</span></h1>
      <p>8 requêtes SQL interactives pour analyser la production agricole mondiale.</p>
    </div>
  </section>

  <div style="max-width:1100px;margin:2rem auto;padding:0 2rem;">

    <!-- R1 -->
    <div class="req-card" id="r1">
      <div class="req-header"><span class="req-num" data-id="r1">R1</span><span class="req-title">Top 10 producteurs par
          culture</span><span class="req-technique"></span></div>
      <div class="req-body">
        <form method="GET" class="req-form" action="#r1">
          <div><label>Culture</label>
            <select name="r1_culture" class="form-select" style="min-width:160px;">
              <?php foreach ($cultures_list as $c): ?>
                <option value="<?= htmlspecialchars($c['nom']) ?>" <?= $c['nom'] == $r1_culture ? 'selected' : '' ?>>
                  <?= htmlspecialchars($c['nom']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <button type="submit" class="btn">Rechercher</button>
        </form>
        <?php if (empty($p1['data'])): ?>
          <p class="no-data">Aucun résultat.</p>
        <?php else: ?>
          <p style="font-size:0.78rem;color:var(--gray-600);margin-bottom:0.5rem;"><?= $p1['total'] ?> résultat(s)</p>
          <table class="result-table">
            <thead>
              <tr>
                <th>#</th>
                <th>Pays</th>
                <th>Production totale (t)</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($p1['data'] as $i => $row): ?>
                <tr>
                  <td><?= ($p1['page'] - 1) * 20 + $i + 1 ?></td>
                  <td><?= htmlspecialchars($row['pays']) ?></td>
                  <td><?= number_format($row['total_production'], 0, ',', ' ') ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
          <?php render_pagination($p1); endif; ?>
      </div>
    </div>

    <!-- R2 -->
    <div class="req-card" id="r2">
      <div class="req-header"><span class="req-num" data-id="r2">R2</span><span class="req-title">Rendement moyen par
          région</span><span class="req-technique"></span></div>
      <div class="req-body">
        <form method="GET" class="req-form" action="#r2">
          <div><label>Année</label>
            <select name="r2_annee" class="form-select" style="min-width:100px;">
              <?php foreach ($annees as $a): ?>
                <option value="<?= $a ?>" <?= $a == $r2_annee ? 'selected' : '' ?>><?= $a ?></option><?php endforeach; ?>
            </select>
          </div>
          <button type="submit" class="btn">Rechercher</button>
        </form>
        <?php if (empty($p2['data'])): ?>
          <p class="no-data">Aucun résultat.</p>
        <?php else: ?>
          <p style="font-size:0.78rem;color:var(--gray-600);margin-bottom:0.5rem;"><?= $p2['total'] ?> résultat(s)</p>
          <table class="result-table">
            <thead>
              <tr>
                <th>Région</th>
                <th>Culture</th>
                <th>Rendement moyen (kg/ha)</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($p2['data'] as $row): ?>
                <tr>
                  <td><?= htmlspecialchars($row['region']) ?></td>
                  <td><?= htmlspecialchars($row['culture']) ?></td>
                  <td><?= number_format($row['rendement_moyen'], 2, ',', ' ') ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
          <?php render_pagination($p2); endif; ?>
      </div>
    </div>

    <!-- R3 -->
    <div class="req-card" id="r3">
      <div class="req-header"><span class="req-num" data-id="r3">R3</span><span class="req-title">Cultures en régression
          mondiale</span><span class="req-technique"></span></div>
      <div class="req-body">
        <form method="GET" class="req-form" action="#r3">
          <div><label>Année 1</label>
            <select name="r3_annee1" class="form-select" style="min-width:100px;">
              <?php foreach ($annees as $a): ?>
                <option value="<?= $a ?>" <?= $a == $r3_annee1 ? 'selected' : '' ?>><?= $a ?></option><?php endforeach; ?>
            </select>
          </div>
          <div><label>Année 2</label>
            <select name="r3_annee2" class="form-select" style="min-width:100px;">
              <?php foreach ($annees as $a): ?>
                <option value="<?= $a ?>" <?= $a == $r3_annee2 ? 'selected' : '' ?>><?= $a ?></option><?php endforeach; ?>
            </select>
          </div>
          <button type="submit" class="btn">Rechercher</button>
        </form>
        <?php if (empty($p3['data'])): ?>
          <p class="no-data">Aucune culture en régression.</p>
        <?php else: ?>
          <p style="font-size:0.78rem;color:var(--gray-600);margin-bottom:0.5rem;"><?= $p3['total'] ?> résultat(s)</p>
          <table class="result-table">
            <thead>
              <tr>
                <th>Culture</th>
                <th>Production <?= $r3_annee1 ?> (t)</th>
                <th>Production <?= $r3_annee2 ?> (t)</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($p3['data'] as $row): ?>
                <tr>
                  <td><?= htmlspecialchars($row['culture']) ?></td>
                  <td><?= number_format($row['prod_annee1'], 0, ',', ' ') ?></td>
                  <td><?= number_format($row['prod_annee2'], 0, ',', ' ') ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
          <?php render_pagination($p3); endif; ?>
      </div>
    </div>

    <!-- R4 -->
    <div class="req-card" id="r4">
      <div class="req-header"><span class="req-num" data-id="r4">R4</span><span class="req-title">Producteurs d'une
          culture sans l'autre</span><span class="req-technique"></span></div>
      <div class="req-body">
        <form method="GET" class="req-form" action="#r4">
          <div><label>Culture A (produite)</label>
            <select name="r4_cultureA" class="form-select">
              <?php foreach ($cultures_list as $c): ?>
                <option value="<?= htmlspecialchars($c['nom']) ?>" <?= $c['nom'] == $r4_cultureA ? 'selected' : '' ?>>
                  <?= htmlspecialchars($c['nom']) ?>
                </option><?php endforeach; ?>
            </select>
          </div>
          <div><label>Culture B (absente)</label>
            <select name="r4_cultureB" class="form-select">
              <?php foreach ($cultures_list as $c): ?>
                <option value="<?= htmlspecialchars($c['nom']) ?>" <?= $c['nom'] == $r4_cultureB ? 'selected' : '' ?>>
                  <?= htmlspecialchars($c['nom']) ?>
                </option><?php endforeach; ?>
            </select>
          </div>
          <button type="submit" class="btn">Rechercher</button>
        </form>
        <?php if (empty($p4['data'])): ?>
          <p class="no-data">Aucun résultat.</p>
        <?php else: ?>
          <p style="font-size:0.78rem;color:var(--gray-600);margin-bottom:0.5rem;"><?= $p4['total'] ?> résultat(s)</p>
          <table class="result-table">
            <thead>
              <tr>
                <th>Pays</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($p4['data'] as $row): ?>
                <tr>
                  <td><?= htmlspecialchars($row['pays']) ?></td>
                </tr><?php endforeach; ?>
            </tbody>
          </table>
          <?php render_pagination($p4); endif; ?>
      </div>
    </div>

    <!-- R5 -->
    <div class="req-card" id="r5">
      <div class="req-header"><span class="req-num" data-id="r5">R5</span><span class="req-title">Pays au-dessus de la
          moyenne mondiale</span><span class="req-technique"></span></div>
      <div class="req-body">
        <form method="GET" class="req-form" action="#r5">
          <div><label>Culture</label>
            <select name="r5_culture" class="form-select">
              <?php foreach ($cultures_list as $c): ?>
                <option value="<?= htmlspecialchars($c['nom']) ?>" <?= $c['nom'] == $r5_culture ? 'selected' : '' ?>>
                  <?= htmlspecialchars($c['nom']) ?>
                </option><?php endforeach; ?>
            </select>
          </div>
          <div><label>Année</label>
            <select name="r5_annee" class="form-select" style="min-width:100px;">
              <?php foreach ($annees as $a): ?>
                <option value="<?= $a ?>" <?= $a == $r5_annee ? 'selected' : '' ?>><?= $a ?></option><?php endforeach; ?>
            </select>
          </div>
          <button type="submit" class="btn">Rechercher</button>
        </form>
        <?php if (empty($p5['data'])): ?>
          <p class="no-data">Aucun résultat.</p>
        <?php else: ?>
          <p style="font-size:0.78rem;color:var(--gray-600);margin-bottom:0.5rem;"><?= $p5['total'] ?> résultat(s)</p>
          <table class="result-table">
            <thead>
              <tr>
                <th>Pays</th>
                <th>Rendement (kg/ha)</th>
                <th>Moyenne mondiale</th>
                <th>Écart</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($p5['data'] as $row): ?>
                <tr>
                  <td><?= htmlspecialchars($row['pays']) ?></td>
                  <td><?= number_format($row['rendement_kg_ha'], 2, ',', ' ') ?></td>
                  <td><?= number_format($row['moyenne_mondiale'], 2, ',', ' ') ?></td>
                  <td style="color:var(--green-light);font-weight:600;">+<?= number_format($row['ecart'], 2, ',', ' ') ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
          <?php render_pagination($p5); endif; ?>
      </div>
    </div>

    <!-- R6 -->
    <div class="req-card" id="r6">
      <div class="req-header"><span class="req-num" data-id="r6">R6</span><span class="req-title">Progression décennale
          en pourcentage (Céréales)</span><span class="req-technique"></span></div>
      <div class="req-body">
        <?php if (empty($p6['data'])): ?>
          <p class="no-data">Aucun résultat.</p>
        <?php else: ?>
          <p style="font-size:0.78rem;color:var(--gray-600);margin-bottom:0.5rem;"><?= $p6['total'] ?> résultat(s)</p>
          <table class="result-table">
            <thead>
              <tr>
                <th>Pays</th>
                <th>Production 2010 (t)</th>
                <th>Production 2022 (t)</th>
                <th>Variation %</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($p6['data'] as $row): ?>
                <tr>
                  <td><?= htmlspecialchars($row['pays']) ?></td>
                  <td><?= number_format($row['prod_2010'], 0, ',', ' ') ?></td>
                  <td><?= number_format($row['prod_2022'], 0, ',', ' ') ?></td>
                  <td style="color:<?= $row['variation_pct'] >= 0 ? 'var(--green-light)' : '#e05a5a' ?>;font-weight:600;">
                    <?= $row['variation_pct'] >= 0 ? '+' : '' ?>     <?= number_format($row['variation_pct'], 1, ',', ' ') ?> %
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
          <?php render_pagination($p6); endif; ?>
      </div>
    </div>

    <!-- R7 -->
    <div class="req-card" id="r7">
      <div class="req-header"><span class="req-num" data-id="r7">R7</span><span class="req-title">Cultures sans données
          récentes</span><span class="req-technique"></span></div>
      <div class="req-body">
        <form method="GET" class="req-form" action="#r7">
          <div><label>Depuis N années</label>
            <input type="number" name="r7_n" value="<?= $r7_n ?>" min="1" max="60" class="form-input"
              style="min-width:80px;">
          </div>
          <button type="submit" class="btn">Rechercher</button>
        </form>
        <?php if (empty($p7['data'])): ?>
          <p class="no-data">Toutes les cultures ont des données récentes.</p>
        <?php else: ?>
          <p style="font-size:0.78rem;color:var(--gray-600);margin-bottom:0.5rem;"><?= $p7['total'] ?> résultat(s)</p>
          <table class="result-table">
            <thead>
              <tr>
                <th>Culture</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($p7['data'] as $row): ?>
                <tr>
                  <td><?= htmlspecialchars($row['culture']) ?></td>
                </tr><?php endforeach; ?>
            </tbody>
          </table>
          <?php render_pagination($p7); endif; ?>
      </div>
    </div>

    <!-- R8 -->
    <div class="req-card" id="r8">
      <div class="req-header"><span class="req-num" data-id="r8">R8</span><span class="req-title">Champion de chaque
          catégorie</span><span class="req-technique"></span></div>
      <div class="req-body">
        <?php if (empty($p8['data'])): ?>
          <p class="no-data">Aucun résultat.</p>
        <?php else: ?>
          <p style="font-size:0.78rem;color:var(--gray-600);margin-bottom:0.5rem;"><?= $p8['total'] ?> résultat(s)</p>
          <table class="result-table">
            <thead>
              <tr>
                <th>Catégorie</th>
                <th>Pays champion</th>
                <th>Production totale (t)</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($p8['data'] as $row): ?>
                <tr>
                  <td><span
                      style="color:var(--green-light);font-weight:600;"><?= htmlspecialchars($row['categorie']) ?></span>
                  </td>
                  <td>🏆 <?= htmlspecialchars($row['pays']) ?></td>
                  <td><?= number_format($row['total_production'], 0, ',', ' ') ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
          <?php render_pagination($p8); endif; ?>
      </div>
    </div>

    <!-- RB — Score de souveraineté alimentaire -->
    <div class="req-card" id="rb">
      <div class="req-header">
        <span class="req-num" style="background:var(--gold-light);color:var(--green-dark);">★</span>
        <span class="req-title">Score de souveraineté alimentaire</span>

      </div>
      <div class="req-body">

        <?php if (empty($pb['data'])): ?>
          <p class="no-data">Aucun résultat.</p>
        <?php else: ?>
          <table class="result-table">
            <thead>
              <tr>
                <th>Continent</th>
                <th>Pays</th>
                <th>Diversité</th>
                <th>Perf. régionale</th>
                <th>Évolution %</th>
                <th>Score souveraineté</th>
              </tr>
            </thead>
            <tbody>
              <?php
              $prev_continent = null;
              foreach ($pb['data'] as $row):
                $continent_change = $row['continent'] !== $prev_continent;
                $prev_continent = $row['continent'];
                ?>
                <?php if ($continent_change): ?>
                  <tr>
                    <td colspan="6"
                      style="background:var(--green-dark);color:var(--gold-light);font-family:'Playfair Display',serif;font-size:0.8rem;font-weight:600;padding:0.5rem 1rem;letter-spacing:0.05em;">
                      🌍 <?= htmlspecialchars($row['continent']) ?>
                    </td>
                  </tr>
                <?php endif; ?>
                <tr>
                  <td style="color:var(--gray-600);font-size:0.8rem;"></td>
                  <td><span class="country-name"><?= htmlspecialchars($row['pays']) ?></span></td>
                  <td><?= (int) $row['diversite'] ?> cultures</td>
                  <td><?= number_format($row['performance_regionale'], 2, ',', ' ') ?></td>
                  <td style="color:<?= $row['evolution_pct'] >= 0 ? 'var(--green-light)' : '#e05a5a' ?>;font-weight:600;">
                    <?= $row['evolution_pct'] >= 0 ? '+' : '' ?>     <?= number_format($row['evolution_pct'], 2, ',', ' ') ?> %
                  </td>
                  <td>
                    <div style="display:flex;align-items:center;gap:8px;">
                      <div
                        style="flex:1;height:6px;background:var(--gray-200);border-radius:99px;overflow:hidden;min-width:60px;">
                        <div
                          style="height:100%;width:<?= min(100, max(0, round($row['score_souverainete'] / 3))) ?>%;background:linear-gradient(90deg,var(--green-mid),var(--green-accent));border-radius:99px;">
                        </div>
                      </div>
                      <span
                        style="font-weight:700;color:var(--green-dark);white-space:nowrap;"><?= number_format($row['score_souverainete'], 2, ',', ' ') ?></span>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
          <?php render_pagination($pb); ?>
        <?php endif; ?>
      </div>
    </div>

  </div>

  <footer class="footer">
    <p>Données : <a href="https://www.fao.org/faostat" target="_blank">FAOSTAT — FAO</a> &nbsp;|&nbsp; Projet BD M1
      Informatique &amp; Big Data &nbsp;|&nbsp; <?= date('Y') ?></p>
  </footer>
</body>

</html>