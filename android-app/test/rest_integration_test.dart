import 'dart:io';

import 'package:flutter_test/flutter_test.dart';
import 'package:rebung_pintar/api_client.dart';

void main() {
  test(
    'klien Flutter berbicara ke REST Laravel nyata terisolasi',
    () async {
      final url = Platform.environment['REBUNG_TEST_API_URL'];
      final password = Platform.environment['REBUNG_TEST_PASSWORD'];
      if (url == null || password == null) {
        return;
      }
      final api = ApiClient(baseUrl: url, allowInsecureLocal: true);
      try {
        final user = await api.login(
          email: 'operator@integration.test',
          password: password,
          deviceName: 'isolated-flutter-e2e',
        );
        expect(user['role'], 'operator');
        expect((await api.me())['id'], user['id']);
        final snapshot = await api.monitoring();
        expect(snapshot['nodes'], hasLength(2));
        expect(snapshot['nodes'][0]['sensors'][0]['value'], isNull);
        final history = await api.history();
        expect(history['meta']['total'], 0);
        expect(
          (await api.espSettings())['integration']['hardware_connected'],
          false,
        );
        await api.logout();
        expect(api.isAuthenticated, isFalse);
        await expectLater(api.me(), throwsA(isA<ApiException>()));
      } finally {
        api.dispose();
      }
    },
    skip: Platform.environment['REBUNG_TEST_API_URL'] == null
        ? 'Gunakan fixture REST terisolasi untuk uji lintas runtime.'
        : false,
  );
}
