import { useRouter } from 'expo-router';
import { useEffect, useState } from 'react';
import { ActivityIndicator, StyleSheet, View } from 'react-native';
import { AppWebView } from '@/components/AppWebView';
import { buildAppPath, getAppBaseUrl } from '@/config/appUrl';
import { colors } from '@/theme';

export default function KasirScreen() {
  const router = useRouter();
  const [uri, setUri] = useState<string | null>(null);

  useEffect(() => {
    getAppBaseUrl().then((baseUrl) => {
      setUri(buildAppPath(baseUrl, '/kasir'));
    });
  }, []);

  if (!uri) {
    return (
      <View style={styles.loading}>
        <ActivityIndicator size="large" color={colors.brand600} />
      </View>
    );
  }

  return (
    <AppWebView
      uri={uri}
      title="Kasir POS"
      onBack={() => router.back()}
    />
  );
}

const styles = StyleSheet.create({
  loading: {
    flex: 1,
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: colors.slate100,
  },
});
