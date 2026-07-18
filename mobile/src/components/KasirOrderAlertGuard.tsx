import { usePathname, useRouter } from 'expo-router';
import type { ReactNode } from 'react';
import { View } from 'react-native';
import { useAuth } from '@/auth';
import { OrderToast } from '@/components/OrderToast';
import { useKasirOrderAlerts } from '@/hooks/useKasirOrderAlerts';
import { setPendingOpenOrderId } from '@/kasir/pendingOrderTracker';

/**
 * Poll + TTS + toast di semua layar kasir, termasuk halaman PIN.
 * Dipasang di root layout agar tidak tertutup native stack.
 */
export function KasirOrderAlertGuard({ children }: { children?: ReactNode }) {
  const { activeModule, user } = useAuth();
  const pathname = usePathname();
  const router = useRouter();

  const enabled =
    activeModule === 'kasir' &&
    !!user?.has_kasir &&
    !pathname.includes('/attendance') &&
    !pathname.includes('/login');

  const { orderAlert, setOrderAlert } = useKasirOrderAlerts(enabled);

  return (
    <View style={{ flex: 1 }}>
      {children}
      <OrderToast
        title={orderAlert?.title ?? null}
        message={orderAlert?.message ?? null}
        sticky
        actionLabel={orderAlert?.pinLocked ? 'Masukkan PIN' : 'Buka Pesanan'}
        onAction={
          orderAlert
            ? async () => {
                if (orderAlert.pinLocked) {
                  router.replace('/kasir/pin' as never);
                  return;
                }
                if (orderAlert.orderId) {
                  setPendingOpenOrderId(orderAlert.orderId);
                }
                router.replace('/kasir' as never);
              }
            : undefined
        }
        onDismiss={() => setOrderAlert(null)}
      />
    </View>
  );
}
