import { Redirect } from 'expo-router';
import { ActivityIndicator, StyleSheet, View } from 'react-native';
import { ROLE_META, useAuth } from '@/auth';
import { colors } from '@/theme';

export default function IndexRedirect() {
  const { user, loading } = useAuth();

  if (loading) {
    return (
      <View style={styles.splash}>
        <ActivityIndicator color={colors.brand600} />
      </View>
    );
  }

  return <Redirect href={user ? ROLE_META[user.role].homeRoute : '/login'} />;
}

const styles = StyleSheet.create({
  splash: { flex: 1, alignItems: 'center', justifyContent: 'center', backgroundColor: colors.slate100 },
});
