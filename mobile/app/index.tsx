import { Redirect } from 'expo-router';
import { ActivityIndicator, StyleSheet, View } from 'react-native';
import { useAuth } from '@/auth';
import { colors } from '@/theme';

export default function IndexRedirect() {
  const { user, activeModule, loading, pin } = useAuth();

  if (loading) {
    return (
      <View style={styles.splash}>
        <ActivityIndicator color={colors.brand600} />
      </View>
    );
  }

  if (!user || !activeModule) {
    return <Redirect href="/login" />;
  }

  if (activeModule === 'kasir') {
    return <Redirect href={(pin?.unlocked ? '/kasir' : '/kasir/pin') as never} />;
  }

  return <Redirect href="/cogs" />;
}

const styles = StyleSheet.create({
  splash: { flex: 1, alignItems: 'center', justifyContent: 'center', backgroundColor: colors.slate100 },
});
