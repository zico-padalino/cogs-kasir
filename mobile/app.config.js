const fs = require('fs');
const path = require('path');

/** @type {import('expo/config').ExpoConfig} */
const appJson = require('./app.json').expo;

const googleServicesPath = path.join(__dirname, 'google-services.json');
const hasGoogleServices = fs.existsSync(googleServicesPath);

if (!hasGoogleServices) {
  console.warn(
    '[cogs-kasir] google-services.json belum ada. Push APK (app tertutup) butuh Firebase FCM. Lihat google-services.json.example',
  );
}

module.exports = {
  ...appJson,
  android: {
    ...appJson.android,
    ...(hasGoogleServices ? { googleServicesFile: './google-services.json' } : {}),
  },
};
