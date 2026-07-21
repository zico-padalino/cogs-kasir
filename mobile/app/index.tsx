import { ActivityIndicator, StyleSheet, View } from 'react-native';
import { colors } from '@/theme';

/**
 * Splash singkat. Redirect auth ditangani RootNavigator di _layout
 * supaya layar ini tidak perlu useAuth (hindari race di luar AuthProvider).
 */
export default function IndexRedirect() {
  return (
    <View style={styles.splash}>
      <ActivityIndicator color={colors.brand600} />
    </View>
  );
}

const styles = StyleSheet.create({
  splash: { flex: 1, alignItems: 'center', justifyContent: 'center', backgroundColor: colors.slate100 },
});
