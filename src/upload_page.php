<?php
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
.file-list li{padding:2px 0}
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

  <!--  already-uploaded files  -->
  <?php if ($uploaded_files): ?>
  <h3>Uploaded Files:</h3>
  <button id="clearAllBtn">Clear All</button>
  <ul class="file-list" id="uploadedFilesList">
    <?php foreach ($uploaded_files as $f): ?>
    <li>
      <a href="serve.php?file=<?= urlencode(
          $f,
      ) ?>" target="_blank"><?= htmlspecialchars($f) ?></a>
      <button class="clear-btn" title="Remove from list">X</button>
    </li>
    <?php endforeach; ?>
  </ul>
  <?php endif; ?>

  <p><a href="logout.php">Logout</a></p>
</div>

<script>
const CHUNK_SIZE = 10 * 1024 * 1024;   // 10 MB
const PARALLEL   = 4;

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
    info.textContent = `${file.name} — 0 / ${chunks}`;
    prog.appendChild(info); prog.appendChild(wrap);

    let done = 0;
    const update = ()=>{ done++; bar.style.width=(done/chunks*100).toFixed(0)+'%';
                         info.textContent=`${file.name} — ${done} / ${chunks}`; };

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
    await Promise.all(workers);
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
</script>
</body>
</html>
