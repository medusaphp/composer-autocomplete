# Composer autocomplete
`Medusa coco` provides autocomplete functionality for Composer commands, their options, and the package names in Cli.

### Prerequisite

Composer has to be globally installed before installing Medusa coco. Please refer to https://getcomposer.org for the installation process.

### Quick installation

```bash 

wget https://raw.githubusercontent.com/medusaphp/composer-autocomplete/main/composer_installer.phar
chmod +x composer_installer.phar
./composer_installer.phar
source ~/.bashrc
```

It is also possible to generate `composer_installer.phar` file manually by cloning the repository:
```bash 

git clone https://github.com/medusaphp/composer-autocomplete.git
cd composer-autocomplete
composer install
php coco.php --create-installer
./composer_installer.phar
source ~/.bashrc
```

#### Features

--- 
*Following features are only usable if a composer json was
found in the current or in one of the parent directories.*
---

* Script autocompletion (https://getcomposer.org/doc/articles/scripts.md#executing-php-scripts)
```shell
composer my-script
composer run my-script
```
