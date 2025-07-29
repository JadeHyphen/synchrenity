
# Synchrenity Audit Trail

> Tamper-proof, centralized audit logging for compliance, security, and observability.

---

## 📝 Logging Actions

```php
$core->audit()->log('user.login', ['ip' => $_SERVER['REMOTE_ADDR']], $userId, ['meta' => 'extra']);
$core->audit()->log('data.export', ['table' => 'users'], $adminId);
```

---

## 🔍 Querying & Filtering Logs

```php
$logs = $core->audit()->getLogs(100, ['action' => 'user.login']);
foreach ($logs as $log) {
    // Display or export
}
```

---

## 🛡️ Security & Compliance

- File/DB logging with encryption
- Tamper detection and anomaly alerts
- Multi-tenancy, geo/IP, RBAC
- Retention, export, and rotation
- Compliance dashboards and reports

---

## 📊 Example: Audit Dashboard

```php
$dashboard = $core->audit()->dashboard();
echo $dashboard->render();
```

---

## 🔗 See Also

- [Monitoring & Logging](MONITORING.md)
- [Usage Guide](USAGE_GUIDE.md)
