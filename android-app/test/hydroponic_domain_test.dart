import 'package:flutter_test/flutter_test.dart';
import 'package:hidroponik_dft/dashboard_widgets.dart';

void main() {
  test('sensor DFT kangkung dan pakcoy mengikuti konfigurasi dua node', () {
    expect(sensors.map((sensor) => sensor.id), [
      'ph',
      'water_temperature',
      'water_level',
      'air_temperature',
      'air_humidity',
      'light_intensity',
      'water_flow',
    ]);
    expect(sensorsForNode(1).map((sensor) => sensor.name), [
      'pH nutrisi',
      'Suhu air',
      'Ketinggian air',
    ]);
    expect(sensorsForNode(2).map((sensor) => sensor.name), [
      'Suhu udara',
      'Kelembapan udara',
      'Intensitas cahaya',
      'Aliran air',
    ]);
  });
}
