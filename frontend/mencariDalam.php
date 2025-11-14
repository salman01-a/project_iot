// index.php
<?php
// Data dummy statis untuk simulasi
$current_data = [
    'distance' => 45.2,
    'rain_status' => 'Hujan',
    'mode' => 'auto',
    'servo_status' => 'ON',
    'led_status' => 'ON', 
    'buzzer_status' => 'OFF'
];

// Generate data dummy 24 jam (setiap 30 menit)
$history_data = [];
for ($i = 48; $i >= 0; $i--) {
    $timestamp = date('Y-m-d H:i', strtotime("-$i * 30 minutes"));
    $distance = rand(3000, 8000) / 100; // 30.00 - 80.00 cm
    $rain_status = rand(0, 1) ? 'Hujan' : 'Tidak Hujan';
    
    $history_data[] = [
        'time' => $timestamp,
        'distance' => $distance,
        'rain' => $rain_status
    ];
}

// Data untuk grafik 24 jam
$chart_labels = [];
$chart_data = [];
$chart_rain = [];

foreach ($history_data as $data) {
    $chart_labels[] = date('H:i', strtotime($data['time']));
    $chart_data[] = $data['distance'];
    $chart_rain[] = $data['rain'] === 'Hujan' ? 1 : 0;
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>River Monitoring System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .card {
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            margin-bottom: 20px;
            border: none;
            border-radius: 10px;
        }
        .status-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        .sensor-card {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            color: white;
        }
        .control-card {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            color: white;
        }
        .aktuator-card {
            background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);
            color: white;
        }
        .badge-hujan {
            background-color: #dc3545;
        }
        .badge-tidak-hujan {
            background-color: #28a745;
        }
        .switch {
            position: relative;
            display: inline-block;
            width: 60px;
            height: 34px;
        }
        .switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }
        .slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #ccc;
            transition: .4s;
            border-radius: 34px;
        }
        .slider:before {
            position: absolute;
            content: "";
            height: 26px;
            width: 26px;
            left: 4px;
            bottom: 4px;
            background-color: white;
            transition: .4s;
            border-radius: 50%;
        }
        input:checked + .slider {
            background-color: #2196F3;
        }
        input:checked + .slider:before {
            transform: translateX(26px);
        }
    </style>
</head>
<body>
    <div class="container-fluid py-4">
        <!-- Header -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center">
                    <h1 class="display-6"><i class="fas fa-water me-2"></i>Sistem Monitoring Sungai</h1>
                    <div class="d-flex align-items-center">
                        <span class="badge bg-success me-2">Live</span>
                        <small class="text-muted">Last updated: <?= date('Y-m-d H:i:s') ?></small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Real-time Status Cards -->
        <div class="row">
            <!-- Sensor Data -->
            <div class="col-md-6">
                <div class="card sensor-card">
                    <div class="card-body">
                        <h5 class="card-title"><i class="fas fa-tachometer-alt me-2"></i>Data Sensor Real-time</h5>
                        <div class="row mt-4">
                            <div class="col-6">
                                <div class="text-center">
                                    <i class="fas fa-ruler-vertical fa-3x mb-2"></i>
                                    <h3><?= $current_data['distance'] ?> cm</h3>
                                    <p class="mb-0">Ketinggian Air</p>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="text-center">
                                    <i class="fas fa-cloud-rain fa-3x mb-2"></i>
                                    <h3>
                                        <span class="badge <?= $current_data['rain_status'] === 'Hujan' ? 'badge-hujan' : 'badge-tidak-hujan' ?>">
                                            <?= $current_data['rain_status'] ?>
                                        </span>
                                    </h3>
                                    <p class="mb-0">Status Hujan</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Control Panel -->
            <div class="col-md-6">
                <div class="card control-card">
                    <div class="card-body">
                        <h5 class="card-title"><i class="fas fa-cogs me-2"></i>Kontrol Sistem</h5>
                        <div class="row mt-4">
                            <div class="col-12">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <span class="h5">Mode Operasi:</span>
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" id="modeSwitch" 
                                            <?= $current_data['mode'] == 'manual' ? 'checked' : '' ?> style="width: 60px; height: 30px;">
                                        <label class="form-check-label h5" for="modeSwitch">
                                            <?= $current_data['mode'] == 'manual' ? 'Manual' : 'Otomatis' ?>
                                        </label>
                                    </div>
                                </div>
                                
                                <!-- Manual Controls (akan muncul hanya di mode manual) -->
                                <div id="manualControls" class="mt-3 <?= $current_data['mode'] == 'manual' ? '' : 'd-none' ?>">
                                    <h6>Kontrol Manual:</h6>
                                    <div class="row">
                                        <div class="col-4">
                                            <button class="btn btn-outline-light w-100 mb-2 servo-control" data-action="servo-on">
                                                <i class="fas fa-toggle-on me-1"></i> Servo ON
                                            </button>
                                        </div>
                                        <div class="col-4">
                                            <button class="btn btn-outline-light w-100 mb-2 led-control" data-action="led-on">
                                                <i class="fas fa-lightbulb me-1"></i> LED ON
                                            </button>
                                        </div>
                                        <div class="col-4">
                                            <button class="btn btn-outline-light w-100 mb-2 buzzer-control" data-action="buzzer-on">
                                                <i class="fas fa-bell me-1"></i> Buzzer ON
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Aktuator Status -->
        <div class="row">
            <div class="col-12">
                <div class="card aktuator-card">
                    <div class="card-body">
                        <h5 class="card-title"><i class="fas fa-microchip me-2"></i>Status Aktuator</h5>
                        <div class="row text-center mt-3">
                            <div class="col-md-4">
                                <div class="border rounded p-3 bg-white bg-opacity-10">
                                    <i class="fas fa-cogs fa-2x mb-2"></i>
                                    <h5>Servo Motor</h5>
                                    <span class="badge bg-<?= $current_data['servo_status'] === 'ON' ? 'success' : 'secondary' ?> fs-6">
                                        <?= $current_data['servo_status'] ?>
                                    </span>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="border rounded p-3 bg-white bg-opacity-10">
                                    <i class="fas fa-lightbulb fa-2x mb-2"></i>
                                    <h5>LED Indicator</h5>
                                    <span class="badge bg-<?= $current_data['led_status'] === 'ON' ? 'warning' : 'secondary' ?> fs-6">
                                        <?= $current_data['led_status'] ?>
                                    </span>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="border rounded p-3 bg-white bg-opacity-10">
                                    <i class="fas fa-bell fa-2x mb-2"></i>
                                    <h5>Buzzer Alarm</h5>
                                    <span class="badge bg-<?= $current_data['buzzer_status'] === 'ON' ? 'danger' : 'secondary' ?> fs-6">
                                        <?= $current_data['buzzer_status'] ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Charts Section -->
        <div class="row">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title"><i class="fas fa-chart-line me-2"></i>Grafik Ketinggian Air (24 Jam)</h5>
                        <canvas id="waterLevelChart" height="120"></canvas>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title"><i class="fas fa-chart-bar me-2"></i>Intensitas Hujan (24 Jam)</h5>
                        <canvas id="rainChart" height="120"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- History Data Table -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title"><i class="fas fa-history me-2"></i>Riwayat Data 24 Jam Terakhir</h5>
                        <div class="table-responsive">
                            <table class="table table-striped table-hover">
                                <thead class="table-dark">
                                    <tr>
                                        <th>Waktu</th>
                                        <th>Ketinggian Air (cm)</th>
                                        <th>Status Hujan</th>
                                        <th>Kategori</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach(array_slice($history_data, 0, 10) as $data): 
                                        $kategori = '';
                                        $badge_class = '';
                                        if ($data['distance'] < 40) {
                                            $kategori = 'Aman';
                                            $badge_class = 'bg-success';
                                        } elseif ($data['distance'] >= 40 && $data['distance'] < 60) {
                                            $kategori = 'Waspada';
                                            $badge_class = 'bg-warning';
                                        } else {
                                            $kategori = 'Bahaya';
                                            $badge_class = 'bg-danger';
                                        }
                                    ?>
                                    <tr>
                                        <td><?= $data['time'] ?></td>
                                        <td><?= number_format($data['distance'], 2) ?></td>
                                        <td>
                                            <span class="badge <?= $data['rain'] === 'Hujan' ? 'badge-hujan' : 'badge-tidak-hujan' ?>">
                                                <?= $data['rain'] ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge <?= $badge_class ?>"><?= $kategori ?></span>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <div class="text-center mt-3">
                            <button class="btn btn-outline-primary" id="loadMore">Muat Data Lebih Banyak</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Inisialisasi grafik ketinggian air
        const waterCtx = document.getElementById('waterLevelChart').getContext('2d');
        const waterLevelChart = new Chart(waterCtx, {
            type: 'line',
            data: {
                labels: <?= json_encode($chart_labels) ?>,
                datasets: [{
                    label: 'Ketinggian Air (cm)',
                    data: <?= json_encode($chart_data) ?>,
                    borderColor: 'rgb(75, 192, 192)',
                    backgroundColor: 'rgba(75, 192, 192, 0.1)',
                    borderWidth: 2,
                    tension: 0.4,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'top',
                    },
                    tooltip: {
                        mode: 'index',
                        intersect: false
                    }
                },
                scales: {
                    x: {
                        display: true,
                        title: {
                            display: true,
                            text: 'Waktu'
                        }
                    },
                    y: {
                        display: true,
                        title: {
                            display: true,
                            text: 'Ketinggian (cm)'
                        },
                        suggestedMin: 30,
                        suggestedMax: 80
                    }
                }
            }
        });

        // Inisialisasi grafik hujan
        const rainCtx = document.getElementById('rainChart').getContext('2d');
        const rainChart = new Chart(rainCtx, {
            type: 'bar',
            data: {
                labels: <?= json_encode(array_slice($chart_labels, 0, 24)) ?>,
                datasets: [{
                    label: 'Status Hujan',
                    data: <?= json_encode(array_slice($chart_rain, 0, 24)) ?>,
                    backgroundColor: <?= json_encode(array_map(function($val) {
                        return $val ? 'rgba(255, 99, 132, 0.8)' : 'rgba(54, 162, 235, 0.8)';
                    }, array_slice($chart_rain, 0, 24))) ?>,
                    borderColor: <?= json_encode(array_map(function($val) {
                        return $val ? 'rgb(255, 99, 132)' : 'rgb(54, 162, 235)';
                    }, array_slice($chart_rain, 0, 24))) ?>,
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return context.parsed.y === 1 ? 'Hujan' : 'Tidak Hujan';
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        max: 1,
                        ticks: {
                            callback: function(value) {
                                return value === 1 ? 'Hujan' : 'Tidak Hujan';
                            }
                        }
                    }
                }
            }
        });

        // Toggle switch handler
        document.getElementById('modeSwitch').addEventListener('change', function() {
            const isManual = this.checked;
            const manualControls = document.getElementById('manualControls');
            const modeLabel = this.nextElementSibling;
            
            if (isManual) {
                modeLabel.textContent = 'Manual';
                manualControls.classList.remove('d-none');
                showAlert('Mode diubah ke: Manual', 'warning');
            } else {
                modeLabel.textContent = 'Otomatis';
                manualControls.classList.add('d-none');
                showAlert('Mode diubah ke: Otomatis', 'info');
            }
        });

        // Manual control buttons
        document.querySelectorAll('.servo-control, .led-control, .buzzer-control').forEach(button => {
            button.addEventListener('click', function() {
                const action = this.getAttribute('data-action');
                const device = this.classList.contains('servo-control') ? 'Servo Motor' : 
                              this.classList.contains('led-control') ? 'LED Indicator' : 'Buzzer Alarm';
                
                showAlert(`${device} diaktifkan secara manual`, 'success');
            });
        });

        // Load more data button
        document.getElementById('loadMore').addEventListener('click', function() {
            showAlert('Memuat data tambahan...', 'info');
            // Simulasi loading
            setTimeout(() => {
                showAlert('Data berhasil dimuat', 'success');
            }, 1000);
        });

        function showAlert(message, type) {
            const alertDiv = document.createElement('div');
            alertDiv.className = `alert alert-${type} alert-dismissible fade show position-fixed`;
            alertDiv.style.cssText = 'top: 20px; right: 20px; z-index: 1050; min-width: 300px;';
            alertDiv.innerHTML = `
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;
            document.body.appendChild(alertDiv);
            
            setTimeout(() => {
                alertDiv.remove();
            }, 3000);
        }

        // Simulasi update data real-time setiap 10 detik
        setInterval(() => {
            // Update waktu terakhir
            document.querySelector('small.text-muted').textContent = 'Last updated: ' + new Date().toLocaleString();
        }, 10000);
    </script>
</body>
</html>
