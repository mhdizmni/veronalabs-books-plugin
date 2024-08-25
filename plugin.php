<?php
/**
 * Plugin Name:     Mahdi Zamani's Books Plugin
 * Plugin URI:      https://github.com/mhdizmni/veronalabs-books-plugin
 * Plugin Prefix:   BOOKS_PLUGIN
 * Description:     Mahdi Zamani's Assignment!
 * Author:          Mahdi Zamani
 * Author URI:      https://github.com/mhdizmni/
 * Text Domain:     books-plugin
 * Domain Path:     /languages
 * Version:         1.0
 */

use BooksPlugin\PostTypes;
use BooksPlugin\DataTable;
use BooksPlugin\Database;
use Rabbit\Application;
use Rabbit\Database\DatabaseServiceProvider;
use Rabbit\Logger\LoggerServiceProvider;
use Rabbit\Plugin;
use Rabbit\Redirects\AdminNotice;
use Rabbit\Redirects\RedirectServiceProvider;
use Rabbit\Templates\TemplatesServiceProvider;
use Rabbit\Utils\Singleton;
use League\Container\Container;

if (file_exists(dirname(__FILE__) . '/vendor/autoload.php')) {
    require dirname(__FILE__) . '/vendor/autoload.php';
}

/**
 * Class BooksPluginInit
 * @package BooksPluginInit
 */
class BooksPluginInit extends Singleton
{
    /**
     * @var Container
     */
    private $application;

    /**
     * BooksPluginInit constructor.
     */
    public function __construct()
    {
        $this->application = Application::get()->loadPlugin(__DIR__, __FILE__, 'config');
        $this->init();
    }

    public function init(): void
    {
        try {

            /**
             * Load service providers
             */
            $this->application->addServiceProvider(RedirectServiceProvider::class);
            $this->application->addServiceProvider(DatabaseServiceProvider::class);
            $this->application->addServiceProvider(TemplatesServiceProvider::class);
            $this->application->addServiceProvider(LoggerServiceProvider::class);
            // Load your own service providers here...

            /**
             * Activation hooks
             */
            $this->application->onActivation(function () {
                Database::create_tables();
            });

            /**
             * Deactivation hooks
             */
            $this->application->onDeactivation(function () {
                // Clear events, cache or something else
            });

            $this->application->boot(function (Plugin $plugin) {
                $plugin->loadPluginTextDomain();

                // load template
                $this->application->template('plugin-template.php', ['foo' => 'bar']);

                new PostTypes();
                DataTable::renderTable();

                add_filter('manage_edit-book_columns', 'books_plugin_edit_book_columns');
                function books_plugin_edit_book_columns($columns)
                {
                    // Remove the 'author' column in books page (not books-list page)
                    unset($columns['author']);

                    return $columns;
                }


            });

        } catch (Exception $e) {
            /**
             * Print the exception message to admin notice area
             */
            add_action('admin_notices', function () use ($e) {
                AdminNotice::permanent(['type' => 'error', 'message' => $e->getMessage()]);
            });

            /**
             * Log the exception to file
             */
            add_action('init', function () use ($e) {
                if ($this->application->has('logger')) {
                    $this->application->get('logger')->warning($e->getMessage());
                }
            });
        }
    }

    /**
     * @return Container
     */
    public function getApplication()
    {
        return $this->application;
    }
}

/**
 * Returns the main instance of ExamplePluginInit.
 *
 * @return Singleton
 */
function bookInfoPlugin(): Singleton
{
    return BooksPluginInit::get();
}

bookInfoPlugin();