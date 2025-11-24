<?php
// Konfigurasi API
$api_url = 'http://localhost:8000/data';
$latest_api_url = 'http://localhost:8000/latest';

// Fungsi untuk mengambil data dari API
function fetchDataFromAPI($url) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);  
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code === 200 && $response) {
        return json_decode($response, true);
    }
    
    return null;
}

// Ambil data dari API
$api_data = fetchDataFromAPI($api_url);
$latest_api_data = fetchDataFromAPI($latest_api_url);

// Jika API tidak tersedia, gunakan data dummy sebagai fallback
if (!$api_data || !isset($api_data['data'])) {
    // Data dummy statis untuk simulasi (fallback)
    $now = time();
    $samples = 96;
    $interval = 15 * 60;
    mt_srand(12345);

    $history_data = [];
    for ($i = $samples - 1; $i >= 0; $i--) {
        $ts = $now - $i * $interval;
        $base = 60 + 40 * sin($i / 8) + 20 * sin($i / 20);
        $noise = mt_rand(-50, 50) / 10;
        $ultrasonic = round(max(5, $base + $noise), 2);

        $rain_period = (sin($i / 12) + cos($i / 7)) / 2;
        $raindrop_raw = (int) round(2000 + 1200 * $rain_period + mt_rand(-800, 800));
        $raindrop_raw = max(0, min(4095, $raindrop_raw));
        $raindrop_status = $raindrop_raw < 2000 ? 'Hujan' : 'Tidak Hujan';

        $history_data[] = [
            'time' => date('Y-m-d H:i:s', $ts),
            'distance' => $ultrasonic,
            'rain' => $raindrop_status,
            'raindrop_raw' => $raindrop_raw
        ];
    }
} else {
    // Proses data dari API
    $history_data = [];
    $api_sensor_data = $api_data['data'];
    
    // Urutkan data berdasarkan waktu (terlama ke terbaru)
    usort($api_sensor_data, function($a, $b) {
        return strtotime($a['created_at']) - strtotime($b['created_at']);
    });
    
    foreach ($api_sensor_data as $sensor) {
        $history_data[] = [
            'time' => date('Y-m-d H:i:s', strtotime($sensor['created_at'])),
            'distance' => $sensor['ultrasonic_data'],
            'rain' => $sensor['raindrops_status'],
            'raindrop_raw' => 0 // Tidak tersedia di API
        ];
    }
    
    // Jika data dari API kurang dari 96 sample, tambahkan data dummy untuk melengkapi
    $current_count = count($history_data);
    if ($current_count < 96) {
        $now = time();
        $missing_samples = 96 - $current_count;
        
        for ($i = $missing_samples - 1; $i >= 0; $i--) {
            $ts = $now - ($i + $current_count) * 900; // 15 menit interval
            $base = 60 + 40 * sin($i / 8) + 20 * sin($i / 20);
            $noise = mt_rand(-50, 50) / 10;
            $ultrasonic = round(max(5, $base + $noise), 2);

            $rain_period = (sin($i / 12) + cos($i / 7)) / 2;
            $raindrop_raw = (int) round(2000 + 1200 * $rain_period + mt_rand(-800, 800));
            $raindrop_raw = max(0, min(4095, $raindrop_raw));
            $raindrop_status = $raindrop_raw < 2000 ? 'Hujan' : 'Tidak Hujan';

            array_unshift($history_data, [
                'time' => date('Y-m-d H:i:s', $ts),
                'distance' => $ultrasonic,
                'rain' => $raindrop_status,
                'raindrop_raw' => $raindrop_raw
            ]);
        }
    }
}

// Data current - ambil dari API latest jika tersedia, atau dari data historis
if ($latest_api_data && $latest_api_data['success'] && $latest_api_data['data']) {
    // Gunakan data dari API latest
    $current_data = [
        'distance' => $latest_api_data['data']['ultrasonic_data'],
        'rain_status' => $latest_api_data['data']['raindrops_status'],
        'mode' => $latest_api_data['device']['mode'],
        'servo_status' => $latest_api_data['device']['servo'] ? 'ON' : 'OFF',
        'led_status' => $latest_api_data['device']['led'] ? 'ON' : 'OFF', 
        'buzzer_status' => $latest_api_data['device']['buzzer'] ? 'ON' : 'OFF'
    ];
} else {
    // Fallback ke data historis
    $last_data = $history_data[count($history_data)-1];
    $is_activated = $last_data['distance'] < 10;

    $current_data = [
        'distance' => $last_data['distance'],
        'rain_status' => $last_data['rain'],
        'mode' => 'otomatis', // Default mode otomatis
        'servo_status' => $is_activated ? 'ON' : 'OFF',
        'led_status' => $is_activated ? 'ON' : 'OFF', 
        'buzzer_status' => $is_activated ? 'ON' : 'OFF'
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

$json_data = json_encode($history_data);
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
    <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-annotation@2.2.1/dist/chartjs-plugin-annotation.min.js"></script>
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
        .control-disabled {
            opacity: 0.6;
            pointer-events: none;
        }
        .chart-container {
            position: relative;
            height: 300px;
            width: 100%;
        }
        .gradient-bg {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        .servo-toggle {
            transform: scale(1.5);
            margin: 0 10px;
        }
        .loading-spinner {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid #f3f3f3;
            border-top: 3px solid #3498db;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
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
                        <small class="text-muted" id="lastUpdated">Last updated: <?= date('Y-m-d H:i:s') ?></small>
                        <span class="badge ms-2" id="apiStatus">
                            <?php if ($latest_api_data && $latest_api_data['success']): ?>
                                <span class="badge bg-info">API Connected</span>
                            <?php else: ?>
                                <span class="badge bg-warning">Using Fallback Data</span>
                            <?php endif; ?>
                        </span>
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
                                    <h3 id="rt-distance"><?= number_format($current_data['distance'], 2) ?> cm</h3>
                                    <p class="mb-0">Ketinggian Air</p>
                                    <small class="text-warning" id="distance-warning">
                                        <?php if ($current_data['distance'] < 10): ?>
                                            ⚠️ Tinggi air kritis!
                                        <?php else: ?>
                                            ✓ Tinggi air normal
                                        <?php endif; ?>
                                    </small>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="text-center">
                                    <i class="fas fa-cloud-rain fa-3x mb-2"></i>
                                    <h3>
                                        <span class="badge <?= $current_data['rain_status'] === 'Hujan' ? 'badge-hujan' : 'badge-tidak-hujan' ?>" id="rt-rain-status">
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
                                        <input class="form-check-input" type="checkbox" id="modeAuto" 
                                            <?= $current_data['mode'] == 'otomatis' ? 'checked' : '' ?> style="width: 60px; height: 30px;">
                                        <label class="form-check-label h5" for="modeAuto">
                                            <span id="modeText"><?= $current_data['mode'] == 'otomatis' ? 'Otomatis' : 'Manual' ?></span>
                                        </label>
                                    </div>
                                </div>
                                
                                <!-- Kontrol Servo ON/OFF -->
                                <div class="mb-3" id="servoControl">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <span class="h6">Servo Motor:</span>
                                        <div class="form-check form-switch">
                                            <input class="form-check-input servo-toggle" type="checkbox" id="servoToggle" 
                                                <?= $current_data['servo_status'] == 'ON' ? 'checked' : '' ?>>
                                            <label class="form-check-label h6" for="servoToggle">
                                                <span id="servoStatusText"><?= $current_data['servo_status'] ?></span>
                                            </label>
                                        </div>
                                    </div>
                                </div>

                                <div class="row" id="manualControls">
                                    <div class="col-4">
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" id="ledToggle" 
                                                <?= $current_data['led_status'] == 'ON' ? 'checked' : '' ?>>
                                            <label class="form-check-label" for="ledToggle">LED</label>
                                        </div>
                                    </div>
                                    <div class="col-4">
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" id="buzzerToggle"
                                                <?= $current_data['buzzer_status'] == 'ON' ? 'checked' : '' ?>>
                                            <label class="form-check-label" for="buzzerToggle">Buzzer</label>
                                        </div>
                                    </div>
                                    <div class="col-4">
                                        <button id="applyManual" class="btn btn-outline-light w-100">
                                            <span id="applyText">Terapkan</span>
                                            <span id="applySpinner" class="loading-spinner d-none ms-2"></span>
                                        </button>
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
                                    <span class="badge <?= $current_data['servo_status'] == 'ON' ? 'bg-success' : 'bg-secondary' ?> fs-6" id="servo-status">
                                        <?= $current_data['servo_status'] ?>
                                    </span>
                                    <div class="mt-2">
                                        <small id="servoPosition">Posisi: <?= $current_data['servo_status'] == 'ON' ? '180° (Tertutup)' : '0° (Terbuka)' ?></small>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="border rounded p-3 bg-white bg-opacity-10">
                                    <i class="fas fa-lightbulb fa-2x mb-2"></i>
                                    <h5>LED Indicator</h5>
                                    <span class="badge <?= $current_data['led_status'] == 'ON' ? 'bg-warning' : 'bg-secondary' ?> fs-6" id="led-status">
                                        <?= $current_data['led_status'] ?>
                                    </span>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="border rounded p-3 bg-white bg-opacity-10">
                                    <i class="fas fa-bell fa-2x mb-2"></i>
                                    <h5>Buzzer Alarm</h5>
                                    <span class="badge <?= $current_data['buzzer_status'] == 'ON' ? 'bg-danger' : 'bg-secondary' ?> fs-6" id="buzzer-status">
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
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title"><i class="fas fa-chart-line me-2"></i>Grafik Ketinggian Air & Status Hujan (24 Jam)</h5>
                        <div class="chart-container">
                            <canvas id="mainChart"></canvas>
                        </div>
                        <div class="mt-3 text-center">
                            <div class="btn-group btn-group-sm" role="group">
                                <button type="button" class="btn btn-outline-primary active" data-range="24">24 Jam</button>
                                <button type="button" class="btn btn-outline-primary" data-range="12">12 Jam</button>
                                <button type="button" class="btn btn-outline-primary" data-range="6">6 Jam</button>
                                <button type="button" class="btn btn-outline-primary" data-range="3">3 Jam</button>
                            </div>
                        </div>
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
                                        <th>Status Sistem</th>
                                    </tr>
                                </thead>
                                <tbody id="historyTable">
                                    <?php 
                                    $display_data = array_slice($history_data, -10);
                                    foreach($display_data as $data): 
                                        $status_sistem = $data['distance'] < 10 ? 'Aktif' : 'Non-Aktif';
                                        $badge_class = $data['distance'] < 10 ? 'bg-danger' : 'bg-success';
                                    ?>
                                    <tr>
                                        <td><?= date('H:i', strtotime($data['time'])) ?></td>
                                        <td><?= number_format($data['distance'], 2) ?></td>
                                        <td>
                                            <span class="badge <?= $data['rain'] === 'Hujan' ? 'badge-hujan' : 'badge-tidak-hujan' ?>">
                                                <?= $data['rain'] ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge <?= $badge_class ?>">
                                                <?= $status_sistem ?>
                                            </span>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Data dari PHP (untuk fallback)
        const data = <?= $json_data ?>;
        let currentIndex = data.length - 1;
        let currentRange = 96; // 24 jam * 4 data per jam

        // Inisialisasi grafik kombinasi dengan desain yang lebih baik
        const mainCtx = document.getElementById('mainChart').getContext('2d');
        
        // Gradient untuk line chart
        const gradient = mainCtx.createLinearGradient(0, 0, 0, 400);
        gradient.addColorStop(0, 'rgba(75, 192, 192, 0.6)');
        gradient.addColorStop(1, 'rgba(75, 192, 192, 0.1)');

        const mainChart = new Chart(mainCtx, {
            type: 'line',
            data: {
                labels: <?= json_encode($chart_labels) ?>,
                datasets: [
                    {
                        type: 'line',
                        label: 'Ketinggian Air (cm)',
                        data: <?= json_encode($chart_data) ?>,
                        borderColor: 'rgb(75, 192, 192)',
                        backgroundColor: gradient,
                        borderWidth: 3,
                        tension: 0.4,
                        fill: true,
                        yAxisID: 'y',
                        pointBackgroundColor: 'rgb(75, 192, 192)',
                        pointBorderColor: '#fff',
                        pointBorderWidth: 2,
                        pointRadius: 4,
                        pointHoverRadius: 6,
                        pointHitRadius: 8,
                        pointHoverBackgroundColor: 'rgb(75, 192, 192)',
                        pointHoverBorderColor: '#fff',
                        pointHoverBorderWidth: 2
                    },
                    {
                        type: 'bar',
                        label: 'Status Hujan',
                        data: <?= json_encode($chart_rain) ?>,
                        backgroundColor: <?= json_encode(array_map(function($val) {
                            return $val ? 'rgba(255, 99, 132, 0.7)' : 'rgba(54, 162, 235, 0.7)';
                        }, $chart_rain)) ?>,
                        borderColor: <?= json_encode(array_map(function($val) {
                            return $val ? 'rgb(255, 99, 132)' : 'rgb(54, 162, 235)';
                        }, $chart_rain)) ?>,
                        borderWidth: 1,
                        yAxisID: 'y1',
                        barPercentage: 1,
                        categoryPercentage: 1
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: { 
                    mode: 'index', 
                    intersect: false 
                },
                plugins: {
                    legend: { 
                        position: 'top',
                        labels: {
                            color: '#333',
                            font: {
                                size: 12,
                                weight: 'bold'
                            },
                            padding: 20,
                            usePointStyle: true,
                            pointStyle: 'circle'
                        }
                    },
                    tooltip: {
                        backgroundColor: 'rgba(0, 0, 0, 0.8)',
                        titleColor: '#fff',
                        bodyColor: '#fff',
                        borderColor: 'rgba(255, 255, 255, 0.2)',
                        borderWidth: 1,
                        cornerRadius: 8,
                        displayColors: true,
                        callbacks: {
                            label: function(context) {
                                if (context.datasetIndex === 1) {
                                    return context.parsed.y === 1 ? 'Status: Hujan' : 'Status: Tidak Hujan';
                                }
                                return `Ketinggian: ${context.parsed.y} cm`;
                            },
                            title: function(tooltipItems) {
                                return tooltipItems[0].label + ' WIB';
                            }
                        }
                    },
                    annotation: {
                        annotations: {
                            bahayaLine: {
                                type: 'line',
                                yMin: 10,
                                yMax: 10,
                                borderColor: 'rgb(220, 53, 69)',
                                borderWidth: 2,
                                borderDash: [5, 5],
                                label: {
                                    display: true,
                                    content: 'Batas Aktifasi (10cm)',
                                    position: 'start',
                                    backgroundColor: 'rgb(220, 53, 69)',
                                    color: 'white',
                                    font: {
                                        size: 10,
                                        weight: 'bold'
                                    }
                                }
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        grid: {
                            color: 'rgba(0, 0, 0, 0.05)',
                            drawBorder: false
                        },
                        ticks: {
                            color: '#666',
                            maxTicksLimit: 12,
                            font: {
                                size: 10
                            }
                        }
                    },
                    y: {
                        type: 'linear',
                        position: 'left',
                        title: { 
                            display: true, 
                            text: 'Ketinggian Air (cm)',
                            color: 'rgb(75, 192, 192)',
                            font: {
                                size: 12,
                                weight: 'bold'
                            }
                        },
                        min: 0,
                        max: 120,
                        grid: {
                            color: 'rgba(0, 0, 0, 0.05)',
                            drawBorder: false
                        },
                        ticks: {
                            color: '#666',
                            stepSize: 20,
                            font: {
                                size: 10
                            }
                        }
                    },
                    y1: {
                        type: 'linear',
                        position: 'right',
                        min: 0,
                        max: 1.2,
                        grid: {
                            drawOnChartArea: false,
                        },
                        title: { 
                            display: true, 
                            text: 'Status Hujan',
                            color: 'rgb(255, 99, 132)',
                            font: {
                                size: 12,
                                weight: 'bold'
                            }
                        },
                        ticks: {
                            stepSize: 1,
                            color: '#666',
                            callback: function(value) {
                                return value === 1 ? 'Hujan' : 'Tidak Hujan';
                            },
                            font: {
                                size: 10
                            }
                        }
                    }
                },
                animation: {
                    duration: 1000,
                    easing: 'easeOutQuart'
                },
                hover: {
                    animationDuration: 400
                },
                responsiveAnimationDuration: 500
            }
        });

        // Fungsi untuk mengubah range data grafik
        function updateChartRange(hours) {
            const pointsPerHour = 4; // 4 data per jam (setiap 15 menit)
            const dataPoints = hours * pointsPerHour;
            
            // Update data yang ditampilkan
            const startIndex = Math.max(0, data.length - dataPoints);
            const displayLabels = <?= json_encode($chart_labels) ?>.slice(startIndex);
            const displayData = <?= json_encode($chart_data) ?>.slice(startIndex);
            const displayRain = <?= json_encode($chart_rain) ?>.slice(startIndex);
            
            mainChart.data.labels = displayLabels;
            mainChart.data.datasets[0].data = displayData;
            mainChart.data.datasets[1].data = displayRain;
            mainChart.data.datasets[1].backgroundColor = displayRain.map(val => 
                val ? 'rgba(255, 99, 132, 0.7)' : 'rgba(54, 162, 235, 0.7)'
            );
            
            mainChart.update('none');
            currentRange = dataPoints;
        }

        // Event listener untuk tombol range
        document.querySelectorAll('[data-range]').forEach(button => {
            button.addEventListener('click', function() {
                // Update active state
                document.querySelectorAll('[data-range]').forEach(btn => {
                    btn.classList.remove('active');
                });
                this.classList.add('active');
                
                // Update chart
                const hours = parseInt(this.getAttribute('data-range'));
                updateChartRange(hours);
            });
        });

        // Fungsi untuk mengontrol akses kontrol manual
        function toggleManualControls(enable) {
            const servoControl = document.getElementById('servoControl');
            const manualControls = document.getElementById('manualControls');
            const applyManual = document.getElementById('applyManual');
            
            if (enable) {
                // Mode manual - enable kontrol
                servoControl.classList.remove('control-disabled');
                manualControls.classList.remove('control-disabled');
                applyManual.classList.remove('disabled');
                applyManual.disabled = false;
            } else {
                // Mode otomatis - disable kontrol
                servoControl.classList.add('control-disabled');
                manualControls.classList.add('control-disabled');
                applyManual.classList.add('disabled');
                applyManual.disabled = true;
            }
        }

        // Fungsi untuk mengambil data real-time dari API
        function fetchRealtimeData() {
            fetch('http://localhost:8000/latest')
                .then(response => response.json())
                .then(apiData => {
                    if (apiData.success) {
                        updateDisplayWithRealtimeData(apiData);
                    } else {
                        console.error('Gagal mengambil data real-time');
                        useFallbackData();
                    }
                })
                .catch(error => {
                    console.error('Error fetching real-time data:', error);
                    useFallbackData();
                });
        }

        // Fungsi untuk update tampilan dengan data real-time
        function updateDisplayWithRealtimeData(apiData) {
            const sensorData = apiData.data;
            const device = apiData.device;
            
            // Update data sensor
            if (sensorData && sensorData.ultrasonic_data !== undefined) {
                document.getElementById('rt-distance').textContent = sensorData.ultrasonic_data.toFixed(2) + ' cm';
            }
            if (sensorData && sensorData.raindrops_status) {
                document.getElementById('rt-rain-status').textContent = sensorData.raindrops_status;
                document.getElementById('rt-rain-status').className = 'badge ' + 
                    (sensorData.raindrops_status === 'Hujan' ? 'badge-hujan' : 'badge-tidak-hujan');
            }

            // Update warning message
            const warningElement = document.getElementById('distance-warning');
            if (sensorData && sensorData.ultrasonic_data < 10) {
                warningElement.innerHTML = '⚠️ Tinggi air kritis! Sistem aktif.';
                warningElement.className = 'text-danger';
            } else {
                warningElement.innerHTML = '✓ Tinggi air normal';
                warningElement.className = 'text-success';
            }

            // Update status perangkat
            if (device && device.mode !== undefined) {
                const isAutoMode = device.mode === 'otomatis';
                document.getElementById('modeAuto').checked = isAutoMode;
                document.getElementById('modeText').textContent = isAutoMode ? 'Otomatis' : 'Manual';
                toggleManualControls(!isAutoMode);
                
                // Update status aktuator
                if (isAutoMode) {
                    // Mode otomatis - update berdasarkan data sensor
                    const isActivated = sensorData && sensorData.ultrasonic_data < 10;
                    document.getElementById('servoToggle').checked = isActivated;
                    document.getElementById('ledToggle').checked = isActivated;
                    document.getElementById('buzzerToggle').checked = isActivated;
                    document.getElementById('servoStatusText').textContent = isActivated ? 'ON' : 'OFF';
                } else {
                    // Mode manual - update berdasarkan status device
                    if (device.servo !== undefined) {
                        document.getElementById('servoToggle').checked = device.servo;
                        document.getElementById('servoStatusText').textContent = device.servo ? 'ON' : 'OFF';
                    }
                    if (device.led !== undefined) {
                        document.getElementById('ledToggle').checked = device.led;
                    }
                    if (device.buzzer !== undefined) {
                        document.getElementById('buzzerToggle').checked = device.buzzer;
                    }
                }
            }

            updateActuatorStatus();
            
            // Update timestamp
            document.getElementById('lastUpdated').textContent = 'Last updated: ' + new Date().toLocaleString();
            
            // Update badge status API
            const apiBadge = document.getElementById('apiStatus');
            if (apiBadge) {
                apiBadge.innerHTML = '<span class="badge bg-info">API Connected</span>';
            }
        }

        // Fallback ke data PHP jika API tidak tersedia
        function useFallbackData() {
            console.log('Menggunakan data fallback dari PHP');
            
            // Gunakan data dari array PHP dengan increment index
            currentIndex = (currentIndex + 1) % data.length;
            const currentData = data[currentIndex];
            
            document.getElementById('rt-distance').textContent = currentData.distance.toFixed(2) + ' cm';
            document.getElementById('rt-rain-status').textContent = currentData.rain;
            document.getElementById('rt-rain-status').className = 'badge ' + 
                (currentData.rain === 'Hujan' ? 'badge-hujan' : 'badge-tidak-hujan');

            // Update warning message
            const warningElement = document.getElementById('distance-warning');
            if (currentData.distance < 10) {
                warningElement.innerHTML = '⚠️ Tinggi air kritis! Sistem aktif.';
                warningElement.className = 'text-danger';
            } else {
                warningElement.innerHTML = '✓ Tinggi air normal';
                warningElement.className = 'text-success';
            }

            // Auto control untuk tampilan
            if (document.getElementById('modeAuto').checked) {
                autoControl(currentData);
            }

            document.getElementById('lastUpdated').textContent = 'Last updated: ' + new Date().toLocaleString();
            
            // Update badge status API
            const apiBadge = document.getElementById('apiStatus');
            if (apiBadge) {
                apiBadge.innerHTML = '<span class="badge bg-warning">API Disconnected</span>';
            }
        }

        // Update real-time data
        function updateRealtime() {
            fetchRealtimeData();
        }

        // Auto control logic sesuai ESP32 (jarak < 10cm) - untuk tampilan saja
        function autoControl(current) {
            const ultr = current.distance;
            const isActivated = ultr < 10;

            // Hanya update tampilan, tidak kirim perintah ke ESP32
            document.getElementById('servoToggle').checked = isActivated;
            document.getElementById('ledToggle').checked = isActivated;
            document.getElementById('buzzerToggle').checked = isActivated;
            document.getElementById('servoStatusText').textContent = isActivated ? 'ON' : 'OFF';
            
            updateActuatorStatus();
        }

        // Update status aktuator
        function updateActuatorStatus() {
            const servoToggle = document.getElementById('servoToggle');
            const ledToggle = document.getElementById('ledToggle');
            const buzzerToggle = document.getElementById('buzzerToggle');

            // Update servo status
            document.getElementById('servo-status').textContent = servoToggle.checked ? 'ON' : 'OFF';
            document.getElementById('servo-status').className = 'badge ' + (servoToggle.checked ? 'bg-success' : 'bg-secondary') + ' fs-6';
            document.getElementById('servoPosition').textContent = 'Posisi: ' + (servoToggle.checked ? '180° (Tertutup)' : '0° (Terbuka)');
            
            // Update LED status
            document.getElementById('led-status').textContent = ledToggle.checked ? 'ON' : 'OFF';
            document.getElementById('led-status').className = 'badge ' + (ledToggle.checked ? 'bg-warning' : 'bg-secondary') + ' fs-6';
            
            // Update buzzer status
            document.getElementById('buzzer-status').textContent = buzzerToggle.checked ? 'ON' : 'OFF';
            document.getElementById('buzzer-status').className = 'badge ' + (buzzerToggle.checked ? 'bg-danger' : 'bg-secondary') + ' fs-6';
        }

        // Fungsi untuk mengirim perintah kontrol ke server
        function sendControlCommand(controlData) {
            const applyButton = document.getElementById('applyManual');
            const applyText = document.getElementById('applyText');
            const applySpinner = document.getElementById('applySpinner');
            
            // Show loading state
            applyText.textContent = 'Mengirim...';
            applySpinner.classList.remove('d-none');
            applyButton.disabled = true;

            let {otomatis, led, buzzer, servo} = controlData;

            if(otomatis === true){
                led = false;
                buzzer = false;
                servo = false;            
            }

            fetch('http://localhost:8000/command', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    otomatis: otomatis,
                    led: led,
                    buzzer: buzzer,
                    servo: servo
                }),
            })
            .then(response => response.json())
            .then(data => {
                console.log('Success:', data);
                showAlert('Perintah berhasil dikirim', 'success');
                
                // Refresh data real-time setelah mengirim perintah
                setTimeout(fetchRealtimeData, 1000);
            })
            .catch((error) => {
                console.error('Error:', error);
                showAlert('Gagal mengirim perintah', 'danger');
            })
            .finally(() => {
                // Reset button state
                applyText.textContent = 'Terapkan';
                applySpinner.classList.add('d-none');
                applyButton.disabled = false;
            });
        }

        // Event listeners
        document.getElementById('modeAuto').addEventListener('change', function() {
            const isAuto = this.checked;
            const controlData = {
                otomatis: isAuto,
                led: false,    // Selalu false ketika mode otomatis
                buzzer: false, // Selalu false ketika mode otomatis  
                servo: false   // Selalu false ketika mode otomatis
            };
            
            sendControlCommand(controlData);
        });

        // Servo toggle event
        document.getElementById('servoToggle').addEventListener('change', function() {
            document.getElementById('servoStatusText').textContent = this.checked ? 'ON' : 'OFF';
        });

        document.getElementById('applyManual').addEventListener('click', function() {
            const controlData = {
                otomatis: false, // Karena mode manual
                led: document.getElementById('ledToggle').checked,
                buzzer: document.getElementById('buzzerToggle').checked,
                servo: document.getElementById('servoToggle').checked
            };
            sendControlCommand(controlData);
        });

        // Alert function
        function showAlert(message, type) {
            // Hapus alert sebelumnya jika ada
            const existingAlert = document.querySelector('.alert.position-fixed');
            if (existingAlert) {
                existingAlert.remove();
            }

            const alertDiv = document.createElement('div');
            alertDiv.className = `alert alert-${type} alert-dismissible fade show position-fixed`;
            alertDiv.style.cssText = 'top: 20px; right: 20px; z-index: 1050; min-width: 300px;';
            alertDiv.innerHTML = `
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;
            document.body.appendChild(alertDiv);
            
            setTimeout(() => {
                if (alertDiv.parentNode) {
                    alertDiv.remove();
                }
            }, 3000);
        }

        // Initialize
        updateActuatorStatus();
        
        // Set initial state berdasarkan mode
        const initialMode = <?= $current_data['mode'] == 'manual' ? 'true' : 'false' ?>;
        toggleManualControls(initialMode);

        // Load data real-time pertama kali
        fetchRealtimeData();

        // Auto update setiap 3 detik dari API
        setInterval(updateRealtime, 3000);
    </script>
</body>
</html>