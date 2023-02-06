# Laravel migrations Jet tools


Migrations Jet tool is intended to be a very helpful plugin when dealing with migrations of your Laravel project. Many times it becomes a tedious job, especially in development moments when you have to test many things, launching and relaunching tables in the database can be annoying.
With these tools the idea is to make the task of the developer a little easier.

## Install

```console
composer require fragale/migratool
```

## Use

### Migrations list

To see a list of the migrations that have been run in the database, execute:
```console
php artisan migratool:jet --list
```
You will see a list with the following information:
* migration #id
* batch #id
* Migration file name
* Class name

### Down a migration

For example, to run down migration #id 15, you can run this command:

```console
php artisan migratool:jet --down=15
```

### Find a migration

To find one or more migrations that match an expression, for example *user* you can run the following command:

```console
php artisan migratool:jet --find=user
```

if any of the migration filenames match the expression *user* then you will see a list of all matching migrations.

### Purge (mass down)

If you want to do a massive down of one or several migrations you can use the *--purge* modifier in combination with the *--find* modifier for example:

```console
php artisan migratool:jet --find=user --purge   
```

You will see a detail of the migrations that will be purged from the database, if you confirm the action they will be removed and you can migrate them again using *php artisan migrate* normally


