import { createElement } from 'react';
import { StyleSheet, Text, TextInput } from 'react-native';
import { font } from '@/theme';

// Terapkan Instrument Sans ke SELURUH <Text>/<TextInput> secara global dengan
// memetakan fontWeight ke file font yang tepat (native butuh nama family spesifik).
// fontFamily eksplisit di style (mis. 'monospace') tetap dipertahankan karena
// style milik pemanggil ditaruh setelah default kita.
function patchComponent(Component: any) {
  if (!Component || Component.__instrumentSansPatched) {
    return;
  }

  const originalRender = Component.render;
  if (typeof originalRender !== 'function') {
    return;
  }

  Component.__instrumentSansPatched = true;
  Component.render = function patchedRender(props: any, ref: any) {
    const element = originalRender.call(this, props, ref);

    if (!element || !element.props) {
      return element;
    }

    const flat = StyleSheet.flatten(element.props.style) || {};
    const family = font(String(flat.fontWeight ?? '400') as any).fontFamily;

    return createElement(element.type, {
      ...element.props,
      style: [{ fontFamily: family }, element.props.style],
    });
  };
}

let applied = false;

export function applyGlobalFont() {
  if (applied) {
    return;
  }
  applied = true;

  try {
    patchComponent(Text as any);
    patchComponent(TextInput as any);
  } catch {
    // Jika struktur internal RN berubah, biarkan font sistem sebagai fallback.
  }
}
