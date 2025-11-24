import express from 'express';
import mysql from 'mysql';
import mqtt from 'mqtt';
import cors from 'cors';
import bodyParser from 'body-parser';

const app = express();
const PORT = 8000;

// Middleware
app.use(cors());
app.use(bodyParser.json());

// MQTT Configuration
const MQTT_BROKER = 'mqtt://broker.emqx.io';
const MQTT_TOPIC = '/iot/upn/if23';

// Connect to MQTT broker
const mqttClient = mqtt.connect(MQTT_BROKER);
mqttClient.on('connect', () => {
  console.log('Connected to MQTT broker');
});

mqttClient.on('error', (err) => {
  console.error('MQTT connection error: ', err);
});

// Buat koneksi global
const connection = mysql.createConnection({
  host: 'localhost',
  user: 'root',
  password: '',
  database: 'iot_testing'
});

// Jalankan koneksi saat server mulai
connection.connect((err) => {
  if (err) {
    console.error('❌ Gagal konek ke database:', err);
    return;
  }
  console.log('✅ Terhubung ke database.');

  // Buat tabel jika belum ada
  const createTableQuery = `
    CREATE TABLE IF NOT EXISTS sensor_data (
      id INT AUTO_INCREMENT PRIMARY KEY,
      ultrasonic_data FLOAT,
      raindrops_status VARCHAR(50),
      created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )
  `;
  connection.query(createTableQuery, (err) => {
    if (err) {
      console.error('Gagal membuat tabel:', err);
    } else {
      console.log('Tabel sensor_data siap.');
    }
  });
});

// Store device status in memory
let deviceStatus = {
  mode: 'otomatis', // 'otomatis' or 'manual'
  led: false,
  buzzer: false,
  servo: false
};

// Handler untuk menyimpan data sensor
const SaveDataHandler = (req, res, next) => {
  const { ultrasonic_data, raindrops_status } = req.body;

  if (ultrasonic_data === undefined || raindrops_status === undefined) {
    return res.status(400).json({ message: 'Bad request: data tidak lengkap' });
  }

  const query = 'INSERT INTO sensor_data (ultrasonic_data, raindrops_status) VALUES (?, ?)';
  connection.query(query, [ultrasonic_data, raindrops_status], (error, results) => {
    if (error) {
      console.error('Error inserting data:', error);
      return res.status(500).json({ message: 'Error inserting data' });
    }
    res.status(200).json({
      message: 'Data inserted successfully',
      dataId: results.insertId
    });
  });
};

// Handler untuk mendapatkan semua data
const getDataHandler = (req, res) => {
  const query = 'SELECT * FROM sensor_data ORDER BY created_at DESC';
  connection.query(query, (error, results) => {
    if (error) {
      console.error('Error fetching data:', error);
      return res.status(500).json({ message: 'Error fetching data' });
    }
    res.status(200).json({
      message: 'Data fetched successfully',
      data: results
    });
  });
};

// Handler untuk mendapatkan data terbaru
const getLatestDataHandler = (req, res) => {
  const query = 'SELECT * FROM sensor_data ORDER BY created_at DESC LIMIT 1';
  connection.query(query, (error, results) => {
    if (error) {
      console.error('Error fetching latest data:', error);
      return res.status(500).json({ message: 'Error fetching latest data' });
    }
    
    const latestData = results.length > 0 ? results[0] : null;
    
    res.status(200).json({
      success: true,
      data: latestData ? {
        ultrasonic_data: latestData.ultrasonic_data,
        raindrops_status: latestData.raindrops_status,
        timestamp: latestData.created_at
      } : null,
      device: deviceStatus,
      timestamp: new Date().toISOString()
    });
  });
};

// Handler untuk command MQTT
const commandTopic = (req, res) => {
  let { otomatis, led, buzzer, servo } = req.body;

  if (otomatis === true) {
    led = false;
    buzzer = false;
    servo = false;
  }
  
  // Update device status in memory
  deviceStatus = {
    mode: otomatis ? 'otomatis' : 'manual',
    led: led || false,
    buzzer: buzzer || false,
    servo: servo || false
  };

  const commandMessage = JSON.stringify({ otomatis, led, buzzer, servo });
  mqttClient.publish('/iot/upn/if23', commandMessage, (err) => {
    if (err) {
      console.error('Error publishing MQTT message:', err);
      return res.status(500).json({ message: 'Error sending command' });
    }
    
    console.log(`✅ MQTT command sent: ${commandMessage}`);
    res.status(200).json({ 
      message: 'Command sent successfully',
      command: { otomatis, led, buzzer, servo }
    });
  });
}

// Handler untuk status device
const getStatusHandler = (req, res) => {
  // Get latest sensor data
  const query = 'SELECT * FROM sensor_data ORDER BY created_at DESC LIMIT 1';
  connection.query(query, (error, results) => {
    if (error) {
      console.error('Error fetching latest data:', error);
      return res.status(500).json({ message: 'Error fetching latest data' });
    }
    
    const latestData = results.length > 0 ? results[0] : null;
    
    res.status(200).json({
      success: true,
      sensor: latestData ? {
        ultrasonic_data: latestData.ultrasonic_data,
        raindrops_status: latestData.raindrops_status,
        timestamp: latestData.created_at
      } : null,
      device: deviceStatus
    });
  });
};

// Handler untuk kontrol penuh
const fullControlHandler = (req, res) => {
  try {
    const { otomatis, led, buzzer, servo } = req.body;
    
    // Update device status
    deviceStatus = {
      mode: otomatis ? 'otomatis' : 'manual',
      led: led || false,
      buzzer: buzzer || false,
      servo: servo || false
    };

    const commandMessage = JSON.stringify({ 
      otomatis: otomatis || false,
      led: led || false,
      buzzer: buzzer || false,
      servo: servo || false
    });

    mqttClient.publish('/iot/upn/if23', commandMessage, (err) => {
      if (err) {
        console.error('Error publishing MQTT message:', err);
        return res.status(500).json({ 
          message: 'Error sending command via MQTT',
          error: err.message 
        });
      }
      
      console.log(`✅ Full control MQTT command sent: ${commandMessage}`);
      res.status(200).json({ 
        message: 'Full control command sent successfully',
        command: JSON.parse(commandMessage),
        device: deviceStatus
      });
    });
    
  } catch (error) {
    console.error('Error in full control:', error);
    res.status(500).json({ 
      message: 'Internal server error',
      error: error.message 
    });
  }
};

// Health check endpoint
const healthHandler = (req, res) => {
  res.json({
    success: true,
    message: 'Server is running',
    timestamp: new Date().toISOString(),
    mqtt: mqttClient.connected ? 'connected' : 'disconnected',
    database: connection.state === 'connected' ? 'connected' : 'disconnected'
  });
};

// Define routes
app.post('/data', SaveDataHandler);
app.get('/data', getDataHandler);
app.get('/latest', getLatestDataHandler);
app.post('/command', commandTopic);
app.get('/status', getStatusHandler);
app.post('/control/full', fullControlHandler);
app.get('/health', healthHandler);

// Start server
app.listen(PORT, () => {
  console.log(`🚀 Server running on port ${PORT}`);
  console.log(`📡 MQTT Broker: ${MQTT_BROKER}`);
  console.log(`📝 MQTT Topic: ${MQTT_TOPIC}`);
  console.log(`🗄️  Database: iot_testing`);
});