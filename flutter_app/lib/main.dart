import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'config/theme.dart';
import 'services/auth_provider.dart';
import 'screens/auth/login_screen.dart';
import 'screens/dashboard/dashboard_screen.dart';
import 'screens/appointments/appointments_screen.dart';
import 'screens/health/health_screen.dart';
import 'screens/messages/messages_screen.dart';
import 'screens/diet/diet_screen.dart';
import 'screens/profile/profile_screen.dart';

void main() async {
  WidgetsFlutterBinding.ensureInitialized();
  final auth = AuthProvider();
  await auth.init();
  runApp(
    ChangeNotifierProvider.value(value: auth, child: const HealthSphereApp()),
  );
}

class HealthSphereApp extends StatelessWidget {
  const HealthSphereApp({super.key});

  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      title: 'HealthSphere',
      debugShowCheckedModeBanner: false,
      theme: AppTheme.light,
      home: context.watch<AuthProvider>().loading
          ? const _SplashScreen()
          : context.watch<AuthProvider>().isLoggedIn
              ? const MainShell()
              : const LoginScreen(),
    );
  }
}

class _SplashScreen extends StatelessWidget {
  const _SplashScreen();
  @override
  Widget build(BuildContext context) => Scaffold(
    body: Container(
      decoration: const BoxDecoration(
        gradient: LinearGradient(
          colors: [Color(0xFF4285E8), Color(0xFF5A4FCE)],
          begin: Alignment.topLeft, end: Alignment.bottomRight,
        ),
      ),
      child: const Center(child: Column(mainAxisSize: MainAxisSize.min, children: [
        Icon(Icons.favorite_rounded, color: Colors.white, size: 64),
        SizedBox(height: 16),
        Text('HealthSphere', style: TextStyle(color: Colors.white, fontSize: 28, fontWeight: FontWeight.bold)),
        SizedBox(height: 24),
        CircularProgressIndicator(color: Colors.white70, strokeWidth: 2),
      ])),
    ),
  );
}

class MainShell extends StatefulWidget {
  const MainShell({super.key});
  @override
  State<MainShell> createState() => _MainShellState();
}

class _MainShellState extends State<MainShell> {
  int _index = 0;

  static const _screens = [
    DashboardScreen(),
    AppointmentsScreen(),
    HealthScreen(),
    DietScreen(),
    MessagesScreen(),
    ProfileScreen(),
  ];

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      body: IndexedStack(index: _index, children: _screens),
      bottomNavigationBar: NavigationBar(
        selectedIndex: _index,
        onDestinationSelected: (i) => setState(() => _index = i),
        backgroundColor: Colors.white,
        indicatorColor: AppTheme.primary.withOpacity(0.12),
        labelBehavior: NavigationDestinationLabelBehavior.alwaysShow,
        destinations: const [
          NavigationDestination(icon: Icon(Icons.dashboard_outlined), selectedIcon: Icon(Icons.dashboard_rounded), label: 'Home'),
          NavigationDestination(icon: Icon(Icons.calendar_today_outlined), selectedIcon: Icon(Icons.calendar_today_rounded), label: 'Appointments'),
          NavigationDestination(icon: Icon(Icons.monitor_heart_outlined), selectedIcon: Icon(Icons.monitor_heart_rounded), label: 'Health'),
          NavigationDestination(icon: Icon(Icons.restaurant_menu_outlined), selectedIcon: Icon(Icons.restaurant_menu_rounded), label: 'Diet'),
          NavigationDestination(icon: Icon(Icons.chat_bubble_outline_rounded), selectedIcon: Icon(Icons.chat_bubble_rounded), label: 'Messages'),
          NavigationDestination(icon: Icon(Icons.person_outline_rounded), selectedIcon: Icon(Icons.person_rounded), label: 'Profile'),
        ],
      ),
    );
  }
}
