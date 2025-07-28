# Synchrenity Roadmap

This document tracks planned and in-progress features for the Synchrenity PHP Framework. All features will follow Synchrenity naming conventions for discoverability and Composer autoloading.

## 1. Forge Templating Engine Improvements
- **Support for `{% ... %}` Tags**: Add parsing/execution for `{% set ... %}`, `{% if ... %}`, `{% for ... %}`, `{% include ... %}`, `{% layout ... %}`.
- **Custom Filters**: Register and use filters like `|escape`, `|upper`, `|lower`, etc.
- **Component/Partial Support**: Enable reusable template components/partials.
- **Better Error Reporting**: Show template line numbers and context in errors.

## 2. Internationalization (i18n) & Localization
- **Dynamic Language Switching**: Helpers to switch language at runtime and reload strings.
- **Pluralization & Formatting**: Support plural forms, date, and number formatting per locale.

## 3. Security & Usability
- **Automatic Output Escaping**: All `{{ ... }}` output is HTML-escaped by default, with raw output option.
- **CSRF Protection**: Easy CSRF token generation and validation for forms.
- **Session Management**: Hardened session handling, flash messages, and support for file/Redis/DB backends.

## 4. Routing & Middleware
- **Flexible Routing**: Route groups, named routes, and route parameters with type validation.
- **Middleware Support**: Attach middleware (auth, logging, etc.) to routes.

## 5. Dependency Injection & Service Container
- **Service Container**: Simple DI container for managing app services, config, and singletons.

## 6. Developer Experience
- **Hot Reload for Templates**: Auto-reload templates on change in dev mode.
- **CLI Tools**: CLI commands for scaffolding modules, controllers, migrations, etc.
- **Better Error Pages**: Pretty error/debug pages in development.

## 7. Testing & Extensibility
- **Test Utilities**: Helpers for HTTP/request/response testing, and template rendering assertions.
- **Plugin/Module System**: Easy registration and discovery of modules/plugins.

## 8. Documentation & Examples
- **Comprehensive Docs**: Document all features, with code samples and best practices.
- **Starter Templates**: Example modules, admin panels, and user flows.

---

**All new classes, helpers, and services will use the `Synchrenity` prefix and be placed in the appropriate PSR-4 namespace for Composer autoloading.**
