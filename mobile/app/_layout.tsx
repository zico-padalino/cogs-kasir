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

const PUBLIC_SEGMENTS = new Set(['login', 'pesan-online']);

function RootNavigator() {
  const { user, activeModule, loading, pin } = useAuth();
  const segments = useSegments();
  const router = useRouter();

  useEffect(() => {
    if (loading) {
      return;
    }

    const first = segments[0] as string | undefined;
    const second = segments[1] as string | undefined;
    const isPublic = first !== undefined && PUBLIC_SEGMENTS.has(first);

    if (!user || !activeModule) {
      if (!isPublic) {
        router.replace('/login');
      }
      return;
    }

    if (first === undefined || first === 'login') {
      if (activeModule === 'kasir') {
        router.replace((pin?.unlocked ? '/kasir' : '/kasir/pin') as never);
      } else {
        router.replace(ROLE_META.cogs.homeRoute);
      }
      return;
    }

    if (activeModule === 'kasir') {
      if (first === 'cogs') {
        router.replace((pin?.unlocked ? '/kasir' : '/kasir/pin') as never);
        return;
      }
      if (first === 'kasir' && second !== 'pin' && second !== 'attendance' && !pin?.unlocked) {
        router.replace('/kasir/pin' as never);
      }
    } else if (first === 'kasir') {
      router.replace('/cogs');
    }
  }, [user, activeModule, loading, segments, router, pin?.unlocked]);

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
