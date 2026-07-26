const fs = require('fs');
const path = require('path');

/** @type {import('expo/config').ExpoConfig} */
const appJson = require('./app.json').expo;

const googleServicesPath = path.join(__dirname, 'google-services.json');
const hasGoogleServices = fs.existsSync(googleServicesPath);

const appModule = String(process.env.EXPO_PUBLIC_APP_MODULE || 'kasir')
  .toLowerCase()
  .trim();
const isDapurOnly = appModule === 'dapur';

if (!hasGoogleServices) {
  console.warn(
    '[cogs-kasir] google-services.json belum ada. Push APK (app tertutup) butuh Firebase FCM. Lihat google-services.json.example',
  );
}

module.exports = {
  ...appJson,
  name: isDapurOnly ? 'DAPUR' : appJson.name,
  android: {
    ...appJson.android,
    ...(hasGoogleServices ? { googleServicesFile: './google-services.json' } : {}),
  },
  extra: {
    ...(appJson.extra || {}),
    appModule: isDapurOnly ? 'dapur' : 'kasir',
  },
};
