import { Alert, Platform } from 'react-native';
import * as ImagePicker from 'expo-image-picker';

export type PickedImage = {
  uri: string;
};

async function ensureLibraryPermission(): Promise<boolean> {
  if (Platform.OS === 'web') {
    return true;
  }

  const current = await ImagePicker.getMediaLibraryPermissionsAsync();
  if (current.granted) {
    return true;
  }

  const asked = await ImagePicker.requestMediaLibraryPermissionsAsync();
  if (asked.granted) {
    return true;
  }

  Alert.alert('Izin galeri', 'Izinkan akses foto di pengaturan HP untuk memilih gambar.');

  return false;
}

async function ensureCameraPermission(): Promise<boolean> {
  if (Platform.OS === 'web') {
    return true;
  }

  const current = await ImagePicker.getCameraPermissionsAsync();
  if (current.granted) {
    return true;
  }

  const asked = await ImagePicker.requestCameraPermissionsAsync();
  if (asked.granted) {
    return true;
  }

  Alert.alert('Izin kamera', 'Izinkan kamera di pengaturan HP untuk mengambil foto.');

  return false;
}

function fromResult(result: ImagePicker.ImagePickerResult): PickedImage | null {
  if (result.canceled || ! result.assets?.[0]?.uri) {
    return null;
  }

  return { uri: result.assets[0].uri };
}

export async function pickFromLibrary(): Promise<PickedImage | null> {
  if (! await ensureLibraryPermission()) {
    return null;
  }

  const result = await ImagePicker.launchImageLibraryAsync({
    mediaTypes: ['images'],
    quality: 0.8,
    allowsEditing: false,
  });

  return fromResult(result);
}

export async function pickFromCamera(): Promise<PickedImage | null> {
  if (! await ensureCameraPermission()) {
    return null;
  }

  const result = await ImagePicker.launchCameraAsync({
    mediaTypes: ['images'],
    quality: 0.7,
    allowsEditing: false,
  });

  return fromResult(result);
}
