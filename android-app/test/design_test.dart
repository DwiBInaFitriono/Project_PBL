import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:rebung_pintar/main.dart';
import 'package:rebung_pintar/dashboard_widgets.dart';
import 'package:rebung_pintar/app_sidebar.dart';

void main() {
  for (final target in ['history', 'esp', 'account']) {
    testWidgets('teks $target singkat dengan peringatan pratinjau', (
      tester,
    ) async {
      await tester.pumpWidget(const MainApp());
      await tester.ensureVisible(find.text('Lihat dashboard (pratinjau)'));
      await tester.tap(find.text('Lihat dashboard (pratinjau)'));
      await tester.pumpAndSettle();
      await tester.tap(find.byTooltip('Buka navigasi'));
      await tester.pumpAndSettle();
      await tester.ensureVisible(find.byKey(Key('nav-$target')));
      await tester.tap(find.byKey(Key('nav-$target')));
      await tester.pumpAndSettle();
      final words = tester
          .widgetList<Text>(find.byType(Text))
          .map((text) => text.data ?? '')
          .join(' ')
          .split(RegExp(r'\s+'))
          .where((word) => word.isNotEmpty)
          .length;
      expect(words, lessThan(target == 'history' ? 85 : 75));
      expect(find.textContaining('PRATINJAU'), findsWidgets);
    });
  }

  testWidgets('Flutter memakai bidang netral dengan aksen kuning palet', (
    tester,
  ) async {
    await tester.pumpWidget(const MainApp());
    final theme = Theme.of(tester.element(find.byType(Scaffold)));
    expect(theme.scaffoldBackgroundColor, const Color(0xFFF2F1EF));
    expect(theme.colorScheme.onSurface, const Color(0xFF282824));
    expect(
      theme.filledButtonTheme.style!.backgroundColor!.resolve({}),
      const Color(0xFFF7B700),
    );
    await tester.ensureVisible(find.text('Lihat dashboard (pratinjau)'));
    await tester.tap(find.text('Lihat dashboard (pratinjau)'));
    await tester.pumpAndSettle();
    await tester.tap(find.byTooltip('Buka navigasi'));
    await tester.pumpAndSettle();
    final side = find.descendant(
      of: find.byType(AppSidebar),
      matching: find.byType(ColoredBox),
    );
    expect(
      tester.widget<ColoredBox>(side.first).color,
      const Color(0xFFFAF9F6),
    );
  });

  testWidgets('dashboard ringkas mempertahankan label dan status data', (
    tester,
  ) async {
    tester.view.devicePixelRatio = 1;
    tester.view.physicalSize = const Size(390, 844);
    addTearDown(tester.view.reset);
    await tester.pumpWidget(const MainApp());
    await tester.ensureVisible(find.text('Lihat dashboard (pratinjau)'));
    await tester.tap(find.text('Lihat dashboard (pratinjau)'));
    await tester.pumpAndSettle();
    final words = tester
        .widgetList<Text>(find.byType(Text))
        .map((text) => text.data ?? '')
        .join(' ')
        .split(RegExp(r'\s+'))
        .where((word) => word.isNotEmpty)
        .length;
    expect(words, lessThan(160));
    expect(find.text('PRATINJAU · Belum terhubung'), findsOneWidget);
    expect(find.text('Belum ada pembacaan'), findsNothing);
    expect(find.byKey(const Key('sensor-1-temperature')), findsOneWidget);
    expect(tester.getSize(find.byType(NodeCard).first).height, lessThan(300));
    await tester.tap(find.byTooltip('Tentang status data'));
    await tester.pumpAndSettle();
    expect(find.textContaining('Data kosong bukan nol'), findsOneWidget);
  });
}
