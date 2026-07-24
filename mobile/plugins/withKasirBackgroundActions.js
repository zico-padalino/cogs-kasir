const {
  withAndroidManifest,
  AndroidConfig,
} = require('@expo/config-plugins');

/**
 * Pastikan service background actions punya foregroundServiceType (Android 14+),
 * dan izinkan query package Thermer untuk cetak thermal.
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

    // Package visibility (Android 11+) — buka Thermer untuk cetak thermal
    if (!manifest.manifest.queries) {
      manifest.manifest.queries = [];
    }
    const queries = manifest.manifest.queries;
    const hasThermerPackage = queries.some(
      (q) => q.package?.some((p) => p.$?.['android:name'] === 'mate.bluetoothprint'),
    );
    if (!hasThermerPackage) {
      queries.push({
        package: [{ $: { 'android:name': 'mate.bluetoothprint' } }],
      });
    }
    const hasThermerScheme = queries.some(
      (q) =>
        q.intent?.some(
          (intent) =>
            intent.data?.some((d) => d.$?.['android:scheme'] === 'thermer'),
        ),
    );
    if (!hasThermerScheme) {
      queries.push({
        intent: [
          {
            action: [{ $: { 'android:name': 'android.intent.action.VIEW' } }],
            data: [{ $: { 'android:scheme': 'thermer' } }],
          },
        ],
      });
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
