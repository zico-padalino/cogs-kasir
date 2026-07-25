import { usePathname, useRouter } from 'expo-router';
import type { ReactNode } from 'react';
import { View } from 'react-native';
import { useAuth } from '@/auth';
import { OrderToast } from '@/components/OrderToast';
import { useDapurOrderAlerts } from '@/hooks/useDapurOrderAlerts';

/**
 * Poll + TTS menu + toast di semua layar dapur, termasuk saat menunggu PIN.
 */
export function DapurOrderAlertGuard({ children }: { children?: ReactNode }) {
  const { activeModule, user } = useAuth();
  const pathname = usePathname();
  const router = useRouter();

  const enabled =
    activeModule === 'dapur' &&
    !!user?.has_kasir &&
    !pathname.includes('/attendance') &&
    !pathname.includes('/login');

  const { orderAlert, setOrderAlert } = useDapurOrderAlerts(enabled);

  return (
    <View style={{ flex: 1 }}>
      {children}
      <OrderToast
        title={orderAlert?.title ?? null}
        message={orderAlert?.message ?? null}
        sticky
        actionLabel={orderAlert?.pinLocked ? 'Masukkan PIN' : 'Lihat Dapur'}
        onAction={
          orderAlert
            ? async () => {
                if (orderAlert.pinLocked) {
                  router.replace('/kasir/pin' as never);
                  return;
                }
                router.replace('/dapur' as never);
              }
            : undefined
        }
        onDismiss={() => setOrderAlert(null)}
      />
    </View>
  );
}
