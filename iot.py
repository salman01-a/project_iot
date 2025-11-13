#main.py untuk iot  
from machine import Pin, ADC, PWM, time_pulse_us
import time
import network
import ujson
import urequests
import gc

# --- CONFIG WiFi ---
ssid = 'POCOPHONE F1'
password = 'halohalo'

API_URL = "http://10.210.2.12:8000/data"
SEND_INTERVAL_S = 10

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

def set_servo_angle(angle):
    angle = max(0, min(180, angle))
    min_us = 500
    max_us = 2500
    us = min_us + (max_us - min_us) * (angle / 180)
    duty = int(us / 20000 * 65535)
    try:
        servo.duty_u16(duty)
    except AttributeError:
        try:
            servo.duty(duty // 256)
        except:
            pass

def cek_hujan():
    try:
        value = rain_sensor.read()
    except Exception:
        value = None
    status = "Hujan" if value and value < 2000 else "Tidak Hujan"
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

# --- Main Program ---
print("Starting sensor monitoring...")
last_sent = time.ticks_ms()

while True:
    jarak = get_distance()
    rain_value, status_hujan = cek_hujan()

    if jarak is not None:
        print("Jarak: {:.2f} cm | Status: {}".format(jarak, status_hujan))
    else:
        print("Jarak: Tidak terdeteksi | Status: {}".format(status_hujan))

    # Control logic
    if jarak is not None and jarak < 10:
        led.on()
        buzzer.on()
        set_servo_angle(180)
    else:
        led.off()
        buzzer.off()
        set_servo_angle(0)

    # Kirim data setiap interval
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