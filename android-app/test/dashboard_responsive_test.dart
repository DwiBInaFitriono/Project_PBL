import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:rebung_pintar/main.dart';

void main() {
  for (final size in [
    const Size(320, 568),
    const Size(390, 844),
    const Size(844, 390),
    const Size(768, 1024),
    const Size(1025, 768),
    const Size(1440, 900),
  ]) {
    for (final dark in [false, true]) {
      testWidgets('semua halaman $size dark=$dark tanpa overflow', (
        tester,
      ) async {
        tester.view.devicePixelRatio = 1;
        tester.view.physicalSize = size;
        addTearDown(tester.view.reset);
        await tester.pumpWidget(const MainApp());
        if (dark) {
          await tester.tap(find.byTooltip('Aktifkan mode gelap'));
          await tester.pumpAndSettle();
        }
        await tester.ensureVisible(find.text('Lihat dashboard (pratinjau)'));
        await tester.tap(find.text('Lihat dashboard (pratinjau)'));
        await tester.pumpAndSettle();
        expect(tester.takeException(), isNull);
        for (final target in ['history', 'esp', 'account', 'dashboard']) {
          if (size.width <= 1024) {
            await tester.tap(find.byTooltip('Buka navigasi'));
            await tester.pumpAndSettle();
          }
          await tester.ensureVisible(find.byKey(Key('nav-$target')));
          await tester.tap(find.byKey(Key('nav-$target')));
          await tester.pumpAndSettle();
          expect(tester.takeException(), isNull, reason: '$target $size');
        }
        if (size.width > 1024) {
          await tester.tap(find.byTooltip('Ringkas sidebar'));
          await tester.pumpAndSettle();
          expect(find.byTooltip('Perluas sidebar'), findsOneWidget);
          expect(tester.takeException(), isNull);
          await tester.tap(find.byKey(const Key('menu-node-1')));
          await tester.pumpAndSettle();
          expect(
            find.byKey(const Key('nav-node-1-temperature')),
            findsOneWidget,
          );
        }
      });
    }
  }

  testWidgets('drawer pendek dan teks 2x menjaga setting akun terjangkau', (
    tester,
  ) async {
    tester.view.devicePixelRatio = 1;
    tester.view.physicalSize = const Size(320, 568);
    tester.platformDispatcher.textScaleFactorTestValue = 2;
    addTearDown(tester.view.reset);
    addTearDown(tester.platformDispatcher.clearTextScaleFactorTestValue);
    await tester.pumpWidget(const MainApp());
    await tester.ensureVisible(find.text('Lihat dashboard (pratinjau)'));
    await tester.tap(find.text('Lihat dashboard (pratinjau)'));
    await tester.pumpAndSettle();
    expect(tester.takeException(), isNull);
    await tester.tap(find.byTooltip('Buka navigasi'));
    await tester.pumpAndSettle();
    expect(
      tester.getRect(find.byKey(const Key('nav-account'))).bottom,
      lessThanOrEqualTo(568),
    );
    await tester.tap(find.byKey(const Key('nav-account')));
    await tester.pumpAndSettle();
    expect(tester.takeException(), isNull);
  });
}
