import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:rebung_pintar/main.dart';

Future<void> openApp(WidgetTester tester) async {
  await tester.pumpWidget(const MainApp());
  await tester.ensureVisible(find.text('Lihat dashboard (pratinjau)'));
  await tester.tap(find.text('Lihat dashboard (pratinjau)'));
  await tester.pumpAndSettle();
}

Future<void> navigate(WidgetTester tester, String target) async {
  await tester.tap(find.byTooltip('Buka navigasi'));
  await tester.pumpAndSettle();
  await tester.ensureVisible(find.byKey(Key('nav-$target')));
  await tester.pumpAndSettle();
  await tester.tap(find.byKey(Key('nav-$target')));
  await tester.pumpAndSettle();
}

void main() {
  testWidgets('tanggal terbalik dan rentang terlalu panjang ditolak', (
    tester,
  ) async {
    await openApp(tester);
    await navigate(tester, 'history');
    Future<void> choose(String key, DateTime date) async {
      await tester.ensureVisible(find.byKey(Key(key)));
      await tester.tap(find.byKey(Key(key)));
      await tester.pumpAndSettle();
      tester
          .widget<CalendarDatePicker>(find.byType(CalendarDatePicker))
          .onDateChanged(date);
      await tester.pumpAndSettle();
      await tester.tap(find.text('Pilih'));
      await tester.pumpAndSettle();
    }

    await choose('history-from', DateTime(2026, 8, 2));
    await choose('history-to', DateTime(2026, 8, 1));
    await tester.ensureVisible(find.byKey(const Key('apply-history')));
    await tester.tap(find.byKey(const Key('apply-history')));
    await tester.pumpAndSettle();
    expect(
      find.text('Sampai tanggal tidak boleh sebelum Dari tanggal.'),
      findsOneWidget,
    );
    await choose('history-to', DateTime(2028, 8, 2));
    await tester.ensureVisible(find.byKey(const Key('apply-history')));
    await tester.tap(find.byKey(const Key('apply-history')));
    await tester.pumpAndSettle();
    expect(find.text('Rentang tanggal maksimal 366 hari.'), findsOneWidget);
  });

  testWidgets('riwayat dan kalender tetap muat saat teks 2x', (tester) async {
    tester.view.devicePixelRatio = 1;
    tester.view.physicalSize = const Size(320, 568);
    tester.platformDispatcher.textScaleFactorTestValue = 2;
    addTearDown(tester.view.reset);
    addTearDown(tester.platformDispatcher.clearTextScaleFactorTestValue);
    await openApp(tester);
    await navigate(tester, 'history');
    expect(tester.takeException(), isNull);
    await tester.ensureVisible(find.byKey(const Key('history-sensor')));
    await tester.tap(find.byKey(const Key('history-sensor')));
    await tester.pumpAndSettle();
    expect(tester.takeException(), isNull);
    await tester.binding.handlePopRoute();
    await tester.pumpAndSettle();
    await tester.ensureVisible(find.byKey(const Key('history-from')));
    await tester.tap(find.byKey(const Key('history-from')));
    await tester.pumpAndSettle();
    expect(tester.takeException(), isNull);
    await tester.tap(find.text('Batal'));
    await tester.pumpAndSettle();
  });

  testWidgets(
    'kalender Indonesia dan tombol tanggal mempunyai nama aksesibel',
    (tester) async {
      await openApp(tester);
      await navigate(tester, 'history');
      final semantics = tester.ensureSemantics();
      await tester.ensureVisible(find.byKey(const Key('history-from')));
      expect(
        find.bySemanticsLabel(RegExp('Dari tanggal.*WIB')),
        findsOneWidget,
      );
      await tester.tap(find.byKey(const Key('history-from')));
      await tester.pumpAndSettle();
      final context = tester.element(find.byType(DatePickerDialog));
      expect(Localizations.localeOf(context).languageCode, 'id');
      expect(
        MaterialLocalizations.of(context).formatMonthYear(DateTime(2026, 8)),
        'Agustus 2026',
      );
      await tester.tap(find.text('Batal'));
      await tester.pumpAndSettle();
      semantics.dispose();
    },
  );

  testWidgets('Back menelusuri halaman sebelum keluar pratinjau', (
    tester,
  ) async {
    await openApp(tester);
    await navigate(tester, 'history');
    await navigate(tester, 'account');
    await tester.binding.handlePopRoute();
    await tester.pumpAndSettle();
    expect(find.byKey(const Key('history-node')), findsOneWidget);
    await tester.binding.handlePopRoute();
    await tester.pumpAndSettle();
    expect(find.text('Ringkasan monitoring'), findsOneWidget);
    await tester.binding.handlePopRoute();
    await tester.pumpAndSettle();
    expect(find.text('Selamat datang kembali.'), findsOneWidget);
  });

  testWidgets('ikon node ringkas membuka submenu dan sensor aktif ditandai', (
    tester,
  ) async {
    tester.view.devicePixelRatio = 1;
    tester.view.physicalSize = const Size(1440, 900);
    addTearDown(tester.view.reset);
    await openApp(tester);
    await tester.tap(find.byKey(const Key('menu-node-1')));
    await tester.pumpAndSettle();
    await tester.tap(find.byTooltip('Ringkas sidebar'));
    await tester.pumpAndSettle();
    await tester.tap(find.byKey(const Key('menu-node-1')));
    await tester.pumpAndSettle();
    expect(find.byKey(const Key('nav-node-1-temperature')), findsOneWidget);
    await tester.tap(find.byKey(const Key('nav-node-1-temperature')));
    await tester.pumpAndSettle();
    final button = tester.widget<TextButton>(
      find.byKey(const Key('nav-node-1-temperature')),
    );
    expect(button.style!.backgroundColor!.resolve({}), const Color(0xFFF7B700));
  });

  testWidgets('filter riwayat bertahan saat membuka pengaturan', (
    tester,
  ) async {
    await openApp(tester);
    await navigate(tester, 'history');
    await tester.tap(find.byKey(const Key('history-node')));
    await tester.pumpAndSettle();
    await tester.tap(find.text('Node 2').last);
    await tester.pumpAndSettle();
    await tester.ensureVisible(find.byKey(const Key('apply-history')));
    await tester.tap(find.byKey(const Key('apply-history')));
    await tester.pumpAndSettle();
    await navigate(tester, 'account');
    await navigate(tester, 'history');
    expect(find.textContaining('Node 2 · Semua parameter'), findsOneWidget);
  });
}
