import 'package:flutter/material.dart';
import 'package:flutter_localizations/flutter_localizations.dart';

import 'app_palette.dart';
import 'login_page.dart';
import 'api_client.dart';

void main() {
  const url = String.fromEnvironment('API_BASE_URL');
  if (url.isEmpty) {
    runApp(const MainApp());
    return;
  }
  try {
    runApp(MainApp(api: ApiClient.fromEnvironment(), ownsApi: true));
  } on ArgumentError {
    runApp(const MainApp(configurationError: 'Konfigurasi API tidak valid.'));
  }
}

class MainApp extends StatefulWidget {
  const MainApp({
    super.key,
    this.api,
    this.ownsApi = false,
    this.configurationError,
  });
  final ApiClient? api;
  final bool ownsApi;
  final String? configurationError;
  @override
  State<MainApp> createState() => _MainAppState();
}

class _MainAppState extends State<MainApp> {
  ThemeMode _themeMode = ThemeMode.system;
  @override
  void dispose() {
    if (widget.ownsApi) widget.api?.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) => MaterialApp(
    title: 'Rebung Pintar',
    locale: const Locale('id'),
    supportedLocales: const [Locale('id')],
    localizationsDelegates: GlobalMaterialLocalizations.delegates,
    debugShowCheckedModeBanner: false,
    theme: AppPalette.theme(Brightness.light),
    darkTheme: AppPalette.theme(Brightness.dark),
    themeMode: _themeMode,
    home: LoginPage(
      api: widget.api,
      configurationError: widget.configurationError,
      onThemeChanged: (dark) =>
          setState(() => _themeMode = dark ? ThemeMode.dark : ThemeMode.light),
    ),
  );
}
