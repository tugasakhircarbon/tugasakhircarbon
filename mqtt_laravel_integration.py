#!/usr/bin/env python3
"""
MQTT to Laravel API Integration
Subscribes to MQTT topics and sends emission sensor data to Laravel API
"""

import paho.mqtt.client as mqtt
import json
import requests
import time
import logging
import sys
from datetime import datetime
from typing import Dict, Any, Optional

# Configuration
MQTT_CONFIG = {
    'broker': 'test.mosquitto.org',  # Ganti sesuai broker Anda
    'port': 1883,
    'username': '',  # Kosong jika tidak ada auth
    'password': '',  # Kosong jika tidak ada auth
    'client_id': 'mqtt_laravel_integration'
}

# MQTT Topics to subscribe
TOPICS = [
    'sensors/emission/data',
    'sensors/emission/co2e',
    'sensors/gps/location',
    'sensors/emission/status'
]

# Laravel API Configuration
LARAVEL_API_CONFIG = {
    'base_url': 'http://localhost:8000/api/mqtt',  # Sesuaikan dengan URL Laravel Anda
    'timeout': 30,
    'retry_attempts': 3,
    'retry_delay': 5,
}

# API Endpoints
API_ENDPOINTS = {
    'health': '/health',
    'sensor_data': '/sensor-data',
    'co2e_data': '/co2e-data',
    'gps_data': '/gps-data',
    'status_log': '/status-log',
    'batch_data': '/batch-data',
}

# Logging configuration
logging.basicConfig(
    level=logging.INFO,
    format='%(asctime)s - %(levelname)s - %(message)s',
    handlers=[
        logging.FileHandler('mqtt_laravel_integration.log', encoding='utf-8'),
        logging.StreamHandler(sys.stdout)
    ]
)
logger = logging.getLogger(__name__)

# Set console encoding for Windows - Fixed version
if sys.platform.startswith('win'):
    import codecs
    import io
    # Use a safer approach for Windows console encoding
    try:
        sys.stdout = codecs.getwriter('utf-8')(sys.stdout.buffer)
    except (AttributeError, io.UnsupportedOperation):
        # Fallback if buffer is not available
        pass

class LaravelApiClient:
    def __init__(self, config: Dict[str, Any]):
        self.base_url = config['base_url']
        self.timeout = config['timeout']
        self.retry_attempts = config['retry_attempts']
        self.retry_delay = config['retry_delay']
        self.session = requests.Session()
        
        # Set default headers
        self.session.headers.update({
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'User-Agent': 'MQTT-Laravel-Integration/1.0'
        })
    
    def health_check(self) -> bool:
        """Check if Laravel API is accessible"""
        try:
            url = f"{self.base_url}{API_ENDPOINTS['health']}"
            response = self.session.get(url, timeout=self.timeout)
            
            if response.status_code == 200:
                data = response.json()
                logger.info(f"✅ Laravel API health check passed: {data.get('message', 'OK')}")
                return True
            else:
                logger.error(f"❌ Laravel API health check failed: HTTP {response.status_code}")
                return False
                
        except Exception as e:
            logger.error(f"❌ Laravel API health check error: {e}")
            return False
    
    def send_data(self, endpoint: str, data: Dict[str, Any]) -> bool:
        """Send data to Laravel API with retry mechanism"""
        url = f"{self.base_url}{endpoint}"
        
        for attempt in range(self.retry_attempts):
            try:
                response = self.session.post(url, json=data, timeout=self.timeout)
                
                if response.status_code in [200, 201]:
                    result = response.json()
                    if result.get('success', False):
                        logger.info(f"✅ Data sent successfully to {endpoint}: {result.get('message', 'OK')}")
                        return True
                    else:
                        logger.error(f"❌ API returned error: {result.get('message', 'Unknown error')}")
                        return False
                else:
                    logger.error(f"❌ HTTP error {response.status_code}: {response.text}")
                    
            except requests.exceptions.Timeout:
                logger.warning(f"⏰ Request timeout (attempt {attempt + 1}/{self.retry_attempts})")
            except requests.exceptions.ConnectionError:
                logger.warning(f"🔌 Connection error (attempt {attempt + 1}/{self.retry_attempts})")
            except Exception as e:
                logger.error(f"❌ Unexpected error: {e}")
            
            if attempt < self.retry_attempts - 1:
                logger.info(f"🔄 Retrying in {self.retry_delay} seconds...")
                time.sleep(self.retry_delay)
        
        logger.error(f"❌ Failed to send data after {self.retry_attempts} attempts")
        return False
    
    def send_sensor_data(self, data: Dict[str, Any]) -> bool:
        """Send sensor data to Laravel"""
        return self.send_data(API_ENDPOINTS['sensor_data'], data)
    
    def send_co2e_data(self, data: Dict[str, Any]) -> bool:
        """Send CO2e data to Laravel"""
        return self.send_data(API_ENDPOINTS['co2e_data'], data)
    
    def send_gps_data(self, data: Dict[str, Any]) -> bool:
        """Send GPS data to Laravel"""
        return self.send_data(API_ENDPOINTS['gps_data'], data)
    
    def send_status_log(self, data: Dict[str, Any]) -> bool:
        """Send status log to Laravel"""
        return self.send_data(API_ENDPOINTS['status_log'], data)
    
    def send_batch_data(self, device_id: str, batch_data: list) -> bool:
        """Send multiple data types in one request"""
        data = {
            'device_id': device_id,
            'batch_data': batch_data
        }
        return self.send_data(API_ENDPOINTS['batch_data'], data)

class MQTTLaravelHandler:
    def __init__(self):
        self.api_client = LaravelApiClient(LARAVEL_API_CONFIG)
        self.client = mqtt.Client(callback_api_version=mqtt.CallbackAPIVersion.VERSION1, 
                                 client_id=MQTT_CONFIG['client_id'])
        self.setup_mqtt()
        self.connected = False
        self.message_count = 0
        self.batch_buffer = {}  # Buffer untuk batch processing
        self.last_batch_send = time.time()
        self.batch_interval = 30  # Send batch every 30 seconds
    
    def setup_mqtt(self):
        """Setup MQTT client"""
        if MQTT_CONFIG['username']:
            self.client.username_pw_set(MQTT_CONFIG['username'], MQTT_CONFIG['password'])
        
        self.client.on_connect = self.on_connect
        self.client.on_message = self.on_message
        self.client.on_disconnect = self.on_disconnect
        self.client.on_subscribe = self.on_subscribe
    
    def on_connect(self, client, userdata, flags, rc):
        """Callback when MQTT client connects"""
        if rc == 0:
            self.connected = True
            logger.info("🔗 Connected to MQTT broker successfully!")
            
            # Subscribe to all topics
            for topic in TOPICS:
                client.subscribe(topic)
                logger.info(f"📡 Subscribed to topic: {topic}")
                
            # Check Laravel API health
            if not self.api_client.health_check():
                logger.warning("⚠️ Laravel API health check failed, but continuing...")
        else:
            logger.error(f"❌ MQTT connection failed with code {rc}")
    
    def on_subscribe(self, client, userdata, mid, granted_qos):
        """Callback when subscription is confirmed"""
        logger.info(f"✅ Subscription confirmed with QoS: {granted_qos}")
    
    def on_disconnect(self, client, userdata, rc):
        """Callback when MQTT client disconnects"""
        self.connected = False
        if rc != 0:
            logger.warning(f"⚠️ Unexpected MQTT disconnection. RC: {rc}")
        else:
            logger.info("👋 MQTT disconnected gracefully")
    
    def on_message(self, client, userdata, msg):
        """Callback when message is received"""
        try:
            self.message_count += 1
            topic = msg.topic
            payload = msg.payload.decode('utf-8')
            
            logger.info(f"📨 Message #{self.message_count} received on topic: {topic}")
            
            # Handle truncated JSON - common issue with Arduino
            if not payload.endswith('}'):
                logger.warning(f"⚠️ Truncated JSON detected, attempting to fix...")
                open_braces = payload.count('{') - payload.count('}')
                if open_braces > 0:
                    payload += '}' * open_braces
                    logger.info("🔧 JSON repair attempted")
            
            # Parse JSON data
            try:
                data = json.loads(payload)
            except json.JSONDecodeError as json_error:
                logger.error(f"❌ JSON decode error: {json_error}")
                logger.error(f"📄 Raw payload length: {len(payload)} chars")
                logger.error(f"📄 Raw payload preview: {payload[:200]}...")
                if len(payload) > 200:
                    logger.error(f"📄 Raw payload end: ...{payload[-50:]}")
                return
            
            # Add timestamp if not present
            if 'timestamp' not in data:
                data['timestamp'] = int(time.time() * 1000)  # milliseconds
            
            # Route to appropriate handler based on topic
            success = False
            if topic == 'sensors/emission/data':
                success = self.handle_sensor_data(data)
            elif topic == 'sensors/emission/co2e':
                success = self.handle_co2e_data(data)
            elif topic == 'sensors/gps/location':
                success = self.handle_gps_data(data)
            elif topic == 'sensors/emission/status':
                success = self.handle_status_log(data)
            else:
                logger.warning(f"⚠️ Unknown topic: {topic}")
            
            if success:
                logger.info(f"✅ Successfully processed message from {topic}")
            else:
                logger.error(f"❌ Failed to process message from {topic}")
                
        except Exception as e:
            logger.error(f"❌ Error processing message: {e}")
            logger.error(f"📍 Topic: {topic}")
            logger.error(f"📄 Payload preview: {str(msg.payload)[:100]}...")
    
    def handle_sensor_data(self, data: Dict[str, Any]) -> bool:
        """Handle sensor data"""
        try:
            return self.api_client.send_sensor_data(data)
        except Exception as e:
            logger.error(f"❌ Error handling sensor data: {e}")
            return False
    
    def handle_co2e_data(self, data: Dict[str, Any]) -> bool:
        """Handle CO2e data"""
        try:
            return self.api_client.send_co2e_data(data)
        except Exception as e:
            logger.error(f"❌ Error handling CO2e data: {e}")
            return False
    
    def handle_gps_data(self, data: Dict[str, Any]) -> bool:
        """Handle GPS data"""
        try:
            return self.api_client.send_gps_data(data)
        except Exception as e:
            logger.error(f"❌ Error handling GPS data: {e}")
            return False
    
    def handle_status_log(self, data: Dict[str, Any]) -> bool:
        """Handle status log"""
        try:
            return self.api_client.send_status_log(data)
        except Exception as e:
            logger.error(f"❌ Error handling status log: {e}")
            return False
    
    def start(self):
        """Start MQTT client"""
        try:
            logger.info("🚀 Starting MQTT Laravel Integration...")
            logger.info(f"📡 MQTT Broker: {MQTT_CONFIG['broker']}:{MQTT_CONFIG['port']}")
            logger.info(f"🌐 Laravel API: {LARAVEL_API_CONFIG['base_url']}")
            logger.info(f"📋 Topics: {', '.join(TOPICS)}")
            
            # Initial health check
            if self.api_client.health_check():
                logger.info("✅ Laravel API is ready")
            else:
                logger.warning("⚠️ Laravel API health check failed, but continuing...")
            
            self.client.connect(MQTT_CONFIG['broker'], MQTT_CONFIG['port'], 60)
            self.client.loop_forever()
            
        except KeyboardInterrupt:
            logger.info("👋 Shutting down gracefully...")
            self.client.disconnect()
        except Exception as e:
            logger.error(f"❌ Error starting MQTT handler: {e}")

def main():
    """Main function"""
    print("=" * 80)
    print("🌱 MQTT Laravel Integration - Carbon Emission Monitoring")
    print("=" * 80)
    print(f"📡 MQTT Broker: {MQTT_CONFIG['broker']}:{MQTT_CONFIG['port']}")
    print(f"🌐 Laravel API: {LARAVEL_API_CONFIG['base_url']}")
    print(f"📋 Topics: {', '.join(TOPICS)}")
    print("=" * 80)
    
    # Create and start MQTT handler
    handler = MQTTLaravelHandler()
    handler.start()

if __name__ == "__main__":
    main()
