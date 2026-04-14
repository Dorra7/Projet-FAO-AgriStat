<?php
require 'connexion.php';

$pays_list    = $pdo->query("SELECT id_pays, nom FROM PAYS ORDER BY nom")->fetchAll(PDO::FETCH_ASSOC);
$cultures_list = $pdo->query("SELECT id_culture, nom FROM CULTURE ORDER BY nom")->fetchAll(PDO::FETCH_ASSOC);
$annees        = $pdo->query("SELECT DISTINCT annee FROM PRODUCTION ORDER BY annee")->fetchAll(PDO::FETCH_COLUMN);

$id_pays1     = isset($_GET['pays1'])    ? (int)$_GET['pays1']    : ($pays_list[0]['id_pays'] ?? null);
$id_pays2     = isset($_GET['pays2'])    ? (int)$_GET['pays2']    : ($pays_list[1]['id_pays'] ?? null);
$id_culture   = isset($_GET['culture'])  ? (int)$_GET['culture']  : ($cultures_list[0]['id_culture'] ?? null);
$annee_debut  = isset($_GET['debut'])    ? (int)$_GET['debut']    : (min($annees) ?: 2010);
$annee_fin    = isset($_GET['fin'])      ? (int)$_GET['fin']      : (max($annees) ?: 2022);

// Noms
$nom1 = $pdo->prepare("SELECT nom FROM PAYS WHERE id_pays = ?"); $nom1->execute([$id_pays1]); $nom_pays1 = $nom1->fetchColumn();
$nom2 = $pdo->prepare("SELECT nom FROM PAYS WHERE id_pays = ?"); $nom2->execute([$id_pays2]); $nom_pays2 = $nom2->fetchColumn();
$nomc = $pdo->prepare("SELECT nom FROM CULTURE WHERE id_culture = ?"); $nomc->execute([$id_culture]); $nom_culture = $nomc->fetchColumn();

// Données pays 1
$stmt1 = $pdo->prepare("SELECT annee, quantite_t, rendement_kg_ha FROM PRODUCTION WHERE id_pays = ? AND id_culture = ? AND annee BETWEEN ? AND ? ORDER BY annee");
$stmt1->execute([$id_pays1, $id_culture, $annee_debut, $annee_fin]);
$data1 = $stmt1->fetchAll(PDO::FETCH_ASSOC);

// Données pays 2
$stmt2 = $pdo->prepare("SELECT annee, quantite_t, rendement_kg_ha FROM PRODUCTION WHERE id_pays = ? AND id_culture = ? AND annee BETWEEN ? AND ? ORDER BY annee");
$stmt2->execute([$id_pays2, $id_culture, $annee_debut, $annee_fin]);
$data2 = $stmt2->fetchAll(PDO::FETCH_ASSOC);

// Indexer par année
$by_annee1 = array_column($data1, null, 'annee');
$by_annee2 = array_column($data2, null, 'annee');
$all_annees = array_unique(array_merge(array_column($data1, 'annee'), array_column($data2, 'annee')));
sort($all_annees);

$total1 = array_sum(array_column($data1, 'quantite_t'));
$total2 = array_sum(array_column($data2, 'quantite_t'));
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Comparaison — AgriStat FAO</title>
  <link rel="stylesheet" href="css/style.css">
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
    <li><a href="culture.php">Cultures</a></li>
    <li><a href="comparaison.php" class="active">Comparaison</a></li>
    <li><a href="stats.php">Statistiques</a></li>
  </ul>
</nav>

<section class="hero">
  <div class="hero-content">
    <div class="hero-badge">⚖️ Comparaison</div>
    <h1><?= htmlspecialchars($nom_pays1) ?> <span>vs</span> <?= htmlspecialchars($nom_pays2) ?></h1>
    <p><?= htmlspecialchars($nom_culture) ?> — <?= $annee_debut ?> à <?= $annee_fin ?></p>
  </div>
</section>

<div style="max-width:1200px;margin:2rem auto;padding:0 2rem;">

  <!-- FILTRES -->
  <form method="GET" style="display:flex;gap:1rem;flex-wrap:wrap;margin-bottom:1.5rem;">
    <div style="display:flex;flex-direction:column;gap:4px;">
      <label style="font-size:0.75rem;font-weight:600;color:var(--gray-600);text-transform:uppercase;letter-spacing:0.05em;">Pays 1</label>
      <select name="pays1" class="form-select">
        <?php foreach ($pays_list as $p): ?>
          <option value="<?= $p['id_pays'] ?>" <?= $p['id_pays']==$id_pays1?'selected':'' ?>><?= htmlspecialchars($p['nom']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div style="display:flex;flex-direction:column;gap:4px;">
      <label style="font-size:0.75rem;font-weight:600;color:var(--gray-600);text-transform:uppercase;letter-spacing:0.05em;">Pays 2</label>
      <select name="pays2" class="form-select">
        <?php foreach ($pays_list as $p): ?>
          <option value="<?= $p['id_pays'] ?>" <?= $p['id_pays']==$id_pays2?'selected':'' ?>><?= htmlspecialchars($p['nom']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div style="display:flex;flex-direction:column;gap:4px;">
      <label style="font-size:0.75rem;font-weight:600;color:var(--gray-600);text-transform:uppercase;letter-spacing:0.05em;">Culture</label>
      <select name="culture" class="form-select">
        <?php foreach ($cultures_list as $c): ?>
          <option value="<?= $c['id_culture'] ?>" <?= $c['id_culture']==$id_culture?'selected':'' ?>><?= htmlspecialchars($c['nom']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div style="display:flex;flex-direction:column;gap:4px;">
      <label style="font-size:0.75rem;font-weight:600;color:var(--gray-600);text-transform:uppercase;letter-spacing:0.05em;">De</label>
      <select name="debut" class="form-select" style="min-width:100px;">
        <?php foreach ($annees as $a): ?>
          <option value="<?= $a ?>" <?= $a==$annee_debut?'selected':'' ?>><?= $a ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div style="display:flex;flex-direction:column;gap:4px;">
      <label style="font-size:0.75rem;font-weight:600;color:var(--gray-600);text-transform:uppercase;letter-spacing:0.05em;">À</label>
      <select name="fin" class="form-select" style="min-width:100px;">
        <?php foreach ($annees as $a): ?>
          <option value="<?= $a ?>" <?= $a==$annee_fin?'selected':'' ?>><?= $a ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div style="display:flex;flex-direction:column;gap:4px;justify-content:flex-end;">
      <button type="submit" class="btn">Comparer</button>
    </div>
  </form>

  <!-- TOTAUX -->
  <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;margin-bottom:1.5rem;">
    <div class="card" style="padding:1.5rem;text-align:center;">
      <div style="font-size:0.75rem;font-weight:700;color:var(--green-light);letter-spacing:0.08em;text-transform:uppercase;margin-bottom:0.5rem;"><?= htmlspecialchars($nom_pays1) ?></div>
      <div style="font-size:2rem;font-weight:700;color:var(--green-dark);"><?= number_format($total1/1000000,2,',',' ') ?> Mt</div>
      <div style="font-size:0.78rem;color:var(--gray-600);">Production totale sur la période</div>
    </div>
    <div class="card" style="padding:1.5rem;text-align:center;">
      <div style="font-size:0.75rem;font-weight:700;color:var(--gold);letter-spacing:0.08em;text-transform:uppercase;margin-bottom:0.5rem;"><?= htmlspecialchars($nom_pays2) ?></div>
      <div style="font-size:2rem;font-weight:700;color:var(--green-dark);"><?= number_format($total2/1000000,2,',',' ') ?> Mt</div>
      <div style="font-size:0.78rem;color:var(--gray-600);">Production totale sur la période</div>
    </div>
  </div>

  <!-- TABLEAU COMPARATIF -->
  <div class="card">
    <div class="card-header">
      <h2>📊 Comparaison année par année</h2>
      <span class="badge"><?= htmlspecialchars($nom_culture) ?></span>
    </div>
    <?php if (empty($all_annees)): ?>
      <p style="padding:2rem;color:var(--gray-600);text-align:center;">Aucune donnée disponible pour cette sélection.</p>
    <?php else: ?>
    <div style="overflow-x:auto;">
      <table class="top5-table" style="min-width:600px;">
        <thead>
          <tr>
            <th>Année</th>
            <th style="color:var(--green-light);"><?= htmlspecialchars($nom_pays1) ?> (t)</th>
            <th style="color:var(--gold);"><?= htmlspecialchars($nom_pays2) ?> (t)</th>
            <th>Écart</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($all_annees as $a):
            $v1 = $by_annee1[$a]['quantite_t'] ?? null;
            $v2 = $by_annee2[$a]['quantite_t'] ?? null;
            $ecart = ($v1 !== null && $v2 !== null) ? $v1 - $v2 : null;
          ?>
          <tr>
            <td><strong><?= $a ?></strong></td>
            <td><?= $v1 !== null ? number_format($v1, 0, ',', ' ') : '—' ?></td>
            <td><?= $v2 !== null ? number_format($v2, 0, ',', ' ') : '—' ?></td>
            <td>
              <?php if ($ecart !== null): ?>
                <span style="color:<?= $ecart >= 0 ? 'var(--green-light)' : '#e05a5a' ?>;font-weight:600;">
                  <?= $ecart >= 0 ? '+' : '' ?><?= number_format($ecart, 0, ',', ' ') ?>
                </span>
              <?php else: echo '—'; endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </div>
</div>

<footer class="footer">
  <p>Données : <a href="https://www.fao.org/faostat" target="_blank">FAOSTAT — FAO</a> &nbsp;|&nbsp; Projet BD M1 Informatique &amp; Big Data &nbsp;|&nbsp; <?= date('Y') ?></p>
</footer>
</body>
</html>