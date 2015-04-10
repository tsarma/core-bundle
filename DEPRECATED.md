Deprecated features
===================

### Using `$this` in configuration files

Using `$this` in configuration files such as `config/config.php` or `dca/*.php`
has been deprecated in Contao 4.0 and will no longer work in Contao 5.0.

You can use the static helper methods such as `System::loadLanguageFile()` or
`Controller::loadDataContainer()` instead.


### Constants

The constants `TL_ROOT`, `TL_MODE`, `TL_START` and `TL_SCRIPT` have been
deprecated and will be removed in Contao 5.0.

You can use the `kernel.root_dir` instead of `TL_ROOT`:

```php
global $kernel;

$rootDir = dirname($kernel->getContainer()->getParameter('kernel.root_dir'));
```

You can check the container scope instead of using `TL_MODE`:

```php
global $kernel;

$isBackEnd  = $kernel->getContainer()->isScopeActive('backend');
$isFrontEnd = $kernel->getContainer()->isScopeActive('frontend');
```

You can use the kernel start time instead of `TL_START:

```php
global $kernel;

$startTime = $kernel->getStartTime();
```

You can use the request stack to get the route instead of using `TL_SCRIPT`:

```php
global $kernel;

$route = $kernel->getContainer()->get('request_stack')->getCurrentRequest()->get('_route');

if ('contao_backend_main' === $route) {
    // Do something
}
```

Type `$ ./app/console router:debug` on the console to see all available routes.


### PHP entry points

Contao 4 only uses a single PHP entry point, namely the `app.php` or
`app_dev.php` file. The previous PHP entry points have been removed and a route
has been set up for each one instead.

Using the old paths is deprecated and will no longer work in Contao 5.0.


### `ModuleLoader`

The `ModuleLoader` class is no longer used and only kept for reasons of
backwards compatibility. It is deprecated and will be removed in Contao 5.0.
Use the container parameter `kernel.bundles` instead:

```php
global $kernel;

$bundles = $kernel->getContainer()->getParameter('kernel.bundles');
```


### `database.sql` files

Using `database.sql` files to set up tables is deprecated in Contao 4.0 and
will no longer be supported in Contao 5.0. Use DCA files instead:

```php
$GLOBALS['TL_DCA']['tl_example'] = array
(
	'config' => array
	(
		'sql' => array
		(
			'keys' => array
			(
				'id' => 'primary',
				'name' => 'unique'
			)
		)
	),
	'fields' => array
	(
		'id' => array
		(
			'sql'                     => "int(10) unsigned NOT NULL auto_increment"
		),
		'name' => array
		(
			'sql'                     => "varchar(32) NULL"
		),
		'value' => array
		(
			'sql'                     => "varchar(32) NOT NULL default ''"
		)
	)
);

```