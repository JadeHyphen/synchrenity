# Creating New Synchrenity Applications

Synchrenity provides a convenient way to create new applications using the `synchrenity new` command, similar to Laravel's installer.

## Installation Methods

### Method 1: Use the Framework CLI (Local)

If you have a local copy of Synchrenity:

```bash
cd /path/to/synchrenity
php synchrenity new my-app
```

### Method 2: Global Installation (Recommended)

Install the Synchrenity installer globally via Composer:

```bash
composer global require synchrenity/installer
```

Make sure your global composer bin directory is in your PATH:

```bash
export PATH="$PATH:$HOME/.composer/vendor/bin"
```

Then create new applications from anywhere:

```bash
synchrenity new my-app
```

## Usage

### Basic Usage

```bash
synchrenity new project-name
```

This will create a new directory called `project-name` with a complete Synchrenity application.

### Options

- `--force` or `-f`: Overwrite the directory if it already exists

```bash
synchrenity new my-app --force
```

## What Gets Created

When you run `synchrenity new my-app`, the following structure is created:

```
my-app/
├── app/                   # Your application code
│   ├── Controllers/       # HTTP controllers
│   ├── Models/           # Data models
│   ├── Middleware/       # Custom middleware
│   └── Services/         # Business logic services
├── bootstrap/            # Framework bootstrap
│   └── app.php          # Application bootstrap
├── config/              # Configuration files
│   ├── app.php         # Application configuration
│   ├── config.php      # General configuration
│   ├── services.php    # Service bindings
│   ├── oauth2.php      # OAuth2 configuration
│   └── api_rate_limits.php # Rate limiting configuration
├── database/            # Database files
│   ├── migrations/     # Database migrations
│   ├── seeders/       # Database seeders
│   └── README.md      # Database documentation
├── lib/                # Framework source code
├── public/             # Web server document root
│   ├── index.php      # Entry point
│   └── .htaccess      # Apache configuration
├── storage/            # Application storage
│   ├── logs/          # Log files
│   ├── cache/         # Cache files
│   └── sessions/      # Session files
├── tests/              # Test files
├── docs/               # Documentation
├── .env                # Environment configuration
├── .env.example        # Example environment file
├── .gitignore          # Git ignore rules
├── composer.json       # Composer configuration
├── synchrenity         # CLI tool
├── phpunit.xml         # PHPUnit configuration
├── Dockerfile          # Docker configuration
├── docker-compose.yml  # Docker Compose configuration
├── LICENSE             # License file
└── README.md           # Project documentation
```

## Next Steps

After creating your application:

1. **Navigate to the project directory:**
   ```bash
   cd my-app
   ```

2. **Install dependencies:**
   ```bash
   composer install
   ```

3. **Configure your environment:**
   Edit the `.env` file to set your database and other configuration options.

4. **Run migrations:**
   ```bash
   php synchrenity migrate
   ```

5. **Seed the database (optional):**
   ```bash
   php synchrenity seed
   ```

6. **Start the development server:**
   ```bash
   php -S localhost:8000 -t public
   ```

Your new Synchrenity application will be available at `http://localhost:8000`.

## Environment Configuration

The generated `.env` file includes secure defaults:

- **APP_KEY**: A randomly generated encryption key
- **APP_ENV**: Set to `development` by default
- **APP_DEBUG**: Enabled by default for development
- **Database**: Configured to use SQLite by default
- **Feature flags**: Advanced features enabled by default

## Development Workflow

Once your application is created, you can use the included CLI tools:

```bash
# Create a new controller
php synchrenity make:controller UserController

# Create a new migration
php synchrenity make:migration create_users_table

# Run migrations
php synchrenity migrate

# View migration status
php synchrenity migration:status

# Optimize the application
php synchrenity optimize
```

## Customization

### Customizing the Template

You can customize what gets created by modifying the `SynchrenityNewCommand` class in `lib/Commands/SynchrenityNewCommand.php`.

### Adding Custom Scaffolding

You can create additional scaffolding commands by extending the `SynchrenityCommand` class and adding them to the `lib/Commands/` directory.

## Troubleshooting

### Permission Issues

If you encounter permission issues:

```bash
chmod +x synchrenity
chmod -R 755 storage/
```

### Missing Dependencies

If composer dependencies are missing:

```bash
composer install
```

### Database Issues

If you encounter database issues, check your `.env` file configuration and ensure your database server is running.

## Global Installation Troubleshooting

### Command Not Found

If `synchrenity new` command is not found after global installation:

1. Check if composer global bin directory is in your PATH
2. Verify the installation: `composer global show synchrenity/installer`
3. Add the composer bin directory to your PATH in your shell profile

### Download Issues

If the installer can't download the framework:

1. Ensure you have Git installed
2. Check your internet connection
3. Verify you have access to the GitHub repository