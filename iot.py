from machine import Pin, ADC, PWM, time_pulse_us
import time
import network
import ujson
import urequests
import gc
from umqtt.simple import MQTTClient

# --- Variabel Global untuk Command ---
otomatis = True  # Default mode otomatis aktif
led_status = False
buzzer_status = False
servo_status = False  # False = 0°, True = 180°

# --- CONFIG WiFi ---
ssid = 'POCOPHONE F1'
password = 'halohalo'

API_URL = "http://10.210.2.12:8000/data"
SEND_INTERVAL_S = 10

# MQTT Configuration
MQTT_BROKER = "broker.emqx.io"
MQTT_TOPIC = "/iot/upn/if23"
MQTT_CLIENT_ID = "esp32_sensor_01"

# --- Pin Configuration ---
TRIG_PIN = 18
ECHO_PIN = 5
BUZZER_PIN = 14
SERVO_PIN = 2
LED_PIN = 19
RAIN_ADC_PIN = 34

# --- Inisialisasi hardware ---
trig = Pin(TRIG_PIN, Pin.OUT)
echo = Pin(ECHO_PIN, Pin.IN)
buzzer = Pin(BUZZER_PIN, Pin.OUT)
led = Pin(LED_PIN, Pin.OUT)
servo = PWM(Pin(SERVO_PIN), freq=50)
rain_sensor = ADC(Pin(RAIN_ADC_PIN))

try:
    rain_sensor.atten(ADC.ATTN_11DB)
except Exception:
    pass

# --- WiFi Connect ---
wlan = network.WLAN(network.STA_IF)
wlan.active(True)

def wifi_connect(ssid_local, pass_local, timeout=15000):
    if wlan.isconnected():
        return True
    print("connecting to WiFi:", ssid_local)
    wlan.connect(ssid_local, pass_local)
    start = time.ticks_ms()
    while not wlan.isconnected():
        if time.ticks_diff(time.ticks_ms(), start) > timeout:
            print("WiFi connect timeout")
            return False
        time.sleep_ms(200)
    print("connected, ifconfig:", wlan.ifconfig())
    return True

wifi_connect(ssid, password)

# --- Fungsi Servo Sederhana (0° atau 180° saja) ---
def set_servo_simple(status):
    # status: False = 0°, True = 180°
    if status:
        # 180 derajat
        duty = int(1500 / 20000 * 65535)  # 2500us untuk 180°
    else:
        # 0 derajat  
        duty = int(500 / 20000 * 65535)   # 500us untuk 0°
    
    try:
        servo.duty_u16(duty)
    except AttributeError:
        try:
            servo.duty(duty // 256)
        except:
            pass

# --- MQTT Callback Function ---
def sub_cb(topic, msg):
    global otomatis, led_status, buzzer_status, servo_status
    
    message = msg.decode().lower()
    print(f"Received message on topic '{topic.decode()}' : {message}")
    
    try:
        # Parsing pesan JSON
        data = ujson.loads(message)
        
        if "otomatis" in data:
            otomatis = bool(data["otomatis"])
            print(f"Mode otomatis: {'AKTIF' if otomatis else 'MANUAL'}")
        
        if not otomatis:  # Hanya proses perintah manual jika mode manual
            if "led" in data:
                led_status = bool(data["led"])
                led.value(led_status)
                #led.on() if led_status else led.off()
                print(f"LED: {'ON' if led_status else 'OFF'}")
            
            if "buzzer" in data:
                buzzer_status = bool(data["buzzer"])
                #buzzer.value(buzzer_status)
                print(f"Buzzer: {'ON' if buzzer_status else 'OFF'}")
                #buzzer.on() if buzzer_status else led.off()
                if buzzer_status :
                    buzzer.on()
                else :
                    buzzer.off()
            
            if "servo" in data:
                servo_status = bool(data["servo"])
                set_servo_simple(servo_status)
                print(f"Servo: {'ON (180°)' if servo_status else 'OFF (0°)'}")
                
    except Exception as e:
        print("Error parsing MQTT message:", e)

def setup_mqtt():
    try:
        client = MQTTClient(MQTT_CLIENT_ID, MQTT_BROKER)
        client.connect()
        client.set_callback(sub_cb)
        print("Connected to MQTT broker!")
        client.subscribe(MQTT_TOPIC.encode())
        print(f"Subscribed to '{MQTT_TOPIC}'")
        return client
    except Exception as e:
        print("MQTT connection failed:", e)
        return None

# Initialize MQTT client
mqtt_client = setup_mqtt()

# --- Fungsi Sensor ---
def get_distance():
    trig.value(0)
    time.sleep_us(2)
    trig.value(1)
    time.sleep_us(10)
    trig.value(0)
    try:
        duration = time_pulse_us(echo, 1, 30000)
    except Exception:
        return None
    if duration <= 0:
        return None
    distance_cm = (duration / 2) * 0.0343
    return distance_cm

def cek_hujan():
    try:
        value = rain_sensor.read()
    except Exception:
        value = None
    status = "Hujan" if value and value < 3500 else "Tidak Hujan"
    return value, status

def send_data(url, ultrasonic_data, raindrops_status):
    try:
        gc.collect()
        headers = {"Content-Type": "application/json"}
        payload = {
            "ultrasonic_data": round(ultrasonic_data, 2) if ultrasonic_data else 0,
            "raindrops_status": raindrops_status
        }

        print("Payload JSON yang dikirim:")
        print(ujson.dumps(payload))

        res = urequests.post(url, data=ujson.dumps(payload), headers=headers)
        print("API Response:", res.status_code)
        print("Response body:", res.text)
        res.close()
        return True
    except Exception as e:
        print("Gagal kirim data:", e)
        return False

# --- Fungsi Kontrol Otomatis ---
def kontrol_otomatis(jarak):
    if jarak is not None and jarak < 3	:
        led.on()
        buzzer.on()
        set_servo_simple(True)  # Servo ON (180°)
    else:
        led.off()
        buzzer.off()
        set_servo_simple(False)  # Servo OFF (0°)

# --- Main Program ---
print("Starting sensor monitoring...")
print("Default mode: OTOMATIS")
last_sent = time.ticks_ms()

while True:
    # Check for MQTT messages if client is connected
    if mqtt_client:
        try:
            mqtt_client.check_msg()
        except Exception as e:
            print("MQTT error:", e)
            # Try to reconnect
            mqtt_client = setup_mqtt()
    
    # Sensor reading
    jarak = get_distance()
    rain_value, status_hujan = cek_hujan()

    if jarak is not None:
        print("Jarak: {:.2f} cm | Status: {} | Mode: {}".format(
            jarak, status_hujan, "OTOMATIS" if otomatis else "MANUAL"))
    else:
        print("Jarak: Tidak terdeteksi | Status: {} | Mode: {}".format(
            status_hujan, "OTOMATIS" if otomatis else "MANUAL"))

    # Control logic berdasarkan mode
    if otomatis:
        # Mode otomatis - kontrol berdasarkan sensor
        kontrol_otomatis(jarak)
    else:
        # Mode manual - kontrol sudah dilakukan di callback MQTT
        # Tetap update display status
        pass

    # Kirim data sensor setiap interval (terlepas dari mode)
    if time.ticks_diff(time.ticks_ms(), last_sent) >= SEND_INTERVAL_S * 1000:
        last_sent = time.ticks_ms()

        if not wlan.isconnected():
            print("WiFi disconnected, reconnecting...")
            wifi_connect(ssid, password)

        if wlan.isconnected():
            send_data(API_URL, jarak if jarak else 0, status_hujan)
        else:
            print("No WiFi - Data not sent")

    time.sleep(1)

