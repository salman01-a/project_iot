// index.php
<?php
// prototype-dashboard/index.php
// PHP 8 + Bootstrap 5 + Chart.js prototype (static dummy data)
// Bahasa: Indonesia

// Generate dummy data: 24 jam, interval 15 menit => 96 sampel
$data = [];
$now = time();
$samples = 96;
$interval = 15 * 60;
mt_srand(12345);
for ($i = $samples - 1; $i >= 0; $i--) {
    $ts = $now - $i * $interval;
    // Buat pola variasi level air (cm) — nilai antara 10 sampai 200 cm
    $base = 60 + 40 * sin($i / 8) + 20 * sin($i / 20);
    $noise = mt_rand(-50, 50) / 10;
    $ultrasonic = round(max(5, $base + $noise), 2);

    // Pembacaan raindrop raw 0 - 4095
    // Kita buat beberapa periode hujan acak
    $rain_period = (sin($i / 12) + cos($i / 7)) / 2;
    $raindrop_raw = (int) round(2000 + 1200 * $rain_period + mt_rand(-800, 800));
    $raindrop_raw = max(0, min(4095, $raindrop_raw));
    $raindrop_status = $raindrop_raw < 2000 ? 'Hujan' : 'Tidak Hujan';

    $data[] = [
        'timestamp' => date('Y-m-d H:i:s', $ts),
        'ultrasonic_cm' => $ultrasonic,
        'raindrop_raw' => $raindrop_raw,
        'raindrop_status' => $raindrop_status,
    ];
}

$json_data = json_encode($data);
?>

<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Dashboard Monitoring Sungai - Prototype</title>
  <!-- Bootstrap 5 -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
  <!-- Chart.js -->
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
  <style>
    body { padding: 20px; }
    .card-compact { padding: 12px; }
    .small-muted { font-size: 0.85rem; color: #6c757d; }
    .status-badge { font-weight: 600; }
  </style>
</head>
<body>
<div class="container-fluid">
  <div class="row mb-3">
    <div class="col-12">
      <h3 class="mb-0">Dashboard Monitoring Sungai (Prototype)</h3>
      <p class="small-muted">Data dummy 24 jam. Bahasa Indonesia. PHP 8, Bootstrap 5.</p>
    </div>
  </div>

  <div class="row g-3">
    <!-- Left: Real-time & Controls -->
    <div class="col-lg-4">
      <div class="card mb-3">
        <div class="card-body">
          <h5 class="card-title">Ringkasan Real-time</h5>
          <div id="realtime">
            <div class="mb-2"><strong>Waktu:</strong> <span id="rt-timestamp">-</span></div>
            <div class="mb-2"><strong>Ketinggian air:</strong> <span id="rt-ultrasonic">-</span> cm</div>
            <div class="mb-2"><strong>Status hujan:</strong> <span id="rt-rain-status" class="badge bg-secondary status-badge">-</span></div>
            <div class="mb-2"><strong>Raw raindrop:</strong> <span id="rt-rain-raw">-</span></div>
          </div>
        </div>
      </div>

      <div class="card mb-3">
        <div class="card-body">
          <h5 class="card-title">Kontrol Aktuator</h5>

          <div class="mb-3 form-check form-switch">
            <input class="form-check-input" type="checkbox" id="modeAuto">
            <label class="form-check-label" for="modeAuto">Mode Otomatis (ON/OFF)</label>
          </div>

          <div class="mb-3">
            <label class="form-label">Servo angle: <span id="servoValue">90</span>°</label>
            <input type="range" class="form-range" min="0" max="180" id="servoRange">
            <div class="form-text">Kontrol manual servo (0 - 180). Saat mode otomatis aktif, otomatisasi dapat mengubah nilai ini.</div>
          </div>

          <div class="mb-3 form-check form-switch">
            <input class="form-check-input" type="checkbox" id="ledToggle">
            <label class="form-check-label" for="ledToggle">LED</label>
          </div>

          <div class="mb-3 form-check form-switch">
            <input class="form-check-input" type="checkbox" id="buzzerToggle">
            <label class="form-check-label" for="buzzerToggle">Buzzer</label>
          </div>

          <div class="d-grid gap-2">
            <button id="applyManual" class="btn btn-primary">Terapkan Kontrol Manual</button>
          </div>

          <div class="mt-2 small-muted">Catatan: Saat <strong>Mode Otomatis</strong> aktif, kontrol manual masih dapat diubah namun otomatisasi dapat menimpa sesuai logika.</div>
        </div>
      </div>

      <div class="card">
        <div class="card-body">
          <h5 class="card-title">Log Singkat</h5>
          <ul id="logList" class="list-unstyled small"></ul>
        </div>
      </div>
    </div>

    <!-- Right: Chart + History -->
    <div class="col-lg-8">
      <div class="card mb-3">
        <div class="card-body">
          <h5 class="card-title">Grafik Ketinggian Air & Status Hujan (24 jam)</h5>
          <canvas id="chartMain" height="120"></canvas>
        </div>
      </div>

      <div class="card">
        <div class="card-body">
          <h5 class="card-title">Riwayat Terakhir (Tabel)</h5>
          <div class="table-responsive">
            <table class="table table-sm table-striped">
              <thead>
                <tr>
                  <th>Waktu</th>
                  <th>Ketinggian (cm)</th>
                  <th>Raw</th>
                  <th>Status Hujan</th>
                </tr>
              </thead>
              <tbody id="historyTable"></tbody>
            </table>
          </div>
        </div>
      </div>

    </div>
  </div>
</div>

<!-- Bootstrap Toast container -->
<div class="position-fixed bottom-0 end-0 p-3" style="z-index: 11">
  <div id="toastContainer"></div>
</div>

<script>
// Parse data dari PHP
const data = <?php echo $json_data; ?>;
let index = data.length - 1; // pointer ke data terbaru

// Inisialisasi elemen
const rtTimestamp = document.getElementById('rt-timestamp');
const rtUltrasonic = document.getElementById('rt-ultrasonic');
const rtRainStatus = document.getElementById('rt-rain-status');
const rtRainRaw = document.getElementById('rt-rain-raw');

const modeAuto = document.getElementById('modeAuto');
const servoRange = document.getElementById('servoRange');
const servoValue = document.getElementById('servoValue');
const ledToggle = document.getElementById('ledToggle');
const buzzerToggle = document.getElementById('buzzerToggle');
const applyManual = document.getElementById('applyManual');
const logList = document.getElementById('logList');
const toastContainer = document.getElementById('toastContainer');

// Chart setup
const ctx = document.getElementById('chartMain');
const labels = data.map(d => d.timestamp);
const ultrasonicData = data.map(d => d.ultrasonic_cm);
const rainBinary = data.map(d => d.raindrop_status === 'Hujan' ? 1 : 0);

const chart = new Chart(ctx, {
  type: 'line',
  data: {
    labels: labels,
    datasets: [
      {
        type: 'line',
        label: 'Ketinggian (cm)',
        data: ultrasonicData,
        yAxisID: 'y',
        tension: 0.3,
        pointRadius: 0.5,
      },
      {
        type: 'bar',
        label: 'Hujan (biner)',
        data: rainBinary,
        yAxisID: 'y1',
        barPercentage: 1.0,
        categoryPercentage: 1.0,
      }
    ]
  },
  options: {
    responsive: true,
    interaction: { mode: 'index', intersect: false },
    scales: {
      y: {
        type: 'linear',
        position: 'left',
        title: { display: true, text: 'Ketinggian (cm)' }
      },
      y1: {
        type: 'linear',
        position: 'right',
        min: 0,
        max: 1.2,
        grid: { drawOnChartArea: false },
        ticks: { stepSize: 1, callback: function(v){ return v ? 'Hujan' : '' } }
      }
    },
    plugins: {
      legend: { display: true }
    }
  }
});

// Update realtime display dari data[index]
function updateRealtime(i) {
  const d = data[i];
  rtTimestamp.textContent = d.timestamp;
  rtUltrasonic.textContent = d.ultrasonic_cm;
  rtRainStatus.textContent = d.raindrop_status;
  rtRainRaw.textContent = d.raindrop_raw;

  // warna badge
  if (d.raindrop_status === 'Hujan') {
    rtRainStatus.className = 'badge bg-primary status-badge';
  } else {
    rtRainStatus.className = 'badge bg-success status-badge';
  }
}

// Isi tabel history (ambil 20 terakhir)
function fillHistory() {
  const tbody = document.getElementById('historyTable');
  tbody.innerHTML = '';
  const start = Math.max(0, data.length - 20);
  for (let i = data.length - 1; i >= start; i--) {
    const r = data[i];
    const tr = document.createElement('tr');
    tr.innerHTML = `<td>${r.timestamp}</td><td>${r.ultrasonic_cm}</td><td>${r.raindrop_raw}</td><td>${r.raindrop_status}</td>`;
    tbody.appendChild(tr);
  }
}

// Toast helper
function showToast(title, body, variant = 'primary') {
  const id = 't' + Date.now();
  const el = document.createElement('div');
  el.innerHTML = `
    <div id="${id}" class="toast align-items-center text-bg-${variant} border-0 mb-2" role="alert" aria-live="assertive" aria-atomic="true">
      <div class="d-flex">
        <div class="toast-body">${body}</div>
        <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
      </div>
    </div>`;
  toastContainer.appendChild(el);
  const t = new bootstrap.Toast(document.getElementById(id), { delay: 3500 });
  t.show();
  // remove after hidden
  document.getElementById(id).addEventListener('hidden.bs.toast', function () { el.remove(); });
}

// Log helper
function addLog(text) {
  const li = document.createElement('li');
  li.textContent = `[${new Date().toLocaleTimeString()}] ${text}`;
  logList.insertBefore(li, logList.firstChild);
  // limit log length
  if (logList.childElementCount > 30) logList.removeChild(logList.lastChild);
}

// Auto-control logic: jalankan jika modeAuto.checked
function autoControl(current) {
  // Contoh logika sederhana berdasarkan ketinggian air
  const ultr = current.ultrasonic_cm;
  let servoTarget = 90;
  let ledOn = false;
  let buzzerOn = false;

  // Jika air mendekati sensor (nilai kecil) artinya tinggi air besar
  if (ultr < 30) {
    servoTarget = 160; // contoh: buka aktuator
    ledOn = true;
    buzzerOn = true;
  } else if (ultr < 60) {
    servoTarget = 120; // waspada
    ledOn = true;
    buzzerOn = false;
  } else {
    servoTarget = 40; // aman
    ledOn = false;
    buzzerOn = false;
  }

  // Terapkan ke UI (meskipun user masih bisa ubah)
  servoRange.value = servoTarget;
  servoValue.textContent = servoTarget;
  ledToggle.checked = ledOn;
  buzzerToggle.checked = buzzerOn;

  showToast('Mode Otomatis', `Auto: servo ${servoTarget}°, LED ${ledOn ? 'ON' : 'OFF'}, Buzzer ${buzzerOn ? 'ON' : 'OFF'}`, 'success');
  addLog(`Auto control applied: servo ${servoTarget}°, LED ${ledOn}, Buzzer ${buzzerOn}`);
}

// Cycle data pointer to simulate real-time streaming
function tick() {
  index = (index + 1) % data.length;
  updateRealtime(index);

  // geser chart window: kita akan highlight data terbaru
  chart.data.datasets[0].data = data.map(d => d.ultrasonic_cm);
  chart.data.datasets[1].data = data.map(d => d.raindrop_status === 'Hujan' ? 1 : 0);
  chart.update('none');

  if (modeAuto.checked) {
    autoControl(data[index]);
  }
}

// Event listeners
servoRange.addEventListener('input', () => { servoValue.textContent = servoRange.value; });
applyManual.addEventListener('click', () => {
  const s = parseInt(servoRange.value);
  const led = ledToggle.checked;
  const buz = buzzerToggle.checked;
  addLog(`Manual apply: servo ${s}°, LED ${led ? 'ON' : 'OFF'}, Buzzer ${buz ? 'ON' : 'OFF'}`);
  showToast('Manual Control', `Manual diterapkan: servo ${s}°, LED ${led ? 'ON' : 'OFF'}`, 'primary');
  if (modeAuto.checked) {
    showToast('Info', 'Mode otomatis aktif. Perubahan manual dapat ditimpa otomatis.', 'warning');
  }
});

modeAuto.addEventListener('change', () => {
  if (modeAuto.checked) {
    showToast('Mode', 'Mode otomatis diaktifkan', 'info');
    addLog('Mode otomatis diaktifkan');
  } else {
    showToast('Mode', 'Mode otomatis dimatikan', 'secondary');
    addLog('Mode otomatis dimatikan');
  }
});

// Inisialisasi tampilan awal
updateRealtime(index);
fillHistory();
servoRange.value = 90; servoValue.textContent = 90;

// Start tick setiap 6 detik untuk simulasi real-time
setInterval(tick, 6000);

</script>
</body>
</html>
