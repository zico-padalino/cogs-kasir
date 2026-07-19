declare module 'react-native-background-actions' {
  type BackgroundTaskOptions = {
    taskName: string;
    taskTitle: string;
    taskDesc: string;
    taskIcon: { name: string; type?: string; package?: string };
    color?: string;
    linkingURI?: string;
    parameters?: Record<string, unknown>;
    progressBar?: { max?: number; value?: number; indeterminate?: boolean };
  };

  type BackgroundTask = (parameters?: Record<string, unknown>) => Promise<void>;

  const BackgroundService: {
    start: (task: BackgroundTask, options: BackgroundTaskOptions) => Promise<void>;
    stop: () => Promise<void>;
    isRunning: () => boolean;
    updateNotification: (options: Partial<BackgroundTaskOptions>) => Promise<void>;
  };

  export default BackgroundService;
}
