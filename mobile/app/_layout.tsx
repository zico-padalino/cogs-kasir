import {
  Fraunces_600SemiBold,
  Fraunces_700Bold,
} from '@expo-google-fonts/fraunces';
import {
  SourceSans3_400Regular,
  SourceSans3_500Medium,
  SourceSans3_600SemiBold,
  SourceSans3_700Bold,
  useFonts,
} from '@expo-google-fonts/source-sans-3';
import { Stack, useRouter, useSegments } from 'expo-router';
import * as SplashScreen from 'expo-splash-screen';
import { StatusBar } from 'expo-status-bar';
import { useEffect } from 'react';
import { SafeAreaProvider } from 'react-native-safe-area-context';
import { AuthProvider, ROLE_META, useAuth } from '@/auth';
import { KasirOrderAlertGuard } from '@/components/KasirOrderAlertGuard';
import { KasirPinSessionGuard } from '@/components/KasirPinSessionGuard';
import { KasirPushKeepAlive } from '@/components/KasirPushKeepAlive';
import { warmupOrderSpeech } from '@/kasir/orderAlert';
import {
  addKasirNotificationResponseListener,
  setupKasirPushRuntime,
} from '@/kasir/pushNotifications';
import { colors } from '@/theme';
import { applyGlobalFont } from '@/theme/applyGlobalFont';

applyGlobalFont();
SplashScreen.preventAutoHideAsync().catch(() => {});
// Background task sedini mungkin (HP terkunci / app di-swipe tutup).
void setupKasirPushRuntime();
// Siapkan engine TTS lebih awal agar suara AI tidak sering kosong di call pertama.
void warmupOrderSpeech();

const PUBLIC_SEGMENTS = new Set(['login', 'pesan-online']);

function RootNavigator() {
  const { user, activeModule, loading, pin } = useAuth();
  const segments = useSegments();
  const router = useRouter();

  useEffect(() => {
    void setupKasirPushRuntime();
    const sub = addKasirNotificationResponseListener(() => {
      if (!user?.has_kasir) {
        return;
      }
      router.push((pin?.unlocked ? '/kasir' : '/kasir/pin') as never);
    });

    return () => sub.remove();
  }, [user?.has_kasir, pin?.unlocked, router]);

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
    <KasirPinSessionGuard>
      <KasirOrderAlertGuard>
        <Stack
          screenOptions={{
            headerShown: false,
            contentStyle: { backgroundColor: colors.slate100 },
            animation: 'slide_from_right',
          }}
        />
      </KasirOrderAlertGuard>
    </KasirPinSessionGuard>
  );
}

export default function RootLayout() {
  const [fontsLoaded, fontError] = useFonts({
    SourceSans3_400Regular,
    SourceSans3_500Medium,
    SourceSans3_600SemiBold,
    SourceSans3_700Bold,
    Fraunces_600SemiBold,
    Fraunces_700Bold,
  });

  const fontsReady = fontsLoaded || !!fontError;

  useEffect(() => {
    if (fontsReady) {
      SplashScreen.hideAsync().catch(() => {});
    }
  }, [fontsReady]);

  // Selalu wrap AuthProvider agar route (termasuk index) tidak pernah di luar context.
  return (
    <SafeAreaProvider>
      <StatusBar style="dark" />
      <AuthProvider>
        {fontsReady ? (
          <>
            <KasirPushKeepAlive />
            <RootNavigator />
          </>
        ) : null}
      </AuthProvider>
    </SafeAreaProvider>
  );
}
