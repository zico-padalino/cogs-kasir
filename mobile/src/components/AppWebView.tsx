import { useCallback, useEffect, useRef, useState } from 'react';
import {
  ActivityIndicator,
  Pressable,
  StyleSheet,
  Text,
  View,
} from 'react-native';
import { WebView, type WebViewNavigation } from 'react-native-webview';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { colors, radius, spacing } from '@/theme';

type AppWebViewProps = {
  uri: string;
  title: string;
  onBack?: () => void;
};

export function AppWebView({ uri, title, onBack }: AppWebViewProps) {
  const insets = useSafeAreaInsets();
  const webViewRef = useRef<WebView>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [canGoBack, setCanGoBack] = useState(false);

  useEffect(() => {
    setError(null);
    setLoading(true);
  }, [uri]);

  const handleNavigationStateChange = useCallback((navState: WebViewNavigation) => {
    setCanGoBack(navState.canGoBack);
  }, []);

  const handleRetry = useCallback(() => {
    setError(null);
    setLoading(true);
    webViewRef.current?.reload();
  }, []);

  const handleBackPress = useCallback(() => {
    if (canGoBack) {
      webViewRef.current?.goBack();
      return;
    }

    onBack?.();
  }, [canGoBack, onBack]);

  return (
    <View style={styles.root}>
      <View style={[styles.toolbar, { paddingTop: insets.top + spacing.sm }]}>
        <Pressable
          accessibilityRole="button"
          onPress={handleBackPress}
          style={({ pressed }) => [styles.backBtn, pressed && styles.pressed]}
        >
          <Text style={styles.backBtnText}>←</Text>
        </Pressable>
        <View style={styles.toolbarCopy}>
          <Text style={styles.toolbarTitle} numberOfLines={1}>
            {title}
          </Text>
          <Text style={styles.toolbarUrl} numberOfLines={1}>
            {uri}
          </Text>
        </View>
        <Pressable
          accessibilityRole="button"
          onPress={handleRetry}
          style={({ pressed }) => [styles.refreshBtn, pressed && styles.pressed]}
        >
          <Text style={styles.refreshBtnText}>↻</Text>
        </Pressable>
      </View>

      {error ? (
        <View style={styles.errorWrap}>
          <Text style={styles.errorTitle}>Tidak bisa memuat halaman</Text>
          <Text style={styles.errorText}>{error}</Text>
          <Pressable
            accessibilityRole="button"
            onPress={handleRetry}
            style={({ pressed }) => [styles.retryBtn, pressed && styles.pressed]}
          >
            <Text style={styles.retryBtnText}>Coba lagi</Text>
          </Pressable>
        </View>
      ) : (
        <WebView
          ref={webViewRef}
          source={{ uri }}
          style={styles.webview}
          onLoadStart={() => {
            setLoading(true);
            setError(null);
          }}
          onLoadEnd={() => setLoading(false)}
          onNavigationStateChange={handleNavigationStateChange}
          onError={() => {
            setLoading(false);
            setError('Periksa koneksi internet dan URL server di Pengaturan.');
          }}
          onHttpError={() => {
            setLoading(false);
            setError('Server merespons error. Pastikan Laravel sudah berjalan.');
          }}
          sharedCookiesEnabled
          thirdPartyCookiesEnabled
          domStorageEnabled
          javaScriptEnabled
          allowsBackForwardNavigationGestures
          setSupportMultipleWindows={false}
          startInLoadingState={false}
          pullToRefreshEnabled
          decelerationRate="normal"
          applicationNameForUserAgent="CogsKasirExpo"
        />
      )}

      {loading && !error ? (
        <View style={styles.loadingOverlay}>
          <ActivityIndicator size="large" color={colors.brand600} />
          <Text style={styles.loadingText}>Memuat…</Text>
        </View>
      ) : null}
    </View>
  );
}

const styles = StyleSheet.create({
  root: {
    flex: 1,
    backgroundColor: colors.slate100,
  },
  toolbar: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: spacing.md,
    borderBottomWidth: 1,
    borderBottomColor: colors.slate200,
    backgroundColor: colors.white,
    paddingHorizontal: spacing.lg,
    paddingBottom: spacing.md,
  },
  backBtn: {
    width: 44,
    height: 44,
    borderRadius: radius.md,
    borderWidth: 1,
    borderColor: colors.slate200,
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: colors.white,
  },
  backBtnText: {
    fontSize: 20,
    color: colors.slate900,
  },
  refreshBtn: {
    width: 44,
    height: 44,
    borderRadius: radius.md,
    borderWidth: 1,
    borderColor: colors.slate200,
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: colors.white,
  },
  refreshBtnText: {
    fontSize: 18,
    color: colors.slate900,
  },
  toolbarCopy: {
    flex: 1,
    minWidth: 0,
  },
  toolbarTitle: {
    fontSize: 15,
    fontWeight: '700',
    color: colors.slate900,
  },
  toolbarUrl: {
    marginTop: 2,
    fontSize: 11,
    color: colors.slate500,
  },
  webview: {
    flex: 1,
    backgroundColor: colors.slate100,
  },
  loadingOverlay: {
    ...StyleSheet.absoluteFillObject,
    top: 88,
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: 'rgba(241, 245, 249, 0.92)',
    gap: spacing.md,
  },
  loadingText: {
    fontSize: 14,
    color: colors.slate600,
  },
  errorWrap: {
    flex: 1,
    alignItems: 'center',
    justifyContent: 'center',
    paddingHorizontal: spacing.xxl,
    gap: spacing.md,
  },
  errorTitle: {
    fontSize: 18,
    fontWeight: '700',
    color: colors.slate900,
    textAlign: 'center',
  },
  errorText: {
    fontSize: 14,
    lineHeight: 20,
    color: colors.slate600,
    textAlign: 'center',
  },
  retryBtn: {
    marginTop: spacing.sm,
    minHeight: 44,
    borderRadius: radius.lg,
    backgroundColor: colors.brand600,
    paddingHorizontal: spacing.xl,
    alignItems: 'center',
    justifyContent: 'center',
  },
  retryBtnText: {
    color: colors.white,
    fontSize: 15,
    fontWeight: '700',
  },
  pressed: {
    opacity: 0.85,
    transform: [{ scale: 0.98 }],
  },
});
