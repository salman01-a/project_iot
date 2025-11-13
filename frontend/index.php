<!-- index.php (atau .html) -->
<!doctype html>
<html>
<head><meta charset="utf-8"><title>IoT Control</title></head>
<body>
  <h1>Sensor</h1>
  <pre id="sensor">memuat...</pre>

  <button id="onBtn">Aktifkan Aktuator</button>
  <button id="offBtn">Matikan Aktuator</button>

  <script>
    async function fetchSensor() {
      const res = await fetch('http://localhost:8000/data'); // GET /data dari Express
      const json = await res.json();
      document.getElementById('sensor').textContent = JSON.stringify(json, null, 2);
    }
    async function sendCmd(cmd) {
      await fetch('http://localhost:8000/data', {
        method: 'POST',
        headers: {'Content-Type':'application/json'},
        body: JSON.stringify({ action: cmd })
      });
      fetchSensor();
    }
    document.getElementById('onBtn').onclick = () => sendCmd('TURN_ON');
    document.getElementById('offBtn').onclick = () => sendCmd('TURN_OFF');

    // polling sederhana tiap 3 detik
    fetchSensor();
    setInterval(fetchSensor, 3000);
  </script>
</body>
</html>
