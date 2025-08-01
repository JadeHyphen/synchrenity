# Synchrenity Global Installer

The Synchrenity global installer allows you to easily create new Synchrenity applications from anywhere on your system using the `synchrenity new` command.

## Installation

### Install Globally via Composer

```bash
composer global require synchrenity/installer
```

Make sure your global composer bin directory is in your PATH. Add this to your shell profile (`.bashrc`, `.zshrc`, etc.):

```bash
export PATH="$PATH:$HOME/.composer/vendor/bin"
```

Or on some systems:

```bash
export PATH="$PATH:$HOME/.config/composer/vendor/bin"
```

### Manual Installation

1. Clone this repository:
```bash
git clone https://github.com/JadeHyphen/synchrenity.git
cd synchrenity/installer
```

2. Install dependencies:
```bash
composer install
```

3. Link the binary globally:
```bash
sudo ln -s $(pwd)/bin/synchrenity /usr/local/bin/synchrenity
```

## Usage

### Create a New Application

```bash
synchrenity new my-app
```

This will:
- Create a new directory called `my-app`
- Copy all necessary Synchrenity framework files
- Generate a new `composer.json` for your project
- Create a `.env` file with secure defaults
- Set up the proper directory structure

### Options

- `--force` or `-f`: Overwrite the directory if it already exists
- `--dev`: Install the latest development version

### Example

```bash
# Create a new application
synchrenity new blog

# Navigate to the new application
cd blog

# Install dependencies
composer install

# Run migrations
php synchrenity migrate

# Seed the database
php synchrenity seed

# Start the development server
php -S localhost:8000 -t public
```

## What Gets Created

When you run `synchrenity new my-app`, the following structure is created:

```
my-app/
├── bootstrap/          # Framework bootstrap files
├── config/            # Configuration files
├── database/          # Migrations and seeders
├── lib/              # Framework source code
├── tests/            # Test files
├── docs/             # Documentation
├── .env              # Environment configuration
├── .env.example      # Example environment file
├── .gitignore        # Git ignore rules
├── composer.json     # Composer configuration
├── synchrenity       # CLI tool
└── README.md         # Project documentation
```

## Requirements

- PHP 8.2 or higher
- Composer
- Git (for downloading the framework)

## Troubleshooting

### Command not found

If you get "command not found" after installing globally, make sure your composer global bin directory is in your PATH.

### Permission denied

If you get permission errors, make sure the synchrenity binary is executable:

```bash
chmod +x ~/.composer/vendor/bin/synchrenity
```

### Git not found

The installer requires Git to download the framework. Install Git from [https://git-scm.com/](https://git-scm.com/).

## Development

To work on the installer:

1. Clone the repository
2. Navigate to the `installer` directory
3. Install dependencies: `composer install`
4. Test locally: `./bin/synchrenity new test-app`