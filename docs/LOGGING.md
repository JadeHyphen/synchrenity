# Synchrenity Logging

> Modern, extensible logging for Synchrenity: file, stdout, JSON, rotation, context, and channels.

---

## 🗂️ Overview
- Logs are stored in `storage/logs/` by default, one file per channel per day.
- Supports log levels: debug, info, notice, warning, error, critical, alert, emergency.
- JSON or plain text format, with context and PID.
- Can log to file, stdout, or both.
- Fully integrated with SynchrenityCore and all modules.

---

## 🚀 Usage

### Basic Logging
```php
use Synchrenity\Logging\SynchrenityLogger;
$logger = new SynchrenityLogger([
    'log_dir' => __DIR__.'/../storage/logs',
    'channel' => 'app',
    'level' => 'info',
    'json' => true,
    'stdout' => false
]);
$logger->info('User logged in', ['user_id' => 123]);
$logger->error('Something failed', ['exception' => $e]);
```

### Integrate with SynchrenityCore
```php
$core->setLogger($logger);
$core->log('info', 'App started');
```

### Log from any module
```php
$core->logger->warning('Cache miss', ['key' => $key]);
```

---

## 🔄 Advanced Features
- Set global context: `$logger->setContext(['request_id' => $rid]);`
- Log rotation: one file per channel per day
- Log to stdout for Docker/cloud: set `stdout` to true
- Use custom channels: e.g. `auth`, `db`, `api`
- JSON logs for observability/ELK

---

## 📂 Log File Structure
- `storage/logs/app-YYYY-MM-DD.log`
- `storage/logs/auth-YYYY-MM-DD.log`
- ...

---

## 🛠️ Configuration Options
- `log_dir`: Directory for log files
- `channel`: Log channel (e.g. app, auth, db)
- `level`: Minimum log level to record
- `json`: Output logs as JSON (default: true)
- `stdout`: Also log to stdout (default: false)

---

## 🔗 See Also
- [Observability & Monitoring](MONITORING.md)
- [API Reference](API.md)
