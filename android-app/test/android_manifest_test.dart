import 'dart:io';

import 'package:flutter_test/flutter_test.dart';

void main() {
  test('HTTP development dibatasi ke loopback/emulator dan build debug', () {
    final debug = File('android/app/src/debug/AndroidManifest.xml')
        .readAsStringSync();
    final main = File('android/app/src/main/AndroidManifest.xml')
        .readAsStringSync();
    expect(
      debug,
      contains(
        'android:networkSecurityConfig="@xml/development_network_security"',
      ),
    );
    expect(main, isNot(contains('development_network_security')));
    final config = File(
      'android/app/src/debug/res/xml/development_network_security.xml',
    ).readAsStringSync();
    expect(config, contains('<base-config cleartextTrafficPermitted="false"'));
    expect(
      config,
      contains('<domain includeSubdomains="false">10.0.2.2</domain>'),
    );
    expect(config, isNot(contains('includeSubdomains="true"')));
  });
  test(
    'manifest utama Android mengizinkan REST tanpa membuka cleartext global',
    () {
      final manifest = File('android/app/src/main/AndroidManifest.xml')
          .readAsStringSync();
      expect(
        manifest,
        contains('<uses-permission android:name="android.permission.INTERNET"'),
      );
      expect(manifest, isNot(contains('android:usesCleartextTraffic="true"')));
    },
  );
}
