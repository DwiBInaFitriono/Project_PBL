import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:rebung_pintar/main.dart';

Future<void> openPreview(WidgetTester tester) async {
  await tester.pumpWidget(const MainApp());
  final link = find.text('Lihat dashboard (pratinjau)');
  await tester.ensureVisible(link);
  await tester.tap(link);
  await tester.pumpAndSettle();
}

void main() {
  testWidgets(
    'tema dashboard bertahan saat pindah halaman dan kembali ke login',
    (tester) async {
      await openPreview(tester);
      await tester.tap(find.byTooltip('Aktifkan mode gelap'));
      await tester.pumpAndSettle();
      expect(find.byTooltip('Aktifkan mode terang'), findsOneWidget);
      await tester.tap(find.byTooltip('Buka navigasi'));
      await tester.pumpAndSettle();
      await tester.tap(find.byKey(const Key('nav-account')));
      await tester.pumpAndSettle();
      expect(find.byTooltip('Aktifkan mode terang'), findsOneWidget);
      await tester.tap(find.byTooltip('Tutup pratinjau'));
      await tester.pumpAndSettle();
      expect(find.byTooltip('Aktifkan mode terang'), findsOneWidget);
    },
  );

  testWidgets('tanggal riwayat membuka kalender dan dapat dibatalkan', (
    tester,
  ) async {
    await openPreview(tester);
    await tester.tap(find.byTooltip('Buka navigasi'));
    await tester.pumpAndSettle();
    await tester.tap(find.byKey(const Key('nav-history')));
    await tester.pumpAndSettle();
    await tester.ensureVisible(find.byKey(const Key('history-from')));
    await tester.tap(find.byKey(const Key('history-from')));
    await tester.pumpAndSettle();
    expect(find.byType(DatePickerDialog), findsOneWidget);
    await tester.tap(find.text('Batal'));
    await tester.pumpAndSettle();
    expect(find.byType(DatePickerDialog), findsNothing);
  });

  testWidgets(
    'riwayat memiliki filter, reset, tanggal, dan ekspor yang jujur',
    (tester) async {
      await openPreview(tester);
      await tester.tap(find.byTooltip('Buka navigasi'));
      await tester.pumpAndSettle();
      await tester.tap(find.byKey(const Key('nav-history')));
      await tester.pumpAndSettle();
      expect(find.text('Riwayat pembacaan'), findsWidgets);
      expect(find.byKey(const Key('history-node')), findsOneWidget);
      expect(find.byKey(const Key('history-sensor')), findsOneWidget);
      await tester.tap(find.byKey(const Key('history-node')));
      await tester.pumpAndSettle();
      await tester.tap(find.text('Node 2').last);
      await tester.pumpAndSettle();
      await tester.ensureVisible(find.byKey(const Key('apply-history')));
      await tester.tap(find.byKey(const Key('apply-history')));
      await tester.pumpAndSettle();
      expect(find.textContaining('Node 2 · Semua parameter'), findsOneWidget);
      await tester.tap(find.byKey(const Key('reset-history')));
      await tester.pumpAndSettle();
      expect(
        find.textContaining('Semua node · Semua parameter'),
        findsOneWidget,
      );
      expect(find.textContaining('PDF belum tersedia.'), findsOneWidget);
      expect(tester.takeException(), isNull);
    },
  );

  testWidgets(
    'pengaturan lengkap tidak berpura-pura mengubah akun atau perangkat',
    (tester) async {
      await openPreview(tester);
      await tester.tap(find.byTooltip('Buka navigasi'));
      await tester.pumpAndSettle();
      await tester.ensureVisible(find.byKey(const Key('nav-esp')));
      await tester.tap(find.byKey(const Key('nav-esp')));
      await tester.pumpAndSettle();
      expect(find.text('Konfigurasi perangkat'), findsOneWidget);
      expect(find.textContaining('protokol'), findsWidgets);
      await tester.tap(find.byTooltip('Buka navigasi'));
      await tester.pumpAndSettle();
      await tester.tap(find.byKey(const Key('nav-account')));
      await tester.pumpAndSettle();
      expect(find.text('Informasi akun'), findsOneWidget);
      expect(find.text('Ganti kata sandi'), findsOneWidget);
      expect(
        find.textContaining('Belum masuk. Form nonaktif.'),
        findsOneWidget,
      );
      expect(tester.takeException(), isNull);
    },
  );

  testWidgets(
    'filter grafik memperbarui parameter dan rentang tanpa membuat data',
    (tester) async {
      await openPreview(tester);
      await tester.ensureVisible(
        find.byKey(const Key('chart-sensor-soil_moisture')),
      );
      await tester.tap(find.byKey(const Key('chart-sensor-soil_moisture')));
      await tester.pumpAndSettle();
      await tester.ensureVisible(find.byKey(const Key('chart-hours-6')));
      await tester.tap(find.byKey(const Key('chart-hours-6')));
      await tester.pumpAndSettle();
      expect(find.text('Kelembapan tanah · %'), findsOneWidget);
      expect(find.text('6 jam terakhir · WIB'), findsOneWidget);
      expect(find.text('Min'), findsNWidgets(2));
      expect(find.text('Rata-rata'), findsNWidgets(2));
    },
  );

  testWidgets('navigasi node menampilkan tiga sensor dan kembali ke login', (
    tester,
  ) async {
    await openPreview(tester);
    await tester.tap(find.byTooltip('Buka navigasi'));
    await tester.pumpAndSettle();
    await tester.tap(find.byKey(const Key('menu-node-2')));
    await tester.pumpAndSettle();
    await tester.ensureVisible(find.byKey(const Key('nav-node-2-temperature')));
    await tester.tap(find.byKey(const Key('nav-node-2-temperature')));
    await tester.pumpAndSettle();
    expect(find.text('Monitoring Node 2'), findsOneWidget);
    expect(find.byKey(const Key('node-card-1')), findsNothing);
    expect(find.byKey(const Key('node-card-2')), findsOneWidget);
    expect(find.text('Min'), findsOneWidget);
    await tester.tap(find.byTooltip('Tutup pratinjau'));
    await tester.pumpAndSettle();
    expect(find.text('Selamat datang kembali.'), findsOneWidget);
  });

  testWidgets('dashboard lengkap dapat dibuka tanpa login palsu', (
    tester,
  ) async {
    await openPreview(tester);
    expect(find.text('Ringkasan monitoring'), findsOneWidget);
    expect(find.textContaining('PRATINJAU'), findsWidgets);
    expect(find.byKey(const Key('node-card-1')), findsOneWidget);
    expect(find.byKey(const Key('node-card-2')), findsOneWidget);
    expect(find.byKey(const Key('sensor-1-temperature')), findsOneWidget);
    expect(find.byKey(const Key('sensor-2-soil_moisture')), findsOneWidget);
    expect(find.text('Tren sensor'), findsOneWidget);
    expect(find.text('Belum ada data'), findsOneWidget);
    expect(find.text('Belum ada riwayat'), findsOneWidget);
    expect(find.text('Online'), findsNothing);
    expect(tester.takeException(), isNull);
  });
}
