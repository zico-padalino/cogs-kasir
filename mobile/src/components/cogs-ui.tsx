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
import { colors, radius, spacing } from '@/theme';

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

const STAT_COLORS: Record<string, { bg: string; text: string }> = {
  brand: { bg: colors.brand50, text: colors.brand700 },
  green: { bg: '#ecfdf5', text: '#047857' },
  amber: { bg: '#fffbeb', text: '#b45309' },
  rose: { bg: '#fff1f2', text: '#be123c' },
  slate: { bg: colors.slate100, text: colors.slate600 },
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
    <View style={[uiStyles.statCard, { backgroundColor: palette.bg }]}>
      <Text style={[uiStyles.statValue, { color: palette.text }]} numberOfLines={1}>
        {value}
      </Text>
      <Text style={uiStyles.statLabel}>{label}</Text>
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
      placeholderTextColor={colors.slate500}
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
        placeholderTextColor={colors.slate500}
        keyboardType="numeric"
        {...props}
        style={[uiStyles.input, uiStyles.rupiahInput, props.style]}
      />
    </View>
  );
}

export function PrimaryButton({
  label,
  onPress,
  tone = 'brand',
  disabled,
}: {
  label: string;
  onPress: () => void;
  tone?: 'brand' | 'green' | 'danger' | 'outline';
  disabled?: boolean;
}) {
  const toneStyle =
    tone === 'green'
      ? uiStyles.btnGreen
      : tone === 'danger'
        ? uiStyles.btnDanger
        : tone === 'outline'
          ? uiStyles.btnOutline
          : uiStyles.btnBrand;
  const textStyle = tone === 'outline' ? uiStyles.btnOutlineText : uiStyles.btnText;

  return (
    <Pressable
      accessibilityRole="button"
      onPress={onPress}
      disabled={disabled}
      style={({ pressed }) => [
        uiStyles.btn,
        toneStyle,
        pressed && uiStyles.pressed,
        disabled && uiStyles.btnDisabled,
      ]}
    >
      <Text style={textStyle}>{label}</Text>
    </Pressable>
  );
}

export function Badge({ label, tone = 'slate' }: { label: string; tone?: keyof typeof STAT_COLORS }) {
  const palette = STAT_COLORS[tone] ?? STAT_COLORS.slate;

  return (
    <View style={[uiStyles.badge, { backgroundColor: palette.bg }]}>
      <Text style={[uiStyles.badgeText, { color: palette.text }]}>{label}</Text>
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
    borderColor: colors.slate200,
    alignItems: 'center',
    justifyContent: 'center',
    backgroundColor: colors.white,
  },
  backBtnText: { fontSize: 20, color: colors.slate900 },
  headerCopy: { flex: 1, minWidth: 0 },
  headerTitle: { fontSize: 17, fontWeight: '800', color: colors.slate900 },
  headerSubtitle: { fontSize: 12, color: colors.slate500, marginTop: 2 },
  card: {
    borderRadius: radius.xl,
    borderWidth: 1,
    borderColor: colors.slate200,
    backgroundColor: colors.white,
    padding: spacing.lg,
    gap: spacing.md,
  },
  sectionTitle: {
    fontSize: 12,
    fontWeight: '700',
    textTransform: 'uppercase',
    letterSpacing: 0.4,
    color: colors.slate500,
  },
  statCard: {
    flexGrow: 1,
    flexBasis: '47%',
    borderRadius: radius.lg,
    padding: spacing.md,
    gap: 4,
  },
  statValue: { fontSize: 18, fontWeight: '800' },
  statLabel: { fontSize: 11, color: colors.slate600, fontWeight: '600' },
  field: { gap: spacing.xs },
  fieldLabel: { fontSize: 12, fontWeight: '700', color: colors.slate600 },
  fieldHint: { fontSize: 11, color: colors.slate500, lineHeight: 15 },
  input: {
    minHeight: 46,
    borderRadius: radius.md,
    borderWidth: 1,
    borderColor: colors.slate200,
    backgroundColor: colors.slate50,
    paddingHorizontal: spacing.md,
    fontSize: 15,
    color: colors.slate900,
  },
  rupiahWrap: { position: 'relative', justifyContent: 'center' },
  rupiahPrefix: {
    position: 'absolute',
    left: spacing.md,
    zIndex: 1,
    fontSize: 14,
    fontWeight: '600',
    color: colors.slate500,
  },
  rupiahInput: { paddingLeft: 38 },
  btn: {
    minHeight: 48,
    borderRadius: radius.lg,
    alignItems: 'center',
    justifyContent: 'center',
    paddingHorizontal: spacing.lg,
  },
  btnBrand: { backgroundColor: colors.brand600 },
  btnGreen: { backgroundColor: colors.green600 },
  btnDanger: { backgroundColor: '#dc2626' },
  btnOutline: { borderWidth: 1, borderColor: colors.slate200, backgroundColor: colors.white },
  btnDisabled: { opacity: 0.5 },
  btnText: { color: colors.white, fontSize: 15, fontWeight: '800' },
  btnOutlineText: { color: colors.slate900, fontSize: 15, fontWeight: '700' },
  badge: { borderRadius: 999, paddingHorizontal: 10, paddingVertical: 4, alignSelf: 'flex-start' },
  badgeText: { fontSize: 11, fontWeight: '700' },
  empty: { alignItems: 'center', paddingVertical: spacing.xxl, gap: spacing.sm },
  emptyIcon: { fontSize: 34 },
  emptyTitle: { fontSize: 15, fontWeight: '700', color: colors.slate900 },
  emptyHint: { fontSize: 13, color: colors.slate500, textAlign: 'center', paddingHorizontal: spacing.lg },
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
    borderRadius: radius.md - 2,
    alignItems: 'center',
    justifyContent: 'center',
    paddingHorizontal: spacing.sm,
  },
  segmentActive: { backgroundColor: colors.white },
  segmentText: { fontSize: 12, fontWeight: '600', color: colors.slate600 },
  segmentTextActive: { color: colors.brand700 },
  pressed: { opacity: 0.9, transform: [{ scale: 0.98 }] },
});
