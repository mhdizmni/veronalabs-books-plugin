<?php

namespace BooksPlugin;

class Database
{
    /**
     * Return table name by key.
     */
    public static function tables(): array
    {
        global $wpdb;

        $tables = [
            'books_info' => [
                'name'          => $wpdb->prefix . 'books_info',
                'query'         => '
						id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                        post_id bigint(20) unsigned NOT NULL,
                        isbn varchar(13) NOT NULL,
                        PRIMARY KEY (id)
					',
            ],
        ];

        return apply_filters( 'book_tables', $tables );
    }

    /**
     * Create all tables on activation.
     */
    public static function create_tables(): void
    {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        foreach ( self::tables() as $table ) {
            $table_name  = $table['name'];
            $table_query = $table['query'];

            if ( $table_name === $wpdb->get_var( "SHOW TABLES LIKE '{$table_name}'" ) ) {
                continue;
            }

            $sql = "CREATE TABLE $table_name (
					$table_query
				) $charset_collate;";

            require_once ABSPATH . 'wp-admin/includes/upgrade.php';

            dbDelta( $sql );
        }
    }
}