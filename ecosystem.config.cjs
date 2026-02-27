module.exports = {
  apps: [
    {
      name: 'esptest-http',
      cwd: __dirname,
      script: 'php',
      args: 'artisan serve --host=127.0.0.1 --port=8010',
      interpreter: 'none',
      autorestart: true,
      restart_delay: 3000,
      max_restarts: 30,
      env: {
        APP_ENV: 'production',
      },
    },
    {
      name: 'esptest-mqtt-worker',
      cwd: __dirname,
      script: 'php',
      args: 'mqtt_worker.php',
      interpreter: 'none',
      autorestart: true,
      restart_delay: 3000,
      max_restarts: 30,
      env: {
        APP_ENV: 'production',
      },
    },
    {
      name: 'esptest-scheduler',
      cwd: __dirname,
      script: 'php',
      args: 'artisan schedule:work',
      interpreter: 'none',
      autorestart: true,
      restart_delay: 3000,
      max_restarts: 30,
      env: {
        APP_ENV: 'production',
      },
    },
  ],
};
