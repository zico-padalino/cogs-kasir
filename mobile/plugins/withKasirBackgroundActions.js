const {
  withAndroidManifest,
  AndroidConfig,
} = require('@expo/config-plugins');

/**
 * Pastikan service background actions punya foregroundServiceType (Android 14+).
 */
function withKasirBackgroundActions(config) {
  return withAndroidManifest(config, (config) => {
    const manifest = config.modResults;
    const app = AndroidConfig.Manifest.getMainApplicationOrThrow(manifest);

    const permissions = [
      'android.permission.FOREGROUND_SERVICE',
      'android.permission.FOREGROUND_SERVICE_DATA_SYNC',
      'android.permission.WAKE_LOCK',
      'android.permission.REQUEST_IGNORE_BATTERY_OPTIMIZATIONS',
    ];

    if (!manifest.manifest['uses-permission']) {
      manifest.manifest['uses-permission'] = [];
    }

    for (const name of permissions) {
      const exists = manifest.manifest['uses-permission'].some(
        (item) => item.$?.['android:name'] === name,
      );
      if (!exists) {
        manifest.manifest['uses-permission'].push({
          $: { 'android:name': name },
        });
      }
    }

    if (!app.service) {
      app.service = [];
    }

    const serviceName = 'com.asterinet.react.bgactions.RNBackgroundActionsTask';
    const existing = app.service.find((s) => s.$?.['android:name'] === serviceName);

    if (existing) {
      existing.$['android:foregroundServiceType'] = 'dataSync';
      existing.$['android:exported'] = 'false';
    } else {
      app.service.push({
        $: {
          'android:name': serviceName,
          'android:foregroundServiceType': 'dataSync',
          'android:exported': 'false',
        },
      });
    }

    return config;
  });
}

module.exports = withKasirBackgroundActions;
