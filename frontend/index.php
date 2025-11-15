<?php
// Konfigurasi API
$api_url = 'http://localhost:8000/data'; // Ganti dengan URL API Anda

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

// Data current dari data terakhir
$current_data = [
    'distance' => $history_data[count($history_data)-1]['distance'],
    'rain_status' => $history_data[count($history_data)-1]['rain'],
    'mode' => 'auto',
    'servo_status' => 'ON',
    'led_status' => 'ON', 
    'buzzer_status' => 'OFF'
];

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

<!-- HTML dan JavaScript tetap sama seperti sebelumnya -->
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
        /* CSS styles tetap sama */
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
                        <?php if ($api_data): ?>
                            <span class="badge bg-info ms-2">API Connected</span>
                        <?php else: ?>
                            <span class="badge bg-warning ms-2">Using Fallback Data</span>
                        <?php endif; ?>
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
                                    <h3 id="rt-distance"><?= $current_data['distance'] ?> cm</h3>
                                    <p class="mb-0">Ketinggian Air</p>
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
                                            <?= $current_data['mode'] == 'manual' ? 'checked' : '' ?> style="width: 60px; height: 30px;">
                                        <label class="form-check-label h5" for="modeAuto">
                                            <?= $current_data['mode'] == 'manual' ? 'Manual' : 'Otomatis' ?>
                                        </label>
                                    </div>
                                </div>
                                
                                <div class="mb-3" id="servoControl">
                                    <label class="form-label">Servo Angle: <span id="servoValue">90</span>°</label>
                                    <input type="range" class="form-range" min="0" max="180" id="servoRange">
                                </div>

                                <div class="row" id="manualControls">
                                    <div class="col-4">
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" id="ledToggle">
                                            <label class="form-check-label" for="ledToggle">LED</label>
                                        </div>
                                    </div>
                                    <div class="col-4">
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" id="buzzerToggle">
                                            <label class="form-check-label" for="buzzerToggle">Buzzer</label>
                                        </div>
                                    </div>
                                    <div class="col-4">
                                        <button id="applyManual" class="btn btn-outline-light w-100">Terapkan</button>
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
                                    <span class="badge bg-success fs-6" id="servo-status">
                                        ON
                                    </span>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="border rounded p-3 bg-white bg-opacity-10">
                                    <i class="fas fa-lightbulb fa-2x mb-2"></i>
                                    <h5>LED Indicator</h5>
                                    <span class="badge bg-warning fs-6" id="led-status">
                                        ON
                                    </span>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="border rounded p-3 bg-white bg-opacity-10">
                                    <i class="fas fa-bell fa-2x mb-2"></i>
                                    <h5>Buzzer Alarm</h5>
                                    <span class="badge bg-secondary fs-6" id="buzzer-status">
                                        OFF
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
                                        <th>Kategori</th>
                                    </tr>
                                </thead>
                                <tbody id="historyTable">
                                    <?php 
                                    $display_data = array_slice($history_data, -10);
                                    foreach($display_data as $data): 
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
                                        <td><?= date('H:i', strtotime($data['time'])) ?></td>
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
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Data dari PHP
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
                            amanLine: {
                                type: 'line',
                                yMin: 40,
                                yMax: 40,
                                borderColor: 'rgb(40, 167, 69)',
                                borderWidth: 2,
                                borderDash: [5, 5],
                                label: {
                                    display: true,
                                    content: 'Batas Aman',
                                    position: 'start',
                                    backgroundColor: 'rgb(40, 167, 69)',
                                    color: 'white',
                                    font: {
                                        size: 10,
                                        weight: 'bold'
                                    }
                                }
                            },
                            waspadaLine: {
                                type: 'line',
                                yMin: 60,
                                yMax: 60,
                                borderColor: 'rgb(255, 193, 7)',
                                borderWidth: 2,
                                borderDash: [5, 5],
                                label: {
                                    display: true,
                                    content: 'Batas Waspada',
                                    position: 'start',
                                    backgroundColor: 'rgb(255, 193, 7)',
                                    color: 'black',
                                    font: {
                                        size: 10,
                                        weight: 'bold'
                                    }
                                }
                            },
                            bahayaLine: {
                                type: 'line',
                                yMin: 80,
                                yMax: 80,
                                borderColor: 'rgb(220, 53, 69)',
                                borderWidth: 2,
                                borderDash: [5, 5],
                                label: {
                                    display: true,
                                    content: 'Batas Bahaya',
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

        // Update real-time data
        function updateRealtime() {
            currentIndex = (currentIndex + 1) % data.length;
            const currentData = data[currentIndex];
            
            document.getElementById('rt-distance').textContent = currentData.distance + ' cm';
            document.getElementById('rt-rain-status').textContent = currentData.rain;
            document.getElementById('rt-rain-status').className = 'badge ' + (currentData.rain === 'Hujan' ? 'badge-hujan' : 'badge-tidak-hujan');
            
            document.querySelector('small.text-muted').textContent = 'Last updated: ' + new Date().toLocaleString();
            
            // Auto control logic
            if (!document.getElementById('modeAuto').checked) {
                autoControl(currentData);
            }
        }

        // Auto control logic
        function autoControl(current) {
            const ultr = current.distance;
            let servoTarget = 90;
            let ledOn = false;
            let buzzerOn = false;

            if (ultr < 30) {
                servoTarget = 160;
                ledOn = true;
                buzzerOn = true;
            } else if (ultr < 60) {
                servoTarget = 120;
                ledOn = true;
                buzzerOn = false;
            } else {
                servoTarget = 40;
                ledOn = false;
                buzzerOn = false;
            }

            servoRange.value = servoTarget;
            servoValue.textContent = servoTarget;
            ledToggle.checked = ledOn;
            buzzerToggle.checked = buzzerOn;
            
            updateActuatorStatus();
        }

        // Update status aktuator
        function updateActuatorStatus() {
            document.getElementById('servo-status').textContent = 'ON';
            document.getElementById('servo-status').className = 'badge bg-success fs-6';
            
            document.getElementById('led-status').textContent = ledToggle.checked ? 'ON' : 'OFF';
            document.getElementById('led-status').className = 'badge ' + (ledToggle.checked ? 'bg-warning' : 'bg-secondary') + ' fs-6';
            
            document.getElementById('buzzer-status').textContent = buzzerToggle.checked ? 'ON' : 'OFF';
            document.getElementById('buzzer-status').className = 'badge ' + (buzzerToggle.checked ? 'bg-danger' : 'bg-secondary') + ' fs-6';
        }

        // Event listeners
        document.getElementById('modeAuto').addEventListener('change', function() {
            const isManual = this.checked;
            const label = this.nextElementSibling;
            
            if (isManual) {
                label.textContent = 'Manual';
                showAlert('Mode diubah ke: Manual', 'warning');
                toggleManualControls(true); // Enable kontrol manual
            } else {
                label.textContent = 'Otomatis';
                showAlert('Mode diubah ke: Otomatis', 'info');
                toggleManualControls(false); // Disable kontrol manual
            }
        });

        document.getElementById('servoRange').addEventListener('input', function() {
            document.getElementById('servoValue').textContent = this.value;
        });

        document.getElementById('applyManual').addEventListener('click', function() {
            showAlert('Kontrol manual diterapkan', 'success');
            updateActuatorStatus();
        });

        // Alert function
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

        // Initialize
        updateActuatorStatus();
        // Set initial state berdasarkan mode
        toggleManualControls(<?= $current_data['mode'] == 'manual' ? 'true' : 'false' ?>);
        
        // Auto update setiap 6 detik
        setInterval(updateRealtime, 6000);
    </script>
</body>
</html>