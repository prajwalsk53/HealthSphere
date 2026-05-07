import 'package:flutter/material.dart';
import 'package:google_fonts/google_fonts.dart';

class AppTheme {
  static const Color primary   = Color(0xFF4285E8);
  static const Color secondary = Color(0xFF5A4FCE);
  static const Color success   = Color(0xFF2ECC71);
  static const Color warning   = Color(0xFFF39C12);
  static const Color danger    = Color(0xFFE74C3C);
  static const Color surface   = Color(0xFFF8FAFF);
  static const Color card      = Colors.white;

  static ThemeData get light => ThemeData(
    useMaterial3: true,
    colorScheme: ColorScheme.fromSeed(
      seedColor: primary,
      brightness: Brightness.light,
    ).copyWith(primary: primary, secondary: secondary),
    textTheme: GoogleFonts.interTextTheme(),
    scaffoldBackgroundColor: surface,
    appBarTheme: AppBarTheme(
      backgroundColor: Colors.white,
      foregroundColor: const Color(0xFF1A1A2E),
      elevation: 0,
      titleTextStyle: GoogleFonts.inter(
        fontSize: 18, fontWeight: FontWeight.w600, color: const Color(0xFF1A1A2E),
      ),
    ),
  cardTheme: CardThemeData(
      color: card,
      elevation: 2,
      shadowColor: Colors.black.withOpacity(0.08),
      shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(16)),
    ),
    elevatedButtonTheme: ElevatedButtonThemeData(
      style: ElevatedButton.styleFrom(
        backgroundColor: primary,
        foregroundColor: Colors.white,
        elevation: 0,
        padding: const EdgeInsets.symmetric(vertical: 16, horizontal: 24),
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
        textStyle: GoogleFonts.inter(fontSize: 15, fontWeight: FontWeight.w600),
      ),
    ),
    inputDecorationTheme: InputDecorationTheme(
      filled: true,
      fillColor: const Color(0xFFF0F4FF),
      border: OutlineInputBorder(
        borderRadius: BorderRadius.circular(12),
        borderSide: BorderSide.none,
      ),
      focusedBorder: OutlineInputBorder(
        borderRadius: BorderRadius.circular(12),
        borderSide: const BorderSide(color: primary, width: 2),
      ),
      contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 14),
    ),
    bottomNavigationBarTheme: const BottomNavigationBarThemeData(
      selectedItemColor: primary,
      unselectedItemColor: Color(0xFF9E9E9E),
      backgroundColor: Colors.white,
      type: BottomNavigationBarType.fixed,
      elevation: 12,
    ),
  );
}
