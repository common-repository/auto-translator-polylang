<?php

// autoload_real.php @generated by Composer

class ComposerAutoloaderInit49a0b17f2c02313ad58b84fbe7802a8a {
	private static $loader;

	public static function loadClassLoader($class) {
		if('Composer\Autoload\ClassLoader' === $class) {
			require __DIR__ . '/ClassLoader.php';
		}
	}

	/**
	 * @return \Composer\Autoload\ClassLoader
	 */
	public static function getLoader() {
		if(null !== self::$loader) {
			return self::$loader;
		}

		spl_autoload_register(['ComposerAutoloaderInit49a0b17f2c02313ad58b84fbe7802a8a', 'loadClassLoader'], true, true);
		self::$loader = $loader = new \Composer\Autoload\ClassLoader(\dirname(__DIR__));
		spl_autoload_unregister(['ComposerAutoloaderInit49a0b17f2c02313ad58b84fbe7802a8a', 'loadClassLoader']);

		require __DIR__ . '/autoload_static.php';
		call_user_func(\Composer\Autoload\ComposerStaticInit49a0b17f2c02313ad58b84fbe7802a8a::getInitializer($loader));

		$loader->register(true);

		return $loader;
	}
}
