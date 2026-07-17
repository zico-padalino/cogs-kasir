import { useEffect, useRef, useState } from 'react';
import { ActivityIndicator, Animated, Pressable, StyleSheet, Text, View } from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { colors, font, radius, shadow, spacing } from '@/theme';

type Props = {
  title: string | null;
  message: string | null;
  actionLabel?: string;
  onAction?: () => void | Promise<void>;
  onDismiss?: () => void;
  /** Tanpa auto-hide jika ada tombol aksi (biar bisa dipilih buka pesanan). */
  sticky?: boolean;
  durationMs?: number;
};

/** Toast visual di atas layar — mirror notifikasi web kasir. */
export function OrderToast({
  title,
  message,
  actionLabel = 'Buka Pesanan',
  onAction,
  onDismiss,
  sticky = false,
  durationMs = 5200,
}: Props) {
  const insets = useSafeAreaInsets();
  const opacity = useRef(new Animated.Value(0)).current;
  const translateY = useRef(new Animated.Value(-16)).current;
  const [visible, setVisible] = useState(false);
  const [busy, setBusy] = useState(false);

  useEffect(() => {
    if (!title || !message) {
      setVisible(false);
      setBusy(false);
      return;
    }

    setVisible(true);
    setBusy(false);
    opacity.setValue(0);
    translateY.setValue(-16);

    Animated.parallel([
      Animated.timing(opacity, { toValue: 1, duration: 220, useNativeDriver: true }),
      Animated.timing(translateY, { toValue: 0, duration: 220, useNativeDriver: true }),
    ]).start();

    if (sticky) {
      return;
    }

    const hide = setTimeout(() => {
      Animated.parallel([
        Animated.timing(opacity, { toValue: 0, duration: 200, useNativeDriver: true }),
        Animated.timing(translateY, { toValue: -12, duration: 200, useNativeDriver: true }),
      ]).start(({ finished }) => {
        if (finished) {
          setVisible(false);
          onDismiss?.();
        }
      });
    }, durationMs);

    return () => clearTimeout(hide);
  }, [title, message, sticky, durationMs, onDismiss, opacity, translateY]);

  if (!visible || !title || !message) {
    return null;
  }

  const dismiss = () => {
    setVisible(false);
    onDismiss?.();
  };

  const handleAction = async () => {
    if (!onAction || busy) return;
    setBusy(true);
    try {
      await onAction();
      dismiss();
    } catch {
      setBusy(false);
    }
  };

  return (
    <View pointerEvents="box-none" style={[styles.wrap, { top: insets.top + 8 }]}>
      <Animated.View style={[styles.toast, { opacity, transform: [{ translateY }] }]}>
        <Text style={styles.icon}>🔔</Text>
        <View style={styles.copy}>
          <Text style={styles.title} numberOfLines={1}>
            {title}
          </Text>
          <Text style={styles.message} numberOfLines={2}>
            {message}
          </Text>
          {onAction ? (
            <View style={styles.actions}>
              <Pressable onPress={dismiss} style={styles.secondaryBtn} disabled={busy}>
                <Text style={styles.secondaryText}>Nanti</Text>
              </Pressable>
              <Pressable
                onPress={() => void handleAction()}
                style={[styles.primaryBtn, busy && { opacity: 0.7 }]}
                disabled={busy}
              >
                {busy ? (
                  <ActivityIndicator color={colors.white} size="small" />
                ) : (
                  <Text style={styles.primaryText}>{actionLabel}</Text>
                )}
              </Pressable>
            </View>
          ) : null}
        </View>
        {!onAction ? (
          <Pressable onPress={dismiss} hitSlop={10} style={styles.close}>
            <Text style={styles.closeText}>✕</Text>
          </Pressable>
        ) : null}
      </Animated.View>
    </View>
  );
}

const styles = StyleSheet.create({
  wrap: {
    position: 'absolute',
    left: spacing.md,
    right: spacing.md,
    zIndex: 100,
  },
  toast: {
    flexDirection: 'row',
    alignItems: 'flex-start',
    gap: spacing.sm,
    backgroundColor: colors.slate900,
    borderRadius: radius.xl,
    paddingHorizontal: spacing.md,
    paddingVertical: spacing.md,
    borderWidth: 1,
    borderColor: colors.slate800,
    ...shadow.md,
  },
  icon: { fontSize: 20, marginTop: 2 },
  copy: { flex: 1, minWidth: 0 },
  title: { color: colors.white, fontSize: 14, ...font('700') },
  message: { color: colors.slate300, fontSize: 12, marginTop: 2, lineHeight: 16 },
  actions: {
    flexDirection: 'row',
    gap: 8,
    marginTop: spacing.md,
  },
  secondaryBtn: {
    flex: 1,
    minHeight: 40,
    borderRadius: radius.md,
    borderWidth: 1,
    borderColor: colors.slate600,
    alignItems: 'center',
    justifyContent: 'center',
  },
  secondaryText: { color: colors.slate200, fontSize: 13, ...font('600') },
  primaryBtn: {
    flex: 1.35,
    minHeight: 40,
    borderRadius: radius.md,
    backgroundColor: colors.brand600,
    alignItems: 'center',
    justifyContent: 'center',
    paddingHorizontal: spacing.sm,
  },
  primaryText: { color: colors.white, fontSize: 13, ...font('700') },
  close: {
    width: 28,
    height: 28,
    borderRadius: 14,
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: 'rgba(255,255,255,0.08)',
  },
  closeText: { color: colors.slate300, fontSize: 12, ...font('700') },
});
