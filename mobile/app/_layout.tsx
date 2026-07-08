import {
  InstrumentSans_400Regular,
  InstrumentSans_500Medium,
  InstrumentSans_600SemiBold,
  InstrumentSans_700Bold,
  useFonts,
} from '@expo-google-fonts/instrument-sans';
import { Stack, useRouter, useSegments } from 'expo-router';
import * as SplashScreen from 'expo-splash-screen';
import { StatusBar } from 'expo-status-bar';
import { useEffect } from 'react';
import { SafeAreaProvider } from 'react-native-safe-area-context';
import { AuthProvider, ROLE_META, useAuth } from '@/auth';
import { colors } from '@/theme';
import { applyGlobalFont } from '@/theme/applyGlobalFont';

applyGlobalFont();
SplashScreen.preventAutoHideAsync().catch(() => {});

// Rute yang boleh diakses tanpa login.
const PUBLIC_SEGMENTS = new Set(['login', 'pesan-online']);

function RootNavigator() {
  const { user, loading } = useAuth();
  const segments = useSegments();
  const router = useRouter();

  useEffect(() => {
    if (loading) {
      return;
    }

    const first = segments[0] as string | undefined;
    const isPublic = first !== undefined && PUBLIC_SEGMENTS.has(first);

    if (!user) {
      if (!isPublic) {
        router.replace('/login');
      }
      return;
    }

    // Sudah login: arahkan dari login/root ke beranda modul sesuai role.
    if (first === undefined || first === 'login') {
      router.replace(ROLE_META[user.role].homeRoute);
      return;
    }

    // Cegah lintas-modul (mirror middleware role Laravel).
    if (first === 'cogs' && user.role !== 'cogs') {
      router.replace('/kasir');
    } else if (first === 'kasir' && user.role !== 'kasir') {
      router.replace('/cogs');
    }
  }, [user, loading, segments, router]);

  return (
    <Stack
      screenOptions={{
        headerShown: false,
        contentStyle: { backgroundColor: colors.slate100 },
        animation: 'slide_from_right',
      }}
    />
  );
}

export default function RootLayout() {
  const [fontsLoaded, fontError] = useFonts({
    InstrumentSans_400Regular,
    InstrumentSans_500Medium,
    InstrumentSans_600SemiBold,
    InstrumentSans_700Bold,
  });

  useEffect(() => {
    if (fontsLoaded || fontError) {
      SplashScreen.hideAsync().catch(() => {});
    }
  }, [fontsLoaded, fontError]);

  if (!fontsLoaded && !fontError) {
    return null;
  }

  return (
    <SafeAreaProvider>
      <StatusBar style="dark" />
      <AuthProvider>
        <RootNavigator />
      </AuthProvider>
    </SafeAreaProvider>
  );
}
