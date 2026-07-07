import { useRouter } from 'expo-router';
import type { ReactNode } from 'react';
import {
  Pressable,
  StyleSheet,
  Text,
  TextInput,
  type TextInputProps,
  View,
} from 'react-native';
import { useSafeAreaInsets } from 'react-native-safe-area-context';
import { colors, font, radius, shadow, spacing } from '@/theme';

export function ScreenHeader({
  title,
  subtitle,
  onBack,
}: {
  title: string;
  subtitle?: string;
  onBack?: () => void;
}) {
  const router = useRouter();
  const insets = useSafeAreaInsets();

  return (
    <View style={[uiStyles.header, { paddingTop: insets.top + spacing.sm }]}>
      <Pressable
        accessibilityRole="button"
        onPress={() => (onBack ? onBack() : router.back())}
        style={({ pressed }) => [uiStyles.backBtn, pressed && uiStyles.pressed]}
      >
        <Text style={uiStyles.backBtnText}>←</Text>
      </Pressable>
      <View style={uiStyles.headerCopy}>
        <Text style={uiStyles.headerTitle} numberOfLines={1}>
          {title}
        </Text>
        {subtitle ? (
          <Text style={uiStyles.headerSubtitle} numberOfLines={1}>
            {subtitle}
          </Text>
        ) : null}
      </View>
    </View>
  );
}

export function Card({ children, style }: { children: ReactNode; style?: object }) {
  return <View style={[uiStyles.card, style]}>{children}</View>;
}

export function SectionTitle({ children }: { children: ReactNode }) {
  return <Text style={uiStyles.sectionTitle}>{children}</Text>;
}

// Numbered brand-tinted header — mirror of Laravel <x-step-header>.
export function StepHeader({
  number,
  title,
  description,
}: {
  number: number | string;
  title: string;
  description?: string;
}) {
  return (
    <View style={uiStyles.stepHeader}>
      <View style={uiStyles.stepHeaderBadge}>
        <Text style={uiStyles.stepHeaderBadgeText}>{number}</Text>
      </View>
      <View style={{ flex: 1 }}>
        <Text style={uiStyles.stepHeaderTitle}>{title}</Text>
        {description ? <Text style={uiStyles.stepHeaderDesc}>{description}</Text> : null}
      </View>
    </View>
  );
}

// "Langkah X dari Y" progress card — mirror of Laravel <x-step-progress>.
export function StepProgress({
  current,
  total,
  percent,
  steps,
}: {
  current: number;
  total: number;
  percent: number;
  steps?: { number: number; short?: string; done?: boolean }[];
}) {
  return (
    <View style={uiStyles.stepProgress}>
      <View style={uiStyles.stepProgressHead}>
        <Text style={uiStyles.stepProgressTitle}>
          Langkah {current} dari {total}
        </Text>
        <Text style={uiStyles.stepProgressPercent}>{percent}%</Text>
      </View>
      <View style={uiStyles.progressTrack}>
        <View style={[uiStyles.progressFill, { width: `${Math.min(100, Math.max(0, percent))}%` }]} />
      </View>
      {steps && steps.length > 0 ? (
        <View style={uiStyles.stepPillRow}>
          {steps.map((step) => {
            const active = step.number === current;
            return (
              <View
                key={step.number}
                style={[
                  uiStyles.stepPill,
                  step.done && uiStyles.stepPillDone,
                  active && uiStyles.stepPillActive,
                ]}
              >
                <Text
                  style={[
                    uiStyles.stepPillText,
                    step.done && uiStyles.stepPillTextDone,
                    active && uiStyles.stepPillTextActive,
                  ]}
                >
                  {step.done ? '✓' : step.number}
                </Text>
              </View>
            );
          })}
        </View>
      ) : null}
    </View>
  );
}

const STAT_COLORS: Record<string, { bg: string; border: string; text: string; label: string }> = {
  brand: { bg: colors.brand50, border: colors.brand100, text: colors.brand700, label: colors.brand600 },
  green: { bg: colors.emerald50, border: colors.green200, text: colors.emerald700, label: colors.green700 },
  amber: { bg: colors.amber50, border: colors.amber200, text: colors.amber700, label: colors.amber700 },
  rose: { bg: colors.rose50, border: '#fecdd3', text: colors.rose700, label: colors.rose600 },
  slate: { bg: colors.slate50, border: colors.slate200, text: colors.slate700, label: colors.slate500 },
  blue: { bg: colors.blue50, border: colors.blue200, text: colors.blue700, label: colors.blue700 },
};

export function StatCard({
  label,
  value,
  color = 'brand',
}: {
  label: string;
  value: string;
  color?: keyof typeof STAT_COLORS;
}) {
  const palette = STAT_COLORS[color] ?? STAT_COLORS.brand;

  return (
    <View style={[uiStyles.statCard, { backgroundColor: palette.bg, borderColor: palette.border }]}>
      <Text style={[uiStyles.statLabel, { color: palette.label }]} numberOfLines={1}>
        {label}
      </Text>
      <Text style={[uiStyles.statValue, { color: palette.text }]} numberOfLines={1}>
        {value}
      </Text>
    </View>
  );
}

export function Field({
  label,
  hint,
  children,
}: {
  label: string;
  hint?: string;
  children: ReactNode;
}) {
  return (
    <View style={uiStyles.field}>
      <Text style={uiStyles.fieldLabel}>{label}</Text>
      {children}
      {hint ? <Text style={uiStyles.fieldHint}>{hint}</Text> : null}
    </View>
  );
}

export function Input(props: TextInputProps) {
  return (
    <TextInput
      placeholderTextColor={colors.slate400}
      {...props}
      style={[uiStyles.input, props.style]}
    />
  );
}

export function RupiahInput(props: TextInputProps) {
  return (
    <View style={uiStyles.rupiahWrap}>
      <Text style={uiStyles.rupiahPrefix}>Rp</Text>
      <TextInput
        placeholderTextColor={colors.slate400}
        keyboardType="numeric"
        {...props}
        style={[uiStyles.input, uiStyles.rupiahInput, props.style]}
      />
    </View>
  );
}

type ButtonTone = 'brand' | 'green' | 'danger' | 'outline' | 'secondary';

export function PrimaryButton({
  label,
  onPress,
  tone = 'brand',
  disabled,
  size,
}: {
  label: string;
  onPress: () => void;
  tone?: ButtonTone;
  disabled?: boolean;
  size?: 'sm';
}) {
  const toneStyle =
    tone === 'green'
      ? uiStyles.btnGreen
      : tone === 'danger'
        ? uiStyles.btnDanger
        : tone === 'outline' || tone === 'secondary'
          ? uiStyles.btnSecondary
          : uiStyles.btnBrand;
  const textStyle =
    tone === 'outline' || tone === 'secondary' ? uiStyles.btnSecondaryText : uiStyles.btnText;

  return (
    <Pressable
      accessibilityRole="button"
      onPress={onPress}
      disabled={disabled}
      style={({ pressed }) => [
        uiStyles.btn,
        toneStyle,
        size === 'sm' && uiStyles.btnSm,
        pressed && uiStyles.pressed,
        disabled && uiStyles.btnDisabled,
      ]}
    >
      <Text style={[textStyle, size === 'sm' && uiStyles.btnSmText]}>{label}</Text>
    </Pressable>
  );
}

const BADGE_COLORS: Record<string, { bg: string; ring: string; text: string }> = {
  slate: { bg: colors.slate100, ring: colors.slate200, text: colors.slate700 },
  green: { bg: colors.green50, ring: colors.green200, text: colors.green700 },
  blue: { bg: colors.blue50, ring: colors.blue200, text: colors.blue700 },
  amber: { bg: colors.amber50, ring: colors.amber200, text: colors.amber800 },
  brand: { bg: colors.brand50, ring: colors.brand200, text: colors.brand700 },
  rose: { bg: colors.rose50, ring: '#fecdd3', text: colors.rose700 },
  emerald: { bg: colors.emerald50, ring: colors.green200, text: colors.emerald700 },
};

export function Badge({
  label,
  tone = 'slate',
}: {
  label: string;
  tone?: keyof typeof BADGE_COLORS;
}) {
  const palette = BADGE_COLORS[tone] ?? BADGE_COLORS.slate;

  return (
    <View style={[uiStyles.badge, { backgroundColor: palette.bg, borderColor: palette.ring }]}>
      <Text style={[uiStyles.badgeText, { color: palette.text }]}>{label}</Text>
    </View>
  );
}

const ALERT_COLORS: Record<string, { bg: string; border: string; text: string }> = {
  success: { bg: colors.green50, border: colors.green200, text: colors.green700 },
  error: { bg: colors.red50, border: colors.red200, text: colors.red700 },
  tip: { bg: colors.amber50, border: colors.amber200, text: colors.amber800 },
  info: { bg: colors.brand50, border: colors.brand100, text: colors.brand700 },
};

export function Callout({
  tone = 'info',
  children,
  style,
}: {
  tone?: keyof typeof ALERT_COLORS;
  children: ReactNode;
  style?: object;
}) {
  const palette = ALERT_COLORS[tone] ?? ALERT_COLORS.info;

  return (
    <View style={[uiStyles.alert, { backgroundColor: palette.bg, borderColor: palette.border }, style]}>
      {typeof children === 'string' ? (
        <Text style={[uiStyles.alertText, { color: palette.text }]}>{children}</Text>
      ) : (
        children
      )}
    </View>
  );
}

export function EmptyState({ icon, title, hint }: { icon: string; title: string; hint?: string }) {
  return (
    <View style={uiStyles.empty}>
      <Text style={uiStyles.emptyIcon}>{icon}</Text>
      <Text style={uiStyles.emptyTitle}>{title}</Text>
      {hint ? <Text style={uiStyles.emptyHint}>{hint}</Text> : null}
    </View>
  );
}

export function Segmented<T extends string>({
  options,
  value,
  onChange,
}: {
  options: { value: T; label: string }[];
  value: T;
  onChange: (value: T) => void;
}) {
  return (
    <View style={uiStyles.segmented}>
      {options.map((option) => (
        <Pressable
          key={option.value}
          onPress={() => onChange(option.value)}
          style={[uiStyles.segmentOption, value === option.value && uiStyles.segmentActive]}
        >
          <Text
            style={[uiStyles.segmentText, value === option.value && uiStyles.segmentTextActive]}
            numberOfLines={1}
          >
            {option.label}
          </Text>
        </Pressable>
      ))}
    </View>
  );
}

export const uiStyles = StyleSheet.create({
  header: {
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
    borderColor: colors.slate300,
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: colors.white,
  },
  backBtnText: { fontSize: 20, color: colors.slate900 },
  headerCopy: { flex: 1, minWidth: 0 },
  headerTitle: { fontSize: 18, color: colors.slate900, ...font('600') },
  headerSubtitle: { fontSize: 13, color: colors.slate500, marginTop: 2, ...font('400') },
  card: {
    borderRadius: radius.lg,
    borderWidth: 1,
    borderColor: colors.slate200,
    backgroundColor: colors.white,
    padding: spacing.lg,
    gap: spacing.md,
    ...shadow.sm,
  },
  sectionTitle: {
    fontSize: 12,
    textTransform: 'uppercase',
    letterSpacing: 0.6,
    color: colors.slate500,
    ...font('700'),
  },
  // Step header (brand-tinted)
  stepHeader: {
    flexDirection: 'row',
    alignItems: 'center',
    gap: spacing.md,
    borderRadius: radius.lg,
    borderWidth: 1,
    borderColor: colors.brand100,
    backgroundColor: colors.brand50,
    padding: spacing.lg,
  },
  stepHeaderBadge: {
    width: 36,
    height: 36,
    borderRadius: radius.full,
    backgroundColor: colors.brand600,
    alignItems: 'center',
    justifyContent: 'center',
  },
  stepHeaderBadgeText: { color: colors.white, fontSize: 16, ...font('700') },
  stepHeaderTitle: { fontSize: 16, color: colors.slate900, ...font('600') },
  stepHeaderDesc: { fontSize: 13, lineHeight: 18, color: colors.slate600, marginTop: 2, ...font('400') },
  // Step progress
  stepProgress: {
    borderRadius: radius.lg,
    borderWidth: 1,
    borderColor: colors.slate200,
    backgroundColor: colors.white,
    padding: spacing.lg,
    gap: spacing.md,
    ...shadow.sm,
  },
  stepProgressHead: { flexDirection: 'row', justifyContent: 'space-between', alignItems: 'center' },
  stepProgressTitle: { fontSize: 14, color: colors.slate900, ...font('600') },
  stepProgressPercent: { fontSize: 14, color: colors.brand600, ...font('700') },
  progressTrack: {
    height: 8,
    borderRadius: radius.full,
    backgroundColor: colors.slate100,
    overflow: 'hidden',
  },
  progressFill: { height: '100%', borderRadius: radius.full, backgroundColor: colors.brand600 },
  stepPillRow: { flexDirection: 'row', flexWrap: 'wrap', gap: spacing.xs },
  stepPill: {
    width: 32,
    height: 32,
    borderRadius: radius.full,
    backgroundColor: colors.slate100,
    alignItems: 'center',
    justifyContent: 'center',
  },
  stepPillDone: { backgroundColor: colors.green600 },
  stepPillActive: { backgroundColor: colors.brand600 },
  stepPillText: { fontSize: 13, color: colors.slate600, ...font('700') },
  stepPillTextDone: { color: colors.white },
  stepPillTextActive: { color: colors.white },
  // Stat card
  statCard: {
    flexGrow: 1,
    flexBasis: '47%',
    borderRadius: radius.lg,
    borderWidth: 1,
    padding: spacing.lg,
    gap: 6,
  },
  statLabel: { fontSize: 11, textTransform: 'uppercase', letterSpacing: 0.4, ...font('500') },
  statValue: { fontSize: 22, ...font('700') },
  field: { gap: 6 },
  fieldLabel: { fontSize: 14, color: colors.slate700, ...font('500') },
  fieldHint: { fontSize: 12, color: colors.slate500, lineHeight: 16, ...font('400') },
  input: {
    minHeight: 46,
    borderRadius: radius.md,
    borderWidth: 1,
    borderColor: colors.slate300,
    backgroundColor: colors.white,
    paddingHorizontal: spacing.md,
    fontSize: 16,
    color: colors.slate900,
    ...font('400'),
  },
  rupiahWrap: { position: 'relative', justifyContent: 'center' },
  rupiahPrefix: {
    position: 'absolute',
    left: spacing.md,
    zIndex: 1,
    fontSize: 14,
    color: colors.slate500,
    ...font('600'),
  },
  rupiahInput: { paddingLeft: 38 },
  btn: {
    minHeight: 46,
    borderRadius: radius.md,
    alignItems: 'center',
    justifyContent: 'center',
    paddingHorizontal: spacing.lg,
    borderWidth: 1,
    ...shadow.sm,
  },
  btnSm: { minHeight: 36, borderRadius: radius.sm, paddingHorizontal: spacing.md },
  btnBrand: { backgroundColor: colors.brand600, borderColor: colors.brand600 },
  btnGreen: { backgroundColor: colors.green600, borderColor: colors.green600 },
  btnDanger: { backgroundColor: colors.red600, borderColor: colors.red600 },
  btnSecondary: { borderColor: colors.slate300, backgroundColor: colors.white },
  btnDisabled: { opacity: 0.5 },
  btnText: { color: colors.white, fontSize: 15, ...font('600') },
  btnSecondaryText: { color: colors.slate700, fontSize: 15, ...font('600') },
  btnSmText: { fontSize: 13 },
  badge: {
    borderRadius: radius.full,
    borderWidth: 1,
    paddingHorizontal: 10,
    paddingVertical: 3,
    alignSelf: 'flex-start',
  },
  badgeText: { fontSize: 11, ...font('600') },
  alert: {
    borderRadius: radius.md,
    borderWidth: 1,
    paddingHorizontal: spacing.lg,
    paddingVertical: spacing.md,
  },
  alertText: { fontSize: 13, lineHeight: 18, ...font('500') },
  empty: { alignItems: 'center', paddingVertical: spacing.xxl, gap: spacing.sm },
  emptyIcon: { fontSize: 34 },
  emptyTitle: { fontSize: 15, color: colors.slate900, ...font('700') },
  emptyHint: {
    fontSize: 13,
    color: colors.slate500,
    textAlign: 'center',
    paddingHorizontal: spacing.lg,
    ...font('400'),
  },
  segmented: {
    flexDirection: 'row',
    gap: spacing.xs,
    padding: 4,
    borderRadius: radius.md,
    backgroundColor: colors.slate100,
  },
  segmentOption: {
    flex: 1,
    minHeight: 40,
    borderRadius: radius.sm,
    alignItems: 'center',
    justifyContent: 'center',
    paddingHorizontal: spacing.sm,
  },
  segmentActive: { backgroundColor: colors.white, ...shadow.sm },
  segmentText: { fontSize: 12, color: colors.slate600, ...font('500') },
  segmentTextActive: { color: colors.brand700, ...font('600') },
  pressed: { opacity: 0.9, transform: [{ scale: 0.98 }] },
});
