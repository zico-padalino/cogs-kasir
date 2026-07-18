import { Stack } from 'expo-router';
import { KasirOrderAlertGuard } from '@/components/KasirOrderAlertGuard';
import { colors } from '@/theme';

export default function KasirLayout() {
  return (
    <KasirOrderAlertGuard>
      <Stack
        screenOptions={{
          headerShown: false,
          contentStyle: { backgroundColor: colors.slate100 },
          animation: 'slide_from_right',
        }}
      />
    </KasirOrderAlertGuard>
  );
}
