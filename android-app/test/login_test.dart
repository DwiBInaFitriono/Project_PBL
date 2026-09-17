import 'package:flutter/material.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:rebung_pintar/main.dart';

void main() {
  testWidgets('tema terang dan gelap berubah tanpa menghapus input', (
    tester,
  ) async {
    await tester.pumpWidget(const MainApp());
    await tester.enterText(find.byKey(const Key('email')), 'uji@example.test');
    await tester.tap(find.byTooltip('Aktifkan mode gelap'));
    await tester.pumpAndSettle();
    expect(
      Theme.of(tester.element(find.byType(Scaffold))).brightness,
      Brightness.dark,
    );
    expect(find.text('uji@example.test'), findsOneWidget);
    await tester.tap(find.byTooltip('Aktifkan mode terang'));
    await tester.pumpAndSettle();
    expect(
      Theme.of(tester.element(find.byType(Scaffold))).brightness,
      Brightness.light,
    );
  });

  for (final size in [
    const Size(320, 568),
    const Size(390, 844),
    const Size(844, 390),
    const Size(768, 1024),
    const Size(1440, 900),
  ]) {
    testWidgets('login responsif $size tanpa overflow', (tester) async {
      tester.view.devicePixelRatio = 1;
      tester.view.physicalSize = size;
      addTearDown(tester.view.resetPhysicalSize);
      addTearDown(tester.view.resetDevicePixelRatio);
      await tester.pumpWidget(const MainApp());
      expect(tester.takeException(), isNull);
      final rect = tester.getRect(find.byKey(const Key('email')));
      expect(rect.left, greaterThanOrEqualTo(0));
      expect(rect.right, lessThanOrEqualTo(size.width));
      expect(rect.width, lessThanOrEqualTo(420));
      await tester.ensureVisible(find.byKey(const Key('submit')));
      await tester.tap(find.byKey(const Key('submit')));
      await tester.pumpAndSettle();
      expect(tester.takeException(), isNull);
    });
  }

  testWidgets(
    'keyboard dan ukuran teks besar tetap dapat menggulir tombol masuk',
    (tester) async {
      tester.view.devicePixelRatio = 1;
      tester.view.physicalSize = const Size(320, 568);
      tester.view.viewInsets = const FakeViewPadding(bottom: 280);
      tester.platformDispatcher.textScaleFactorTestValue = 2;
      addTearDown(tester.view.reset);
      addTearDown(tester.platformDispatcher.clearTextScaleFactorTestValue);
      await tester.pumpWidget(const MainApp());
      await tester.ensureVisible(find.byKey(const Key('submit')));
      await tester.pumpAndSettle();
      expect(tester.takeException(), isNull);
      await tester.tap(find.byKey(const Key('submit')));
      await tester.pumpAndSettle();
      expect(find.text('Alamat email wajib diisi.'), findsOneWidget);
      expect(tester.takeException(), isNull);
    },
  );

  testWidgets(
    'validasi menolak form kosong lalu menjelaskan integrasi belum tersedia',
    (tester) async {
      await tester.pumpWidget(const MainApp());
      await tester.tap(find.byKey(const Key('submit')));
      await tester.pumpAndSettle();
      expect(find.text('Alamat email wajib diisi.'), findsOneWidget);
      expect(find.text('Kata sandi wajib diisi.'), findsOneWidget);
      await tester.enterText(find.byKey(const Key('email')), 'bukan-email');
      await tester.enterText(find.byKey(const Key('password')), 'contoh-uji');
      await tester.ensureVisible(find.byKey(const Key('submit')));
      await tester.tap(find.byKey(const Key('submit')));
      await tester.pumpAndSettle();
      expect(find.text('Masukkan alamat email yang valid.'), findsOneWidget);
      await tester.enterText(
        find.byKey(const Key('email')),
        'test@example.test',
      );
      await tester.ensureVisible(find.byKey(const Key('submit')));
      await tester.tap(find.byKey(const Key('submit')));
      await tester.pumpAndSettle();
      expect(
        find.textContaining('Login belum dihubungkan ke API Laravel'),
        findsOneWidget,
      );
      expect(find.text('Selamat datang kembali.'), findsOneWidget);
    },
  );

  testWidgets('password dapat ditampilkan tanpa mengirim form', (tester) async {
    await tester.pumpWidget(const MainApp());
    final password = find.descendant(
      of: find.byKey(const Key('password')),
      matching: find.byType(TextField),
    );
    expect(tester.widget<TextField>(password).obscureText, isTrue);
    await tester.tap(find.byTooltip('Tampilkan kata sandi'));
    await tester.pumpAndSettle();
    expect(tester.widget<TextField>(password).obscureText, isFalse);
    await tester.tap(find.byTooltip('Sembunyikan kata sandi'));
    await tester.pumpAndSettle();
    expect(tester.widget<TextField>(password).obscureText, isTrue);
  });

  testWidgets('login menampilkan identitas, label, dan batas pratinjau', (
    tester,
  ) async {
    await tester.pumpWidget(const MainApp());
    expect(find.text('Selamat datang kembali.'), findsOneWidget);
    expect(find.text('Alamat email'), findsOneWidget);
    expect(find.text('Kata sandi'), findsOneWidget);
    expect(find.textContaining('Belum terhubung ke server'), findsOneWidget);
    expect(find.byType(TextFormField), findsNWidgets(2));
  });
}
