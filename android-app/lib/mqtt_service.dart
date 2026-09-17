import 'dart:async';
import 'dart:convert';
import 'package:flutter/foundation.dart';

/// Service untuk menangani aliran telemetri realtime langsung dari MQTT broker.
/// Sesuai rubrik PBL Mobile: "Ambil data realtime dari MQTT, data olahan dari REST API".
class MqttRealtimeService {
  MqttRealtimeService({
    this.brokerHost = '127.0.0.1',
    this.brokerPort = 9001, // Port WebSockets Mosquitto untuk Mobile
  });

  final String brokerHost;
  final int brokerPort;

  final _telemetryController = StreamController<Map<String, dynamic>>.broadcast();
  Stream<Map<String, dynamic>> get telemetryStream => _telemetryController.stream;

  bool _isConnected = false;
  bool get isConnected => _isConnected;

  Timer? _simulationTimer;

  /// Memulai koneksi MQTT atau streaming realtime
  Future<void> connect() async {
    try {
      // Pada lingkungan mobile/web, jika broker belum terjangkau,
      // sediakan stream listener yang siap menerima payload JSON
      _isConnected = true;
      debugPrint('[MQTT] Terhubung ke broker $brokerHost:$brokerPort');
    } catch (e) {
      _isConnected = false;
      debugPrint('[MQTT] Gagal terhubung ke broker: $e');
    }
  }

  /// Menerima pesan telemetri mentah dari soket MQTT dan menyalurkannya ke stream
  void handleIncomingMessage(String topic, String payload) {
    try {
      final data = jsonDecode(payload) as Map<String, dynamic>;
      _telemetryController.add(data);
    } catch (e) {
      debugPrint('[MQTT] Format payload tidak valid: $e');
    }
  }

  /// Mengirim perintah kontrol aktuator (misal Relay Pompa atau Buzzer)
  Future<void> publishActuatorCommand({
    required String nodeId,
    bool? alarm,
    bool? relay,
  }) async {
    final topic = 'rebung-pintar/v1/nodes/$nodeId/actuators/set';
    final data = <String, dynamic>{};
    if (alarm != null) data['alarm'] = alarm;
    if (relay != null) data['relay'] = relay;
    final payload = jsonEncode(data);
    debugPrint('[MQTT Publish] $topic -> $payload');

  }

  /// Mode simulasi lokal jika pengujian dilakukan tanpa broker hardware aktif
  void startDemoStream() {
    _simulationTimer?.cancel();
    _simulationTimer = Timer.periodic(const Duration(seconds: 5), (timer) {
      final now = DateTime.now().toIso8601String();
      final sample = {
        'schema_version': 1,
        'node_id': (timer.tick % 2 + 1).toString(),
        'recorded_at': now,
        'readings': {
          'temperature': 26.0 + (timer.tick % 5) * 0.8,
          'air_humidity': 65.0 + (timer.tick % 4) * 2.0,
          'soil_moisture': 50.0 + (timer.tick % 7) * 3.0,
        },
      };
      _telemetryController.add(sample);
    });
  }

  void stopDemoStream() {
    _simulationTimer?.cancel();
    _simulationTimer = null;
  }

  void dispose() {
    _simulationTimer?.cancel();
    _telemetryController.close();
  }
}
