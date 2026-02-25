<?php
/* error_reporting(E_ALL); */
/* ini_set("display_errors", "1"); */

/* ----------  auth – IP lock, no session hold  ---------- */
session_save_path(__DIR__ . "/tmp_sessions");
session_start();
include __DIR__ . "/auth.php";
session_write_close();

/* ----------  dirs  ---------- */
$UPLOAD_DIR = __DIR__ . "/uploads";
$TMP_DIR = __DIR__ . "/tmp_chunks";
if (!is_dir($UPLOAD_DIR)) {
    mkdir($UPLOAD_DIR, 0755, true);
}
if (!is_dir($TMP_DIR)) {
    mkdir($TMP_DIR, 0755, true);
}

/* ----------  already finished files  ---------- */
$uploaded_files = array_diff(scandir($UPLOAD_DIR), [".", ".."]);

/* ----------  read & prune the upload-hash cookie  ---------- */
$cookie_hashes = [];
if (!empty($_COOKIE["file_hashes"])) {
    $decoded = @unserialize($_COOKIE["file_hashes"]);
    if (is_array($decoded)) {
        $cookie_hashes = $decoded;
    }
}

/* Remove entries for files that no longer exist on disk */
$pruned = false;
foreach (array_keys($cookie_hashes) as $name) {
    if (!in_array($name, $uploaded_files, true)) {
        unset($cookie_hashes[$name]);
        $pruned = true;
    }
}

/* Write back the pruned cookie — max-age 400 days (browser ceiling) */
if ($pruned) {
    $cookie_val = serialize($cookie_hashes);
    setcookie(
        "file_hashes",
        $cookie_val,
        time() + 34560000,
        "/",
        "",
        true,
        false,
    );
}

/* ---------- Helper: delete a single file ---------- */
function delete_file($filename)
{
    global $UPLOAD_DIR;
    $file = $UPLOAD_DIR . "/" . basename($filename); // avoid path traversal
    if (file_exists($file)) {
        unlink($file);
    }
}

/* ---------- Helper: delete all uploaded files ---------- */
function delete_all_files()
{
    global $UPLOAD_DIR;
    $files = array_diff(scandir($UPLOAD_DIR), [".", ".."]);
    foreach ($files as $f) {
        unlink($UPLOAD_DIR . "/" . $f);
    }
}

/* ---------- Handle AJAX POST requests ---------- */
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    header("Content-Type: application/json");
    if (!empty($_POST["delete_file"])) {
        delete_file($_POST["delete_file"]);
        echo json_encode(["status" => "ok", "file" => $_POST["delete_file"]]);
        exit();
    }
    if (!empty($_POST["delete_all"])) {
        delete_all_files();
        echo json_encode(["status" => "ok"]);
        exit();
    }
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Fast parallel upload</title>
<style>
body{margin:0;font-family:Arial,Helvetica,sans-serif;background:#111827;color:#fff;display:flex;justify-content:center;align-items:flex-start;min-height:100vh;padding-top:40px}
.container{background:rgba(255,255,255,.05);padding:40px;border-radius:14px;backdrop-filter:blur(10px);box-shadow:0 10px 30px rgba(0,0,0,.5);width:550px;text-align:center}
input[type=file]{display:none}
button{padding:10px 20px;border-radius:6px;border:none;background:#2563eb;color:#fff;cursor:pointer;margin:5px}
button:hover{background:#1e40af}
.progress-wrapper{background:rgba(255,255,255,.1);border-radius:6px;margin:8px 0;height:18px}
.progress-bar{background:#2563eb;height:100%;border-radius:6px;width:0}
.chunk-info{font-size:12px;margin-bottom:8px}
.speed-log{font-size:11px;font-family:monospace;margin-top:8px;white-space:pre}
.file-list{text-align:left;margin-top:20px;max-height:300px;overflow-y:auto}
.file-list li{padding:4px 0}
.file-hash{display:block;font-size:10px;font-family:monospace;color:#9ca3af;word-break:break-all;margin-top:1px}
.file-hash.upload{color:#6ee7b7}
a{color:#60a5fa;text-decoration:none}
a:hover{text-decoration:underline}
.clear-btn{margin-left:8px;color:#fff;background:red;border:none;border-radius:4px;cursor:pointer;padding:2px 6px}
#clearAllBtn{margin-bottom:10px;color:#fff;background:red;border:none;border-radius:4px;padding:5px 10px;cursor:pointer}
</style>
</head>
<body>
<div class="container">
  <h1>Upload Files / Folder</h1>
  <button id="selFiles">Select Files</button>
  <button id="selFolder">Select Folder</button>
  <input type="file" id="fileInput" multiple>
  <input type="file" id="folderInput" webkitdirectory>
  <div id="progress"></div>
  <div id="speedBox" class="speed-log"></div>
  <!-- ---------- Search for uploaded files ---------- -->
  <div style="margin-top:20px; text-align:left;">
    <label for="fileSearch" style="font-weight:bold; display:block; margin-bottom:4px;">Search Uploaded Files:</label>
    <input type="text" id="fileSearch" placeholder="Type to filter..." style="width:100%; padding:6px; border-radius:4px; border:1px solid #ccc; background:#1f2937; color:#fff;">
  </div>

  <!--  already-uploaded files  -->
  <?php if ($uploaded_files): ?>
  <h3>Uploaded Files:</h3>
  <button id="clearAllBtn">Clear All</button>
  <ul class="file-list" id="uploadedFilesList">
    <?php foreach ($uploaded_files as $f): ?>
    <?php $server_hash = hash_file("sha256", $UPLOAD_DIR . "/" . $f); ?>
    <?php $upload_hash = $cookie_hashes[$f] ?? null; ?>
    <li>
      <a href="serve.php?file=<?= urlencode(
          $f,
      ) ?>" target="_blank"><?= htmlspecialchars($f) ?></a>
      <button class="clear-btn" data-filename="<?= htmlspecialchars(
          $f,
      ) ?>" title="Remove from list">X</button>
      <span class="file-hash">SHA-256 (server): <?= $server_hash ?></span>
      <?php if ($upload_hash): ?>
      <span class="file-hash upload">SHA-256 (upload): <?= htmlspecialchars(
          $upload_hash,
      ) ?></span>
      <?php else: ?>
      <span class="file-hash upload">SHA-256 (upload): n/a</span>
      <?php endif; ?>
    </li>
    <?php endforeach; ?>
  </ul>
  <?php endif; ?>

  <p><a href="logout.php">Logout</a></p>
</div>

<script>
const CHUNK_SIZE = 10 * 1024 * 1024;   // 10 MB
const PARALLEL   = 4;
const COOKIE_MAX_AGE = 34560000;        // 400 days in seconds

/* ----------- cuz json_decode() doesn't exist :( ----- */
function phpSerialize(obj){
  const keys = Object.keys(obj);
  let out = `a:${keys.length}:{`;
  for(const k of keys){
    const v = obj[k];
    out += `s:${k.length}:"${k}";s:${v.length}:"${v}";`;
  }
  out += '}';
  return out;
}

function phpUnserialize(str){
  const result = {};
  const regex = /s:(\d+):"(.*?)";s:(\d+):"(.*?)";/g;
  let match;
  while((match = regex.exec(str)) !== null){
    result[match[2]] = match[4];
  }
  return result;
}

/* ----------  cookie helpers  ---------- */
function getHashCookie(){
  const m = document.cookie.match(/(?:^|;\s*)file_hashes=([^;]*)/);
  if(!m) return {};
  try{
    return phpUnserialize(decodeURIComponent(m[1]));
  }catch(e){
    return {};
  }
}

function setHashCookie(obj){
  const val = encodeURIComponent(phpSerialize(obj));
  document.cookie = `file_hashes=${val}; path=/; max-age=${COOKIE_MAX_AGE}; secure; samesite=lax`;
}

function saveFileHash(name, hash){
  const obj = getHashCookie();
  obj[name] = hash;
  setHashCookie(obj);
}

/* ----------  worker stats  ---------- */
const stats = Array.from({length: PARALLEL}, (_,id)=>({id, chunk:-1, start:0, bytes:0}));
const speedBox = document.getElementById('speedBox');
setInterval(()=>{
  const lines = ['Worker Chunk  Speed'];
  stats.forEach(w=>{
    if(w.chunk===-1){ lines.push(`  ${w.id}   idle    —`); return; }
    const el = (performance.now()-w.start)/1000;
    const spd = el ? (w.bytes/el/1e6).toFixed(2) : '0.00';
    lines.push(`  ${w.id}   #${w.chunk}  ${spd} MB/s`);
  });
  speedBox.textContent = lines.join('\n');
},1000);

/* ----------  upload core  ---------- */
/* ----------  client-side SHA-256  ---------- */
async function hashFile(file){
  const buf = await file.arrayBuffer();
  const hash = await crypto.subtle.digest('SHA-256', buf);
  return Array.from(new Uint8Array(hash)).map(b=>b.toString(16).padStart(2,'0')).join('');
}

async function uploadFiles(list){
  const prog = document.getElementById('progress');
  prog.innerHTML = '';
  for(const file of list){
    const chunks = Math.ceil(file.size/CHUNK_SIZE);
    /* DOM */
    const wrap = document.createElement('div'); wrap.className='progress-wrapper';
    const bar  = document.createElement('div'); bar.className='progress-bar';
    wrap.appendChild(bar);
    const info = document.createElement('div'); info.className='chunk-info';
    info.textContent = `${file.name} — 0 / ${chunks} — SHA-256: computing…`;
    prog.appendChild(info); prog.appendChild(wrap);

    let done = 0;
    let fileHash = null;
    const updateInfo = ()=>{
      const hashStr = fileHash ? fileHash : 'computing…';
      info.textContent = `${file.name} — ${done} / ${chunks} — SHA-256: ${hashStr}`;
    };
    const update = ()=>{ done++; bar.style.width=(done/chunks*100).toFixed(0)+'%'; updateInfo(); };

    /* start hashing in parallel with upload */
    const hashPromise = hashFile(file).then(h=>{ fileHash = h; updateInfo(); });

    const queue = [];
    for(let i=0;i<chunks;i++){
      const start=i*CHUNK_SIZE, end=Math.min(file.size,start+CHUNK_SIZE);
      queue.push({idx:i, blob:file.slice(start,end)});
    }
    const workers = Array.from({length:PARALLEL}, async (_,wid)=>{
      while(queue.length){
        const {idx,blob} = queue.shift();
        stats[wid].chunk = idx;
        stats[wid].bytes = blob.size;
        stats[wid].start = performance.now();
        await fetch('upload.php',{
          method:'POST',
          headers:{
            'X-Filename': file.name,
            'X-Chunk'   : idx,
            'X-Chunks'  : chunks
          },
          body: blob
        });
        stats[wid].chunk = -1;
        update();
      }
    });
    await Promise.all([...workers, hashPromise]);

    /* persist the client-side hash into the cookie */
    if(fileHash){
      saveFileHash(file.name, fileHash);
    }
  }
  const ok = document.createElement('div');
  ok.textContent = 'All uploads finished – reloading…';
  prog.appendChild(ok);
  setTimeout(()=>location.reload(),1200);
}

/* ----------  UI glue  ---------- */
document.getElementById('selFiles').onclick   = ()=>document.getElementById('fileInput').click();
document.getElementById('selFolder').onclick  = ()=>document.getElementById('folderInput').click();
document.getElementById('fileInput').onchange   = e=>uploadFiles(e.target.files);
document.getElementById('folderInput').onchange = e=>uploadFiles(e.target.files);

/* ---------- Delete single file ---------- */
document.querySelectorAll('.clear-btn').forEach(btn => {
    btn.onclick = async (e) => {
        const filename = btn.dataset.filename;
        if (!confirm(`Delete "${filename}"?`)) return;

        const form = new FormData();
        form.append('delete_file', filename);

        try {
            const res = await fetch('upload_page.php', { method: 'POST', body: form });
            const data = await res.json();
            if (data.status === 'ok') {
                // Remove the file from the list
                btn.closest('li').remove();
            }
        } catch (err) {
            alert('Failed to delete file.');
            console.error(err);
        }
    };
});

/* ---------- Delete all files ---------- */
document.getElementById('clearAllBtn').onclick = async (e) => {
    if (!confirm('Delete all uploaded files?')) return;

    const form = new FormData();
    form.append('delete_all', '1');

    try {
        const res = await fetch('upload_page.php', { method: 'POST', body: form });
        const data = await res.json();
        if (data.status === 'ok') {
            // Clear the file list in the DOM
            const ul = document.getElementById('uploadedFilesList');
            if (ul) ul.innerHTML = '';
        }
    } catch (err) {
        alert('Failed to delete all files.');
        console.error(err);
    }
}
/* ---------- Search / filter uploaded files ---------- */
const searchInput = document.getElementById('fileSearch');
searchInput.addEventListener('input', () => {
    const filter = searchInput.value.toLowerCase();
    const ul = document.getElementById('uploadedFilesList');
    if (!ul) return;
    const items = ul.querySelectorAll('li');
    items.forEach(li => {
        const text = li.querySelector('a')?.textContent.toLowerCase() || '';
        li.style.display = text.includes(filter) ? '' : 'none';
    });
});
</script>
</body>
</html>
