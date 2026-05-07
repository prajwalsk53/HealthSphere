<?php
require_once __DIR__ . '/../config/config.php';
requireRole('patient');
$user = getCurrentUser();
$uid  = $user['id'];

$success = $error = '';
$uploadDir = __DIR__ . '/../uploads/' . $uid . '/';
if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

// ── Upload ─────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['doc_file'])) {
    $file     = $_FILES['doc_file'];
    $title    = trim($_POST['title'] ?? '');
    $docType  = $_POST['doc_type'] ?? 'other';
    $desc     = trim($_POST['description'] ?? '');
    $allowed  = ['application/pdf','image/jpeg','image/png','image/jpg','image/gif'];
    $maxSize  = 10 * 1024 * 1024; // 10 MB

    if (!$title)                                 $error = 'Please enter a title.';
    elseif ($file['error'] !== UPLOAD_ERR_OK)    $error = 'Upload failed. Please try again.';
    elseif ($file['size'] > $maxSize)            $error = 'File too large. Max 10 MB.';
    elseif (!in_array($file['type'], $allowed))  $error = 'Only PDF, JPG, PNG files allowed.';
    else {
        $ext      = pathinfo($file['name'], PATHINFO_EXTENSION);
        $safeName = preg_replace('/[^a-zA-Z0-9_\-]/', '_', pathinfo($file['name'], PATHINFO_FILENAME));
        $fileName = time() . '_' . $safeName . '.' . $ext;
        $filePath = $uploadDir . $fileName;

        if (move_uploaded_file($file['tmp_name'], $filePath)) {
            $pdo->prepare("
                INSERT INTO documents (patient_id,uploaded_by,title,description,file_path,file_name,file_type,file_size,doc_type)
                VALUES (?,?,?,?,?,?,?,?,?)
            ")->execute([$uid, $uid, $title, $desc, 'uploads/'.$uid.'/'.$fileName, $file['name'], $file['type'], $file['size'], $docType]);
            $success = 'Document "' . e($title) . '" uploaded successfully!';
        } else {
            $error = 'Failed to save file. Check server permissions.';
        }
    }
}

// ── Delete ─────────────────────────────────────────────────────────
if (isset($_GET['delete'])) {
    $did   = (int)$_GET['delete'];
    $docRow = $pdo->prepare("SELECT * FROM documents WHERE id=? AND patient_id=?");
    $docRow->execute([$did, $uid]);
    $docRow = $docRow->fetch();
    if ($docRow) {
        $fullPath = __DIR__ . '/../' . $docRow['file_path'];
        if (file_exists($fullPath)) unlink($fullPath);
        $pdo->prepare("DELETE FROM documents WHERE id=?")->execute([$did]);
        $success = 'Document deleted.';
    }
}

// ── Fetch all docs ─────────────────────────────────────────────────
$filterType = $_GET['type'] ?? '';
$whereClause = $filterType ? 'AND doc_type=?' : '';
$docsStmt = $pdo->prepare("SELECT * FROM documents WHERE patient_id=? $whereClause ORDER BY created_at DESC");
$docsStmt->execute($filterType ? [$uid, $filterType] : [$uid]);
$documents = $docsStmt->fetchAll();

$notifCount = getUnreadCount($pdo, $uid);
$msgCount   = getUnreadMessages($pdo, $uid);

$docTypes = ['lab_report'=>'Lab Report','prescription'=>'Prescription','xray'=>'X-Ray / Scan','scan'=>'MRI / CT Scan','discharge'=>'Discharge Summary','referral'=>'Referral Letter','other'=>'Other'];
$docIcons = ['lab_report'=>'🧪','prescription'=>'💊','xray'=>'🔬','scan'=>'🧠','discharge'=>'📋','referral'=>'📨','other'=>'📄'];
$docColors = ['lab_report'=>'#1565C0','prescription'=>'#16A34A','xray'=>'#7C3AED','scan'=>'#0891B2','discharge'=>'#D97706','referral'=>'#DC2626','other'=>'#5E7A99'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Medical Documents — HealthSphere</title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="../assets/css/style.css">
<style>
.doc-card { background:#fff; border:1px solid var(--hs-border); border-radius:var(--radius); padding:20px; transition:var(--transition); position:relative; overflow:hidden; }
.doc-card:hover { box-shadow:var(--shadow-md); transform:translateY(-2px); }
.doc-card .doc-type-bar { position:absolute; top:0; left:0; right:0; height:4px; }
.upload-zone { border:2px dashed var(--hs-border); border-radius:var(--radius); padding:40px; text-align:center; cursor:pointer; transition:var(--transition); }
.upload-zone:hover, .upload-zone.drag-over { border-color:var(--hs-blue); background:#EFF6FF; }
.pdf-preview { background:#F1F5F9; border-radius:8px; height:140px; display:flex; align-items:center; justify-content:center; margin-bottom:12px; font-size:48px; }
.img-preview { border-radius:8px; height:140px; width:100%; object-fit:cover; margin-bottom:12px; }
</style>
</head>
<body>
<?php include __DIR__ . '/../includes/sidebar.php'; ?>
<div class="hs-main">
  <div class="hs-topbar">
    <div>
      <div class="page-title"><i class="fas fa-folder-medical" style="color:var(--hs-blue);"></i> Medical Documents</div>
      <div class="page-subtitle">Securely store and manage your health records &middot; <?= count($documents) ?> documents</div>
    </div>
    <div class="topbar-actions">
      <button class="btn-hs btn-primary-hs" onclick="document.getElementById('uploadModal').style.display='flex'">
        <i class="fas fa-upload"></i> Upload Document
      </button>
    </div>
  </div>

  <div class="hs-content">
    <?php if ($success): ?>
    <div style="background:#DCFCE7;border:1px solid #BBF7D0;border-radius:8px;padding:12px 16px;margin-bottom:16px;color:#166534;font-size:13px;font-weight:600;display:flex;align-items:center;gap:8px;">
      <i class="fas fa-check-circle"></i> <?= e($success) ?>
    </div>
    <?php endif; ?>
    <?php if ($error): ?>
    <div style="background:#FEE2E2;border:1px solid #FECACA;border-radius:8px;padding:12px 16px;margin-bottom:16px;color:#991B1B;font-size:13px;display:flex;align-items:center;gap:8px;">
      <i class="fas fa-exclamation-circle"></i> <?= e($error) ?>
    </div>
    <?php endif; ?>

    <div style="display:grid;grid-template-columns:220px 1fr;gap:20px;">

      <!-- Sidebar filters -->
      <div>
        <div class="hs-card">
          <div class="hs-card-body" style="padding:12px;">
            <div style="font-size:11px;font-weight:700;letter-spacing:1px;text-transform:uppercase;color:var(--hs-muted);margin-bottom:10px;">Filter by Type</div>
            <a href="documents.php" style="display:flex;align-items:center;justify-content:space-between;padding:9px 12px;border-radius:8px;text-decoration:none;margin-bottom:4px;background:<?= !$filterType?'var(--hs-blue)':'transparent' ?>;color:<?= !$filterType?'#fff':'var(--hs-text)' ?>;">
              <span style="font-size:13px;font-weight:500;">All Documents</span>
              <span style="font-size:11px;background:rgba(255,255,255,.2);padding:1px 7px;border-radius:10px;"><?= count($documents) ?></span>
            </a>
            <?php foreach ($docTypes as $key => $label):
              $cnt = $pdo->prepare("SELECT COUNT(*) FROM documents WHERE patient_id=? AND doc_type=?");
              $cnt->execute([$uid, $key]);
              $cnt = $cnt->fetchColumn();
            ?>
            <a href="?type=<?= $key ?>" style="display:flex;align-items:center;justify-content:space-between;padding:9px 12px;border-radius:8px;text-decoration:none;margin-bottom:4px;transition:var(--transition);background:<?= $filterType===$key?'var(--hs-blue)':'transparent' ?>;color:<?= $filterType===$key?'#fff':'var(--hs-text)' ?>;" onmouseover="if('<?= $filterType ?>'!='<?= $key ?>')this.style.background='var(--hs-off-white)'" onmouseout="if('<?= $filterType ?>'!='<?= $key ?>')this.style.background='transparent'">
              <span style="font-size:13px;font-weight:500;display:flex;align-items:center;gap:6px;"><?= $docIcons[$key] ?> <?= $label ?></span>
              <?php if ($cnt > 0): ?>
              <span style="font-size:11px;background:<?= $filterType===$key?'rgba(255,255,255,.25)':'var(--hs-bg)' ?>;padding:1px 7px;border-radius:10px;"><?= $cnt ?></span>
              <?php endif; ?>
            </a>
            <?php endforeach; ?>

            <div style="margin-top:16px;padding-top:16px;border-top:1px solid var(--hs-border);">
              <div style="font-size:11px;font-weight:700;letter-spacing:1px;text-transform:uppercase;color:var(--hs-muted);margin-bottom:10px;">Storage</div>
              <?php
              $totalSizeStmt = $pdo->prepare("SELECT SUM(file_size) FROM documents WHERE patient_id=?");
              $totalSizeStmt->execute([$uid]);
              $totalBytes = (int)$totalSizeStmt->fetchColumn();
              $totalMB = round($totalBytes / 1024 / 1024, 1);
              $pct = min(round(($totalMB / 100) * 100), 100);
              ?>
              <div style="font-size:22px;font-weight:800;color:var(--hs-navy);"><?= $totalMB ?>MB</div>
              <div style="font-size:11px;color:var(--hs-muted);margin-bottom:6px;">of 100MB used</div>
              <div style="background:var(--hs-bg);border-radius:4px;height:6px;overflow:hidden;">
                <div style="width:<?= $pct ?>%;background:var(--hs-blue);height:100%;border-radius:4px;transition:width 1s;"></div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Documents grid -->
      <div>
        <!-- Search + view toggle -->
        <div style="display:flex;gap:10px;margin-bottom:16px;align-items:center;">
          <div class="input-icon-wrap" style="flex:1;">
            <i class="fas fa-search"></i>
            <input type="text" id="docSearch" placeholder="Search documents..." class="form-control">
          </div>
          <button id="viewGrid" class="btn-hs btn-primary-hs btn-sm-hs" onclick="switchView('grid')"><i class="fas fa-th"></i></button>
          <button id="viewList" class="btn-hs btn-outline-hs btn-sm-hs" onclick="switchView('list')"><i class="fas fa-list"></i></button>
        </div>

        <?php if (empty($documents)): ?>
        <div style="text-align:center;padding:60px 20px;background:#fff;border-radius:var(--radius);border:1px solid var(--hs-border);">
          <div style="font-size:60px;margin-bottom:16px;">📁</div>
          <h4 style="color:var(--hs-navy);margin-bottom:8px;">No Documents Yet</h4>
          <p style="color:var(--hs-muted);font-size:13px;">Upload your lab reports, prescriptions, or medical scans to keep everything in one place.</p>
          <button class="btn-hs btn-primary-hs" style="margin-top:16px;" onclick="document.getElementById('uploadModal').style.display='flex'">
            <i class="fas fa-upload"></i> Upload First Document
          </button>
        </div>
        <?php else: ?>
        <div id="docGrid" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:16px;">
          <?php foreach ($documents as $doc):
            $isImage  = str_contains($doc['file_type'], 'image');
            $typeColor= $docColors[$doc['doc_type']] ?? '#5E7A99';
            $fullPath = __DIR__ . '/../' . $doc['file_path'];
            $fileExists = file_exists($fullPath);
          ?>
          <div class="doc-card" data-title="<?= e(strtolower($doc['title'])) ?>">
            <div class="doc-type-bar" style="background:<?= $typeColor ?>;"></div>
            <?php if ($isImage && $fileExists): ?>
            <img src="/HealthSphere/<?= e($doc['file_path']) ?>" class="img-preview" alt="<?= e($doc['title']) ?>">
            <?php else: ?>
            <div class="pdf-preview" style="background:<?= $typeColor ?>18;">
              <span><?= $docIcons[$doc['doc_type']] ?? '📄' ?></span>
            </div>
            <?php endif; ?>

            <div style="display:flex;align-items:center;gap:8px;margin-bottom:6px;">
              <span style="background:<?= $typeColor ?>18;color:<?= $typeColor ?>;padding:2px 8px;border-radius:4px;font-size:11px;font-weight:700;">
                <?= $docTypes[$doc['doc_type']] ?? 'Document' ?>
              </span>
            </div>

            <div style="font-weight:700;font-size:14px;color:var(--hs-navy);margin-bottom:4px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
              <?= e($doc['title']) ?>
            </div>
            <?php if ($doc['description']): ?>
            <div style="font-size:12px;color:var(--hs-muted);margin-bottom:8px;line-height:1.5;overflow:hidden;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;">
              <?= e($doc['description']) ?>
            </div>
            <?php endif; ?>

            <div style="font-size:11px;color:var(--hs-muted);margin-bottom:12px;display:flex;gap:10px;">
              <span><i class="fas fa-file"></i> <?= round($doc['file_size']/1024, 1) ?>KB</span>
              <span><i class="fas fa-calendar"></i> <?= formatDate($doc['created_at'], 'd M Y') ?></span>
            </div>

            <div style="display:flex;gap:6px;">
              <?php if ($fileExists): ?>
              <a href="/HealthSphere/<?= e($doc['file_path']) ?>" target="_blank" class="btn-hs btn-primary-hs btn-sm-hs" style="flex:1;justify-content:center;text-decoration:none;">
                <i class="fas fa-eye"></i> View
              </a>
              <a href="/HealthSphere/<?= e($doc['file_path']) ?>" download="<?= e($doc['file_name']) ?>" class="btn-hs btn-outline-hs btn-sm-hs">
                <i class="fas fa-download"></i>
              </a>
              <?php else: ?>
              <span class="btn-hs btn-sm-hs" style="background:#F1F5F9;color:var(--hs-muted);flex:1;justify-content:center;cursor:default;">File Missing</span>
              <?php endif; ?>
              <a href="?delete=<?= $doc['id'] ?>" class="btn-hs btn-danger-hs btn-sm-hs" onclick="return confirm('Delete this document?')">
                <i class="fas fa-trash"></i>
              </a>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
      </div>

    </div><!-- /grid -->

    <!-- Security notice -->
    <div style="display:flex;align-items:center;gap:12px;background:var(--hs-off-white);border:1px solid var(--hs-border);border-radius:8px;padding:14px 18px;margin-top:20px;">
      <i class="fas fa-lock" style="color:var(--hs-blue);font-size:18px;"></i>
      <div style="font-size:13px;color:var(--hs-muted);">
        <strong style="color:var(--hs-navy);">Your documents are private and secure.</strong>
        Only you and authorised doctors you share with can access these files. All data is end-to-end protected.
      </div>
    </div>

  </div>
</div>

<!-- Upload Modal -->
<div id="uploadModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:2000;align-items:center;justify-content:center;padding:20px;">
  <div style="background:#fff;border-radius:16px;width:100%;max-width:520px;box-shadow:var(--shadow-lg);overflow:hidden;">
    <div style="background:var(--hs-navy);color:#fff;padding:18px 24px;display:flex;justify-content:space-between;align-items:center;">
      <h5 style="margin:0;font-size:16px;font-weight:700;"><i class="fas fa-upload"></i> Upload Medical Document</h5>
      <button onclick="document.getElementById('uploadModal').style.display='none'" style="background:none;border:none;color:#fff;font-size:22px;cursor:pointer;">&times;</button>
    </div>
    <form method="POST" enctype="multipart/form-data" style="padding:24px;">
      <!-- Drag-drop zone -->
      <div class="upload-zone" id="uploadZone" onclick="document.getElementById('fileInput').click()">
        <i class="fas fa-cloud-upload-alt" style="font-size:40px;color:var(--hs-blue);margin-bottom:12px;"></i>
        <div id="dropText">
          <p style="font-weight:700;color:var(--hs-navy);margin:0;">Click or drag &amp; drop your file</p>
          <p style="font-size:12px;color:var(--hs-muted);margin:4px 0 0;">PDF, JPG, PNG &mdash; Max 10MB</p>
        </div>
        <div id="filePreview" style="display:none;margin-top:10px;">
          <i class="fas fa-file-check" style="color:#16A34A;font-size:20px;"></i>
          <span id="fileName" style="font-size:13px;font-weight:600;color:#166534;"></span>
        </div>
        <input type="file" id="fileInput" name="doc_file" accept=".pdf,.jpg,.jpeg,.png" style="display:none;" required onchange="previewFile(this)">
      </div>

      <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-top:16px;">
        <div style="grid-column:1/-1;">
          <label class="form-label">Document Title *</label>
          <input type="text" name="title" class="form-control" placeholder="e.g. Blood Test Results Oct 2025" required>
        </div>
        <div>
          <label class="form-label">Document Type</label>
          <select name="doc_type" class="form-select">
            <?php foreach ($docTypes as $k => $v): ?>
            <option value="<?= $k ?>"><?= $v ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label class="form-label">&nbsp;</label>
          <div style="display:flex;align-items:center;gap:8px;padding:10px 14px;border:1px solid var(--hs-border);border-radius:8px;margin-top:0;">
            <input type="checkbox" name="share_with_doctor" id="shareDoc" style="width:16px;height:16px;accent-color:var(--hs-blue);">
            <label for="shareDoc" style="font-size:13px;cursor:pointer;color:var(--hs-text);">Share with my doctor</label>
          </div>
        </div>
        <div style="grid-column:1/-1;">
          <label class="form-label">Description (optional)</label>
          <textarea name="description" class="form-control" rows="2" placeholder="Brief notes about this document..."></textarea>
        </div>
      </div>

      <div style="display:flex;gap:12px;margin-top:20px;">
        <button type="submit" class="btn-hs btn-primary-hs" style="flex:1;justify-content:center;">
          <i class="fas fa-upload"></i> Upload Document
        </button>
        <button type="button" onclick="document.getElementById('uploadModal').style.display='none'" class="btn-hs btn-outline-hs">
          Cancel
        </button>
      </div>
    </form>
  </div>
</div>

<script src="../assets/js/main.js"></script>
<script>
// Search
document.getElementById('docSearch').addEventListener('input', function() {
  const q = this.value.toLowerCase();
  document.querySelectorAll('.doc-card').forEach(card => {
    card.style.display = card.dataset.title.includes(q) ? '' : 'none';
  });
});

// View toggle
function switchView(mode) {
  const grid = document.getElementById('docGrid');
  if (!grid) return;
  if (mode === 'list') {
    grid.style.gridTemplateColumns = '1fr';
    document.getElementById('viewList').className = 'btn-hs btn-primary-hs btn-sm-hs';
    document.getElementById('viewGrid').className = 'btn-hs btn-outline-hs btn-sm-hs';
  } else {
    grid.style.gridTemplateColumns = 'repeat(auto-fill,minmax(240px,1fr))';
    document.getElementById('viewGrid').className = 'btn-hs btn-primary-hs btn-sm-hs';
    document.getElementById('viewList').className = 'btn-hs btn-outline-hs btn-sm-hs';
  }
}

// File preview
function previewFile(input) {
  if (input.files && input.files[0]) {
    document.getElementById('dropText').style.display = 'none';
    document.getElementById('filePreview').style.display = 'block';
    document.getElementById('fileName').textContent = input.files[0].name + ' (' + (input.files[0].size/1024).toFixed(0) + ' KB)';
    document.getElementById('uploadZone').style.borderColor = '#16A34A';
  }
}

// Drag & drop
const zone = document.getElementById('uploadZone');
const fileInput = document.getElementById('fileInput');
['dragover','dragenter'].forEach(e => zone.addEventListener(e, ev => { ev.preventDefault(); zone.classList.add('drag-over'); }));
['dragleave','drop'].forEach(e => zone.addEventListener(e, ev => { ev.preventDefault(); zone.classList.remove('drag-over'); }));
zone.addEventListener('drop', ev => {
  const files = ev.dataTransfer.files;
  if (files.length) {
    const dt = new DataTransfer();
    dt.items.add(files[0]);
    fileInput.files = dt.files;
    previewFile(fileInput);
  }
});
</script>
</body>
</html>
