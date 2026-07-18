import { Stack } from 'expo-router';
import { colors } from '@/theme';

export default function KasirLayout() {
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
