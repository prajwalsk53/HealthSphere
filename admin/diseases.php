<?php
require_once __DIR__ . '/../config/config.php';
requireRole('admin');
$user = getCurrentUser(); $uid = $user['id'];
// Handle import from MedlinePlus
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_disease'])) {
    $pdo->prepare("INSERT INTO genetic_diseases (disease_name,inheritance_type,patient_count,key_symptoms,food_triggers,recommended_foods,exercise_guidance,care_plan) VALUES (?,?,?,?,?,?,?,?)")
        ->execute([
            trim($_POST['disease_name'] ?? ''),
            $_POST['inheritance_type'] ?? 'complex',
            (int)($_POST['patient_count'] ?? 0),
            trim($_POST['key_symptoms'] ?? ''),
            trim($_POST['food_triggers'] ?? ''),
            trim($_POST['recommended_foods'] ?? ''),
            trim($_POST['exercise_guidance'] ?? ''),
            $_POST['care_plan'] ?? 'standard',
        ]);
    header('Location: diseases.php');
    exit;
}
$diseases = $pdo->query("SELECT * FROM genetic_diseases ORDER BY patient_count DESC")->fetchAll();
$notifCount = getUnreadCount($pdo, $uid);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Genetic Diseases — HealthSphere Admin</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<?php include __DIR__ . '/../includes/sidebar.php'; ?>
<div class="hs-main">
  <div class="hs-topbar">
    <div><div class="page-title"><i class="fas fa-dna" style="color:var(--hs-blue);"></i> Genetic Diseases Registry</div><div class="page-subtitle"><?= count($diseases) ?> conditions</div></div>
  </div>
  <div class="hs-content">

    <!-- Tabs -->
    <div style="display:flex;gap:0;margin-bottom:20px;border-bottom:2px solid var(--hs-border);">
      <button id="tab-local" onclick="switchTab('local')" style="padding:10px 20px;font-size:13px;font-weight:700;border:none;background:none;cursor:pointer;color:var(--hs-blue);border-bottom:2px solid var(--hs-blue);margin-bottom:-2px;"><i class="fas fa-database"></i> Local Registry <span style="background:var(--hs-blue);color:#fff;padding:1px 7px;border-radius:10px;font-size:11px;margin-left:4px;"><?= count($diseases) ?></span></button>
      <button id="tab-nlm" onclick="switchTab('nlm')" style="padding:10px 20px;font-size:13px;font-weight:700;border:none;background:none;cursor:pointer;color:var(--hs-muted);border-bottom:2px solid transparent;margin-bottom:-2px;"><i class="fas fa-flask"></i> Search MedlinePlus <span style="background:#7C3AED;color:#fff;padding:1px 7px;border-radius:10px;font-size:11px;margin-left:4px;">NLM</span></button>
    </div>

    <!-- LOCAL TAB -->
    <div id="pane-local">
    <div class="hs-card">
      <div class="hs-card-header"><span class="card-title"><i class="fas fa-dna"></i> Genetic Diseases</span></div>
      <div class="hs-card-body p-0" style="overflow-x:auto;">
        <table class="hs-table" style="min-width:1000px;">
          <thead><tr><th><input type="checkbox"></th><th>Name</th><th>Inheritance</th><th>Patients</th><th>Key Symptoms</th><th>Food Triggers</th><th>Recommended Foods</th><th>Exercise Guidance</th><th>Care Plan</th><th>Actions</th></tr></thead>
          <tbody>
            <?php foreach ($diseases as $d):
              $cp = ['intensive'=>'bg-danger','moderate'=>'bg-warning text-dark','standard'=>'bg-info','preventive'=>'bg-success'][$d['care_plan']] ?? 'bg-secondary';
              $inherit = str_replace('_',' ',ucfirst($d['inheritance_type']));
            ?>
            <tr>
              <td><input type="checkbox"></td>
              <td><strong><?= e($d['disease_name']) ?></strong></td>
              <td style="font-size:12px;"><?= $inherit ?></td>
              <td style="font-weight:700;color:var(--hs-blue);"><?= number_format($d['patient_count']) ?></td>
              <td style="font-size:12px;max-width:150px;"><?= e(substr($d['key_symptoms'],0,80)).'...' ?></td>
              <td style="font-size:12px;max-width:120px;"><?= e(substr($d['food_triggers'],0,60)).'...' ?></td>
              <td style="font-size:12px;max-width:130px;"><?= e(substr($d['recommended_foods'],0,70)).'...' ?></td>
              <td style="font-size:12px;max-width:120px;"><?= e(substr($d['exercise_guidance'],0,70)).'...' ?></td>
              <td><span class="badge <?= $cp ?>"><?= ucfirst($d['care_plan']) ?></span></td>
              <td>
                <div style="display:flex;gap:4px;">
                  <button class="btn-hs btn-outline-hs btn-sm-hs" title="View"><i class="fas fa-eye"></i></button>
                  <button class="btn-hs btn-outline-hs btn-sm-hs" title="Edit"><i class="fas fa-edit"></i></button>
                  <button class="btn-hs btn-danger-hs btn-sm-hs" title="Delete"><i class="fas fa-trash"></i></button>
                </div>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
    </div><!-- /pane-local -->

    <!-- MEDLINEPLUS TAB -->
    <div id="pane-nlm" style="display:none;">
      <div class="hs-card" style="margin-bottom:16px;">
        <div class="hs-card-body">
          <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
            <div class="input-icon-wrap" style="flex:1;max-width:480px;"><i class="fas fa-search"></i><input type="text" id="nlmQ" class="form-control" placeholder="Search: e.g. cystic fibrosis, sickle cell, BRCA..."></div>
            <button onclick="searchNLM()" class="btn-hs btn-primary-hs"><i class="fas fa-search"></i> Search</button>
            <span style="font-size:12px;color:var(--hs-muted);"><i class="fas fa-university" style="color:#7C3AED;"></i> US National Library of Medicine — free, no key</span>
          </div>
        </div>
      </div>

      <div id="nlmLoading" style="display:none;text-align:center;padding:40px;color:var(--hs-muted);"><i class="fas fa-spinner fa-spin fa-2x"></i><br><br>Searching MedlinePlus Genetics...</div>
      <div id="nlmError"   style="display:none;background:#FEE2E2;border:1px solid #FECACA;border-radius:8px;padding:14px 18px;color:#991B1B;font-size:13px;margin-bottom:16px;"></div>
      <div id="nlmEmpty"   style="display:none;text-align:center;padding:60px 20px;color:var(--hs-muted);"><i class="fas fa-dna fa-3x" style="margin-bottom:16px;opacity:.3;"></i><br>Search for any genetic condition to get data from the US National Library of Medicine.</div>

      <!-- Results list -->
      <div id="nlmResults" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:16px;"></div>

      <!-- Detail panel -->
      <div id="nlmDetail" style="display:none;"></div>
    </div><!-- /pane-nlm -->

  </div>
</div>

<!-- Add Disease Modal (pre-filled from MedlinePlus) -->
<div id="addDiseaseModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:2000;align-items:center;justify-content:center;padding:20px;overflow-y:auto;">
  <div style="background:#fff;border-radius:16px;width:100%;max-width:600px;box-shadow:var(--shadow-lg);overflow:hidden;">
    <div style="background:var(--hs-navy);color:#fff;padding:18px 24px;display:flex;justify-content:space-between;align-items:center;">
      <h5 style="margin:0;font-size:16px;font-weight:700;"><i class="fas fa-dna"></i> Import Genetic Condition</h5>
      <button onclick="document.getElementById('addDiseaseModal').style.display='none'" style="background:none;border:none;color:#fff;font-size:22px;cursor:pointer;">×</button>
    </div>
    <form method="POST" action="diseases.php" style="padding:24px;">
      <input type="hidden" name="add_disease" value="1">
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:14px;">
        <div style="grid-column:1/-1"><label class="form-label">Disease Name *</label><input type="text" name="disease_name" id="imp_name" class="form-control" required></div>
        <div><label class="form-label">Inheritance Type</label>
          <select name="inheritance_type" id="imp_inherit" class="form-select">
            <option value="complex">Complex / Multifactorial</option>
            <option value="autosomal_dominant">Autosomal Dominant</option>
            <option value="autosomal_recessive">Autosomal Recessive</option>
            <option value="x_linked">X-Linked</option>
            <option value="mitochondrial">Mitochondrial</option>
          </select>
        </div>
        <div><label class="form-label">Care Plan</label>
          <select name="care_plan" class="form-select">
            <option value="standard">Standard</option>
            <option value="moderate">Moderate</option>
            <option value="intensive">Intensive</option>
            <option value="preventive">Preventive</option>
          </select>
        </div>
        <div style="grid-column:1/-1"><label class="form-label">Key Symptoms (from NLM)</label><textarea name="key_symptoms" id="imp_symptoms" class="form-control" rows="3"></textarea></div>
        <div style="grid-column:1/-1"><label class="form-label">Related Genes</label><input type="text" name="food_triggers" id="imp_genes" class="form-control" placeholder="Genes from NLM (stored in food_triggers field)"></div>
        <div style="grid-column:1/-1"><label class="form-label">Summary / Description</label><textarea name="recommended_foods" id="imp_summary" class="form-control" rows="3"></textarea></div>
        <div><label class="form-label">Exercise Guidance</label><input type="text" name="exercise_guidance" class="form-control" placeholder="As tolerated"></div>
        <div><label class="form-label">Patient Count</label><input type="number" name="patient_count" class="form-control" value="0"></div>
      </div>
      <div style="font-size:11px;color:var(--hs-muted);margin-bottom:14px;"><i class="fas fa-info-circle"></i> Data sourced from MedlinePlus Genetics (US National Library of Medicine)</div>
      <div style="display:flex;gap:12px;">
        <button type="submit" class="btn-hs btn-primary-hs" style="flex:1;justify-content:center;"><i class="fas fa-download"></i> Import to Registry</button>
        <button type="button" onclick="document.getElementById('addDiseaseModal').style.display='none'" class="btn-hs btn-outline-hs">Cancel</button>
      </div>
    </form>
  </div>
</div>

<script src="../assets/js/main.js"></script>
<script>
// ── Tab switching ────────────────────────────────────────────────
function switchTab(tab) {
  document.getElementById('pane-local').style.display = tab === 'local' ? '' : 'none';
  document.getElementById('pane-nlm').style.display   = tab === 'nlm'   ? '' : 'none';
  ['local','nlm'].forEach(t => {
    const btn = document.getElementById('tab-'+t);
    const active = t === tab;
    btn.style.color = active ? 'var(--hs-blue)' : 'var(--hs-muted)';
    btn.style.borderBottomColor = active ? 'var(--hs-blue)' : 'transparent';
  });
  if (tab === 'nlm') document.getElementById('nlmEmpty').style.display = 'block';
}

document.getElementById('nlmQ').addEventListener('keydown', e => { if (e.key === 'Enter') searchNLM(); });

// ── Search MedlinePlus ───────────────────────────────────────────
async function searchNLM() {
  const q = document.getElementById('nlmQ').value.trim();
  if (!q) return;

  document.getElementById('nlmResults').innerHTML = '';
  document.getElementById('nlmDetail').style.display  = 'none';
  document.getElementById('nlmDetail').innerHTML = '';
  document.getElementById('nlmEmpty').style.display   = 'none';
  document.getElementById('nlmError').style.display   = 'none';
  document.getElementById('nlmLoading').style.display = 'block';

  try {
    const res  = await fetch(`../api/medlineplus-search.php?q=${encodeURIComponent(q)}`);
    const data = await res.json();
    document.getElementById('nlmLoading').style.display = 'none';

    if (data.error) {
      document.getElementById('nlmError').style.display = 'block';
      document.getElementById('nlmError').textContent   = data.error;
      return;
    }

    const list = data.results || [];
    if (list.length === 0) {
      document.getElementById('nlmEmpty').style.display = 'block';
      return;
    }

    const grid = document.getElementById('nlmResults');
    list.forEach(item => {
      const card = document.createElement('div');
      card.style.cssText = 'background:#fff;border:1.5px solid var(--hs-border);border-radius:12px;padding:16px;cursor:pointer;transition:.2s;';
      card.onmouseenter = () => card.style.borderColor = 'var(--hs-blue)';
      card.onmouseleave = () => card.style.borderColor = 'var(--hs-border)';
      card.innerHTML = `
        <div style="display:flex;align-items:flex-start;gap:10px;margin-bottom:8px;">
          <div style="width:36px;height:36px;border-radius:8px;background:linear-gradient(135deg,#7C3AED,#4285F4);display:flex;align-items:center;justify-content:center;flex-shrink:0;font-size:16px;">🧬</div>
          <div>
            <div style="font-weight:700;font-size:14px;color:var(--hs-navy);">${item.name}</div>
            <div style="font-size:10px;color:#7C3AED;font-weight:600;margin-top:1px;">NLM Genetics</div>
          </div>
        </div>
        <p style="font-size:12px;color:var(--hs-muted);line-height:1.5;margin:0 0 12px;">${item.snippet || 'Click to load full details...'}</p>
        <button onclick="loadDetail('${item.slug}','${item.name.replace(/'/g,"\\'")}')"
          style="width:100%;background:linear-gradient(135deg,#7C3AED,#4285F4);color:#fff;border:none;border-radius:7px;padding:7px;font-size:12px;font-weight:700;cursor:pointer;">
          <i class="fas fa-search-plus"></i> Load Full Details
        </button>`;
      grid.appendChild(card);
    });

  } catch(err) {
    document.getElementById('nlmLoading').style.display = 'none';
    document.getElementById('nlmError').style.display   = 'block';
    document.getElementById('nlmError').textContent     = 'Network error: ' + err.message;
  }
}

// ── Load full condition detail ────────────────────────────────────
async function loadDetail(slug, name) {
  const detail = document.getElementById('nlmDetail');
  detail.style.display = 'block';
  detail.innerHTML = `<div style="text-align:center;padding:30px;color:var(--hs-muted);"><i class="fas fa-spinner fa-spin fa-2x"></i><br><br>Loading ${name}...</div>`;
  detail.scrollIntoView({behavior:'smooth', block:'start'});

  try {
    const res  = await fetch(`../api/medlineplus-search.php?type=detail&slug=${encodeURIComponent(slug)}`);
    const d    = await res.json();
    if (d.error) { detail.innerHTML = `<div style="background:#FEE2E2;padding:14px;border-radius:8px;color:#991B1B;">${d.error}</div>`; return; }

    detail.innerHTML = `
      <div class="hs-card" style="margin-top:16px;">
        <div class="hs-card-header" style="display:flex;justify-content:space-between;align-items:center;">
          <span class="card-title" style="display:flex;align-items:center;gap:10px;">
            <span style="font-size:20px;">🧬</span> ${d.name}
            <span style="background:#7C3AED;color:#fff;padding:2px 9px;border-radius:10px;font-size:11px;">MedlinePlus Genetics</span>
          </span>
          <a href="${d.url}" target="_blank" style="font-size:12px;color:var(--hs-blue);">View on NLM <i class="fas fa-external-link-alt"></i></a>
        </div>
        <div class="hs-card-body">
          <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:14px;margin-bottom:20px;">
            <div style="background:var(--hs-off-white);border-radius:10px;padding:14px;">
              <div style="font-size:11px;font-weight:700;color:var(--hs-muted);text-transform:uppercase;margin-bottom:4px;">Inheritance</div>
              <div style="font-weight:700;color:var(--hs-navy);">${d.inheritance_label || '—'}</div>
            </div>
            <div style="background:var(--hs-off-white);border-radius:10px;padding:14px;">
              <div style="font-size:11px;font-weight:700;color:var(--hs-muted);text-transform:uppercase;margin-bottom:4px;">Related Genes</div>
              <div style="font-weight:600;color:#7C3AED;font-size:13px;">${d.genes || '—'}</div>
            </div>
            <div style="background:var(--hs-off-white);border-radius:10px;padding:14px;">
              <div style="font-size:11px;font-weight:700;color:var(--hs-muted);text-transform:uppercase;margin-bottom:4px;">Also Known As</div>
              <div style="font-size:12px;color:var(--hs-navy);">${d.synonyms || '—'}</div>
            </div>
          </div>
          ${d.symptoms ? `<div style="margin-bottom:16px;"><div style="font-weight:700;font-size:13px;color:var(--hs-navy);margin-bottom:6px;"><i class="fas fa-stethoscope" style="color:var(--hs-blue);"></i> Key Symptoms / Signs</div><p style="font-size:13px;color:#374151;line-height:1.6;background:var(--hs-off-white);border-radius:8px;padding:12px;">${d.symptoms}</p></div>` : ''}
          ${d.summary ? `<div style="margin-bottom:16px;"><div style="font-weight:700;font-size:13px;color:var(--hs-navy);margin-bottom:6px;"><i class="fas fa-info-circle" style="color:#7C3AED;"></i> Summary</div><p style="font-size:13px;color:#374151;line-height:1.6;">${d.summary}</p></div>` : ''}
          <button onclick="prefillImport(${JSON.stringify(d).replace(/</g,'&lt;')})"
            style="background:linear-gradient(135deg,#7C3AED,#4285F4);color:#fff;border:none;border-radius:9px;padding:11px 24px;font-size:13px;font-weight:700;cursor:pointer;">
            <i class="fas fa-download"></i> Import to Registry
          </button>
        </div>
      </div>`;
  } catch(err) {
    detail.innerHTML = `<div style="background:#FEE2E2;padding:14px;border-radius:8px;color:#991B1B;">Error: ${err.message}</div>`;
  }
}

// ── Pre-fill import modal ─────────────────────────────────────────
function prefillImport(d) {
  document.getElementById('imp_name').value     = d.name     || '';
  document.getElementById('imp_symptoms').value = d.symptoms || '';
  document.getElementById('imp_genes').value    = d.genes    || '';
  document.getElementById('imp_summary').value  = d.summary  || '';
  const sel = document.getElementById('imp_inherit');
  if (d.inheritance) sel.value = d.inheritance;
  document.getElementById('addDiseaseModal').style.display = 'flex';
}
</script>
</body>
</html>
