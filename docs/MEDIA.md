
# Synchrenity Media Manager

> Secure uploads, metadata extraction, hooks, audit logging, and extensibility.

---

## 📤 Uploading Files

```php
$media = $core->media;
$media->upload($file);
```

---

## 📝 Metadata Extraction

```php
$meta = $media->getMetadata($fileId);
```

---

## 🔄 Hooks & Events

```php
$media->on('media.uploaded', function($meta) {
    // Log or process
});
```

---

## 🧑‍💻 Example: Custom Storage Backend

```php
$media->setStorageBackend(new MyCustomStorage());
```

---

## 🔗 See Also

- [Audit Trail](AUDIT.md)
- [Usage Guide](USAGE_GUIDE.md)
