import 'package:flutter/material.dart';

abstract final class AppPalette {
  static const ivory = Color(0xFFF2F1EF);
  static const yellow = Color(0xFFF7B700);
  static const navy = Color(0xFF011E60);
  static const blue = Color(0xFF6A7FC0);
  static const ink = Color(0xFF282824);
  static const muted = Color(0xFF69665E);
  static const dark = Color(0xFF191A18);
  static const darkCard = Color(0xFF242521);
  static const card = Color(0xFFFAF9F6);

  static ThemeData theme(Brightness brightness) {
    final isDark = brightness == Brightness.dark;
    final text = isDark ? ivory : ink;
    final border = isDark ? const Color(0xFF494A43) : const Color(0xFFDAD8D1);
    final surface = isDark ? dark : ivory;
    final scheme =
        ColorScheme.fromSeed(
          seedColor: yellow,
          brightness: brightness,
        ).copyWith(
          primary: isDark ? yellow : navy,
          onPrimary: isDark ? navy : ivory,
          secondary: yellow,
          onSecondary: navy,
          tertiary: blue,
          surface: surface,
          surfaceContainerHighest: isDark ? darkCard : card,
          onSurface: text,
          onSurfaceVariant: isDark ? const Color(0xFFC0BEB6) : muted,
          outline: border,
          outlineVariant: border,
          surfaceTint: Colors.transparent,
        );
    final shape = RoundedRectangleBorder(
      borderRadius: BorderRadius.circular(12),
    );
    return ThemeData(
      useMaterial3: true,
      brightness: brightness,
      colorScheme: scheme,
      scaffoldBackgroundColor: surface,
      dividerColor: border,
      filledButtonTheme: FilledButtonThemeData(
        style: FilledButton.styleFrom(
          backgroundColor: yellow,
          foregroundColor: navy,
          minimumSize: const Size(48, 48),
          shape: shape,
        ),
      ),
      outlinedButtonTheme: OutlinedButtonThemeData(
        style: OutlinedButton.styleFrom(
          foregroundColor: text,
          side: BorderSide(color: border),
          minimumSize: const Size(48, 48),
          shape: shape,
        ),
      ),
      textButtonTheme: TextButtonThemeData(
        style: TextButton.styleFrom(
          foregroundColor: text,
          minimumSize: const Size(48, 48),
        ),
      ),
      iconButtonTheme: IconButtonThemeData(
        style: IconButton.styleFrom(
          foregroundColor: text,
          minimumSize: const Size(48, 48),
        ),
      ),
      inputDecorationTheme: InputDecorationTheme(
        filled: true,
        fillColor: isDark ? darkCard : card,
        errorMaxLines: 3,
        contentPadding: const EdgeInsets.symmetric(
          horizontal: 16,
          vertical: 16,
        ),
        border: OutlineInputBorder(borderRadius: BorderRadius.circular(12)),
        enabledBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(12),
          borderSide: BorderSide(color: border),
        ),
      ),
    );
  }
}
