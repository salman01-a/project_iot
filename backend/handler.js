import mysql from 'mysql';
import mqtt from 'mqtt';

const mqttClient = mqtt.connect('mqtt://broker.emqx.io');
mqttClient.on('connect', () => {
  console.log('Connected to MQTT broker');
});

mqttClient.on('error', (err) => {
  console.error('MQTT connection error: ', err);
});

// Buat koneksi global (tidak perlu connect/disconnect tiap request)
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
}

const commandTopic = (req, res) => {
  let { otomatis, led, buzzer, servo } = req.body;

  if(otomatis === true){
    led = false;
    buzzer = false;
    servo = false;
  }
  const commandMessage = JSON.stringify({ otomatis, led, buzzer, servo });
  mqttClient.publish('/iot/upn/if23', commandMessage, (err) => {
    if (err) {
      console.error('Error publishing MQTT message:', err);
      return res.status(500).json({ message: 'Error sending command' });
    }
    res.status(200).json({ message: 'Command sent successfully' });
  });
}

export { SaveDataHandler, getDataHandler, commandTopic }; 
