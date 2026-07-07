const { getDefaultConfig } = require('expo/metro-config');

const config = getDefaultConfig(__dirname);

// expo-sqlite di web mengimpor file .wasm (wa-sqlite). Metro perlu
// memperlakukan .wasm sebagai aset agar bundling web berhasil.
config.resolver.assetExts.push('wasm');

module.exports = config;
